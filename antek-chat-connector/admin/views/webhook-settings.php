<?php
/**
 * Webhook Settings View
 *
 * Admin interface for webhook authentication and testing
 *
 * @package Antek_Chat_Connector
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$settings = get_option('antek_chat_automation_settings', []);
$webhook_auth_method = isset($settings['webhook_auth_method']) ? $settings['webhook_auth_method'] : 'api_key';

// Get webhook URL
$webhook_url = rest_url('antek-chat/v1/webhook');

// Get recent webhook events
global $wpdb;
$webhooks_table = $wpdb->prefix . 'antek_chat_webhooks';
$recent_webhooks = $wpdb->get_results(
    "SELECT * FROM $webhooks_table ORDER BY created_at DESC LIMIT 50",
    ARRAY_A
);
?>

<div class="wrap antek-chat-settings">
    <h1><?php _e('AAVAC Bot - Webhook Settings', 'antek-chat-connector'); ?></h1>

    <div class="antek-settings-header">
        <p class="description">
            <?php _e('Configure webhook authentication for incoming events from voice providers and automation platforms.', 'antek-chat-connector'); ?>
        </p>
    </div>

    <form method="post" action="options.php" id="webhook-settings-form">
        <?php settings_fields('antek_chat_automation_settings'); ?>

        <!-- Webhook Endpoint URL -->
        <div class="antek-section">
            <h2><?php _e('Webhook Endpoint', 'antek-chat-connector'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="webhook_url"><?php _e('Webhook URL', 'antek-chat-connector'); ?></label>
                    </th>
                    <td>
                        <div class="antek-url-display">
                            <input type="text"
                                   id="webhook_url"
                                   value="<?php echo esc_url($webhook_url); ?>"
                                   readonly
                                   class="large-text code">
                            <button type="button" id="copy-webhook-url" class="button button-secondary">
                                <span class="dashicons dashicons-clipboard"></span>
                                <?php _e('Copy', 'antek-chat-connector'); ?>
                            </button>
                        </div>
                        <p class="description">
                            <?php _e('Use this URL in your voice provider or automation platform webhook configuration.', 'antek-chat-connector'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Authentication Method -->
        <div class="antek-section">
            <h2><?php _e('Webhook Authentication', 'antek-chat-connector'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="webhook_auth_method"><?php _e('Authentication Method', 'antek-chat-connector'); ?></label>
                    </th>
                    <td>
                        <select id="webhook_auth_method"
                                name="antek_chat_automation_settings[webhook_auth_method]"
                                class="regular-text">
                            <option value="api_key" <?php selected($webhook_auth_method, 'api_key'); ?>>
                                <?php _e('API Key (X-API-Key header)', 'antek-chat-connector'); ?>
                            </option>
                            <option value="hmac" <?php selected($webhook_auth_method, 'hmac'); ?>>
                                <?php _e('HMAC-SHA256 (X-Webhook-Signature)', 'antek-chat-connector'); ?>
                            </option>
                            <option value="basic" <?php selected($webhook_auth_method, 'basic'); ?>>
                                <?php _e('Basic Auth (Authorization header)', 'antek-chat-connector'); ?>
                            </option>
                            <option value="none" <?php selected($webhook_auth_method, 'none'); ?>>
                                <?php _e('None (Development Only)', 'antek-chat-connector'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php _e('Choose how incoming webhooks will be authenticated.', 'antek-chat-connector'); ?>
                        </p>
                        <?php if ($webhook_auth_method === 'none'): ?>
                            <p class="antek-warning">
                                <span class="dashicons dashicons-warning"></span>
                                <?php _e('WARNING: No authentication is insecure. Use only for development!', 'antek-chat-connector'); ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- API Key Settings -->
        <div class="antek-section auth-section" id="auth-api-key" style="<?php echo ($webhook_auth_method === 'api_key') ? '' : 'display:none;'; ?>">
            <h3><?php _e('API Key Configuration', 'antek-chat-connector'); ?></h3>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="webhook_api_key"><?php _e('API Key', 'antek-chat-connector'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <div class="antek-key-input">
                            <input type="password"
                                   id="webhook_api_key"
                                   name="antek_chat_automation_settings[webhook_api_key]"
                                   value="<?php echo esc_attr($settings['webhook_api_key'] ?? ''); ?>"
                                   class="regular-text"
                                   placeholder="<?php esc_attr_e('Enter or generate API key', 'antek-chat-connector'); ?>">
                            <button type="button" id="generate-api-key" class="button button-secondary">
                                <span class="dashicons dashicons-randomize"></span>
                                <?php _e('Generate', 'antek-chat-connector'); ?>
                            </button>
                        </div>
                        <p class="description">
                            <?php _e('Sender must include this key in X-API-Key header.', 'antek-chat-connector'); ?>
                        </p>
                        <?php if (!empty($settings['webhook_api_key'])): ?>
                            <p class="antek-status encrypted">
                                <span class="dashicons dashicons-lock"></span>
                                <?php _e('API key is encrypted in database', 'antek-chat-connector'); ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Example Header', 'antek-chat-connector'); ?></th>
                    <td>
                        <code class="antek-code-block">X-API-Key: your-generated-key-here</code>
                    </td>
                </tr>
            </table>
        </div>

        <!-- HMAC Settings -->
        <div class="antek-section auth-section" id="auth-hmac" style="<?php echo ($webhook_auth_method === 'hmac') ? '' : 'display:none;'; ?>">
            <h3><?php _e('HMAC-SHA256 Configuration', 'antek-chat-connector'); ?></h3>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="webhook_secret"><?php _e('Webhook Secret', 'antek-chat-connector'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <div class="antek-key-input">
                            <input type="password"
                                   id="webhook_secret"
                                   name="antek_chat_automation_settings[webhook_secret]"
                                   value="<?php echo esc_attr($settings['webhook_secret'] ?? ''); ?>"
                                   class="regular-text"
                                   placeholder="<?php esc_attr_e('Enter or generate secret', 'antek-chat-connector'); ?>">
                            <button type="button" id="generate-secret" class="button button-secondary">
                                <span class="dashicons dashicons-randomize"></span>
                                <?php _e('Generate', 'antek-chat-connector'); ?>
                            </button>
                        </div>
                        <p class="description">
                            <?php _e('Shared secret for HMAC signature verification.', 'antek-chat-connector'); ?>
                        </p>
                        <?php if (!empty($settings['webhook_secret'])): ?>
                            <p class="antek-status encrypted">
                                <span class="dashicons dashicons-lock"></span>
                                <?php _e('Secret is encrypted in database', 'antek-chat-connector'); ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Signature Calculation', 'antek-chat-connector'); ?></th>
                    <td>
                        <code class="antek-code-block">
                            signature = hmac_sha256(request_body, webhook_secret)<br>
                            X-Webhook-Signature: {signature}
                        </code>
                        <p class="description">
                            <?php _e('Sender must calculate HMAC-SHA256 of the entire request body using the shared secret.', 'antek-chat-connector'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Basic Auth Settings -->
        <div class="antek-section auth-section" id="auth-basic" style="<?php echo ($webhook_auth_method === 'basic') ? '' : 'display:none;'; ?>">
            <h3><?php _e('Basic Authentication Configuration', 'antek-chat-connector'); ?></h3>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="webhook_basic_username"><?php _e('Username', 'antek-chat-connector'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text"
                               id="webhook_basic_username"
                               name="antek_chat_automation_settings[webhook_basic_username]"
                               value="<?php echo esc_attr($settings['webhook_basic_username'] ?? ''); ?>"
                               class="regular-text"
                               autocomplete="off">
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="webhook_basic_password"><?php _e('Password', 'antek-chat-connector'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="password"
                               id="webhook_basic_password"
                               name="antek_chat_automation_settings[webhook_basic_password]"
                               value="<?php echo esc_attr($settings['webhook_basic_password'] ?? ''); ?>"
                               class="regular-text"
                               autocomplete="new-password">
                        <?php if (!empty($settings['webhook_basic_password'])): ?>
                            <p class="antek-status encrypted">
                                <span class="dashicons dashicons-lock"></span>
                                <?php _e('Password is encrypted in database', 'antek-chat-connector'); ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Example Header', 'antek-chat-connector'); ?></th>
                    <td>
                        <code class="antek-code-block">
                            Authorization: Basic base64(username:password)
                        </code>
                    </td>
                </tr>
            </table>
        </div>

        <!-- IP Whitelist -->
        <div class="antek-section">
            <h2><?php _e('IP Whitelist (Optional)', 'antek-chat-connector'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="webhook_ip_whitelist"><?php _e('Allowed IP Addresses', 'antek-chat-connector'); ?></label>
                    </th>
                    <td>
                        <textarea id="webhook_ip_whitelist"
                                  name="antek_chat_automation_settings[webhook_ip_whitelist]"
                                  rows="5"
                                  class="large-text code"
                                  placeholder="<?php esc_attr_e('192.168.1.0/24\n10.0.0.1\n1.2.3.4', 'antek-chat-connector'); ?>"><?php echo esc_textarea($settings['webhook_ip_whitelist'] ?? ''); ?></textarea>
                        <p class="description">
                            <?php _e('One IP address or CIDR range per line. Leave empty to allow all IPs.', 'antek-chat-connector'); ?><br>
                            <?php _e('Example: 192.168.1.0/24 allows 192.168.1.0 - 192.168.1.255', 'antek-chat-connector'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button(__('Save Webhook Settings', 'antek-chat-connector')); ?>
    </form>

    <!-- Test Webhook Panel -->
    <div class="antek-section antek-test-panel">
        <h2><?php _e('Test Webhook', 'antek-chat-connector'); ?></h2>

        <div class="antek-test-form">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="test_provider"><?php _e('Provider', 'antek-chat-connector'); ?></label>
                    </th>
                    <td>
                        <select id="test_provider" class="regular-text">
                            <option value="retell"><?php _e('Retell AI', 'antek-chat-connector'); ?></option>
                            <option value="elevenlabs"><?php _e('ElevenLabs', 'antek-chat-connector'); ?></option>
                            <option value="custom"><?php _e('Custom Payload', 'antek-chat-connector'); ?></option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="test_event_type"><?php _e('Event Type', 'antek-chat-connector'); ?></label>
                    </th>
                    <td>
                        <select id="test_event_type" class="regular-text">
                            <!-- Retell events -->
                            <optgroup label="<?php esc_attr_e('Retell AI', 'antek-chat-connector'); ?>" id="retell-events">
                                <option value="call_started"><?php _e('Call Started', 'antek-chat-connector'); ?></option>
                                <option value="call_ended"><?php _e('Call Ended', 'antek-chat-connector'); ?></option>
                            </optgroup>
                            <!-- ElevenLabs events -->
                            <optgroup label="<?php esc_attr_e('ElevenLabs', 'antek-chat-connector'); ?>" id="elevenlabs-events" style="display:none;">
                                <option value="conversation_initiation_metadata"><?php _e('Conversation Started', 'antek-chat-connector'); ?></option>
                                <option value="user_transcript"><?php _e('User Transcript', 'antek-chat-connector'); ?></option>
                            </optgroup>
                        </select>
                    </td>
                </tr>

                <tr id="custom-payload-row" style="display:none;">
                    <th scope="row">
                        <label for="test_payload"><?php _e('Custom Payload (JSON)', 'antek-chat-connector'); ?></label>
                    </th>
                    <td>
                        <textarea id="test_payload" rows="8" class="large-text code" placeholder='{"event": "test", "data": {}}'></textarea>
                    </td>
                </tr>
            </table>

            <p>
                <button type="button" id="send-test-webhook" class="button button-primary">
                    <span class="dashicons dashicons-megaphone"></span>
                    <?php _e('Send Test Webhook', 'antek-chat-connector'); ?>
                </button>
            </p>

            <div id="test-webhook-result"></div>
        </div>
    </div>

    <!-- Recent Webhook Events -->
    <div class="antek-section">
        <h2><?php _e('Recent Webhook Events', 'antek-chat-connector'); ?></h2>

        <?php if (empty($recent_webhooks)): ?>
            <p class="description"><?php _e('No webhook events received yet.', 'antek-chat-connector'); ?></p>
        <?php else: ?>
            <table class="widefat antek-webhooks-table">
                <thead>
                    <tr>
                        <th><?php _e('Time', 'antek-chat-connector'); ?></th>
                        <th><?php _e('Provider', 'antek-chat-connector'); ?></th>
                        <th><?php _e('Event', 'antek-chat-connector'); ?></th>
                        <th><?php _e('Auth', 'antek-chat-connector'); ?></th>
                        <th><?php _e('Status', 'antek-chat-connector'); ?></th>
                        <th><?php _e('Details', 'antek-chat-connector'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_webhooks as $webhook): ?>
                        <tr>
                            <td><?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($webhook['created_at']))); ?></td>
                            <td><?php echo esc_html($webhook['provider'] ?? 'N/A'); ?></td>
                            <td><code><?php echo esc_html($webhook['event_type'] ?? 'unknown'); ?></code></td>
                            <td>
                                <?php if ($webhook['verified']): ?>
                                    <span class="antek-badge success"><?php _e('Verified', 'antek-chat-connector'); ?></span>
                                <?php else: ?>
                                    <span class="antek-badge error"><?php _e('Failed', 'antek-chat-connector'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($webhook['processed']): ?>
                                    <span class="antek-badge success"><?php echo esc_html($webhook['response_status']); ?></span>
                                <?php else: ?>
                                    <span class="antek-badge warning"><?php _e('Pending', 'antek-chat-connector'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="button button-small view-webhook-details" data-id="<?php echo esc_attr($webhook['id']); ?>">
                                    <?php _e('View', 'antek-chat-connector'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<style>
.antek-url-display {
    display: flex;
    gap: 10px;
    align-items: center;
}

.antek-url-display input {
    flex: 1;
}

.antek-key-input {
    display: flex;
    gap: 10px;
    align-items: center;
}

.antek-key-input input {
    flex: 1;
}

.antek-code-block {
    display: block;
    background: #f6f7f7;
    padding: 10px;
    border-left: 3px solid #2271b1;
    font-family: monospace;
    font-size: 13px;
    line-height: 1.6;
}

.antek-warning {
    background: #fcf3cf;
    border-left: 4px solid #f39c12;
    padding: 10px 15px;
    margin-top: 10px;
}

.antek-warning .dashicons {
    color: #f39c12;
}

.antek-test-panel {
    background: #f9f9f9;
}

#test-webhook-result {
    margin-top: 15px;
    padding: 15px;
    border-radius: 4px;
    display: none;
}

#test-webhook-result.success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

#test-webhook-result.error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.antek-webhooks-table {
    margin-top: 15px;
}

.antek-webhooks-table th {
    background: #f0f0f1;
    font-weight: 600;
}

.antek-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
}

.antek-badge.success {
    background: #d4edda;
    color: #155724;
}

.antek-badge.error {
    background: #f8d7da;
    color: #721c24;
}

.antek-badge.warning {
    background: #fff3cd;
    color: #856404;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle auth sections based on method
    function updateAuthSections() {
        const method = $('#webhook_auth_method').val();
        $('.auth-section').hide();
        $('#auth-' + method).show();
    }

    $('#webhook_auth_method').on('change', updateAuthSections);
    updateAuthSections();

    // Copy webhook URL
    $('#copy-webhook-url').on('click', function() {
        const url = $('#webhook_url').val();
        navigator.clipboard.writeText(url).then(function() {
            const $btn = $('#copy-webhook-url');
            const originalText = $btn.html();
            $btn.html('<span class="dashicons dashicons-yes"></span> <?php esc_html_e('Copied!', 'antek-chat-connector'); ?>');
            setTimeout(function() {
                $btn.html(originalText);
            }, 2000);
        });
    });

    // Generate API key
    $('#generate-api-key').on('click', function() {
        const key = generateRandomKey(64);
        $('#webhook_api_key').val(key).attr('type', 'text');
        setTimeout(function() {
            $('#webhook_api_key').attr('type', 'password');
        }, 3000);
    });

    // Generate secret
    $('#generate-secret').on('click', function() {
        const secret = generateRandomKey(64);
        $('#webhook_secret').val(secret).attr('type', 'text');
        setTimeout(function() {
            $('#webhook_secret').attr('type', 'password');
        }, 3000);
    });

    // Generate random key
    function generateRandomKey(length) {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        let result = '';
        for (let i = 0; i < length; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return result;
    }

    // Test webhook provider selection
    $('#test_provider').on('change', function() {
        const provider = $(this).val();

        if (provider === 'custom') {
            $('#custom-payload-row').show();
            $('#test_event_type').closest('tr').hide();
        } else {
            $('#custom-payload-row').hide();
            $('#test_event_type').closest('tr').show();

            // Show appropriate event options
            $('optgroup').hide();
            $('#' + provider + '-events').show();
            $('#test_event_type').val($('#' + provider + '-events option:first').val());
        }
    });

    // Send test webhook
    $('#send-test-webhook').on('click', function() {
        const $btn = $(this);
        const $result = $('#test-webhook-result');
        const provider = $('#test_provider').val();
        const eventType = $('#test_event_type').val();
        let payload = null;

        if (provider === 'custom') {
            try {
                payload = JSON.parse($('#test_payload').val());
            } catch (e) {
                $result.removeClass('success').addClass('error').html('Invalid JSON payload').show();
                return;
            }
        }

        $btn.prop('disabled', true);
        $result.removeClass('success error').html('<?php esc_html_e('Sending...', 'antek-chat-connector'); ?>').show();

        $.ajax({
            url: '<?php echo esc_url(rest_url('antek-chat/v1/test-webhook')); ?>',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            data: JSON.stringify({
                provider: provider !== 'custom' ? provider : 'test',
                event_type: eventType,
                payload: payload
            }),
            contentType: 'application/json',
            success: function(response) {
                $result.addClass('success').html(
                    '<strong><?php esc_html_e('Success!', 'antek-chat-connector'); ?></strong><br>' +
                    response.message +
                    '<br><br><strong><?php esc_html_e('Normalized Event:', 'antek-chat-connector'); ?></strong><br>' +
                    '<pre>' + JSON.stringify(response.normalized_event, null, 2) + '</pre>'
                );
            },
            error: function(xhr) {
                const response = xhr.responseJSON || {};
                $result.addClass('error').html(
                    '<strong><?php esc_html_e('Error', 'antek-chat-connector'); ?></strong><br>' +
                    (response.message || '<?php esc_html_e('Test webhook failed', 'antek-chat-connector'); ?>')
                );
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    // View webhook details (placeholder - would show modal with full payload)
    $('.view-webhook-details').on('click', function() {
        alert('<?php esc_html_e('Webhook details modal - to be implemented', 'antek-chat-connector'); ?>');
    });
});
</script>
