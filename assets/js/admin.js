function formdeskRefreshPendingCount() {

    jQuery.ajax({

        url: FormDeskAdmin.ajaxurl,
        type: 'POST',
        data: { action: 'formdesk_get_pending_count', nonce: FormDeskAdmin.nonce },

        success: function (response) {

            if (!response.success) {
                return;
            }

            let count = response.data.count;
            let menu  = jQuery('#toplevel_page_formdesk .formdesk-count');

            if (count > 0) {

                if (menu.length) {
                    menu.text(count);
                } else {
                    jQuery('#toplevel_page_formdesk a')
                        .first()
                        .append(' <span class="formdesk-count">' + count + '</span>');
                }

            } else {
                menu.remove();
            }

        }

    });

}

function formdeskRefreshStats() {

    jQuery.ajax({

        url: FormDeskAdmin.ajaxurl,
        type: 'POST',
        data: { action: 'formdesk_get_stats', nonce: FormDeskAdmin.nonce },

        success: function (response) {

            if (!response.success) {
                return;
            }

            // کارت‌های آماری بر اساس ویژگی data-stat-key به‌روزرسانی می‌شوند
            // (تعداد و عنوان این کارت‌ها کاملاً متناسب با وضعیت‌های تعریف‌شده
            // در تنظیمات است و می‌تواند در هر لحظه توسط طراح سایت تغییر کند)
            jQuery('.formdesk-dashboard [data-stat-key]').each(function () {

                let key   = jQuery(this).data('stat-key');
                let value = response.data[key];

                if (typeof value !== 'undefined') {
                    jQuery(this).text(value);
                }

            });

        }

    });

}

jQuery(function ($) {

    /*
    |--------------------------------------------------------------------------
    | تغییر وضعیت متقاضی
    |--------------------------------------------------------------------------
    */

    $(document).on('change', '.formdesk-status', function () {

        var select = $(this);

        $.ajax({

            url: FormDeskAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'formdesk_update_status',
                order_id: select.data('order'),
                status: select.val(),
                nonce: FormDeskAdmin.nonce
            },

            success: function (response) {

                if (response.success) {

                    formdeskRefreshStats();
                    formdeskRefreshPendingCount();

                    select.css('background', '#dff6dd');
                    setTimeout(function () {
                        select.css('background', '');
                    }, 700);

                } else {
                    alert('خطا در ذخیره وضعیت');
                }

            },

            error: function () {
                alert('خطا در ارتباط با سرور');
            }

        });

    });

    /*
    |--------------------------------------------------------------------------
    | حذف متقاضی
    |--------------------------------------------------------------------------
    */

    $(document).on('click', '.formdesk-delete', function () {

        if (!confirm('آیا از حذف این متقاضی مطمئن هستید؟')) {
            return;
        }

        var button = $(this);

        $.ajax({

            url: FormDeskAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'formdesk_delete_registration',
                order_id: button.data('order'),
                nonce: FormDeskAdmin.nonce
            },

            success: function (response) {

                if (response.success) {

                    button.closest('tr').fadeOut(300, function () {
                        $(this).remove();
                        formdeskRefreshStats();
                        formdeskRefreshPendingCount();
                    });

                } else {
                    alert('خطا در حذف متقاضی');
                }

            },

            error: function () {
                alert('ارتباط با سرور برقرار نشد.');
            }

        });

    });

    /*
    |--------------------------------------------------------------------------
    | جستجوی زنده متقاضیان
    |--------------------------------------------------------------------------
    */

    $('#formdesk-search').on('keyup', function () {

        let value = $(this).val().toLowerCase().trim();

        $('table.widefat tbody tr').each(function () {

            let name     = ($(this).data('name') || '').toString().toLowerCase();
            let national = ($(this).data('national') || '').toString().toLowerCase();
            let mobile   = ($(this).data('mobile') || '').toString().toLowerCase();

            if (name.indexOf(value) > -1 || national.indexOf(value) > -1 || mobile.indexOf(value) > -1) {
                $(this).show();
            } else {
                $(this).hide();
            }

        });

    });

    /*
    |--------------------------------------------------------------------------
    | هایلایت متقاضی از طریق لینک اعلان
    |--------------------------------------------------------------------------
    */

    const params = new URLSearchParams(window.location.search);
    const id = params.get('highlight');

    if (id) {

        let row = $('#registration-' + id);

        if (row.length) {

            $('html,body').animate({ scrollTop: row.offset().top - 180 }, 700);

            row.css({ background: '#d8ecff', transition: 'all .5s' });

            setTimeout(function () {
                row.css('background', '');
            }, 4000);

        }

    }

});

setInterval(function () {
    formdeskRefreshPendingCount();
}, 30000);
