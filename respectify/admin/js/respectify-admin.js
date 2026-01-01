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

	// Feature names mapping (internal name -> display name)
    var featureNames = {
        'commentscore': 'Comment Quality Scoring',
        'commentrelevance': 'Relevance Checking',
        'dogwhistle': 'Dogwhistle Detection',
        'antispam': 'Spam Detection'
    };

    // All features to display (in order - spam last)
    var allFeatures = ['commentscore', 'commentrelevance', 'dogwhistle', 'antispam'];

    // Mapping from endpoint names to checkbox setting names
    var endpointToCheckbox = {
        'commentscore': 'assess_health',
        'commentrelevance': 'check_relevance',
        'dogwhistle': 'check_dogwhistle',
        'antispam': 'check_spam'
    };

    // Function to update subscription status display
    function updateSubscriptionStatus(subscription) {
        var $container = $('#respectify-subscription-status');
        var $planName = $('#respectify-plan-name');
        var $featuresList = $('#respectify-features-list');

        if (!subscription) {
            $container.hide();
            // Clear checkbox indicators
            $('.respectify-feature-indicator').remove();
            return;
        }

        $container.show();

        // Show plan name or "No active subscription" with colored indicator
        if (subscription.active && subscription.plan_name) {
            $planName.html('<span style="color: #46b450;">●</span> <strong>Plan:</strong> ' + $('<div>').text(subscription.plan_name).html());
            $container.css('border-left', '4px solid #46b450');
        } else {
            $planName.html('<span style="color: #d63638;">●</span> <em>No active subscription</em>');
            $container.css('border-left', '4px solid #d63638');
        }

        // Build features list with checkmarks/crosses
        var allowedEndpoints = subscription.allowed_endpoints || [];
        var html = '<table class="respectify-features-table">';

        for (var i = 0; i < allFeatures.length; i++) {
            var feature = allFeatures[i];
            var displayName = featureNames[feature] || feature;
            var isAllowed = allowedEndpoints.indexOf(feature) !== -1;
            var icon = isAllowed ? '<span style="color: green;">✓</span>' : '<span style="color: #999;">✗</span>';
            var textStyle = isAllowed ? '' : 'color: #999;';

            html += '<tr><td style="padding: 2px 10px 2px 0;">' + icon + '</td>';
            html += '<td style="padding: 2px 0; ' + textStyle + '">' + displayName + '</td></tr>';
        }

        html += '</table>';
        $featuresList.html(html);

        // Update indicators next to checkboxes
        updateCheckboxIndicators(allowedEndpoints);
    }

    // Function to update indicators next to each feature checkbox
    function updateCheckboxIndicators(allowedEndpoints) {
        // Remove existing indicators
        $('.respectify-feature-indicator').remove();

        for (var endpoint in endpointToCheckbox) {
            var checkboxName = endpointToCheckbox[endpoint];
            var isAllowed = allowedEndpoints && allowedEndpoints.indexOf(endpoint) !== -1;
            var icon = isAllowed ? '✓' : '✗';
            var text = isAllowed ? 'In your plan' : 'Not in your plan';
            var color = isAllowed ? '#46b450' : '#999';

            // Find the checkbox and add indicator inline after the label text
            var $checkbox = $('input[name="respectify_assessment_settings[' + checkboxName + ']"]');
            if ($checkbox.length) {
                var $label = $checkbox.closest('label');
                if ($label.length) {
                    // Append the indicator inside the label, after the text
                    $label.append('<span class="respectify-feature-indicator" style="color: ' + color + '; margin-left: 10px; font-size: 12px; font-weight: normal;">' + icon + ' ' + text + '</span>');
                }
            }
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
