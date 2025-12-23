<?php
/**
 * n8n Connection Settings View
 *
 * @package Antek_Chat_Connector
 * @since 1.2.1
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('antek_chat_connection', array(
    'chat_mode' => 'n8n',
    'n8n_webhook_url' => '',
    'widget_enabled' => true,
));
?>

<div class="antek-settings-section">
    <div class="antek-info-box">
        <h3>🔗 n8n Webhook Integration</h3>
        <p>Connect to your n8n workflow for AI-powered text chat. This allows you to use any AI model (OpenAI, Claude, Gemini, etc.) through n8n.</p>
    </div>

    <form method="post" action="options.php">
        <?php settings_fields('antek_chat_connection'); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="widget_enabled">Enable Chat Widget</label>
                </th>
                <td>
                    <input type="checkbox"
                           name="antek_chat_connection[widget_enabled]"
                           id="widget_enabled"
                           value="1"
                           <?php checked($settings['widget_enabled'], 1); ?>>
                    <label for="widget_enabled">Show chat widget on frontend</label>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="chat_mode">Text Chat Mode</label>
                </th>
                <td>
                    <select name="antek_chat_connection[chat_mode]" id="chat_mode">
                        <option value="n8n" <?php selected($settings['chat_mode'], 'n8n'); ?>>
                            n8n Webhook (General AI)
                        </option>
                        <option value="retell" <?php selected($settings['chat_mode'], 'retell'); ?>>
                            Retell Text Chat (Configure in Retell Text Chat tab)
                        </option>
                    </select>
                    <p class="description">Choose how text messages are processed</p>
                </td>
            </tr>

            <tr id="n8n-webhook-row" style="<?php echo $settings['chat_mode'] === 'retell' ? 'display:none;' : ''; ?>">
                <th scope="row">
                    <label for="n8n_webhook_url">n8n Webhook URL</label>
                </th>
                <td>
                    <input type="url"
                           name="antek_chat_connection[n8n_webhook_url]"
                           id="n8n_webhook_url"
                           value="<?php echo esc_attr($settings['n8n_webhook_url']); ?>"
                           class="large-text"
                           placeholder="https://your-n8n.app/webhook/chat">
                    <p class="description">Your n8n webhook URL that processes chat messages</p>
                </td>
            </tr>
        </table>

        <div class="antek-setup-instructions">
            <h3>📝 n8n Workflow Setup</h3>
            <ol>
                <li><strong>Create n8n Workflow:</strong>
                    <ul>
                        <li>Add "Webhook" trigger node (POST method)</li>
                        <li>Add your AI node (OpenAI, Claude, etc.)</li>
                        <li>Add "Respond to Webhook" node</li>
                    </ul>
                </li>
                <li><strong>Webhook Configuration:</strong>
                    <pre>Expected Input:
{
  "message": "user message here",
  "session_id": "unique-session-id",
  "metadata": {...}
}

Required Response:
{
  "response": "AI response here"
}</pre>
                </li>
                <li><strong>Test Connection:</strong>
                    <button type="button" class="button" id="test-n8n-webhook">Test Webhook</button>
                    <span id="test-result"></span>
                </li>
            </ol>
        </div>

        <?php submit_button(); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Show/hide n8n fields based on mode
    $('#chat_mode').on('change', function() {
        if ($(this).val() === 'n8n') {
            $('#n8n-webhook-row').show();
        } else {
            $('#n8n-webhook-row').hide();
        }
    });

    // Test webhook
    $('#test-n8n-webhook').on('click', function() {
        const url = $('#n8n_webhook_url').val();
        if (!url) {
            $('#test-result').html('<span style="color:red;">⚠️ Please enter webhook URL</span>');
            return;
        }

        $(this).prop('disabled', true).text('Testing...');
        $('#test-result').html('<span style="color:blue;">⏳ Testing connection...</span>');

        $.ajax({
            url: url,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                message: 'Test message from AAVAC Bot',
                session_id: 'test-session',
                metadata: { test: true }
            }),
            timeout: 10000,
            success: function(response) {
                if (response && response.response) {
                    $('#test-result').html('<span style="color:green;">✅ Success! Response: ' + response.response + '</span>');
                } else {
                    $('#test-result').html('<span style="color:orange;">⚠️ Webhook responded but format unexpected</span>');
                }
            },
            error: function(xhr, status, error) {
                $('#test-result').html('<span style="color:red;">❌ Failed: ' + error + '</span>');
            },
            complete: function() {
                $('#test-n8n-webhook').prop('disabled', false).text('Test Webhook');
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
.antek-setup-instructions pre {
    background: #fff;
    padding: 10px;
    border: 1px solid #ddd;
    overflow-x: auto;
}
</style>
