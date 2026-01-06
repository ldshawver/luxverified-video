jQuery(document).ready(function($) {

    $(document).on('click', '.lux-verify-action', function(e) {
        e.preventDefault();

        const $btn = $(this);
        const userId = $btn.data('user');
        const action = $btn.data('action');

        $.ajax({
            url: luxVerified.ajaxurl,
            type: 'POST',
            data: {
                action: 'lux_verified_update_status',
                nonce: luxVerified.nonce,
                user_id: userId,
                status: action
            },
            beforeSend: function() {
                $btn.prop('disabled', true).text('Processing...');
            },
            success: function(response) {
                if (response.success) {
                    alert('Status updated: ' + response.data);
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('AJAX error. Please try again.');
            },
            complete: function() {
                $btn.prop('disabled', false).text(action === 'approve' ? 'Approve' : 'Reject');
            }
        });
    });

});
