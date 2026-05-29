
(function ($) {
    const config = window.LskyUploadOne || {};

    if (config.flag !== 'page') {
        return;
    }

    $('#lsky-upload-one').off('click').on('click', function () {
        const button = $(this);
        button.prop('disabled', true);

        $.ajax({
            type: 'POST',
            url: config.ajaxUrl,
            data: {
                action: 'lsky_upload_one',
                post_id: config.postId,
                nonce: config.nonce
            },
            success: function (res) {
                if (res && res.success) {
                    alert(res.data.message || '替换成功');
                    return;
                }

                alert((res && res.data && res.data.message) || '替换失败');
            },
            error: function (xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.data
                    ? xhr.responseJSON.data.message
                    : '替换失败';
                alert(message);
            },
            complete: function () {
                button.prop('disabled', false);
            }
        });
    });
})(jQuery);
