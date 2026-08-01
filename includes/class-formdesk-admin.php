<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * پنل مدیریت متقاضیان، متاباکس سفارش، آمار و عملیات ایجکس
 */
class FormDesk_Admin
{
    public function register_hooks()
    {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

        add_action('woocommerce_admin_order_data_after_order_details', array($this, 'render_order_meta_box'));
        add_action('woocommerce_process_shop_order_meta', array($this, 'save_order_meta'));

        add_action('wp_ajax_formdesk_update_status', array($this, 'ajax_update_status'));
        add_action('wp_ajax_formdesk_delete_registration', array($this, 'ajax_delete'));
        add_action('wp_ajax_formdesk_get_stats', array($this, 'ajax_get_stats'));
        add_action('wp_ajax_formdesk_get_pending_count', array($this, 'ajax_get_pending_count'));
    }

    /*
    |--------------------------------------------------------------------------
    | منوی مدیریت
    |--------------------------------------------------------------------------
    */

    public function register_menu()
    {
        $pending_count = $this->get_pending_count();
        $menu_title    = 'FormDesk';

        if ($pending_count > 0) {
            $menu_title .= ' <span class="formdesk-count">' . $pending_count . '</span>';
        }

        $icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><text x="50%" y="15" font-size="15" text-anchor="middle">📝</text></svg>';
        $icon_url = 'data:image/svg+xml;base64,' . base64_encode($icon_svg);

        add_menu_page(
            'FormDesk',
            $menu_title,
            'manage_woocommerce',
            'formdesk',
            array($this, 'render_dashboard'),
            $icon_url,
            56
        );

        // زیرمنوی «داشبورد» — همان صفحه‌ی اصلی پلاگین با یک برچسب مشخص
        add_submenu_page(
            'formdesk',
            'داشبورد FormDesk',
            'داشبورد',
            'manage_woocommerce',
            'formdesk',
            array($this, 'render_dashboard')
        );

        $fields = new FormDesk_Fields();
        $fields->register_menu();

        $settings = new FormDesk_Settings();
        $settings->register_menu();
    }

