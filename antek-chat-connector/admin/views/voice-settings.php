<?php
/**
 * Voice Settings View
 *
 * @package Antek_Chat_Connector
 * @since 1.2.1
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('antek_chat_voice', array(
    'enabled' => false,
    'n8n_voice_token_url' => '',
    'retell_agent_id' => '',
));
?>

<div class="antek-settings-section">
    <div class="antek-info-box">
        <h3>🎙️ Retell Voice Integration</h3>
        <p>Enable voice calls using Retell AI. This uses WebRTC for real-time voice streaming.</p>
        <p><strong>Important:</strong> Voice requires HTTPS and uses n8n only for secure token generation. The actual voice connection goes directly to Retell's servers.</p>
    </div>

    <form method="post" action="options.php">
        <?php settings_fields('antek_chat_voice'); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="enabled">Enable Voice Features</label>
                </th>
                <td>
                    <input type="checkbox"
                           name="antek_chat_voice[enabled]"
                           id="enabled"
                           value="1"
                           <?php checked($settings['enabled'], 1); ?>>
                    <label for="enabled">Show microphone button for voice calls</label>
                    <p class="description">⚠️ Requires HTTPS to work</p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="retell_agent_id">Retell Voice Agent ID</label>
                </th>
                <td>
                    <input type="text"
                           name="antek_chat_voice[retell_agent_id]"
                           id="retell_agent_id"
                           value="<?php echo esc_attr($settings['retell_agent_id']); ?>"
                           class="regular-text"
                           placeholder="agent_xxxxxxxxxxxxx">
                    <p class="description">Your Retell AI agent ID configured for voice calls</p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="n8n_voice_token_url">n8n Voice Token URL</label>
                </th>
                <td>
                    <input type="url"
                           name="antek_chat_voice[n8n_voice_token_url]"
                           id="n8n_voice_token_url"
                           value="<?php echo esc_attr($settings['n8n_voice_token_url']); ?>"
                           class="large-text"
                           placeholder="https://your-n8n.app/webhook/retell-voice-token">
                    <p class="description">n8n webhook that generates Retell access tokens</p>
                </td>
            </tr>
        </table>

        <div class="antek-setup-instructions">
            <h3>📝 Voice Setup Instructions</h3>

            <div class="antek-warning-box">
                <h4>⚠️ Understanding Voice Architecture</h4>
                <p>Voice calls work differently from text chat:</p>
                <ul>
                    <li><strong>Text Chat:</strong> WordPress → n8n → AI → Response (fully proxied)</li>
                    <li><strong>Voice:</strong>
                        <ul>
                            <li>Step 1: WordPress → n8n → Retell API (get token)</li>
                            <li>Step 2: Browser → Retell Servers (WebRTC audio stream)</li>
                        </ul>
                    </li>
                </ul>
                <p><strong>Why?</strong> Real-time audio requires WebRTC which cannot be proxied through n8n. n8n is only used to securely generate access tokens.</p>
            </div>

            <h4>Step 1: Create n8n Voice Token Workflow</h4>
            <pre>Webhook Trigger (POST) → HTTP Request Node → Respond to Webhook

HTTP Request Configuration:
- Method: POST
- URL: https://api.retellai.com/v2/create-web-call
- Headers:
  - Authorization: Bearer YOUR_RETELL_API_KEY
  - Content-Type: application/json
- Body:
  {
    "agent_id": "{{$json.body.agent_id}}",
    "metadata": {{$json.body.metadata}}
  }

Response Format:
{
  "success": true,
  "access_token": "{{$json.access_token}}",
  "call_id": "{{$json.call_id}}",
  "agent_id": "{{$json.agent_id}}",
  "call_status": "{{$json.call_status}}"
}</pre>

            <h4>Step 2: Import Pre-Built Workflow</h4>
            <p>You can use the provided workflow:</p>
            <ul>
                <li><code>WordPress Retell AI - Create Call.json</code></li>
            </ul>
            <p>Import into n8n and update the Retell API key.</p>

            <h4>Step 3: Requirements Checklist</h4>
            <ul>
                <li>✅ Site has valid HTTPS certificate</li>
                <li>✅ Retell AI account with API key</li>
                <li>✅ Retell agent configured for voice</li>
                <li>✅ n8n workflow activated and tested</li>
                <li>✅ Webhook URL copied to field above</li>
            </ul>

            <h4>Step 4: Test Voice Connection</h4>
            <button type="button" class="button button-primary" id="test-voice">Test Voice Setup</button>
            <span id="test-result"></span>

            <div id="voice-test-details" style="display:none; margin-top: 20px;">
                <h5>Test Results:</h5>
                <ul id="test-steps"></ul>
            </div>
        </div>

        <?php submit_button(); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $('#test-voice').on('click', function() {
        const tokenUrl = $('#n8n_voice_token_url').val();
        const agentId = $('#retell_agent_id').val();

        $('#test-steps').empty();
        $('#voice-test-details').show();

        function addStep(message, status) {
            const icon = status === 'success' ? '✅' : status === 'error' ? '❌' : '⏳';
            $('#test-steps').append('<li>' + icon + ' ' + message + '</li>');
        }

        if (!tokenUrl || !agentId) {
            addStep('Missing configuration', 'error');
            $('#test-result').html('<span style="color:red;">⚠️ Please enter both Agent ID and Token URL</span>');
            return;
        }

        $(this).prop('disabled', true).text('Testing...');
        addStep('Testing HTTPS requirement...', 'loading');

        if (window.location.protocol !== 'https:') {
            addStep('HTTPS check failed - site must use HTTPS for voice', 'error');
            $('#test-result').html('<span style="color:red;">❌ Voice requires HTTPS</span>');
            $(this).prop('disabled', false).text('Test Voice Setup');
            return;
        }
        addStep('HTTPS verified', 'success');

        addStep('Requesting access token from n8n...', 'loading');

        $.ajax({
            url: tokenUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                agent_id: agentId,
                metadata: {
                    test: true,
                    source: 'wordpress_admin'
                }
            }),
            timeout: 10000,
            success: function(response) {
                if (response && response.success && response.access_token) {
                    addStep('Token received successfully', 'success');
                    addStep('Call ID: ' + response.call_id, 'success');
                    addStep('Agent ID: ' + response.agent_id, 'success');

                    $('#test-result').html('<span style="color:green;">✅ Voice setup working! Try the microphone button on frontend.</span>');

                    // Check if Retell SDK would load
                    addStep('Checking Retell SDK availability...', 'loading');
                    const sdkTest = new Image();
                    sdkTest.onerror = function() {
                        addStep('Retell SDK CDN accessible', 'success');
                    };
                    sdkTest.src = 'https://cdn.jsdelivr.net/npm/retell-client-js-sdk@2.3.0/dist/retell-client-js-sdk.min.js';

                } else {
                    addStep('Token response invalid', 'error');
                    $('#test-result').html('<span style="color:orange;">⚠️ Token received but format unexpected. Check n8n workflow response format.</span>');
                }
            },
            error: function(xhr, status, error) {
                addStep('Token request failed: ' + error, 'error');
                $('#test-result').html('<span style="color:red;">❌ Failed to get token. Check n8n workflow is activated and URL is correct.</span>');
            },
            complete: function() {
                $('#test-voice').prop('disabled', false).text('Test Voice Setup');
            }
        });
    });
});
</script>

<style>
.antek-settings-section { max-width: 900px; }
.antek-info-box {
    background: #e7f3ff;
    border-left: 4px solid #2271b1;
    padding: 15px;
    margin-bottom: 20px;
}
.antek-warning-box {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 15px;
    margin: 20px 0;
}
.antek-warning-box h4 {
    margin-top: 0;
}
.antek-setup-instructions {
    background: #f9f9f9;
    border: 1px solid #ddd;
    padding: 20px;
    margin-top: 20px;
}
.antek-setup-instructions h4 {
    margin-top: 20px;
    margin-bottom: 10px;
}
.antek-setup-instructions pre {
    background: #fff;
    padding: 10px;
    border: 1px solid #ddd;
    overflow-x: auto;
    font-size: 12px;
    line-height: 1.5;
}
#voice-test-details {
    background: #fff;
    border: 1px solid #ddd;
    padding: 15px;
    border-radius: 4px;
}
#test-steps li {
    margin: 5px 0;
    font-family: monospace;
}
</style>
