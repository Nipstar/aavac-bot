<?php
/**
 * Widget Renderer Class
 *
 * Renders the chat widget on frontend
 *
 * @package Antek_Chat_Connector
 * @since 1.0.0
 * @updated 1.1.0 Added multimodal widget support with voice providers and media uploads
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Widget renderer class
 *
 * @since 1.0.0
 */
class Antek_Chat_Widget_Renderer {

    /**
     * Render the widget
     *
     * @param array $args Optional arguments to override settings
     * @since 1.0.0
     * @updated 1.1.0 Added multimodal configuration
     */
    public function render($args = array()) {
        $settings = get_option('antek_chat_settings');
        $appearance = get_option('antek_chat_appearance');
        $popup = get_option('antek_chat_popup');

        // Get v1.1.0 settings
        $voice_settings = get_option('antek_chat_voice_settings', array());
        $advanced_settings = get_option('antek_chat_advanced_settings', array());

        // Check if widget is enabled
        $widget_enabled = isset($settings['widget_enabled']) ? $settings['widget_enabled'] : true;
        if (!$widget_enabled && empty($args)) {
            return;
        }

        // Merge custom args with settings
        if (!empty($args)) {
            if (isset($args['position'])) {
                $appearance['widget_position'] = $args['position'];
            }
            if (isset($args['voice_enabled'])) {
                $voice_settings['voice_enabled'] = $args['voice_enabled'];
            }
            if (isset($args['use_rest'])) {
                $use_rest_api = $args['use_rest'];
            }
        }

        // Determine if multimodal features are enabled
        // Validate voice configuration before enabling
        $voice_enabled = false;
        if (isset($voice_settings['voice_enabled']) && $voice_settings['voice_enabled']) {
            $provider = isset($voice_settings['voice_provider']) ? $voice_settings['voice_provider'] : 'retell';

            // Check if provider is properly configured
            if ($provider === 'retell') {
                $has_api_key = !empty($voice_settings['retell_api_key']);
                $has_agent_id = !empty($voice_settings['retell_agent_id']);

                if ($has_api_key && $has_agent_id) {
                    $voice_enabled = true;
                } else {
                    error_log('Antek Chat: Voice disabled - Retell missing ' .
                        (!$has_api_key ? 'API key' : 'Agent ID'));
                }
            }
        }

        $media_enabled = true; // Media upload always available
        $multimodal_enabled = $voice_enabled || $media_enabled;

        // Use REST API if multimodal features are enabled or explicitly requested
        $use_rest_api = isset($use_rest_api) ? $use_rest_api : $multimodal_enabled;

        // Enqueue assets
        $this->enqueue_assets($multimodal_enabled, $voice_enabled);

        // Get session manager
        $session_manager = new Antek_Chat_Session_Manager();
        $session_id = $session_manager->get_session_id();

        // Prepare voice provider configuration
        $voice_provider = isset($voice_settings['voice_provider']) ? $voice_settings['voice_provider'] : 'retell';

        // Build JavaScript configuration
        $config = array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url(),
            'nonce' => wp_create_nonce('antek_chat_nonce'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'sessionId' => $session_id,
            'appearance' => $appearance,
            'popup' => $popup,
            'multimodalEnabled' => $multimodal_enabled,
            'voiceEnabled' => $voice_enabled,
            'mediaEnabled' => $media_enabled,
            'useRestApi' => $use_rest_api,
            'voiceProvider' => $voice_provider,
            'strings' => array(
                'placeholder' => __('Type your message...', 'antek-chat-connector'),
                'send' => __('Send', 'antek-chat-connector'),
                'title' => __('Chat with us', 'antek-chat-connector'),
                'error' => __('Sorry, there was an error. Please try again.', 'antek-chat-connector'),
                'connecting' => __('Connecting...', 'antek-chat-connector'),
                'micPermission' => __('Please allow microphone access to use voice chat.', 'antek-chat-connector'),
                'uploadError' => __('File upload failed. Please try again.', 'antek-chat-connector'),
                'fileTooLarge' => __('File is too large. Maximum size: ', 'antek-chat-connector'),
                'unsupportedFileType' => __('Unsupported file type.', 'antek-chat-connector'),
            ),
        );

        // Add media upload configuration
        if ($media_enabled) {
            $config['maxFileSizeMB'] = isset($advanced_settings['media_max_file_size_mb'])
                ? absint($advanced_settings['media_max_file_size_mb'])
                : 50;
            $config['allowedFileTypes'] = isset($advanced_settings['media_allowed_types'])
                ? $advanced_settings['media_allowed_types']
                : array('image', 'audio', 'document', 'video');
        }

        // Removed: Legacy ElevenLabs integration (no longer supported)

        // Pass config to JavaScript
        $script_handle = $multimodal_enabled ? 'antek-chat-multimodal' : 'antek-chat-widget';
        wp_localize_script($script_handle, 'antekChatConfig', $config);

        // Include widget template
        include ANTEK_CHAT_PLUGIN_DIR . 'public/templates/chat-widget.php';
    }

