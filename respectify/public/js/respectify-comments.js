(function($) {
    $(document).ready(function() {
        $('#commentform').on('submit', function(event) {
            event.preventDefault();

            var form = $(this);
            // Get the form data
            var formData = {
                action: 'respectify_submit_comment',
                comment_post_ID: form.find('input[name="comment_post_ID"]').val(),
                comment_content: form.find('textarea[name="comment"]').val(),
                comment_parent: form.find('input[name="comment_parent"]').val(),
                respectify_nonce: form.find('input[name="respectify_nonce"]').val()
            };

            // Get user info regardless of login status
            formData.author = form.find('input[name="author"]').val();
            formData.email = form.find('input[name="email"]').val();
            formData.url = form.find('input[name="url"]').val();

            // If user is logged in, get their user_id
            if (form.find('input[name="user_id"]').length) {
                formData.user_id = form.find('input[name="user_id"]').val();
            }

            // Remove previous messages and 'Post Anyway' button
            $('.respectify-message, #respectify-post-anyway').remove();

            // Show a loading message
            var loadingMessage = $('<p class="respectify-message">' + respectify_comments_i18n.submitting + '</p>');
            form.before(loadingMessage);

            // Check if 'Post Anyway' button was clicked
            var postAnyway = form.data('post-anyway') || false;
            if (postAnyway) {
                formData.post_anyway = '1';
                // Reset the data attribute
                form.data('post-anyway', false);
            }

            $.ajax({
                type: 'POST',
                url: respectify_ajax_object.ajax_url,
                data: formData,
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
                        var message = response.data.message || respectify_comments_i18n.error_occurred;
                        var errorMessage = $('<div class="respectify-message respectify-error">' + message + '</div>');
                        form.before(errorMessage);
                    }
                },
                error: function() {
                    // Remove the loading message
                    loadingMessage.remove();
                    var errorMessage = $('<p class="respectify-message error">' + respectify_comments_i18n.error_try_again + '</p>');
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