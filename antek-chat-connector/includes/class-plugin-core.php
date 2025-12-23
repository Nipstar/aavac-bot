<?php
/**
 * Plugin Core Class
 *
 * Main plugin orchestrator that initializes all components
 *
 * @package Antek_Chat_Connector
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Core plugin class
 *
 * @since 1.0.0
 */
class Antek_Chat_Plugin_Core {

    /**
     * Admin settings instance
     *
     * @var Antek_Chat_Admin_Settings
     */
    private $admin_settings;

    /**
     * Widget renderer instance
     *
     * @var Antek_Chat_Widget_Renderer
     */
    private $widget_renderer;

    /**
     * Initialize the plugin
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->admin_settings = new Antek_Chat_Admin_Settings();
        $this->widget_renderer = new Antek_Chat_Widget_Renderer();
    }

    /**
     * Run the plugin
     *
     * @since 1.0.0
     */
    public function run() {
        // Register AJAX handlers
        add_action('wp_ajax_antek_chat_send_message', array($this, 'handle_send_message'));
        add_action('wp_ajax_nopriv_antek_chat_send_message', array($this, 'handle_send_message'));

        add_action('wp_ajax_antek_chat_get_history', array($this, 'handle_get_history'));
        add_action('wp_ajax_nopriv_antek_chat_get_history', array($this, 'handle_get_history'));

        add_action('wp_ajax_antek_chat_test_webhook', array($this, 'handle_test_webhook'));
        add_action('wp_ajax_antek_chat_detect_theme_colors', array($this, 'handle_detect_theme_colors'));

        // Render widget on frontend
        add_action('wp_footer', array($this->widget_renderer, 'render'));
    }

    /**
     * Handle send message AJAX request
     *
     * @since 1.0.0
     */
    public function handle_send_message() {
        check_ajax_referer('antek_chat_nonce', 'nonce');

        $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';
        $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
        $page_url = isset($_POST['page_url']) ? esc_url_raw($_POST['page_url']) : '';

        Antek_Chat_Debug_Logger::log('chat', 'Message received from frontend', 'info', [
            'session_id' => $session_id,
            'message_length' => strlen($message),
            'page_url' => $page_url
        ]);

        if (empty($session_id) || empty($message)) {
            Antek_Chat_Debug_Logger::log('chat', 'Invalid request - missing session_id or message', 'error');
            wp_send_json_error(array('message' => __('Invalid request', 'antek-chat-connector')));
            return;
        }

        // Check rate limit
        if (!$this->check_rate_limit($session_id)) {
            Antek_Chat_Debug_Logger::log('chat', 'Rate limit exceeded', 'warning', [
                'session_id' => $session_id
            ]);
            wp_send_json_error(array('message' => __('Too many messages. Please wait a moment.', 'antek-chat-connector')));
            return;
        }

        // Get chat mode setting (v1.2.1+ structure)
        $connection_settings = get_option('antek_chat_connection', array());
        $chat_mode = isset($connection_settings['chat_mode']) ? $connection_settings['chat_mode'] : 'n8n';

        Antek_Chat_Debug_Logger::log('chat', 'Chat mode selected', 'info', [
            'chat_mode' => $chat_mode
        ]);

        // Initialize handlers
        $session_manager = new Antek_Chat_Session_Manager();

        // Get conversation history for context
        $history = $session_manager->get_conversation($session_id);

        // Route to webhook handler (handles both n8n and retell modes)
        Antek_Chat_Debug_Logger::log('chat', 'Routing to webhook handler', 'info');
        $webhook_handler = new Antek_Chat_Webhook_Handler();
        $result = $webhook_handler->send_message($session_id, $message, array(
            'user_id' => get_current_user_id(),
            'history' => $history,
            'page_url' => $page_url,
        ));

        // Legacy: Keep for backward compatibility if needed
        if (false && isset($connection_settings['chat_provider']) && $connection_settings['chat_provider'] === 'voice_provider') {
            // Use configured voice provider for text chat
            require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/providers/class-voice-provider-factory.php';

            // Detect which voice provider is enabled
            $voice_settings = get_option('antek_chat_voice_settings', array());
            $voice_enabled = isset($voice_settings['voice_enabled']) ? (bool) $voice_settings['voice_enabled'] : false;
            $voice_provider = isset($voice_settings['voice_provider']) ? $voice_settings['voice_provider'] : 'retell';

            Antek_Chat_Debug_Logger::log('chat', 'Routing to voice provider', 'info', [
                'voice_provider' => $voice_provider,
                'voice_enabled' => $voice_enabled
            ]);

            if (!$voice_enabled) {
                Antek_Chat_Debug_Logger::log('chat', 'Voice provider disabled', 'error');
                $result = new WP_Error('voice_provider_disabled', __('Voice provider is not enabled. Please enable it in Voice Provider settings.', 'antek-chat-connector'));
            } else {
                try {
                    $provider = Antek_Chat_Voice_Provider_Factory::create($voice_provider);
                    Antek_Chat_Debug_Logger::log('chat', 'Voice provider created successfully', 'info', [
                        'provider' => $voice_provider
                    ]);
                    $result = $provider->send_text_message($message, array(
                        'session_id' => $session_id,
                        'history' => $history,
                        'page_url' => $page_url,
                        'user_id' => get_current_user_id(),
                    ));
                } catch (Exception $e) {
                    Antek_Chat_Debug_Logger::log('chat', 'Provider factory error', 'error', [
                        'error' => $e->getMessage(),
                        'provider' => $voice_provider
                    ]);
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('Antek Chat - Provider error: ' . $e->getMessage());
                    }
                    $result = new WP_Error('provider_error', $e->getMessage());
                }
            }
        }

