<?php
/**
 * Order Item Details — переопределённый плагином Custom Prices WooCommerce.
 * Одна строка товара в таблице заказа.
 * Добавлены колонки: «Единицы измерения» и «Общий объем / Площадь».
 * Обрабатывает возвраты: при наличии refunded_qty показывает зачёркнутое кол-во.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/order/order-details-item.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 5.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! apply_filters( 'woocommerce_order_item_visible', true, $item ) ) {
	return;
}

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
?>
<tr class="<?php echo esc_attr( apply_filters( 'woocommerce_order_item_class', 'woocommerce-table__line-item order_item', $item, $order ) ); ?>">

	<td class="woocommerce-table__product-name product-name">
		<?php
		$is_visible        = $product && $product->is_visible();
		$product_permalink = apply_filters( 'woocommerce_order_item_permalink', $is_visible ? $product->get_permalink( $item ) : '', $item, $order );

		echo wp_kses_post( apply_filters( 'woocommerce_order_item_name', $product_permalink ? sprintf( '<a href="%s">%s</a>', $product_permalink, $item->get_name() ) : $item->get_name(), $item, $is_visible ) );

		$qty          = $item->get_quantity();
		$refunded_qty = $order->get_qty_refunded_for_item( $item_id );

		if ( $refunded_qty ) {
			$qty_display = '<del>' . esc_html( $qty ) . '</del> <ins>' . esc_html( $qty - ( $refunded_qty * -1 ) ) . '</ins>';
		} else {
			$qty_display = esc_html( $qty );
		}

		echo apply_filters( 'woocommerce_order_item_quantity_html', ' <strong class="product-quantity">' . sprintf( '&times;&nbsp;%s', $qty_display ) . '</strong>', $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		do_action( 'woocommerce_order_item_meta_start', $item_id, $item, $order, false );

		wc_display_item_meta( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		do_action( 'woocommerce_order_item_meta_end', $item_id, $item, $order, false );
		?>
	</td>

	<td class="woocommerce-table__product-price product-price">
		<?php echo $order->get_formatted_line_subtotal( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</td>

	<td class="woocommerce-table__product-quantity product-quantity">
		<?php echo $qty_display; ?>
	</td>

	<td class="woocommerce-table__product-units product-units">
		<?php echo esc_html( $units ); ?>
	</td>

	<td class="woocommerce-table__product-volume-square product-volume-square">
		<?php echo $volume_square; ?>
	</td>

	<td class="woocommerce-table__product-total product-total">
		<?php echo $order->get_formatted_line_subtotal( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</td>

</tr>
<?php if ( $show_purchase_note && is_product() ) { ?>

	<tr class="woocommerce-table__product-purchase-note product-purchase-note">

		<td colspan="2"><?php echo wpautop( do_shortcode( wp_kses_post( $purchase_note ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>

	</tr>

<?php } ?>