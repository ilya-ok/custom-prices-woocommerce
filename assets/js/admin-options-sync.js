/* global cpMsOptionsSync, jQuery */
( function ( $ ) {
	'use strict';

	var cfg     = window.cpMsOptionsSync || {};
	var strings = cfg.strings || {};
	var ajaxUrl = cfg.ajaxurl || '';
	var nonce   = cfg.nonce || '';

	var sourceBlogId = 0;
	var sourceOptions = {};

	// =========================================================================
	// Шаг 1: выбор источника
	// =========================================================================

	$( '#cp-ms-source-site' ).on( 'change', function () {
		sourceBlogId = parseInt( $( this ).val(), 10 ) || 0;
		$( '#cp-ms-load-btn' ).prop( 'disabled', ! sourceBlogId );

		// Сбрасываем при смене источника
		$( '#cp-ms-preview' ).hide();
		$( '#cp-ms-targets-section' ).hide();
		$( '#cp-ms-results-section' ).hide();

		// Снимаем отметку с источника в списке целей
		updateTargetList( sourceBlogId );
	} );

	$( '#cp-ms-load-btn' ).on( 'click', function () {
		if ( ! sourceBlogId ) {
			alert( strings.noSource );
			return;
		}
		loadOptions( sourceBlogId );
	} );

	function loadOptions( blogId ) {
		var $btn     = $( '#cp-ms-load-btn' );
		var $spinner = $( '#cp-ms-load-spinner' );

		$btn.prop( 'disabled', true );
		$spinner.addClass( 'is-active' );
		$( '#cp-ms-preview' ).hide();
		$( '#cp-ms-targets-section' ).hide();
		$( '#cp-ms-results-section' ).hide();

		$.post( ajaxUrl, {
			action:         'cp_ms_load_options',
			source_blog_id: blogId,
			nonce:          nonce,
		} )
		.done( function ( response ) {
			if ( response && response.success ) {
				sourceOptions = response.data.options || {};
				renderPreview( sourceOptions, response.data.labels || {} );
				updateTargetList( blogId );
				$( '#cp-ms-preview' ).show();
				$( '#cp-ms-targets-section' ).show();
			} else {
				alert( strings.error + ': ' + ( response && response.data && response.data.message ? response.data.message : '' ) );
			}
		} )
		.fail( function () {
			alert( strings.error );
		} )
		.always( function () {
			$spinner.removeClass( 'is-active' );
			$btn.prop( 'disabled', false );
		} );
	}

	/**
	 * Рендерит таблицу предпросмотра настроек источника.
	 */
	function renderPreview( options, labels ) {
		var $tbody = $( '#cp-ms-preview-body' ).empty();

		var keys = Object.keys( labels );
		if ( ! keys.length ) {
			keys = Object.keys( options );
		}

		for ( var i = 0; i < keys.length; i++ ) {
			var key   = keys[ i ];
			var label = labels[ key ] || key;
			var value = options.hasOwnProperty( key ) ? options[ key ] : '';
			if ( value === '' || value === null || value === undefined ) {
				value = '<em style="color:#aaa;">—</em>';
			} else {
				value = escHtml( String( value ) );
			}

			$tbody.append(
				'<tr>' +
				'<td style="width:260px;color:#555;">' + escHtml( label ) + '</td>' +
				'<td>' + value + '</td>' +
				'</tr>'
			);
		}
	}

	/**
	 * Обновляет список целей: скрывает/показывает источник.
	 */
	function updateTargetList( sourceBlogId ) {
		$( '#cp-ms-target-list .cp-ms-target-cb' ).each( function () {
			var blogId = parseInt( $( this ).val(), 10 );
			if ( blogId === sourceBlogId ) {
				$( this ).prop( 'checked', false ).prop( 'disabled', true ).closest( 'li' ).css( 'opacity', '0.4' );
			} else {
				$( this ).prop( 'disabled', false ).closest( 'li' ).css( 'opacity', '' );
			}
		} );
	}

	// =========================================================================
	// Шаг 2: выбор целей
	// =========================================================================

	$( '#cp-ms-select-all' ).on( 'change', function () {
		var checked = $( this ).prop( 'checked' );
		$( '.cp-ms-target-cb:not(:disabled)' ).prop( 'checked', checked );
	} );

	// Синхронизация выбранных целей
	$( '#cp-ms-sync-btn' ).on( 'click', function () {
		var targets = [];
		$( '.cp-ms-target-cb:checked' ).each( function () {
			targets.push( parseInt( $( this ).val(), 10 ) );
		} );

		if ( ! sourceBlogId ) {
			alert( strings.noSource );
			return;
		}

		if ( ! targets.length ) {
			alert( strings.noTarget );
			return;
		}

		if ( ! window.confirm( strings.confirm ) ) {
			return;
		}

		syncOptions( sourceBlogId, targets );
	} );

	function syncOptions( blogId, targets ) {
		var $btn     = $( '#cp-ms-sync-btn' );
		var $spinner = $( '#cp-ms-sync-spinner' );
		var $status  = $( '#cp-ms-sync-status' );

		$btn.prop( 'disabled', true );
		$spinner.addClass( 'is-active' );
		$status.text( strings.syncing ).removeClass( 'cp-ms-success cp-ms-error' );
		$( '#cp-ms-results-section' ).hide();

		var data = {
			action:         'cp_ms_sync_options',
			source_blog_id: blogId,
			nonce:          nonce,
		};

		// Передаём массив целевых сайтов
		for ( var i = 0; i < targets.length; i++ ) {
			data[ 'target_sites[' + i + ']' ] = targets[ i ];
		}

		$.post( ajaxUrl, data )
		.done( function ( response ) {
			if ( response && response.success ) {
				var d = response.data;
				$status.text( strings.synced + ': ' + d.synced ).addClass( 'cp-ms-success' );
				renderResults( d.results );
				$( '#cp-ms-results-section' ).show();
			} else {
				var msg = response && response.data && response.data.message ? response.data.message : strings.error;
				$status.text( strings.error + ': ' + msg ).addClass( 'cp-ms-error' );
			}
		} )
		.fail( function () {
			$status.text( strings.error ).addClass( 'cp-ms-error' );
		} )
		.always( function () {
			$spinner.removeClass( 'is-active' );
			$btn.prop( 'disabled', false );
		} );
	}

	// =========================================================================
	// Шаг 3: результат
	// =========================================================================

	function renderResults( results ) {
		var $tbody = $( '#cp-ms-results-body' ).empty();

		for ( var i = 0; i < results.length; i++ ) {
			var r = results[ i ];
			var statusHtml = r.status === 'ok'
				? '<span style="color:#2e6b0a;">&#10003; ' + escHtml( 'Скопировано' ) + '</span>'
				: '<span style="color:#d63638;">&#10007; ' + escHtml( r.status ) + '</span>';

			$tbody.append(
				'<tr>' +
				'<td><strong>' + escHtml( r.site ) + '</strong></td>' +
				'<td>' + statusHtml + '</td>' +
				'</tr>'
			);
		}
	}

	// =========================================================================
	// Хелперы
	// =========================================================================

	function escHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

} )( jQuery );
