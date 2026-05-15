<?php
/**
 * Синхронизация настроек custom_prices_options между сайтами мультисайта.
 *
 * Страница в сетевой админке: Цены Multisite → Настройки Custom Prices.
 * Позволяет скопировать весь option custom_prices_options с любого сайта
 * на один или несколько других сайтов сети.
 *
 * @package Custom_Prices_Woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Класс Custom_Prices_Options_Sync
 */
class Custom_Prices_Options_Sync {

	/**
	 * Singleton instance.
	 *
	 * @var Custom_Prices_Options_Sync|null
	 */
	private static $instance = null;

	/**
	 * Nonce action.
	 *
	 * @var string
	 */
	private $nonce_action = 'cp_ms_options_sync_nonce';

	/**
	 * Человекочитаемые названия ключей custom_prices_options.
	 *
	 * @var array
	 */
	private $field_labels = array(
		'min_order_otrez'        => 'Мин. заказ на отрез',
		'min_order_opt'          => 'Мин. заказ опт',
		'step_otrez'             => 'Шаг кол-во (отрез)',
		'step_opt'               => 'Шаг кол-во (опт)',
		'attr_width'             => 'Атрибут ширины',
		'attr_length'            => 'Атрибут длины',
		'label_buy_btn'          => 'Кнопка «Купить»',
		'label_cart_btn'         => 'Кнопка «В корзину»',
		'label_tab_rrc'          => 'Таб: РРЦ',
		'label_tab_opt'          => 'Таб: ОПТ',
		'label_tab_rulon'        => 'Таб: Рулон',
		'label_tab_otrez'        => 'Таб: Отрез',
		'label_qty'              => 'Метка: Количество',
		'label_length_input'     => 'Метка: Длина отреза',
		'label_width_select'     => 'Метка: Ширина рулона',
		'label_total'            => 'Метка: Итого',
		'label_volume'           => 'Метка: Объём',
		'label_area'             => 'Метка: Площадь',
		'label_price_rrc'        => 'Цена РРЦ',
		'label_price_opt'        => 'Цена ОПТ',
		'label_price_rulon'      => 'Цена от рулона',
		'label_price_otrez'      => 'Цена на отрез',
		'label_price_std'        => 'Цена (стандартная)',
		'label_price_rrc_mobile'   => 'Цена РРЦ (мобайл)',
		'label_price_opt_mobile'   => 'Цена ОПТ (мобайл)',
		'label_price_rulon_mobile' => 'Цена от рулона (мобайл)',
		'label_price_otrez_mobile' => 'Цена на отрез (мобайл)',
		'suffix_currency'        => 'Суффикс валюты',
		'suffix_area'            => 'Суффикс площади',
		'suffix_width'           => 'Суффикс ширины',
	);

	/**
	 * @return Custom_Prices_Options_Sync
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
		// Приоритет 12 — после Настроек (11) и Bulk Edit (10)
		add_action( 'network_admin_menu',    array( $this, 'add_menu_page' ), 12 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_cp_ms_load_options', array( $this, 'ajax_load_options' ) );
		add_action( 'wp_ajax_cp_ms_sync_options', array( $this, 'ajax_sync_options' ) );
	}

	/**
	 * Регистрирует подменю «Настройки Custom Prices» под «Цены Multisite».
	 */
	public function add_menu_page() {
		add_submenu_page(
			'cp-ms-bulk-edit',
			__( 'Синхронизация настроек Custom Prices', 'custom-prices-woocommerce' ),
			__( 'Настройки Custom Prices', 'custom-prices-woocommerce' ),
			'manage_network',
			'cp-ms-options-sync',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Подключает JS и CSS только на этой странице.
	 *
	 * @param string $hook Текущий hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( false === strpos( $hook, 'cp-ms-options-sync' ) ) {
			return;
		}

		$plugin_url = plugin_dir_url( dirname( __FILE__ ) );
		$plugin_dir = plugin_dir_path( dirname( __FILE__ ) );

		// Переиспользуем CSS от bulk-edit (ms-section, ms-sites-list и пр.)
		$css_path = $plugin_dir . 'assets/css/admin-multisite-bulk-edit.css';
		wp_enqueue_style(
			'cp-ms-bulk-edit',
			$plugin_url . 'assets/css/admin-multisite-bulk-edit.css',
			array(),
			file_exists( $css_path ) ? filemtime( $css_path ) : '1.0'
		);

		$js_path = $plugin_dir . 'assets/js/admin-options-sync.js';
		wp_enqueue_script(
			'cp-ms-options-sync',
			$plugin_url . 'assets/js/admin-options-sync.js',
			array( 'jquery' ),
			file_exists( $js_path ) ? filemtime( $js_path ) : '1.0',
			true
		);

		wp_localize_script(
			'cp-ms-options-sync',
			'cpMsOptionsSync',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( $this->nonce_action ),
				'strings' => array(
					'loading'     => __( 'Загрузка...', 'custom-prices-woocommerce' ),
					'syncing'     => __( 'Синхронизация...', 'custom-prices-woocommerce' ),
					'synced'      => __( 'Синхронизировано', 'custom-prices-woocommerce' ),
					'error'       => __( 'Ошибка', 'custom-prices-woocommerce' ),
					'noTarget'    => __( 'Выберите хотя бы один целевой сайт', 'custom-prices-woocommerce' ),
					'noSource'    => __( 'Выберите сайт-источник', 'custom-prices-woocommerce' ),
					'confirm'     => __( 'Настройки Custom Prices на выбранных сайтах будут заменены. Продолжить?', 'custom-prices-woocommerce' ),
				),
			)
		);
	}

