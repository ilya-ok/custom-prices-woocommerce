<?php
/**
 * Класс для админ-панели: добавление и сохранение кастомных полей цен товара.
 *
 * Поля выводятся в секции «Цена» (General tab) редактора товара WooCommerce.
 * Все значения хранятся как post_meta с ключами вида _custom_price_*.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Custom_Prices_Admin {
    public function __construct() {
        // Рендер полей в секции цен товара
        add_action('woocommerce_product_options_pricing', [$this, 'add_product_fields']);
        // Сохранение полей при обновлении товара
        add_action('woocommerce_process_product_meta', [$this, 'save_product_fields']);
    }

    /**
     * Добавляет поля настройки цен в секцию «Цена» товара в WC-админке.
     *
     * Поля:
     *  _custom_price_type  — тип цены: '' (стандартная), 'opt' (оптовая), 'otrez' (на отрез)
     *  _price_otrez        — цена за метр погонный (м.п.) — используется для типа 'otrez'
     *  _price_opt          — оптовая цена за единицу — используется для типа 'opt'
     *  _min_order          — минимальный объём заказа в единицах
     *  _step_quantity      — шаг изменения количества (например, 1, 5, 10)
     *  _volume_unit        — объём/площадь в одной единице товара (для вывода суммарного объёма)
     *  _units              — текст единицы измерения (например, «м.п.», «рулон», «кг»)
     */
    public function add_product_fields() {
        global $post;

        // Выпадающий список: тип ценообразования товара
        woocommerce_wp_select([
            'id' => '_custom_price_type',
            'label' => __('Тип цены', 'custom-prices-woocommerce'),
            'options' => [
                '' => __('Стандартная', 'custom-prices-woocommerce'),   // Обычный товар — WC считает сам
                'opt' => __('За опт', 'custom-prices-woocommerce'),     // Два уровня цен: РРЦ и ОПТ
                'otrez' => __('На отрез', 'custom-prices-woocommerce')  // Продажа рулонами и метрами
            ],
            'value' => get_post_meta($post->ID, '_custom_price_type', true)
        ]);

        // Цена за м.п. для отреза (используется в формуле: qty × width × _price_otrez)
        woocommerce_wp_text_input([
            'id' => '_price_otrez',
            'label' => __('Цена на отрез (за м.п.)', 'custom-prices-woocommerce'),
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0'],
            'value' => get_post_meta($post->ID, '_price_otrez', true)
        ]);

        // Оптовая цена за единицу (используется в формуле: qty × _price_opt)
        woocommerce_wp_text_input([
            'id' => '_price_opt',
            'label' => __('Цена за опт (за единицу)', 'custom-prices-woocommerce'),
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0'],
            'value' => get_post_meta($post->ID, '_price_opt', true)
        ]);

        // Минимальное количество для заказа (устанавливается как min в input)
        // Если не задано — берётся из глобальных настроек плагина
        woocommerce_wp_text_input([
            'id' => '_min_order',
            'label' => __('Минимальный заказ', 'custom-prices-woocommerce'),
            'type' => 'number',
            'custom_attributes' => ['min' => '1'],
            'value' => get_post_meta($post->ID, '_min_order', true)
        ]);

        // Шаг количества — кнопки +/- меняют значение на этот шаг
        woocommerce_wp_text_input([
            'id' => '_step_quantity',
            'label' => __('Шаг количества', 'custom-prices-woocommerce'),
            'type' => 'number',
            'custom_attributes' => ['min' => '1'],
            'value' => get_post_meta($post->ID, '_step_quantity', true)
        ]);

        // Объём единицы товара — используется для вывода «Объём: N единиц» в корзине (тип opt)
        woocommerce_wp_text_input([
            'id' => '_volume_unit',
            'label' => __('Объем в единице товара', 'custom-prices-woocommerce'),
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0'],
            'value' => get_post_meta($post->ID, '_volume_unit', true)
        ]);

        // Текст единицы измерения — выводится в колонке «Ед.изм» корзины
        woocommerce_wp_text_input([
            'id' => '_units',
            'label' => __('Единицы измерения', 'custom-prices-woocommerce'),
            'value' => get_post_meta($post->ID, '_units', true)
        ]);
    }

    /**
     * Сохранение всех кастомных полей в post_meta при сохранении товара.
     * Все значения пропускаются через sanitize_text_field.
     */
    public function save_product_fields($post_id) {
        $fields = ['_custom_price_type', '_price_otrez', '_price_opt', '_min_order', '_step_quantity', '_volume_unit', '_units'];
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
    }
}