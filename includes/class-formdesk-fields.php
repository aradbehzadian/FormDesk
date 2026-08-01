<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * سیستم فیلدهای پویا: امکان افزودن/حذف/ویرایش فیلدهای فرم ثبت‌نام از تنظیمات
 *
 * فیلدهای "national_code" و "mobile" به دلیل منطق حیاتی (اعتبارسنجی و
 * جلوگیری از ثبت تکراری) همیشه محافظت‌شده و غیرقابل‌حذف هستند.
 */
class FormDesk_Fields
{
    const OPTION_KEY = 'formdesk_fields';

    const PROTECTED_KEYS = array('national_code', 'mobile');

    /*
    |--------------------------------------------------------------------------
    | فیلدهای پیش‌فرض (فقط هنگام فعال‌سازی اولیه پلاگین ساخته می‌شوند)
    |--------------------------------------------------------------------------
    */

    public static function defaults()
    {
        return array(
            array('key' => 'first_name',       'label' => 'نام',                              'type' => 'text',        'required' => 0, 'options' => '', 'show_in_pdf' => 1),
            array('key' => 'last_name',         'label' => 'نام خانوادگی',                     'type' => 'text',        'required' => 0, 'options' => '', 'show_in_pdf' => 1),
            array('key' => 'national_code',     'label' => 'کد ملی',                           'type' => 'text',        'required' => 1, 'options' => '', 'show_in_pdf' => 1),
            array('key' => 'mobile',            'label' => 'شماره همراه',                      'type' => 'tel',         'required' => 1, 'options' => '', 'show_in_pdf' => 1),
            array('key' => 'birth_date',        'label' => 'تاریخ تولد',                       'type' => 'jalali_date', 'required' => 0, 'options' => '', 'show_in_pdf' => 1),
            array('key' => 'document',          'label' => 'تصویر صفحه اول شناسنامه',          'type' => 'file',        'required' => 1, 'options' => '', 'show_in_pdf' => 1),
            array('key' => 'address',           'label' => 'آدرس',                             'type' => 'textarea',    'required' => 0, 'options' => '', 'show_in_pdf' => 1),
        );
    }

    public static function field_types()
    {
        return array(
            'text'        => 'متن کوتاه',
            'textarea'    => 'متن بلند',
            'tel'         => 'شماره تلفن',
            'number'      => 'عدد',
            'jalali_date' => 'تاریخ (تقویم شمسی)',
            'select'      => 'لیست کشویی',
            'file'        => 'آپلود فایل/تصویر',
        );
    }

    public static function all()
    {
        $fields = get_option(self::OPTION_KEY, null);

        if (!is_array($fields)) {
            $fields = self::defaults();
            update_option(self::OPTION_KEY, $fields);
            return $fields;
        }

        // سازگاری با نسخه‌های قبلی: فیلدهایی که کلید show_in_pdf ندارند، پیش‌فرض روشن در نظر گرفته می‌شوند
        $needs_migration = false;

        foreach ($fields as $i => $field) {
            if (!array_key_exists('show_in_pdf', $field)) {
                $fields[$i]['show_in_pdf'] = 1;
                $needs_migration = true;
            }
        }

        if ($needs_migration) {
            update_option(self::OPTION_KEY, $fields);
        }

        return $fields;
    }

    public static function get($key)
    {
        foreach (self::all() as $field) {
            if ($field['key'] === $key) {
                return $field;
            }
        }

        return null;
    }

    public static function is_protected($key)
    {
        return in_array($key, self::PROTECTED_KEYS, true);
    }

    /*
    |--------------------------------------------------------------------------
    | ذخیره (افزودن یا ویرایش) یک فیلد
    |--------------------------------------------------------------------------
    */

