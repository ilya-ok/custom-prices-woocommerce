# Настройки: тексты кнопок и меток (v1.2.0)

## Контекст

К существующим 6 числовым/select-полям (`min_order_*`, `step_*`, `attr_width`, `attr_length`) добавлены 4 новые секции с текстовыми полями, позволяющими настраивать все кастомные тексты на фронтенде без правки кода.

Страница настроек: WooCommerce → Custom Prices Settings (`class-custom-prices-settings.php`).

## Новые секции и поля в `custom_prices_options`

| Секция | Ключ | По умолчанию |
|--------|------|-------------|
| **Тексты кнопок** | `label_buy_btn` | «Купить» |
| | `label_cart_btn` | «В корзину» |
| **Названия табов** | `label_tab_rrc` | «РРЦ» |
| | `label_tab_opt` | «ОПТ» |
| | `label_tab_rulon` | «Рулон» |
| | `label_tab_otrez` | «Отрез» |
| **Метки полей и итогов** | `label_qty` | «Количество:» |
| | `label_length_input` | «Длина отреза:» |
| | `label_width_select` | «Ширина рулона:» |
| | `label_total` | «Итого:» |
| | `label_volume` | «Объем:» |
| | `label_area` | «Площадь:» |
| **Метки цен** | `label_price_rrc` | «Цена (РРЦ):» |
| | `label_price_opt` | «Цена (ОПТ):» |
| | `label_price_rulon` | «Цена (от рулона):» |
| | `label_price_otrez` | «Цена (на отрез):» |
| | `label_price_std` | «Цена:» |
| | `label_price_rrc_mobile` | «» (пусто = нет мобайл-дубля) |
| | `label_price_opt_mobile` | «» |
| | `label_price_rulon_mobile` | «» |
| | `label_price_otrez_mobile` | «» |
| **Суффиксы** | `suffix_currency` | «руб.» |
| | `suffix_area` | «м²» |
| | `suffix_width` | «м» |

Добавлен метод `text_field_callback($args)` — рендерит `<input type="text">` с подсказкой «По умолчанию: «…»».

## Мобильные метки цен (`label_price_*_mobile`)

Позволяют задать сокращённую версию метки цены для мобильных устройств. Реализовано CSS-переключением:
- Если мобайл-метка **задана и отличается** от десктопной → рендерятся два `<div>` с модификаторами `--desktop` / `--mobile`
- Если пустая или совпадает → один обычный `<div>`

Реализовано через `render_price_label()` в `Custom_Prices_Frontend` — подробнее в [frontend.md](frontend.md).
