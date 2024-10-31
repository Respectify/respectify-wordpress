jQuery(document).ready(function($) {
    $('#commentform').on('submit', function(event) {
        event.preventDefault();

        var form = $(this);
        var formData = form.serialize();

        $.post(form.attr('action'), formData, function(response) {
            if (response.success) {
                form.unbind('submit').submit();
            } else {
                // Display the error message
                $('#commentform .comment-form-comment').append('<p class="respectify-error-message">' + response.data + '</p>');
            }
        });
    });
});