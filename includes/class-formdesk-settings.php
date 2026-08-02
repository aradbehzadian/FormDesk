<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * مدیریت تنظیمات پلاگین (جایگزین مقادیر ثابت هاردکد شده در نسخه قبلی)
 */
class FormDesk_Settings
{
    const OPTION_KEY = 'formdesk_settings';

    public static function defaults()
    {
        return array(
            'product_id'      => 0,
            'per_page'        => 20,
            'max_upload_mb'   => 2,
            'business_name'   => 'کسب‌وکار من',
            'pdf_title'       => 'فرم ثبت نام',
            'logo_url'        => '',
            'delete_data_on_uninstall' => 0,
            'form_title'          => 'ثبت نام آنلاین',
            'form_subtitle'       => 'لطفاً اطلاعات خود را با دقت وارد نمایید.',
            'submit_button_text'  => 'ثبت نام',
            'success_message'     => 'ثبت نام با موفقیت انجام شد. به‌زودی با شما تماس خواهیم گرفت.',
            'primary_color'       => '#5751E1',
            'accent_color'        => '#FFC327',
            'page_bg_color'       => '#F5F7FD',
            'card_bg_color'       => '#FFFFFF',
            'heading_color'       => '#26273B',
            'subtitle_color'      => '#777777',
            'label_color'         => '#444444',
            'input_bg_color'      => '#FAFBFF',
            'input_border_color'  => '#E8EBF5',
            'button_text_color'   => '#FFFFFF',
            'corner_style'        => 'rounded',
            'show_status_column'  => 1,
            'show_stat_cards'     => 1,
            'show_form_icon'      => 1,
            'show_icon_circle_bg' => 1,
            'form_icon_emoji'     => '📝',
        );
    }

    public static function all()
    {
        $saved = get_option(self::OPTION_KEY, array());
        return wp_parse_args($saved, self::defaults());
    }

    public static function get($key, $default = null)
    {
        $all = self::all();
        return isset($all[$key]) ? $all[$key] : $default;
    }

    public function register_menu()
    {
        add_submenu_page(
            'formdesk',
            'تنظیمات FormDesk',
            'تنظیمات',
            'manage_woocommerce',
            'formdesk-settings',
            array($this, 'render_page')
        );
    }

    public function register_settings()
    {
        register_setting('formdesk_settings_group', self::OPTION_KEY, array($this, 'sanitize'));

        // اگر تغییرات تنظیمات (رنگ‌بندی، متن‌ها و ...) در سایت دیده نمی‌شود،
        // معمولاً به‌خاطر کش کامل صفحه (مثل LiteSpeed Cache) است. برای همین،
        // بعد از هر ذخیره‌ی موفق تنظیمات، در صورت وجود افزونه‌های کش شناخته‌شده،
        // به‌صورت خودکار کش پاک می‌شود تا تغییرات بلافاصله در فرانت‌اند دیده شود.
        add_action('update_option_' . self::OPTION_KEY, array($this, 'purge_known_caches'), 10, 0);
        add_action('add_option_' . self::OPTION_KEY, array($this, 'purge_known_caches'), 10, 0);
    }

    public function purge_known_caches()
    {
        // LiteSpeed Cache
        if (has_action('litespeed_purge_all')) {
            do_action('litespeed_purge_all');
        }

        // WP Rocket
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }

