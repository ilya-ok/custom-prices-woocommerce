<?php
/**
 * Страница массового редактирования полей цен товаров по категории.
 *
 * Подменю «Массовое редактирование» под WooCommerce.
 * Выбор категории (иерархический) → AJAX загрузка товаров →
 * редактирование полей inline → AJAX сохранение с индикатором статуса.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Custom_Prices_Bulk_Edit {

    public function __construct() {
        add_action( 'admin_menu',                          [$this, 'add_page'] );
        add_action( 'admin_enqueue_scripts',               [$this, 'enqueue_assets'] );
        add_action( 'wp_ajax_cp_get_products',             [$this, 'ajax_get_products'] );
        add_action( 'wp_ajax_cp_save_product',             [$this, 'ajax_save_product'] );
        add_action( 'wp_ajax_cp_bulk_apply_price',         [$this, 'ajax_bulk_apply_price'] );
        add_action( 'wp_ajax_cp_bulk_apply_fields',        [$this, 'ajax_bulk_apply_fields'] );
    }

    /* ------------------------------------------------------------------ */
    /* Регистрация подменю                                                 */
    /* ------------------------------------------------------------------ */

    public function add_page() {
        add_menu_page(
            __( 'Custom Prices', 'custom-prices-woocommerce' ),
            __( 'Custom Prices', 'custom-prices-woocommerce' ),
            'manage_woocommerce',
            'custom-prices',
            [$this, 'render_page'],
            'dashicons-money-alt',
            57
        );

        add_submenu_page(
            'custom-prices',
            __( 'Массовое редактирование цен', 'custom-prices-woocommerce' ),
            __( 'Массовое редактирование', 'custom-prices-woocommerce' ),
            'manage_woocommerce',
            'custom-prices',
            [$this, 'render_page']
        );

        add_submenu_page(
            'custom-prices',
            __( 'Настройки Custom Prices', 'custom-prices-woocommerce' ),
            __( 'Настройки', 'custom-prices-woocommerce' ),
            'manage_woocommerce',
            'custom-prices-settings',
            [ Custom_Prices_Settings::get_instance(), 'settings_page_callback' ]
        );
    }

    /* ------------------------------------------------------------------ */
    /* Подключение assets                                                  */
    /* ------------------------------------------------------------------ */

    public function enqueue_assets( $hook ) {
        if ( 'toplevel_page_custom-prices' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'cp-bulk-edit-css',
            plugin_dir_url( __DIR__ ) . 'assets/css/admin-bulk-edit.css',
            [],
            '1.0'
        );

        wp_enqueue_script(
            'cp-bulk-edit-js',
            plugin_dir_url( __DIR__ ) . 'assets/js/admin-bulk-edit.js',
            ['jquery'],
            '1.0',
            true // в футере
        );

        wp_localize_script( 'cp-bulk-edit-js', 'cpBulkEdit', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'cp_bulk_edit_nonce' ),
        ] );
    }

    /* ------------------------------------------------------------------ */
    /* Рендер страницы                                                     */
    /* ------------------------------------------------------------------ */

    public function render_page() {
        $categories = $this->get_categories_tree();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e( 'Массовое редактирование цен', 'custom-prices-woocommerce' ); ?></h1>

            <div class="cp-bulk-header">
                <label for="cp-category-select">
                    <?php _e( 'Категория товаров:', 'custom-prices-woocommerce' ); ?>
                </label>
                <select id="cp-category-select">
                    <option value="">— <?php _e( 'Выберите категорию', 'custom-prices-woocommerce' ); ?> —</option>
                    <?php foreach ( $categories as $cat ): ?>
                        <option value="<?php echo esc_attr( $cat['id'] ); ?>">
                            <?php echo esc_html( $cat['label'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span id="cp-product-count" class="cp-count"></span>
            </div>

            <!-- Панель массовых операций с ценами -->
            <div id="cp-bulk-operations" class="cp-bulk-operations" style="display:none;">
                <h2><?php _e( 'Массовое изменение цен', 'custom-prices-woocommerce' ); ?></h2>
                <p class="description">
                    <?php _e( 'Автоматически рассчитать и установить цены на отрез или опт для всех товаров категории на основе их стандартной цены.', 'custom-prices-woocommerce' ); ?>
                </p>

                <div class="cp-bulk-form">
                    <div class="cp-form-row">
                        <div class="cp-form-field">
                            <label for="cp-operation-type">
                                <?php _e( 'Операция:', 'custom-prices-woocommerce' ); ?>
                            </label>
                            <select id="cp-operation-type">
                                <option value="add"><?php _e( 'Добавить к стандартной цене', 'custom-prices-woocommerce' ); ?></option>
                                <option value="subtract"><?php _e( 'Вычесть из стандартной цены', 'custom-prices-woocommerce' ); ?></option>
                                <option value="add_percent"><?php _e( 'Добавить процент к цене', 'custom-prices-woocommerce' ); ?></option>
                                <option value="subtract_percent"><?php _e( 'Вычесть процент от цены', 'custom-prices-woocommerce' ); ?></option>
                            </select>
                        </div>

                        <div class="cp-form-field">
                            <label for="cp-operation-value">
                                <?php _e( 'Значение:', 'custom-prices-woocommerce' ); ?>
                            </label>
                            <input type="number" id="cp-operation-value" step="0.01" min="0" placeholder="0" />
                            <span class="cp-value-hint" id="cp-value-hint"><?php _e( 'руб.', 'custom-prices-woocommerce' ); ?></span>
                        </div>

                        <div class="cp-form-field">
                            <label for="cp-target-field">
                                <?php _e( 'Целевое поле цены:', 'custom-prices-woocommerce' ); ?>
                            </label>
                            <select id="cp-target-field">
                                <option value="otrez"><?php _e( 'Цена на отрез (м.п.)', 'custom-prices-woocommerce' ); ?></option>
                                <option value="opt"><?php _e( 'Цена опт (ед.)', 'custom-prices-woocommerce' ); ?></option>
                            </select>
                        </div>

                        <div class="cp-form-field">
                            <button type="button" id="cp-apply-bulk-btn" class="button button-primary button-large">
                                <span class="dashicons dashicons-update"></span>
                                <?php _e( 'Применить ко всем товарам', 'custom-prices-woocommerce' ); ?>
                            </button>
                        </div>
                    </div>

                    <div class="cp-bulk-status" id="cp-bulk-status" style="display:none;">
                        <span class="cp-status-text"></span>
                    </div>

                    <div class="cp-bulk-example">
                        <strong><?php _e( 'Пример:', 'custom-prices-woocommerce' ); ?></strong>
                        <span id="cp-example-text">
                            <?php _e( 'Стандартная цена товара: 1000 руб. → Цена на отрез: 1000 руб.', 'custom-prices-woocommerce' ); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Панель массового редактирования дополнительных полей -->
            <div id="cp-bulk-fields" class="cp-bulk-operations" style="display:none;">
                <h2><?php _e( 'Массовое редактирование полей', 'custom-prices-woocommerce' ); ?></h2>
                <p class="description">
                    <?php _e( 'Установить одинаковые значения полей для всех товаров категории. Оставьте поле пустым, если не хотите его изменять.', 'custom-prices-woocommerce' ); ?>
                </p>

                <div class="cp-bulk-form">
                    <div class="cp-fields-grid">
                        <div class="cp-form-field">
                            <label for="cp-bulk-type">
                                <?php _e( 'Тип цены:', 'custom-prices-woocommerce' ); ?>
                            </label>
                            <select id="cp-bulk-type">
                                <option value="">— <?php _e( 'Не изменять', 'custom-prices-woocommerce' ); ?> —</option>
                                <option value="standard"><?php _e( 'Стандартная', 'custom-prices-woocommerce' ); ?></option>
                                <option value="opt"><?php _e( 'За опт', 'custom-prices-woocommerce' ); ?></option>
                                <option value="otrez"><?php _e( 'На отрез', 'custom-prices-woocommerce' ); ?></option>
                            </select>
                        </div>

                        <div class="cp-form-field">
                            <label for="cp-bulk-units">
                                <?php _e( 'Ед. изм.:', 'custom-prices-woocommerce' ); ?>
                            </label>
                            <input type="text" id="cp-bulk-units" placeholder="<?php esc_attr_e( 'м.п., рулон, кг...', 'custom-prices-woocommerce' ); ?>" />
                            <small class="cp-hint"><?php _e( 'Оставьте пустым, чтобы не изменять', 'custom-prices-woocommerce' ); ?></small>
                        </div>

                        <div class="cp-form-field">
                            <label for="cp-bulk-min-order">
                                <?php _e( 'Мин. заказ:', 'custom-prices-woocommerce' ); ?>
                            </label>
                            <input type="number" id="cp-bulk-min-order" min="0" step="1" placeholder="0" />
                            <small class="cp-hint"><?php _e( 'Минимальное количество для заказа', 'custom-prices-woocommerce' ); ?></small>
                        </div>

                        <div class="cp-form-field">
                            <label for="cp-bulk-step-qty">
                                <?php _e( 'Шаг кол-во:', 'custom-prices-woocommerce' ); ?>
                            </label>
                            <input type="number" id="cp-bulk-step-qty" min="0" step="1" placeholder="0" />
                            <small class="cp-hint"><?php _e( 'Шаг изменения количества', 'custom-prices-woocommerce' ); ?></small>
                        </div>

                        <div class="cp-form-field">
                            <label for="cp-bulk-volume">
                                <?php _e( 'Объём ед.:', 'custom-prices-woocommerce' ); ?>
                            </label>
                            <input type="number" id="cp-bulk-volume" min="0" step="0.01" placeholder="0" />
                            <small class="cp-hint"><?php _e( 'Объём одной единицы товара', 'custom-prices-woocommerce' ); ?></small>
                        </div>
                    </div>

                    <div class="cp-form-actions">
                        <button type="button" id="cp-apply-fields-btn" class="button button-primary button-large">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e( 'Применить ко всем товарам', 'custom-prices-woocommerce' ); ?>
                        </button>
                    </div>

                    <div class="cp-bulk-status" id="cp-fields-status" style="display:none;">
                        <span class="cp-status-text"></span>
                    </div>

                    <div class="cp-bulk-note">
                        <span class="dashicons dashicons-info"></span>
                        <strong><?php _e( 'Примечание:', 'custom-prices-woocommerce' ); ?></strong>
                        <?php _e( 'Изменяются только те поля, которые заполнены. Пустые поля будут проигнорированы.', 'custom-prices-woocommerce' ); ?>
                    </div>
                </div>
            </div>

            <div id="cp-products-container" style="display:none;"></div>
        </div>
        <?php
    }

    /* ------------------------------------------------------------------ */
    /* Построение иерархического списка категорий                          */
    /* ------------------------------------------------------------------ */

    /**
     * Возвращает flat-массив категорий с отметками уровня вложенности.
     * Пример: [['id' => 5, 'label' => 'Товары'], ['id' => 12, 'label' => '— Подкатегория']]
     */
    private function get_categories_tree() {
        $all_terms = get_terms( [
            'taxonomy'   => 'product_cat',
            'orderby'    => 'name',
            'hide_empty' => false,
            'number'     => 0,
        ] );

        if ( is_wp_error( $all_terms ) || empty( $all_terms ) ) {
            return [];
        }

        // Группируем термины по parent
        $children_map = [];
        foreach ( $all_terms as $term ) {
            $children_map[ $term->parent ][] = $term;
        }

        $result = [];
        $this->build_tree( 0, $children_map, $result, 0 );
        return $result;
    }

    /**
     * Рекурсивно строит flat-массив из дерева категорий.
     */
    private function build_tree( $parent_id, &$children_map, &$result, $depth ) {
        if ( ! isset( $children_map[ $parent_id ] ) ) {
            return;
        }

        foreach ( $children_map[ $parent_id ] as $term ) {
            $prefix       = str_repeat( '— ', $depth );
            $result[]     = [
                'id'    => $term->term_id,
                'label' => $prefix . $term->name,
            ];
            $this->build_tree( $term->term_id, $children_map, $result, $depth + 1 );
        }
    }

    /**
     * Рекурсивно собирает ID категории и всех её подкатегорий.
     */
    private function get_subcategory_ids( $cat_id ) {
        $ids = [ (int) $cat_id ];

        $children = get_terms( [
            'taxonomy' => 'product_cat',
            'parent'   => $cat_id,
            'number'   => 0,
        ] );

        if ( ! is_wp_error( $children ) ) {
            foreach ( $children as $child ) {
                $ids = array_merge( $ids, $this->get_subcategory_ids( $child->term_id ) );
            }
        }

        return $ids;
    }

    /* ------------------------------------------------------------------ */
    /* AJAX: загрузка товаров категории                                    */
    /* ------------------------------------------------------------------ */

    public function ajax_get_products() {
        check_ajax_referer( 'cp_bulk_edit_nonce', 'nonce' );

        $category_id = intval( $_POST['category_id'] ?? 0 );
        if ( ! $category_id ) {
            wp_send_json_error( 'Invalid category' );
        }

        // Включаем подкатегории
        $cat_ids = $this->get_subcategory_ids( $category_id );

        $products = get_posts( [
            'post_type'      => 'product',
            'posts_per_page' => 200,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'tax_query'      => [
                [
                    'taxonomy' => 'product_cat',
                    'terms'    => $cat_ids,
                    'operator' => 'IN',
                ],
            ],
        ] );

        if ( empty( $products ) ) {
            wp_send_json_success( [
                'html'  => '<p class="cp-empty">' . esc_html__( 'Товаров в этой категории нет.', 'custom-prices-woocommerce' ) . '</p>',
                'count' => 0,
            ] );
        }

        $options = get_option( 'custom_prices_options', [] );
        $html    = $this->build_products_table( $products, $options );

        wp_send_json_success( [
            'html'  => $html,
            'count' => count( $products ),
        ] );
    }

    /**
     * Генерирует HTML-таблицу товаров.
     */
    private function build_products_table( $products, $options = [] ) {
        $attr_width  = ! empty( $options['attr_width'] )  ? $options['attr_width']  : '';
        $attr_length = ! empty( $options['attr_length'] ) ? $options['attr_length'] : '';

        $html = '<table class="widefat cp-products-table">';
        $html .= '<thead><tr>'
            . '<th class="cp-col-img"></th>'
            . '<th class="cp-col-name">' . esc_html__( 'Товар', 'custom-prices-woocommerce' ) . '</th>'
            . '<th class="cp-col-type">' . esc_html__( 'Тип цены', 'custom-prices-woocommerce' ) . '</th>'
            . '<th class="cp-col-num">' . esc_html__( 'Стандарт цена', 'custom-prices-woocommerce' ) . '</th>'
            . '<th class="cp-col-num">' . esc_html__( 'Цена отрез (м.п.)', 'custom-prices-woocommerce' ) . '</th>'
            . '<th class="cp-col-num">' . esc_html__( 'Цена опт (ед.)', 'custom-prices-woocommerce' ) . '</th>'
            . '<th class="cp-col-txt">' . esc_html__( 'Ед. изм.', 'custom-prices-woocommerce' ) . '</th>'
            . '<th class="cp-col-num">' . esc_html__( 'Мин. заказ', 'custom-prices-woocommerce' ) . '</th>'
            . '<th class="cp-col-num">' . esc_html__( 'Шаг кол-во', 'custom-prices-woocommerce' ) . '</th>'
            . '<th class="cp-col-num">' . esc_html__( 'Объём ед.', 'custom-prices-woocommerce' ) . '</th>'
            . '<th class="cp-col-actions"></th>'
            . '</tr></thead>';
        $html .= '<tbody>';

        foreach ( $products as $product_post ) {
            $product = wc_get_product( $product_post->ID );
            if ( ! $product ) {
                continue;
            }

            $id            = $product->get_id();
            $image_html    = $product->get_image( 'woocommerce_thumbnail' );
            $sku           = $product->get_sku() ?: '—';
            $price         = $product->get_price();

            // Стандартная цена (regular_price)
            $regular_price = $product->get_regular_price();

            // Текущие значения meta
            $type        = get_post_meta( $id, '_custom_price_type',  true );
            $price_otrez = get_post_meta( $id, '_price_otrez',        true );
            $price_opt   = get_post_meta( $id, '_price_opt',          true );
            $units       = get_post_meta( $id, '_units',              true );
            $min_order   = get_post_meta( $id, '_min_order',          true );
            $step_qty    = get_post_meta( $id, '_step_quantity',      true );
            $volume_unit = get_post_meta( $id, '_volume_unit',        true );

            $html .= '<tr class="cp-product-row" data-product-id="' . esc_attr( $id ) . '">';

            // Изображение
            $html .= '<td class="cp-col-img"><div class="cp-product-img">' . $image_html . '</div></td>';

            // Ширины и длина (из атрибутов товара)
            $width_str  = '';
            $length_str = '';
            if ( $attr_width ) {
                $width_terms = wp_get_post_terms( $id, $attr_width, [ 'fields' => 'names' ] );
                if ( ! is_wp_error( $width_terms ) && ! empty( $width_terms ) ) {
                    $width_str = implode( ', ', $width_terms );
                }
            }
            if ( $attr_length ) {
                $length_terms = wp_get_post_terms( $id, $attr_length, [ 'fields' => 'names' ] );
                if ( ! is_wp_error( $length_terms ) && ! empty( $length_terms ) ) {
                    $length_str = $length_terms[0];
                }
            }

            // Имя / артикул / цена / ширина / длина
            $product_url = get_permalink( $id );
            $edit_url    = admin_url( 'post.php?post=' . $id . '&action=edit' );

            $html .= '<td class="cp-col-name">'
                . '<div class="cp-product-title">'
                . '<strong><a href="' . esc_url( $product_url ) . '" target="_blank" class="cp-product-link">'
                . esc_html( $product->get_name() )
                . '</a></strong>'
                . '<a href="' . esc_url( $edit_url ) . '" target="_blank" class="cp-edit-link">'
                . esc_html__( 'Редактировать', 'custom-prices-woocommerce' )
                . '</a>'
                . '</div>'
                . '<div class="cp-meta">'
                . '<span>' . esc_html__( 'Артикул:', 'custom-prices-woocommerce' ) . ' ' . esc_html( $sku ) . '</span>'
                . '<span>' . esc_html__( 'Цена:', 'custom-prices-woocommerce' ) . ' ' . esc_html( $price ) . ' ' . esc_html__( 'руб.', 'custom-prices-woocommerce' ) . '</span>'
                . ( $width_str  ? '<span>' . esc_html__( 'Ширина:', 'custom-prices-woocommerce' ) . ' ' . esc_html( $width_str )  . '</span>' : '' )
                . ( $length_str ? '<span>' . esc_html__( 'Длина:', 'custom-prices-woocommerce' )  . ' ' . esc_html( $length_str ) . '</span>' : '' )
                . '</div>'
                . '</td>';

            // Тип цены (select)
            $html .= '<td class="cp-col-type"><select name="cp_type">'
                . '<option value=""'     . selected( $type, '',      false ) . '>' . esc_html__( 'Стандартная', 'custom-prices-woocommerce' ) . '</option>'
                . '<option value="opt"'  . selected( $type, 'opt',   false ) . '>' . esc_html__( 'За опт',      'custom-prices-woocommerce' ) . '</option>'
                . '<option value="otrez"'. selected( $type, 'otrez', false ) . '>' . esc_html__( 'На отрез',    'custom-prices-woocommerce' ) . '</option>'
                . '</select></td>';

            // Числовые поля
            $html .= '<td class="cp-col-num"><input type="number" name="cp_regular_price" value="' . esc_attr( $regular_price ) . '" step="0.01" min="0"></td>';
            $html .= '<td class="cp-col-num"><input type="number" name="cp_price_otrez"  value="' . esc_attr( $price_otrez )  . '" step="0.01" min="0"></td>';
            $html .= '<td class="cp-col-num"><input type="number" name="cp_price_opt"    value="' . esc_attr( $price_opt )    . '" step="0.01" min="0"></td>';

            // Текст (единицы измерения)
            $html .= '<td class="cp-txt"><input type="text"   name="cp_units"         value="' . esc_attr( $units )       . '"></td>';

            // Числовые поля (целые)
            $html .= '<td class="cp-col-num"><input type="number" name="cp_min_order"    value="' . esc_attr( $min_order )   . '" min="1"></td>';
            $html .= '<td class="cp-col-num"><input type="number" name="cp_step_qty"     value="' . esc_attr( $step_qty )    . '" min="1"></td>';
            $html .= '<td class="cp-col-num"><input type="number" name="cp_volume_unit"  value="' . esc_attr( $volume_unit ) . '" step="0.01" min="0"></td>';

            // Кнопка сохранить + статус
            $html .= '<td class="cp-col-actions">'
                . '<button class="cp-save-btn button button-primary" data-product-id="' . esc_attr( $id ) . '">'
                . esc_html__( 'Сохранить', 'custom-prices-woocommerce' )
                . '</button>'
                . '<span class="cp-save-status"></span>'
                . '</td>';

            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }

    /* ------------------------------------------------------------------ */
    /* AJAX: сохранение полей одного товара                                */
    /* ------------------------------------------------------------------ */

    public function ajax_save_product() {
        check_ajax_referer( 'cp_bulk_edit_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $product_id = intval( $_POST['product_id'] ?? 0 );
        if ( ! $product_id || get_post_type( $product_id ) !== 'product' ) {
            wp_send_json_error( 'Invalid product' );
        }

        // Обновляем стандартную цену товара (regular_price)
        if ( isset( $_POST['cp_regular_price'] ) ) {
            $regular_price = floatval( $_POST['cp_regular_price'] );
            update_post_meta( $product_id, '_regular_price', $regular_price );
            update_post_meta( $product_id, '_price', $regular_price );
        }

        // Маппинг: meta_key => POST-ключ
        $fields = [
            '_custom_price_type' => 'cp_type',
            '_price_otrez'       => 'cp_price_otrez',
            '_price_opt'         => 'cp_price_opt',
            '_units'             => 'cp_units',
            '_min_order'         => 'cp_min_order',
            '_step_quantity'     => 'cp_step_qty',
            '_volume_unit'       => 'cp_volume_unit',
        ];

        foreach ( $fields as $meta_key => $post_key ) {
            if ( isset( $_POST[ $post_key ] ) ) {
                update_post_meta( $product_id, $meta_key, sanitize_text_field( $_POST[ $post_key ] ) );
            }
        }

        wp_send_json_success( 'saved' );
    }

    /* ------------------------------------------------------------------ */
    /* AJAX: массовое применение расчета цен ко всем товарам категории    */
    /* ------------------------------------------------------------------ */

    public function ajax_bulk_apply_price() {
        check_ajax_referer( 'cp_bulk_edit_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $category_id    = intval( $_POST['category_id'] ?? 0 );
        $operation_type = sanitize_text_field( $_POST['operation_type'] ?? 'add' );
        $value          = floatval( $_POST['value'] ?? 0 );
        $target_field   = sanitize_text_field( $_POST['target_field'] ?? 'otrez' );

        if ( ! $category_id ) {
            wp_send_json_error( 'Invalid category' );
        }

        if ( $value < 0 ) {
            wp_send_json_error( 'Invalid value' );
        }

        // Получаем все товары категории (включая подкатегории)
        $cat_ids = $this->get_subcategory_ids( $category_id );

        $products = get_posts( [
            'post_type'      => 'product',
            'posts_per_page' => -1, // Все товары
            'fields'         => 'ids',
            'tax_query'      => [
                [
                    'taxonomy' => 'product_cat',
                    'terms'    => $cat_ids,
                    'operator' => 'IN',
                ],
            ],
        ] );

        if ( empty( $products ) ) {
            wp_send_json_error( 'No products found' );
        }

        // Определяем целевое meta_key
        $target_meta_key = ( $target_field === 'opt' ) ? '_price_opt' : '_price_otrez';

        $updated_count = 0;
        $errors_count  = 0;

        foreach ( $products as $product_id ) {
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                $errors_count++;
                continue;
            }

            // Получаем стандартную цену товара
            $standard_price = $product->get_regular_price();
            if ( empty( $standard_price ) ) {
                $standard_price = $product->get_price();
            }

            if ( empty( $standard_price ) || $standard_price <= 0 ) {
                // Пропускаем товары без цены
                continue;
            }

            // Вычисляем новую цену на основе операции
            $new_price = $this->calculate_price( $standard_price, $operation_type, $value );

            if ( $new_price !== false && $new_price >= 0 ) {
                // Обновляем meta поле
                update_post_meta( $product_id, $target_meta_key, $new_price );
                $updated_count++;
            } else {
                $errors_count++;
            }
        }

        wp_send_json_success( [
            'updated' => $updated_count,
            'errors'  => $errors_count,
            'total'   => count( $products ),
        ] );
    }

    /**
     * Вычисляет новую цену на основе операции.
     *
     * @param float  $base_price     Базовая цена.
     * @param string $operation_type Тип операции (add, subtract, add_percent, subtract_percent).
     * @param float  $value          Значение для операции.
     * @return float|false Новая цена или false при ошибке.
     */
    private function calculate_price( $base_price, $operation_type, $value ) {
        $base_price = floatval( $base_price );
        $value      = floatval( $value );

        switch ( $operation_type ) {
            case 'add':
                return $base_price + $value;

            case 'subtract':
                $result = $base_price - $value;
                return max( 0, $result ); // Не допускаем отрицательных цен

            case 'add_percent':
                if ( $value < 0 || $value > 1000 ) {
                    return false; // Защита от некорректных процентов
                }
                return $base_price + ( $base_price * $value / 100 );

            case 'subtract_percent':
                if ( $value < 0 || $value > 100 ) {
                    return false; // Защита от некорректных процентов
                }
                $result = $base_price - ( $base_price * $value / 100 );
                return max( 0, $result );

            default:
                return false;
        }
    }

    /* ------------------------------------------------------------------ */
    /* AJAX: массовое применение дополнительных полей                     */
    /* ------------------------------------------------------------------ */

    public function ajax_bulk_apply_fields() {
        check_ajax_referer( 'cp_bulk_edit_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $category_id = intval( $_POST['category_id'] ?? 0 );
        if ( ! $category_id ) {
            wp_send_json_error( 'Invalid category' );
        }

        // Получаем значения полей из POST
        $type       = isset( $_POST['type'] ) && $_POST['type'] !== '' ? sanitize_text_field( $_POST['type'] ) : null;
        $units      = isset( $_POST['units'] ) && $_POST['units'] !== '' ? sanitize_text_field( $_POST['units'] ) : null;
        $min_order  = isset( $_POST['min_order'] ) && $_POST['min_order'] !== '' ? absint( $_POST['min_order'] ) : null;
        $step_qty   = isset( $_POST['step_qty'] ) && $_POST['step_qty'] !== '' ? absint( $_POST['step_qty'] ) : null;
        $volume     = isset( $_POST['volume'] ) && $_POST['volume'] !== '' ? floatval( $_POST['volume'] ) : null;

        // Проверяем, что хотя бы одно поле заполнено
        if ( is_null( $type ) && is_null( $units ) && is_null( $min_order ) && is_null( $step_qty ) && is_null( $volume ) ) {
            wp_send_json_error( 'No fields to update' );
        }

        // Получаем все товары категории (включая подкатегории)
        $cat_ids = $this->get_subcategory_ids( $category_id );

        $products = get_posts( [
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => [
                [
                    'taxonomy' => 'product_cat',
                    'terms'    => $cat_ids,
                    'operator' => 'IN',
                ],
            ],
        ] );

        if ( empty( $products ) ) {
            wp_send_json_error( 'No products found' );
        }

        $updated_count = 0;
        $fields_updated = [];

        foreach ( $products as $product_id ) {
            // Обновляем только те поля, которые не null
            if ( ! is_null( $type ) ) {
                $type_value = ( $type === 'standard' ) ? '' : $type;
                update_post_meta( $product_id, '_custom_price_type', $type_value );
                $fields_updated['type'] = true;
            }

            if ( ! is_null( $units ) ) {
                update_post_meta( $product_id, '_units', $units );
                $fields_updated['units'] = true;
            }

            if ( ! is_null( $min_order ) ) {
                update_post_meta( $product_id, '_min_order', $min_order );
                $fields_updated['min_order'] = true;
            }

            if ( ! is_null( $step_qty ) ) {
                update_post_meta( $product_id, '_step_quantity', $step_qty );
                $fields_updated['step_qty'] = true;
            }

            if ( ! is_null( $volume ) ) {
                update_post_meta( $product_id, '_volume_unit', $volume );
                $fields_updated['volume'] = true;
            }

            $updated_count++;
        }

        wp_send_json_success( [
            'updated'        => $updated_count,
            'total'          => count( $products ),
            'fields_updated' => array_keys( $fields_updated ),
        ] );
    }
}
