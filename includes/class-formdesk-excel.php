<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * خروجی گرفتن اکسل از لیست متقاضیان
 */
class FormDesk_Excel
{
    public function register_hooks()
    {
        add_action('admin_post_formdesk_export_excel', array($this, 'export'));
    }

    public function export()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('دسترسی غیرمجاز');
        }

        check_admin_referer('formdesk_export_excel');

        if (!class_exists(Spreadsheet::class)) {
            wp_die('کتابخانه PhpSpreadsheet نصب نشده است. لطفاً composer install را اجرا کنید.');
        }

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('متقاضیان');

        $sheet->setCellValue('A1', 'نام');
        $sheet->setCellValue('B1', 'نام خانوادگی');
        $sheet->setCellValue('C1', 'کد ملی');
        $sheet->setCellValue('D1', 'موبایل');
        $sheet->setCellValue('E1', 'آدرس');
        $sheet->setCellValue('F1', 'وضعیت');
        $sheet->setCellValue('G1', 'تاریخ ثبت');

        $orders = wc_get_orders(array(
            'limit'   => -1,
            'orderby' => 'date',
            'order'   => 'DESC',
        ));

        $row = 2;

        foreach ($orders as $order) {

            if (!FormDesk_Registration::is_registration_order($order)) {
                continue;
            }

            $status = $order->get_meta('_fd_status');

            $sheet->setCellValue('A' . $row, $order->get_meta('_fd_first_name'));
            $sheet->setCellValue('B' . $row, $order->get_meta('_fd_last_name'));
            $sheet->setCellValue('C' . $row, $order->get_meta('_fd_national_code'));
            $sheet->setCellValue('D' . $row, $order->get_meta('_fd_mobile'));
            $sheet->setCellValue('E' . $row, $order->get_meta('_fd_address'));
            $sheet->setCellValue('F' . $row, FormDesk_Statuses::get_label($status));
            $sheet->setCellValue('G' . $row, $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i') : '');

            $row++;
        }

        $filename = 'formdesk-registrations-' . date('Y-m-d') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');

        exit;
    }
}
