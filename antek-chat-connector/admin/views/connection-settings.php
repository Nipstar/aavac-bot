<?php
/**
 * Connection Settings View
 *
 * @package Antek_Chat_Connector
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('antek_chat_settings', array());
?>

<form method="post" action="options.php">
    <?php
    settings_fields('antek_chat_settings');
    ?>

    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row">
                    <label for="use_voice_provider_for_chat"><?php esc_html_e('Text Chat Provider', 'antek-chat-connector'); ?></label>
                </th>
                <td>
                    <fieldset>
                        <label>
                            <input type="radio"
                                   name="antek_chat_settings[chat_provider]"
                                   id="use_voice_provider_for_chat"
                                   value="voice_provider"
                                   <?php checked(isset($settings['chat_provider']) && $settings['chat_provider'] !== 'n8n', true); ?>>
                            <?php esc_html_e('Use Voice Provider (same as voice chat)', 'antek-chat-connector'); ?>
                        </label>
                        <p class="description" style="margin-left: 25px;">
                            <?php esc_html_e('Text chat will use the provider configured in Voice Provider settings (Retell AI or ElevenLabs). Both voice and text use the same agent.', 'antek-chat-connector'); ?>
                        </p>
                        <br>
                        <label>
                            <input type="radio"
                                   name="antek_chat_settings[chat_provider]"
                                   id="use_n8n_for_chat"
                                   value="n8n"
                                   <?php checked(isset($settings['chat_provider']) ? $settings['chat_provider'] : 'n8n', 'n8n'); ?>>
                            <?php esc_html_e('Use n8n/Make/Zapier Webhook (custom workflow)', 'antek-chat-connector'); ?>
                        </label>
                        <p class="description" style="margin-left: 25px;">
                            <?php esc_html_e('Text chat will use your n8n webhook. Voice will still use the Voice Provider.', 'antek-chat-connector'); ?>
                        </p>
                    </fieldset>
                </td>
            </tr>

            <tr class="chat-provider-field n8n-field">
                <th scope="row">
                    <label for="n8n_webhook_url"><?php esc_html_e('n8n Webhook URL', 'antek-chat-connector'); ?></label>
                </th>
                <td>
                    <input type="url"
                           name="antek_chat_settings[n8n_webhook_url]"
                           id="n8n_webhook_url"
                           value="<?php echo esc_attr(isset($settings['n8n_webhook_url']) ? $settings['n8n_webhook_url'] : ''); ?>"
                           class="regular-text"
                           placeholder="https://your-n8n-instance.com/webhook/...">
                    <p class="description">
                        <?php esc_html_e('Your n8n webhook URL that will handle chat messages', 'antek-chat-connector'); ?>
                    </p>
                </td>
            </tr>

            <tr class="chat-provider-field voice_provider-field" style="display: none;">
                <th scope="row">
                    <?php esc_html_e('Voice Provider Settings', 'antek-chat-connector'); ?>
                </th>
                <td>
                    <p class="description">
                        <?php esc_html_e('Text chat will use the provider configured in the Voice Provider tab. Configure your API credentials and agent IDs there.', 'antek-chat-connector'); ?>
                    </p>
                    <a href="?page=antek-chat-connector&tab=voice_provider" class="button button-secondary">
                        <span class="dashicons dashicons-admin-settings" style="vertical-align: middle;"></span>
                        <?php esc_html_e('Configure Voice Provider', 'antek-chat-connector'); ?>
                    </a>
                    <p class="description" style="margin-top: 10px;">
                        <strong><?php esc_html_e('Tip:', 'antek-chat-connector'); ?></strong>
                        <?php esc_html_e('You can use separate agents for voice and text chat by configuring both "Voice Agent ID" and "Text Chat Agent ID" in Voice Provider settings.', 'antek-chat-connector'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php esc_html_e('Widget Status', 'antek-chat-connector'); ?>
                </th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox"
                                   name="antek_chat_settings[widget_enabled]"
                                   id="widget_enabled"
                                   value="1"
                                   <?php checked(isset($settings['widget_enabled']) ? $settings['widget_enabled'] : true, 1); ?>>
                            <?php esc_html_e('Show chat widget on frontend', 'antek-chat-connector'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php esc_html_e('Test Connection', 'antek-chat-connector'); ?>
                </th>
                <td>
                    <button type="button" id="test-webhook" class="button button-secondary">
                        <?php esc_html_e('Test Webhook', 'antek-chat-connector'); ?>
                    </button>
                    <span id="test-result" style="margin-left: 10px;"></span>
                    <p class="description">
                        <?php esc_html_e('Test the connection to your n8n webhook', 'antek-chat-connector'); ?>
                    </p>
                </td>
            </tr>
        </tbody>
    </table>

    <?php submit_button(); ?>
</form>

<div class="antek-chat-info-box" style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-left: 4px solid #FF6B4A;">
    <h3><?php esc_html_e('Usage Instructions', 'antek-chat-connector'); ?></h3>
    <p><?php esc_html_e('To use this plugin:', 'antek-chat-connector'); ?></p>
    <ol>
        <li><?php esc_html_e('Create a webhook in your n8n workflow', 'antek-chat-connector'); ?></li>
        <li><?php esc_html_e('Paste the webhook URL above', 'antek-chat-connector'); ?></li>
        <li><?php esc_html_e('Configure your workflow to return a JSON response with a "response" field', 'antek-chat-connector'); ?></li>
        <li><?php esc_html_e('Customize the appearance in the Appearance tab', 'antek-chat-connector'); ?></li>
        <li><?php esc_html_e('The widget will appear automatically on your site', 'antek-chat-connector'); ?></li>
    </ol>
    <p><strong><?php esc_html_e('Expected Response Format:', 'antek-chat-connector'); ?></strong></p>
    <pre style="background: white; padding: 10px; border: 1px solid #ddd; overflow-x: auto;">
{
  "response": "Your AI response text here",
  "metadata": {}
}</pre>
</div>