        // W3 Total Cache
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }

        // WP Super Cache
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }

        // پاک‌سازی کش آبجکت وردپرس (در صورت استفاده از object cache)
        wp_cache_flush();
    }

    public function sanitize($input)
    {
        $defaults = self::defaults();

        return array(
            'product_id'    => isset($input['product_id']) ? intval($input['product_id']) : $defaults['product_id'],
            'per_page'      => isset($input['per_page']) ? max(1, intval($input['per_page'])) : $defaults['per_page'],
            'max_upload_mb' => isset($input['max_upload_mb']) ? max(1, intval($input['max_upload_mb'])) : $defaults['max_upload_mb'],
            'business_name'   => isset($input['business_name']) ? sanitize_text_field($input['business_name']) : $defaults['business_name'],
            'pdf_title'     => isset($input['pdf_title']) ? sanitize_text_field($input['pdf_title']) : $defaults['pdf_title'],
            'logo_url'      => isset($input['logo_url']) ? esc_url_raw($input['logo_url']) : $defaults['logo_url'],
            'delete_data_on_uninstall' => !empty($input['delete_data_on_uninstall']) ? 1 : 0,
            'form_title'         => isset($input['form_title']) ? sanitize_text_field($input['form_title']) : $defaults['form_title'],
            'form_subtitle'      => isset($input['form_subtitle']) ? sanitize_text_field($input['form_subtitle']) : $defaults['form_subtitle'],
            'submit_button_text' => isset($input['submit_button_text']) ? sanitize_text_field($input['submit_button_text']) : $defaults['submit_button_text'],
            'success_message'    => isset($input['success_message']) ? sanitize_textarea_field($input['success_message']) : $defaults['success_message'],
            'primary_color'      => (isset($input['primary_color']) && sanitize_hex_color($input['primary_color'])) ? sanitize_hex_color($input['primary_color']) : $defaults['primary_color'],
            'accent_color'       => (isset($input['accent_color']) && sanitize_hex_color($input['accent_color'])) ? sanitize_hex_color($input['accent_color']) : $defaults['accent_color'],
            'page_bg_color'      => (isset($input['page_bg_color']) && sanitize_hex_color($input['page_bg_color'])) ? sanitize_hex_color($input['page_bg_color']) : $defaults['page_bg_color'],
            'card_bg_color'      => (isset($input['card_bg_color']) && sanitize_hex_color($input['card_bg_color'])) ? sanitize_hex_color($input['card_bg_color']) : $defaults['card_bg_color'],
            'heading_color'      => (isset($input['heading_color']) && sanitize_hex_color($input['heading_color'])) ? sanitize_hex_color($input['heading_color']) : $defaults['heading_color'],
            'subtitle_color'     => (isset($input['subtitle_color']) && sanitize_hex_color($input['subtitle_color'])) ? sanitize_hex_color($input['subtitle_color']) : $defaults['subtitle_color'],
            'label_color'        => (isset($input['label_color']) && sanitize_hex_color($input['label_color'])) ? sanitize_hex_color($input['label_color']) : $defaults['label_color'],
            'input_bg_color'     => (isset($input['input_bg_color']) && sanitize_hex_color($input['input_bg_color'])) ? sanitize_hex_color($input['input_bg_color']) : $defaults['input_bg_color'],
            'input_border_color' => (isset($input['input_border_color']) && sanitize_hex_color($input['input_border_color'])) ? sanitize_hex_color($input['input_border_color']) : $defaults['input_border_color'],
            'button_text_color'  => (isset($input['button_text_color']) && sanitize_hex_color($input['button_text_color'])) ? sanitize_hex_color($input['button_text_color']) : $defaults['button_text_color'],
            'corner_style'       => in_array(($input['corner_style'] ?? ''), array('rounded', 'soft', 'sharp'), true) ? $input['corner_style'] : $defaults['corner_style'],
            'show_status_column' => !empty($input['show_status_column']) ? 1 : 0,
            'show_stat_cards'    => !empty($input['show_stat_cards']) ? 1 : 0,
            'show_form_icon'     => !empty($input['show_form_icon']) ? 1 : 0,
            'show_icon_circle_bg' => !empty($input['show_icon_circle_bg']) ? 1 : 0,
            'form_icon_emoji'    => (isset($input['form_icon_emoji']) && trim($input['form_icon_emoji']) !== '') ? sanitize_text_field($input['form_icon_emoji']) : $defaults['form_icon_emoji'],
        );
    }

    public function render_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $settings = self::all();
        $statuses = FormDesk_Statuses::all();

        $corner_labels = array(
            'rounded' => 'گرد (پیش‌فرض)',
            'soft'    => 'کمی گرد',
            'sharp'   => 'تیز (بدون گردی)',
        );
