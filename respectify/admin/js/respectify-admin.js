(function( $ ) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */

	// For the admin page, button to test credentials
    $(document).ready(function() {
        $('#respectify-test-button').on('click', function() {
            $('#respectify-test-result').html('Testing...');

            // Get the values from the input fields
            var email = $('input[name="respectify_email"]').val();
            var apiKey = $('input[name="respectify_api_key"]').val();

            $.post(respectify_ajax_object.ajax_url, {
                action: 'respectify_test_credentials',
                nonce: respectify_ajax_object.nonce,
                email: email,
                api_key: apiKey
            }, function(response) {
                console.log('AJAX response:', response); // Debugging: Log the response
                if (response.success) {
                    if (response.data && response.data.message) {
                        $('#respectify-test-result').html('<span style="color:green;">' + response.data.message + '</span>');
                    } else {
                        console.log('Success response but no message:', response);
                        $('#respectify-test-result').html('<span style="color:green;">✅ Success, but no message provided. Try using Respectify but if you get errors, contact Support.</span>');
                    }
                } else {
                    if (response.data && response.data.message) {
                        $('#respectify-test-result').html('<span style="color:red;">' + response.data.message + '</span>');
                    } else {
                        console.log('Error response but no message:', response);
                        $('#respectify-test-result').html('<span style="color:red;">❌ An error occurred.</span>');
                    }
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.log('AJAX request failed:', textStatus, errorThrown); // Debugging: Log the failure
                $('#respectify-test-result').html('<span style="color:red;">❌ An error occurred.</span>');
            });
        });


        /* Settings slider */

        // Select the slider and the element to display its value
        var $slider = $('#respectify_revise_min_score');
        var $valueDisplay = $('#revise_min_score_value');
        var $sliderRow = $slider.closest('.respectify-slider-row');

        // Function to update the slider value display and color
        function updateSlider() {
            var value = $slider.val();
            $valueDisplay.text(value);

            // Remove existing slider-value-* classes
            $slider.removeClass('slider-value-1 slider-value-2 slider-value-3 slider-value-4 slider-value-5');

            // Add the class corresponding to the current slider value
            $slider.addClass('slider-value-' + value);
        }

        // Initialize the slider on page load
        updateSlider();

        // Update the slider when its value changes
        $slider.on('input change', function() {
            updateSlider();
        });
    });

})( jQuery );