    public static function save_field($data)
    {
        $fields = self::all();

        $key = sanitize_key($data['key'] ?? '');

        if ($key === '') {
            return new WP_Error('invalid_key', 'کلید فیلد نامعتبر است.');
        }

        $new_field = array(
            'key'         => $key,
            'label'       => sanitize_text_field($data['label'] ?? $key),
            'type'        => in_array($data['type'] ?? '', array_keys(self::field_types()), true) ? $data['type'] : 'text',
            'required'    => !empty($data['required']) ? 1 : 0,
            'options'     => sanitize_text_field($data['options'] ?? ''),
            'show_in_pdf' => !empty($data['show_in_pdf']) ? 1 : 0,
        );

        // فیلدهای محافظت‌شده: نوع و اجباری‌بودن قابل تغییر نیست
        if (self::is_protected($key)) {
            $new_field['type']     = ($key === 'mobile') ? 'tel' : 'text';
            $new_field['required'] = 1;
        }

        $found = false;

        foreach ($fields as $i => $field) {
            if ($field['key'] === $key) {
                $fields[$i] = $new_field;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $fields[] = $new_field;
        }

        update_option(self::OPTION_KEY, $fields);

        return true;
    }

    public static function delete_field($key)
    {
        if (self::is_protected($key)) {
            return new WP_Error('protected', 'این فیلد قابل حذف نیست.');
        }

        $fields = array_values(array_filter(self::all(), function ($field) use ($key) {
            return $field['key'] !== $key;
        }));

        update_option(self::OPTION_KEY, $fields);

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | صفحه مدیریت فیلدها (زیرمنوی پیشخوان)
    |--------------------------------------------------------------------------
    */

    public function register_menu()
    {
        add_submenu_page(
            'formdesk',
            'فیلدهای فرم',
            'فیلدهای فرم',
            'manage_woocommerce',
            'formdesk-fields',
            array($this, 'render_page')
        );
    }

    public function register_hooks()
    {
        add_action('admin_post_formdesk_save_field', array($this, 'handle_save'));
        add_action('admin_post_formdesk_delete_field', array($this, 'handle_delete'));
    }

    public function handle_save()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('دسترسی غیرمجاز');
        }

        check_admin_referer('formdesk_save_field');

        $result = self::save_field($_POST);

        $redirect = admin_url('admin.php?page=formdesk-fields');

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

        check_admin_referer('formdesk_delete_field_' . $key);

        self::delete_field($key);

        wp_safe_redirect(admin_url('admin.php?page=formdesk-fields&fd_deleted=1'));
        exit;
    }

    public function render_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('دسترسی غیرمجاز');
        }

        $fields    = self::all();
        $edit_key  = isset($_GET['edit']) ? sanitize_key($_GET['edit']) : '';
        $edit_data = $edit_key ? self::get($edit_key) : null;
