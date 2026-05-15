<?php
/**
 * Класс для глобальных настроек плагина.
 *
 * Создаёт подменю «Custom Prices» в разделе WooCommerce.
 * Все настройки хранятся в одном WordPress option: custom_prices_options (массив).
 *
 * Поля (основные):
 *  min_order_otrez  — минимальный заказ на отрез (умолчание для всех товаров типа otrez)
 *  min_order_opt    — минимальный заказ опт
 *  step_otrez       — шаг количества для отрезов
 *  step_opt         — шаг количества для опт
 *  attr_width       — WC taxonomy slug атрибута ширины (например pa_shirina)
 *  attr_length      — WC taxonomy slug атрибута длины (например pa_dlina)
 *
 * Поля (тексты кнопок и меток):
 *  label_buy_btn       — кнопка «Купить» на листинге
 *  label_cart_btn      — кнопка «В корзину»
 *  label_tab_rrc       — таб РРЦ
 *  label_tab_opt       — таб ОПТ
 *  label_tab_rulon     — таб Рулон
 *  label_tab_otrez     — таб Отрез
 *  label_qty           — метка поля количества
 *  label_length_input  — метка поля длины отреза
 *  label_width_select  — метка выбора ширины рулона
 *  label_total         — метка блока итого
 *  label_volume        — метка блока объёма
 *  label_area          — метка блока площади
 *  label_price_rrc     — метка цены РРЦ
 *  label_price_opt     — метка цены ОПТ
 *  label_price_rulon   — метка цены от рулона
 *  label_price_otrez   — метка цены на отрез
 *  label_price_std     — метка стандартной цены
 *  suffix_currency     — суффикс валюты («руб.»)
 *  suffix_area         — суффикс площади («м²»)
 *  suffix_width        — суффикс ширины («м»)
 *
 * Значения из мета товара (_min_order, _step_quantity) имеют приоритет над глобальными.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Custom_Prices_Settings {
    public function __construct() {
        // Регистрируем подменю WooCommerce
        add_action('admin_menu', [$this, 'add_settings_page']);
        // Регистрируем поля и секции через WP Settings API
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Добавляет подменю «Custom Prices» в WooCommerce.
     * Доступ — роль manage_woocommerce (WC managers и выше).
     */
    public function add_settings_page() {
        add_submenu_page(
            'woocommerce',
            __('Настройки Custom Prices', 'custom-prices-woocommerce'),
            __('Custom Prices', 'custom-prices-woocommerce'),
            'manage_woocommerce',
            'custom-prices-settings',
            [$this, 'settings_page_callback']
        );
    }

    /**
     * Рендер страницы настроек через WP Settings API.
     */
    public function settings_page_callback() {
        ?>
        <div class="wrap">
            <h1><?php _e('Глобальные настройки Custom Prices', 'custom-prices-woocommerce'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('custom_prices_settings_group');
                do_settings_sections('custom-prices-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Регистрация настроек, секций и полей через WP Settings API.
     * Все поля сохраняются в один option: custom_prices_options[field_id].
     */
    public function register_settings() {
        register_setting('custom_prices_settings_group', 'custom_prices_options');

        // ── Секция: Основные настройки ────────────────────────────────────────
        add_settings_section('main_section', __('Основные настройки', 'custom-prices-woocommerce'), null, 'custom-prices-settings');

        // Числовые поля: минимальные заказы и шаги
        add_settings_field('min_order_otrez', __('Минимальный заказ на отрез', 'custom-prices-woocommerce'), [$this, 'field_callback'], 'custom-prices-settings', 'main_section', ['id' => 'min_order_otrez', 'type' => 'number']);
        add_settings_field('min_order_opt',   __('Минимальный заказ опт',       'custom-prices-woocommerce'), [$this, 'field_callback'], 'custom-prices-settings', 'main_section', ['id' => 'min_order_opt',   'type' => 'number']);
        add_settings_field('step_otrez',      __('Шаг количества на отрез',     'custom-prices-woocommerce'), [$this, 'field_callback'], 'custom-prices-settings', 'main_section', ['id' => 'step_otrez',      'type' => 'number']);
        add_settings_field('step_opt',        __('Шаг количества за опт',       'custom-prices-woocommerce'), [$this, 'field_callback'], 'custom-prices-settings', 'main_section', ['id' => 'step_opt',        'type' => 'number']);

        // Выпадающие списки: WC-атрибуты для ширины и длины рулона
        add_settings_field('attr_width',  __('Атрибут ширины рулона', 'custom-prices-woocommerce'), [$this, 'attr_callback'], 'custom-prices-settings', 'main_section', ['id' => 'attr_width']);
        add_settings_field('attr_length', __('Атрибут длины рулона',  'custom-prices-woocommerce'), [$this, 'attr_callback'], 'custom-prices-settings', 'main_section', ['id' => 'attr_length']);

        // ── Секция: Тексты кнопок ─────────────────────────────────────────────
        add_settings_section(
            'buttons_section',
            __('Тексты кнопок', 'custom-prices-woocommerce'),
            function() {
                echo '<p class="description">' . __('Тексты кнопок на листинге и в формах заказа.', 'custom-prices-woocommerce') . '</p>';
            },
            'custom-prices-settings'
        );

        add_settings_field('label_buy_btn',  __('Кнопка «Купить» (листинг)', 'custom-prices-woocommerce'), [$this, 'text_field_callback'], 'custom-prices-settings', 'buttons_section', ['id' => 'label_buy_btn',  'default' => 'Купить']);
        add_settings_field('label_cart_btn', __('Кнопка «В корзину»',        'custom-prices-woocommerce'), [$this, 'text_field_callback'], 'custom-prices-settings', 'buttons_section', ['id' => 'label_cart_btn', 'default' => 'В корзину']);

        // ── Секция: Тексты табов ──────────────────────────────────────────────
        add_settings_section(
            'tabs_section',
            __('Названия табов', 'custom-prices-woocommerce'),
            function() {
                echo '<p class="description">' . __('Названия вкладок в формах выбора типа заказа.', 'custom-prices-woocommerce') . '</p>';
            },
            'custom-prices-settings'
        );

        add_settings_field('label_tab_rrc',   __('Таб: РРЦ',   'custom-prices-woocommerce'), [$this, 'text_field_callback'], 'custom-prices-settings', 'tabs_section', ['id' => 'label_tab_rrc',   'default' => 'РРЦ']);
        add_settings_field('label_tab_opt',   __('Таб: ОПТ',   'custom-prices-woocommerce'), [$this, 'text_field_callback'], 'custom-prices-settings', 'tabs_section', ['id' => 'label_tab_opt',   'default' => 'ОПТ']);
        add_settings_field('label_tab_rulon', __('Таб: Рулон', 'custom-prices-woocommerce'), [$this, 'text_field_callback'], 'custom-prices-settings', 'tabs_section', ['id' => 'label_tab_rulon', 'default' => 'Рулон']);
        add_settings_field('label_tab_otrez', __('Таб: Отрез', 'custom-prices-woocommerce'), [$this, 'text_field_callback'], 'custom-prices-settings', 'tabs_section', ['id' => 'label_tab_otrez', 'default' => 'Отрез']);

        // ── Секция: Метки форм ────────────────────────────────────────────────
        add_settings_section(
            'form_labels_section',
            __('Метки полей и итогов', 'custom-prices-woocommerce'),
            function() {
                echo '<p class="description">' . __('Тексты меток в формах заказа и блоках итогов.', 'custom-prices-woocommerce') . '</p>';
            },
            'custom-prices-settings'
        );

        add_settings_field('label_qty',          __('Метка: Количество',     'custom-prices-woocommerce'), [$this, 'text_field_callback'], 'custom-prices-settings', 'form_labels_section', ['id' => 'label_qty',          'default' => 'Количество:']);
        add_settings_field('label_length_input', __('Метка: Длина отреза',   'custom-prices-woocommerce'), [$this, 'text_field_callback'], 'custom-prices-settings', 'form_labels_section', ['id' => 'label_length_input', 'default' => 'Длина отреза:']);
        add_settings_field('label_width_select', __('Метка: Ширина рулона',  'custom-prices-woocommerce'), [$this, 'text_field_callback'], 'custom-prices-settings', 'form_labels_section', ['id' => 'label_width_select', 'default' => 'Ширина рулона:']);
        add_settings_field('label_total',        __('Метка: Итого',          'custom-prices-woocommerce'), [$this, 'text_field_callback'], 'custom-prices-settings', 'form_labels_section', ['id' => 'label_total',        'default' => 'Итого:']);
        add_settings_field('label_volume',       __('Метка: Объём',          'custom-prices-woocommerce'), [$this, 'text_field_callback'], 'custom-prices-settings', 'form_labels_section', ['id' => 'label_volume',       'default' => 'Объем:']);
        add_settings_field('label_area',         __('Метка: Площадь',        'custom-prices-woocommerce'), [$this, 'text_field_callback'], 'custom-prices-settings', 'form_labels_section', ['id' => 'label_area',         'default' => 'Площадь:']);

        // ── Секция: Метки цен ─────────────────────────────────────────────────
        add_settings_section(
            'price_labels_section',
            __('Метки цен', 'custom-prices-woocommerce'),
            function() {
                echo '<p class="description">' . __('Текстовые метки рядом с ценами товаров.', 'custom-prices-woocommerce') . '</p>';
            },
            'custom-prices-settings'
        );

        add_settings_field('label_price_rrc',   __('Цена РРЦ',           'custom-prices-woocommerce'), [$this, 'text_field_callback'], 'custom-prices-settings', 'price_labels_section', ['id' => 'label_price_rrc',   'default' => 'Цена (РРЦ):']);
        add_settings_field('label_price_opt',   __('Цена ОПТ',           'custom-prices-woocommerce'), [$this, 'text_field_callback'], 'custom-prices-settings', 'price_labels_section', ['id' => 'label_price_opt',   'default' => 'Цена (ОПТ):']);
        add_settings_field('label_price_rulon', __('Цена от рулона',     'custom-prices-woocommerce'), [$this, 'text_field_callback'], 'custom-prices-settings', 'price_labels_section', ['id' => 'label_price_rulon', 'default' => 'Цена (от рулона):']);
        add_settings_field('label_price_otrez', __('Цена на отрез',      'custom-prices-woocommerce'), [$this, 'text_field_callback'], 'custom-prices-settings', 'price_labels_section', ['id' => 'label_price_otrez', 'default' => 'Цена (на отрез):']);
        add_settings_field('label_price_std',   __('Цена (стандартная)', 'custom-prices-woocommerce'), [$this, 'text_field_callback'], 'custom-prices-settings', 'price_labels_section', ['id' => 'label_price_std',   'default' => 'Цена:']);
        add_settings_field('label_price_rrc_mobile',   __('Цена РРЦ (мобайл)',       'custom-prices-woocommerce'), [$this, 'text_field_callback'], 'custom-prices-settings', 'price_labels_section', ['id' => 'label_price_rrc_mobile',   'default' => '']);
        add_settings_field('label_price_opt_mobile',   __('Цена ОПТ (мобайл)',       'custom-prices-woocommerce'), [$this, 'text_field_callback'], 'custom-prices-settings', 'price_labels_section', ['id' => 'label_price_opt_mobile',   'default' => '']);
        add_settings_field('label_price_rulon_mobile', __('Цена от рулона (мобайл)', 'custom-prices-woocommerce'), [$this, 'text_field_callback'], 'custom-prices-settings', 'price_labels_section', ['id' => 'label_price_rulon_mobile', 'default' => '']);
        add_settings_field('label_price_otrez_mobile', __('Цена на отрез (мобайл)',  'custom-prices-woocommerce'), [$this, 'text_field_callback'], 'custom-prices-settings', 'price_labels_section', ['id' => 'label_price_otrez_mobile', 'default' => '']);

        // ── Секция: Суффиксы ──────────────────────────────────────────────────
        add_settings_section(
            'suffixes_section',
            __('Суффиксы единиц измерения', 'custom-prices-woocommerce'),
            function() {
                echo '<p class="description">' . __('Суффиксы, отображаемые после чисел в итогах и формах.', 'custom-prices-woocommerce') . '</p>';
            },
            'custom-prices-settings'
        );

        add_settings_field('suffix_currency', __('Суффикс валюты',  'custom-prices-woocommerce'), [$this, 'text_field_callback'], 'custom-prices-settings', 'suffixes_section', ['id' => 'suffix_currency', 'default' => html_entity_decode(get_woocommerce_currency_symbol(), ENT_COMPAT, 'UTF-8')]);
        add_settings_field('suffix_area',     __('Суффикс площади', 'custom-prices-woocommerce'), [$this, 'text_field_callback'], 'custom-prices-settings', 'suffixes_section', ['id' => 'suffix_area',     'default' => 'м²']);
        add_settings_field('suffix_width',    __('Суффикс ширины',  'custom-prices-woocommerce'), [$this, 'text_field_callback'], 'custom-prices-settings', 'suffixes_section', ['id' => 'suffix_width',    'default' => 'м']);
    }

    /**
     * Рендер числового поля (input type=number).
     */
    public function field_callback($args) {
        $options = get_option('custom_prices_options', []);
        $value   = isset($options[$args['id']]) ? $options[$args['id']] : '';
        echo '<input type="' . $args['type'] . '" id="' . esc_attr($args['id']) . '" name="custom_prices_options[' . esc_attr($args['id']) . ']" value="' . esc_attr($value) . '">';
    }

    /**
     * Рендер текстового поля (input type=text).
     * Показывает значение по умолчанию как подсказку.
     */
    public function text_field_callback($args) {
        $options = get_option('custom_prices_options', []);
        $default = $args['default'] ?? '';
        $value   = isset($options[$args['id']]) && $options[$args['id']] !== '' ? $options[$args['id']] : $default;
        echo '<input type="text" id="' . esc_attr($args['id']) . '" name="custom_prices_options[' . esc_attr($args['id']) . ']" value="' . esc_attr($value) . '" class="regular-text">';
        if ($default !== '') {
            echo ' <span class="description">' . sprintf(__('По умолчанию: «%s»', 'custom-prices-woocommerce'), esc_html($default)) . '</span>';
        }
    }

    /**
     * Рендер выпадающего списка атрибутов WooCommerce.
     * Перебирает все зарегистрированные атрибуты (wc_get_attribute_taxonomies)
     * и выводит select с значениями вида pa_{attribute_name}.
     */
    public function attr_callback($args) {
        $options    = get_option('custom_prices_options', []);
        $value      = isset($options[$args['id']]) ? $options[$args['id']] : '';
        $attributes = wc_get_attribute_taxonomies();
        echo '<select id="' . esc_attr($args['id']) . '" name="custom_prices_options[' . esc_attr($args['id']) . ']">';
        echo '<option value="">' . __('Выберите атрибут', 'custom-prices-woocommerce') . '</option>';
        foreach ($attributes as $attr) {
            $name = $attr->attribute_name;
            echo '<option value="pa_' . $name . '" ' . selected($value, 'pa_' . $name, false) . '>' . esc_html($attr->attribute_label) . '</option>';
        }
        echo '</select>';
    }
}
