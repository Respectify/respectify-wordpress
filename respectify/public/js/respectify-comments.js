(function($) {
    $(document).ready(function() {
        $('#commentform').on('submit', function(event) {
            event.preventDefault();

            var form = $(this);
            var formData = form.serializeArray(); // Should include the nonce
            formData.push({ name: 'action', value: 'respectify_submit_comment' });

            // Remove previous messages and 'Post Anyway' button
            $('.respectify-message, #respectify-post-anyway').remove();

            // Show a loading message
            var loadingMessage = $('<p class="respectify-message">Submitting your comment; please wait a moment...</p>');
            form.before(loadingMessage);

            // Check if 'Post Anyway' button was clicked
            var postAnyway = form.data('post-anyway') || false;
            if (postAnyway) {
                formData.push({ name: 'post_anyway', value: '1' });
                // Reset the data attribute
                form.data('post-anyway', false);
            }

            $.ajax({
                type: 'POST',
                url: respectify_ajax_object.ajax_url,
                data: $.param(formData),
                success: function(response) {
                    // Remove the loading message
                    loadingMessage.remove();

                    if (response.success) {
                        // Reset the form
                        form[0].reset();
                        // Display success message
                        var successMessage = $('<p class="respectify-message respectify-success">' + response.data.message + '</p>');
                        form.before(successMessage);
                        // Append the new comment to the comment list
                        if (response.data.comment_html) {
                            // Assuming the comments are in a <ol> or <ul> with class 'comment-list'
                            $('.comment-list').append(response.data.comment_html);
                        } else {
                            // If comment HTML is not provided, reload the page
                            location.reload();
                        }
                    } else {
                        // Display error message
                        var message = response.data.message || 'An error occurred.';
                        var errorMessage = $('<div class="respectify-message respectify-error">' + message + '</div>');
                        form.before(errorMessage);
                    }
                },
                error: function() {
                    // Remove the loading message
                    loadingMessage.remove();
                    var errorMessage = $('<p class="respectify-message error">An error occurred. Please try again.</p>');
                    form.before(errorMessage);
                }
            });
        });

        // Handle 'Post Anyway' button click
        $(document).on('click', '#respectify-post-anyway', function() {
            var form = $('#commentform');
            form.data('post-anyway', true);
            // Remove any existing error messages and the button itself
            $('.respectify-message, #respectify-post-anyway').remove();
            form.submit();
        });
    });
})(jQuery);