/**
 * admin-bulk-edit.js — массовое редактирование цен товаров.
 *
 * 1. При смене категории — AJAX-загрузка таблицы товаров.
 * 2. При нажатии «Сохранить» — AJAX-сохранение полей одного товара
 *    с индикатором: Загрузка… → Выполнено ✓ / Ошибка.
 *
 * Глобальный объект cpBulkEdit (wp_localize_script):
 *   { ajax_url: string, nonce: string }
 */
jQuery( function( $ ) {

    var ajaxUrl = cpBulkEdit.ajax_url;
    var nonce   = cpBulkEdit.nonce;
    var $container = $('#cp-products-container');
    var $count     = $('#cp-product-count');
    var $bulkOps   = $('#cp-bulk-operations');
    var $bulkFields = $('#cp-bulk-fields');
    var currentCategoryId = null;

    /* -------------------------------------------------------------- */
    /* Смена категории → загрузка товаров                             */
    /* -------------------------------------------------------------- */

    $('#cp-category-select').on('change', function() {
        var catId = $(this).val();
        currentCategoryId = catId;

        if ( ! catId ) {
            $container.hide();
            $bulkOps.hide();
            $bulkFields.hide();
            $count.text('');
            return;
        }

        $container.show().html(
            '<p class="cp-loading">Загрузка товаров…</p>'
        );
        $count.text('');
        $bulkOps.show();
        $bulkFields.show();

        $.ajax({
            url:  ajaxUrl,
            type: 'POST',
            data: {
                action:      'cp_get_products',
                nonce:       nonce,
                category_id: catId
            },
            success: function( response ) {
                if ( response.success ) {
                    $container.html( response.data.html );

                    if ( response.data.count > 0 ) {
                        $count.text( '(' + response.data.count + ' товаров)' );
                    } else {
                        $count.text('');
                    }
                } else {
                    $container.html(
                        '<p class="cp-error">Ошибка загрузки товаров.</p>'
                    );
                }
            },
            error: function() {
                $container.html(
                    '<p class="cp-error">Ошибка соединения. Проверьте подключение.</p>'
                );
            }
        });
    });

    /* -------------------------------------------------------------- */
    /* Сохранение полей одного товара                                 */
    /* -------------------------------------------------------------- */

    $container.on('click', '.cp-save-btn', function() {
        var $btn    = $(this);
        var $row    = $btn.closest('tr');
        var $status = $btn.siblings('.cp-save-status');
        var productId = $btn.data('product-id');

        // Блокируем кнопку, показываем «Загрузка…»
        $btn.prop('disabled', true);
        setStatus( $status, 'loading', 'Загрузка…' );

        // Собираем значения полей из строки товара
        var data = {
            action:          'cp_save_product',
            nonce:           nonce,
            product_id:      productId,
            cp_type:         $row.find('[name="cp_type"]').val(),
            cp_regular_price: $row.find('[name="cp_regular_price"]').val(),
            cp_price_otrez:  $row.find('[name="cp_price_otrez"]').val(),
            cp_price_opt:    $row.find('[name="cp_price_opt"]').val(),
            cp_units:        $row.find('[name="cp_units"]').val(),
            cp_min_order:    $row.find('[name="cp_min_order"]').val(),
            cp_step_qty:     $row.find('[name="cp_step_qty"]').val(),
            cp_volume_unit:  $row.find('[name="cp_volume_unit"]').val()
        };

        $.ajax({
            url:  ajaxUrl,
            type: 'POST',
            data: data,
            success: function( response ) {
                if ( response.success ) {
                    setStatus( $status, 'done', 'Выполнено ✓' );
                } else {
                    setStatus( $status, 'error', 'Ошибка' );
                }
            },
            error: function() {
                setStatus( $status, 'error', 'Ошибка' );
            },
            complete: function() {
                $btn.prop('disabled', false);

                // Автоскрытие статуса через 3 секунды
                setTimeout( function() {
                    $status.text('').removeClass(
                        'cp-status-done cp-status-error cp-status-loading'
                    );
                }, 3000 );
            }
        });
    });

    /* -------------------------------------------------------------- */
    /* Утилита: установка статуса                                     */
    /* -------------------------------------------------------------- */

    function setStatus( $el, type, text ) {
        $el.text( text ).removeClass(
            'cp-status-loading cp-status-done cp-status-error'
        ).addClass( 'cp-status-' + type );
    }

    /* -------------------------------------------------------------- */
    /* Массовое применение цен                                        */
    /* -------------------------------------------------------------- */

    // Обновление примера и подсказки при изменении полей
    function updateBulkExample() {
        var operation = $('#cp-operation-type').val();
        var value     = parseFloat( $('#cp-operation-value').val() ) || 0;
        var target    = $('#cp-target-field').val();
        var basePrice = 1000; // Пример базовой цены

        // Обновляем подсказку (руб. или %)
        var hint = (operation === 'add_percent' || operation === 'subtract_percent') ? '%' : 'руб.';
        $('#cp-value-hint').text(hint);

        // Вычисляем результат
        var newPrice = calculateExamplePrice(basePrice, operation, value);

        // Название целевого поля
        var targetName = (target === 'opt') ? 'Цена опт' : 'Цена на отрез';

        // Обновляем текст примера
        $('#cp-example-text').text(
            'Стандартная цена товара: ' + basePrice + ' руб. → ' + targetName + ': ' + newPrice.toFixed(2) + ' руб.'
        );
    }

    // Вычисление цены для примера
    function calculateExamplePrice(basePrice, operation, value) {
        switch (operation) {
            case 'add':
                return basePrice + value;
            case 'subtract':
                return Math.max(0, basePrice - value);
            case 'add_percent':
                return basePrice + (basePrice * value / 100);
            case 'subtract_percent':
                return Math.max(0, basePrice - (basePrice * value / 100));
            default:
                return basePrice;
        }
    }

    // Обновляем пример при изменении любого поля
    $('#cp-operation-type, #cp-operation-value, #cp-target-field').on('change input', updateBulkExample);

    // Инициализация примера при загрузке страницы
    updateBulkExample();

    // Обработка клика по кнопке "Применить ко всем"
    $('#cp-apply-bulk-btn').on('click', function() {
        var $btn    = $(this);
        var $status = $('#cp-bulk-status');

        if (!currentCategoryId) {
            alert('Пожалуйста, выберите категорию');
            return;
        }

        var operation = $('#cp-operation-type').val();
        var value     = parseFloat( $('#cp-operation-value').val() );
        var target    = $('#cp-target-field').val();

        if (isNaN(value) || value < 0) {
            alert('Пожалуйста, введите корректное значение (больше или равно 0)');
            return;
        }

        if (!confirm('Вы уверены, что хотите применить эту операцию ко всем товарам выбранной категории? Это действие изменит цены всех товаров.')) {
            return;
        }

        // Блокируем кнопку
        $btn.prop('disabled', true);
        $status.show().find('.cp-status-text').text('Обработка товаров...').removeClass('cp-status-done cp-status-error').addClass('cp-status-loading');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action:         'cp_bulk_apply_price',
                nonce:          nonce,
                category_id:    currentCategoryId,
                operation_type: operation,
                value:          value,
                target_field:   target
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    var message = 'Успешно обновлено: ' + data.updated + ' товаров';
                    if (data.errors > 0) {
                        message += ' (ошибок: ' + data.errors + ')';
                    }
                    $status.find('.cp-status-text').text(message).removeClass('cp-status-loading cp-status-error').addClass('cp-status-done');

                    // Перезагружаем таблицу товаров
                    $('#cp-category-select').trigger('change');
                } else {
                    $status.find('.cp-status-text').text('Ошибка: ' + (response.data || 'Неизвестная ошибка')).removeClass('cp-status-loading cp-status-done').addClass('cp-status-error');
                }
            },
            error: function() {
                $status.find('.cp-status-text').text('Ошибка соединения').removeClass('cp-status-loading cp-status-done').addClass('cp-status-error');
            },
            complete: function() {
                $btn.prop('disabled', false);

                // Автоскрытие статуса через 5 секунд
                setTimeout(function() {
                    $status.fadeOut();
                }, 5000);
            }
        });
    });

    /* -------------------------------------------------------------- */
    /* Массовое применение дополнительных полей                       */
    /* -------------------------------------------------------------- */

    $('#cp-apply-fields-btn').on('click', function() {
        var $btn    = $(this);
        var $status = $('#cp-fields-status');

        if (!currentCategoryId) {
            alert('Пожалуйста, выберите категорию');
            return;
        }

        // Собираем значения полей
        var type      = $('#cp-bulk-type').val();
        var units     = $('#cp-bulk-units').val().trim();
        var minOrder  = $('#cp-bulk-min-order').val().trim();
        var stepQty   = $('#cp-bulk-step-qty').val().trim();
        var volume    = $('#cp-bulk-volume').val().trim();

        // Проверяем, что хотя бы одно поле заполнено
        if (!type && !units && !minOrder && !stepQty && !volume) {
            alert('Пожалуйста, заполните хотя бы одно поле для изменения');
            return;
        }

        // Формируем сообщение для подтверждения
        var fieldsToUpdate = [];
        if (type) fieldsToUpdate.push('Тип цены');
        if (units) fieldsToUpdate.push('Ед. изм.');
        if (minOrder) fieldsToUpdate.push('Мин. заказ');
        if (stepQty) fieldsToUpdate.push('Шаг кол-во');
        if (volume) fieldsToUpdate.push('Объём ед.');

        var confirmMsg = 'Вы уверены, что хотите изменить следующие поля для всех товаров категории?\n\n' +
                         fieldsToUpdate.join(', ') + '\n\n' +
                         'Это действие изменит данные всех товаров выбранной категории.';

        if (!confirm(confirmMsg)) {
            return;
        }

        // Блокируем кнопку
        $btn.prop('disabled', true);
        $status.show().find('.cp-status-text').text('Обработка товаров...').removeClass('cp-status-done cp-status-error').addClass('cp-status-loading');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action:       'cp_bulk_apply_fields',
                nonce:        nonce,
                category_id:  currentCategoryId,
                type:         type,
                units:        units,
                min_order:    minOrder,
                step_qty:     stepQty,
                volume:       volume
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    var message = 'Успешно обновлено: ' + data.updated + ' товаров';

                    // Добавляем информацию о том, какие поля были обновлены
                    if (data.fields_updated && data.fields_updated.length > 0) {
                        var fieldsNames = {
                            'type': 'Тип цены',
                            'units': 'Ед. изм.',
                            'min_order': 'Мин. заказ',
                            'step_qty': 'Шаг кол-во',
                            'volume': 'Объём ед.'
                        };
                        var updatedFields = data.fields_updated.map(function(f) {
                            return fieldsNames[f] || f;
                        });
                        message += ' (изменено: ' + updatedFields.join(', ') + ')';
                    }

                    $status.find('.cp-status-text').text(message).removeClass('cp-status-loading cp-status-error').addClass('cp-status-done');

                    // Перезагружаем таблицу товаров
                    $('#cp-category-select').trigger('change');

                    // Очищаем поля формы
                    $('#cp-bulk-type').val('');
                    $('#cp-bulk-units').val('');
                    $('#cp-bulk-min-order').val('');
                    $('#cp-bulk-step-qty').val('');
                    $('#cp-bulk-volume').val('');
                } else {
                    $status.find('.cp-status-text').text('Ошибка: ' + (response.data || 'Неизвестная ошибка')).removeClass('cp-status-loading cp-status-done').addClass('cp-status-error');
                }
            },
            error: function() {
                $status.find('.cp-status-text').text('Ошибка соединения').removeClass('cp-status-loading cp-status-done').addClass('cp-status-error');
            },
            complete: function() {
                $btn.prop('disabled', false);

                // Автоскрытие статуса через 5 секунд
                setTimeout(function() {
                    $status.fadeOut();
                }, 5000);
            }
        });
    });

} );
