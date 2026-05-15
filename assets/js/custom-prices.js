/**
 * custom-prices.js — основной JS плагина Custom Prices WooCommerce.
 *
 * Секции файла:
 *  1. Popup на листинге (строки ~1-59)      — открытие модального окна товара через Magnific Popup + AJAX
 *  2. attachEvents() (строки ~61-165)       — привязка событий: +/-, input change, add-to-cart AJAX
 *  3. Инициализация на single (строки ~167-190) — инициализация табов для товаров уже на странице
 *  4. calculate_total() (строки ~197-251)   — расчёт «Итого» и «Объём/Площадь» в реальном времени
 *  5. Калькулятор площади (строки ~254-287) — отдельный калькулятор для PDF-раздела
 *  6. PDF генерация (строки ~290-815)       — создание коммерческого предложения через pdfMake
 *
 * Зависимости: jQuery, jQuery UI Tabs, Magnific Popup, pdfMake.
 * Данные товара передаются через wp_localize_script:
 *  - custom_prices_global        — { ajax_url, nonce }
 *  - custom_product_data_{ID}    — { type, min_order, step, prices, widths, length, ... }
 */
jQuery(function($) {
    // === СЕКЦИЯ 1: Popup товара на листинге ===
    // При клике на кнопку «Купить» открываем Magnific Popup и загружаем HTML товара через AJAX
    $(document).on('click', '.popup-link', function(e) {
        e.preventDefault();
        var product_id = $(this).data('product_id');
        var popupContent = '<div class="popup-loader">Загрузка...</div>';

        $.magnificPopup.open({
            items: {
                src: '<div class="custom-popup">' + popupContent + '</div>',
                type: 'inline'
            },
            midClick: true,
            removalDelay: 300,
            mainClass: 'mfp-fade',
            callbacks: {
                open: function() {
                    $.ajax({
                        url: custom_prices_global.ajax_url, // Используем глобальный объект
                        type: 'POST',
                        data: {
                            action: 'get_product_popup',
                            nonce: custom_prices_global.nonce, // Используем глобальный nonce
                            product_id: product_id
                        },
                        success: function(response) {
                            if (response.success) {
                                $('.custom-popup').html(response.data.html);
                                // Инициализируем табы и события после загрузки
                                var container = $('.custom-tabs');
                                var data = window['custom_product_data_' + product_id];
                                container.tabs({
                                    activate: function(event, ui) {
                                        var active_tab_id = ui.newPanel.attr('id');
                                        if (data && data.type === 'opt') {
                                            container.find('input[name="qty_rrc"]').val(active_tab_id === 'tab-rrc' ? 1 : 0);
                                            container.find('input[name="qty_opt"]').val(active_tab_id === 'tab-opt' ? data.min_order : 0);
                                        } else if (data && data.type === 'otrez') {
                                            container.find('input[name="qty_otrez"]').val(active_tab_id === 'tab-otrez' ? data.min_order : 0);
                                            container.find('input[name="qty_rulon"]').val(active_tab_id === 'tab-rulon' ? 1 : 0);
                                            container.find('input[name="width"]').prop('checked', false);
                                            container.find('.error-block').hide();
                                        }
                                        calculate_total(container, data);
                                    }
                                });
                                attachEvents(container, data, product_id);
                            } else {
                                alert(response.data.message);
                            }
                        },
                        error: function() {
                            alert('Ошибка загрузки попапа');
                        }
                    });
                }
            }
        });
    });

    // === СЕКЦИЯ 2: Привязка событий к форме товара ===
    // Вызывается после загрузки popup или инициализации формы на single-странице.
    // container — jQuery-элемент .custom-tabs
    // data      — объект custom_product_data_{id} (из wp_localize_script)
    function attachEvents(container, data, product_id) {
        if (!data) {
            console.error('Product data not found');
            return;
        }

        // Кнопки + и - для количества
        container.find('.quantity-buttons .minus').on('click', function() {
            var input = $(this).next('.qty-input');
            var val = parseFloat(input.val());
            var min = parseFloat(input.attr('min')) || 1;
            var step = parseFloat(input.attr('step')) || 1;
            if (val - step >= min) {
                input.val(val - step).trigger('change');
            }
        });

        container.find('.quantity-buttons .plus').on('click', function() {
            var input = $(this).prev('.qty-input');
            var val = parseFloat(input.val());
            var step = parseFloat(input.attr('step')) || 1;
            input.val(val + step).trigger('change');
        });

        // При изменении количества или ширины: сбрасываем qty в неактивных табах,
        // показываем/скрываем error-block если ширина не выбрана, и пересчитываем total
        container.find('.qty-input, input[name="width"]').on('input change', function() {
            var active_tab = container.find('.ui-tabs-panel:visible');
            var other_inputs = container.find('.qty-input').not(active_tab.find('.qty-input'));
            other_inputs.val(0);

            if (data.type === 'otrez' && $(this).is('.qty-input') && !container.find('input[name="width"]:checked').length && parseFloat($(this).val()) > 0) {
                container.find('.error-block').show();
            } else {
                container.find('.error-block').hide();
            }

            calculate_total(container, data);
        });

        // Клик «В корзину» → AJAX add_to_cart_custom.
        // После успеха: триггер added_to_cart (обновляет корзину), закрываем popup если на листинге.
        container.find('.add-to-cart-ajax').click(function(e) {
            e.preventDefault();
            var button = $(this);
            var current_product_id = button.data('product_id');
            button.find('.loader').show();
            button.prop('disabled', true);
            var qty_rrc = parseFloat(container.find('input[name="qty_rrc"]').val() || 0);
            var qty_opt = parseFloat(container.find('input[name="qty_opt"]').val() || 0);
            var qty_otrez = parseFloat(container.find('input[name="qty_otrez"]').val() || 0);
            var qty_rulon = parseFloat(container.find('input[name="qty_rulon"]').val() || 0);
            var width = container.find('input[name="width"]:checked').val() || '';
            var width_value = parseFloat(container.find('input[name="width"]:checked').data('value') || 0);

            if (data.type === 'otrez' && (qty_otrez > 0 || qty_rulon > 0) && !width) {
                container.find('.error-block').show();
                button.find('.loader').hide();
                button.prop('disabled', false);
                return;
            }

            $.ajax({
                url: data.ajax_url,
                type: 'POST',
                data: {
                    action: 'add_to_cart_custom',
                    nonce: data.nonce,
                    product_id: current_product_id,
                    qty_rrc: qty_rrc,
                    qty_opt: qty_opt,
                    qty_otrez: qty_otrez,
                    qty_rulon: qty_rulon,
                    width: width,
                    width_value: width_value,
                    length: data.length,
                    unit: data.units
                },
                success: function(response) {
                    button.find('.loader').hide();
                    if (response.success) {
                        button.text('Товар добавлен');
                        button.find('.checkmark').show();
                        setTimeout(function() {
                            button.text('Купить');
                            button.find('.checkmark').hide();
                            button.prop('disabled', false);
                        }, 2000);
                        $(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash]);

                        // Закрываем попап только если это листинг (не single product)
                        if (container.attr('data-is-single') === 'false') {
                            $.magnificPopup.close();
                        }
                    } else {
                        alert(response.data.message);
                        button.prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Ajax error:', error);
                    alert('Ошибка Ajax: ' + error);
                    button.find('.loader').hide();
                    button.prop('disabled', false);
                }
            });
        });
    }

    // === СЕКЦИЯ 3: Инициализация табов для single-страницы товара ===
    // Формы уже есть в HTML (не загружены через AJAX). Инициализируем jQuery UI Tabs и события.
    $('.custom-tabs').each(function() {
        var container = $(this);
        var product_id = container.closest('.product-popup__content').data('product_id') || container.closest('.product').data('product_id');
        var data = window['custom_product_data_' + product_id];
        if (data) {
            container.tabs({
                activate: function(event, ui) {
                    var active_tab_id = ui.newPanel.attr('id');
                    if (data.type === 'opt') {
                        container.find('input[name="qty_rrc"]').val(active_tab_id === 'tab-rrc' ? 1 : 0);
                        container.find('input[name="qty_opt"]').val(active_tab_id === 'tab-opt' ? data.min_order : 0);
                    } else if (data.type === 'otrez') {
                        container.find('input[name="qty_otrez"]').val(active_tab_id === 'tab-otrez' ? data.min_order : 0);
                        container.find('input[name="qty_rulon"]').val(active_tab_id === 'tab-rulon' ? 1 : 0);
                        container.find('input[name="width"]').prop('checked', false);
                        container.find('.error-block').hide();
                    }
                    calculate_total(container, data);
                }
            });
            attachEvents(container, data, product_id);
        }
    });


    // Форматирование числа с разделителями тысяч (пробелы): 1234567 → «1 234 567»
    function numberWithSpaces(x) {
        return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, " ");
    }

    // === СЕКЦИЯ 4: Расчёт «Итого» и «Объём/Площадь» ===
    // Вызывается при любом изменении в форме. Читает активный таб и обновляет DOM.
    //
    // opt/РРЦ:     total = price_standard × qty,  volume = volume_unit × qty
    // opt/ОПТ:     total = price_extra × qty,     volume = volume_unit × qty
    // otrez/отрез: total = qty × width × price_extra,           square = qty × width
    // otrez/рулон: total = qty × width × length × price_standard, square = qty × width × length
    function calculate_total(container, data) {
        if (!data) return;

        var total = 0;
        var volume = 0;
        var square = 0;
        var qty = 0;
        var price = 0;

        var active_tab_id = container.find('.ui-tabs-panel:visible').attr('id');

        if (data.type === 'opt') {
            if (active_tab_id === 'tab-rrc') {
                qty = parseFloat(container.find('input[name="qty_rrc"]').val() || 0);
                price = data.price_standard;
            } else {
                qty = parseFloat(container.find('input[name="qty_opt"]').val() || 0);
                price = data.price_extra;
            }
            total = qty * price;
            volume = qty * data.volume_unit;

            total = total.toFixed(0);
            volume = volume.toFixed(0);

            total = numberWithSpaces(total);
            volume = numberWithSpaces(volume);

            container.find('.total-price span.value').text(total);
            if (data.volume_unit > 0) {
                container.find('.total-volume span.value').text(volume);
            }
        } else if (data.type === 'otrez') {
            var width = parseFloat(container.find('input[name="width"]:checked').data('value') || 0);
            if (active_tab_id === 'tab-otrez') {
                qty = parseFloat(container.find('input[name="qty_otrez"]').val() || 0);
                total = qty * width * data.price_extra;
                square = qty * width;
            } else {
                qty = parseFloat(container.find('input[name="qty_rulon"]').val() || 0);
                var area = width * data.length * qty;
                total = area * data.price_standard;
                square = area;
            }

            total = total.toFixed(0);
            square = square.toFixed(0);

            total = numberWithSpaces(total);
            square = numberWithSpaces(square);
            
            container.find('.total-price span.value').text(total);
            container.find('.total-square span.value').text(square);
        }
    }


    // === СЕКЦИЯ 5: Калькулятор площади для PDF-раздела ===
    // Отдельный калькулятор на странице товара: при изменении area_1/area_2 пересчитывает
    // площадь поля и стоимость (используется для генерации PDF КП)
    $('body').on('change', '.custom-quantity-fields-calculator input', function(e) {

        var $container = $(this).closest('.custom-quantity-fields-calculator');
        
        var area_1 = $container.find('input[name=area_1]').val();
        var area_2 = $container.find('input[name=area_2]').val();
        
        console.log('area_1 - ' + area_1);
        console.log('area_2 - ' + area_2);

        var area = area_1*area_2;
        console.log('area - ' + area);
        $('.pdf_area_total').text(area);

        var price = $('#price_per_m2_cut').data('price_per_m2_cut')*1;
        var pdf_total = price*area;
        $('.pdf_total').text(pdf_total);

        var length = $('.pa_dlina').data('length');
        console.log('length - ' + length);

        var shirina = $container.find('input[name=shirina]:checked').val();
        console.log('shirina - ' + shirina);

        

        if(shirina) {
            $('.pdf_attr_error').hide();

        } else {
            $('.pdf_attr_error').show();
        }

    });


// === СЕКЦИЯ 6: PDF коммерческое предложение ===
// Генерация PDF КП для товаров искусственной травы через pdfMake.
// Попап открывается по ссылке #popup_pdf_download, генерация запускается по клику .pdf_download.
//
// Расчёт в PDF включает:
//  - Стоимость искусственной травы (площадь × цена за м²)
//  - Разметка поля (зависит от вида спорта: футбол/мини-футбол/теннис/хоккей)
//  - Шовная лента (расчёт стыков рулонов × 65 руб/п.м × 1.05)
//  - Клей (банки по 10, 4000 руб/банка)
//  - Кварцевый песок (коэффициент зависит от высоты ворса, 3950 руб/тонна)
//  - Резиновая крошка (коэффициент зависит от высоты ворса, 24500 руб/тонна)
//
// Изображения (логотип, печать, подпись) встроены как base64 дальше в файле.
$('a[href="#popup_pdf_download"]').magnificPopup({
    type: 'inline',

    fixedContentPos: false,
    fixedBgPos: true,

    overflowY: 'auto',

    closeBtnInside: true,
    preloader: false,
        
    midClick: true,
    removalDelay: 300,
    mainClass: 'my-mfp-slide-bottom',

/*
    preloader: true,
    tLoading: '<i class="fas fa-spinner"></i>',
    callbacks: {

    }
*/

});


    // Клик «Скачать КП» → собираем данные из DOM, считаем все статьи, генерируем PDF
    $('body').on('click', '.pdf_download', function(e) {
        e.preventDefault(); // Предотвращаем стандартное поведение кнопки

        var logo = 'data:image/png;base64,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';
        var print = 'data:image/png;base64,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';
        var signature = 'data:image/png;base64,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';

        var h1 = $('h1').text().trim();

        var tab = $('.calculator_cat_wrap input:checked').val();

        var $container = $(this).closest('.custom-quantity-fields-calculator');
        var dlina = $('.filter_product.selected').data("dlina");
        dlina = dlina.slice(0, -2)*1;
        var shirina = $('input[name=shirina]:checked').val()*1;

        console.log('shirina - ' + shirina);

        var atrrRow = [];
        var atrrBody = [];
        $('.woocommerce-product-attributes-item').each(function () {
            var atrrRow = [];
            var first = $(this).find('.woocommerce-product-attributes-item__label').text().trim();
            var last = $(this).find('.woocommerce-product-attributes-item__value').text().trim();

            atrrRow.push( {text: first, bold: true }, {text: last} );
            atrrBody.push(atrrRow);
        });

        var imgToExport = document.getElementById('pdf_img');
        var canvas = document.createElement('canvas');
        canvas.width = imgToExport.width; 
        canvas.height = imgToExport.height; 
        canvas.getContext('2d').drawImage(imgToExport, 0, 0);
        var pdfImg = canvas.toDataURL('image/png');

        var area1 = $('.calculator_area-tab input[name=area_2]').val()*1;
        var area2 = $('.calculator_area-tab input[name=area_1]').val()*1;
        var area = area1*area2;

        var price = $('#price_per_m2_roll').data('price_per_m2_roll')*1;

        var vors_height = $('.filter_product.selected').data('vysota');
        vors_height = vors_height = vors_height.slice(0, -2)*1;

        var totalBody = [];
        var totalHead = [];
        totalHead.push(
            { text: 'Наименование', fillColor: '#8bc73f', bold: true }, 
            { text: 'Ед.изм.', fillColor: '#8bc73f', bold: true }, 
            { text: 'Объем', fillColor: '#8bc73f', bold: true }, 
            { text: 'Цена', fillColor: '#8bc73f', bold: true }, 
            { text: 'Сумма', fillColor: '#8bc73f', bold: true } 
        );
        var totalRow = [];
        totalRow.push(
            { text: h1, alignment:'left' }, 
            { text: 'м2', alignment:'center', margin: [0, 10, 0, 0] }, 
            { text: area, alignment:'center', margin: [0, 10, 0, 0] }, 
            { text: price + ' руб.', alignment:'center', margin: [0, 10, 0, 0] }, 
            { text: price*area + ' руб.', alignment:'center', margin: [0, 10, 0, 0] } 
        );
        totalBody.push(totalHead);
        totalBody.push(totalRow);


        var total = area*price;

                            var totalMarkup = [];
                            var totalGlue = [];
                            var totalLenta = [];
                            var totalPesok = [];
                            var totalKroshka = [];


                            // --- Разметка поля (опционально) ---
                            // markup_count = площадь разметки (м²), зависит от вида спорта (tab)
                            // tab: 48=футбол, 49=мини-футбол, 50=хоккей, 51=теннис
                            // Если выбрана лента — +3% к площади разметки
                            if ($('input[name=markup]').prop("checked")) {

                                var markup;
                                var markup_count;

                                if(tab == 48) {                                     // Футбол
                                    markup_count = (area1*2+area2*3+314);
                                    markup = markup_count*0.10;
                                } else if(tab == 49) {                              // Мини-Футбол
                                    markup_count = (area1*2+area2*3+65);
                                    markup = markup_count*0.08;
                                } else if(tab == 51) {                              // Теннис
                                    markup_count = (area1*4+area2*2+area2+1.73+(area2-2.74)*2+12.85);
                                    markup = markup_count*0.05;
                                } else if(tab == 50) {                              // Хоккей
                                    markup_count = (area1*2+area2*5+121);
                                    markup = markup_count*0.075;
                                }

                                if ($('input[name=lenta]').prop("checked")) {
                                } else {
                                    markup_count = markup_count*1.03;
                                }
                                markup = markup*1.03;

                                markup_count = Math.ceil(markup_count);
                                markup = Math.ceil(markup);

                                totalMarkup.push( {text: ' Разметка поля', alignment:'left' }, {text: 'м2'}, {text: markup}, {text: price + ' руб.'}, {text: markup*price + ' руб.'} );
                                totalBody.push(totalMarkup);
                                total = total+markup*price;

                            }


                            // --- Шовная лента (опционально) ---
                            // Рассчитывает количество метров ленты на основе стыков между рулонами.
                            // calc2 = суммарная длина стыков (п.м), цена = 65 руб/п.м × 1.05 (запас)
                            if ($('input[name=lenta]').prop("checked")) {

                                var calc1 = area2/shirina;
                                var rul_s_count = area1/shirina;
                                var calcnum = Number.isInteger(calc1);
                                if(calcnum) {
                                    calc1 = calc1-1;
                                } else {
                                    calc1 = Math.floor(calc1);
                                }

        // console.log('calc1 - ' + calc1);
        // console.log('shirina - ' + shirina);

                                var calc2 = calc1*area1;
                                var con = area1/dlina;

                                if( con == 1 ) {

                                    con = con - 1;
                                      console.log( ' Четное в ширину раз ' + con);
                                    calc2 = area2*con+calc2;

                                    var styk = 0;
                                    styk = area2/shirina*con;

                                }    else {

                                    con = Math.floor(con);
                                      console.log( ' Нечетное в ширину раз ' + con);

                                      var stepX = dlina/area1;
                                        var stepY = shirina/area2;
                                        var posX = stepX*730;
                                        var posY = stepY*365;
                                        var y = 1;
                                        var yi = 0;
                                        var styk = 0;
                                        while(yi < 365) {
                                            if(y%2){
                                                var x = 1;
                                                var xi = posX + 2;
                                            } else {
                                                var x = 1;
                                                var xi = (posX+2)/2;
                                            }
                                            while(xi < 730) {
                                                xi += posX;
                                                xi = Math.ceil(xi);
                                                x++;
                                            }
                                          yi += posY;
                                            yi = Math.ceil(yi);
                                          y++;
                                          styk = styk+x-1;
                                        }
                                          console.log('styk - ' + styk);

                                          $('.styk span').html(styk);

                                        calc2 = styk*shirina+calc2;

                                }

                                if ($('input[name=markup]').prop("checked")) {
                                    calc2 = calc2+markup_count;
                                }

                                var lenta_count = calc2*65*1.05;

                                var lenta = calc2*65*1.05;
                                totalLenta.push( {text: 'Шовная лента', alignment:'left' }, {text: 'п.м'}, {text: (calc2*1.05).toFixed(1)}, {text: '65 руб.'}, {text: lenta + ' руб.'} );
                                totalBody.push(totalLenta);
                                total = total+lenta;

                            }


                            // --- Клей для искусственной травы (опционально) ---
                            // 0.5 кг клея на 1 п.м стыка × 1.05 запас, банки по 10 кг, 4000 руб/банка
                            if ($('input[name=glue]').prop("checked")) {

                                var glue_count = calc2*0.5*1.05;
                                glue_count = Math.ceil(glue_count/10);
                                var glue = glue_count*4000;

                                totalGlue.push( {text: ' Клей для искусственной травы', alignment:'left' }, {text: 'банка'}, {text: glue_count}, {text: '4000 руб.'}, {text: glue + ' руб.'} );
                                totalBody.push(totalGlue);
                                total = total+glue;

                            }



                            // --- Кварцевый песок (опционально) ---
                            // Количество тонн зависит от высоты ворса (vors_height мм).
                            // Формула: коэффициент × площадь / 1000. Цена: 3950 руб/тонна.
                            if ($('input[name=pesok]').prop("checked")) {

                                var pesok;
                                if( vors_height >= 10 && vors_height <= 12 ) {
                                    pesok_count = 7.8*area/1000;
                                    pesok = pesok_count*3950;
                                } else if( vors_height >= 14 && vors_height <= 15 ) {
                                    pesok_count = 10*area/1000;
                                    pesok = pesok_count*3950;
                                } else if( vors_height == 20 ) {
                                    pesok_count = 4.8*area/1000;
                                    pesok = pesok_count*3950;
                                } else if( vors_height == 25 ) {
                                    pesok_count = 6*area/1000;
                                    pesok = pesok_count*3950;
                                } else if( vors_height == 30 ) {
                                    pesok_count = 7.5*area/1000;
                                    pesok = pesok_count*3950;
                                } else if( vors_height >= 32 && vors_height <= 35 ) {
                                    pesok_count = 9*area/1000;
                                    pesok = pesok_count*3950;
                                } else if( vors_height == 40 ) {
                                    pesok_count = 12*area/1000;
                                    pesok = pesok_count*3950;
                                } else if( vors_height == 45 ) {
                                    pesok_count = 20*area/1000;
                                    pesok = pesok_count*3950;
                                } else if( vors_height == 50 ) {
                                    pesok_count = 22*area/1000;
                                    pesok = pesok_count*3950;
                                } else if( vors_height == 55 ) {
                                    pesok_count = 22*area/1000;
                                    pesok = pesok_count*3950;
                                } else if( vors_height == 60 ) {
                                    pesok_count = 22*area/1000;
                                    pesok = pesok_count*3950;
                                }

                                pesok = Math.ceil(pesok);

                                totalPesok.push( {text: 'Кварцевый песок', alignment:'left' }, {text: 'тонна'}, {text: pesok_count}, {text: '3950 руб.'}, {text: pesok + ' руб.'} );
                                totalBody.push(totalPesok);
                                total = total+pesok;
                            }

                            // --- Резиновая крошка (опционально) ---
                            // Количество тонн зависит от высоты ворса (vors_height мм).
                            // Для ворса 10–15 мм крошка не нужна (= 0).
                            // Формула: коэффициент × площадь / 1000. Цена: 24500 руб/тонна.
                            if ($('input[name=kroshka]').prop("checked")) {

                                var kroshka;
                                if( vors_height >= 10 && vors_height <= 12 ) {
                                    kroshka_count = 0;
                                    kroshka = 0;
                                } else if( vors_height >= 14 && vors_height <= 15 ) {
                                    kroshka_count = 0;
                                    kroshka = 0;
                                } else if( vors_height == 20 ) {
                                    kroshka_count = 3*area/1000;
                                    kroshka = kroshka_count*24500;
                                } else if( vors_height == 25 ) {
                                    kroshka_count = 4*area/1000;
                                    kroshka =kroshka_count*24500;
                                } else if( vors_height == 30 ) {
                                    kroshka_count = 5*area/1000;
                                    kroshka = kroshka_count*24500;
                                } else if( vors_height >= 32 && vors_height <= 35 ) {
                                    kroshka_count = 6*area/1000;
                                    kroshka = kroshka_count*24500;
                                } else if( vors_height == 40 ) {
                                    kroshka_count = 7*area/1000;
                                    kroshka = kroshka_count*24500;
                                } else if( vors_height == 45 ) {
                                    kroshka_count = 8*area/1000;
                                    kroshka = kroshka_count*24500;
                                } else if( vors_height == 50 ) {
                                    kroshka_count = 9*area/1000;
                                    kroshka = kroshka_count*24500;
                                } else if( vors_height == 55 ) {
                                    kroshka_count = 10.5*area/1000;
                                    kroshka = kroshka_count*24500;
                                } else if( vors_height == 60 ) {
                                    kroshka_count = 12*area/1000;
                                    kroshka = kroshka_count*24500;
                                }

                                kroshka = Math.ceil(kroshka);

                                totalKroshka.push( {text: 'Резиновая крошка', alignment:'left' }, {text: 'тонна'}, {text: kroshka_count}, {text: '24500 руб.'}, {text: kroshka + ' руб.'} );
                                totalBody.push(totalKroshka);
                                total = total+kroshka;
                            }


                            total = Math.ceil(total);

        var sity = $('#pdf_sity').text();
        var date = $('#pdf_date').text();



        // --- Генерация PDF документа ---
        // docInfo — объект-спецификация для pdfMake.
        // Структура: логотип + контакты, заголовок КП, таблица атрибутов товара,
        // текст с площадью, расчётная таблица (totalBody), итого, примечания, подпись.
        var docInfo = {

                pageMargins:[30,10,30,10],

                content: [
                    {
                        columns: [
                            {
                                image: logo,
                                width: 120
                            },
                            [
                                {
                                    fontSize: 12,
                                    bold: true,
                                    text: 'Ваш надежный путеводитель в мир зеленого будущего!'
                                },
                                {
                                    fontSize: 11,
                                    bold: true,
                                    text: 'ООО "КМС-Спорт"" ИНН 6658528023/665801001'
                                },
                                {
                                    fontSize: 12,
                                    bold: true,
                                    color: '#8bc73f',
                                    text: 'ТЕЛ. 8(800)-201-41-00; 8(343)-209-70-60; 8(922)-021-41-11'
                                },
                                {
                                    text: [
                                        'Сайт ',
                                        { text: 'https://iskustvennaya-trava.ru/', color: '#8bc73f', decoration: 'underline', },
                                        ' - Искусственная трава и комплектующие',
                                        ]
                                },
                                {
                                    text: [
                                    'Сайт ',
                                    { text: 'https://ufgrass.ru/', color: '#8bc73f', decoration: 'underline', },
                                    ' - Искусственная трава и комплектующие',
                                    ]
                                },
                            ]
                        ]
                    },
                    {
                        canvas: [
                            {
                                type: 'line',
                                x1: 0, y1: 10,
                                x2: 530, y2: 10,
                                lineWidth: 2
                            },
                            {
                                type: 'line',
                                x1: 0, y1: 14,
                                x2: 500, y2: 14,
                                lineWidth: 2
                            },
                        ]
                    },
                    '\n',
                    {
                        fontSize: 16,
                        text: 'Коммерческое предложение',
                        bold: true,
                        alignment:'center'
                    },
                    {
                        fontSize: 12,
                        text: h1,
                        bold: true,
                        alignment:'center'
                    },
                    '\n',
                    {
                        columns: [
                            {
                                table: {
                                    // headers are automatically repeated if the table spans over multiple pages
                                    // you can declare how many rows should be treated as headers
                                    headerRows: 1,
                                    body: atrrBody
                                }, // table
                                layout: 'noBorders',
                                fontSize: 9,
                            },
                            {
                                image: pdfImg,
                                width: 200
                            }
                        ]
                    },
                    '\n',
                    {
                        text: [
                            'По Вашему запросу компания ',
                            { text: '«UF Grass»', bold: true, },
                            ' готова поставить товар, а именно: ' + h1 + ' площадью ',
                            { text: area + ' м2:', bold: true, }
                        ]
                    },
                    '\n',
                    {
                        table: {
                            widths: ['*', 40, 40, 50, 80 ],
                            // headers are automatically repeated if the table spans over multiple pages
                            // you can declare how many rows should be treated as headers
                            body: totalBody
                        }, // table
                        fontSize: 9,
                        alignment: "center"
                        //  layout: 'lightHorizontalLines'
                    },
                    {   
                        table: {
                            widths: ['*'],
                            body: [
                                [{ text: 'Итого: ' + total + ' руб.', fillColor: '#ee0' }],
                            ]
                        }, // table
                        fontSize: 9,
                        alignment: "right"
                    },
                    {
                        italics: true,
                        fontSize: 10,
                        text: '\n*Стоимость указана без НДС\n *Условия оплаты: 100% предоплата\n *Итоговая цена указана без доставки до г. ' + sity + '\n *Итоговая цена указана без монтажа\n *Итоговая цена указана без учета засыпных материалов: кварцевого песка и резинового гранулянта\n'
                    },
                    {
                        columns: [
                            [
                            ],
                            {
                                image: print,
                                width: 120
                            },
                            [
                                {  
                                    image: signature,
                                    width: 180
                                },
                                {
                                    fontSize: 9,
                                    margin: [ 0, 0, 27, 0 ],
                                    text: 'от ' + date + 'г.',
                                    alignment:'right'
                                }
                            ]
                        ]
                    },
                    {
                            text: [
                            'С уважением, команда ',
                            { text: 'UF Grass', bold: true,  },
                        ],
                        color: '#00008b',
                    },
                    {
                        fontSize: 10,
                        color: '#00008b',
                        text: 'Тел. 8-(800)-201-41-00\n Тел. 8-(922)-021-41-11\n'
                    },
                    {
                        text: [
                            { text: 'E-mail:', bold: true,  },
                            { text: 'trava@ufgrass.ru', decoration: 'underline', color: '#00f', },
                        ],
                        color: '#00008b',
                    },
                    {
                        text: [
                            { text: 'Сайт:', bold: true,  },
                            { text: 'https://iskustvennaya-trava.ru/', decoration: 'underline', color: '#00f', },
                            ' - Искусственная трава и комплектующие'
                        ],
                        color: '#00008b',
                    },
                    {
                        text: [
                            { text: 'Сайт:', bold: true,  },
                            { text: 'https://ufgrass.ru/', decoration: 'underline', color: '#00f', },
                            ' - Искусственная трава и комплектующие'
                        ],
                        color: '#00008b',
                    },
                ], // content end
                defaultStyle: {
                    fontSize: 10,
                    columnGap: 10
                }
            } // var docInfo end

            // Генерация и скачивание PDF файла
            pdfMake.createPdf(docInfo).download('КП (КМС-Спорт).pdf');

        return false;

    });


});