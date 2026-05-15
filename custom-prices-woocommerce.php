<?php
/**
 * Plugin Name: Custom Prices WooCommerce
 * Plugin URI: https://example.com
 * Description: Плагин для WooCommerce, добавляющий цены на отрез и за опт, с расчетами и кастомной корзиной.
 * Version: 1.0.9
 * Author: Grok
 * Author URI: https://x.ai
 * Text Domain: custom-prices-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.0
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 */

// Блокировка прямого доступа к файлу через URL
if (!defined('ABSPATH')) {
    exit;
}

// Инициализация плагина после загрузки всех плагинов (plugins_loaded)
add_action('plugins_loaded', function() {
    // ✅ ИСПРАВЛЕНО: Правильная проверка для мультисайта
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>Плагин Custom Prices WooCommerce требует WooCommerce для работы!</p></div>';
        });
        return;
    }

    // Подключаем все классы плагина
    require_once __DIR__ . '/includes/class-custom-prices-woocommerce.php';  // Главный singleton-координатор
    require_once __DIR__ . '/includes/class-custom-prices-admin.php';        // Поля товара в WC-админке
    require_once __DIR__ . '/includes/class-custom-prices-frontend.php';     // Вывод цен и форм на фронтенде
    require_once __DIR__ . '/includes/class-custom-prices-cart.php';         // Кастомная корзина
    require_once __DIR__ . '/includes/class-custom-prices-ajax.php';         // AJAX-обработчики
    require_once __DIR__ . '/includes/class-custom-prices-settings.php';     // Страница настроек
    require_once __DIR__ . '/includes/class-custom-prices-bulk-edit.php';    // Массовое редактирование цен (per-site)

    // Мультисайт: синхронизация цен, сетевая страница редактирования и настроек
    if ( is_multisite() ) {
        require_once __DIR__ . '/includes/class-custom-prices-sync.php';
        require_once __DIR__ . '/includes/class-custom-prices-multisite-bulk-edit.php';
        require_once __DIR__ . '/includes/class-custom-prices-multisite-settings.php';
        require_once __DIR__ . '/includes/class-custom-prices-options-sync.php';
    }

    // Инициализация классов
    Custom_Prices_Woocommerce::get_instance();
    new Custom_Prices_Bulk_Edit();

    if ( is_multisite() ) {
        Custom_Prices_Sync::get_instance();
        Custom_Prices_Multisite_Bulk_Edit::get_instance();
        Custom_Prices_Multisite_Settings::get_instance();
        Custom_Prices_Options_Sync::get_instance();
    }
});

// Предупреждение: если тема содержит собственный cart.php, он будет иметь приоритет
// над шаблоном плагина и кастомные колонки (Ед.изм, Объём) не будут отображаться
add_action('admin_notices', function() {
    $theme_template = get_stylesheet_directory() . '/woocommerce/cart/cart.php';
    if (file_exists($theme_template)) {
        echo '<div class="notice notice-warning"><p>Тема содержит файл woocommerce/cart/cart.php, который переопределяет шаблон корзины плагина Custom Prices WooCommerce. Удалите или замените его для отображения кастомных колонок.</p></div>';
    }
});