<?php
/**
 * Автоматическая синхронизация цен и мета-полей товаров между сайтами мультисайта.
 *
 * Слушает woocommerce_update_product / woocommerce_new_product и при каждом сохранении
 * товара копирует его цены и кастомные мета-поля на все другие сайты сети по SKU.
 *
 * @package Custom_Prices_Woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Класс Custom_Prices_Sync
 */
class Custom_Prices_Sync {

	/**
	 * Singleton instance.
	 *
	 * @var Custom_Prices_Sync|null
	 */
	private static $instance = null;

	/**
	 * Флаг предотвращения рекурсии при switch_to_blog.
	 *
	 * @var bool
	 */
	private $is_syncing = false;

	/**
	 * @return Custom_Prices_Sync
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Конструктор: регистрация хуков.
	 */
	private function __construct() {
		add_action( 'woocommerce_update_product', array( $this, 'sync_product_price' ), 10, 1 );
		add_action( 'woocommerce_new_product',    array( $this, 'sync_product_price' ), 10, 1 );
		add_action( 'updated_post_meta',          array( $this, 'sync_on_meta_update' ), 10, 4 );
	}

	/**
	 * Запускает синхронизацию при сохранении товара в WooCommerce.
	 *
	 * @param int $product_id ID товара.
	 */
	public function sync_product_price( $product_id ) {
		if ( $this->is_syncing ) {
			return;
		}

		if ( ! get_site_option( 'cp_ms_auto_sync', '1' ) ) {
			return;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}

		$sku = $product->get_sku();
		if ( empty( $sku ) ) {
			return;
		}

		$regular_price = $product->get_regular_price();
		$sale_price    = $product->get_sale_price();

		$meta_fields = array(
			'_custom_price_type',
			'_price_otrez',
			'_price_opt',
			'_units',
			'_min_order',
			'_step_quantity',
			'_volume_unit',
		);

		$meta_data = array();
		foreach ( $meta_fields as $meta_key ) {
			$value = get_post_meta( $product_id, $meta_key, true );
			if ( '' !== $value ) {
				$meta_data[ $meta_key ] = $value;
			}
		}

		$this->sync_to_all_sites( $sku, $regular_price, $sale_price, $product_id, $meta_data );
	}

	/**
	 * Триггер синхронизации при быстром/массовом редактировании цен через мета.
	 *
	 * @param int    $meta_id    ID мета-записи.
	 * @param int    $object_id  ID объекта.
	 * @param string $meta_key   Ключ мета.
	 * @param mixed  $meta_value Значение.
	 */
	public function sync_on_meta_update( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( ! in_array( $meta_key, array( '_regular_price', '_sale_price', '_price' ), true ) ) {
			return;
		}

		$post_type = get_post_type( $object_id );
		if ( 'product' !== $post_type && 'product_variation' !== $post_type ) {
			return;
		}

		$this->sync_product_price( $object_id );
	}

	/**
	 * Синхронизирует цены и мета-данные на все сайты мультисайта по SKU.
	 *
	 * @param string $sku           SKU товара.
	 * @param string $regular_price Обычная цена.
	 * @param string $sale_price    Цена со скидкой.
	 * @param int    $exclude_id    ID товара на текущем сайте (для исключения).
	 * @param array  $meta_data     Кастомные мета-поля.
	 */
	public function sync_to_all_sites( $sku, $regular_price, $sale_price, $exclude_id = 0, $meta_data = array() ) {
		$this->is_syncing = true;

		$current_site_id = get_current_blog_id();
		$sites           = get_sites( array( 'number' => 0 ) );

		foreach ( $sites as $site ) {
			if ( (int) $site->blog_id === $current_site_id ) {
				continue;
			}

			switch_to_blog( $site->blog_id );

			$product_id = wc_get_product_id_by_sku( $sku );
			if ( $product_id ) {
				$this->update_product_prices( $product_id, $regular_price, $sale_price, $meta_data );
			}

			restore_current_blog();
		}

		$this->is_syncing = false;
	}

	/**
	 * Обновляет цены и мета-данные одного товара.
	 *
	 * @param int    $product_id    ID товара.
	 * @param string $regular_price Обычная цена.
	 * @param string $sale_price    Цена со скидкой.
	 * @param array  $meta_data     Дополнительные мета-поля.
	 */
	private function update_product_prices( $product_id, $regular_price, $sale_price, $meta_data = array() ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}

		if ( get_site_option( 'cp_ms_sync_regular_price', '1' ) ) {
			$product->set_regular_price( $regular_price );
		}

		if ( get_site_option( 'cp_ms_sync_sale_price', '1' ) ) {
			$product->set_sale_price( $sale_price );
		}

		$product->save();

		if ( ! empty( $meta_data ) ) {
			foreach ( $meta_data as $meta_key => $meta_value ) {
				update_post_meta( $product_id, $meta_key, $meta_value );
			}
		}
	}

	/**
	 * Массовая синхронизация: применяет массив обновлений на все сайты.
	 *
	 * @param array $price_updates [ sku => ['regular' => ..., 'sale' => ..., 'meta' => [...]] ]
	 * @return array ['success' => N, 'failed' => N, 'errors' => [...]]
	 */
	public function bulk_sync_prices( $price_updates ) {
		$results = array(
			'success' => 0,
			'failed'  => 0,
			'errors'  => array(),
		);

		foreach ( $price_updates as $sku => $prices ) {
			$regular_price = isset( $prices['regular'] ) ? $prices['regular'] : '';
			$sale_price    = isset( $prices['sale'] ) ? $prices['sale'] : '';
			$meta_data     = isset( $prices['meta'] ) ? $prices['meta'] : array();

			$product_id = wc_get_product_id_by_sku( $sku );
			if ( ! $product_id ) {
				$results['failed']++;
				$results['errors'][] = sprintf(
					/* translators: %s: SKU товара */
					__( 'Товар с SKU "%s" не найден', 'custom-prices-woocommerce' ),
					$sku
				);
				continue;
			}

			$this->update_product_prices( $product_id, $regular_price, $sale_price, $meta_data );
			$this->sync_to_all_sites( $sku, $regular_price, $sale_price, $product_id, $meta_data );

			$results['success']++;
		}

		return $results;
	}

	private function __clone() {}
	public function __wakeup() {}
}
