<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ساخت فرم چاپی PDF برای یک متقاضی
 */
class FormDesk_Pdf
{
    public function register_hooks()
    {
        add_action('admin_post_formdesk_export_pdf', array($this, 'export'));
    }

    public function export()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('دسترسی غیرمجاز');
        }

        $order_id = intval($_GET['order_id'] ?? 0);

        if (!$order_id) {
            wp_die('شماره سفارش نامعتبر است.');
        }

        check_admin_referer('formdesk_export_pdf_' . $order_id);

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_die('سفارش پیدا نشد.');
        }

        if (!class_exists(\Mpdf\Mpdf::class)) {
            wp_die('کتابخانه mPDF نصب نشده است. لطفاً composer install را اجرا کنید.');
        }

        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs          = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData          = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf(array(
            'mode'   => 'utf-8',
            'format' => 'A4',

            'fontDir' => array_merge($fontDirs, array(FORMDESK_PATH . 'assets/fonts')),

            'fontdata' => $fontData + array(
                'vazir' => array(
                    'R'          => 'Vazirmatn-Regular.ttf',
                    'B'          => 'Vazirmatn-Regular.ttf',
                    'I'          => 'Vazirmatn-Regular.ttf',
                    'BI'         => 'Vazirmatn-Regular.ttf',
                    'useOTL'     => 0xFF,
                    'useKashida' => 75,
                ),
            ),

            'default_font'      => 'vazir',
            'autoScriptToLang'  => false,
            'autoLangToFont'    => false,
            'useOTL'            => 0xFF,
            'useKashida'        => 75,
            'useSubstitutions'  => false,
        ));

        $mpdf->SetDirectionality('rtl');
        $mpdf->SetDisplayMode('fullpage');

        $logo       = FormDesk_Settings::get('logo_url', '');
        $school     = FormDesk_Settings::get('business_name', 'کسب‌وکار من');
        $pdf_title  = FormDesk_Settings::get('pdf_title', 'فرم ثبت نام');
        $status_key = $order->get_meta('_fd_status');
        $status_txt = FormDesk_Statuses::get_label($status_key);

        $html = $this->build_html($order, $logo, $school, $pdf_title, $status_txt);

        $mpdf->WriteHTML($html);
        $mpdf->Output('registration-' . $order_id . '.pdf', \Mpdf\Output\Destination::INLINE);

        exit;
    }

    private function build_html($order, $logo, $school, $pdf_title, $status_txt)
    {
        // فقط فیلدهایی که در تنظیمات، گزینه‌ی «نمایش در PDF» برایشان فعال شده باشد
        $pdf_fields  = FormDesk_Fields::pdf_fields();
        $text_fields = array_filter($pdf_fields, function ($f) { return $f['type'] !== 'file'; });
        $file_fields = array_filter($pdf_fields, function ($f) { return $f['type'] === 'file'; });

        ob_start();
        ?>
        <style>
            * { box-sizing: border-box; }
            body { font-family: vazir; direction: rtl; color:#000; font-size:13px; margin:0; padding:0; }

            .header { width:100%; border:2px solid #000; border-radius:6px; padding:14px 18px; margin-bottom:22px; }
            .header-table { width:100%; border-collapse:collapse; }
            .header-table td { vertical-align:middle; }
            .logo { width:100px; text-align:center; }
            .logo img { width:80px; }
            .title { text-align:center; }
            .title h1 { margin:0; font-size:22px; font-weight:bold; color:#000; }
            .title p { margin:8px 0 0; font-size:11px; color:#444; }
            .separator { margin-top:16px; border-top:2px solid #000; }

            .info-box { width:210px; }
            .info-card {
                border:1.5px solid #000;
                border-radius:10px;
                overflow:hidden;
            }
            .info-card table { width:100%; border-collapse:collapse; }
            .info-card tr:nth-child(even) td { background:#f3f3f3; }
            .info-card td {
                padding:7px 10px;
                font-size:11.5px;
                border-bottom:1px solid #ddd;
            }
            .info-card tr:last-child td { border-bottom:none; }
            .info-card .label {
                font-weight:bold;
                color:#333;
                width:52%;
                text-align:right;
                border-left:1px solid #ddd;
            }
            .info-card .value {
                text-align:center;
                color:#000;
            }

            .info-title, .document-title {
                background:#fff; color:#000; border:2px solid #000; border-bottom:none;
                padding:10px; text-align:center; font-size:15px; font-weight:bold;
                border-radius:6px 6px 0 0; letter-spacing:0.3px;
            }
            .document-title { margin-top:24px; }

            .info-table { width:100%; border-collapse:collapse; margin-bottom:24px; border:2px solid #000; border-top:none; }
            .info-table td { border:1px solid #666; padding:10px 12px; font-size:12.5px; }
            .info-table .label { background:#eee; font-weight:bold; width:22%; }
            .info-table .value { width:78%; }

            .document-table { width:100%; border-collapse:collapse; margin-bottom:24px; border:2px solid #000; border-top:none; }
            .document-table td { border:1px solid #666; padding:16px; vertical-align:top; }
            .document-image { text-align:center; background:#f5f5f5; }
            .document-image img { width:220px; border:1px solid #000; padding:6px; background:#fff; }
            .document-note { font-size:12.5px; line-height:26px; }
            .document-note b { color:#000; font-size:13px; }

            .footer { text-align:center; font-size:11px; color:#444; border-top:1px solid #000; padding-top:12px; margin-top:20px; }
        </style>

        <div class="header">
            <table class="header-table" dir="ltr">
                <tr>
                    <td class="info-box" dir="rtl">
                        <div class="info-card">
                            <table>
                                <tr><td class="label">شماره پرونده</td><td class="value">#<?php echo esc_html($order->get_id()); ?></td></tr>
                                <tr><td class="label">تاریخ ثبت</td><td class="value"><?php echo esc_html($order->get_date_created()->date('Y/m/d')); ?></td></tr>
                                <tr><td class="label">ساعت ثبت</td><td class="value"><?php echo esc_html($order->get_date_created()->date('H:i')); ?></td></tr>
                                <tr><td class="label">وضعیت</td><td class="value"><?php echo esc_html($status_txt); ?></td></tr>
                            </table>
                        </div>
                    </td>
                    <td class="title" dir="rtl">
                        <h1><?php echo esc_html($pdf_title . ' ' . $school); ?></h1>
                        <p>فرم ثبت اطلاعات متقاضی</p>
                    </td>
                    <td class="logo">
                        <?php if ($logo) : ?>
                            <img src="<?php echo esc_url($logo); ?>" width="100" height="100">
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            <div class="separator"></div>
        </div>

        <?php if (!empty($text_fields)) : ?>

            <div class="info-title">مشخصات متقاضی</div>

            <table class="info-table">
                <?php foreach ($text_fields as $field) :

                    $raw = $order->get_meta('_fd_' . $field['key']);
                    $val = ($field['type'] === 'textarea') ? nl2br(esc_html($raw)) : esc_html($raw);
                    ?>
                    <tr>
                        <td class="label"><?php echo esc_html($field['label']); ?></td>
                        <td class="value"><?php echo ($raw !== '' && $raw !== null) ? $val : '-'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>

        <?php endif; ?>

        <?php foreach ($file_fields as $field) :

            $file_url = $order->get_meta('_fd_' . $field['key']);
            ?>
            <div class="document-title"><?php echo esc_html($field['label']); ?></div>

            <table class="document-table">
                <tr>
                    <td width="40%" class="document-image">
                        <?php if ($file_url) : ?>
                            <img src="<?php echo esc_url($file_url); ?>">
                        <?php endif; ?>
                    </td>
                    <td width="60%" class="document-note">
                        <b>مدرک بارگذاری شده:</b><br><br>
                        <?php echo esc_html($field['label']); ?><br><br>
                        این تصویر توسط متقاضی هنگام ثبت درخواست در سامانه بارگذاری شده است.<br><br>
                        اپراتور موظف است قبل از تأیید نهایی، اطلاعات تصویر را با اطلاعات ثبت شده مطابقت دهد.
                    </td>
                </tr>
            </table>

        <?php endforeach; ?>

        <div class="footer">
            این فرم توسط سامانه مدیریت ثبت‌نام (FormDesk) ایجاد شده است.<br>
            تاریخ چاپ: <?php echo esc_html(date('Y/m/d H:i')); ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
