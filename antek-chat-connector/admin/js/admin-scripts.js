/**
 * Admin Scripts
 *
 * @package Antek_Chat_Connector
 * @since 1.0.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // Initialize color pickers
        if ($.fn.wpColorPicker) {
            $('.antek-color-picker').wpColorPicker();
        }

        // Handle chat provider selection
        function updateChatProviderFields() {
            var provider = $('input[name="antek_chat_settings[chat_provider]"]:checked').val();
            $('.chat-provider-field').hide();
            if (provider) {
                $('.' + provider + '-field').show();
            }
        }

        $('input[name="antek_chat_settings[chat_provider]"]').on('change', updateChatProviderFields);
        updateChatProviderFields(); // Run on page load

        // Test webhook button
        $('#test-webhook').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $result = $('#test-result');

            // Disable button and show loading
            $button.prop('disabled', true);
            $result.removeClass('success error')
                   .html('<span class="spinner is-active"></span>' + antekChatAdmin.strings.testing);

            // Send AJAX request
            $.ajax({
                url: antekChatAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'antek_chat_test_webhook',
                    nonce: antekChatAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.addClass('success')
                               .text('✓ ' + response.data.message);
                    } else {
                        $result.addClass('error')
                               .text('✗ ' + antekChatAdmin.strings.error + ' ' + response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    $result.addClass('error')
                           .text('✗ ' + antekChatAdmin.strings.error + ' ' + error);
                },
                complete: function() {
                    $button.prop('disabled', false);

                    // Clear result after 5 seconds
                    setTimeout(function() {
                        $result.fadeOut(function() {
                            $(this).removeClass('success error').text('').show();
                        });
                    }, 5000);
                }
            });
        });

        // Show/hide popup delay description based on trigger type
        $('#popup_trigger').on('change', function() {
            var trigger = $(this).val();
            var $delayField = $('#popup_delay').closest('tr');
            var $description = $delayField.find('.description');

            if (trigger === 'time') {
                $description.html('For time delay: milliseconds (e.g., 3000 = 3 seconds)');
            } else if (trigger === 'scroll') {
                $description.html('For scroll trigger: percentage (e.g., 50 = 50% down the page)');
            } else if (trigger === 'exit') {
                $description.html('Triggers when user moves mouse outside of window (exit intent)');
                $delayField.hide();
                return;
            }

            $delayField.show();
        }).trigger('change');

        // Settings form validation
        $('form').on('submit', function(e) {
            var $webhookUrl = $('#n8n_webhook_url');

            if ($webhookUrl.length && $webhookUrl.val()) {
                var url = $webhookUrl.val();

                // Basic URL validation
                if (!url.match(/^https?:\/\/.+/)) {
                    e.preventDefault();
                    alert('Please enter a valid webhook URL starting with http:// or https://');
                    $webhookUrl.focus();
                    return false;
                }
            }

            var $apiKey = $('#elevenlabs_api_key');
            var $voiceEnabled = $('#voice_enabled');

            if ($voiceEnabled.length && $voiceEnabled.is(':checked')) {
                if ($apiKey.val() === '') {
                    e.preventDefault();
                    alert('Please enter an ElevenLabs API key to enable voice chat');
                    $apiKey.focus();
                    return false;
                }
            }
        });

        // Detect theme colors button
        $('#detect-theme-colors').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $result = $('#detect-colors-result');

            // Disable button and show loading
            $button.prop('disabled', true);
            $result.removeClass('success error')
                   .html('<span class="spinner is-active" style="float: none; margin: 0;"></span>');

            // Send AJAX request
            $.ajax({
                url: antekChatAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'antek_chat_detect_theme_colors',
                    nonce: antekChatAdmin.nonce
                },
                success: function(response) {
                    if (response.success && response.data.colors) {
                        var colors = response.data.colors;

                        // Update color picker inputs
                        $('#primary_color').val(colors.primary_color).trigger('change');
                        $('#secondary_color').val(colors.secondary_color).trigger('change');
                        $('#background_color').val(colors.background_color).trigger('change');
                        $('#text_color').val(colors.text_color).trigger('change');

                        // Update WordPress color pickers
                        if ($.fn.wpColorPicker) {
                            $('#primary_color').wpColorPicker('color', colors.primary_color);
                            $('#secondary_color').wpColorPicker('color', colors.secondary_color);
                            $('#background_color').wpColorPicker('color', colors.background_color);
                            $('#text_color').wpColorPicker('color', colors.text_color);
                        }

                        $result.addClass('success')
                               .html('<span class="dashicons dashicons-yes" style="color: #00a32a;"></span> ' + response.data.message);
                    } else {
                        $result.addClass('error')
                               .text('✗ ' + (response.data.message || antekChatAdmin.strings.error));
                    }
                },
                error: function(xhr, status, error) {
                    $result.addClass('error')
                           .text('✗ ' + antekChatAdmin.strings.error + ' ' + error);
                },
                complete: function() {
                    $button.prop('disabled', false);

                    // Clear result after 5 seconds
                    setTimeout(function() {
                        $result.fadeOut(function() {
                            $(this).removeClass('success error').html('').show();
                        });
                    }, 5000);
                }
            });
        });

    });

})(jQuery);