    /**
     * Enqueue frontend assets
     *
     * @param bool $multimodal_enabled Whether multimodal features are enabled
     * @param bool $voice_enabled Whether voice features are enabled
     * @since 1.0.0
     * @updated 1.1.8 Simplified asset loading with better error handling
     */
    private function enqueue_assets($multimodal_enabled = false, $voice_enabled = false) {
        // Set up version parameter for cache-busting
        $script_version = ANTEK_CHAT_VERSION;

        // Add timestamp in development/staging environments for aggressive cache-busting
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $script_version = ANTEK_CHAT_VERSION . '.' . time();
        }

        // Enqueue base styles
        wp_enqueue_style(
            'antek-chat-widget',
            ANTEK_CHAT_PLUGIN_URL . 'public/css/widget-styles.css',
            array(),
            $script_version
        );

        // Get appearance settings
        $appearance = get_option('antek_chat_appearance', array());
        $color_source = $appearance['color_source'] ?? 'custom';

        $dynamic_css = '';

        try {
            // ONLY detect colors if explicitly set to elementor/divi
            if ($color_source === 'elementor' || $color_source === 'divi') {
                $color_detector = new Antek_Chat_Theme_Color_Detector();
                if ($color_detector) {
                    $dynamic_css = $color_detector->generate_css_variables();
                }
            }

            // Use custom colors (or plugin defaults)
            if ($color_source === 'custom' || $color_source === 'plugin' || empty($dynamic_css)) {
                $primary = $appearance['primary_color'] ?? '#FF6B4A';
                $secondary = $appearance['secondary_color'] ?? '#8FA68E';
                $text = $appearance['text_color'] ?? '#2C2C2C';
                $background = $appearance['background_color'] ?? '#FFFFFF';

                // CRITICAL: Use !important to override theme styles
                $primary_rgb = $this->hex_to_rgb($primary);
                $dynamic_css = "
/* Antek Chat Custom Colors - Override Everything */
:root {
    --antek-primary: {$primary} !important;
    --antek-secondary: {$secondary} !important;
    --antek-text: {$text} !important;
    --antek-background: {$background} !important;
}

/* Force custom colors on all chat elements */
.antek-chat-widget {
    --antek-primary: {$primary} !important;
    --antek-secondary: {$secondary} !important;
    --antek-text: {$text} !important;
    --antek-background: {$background} !important;
}

/* Header */
.antek-chat-header {
    background: {$primary} !important;
    color: {$background} !important;
}

/* Bot messages */
.antek-message.bot {
    background: {$primary} !important;
    color: {$background} !important;
}

/* User messages */
.antek-message.user {
    background: {$secondary} !important;
    color: {$background} !important;
}

/* Buttons */
.antek-voice-button,
.antek-mode-button.active,
.antek-send-button {
    background: {$secondary} !important;
    color: {$background} !important;
}

.antek-mode-button:hover {
    background: {$secondary} !important;
    opacity: 0.9;
}

/* Input border */
.antek-input-wrapper input {
    border-color: {$primary} !important;
}

.antek-input-wrapper input:focus {
    border-color: {$primary} !important;
    box-shadow: 0 0 0 2px rgba({$primary_rgb}, 0.2) !important;
}

/* Chat toggle button */
.antek-chat-toggle {
    background: {$secondary} !important;
}
";
            }

            // Add custom CSS if provided
            if (!empty($appearance['custom_css'])) {
                $dynamic_css .= "\n" . wp_strip_all_tags($appearance['custom_css']);
            }

        } catch (Exception $e) {
            error_log('Antek Chat: Color generation error: ' . $e->getMessage());

            // Fallback to plugin defaults
            $dynamic_css = ":root {
                --antek-primary: #FF6B4A !important;
                --antek-secondary: #8FA68E !important;
                --antek-text: #2C2C2C !important;
                --antek-background: #FFFFFF !important;
            }";
        }

        wp_add_inline_style('antek-chat-widget', $dynamic_css);

        // CRITICAL: Load Retell SDK in HEAD (not footer) to ensure it's available
        $voice_settings = get_option('antek_chat_voice_settings', array());
        $voice_enabled = !empty($voice_settings['voice_enabled']);

        if ($voice_enabled) {
            error_log('Antek Chat: Voice enabled - loading Retell SDK and provider scripts');

            // Load Retell SDK from CDN (in HEAD for availability)
            // Required for BOTH direct Retell and n8n-proxied Retell
            // n8n returns genuine Retell access tokens that require the SDK
            wp_enqueue_script(
                'retell-web-sdk',
                'https://cdn.jsdelivr.net/npm/retell-client-js-sdk@2.3.0/dist/retell-client-js-sdk.min.js',
                array(),
                '2.3.0',
                false // Load in HEAD, not footer
            );

            // Voice provider factory (depends on SDK)
            wp_enqueue_script(
                'antek-voice-provider-factory',
                ANTEK_CHAT_PLUGIN_URL . 'public/js/providers/voice-provider-factory.js',
                array('jquery', 'retell-web-sdk'),
                $script_version,
                true
            );

            // Retell provider implementation (depends on factory and SDK)
            // Works for both 'retell' and 'n8n-retell' providers
            wp_enqueue_script(
                'antek-retell-provider',
                ANTEK_CHAT_PLUGIN_URL . 'public/js/providers/retell-provider.js',
                array('jquery', 'antek-voice-provider-factory', 'retell-web-sdk'),
                $script_version,
                true
            );

            error_log('Antek Chat: Voice SDK and provider scripts enqueued successfully');
        }

        // Main widget scripts
        wp_enqueue_script(
            'antek-chat-widget',
            ANTEK_CHAT_PLUGIN_URL . 'public/js/chat-widget.js',
            array('jquery'),
            $script_version,
            true
        );

        wp_enqueue_script(
            'antek-chat-multimodal',
            ANTEK_CHAT_PLUGIN_URL . 'public/js/multimodal-widget.js',
            array_filter(array(
                'antek-chat-widget',
                $voice_enabled ? 'antek-voice-provider-factory' : null
            )),
            $script_version,
            true
        );

        // Pass config to JavaScript
        wp_localize_script('antek-chat-multimodal', 'antekChatConfig', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url(),
            'nonce' => wp_create_nonce('wp_rest'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'sessionId' => uniqid('session_', true),
            'voiceEnabled' => $voice_enabled ? '1' : '0',
            'multimodalEnabled' => '1',
            'voiceProvider' => $voice_enabled ? ($voice_settings['voice_provider'] ?? 'retell') : '',
            'appearance' => $appearance,
            'strings' => array(
                'placeholder' => __('Type your message...', 'antek-chat-connector'),
                'send' => __('Send', 'antek-chat-connector'),
                'title' => __('Chat with us', 'antek-chat-connector'),
                'error' => __('Sorry, there was an error. Please try again.', 'antek-chat-connector'),
            ),
        ));
    }

    /**
     * Convert hex color to RGB
     *
     * @param string $hex Hex color code
     * @return string RGB values as "r, g, b"
     * @since 1.1.9
     */
    private function hex_to_rgb($hex) {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return "$r, $g, $b";
    }
}
