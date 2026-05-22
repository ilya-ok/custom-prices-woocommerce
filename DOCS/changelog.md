# Changelog

## v1.4.6 — 2026-05-22

### Переименование и вынос меню

**Network Admin:**
- Пункт меню "Цены Multisite" переименован в **"Custom Prices"** (иконка `dashicons-money-alt`)

**Site Admin:**
- Страницы плагина вынесены из-под меню WooCommerce в отдельный верхний пункт **"Custom Prices"** (иконка `dashicons-money-alt`)
- Структура: Custom Prices → Массовое редактирование / Настройки

**Файлы:**
- `includes/class-custom-prices-multisite-bulk-edit.php` — slug меню `cp-ms-bulk-edit`, label → "Custom Prices"
- `includes/class-custom-prices-bulk-edit.php` — новый `add_menu_page` со slug `custom-prices`, убран родитель `woocommerce`
- `includes/class-custom-prices-settings.php` — родитель изменён с `woocommerce` на `custom-prices`

---

## v1.4.5 — 2026-04-22

### custom-opt single: totals-row на странице товара

**Файл:** `includes/class-custom-prices-frontend.php` → `display_forms()` (ветка `$is_single = true`, `$type == 'opt'`)

- Обёртка `<div class="single-type-opt-totals">` заменена на `<div class="totals-row">` — блоки `total-price` и `total-volume` встают рядом через flex
- `<span>0</span>` → `<span class="value">0</span>` в обоих блоках
- Суффиксы обёрнуты в `<span class="suffix">...</span>`

> Причина: для single-страницы opt HTML рендерится inline в `display_forms()`, а не через `display_opt_form()` (которая используется только в попапе). Поэтому предыдущее изменение v1.4.4 не затронуло этот блок.

---

## v1.4.4 — 2026-04-22

### custom-opt: total-price + total-volume обёрнуты в totals-row, суффиксы в span

**Файл:** `includes/class-custom-prices-frontend.php` → `display_opt_form()`

- `total-price` и `total-volume` обёрнуты в `<div class="totals-row">` — блоки встают рядом (flex)
- Числовые значения: `<span>0</span>` → `<span class="value">0</span>` в обоих блоках
- Суффиксы обёрнуты в `<span class="suffix">...</span>`

**Файл:** `assets/js/custom-prices.js`

- Селектор обновлён: `.total-volume span` → `.total-volume span.value` — JS не затрагивает новый `span.suffix`

---

## v1.4.3 — 2026-04-22

### Стандартный товар: блок total-price с расчётом стоимости

**Файл:** `includes/class-custom-prices-frontend.php` → `custom_price_html()` (ветка `else`, `is_product()`)

- На `<form class="cart">` добавлен атрибут `data-price="$standard_price"` — источник цены для JS
- Между `woocommerce_quantity_input()` и кнопкой «В корзину» добавлен блок:
  `<div class="total-price">Итого: <span class="value">0</span> <span class="suffix">руб.</span></div>`
- Текст «Итого:» и суффикс «руб.» берутся из `$labels['total']` и `$labels['suffix_currency']`

---

## v1.4.2 — 2026-04-22

### custom-otrez: total-price + total-square рядом, суффиксы в span

**Файл:** `includes/class-custom-prices-frontend.php` → `display_otrez_form()`

- `total-price` и `total-square` обёрнуты в общий `<div class="totals-row">`, блоки встают рядом
- Числовые значения: `<span>0</span>` → `<span class="value">0</span>`
- Суффиксы «руб.» и «м²» обёрнуты в `<span class="suffix">...</span>`

**Файл:** `assets/js/custom-prices.js`

- Селекторы обновлены: `.total-price span` → `.total-price span.value`, `.total-square span` → `.total-square span.value` — JS не затрагивает новый `span.suffix`

**Файл:** `assets/css/custom-prices.css`

- Добавлен `.totals-row { display: flex; justify-content: space-around; gap: 10px; margin-top: 20px; margin-bottom: 15px; }`
- `.total-price, .total-square` — `font-weight: 400`; вложенные `span` — `font-weight: 700; font-size: 20px`

---

### custom-popup: цена перенесена в custom-tabs

**Файл:** `includes/class-custom-prices-frontend.php` → `display_forms()`

- Переменные `$primary_term_id`, `$select_cat_units` вынесены в outer scope (после `$labels`)
- Блок `cat_price_wrap cat_price_wrap_reverse` удалён из `.product-popup__55`
- В начало `.product-popup__45 .custom-tabs` добавлен `<div class="custom-price-wrap">` с `price_wrap`-блоками (классы `file_wrap-weght` + `price`) — структура идентична странице товара

---

### Стандартный товар: product-actions перед cart_wrap

**Файл:** `includes/class-custom-prices-frontend.php` → `display_price_section()` (тип `standard`)

- Добавлен `do_action('custom_prices_before_cart_wrap')` между `custom-price-wrap` и `cart_wrap`
- Тема подключает `render_product_actions()` к этому хуку — кнопки избранного/сравнения рендерятся между ценой и формой корзины

---

## v1.4.1
- `templates/woocommerce/checkout/review-order.php`: на странице `/checkout/` в таблице обзора заказа удалены колонки `product-price`, `product-quantity`, `product-units`, `product-volume-square`, `product-total` — оставлена только `product-name`. Удалены связанные вычисления `$type`, `$custom_data`, `$units`, `$volume_square` из цикла foreach.

## v1.4.0
- Добавлена страница синхронизации настроек `custom_prices_options` между сайтами мультисети
- Новая запись в подменю «Цены Multisite»: slug `cp-ms-options-sync`

## v1.3.0
- Перенесён функционал плагина `multisite-sync`: автосинхронизация цен, сетевая страница массового редактирования, страница настроек синхронизации
- Все три компонента загружаются только при `is_multisite()`

## v1.2.0
- Страница настроек: добавлены 4 секции с 24 текстовыми полями (кнопки, табы, метки форм, метки цен, суффиксы)
- Frontend: приватный метод `get_labels()` — все тексты из настроек с fallback на дефолты
- Frontend: метод `render_price_label()` — CSS-переключение десктоп/мобайл метки цены
- Мобайл-дубли для меток цен: `label_price_rrc_mobile`, `label_price_opt_mobile`, `label_price_rulon_mobile`, `label_price_otrez_mobile`

## v1.1.1
- В массовом редактировании цен добавлено отображение ширины и длины рулона (атрибуты из настроек `attr_width` / `attr_length`) рядом с артикулом и ценой. Значения берутся через `wp_get_post_terms` и отображаются только если у товара есть данные; для ширины несколько значений объединяются через запятую.

## v1.1.0
- Добавлена страница массового редактирования цен (WooCommerce → Массовое редактирование)
  - Иерархический выбор категорий с AJAX-загрузкой товаров
  - Inline-редактирование 7 полей с AJAX-сохранением и индикатором статуса
- Исправлен рендер корзины: удаление `wp_template` FSE-посты `page-cart`/`page-checkout`, конвертация блоков `<!-- wp:woocommerce/cart -->` в шорткод `[woocommerce_cart]`
- Удалён спам `error_log` из cart.php

## v1.0.9
- Попап товара загружается через AJAX (не встроенный HTML на листинге)
- Глобальная локализация nonce через `custom_prices_global`
- Модификация имени товара в заказе для типа otrez

## v1.0.x (предыдущие)
- Базовая система opt/otrez с табами
- Кастомные шаблоны корзины и checkout
- PDF генерация коммерческих предложений
- Сохранение custom_data в order items
