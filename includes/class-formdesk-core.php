<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * نقطه اتصال مرکزی تمام بخش‌های پلاگین
 */
class FormDesk_Core
{
    public function init()
    {
        // بارگذاری فایل ترجمه (در صورت وجود)
        load_plugin_textdomain('formdesk', false, dirname(plugin_basename(FORMDESK_FILE)) . '/languages');

        // ارتقای خودکار ساختار جدول در صورتی که پلاگین بدون غیرفعال/فعال‌سازی مجدد آپدیت شده باشد
        if (get_option('formdesk_db_version') !== FORMDESK_DB_VERSION) {
            FormDesk_Activator::install_tables();
            update_option('formdesk_db_version', FORMDESK_DB_VERSION);
        }

        $settings      = new FormDesk_Settings();
        $statuses      = new FormDesk_Statuses();
        $fields        = new FormDesk_Fields();
        $registration  = new FormDesk_Registration();
        $notifications = new FormDesk_Notifications();
        $excel         = new FormDesk_Excel();
        $pdf           = new FormDesk_Pdf();
        $admin         = new FormDesk_Admin();

        add_action('admin_init', array($settings, 'register_settings'));

        $statuses->register_hooks();
        $fields->register_hooks();
        $registration->register_hooks();
        $notifications->register_hooks();
        $excel->register_hooks();
        $pdf->register_hooks();
        $admin->register_hooks();
    }
}
