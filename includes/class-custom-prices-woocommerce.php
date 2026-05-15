<?php
/**
 * Основной класс плагина, реализованный как Singleton.
 * Координирует инициализацию всех компонентов, подключение assets,
 * переопределение шаблонов WooCommerce, сохранение custom_data в заказах.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Custom_Prices_Woocommerce {
    private static $instance = null;

    private function __construct() {
        // Создаём экземпляры всех компонентов — каждый регистрирует свои хуки в конструкторе
        new Custom_Prices_Admin();       // Поля товара в WC-админке
        new Custom_Prices_Frontend();    // Цены и формы на фронтенде
        new Custom_Prices_Cart();        // Модификация корзины
        new Custom_Prices_Ajax();        // AJAX-обработчики
        new Custom_Prices_Settings();    // Страница глобальных настроек

        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Подставляем собственные шаблоны вместо стандартных WC (приоритет 100 > умолчания WC)
        add_filter('woocommerce_locate_template', [$this, 'locate_template'], 100, 3);

        // WC 9+: корзина и checkout по умолчанию рендерятся через React Blocks,
        // что полностью обходит wc_get_template() и наш locate_template.
        // Одноразовая конверсия блоков в шорткоды при первом запуске.
        add_action('init', [$this, 'ensure_classic_cart_and_checkout'], 999);

        // При создании заказа сохраняем custom_data в meta order item и устанавливаем пересчитанный subtotal
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_custom_data_to_order_item'], 10, 4);

        // Убираем стандартную строку «Промежуточный итог» из таблицы итогов заказа
        add_filter('woocommerce_get_order_item_totals', [$this, 'remove_subtotal_from_order_totals'], 10, 3);

        // Для товаров типа 'otrez' добавляем к названию товара ширину и режим (отрез/рулон)
        // Виден на checkout, order-received и в письмах
        add_filter('woocommerce_order_item_name', [$this, 'custom_order_item_name'], 10, 3);
    }

    /**
     * Получение единственного экземпляра (Singleton).
     */
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new Custom_Prices_Woocommerce();
        }
        return self::$instance;
    }

    /**
     * Подключение JS и CSS на нужных страницах:
     * - товар, магазин, категория, тег, корзина.
     * Подключаем: кастомный JS/CSS, Magnific Popup, pdfMake (для PDF КП).
     * Локализуем глобальные переменные (ajax_url, nonce) для JS.
     */
    public function enqueue_assets() {
        if (is_product() || is_shop() || is_product_category() || is_product_tag() || is_cart()) {
            // jQuery UI Tabs — для переключения РРЦ/ОПТ и Рулон/Отрез
            wp_enqueue_script('jquery-ui-tabs');
            wp_enqueue_script('custom-prices-js', plugin_dir_url(__DIR__) . 'assets/js/custom-prices.js', ['jquery', 'jquery-ui-tabs'], '1.0.9', true);
            wp_enqueue_style('custom-prices-css', plugin_dir_url(__DIR__) . 'assets/css/custom-prices.css', [], '1.0.9');

            // Magnific Popup — модальный попап товара на листинге и попап для PDF
            wp_enqueue_style('magnific-popup', plugin_dir_url(__DIR__) . 'assets/css/magnific-popup.css', [], '1.1.0');
            wp_enqueue_script('magnific-popup', plugin_dir_url(__DIR__) . 'assets/js/jquery.magnific-popup.min.js', ['jquery'], '1.1.0', true);

            // pdfMake — генерация PDF коммерческого предложения на клиентской стороне
            wp_enqueue_script('pdfmake', plugin_dir_url(__FILE__) . '../assets/js/pdfmake/pdfmake.min.js', ['jquery'], '1.17', true);
            wp_enqueue_script('vfs_fonts', plugin_dir_url(__FILE__) . '../assets/js/pdfmake/vfs_fonts.js', ['jquery'], '1.17', true);

            // Глобальные переменные для JS: ajax_url и nonce (не зависят от конкретного товара)
            wp_localize_script('custom-prices-js', 'custom_prices_global', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('custom_prices_nonce')
            ]);
        }
    }

    public function enqueue_admin_assets($hook) {
        // Зарезервировано для admin-specific JS/CSS при необходимости
    }

    /**
     * Переопределение шаблонов WooCommerce.
     * WC ищет шаблоны в теме, затем в плагинах. Этот фильтр подставляет
     * наши шаблоны с доп. колонками (Ед.изм, Объём/Площадь).
     * Переопределённые шаблоны: cart, checkout review-order, order details.
     */
    public function locate_template($template, $template_name, $template_path) {
        // Список шаблонов, которые плагин переопределяет
        $plugin_templates = [
            'cart/cart.php',
            'checkout/review-order.php',
            'order/order-details.php',
            'order/order-details-item.php',
        ];

        if (in_array($template_name, $plugin_templates)) {
            $plugin_template = plugin_dir_path(__DIR__) . 'templates/woocommerce/' . $template_name;
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }

        return $template;
    }

    /**
     * Сохранение custom_data в order item при создании заказа.
     * Также пересчитывает и устанавливает subtotal и total для строки заказа,
     * так как стандартный WC-расчёт не учитывает ширину/длину/тип.
     */
    public function save_custom_data_to_order_item($item, $cart_item_key, $values, $order) {
        if (isset($values['custom_data'])) {
            // Сохраняем массив custom_data как meta — доступно в деталях заказа и emails
            $item->update_meta_data('_custom_data', $values['custom_data']);

            // Пересчитываем итог для этого товара по нашей формуле
            $custom_subtotal = $this->get_custom_subtotal($values);
            $item->set_subtotal($custom_subtotal);
            $item->set_total($custom_subtotal); // total = subtotal, если нет скидок на строку
        }
    }

    /**
     * Расчёт итоговой стоимости одного товара в корзине.
     *
     * opt/РРЦ:   total = price × qty
     * opt/ОПТ:   total = price_opt × qty
     * otrez/отрез: total = qty × width × price_otrez  (м.п.)
     * otrez/рулон: total = qty × width × length × price (м²)
     * standard:  стандартная логика WC
     *
     * Дублируется в Custom_Prices_Cart и Custom_Prices_Ajax для автономной работы классов.
     */
    private function get_custom_subtotal($cart_item) {
        $product_id = $cart_item['product_id'];
        $type = get_post_meta($product_id, '_custom_price_type', true);
        $custom_data = !empty($cart_item['custom_data']) ? $cart_item['custom_data'] : [];
        $qty = $cart_item['quantity'];
        $total = 0;

        if ($type == 'opt') {
            // is_opt = true означает вкладка ОПТ, false — РРЦ
            $price = !empty($custom_data['is_opt']) ? (float) get_post_meta($product_id, '_price_opt', true) : (float) $cart_item['data']->get_price();
            $total = $price * $qty;
        } elseif ($type == 'otrez') {
            $width = !empty($custom_data['width_value']) ? (float) $custom_data['width_value'] : 0;
            if (!empty($custom_data['is_otrez']) && $width > 0) {
                // Отрез: цена за м.п. × количество метров × ширина рулона
                $price = (float) get_post_meta($product_id, '_price_otrez', true);
                $total = $qty * $width * $price;
            } else {
                // Рулон: цена за м² × (кол-во рулонов × ширина × длина рулона)
                $price = (float) $cart_item['data']->get_price();
                $length = !empty($custom_data['length']) ? (float) $custom_data['length'] : 0;
                $area = $qty * $width * $length;
                $total = $area * $price;
            }
        } else {
            // Стандартный товар — используем стандартный WC-расчёт
            $total = (float) wc_get_price_to_display($cart_item['data'], ['qty' => $qty]);
        }

        return $total;
    }

    /**
     * Убираем строку «Промежуточный итог» (cart_subtotal) из итогов заказа.
     * Итоги для нашей корзины пересчитаны кастомно, стандартный subtotal некорректен.
     */
    public function remove_subtotal_from_order_totals($total_rows, $order, $tax_display) {
        unset($total_rows['cart_subtotal']);
        return $total_rows;
    }

    /**
     * Модифицируем название товара в заказе для типа 'otrez'.
     * Добавляем к названию ширину и режим: «Товар (на отрез, ширина: 1.5 м)».
     * Виден в checkout, order-received, деталях заказа и в письмах WooCommerce.
     */
    public function custom_order_item_name($item_name, $item, $is_visible) {
        $product_id = $item->get_product_id();
        $type = get_post_meta($product_id, '_custom_price_type', true);

        if ($type === 'otrez') {
            $custom_data = $item->get_meta('_custom_data', true);
            if ($custom_data) {
                $width = isset($custom_data['width']) ? $custom_data['width'] : '';
                // is_otrez = true → «на отрез» (м.п.), false → «в рулоне» (целый рулон)
                $mode = !empty($custom_data['is_otrez']) ? 'на отрез' : 'в рулоне';
                $item_name .= sprintf(' (%s, ширина: %s)', $mode, esc_html($width));
            }
        }

        return $item_name;
    }

    /**
     * WC 9+ рендерит корзину и checkout через React Blocks по умолчанию.
     * Блоки не вызывают wc_get_template(), поэтому наши шаблоны (cart.php,
     * review-order.php) никогда не подставляются.
     *
     * Два уровня проблемы:
     * 1) post_content страницы содержит <!-- wp:woocommerce/cart --> блок
     *    → заменяем на шорткод [woocommerce_cart]
     * 2) WC создаёт wp_template посты page-cart / page-checkout в БД —
     *    они перехватывают рендер страницы целиком, обходя post_content.
     *    → удаляем эти wp_template посты.
     *
     * После этого WP рендерит обычную страницу, do_shortcode обрабатывает
     * [woocommerce_cart], WC вызывает wc_get_template('cart/cart.php'),
     * и наш locate_template подставляет кастомный шаблон.
     */
    public function ensure_classic_cart_and_checkout() {
        // --- Часть 1: удаление wp_template page-cart / page-checkout ---
        // Не ставим one-time флаг, т.к. WC может пересоздать шаблоны при обновлении.
        // Проверка очень дешёва (один запрос к БД), фактическое удаление — редкость.
        $block_templates = get_posts([
            'post_type'      => 'wp_template',
            'posts_per_page' => -1,
            'post_status'    => ['publish', 'auto-draft', 'inherit'],
        ]);
        foreach ($block_templates as $tmpl) {
            if (in_array($tmpl->post_name, ['page-cart', 'page-checkout'])) {
                wp_delete_post($tmpl->ID, true);
            }
        }

        // --- Часть 2: замена блоков на шорткоды (один раз) ---
        if (get_option('_custom_prices_classic_pages_converted')) {
            return;
        }
        update_option('_custom_prices_classic_pages_converted', '1');

        $pages = [
            'cart'     => '[woocommerce_cart]',
            'checkout' => '[woocommerce_checkout]',
        ];

        foreach ($pages as $page_key => $shortcode) {
            $page_id = wc_get_page_id($page_key);
            if (!$page_id) {
                continue;
            }

            $post = get_post($page_id);
            if (!$post) {
                continue;
            }

            $block_name = 'woocommerce/' . $page_key;
            if (has_block($block_name, $post)) {
                wp_update_post([
                    'ID'           => $page_id,
                    'post_content' => $shortcode,
                ]);
            }
        }
    }
}