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
            // New: retrieve advanced settings
            var baseUrl = $('input[name="respectify_base_url"]').val();
            var apiVersion = $('input[name="respectify_api_version"]').val();

            $.post(respectify_ajax_object.ajax_url, {
                action: 'respectify_test_credentials',
                nonce: respectify_ajax_object.nonce,
                email: email,
                api_key: apiKey,
                base_url: baseUrl,
                api_version: apiVersion
            }, function(response) {
                console.log('AJAX response:', response); // Debugging: Log the response
                if (response.success) {
                    if (response.data && response.data.message) {
                        $('#respectify-test-result').html('<span style="color:green;">' + response.data.message + '</span>');
                    } else {
                        console.log('Success response but no message:', response);
                        $('#respectify-test-result').html('<span style="color:green;">' + respectify_admin_i18n.success_no_message + '</span>');
                    }
                } else {
                    if (response.data && response.data.message) {
                        $('#respectify-test-result').html('<span style="color:red;">' + response.data.message + '</span>');
                    } else {
                        console.log('Error response but no message:', response);
                        $('#respectify-test-result').html('<span style="color:red;">' + respectify_admin_i18n.error_prefix + response.data.message + '</span>');
                    }
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.log('AJAX request failed:', textStatus, errorThrown); // Debugging: Log the failure
                $('#respectify-test-result').html('<span style="color:red;">' + respectify_admin_i18n.error_prefix + response.data.message + '</span>');
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

        // Update the minimum score value display when the slider changes
        $('#respectify_revise_min_score').on('input', function() {
            $('#revise_min_score_value').text($(this).val());
        });

        // Banned topics threshold slider
        document.getElementById('respectify_banned_topics_threshold')?.addEventListener('input', function(e) {
            const value = parseFloat(e.target.value);
            const percentage = Math.round(value * 100);
            const textElement = e.target.parentElement.querySelector('.description');
            if (textElement) {
                textElement.textContent = `It's ok for ${percentage}% of the comment to be about an unwanted topic.`;
            }
            
            // Update color class
            const step = Math.floor(percentage / 10);
            e.target.classList.remove('slider-value-0', 'slider-value-1', 'slider-value-2', 'slider-value-3', 
                                    'slider-value-4', 'slider-value-5', 'slider-value-6', 'slider-value-7', 
                                    'slider-value-8', 'slider-value-9', 'slider-value-10');
            e.target.classList.add('slider-value-' + step);
        });

        // Set initial value
        const bannedTopicsSlider = document.getElementById('respectify_banned_topics_threshold');
        if (bannedTopicsSlider) {
            bannedTopicsSlider.dispatchEvent(new Event('input'));
        }

        // Handle the banned topics mode radio buttons
        $('input[name="respectify_relevance_settings[banned_topics_mode]"]').on('change', function() {
            var isThreshold = $(this).val() === 'threshold';
            var $slider = $('#banned-topics-threshold-slider');
            var $sliderInput = $('#respectify_banned_topics_threshold');
            
            if (isThreshold) {
                $slider.css('opacity', '1');
                $sliderInput.prop('disabled', false);
            } else {
                $slider.css('opacity', '0.5');
                $sliderInput.prop('disabled', true);
            }
        });

        // Advanced settings: accordion hiding them
        $('#respectify-advanced-settings-button').click(function() {
            var panel = $(this).next('.respectify-panel');
            panel.toggleClass('active');
            if (panel.css('max-height') === '0px') {
                panel.css('max-height', panel.prop('scrollHeight') + 'px');
            } else {
                panel.css('max-height', '0px');
            }

            //$(this).css('display', 'none'); // Hides the button once expanded
        });
    });

})( jQuery );
