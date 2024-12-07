jQuery(document).ready(function($) {
    $('#commentform').on('submit', function(event) {
        event.preventDefault();

        var form = $(this);
        var formData = form.serialize();

        // Remove messages
        $('#commentform .comment-form-comment .respectify-error-message').remove();
        $('#commentform .comment-form-comment .respectify-success-message').remove();
        $('#commentform .comment-form-comment .respectify-working-message').remove();

        $('#commentform .comment-form-comment').append('<p class="respectify-working-message">' + 'Please wait...' + '</p>');

        $.ajax({
            type: 'POST',
            url: form.attr('action'),
            data: formData,
            success: function(response) {
                alert('AJAX response:' + JSON.stringify(response));

                $('#commentform .comment-form-comment .respectify-error-message').remove();
                $('#commentform .comment-form-comment .respectify-success-message').remove();
                $('#commentform .comment-form-comment .respectify-working-message').remove();

                if (response.success) {
                    // Clear the comment text field
                    $('#comment').val('');
                    // Reset the form
                    form[0].reset();

                    $('#commentform .comment-form-comment').append('<p class="respectify-success-message">' + response.data + '</p>');
                } else {
                    // Display the error message
                    $('#commentform .comment-form-comment').append('<p class="respectify-error-message">' + response.data + '</p>');
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX error:', status, error);
            }
        });
    });
});