<?php
/**
 * Order details table — переопределённый плагином Custom Prices WooCommerce.
 * Добавлены колонки: «Единицы измерения» и «Общий объем / Площадь».
 * Данные custom_data берутся из order item meta (_custom_data), а не из сессии корзины.
 * Используется для: страницы «Детали заказа» в My Account, письм WooCommerce, и admin.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/order/order-details.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 8.5.0
 */

defined( 'ABSPATH' ) || exit;

$order = wc_get_order( $order_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

if ( ! $order ) {
	return;
}

$order_items           = $order->get_items( apply_filters( 'woocommerce_purchase_order_item_types', 'line_item' ) );
$show_purchase_note    = $order->has_status( apply_filters( 'woocommerce_purchase_note_order_statuses', array( 'completed', 'processing' ) ) );
$show_customer_details = is_user_logged_in() && $order->get_user_id() === get_current_user_id();
$downloads             = $order->get_downloadable_items();
$has_downloads         = ( count( $downloads ) > 0 );

do_action( 'woocommerce_order_details_before_order_table', $order );
?>

<h2 class="woocommerce-order-details__title"><?php esc_html_e( 'Order details', 'woocommerce' ); ?></h2>

<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
	<thead>
		<tr>
			<th class="woocommerce-table__product-name product-name"><?php esc_html_e( 'Product', 'woocommerce' ); ?></th>
			<th class="woocommerce-table__product-price product-price"><?php esc_html_e( 'Price', 'woocommerce' ); ?></th>
			<th class="woocommerce-table__product-quantity product-quantity"><?php esc_html_e( 'Quantity', 'woocommerce' ); ?></th>
			<th class="woocommerce-table__product-units product-units"><?php _e( 'Единицы измерения', 'custom-prices-woocommerce' ); ?></th>
			<th class="woocommerce-table__product-volume-square product-volume-square"><?php _e( 'Общий объем / Площадь', 'custom-prices-woocommerce' ); ?></th>
			<th class="woocommerce-table__product-table product-total"><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php
		do_action( 'woocommerce_order_details_before_order_table_items', $order );

		foreach ( $order_items as $item_id => $item ) {
			$product = $item->get_product();
			$product_id = $item->get_product_id();
			$type = get_post_meta( $product_id, '_custom_price_type', true );
			$custom_data = $item->get_meta( '_custom_data', true ) ?: [];
			$units = get_post_meta( $product_id, '_units', true ) ?: '-';
			$volume_square = '-';

			if ( $type == 'opt' ) {
				$volume_unit = (float) get_post_meta( $product_id, '_volume_unit', true );
				$volume_square = ( $volume_unit > 0 ) ? ( $volume_unit * $item->get_quantity() ) . ' ' . esc_html( $units ) : '-';
				$units = !empty( $custom_data['unit'] ) ? $custom_data['unit'] : $units;
			} elseif ( $type == 'otrez' ) {
				$width = !empty( $custom_data['width_value'] ) ? (float) $custom_data['width_value'] : 0;
				$square = 0;
				if ( !empty( $custom_data['is_otrez'] ) ) {
					$square = $item->get_quantity() * $width;
					$units = !empty( $custom_data['unit'] ) ? $custom_data['unit'] : 'м.п.';
				} else {
					$length = !empty( $custom_data['length'] ) ? (float) $custom_data['length'] : 0;
					$square = $item->get_quantity() * $width * $length;
					$units = !empty( $custom_data['unit'] ) ? $custom_data['unit'] : 'рулон';
				}
				$volume_square = $square > 0 ? $square . ' м²' : '-';
			}

			wc_get_template(
				'order/order-details-item.php',
				array(
					'order'              => $order,
					'item_id'            => $item_id,
					'item'               => $item,
					'show_purchase_note' => $show_purchase_note,
					'purchase_note'      => $product ? $product->get_purchase_note() : '',
					'product'            => $product,
				)
			);
		}

		do_action( 'woocommerce_order_details_after_order_table_items', $order );
		?>
	</tbody>

	<tfoot>
		<?php
		foreach ( $order->get_order_item_totals() as $key => $total ) {
			?>
					<tr>
						<th scope="row"><?php echo esc_html( $total['label'] ); ?></th>
						<td><?php echo wp_kses_post( $total['value'] ); ?></td>
					</tr>
					<?php
		}
		?>
		<?php if ( $order->get_customer_note() ) : ?>
			<tr>
				<th><?php esc_html_e( 'Note:', 'woocommerce' ); ?></th>
				<td><?php echo wp_kses_post( nl2br( wptexturize( $order->get_customer_note() ) ) ); ?></td>
			</tr>
		<?php endif; ?>
	</tfoot>
</table>

<?php if ( $show_customer_details ) : ?>
	<?php wc_get_template( 'order/order-details-customer.php', array( 'order' => $order ) ); ?>
<?php endif; ?>

<?php do_action( 'woocommerce_order_details_after_order_table', $order ); ?>