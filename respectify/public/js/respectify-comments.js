(function($) {
    $(document).ready(function() {
        $('#commentform').on('submit', function(event) {
            event.preventDefault();

            var form = $(this);
            var formData = form.serializeArray();
            formData.push({ name: 'action', value: 'respectify_submit_comment' });

            // Check if 'Post Anyway' button was clicked
            var postAnyway = form.data('post-anyway') || false;
            if (postAnyway) {
                formData.push({ name: 'post_anyway', value: '1' });
            }

            // Remove previous messages
            $('.respectify-message').remove();

            // Show a loading message
            form.append('<p class="respectify-message">Submitting your comment; please wait a moment...</p>');

            $.ajax({
                type: 'POST',
                url: respectify_ajax_object.ajax_url,
                data: formData,
                success: function(response) {
                    $('.respectify-message').remove();

                    if (response.success) {
                        // Reset the form
                        form[0].reset();
                        // Display success message
                        form.append('<p class="respectify-message success">' + response.data.message + '</p>');
                    } else {
                        // Display error message
                        var message = response.data.message || 'An error occurred.';
                        form.append('<p class="respectify-message error">' + message + '</p>');

                        // Show 'Post Anyway' button if allowed
                        if (response.data.allow_post_anyway) {
                            form.append('<button type="button" id="respectify-post-anyway" class="respectify-button">Post Anyway</button>');
                        }
                    }
                },
                error: function() {
                    $('.respectify-message').remove();
                    form.append('<p class="respectify-message error">An error occurred. Please try again.</p>');
                }
            });
        });

        // Handle 'Post Anyway' button click
        $(document).on('click', '#respectify-post-anyway', function() {
            var form = $('#commentform');
            form.data('post-anyway', true);
            form.submit();
            $(this).remove(); // Remove the button to prevent multiple clicks
        });
    });
})(jQuery);