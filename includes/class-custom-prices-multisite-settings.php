<?php
/**
 * Страница настроек мультисайт-синхронизации в сетевой админке.
 *
 * Подменю «Настройки» под пунктом «Цены Multisite» (cp-ms-bulk-edit).
 * Управляет site_options: cp_ms_auto_sync, cp_ms_sync_regular_price,
 * cp_ms_sync_sale_price, cp_ms_debug_mode.
 *
 * @package Custom_Prices_Woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Класс Custom_Prices_Multisite_Settings
 */
class Custom_Prices_Multisite_Settings {

	/**
	 * Singleton instance.
	 *
	 * @var Custom_Prices_Multisite_Settings|null
	 */
	private static $instance = null;

	/**
	 * @return Custom_Prices_Multisite_Settings
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
		// Приоритет 11 — после создания главного меню (приоритет 10 у Bulk Edit)
		add_action( 'network_admin_menu',    array( $this, 'add_settings_page' ), 11 );
		add_action( 'admin_post_cp_ms_save_settings', array( $this, 'save_settings' ) );
		add_action( 'network_admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Регистрирует подменю «Настройки» под «Цены Multisite».
	 */
	public function add_settings_page() {
		add_submenu_page(
			'cp-ms-bulk-edit',
			__( 'Настройки синхронизации цен', 'custom-prices-woocommerce' ),
			__( 'Настройки', 'custom-prices-woocommerce' ),
			'manage_network',
			'cp-ms-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Рендерит страницу настроек.
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cp_ms_save_settings">
				<?php wp_nonce_field( 'cp_ms_settings', 'cp_ms_settings_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Автоматическая синхронизация', 'custom-prices-woocommerce' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="cp_ms_auto_sync" value="1" <?php checked( get_site_option( 'cp_ms_auto_sync', '1' ), '1' ); ?>>
								<?php esc_html_e( 'Автоматически синхронизировать цены при сохранении товара', 'custom-prices-woocommerce' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Если отключено, цены синхронизируются только через страницу массового редактирования.', 'custom-prices-woocommerce' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Синхронизировать обычную цену', 'custom-prices-woocommerce' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="cp_ms_sync_regular_price" value="1" <?php checked( get_site_option( 'cp_ms_sync_regular_price', '1' ), '1' ); ?>>
								<?php esc_html_e( 'Синхронизировать обычную цену (Regular Price)', 'custom-prices-woocommerce' ); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Синхронизировать цену со скидкой', 'custom-prices-woocommerce' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="cp_ms_sync_sale_price" value="1" <?php checked( get_site_option( 'cp_ms_sync_sale_price', '1' ), '1' ); ?>>
								<?php esc_html_e( 'Синхронизировать цену со скидкой (Sale Price)', 'custom-prices-woocommerce' ); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Отладочная информация', 'custom-prices-woocommerce' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="cp_ms_debug_mode" value="1" <?php checked( get_site_option( 'cp_ms_debug_mode' ), '1' ); ?>>
								<?php esc_html_e( 'Включить режим отладки (логирование в debug.log)', 'custom-prices-woocommerce' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Сохранить настройки', 'custom-prices-woocommerce' ) ); ?>
			</form>

			<hr>

			<h2><?php esc_html_e( 'Информация о сети', 'custom-prices-woocommerce' ); ?></h2>
			<table class="widefat">
				<tr>
					<th><?php esc_html_e( 'Количество сайтов в сети', 'custom-prices-woocommerce' ); ?></th>
					<td><?php echo esc_html( get_blog_count() ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'WooCommerce версия', 'custom-prices-woocommerce' ); ?></th>
					<td><?php echo esc_html( defined( 'WC_VERSION' ) ? WC_VERSION : __( 'Не установлен', 'custom-prices-woocommerce' ) ); ?></td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Обработчик сохранения настроек (admin-post.php).
	 */
	public function save_settings() {
		if ( ! isset( $_POST['cp_ms_settings_nonce'] ) || ! wp_verify_nonce( $_POST['cp_ms_settings_nonce'], 'cp_ms_settings' ) ) {
			wp_die( esc_html__( 'Ошибка безопасности', 'custom-prices-woocommerce' ) );
		}

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'У вас нет прав для этого действия', 'custom-prices-woocommerce' ) );
		}

		update_site_option( 'cp_ms_auto_sync',          isset( $_POST['cp_ms_auto_sync'] )          ? '1' : '0' );
		update_site_option( 'cp_ms_sync_regular_price', isset( $_POST['cp_ms_sync_regular_price'] ) ? '1' : '0' );
		update_site_option( 'cp_ms_sync_sale_price',    isset( $_POST['cp_ms_sync_sale_price'] )    ? '1' : '0' );
		update_site_option( 'cp_ms_debug_mode',         isset( $_POST['cp_ms_debug_mode'] )         ? '1' : '0' );

		wp_safe_redirect( add_query_arg(
			array(
				'page'    => 'cp-ms-settings',
				'updated' => '1',
			),
			network_admin_url( 'admin.php' )
		) );
		exit;
	}

	/**
	 * Выводит уведомление об успешном сохранении настроек.
	 */
	public function admin_notices() {
		if ( ! isset( $_GET['page'] ) || ! in_array( $_GET['page'], array( 'cp-ms-settings', 'cp-ms-bulk-edit' ), true ) ) {
			return;
		}

		if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Настройки сохранены.', 'custom-prices-woocommerce' ); ?></p>
			</div>
			<?php
		}
	}

	private function __clone() {}
	public function __wakeup() {}
}