    public function enqueue_assets($hook)
    {
        // استایل به دلیل بج تعداد در منوی کناری، در تمام صفحات مدیریت لازم است
        wp_enqueue_style('formdesk-admin', FORMDESK_URL . 'assets/css/admin.css', array(), FORMDESK_VERSION);

        if ($hook !== 'toplevel_page_formdesk') {
            return;
        }

        wp_enqueue_script(
            'formdesk-admin',
            FORMDESK_URL . 'assets/js/admin.js',
            array('jquery'),
            FORMDESK_VERSION,
            true
        );

        wp_localize_script('formdesk-admin', 'FormDeskAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('formdesk_admin_nonce'),
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | تعداد متقاضیان در انتظار تماس (برای بج منو)
    |--------------------------------------------------------------------------
    */

    public function get_pending_count()
    {
        $default_status = FormDesk_Statuses::default_key();

        if ($default_status === '') {
            return 0;
        }

        $orders = wc_get_orders(array(
            'limit'  => -1,
            'status' => array('on-hold', 'processing'),
        ));

        $count = 0;

        foreach ($orders as $order) {

            if (!FormDesk_Registration::is_registration_order($order)) {
                continue;
            }

            if ($order->get_meta('_fd_status') === $default_status) {
                $count++;
            }
        }

        return $count;
    }

    /*
    |--------------------------------------------------------------------------
    | صفحه داشبورد متقاضیان
    |--------------------------------------------------------------------------
    */

    public function render_dashboard()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('دسترسی غیرمجاز');
        }

        if (!empty($_GET['notification'])) {
            global $wpdb;
            $table = $wpdb->prefix . FORMDESK_TABLE_NOTIFICATIONS;
            $wpdb->update($table, array('seen' => 1), array('id' => intval($_GET['notification'])), array('%d'), array('%d'));
        }

        $current_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
        $current_page   = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page       = (int) FormDesk_Settings::get('per_page', 20);
        $current_order  = (isset($_GET['order']) && $_GET['order'] === 'ASC') ? 'ASC' : 'DESC';

        $orders = wc_get_orders(array(
            'limit'   => $per_page,
            'paged'   => $current_page,
            'orderby' => 'date',
            'order'   => $current_order,
            'return'  => 'objects',
        ));

        $total_orders = wc_get_orders(array('limit' => -1, 'return' => 'ids'));
        $total_registration = 0;

        foreach ($total_orders as $order_id) {
            $order = wc_get_order($order_id);
            if ($order && FormDesk_Registration::is_registration_order($order)) {
                $total_registration++;
            }
        }

        $total_pages = $per_page > 0 ? ceil($total_registration / $per_page) : 1;

        $status_labels = FormDesk_Statuses::labels();

        $stats = array('total' => 0);

        foreach ($status_labels as $key => $label) {
            $stats[$key] = 0;
        }

        foreach ($orders as $order) {

            if (!FormDesk_Registration::is_registration_order($order)) {
                continue;
            }

            $stats['total']++;
            $status = $order->get_meta('_fd_status');

            if (isset($stats[$status])) {
                $stats[$status]++;
            }
        }

        $statuses = $status_labels;
        $show_status_column = (bool) FormDesk_Settings::get('show_status_column', 1);
        $show_stat_cards    = (bool) FormDesk_Settings::get('show_stat_cards', 1);
?>

        <div class="wrap">

            <h1>لیست متقاضیان</h1>
            <hr>

            <p style="margin:20px 0;">
                <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=formdesk_export_excel'), 'formdesk_export_excel'); ?>" class="button button-primary">
                    📥 خروجی Excel
                </a>
            </p>

            <?php if ($show_stat_cards) : ?>
                <div class="formdesk-dashboard">
                    <div class="card">
                        <h2 data-stat-key="total"><?php echo $stats['total']; ?></h2>
                        <p>کل متقاضیان</p>
                    </div>
                    <?php foreach ($status_labels as $key => $label) : ?>
                        <div class="card">
                            <h2 data-stat-key="<?php echo esc_attr($key); ?>"><?php echo (int) $stats[$key]; ?></h2>
                            <p><?php echo esc_html($label); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div style="margin:20px 0;">
                <?php
                $filters = array('all' => 'کل متقاضیان') + $status_labels;

                foreach ($filters as $key => $title) {
                    $url = admin_url('admin.php?page=formdesk');
                    if ($key !== 'all') {
                        $url .= '&status=' . $key;
                    }
                    $class = ($current_status === $key) ? 'button-primary' : '';
                    echo '<a href="' . esc_url($url) . '" class="button ' . esc_attr($class) . '" style="margin-left:8px">' . esc_html($title) . '</a>';
                }
                ?>
            </div>

            <p style="margin:20px 0">
                <input type="text" id="formdesk-search" class="regular-text"
                    placeholder="🔍 جستجو بر اساس نام، کد ملی یا شماره همراه..." style="width:350px;max-width:100%;">
            </p>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>شماره</th>
                        <th>نام و نام خانوادگی</th>
                        <th>کد ملی</th>
                        <th>موبایل</th>
                        <?php if ($show_status_column) : ?>
                            <th>وضعیت متقاضی</th>
                        <?php endif; ?>
                        <th>
                            <?php
                            $new_order = ($current_order === 'DESC') ? 'ASC' : 'DESC';
                            $url = add_query_arg(array(
                                'page'   => 'formdesk',
                                'status' => $current_status !== 'all' ? $current_status : false,
                                'paged'  => 1,
                                'order'  => $new_order,
                            ), admin_url('admin.php'));
                            ?>
                            <a href="<?php echo esc_url($url); ?>" style="text-decoration:none;">
                                تاریخ ثبت <?php echo $current_order === 'DESC' ? '↓' : '↑'; ?>
                            </a>
                        </th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order) :

                        $status = $order->get_meta('_fd_status');

