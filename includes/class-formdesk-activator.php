<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * وظیفه: ساخت جدول اعلان‌ها، مقادیر پیش‌فرض و محصول خودکار ووکامرس هنگام فعال‌سازی پلاگین
 */
class FormDesk_Activator
{
    public static function activate()
    {
        self::install_tables();
        self::install_defaults();
        self::maybe_create_product();

        update_option('formdesk_db_version', FORMDESK_DB_VERSION);
    }

    /*
    |--------------------------------------------------------------------------
    | ساخت / به‌روزرسانی جدول اعلان‌ها
    |--------------------------------------------------------------------------
    */

    public static function install_tables()
    {
        global $wpdb;

        $table   = $wpdb->prefix . FORMDESK_TABLE_NOTIFICATIONS;
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta("
            CREATE TABLE {$table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                order_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                type VARCHAR(30) NOT NULL DEFAULT 'applicant',
                url VARCHAR(255) NOT NULL DEFAULT '',
                seen TINYINT(1) DEFAULT 0,
                created_at DATETIME NOT NULL,
                PRIMARY KEY(id)
            ) {$charset};
        ");
    }

    /*
    |--------------------------------------------------------------------------
    | مقادیر پیش‌فرض تنظیمات و فیلدها
    |--------------------------------------------------------------------------
    */

    public static function install_defaults()
    {
        if (!get_option('formdesk_settings')) {
            update_option('formdesk_settings', FormDesk_Settings::defaults());
        }

        if (!get_option('formdesk_fields')) {
            update_option('formdesk_fields', FormDesk_Fields::defaults());
        }

        if (!get_option('formdesk_statuses')) {
            update_option('formdesk_statuses', FormDesk_Statuses::defaults());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ساخت خودکار محصول ووکامرس ثبت‌نام (فقط یک‌بار)
    |--------------------------------------------------------------------------
    | برای جلوگیری از ساخت تکراری (مثلاً وقتی پلاگین حذف و دوباره نصب می‌شود
    | ولی محصول قبلی به‌صورت دستی حذف نشده)، وجود محصول از طریق یک متای
    | اختصاصی (_formdesk_auto_product) بررسی می‌شود که مستقل از تنظیمات
    | پلاگین است و با حذف/نصب مجدد پلاگین از بین نمی‌رود.
    |--------------------------------------------------------------------------
    */

    public static function maybe_create_product()
    {
        if (!class_exists('WooCommerce') || !class_exists('WC_Product_Simple')) {
            return;
        }

        $settings_url = admin_url('admin.php?page=formdesk-settings');

        $existing = get_posts(array(
            'post_type'   => 'product',
            'post_status' => 'any',
            'numberposts' => 1,
            'meta_key'    => '_formdesk_auto_product',
            'meta_value'  => '1',
            'fields'      => 'ids',
        ));

        if (!empty($existing)) {

            $product_id = $existing[0];

            FormDesk_Notifications::add(
                0,
                'محصول ووکامرس یافت شد',
                'محصولی متناسب با این افزونه از قبل در فروشگاه وجود دارد و دوباره ساخته نشد. شناسه محصول: #' . $product_id . ' — شما باید این شناسه را در قسمت تنظیمات افزونه، در فیلد «شناسه محصول ووکامرس»، درج نمایید.',
                'settings',
                $settings_url
            );

            return;
        }

        $product = new WC_Product_Simple();

        $product->set_name('هزینه ثبت‌نام');
        $product->set_description('این محصول فقط برای ثبت درخواست ثبت‌نام از طریق FormDesk استفاده می‌شود.');
        $product->set_status('publish');
        $product->set_catalog_visibility('visible');
        $product->set_manage_stock(false);
        $product->set_stock_status('instock');

        $product_id = $product->save();

        if (!$product_id) {
            return;
        }

        update_post_meta($product_id, '_formdesk_auto_product', '1');

        FormDesk_Notifications::add(
            0,
            'محصول ووکامرس ساخته شد',
            'یک محصول ووکامرسی برای ثبت‌نام متقاضیان به‌طور خودکار ساخته شد. شناسه محصول: #' . $product_id . ' — شما باید این شناسه را در قسمت تنظیمات افزونه، در فیلد «شناسه محصول ووکامرس»، درج نمایید.',
            'settings',
            $settings_url
        );
    }
}
