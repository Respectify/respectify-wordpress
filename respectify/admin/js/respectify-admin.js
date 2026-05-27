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

    // Show the account's credit status from the usercheck endpoint.
    //
    // Respectify uses credit-based billing: every feature is available and charged per use,
    // so there is no per-feature "in your plan" gating to display. We just show the current
    // credit balance / status. (Earlier versions rendered a features table and per-checkbox
    // "In your plan / Not in your plan" indicators from an allowed_endpoints list the server
    // no longer returns; that is intentionally gone.)
    function updateSubscriptionStatus(subscription) {
        var $container = $('#respectify-subscription-status');
        var $planName = $('#respectify-plan-name');
        var $featuresList = $('#respectify-features-list');

        // Remove any legacy per-checkbox plan indicators left by older plugin versions.
        $('.respectify-feature-indicator').remove();
        if ($featuresList.length) {
            $featuresList.empty();
        }

        if (!subscription) {
            $container.hide();
            return;
        }

        $container.show();

        // plan_name from the server already carries the balance, e.g. "Credit Balance: $12.34"
        // (or "Internal Unlimited"). Show it directly; flag a depleted balance in red.
        if (subscription.active && subscription.plan_name) {
            $planName.html('<span style="color: #46b450;">●</span> ' + $('<div>').text(subscription.plan_name).html());
            $container.css('border-left', '4px solid #46b450');
        } else {
            var label = subscription.plan_name
                ? $('<div>').text(subscription.plan_name).html() + ' — add funds to continue'
                : 'No credit — add funds to continue';
            $planName.html('<span style="color: #d63638;">●</span> <em>' + label + '</em>');
            $container.css('border-left', '4px solid #d63638');
        }
    }

    // Function to fetch subscription status (used on page load and test click)
    function fetchSubscriptionStatus(showTestResult) {
        var email = $('input[name="respectify_email"]').val();
        var apiKey = $('input[name="respectify_api_key"]').val();
        var baseUrl = $('input[name="respectify_base_url"]').val();
        var apiVersion = $('input[name="respectify_api_version"]').val();

        // Don't fetch if no credentials
        if (!email || !apiKey) {
            return;
        }

        if (showTestResult) {
            $('#respectify-test-result').html('Testing...');
        }

        $.post(respectify_ajax_object.ajax_url, {
            action: 'respectify_test_credentials',
            nonce: respectify_ajax_object.nonce,
            email: email,
            api_key: apiKey,
            base_url: baseUrl,
            api_version: apiVersion
        }, function(response) {
            if (showTestResult) {
                console.log('AJAX response:', response);
                if (response.success) {
                    if (response.data && response.data.message) {
                        // Check if there's an active subscription to determine color
                        var hasSubscription = response.data.has_subscription;
                        var color = hasSubscription ? 'green' : '#b26200';
                        // Use .html() for the message since it may contain a link
                        $('#respectify-test-result').html('<span style="color:' + color + ';">' + response.data.message + '</span>');
                    } else {
                        console.log('Success response but no message:', response);
                        $('#respectify-test-result').html('<span style="color:green;"></span>').find('span').text(respectify_admin_i18n.success_no_message);
                    }
                } else {
                    if (response.data && response.data.message) {
                        $('#respectify-test-result').html('<span style="color:red;"></span>').find('span').text(response.data.message);
                    } else {
                        console.log('Error response but no message:', response);
                        $('#respectify-test-result').html('<span style="color:red;"></span>').find('span').text(respectify_admin_i18n.error_prefix + response.data.message);
                    }
                }
            }

            // Update subscription status display (on success only)
            if (response.success && response.data && response.data.subscription) {
                updateSubscriptionStatus(response.data.subscription);
            } else if (showTestResult) {
                $('#respectify-subscription-status').hide();
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            if (showTestResult) {
                console.log('AJAX request failed:', textStatus, errorThrown);
                $('#respectify-test-result').html('<span style="color:red;"></span>').find('span').text(respectify_admin_i18n.error_generic);
                $('#respectify-subscription-status').hide();
            }
        });
    }

    // For the admin page, button to test credentials
    $(document).ready(function() {
        // Fetch subscription status on page load (silently)
        fetchSubscriptionStatus(false);

        $('#respectify-test-button').on('click', function() {
            fetchSubscriptionStatus(true);
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
            const textElement = e.target.closest('.respectify-slider-row').querySelector('.description');
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

        // Toxicity threshold slider - update color based on value
        document.getElementById('respectify_toxicity_threshold')?.addEventListener('input', function(e) {
            const value = parseFloat(e.target.value);
            const percentage = Math.round(value * 100);

            // Update color class (1-10 scale based on percentage)
            const step = Math.floor(percentage / 10);
            e.target.classList.remove('slider-value-0', 'slider-value-1', 'slider-value-2', 'slider-value-3',
                                    'slider-value-4', 'slider-value-5', 'slider-value-6', 'slider-value-7',
                                    'slider-value-8', 'slider-value-9', 'slider-value-10');
            e.target.classList.add('slider-value-' + step);
        });

        // Set initial toxicity slider value
        const toxicitySlider = document.getElementById('respectify_toxicity_threshold');
        if (toxicitySlider) {
            toxicitySlider.dispatchEvent(new Event('input'));
        }

        // Handle the toxicity checkbox to enable/disable the slider
        $('#respectify_toxicity_checkbox').on('change', function() {
            var isChecked = $(this).is(':checked');
            var $sliderRow = $('#toxicity-threshold-slider');
            var $sliderInput = $('#respectify_toxicity_threshold');

            if (isChecked) {
                $sliderRow.css('opacity', '1');
                $sliderInput.prop('disabled', false);
            } else {
                $sliderRow.css('opacity', '0.5');
                $sliderInput.prop('disabled', true);
            }
        });

        // Set initial state based on checkbox
        $('#respectify_toxicity_checkbox').trigger('change');

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

        // Compatibility fix buttons
        $(document).on('click', '.respectify-fix-button', function() {
            var $button = $(this);
            var $status = $button.siblings('.respectify-fix-status');
            var $issue = $button.closest('.respectify-compatibility-issue');
            var setting = $button.data('setting');
            var nonce = $button.data('nonce');

            // Disable button and show progress
            $button.prop('disabled', true);
            $status.removeClass('success error').text(respectify_admin_i18n.fixing || 'Applying fix...');

            $.post(respectify_ajax_object.ajax_url, {
                action: 'respectify_fix_compatibility',
                setting: setting,
                nonce: nonce
            }, function(response) {
                if (response.success) {
                    $status.addClass('success').text(response.data.message);
                    // Fade out the issue after a delay
                    setTimeout(function() {
                        $issue.fadeOut(400, function() {
                            $(this).remove();
                            // If no more issues, remove the entire notice
                            if ($('.respectify-compatibility-issue').length === 0) {
                                $('.respectify-compatibility-notice').fadeOut(400, function() {
                                    $(this).remove();
                                });
                            }
                        });
                    }, 2000);
                } else {
                    $status.addClass('error').text(response.data.message || 'An error occurred.');
                    $button.prop('disabled', false);
                }
            }).fail(function() {
                $status.addClass('error').text('Request failed. Please try again.');
                $button.prop('disabled', false);
            });
        });
    });

})( jQuery );
