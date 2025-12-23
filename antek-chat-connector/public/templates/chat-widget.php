<?php
/**
 * Chat Widget Template
 *
 * @package Antek_Chat_Connector
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$appearance = get_option('antek_chat_appearance', array());
$position = isset($appearance['widget_position']) ? $appearance['widget_position'] : 'bottom-right';
$size = isset($appearance['widget_size']) ? $appearance['widget_size'] : 'medium';

// Get voice enabled status from v1.1.0 settings (with backward compatibility)
$voice_settings = get_option('antek_chat_voice_settings', array());
$voice_enabled = isset($voice_settings['voice_enabled']) ? $voice_settings['voice_enabled'] : false;

// Fallback to legacy v1.0.0 settings if new settings empty
if (!$voice_enabled) {
    $legacy_settings = get_option('antek_chat_settings', array());
    $voice_enabled = isset($legacy_settings['voice_enabled']) ? $legacy_settings['voice_enabled'] : false;
}
?>

<!-- Antek Chat Connector Widget -->
<div id="antek-chat-widget" class="antek-chat-widget antek-<?php echo esc_attr($position); ?> antek-size-<?php echo esc_attr($size); ?>">

    <!-- Chat Trigger Button -->
    <button class="antek-chat-trigger" id="antek-chat-trigger" aria-label="<?php esc_attr_e('Open chat', 'antek-chat-connector'); ?>">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2ZM20 16H5.17L4 17.17V4H20V16Z" fill="currentColor"/>
            <path d="M7 9H17V11H7V9Z" fill="currentColor"/>
            <path d="M7 12H14V14H7V12Z" fill="currentColor"/>
        </svg>
    </button>

    <!-- Chat Window -->
    <div class="antek-chat-window" id="antek-chat-window" style="display: none;">

        <!-- Header -->
        <div class="antek-chat-header">
            <span class="antek-chat-title"><?php esc_html_e('Chat with us', 'antek-chat-connector'); ?></span>

            <?php if ($voice_enabled): ?>
            <div class="antek-mode-toggle" id="antek-mode-toggle">
                <button class="antek-mode-btn antek-mode-text active"
                        data-mode="text"
                        title="<?php esc_attr_e('Switch to text mode', 'antek-chat-connector'); ?>"
                        aria-label="<?php esc_attr_e('Text mode', 'antek-chat-connector'); ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <span class="mode-label"><?php esc_html_e('Text', 'antek-chat-connector'); ?></span>
                </button>
                <button class="antek-mode-btn antek-mode-voice"
                        data-mode="voice"
                        title="<?php esc_attr_e('Switch to voice mode', 'antek-chat-connector'); ?>"
                        aria-label="<?php esc_attr_e('Voice mode', 'antek-chat-connector'); ?>">
                    <svg width="16" height="16" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M10 12C11.66 12 13 10.66 13 9V4C13 2.34 11.66 1 10 1C8.34 1 7 2.34 7 4V9C7 10.66 8.34 12 10 12Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        <path d="M16 9C16 12.31 13.31 15 10 15C6.69 15 4 12.31 4 9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span class="mode-label"><?php esc_html_e('Voice', 'antek-chat-connector'); ?></span>
                </button>
            </div>
            <?php endif; ?>

            <button class="antek-chat-close" id="antek-chat-close" aria-label="<?php esc_attr_e('Close chat', 'antek-chat-connector'); ?>">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M15 5L5 15M5 5L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        <!-- Messages Container -->
        <div class="antek-chat-messages" id="antek-chat-messages">
            <div class="antek-chat-welcome">
                <?php esc_html_e('Hello! How can we help you today?', 'antek-chat-connector'); ?>
            </div>
        </div>

        <!-- Input Area -->
        <div class="antek-chat-input-wrapper">
            <!-- Text Mode Input (default) -->
            <div class="antek-input-mode antek-text-mode active" id="antek-text-mode">
                <textarea class="antek-chat-input"
                          id="antek-chat-input"
                          rows="1"
                          placeholder="<?php esc_attr_e('Type your message...', 'antek-chat-connector'); ?>"
                          aria-label="<?php esc_attr_e('Chat message input', 'antek-chat-connector'); ?>"></textarea>

                <button class="antek-chat-send" id="antek-chat-send" aria-label="<?php esc_attr_e('Send message', 'antek-chat-connector'); ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                    </svg>
                </button>
            </div>

            <!-- Voice Mode Controls (hidden by default) -->
            <?php if ($voice_enabled): ?>
            <div class="antek-input-mode antek-voice-mode" id="antek-voice-mode" style="display: none;">
                <div class="antek-voice-status-container">
                    <p class="antek-voice-instructions"><?php esc_html_e('Click the microphone to start talking', 'antek-chat-connector'); ?></p>
                    <p id="antek-voice-status" class="antek-voice-status-text"></p>
                </div>
                <button class="antek-voice-button" id="antek-voice-call-button" aria-label="<?php esc_attr_e('Start voice chat', 'antek-chat-connector'); ?>" title="<?php esc_attr_e('Click to start voice call', 'antek-chat-connector'); ?>">
                    <svg class="voice-icon" width="48" height="48" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z"/>
                        <path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"/>
                    </svg>
                </button>
            </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<!-- Apply custom styles -->
<style id="antek-chat-custom-styles">
    :root {
        --antek-primary: <?php echo esc_attr(isset($appearance['primary_color']) ? $appearance['primary_color'] : '#FF6B4A'); ?>;
        --antek-secondary: <?php echo esc_attr(isset($appearance['secondary_color']) ? $appearance['secondary_color'] : '#8FA68E'); ?>;
        --antek-background: <?php echo esc_attr(isset($appearance['background_color']) ? $appearance['background_color'] : '#FDFBF6'); ?>;
        --antek-text: <?php echo esc_attr(isset($appearance['text_color']) ? $appearance['text_color'] : '#2C2C2C'); ?>;
        --antek-radius: <?php echo esc_attr(isset($appearance['border_radius']) ? $appearance['border_radius'] : '12px'); ?>;
        --antek-font: <?php echo esc_attr(isset($appearance['font_family']) ? $appearance['font_family'] : 'inherit'); ?>;
    }

    <?php if (!empty($appearance['custom_css'])): ?>
    <?php echo wp_strip_all_tags($appearance['custom_css']); ?>
    <?php endif; ?>
</style>
