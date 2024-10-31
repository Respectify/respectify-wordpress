jQuery(document).ready(function($) {
    $('#commentform').on('submit', function(event) {
        event.preventDefault();

        var form = $(this);
        var formData = form.serialize();

        $.ajax({
            type: 'POST',
            url: form.attr('action'),
            data: formData,
            success: function(response) {
                if (response.success) {
                    form.unbind('submit').submit();
                } else {
                    // Display the error message
                    $('#commentform .comment-form-comment .respectify-error-message').remove();

                    $('#commentform .comment-form-comment').append('<p class="respectify-error-message">' + response.data + '</p>');
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX error:', status, error);
            }
        });
    });
});