        if (is_wp_error($result)) {
            Antek_Chat_Debug_Logger::log('chat', 'Message send failed', 'error', [
                'error' => $result->get_error_message(),
                'provider' => $chat_provider
            ]);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Antek Chat - Error: ' . $result->get_error_message());
            }
            wp_send_json_error(array('message' => __('Failed to send message. Please check your provider settings.', 'antek-chat-connector')));
            return;
        }

        // Save to session
        $response_text = isset($result['response']) ? $result['response'] : __('Sorry, I didn\'t understand that.', 'antek-chat-connector');
        $session_manager->save_conversation($session_id, $message, $response_text);

        Antek_Chat_Debug_Logger::log('chat', 'Message sent successfully', 'info', [
            'response_length' => strlen($response_text),
            'provider' => $chat_provider
        ]);

        wp_send_json_success(array(
            'response' => $response_text,
            'metadata' => isset($result['metadata']) ? $result['metadata'] : array(),
        ));
    }

    /**
     * Handle get history AJAX request
     *
     * @since 1.0.0
     */
    public function handle_get_history() {
        check_ajax_referer('antek_chat_nonce', 'nonce');

        $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';

        if (empty($session_id)) {
            wp_send_json_error(array('message' => __('Invalid session', 'antek-chat-connector')));
            return;
        }

        $session_manager = new Antek_Chat_Session_Manager();
        $history = $session_manager->get_conversation($session_id);

        wp_send_json_success(array('history' => $history));
    }

    /**
     * Handle test webhook AJAX request
     *
     * @since 1.0.0
     */
    public function handle_test_webhook() {
        check_ajax_referer('antek_chat_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'antek-chat-connector')));
            return;
        }

        $webhook_handler = new Antek_Chat_Webhook_Handler();
        $result = $webhook_handler->send_message(
            'test-session',
            'Test message from Antek Chat Connector',
            array('test' => true)
        );

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        wp_send_json_success(array('message' => __('Webhook connection successful!', 'antek-chat-connector')));
    }

    /**
     * Check rate limit for session
     *
     * @param string $session_id Session ID
     * @return bool True if within limit, false otherwise
     * @since 1.0.0
     */
    private function check_rate_limit($session_id) {
        $key = 'antek_chat_rate_' . md5($session_id);
        $count = get_transient($key);

        if ($count === false) {
            $count = 0;
        }

        $max_messages = 50;
        $time_window = 3600; // 1 hour

        if ($count >= $max_messages) {
            return false;
        }

        set_transient($key, $count + 1, $time_window);
        return true;
    }

    /**
     * Handle detect theme colors AJAX request
     *
     * Detects colors from the active theme and returns them
     *
     * @since 1.1.0
     */
    public function handle_detect_theme_colors() {
        check_ajax_referer('antek_chat_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'antek-chat-connector')));
            return;
        }

        require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/class-debug-logger.php';

        Antek_Chat_Debug_Logger::log('theme', 'Theme color detection started', 'info');

        $colors = $this->detect_theme_colors();

        Antek_Chat_Debug_Logger::log('theme', 'Theme colors detected', 'info', array(
            'colors' => $colors,
            'elementor_active' => defined('ELEMENTOR_VERSION'),
            'theme' => wp_get_theme()->get('Name')
        ));

        wp_send_json_success(array(
            'colors' => $colors,
            'message' => __('Theme colors detected successfully', 'antek-chat-connector')
        ));
    }

    /**
     * Detect theme colors from active WordPress theme
     *
     * Attempts to extract colors from theme.json, customizer, and CSS variables
     *
     * @return array Array of detected colors
     * @since 1.1.0
     */
    private function detect_theme_colors() {
        $colors = array(
            'primary_color' => '#FF6B4A',
            'secondary_color' => '#8FA68E',
            'background_color' => '#FDFBF6',
            'text_color' => '#2C2C2C'
        );

        // 1. Try Elementor first (most popular page builder)
        if (defined('ELEMENTOR_VERSION')) {
            $elementor_colors = $this->get_elementor_colors();
            if (!empty($elementor_colors)) {
                $colors = array_merge($colors, $elementor_colors);
                return apply_filters('antek_chat_detected_theme_colors', $colors);
            }
        }

        // 2. Try popular theme frameworks
        $theme_colors = $this->get_theme_framework_colors();
        if (!empty($theme_colors)) {
            $colors = array_merge($colors, $theme_colors);
        }

        // 3. Try to get colors from theme.json (WordPress 5.8+)
        if (function_exists('wp_get_global_settings')) {
            $global_settings = wp_get_global_settings();

            if (isset($global_settings['color']['palette']['theme'])) {
                $palette = $global_settings['color']['palette']['theme'];

                // Map theme palette to our colors
                if (!empty($palette)) {
                    // Primary color - usually first or a prominent color
                    if (isset($palette[0]['color'])) {
                        $colors['primary_color'] = $palette[0]['color'];
                    }
                    // Secondary color - second color if available
                    if (isset($palette[1]['color'])) {
                        $colors['secondary_color'] = $palette[1]['color'];
                    }
                    // Background - look for base or background color
                    foreach ($palette as $color_item) {
                        $slug = strtolower($color_item['slug'] ?? '');
                        if (strpos($slug, 'background') !== false || strpos($slug, 'base') !== false) {
                            $colors['background_color'] = $color_item['color'];
                            break;
                        }
                    }
                    // Text color - look for foreground or text color
                    foreach ($palette as $color_item) {
                        $slug = strtolower($color_item['slug'] ?? '');
                        if (strpos($slug, 'foreground') !== false || strpos($slug, 'text') !== false || strpos($slug, 'contrast') !== false) {
                            $colors['text_color'] = $color_item['color'];
                            break;
                        }
                    }
                }
            }
        }

        // 4. Try to get colors from customizer
        $theme_mods = get_theme_mods();
        if (!empty($theme_mods)) {
            // Common theme mod keys for colors
            $color_mappings = array(
                'primary_color' => array('primary_color', 'accent_color', 'link_color', 'theme_color'),
                'secondary_color' => array('secondary_color', 'secondary_accent', 'highlight_color'),
                'background_color' => array('background_color', 'body_bg_color', 'bg_color', 'site_background'),
                'text_color' => array('text_color', 'body_text_color', 'font_color', 'main_text_color')
            );

            foreach ($color_mappings as $our_key => $possible_keys) {
                foreach ($possible_keys as $theme_key) {
                    if (isset($theme_mods[$theme_key]) && !empty($theme_mods[$theme_key])) {
                        $color_value = $theme_mods[$theme_key];
                        // Add # if missing
                        if (strpos($color_value, '#') !== 0 && ctype_xdigit($color_value)) {
                            $color_value = '#' . $color_value;
                        }
                        if ($this->is_valid_hex_color($color_value)) {
                            $colors[$our_key] = $color_value;
                            break;
                        }
                    }
                }
            }
        }

        // 5. Fallback: Try to detect from body background-color (WordPress default)
        $background_color = get_background_color();
        if (!empty($background_color)) {
            $colors['background_color'] = '#' . $background_color;
        }

        // Apply filters to allow themes to override detection
        $colors = apply_filters('antek_chat_detected_theme_colors', $colors);

        return $colors;
    }

    /**
     * Get colors from Elementor
     *
     * Uses multi-approach fallback system:
     * 1. Elementor Kit Manager API (most reliable)
     * 2. Direct meta query with multiple keys
     * 3. Global Elementor settings (legacy)
     *
     * @return array Array of detected colors
     * @since 1.1.0
     */
    private function get_elementor_colors() {
        $colors = array();

        Antek_Chat_Debug_Logger::log('theme', 'Starting Elementor color detection', 'info', array(
            'has_elementor' => defined('ELEMENTOR_VERSION'),
            'elementor_version' => defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : 'N/A'
        ));

        if (!defined('ELEMENTOR_VERSION')) {
            return $colors;
        }

        // APPROACH 1: Use Elementor Kit Manager API (most reliable)
        if (class_exists('\Elementor\Core\Kits\Manager')) {
            try {
                $kit_id = \Elementor\Plugin::$instance->kits_manager->get_active_id();

                Antek_Chat_Debug_Logger::log('theme', 'Elementor Kit Manager approach', 'info', array(
                    'kit_id' => $kit_id
                ));

                if ($kit_id) {
                    $kit = \Elementor\Plugin::$instance->documents->get($kit_id);

                    if ($kit) {
                        // Get system colors
                        $system_colors = $kit->get_settings('system_colors');

                        Antek_Chat_Debug_Logger::log('theme', 'Kit Manager system colors', 'info', array(
                            'has_system_colors' => !empty($system_colors),
                            'color_count' => is_array($system_colors) ? count($system_colors) : 0,
                            'system_colors' => $system_colors
                        ));

                        if (!empty($system_colors) && is_array($system_colors)) {
                            foreach ($system_colors as $color) {
                                $color_id = strtolower($color['_id'] ?? '');
                                $color_value = $color['color'] ?? '';

                                if (!$this->is_valid_hex_color($color_value)) {
                                    continue;
                                }

                                // Map Elementor color IDs to our colors
                                if ($color_id === 'primary' || strpos($color_id, 'primary') !== false) {
                                    $colors['primary_color'] = $color_value;
                                } elseif ($color_id === 'secondary' || strpos($color_id, 'secondary') !== false) {
                                    $colors['secondary_color'] = $color_value;
                                } elseif ($color_id === 'text' || strpos($color_id, 'text') !== false) {
                                    $colors['text_color'] = $color_value;
                                } elseif ($color_id === 'accent' && empty($colors['primary_color'])) {
                                    $colors['primary_color'] = $color_value;
                                }
                            }
                        }

                        // Get custom colors as fallback
                        if (empty($colors)) {
                            $custom_colors = $kit->get_settings('custom_colors');

                            Antek_Chat_Debug_Logger::log('theme', 'Kit Manager custom colors', 'info', array(
                                'custom_colors' => $custom_colors
                            ));

                            if (!empty($custom_colors) && is_array($custom_colors)) {
                                if (isset($custom_colors[0]['color'])) {
                                    $colors['primary_color'] = $custom_colors[0]['color'];
                                }
                                if (isset($custom_colors[1]['color'])) {
                                    $colors['secondary_color'] = $custom_colors[1]['color'];
                                }
                            }
                        }
                    }
                }

                if (!empty($colors)) {
                    Antek_Chat_Debug_Logger::log('theme', 'Elementor Kit Manager SUCCESS', 'info', array(
                        'detected_colors' => $colors
                    ));
                    return $colors;
                }

            } catch (Exception $e) {
                Antek_Chat_Debug_Logger::log('theme', 'Elementor Kit Manager error', 'error', array(
                    'error' => $e->getMessage()
                ));
            }
        }

        // APPROACH 2: Direct meta query with multiple key patterns (fallback)
        $kit_id = get_option('elementor_active_kit');

        Antek_Chat_Debug_Logger::log('theme', 'Direct meta query approach', 'info', array(
            'kit_id' => $kit_id
        ));

        if ($kit_id) {
            // Try multiple meta key patterns
            $meta_keys = array(
                '_elementor_page_settings',
                '_elementor_data',
                'elementor_settings',
                'elementor_page_settings'
            );

            foreach ($meta_keys as $meta_key) {
                $kit_settings = get_post_meta($kit_id, $meta_key, true);

                Antek_Chat_Debug_Logger::log('theme', "Trying meta key: {$meta_key}", 'info', array(
                    'has_data' => !empty($kit_settings),
                    'data_type' => gettype($kit_settings),
                    'data_keys' => is_array($kit_settings) ? array_keys($kit_settings) : 'not array'
                ));

                if (!empty($kit_settings) && is_array($kit_settings)) {
                    // Try system_colors
                    if (!empty($kit_settings['system_colors'])) {
                        $system_colors = $kit_settings['system_colors'];

                        foreach ($system_colors as $color) {
                            $color_id = strtolower($color['_id'] ?? '');
                            $color_value = $color['color'] ?? '';

                            if (!$this->is_valid_hex_color($color_value)) {
                                continue;
                            }

                            if ($color_id === 'primary' || strpos($color_id, 'primary') !== false) {
                                $colors['primary_color'] = $color_value;
                            } elseif ($color_id === 'secondary' || strpos($color_id, 'secondary') !== false) {
                                $colors['secondary_color'] = $color_value;
                            } elseif ($color_id === 'text' || strpos($color_id, 'text') !== false) {
                                $colors['text_color'] = $color_value;
                            }
                        }
                    }

                    // Try custom_colors
                    if (empty($colors) && !empty($kit_settings['custom_colors'])) {
                        $custom_colors = $kit_settings['custom_colors'];
                        if (isset($custom_colors[0]['color'])) {
                            $colors['primary_color'] = $custom_colors[0]['color'];
                        }
                        if (isset($custom_colors[1]['color'])) {
                            $colors['secondary_color'] = $custom_colors[1]['color'];
                        }
                    }

                    if (!empty($colors)) {
                        Antek_Chat_Debug_Logger::log('theme', "Meta key {$meta_key} SUCCESS", 'info', array(
                            'detected_colors' => $colors
                        ));
                        return $colors;
                    }
                }
            }
        }

        // APPROACH 3: Global Elementor settings (legacy fallback)
        $elementor_options = get_option('elementor_scheme_color');

        Antek_Chat_Debug_Logger::log('theme', 'Global Elementor settings approach', 'info', array(
            'has_scheme' => !empty($elementor_options)
        ));

        if (!empty($elementor_options) && is_array($elementor_options)) {
            // Map scheme colors (1=primary, 2=secondary, 3=text, 4=accent)
            if (isset($elementor_options['1']) && $this->is_valid_hex_color($elementor_options['1'])) {
                $colors['primary_color'] = $elementor_options['1'];
            }
            if (isset($elementor_options['2']) && $this->is_valid_hex_color($elementor_options['2'])) {
                $colors['secondary_color'] = $elementor_options['2'];
            }
            if (isset($elementor_options['3']) && $this->is_valid_hex_color($elementor_options['3'])) {
                $colors['text_color'] = $elementor_options['3'];
            }
        }

        Antek_Chat_Debug_Logger::log('theme', 'Elementor color detection complete', 'info', array(
            'detected_colors' => $colors,
            'success' => !empty($colors)
        ));

        return $colors;
    }

    /**
     * Get colors from popular theme frameworks
     *
     * @return array Array of detected colors
     * @since 1.1.0
     */
    private function get_theme_framework_colors() {
        $colors = array();
        $current_theme = wp_get_theme();
        $theme_name = strtolower($current_theme->get('Name'));
        $theme_template = strtolower($current_theme->get_template());

        // Astra theme
        if (strpos($theme_name, 'astra') !== false || $theme_template === 'astra') {
            $astra_options = get_option('astra-settings');
            if (!empty($astra_options)) {
                if (!empty($astra_options['theme-color'])) {
                    $colors['primary_color'] = $astra_options['theme-color'];
                }
                if (!empty($astra_options['link-color'])) {
                    $colors['primary_color'] = $astra_options['link-color'];
                }
                if (!empty($astra_options['text-color'])) {
                    $colors['text_color'] = $astra_options['text-color'];
                }
            }
        }

        // GeneratePress theme
        if (strpos($theme_name, 'generatepress') !== false || $theme_template === 'generatepress') {
            $gp_settings = get_option('generate_settings');
            if (!empty($gp_settings)) {
                if (!empty($gp_settings['link_color'])) {
                    $colors['primary_color'] = $gp_settings['link_color'];
                }
                if (!empty($gp_settings['text_color'])) {
                    $colors['text_color'] = $gp_settings['text_color'];
                }
                if (!empty($gp_settings['background_color'])) {
                    $colors['background_color'] = $gp_settings['background_color'];
                }
            }
        }

        // OceanWP theme
        if (strpos($theme_name, 'oceanwp') !== false || $theme_template === 'oceanwp') {
            $primary = get_theme_mod('ocean_primary_color');
            $main_border = get_theme_mod('ocean_main_border_color');

            if ($primary) $colors['primary_color'] = $primary;
            if ($main_border) $colors['secondary_color'] = $main_border;
        }

        // Avada theme
        if (strpos($theme_name, 'avada') !== false || $theme_template === 'avada') {
            $primary = get_theme_mod('primary_color');
            if ($primary) $colors['primary_color'] = $primary;
        }

        // Divi theme/builder
        if (strpos($theme_name, 'divi') !== false || $theme_template === 'divi' || defined('ET_BUILDER_VERSION')) {
            $accent_color = get_theme_mod('accent_color');
            if ($accent_color) $colors['primary_color'] = $accent_color;
        }

        return $colors;
    }

    /**
     * Validate hex color code
     *
     * @param string $color Color code to validate
     * @return bool True if valid hex color
     * @since 1.1.0
     */
    private function is_valid_hex_color($color) {
        return preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color);
    }
}
