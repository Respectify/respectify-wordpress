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
                    $('.respectify-error-message').remove(); // Remove any existing error messages
                    $('#commentform .comment-form-comment').append('<p class="respectify-error-message">' + response.data + '</p>');
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX error:', status, error);
            }
        });
    });
});