	/**
	 * Рендерит страницу синхронизации настроек.
	 */
	public function render_page() {
		$sites = get_sites( array( 'number' => 0 ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Синхронизация настроек Custom Prices', 'custom-prices-woocommerce' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Скопируйте настройки страницы «Custom Prices» с одного сайта на другие сайты мультисайта. Все поля custom_prices_options будут заменены.', 'custom-prices-woocommerce' ); ?>
			</p>

			<?php if ( count( $sites ) < 2 ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'В сети менее двух сайтов. Синхронизация недоступна.', 'custom-prices-woocommerce' ); ?></p></div>
				<?php return; ?>
			<?php endif; ?>

			<!-- ── Шаг 1: выбор источника ── -->
			<div class="ms-section">
				<h2><?php esc_html_e( 'Шаг 1 — Источник настроек', 'custom-prices-woocommerce' ); ?></h2>
				<div class="ms-bulk-header">
					<label for="cp-ms-source-site">
						<?php esc_html_e( 'Сайт-источник:', 'custom-prices-woocommerce' ); ?>
					</label>
					<select id="cp-ms-source-site">
						<option value="">— <?php esc_html_e( 'Выберите сайт', 'custom-prices-woocommerce' ); ?> —</option>
						<?php foreach ( $sites as $site ) : ?>
							<option value="<?php echo esc_attr( $site->blog_id ); ?>">
								<?php echo esc_html( get_blog_option( $site->blog_id, 'blogname' ) ); ?>
								(<?php echo esc_url( $site->domain . $site->path ); ?>)
							</option>
						<?php endforeach; ?>
					</select>
					<button id="cp-ms-load-btn" class="button" disabled>
						<?php esc_html_e( 'Загрузить настройки', 'custom-prices-woocommerce' ); ?>
					</button>
					<span class="spinner" id="cp-ms-load-spinner"></span>
				</div>

				<div id="cp-ms-preview" style="display:none; margin-top: 16px;">
					<h3><?php esc_html_e( 'Текущие настройки источника', 'custom-prices-woocommerce' ); ?></h3>
					<table class="widefat cp-ms-options-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Настройка', 'custom-prices-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Значение', 'custom-prices-woocommerce' ); ?></th>
							</tr>
						</thead>
						<tbody id="cp-ms-preview-body"></tbody>
					</table>
				</div>
			</div>

			<!-- ── Шаг 2: выбор целей ── -->
			<div class="ms-section" id="cp-ms-targets-section" style="display:none;">
				<h2><?php esc_html_e( 'Шаг 2 — Целевые сайты', 'custom-prices-woocommerce' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Выберите сайты, на которые нужно скопировать настройки. Настройки на этих сайтах будут полностью заменены.', 'custom-prices-woocommerce' ); ?>
				</p>

				<label style="font-weight:600; display:block; margin-bottom:8px;">
					<input type="checkbox" id="cp-ms-select-all">
					<?php esc_html_e( 'Выбрать / снять все', 'custom-prices-woocommerce' ); ?>
				</label>

				<ul class="ms-sites-list" id="cp-ms-target-list">
					<?php foreach ( $sites as $site ) : ?>
						<li>
							<label>
								<input type="checkbox" class="cp-ms-target-cb" value="<?php echo esc_attr( $site->blog_id ); ?>">
								<strong><?php echo esc_html( get_blog_option( $site->blog_id, 'blogname' ) ); ?></strong>
								<span class="ms-site-url">(<?php echo esc_url( $site->domain . $site->path ); ?>)</span>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>

				<div style="margin-top:16px; display:flex; align-items:center; gap:12px;">
					<button id="cp-ms-sync-btn" class="button button-primary">
						<?php esc_html_e( 'Синхронизировать настройки', 'custom-prices-woocommerce' ); ?>
					</button>
					<span class="spinner" id="cp-ms-sync-spinner"></span>
					<span id="cp-ms-sync-status"></span>
				</div>
			</div>

			<!-- ── Шаг 3: результат ── -->
			<div class="ms-section" id="cp-ms-results-section" style="display:none;">
				<h2><?php esc_html_e( 'Результат синхронизации', 'custom-prices-woocommerce' ); ?></h2>
				<table class="widefat" id="cp-ms-results-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Сайт', 'custom-prices-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Статус', 'custom-prices-woocommerce' ); ?></th>
						</tr>
					</thead>
					<tbody id="cp-ms-results-body"></tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX: загружает custom_prices_options с указанного сайта.
	 *
	 * POST: source_blog_id, nonce
	 * Returns: { options: {key: value, ...}, blog_name: '...' }
	 */
	public function ajax_load_options() {
		check_ajax_referer( $this->nonce_action, 'nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_send_json_error( array( 'message' => 'Access denied' ), 403 );
		}

		$source_blog_id = isset( $_POST['source_blog_id'] ) ? intval( $_POST['source_blog_id'] ) : 0;
		if ( ! $source_blog_id ) {
			wp_send_json_error( array( 'message' => 'Invalid blog_id' ), 400 );
		}

		switch_to_blog( $source_blog_id );
		$options   = get_option( 'custom_prices_options', array() );
		$blog_name = get_bloginfo( 'name' );
		restore_current_blog();

		wp_send_json_success( array(
			'options'   => $options,
			'blog_name' => $blog_name,
			'blog_id'   => $source_blog_id,
			'labels'    => $this->field_labels,
		) );
	}

	/**
	 * AJAX: копирует custom_prices_options с источника на выбранные сайты.
	 *
	 * POST: source_blog_id, target_sites[], nonce
	 * Returns: { synced: N, results: [{blog_id, site, status}] }
	 */
	public function ajax_sync_options() {
		check_ajax_referer( $this->nonce_action, 'nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_send_json_error( array( 'message' => 'Access denied' ), 403 );
		}

		$source_blog_id = isset( $_POST['source_blog_id'] ) ? intval( $_POST['source_blog_id'] ) : 0;
		$target_sites   = isset( $_POST['target_sites'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['target_sites'] ) ) : array();

		if ( ! $source_blog_id ) {
			wp_send_json_error( array( 'message' => 'Invalid source' ), 400 );
		}

		if ( empty( $target_sites ) ) {
			wp_send_json_error( array( 'message' => 'No target sites' ), 400 );
		}

		// Читаем настройки источника
		switch_to_blog( $source_blog_id );
		$options = get_option( 'custom_prices_options', array() );
		restore_current_blog();

		$results = array();

		foreach ( $target_sites as $blog_id ) {
			if ( $blog_id === $source_blog_id ) {
				continue; // источник не трогаем
			}

			switch_to_blog( $blog_id );

			$updated = update_option( 'custom_prices_options', $options );

			$results[] = array(
				'blog_id' => $blog_id,
				'site'    => get_bloginfo( 'name' ),
				'status'  => 'ok',
			);

			restore_current_blog();
		}

		wp_send_json_success( array(
			'synced'  => count( $results ),
			'results' => $results,
		) );
	}

	private function __clone() {}
	public function __wakeup() {}
}
