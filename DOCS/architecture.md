# Архитектура и ключевые принципы

1. **Singleton** — `Custom_Prices_Woocommerce` — единственный класс, создаваемый через `get_instance()`. Он создаёт экземпляры всех остальных классов в конструкторе.
2. **Мета-поля товара** — вся конфигурация хранится в `post_meta` каждого товара. Глобальные значения по умолчанию хранятся в `custom_prices_options` (один WordPress option).
3. **Переопределение шаблонов** — через `woocommerce_locate_template` плагин подставляет свои шаблоны вместо стандартных WC для корзины, checkout и деталей заказа.
4. **AJAX-взаимодействие** — добавление в корзину и открытие попапа товара на листинге происходят через AJAX без перезагрузки страницы.
5. **Localize Script** — данные товара передаются в JS через `wp_localize_script` под ключом `custom_product_data_{product_id}` (для каждого товара) и `custom_prices_global` (глобально: ajax_url, nonce).
6. **PDF** — коммерческое предложение генерируется целиком на клиентской стороне через pdfMake. Изображения (логотип, печать, подпись) встроены в JS как base64.

---

## Мета-поля товара (post_meta)

| Поле | Тип | Описание |
|---|---|---|
| `_custom_price_type` | string | Тип цены: `''` (стандартная), `'opt'`, `'otrez'` |
| `_price_otrez` | float | Цена за метр погонный (м.п.) для типа otrez |
| `_price_opt` | float | Оптовая цена за единицу для типа opt |
| `_min_order` | int | Минимальный объём заказа (кол-во) |
| `_step_quantity` | int | Шаг изменения количества |
| `_volume_unit` | float | Объём в одной единице товара (для вывода «Объём: N единиц») |
| `_units` | string | Текст единицы измерения (например, «м.п.», «рулон») |

---

## Глобальные настройки (custom_prices_options)

| Ключ | Описание |
|---|---|
| `min_order_otrez` | Минимальный заказ на отрез (по умолчанию для всех товаров типа otrez) |
| `min_order_opt` | Минимальный заказ оптом |
| `step_otrez` | Шаг количества для отрезов |
| `step_opt` | Шаг количества для опт |
| `attr_width` | Taxonomy slug атрибута ширины рулона (например, `pa_shirina`) |
| `attr_length` | Taxonomy slug атрибута длины рулона (например, `pa_dlina`) |

Приоритет: значение из мета-поля товара > глобальное значение из настроек.

---

## Формулы расчёта стоимости

### Тип `opt`

| Таб | Цена | Формула |
|---|---|---|
| РРЦ | `price` (стандартная цена товара) | `total = price × qty` |
| ОПТ | `_price_opt` | `total = price_opt × qty` |

Объём: `volume = _volume_unit × qty`

### Тип `otrez`

| Таб | Единица | Формула |
|---|---|---|
| Отрез | м.п. | `total = qty × width × _price_otrez` |
| Рулон | м² | `total = qty × width × length × price` (стандартная цена) |

Площадь: `area = qty × width` (отрез) или `qty × width × length` (рулон)

Здесь `width` — значение выбранного радиоблока (из атрибута `attr_width`), `length` — из атрибута `attr_length`.

---

## Поток данных: добавление товара в корзину

```
Пользователь нажимает «В корзину»
    │
    ▼
JS собирает: product_id, qty из активного таба, width, width_value, length
    │
    ▼
AJAX → add_to_cart_custom (class-custom-prices-ajax.php)
    │  ├─ Проверка nonce
    │  ├─ Определение типа товара
    │  ├─ Формирование custom_data (is_opt/is_otrez, width, unit и т.д.)
    │  └─ WC()->cart->add_to_cart(..., ['custom_data' => ...])
    │
    ▼
custom_data сохраняется в сессии корзины
    │
    ▼
При отображении корзины:
    ├─ get_cart_item_from_session → восстанавливает custom_data из сессии
    ├─ custom_cart_item_price → показывает правильную цену
    ├─ custom_cart_item_subtotal → считает итог по формуле
    └─ cart.php template → выводит колонки Ед.изм и Объём/Площадь
    │
    ▼
При оформлении заказа:
    └─ save_custom_data_to_order_item → сохраняет custom_data в order item meta (_custom_data)
        └─ устанавливает subtotal и total для order item
```
