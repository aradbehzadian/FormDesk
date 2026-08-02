<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * فرم ثبت‌نام عمومی (بر اساس فیلدهای پویای تعریف‌شده در تنظیمات)، ثبت سفارش و تاریخچه وضعیت
 */
class FormDesk_Registration
{
    public function register_hooks()
    {
        add_shortcode('formdesk_form', array($this, 'render_form'));

        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        add_action('wp_ajax_formdesk_register', array($this, 'ajax_register'));
        add_action('wp_ajax_nopriv_formdesk_register', array($this, 'ajax_register'));
    }

    /*
    |--------------------------------------------------------------------------
    | بارگذاری استایل و اسکریپت فرانت‌اند (فقط جایی که شورتکد استفاده شده)
    |--------------------------------------------------------------------------
    */

    public function enqueue_assets()
    {
        if (!is_singular()) {
            return;
        }

        global $post;

        if (!$post || !has_shortcode($post->post_content, 'formdesk_form')) {
            return;
        }

        wp_enqueue_style(
            'formdesk-frontend',
            FORMDESK_URL . 'assets/css/frontend.css',
            array(),
            FORMDESK_VERSION
        );

        // اعمال رنگ‌ها و شکل ظاهری سفارشی‌شده از تنظیمات، بدون نیاز به ویرایش فایل CSS
        wp_add_inline_style('formdesk-frontend', self::style_vars_css());

        wp_enqueue_script(
            'formdesk-frontend',
            FORMDESK_URL . 'assets/js/frontend.js',
            array('jquery'),
            FORMDESK_VERSION,
            true
        );

        wp_localize_script('formdesk-frontend', 'FormDesk', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('formdesk_register_nonce'),
        ));

