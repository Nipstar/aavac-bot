<?php
/**
 * Voice Provider Settings View
 *
 * Admin interface for voice provider selection and configuration
 *
 * @package Antek_Chat_Connector
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings - with backward compatibility for old antek_chat_voice option
$settings = get_option('antek_chat_voice_settings', []);
$old_settings = get_option('antek_chat_voice', []);

// If new settings are empty, migrate from old settings
if (empty($settings) && !empty($old_settings)) {
    $settings = [
        'voice_enabled' => isset($old_settings['enabled']) ? (bool) $old_settings['enabled'] : false,
        'voice_provider' => 'n8n-retell', // Default to n8n-retell if using old format with n8n webhook
        'retell_agent_id' => isset($old_settings['retell_agent_id']) ? $old_settings['retell_agent_id'] : '',
        'n8n_base_url' => isset($old_settings['n8n_voice_token_url']) ? $old_settings['n8n_voice_token_url'] : '',
        'n8n_retell_agent_id' => isset($old_settings['retell_agent_id']) ? $old_settings['retell_agent_id'] : '',
    ];
}

$voice_enabled = isset($settings['voice_enabled']) ? (bool) $settings['voice_enabled'] : false;
$voice_provider = isset($settings['voice_provider']) ? $settings['voice_provider'] : 'retell';
$use_retell_chat = isset($settings['use_retell_chat']) ? (bool) $settings['use_retell_chat'] : true; // Default to TRUE

// Get provider factory
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/providers/class-voice-provider-factory.php';

// Get provider info
$provider_info = Antek_Chat_Voice_Provider_Factory::get_provider_info(true);
$comparison = Antek_Chat_Voice_Provider_Factory::get_provider_comparison();
?>

<div class="wrap antek-chat-settings">
    <h1><?php _e('AAVAC Bot - Voice Provider Settings', 'antek-chat-connector'); ?></h1>

    <div class="antek-settings-header">
        <p class="description">
            <?php _e('Configure Retell AI for real-time voice and text conversations.', 'antek-chat-connector'); ?>
        </p>
    </div>

    <form method="post" action="options.php" id="voice-provider-form">
        <?php
        settings_fields('antek_chat_voice_settings');
        ?>

        <!-- Voice Enable Toggle -->
        <div class="antek-section">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="voice_enabled"><?php _e('Enable Voice Chat', 'antek-chat-connector'); ?></label>
                    </th>
                    <td>
                        <label class="antek-toggle">
                            <input type="checkbox"
                                   id="voice_enabled"
                                   name="antek_chat_voice_settings[voice_enabled]"
                                   value="1"
                                   <?php checked($voice_enabled); ?>>
                            <span class="antek-toggle-slider"></span>
                        </label>
                        <p class="description">
                            <?php _e('Enable voice conversations in the chat widget', 'antek-chat-connector'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="use_retell_chat"><?php _e('Use Retell for Text Chat', 'antek-chat-connector'); ?></label>
                    </th>
                    <td>
                        <label class="antek-toggle">
                            <input type="checkbox"
                                   id="use_retell_chat"
                                   name="antek_chat_voice_settings[use_retell_chat]"
                                   value="1"
                                   <?php checked($use_retell_chat); ?>>
                            <span class="antek-toggle-slider"></span>
                        </label>
                        <p class="description">
                            <?php _e('Use Retell Chat Agent for text messages instead of webhook. Your Retell agent must support text chat. When enabled, text messages are sent to Retell API instead of your n8n webhook.', 'antek-chat-connector'); ?>
                        </p>
                    </td>
                </tr>

                <tr id="provider-selection-row" style="<?php echo $voice_enabled ? '' : 'display:none;'; ?>">
                    <th scope="row">
                        <label><?php _e('Voice Provider', 'antek-chat-connector'); ?></label>
                    </th>
                    <td>
                        <fieldset>
                            <label style="display: block; margin-bottom: 10px;">
                                <input type="radio"
                                       name="antek_chat_voice_settings[voice_provider]"
                                       value="retell"
                                       <?php checked($voice_provider, 'retell'); ?>>
                                <?php _e('Direct Retell API', 'antek-chat-connector'); ?>
                            </label>
                            <label style="display: block;">
                                <input type="radio"
                                       name="antek_chat_voice_settings[voice_provider]"
                                       value="n8n-retell"
                                       <?php checked($voice_provider, 'n8n-retell'); ?>>
                                <?php _e('n8n (Retell Proxy)', 'antek-chat-connector'); ?>
                            </label>
                        </fieldset>
                        <p class="description">
                            <?php _e('Choose between direct Retell API integration or n8n middleware proxy', 'antek-chat-connector'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Retell AI Settings (Direct API) -->
        <div class="antek-section provider-settings" id="retell-settings" style="<?php echo $voice_enabled ? '' : 'display:none;'; ?>">
            <h2><?php _e('Retell AI Configuration', 'antek-chat-connector'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="retell_api_key"><?php _e('API Key', 'antek-chat-connector'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="password"
                               id="retell_api_key"
                               name="antek_chat_voice_settings[retell_api_key]"
                               value="<?php echo esc_attr($settings['retell_api_key'] ?? ''); ?>"
                               class="regular-text"
                               placeholder="<?php esc_attr_e('Enter Retell API key', 'antek-chat-connector'); ?>">
                        <p class="description">
                            <?php printf(
                                __('Get your API key from %s', 'antek-chat-connector'),
                                '<a href="https://beta.retellai.com" target="_blank">Retell AI Dashboard</a>'
                            ); ?>
                        </p>
                        <?php if (!empty($settings['retell_api_key'])): ?>
                            <p class="antek-status encrypted">
                                <span class="dashicons dashicons-lock"></span>
                                <?php _e('API key is encrypted', 'antek-chat-connector'); ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="retell_agent_id"><?php _e('Agent ID', 'antek-chat-connector'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text"
                               id="retell_agent_id"
                               name="antek_chat_voice_settings[retell_agent_id]"
                               value="<?php echo esc_attr($settings['retell_agent_id'] ?? ''); ?>"
                               class="regular-text"
                               placeholder="<?php esc_attr_e('agent_xxxxxxxx', 'antek-chat-connector'); ?>">
                        <p class="description">
                            <?php _e('Your Retell agent ID for both voice and text chat (starts with agent_)', 'antek-chat-connector'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="retell_text_chat_agent_id">
                            <?php _e('Text Chat Agent ID', 'antek-chat-connector'); ?>
                            <span style="color: #666;">(<?php _e('Optional', 'antek-chat-connector'); ?>)</span>
                        </label>
                    </th>
                    <td>
                        <input type="text"
                               id="retell_text_chat_agent_id"
                               name="antek_chat_voice_settings[retell_chat_agent_id]"
                               value="<?php echo esc_attr($settings['retell_chat_agent_id'] ?? ''); ?>"
                               class="regular-text"
                               placeholder="<?php esc_attr_e('agent_xxxxxxxx (optional)', 'antek-chat-connector'); ?>">
                        <p class="description">
                            <?php _e('Optional: Use a different agent for text chat. If empty, will use the main Agent ID above for both voice and text.', 'antek-chat-connector'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <div class="antek-provider-status" id="retell-status"></div>
        </div>

        <!-- n8n-Retell Settings -->
        <div class="antek-section provider-settings" id="n8n-retell-settings" style="<?php echo ($voice_enabled && $voice_provider === 'n8n-retell') ? '' : 'display:none;'; ?>">
            <h2><?php _e('n8n Integration Configuration', 'antek-chat-connector'); ?></h2>
            <p class="description">
                <?php _e('Configure n8n webhooks that proxy to Retell AI. Your n8n workflows must return genuine Retell access tokens for voice calls.', 'antek-chat-connector'); ?>
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="n8n_base_url"><?php _e('n8n Base URL', 'antek-chat-connector'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="url"
                               id="n8n_base_url"
                               name="antek_chat_voice_settings[n8n_base_url]"
                               value="<?php echo esc_attr($settings['n8n_base_url'] ?? ''); ?>"
                               class="regular-text"
                               placeholder="https://n8n-instance.com">
                        <p class="description">
                            <?php _e('Your n8n instance URL (without trailing slash)', 'antek-chat-connector'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="n8n_voice_endpoint"><?php _e('Voice Call Endpoint', 'antek-chat-connector'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text"
                               id="n8n_voice_endpoint"
                               name="antek_chat_voice_settings[n8n_voice_endpoint]"
                               value="<?php echo esc_attr($settings['n8n_voice_endpoint'] ?? '/webhook/wordpress-retell-create-call'); ?>"
                               class="regular-text"
                               placeholder="/webhook/wordpress-retell-create-call">
                        <p class="description">
                            <?php _e('n8n webhook path for creating voice calls', 'antek-chat-connector'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="n8n_retell_agent_id"><?php _e('Voice Agent ID', 'antek-chat-connector'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text"
                               id="n8n_retell_agent_id"
                               name="antek_chat_voice_settings[n8n_retell_agent_id]"
                               value="<?php echo esc_attr($settings['n8n_retell_agent_id'] ?? ''); ?>"
                               class="regular-text"
                               placeholder="agent_xxxxxxxx">
                        <p class="description">
                            <?php _e('Retell Agent ID for voice calls (passed to n8n)', 'antek-chat-connector'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="n8n_text_mode"><?php _e('Text Chat Mode', 'antek-chat-connector'); ?></label>
                    </th>
                    <td>
                        <select id="n8n_text_mode" name="antek_chat_voice_settings[n8n_text_mode]" class="regular-text">
                            <option value="simple" <?php selected($settings['n8n_text_mode'] ?? 'simple', 'simple'); ?>>
                                <?php _e('Simple Webhook', 'antek-chat-connector'); ?>
                            </option>
                            <option value="session" <?php selected($settings['n8n_text_mode'] ?? 'simple', 'session'); ?>>
                                <?php _e('Session-Based', 'antek-chat-connector'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php _e('Simple: Use webhook from Connection settings. Session-Based: Create chat sessions with separate endpoints.', 'antek-chat-connector'); ?>
                        </p>
                    </td>
                </tr>

                <!-- Session-based fields (shown when session mode selected) -->
                <tr class="n8n-session-field" style="<?php echo (isset($settings['n8n_text_mode']) && $settings['n8n_text_mode'] === 'session') ? '' : 'display:none;'; ?>">
                    <th scope="row">
                        <label for="n8n_text_session_endpoint"><?php _e('Create Session Endpoint', 'antek-chat-connector'); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               id="n8n_text_session_endpoint"
                               name="antek_chat_voice_settings[n8n_text_session_endpoint]"
                               value="<?php echo esc_attr($settings['n8n_text_session_endpoint'] ?? '/webhook/retell-create-chat-session'); ?>"
                               class="regular-text"
                               placeholder="/webhook/retell-create-chat-session">
                        <p class="description">
                            <?php _e('n8n webhook path for creating text chat sessions', 'antek-chat-connector'); ?>
                        </p>
                    </td>
                </tr>

                <tr class="n8n-session-field" style="<?php echo (isset($settings['n8n_text_mode']) && $settings['n8n_text_mode'] === 'session') ? '' : 'display:none;'; ?>">
                    <th scope="row">
                        <label for="n8n_text_message_endpoint"><?php _e('Send Message Endpoint', 'antek-chat-connector'); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               id="n8n_text_message_endpoint"
                               name="antek_chat_voice_settings[n8n_text_message_endpoint]"
                               value="<?php echo esc_attr($settings['n8n_text_message_endpoint'] ?? '/webhook/retell-send-message'); ?>"
                               class="regular-text"
                               placeholder="/webhook/retell-send-message">
                        <p class="description">
                            <?php _e('n8n webhook path for sending text messages', 'antek-chat-connector'); ?>
                        </p>
                    </td>
                </tr>

                <tr class="n8n-session-field" style="<?php echo (isset($settings['n8n_text_mode']) && $settings['n8n_text_mode'] === 'session') ? '' : 'display:none;'; ?>">
                    <th scope="row">
                        <label for="n8n_retell_text_agent_id">
                            <?php _e('Text Chat Agent ID', 'antek-chat-connector'); ?>
                            <span style="color: #666;">(<?php _e('Optional', 'antek-chat-connector'); ?>)</span>
                        </label>
                    </th>
                    <td>
                        <input type="text"
                               id="n8n_retell_text_agent_id"
                               name="antek_chat_voice_settings[n8n_retell_text_agent_id]"
                               value="<?php echo esc_attr($settings['n8n_retell_text_agent_id'] ?? ''); ?>"
                               class="regular-text"
                               placeholder="agent_xxxxxxxx (optional)">
                        <p class="description">
                            <?php _e('Optional: Use a different agent for text chat. If empty, will use Voice Agent ID above.', 'antek-chat-connector'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <div class="antek-provider-status" id="n8n-retell-status"></div>
        </div>

        <!-- Test Connection Button -->
        <div class="antek-section" id="test-connection-section" style="<?php echo $voice_enabled ? '' : 'display:none;'; ?>">
            <p>
                <button type="button" id="test-connection-btn" class="button button-secondary">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Test Connection', 'antek-chat-connector'); ?>
                </button>
                <span id="test-connection-result"></span>
            </p>
        </div>

        <?php submit_button(__('Save Voice Settings', 'antek-chat-connector')); ?>
    </form>
</div>

<style>
.antek-chat-settings .antek-section {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.antek-chat-settings .antek-section h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #ddd;
}

.antek-toggle {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.antek-toggle input {
    opacity: 0;
    width: 0;
    height: 0;
}

.antek-toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 24px;
}

.antek-toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

.antek-toggle input:checked + .antek-toggle-slider {
    background-color: #2271b1;
}

.antek-toggle input:checked + .antek-toggle-slider:before {
    transform: translateX(26px);
}

.antek-comparison-table {
    margin-top: 15px;
}

.antek-comparison-table th {
    background: #f0f0f1;
    font-weight: 600;
}

.antek-status.encrypted {
    color: #00a32a;
}

.antek-status.encrypted .dashicons {
    color: #00a32a;
}

.required {
    color: #d63638;
}

#test-connection-result.success {
    color: #00a32a;
    margin-left: 10px;
}

#test-connection-result.error {
    color: #d63638;
    margin-left: 10px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle provider settings visibility based on voice enabled and provider selection
    function updateProviderVisibility() {
        const voiceEnabled = $('#voice_enabled').is(':checked');
        const provider = $('input[name="antek_chat_voice_settings[voice_provider]"]:checked').val();

        // Show/hide provider selection row
        if (voiceEnabled) {
            $('#provider-selection-row, #test-connection-section').show();
        } else {
            $('#provider-selection-row, #test-connection-section').hide();
        }

        // Show/hide provider-specific settings
        $('.provider-settings').hide();
        if (voiceEnabled) {
            if (provider === 'retell') {
                $('#retell-settings').show();
            } else if (provider === 'n8n-retell') {
                $('#n8n-retell-settings').show();
            }
        }
    }

    // Toggle n8n session-based fields visibility
    function updateN8nTextMode() {
        const textMode = $('#n8n_text_mode').val();
        if (textMode === 'session') {
            $('.n8n-session-field').show();
        } else {
            $('.n8n-session-field').hide();
        }
    }

    $('#voice_enabled').on('change', updateProviderVisibility);
    $('input[name="antek_chat_voice_settings[voice_provider]"]').on('change', updateProviderVisibility);
    $('#n8n_text_mode').on('change', updateN8nTextMode);

    // Initialize visibility
    updateProviderVisibility();
    updateN8nTextMode();

    // Test connection
    $('#test-connection-btn').on('click', function() {
        const $btn = $(this);
        const $result = $('#test-connection-result');
        const provider = 'retell'; // Only Retell is supported

        $btn.prop('disabled', true);
        $result.removeClass('success error').text('<?php esc_html_e('Testing...', 'antek-chat-connector'); ?>');

        $.ajax({
            url: '<?php echo esc_url(rest_url('antek-chat/v1/providers/')); ?>' + provider + '/test',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function(response) {
                $result.addClass('success').html('<span class="dashicons dashicons-yes"></span> ' + response.message);
            },
            error: function(xhr) {
                const response = xhr.responseJSON || {};
                $result.addClass('error').html('<span class="dashicons dashicons-no"></span> ' + (response.message || '<?php esc_html_e('Connection failed', 'antek-chat-connector'); ?>'));
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });
});
</script>
