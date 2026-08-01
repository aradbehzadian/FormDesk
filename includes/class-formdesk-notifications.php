<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * اعلان‌های زنده پنل مدیریت (متقاضی جدید و ...)
 */
class FormDesk_Notifications
{
    public function register_hooks()
    {
        add_action('wp_ajax_formdesk_check_notifications', array($this, 'ajax_check'));
        add_action('wp_ajax_formdesk_read_notification', array($this, 'ajax_read'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * بارگذاری اسکریپت اعلان‌ها در تمام صفحات مدیریت
     * (برخلاف نسخه اولیه که فقط در صفحه پلاگین بارگذاری می‌شد)
     */
    public function enqueue_assets()
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        wp_enqueue_script(
            'formdesk-notifications',
            FORMDESK_URL . 'assets/js/notifications.js',
            array('jquery'),
            FORMDESK_VERSION,
            true
        );

        wp_localize_script('formdesk-notifications', 'FormDeskNotification', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('formdesk_admin_nonce'),
        ));
    }

    public static function add($order_id, $title, $message, $type = 'applicant', $url = '')
    {
        global $wpdb;

        $table = $wpdb->prefix . FORMDESK_TABLE_NOTIFICATIONS;

        $wpdb->insert(
            $table,
            array(
                'order_id'   => $order_id,
                'title'      => $title,
                'message'    => $message,
                'type'       => $type,
                'url'        => $url,
                'seen'       => 0,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s', '%s', '%d', '%s')
        );
    }

    public function ajax_check()
    {
        if (!check_ajax_referer('formdesk_admin_nonce', 'nonce', false)) {
            wp_send_json_error();
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error();
        }

        global $wpdb;

        $table = $wpdb->prefix . FORMDESK_TABLE_NOTIFICATIONS;

        $notifications = $wpdb->get_results("SELECT * FROM {$table} WHERE seen = 0 ORDER BY id ASC");

        if (empty($notifications)) {
            wp_send_json_success(array('found' => false));
        }

        wp_send_json_success(array(
            'found'         => true,
            'notifications' => $notifications,
        ));
    }

    public function ajax_read()
    {
        if (!check_ajax_referer('formdesk_admin_nonce', 'nonce', false)) {
            wp_send_json_error();
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error();
        }

        global $wpdb;

        $table = $wpdb->prefix . FORMDESK_TABLE_NOTIFICATIONS;
        $id    = intval($_POST['id'] ?? 0);

        $wpdb->update(
            $table,
            array('seen' => 1),
            array('id' => $id)
        );

        wp_send_json_success();
    }
}
