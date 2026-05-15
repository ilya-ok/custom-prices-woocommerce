/* global cpMsData, jQuery */
( function ( $ ) {
	'use strict';

	/**
	 * Загрузка товаров при выборе категории.
	 */
	function loadProducts() {
		var categoryId = $( this ).val();

		if ( ! categoryId ) {
			$( '#ms-products-container' ).hide().empty();
			$( '#ms-product-count' ).text( '' );
			return;
		}

		$( '.ms-loading' ).show();
		$( '#ms-products-container' ).hide().empty();
		$( '#ms-product-count' ).text( '' );

		$.ajax( {
			url:  cpMsData.ajaxurl,
			type: 'POST',
			data: {
				action:      'cp_ms_get_products',
				category_id: categoryId,
				nonce:       cpMsData.nonce,
			},
			success: function ( response ) {
				$( '.ms-loading' ).hide();

				if ( response.success ) {
					$( '#ms-products-container' ).html( response.data.html ).show();

					if ( response.data.count > 0 ) {
						$( '#ms-product-count' ).text( '(' + response.data.count + ' товаров)' );
					}
				} else {
					alert( 'Ошибка загрузки товаров' );
				}
			},
			error: function () {
				$( '.ms-loading' ).hide();
				alert( 'Ошибка AJAX запроса' );
			},
		} );
	}

	/**
	 * Сохранение одного товара с синхронизацией на все сайты.
	 */
	function saveProduct() {
		var $btn    = $( this );
		var $row    = $btn.closest( 'tr' );
		var $status = $row.find( '.ms-save-status' );

		var productId    = $btn.data( 'product-id' );
		var regularPrice = $row.find( 'input[name="ms_regular_price"]' ).val();
		var salePrice    = $row.find( 'input[name="ms_sale_price"]' ).val();
		var type         = $row.find( 'select[name="ms_type"]' ).val();
		var priceOtrez   = $row.find( 'input[name="ms_price_otrez"]' ).val();
		var priceOpt     = $row.find( 'input[name="ms_price_opt"]' ).val();
		var units        = $row.find( 'input[name="ms_units"]' ).val();
		var minOrder     = $row.find( 'input[name="ms_min_order"]' ).val();
		var stepQty      = $row.find( 'input[name="ms_step_qty"]' ).val();
		var volumeUnit   = $row.find( 'input[name="ms_volume_unit"]' ).val();

		$btn.prop( 'disabled', true ).text( 'Сохранение...' );
		$status.html( '<span class="spinner is-active" style="float:none;margin:0;"></span>' );

		$.ajax( {
			url:  cpMsData.ajaxurl,
			type: 'POST',
			data: {
				action:        'cp_ms_save_product',
				product_id:    productId,
				regular_price: regularPrice,
				sale_price:    salePrice,
				type:          type,
				price_otrez:   priceOtrez,
				price_opt:     priceOpt,
				units:         units,
				min_order:     minOrder,
				step_qty:      stepQty,
				volume_unit:   volumeUnit,
				nonce:         cpMsData.nonce,
			},
			success: function ( response ) {
				$btn.prop( 'disabled', false ).text( 'Сохранить' );

				if ( response.success ) {
					$status.html( '<span style="color:#46b450;">✓ ' + response.data + '</span>' );
					setTimeout( function () {
						$status.html( '' );
					}, 3000 );
				} else {
					$status.html( '<span style="color:#dc3232;">✗ ' + response.data + '</span>' );
				}
			},
			error: function () {
				$btn.prop( 'disabled', false ).text( 'Сохранить' );
				$status.html( '<span style="color:#dc3232;">✗ Ошибка сохранения</span>' );
			},
		} );
	}

	$( document ).ready( function () {
		$( '#ms-category-select' ).on( 'change', loadProducts );

		$( document ).on( 'click', '.ms-save-btn', saveProduct );

		// Enter в поле ввода = сохранение строки
		$( document ).on( 'keypress', '.ms-product-row input', function ( e ) {
			if ( 13 === e.which ) {
				e.preventDefault();
				$( this ).closest( 'tr' ).find( '.ms-save-btn' ).trigger( 'click' );
			}
		} );
	} );

} )( jQuery );