?>
        <div class="wrap">
            <h1>📝 فیلدهای فرم ثبت‌نام</h1>

            <?php if (!empty($_GET['fd_saved'])) : ?>
                <div class="notice notice-success">
                    <p>فیلد با موفقیت ذخیره شد.</p>
                </div>
            <?php endif; ?>

            <?php if (!empty($_GET['fd_deleted'])) : ?>
                <div class="notice notice-success">
                    <p>فیلد حذف شد.</p>
                </div>
            <?php endif; ?>

            <?php if (!empty($_GET['fd_error'])) : ?>
                <div class="notice notice-error">
                    <p><?php echo esc_html(urldecode($_GET['fd_error'])); ?></p>
                </div>
            <?php endif; ?>

            <h2 class="title">فیلدهای فعلی</h2>

            <table class="widefat striped" style="max-width:900px">
                <thead>
                    <tr>
                        <th>برچسب</th>
                        <th>کلید</th>
                        <th>نوع</th>
                        <th>اجباری</th>
                        <th>نمایش در PDF</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fields as $field) :
                        $types = self::field_types();
                    ?>
                        <tr>
                            <td><?php echo esc_html($field['label']); ?></td>
                            <td><code><?php echo esc_html($field['key']); ?></code></td>
                            <td><?php echo esc_html($types[$field['type']] ?? $field['type']); ?></td>
                            <td><?php echo !empty($field['required']) ? '✅' : '—'; ?></td>
                            <td><?php echo !empty($field['show_in_pdf']) ? '✅' : '—'; ?></td>
                            <td>
                                <a class="button button-small" href="<?php echo esc_url(add_query_arg(array('page' => 'formdesk-fields', 'edit' => $field['key']), admin_url('admin.php'))); ?>">
                                    ویرایش
                                </a>

                                <?php if (!self::is_protected($field['key'])) : ?>
                                    <a class="button button-small button-link-delete"
                                        onclick="return confirm('آیا از حذف این فیلد مطمئن هستید؟');"
                                        href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=formdesk_delete_field&key=' . $field['key']), 'formdesk_delete_field_' . $field['key']); ?>">
                                        حذف
                                    </a>
                                <?php else : ?>
                                    <span class="description">(محافظت‌شده)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <hr>

            <h2 class="title"><?php echo $edit_data ? 'ویرایش فیلد: ' . esc_html($edit_data['label']) : 'افزودن فیلد جدید'; ?></h2>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:600px">

                <?php wp_nonce_field('formdesk_save_field'); ?>
                <input type="hidden" name="action" value="formdesk_save_field">

                <table class="form-table">

                    <tr>
                        <th><label for="fd_field_label">برچسب فیلد</label></th>
                        <td>
                            <input type="text" id="fd_field_label" name="label" class="regular-text" required
                                value="<?php echo esc_attr($edit_data['label'] ?? ''); ?>">
                        </td>
                    </tr>

                    <tr>
                        <th><label for="fd_field_key">کلید یکتا (انگلیسی، بدون فاصله)</label></th>
                        <td>
                            <input type="text" id="fd_field_key" name="key" class="regular-text" required
                                pattern="[a-z0-9_]+"
                                <?php echo ($edit_data && self::is_protected($edit_data['key'])) ? 'readonly' : ''; ?>
                                value="<?php echo esc_attr($edit_data['key'] ?? ''); ?>">
                            <p class="description">فقط حروف کوچک انگلیسی، عدد و زیرخط. مثال: father_name</p>
                        </td>
                    </tr>

                    <tr>
                        <th><label for="fd_field_type">نوع فیلد</label></th>
                        <td>
                            <select id="fd_field_type" name="type" <?php echo ($edit_data && self::is_protected($edit_data['key'])) ? 'disabled' : ''; ?>>
                                <?php foreach (self::field_types() as $type_key => $type_label) : ?>
                                    <option value="<?php echo esc_attr($type_key); ?>" <?php selected($edit_data['type'] ?? '', $type_key); ?>>
                                        <?php echo esc_html($type_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th><label for="fd_field_options">گزینه‌های لیست کشویی</label></th>
                        <td>
                            <input type="text" id="fd_field_options" name="options" class="regular-text"
                                value="<?php echo esc_attr($edit_data['options'] ?? ''); ?>" placeholder="گزینه۱،گزینه۲،گزینه۳">
                            <p class="description">فقط برای نوع «لیست کشویی» — گزینه‌ها را با ویرگول (،) از هم جدا کنید.</p>
                        </td>
                    </tr>

                    <tr>
                        <th>اجباری باشد؟</th>
                        <td>
                            <label>
                                <input type="checkbox" name="required" value="1"
                                    <?php checked(!empty($edit_data['required'])); ?>
                                    <?php echo ($edit_data && self::is_protected($edit_data['key'])) ? 'disabled checked' : ''; ?>>
                                بله، پر کردن این فیلد اجباری باشد
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th>نمایش در PDF</th>
                        <td>
                            <label>
                                <input type="checkbox" name="show_in_pdf" value="1"
                                    <?php checked(!empty($edit_data['show_in_pdf'])); ?>>
                                بله، این فیلد در خروجی PDF پرونده متقاضی نمایش داده شود
                            </label>
                            <p class="description">اگر فیلد را حذف کنید، به‌صورت خودکار از PDF هم حذف می‌شود.</p>
                        </td>
                    </tr>

                </table>

                <?php submit_button($edit_data ? 'ذخیره تغییرات' : 'افزودن فیلد'); ?>

            </form>
        </div>
    <?php
    }

    /*
    |--------------------------------------------------------------------------
    | رندر یک فیلد در فرم فرانت‌اند
    |--------------------------------------------------------------------------
    */

    public static function render_frontend_field($field)
    {
        $required_attr = !empty($field['required']) ? 'required' : '';
        $required_mark = !empty($field['required']) ? ' <span class="fd-required">*</span>' : '';
        $wrapper_class = ($field['type'] === 'textarea') ? 'fd-field fd-full' : 'fd-field';

        if ($field['type'] === 'file') {
            $wrapper_class .= ' fd-upload';
        }
    ?>
        <p class="<?php echo esc_attr($wrapper_class); ?>">
            <label><?php echo esc_html($field['label']) . $required_mark; ?></label>

            <?php if ($field['type'] === 'textarea') : ?>
                <textarea name="<?php echo esc_attr($field['key']); ?>" <?php echo $required_attr; ?>></textarea>

            <?php elseif ($field['type'] === 'file') : ?>
                <input type="file" name="<?php echo esc_attr($field['key']); ?>" <?php echo $required_attr; ?>>

            <?php elseif ($field['type'] === 'select') : ?>
                <select name="<?php echo esc_attr($field['key']); ?>" <?php echo $required_attr; ?>>
                    <option value="">— انتخاب کنید —</option>
                    <?php foreach (explode('،', str_replace(',', '،', $field['options'])) as $option) :
                        $option = trim($option);
                        if ($option === '') continue;
                    ?>
                        <option value="<?php echo esc_attr($option); ?>"><?php echo esc_html($option); ?></option>
                    <?php endforeach; ?>
                </select>

            <?php elseif ($field['type'] === 'jalali_date') : ?>
                <input type="text" class="fd-jalali-date" autocomplete="off" readonly
                    name="<?php echo esc_attr($field['key']); ?>" placeholder="انتخاب تاریخ" <?php echo $required_attr; ?>>

            <?php elseif ($field['type'] === 'number') : ?>
                <input type="number" name="<?php echo esc_attr($field['key']); ?>" <?php echo $required_attr; ?>>

            <?php elseif ($field['type'] === 'tel') : ?>
                <input type="text" name="<?php echo esc_attr($field['key']); ?>" <?php echo $required_attr; ?>>

            <?php else : ?>
                <input type="text" name="<?php echo esc_attr($field['key']); ?>" <?php echo $required_attr; ?>>

            <?php endif; ?>
        </p>
<?php
    }

    /*
    |--------------------------------------------------------------------------
    | آیا فرم فعلی حداقل یک فیلد تاریخ شمسی دارد؟
    |--------------------------------------------------------------------------
    */

    public static function has_jalali_field()
    {
        foreach (self::all() as $field) {
            if ($field['type'] === 'jalali_date') {
                return true;
            }
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | فیلدهایی که باید در خروجی PDF نمایش داده شوند
    |--------------------------------------------------------------------------
    */

    public static function pdf_fields()
    {
        return array_values(array_filter(self::all(), function ($field) {
            return !empty($field['show_in_pdf']);
        }));
    }

    /*
    |--------------------------------------------------------------------------
    | اعتبارسنجی و پاکسازی مقدار ارسالی برای یک فیلد (غیر از نوع file)
    |--------------------------------------------------------------------------
    */

    public static function sanitize_value($field, $raw)
    {
        switch ($field['type']) {

            case 'textarea':
                return sanitize_textarea_field($raw);

            case 'jalali_date':
                // فقط قالب عددی روز/ماه/سال شمسی مجاز است
                return preg_match('/^\d{4}\/\d{1,2}\/\d{1,2}$/', $raw) ? sanitize_text_field($raw) : '';

            case 'number':
                return preg_replace('/[^0-9\-\.]/', '', $raw);

            default:
                return sanitize_text_field($raw);
        }
    }
}