                        if ($current_status !== 'all' && $status !== $current_status) {
                            continue;
                        }

                        if (!FormDesk_Registration::is_registration_order($order)) {
                            continue;
                        }
                    ?>
                        <tr id="registration-<?php echo esc_attr($order->get_id()); ?>"
                            data-name="<?php echo esc_attr($order->get_meta('_fd_first_name') . ' ' . $order->get_meta('_fd_last_name')); ?>"
                            data-national="<?php echo esc_attr($order->get_meta('_fd_national_code')); ?>"
                            data-mobile="<?php echo esc_attr($order->get_meta('_fd_mobile')); ?>">

                            <td>#<?php echo esc_html($order->get_id()); ?></td>
                            <td><?php echo esc_html($order->get_meta('_fd_first_name') . ' ' . $order->get_meta('_fd_last_name')); ?></td>
                            <td><?php echo esc_html($order->get_meta('_fd_national_code')); ?></td>
                            <td><?php echo esc_html($order->get_meta('_fd_mobile')); ?></td>
                            <?php if ($show_status_column) : ?>
                                <td>
                                    <select class="formdesk-status" data-order="<?php echo esc_attr($order->get_id()); ?>">
                                        <?php foreach ($statuses as $key => $label) : ?>
                                            <option value="<?php echo esc_attr($key); ?>" <?php selected($status, $key); ?>>
                                                <?php echo esc_html($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            <?php endif; ?>
                            <td><?php echo esc_html($order->get_date_created()->date('Y/m/d H:i')); ?></td>
                            <td>
                                <a class="button button-small" href="<?php echo esc_url(admin_url('post.php?post=' . $order->get_id() . '&action=edit')); ?>">جزئیات</a>

                                <?php $image = $order->get_meta('_fd_document');
                                if ($image) : ?>
                                    <a class="button button-small" href="<?php echo esc_url($image); ?>" target="_blank">مشاهده تصویر</a>
                                <?php endif; ?>

                                <a class="button button-small" target="_blank"
                                    href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=formdesk_export_pdf&order_id=' . $order->get_id()), 'formdesk_export_pdf_' . $order->get_id()); ?>">
                                    PDF
                                </a>

                                <button type="button" class="button button-small button-link-delete formdesk-delete" data-order="<?php echo esc_attr($order->get_id()); ?>">
                                    حذف
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1) : ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links(array(
                            'base' => add_query_arg(array(
                                'page'   => 'formdesk',
                                'status' => $current_status !== 'all' ? $current_status : false,
                                'order'  => $current_order,
                                'paged'  => '%#%',
                            ), admin_url('admin.php')),
                            'format'    => '',
                            'current'   => $current_page,
                            'total'     => $total_pages,
                            'prev_text' => '« قبلی',
                            'next_text' => 'بعدی »',
                        ));
                        ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    <?php
    }

    /*
    |--------------------------------------------------------------------------
    | متاباکس اطلاعات ثبت‌نام در صفحه سفارش
    |--------------------------------------------------------------------------
    */

    public function render_order_meta_box($order)
    {
        if (!FormDesk_Registration::is_registration_order($order)) {
            return;
        }

        $status       = $order->get_meta('_fd_status');
        $status_label = FormDesk_Statuses::labels();
    ?>
        <div class="formdesk-registration-box">
            <h3>اطلاعات ثبت‌نام</h3>

            <p style="margin-bottom:20px">
                <strong>وضعیت متقاضی</strong>
                <select name="fd_registration_status" style="min-width:250px">
                    <?php foreach ($status_label as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($status, $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <table class="widefat">
                <?php foreach (FormDesk_Fields::all() as $field) :

                    $raw = $order->get_meta('_fd_' . $field['key']);
                ?>
                    <tr>
                        <td><?php echo esc_html($field['label']); ?></td>
                        <td>
                            <?php if ($field['type'] === 'file') : ?>
                                <?php echo $raw ? '<a href="' . esc_url($raw) . '" target="_blank">مشاهده تصویر</a>' : '-'; ?>
                            <?php elseif ($field['type'] === 'textarea') : ?>
                                <?php echo nl2br(esc_html($raw)); ?>
                            <?php else : ?>
                                <?php echo esc_html($raw) !== '' ? esc_html($raw) : '-'; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <?php
        $history = $order->get_meta('_fd_history');

        if (!empty($history) && is_array($history)) :
        ?>
            <hr>
            <h3>تاریخچه تغییرات وضعیت</h3>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>زمان</th>
                        <th>کاربر</th>
                        <th>از وضعیت</th>
                        <th>به وضعیت</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_reverse($history) as $item) : ?>
                        <tr>
                            <td><?php echo esc_html($item['time']); ?></td>
                            <td><?php echo esc_html($item['user']); ?></td>
                            <td><?php echo esc_html($item['from']); ?></td>
                            <td><?php echo esc_html($item['to']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
<?php endif;
    }

    public function save_order_meta($order_id)
    {
        if (!isset($_POST['fd_registration_status'])) {
            return;
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        $order->update_meta_data('_fd_status', sanitize_text_field($_POST['fd_registration_status']));
        $order->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Ajax: تغییر وضعیت
    |--------------------------------------------------------------------------
    */

    public function ajax_update_status()
    {
        if (!check_ajax_referer('formdesk_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'درخواست نامعتبر است.'));
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error();
        }

        $order_id = intval($_POST['order_id'] ?? 0);
        $status   = sanitize_text_field($_POST['status'] ?? '');
        $statuses = FormDesk_Statuses::labels();

        if (!isset($statuses[$status])) {
            wp_send_json_error();
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(array('message' => 'سفارش پیدا نشد.'));
        }

        $old_status = $order->get_meta('_fd_status');

        if ($old_status !== $status) {

            $order->update_meta_data('_fd_status', $status);
            $order->add_order_note('وضعیت متقاضی تغییر کرد به: ' . $statuses[$status]);
            $order->save();

            FormDesk_Registration::add_history($order_id, $old_status, $status);
        }

        wp_send_json_success();
    }

    /*
    |--------------------------------------------------------------------------
    | Ajax: حذف متقاضی
    |--------------------------------------------------------------------------
    */

    public function ajax_delete()
    {
        if (!check_ajax_referer('formdesk_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'درخواست نامعتبر است.'));
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error();
        }

        $order_id = intval($_POST['order_id'] ?? 0);

        if (!$order_id) {
            wp_send_json_error();
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(array('message' => 'سفارش پیدا نشد.'));
        }

        // حذف کامل (force delete) - چه سفارش‌ها به‌صورت Post ذخیره شده باشند
        // چه در جدول اختصاصی HPOS ووکامرس، این متد به‌درستی کار می‌کند
        $order->delete(true);

        wp_send_json_success();
    }

    /*
    |--------------------------------------------------------------------------
    | Ajax: آمار زنده
    |--------------------------------------------------------------------------
    */

    public function ajax_get_stats()
    {
        if (!check_ajax_referer('formdesk_admin_nonce', 'nonce', false)) {
            wp_send_json_error();
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error();
        }

        $status_labels = FormDesk_Statuses::labels();

        $stats = array('total' => 0);

        foreach ($status_labels as $key => $label) {
            $stats[$key] = 0;
        }

        $orders = wc_get_orders(array('limit' => -1));

        foreach ($orders as $order) {

            if (!FormDesk_Registration::is_registration_order($order)) {
                continue;
            }

            $stats['total']++;
            $status = $order->get_meta('_fd_status');

            if (isset($stats[$status])) {
                $stats[$status]++;
            }
        }

        wp_send_json_success($stats);
    }

    public function ajax_get_pending_count()
    {
        if (!check_ajax_referer('formdesk_admin_nonce', 'nonce', false)) {
            wp_send_json_error();
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error();
        }

        wp_send_json_success(array('count' => $this->get_pending_count()));
    }
}
