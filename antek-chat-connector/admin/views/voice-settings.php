<?php
/**
 * Voice Settings View
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
    <?php settings_fields('antek_chat_settings'); ?>

    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row">
                    <?php esc_html_e('Enable Voice', 'antek-chat-connector'); ?>
                </th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox"
                                   name="antek_chat_settings[voice_enabled]"
                                   id="voice_enabled"
                                   value="1"
                                   <?php checked(isset($settings['voice_enabled']) ? $settings['voice_enabled'] : false, 1); ?>>
                            <?php esc_html_e('Enable voice chat with ElevenLabs', 'antek-chat-connector'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="elevenlabs_api_key"><?php esc_html_e('ElevenLabs API Key', 'antek-chat-connector'); ?></label>
                </th>
                <td>
                    <input type="password"
                           name="antek_chat_settings[elevenlabs_api_key]"
                           id="elevenlabs_api_key"
                           value="<?php echo esc_attr(isset($settings['elevenlabs_api_key']) ? $settings['elevenlabs_api_key'] : ''); ?>"
                           class="regular-text"
                           placeholder="sk_...">
                    <p class="description">
                        <?php esc_html_e('Your ElevenLabs API key', 'antek-chat-connector'); ?>
                        <a href="https://elevenlabs.io" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e('Get API key', 'antek-chat-connector'); ?>
                        </a>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="elevenlabs_voice_id"><?php esc_html_e('Voice ID / Agent ID', 'antek-chat-connector'); ?></label>
                </th>
                <td>
                    <input type="text"
                           name="antek_chat_settings[elevenlabs_voice_id]"
                           id="elevenlabs_voice_id"
                           value="<?php echo esc_attr(isset($settings['elevenlabs_voice_id']) ? $settings['elevenlabs_voice_id'] : ''); ?>"
                           class="regular-text"
                           placeholder="21m00Tcm4TlvDq8ikWAM">
                    <p class="description">
                        <?php esc_html_e('ElevenLabs voice ID or conversational AI agent ID', 'antek-chat-connector'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php esc_html_e('Widget Configuration', 'antek-chat-connector'); ?>
                </th>
                <td>
                    <input type="hidden" name="antek_chat_settings[widget_enabled]" value="<?php echo esc_attr(isset($settings['widget_enabled']) ? $settings['widget_enabled'] : 1); ?>">
                    <input type="hidden" name="antek_chat_settings[n8n_webhook_url]" value="<?php echo esc_attr(isset($settings['n8n_webhook_url']) ? $settings['n8n_webhook_url'] : ''); ?>">
                    <p class="description">
                        <?php esc_html_e('When voice is enabled, a microphone button will appear in the chat widget.', 'antek-chat-connector'); ?>
                    </p>
                </td>
            </tr>
        </tbody>
    </table>

    <?php submit_button(); ?>
</form>

<div class="antek-chat-info-box" style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-left: 4px solid #FF6B4A;">
    <h3><?php esc_html_e('Voice Chat Setup', 'antek-chat-connector'); ?></h3>
    <p><?php esc_html_e('To enable voice chat:', 'antek-chat-connector'); ?></p>
    <ol>
        <li><?php esc_html_e('Sign up for an ElevenLabs account at elevenlabs.io', 'antek-chat-connector'); ?></li>
        <li><?php esc_html_e('Create a conversational AI agent or select a voice', 'antek-chat-connector'); ?></li>
        <li><?php esc_html_e('Copy your API key and voice/agent ID', 'antek-chat-connector'); ?></li>
        <li><?php esc_html_e('Paste them above and enable voice chat', 'antek-chat-connector'); ?></li>
        <li><?php esc_html_e('Users will see a microphone button in the chat widget', 'antek-chat-connector'); ?></li>
    </ol>
    <p><strong><?php esc_html_e('Note:', 'antek-chat-connector'); ?></strong> <?php esc_html_e('Voice chat requires HTTPS and microphone permissions from users.', 'antek-chat-connector'); ?></p>
</div>
