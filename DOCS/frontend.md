# Frontend: попап, JS, PDF

## Попап на листинге (shop/category)

На листинге товаров стандартная кнопка «Add to cart» заменена на кнопку «Купить» (`popup-link`). При клике:

1. Открывается Magnific Popup с «Загрузка...»
2. AJAX `get_product_popup` загружает HTML попапа (генерируется `Custom_Prices_Frontend::get_popup_html()`)
3. JS инициализирует jQuery UI Tabs и привязывает события (+/-, расчёт, добавление в корзину)
4. После успешного добавления попап закрывается автоматически

На странице одного товара (`is_single`) попап не используется — форма выводится на месте.

---

## JavaScript (custom-prices.js)

Файл ~815 строк кода + встроенные base64-изображения (~150KB итого).

### Секция 1: Попап и табы (строки 1–190)
- Обработка клика на `.popup-link` → открытие Magnific Popup → AJAX загрузка HTML попапа
- `attachEvents()` — привязка событий: кнопки +/-, input change, add-to-cart-ajax
- Инициализация jQuery UI Tabs для already-rendered формы на single-странице
- При смене таба значения qty в неактивных табах сбрасываются в 0

### Секция 2: calculate_total (строки 197–251)
- Читает активный таб
- Для `opt`: total = qty × price (standard или extra), volume = qty × volume_unit
- Для `otrez`: total = qty × width × price_extra (отрез) или qty × width × length × price_standard (рулон)
- Обновляет DOM: `.total-price span`, `.total-volume span`, `.total-square span`

### Секция 3: Калькулятор площади (строки 254–287)
- Отдельный калькулятор для страниц с `.custom-quantity-fields-calculator`
- При изменении area_1/area_2 пересчитывает площадь и итого для PDF

### Секция 4: PDF генерация (строки 290–815)
- Попап для PDF через Magnific Popup (inline `#popup_pdf_download`)
- По клику `.pdf_download` запускается генерация:
  1. Собирает атрибуты товара из DOM в таблицу `atrrBody`
  2. Считает площадь поля, стоимость трава
  3. Опционально: разметка (по виду спорта), шовная лента, клей, песок, крошка
  4. Собирает всё в `docInfo` (pdfMake document definition)
  5. Генерирует PDF: `pdfMake.createPdf(docInfo).download('КП (КМС-Спорт).pdf')`

---

## PDF коммерческое предложение

Функция генерации PDF (`custom-prices.js`, начиная ~строка 316) работает только на страницах товаров искусственной травы.

### Исходные данные (берутся из DOM)
- `area_1`, `area_2` — длина и ширина поля
- `shirina` — ширина рулона (выбранная)
- `dlina` — длина рулона (из data-атрибута товара)
- `vors_height` — высота ворса (из data-атрибута товара)
- `tab` — вид спорта (ID термина): 48=футбол, 49=мини-футбол, 50=хоккей, 51=теннис

### Расчёты в PDF
| Статья | Формула | Единица |
|---|---|---|
| Искусственная трава | area × price_per_m2 | м² |
| Разметка поля | зависит от вида спорта (tab) | м² |
| Шовная лента | на основе количества стыков рулонов | п.м |
| Клей | calc2 × 0.5 × 1.05, округлённое до банок по 10 | банка |
| Кварцевый песок | коэффициент × area / 1000, зависит от vors_height | тонна |
| Резиновая крошка | коэффициент × area / 1000, зависит от vors_height | тонна |

Коэффициенты песка и крошки варьируются от высоты ворса (10–60 мм).

### Разметка по видам спорта
- **Футбол (48)**: `markup_count = area1×2 + area2×3 + 314`, цена × 0.10
- **Мини-футбол (49)**: `markup_count = area1×2 + area2×3 + 65`, цена × 0.08
- **Теннис (51)**: сложная формула с учётом разметки теннисного корта
- **Хоккей (50)**: `markup_count = area1×2 + area2×5 + 121`, цена × 0.075

### Содержание PDF
- Логотип и контакты компании КМС-Спорт / UF Grass
- Таблица атрибутов товара
- Текст с названием товара и площадью
- Расчётная таблица со всеми статьями
- Итог
- Примечания (без НДС, без доставки, без монтажа)
- Печать и подпись (base64-изображения)

---

## Frontend: get_labels() + render_price_label() (class-custom-prices-frontend.php)

### Метод `get_labels()`

Приватный метод, читает все 24 текстовых ключа из `custom_prices_options` с fallback на дефолты:

```php
private function get_labels() {
    $opts = get_option('custom_prices_options', []);
    $t    = function($key, $default) use ($opts) {
        return (isset($opts[$key]) && $opts[$key] !== '') ? $opts[$key] : $default;
    };
    return [
        'buy_btn'            => $t('label_buy_btn',            'Купить'),
        'cart_btn'           => $t('label_cart_btn',           'В корзину'),
        // ... табы, метки, суффиксы, метки цен, мобайл-дубли
        'price_rrc_mobile'   => $t('label_price_rrc_mobile',   ''),
        // ...
    ];
}
```

Вызывается в `custom_price_html()`, `display_custom_forms_listing()` и `display_forms()`.

### Метод `render_price_label()`

```php
private function render_price_label($desktop, $mobile, $class = 'file_wrap-weght') {
    if (!empty($mobile) && $mobile !== $desktop) {
        return "<div class='{$class} {$class}--desktop'>" . esc_html($desktop) . "</div>"
             . "<div class='{$class}--mobile'>"           . esc_html($mobile)  . "</div>";
    }
    return "<div class='{$class}'>" . esc_html($desktop) . "</div>";
}
```

Используется:
- В `custom_price_html()` для основного блока цен (`class='file_wrap-weght'`)
- В `display_forms()` для попапа (`class='product-popup__meta-weght'`)

### Где применяется `$labels`

| Метод | Что заменено |
|-------|-------------|
| `custom_price_html()` | Метки цен РРЦ/ОПТ/рулон/отрез/стандарт |
| `display_custom_forms_listing()` | Кнопка «Купить» |
| `display_forms()` | Метки цен в попапе; табы, qty, total, cart btn (inline opt) |
| `display_opt_form()` | Табы РРЦ/ОПТ, Количество, Итого, Объём, «В корзину» |
| `display_otrez_form()` | Табы Рулон/Отрез, Количество, Длина отреза, Ширина рулона, суффикс м, Итого, Площадь, «В корзину» |

Методы `display_opt_form` / `display_otrez_form` обновлены: добавлен параметр `$labels = []`, передаётся при вызовах из `display_forms()`.

---

## CSS: мобильные метки цен (assets/css/custom-prices.css)

```css
.file_wrap-weght--mobile,
.product-popup__meta-weght--mobile {
    display: none;
}

@media (max-width: 767px) {
    .file_wrap-weght--desktop,
    .product-popup__meta-weght--desktop { display: none; }
    .file_wrap-weght--mobile,
    .product-popup__meta-weght--mobile  { display: block; }
}
```

Брейкпоинт `767px` совпадает с `$vp-mobile` темы.
