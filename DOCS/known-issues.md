# Известные особенности и исправленные баги

## Известные особенности

1. **Дублирование `get_custom_subtotal`** — метод содержится в трёх местах: `Custom_Prices_Cart`, `Custom_Prices_Ajax`, `Custom_Prices_Woocommerce`. Это сделано, чтобы избежать зависимости между классами (каждый класс работает автономно).
2. **error_log** — по всему коду расставлены `error_log()` для отладки. В продакшн-среде рекомендуется убрать или перевести за флаг.
3. **Артикул в попапе** — в попапе товара жёстко прописан артикул `3614` (строка 189 frontend). Это захардкоженное значение, требует замены на динамическое.
4. **Атрибуты бренда** — проверяются два варианта написания: `pa_brend` и `pa_brand` (опечатка / смешение eng/ru).
5. **Цены в PDF** — часть цен захардкожена в JS: песок — 3950 руб/тонна, крошка — 24500 руб/тонна, клей — 4000 руб/банка, лента — 65 руб/п.м. При изменении цен нужно обновлять JS.
6. **Доставка отключена глобально** — `woocommerce_cart_needs_shipping` возвращает `false`. Если нужна доставка, фильтр нужно снять или сделать условным.

---

## Исправленные баги (frontend)

| № | Баг | Файл / строка | Причина | Исправление |
|---|---|---|---|---|
| 1 | Taxonomy slug `product_category` | class-custom-prices-bulk-edit.php (3 вхождения) | WC регистрирует категории под slug `product_cat`, не `product_category` | Заменено на `product_cat` |
| 2 | `wp_send_json_fail()` не существует | class-custom-prices-bulk-edit.php (3 вхождения) | Функция называется `wp_send_json_error()` | Заменено на `wp_send_json_error()` |
| 3 | `selected()` загрязнял JSON-ответ | class-custom-prices-bulk-edit.php, `build_products_table()` | Третий аргумент `true` — echo-режим; `selected` выводилось напрямую в output buffer перед JSON | Изменён на `false` (return-режим) |
| 4 | `post_exists()` всегда возвращал 0 | class-custom-prices-bulk-edit.php, `ajax_save_product()` | `post_exists($title, $content)` ищет по названию, не по ID | Заменено на `get_post_type($id) !== 'product'` |
| 5 | Табы на single-странице товара не инициализируются | custom-prices.js:195 | Селектор `.product-popup-content` не совпадал с реальным BEM-классом `.product-popup__content` из PHP → `product_id` всегда `undefined` → `data` не найдена | Исправлен селектор на `.product-popup__content` |
