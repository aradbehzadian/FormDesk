jQuery(function ($) {

    $('#formdesk-registration-form').on('submit', function (e) {

        e.preventDefault();

        let formData = new FormData(this);

        formData.append('action', 'formdesk_register');
        formData.append('nonce', FormDesk.nonce);

        $.ajax({

            url: FormDesk.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,

            beforeSend: function () {
                $('#formdesk-register-result').html(`
                    <div class="fd-loading">
                        <span class="fd-spinner"></span>
                        در حال ارسال اطلاعات...
                    </div>
                `);
            },

            success: function (response) {

                if (response.success) {

                    $('#formdesk-register-result').html(`
                        <div class="success">✅ ${response.data.message}</div>
                    `);

                    $('#formdesk-registration-form')[0].reset();

                } else {

                    $('#formdesk-register-result').html(`
                        <div class="error">❌ ${response.data.message}</div>
                    `);

                }

            },

            error: function () {
                $('#formdesk-register-result').html(`
                    <div class="error">❌ خطا در ارسال اطلاعات</div>
                `);
            }

        });

    });

});
