<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * مدیریت وضعیت‌های متقاضی (منوی کشویی وضعیت)
 *
 * برخلاف نسخه‌های قبلی که وضعیت‌ها (در انتظار تماس / ثبت‌نام نهایی شد / رد
 * درخواست) به‌صورت ثابت در کد نوشته شده بودند، این کلاس امکان می‌دهد طراح
 * وب‌سایتی که FormDesk را نصب می‌کند، از پیشخوان، وضعیت‌ها را به دلخواه
 * ویرایش، اضافه یا حذف کند — بدون نیاز به ویرایش کد.
 *
 * نکته: اولین وضعیت موجود در لیست، همیشه به‌عنوان «وضعیت اولیه» برای
 * متقاضیانی که تازه فرم را ارسال کرده‌اند در نظر گرفته می‌شود.
 */
class FormDesk_Statuses
{
    const OPTION_KEY = 'formdesk_statuses';

    /*
    |--------------------------------------------------------------------------
    | وضعیت‌های پیش‌فرض (فقط هنگام فعال‌سازی اولیه پلاگین ساخته می‌شوند)
    |--------------------------------------------------------------------------
    */

    public static function defaults()
    {
        return array(
            array('key' => 'calling',  'label' => '🔵 در انتظار تماس'),
            array('key' => 'approved', 'label' => '🟢 ثبت‌نام نهایی شد'),
            array('key' => 'rejected', 'label' => '🔴 رد درخواست'),
        );
    }

    public static function all()
    {
        $statuses = get_option(self::OPTION_KEY, null);

        if (!is_array($statuses) || empty($statuses)) {
            $statuses = self::defaults();
            update_option(self::OPTION_KEY, $statuses);
        }

        return $statuses;
    }

    /*
    |--------------------------------------------------------------------------
    | آرایه‌ی کلید => برچسب (برای استفاده در select و شمارش آمار)
    |--------------------------------------------------------------------------
    */

    public static function labels()
    {
        $labels = array();

        foreach (self::all() as $status) {
            $labels[$status['key']] = $status['label'];
        }

        return $labels;
    }

    /*
    |--------------------------------------------------------------------------
    | وضعیت اولیه (اولین آیتم لیست) — برای متقاضیان تازه ثبت‌نام‌کرده
    |--------------------------------------------------------------------------
    */

    public static function default_key()
    {
        $all = self::all();
        return isset($all[0]['key']) ? $all[0]['key'] : '';
    }

    /*
    |--------------------------------------------------------------------------
    | برچسب یک وضعیت بر اساس کلید (با پشتیبانی از وضعیت‌های حذف‌شده)
    |--------------------------------------------------------------------------
    */

    public static function get_label($key)
    {
        $labels = self::labels();

        if (isset($labels[$key])) {
            return $labels[$key];
        }

        // این وضعیت از لیست فعلی حذف شده؛ برای اینکه سوابق قدیمی گم نشوند
        // همچنان با یک برچسب قابل‌فهم نمایش داده می‌شود
        return $key !== '' ? $key . ' (حذف‌شده)' : '—';
    }

    /*
    |--------------------------------------------------------------------------
    | افزودن یا ویرایش یک وضعیت
    |--------------------------------------------------------------------------
    */

    public static function save_status($data)
    {
        $statuses = self::all();
        $label    = sanitize_text_field($data['label'] ?? '');

        if ($label === '') {
            return new WP_Error('invalid_label', 'عنوان وضعیت نمی‌تواند خالی باشد.');
        }

        $key   = sanitize_key($data['key'] ?? '');
        $found = false;

        if ($key !== '') {
            foreach ($statuses as $i => $status) {
                if ($status['key'] === $key) {
                    $statuses[$i]['label'] = $label;
                    $found = true;
                    break;
                }
            }
        }

        if (!$found) {

            $existing_keys = wp_list_pluck($statuses, 'key');
            $i = 1;

            do {
                $new_key = 'status_' . $i;
                $i++;
            } while (in_array($new_key, $existing_keys, true));

            $statuses[] = array('key' => $new_key, 'label' => $label);
        }

        update_option(self::OPTION_KEY, $statuses);

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | حذف یک وضعیت (حداقل یک وضعیت باید همیشه باقی بماند)
    |--------------------------------------------------------------------------
    */

    public static function delete_status($key)
    {
        $statuses = self::all();

        if (count($statuses) <= 1) {
            return new WP_Error('last_status', 'حداقل یک وضعیت باید در لیست باقی بماند.');
        }

        $statuses = array_values(array_filter($statuses, function ($status) use ($key) {
            return $status['key'] !== $key;
        }));

        update_option(self::OPTION_KEY, $statuses);

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | صفحه مدیریت وضعیت‌ها (زیرمنوی تنظیمات)
    |--------------------------------------------------------------------------
    */

    public function register_hooks()
    {
        add_action('admin_post_formdesk_save_status', array($this, 'handle_save'));
        add_action('admin_post_formdesk_delete_status', array($this, 'handle_delete'));
    }

    public function handle_save()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('دسترسی غیرمجاز');
        }

        check_admin_referer('formdesk_save_status');

        $result   = self::save_status($_POST);
        $redirect = admin_url('admin.php?page=formdesk-settings');

        if (is_wp_error($result)) {
            $redirect = add_query_arg('fd_error', urlencode($result->get_error_message()), $redirect);
        } else {
            $redirect = add_query_arg('fd_saved', 1, $redirect);
        }

        wp_safe_redirect($redirect);
        exit;
    }

    public function handle_delete()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('دسترسی غیرمجاز');
        }

        $key = sanitize_key($_GET['key'] ?? '');

        check_admin_referer('formdesk_delete_status_' . $key);

        $result   = self::delete_status($key);
        $redirect = admin_url('admin.php?page=formdesk-settings');

        if (is_wp_error($result)) {
            $redirect = add_query_arg('fd_error', urlencode($result->get_error_message()), $redirect);
        } else {
            $redirect = add_query_arg('fd_saved', 1, $redirect);
        }

        wp_safe_redirect($redirect);
        exit;
    }
}
