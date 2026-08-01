jQuery(function ($) {

    let checking = false;

    function checkNotifications() {

        if (checking) {
            return;
        }

        checking = true;

        $.ajax({

            url: FormDeskNotification.ajaxurl,
            type: 'POST',
            data: { action: 'formdesk_check_notifications', nonce: FormDeskNotification.nonce },

            success: function (response) {

                checking = false;

                if (!response.success || !response.data.found) {
                    return;
                }

                $.each(response.data.notifications, function (i, notification) {
                    showNotification(notification);
                });

            },

            error: function () {
                checking = false;
            }

        });

    }

    function escapeHtml(str) {
        return $('<div>').text(str == null ? '' : String(str)).html();
    }

    function showNotification(data) {

        if ($('#formdesk-notification-' + data.id).length) {
            return;
        }

        let count = $('.formdesk-notification').length;

        let isApplicant  = !data.type || data.type === 'applicant';
        let buttonLabel  = isApplicant ? 'مشاهده پرونده' : 'رفتن به تنظیمات';

        let html =
            '<div class="formdesk-notification" id="formdesk-notification-' + data.id + '">' +
                '<div class="dn-title">🚗 ' + escapeHtml(data.title) + '</div>' +
                '<div class="dn-message">' + escapeHtml(data.message) + '</div>' +
                '<div class="dn-buttons">' +
                    '<button class="button button-primary dn-open" data-id="' + data.id + '" data-order="' + data.order_id + '" data-type="' + (data.type || 'applicant') + '" data-url="' + (data.url || '') + '">' + buttonLabel + '</button>' +
                    '<button class="button dn-close" data-id="' + data.id + '">بستن</button>' +
                '</div>' +
            '</div>';

        $('body').append(html);

        $('#formdesk-notification-' + data.id).css('bottom', (25 + (count * 170)) + 'px');

    }

    $(document).on('click', '.dn-close', function () {

        let id  = $(this).data('id');
        let box = $('#formdesk-notification-' + id);

        $.ajax({
            url: FormDeskNotification.ajaxurl,
            type: 'POST',
            data: { action: 'formdesk_read_notification', id: id, nonce: FormDeskNotification.nonce },

            success: function (response) {

                if (!response.success) {
                    return;
                }

                box.fadeOut(300, function () {

                    $(this).remove();

                    $('.formdesk-notification').each(function (index) {
                        $(this).animate({ bottom: (25 + (index * 170)) + 'px' }, 200);
                    });

                });

            }

        });

    });

    $(document).on('click', '.dn-open', function () {

        let id   = $(this).data('id');
        let type = $(this).data('type');

        $.post(FormDeskNotification.ajaxurl, {
            action: 'formdesk_read_notification',
            id: id,
            nonce: FormDeskNotification.nonce
        });

        if (!type || type === 'applicant') {

            let order = $(this).data('order');
            window.location = 'admin.php?page=formdesk&highlight=' + order + '&notification=' + id;

        } else {

            let url = $(this).data('url');
            if (url) {
                window.location = url;
            }

        }

    });

    checkNotifications();
    setInterval(checkNotifications, 5000);

});
