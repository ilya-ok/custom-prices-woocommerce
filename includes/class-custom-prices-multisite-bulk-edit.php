<?php
/**
 * Страница массового редактирования цен товаров в сетевой админке.
 *
 * Доступна по адресу: Network Admin → Цены Multisite (page=cp-ms-bulk-edit).
 * При сохранении товара автоматически синхронизирует изменения на все сайты сети.
 *
 * @package Custom_Prices_Woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Класс Custom_Prices_Multisite_Bulk_Edit
 */
class Custom_Prices_Multisite_Bulk_Edit {

	/**
	 * Singleton instance.
	 *
	 * @var Custom_Prices_Multisite_Bulk_Edit|null
	 */
	private static $instance = null;

	/**
	 * @return Custom_Prices_Multisite_Bulk_Edit
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Конструктор.
	 */
	private function __construct() {
		add_action( 'network_admin_menu',     array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts',  array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_cp_ms_get_products', array( $this, 'ajax_get_products' ) );
		add_action( 'wp_ajax_cp_ms_save_product', array( $this, 'ajax_save_product' ) );
	}

	/**
	 * Регистрирует верхнеуровневый пункт меню в сетевой админке.
	 */
	public function add_menu_page() {
		add_menu_page(
			__( 'Массовое редактирование цен', 'custom-prices-woocommerce' ),
			__( 'Custom Prices', 'custom-prices-woocommerce' ),
			'manage_network',
			'cp-ms-bulk-edit',
			array( $this, 'render_page' ),
			'dashicons-money-alt',
			56
		);
	}