?>
        <div class="wrap formdesk-settings-wrap">

            <div class="fd-settings-hero">
                <div class="fd-settings-hero-icon">⚙️</div>
                <div>
                    <h1>تنظیمات FormDesk</h1>
                    <p>پیکربندی عمومی، ظاهر فرم فرانت‌اند و وضعیت‌های متقاضی — همه از همین صفحه و بدون نیاز به ویرایش کد.</p>
                </div>
            </div>

            <?php if (!empty($_GET['fd_saved'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>با موفقیت ذخیره شد.</p>
                </div>
            <?php endif; ?>

            <?php if (!empty($_GET['fd_error'])) : ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['fd_error']))); ?></p>
                </div>
            <?php endif; ?>

            <input type="radio" name="fd-tab" id="fd-tab-general" class="fd-tab-radio" checked>
            <input type="radio" name="fd-tab" id="fd-tab-appearance" class="fd-tab-radio">
            <input type="radio" name="fd-tab" id="fd-tab-statuses" class="fd-tab-radio">

            <div class="fd-tab-bar">
                <label for="fd-tab-general"><span class="fd-tab-icon">⚙️</span> تنظیمات عمومی</label>
                <label for="fd-tab-appearance"><span class="fd-tab-icon">🎨</span> ظاهر و متن‌های فرم فرانت‌اند</label>
                <label for="fd-tab-statuses"><span class="fd-tab-icon">🏷️</span> وضعیت‌های متقاضی</label>
            </div>

            <form method="post" action="options.php" class="fd-tab-form">
                <?php settings_fields('formdesk_settings_group'); ?>

                <div class="fd-tab-panel fd-panel-general">
                    <p class="fd-panel-intro">اتصال به ووکامرس، محدودیت‌های آپلود و رفتار داشبورد مدیریت.</p>

                    <table class="form-table">

                        <tr>
                            <th><label for="fd_product_id">شناسه محصول ووکامرس (ثبت‌نام)</label></th>
                            <td>
                                <input type="number" id="fd_product_id" name="<?php echo self::OPTION_KEY; ?>[product_id]"
                                    value="<?php echo esc_attr($settings['product_id']); ?>" class="regular-text">
                                <p class="description">شناسه محصولی که هزینه ثبت‌نام روی آن ساخته می‌شود (ID محصول در ووکامرس).</p>
                            </td>
                        </tr>

                        <tr>
                            <th><label for="fd_per_page">تعداد ردیف در هر صفحه</label></th>
                            <td>
                                <input type="number" id="fd_per_page" name="<?php echo self::OPTION_KEY; ?>[per_page]"
                                    value="<?php echo esc_attr($settings['per_page']); ?>" class="regular-text">
                            </td>
                        </tr>

                        <tr>
                            <th><label for="fd_max_upload">حداکثر حجم تصویر (مگابایت)</label></th>
                            <td>
                                <input type="number" id="fd_max_upload" name="<?php echo self::OPTION_KEY; ?>[max_upload_mb]"
                                    value="<?php echo esc_attr($settings['max_upload_mb']); ?>" class="regular-text">
                            </td>
                        </tr>

                        <tr>
                            <th><label for="fd_business_name">نام سازمان/کسب‌وکار (برای PDF)</label></th>
                            <td>
                                <input type="text" id="fd_business_name" name="<?php echo self::OPTION_KEY; ?>[business_name]"
                                    value="<?php echo esc_attr($settings['business_name']); ?>" class="regular-text">
                            </td>
                        </tr>

                        <tr>
                            <th><label for="fd_pdf_title">عنوان فرم (برای PDF)</label></th>
                            <td>
                                <input type="text" id="fd_pdf_title" name="<?php echo self::OPTION_KEY; ?>[pdf_title]"
                                    value="<?php echo esc_attr($settings['pdf_title']); ?>" class="regular-text">
                                <p class="description">قسمت اول عنوان بالای PDF (پیش‌فرض: «فرم ثبت نام»). عنوان کامل از ترکیب این متن + نام سازمان/کسب‌وکار ساخته می‌شود.</p>
                            </td>
                        </tr>

                        <tr>
                            <th><label for="fd_logo_url">آدرس لوگو (برای PDF و فرم فرانت‌اند)</label></th>
                            <td>
                                <input type="text" id="fd_logo_url" name="<?php echo self::OPTION_KEY; ?>[logo_url]"
                                    value="<?php echo esc_attr($settings['logo_url']); ?>" class="regular-text">
                                <p class="description">آدرس مستقیم فایل تصویر لوگو (URL). در صورت خالی بودن، یک آیکون پیش‌فرض نمایش داده می‌شود.</p>
                            </td>
                        </tr>

                        <tr>
                            <th>نمایش کارت‌های آماری در داشبورد</th>
                            <td>
                                <label class="fd-toggle">
                                    <input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[show_stat_cards]" value="1"
                                        <?php checked(!empty($settings['show_stat_cards'])); ?>>
                                    بله، ردیف کارت‌های آماری (کل متقاضیان و تعداد هر وضعیت) در بالای صفحه‌ی داشبورد نمایش داده شود
                                </label>
                                <p class="description">در صورت غیرفعال بودن این گزینه، کارت‌های آماری از صفحه‌ی داشبورد حذف می‌شوند؛ دکمه‌های فیلتر وضعیت و جدول متقاضیان تحت تأثیر این گزینه قرار نمی‌گیرند.</p>
                            </td>
                        </tr>

                        <tr>
                            <th>نمایش ستون «وضعیت متقاضی» در داشبورد</th>
                            <td>
                                <label class="fd-toggle">
                                    <input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[show_status_column]" value="1"
                                        <?php checked(!empty($settings['show_status_column'])); ?>>
                                    بله، ستون وضعیت متقاضی (منوی کشویی وضعیت) در جدول لیست متقاضیان صفحه‌ی داشبورد نمایش داده شود
                                </label>
                                <p class="description">در صورت غیرفعال بودن این گزینه، ستون وضعیت از جدول داشبورد حذف می‌شود؛ اما تغییر وضعیت همچنان از صفحه‌ی ویرایش سفارش امکان‌پذیر است. کارت‌های آماری و فیلترهای بالای جدول تحت تأثیر این گزینه قرار نمی‌گیرند.</p>
                            </td>
                        </tr>

                        <tr>
                            <th>حذف اطلاعات هنگام حذف پلاگین</th>
                            <td>
                                <label class="fd-toggle">
                                    <input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[delete_data_on_uninstall]" value="1"
                                        <?php checked(!empty($settings['delete_data_on_uninstall'])); ?>>
                                    بله، در صورت حذف کامل پلاگین از پیشخوان، تمام سفارش‌های ثبت‌نام، تنظیمات و جدول اعلان‌ها برای همیشه پاک شوند
                                </label>
                                <p class="description fd-danger-text">⚠️ توجه: این عملیات غیرقابل‌بازگشت است. اگر این گزینه غیرفعال باشد (پیش‌فرض)، حذف پلاگین هیچ داده‌ای را پاک نمی‌کند.</p>
                            </td>
                        </tr>

                    </table>
                </div>

                <div class="fd-tab-panel fd-panel-appearance">
                    <p class="fd-panel-intro">هر متن، رنگ و شکل ظاهریِ فرم ثبت‌نامی که در سایت نمایش داده می‌شود، از همین‌جا و بدون ویرایش کد قابل تغییر است. تغییرات بلافاصله پس از ذخیره روی فرم فرانت‌اند اعمال می‌شود.</p>

                    <h3 class="fd-settings-subheading">📝 متن‌ها و آیکون بالای فرم</h3>

                    <table class="form-table">

                        <tr>
                            <th>نمایش دایره‌ی آیکون بالای فرم</th>
                            <td>
                                <label class="fd-toggle">
                                    <input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[show_form_icon]" value="1"
                                        <?php checked(!empty($settings['show_form_icon'])); ?>>
                                    بله، دایره‌ی آیکون/لوگو در بالای فرم فرانت‌اند نمایش داده شود
                                </label>
                                <p class="description">در صورت غیرفعال بودن این گزینه، این دایره کلاً از بالای فرم حذف می‌شود و فقط عنوان و زیرعنوان فرم نمایش داده می‌شود.</p>
                            </td>
                        </tr>

                        <tr>
                            <th>حذف حاشیه‌ی گرد رنگی دور آیکون</th>
                            <td>
                                <label class="fd-toggle">
                                    <input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[show_icon_circle_bg]" value="1"
                                        <?php checked(!empty($settings['show_icon_circle_bg'])); ?>>
                                    بله، حاشیه‌ی گرد و رنگی پشت آیکون/لوگو نمایش داده شود
                                </label>
                                <p class="description">در صورت غیرفعال بودن این گزینه، فقط خود لوگو یا اموجی (بدون دایره‌ی رنگی پشت آن) نمایش داده می‌شود. این گزینه فقط زمانی اثر دارد که «نمایش دایره‌ی آیکون بالای فرم» فعال باشد.</p>
                            </td>
                        </tr>

                        <tr>
                            <th><label for="fd_form_icon_emoji">اموجی داخل دایره</label></th>
                            <td>
                                <input type="text" id="fd_form_icon_emoji" name="<?php echo self::OPTION_KEY; ?>[form_icon_emoji]"
                                    value="<?php echo esc_attr($settings['form_icon_emoji']); ?>" class="fd-emoji-input" maxlength="10">
                                <p class="description">این اموجی فقط زمانی نمایش داده می‌شود که فیلد «آدرس لوگو» (در تنظیمات عمومی) خالی باشد؛ در صورت تنظیم آدرس لوگو، تصویر لوگو به‌جای اموجی نمایش داده می‌شود.</p>
                            </td>
                        </tr>

                        <tr>
                            <th><label for="fd_form_title">عنوان فرم (نمایش در سایت)</label></th>
                            <td>
                                <input type="text" id="fd_form_title" name="<?php echo self::OPTION_KEY; ?>[form_title]"
                                    value="<?php echo esc_attr($settings['form_title']); ?>" class="regular-text">
                            </td>
                        </tr>

                        <tr>
                            <th><label for="fd_form_subtitle">زیرعنوان فرم</label></th>
                            <td>
                                <input type="text" id="fd_form_subtitle" name="<?php echo self::OPTION_KEY; ?>[form_subtitle]"
                                    value="<?php echo esc_attr($settings['form_subtitle']); ?>" class="regular-text">
                            </td>
                        </tr>

                        <tr>
                            <th><label for="fd_submit_button_text">متن دکمه ارسال</label></th>
                            <td>
                                <input type="text" id="fd_submit_button_text" name="<?php echo self::OPTION_KEY; ?>[submit_button_text]"
                                    value="<?php echo esc_attr($settings['submit_button_text']); ?>" class="regular-text">
                            </td>
                        </tr>

                        <tr>
                            <th><label for="fd_success_message">پیام موفقیت پس از ارسال فرم</label></th>
                            <td>
                                <textarea id="fd_success_message" name="<?php echo self::OPTION_KEY; ?>[success_message]"
                                    class="large-text" rows="2"><?php echo esc_textarea($settings['success_message']); ?></textarea>
                            </td>
                        </tr>

                    </table>

                    <h3 class="fd-settings-subheading">🔲 شکل ظاهری (گوشه‌ها)</h3>

                    <table class="form-table">
                        <tr>
                            <th><label for="fd_corner_style">میزان گردی گوشه‌ها</label></th>
                            <td>
                                <select id="fd_corner_style" name="<?php echo self::OPTION_KEY; ?>[corner_style]">
                                    <?php foreach ($corner_labels as $key => $label) : ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($settings['corner_style'], $key); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">روی گوشه‌ی کارت فرم، فیلدهای ورودی و دکمه‌ی ثبت‌نام به‌صورت هماهنگ اعمال می‌شود.</p>
                            </td>
                        </tr>
                    </table>

                    <h3 class="fd-settings-subheading">🎨 رنگ‌بندی</h3>
                    <p class="description">هر رنگ را با کلیک روی نمونه‌ی رنگی انتخاب کنید.</p>

                    <div class="fd-color-grid">

                        <div class="fd-color-item">
                            <label for="fd_primary_color">رنگ اصلی (برند)</label>
                            <input type="color" id="fd_primary_color" name="<?php echo self::OPTION_KEY; ?>[primary_color]"
                                value="<?php echo esc_attr($settings['primary_color']); ?>">
                            <p class="description">آیکون بالای فرم، حاشیه‌ی فیلد هنگام فوکوس و رنگ اصلی دکمه‌ی ثبت‌نام.</p>
                        </div>

                        <div class="fd-color-item">
                            <label for="fd_accent_color">رنگ تاکیدی</label>
                            <input type="color" id="fd_accent_color" name="<?php echo self::OPTION_KEY; ?>[accent_color]"
                                value="<?php echo esc_attr($settings['accent_color']); ?>">
                            <p class="description">رنگ دکمه‌ی ثبت‌نام هنگام هاور (hover) و نشانه‌ی ستاره (*) فیلدهای اجباری.</p>
                        </div>

                        <div class="fd-color-item">
                            <label for="fd_page_bg_color">رنگ پس‌زمینه‌ی صفحه</label>
                            <input type="color" id="fd_page_bg_color" name="<?php echo self::OPTION_KEY; ?>[page_bg_color]"
                                value="<?php echo esc_attr($settings['page_bg_color']); ?>">
                            <p class="description">پس‌زمینه‌ی اطراف کارت فرم.</p>
                        </div>

                        <div class="fd-color-item">
                            <label for="fd_card_bg_color">رنگ پس‌زمینه‌ی کارت فرم</label>
                            <input type="color" id="fd_card_bg_color" name="<?php echo self::OPTION_KEY; ?>[card_bg_color]"
                                value="<?php echo esc_attr($settings['card_bg_color']); ?>">
                        </div>

                        <div class="fd-color-item">
                            <label for="fd_heading_color">رنگ عنوان فرم</label>
                            <input type="color" id="fd_heading_color" name="<?php echo self::OPTION_KEY; ?>[heading_color]"
                                value="<?php echo esc_attr($settings['heading_color']); ?>">
                        </div>

                        <div class="fd-color-item">
                            <label for="fd_subtitle_color">رنگ زیرعنوان فرم</label>
                            <input type="color" id="fd_subtitle_color" name="<?php echo self::OPTION_KEY; ?>[subtitle_color]"
                                value="<?php echo esc_attr($settings['subtitle_color']); ?>">
                        </div>

                        <div class="fd-color-item">
                            <label for="fd_label_color">رنگ برچسب فیلدها</label>
                            <input type="color" id="fd_label_color" name="<?php echo self::OPTION_KEY; ?>[label_color]"
                                value="<?php echo esc_attr($settings['label_color']); ?>">
                        </div>

                        <div class="fd-color-item">
                            <label for="fd_input_bg_color">رنگ پس‌زمینه‌ی فیلدها</label>
                            <input type="color" id="fd_input_bg_color" name="<?php echo self::OPTION_KEY; ?>[input_bg_color]"
                                value="<?php echo esc_attr($settings['input_bg_color']); ?>">
                        </div>

                        <div class="fd-color-item">
                            <label for="fd_input_border_color">رنگ حاشیه‌ی فیلدها</label>
                            <input type="color" id="fd_input_border_color" name="<?php echo self::OPTION_KEY; ?>[input_border_color]"
                                value="<?php echo esc_attr($settings['input_border_color']); ?>">
                        </div>

                        <div class="fd-color-item">
                            <label for="fd_button_text_color">رنگ متن دکمه‌ی ثبت‌نام</label>
                            <input type="color" id="fd_button_text_color" name="<?php echo self::OPTION_KEY; ?>[button_text_color]"
                                value="<?php echo esc_attr($settings['button_text_color']); ?>">
                        </div>

                    </div>
                </div>

                <?php submit_button('ذخیره تنظیمات'); ?>
            </form>

            <div class="fd-tab-panel fd-panel-statuses">
                <p class="fd-panel-intro">
                    این گزینه‌ها همان منوی کشویی وضعیت متقاضی در صفحه‌ی داشبورد و متاباکس سفارش هستند.
                    می‌توانید عنوان هر وضعیت را تغییر دهید، وضعیت جدید اضافه کنید یا وضعیت‌های موجود را حذف کنید — کارت‌های آماری صفحه‌ی داشبورد به‌صورت خودکار متناسب با همین لیست به‌روزرسانی می‌شوند.
                    <br>
                    <strong>نکته:</strong> اولین ردیف این لیست، وضعیت پیش‌فرضی است که به متقاضیان تازه ثبت‌نام‌کرده تعلق می‌گیرد.
                </p>

                <table class="widefat striped fd-status-table">
                    <thead>
                        <tr>
                            <th>عنوان وضعیت</th>
                            <th style="width:110px">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($statuses as $status) : ?>
                            <tr>
                                <td>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="fd-inline-form">
                                        <?php wp_nonce_field('formdesk_save_status'); ?>
                                        <input type="hidden" name="action" value="formdesk_save_status">
                                        <input type="hidden" name="key" value="<?php echo esc_attr($status['key']); ?>">
                                        <input type="text" name="label" value="<?php echo esc_attr($status['label']); ?>" class="regular-text">
                                        <button type="submit" class="button">ذخیره</button>
                                    </form>
                                </td>
                                <td>
                                    <?php if (count($statuses) > 1) : ?>
                                        <a class="button button-small button-link-delete"
                                            href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=formdesk_delete_status&key=' . $status['key']), 'formdesk_delete_status_' . $status['key']); ?>"
                                            onclick="return confirm('این وضعیت حذف شود؟');">
                                            حذف
                                        </a>
                                    <?php else : ?>
                                        <span class="description">(آخرین وضعیت)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h3 class="fd-settings-subheading">➕ افزودن وضعیت جدید</h3>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="fd-inline-form fd-add-status-form">
                    <?php wp_nonce_field('formdesk_save_status'); ?>
                    <input type="hidden" name="action" value="formdesk_save_status">
                    <input type="text" name="label" class="regular-text" placeholder="مثال: 🟣 در حال بررسی مدارک" required>
                    <button type="submit" class="button button-primary">افزودن</button>
                </form>
            </div>

        </div>
<?php
    }
}
