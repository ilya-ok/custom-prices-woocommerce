<?php
/**
 * Класс для корзины: кастомные колонки, расчёт итогов, AJAX-обновления.
 *
 * Модифицирует стандартную корзину WooCommerce:
 *  - Добавляет ширину к названию товара
 *  - Показывает правильную цену единицы (РРЦ/ОПТ/отрез/рулон)
 *  - Рендерит input количества с корректными min/step
 *  - Рассчитывает итог по формуле (не стандартная WC логика)
 *  - Выводит блок «Итого» вместо стандартного cart_totals
 *  - Поставляет AJAX-фрагменты для обновления корзины без перезагрузки
 */

if (!defined('ABSPATH')) {
    exit;
}

class Custom_Prices_Cart {
    public function __construct() {
        // Фильтры для модификации колонок корзины
        add_filter('woocommerce_cart_item_name', [$this, 'custom_cart_item_name'], 10, 3);
        add_filter('woocommerce_cart_item_quantity', [$this, 'custom_cart_item_quantity'], 10, 3);
        add_filter('woocommerce_cart_item_price', [$this, 'custom_cart_item_price'], 10, 3);
        add_filter('woocommerce_cart_item_subtotal', [$this, 'custom_cart_item_subtotal'], 10, 3);

        // Восстановление custom_data из сессии при загрузке корзины
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'get_cart_item_from_session'], 20, 3);

        // Замена стандартного итога корзины на кастомный
        add_filter('woocommerce_cart_subtotal', [$this, 'ensure_custom_subtotal'], 10, 3);
        add_action('woocommerce_cart_collaterals', [$this, 'custom_cart_totals'], 10);

        // Прелоадер (анимация загрузки) при обновлении корзины
        add_action('woocommerce_cart_contents', [$this, 'add_preloader']);

        // Inline-скрипт для автоматического обновления корзины при изменении qty
        add_action('wp_footer', [$this, 'cart_ajax_js']);

        // Убираем стандартный блок cart_totals WooCommerce — заменяем на свой
        remove_action('woocommerce_cart_collaterals', 'woocommerce_cart_totals', 10);

        // Отключаем доставку глобально (магазин не используя доставку через WC)
        add_filter('woocommerce_cart_needs_shipping', '__return_false');

        // AJAX-фрагменты: обновление корзины после добавления/удаления товара без перезагрузки
        add_filter('woocommerce_update_cart_fragments', [$this, 'add_custom_fragments']);
        add_filter('woocommerce_add_to_cart_fragments', [$this, 'add_custom_fragments']);

        // Убираем отображение cart-subtotal на checkout (итоги пересчитаны нами)
        add_filter('woocommerce_cart_subtotal', '__return_empty_string', 999);

        // Переопределяем общий итог корзины на checkout — используем наш расчёт
        add_filter('woocommerce_cart_get_total', [$this, 'get_custom_cart_total'], 999);
    }

    /**
     * Восстанавливает custom_data из сессии WC при загрузке корзины.
     * Без этого custom_data теряется после перезагрузки страницы.
     */
    public function get_cart_item_from_session($cart_item, $values, $key) {
        if (isset($values['custom_data'])) {
            $cart_item['custom_data'] = $values['custom_data'];
            error_log('Custom Prices: Cart item session data for ' . $key . ' - ' . print_r($cart_item['custom_data'], true));
        } else {
            error_log('Custom Prices: No custom_data in session for cart item ' . $key);
            $cart_item['custom_data'] = [];
        }
        return $cart_item;
    }

    /**
     * Добавляет ширину рулона к названию товара в корзине.
     * Например: «Искусственная трава (Ширина: 1.5 м)»
     */
    public function custom_cart_item_name($name, $cart_item, $cart_item_key) {
        if (!empty($cart_item['custom_data']['width'])) {
            $name .= ' (' . __('Ширина: ', 'custom-prices-woocommerce') . esc_html($cart_item['custom_data']['width']) . ')';
        }
        return $name;
    }

    /**
     * Показывает цену единицы товара в колонке «Цена» корзины.
     *
     * opt/РРЦ   → стандартная цена товара
     * opt/ОПТ   → _price_opt
     * otrez/отрез → _price_otrez (за м.п.)
     * otrez/рулон → стандартная цена (за м²)
     */
    public function custom_cart_item_price($price, $cart_item, $cart_item_key) {
        $product_id = $cart_item['product_id'];
        $type = get_post_meta($product_id, '_custom_price_type', true);
        $custom_data = !empty($cart_item['custom_data']) ? $cart_item['custom_data'] : [];

        $price_used = 0;
        if ($type == 'opt') {
            $price_used = !empty($custom_data['is_opt']) ? (float) get_post_meta($product_id, '_price_opt', true) : (float) $cart_item['data']->get_price();
        } elseif ($type == 'otrez') {
            $price_used = !empty($custom_data['is_otrez']) ? (float) get_post_meta($product_id, '_price_otrez', true) : (float) $cart_item['data']->get_price();
        } else {
            $price_used = (float) $cart_item['data']->get_price();
        }
        error_log("Custom Prices: Price for cart item $cart_item_key (type: $type) - $price_used");
        return wc_price($price_used);
    }

    /**
     * Рендерит input количества в корзине с корректными min и step.
     * Для ОПТ: min = _min_order, для остальных: min = 1.
     * Step берётся из мета товара или глобальных настроек.
     */
    public function custom_cart_item_quantity($quantity, $cart_item_key, $cart_item) {
        $type = get_post_meta($cart_item['product_id'], '_custom_price_type', true);
        $step = get_post_meta($cart_item['product_id'], '_step_quantity', true) ?: (get_option('custom_prices_options')['step_' . $type] ?? 1);
        // Минимум для ОПТ — из настроек товара или глобально; для остальных — 1
        $min = ($type == 'opt' && !empty($cart_item['custom_data']['is_opt'])) ? (get_post_meta($cart_item['product_id'], '_min_order', true) ?: (get_option('custom_prices_options')['min_order_opt'] ?? 1)) : 1;

        return woocommerce_quantity_input([
            'input_name' => "cart[{$cart_item_key}][qty]",
            'input_value' => $cart_item['quantity'],
            'max_value' => $cart_item['data']->get_stock_quantity(),
            'min_value' => $min,
            'product_name' => $cart_item['data']->get_name(),
            'step' => $step
        ], $cart_item['data'], false);
    }

    /**
     * Рассчитывает и возвращает отформатированную сумму для одной строки корзины.
     */
    public function custom_cart_item_subtotal($subtotal, $cart_item, $cart_item_key) {
        $total = $this->get_custom_subtotal($cart_item);
        error_log("Custom Prices: Subtotal for cart item $cart_item_key - $total");
        return wc_price($total);
    }

    /**
     * Ядро расчёта итога для одного товара в корзине.
     *
     * opt/РРЦ:    total = price × qty
     * opt/ОПТ:    total = price_opt × qty
     * otrez/отрез: total = qty × width × price_otrez   (м.п.)
     * otrez/рулон: total = qty × width × length × price (м²)
     * standard:   стандартная логика WC (wc_get_price_to_display)
     */
    public function get_custom_subtotal($cart_item) {
        $product_id = $cart_item['product_id'];
        $type = get_post_meta($product_id, '_custom_price_type', true);
        $custom_data = !empty($cart_item['custom_data']) ? $cart_item['custom_data'] : [];
        $qty = $cart_item['quantity'];
        $total = 0;

        error_log("Custom Prices: Calculating subtotal for product $product_id (type: $type), qty: $qty, custom_data: " . print_r($custom_data, true));

        if ($type == 'opt') {
            // is_opt = true → ОПТ цена, false → РРЦ (стандартная)
            $price = !empty($custom_data['is_opt']) ? (float) get_post_meta($product_id, '_price_opt', true) : (float) $cart_item['data']->get_price();
            $total = $price * $qty;
        } elseif ($type == 'otrez') {
            $width = !empty($custom_data['width_value']) ? (float) $custom_data['width_value'] : 0;
            if (!empty($custom_data['is_otrez']) && $width > 0) {
                // Отрез: цена за м.п. × кол-во метров × ширина
                $price = (float) get_post_meta($product_id, '_price_otrez', true);
                $total = $qty * $width * $price;
            } else {
                // Рулон: цена за м² × (кол-во × ширина × длина рулона)
                $price = (float) $cart_item['data']->get_price();
                $length = !empty($custom_data['length']) ? (float) $custom_data['length'] : 0;
                $area = $qty * $width * $length;
                $total = $area * $price;
            }
        } else {
            // Стандартный товар
            $total = (float) wc_get_price_to_display($cart_item['data'], ['qty' => $qty]);
        }

        return $total;
    }

    /**
     * Возвращает отформатированный суммарный итог всей корзины.
     * Используется как фильтр woocommerce_cart_subtotal.
     */
    public function ensure_custom_subtotal($cart_subtotal, $compound, $cart) {
        $total = $this->get_custom_cart_total();
        error_log("Custom Prices: Cart subtotal - $total");
        return wc_price($total);
    }

    /**
     * Суммирует итоги всех товаров корзины по нашей формуле.
     * Также используется как фильтр woocommerce_cart_get_total (для checkout total).
     */
    public function get_custom_cart_total() {
        $total = 0;
        foreach (WC()->cart->get_cart() as $cart_item) {
            $total += $this->get_custom_subtotal($cart_item);
        }
        return $total;
    }

    /**
     * Рендерит блок «Итого: N руб.» в cart collaterals.
     * Вместо стандартного woocommerce_cart_totals.
     */
    public function custom_cart_totals() {
        $total = $this->get_custom_cart_total();
        ?>
        <div class="cart-total-custom" data-total="<?php echo esc_attr($total); ?>">
            <h2><?php _e('Итого', 'custom-prices-woocommerce'); ?>: <span class="total-amount"><?php echo wc_price($total); ?></span></h2>
        </div>
        <?php
    }

    /**
     * Полноэкранный прелоадер — показывается пока корзина обновляется через AJAX.
     */
    public function add_preloader() {
        ?>
        <div class="cart-preloader" style="display:none;">
            <div class="loader"><?php _e('Загрузка...', 'custom-prices-woocommerce'); ?></div>
        </div>
        <?php
    }

    /**
     * Добавляет inline-скрипт на cart-странице.
     *
     * Логика:
     *  - При изменении qty (input.qty) → автоматически нажимаем скрытую кнопку update_cart
     *  - При удалении товара (a.remove) → показываем прелоадер
     *  - После событий WC (fragments_refreshed, added_to_cart и т.д.) → скрываем прелоадер
     *    и обновляем блок «Итого» через AJAX (custom_get_cart_total)
     */
    public function cart_ajax_js() {
        if (is_cart()) {
            $nonce = wp_create_nonce('woocommerce-cart');
            ?>
            <script type="text/javascript">
            jQuery(function($) {
                // При изменении qty автоматически обновляем корзину
                $(document).on('change', '.woocommerce-cart-form input.qty', function() {
                    $('.cart-preloader').show();
                    var $form = $(this).closest('form');
                    $form.find('button[name="update_cart"]').prop('disabled', false).trigger('click');
                });

                // Показываем прелоадер при удалении товара
                $(document).on('click', '.woocommerce-cart-form a.remove', function() {
                    $('.cart-preloader').show();
                });

                // После обновления корзины: скрываем прелоадер и обновляем «Итого»
                $(document.body).on('wc_fragments_refreshed updated_cart_totals removed_from_cart added_to_cart', function() {
                    $('.cart-preloader').hide();
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'custom_get_cart_total',
                            security: '<?php echo $nonce; ?>'
                        },
                        success: function(data) {
                            $('.cart-total-custom .total-amount').html(data);
                        },
                        error: function() {
                            console.log('Error updating cart total');
                        }
                    });
                });
            });
            </script>
            <?php
        }
    }

    /**
     * Поставляет AJAX-фрагменты для обновления корзины без полной перезагрузки.
     *
     * Фрагменты:
     *  - form.woocommerce-cart-form  → вся таблица корзины (с кастомными колонками)
     *  - div.cart-collaterals        → блок с итогами
     *  - div.cart-total-custom       → блок «Итого: N руб.»
     */
    public function add_custom_fragments($fragments) {
        // Перерендерим полную таблицу корзины
        ob_start();
        wc_get_template('cart/cart.php');
        $fragments['form.woocommerce-cart-form'] = ob_get_clean();

        // Перерендерим cart collaterals (итоги, промо-код и т.д.)
        ob_start();
        ?>
        <div class="cart-collaterals">
            <?php do_action('woocommerce_cart_collaterals'); ?>
        </div>
        <?php
        $fragments['div.cart-collaterals'] = ob_get_clean();

        // Отдельно блок «Итого» (обновляется и через inline-скрипт)
        ob_start();
        $this->custom_cart_totals();
        $fragments['div.cart-total-custom'] = ob_get_clean();

        error_log("Custom Prices: Generated fragments for Ajax update, including separate cart-total-custom");

        return $fragments;
    }
}