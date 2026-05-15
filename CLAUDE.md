# Custom Prices WooCommerce

Плагин для WooCommerce, реализующий систему **кастомных цен** для товаров, которые продаются не по стандартной схеме «штука × цена». Поддерживает два специальных режима:

- **opt (оптовая)** — товар продаётся за единицу по двум ценам: РРЦ (розничная) и ОПТ (оптовая).
- **otrez (на отрез)** — товар продаётся метрами (м.п.) или целыми рулонами. Цена зависит от выбранной ширины рулона и длины отреза.

Для товаров с типом «Стандартная» плагин не вмешивается. Дополнительно: на страницах товаров искусственной травы доступна генерация **PDF коммерческого предложения** (через pdfMake).

---

## Структура файлов

```
custom-prices-woocommerce/
├── custom-prices-woocommerce.php              # Точка входа, проверка WC, загрузка классов
├── includes/
│   ├── class-custom-prices-woocommerce.php    # Singleton-координатор: assets, шаблоны, сохранение заказов
│   ├── class-custom-prices-admin.php          # Поля товара в WC-админке (мета-данные)
│   ├── class-custom-prices-frontend.php       # Вывод цен и форм на product/listing страницах
│   ├── class-custom-prices-cart.php           # Кастомная корзина: колонки, итоги, AJAX-обновления
│   ├── class-custom-prices-ajax.php           # AJAX-обработчики: добавление в корзину, попап, итого
│   ├── class-custom-prices-settings.php       # Страница глобальных настроек плагина в WC-админке
│   ├── class-custom-prices-bulk-edit.php      # Массовое редактирование цен товаров по категории
│   ├── class-custom-prices-sync.php           # [multisite] Автосинхронизация цен при сохранении
│   ├── class-custom-prices-multisite-bulk-edit.php  # [multisite] Сетевое массовое редактирование
│   ├── class-custom-prices-multisite-settings.php   # [multisite] Настройки синхронизации
│   └── class-custom-prices-options-sync.php   # [multisite] Синхронизация custom_prices_options
├── templates/woocommerce/
│   ├── cart/cart.php
│   ├── checkout/review-order.php
│   └── order/order-details.php + order-details-item.php
├── assets/
│   ├── css/custom-prices.css + admin-bulk-edit.css + admin-multisite-bulk-edit.css
│   └── js/custom-prices.js + admin-bulk-edit.js + admin-multisite-bulk-edit.js
│       + admin-options-sync.js + pdfmake/
└── DOCS/                                      # Подробная документация (см. ниже)
```

---

## Документация (DOCS/)

| Файл | Содержание |
|------|-----------|
| [DOCS/architecture.md](DOCS/architecture.md) | Ключевые принципы, мета-поля, глобальные настройки, формулы расчёта opt/otrez, поток add-to-cart |
| [DOCS/classes.md](DOCS/classes.md) | Описание всех PHP-классов: хуки, методы, ответственность |
| [DOCS/frontend.md](DOCS/frontend.md) | Попап на листинге, JS-секции, PDF КП, `get_labels()`, `render_price_label()`, CSS мобайл-метки |
| [DOCS/templates.md](DOCS/templates.md) | Переопределённые шаблоны WC: корзина, checkout, детали заказа |
| [DOCS/settings-labels.md](DOCS/settings-labels.md) | Текстовые настройки (v1.2.0): 24 ключа для кнопок, табов, меток, суффиксов |
| [DOCS/multisite.md](DOCS/multisite.md) | Синхронизация цен и настроек в мультисети (v1.3.0–v1.4.1) |
| [DOCS/known-issues.md](DOCS/known-issues.md) | Известные особенности и таблица исправленных багов |
| [DOCS/changelog.md](DOCS/changelog.md) | История версий v1.0.x → v1.4.1 |