	/**
	 * Подключает CSS и JS только на странице массового редактирования.
	 *
	 * @param string $hook Текущий hook страницы.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'toplevel_page_cp-ms-bulk-edit' !== $hook && 'toplevel_page_cp-ms-bulk-edit-network' !== $hook ) {
			return;
		}

		$plugin_url = plugin_dir_url( dirname( __FILE__ ) );
		$plugin_dir = plugin_dir_path( dirname( __FILE__ ) );

		$css_path = $plugin_dir . 'assets/css/admin-multisite-bulk-edit.css';
		wp_enqueue_style(
			'cp-ms-bulk-edit',
			$plugin_url . 'assets/css/admin-multisite-bulk-edit.css',
			array(),
			file_exists( $css_path ) ? filemtime( $css_path ) : '1.0'
		);

		$js_path = $plugin_dir . 'assets/js/admin-multisite-bulk-edit.js';
		wp_enqueue_script(
			'cp-ms-bulk-edit',
			$plugin_url . 'assets/js/admin-multisite-bulk-edit.js',
			array( 'jquery' ),
			file_exists( $js_path ) ? filemtime( $js_path ) : '1.0',
			true
		);

		wp_localize_script(
			'cp-ms-bulk-edit',
			'cpMsData',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cp_ms_bulk_edit_nonce' ),
			)
		);
	}

	/**
	 * Рендерит страницу массового редактирования.
	 */
	public function render_page() {
		$categories = $this->get_categories_tree();
		$sites       = get_sites( array( 'number' => 0 ) );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<p class="description">
				<?php esc_html_e( 'Выберите категорию и отредактируйте цены товаров. Цены автоматически синхронизируются на все сайты мультисайта по SKU.', 'custom-prices-woocommerce' ); ?>
			</p>

			<div class="ms-section">
				<div class="ms-bulk-header">
					<label for="ms-category-select">
						<?php esc_html_e( 'Категория товаров:', 'custom-prices-woocommerce' ); ?>
					</label>
					<select id="ms-category-select">
						<option value="">— <?php esc_html_e( 'Выберите категорию', 'custom-prices-woocommerce' ); ?> —</option>
						<?php foreach ( $categories as $cat ) : ?>
							<option value="<?php echo esc_attr( $cat['id'] ); ?>">
								<?php echo esc_html( $cat['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<span id="ms-product-count" class="ms-count"></span>
					<span class="ms-loading" style="display:none;">
						<span class="spinner is-active" style="float:none;margin:0;"></span>
						<?php esc_html_e( 'Загрузка товаров...', 'custom-prices-woocommerce' ); ?>
					</span>
				</div>
			</div>

			<div id="ms-products-container" style="display:none;"></div>

			<div class="ms-section ms-sites-info">
				<h2><?php esc_html_e( 'Синхронизация', 'custom-prices-woocommerce' ); ?></h2>
				<p><?php esc_html_e( 'При сохранении цены будут синхронизированы на следующие сайты:', 'custom-prices-woocommerce' ); ?></p>
				<ul class="ms-sites-list">
					<?php foreach ( $sites as $site ) : ?>
						<li>
							<strong><?php echo esc_html( get_blog_option( $site->blog_id, 'blogname' ) ); ?></strong>
							<span class="ms-site-url">(<?php echo esc_url( $site->domain . $site->path ); ?>)</span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Возвращает иерархический flat-массив категорий товаров.
	 *
	 * @return array [ ['id' => N, 'label' => '— — Название'], ... ]
	 */
	private function get_categories_tree() {
		$all_terms = get_terms( array(
			'taxonomy'   => 'product_cat',
			'orderby'    => 'name',
			'hide_empty' => false,
			'number'     => 0,
		) );

		if ( is_wp_error( $all_terms ) || empty( $all_terms ) ) {
			return array();
		}

		$children_map = array();
		foreach ( $all_terms as $term ) {
			$children_map[ $term->parent ][] = $term;
		}

		$result = array();
		$this->build_tree( 0, $children_map, $result, 0 );
		return $result;
	}

	/**
	 * Рекурсивно строит flat-массив из дерева категорий.
	 *
	 * @param int   $parent_id    ID родительской категории.
	 * @param array $children_map Карта дочерних элементов.
	 * @param array $result       Результирующий массив (по ссылке).
	 * @param int   $depth        Глубина вложенности.
	 */
	private function build_tree( $parent_id, &$children_map, &$result, $depth ) {
		if ( ! isset( $children_map[ $parent_id ] ) ) {
			return;
		}

		foreach ( $children_map[ $parent_id ] as $term ) {
			$prefix   = str_repeat( '— ', $depth );
			$result[] = array(
				'id'    => $term->term_id,
				'label' => $prefix . $term->name,
			);
			$this->build_tree( $term->term_id, $children_map, $result, $depth + 1 );
		}
	}

	/**
	 * Рекурсивно собирает ID категории и всех её подкатегорий.
	 *
	 * @param int $cat_id ID категории.
	 * @return int[]
	 */
	private function get_subcategory_ids( $cat_id ) {
		$ids = array( (int) $cat_id );

		$children = get_terms( array(
			'taxonomy' => 'product_cat',
			'parent'   => $cat_id,
			'number'   => 0,
		) );

		if ( ! is_wp_error( $children ) ) {
			foreach ( $children as $child ) {
				$ids = array_merge( $ids, $this->get_subcategory_ids( $child->term_id ) );
			}
		}

		return $ids;
	}

	/**
	 * AJAX: загружает товары выбранной категории (включая подкатегории).
	 */
	public function ajax_get_products() {
		check_ajax_referer( 'cp_ms_bulk_edit_nonce', 'nonce' );

		$category_id = isset( $_POST['category_id'] ) ? intval( $_POST['category_id'] ) : 0;
		if ( ! $category_id ) {
			wp_send_json_error( 'Invalid category' );
		}

		$cat_ids = $this->get_subcategory_ids( $category_id );

		$products = get_posts( array(
			'post_type'      => 'product',
			'posts_per_page' => 200,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'tax_query'      => array(
				array(
					'taxonomy' => 'product_cat',
					'terms'    => $cat_ids,
					'operator' => 'IN',
				),
			),
		) );

		if ( empty( $products ) ) {
			wp_send_json_success( array(
				'html'  => '<p class="ms-empty">' . esc_html__( 'Товаров в этой категории нет.', 'custom-prices-woocommerce' ) . '</p>',
				'count' => 0,
			) );
		}

		wp_send_json_success( array(
			'html'  => $this->build_products_table( $products ),
			'count' => count( $products ),
		) );
	}

	/**
	 * Генерирует HTML-таблицу товаров для массового редактирования.
	 *
	 * @param WP_Post[] $products Массив записей товаров.
	 * @return string
	 */
	private function build_products_table( $products ) {
		$html  = '<table class="widefat ms-products-table">';
		$html .= '<thead><tr>';
		$html .= '<th class="ms-col-img"></th>';
		$html .= '<th class="ms-col-name">'    . esc_html__( 'Товар', 'custom-prices-woocommerce' ) . '</th>';
		$html .= '<th class="ms-col-sku">'     . esc_html__( 'SKU', 'custom-prices-woocommerce' ) . '</th>';
		$html .= '<th class="ms-col-num">'     . esc_html__( 'Обычная цена', 'custom-prices-woocommerce' ) . '</th>';
		$html .= '<th class="ms-col-num">'     . esc_html__( 'Цена со скидкой', 'custom-prices-woocommerce' ) . '</th>';
		$html .= '<th class="ms-col-type">'    . esc_html__( 'Тип цены', 'custom-prices-woocommerce' ) . '</th>';
		$html .= '<th class="ms-col-num">'     . esc_html__( 'Цена отрез (м.п.)', 'custom-prices-woocommerce' ) . '</th>';
		$html .= '<th class="ms-col-num">'     . esc_html__( 'Цена опт (ед.)', 'custom-prices-woocommerce' ) . '</th>';
		$html .= '<th class="ms-col-txt">'     . esc_html__( 'Ед. изм.', 'custom-prices-woocommerce' ) . '</th>';
		$html .= '<th class="ms-col-num">'     . esc_html__( 'Мин. заказ', 'custom-prices-woocommerce' ) . '</th>';
		$html .= '<th class="ms-col-num">'     . esc_html__( 'Шаг кол-во', 'custom-prices-woocommerce' ) . '</th>';
		$html .= '<th class="ms-col-num">'     . esc_html__( 'Объём ед.', 'custom-prices-woocommerce' ) . '</th>';
		$html .= '<th class="ms-col-actions">' . esc_html__( 'Действия', 'custom-prices-woocommerce' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		foreach ( $products as $product_post ) {
			$product = wc_get_product( $product_post->ID );
			if ( ! $product ) {
				continue;
			}

			$id            = $product->get_id();
			$image_html    = $product->get_image( 'woocommerce_thumbnail' );
			$sku           = $product->get_sku() ?: '—';
			$regular_price = $product->get_regular_price();
			$sale_price    = $product->get_sale_price();

			$type        = get_post_meta( $id, '_custom_price_type', true );
			$price_otrez = get_post_meta( $id, '_price_otrez',       true );
			$price_opt   = get_post_meta( $id, '_price_opt',         true );
			$units       = get_post_meta( $id, '_units',             true );
			$min_order   = get_post_meta( $id, '_min_order',         true );
			$step_qty    = get_post_meta( $id, '_step_quantity',     true );
			$volume_unit = get_post_meta( $id, '_volume_unit',       true );

			$html .= '<tr class="ms-product-row" data-product-id="' . esc_attr( $id ) . '">';

			$html .= '<td class="ms-col-img"><div class="ms-product-img">' . $image_html . '</div></td>';

			$html .= '<td class="ms-col-name"><strong>' . esc_html( $product->get_name() ) . '</strong></td>';

			$html .= '<td class="ms-col-sku">' . esc_html( $sku ) . '</td>';

			$html .= '<td class="ms-col-num"><input type="number" name="ms_regular_price" value="' . esc_attr( $regular_price ) . '" step="0.01" min="0"></td>';

			$html .= '<td class="ms-col-num"><input type="number" name="ms_sale_price" value="' . esc_attr( $sale_price ) . '" step="0.01" min="0"></td>';

			$html .= '<td class="ms-col-type"><select name="ms_type">';
			$html .= '<option value=""'      . selected( $type, '',      false ) . '>' . esc_html__( 'Стандартная', 'custom-prices-woocommerce' ) . '</option>';
			$html .= '<option value="opt"'   . selected( $type, 'opt',   false ) . '>' . esc_html__( 'За опт',      'custom-prices-woocommerce' ) . '</option>';
			$html .= '<option value="otrez"' . selected( $type, 'otrez', false ) . '>' . esc_html__( 'На отрез',    'custom-prices-woocommerce' ) . '</option>';
			$html .= '</select></td>';

			$html .= '<td class="ms-col-num"><input type="number" name="ms_price_otrez" value="' . esc_attr( $price_otrez ) . '" step="0.01" min="0"></td>';

			$html .= '<td class="ms-col-num"><input type="number" name="ms_price_opt" value="' . esc_attr( $price_opt ) . '" step="0.01" min="0"></td>';

			$html .= '<td class="ms-col-txt"><input type="text" name="ms_units" value="' . esc_attr( $units ) . '"></td>';

			$html .= '<td class="ms-col-num"><input type="number" name="ms_min_order" value="' . esc_attr( $min_order ) . '" min="1"></td>';

			$html .= '<td class="ms-col-num"><input type="number" name="ms_step_qty" value="' . esc_attr( $step_qty ) . '" min="1"></td>';

			$html .= '<td class="ms-col-num"><input type="number" name="ms_volume_unit" value="' . esc_attr( $volume_unit ) . '" step="0.01" min="0"></td>';

			$html .= '<td class="ms-col-actions">';
			$html .= '<button class="ms-save-btn button button-primary" data-product-id="' . esc_attr( $id ) . '">';
			$html .= esc_html__( 'Сохранить', 'custom-prices-woocommerce' );
			$html .= '</button>';
			$html .= '<span class="ms-save-status"></span>';
			$html .= '</td>';

			$html .= '</tr>';
		}

		$html .= '</tbody></table>';
		return $html;
	}

	/**
	 * AJAX: сохраняет поля одного товара и синхронизирует на все сайты.
	 */
	public function ajax_save_product() {
		check_ajax_referer( 'cp_ms_bulk_edit_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			wp_send_json_error( 'Invalid product' );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( 'Product not found' );
		}

		$sku = $product->get_sku();
		if ( empty( $sku ) ) {
			wp_send_json_error( __( 'Товар не имеет SKU. Синхронизация невозможна.', 'custom-prices-woocommerce' ) );
		}

		$regular_price = isset( $_POST['regular_price'] ) ? sanitize_text_field( wp_unslash( $_POST['regular_price'] ) ) : '';
		$sale_price    = isset( $_POST['sale_price'] )    ? sanitize_text_field( wp_unslash( $_POST['sale_price'] ) )    : '';

		if ( '' !== $regular_price ) {
			$product->set_regular_price( $regular_price );
		}
		if ( '' !== $sale_price ) {
			$product->set_sale_price( $sale_price );
		}
		$product->save();

		$meta_fields = array(
			'_custom_price_type' => 'type',
			'_price_otrez'       => 'price_otrez',
			'_price_opt'         => 'price_opt',
			'_units'             => 'units',
			'_min_order'         => 'min_order',
			'_step_quantity'     => 'step_qty',
			'_volume_unit'       => 'volume_unit',
		);

		$meta_data = array();
		foreach ( $meta_fields as $meta_key => $post_key ) {
			if ( isset( $_POST[ $post_key ] ) ) {
				$value = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) );
				update_post_meta( $product_id, $meta_key, $value );
				$meta_data[ $meta_key ] = $value;
			}
		}

		Custom_Prices_Sync::get_instance()->sync_to_all_sites( $sku, $regular_price, $sale_price, $product_id, $meta_data );

		wp_send_json_success( __( 'Цены и данные сохранены и синхронизированы', 'custom-prices-woocommerce' ) );
	}

	private function __clone() {}
	public function __wakeup() {}
}
