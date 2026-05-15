# Описание PHP-классов

## Custom_Prices_Woocommerce (class-custom-prices-woocommerce.php)

**Роль:** Главный координатор. Singleton.

- Создаёт экземпляры всех остальных классов
- Подключает JS/CSS assets на нужных страницах (product, shop, category, cart)
- Подключает Magnific Popup и pdfMake
- Через `woocommerce_locate_template` подставляет собственные шаблоны корзины, checkout, order
- `save_custom_data_to_order_item` — при создании заказа сохраняет `custom_data` в meta заказа и пересчитывает subtotal/total
- `remove_subtotal_from_order_totals` — убирает строку «Промежуточный итог» из итогов заказа (пересчитана кастомно)
- `custom_order_item_name` — для типа `otrez` добавляет к названию товара ширину и режим (отрез/рулон) в checkout/emails

---

## Custom_Prices_Admin (class-custom-prices-admin.php)

**Роль:** Добавляет поля настройки цен на странице редактирования товара в WC-админке.

Хуки:
- `woocommerce_product_options_pricing` — рендер полей
- `woocommerce_process_product_meta` — сохранение полей

Поля: тип цены (select), цена на отрез, цена опт, минимальный заказ, шаг количества, объём единицы, единицы измерения.

---

## Custom_Prices_Frontend (class-custom-prices-frontend.php)

**Роль:** Вывод цен и форм покупки на фронтенде.

- `custom_price_html` — переопределяет стандартный HTML цены WC. Для `opt` показывает РРЦ и ОПТ, для `otrez` — цену от рулона и на отрез. Для стандартных товаров на single-странице добавляет обычную форму add-to-cart.
- `display_custom_forms` — вызывается через кастомный action `custom_price_forms` на single-странице товара. Рендерит табы с формой для opt или otrez.
- `display_custom_forms_listing` — на листинге заменяет стандартную кнопку WC на кнопку «Купить» (для попапа). Локализует данные товара в JS.
- `get_popup_html` — генерирует HTML для попапа (вызывается через AJAX)
- `display_opt_form` / `display_otrez_form` — приватные методы, рендеряют HTML табов для соответствующего типа
- `get_labels()` — читает 24 текстовых ключа из настроек с fallback на дефолты (см. [settings-labels.md](settings-labels.md))
- `render_price_label()` — CSS-переключение десктоп/мобайл метки цены (см. [settings-labels.md](settings-labels.md))

Зависимости от внешних функций темы:
- `yoast_get_primary_term_id()` — для определения первичной категории товара
- `fw_get_db_term_option()` — для получения единиц измерения категории (настройка темы)
- `fw_get_db_settings_option()` — для получения города и цены доставки

---

## Custom_Prices_Cart (class-custom-prices-cart.php)

**Роль:** Модифицирует отображение и поведение корзины.

- `get_cart_item_from_session` — восстанавливает `custom_data` из сессии WC при загрузке корзины
- `custom_cart_item_name` — добавляет ширину к названию товара в корзине
- `custom_cart_item_price` — показывает правильную цену единицы в зависимости от типа и вкладки (РРЦ/ОПТ/отрез/рулон)
- `custom_cart_item_quantity` — рендерит input количества с правильными min/step
- `custom_cart_item_subtotal` / `get_custom_subtotal` — рассчитывает итог по формуле для каждого товара
- `get_custom_cart_total` — суммирует итоги всех товаров корзины
- `custom_cart_totals` — рендерит блок «Итого: N руб.»
- `add_custom_fragments` — возвращает AJAX-фрагменты для обновления корзины без перезагрузки (форма, collaterals, итого)
- `cart_ajax_js` — добавляет inline-скрипт на cart-странице для автоматического обновления корзины при изменении количества

Также отключает:
- стандартный `woocommerce_cart_totals`
- доставку (`woocommerce_cart_needs_shipping` → false)
- стандартный `cart_subtotal` на checkout

---

## Custom_Prices_Ajax (class-custom-prices-ajax.php)

**Роль:** Обработка AJAX-запросов от фронтенда.

- `add_to_cart_custom` — добавляет товар в корзину с custom_data. Определяет тип товара, формирует массив custom_data, вызывает `WC()->cart->add_to_cart()`. Возвращает JSON с cart fragments.
- `custom_get_cart_total` — возвращает отформатированную сумму корзины (для обновления «Итого» после событий WC)
- `get_product_popup` — генерирует HTML попапа товара и возвращает через JSON

Все handlers доступны и для авторизованных, и для анонимных пользователей (nopriv). Все проверяют nonce.

---

## Custom_Prices_Settings (class-custom-prices-settings.php)

**Роль:** Страница настроек плагина в WC-админке (подменю WooCommerce).

Регистрирует 6 базовых полей: два числовых (min_order), два числовых (step), два выпадающих списка для выбора атрибутов ширины и длины из существующих WC-атрибутов.

Плюс 4 секции с 24 текстовыми полями (v1.2.0) — см. [settings-labels.md](settings-labels.md).

---

## Custom_Prices_Bulk_Edit (class-custom-prices-bulk-edit.php)

**Роль:** Страница массового редактирования полей цен товаров по категории (подменю WooCommerce → Массовое редактирование).

Поток работы:
1. Рендер страницы: иерархический `<select>` категорий (с отметками уровня вложенности `— — Подкатегория`)
2. При смене категории — AJAX `cp_get_products`: сервер собирает товары из категории и всех подкатегорий (рекурсивно), возвращает HTML-таблицу
3. Каждая строка таблицы: изображение, имя + артикул + цена + ширина + длина (из атрибутов `attr_width`/`attr_length` настроек плагина, только если есть значения), 7 полей редактирования (тип цены, цена отрез, цена опт, ед. изм., мин. заказ, шаг кол-во, объём ед.), кнопка «Сохранить»
4. При нажатии «Сохранить» — AJAX `cp_save_product`: обновляет `post_meta` для одного товара, рядом с кнопкой отображается индикатор: Загрузка… → Выполнено ✓ / Ошибка (автоскрытие через 3 секунды)

Методы:
- `get_categories_tree()` / `build_tree()` — иерархический flat-массив категорий `product_cat`
- `get_subcategory_ids()` — рекурсивно собирает ID категории и всех дочерних
- `ajax_get_products()` — загрузка товаров, строит HTML через `build_products_table()`
- `ajax_save_product()` — сохранение 7 meta-полей одного товара с валидацией nonce и прав

Подключение assets и JS-локализация (`cpBulkEdit: {ajax_url, nonce}`) ограничены хуком `woocommerce_page_custom-prices-bulk-edit`.
