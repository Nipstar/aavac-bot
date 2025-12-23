<?php
/**
 * Retell Text Chat Settings View
 *
 * @package Antek_Chat_Connector
 * @since 1.2.1
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('antek_chat_retell_text', array(
    'enabled' => false,
    'n8n_create_session_url' => '',
    'n8n_send_message_url' => '',
    'retell_agent_id' => '',
));
?>

<div class="antek-settings-section">
    <div class="antek-info-box">
        <h3>💬 Retell Text Chat Integration</h3>
        <p>Use Retell AI's conversational platform for text chat. This requires two n8n workflows to handle session creation and message sending.</p>
        <p><strong>Note:</strong> This is different from Retell Voice. Text chat uses Retell's chat API, not voice SDK.</p>
    </div>

    <form method="post" action="options.php">
        <?php settings_fields('antek_chat_retell_text'); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="enabled">Enable Retell Text Chat</label>
                </th>
                <td>
                    <input type="checkbox"
                           name="antek_chat_retell_text[enabled]"
                           id="enabled"
                           value="1"
                           <?php checked($settings['enabled'], 1); ?>>
                    <label for="enabled">Use Retell AI for text chat responses</label>
                    <p class="description">Enable this to use Retell Text Chat mode in Connection Settings</p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="retell_agent_id">Retell Agent ID</label>
                </th>
                <td>
                    <input type="text"
                           name="antek_chat_retell_text[retell_agent_id]"
                           id="retell_agent_id"
                           value="<?php echo esc_attr($settings['retell_agent_id']); ?>"
                           class="regular-text"
                           placeholder="agent_xxxxxxxxxxxxx">
                    <p class="description">Your Retell AI agent ID for text chat</p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="n8n_create_session_url">n8n Create Session URL</label>
                </th>
                <td>
                    <input type="url"
                           name="antek_chat_retell_text[n8n_create_session_url]"
                           id="n8n_create_session_url"
                           value="<?php echo esc_attr($settings['n8n_create_session_url']); ?>"
                           class="large-text"
                           placeholder="https://your-n8n.app/webhook/retell-create-session">
                    <p class="description">n8n webhook that calls Retell's create-chat endpoint</p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="n8n_send_message_url">n8n Send Message URL</label>
                </th>
                <td>
                    <input type="url"
                           name="antek_chat_retell_text[n8n_send_message_url]"
                           id="n8n_send_message_url"
                           value="<?php echo esc_attr($settings['n8n_send_message_url']); ?>"
                           class="large-text"
                           placeholder="https://your-n8n.app/webhook/retell-send-message">
                    <p class="description">n8n webhook that calls Retell's create-chat-completion endpoint</p>
                </td>
            </tr>
        </table>

        <div class="antek-setup-instructions">
            <h3>📝 Setup Instructions</h3>

            <h4>Step 1: Get Retell API Key</h4>
            <ol>
                <li>Go to <a href="https://beta.retellai.com/" target="_blank">Retell AI Dashboard</a></li>
                <li>Navigate to Settings → API Keys</li>
                <li>Create a new API key and copy it</li>
                <li>Find your Agent ID from the Agents page</li>
            </ol>

            <h4>Step 2: Create n8n Workflow #1 - Create Session</h4>
            <p>This workflow initializes a new chat session with Retell:</p>
            <pre>Webhook Trigger (POST) → HTTP Request Node → Respond to Webhook

HTTP Request Configuration:
- Method: POST
- URL: https://api.retellai.com/create-chat
- Headers:
  - Authorization: Bearer YOUR_RETELL_API_KEY
  - Content-Type: application/json
- Body: {} (empty object)

Response Format:
{
  "success": true,
  "chat_id": "{{$json.chat_id}}",
  "agent_id": "{{$json.agent_id}}",
  "chat_status": "{{$json.chat_status}}"
}</pre>

            <h4>Step 3: Create n8n Workflow #2 - Send Message</h4>
            <p>This workflow sends user messages and gets AI responses:</p>
            <pre>Webhook Trigger (POST) → HTTP Request Node → Code Node → Respond to Webhook

HTTP Request Configuration:
- Method: POST
- URL: https://api.retellai.com/create-chat-completion
- Headers:
  - Authorization: Bearer YOUR_RETELL_API_KEY
  - Content-Type: application/json
- Body:
  {
    "chat_id": "{{$json.body.chat_id}}",
    "user_input": "{{$json.body.message}}"
  }

Code Node (Format Response):
const response = $input.first().json;
const agentMessages = response.messages.filter(msg => msg.role === 'agent');
const lastMessage = agentMessages[agentMessages.length - 1];

return [{
  success: true,
  response: lastMessage?.content || 'No response',
  message_id: lastMessage?.message_id,
  timestamp: lastMessage?.created_timestamp
}];

Response to Webhook: Return the formatted response</pre>

            <h4>Step 4: Import Pre-Built Workflows</h4>
            <p>You can use the workflow JSON files provided:</p>
            <ul>
                <li><code>Retell Text Chat - Create Session.json</code></li>
                <li><code>Retell Text Chat - Send Message.json</code></li>
            </ul>
            <p>Import these into n8n and update the Retell API key.</p>

            <h4>Step 5: Test Connection</h4>
            <button type="button" class="button" id="test-retell-text">Test Retell Text Chat</button>
            <span id="test-result"></span>
        </div>

        <?php submit_button(); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $('#test-retell-text').on('click', function() {
        const createUrl = $('#n8n_create_session_url').val();
        const sendUrl = $('#n8n_send_message_url').val();
        const agentId = $('#retell_agent_id').val();

        if (!createUrl || !sendUrl) {
            $('#test-result').html('<span style="color:red;">⚠️ Please enter both webhook URLs</span>');
            return;
        }

        if (!agentId) {
            $('#test-result').html('<span style="color:red;">⚠️ Please enter Retell Agent ID</span>');
            return;
        }

        $(this).prop('disabled', true).text('Testing...');
        $('#test-result').html('<span style="color:blue;">⏳ Step 1: Creating session...</span>');

        // Step 1: Create session
        $.ajax({
            url: createUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                agent_id: agentId,
                user_id: 1,
                session_id: 'test_' + Date.now()
            }),
            timeout: 10000,
            success: function(sessionResponse) {
                if (sessionResponse && sessionResponse.success && sessionResponse.chat_id) {
                    $('#test-result').html('<span style="color:blue;">⏳ Step 2: Sending test message...</span>');

                    // Step 2: Send message
                    $.ajax({
                        url: sendUrl,
                        method: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({
                            chat_id: sessionResponse.chat_id,
                            message: 'Hello, this is a test'
                        }),
                        timeout: 15000,
                        success: function(messageResponse) {
                            if (messageResponse && messageResponse.success && messageResponse.response) {
                                $('#test-result').html('<span style="color:green;">✅ Success! Response: ' + messageResponse.response + '</span>');
                            } else {
                                $('#test-result').html('<span style="color:orange;">⚠️ Message sent but response format unexpected</span>');
                            }
                        },
                        error: function(xhr, status, error) {
                            $('#test-result').html('<span style="color:red;">❌ Step 2 failed: ' + error + '</span>');
                        },
                        complete: function() {
                            $('#test-retell-text').prop('disabled', false).text('Test Retell Text Chat');
                        }
                    });
                } else {
                    $('#test-result').html('<span style="color:orange;">⚠️ Session created but format unexpected</span>');
                    $('#test-retell-text').prop('disabled', false).text('Test Retell Text Chat');
                }
            },
            error: function(xhr, status, error) {
                $('#test-result').html('<span style="color:red;">❌ Step 1 failed: ' + error + '</span>');
                $('#test-retell-text').prop('disabled', false).text('Test Retell Text Chat');
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
</style>
