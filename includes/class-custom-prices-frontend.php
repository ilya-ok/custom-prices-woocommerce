<?php
/**
 * Класс для фронтенда: вывод цен, форм покупки и табов на страницах товаров и листинге.
 *
 * Обязанности:
 *  - Переопределяет HTML цены товара (woocommerce_get_price_html) для вывода РРЦ/ОПТ или Рулон/Отрез
 *  - На single-странице товара выводит форму с табами (opt или otrez) вместо стандартной кнопки «Add to cart»
 *  - На листинге (shop, category) заменяет кнопку WC на кнопку «Купить», открывающую popup через AJAX
 *  - Генерирует HTML попапа по запросу AJAX (get_popup_html)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Custom_Prices_Frontend {
    public function __construct() {
        // Переопределяем HTML цены товара для всех товаров с кастомным типом
        add_filter('woocommerce_get_price_html', [$this, 'custom_price_html'], 20, 2);

        // Форма покупки на single-странице товара: вызывается через кастомный action в теме
        // (вместо стандартного woocommerce_single_product_summary, чтобы тема контролировала позицию)
        add_action('custom_price_forms', [$this, 'display_custom_forms'], 20);

        // Убираем стандартную кнопку «Add to cart» на single-странице (заменяем на нашу форму)
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);

        // На листинге: заменяем стандартную кнопку на кнопку «Купить» для popup
        add_action('woocommerce_after_shop_loop_item', [$this, 'display_custom_forms_listing'], 10);
        remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10);
    }

    /**
     * Переопределяет HTML цены товара.
     *
     * opt  → выводит две цены: РРЦ и ОПТ (с единицей измерения из категории)
     * otrez → выводит две цены: от рулона (м²) и на отрез (м.п.)
     * standard → на single-странице добавляет обычную форму add-to-cart, на листинге — просто цену
     *
     * Единицы измерения для opt/standard берутся из настроек категории товара (fw_get_db_term_option).
     * Единицы для otrez берутся из мета товара (_units).
     */
    public function custom_price_html($price_html, $product) {
        $type = get_post_meta($product->get_id(), '_custom_price_type', true);
        $standard_price = $product->get_price();

        // Единицы измерения из мета товара (для типа otrez)
        $units = get_post_meta($product->get_id(), '_units', true);

        // Единицы измерения из настроек категории товара (для типа opt и standard)
        // yoast_get_primary_term_id — из плагина Yoast, определяет основную категорию
        // fw_get_db_term_option — из темы/framework, хранит настройки категории
        $primary_term_id = yoast_get_primary_term_id( 'product_cat', $product->get_id() );
        $select_cat_units = fw_get_db_term_option($primary_term_id, 'product_cat')['select_cat'];

        $extra_price = '';
        $labels = $this->get_labels();

        if ($type == 'opt') {
            $extra_price = get_post_meta($product->get_id(), '_price_opt', true);
            echo "<div class='price custom-price-wrap'>";
                echo "<div class='file_wrap_item price_wrap'>";
                    echo $this->render_price_label($labels['price_rrc'], $labels['price_rrc_mobile']);
                    echo "<div class='price'>" . wc_price($standard_price) . "/" . $select_cat_units . "</div>";
                echo "</div>";
                echo "<div class='file_wrap_item price_wrap'>";
                    echo $this->render_price_label($labels['price_opt'], $labels['price_opt_mobile']);
                    echo "<div class='price'>" . wc_price($extra_price) . "/" . $select_cat_units . "</div>";
                echo "</div>";
            echo "</div>";
        } elseif ($type == 'otrez') {
            $extra_price = get_post_meta($product->get_id(), '_price_otrez', true);
            echo "<div class='price custom-price-wrap'>";
                echo "<div class='file_wrap_item price_wrap'>";
                    echo $this->render_price_label($labels['price_rulon'], $labels['price_rulon_mobile']);
                    echo "<div class='price'>" . wc_price($standard_price) . "/" . $units . "</div>";
                echo "</div>";
                echo "<div class='file_wrap_item price_wrap'>";
                    echo $this->render_price_label($labels['price_otrez'], $labels['price_otrez_mobile']);
                    echo "<div class='price'>" . wc_price($extra_price) . "/" . $units . "</div>";
                echo "</div>";
            echo "</div>";
        } else {
            if (is_product()) {
                echo "<div class='price custom-price-wrap'>";
                    echo "<div class='file_wrap_item price_wrap'>";
                        echo "<div class='file_wrap-weght'>" . esc_html($labels['price_std']) . "</div>";
                        echo "<div class='price'>" . wc_price($standard_price) . "/" . $select_cat_units . "</div>";
                    echo "</div>";
                echo "</div>";
                do_action('custom_prices_before_cart_wrap');
                echo '<div class="cart_wrap">';
                do_action( 'woocommerce_before_add_to_cart_form' );
                ?>
                <form class="cart" action="<?php echo esc_url( $product->add_to_cart_url() ); ?>" method="post" enctype='multipart/form-data' data-price="<?php echo esc_attr( $standard_price ); ?>">
                    <?php woocommerce_quantity_input( array(), $product ); ?>
                    <div class="total-price"><?php echo esc_html($labels['total'] ?? 'Итого:'); ?> <span class="value">0</span> <span class="suffix"><?php echo esc_html($labels['suffix_currency'] ?? html_entity_decode(get_woocommerce_currency_symbol(), ENT_COMPAT, 'UTF-8')); ?></span></div>
                    <button type="submit" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" class="single_add_to_cart_button button alt">
                        <?php echo esc_html( $product->single_add_to_cart_text() ); ?>
                    </button>
                </form>
                <?php
                do_action( 'woocommerce_after_add_to_cart_form' );
                echo '</div>';
            } else {
                echo "<div class='price custom-price-wrap'>";
                    echo "<div class='file_wrap_item price_wrap'>";
                        echo "<div class='file_wrap-weght'>" . esc_html($labels['price_std']) . "</div>";
                        echo "<div class='price'>" . wc_price($standard_price) . "/" . $select_cat_units . "</div>";
                    echo "</div>";
                echo "</div>";
            }
        }
    }

    /**
     * Точка входа для single-страницы товара (вызывается через action custom_price_forms).
     */
    public function display_custom_forms() {
        $this->display_forms(true);
    }

    /**
     * Выводит кнопку «Купить» на листинге товаров.
     *
     * Для товаров без кастомного типа — возвращаем стандартную WC-кнопку.
     * Для товаров с типом opt/otrez — выводим кнопку «Купить», которая открывает popup.
     * Также локализуем данные товара в JS (custom_product_data_{id}) для использования
     * в JS после загрузки popup через AJAX.
     */
    public function display_custom_forms_listing() {
        global $product;
        $id = $product->get_id();
        $type = get_post_meta($id, '_custom_price_type', true);
        if (!$type) {
            // Стандартный товар — используем кнопку WooCommerce по умолчанию
            woocommerce_template_loop_add_to_cart();
            return;
        }

        // Локализуем данные товара в JS для Ajax-попапа
        $options = get_option('custom_prices_options', []);
        $min_order = get_post_meta($id, '_min_order', true) ?: ($options['min_order_' . $type] ?? 1);
        $step = get_post_meta($id, '_step_quantity', true) ?: ($options['step_' . $type] ?? 1);
        $volume_unit = (float) get_post_meta($id, '_volume_unit', true);
        $units = get_post_meta($id, '_units', true);
        $price_standard = (float) $product->get_price();
        $price_extra = (float) get_post_meta($id, $type == 'opt' ? '_price_opt' : '_price_otrez', true);
        
        wp_localize_script('custom-prices-js', 'custom_product_data_' . $id, [
            'type' => $type,
            'min_order' => $min_order,
            'step' => $step,
            'volume_unit' => $volume_unit,
            'units' => $units,
            'price_standard' => $price_standard,
            'price_extra' => $price_extra,
            'length' => $this->get_length($id, $options),
            'widths' => $this->get_widths($id, $options),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('custom_prices_nonce')
        ]);

        $labels = $this->get_labels();
        // Кнопка «Купить» — JS перехватывает клик, открывает popup и загружает HTML через AJAX
        echo "<a href='#' class='popup-link button' data-product_id='{$id}'>" . esc_html($labels['buy_btn']) . "</a>";
    }

    /**
     * Генерация HTML попапа товара для AJAX-ответа.
     * Вызывается из Custom_Prices_Ajax::get_product_popup().
     * Использует output buffering для захвата HTML из display_forms().
     */
    public function get_popup_html($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) return '';

        ob_start();
        $this->display_forms(false, $product);
        return ob_get_clean();
    }

    /**
     * Главный метод рендера формы покупки.
     *
     * @param bool        $is_single  true — single-страница товара, false — popup на листинге
     * @param WC_Product  $product    объект товара (null на single — берётся из global)
     *
     * Структура вывода:
     *  - popup ($is_single = false): полная карточка товара (изображение, атрибуты, цены, форма с табами)
     *  - single ($is_single = true): только форма с табами (изображение и атрибуты уже есть на странице)
     *
     * Для обоих случаев локализуем данные товара в JS (custom_product_data_{id}).
     */
    private function display_forms($is_single = true, $product = null) {
        if (!$product) {
            global $product;
        }
        if (!is_a($product, 'WC_Product')) {
            error_log('Custom Prices: $product not set or not WC_Product on ' . ($is_single ? 'single' : 'listing'));
            if (!$is_single) woocommerce_template_loop_add_to_cart();
            return;
        }

        $id = $product->get_id();
        $type = get_post_meta($id, '_custom_price_type', true);
        if (!$type) {
            if (!$is_single) woocommerce_template_loop_add_to_cart();
            return;
        }

        // Загружаем параметры: из мета товара с fallback на глобальные настройки
        $options = get_option('custom_prices_options', []);
        $min_order = get_post_meta($id, '_min_order', true) ?: ($options['min_order_' . $type] ?? 1);
        $step = get_post_meta($id, '_step_quantity', true) ?: ($options['step_' . $type] ?? 1);
        $volume_unit = (float) get_post_meta($id, '_volume_unit', true);
        $units = get_post_meta($id, '_units', true);
        $price_standard = (float) $product->get_price();
        // price_extra: оптовая цена для opt, цена на отрез для otrez
        $price_extra = (float) get_post_meta($id, $type == 'opt' ? '_price_opt' : '_price_otrez', true);
        $labels = $this->get_labels();
        $primary_term_id = yoast_get_primary_term_id('product_cat', $id);
        $select_cat_units = fw_get_db_term_option($primary_term_id, 'product_cat')['select_cat'] ?? '';

        // Передаём все параметры товара в JS под уникальным ключом custom_product_data_{id}
        // JS использует эти данные для расчёта total и отправки AJAX
        wp_localize_script('custom-prices-js', 'custom_product_data_' . $id, [
            'type' => $type,
            'min_order' => $min_order,
            'step' => $step,
            'volume_unit' => $volume_unit,
            'units' => $units,
            'price_standard' => $price_standard,
            'price_extra' => $price_extra,
            'length' => $this->get_length($id, $options),
            'widths' => $this->get_widths($id, $options),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('custom_prices_nonce')
        ]); ?>

        <div class="product-popup__content" data-product_id="<?php echo esc_attr($id); ?>">
            <?php if (!$is_single) : // Полная карточка товара — только в popup на листинге ?>
                <h2><?php echo $product->get_name(); ?></h2>
                <div class="product-popup__row">
                    <div class="product-popup__55">
                        <div class="product-popup__row">
                            <div class="product-popup__45">
                                <?php echo $product->get_image('woocommerce_single'); ?>
                            </div>
                            <div class="product-popup__55">
                                    <div class="product-popup__meta product-popup__meta--first">
                                        <div class="product-popup__meta-weght">Артикул:</div>
                                        <!-- TODO: артикул захардкожен, заменить на динамическое значение из мета товара -->
                                        <div class="product-popup__meta-light">3614</div>
                                    </div>
                                    <div class="product-popup__meta">
                                        <div class="product-popup__meta-weght">Бренд:</div>
                                        <!-- Бренд может быть в атрибуте pa_brend или pa_brand (два варианта написания) -->
                                        <?php if($product->get_attribute( 'pa_brend' ) and $product->get_attribute( 'pa_brand' )) { ?>
                                                <div class="product-popup__meta-light"><?php echo $product->get_attribute( 'pa_brend' ); ?></div>
                                        <?php   } else { ?>
                                            <div class="product-popup__meta-light"><?php echo $product->get_attribute( 'pa_brend' ); ?></div>
                                            <div class="product-popup__meta-light"><?php echo $product->get_attribute( 'pa_brand' ); ?></div>
                                        <?php   } ?>
                                    </div>
                                    <div class="product-popup__meta">
                                        <div class="product-popup__meta-weght">Страна производства:</div>
                                        <!-- Аналогично бренду: два варианта написания атрибута -->
                                        <?php if($product->get_attribute( 'pa_strana' ) and $product->get_attribute( 'pa_strana-proizvodstva' )) { ?>
                                                <div class="product-popup__meta-light"><?php echo $product->get_attribute( 'pa_strana' ); ?></div>
                                        <?php   } else { ?>
                                            <div class="product-popup__meta-light"><?php echo $product->get_attribute( 'pa_strana' ); ?></div>
                                            <div class="product-popup__meta-light"><?php echo $product->get_attribute( 'pa_strana-proizvodstva' ); ?></div>
                                        <?php   } ?>
                                    </div>
                                    <div class="product-popup__meta">
                                        <!-- Город и цена доставки из глобальных настроек темы (fw_get_db_settings_option) -->
                                        <div class="product-popup__meta-weght">Доставка по г. <?php echo fw_get_db_settings_option('product_city'); ?>:</div>
                                        <div class="product-popup__meta-light"><?php echo fw_get_db_settings_option('delivery_price'); ?></div>
                                    </div>
                            </div>
                        </div>
                        <div class="shop_attributes">
                                <?php
                                $attributes = $product->get_attributes();
                                foreach ( $attributes as $attribute ) : ?>
                                    <div class="shop_attributes_row row_<?php echo $attribute->get_name(); ?>">

                                        <div><?php echo wc_attribute_label( $attribute->get_name() ); ?>:</div>
                                        <div class="attr_cart_dots"></div>
                                        <div><?php
                                            $values = array();

                                            if ( $attribute->is_taxonomy() ) {
                                                $attribute_taxonomy = $attribute->get_taxonomy_object();
                                                $attribute_values = wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'all' ) );

                                                foreach ( $attribute_values as $attribute_value ) {
                                                    $value_name = esc_html( $attribute_value->name );

                                                    $values[] = $value_name;
                                                }
                                            } else {
                                                $values = $attribute->get_options();

                                                foreach ( $values as &$value ) {
                                                    $value = make_clickable( esc_html( $value ) );
                                                }
                                            }

                                            echo apply_filters( 'woocommerce_attribute', wpautop( wptexturize( implode( ', ', $values ) ) ), $attribute, $values );
                                        ?></div>
                                    </div>
                                <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="product-popup__45">

                        <div class="custom-tabs custom-<?php echo esc_attr($type); ?>" data-is-single="<?php echo $is_single ? 'true' : 'false'; ?>">
                            <div class="custom-price-wrap">
                                <?php if ($type == 'opt') : ?>
                                    <div class="price_wrap">
                                        <?php echo $this->render_price_label($labels['price_rrc'], $labels['price_rrc_mobile']); ?>
                                        <div class="price"><?php echo wc_price($price_standard) . '/' . $select_cat_units; ?></div>
                                    </div>
                                    <div class="price_wrap">
                                        <?php echo $this->render_price_label($labels['price_opt'], $labels['price_opt_mobile']); ?>
                                        <div class="price"><?php echo wc_price($price_extra) . '/' . $select_cat_units; ?></div>
                                    </div>
                                <?php elseif ($type == 'otrez') : ?>
                                    <div class="price_wrap">
                                        <?php echo $this->render_price_label($labels['price_rulon'], $labels['price_rulon_mobile']); ?>
                                        <div class="price"><?php echo wc_price($price_standard) . '/' . $units; ?></div>
                                    </div>
                                    <div class="price_wrap">
                                        <?php echo $this->render_price_label($labels['price_otrez'], $labels['price_otrez_mobile']); ?>
                                        <div class="price"><?php echo wc_price($price_extra) . '/' . $units; ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php
                            if ($type == 'otrez') {
                                $this->display_otrez_form($this->get_widths($id, $options), $min_order, $step, $id, $is_single, $labels);
                            } else {
                                $this->display_opt_form($min_order, $step, $volume_unit, $units, $id, $is_single, $labels);
                            }
                            ?>
                        </div>

                    </div>
                </div>

            <?php endif; ?>

            <?php if ($is_single) : // Выводим image и attributes только в попапе 

                    if( $type == "otrez") : ?>

                <div class="custom-quantity-fields-single">
                    <div class="custom-quantity-fields-loop custom-quantity-fields-cart">
                    <!-- <div class="h3">Расчёт стоимости:</div> -->

                        <div class="custom-tabs custom-<?php echo esc_attr($type); ?>" data-is-single="<?php echo $is_single ? 'true' : 'false'; ?>">
                            <?php
                            if ($type == 'otrez') {
                                $this->display_otrez_form($this->get_widths($id, $options), $min_order, $step, $id, $is_single, $labels);
                            } else {
                                $this->display_opt_form($min_order, $step, $volume_unit, $units, $id, $is_single, $labels);
                            }
                            ?>
                        </div>

                    </div>

                </div>

                <?php endif; ?>

                <?php if( $type == "opt") : ?>

                    <div class="single-type-opt">
                        <div class="custom-tabs custom-<?php echo esc_attr($type); ?>" data-is-single="<?php echo $is_single ? 'true' : 'false'; ?>">
                            <!-- <div class="h3">Расчёт стоимости:</div> -->

                                    <div class="single-type-opt-tabs">
                                        <ul>
                                            <li><a href="#tab-rrc"><?php echo esc_html($labels['tab_rrc']); ?></a></li>
                                            <li><a href="#tab-opt"><?php echo esc_html($labels['tab_opt']); ?></a></li>
                                        </ul>
                                        <div id="tab-rrc">
                                            <label><?php echo esc_html($labels['qty']); ?></label>
                                            <div class="quantity-buttons">
                                                <button class="minus">-</button>
                                                <input type="number" class="qty-input" name="qty_rrc" min="1" step="<?php echo esc_attr($step); ?>" value="1">
                                                <button class="plus">+</button>
                                            </div>
                                        </div>
                                        <div id="tab-opt">
                                            <label><?php echo esc_html($labels['qty']); ?></label>
                                            <div class="quantity-buttons">
                                                <button class="minus">-</button>
                                                <input type="number" class="qty-input" name="qty_opt" min="<?php echo esc_attr($min_order); ?>" step="<?php echo esc_attr($step); ?>" value="<?php echo esc_attr($min_order); ?>">
                                                <button class="plus">+</button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="totals-row">
                                        <div class="total-price"><?php echo esc_html($labels['total']); ?> <span class="value">0</span> <span class="suffix"><?php echo esc_html($labels['suffix_currency']); ?></span></div>
                                        <?php if ($volume_unit > 0) : ?>
                                            <div class="total-volume"><?php echo esc_html($labels['volume']); ?> <span class="value">0</span> <span class="suffix"><?php echo esc_html($units); ?></span></div>
                                        <?php endif; ?>
                                    </div>

                                    <button class="add-to-cart-ajax button alt" data-product_id="<?php echo esc_attr($id); ?>"><?php echo esc_html($labels['cart_btn']); ?><span class="loader" style="display:none;"> &#8635;</span><span class="checkmark" style="display:none;"> &#10003;</span></button>

                        </div>
                    </div>

                <?php endif; ?>


            <?php endif; ?>

        </div>
        <?php
    }

    /**
     * Возвращает все настраиваемые тексты кнопок и меток из глобальных настроек.
     * При отсутствии настройки используются значения по умолчанию.
     *
     * @return array
     */
    private function get_labels() {
        $opts = get_option('custom_prices_options', []);
        $t    = function($key, $default) use ($opts) {
            return (isset($opts[$key]) && $opts[$key] !== '') ? $opts[$key] : $default;
        };
        return [
            'buy_btn'       => $t('label_buy_btn',       'Купить'),
            'cart_btn'      => $t('label_cart_btn',      'В корзину'),
            'tab_rrc'       => $t('label_tab_rrc',       'РРЦ'),
            'tab_opt'       => $t('label_tab_opt',       'ОПТ'),
            'tab_rulon'     => $t('label_tab_rulon',     'Рулон'),
            'tab_otrez'     => $t('label_tab_otrez',     'Отрез'),
            'qty'           => $t('label_qty',           'Количество:'),
            'length_input'  => $t('label_length_input',  'Длина отреза:'),
            'width_select'  => $t('label_width_select',  'Ширина рулона:'),
            'total'         => $t('label_total',         'Итого:'),
            'volume'        => $t('label_volume',        'Объем:'),
            'area'          => $t('label_area',          'Площадь:'),
            'price_rrc'     => $t('label_price_rrc',     'Цена (РРЦ):'),
            'price_opt'     => $t('label_price_opt',     'Цена (ОПТ):'),
            'price_rulon'   => $t('label_price_rulon',   'Цена (от рулона):'),
            'price_otrez'   => $t('label_price_otrez',   'Цена (на отрез):'),
            'price_std'     => $t('label_price_std',     'Цена:'),
            'price_rrc_mobile'   => $t('label_price_rrc_mobile',   ''),
            'price_opt_mobile'   => $t('label_price_opt_mobile',   ''),
            'price_rulon_mobile' => $t('label_price_rulon_mobile', ''),
            'price_otrez_mobile' => $t('label_price_otrez_mobile', ''),
            'suffix_currency' => $t('suffix_currency',   html_entity_decode(get_woocommerce_currency_symbol(), ENT_COMPAT, 'UTF-8')),
            'suffix_area'   => $t('suffix_area',         'м\u00B2'),
            'suffix_width'  => $t('suffix_width',        'м'),
        ];
    }

    /**
     * Рендерит HTML-метку цены с поддержкой мобильного дубля.
     * Если $mobile задан и отличается от $desktop — рендерит два div:
     *  --desktop (скрывается на мобайле) и --mobile (видим только на мобайле).
     * Иначе — один обычный div.
     *
     * @param string $desktop Текст для десктопа
     * @param string $mobile  Текст для мобайла (пустой = использовать desktop)
     * @param string $class   CSS-класс блока
     * @return string
     */
    private function render_price_label($desktop, $mobile, $class = 'file_wrap-weght') {
        if (!empty($mobile) && $mobile !== $desktop) {
            return "<div class='{$class} {$class}--desktop'>" . esc_html($desktop) . "</div>"
                 . "<div class='{$class}--mobile'>" . esc_html($mobile) . "</div>";
        }
        return "<div class='{$class}'>" . esc_html($desktop) . "</div>";
    }

    /**
     * Получает доступные ширины рулона товара из WC-атрибута.
     * Атрибут задаётся в глобальных настройках (attr_width, например pa_shirina).
     * Возвращает массив: [slug => float_value], например ['1-5' => 1.5, '2-0' => 2.0].
     * Запятые в значениях термов заменяются на точки для корректного float-преобразования.
     */
    private function get_widths($id, $options) {
        $attr_width = $options['attr_width'] ?? '';
        $widths = [];
        if ($attr_width) {
            $terms = wp_get_post_terms($id, $attr_width, ['fields' => 'all']);
            foreach ($terms as $term) {
                $widths[$term->slug] = (float) str_replace(',', '.', $term->name);
            }
        }
        return $widths;
    }

    /**
     * Получает длину рулона товара из WC-атрибута (attr_length, например pa_dlina).
     * Используется для расчёта площади при покупке целым рулоном: area = width × length.
     * Берётся первое значение термина (товар предполагается иметь одну длину).
     */
    private function get_length($id, $options) {
        $attr_length = $options['attr_length'] ?? '';
        $length = 0;
        if ($attr_length) {
            $length_terms = wp_get_post_terms($id, $attr_length, ['fields' => 'names']);
            $length = (float) str_replace(',', '.', ($length_terms[0] ?? 0));
        }
        return $length;
    }

    /**
     * Рендер формы для типа 'opt' (оптовая цена).
     *
     * Табы:
     *  РРЦ — розничная цена, min = 1
     *  ОПТ — оптовая цена, min = _min_order
     *
     * После табов: блок «Итого» (обновляется JS), опционально «Объём» (если _volume_unit > 0)
     * и кнопка «В корзину» (AJAX).
     */
    private function display_opt_form($min_order, $step, $volume_unit, $units, $id, $is_single, $labels = []) {
        ?>
        <ul>
            <li><a href="#tab-rrc"><?php echo esc_html($labels['tab_rrc'] ?? 'РРЦ'); ?></a></li>
            <li><a href="#tab-opt"><?php echo esc_html($labels['tab_opt'] ?? 'ОПТ'); ?></a></li>
        </ul>
        <div id="tab-rrc">
            <label><?php echo esc_html($labels['qty'] ?? 'Количество:'); ?></label>
            <div class="quantity-buttons">
                <button class="minus">-</button>
                <input type="number" class="qty-input" name="qty_rrc" min="1" step="<?php echo esc_attr($step); ?>" value="1">
                <button class="plus">+</button>
            </div>
        </div>
        <div id="tab-opt">
            <label><?php echo esc_html($labels['qty'] ?? 'Количество:'); ?></label>
            <div class="quantity-buttons">
                <button class="minus">-</button>
                <input type="number" class="qty-input" name="qty_opt" min="<?php echo esc_attr($min_order); ?>" step="<?php echo esc_attr($step); ?>" value="<?php echo esc_attr($min_order); ?>">
                <button class="plus">+</button>
            </div>
        </div>
        <div class="totals-row">
            <div class="total-price"><?php echo esc_html($labels['total'] ?? 'Итого:'); ?> <span class="value">0</span> <span class="suffix"><?php echo esc_html($labels['suffix_currency'] ?? html_entity_decode(get_woocommerce_currency_symbol(), ENT_COMPAT, 'UTF-8')); ?></span></div>
            <?php if ($volume_unit > 0) : ?>
                <div class="total-volume"><?php echo esc_html($labels['volume'] ?? 'Объем:'); ?> <span class="value">0</span> <span class="suffix"><?php echo esc_html($units); ?></span></div>
            <?php endif; ?>
        </div>
        <button class="add-to-cart-ajax button alt" data-product_id="<?php echo esc_attr($id); ?>"><?php echo esc_html($labels['cart_btn'] ?? 'В корзину'); ?><span class="loader" style="display:none;"> &#8635;</span><span class="checkmark" style="display:none;"> &#10003;</span></button>
        <?php
    }

    /**
     * Рендер формы для типа 'otrez' (продажа на отрез / рулоном).
     *
     * Табы:
     *  Рулон  — покупка целыми рулонами, min = 1, step = 1
     *  Отрез  — покупка метрами, min = _min_order, step = _step_quantity
     *
     * Ниже табов: выбор ширины рулона (radio-кнопки из доступных widths).
     * Если ширина не выбрана а количество > 0 — JS показывает error-block.
     * После: «Итого» и «Площадь» (обновляются JS), кнопка «В корзину».
     */
    private function display_otrez_form($widths, $min_order, $step, $id, $is_single, $labels = []) {
        ?>
        <ul>
            <li><a href="#tab-rulon"><?php echo esc_html($labels['tab_rulon'] ?? 'Рулон'); ?></a></li>
            <li><a href="#tab-otrez"><?php echo esc_html($labels['tab_otrez'] ?? 'Отрез'); ?></a></li>
        </ul>
        <div id="tab-rulon">
            <label><?php echo esc_html($labels['qty'] ?? 'Количество:'); ?></label>
            <div class="quantity-buttons">
                <button class="minus">-</button>
                <input type="number" class="qty-input" name="qty_rulon" min="1" step="1" value="1">
                <button class="plus">+</button>
            </div>
            <span class="qty-unit">шт.</span>
        </div>
        <div id="tab-otrez">
            <label><?php echo esc_html($labels['length_input'] ?? 'Длина отреза:'); ?></label>
            <div class="quantity-buttons">
                <button class="minus">-</button>
                <input type="number" class="qty-input" name="qty_otrez" min="<?php echo esc_attr($min_order); ?>" step="<?php echo esc_attr($step); ?>" value="<?php echo esc_attr($min_order); ?>">
                <button class="plus">+</button>
            </div>
            <span class="qty-unit">м.п.</span>
        </div>
        <div class="width-selection attr_cart_wrap">
            <p><?php echo esc_html($labels['width_select'] ?? 'Ширина рулона:'); ?></p>
            <div class="attr_wrap">
                <?php foreach ($widths as $slug => $name) : ?>
                    <div class="attr_cart">
                        <input id="width-selection_<?php echo esc_attr($name); ?>" type="radio" name="width" value="<?php echo esc_attr($name); ?>" data-value="<?php echo esc_attr($name); ?>">
                        <label for="width-selection_<?php echo esc_attr($name); ?>"><?php echo esc_attr($name); ?> <?php echo esc_html($labels['suffix_width'] ?? 'м'); ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="error-block" style="display:none; color:red;"><?php _e('- (Выберите)', 'custom-prices-woocommerce'); ?></div>
        </div>
        <div class="totals-row">
            <div class="total-price"><?php echo esc_html($labels['total'] ?? 'Итого:'); ?> <span class="value">0</span> <span class="suffix"><?php echo esc_html($labels['suffix_currency'] ?? html_entity_decode(get_woocommerce_currency_symbol(), ENT_COMPAT, 'UTF-8')); ?></span></div>
            <div class="total-square"><?php echo esc_html($labels['area'] ?? 'Площадь:'); ?> <span class="value">0</span> <span class="suffix"><?php echo esc_html($labels['suffix_area'] ?? 'м²'); ?></span></div>
        </div>
        <button class="add-to-cart-ajax button alt" data-product_id="<?php echo esc_attr($id); ?>"><?php echo esc_html($labels['cart_btn'] ?? 'В корзину'); ?><span class="loader" style="display:none;"> &#8635;</span><span class="checkmark" style="display:none;"> &#10003;</span></button>
        <?php
    }
}