        // انتخابگر تاریخ شمسی فقط در صورت وجود حداقل یک فیلد تاریخ بارگذاری می‌شود
        if (FormDesk_Fields::has_jalali_field()) {

            wp_enqueue_style(
                'formdesk-jalali-datepicker',
                FORMDESK_URL . 'assets/css/jalali-datepicker.css',
                array(),
                FORMDESK_VERSION
            );

            wp_enqueue_script(
                'formdesk-jalali-datepicker',
                FORMDESK_URL . 'assets/js/jalali-datepicker.js',
                array('jquery'),
                FORMDESK_VERSION,
                true
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ساخت رشته‌ی متغیرهای CSS بر اساس تنظیمات (برای رنگ‌بندی و شکل ظاهری فرم)
    |--------------------------------------------------------------------------
    | این تابع در دو جا استفاده می‌شود: هم به‌عنوان inline style ضمیمه‌ی
    | استایل اصلی (enqueue_assets)، و هم مستقیماً کنار خروجی شورتکد
    | (render_form) تا مطمئن شویم مستقل از این‌که شورتکد در کجای سایت
    | (صفحه عادی، ویجت، المنتور و ...) قرار گرفته، رنگ‌های سفارشی‌شده
    | همیشه اعمال می‌شوند.
    |--------------------------------------------------------------------------
    */

    public static function style_vars_css()
    {
        $primary_color       = FormDesk_Settings::get('primary_color', '#5751E1');
        $accent_color        = FormDesk_Settings::get('accent_color', '#FFC327');
        $page_bg_color       = FormDesk_Settings::get('page_bg_color', '#F5F7FD');
        $card_bg_color       = FormDesk_Settings::get('card_bg_color', '#FFFFFF');
        $heading_color       = FormDesk_Settings::get('heading_color', '#26273B');
        $subtitle_color      = FormDesk_Settings::get('subtitle_color', '#777777');
        $label_color         = FormDesk_Settings::get('label_color', '#444444');
        $input_bg_color      = FormDesk_Settings::get('input_bg_color', '#FAFBFF');
        $input_border_color  = FormDesk_Settings::get('input_border_color', '#E8EBF5');
        $button_text_color   = FormDesk_Settings::get('button_text_color', '#FFFFFF');
        $corner_style        = FormDesk_Settings::get('corner_style', 'rounded');

        $corner_radii = array(
            'rounded' => array('card' => '28px', 'field' => '18px'),
            'soft'    => array('card' => '18px', 'field' => '12px'),
            'sharp'   => array('card' => '8px',  'field' => '6px'),
        );

        $radius = isset($corner_radii[$corner_style]) ? $corner_radii[$corner_style] : $corner_radii['rounded'];

        return ':root{'
            . '--fd-primary:' . esc_attr($primary_color) . ';'
            . '--fd-accent:' . esc_attr($accent_color) . ';'
            . '--fd-page-bg:' . esc_attr($page_bg_color) . ';'
            . '--fd-card-bg:' . esc_attr($card_bg_color) . ';'
            . '--fd-heading:' . esc_attr($heading_color) . ';'
            . '--fd-subtitle:' . esc_attr($subtitle_color) . ';'
            . '--fd-label:' . esc_attr($label_color) . ';'
            . '--fd-input-bg:' . esc_attr($input_bg_color) . ';'
            . '--fd-input-border:' . esc_attr($input_border_color) . ';'
            . '--fd-button-text:' . esc_attr($button_text_color) . ';'
            . '--fd-radius-card:' . esc_attr($radius['card']) . ';'
            . '--fd-radius-field:' . esc_attr($radius['field']) . ';'
            . '}';
    }

    /*
    |--------------------------------------------------------------------------
    | خروجی شورتکد فرم (بر اساس فیلدهای پویا)
    |--------------------------------------------------------------------------
    */

    public function render_form()
    {
        $logo_url            = FormDesk_Settings::get('logo_url', '');
        $form_title          = FormDesk_Settings::get('form_title', 'ثبت نام آنلاین');
        $form_subtitle       = FormDesk_Settings::get('form_subtitle', '');
        $button_text         = FormDesk_Settings::get('submit_button_text', 'ثبت نام');
        $show_icon           = (bool) FormDesk_Settings::get('show_form_icon', 1);
        $show_icon_circle_bg = (bool) FormDesk_Settings::get('show_icon_circle_bg', 1);
        $icon_emoji          = FormDesk_Settings::get('form_icon_emoji', '📝');
        $logo_class          = 'fd-logo' . ($show_icon_circle_bg ? '' : ' fd-logo-plain');

        ob_start();
?>

        <style id="formdesk-inline-vars">
            <?php echo self::style_vars_css(); ?>
        </style>

        <div class="fd-form-wrapper">

            <div class="fd-form-card">

                <div class="fd-form-header">
                    <?php if ($show_icon) : ?>
                        <?php if ($logo_url) : ?>
                            <div class="<?php echo esc_attr($logo_class); ?>"><img src="<?php echo esc_url($logo_url); ?>" alt="" style="max-height:56px;"></div>
                        <?php else : ?>
                            <div class="<?php echo esc_attr($logo_class); ?>"><?php echo esc_html($icon_emoji); ?></div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <h2><?php echo esc_html($form_title); ?></h2>
                    <?php if ($form_subtitle) : ?>
                        <p><?php echo esc_html($form_subtitle); ?></p>
                    <?php endif; ?>
                </div>

                <form id="formdesk-registration-form" enctype="multipart/form-data">

                    <?php foreach (FormDesk_Fields::all() as $field) : ?>
                        <?php FormDesk_Fields::render_frontend_field($field); ?>
                    <?php endforeach; ?>

                    <button class="fd-submit" type="submit"><?php echo esc_html($button_text); ?></button>

                </form>

                <div id="formdesk-register-result"></div>

            </div>

        </div>

<?php
        return ob_get_clean();
    }

    /*
    |--------------------------------------------------------------------------
    | محدودسازی نرخ ارسال فرم (جلوگیری از اسپم/سیل درخواست از یک IP)
    |--------------------------------------------------------------------------
    */

    public static function is_rate_limited($max_attempts = 5, $window_seconds = 600)
    {
        $ip = '';

        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        if ($ip === '') {
            return false; // در نبود IP قابل‌تشخیص، محدودیت اعمال نمی‌شود
        }

        $key      = 'formdesk_rl_' . md5($ip);
        $attempts = (int) get_transient($key);

        if ($attempts >= $max_attempts) {
            return true;
        }

        set_transient($key, $attempts + 1, $window_seconds);

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | اعتبارسنجی کد ملی ایران
    |--------------------------------------------------------------------------
    */

    public static function validate_national_code($code)
    {
        if (!preg_match('/^\d{10}$/', $code)) {
            return false;
        }

        if (preg_match('/^(\d)\1{9}$/', $code)) {
            return false;
        }

        $check = intval(substr($code, 9, 1));
        $sum   = 0;

        for ($i = 0; $i < 9; $i++) {
            $sum += intval($code[$i]) * (10 - $i);
        }

        $remain = $sum % 11;

        return ($remain < 2 && $check == $remain) || ($remain >= 2 && $check == (11 - $remain));
    }

    /*
    |--------------------------------------------------------------------------
    | لیست وضعیت‌های متقاضی (اکنون کاملاً قابل مدیریت است — نگاه کنید به FormDesk_Statuses)
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | ثبت تاریخچه تغییر وضعیت
    |--------------------------------------------------------------------------
    */

    public static function add_history($order_id, $from, $to)
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        $history = $order->get_meta('_fd_history');

        if (!is_array($history)) {
            $history = array();
        }

        $user     = wp_get_current_user();
        $statuses = FormDesk_Statuses::labels();

        $history[] = array(
            'time' => current_time('mysql'),
            'user' => $user->display_name,
            'from' => $statuses[$from] ?? $from,
            'to'   => $statuses[$to] ?? $to,
        );

        $order->update_meta_data('_fd_history', $history);
        $order->save();
    }

    /*
    |--------------------------------------------------------------------------
    | آیا سفارش مربوط به محصول ثبت‌نام FormDesk هست؟
    |--------------------------------------------------------------------------
    */

    public static function is_registration_order($order)
    {
        $product_id = (int) FormDesk_Settings::get('product_id');

        foreach ($order->get_items() as $item) {
            if ((int) $item->get_product_id() === $product_id) {
                return true;
            }
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Ajax ثبت‌نام متقاضی (کاملاً بر اساس فیلدهای پویا)
    |--------------------------------------------------------------------------
    */

    public function ajax_register()
    {
        check_ajax_referer('formdesk_register_nonce', 'nonce');

        if (self::is_rate_limited()) {
            wp_send_json_error(array('message' => 'تعداد درخواست‌های شما زیاد بوده است. لطفاً چند دقیقه دیگر دوباره تلاش کنید.'));
        }

        $fields = FormDesk_Fields::all();
        $values = array();

        /*
        |----------------------------------------------------------------------
        | پردازش فیلدهای متنی/انتخابی/تاریخ
        |----------------------------------------------------------------------
        */

        foreach ($fields as $field) {

            if ($field['type'] === 'file') {
                continue; // فایل‌ها در مرحله بعد پردازش می‌شوند
            }

            $raw = wp_unslash($_POST[$field['key']] ?? '');

            if (!empty($field['required']) && trim($raw) === '') {
                wp_send_json_error(array('message' => 'لطفاً «' . $field['label'] . '» را پر کنید.'));
            }

            $value = FormDesk_Fields::sanitize_value($field, $raw);

            if (!empty($field['required']) && $field['type'] === 'jalali_date' && $value === '') {
                wp_send_json_error(array('message' => 'فرمت «' . $field['label'] . '» صحیح نیست.'));
            }

            // اعتبارسنجی اختصاصی کد ملی
            if ($field['key'] === 'national_code' && !self::validate_national_code($value)) {
                wp_send_json_error(array('message' => 'کد ملی وارد شده صحیح نیست.'));
            }

            // اعتبارسنجی اختصاصی شماره همراه
            if ($field['key'] === 'mobile' && !preg_match('/^09[0-9]{9}$/', $value)) {
                wp_send_json_error(array('message' => 'شماره همراه معتبر نیست.'));
            }

            // اعتبارسنجی گزینه‌های لیست کشویی
            if ($field['type'] === 'select' && $value !== '') {
                $options = array_map('trim', explode('،', str_replace(',', '،', $field['options'])));
                if (!in_array($value, $options, true)) {
                    wp_send_json_error(array('message' => 'مقدار «' . $field['label'] . '» نامعتبر است.'));
                }
            }

            $values[$field['key']] = $value;
        }

        /*
        |----------------------------------------------------------------------
        | بررسی تکراری نبودن کد ملی / شماره همراه
        |----------------------------------------------------------------------
        */

        if (isset($values['national_code'])) {

            $exist_national = wc_get_orders(array(
                'limit'      => 1,
                'meta_key'   => '_fd_national_code',
                'meta_value' => $values['national_code'],
            ));

            if (!empty($exist_national)) {
                wp_send_json_error(array('message' => 'این کد ملی قبلاً ثبت شده است.'));
            }
        }

        if (isset($values['mobile'])) {

            $exist_mobile = wc_get_orders(array(
                'limit'      => 1,
                'meta_key'   => '_fd_mobile',
                'meta_value' => $values['mobile'],
            ));

            if (!empty($exist_mobile)) {
                wp_send_json_error(array('message' => 'این شماره همراه قبلاً ثبت شده است.'));
            }
        }

        /*
        |----------------------------------------------------------------------
        | پردازش فیلدهای آپلود فایل
        |----------------------------------------------------------------------
        */

        $max_bytes = (int) FormDesk_Settings::get('max_upload_mb', 2) * 1024 * 1024;

        foreach ($fields as $field) {

            if ($field['type'] !== 'file') {
                continue;
            }

            $values[$field['key']] = '';

            if (empty($_FILES[$field['key']]['name'])) {

                if (!empty($field['required'])) {
                    wp_send_json_error(array('message' => 'لطفاً «' . $field['label'] . '» را بارگذاری کنید.'));
                }

                continue;
            }

            require_once ABSPATH . 'wp-admin/includes/file.php';

            if ($_FILES[$field['key']]['size'] > $max_bytes) {
                wp_send_json_error(array(
                    'message' => 'حجم «' . $field['label'] . '» نباید بیشتر از ' . FormDesk_Settings::get('max_upload_mb', 2) . ' مگابایت باشد.',
                ));
            }

            $allowed_exts  = array('jpg', 'jpeg', 'png', 'webp', 'heic', 'heif');
            $allowed_mimes = array('image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif');

            $check = wp_check_filetype_and_ext(
                $_FILES[$field['key']]['tmp_name'],
                $_FILES[$field['key']]['name']
            );

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $_FILES[$field['key']]['tmp_name']);
            finfo_close($finfo);

            if (!in_array($check['ext'], $allowed_exts) || !in_array($mime, $allowed_mimes)) {
                wp_send_json_error(array('message' => '«' . $field['label'] . '» نامعتبر است. فقط تصویر مجاز است.'));
            }

            $upload = wp_handle_upload($_FILES[$field['key']], array('test_form' => false));

            if (isset($upload['error'])) {
                wp_send_json_error(array('message' => $upload['error']));
            }

            $values[$field['key']] = $upload['url'];
        }

        /*
        |----------------------------------------------------------------------
        | بررسی وجود محصول و ساخت سفارش
        |----------------------------------------------------------------------
        */

        $product_id = (int) FormDesk_Settings::get('product_id');
        $product    = wc_get_product($product_id);

        if (!$product) {
            wp_send_json_error(array('message' => 'محصول ثبت‌نام تعریف نشده است. لطفاً با پشتیبانی تماس بگیرید.'));
        }

        $order = wc_create_order();
        $order->add_product($product, 1);

        foreach ($values as $key => $value) {
            $order->update_meta_data('_fd_' . $key, $value);
        }

        $order->update_meta_data('_fd_status', FormDesk_Statuses::default_key());

        $order->set_address(array(
            'first_name' => $values['first_name'] ?? '',
            'last_name'  => $values['last_name'] ?? '',
            'phone'      => $values['mobile'] ?? '',
        ), 'billing');

        $order->update_status('on-hold');
        $order->calculate_totals();
        $order->save();

        FormDesk_Notifications::add(
            $order->get_id(),
            'متقاضی جدید',
            trim(($values['first_name'] ?? '') . ' ' . ($values['last_name'] ?? ''))
        );

        wp_send_json_success(array(
            'message' => FormDesk_Settings::get('success_message', 'ثبت نام با موفقیت انجام شد.'),
        ));
    }
}
