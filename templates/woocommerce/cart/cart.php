<?php
/**
 * Cart Page — переопределённый плагином Custom Prices WooCommerce.
 *
 * Отличия от стандартного шаблона WC:
 *  - Добавлены колонки: «Ед. изм.» и «Общее кол-во / Площадь»
 *  - Кнопка «Update Cart» скрыта (JS нажимает её автоматически при изменении qty)
 *  - Кнопка «Оформить заказ» добавлена под формой корзины
 *
 * Расчёт volume_square:
 *  opt:   volume_unit × qty (например, «25 м²»)
 *  otrez: qty × width (м.п.) или qty × width × length (м²)
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 7.9.0
 */

defined('ABSPATH') || exit;

do_action('woocommerce_before_cart'); ?>





<form class="woocommerce-cart-form" action="<?php echo esc_url(wc_get_cart_url()); ?>" method="post">
    <?php do_action('woocommerce_before_cart_table'); ?>

    <table class="shop_table shop_table_responsive cart woocommerce-cart-form__contents" cellspacing="0">
        <thead>
            <tr>
                <th class="product-remove"><span class="screen-reader-text"><?php esc_html_e('Remove item', 'woocommerce'); ?></span></th>
                <th class="product-thumbnail"><span class="screen-reader-text"><?php esc_html_e('Thumbnail image', 'woocommerce'); ?></span></th>
                <th class="product-name"><?php esc_html_e('Наименование', 'woocommerce'); ?></th>
                <th class="product-price"><?php esc_html_e('Price', 'woocommerce'); ?></th>
                <th class="product-quantity"><?php esc_html_e('Quantity', 'woocommerce'); ?></th>
                <th class="product-units"><?php _e('Ед. изм.', 'custom-prices-woocommerce'); ?></th>
                <th class="product-volume-square"><?php _e('Общее кол-во    ', 'custom-prices-woocommerce'); ?></th>
                <th class="product-subtotal"><?php esc_html_e('Сумма', 'woocommerce'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php do_action('woocommerce_before_cart_contents'); ?>

            <?php
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
                $product_id = apply_filters('woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key);
                $type = get_post_meta($product_id, '_custom_price_type', true);
                $custom_data = !empty($cart_item['custom_data']) ? $cart_item['custom_data'] : [];
                $units = get_post_meta($product_id, '_units', true) ?: '-';
                $volume_square = '-'; // Значение колонки «Общее кол-во / Площадь»

                // Рассчитываем единицы измерения и суммарный объём/площадь по типу товара
                if ($type == 'opt') {
                    $volume_unit = (float) get_post_meta($product_id, '_volume_unit', true);
                    $volume_square = ($volume_unit > 0) ? ($volume_unit * $cart_item['quantity']) . ' ' . esc_html($units) : '-';
                    $units = !empty($custom_data['unit']) ? $custom_data['unit'] : $units;
                } elseif ($type == 'otrez') {
                    $width = !empty($custom_data['width_value']) ? (float) $custom_data['width_value'] : 0;
                    $square = 0;
                    if (!empty($custom_data['is_otrez'])) {
                        $square = $cart_item['quantity'] * $width;
                        $units = !empty($custom_data['unit']) ? $custom_data['unit'] : 'м.п.';
                    } else {
                        $length = !empty($custom_data['length']) ? (float) $custom_data['length'] : 0;
                        $square = $cart_item['quantity'] * $width * $length;
                        $units = !empty($custom_data['unit']) ? $custom_data['unit'] : 'рулон';
                    }
                    $volume_square = $square > 0 ? $square . ' м²' : '-';
                }

                $product_name = apply_filters('woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key);
                $product_permalink = apply_filters('woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink($cart_item) : '', $cart_item, $cart_item_key);

                if ($_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters('woocommerce_cart_item_visible', true, $cart_item, $cart_item_key)) {
                    ?>
                    <tr class="woocommerce-cart-form__cart-item <?php echo esc_attr(apply_filters('woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key)); ?>">
                        <td class="product-remove">
                            <?php
                            echo apply_filters(
                                'woocommerce_cart_item_remove_link',
                                sprintf(
                                    '<a href="%s" class="remove" aria-label="%s" data-product_id="%s" data-product_sku="%s">&times;</a>',
                                    esc_url(wc_get_cart_remove_url($cart_item_key)),
                                    esc_attr(sprintf(__('Remove %s from cart', 'woocommerce'), wp_strip_all_tags($product_name))),
                                    esc_attr($product_id),
                                    esc_attr($_product->get_sku())
                                ),
                                $cart_item_key
                            );
                            ?>
                        </td>
                        <td class="product-thumbnail">
                            <?php
                            $thumbnail = apply_filters('woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key);
                            if (!$product_permalink) {
                                echo $thumbnail;
                            } else {
                                printf('<a href="%s">%s</a>', esc_url($product_permalink), $thumbnail);
                            }
                            ?>
                        </td>
                        <td class="product-name" data-title="<?php esc_attr_e('Наименование', 'woocommerce'); ?>">
                            <?php
                            if (!$product_permalink) {
                                echo wp_kses_post($product_name . '&nbsp;');
                            } else {
                                echo wp_kses_post(apply_filters('woocommerce_cart_item_name', sprintf('<a href="%s">%s</a>', esc_url($product_permalink), $_product->get_name()), $cart_item, $cart_item_key));
                            }
                            do_action('woocommerce_after_cart_item_name', $cart_item, $cart_item_key);
                            echo wc_get_formatted_cart_item_data($cart_item);
                            if ($_product->backorders_require_notification() && $_product->is_on_backorder($cart_item['quantity'])) {
                                echo wp_kses_post(apply_filters('woocommerce_cart_item_backorder_notification', '<p class="backorder_notification">' . esc_html__('Available on backorder', 'woocommerce') . '</p>', $product_id));
                            }
                            ?>
                        </td>
                        <td class="product-price" data-title="<?php esc_attr_e('Price', 'woocommerce'); ?>">
                            <?php
                            echo apply_filters('woocommerce_cart_item_price', WC()->cart->get_product_price($_product), $cart_item, $cart_item_key);
                            ?>
                        </td>
                        <td class="product-quantity" data-title="<?php esc_attr_e('Quantity', 'woocommerce'); ?>">
                            <?php
                            echo apply_filters('woocommerce_cart_item_quantity', '', $cart_item_key, $cart_item);
                            ?>
                        </td>
                        <td class="product-units" data-title="<?php esc_attr_e('Ед. изм.', 'custom-prices-woocommerce'); ?>">
                            <?php echo esc_html($units); ?>
                        </td>
                        <td class="product-volume-square" data-title="<?php esc_attr_e('Общее кол-во', 'custom-prices-woocommerce'); ?>">
                            <?php echo $volume_square; ?>
                        </td>
                        <td class="product-subtotal" data-title="<?php esc_attr_e('Сумма', 'woocommerce'); ?>">
                            <?php
                            echo apply_filters('woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal($_product, $cart_item['quantity']), $cart_item, $cart_item_key);
                            ?>
                        </td>
                    </tr>
                    <?php
                }
            }
            ?>

            <?php do_action('woocommerce_cart_contents'); ?>

            <tr>
                <td colspan="8" class="actions">
                    <?php if (wc_coupons_enabled()) { ?>
                        <div class="coupon">
                            <label for="coupon_code" class="screen-reader-text"><?php esc_html_e('Coupon:', 'woocommerce'); ?></label>
                            <input type="text" name="coupon_code" class="input-text" id="coupon_code" value="" placeholder="<?php esc_attr_e('Coupon code', 'woocommerce'); ?>" />
                            <button type="submit" class="button<?php echo esc_attr(wc_wp_theme_get_element_class_name('button') ? ' ' . wc_wp_theme_get_element_class_name('button') : ''); ?>" name="apply_coupon" value="<?php esc_attr_e('Apply coupon', 'woocommerce'); ?>"><?php esc_html_e('Apply coupon', 'woocommerce'); ?></button>
                            <?php do_action('woocommerce_cart_coupon'); ?>
                        </div>
                    <?php } ?>

                    <!-- Скрытая кнопка update_cart — JS нажимает её автоматически при изменении qty -->
                    <!-- Без этого пришлось бы ждать ручного нажатия «Обновить корзину» -->
                    <button type="submit" class="button" name="update_cart" value="<?php esc_attr_e( 'Update cart', 'woocommerce' ); ?>" style="display: none;"><?php esc_html_e( 'Update cart', 'woocommerce' ); ?></button>

                    <?php do_action('woocommerce_cart_actions'); ?>
                    <?php wp_nonce_field('woocommerce-cart', 'woocommerce-cart-nonce'); ?>
                </td>
            </tr>

            <?php do_action('woocommerce_after_cart_contents'); ?>
        </tbody>
    </table>
    <?php do_action('woocommerce_after_cart_table'); ?>
</form>

<?php do_action('woocommerce_before_cart_collaterals'); ?>

<div class="cart-collaterals">
    <?php do_action('woocommerce_cart_collaterals'); ?>
</div>

<!-- Кнопка «Оформить заказ» — переход на checkout -->
<a href="<?php echo esc_url(wc_get_checkout_url()); ?>" class="button checkout-button"><?php _e('Оформить заказ', 'woocommerce'); ?></a>

<?php do_action('woocommerce_after_cart'); ?>