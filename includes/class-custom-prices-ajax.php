<?php
/**
 * Класс для обработки AJAX-запросов фронтенда.
 *
 * Обработчики:
 *  add_to_cart_custom     — добавление товара в корзину с custom_data
 *  custom_get_cart_total  — получение текущей суммы корзины (для обновления «Итого»)
 *  get_product_popup      — загрузка HTML попапа товара
 *
 * Все обработчики доступны и для авторизованных, и для анонимных пользователей.
 * Каждый обработчик проверяет nonce перед выполнением.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Custom_Prices_Ajax {
    public function __construct() {
        // Добавление товара в корзину (доступно без авторизации)
        add_action('wp_ajax_add_to_cart_custom', [$this, 'add_to_cart_custom']);
        add_action('wp_ajax_nopriv_add_to_cart_custom', [$this, 'add_to_cart_custom']);

        // Получение текущего итога корзины (для обновления блока «Итого» после событий WC)
        add_action('wp_ajax_custom_get_cart_total', [$this, 'custom_get_cart_total']);
        add_action('wp_ajax_nopriv_custom_get_cart_total', [$this, 'custom_get_cart_total']);

        // Загрузка HTML попапа товара на листинге
        add_action('wp_ajax_get_product_popup', [$this, 'get_product_popup']);
        add_action('wp_ajax_nopriv_get_product_popup', [$this, 'get_product_popup']);
    }

    /**
     * Возвращает текущую сумму корзины в отформатированном виде (wc_price).
     * Вызывается JS после событий added_to_cart, removed_from_cart и т.д.
     * Использует nonce woocommerce-cart (стандартный WC nonce).
     */
    public function custom_get_cart_total() {
        check_ajax_referer('woocommerce-cart', 'security');

        $total = 0;
        foreach (WC()->cart->get_cart() as $cart_item) {
            $total += $this->get_custom_subtotal($cart_item); // Метод из Custom_Prices_Cart, но чтобы избежать зависимости, дублируем логику или вызываем filter
        }
        echo wc_price($total);
        wp_die();
    }

    /**
     * Расчёт итога одного товара в корзине (дубликат из Custom_Prices_Cart).
     * Дублирование намеренное: позволяет классу работать автономно без зависимости от Cart.
     * Формулы: см. Custom_Prices_Cart::get_custom_subtotal().
     */
    private function get_custom_subtotal($cart_item) {
        $product_id = $cart_item['product_id'];
        $type = get_post_meta($product_id, '_custom_price_type', true);
        $custom_data = !empty($cart_item['custom_data']) ? $cart_item['custom_data'] : [];
        $qty = $cart_item['quantity'];
        $total = 0;

        if ($type == 'opt') {
            $price = !empty($custom_data['is_opt']) ? (float) get_post_meta($product_id, '_price_opt', true) : (float) $cart_item['data']->get_price();
            $total = $price * $qty;
        } elseif ($type == 'otrez') {
            $width = !empty($custom_data['width_value']) ? (float) $custom_data['width_value'] : 0;
            if (!empty($custom_data['is_otrez']) && $width > 0) {
                $price = (float) get_post_meta($product_id, '_price_otrez', true);
                $total = $qty * $width * $price;
            } else {
                $price = (float) $cart_item['data']->get_price();
                $length = !empty($custom_data['length']) ? (float) $custom_data['length'] : 0;
                $area = $qty * $width * $length;
                $total = $area * $price;
            }
        } else {
            $total = (float) wc_get_price_to_display($cart_item['data'], ['qty' => $qty]);
        }

        return $total;
    }

    /**
     * Загружает HTML попапа товара для листинга.
     * Вызывается JS при клике на кнопку «Купить».
     * Делегирует генерацию HTML классу Custom_Prices_Frontend::get_popup_html().
     * Возвращает JSON: { success: true, data: { html: '...' } }
     */
    public function get_product_popup() {
        check_ajax_referer('custom_prices_nonce', 'nonce');

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        if (!$product_id) {
            wp_send_json_error(['message' => __('Неверный ID товара', 'custom-prices-woocommerce')]);
        }

        $frontend = new Custom_Prices_Frontend();
        $html = $frontend->get_popup_html($product_id);

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Добавляет товар в корзину с custom_data.
     *
     * Получает из POST: product_id, qty из 4 полей (qty_rrc, qty_opt, qty_otrez, qty_rulon),
     * ширину (width/width_value), длину (length), единицу измерения (unit).
     *
     * Логика формирования custom_data:
     *  opt  + qty_rrc > 0  → is_opt = false (РРЦ)
     *  opt  + qty_opt > 0  → is_opt = true  (ОПТ)
     *  otrez + qty_otrez > 0 → is_otrez = true  (отрез, м.п.)
     *  otrez + qty_rulon > 0 → is_otrez = false (рулон)
     *
     * custom_data сохраняется в сессии WC и используется для расчётов в корзине.
     * Возвращает JSON с fragments и cart_hash для AJAX-обновления корзины.
     */
    public function add_to_cart_custom() {
        check_ajax_referer('custom_prices_nonce', 'nonce');

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $qty_rrc = isset($_POST['qty_rrc']) ? floatval($_POST['qty_rrc']) : 0;
        $qty_opt = isset($_POST['qty_opt']) ? floatval($_POST['qty_opt']) : 0;
        $qty_otrez = isset($_POST['qty_otrez']) ? floatval($_POST['qty_otrez']) : 0;
        $qty_rulon = isset($_POST['qty_rulon']) ? floatval($_POST['qty_rulon']) : 0;
        $width = isset($_POST['width']) ? sanitize_text_field($_POST['width']) : '';
        $width_value = isset($_POST['width_value']) ? floatval($_POST['width_value']) : 0;
        $length = isset($_POST['length']) ? floatval($_POST['length']) : 0;
        $unit = isset($_POST['unit']) ? sanitize_text_field($_POST['unit']) : '';

        error_log("Custom Prices: Ajax add_to_cart - Product ID: $product_id, qty_rrc: $qty_rrc, qty_opt: $qty_opt, qty_otrez: $qty_otrez, qty_rulon: $qty_rulon, width: $width, width_value: $width_value, length: $length, unit: $unit");

        if (!$product_id) {
            wp_send_json_error(['message' => __('Неверный ID товара', 'custom-prices-woocommerce')]);
        }

        $type = get_post_meta($product_id, '_custom_price_type', true);
        $custom_data = [];

        error_log("Custom Prices: Product meta - _custom_price_type: $type, _price_otrez: " . get_post_meta($product_id, '_price_otrez', true));

        if ($type == 'opt') {
            // Определяем, из какой вкладки (РРЦ или ОПТ) пришёл запрос
            if ($qty_rrc > 0) {
                $custom_data['is_opt'] = false; // РРЦ — стандартная цена
                $quantity = $qty_rrc;
            } elseif ($qty_opt > 0) {
                $custom_data['is_opt'] = true;  // ОПТ — оптовая цена
                $quantity = $qty_opt;
            } else {
                wp_send_json_error(['message' => __('Укажите количество', 'custom-prices-woocommerce')]);
            }
            $custom_data['unit'] = $unit ?: get_post_meta($product_id, '_units', true) ?: 'ед.';
        } elseif ($type == 'otrez') {
            if ($qty_otrez > 0 && $width) {
                // Отрез: количество метров + ширина рулона
                $custom_data['is_otrez'] = true;
                $custom_data['width'] = $width;           // текст ширины (для отображения)
                $custom_data['width_value'] = $width_value; // числовое значение (для расчёта)
                $custom_data['unit'] = 'м.п.';
                $quantity = $qty_otrez;
            } elseif ($qty_rulon > 0 && $width) {
                // Рулон: количество рулонов + ширина + длина
                $custom_data['is_otrez'] = false;
                $custom_data['width'] = $width;
                $custom_data['width_value'] = $width_value;
                $custom_data['length'] = $length;          // длина рулона (из атрибута товара)
                $custom_data['unit'] = 'рулон';
                $quantity = $qty_rulon;
            } else {
                wp_send_json_error(['message' => __('Укажите количество и ширину', 'custom-prices-woocommerce')]);
            }
        } else {
            // Стандартный товар — берём первое ненулевое количество
            $quantity = $qty_rrc ?: $qty_opt ?: $qty_otrez ?: $qty_rulon;
            if ($quantity <= 0) {
                wp_send_json_error(['message' => __('Укажите количество', 'custom-prices-woocommerce')]);
            }
            $custom_data['unit'] = $unit ?: '-';
        }

        error_log("Custom Prices: Adding to cart - Product ID: $product_id, Quantity: $quantity, Custom Data: " . print_r($custom_data, true));

        // Добавляем товар в WC-корзину. custom_data передаётся через 5-й параметр
        // и сохраняется в сессии (восстанавливается через get_cart_item_from_session)
        $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, 0, [], ['custom_data' => $custom_data]);

        if ($cart_item_key) {
            error_log("Custom Prices: Successfully added to cart - Cart item key: $cart_item_key");
            $fragments = [
                'div.cart-total-custom' => '<div class="cart-total-custom"><h2>' . __('Итого', 'custom-prices-woocommerce') . ': <span class="total-amount">' . WC()->cart->get_cart_subtotal() . '</span></h2></div>'
            ];
            wp_send_json_success(['fragments' => $fragments, 'cart_hash' => WC()->cart->get_cart_hash()]);
        } else {
            error_log("Custom Prices: Failed to add to cart - Product ID: $product_id");
            wp_send_json_error(['message' => __('Ошибка добавления в корзину', 'custom-prices-woocommerce')]);
        }
    }
}