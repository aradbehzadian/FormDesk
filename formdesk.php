<?php

/**
 * Plugin Name: FormDesk
 * Description: سامانه مدیریت ثبت‌نام سفارشی — فرم ثبت‌نام آنلاین با فیلدها و وضعیت‌های کاملاً قابل‌تعریف، پنل مدیریت متقاضیان، اعلان‌های زنده، خروجی Excel و PDF.
 * Version:     2.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author:      Arad Behzadian
 * Author URI:  https://github.com/aradbehzadian/FormDesk
 * Text Domain: formdesk
 * Domain Path: /languages
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

/*
|--------------------------------------------------------------------------
| ثابت‌های اصلی پلاگین
|--------------------------------------------------------------------------
*/

define('FORMDESK_VERSION', '2.0.0');
define('FORMDESK_DB_VERSION', '1.1');
define('FORMDESK_FILE', __FILE__);
define('FORMDESK_PATH', plugin_dir_path(__FILE__));
define('FORMDESK_URL', plugin_dir_url(__FILE__));
define('FORMDESK_TABLE_NOTIFICATIONS', 'formdesk_notifications');

/*
|--------------------------------------------------------------------------
| بارگذاری Composer Autoload (mPDF / PhpSpreadsheet)
|--------------------------------------------------------------------------
*/

if (file_exists(FORMDESK_PATH . 'vendor/autoload.php')) {
    require_once FORMDESK_PATH . 'vendor/autoload.php';
}

/*
|--------------------------------------------------------------------------
| بارگذاری کلاس‌های پلاگین
|--------------------------------------------------------------------------
*/

require_once FORMDESK_PATH . 'includes/class-formdesk-activator.php';
require_once FORMDESK_PATH . 'includes/class-formdesk-settings.php';
require_once FORMDESK_PATH . 'includes/class-formdesk-statuses.php';
require_once FORMDESK_PATH . 'includes/class-formdesk-fields.php';
require_once FORMDESK_PATH . 'includes/class-formdesk-registration.php';
require_once FORMDESK_PATH . 'includes/class-formdesk-notifications.php';
require_once FORMDESK_PATH . 'includes/class-formdesk-excel.php';
require_once FORMDESK_PATH . 'includes/class-formdesk-pdf.php';
require_once FORMDESK_PATH . 'includes/class-formdesk-admin.php';
require_once FORMDESK_PATH . 'includes/class-formdesk-core.php';

/*
|--------------------------------------------------------------------------
| فعال‌سازی پلاگین (ساخت جدول اعلان‌ها)
|--------------------------------------------------------------------------
*/

register_activation_hook(__FILE__, array('FormDesk_Activator', 'activate'));

/*
|--------------------------------------------------------------------------
| بررسی فعال بودن ووکامرس
|--------------------------------------------------------------------------
*/

add_action('admin_init', function () {

    if (!class_exists('WooCommerce') && current_user_can('activate_plugins')) {

        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo 'پلاگین <strong>FormDesk</strong> برای فعالیت نیاز به فعال بودن ووکامرس دارد.';
            echo '</p></div>';
        });
    }
});

/*
|--------------------------------------------------------------------------
| اجرای پلاگین
|--------------------------------------------------------------------------
*/

function formdesk_run()
{
    // اگر ووکامرس فعال نباشد، پلاگین اصلاً اجرا نمی‌شود (فقط پیام هشدار بالا نمایش داده می‌شود)
    if (!class_exists('WooCommerce')) {
        return;
    }

    $core = new FormDesk_Core();
    $core->init();
}

add_action('plugins_loaded', 'formdesk_run');
