# Шаблоны (templates/woocommerce/)

Все четыре переопределённых шаблона добавляют две колонки:

| Колонка | Описание |
|---|---|
| **Ед. изм.** | Единица измерения из `custom_data['unit']` или из `_units` товара |
| **Общее кол-во / Площадь** | Для `opt`: `_volume_unit × qty`. Для `otrez`: `qty × width` (м.п.) или `qty × width × length` (м²) |

## cart.php
- Добавлена скрытая кнопка `update_cart` (нажимается JS автоматически при изменении количества)
- Кнопка «Оформить заказ» выведена под формой корзины

## review-order.php (checkout/review-order.php)
- Строка cart-subtotal скрыта (`display: none`) — итоги считаются кастомно
- Таблица имеет класс `shop_table_responsive` — обеспечивает мобильную адаптацию аналогично `cart.php`: `thead` скрывается, каждый `<td>` показывает подпись через `::before { content: attr(data-title) }`
- **v1.4.1**: на странице `/checkout/` удалены колонки `product-price`, `product-quantity`, `product-units`, `product-volume-square`, `product-total` — оставлена только `product-name`

## order-details.php (order/)
- Данные `custom_data` берутся из meta order item (`_custom_data`), а не из сессии

## order-details-item.php (order/)
- Обрабатывает возвраты: при наличии `refunded_qty` показывает зачёркнутое количество и скорректированное
