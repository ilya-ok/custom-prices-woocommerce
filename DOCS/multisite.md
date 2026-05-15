# Мультисайт: синхронизация цен и настроек (v1.3.0–v1.4.1)

Весь мультисайт-функционал загружается только при `is_multisite()`.

---

## Новые файлы

| Файл | Класс | Назначение |
|------|-------|-----------|
| `includes/class-custom-prices-sync.php` | `Custom_Prices_Sync` | Автосинхронизация при сохранении товара |
| `includes/class-custom-prices-multisite-bulk-edit.php` | `Custom_Prices_Multisite_Bulk_Edit` | Сетевая страница массового редактирования |
| `includes/class-custom-prices-multisite-settings.php` | `Custom_Prices_Multisite_Settings` | Настройки синхронизации в сетевой админке |
| `includes/class-custom-prices-options-sync.php` | `Custom_Prices_Options_Sync` | Страница синхронизации настроек |
| `assets/js/admin-multisite-bulk-edit.js` | — | JS для массового редактирования |
| `assets/css/admin-multisite-bulk-edit.css` | — | Стили страницы |
| `assets/js/admin-options-sync.js` | — | JS для 3-шагового UI синхронизации настроек |

---

## Custom_Prices_Sync

**Singleton.** Автоматически синхронизирует цены и мета-поля товара на все сайты мультисети при сохранении товара в WooCommerce.

**Хуки:**
```php
add_action( 'woocommerce_update_product', 'sync_product_price' );
add_action( 'woocommerce_new_product',    'sync_product_price' );
add_action( 'updated_post_meta',          'sync_on_meta_update' );  // для быстрого редактирования
```

**Логика `sync_product_price()`:**
1. Проверяет флаг `$is_syncing` (предотвращение рекурсии)
2. Проверяет `get_site_option('cp_ms_auto_sync', '1')`
3. Получает SKU товара — без SKU синхронизация не происходит
4. Собирает мета-поля: `_custom_price_type`, `_price_otrez`, `_price_opt`, `_units`, `_min_order`, `_step_quantity`, `_volume_unit`
5. Вызывает `sync_to_all_sites()` — `switch_to_blog()` → `wc_get_product_id_by_sku()` → `update_product_prices()` → `restore_current_blog()`

**Публичный метод `sync_to_all_sites($sku, $regular_price, $sale_price, $exclude_id, $meta_data)`** — используется из `Custom_Prices_Multisite_Bulk_Edit::ajax_save_product()`.

---

## Custom_Prices_Multisite_Bulk_Edit

**Singleton.** Страница в сетевой админке: **Цены Multisite** (slug `cp-ms-bulk-edit`, `network_admin_menu`).

**AJAX actions:**

| Action | Метод | Описание |
|--------|-------|----------|
| `cp_ms_get_products` | `ajax_get_products()` | Загружает товары категории (до 200 шт, включая подкатегории) |
| `cp_ms_save_product` | `ajax_save_product()` | Сохраняет 9 полей товара + вызывает `Custom_Prices_Sync::sync_to_all_sites()` |

Nonce: `cp_ms_bulk_edit_nonce`
Права: `manage_network`
JS-переменная: `cpMsData` (`ajaxurl`, `nonce`)

**Таблица товаров** — 12 колонок: изображение, название, SKU, обычная цена, цена со скидкой, тип цены (`/opt/otrez`), цена отрез, цена опт, ед. изм., мин. заказ, шаг кол-во, объём ед., кнопка «Сохранить».

---

## Custom_Prices_Multisite_Settings

**Singleton.** Подменю **Настройки** под «Цены Multisite» (slug `cp-ms-settings`, приоритет 11).

Управляет `site_option` (для всей сети):

| Опция | По умолчанию | Описание |
|-------|-------------|---------|
| `cp_ms_auto_sync` | `'1'` | Автосинхронизация при сохранении товара |
| `cp_ms_sync_regular_price` | `'1'` | Синхронизировать обычную цену |
| `cp_ms_sync_sale_price` | `'1'` | Синхронизировать цену со скидкой |
| `cp_ms_debug_mode` | `'0'` | Режим отладки |

Сохранение через `admin-post.php` action `cp_ms_save_settings`, nonce `cp_ms_settings`.

---

## Custom_Prices_Options_Sync

**Singleton.** Подменю **Настройки Custom Prices** под «Цены Multisite» (slug `cp-ms-options-sync`, приоритет 12).

Позволяет скопировать весь option `custom_prices_options` с любого сайта на один или несколько других.

**AJAX actions:**

| Action | Метод | Input | Output |
|--------|-------|-------|--------|
| `cp_ms_load_options` | `ajax_load_options()` | `source_blog_id`, `nonce` | `{options, blog_name, blog_id, labels}` |
| `cp_ms_sync_options` | `ajax_sync_options()` | `source_blog_id`, `target_sites[]`, `nonce` | `{synced: N, results: [{blog_id, site, status}]}` |

Nonce: `cp_ms_options_sync_nonce`
Права: `manage_network`
JS-переменная: `cpMsOptionsSync` (`ajaxurl`, `nonce`, `strings`)
CSS: переиспользует `admin-multisite-bulk-edit.css`

**Поле `$field_labels`** — 30 человекочитаемых названий ключей `custom_prices_options`.

**UI — 3 шага:**
1. Выбор сайта-источника → загрузка настроек (таблица предпросмотра)
2. Выбор целевых сайтов (источник автоматически отключён) + кнопка «Синхронизировать»
3. Таблица результатов (✓ / ✗ по каждому сайту)

**Ключевые детали:**
- `ajax_load_options()`: `switch_to_blog()` → `get_option('custom_prices_options', [])` → `restore_current_blog()`
- `ajax_sync_options()`: читает источник → foreach target: `switch_to_blog()` → `update_option('custom_prices_options', $options)` → `restore_current_blog()`. Источник в списке целей пропускается (`continue`).

---

## Отличия от оригинального плагина multisite-sync

| Оригинал | Перенесено как |
|----------|---------------|
| Класс `MS_Sync` | `Custom_Prices_Sync` |
| Класс `MS_Bulk_Edit` | `Custom_Prices_Multisite_Bulk_Edit` |
| Класс `MS_Admin` | `Custom_Prices_Multisite_Settings` |
| Опции `ms_auto_sync`, `ms_sync_*` | `cp_ms_auto_sync`, `cp_ms_sync_*` |
| AJAX `ms_get_products`, `ms_save_product` | `cp_ms_get_products`, `cp_ms_save_product` |
| Слаги `ms-bulk-edit`, `ms-settings` | `cp-ms-bulk-edit`, `cp-ms-settings` |
| Nonce `ms_bulk_edit_nonce` | `cp_ms_bulk_edit_nonce` |
| JS-переменная `msData` | `cpMsData` |
| Константы `MS_PLUGIN_URL/DIR` | `plugin_dir_url/path(dirname(__FILE__))` |
