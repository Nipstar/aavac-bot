<?php
/**
 * Admin Settings Class
 *
 * Handles WordPress admin interface
 *
 * @package Antek_Chat_Connector
 * @since 1.0.0
 * @updated 1.1.0 Added voice provider, webhook, and advanced settings tabs
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin settings class
 *
 * @since 1.0.0
 */
class Antek_Chat_Admin_Settings {

    /**
     * Encryption manager instance
     *
     * @var Antek_Chat_Encryption_Manager
     * @since 1.1.0
     */
    private $encryption;

    /**
     * Initialize admin settings
     *
     * @since 1.0.0
     * @updated 1.1.0 Added encryption manager initialization
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Initialize encryption manager for sensitive data
        $this->encryption = new Antek_Chat_Encryption_Manager();

        // Add AJAX handlers for cleanup actions
        add_action('wp_ajax_antek_chat_cleanup_jobs', array($this, 'ajax_cleanup_jobs'));
        add_action('wp_ajax_antek_chat_cleanup_media', array($this, 'ajax_cleanup_media'));
        add_action('wp_ajax_antek_chat_test_webhook', array($this, 'ajax_test_webhook'));
        add_action('wp_ajax_antek_chat_test_provider', array($this, 'ajax_test_provider'));
    }

    /**
     * Add admin menu page
     *
     * @since 1.0.0
     * @updated 1.1.0 Updated menu title to AAVAC Bot
     */
    public function add_menu_page() {
        add_menu_page(
            __('AAVAC Bot', 'antek-chat-connector'),
            __('AAVAC Bot', 'antek-chat-connector'),
            'manage_options',
            'antek-chat-connector',
            array($this, 'render_settings_page'),
            'dashicons-format-chat',
            30
        );

        // Add debug logs submenu
        add_submenu_page(
            'antek-chat-connector',
            __('Debug Logs', 'antek-chat-connector'),
            __('Debug Logs', 'antek-chat-connector'),
            'manage_options',
            'antek-chat-logs',
            array($this, 'render_logs_page')
        );
    }

    /**
     * Render debug logs page
     *
     * @since 1.1.0
     */
    public function render_logs_page() {
        require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/class-debug-logger.php';
        include ANTEK_CHAT_PLUGIN_DIR . 'admin/views/debug-logs.php';
    }

    /**
     * Register settings
     *
     * @since 1.0.0
     * @updated 1.1.0 Added voice provider, webhook, and advanced settings
     */
    public function register_settings() {
        // Original v1.0.0 settings
        register_setting('antek_chat_settings', 'antek_chat_settings', array(
            'sanitize_callback' => array($this, 'sanitize_settings'),
        ));

        register_setting('antek_chat_appearance', 'antek_chat_appearance', array(
            'sanitize_callback' => array($this, 'sanitize_appearance'),
        ));

        register_setting('antek_chat_popup', 'antek_chat_popup', array(
            'sanitize_callback' => array($this, 'sanitize_popup'),
        ));

        // New v1.1.0 settings
        register_setting('antek_chat_voice_settings', 'antek_chat_voice_settings', array(
            'sanitize_callback' => array($this, 'sanitize_voice_settings'),
        ));

        register_setting('antek_chat_automation_settings', 'antek_chat_automation_settings', array(
            'sanitize_callback' => array($this, 'sanitize_automation_settings'),
        ));

        register_setting('antek_chat_advanced_settings', 'antek_chat_advanced_settings', array(
            'sanitize_callback' => array($this, 'sanitize_advanced_settings'),
        ));
    }

    /**
     * Sanitize settings
     *
     * @param array $input Input data
     * @return array Sanitized data
     * @since 1.0.0
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        // Chat provider selection
        if (isset($input['chat_provider'])) {
            $allowed_providers = array('n8n', 'voice_provider');
            $sanitized['chat_provider'] = in_array($input['chat_provider'], $allowed_providers)
                ? $input['chat_provider']
                : 'n8n';
        } else {
            $sanitized['chat_provider'] = 'n8n';
        }

        if (isset($input['n8n_webhook_url'])) {
            $sanitized['n8n_webhook_url'] = esc_url_raw($input['n8n_webhook_url']);
        }

        // Legacy settings (keep for backward compatibility)
        if (isset($input['elevenlabs_api_key'])) {
            $sanitized['elevenlabs_api_key'] = sanitize_text_field($input['elevenlabs_api_key']);
        }

        if (isset($input['elevenlabs_voice_id'])) {
            $sanitized['elevenlabs_voice_id'] = sanitize_text_field($input['elevenlabs_voice_id']);
        }

        $sanitized['widget_enabled'] = isset($input['widget_enabled']) ? (bool) $input['widget_enabled'] : false;
        $sanitized['voice_enabled'] = isset($input['voice_enabled']) ? (bool) $input['voice_enabled'] : false;

        return $sanitized;
    }

    /**
     * Sanitize appearance settings
     *
     * @param array $input Input data
     * @return array Sanitized data
     * @since 1.0.0
     */
    public function sanitize_appearance($input) {
        $sanitized = array();

        // Sanitize color source selection
        if (isset($input['color_source'])) {
            $allowed_sources = array('auto', 'elementor', 'divi', 'custom');
            $sanitized['color_source'] = in_array($input['color_source'], $allowed_sources)
                ? $input['color_source']
                : 'auto';
        }

        $color_fields = array('primary_color', 'secondary_color', 'background_color', 'text_color');
        foreach ($color_fields as $field) {
            if (isset($input[$field])) {
                $sanitized[$field] = sanitize_hex_color($input[$field]);
            }
        }

        if (isset($input['border_radius'])) {
            $sanitized['border_radius'] = sanitize_text_field($input['border_radius']);
        }

        if (isset($input['widget_position'])) {
            $allowed_positions = array('bottom-right', 'bottom-left', 'top-right', 'top-left');
            $sanitized['widget_position'] = in_array($input['widget_position'], $allowed_positions)
                ? $input['widget_position']
                : 'bottom-right';
        }

        if (isset($input['widget_size'])) {
            $allowed_sizes = array('small', 'medium', 'large');
            $sanitized['widget_size'] = in_array($input['widget_size'], $allowed_sizes)
                ? $input['widget_size']
                : 'medium';
        }

        if (isset($input['custom_css'])) {
            $sanitized['custom_css'] = wp_strip_all_tags($input['custom_css']);
        }

        if (isset($input['font_family'])) {
            $sanitized['font_family'] = sanitize_text_field($input['font_family']);
        }

        return $sanitized;
    }

    /**
     * Sanitize popup settings
     *
     * @param array $input Input data
     * @return array Sanitized data
     * @since 1.0.0
     */
    public function sanitize_popup($input) {
        $sanitized = array();

        $sanitized['popup_enabled'] = isset($input['popup_enabled']) ? (bool) $input['popup_enabled'] : false;

        if (isset($input['popup_delay'])) {
            $sanitized['popup_delay'] = absint($input['popup_delay']);
        }

        if (isset($input['popup_trigger'])) {
            $allowed_triggers = array('time', 'scroll', 'exit');
            $sanitized['popup_trigger'] = in_array($input['popup_trigger'], $allowed_triggers)
                ? $input['popup_trigger']
                : 'time';
        }

        if (isset($input['popup_message'])) {
            $sanitized['popup_message'] = sanitize_textarea_field($input['popup_message']);
        }

        if (isset($input['popup_pages'])) {
            $sanitized['popup_pages'] = array_map('sanitize_text_field', (array) $input['popup_pages']);
        }

        if (isset($input['popup_frequency'])) {
            $allowed_frequencies = array('once', 'session', 'always');
            $sanitized['popup_frequency'] = in_array($input['popup_frequency'], $allowed_frequencies)
                ? $input['popup_frequency']
                : 'once';
        }

        return $sanitized;
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     * @since 1.0.0
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our settings page
        if ($hook !== 'toplevel_page_antek-chat-connector') {
            return;
        }

        // Enqueue WordPress color picker
        wp_enqueue_style('wp-color-picker');

        // Enqueue admin styles
        wp_enqueue_style(
            'antek-chat-admin',
            ANTEK_CHAT_PLUGIN_URL . 'admin/css/admin-styles.css',
            array('wp-color-picker'),
            ANTEK_CHAT_VERSION
        );

        // Enqueue admin scripts
        wp_enqueue_script(
            'antek-chat-admin',
            ANTEK_CHAT_PLUGIN_URL . 'admin/js/admin-scripts.js',
            array('jquery', 'wp-color-picker'),
            ANTEK_CHAT_VERSION,
            true
        );

        // Localize admin script
        wp_localize_script('antek-chat-admin', 'antekChatAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('antek_chat_admin_nonce'),
            'strings' => array(
                'testing' => __('Testing...', 'antek-chat-connector'),
                'success' => __('Success!', 'antek-chat-connector'),
                'error' => __('Error:', 'antek-chat-connector'),
            ),
        ));
    }

    /**
     * Render settings page
     *
     * @since 1.0.0
     * @updated 1.1.0 Added new tabs for voice provider, webhook, and advanced settings
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get active tab
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'connection';

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php settings_errors(); ?>

            <h2 class="nav-tab-wrapper">
                <a href="?page=antek-chat-connector&amp;tab=connection" class="nav-tab <?php echo $active_tab === 'connection' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Connection', 'antek-chat-connector'); ?>
                </a>
                <a href="?page=antek-chat-connector&amp;tab=voice_provider" class="nav-tab <?php echo $active_tab === 'voice_provider' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Voice Provider', 'antek-chat-connector'); ?>
                </a>
                <a href="?page=antek-chat-connector&amp;tab=webhook" class="nav-tab <?php echo $active_tab === 'webhook' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Webhooks', 'antek-chat-connector'); ?>
                </a>
                <a href="?page=antek-chat-connector&amp;tab=advanced" class="nav-tab <?php echo $active_tab === 'advanced' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Advanced', 'antek-chat-connector'); ?>
                </a>
                <a href="?page=antek-chat-connector&amp;tab=appearance" class="nav-tab <?php echo $active_tab === 'appearance' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Appearance', 'antek-chat-connector'); ?>
                </a>
                <a href="?page=antek-chat-connector&amp;tab=popup" class="nav-tab <?php echo $active_tab === 'popup' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Popup Settings', 'antek-chat-connector'); ?>
                </a>
            </h2>

            <div class="antek-chat-settings-content">
                <?php
                switch ($active_tab) {
                    case 'connection':
                        include ANTEK_CHAT_PLUGIN_DIR . 'admin/views/connection-settings.php';
                        break;
                    case 'voice_provider':
                        include ANTEK_CHAT_PLUGIN_DIR . 'admin/views/voice-provider-settings.php';
                        break;
                    case 'webhook':
                        include ANTEK_CHAT_PLUGIN_DIR . 'admin/views/webhook-settings.php';
                        break;
                    case 'advanced':
                        include ANTEK_CHAT_PLUGIN_DIR . 'admin/views/advanced-settings.php';
                        break;
                    case 'appearance':
                        include ANTEK_CHAT_PLUGIN_DIR . 'admin/views/appearance-settings.php';
                        break;
                    case 'popup':
                        include ANTEK_CHAT_PLUGIN_DIR . 'admin/views/popup-settings.php';
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Sanitize voice provider settings
     *
     * @param array $input Input data
     * @return array Sanitized data
     * @since 1.1.0
     */
    public function sanitize_voice_settings($input) {
        $sanitized = array();

        // Voice enabled toggle
        $sanitized['voice_enabled'] = isset($input['voice_enabled']) ? (bool) $input['voice_enabled'] : false;

        // Voice provider selection
        if (isset($input['voice_provider'])) {
            $allowed_providers = array('retell', 'elevenlabs', 'n8n-retell');
            $sanitized['voice_provider'] = in_array($input['voice_provider'], $allowed_providers)
                ? $input['voice_provider']
                : 'retell';
        }

        // Retell settings (encrypt API key)
        if (isset($input['retell_api_key']) && !empty($input['retell_api_key'])) {
            // Only encrypt if it's a new key (not already encrypted)
            if (strpos($input['retell_api_key'], '***') === false) {
                $sanitized['retell_api_key'] = $this->encryption->encrypt(sanitize_text_field($input['retell_api_key']));
            } else {
                // Keep existing encrypted value
                $existing = get_option('antek_chat_voice_settings', array());
                $sanitized['retell_api_key'] = isset($existing['retell_api_key']) ? $existing['retell_api_key'] : '';
            }
        }

        if (isset($input['retell_agent_id'])) {
            $sanitized['retell_agent_id'] = sanitize_text_field($input['retell_agent_id']);
        }

        // Retell chat agent ID (optional, separate from voice)
        if (isset($input['retell_chat_agent_id'])) {
            $sanitized['retell_chat_agent_id'] = sanitize_text_field($input['retell_chat_agent_id']);
        }

        // ElevenLabs settings (encrypt API key)
        if (isset($input['elevenlabs_api_key']) && !empty($input['elevenlabs_api_key'])) {
            // Only encrypt if it's a new key (not already encrypted)
            if (strpos($input['elevenlabs_api_key'], '***') === false) {
                $sanitized['elevenlabs_api_key'] = $this->encryption->encrypt(sanitize_text_field($input['elevenlabs_api_key']));
            } else {
                // Keep existing encrypted value
                $existing = get_option('antek_chat_voice_settings', array());
                $sanitized['elevenlabs_api_key'] = isset($existing['elevenlabs_api_key']) ? $existing['elevenlabs_api_key'] : '';
            }
        }

        if (isset($input['elevenlabs_agent_id'])) {
            $sanitized['elevenlabs_agent_id'] = sanitize_text_field($input['elevenlabs_agent_id']);
        }

        // ElevenLabs chat agent ID (optional, separate from voice)
        if (isset($input['elevenlabs_chat_agent_id'])) {
            $sanitized['elevenlabs_chat_agent_id'] = sanitize_text_field($input['elevenlabs_chat_agent_id']);
        }

        $sanitized['elevenlabs_public_agent'] = isset($input['elevenlabs_public_agent']) ? (bool) $input['elevenlabs_public_agent'] : false;

        // Connection type
        if (isset($input['elevenlabs_connection_type'])) {
            $allowed_types = array('websocket', 'webrtc');
            $sanitized['elevenlabs_connection_type'] = in_array($input['elevenlabs_connection_type'], $allowed_types)
                ? $input['elevenlabs_connection_type']
                : 'websocket';
        }

        // Use Retell Chat toggle
        $sanitized['use_retell_chat'] = isset($input['use_retell_chat']) ? (bool) $input['use_retell_chat'] : false;

        // n8n-Retell settings (v1.2.0+)
        if (isset($input['n8n_base_url'])) {
            $sanitized['n8n_base_url'] = esc_url_raw($input['n8n_base_url']);
        }

        if (isset($input['n8n_voice_endpoint'])) {
            $sanitized['n8n_voice_endpoint'] = sanitize_text_field($input['n8n_voice_endpoint']);
        }

        if (isset($input['n8n_retell_agent_id'])) {
            $sanitized['n8n_retell_agent_id'] = sanitize_text_field($input['n8n_retell_agent_id']);
        }

        // n8n text chat mode
        if (isset($input['n8n_text_mode'])) {
            $allowed_modes = array('simple', 'session');
            $sanitized['n8n_text_mode'] = in_array($input['n8n_text_mode'], $allowed_modes)
                ? $input['n8n_text_mode']
                : 'simple';
        }

        if (isset($input['n8n_text_session_endpoint'])) {
            $sanitized['n8n_text_session_endpoint'] = sanitize_text_field($input['n8n_text_session_endpoint']);
        }

        if (isset($input['n8n_text_message_endpoint'])) {
            $sanitized['n8n_text_message_endpoint'] = sanitize_text_field($input['n8n_text_message_endpoint']);
        }

        if (isset($input['n8n_retell_text_agent_id'])) {
            $sanitized['n8n_retell_text_agent_id'] = sanitize_text_field($input['n8n_retell_text_agent_id']);
        }

        return $sanitized;
    }

    /**
     * Sanitize webhook/automation settings
     *
     * @param array $input Input data
     * @return array Sanitized data
     * @since 1.1.0
     */
    public function sanitize_automation_settings($input) {
        $sanitized = array();

        // Automation webhook URL
        if (isset($input['automation_webhook_url'])) {
            $sanitized['automation_webhook_url'] = esc_url_raw($input['automation_webhook_url']);
        }

        // Automation auth method
        if (isset($input['automation_auth_method'])) {
            $allowed_methods = array('none', 'bearer', 'api_key');
            $sanitized['automation_auth_method'] = in_array($input['automation_auth_method'], $allowed_methods)
                ? $input['automation_auth_method']
                : 'none';
        }

        // Automation auth token (encrypt)
        if (isset($input['automation_auth_token']) && !empty($input['automation_auth_token'])) {
            if (strpos($input['automation_auth_token'], '***') === false) {
                $sanitized['automation_auth_token'] = $this->encryption->encrypt(sanitize_text_field($input['automation_auth_token']));
            } else {
                $existing = get_option('antek_chat_automation_settings', array());
                $sanitized['automation_auth_token'] = isset($existing['automation_auth_token']) ? $existing['automation_auth_token'] : '';
            }
        }

        // Webhook auth method
        if (isset($input['webhook_auth_method'])) {
            $allowed_methods = array('none', 'api_key', 'hmac', 'basic');
            $sanitized['webhook_auth_method'] = in_array($input['webhook_auth_method'], $allowed_methods)
                ? $input['webhook_auth_method']
                : 'api_key';
        }

        // Webhook API key (encrypt)
        if (isset($input['webhook_api_key']) && !empty($input['webhook_api_key'])) {
            if (strpos($input['webhook_api_key'], '***') === false) {
                $sanitized['webhook_api_key'] = $this->encryption->encrypt(sanitize_text_field($input['webhook_api_key']));
            } else {
                $existing = get_option('antek_chat_automation_settings', array());
                $sanitized['webhook_api_key'] = isset($existing['webhook_api_key']) ? $existing['webhook_api_key'] : '';
            }
        }

        // Webhook secret (encrypt)
        if (isset($input['webhook_secret']) && !empty($input['webhook_secret'])) {
            if (strpos($input['webhook_secret'], '***') === false) {
                $sanitized['webhook_secret'] = $this->encryption->encrypt(sanitize_text_field($input['webhook_secret']));
            } else {
                $existing = get_option('antek_chat_automation_settings', array());
                $sanitized['webhook_secret'] = isset($existing['webhook_secret']) ? $existing['webhook_secret'] : '';
            }
        }

        // Basic auth username
        if (isset($input['webhook_basic_username'])) {
            $sanitized['webhook_basic_username'] = sanitize_text_field($input['webhook_basic_username']);
        }

        // Basic auth password (encrypt)
        if (isset($input['webhook_basic_password']) && !empty($input['webhook_basic_password'])) {
            if (strpos($input['webhook_basic_password'], '***') === false) {
                $sanitized['webhook_basic_password'] = $this->encryption->encrypt(sanitize_text_field($input['webhook_basic_password']));
            } else {
                $existing = get_option('antek_chat_automation_settings', array());
                $sanitized['webhook_basic_password'] = isset($existing['webhook_basic_password']) ? $existing['webhook_basic_password'] : '';
            }
        }

        // IP whitelist
        if (isset($input['webhook_ip_whitelist'])) {
            $sanitized['webhook_ip_whitelist'] = sanitize_textarea_field($input['webhook_ip_whitelist']);
        }

        return $sanitized;
    }

    /**
     * Sanitize advanced settings
     *
     * @param array $input Input data
     * @return array Sanitized data
     * @since 1.1.0
     */
    public function sanitize_advanced_settings($input) {
        $sanitized = array();

        // Rate limiting
        if (isset($input['rate_limit_messages_per_hour'])) {
            $sanitized['rate_limit_messages_per_hour'] = absint($input['rate_limit_messages_per_hour']);
            $sanitized['rate_limit_messages_per_hour'] = max(1, min(1000, $sanitized['rate_limit_messages_per_hour']));
        }

        if (isset($input['rate_limit_tokens_per_minute'])) {
            $sanitized['rate_limit_tokens_per_minute'] = absint($input['rate_limit_tokens_per_minute']);
            $sanitized['rate_limit_tokens_per_minute'] = max(1, min(100, $sanitized['rate_limit_tokens_per_minute']));
        }

        if (isset($input['rate_limit_uploads_per_hour'])) {
            $sanitized['rate_limit_uploads_per_hour'] = absint($input['rate_limit_uploads_per_hour']);
            $sanitized['rate_limit_uploads_per_hour'] = max(1, min(100, $sanitized['rate_limit_uploads_per_hour']));
        }

        // Async processing
        $sanitized['async_jobs_enabled'] = isset($input['async_jobs_enabled']) ? (bool) $input['async_jobs_enabled'] : false;

        if (isset($input['async_max_retries'])) {
            $sanitized['async_max_retries'] = absint($input['async_max_retries']);
            $sanitized['async_max_retries'] = max(0, min(10, $sanitized['async_max_retries']));
        }

        if (isset($input['async_callback_timeout'])) {
            $sanitized['async_callback_timeout'] = absint($input['async_callback_timeout']);
            $sanitized['async_callback_timeout'] = max(5, min(300, $sanitized['async_callback_timeout']));
        }

        if (isset($input['async_cleanup_days'])) {
            $sanitized['async_cleanup_days'] = absint($input['async_cleanup_days']);
            $sanitized['async_cleanup_days'] = max(1, min(365, $sanitized['async_cleanup_days']));
        }

        // Media upload
        if (isset($input['media_max_file_size_mb'])) {
            $sanitized['media_max_file_size_mb'] = absint($input['media_max_file_size_mb']);
            $sanitized['media_max_file_size_mb'] = max(1, min(500, $sanitized['media_max_file_size_mb']));
        }

        if (isset($input['media_allowed_types'])) {
            $allowed_types = array('image', 'audio', 'document', 'video');
            $sanitized['media_allowed_types'] = array_intersect((array) $input['media_allowed_types'], $allowed_types);
        }

        if (isset($input['media_storage_location'])) {
            $sanitized['media_storage_location'] = sanitize_text_field($input['media_storage_location']);
        }

        return $sanitized;
    }

    /**
     * AJAX handler: Cleanup old jobs
     *
     * @since 1.1.0
     */
    public function ajax_cleanup_jobs() {
        check_ajax_referer('antek_chat_cleanup', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'antek-chat-connector')));
            return;
        }

        global $wpdb;
        $jobs_table = $wpdb->prefix . 'antek_chat_jobs';
        $settings = get_option('antek_chat_advanced_settings', array());
        $cleanup_days = isset($settings['async_cleanup_days']) ? absint($settings['async_cleanup_days']) : 7;

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$jobs_table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $cleanup_days
        ));

        wp_send_json_success(array(
            'message' => sprintf(
                /* translators: %d: number of jobs deleted */
                _n('%d job deleted successfully.', '%d jobs deleted successfully.', $deleted, 'antek-chat-connector'),
                $deleted
            ),
            'deleted' => $deleted,
        ));
    }

    /**
     * AJAX handler: Cleanup old media
     *
     * @since 1.1.0
     */
    public function ajax_cleanup_media() {
        check_ajax_referer('antek_chat_cleanup', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'antek-chat-connector')));
            return;
        }

        $media_manager = new Antek_Chat_Media_Manager();
        $result = $media_manager->cleanup_old_media(90); // 90+ days

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            wp_send_json_success(array(
                'message' => sprintf(
                    /* translators: %d: number of files deleted */
                    _n('%d file deleted successfully.', '%d files deleted successfully.', $result, 'antek-chat-connector'),
                    $result
                ),
                'deleted' => $result,
            ));
        }
    }

    /**
     * AJAX handler: Test webhook configuration
     *
     * @since 1.1.0
     */
    public function ajax_test_webhook() {
        check_ajax_referer('antek_chat_test', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'antek-chat-connector')));
            return;
        }

        $provider = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : 'retell';
        $event_type = isset($_POST['event_type']) ? sanitize_text_field($_POST['event_type']) : 'call_started';

        // Build test payload based on provider
        $payload = array(
            'event' => $event_type,
            'timestamp' => current_time('mysql'),
            'test' => true,
        );

        $webhook_url = rest_url('antek-chat/v1/webhook');
        $authenticator = new Antek_Chat_Webhook_Authenticator();

        $headers = array('Content-Type' => 'application/json');

        // Add authentication headers based on method
        $settings = get_option('antek_chat_automation_settings', array());
        $auth_method = isset($settings['webhook_auth_method']) ? $settings['webhook_auth_method'] : 'api_key';

        if ($auth_method === 'api_key' && !empty($settings['webhook_api_key'])) {
            $encryption = new Antek_Chat_Encryption_Manager();
            $api_key = $encryption->decrypt($settings['webhook_api_key']);
            $headers['X-API-Key'] = $api_key;
        }

        $response = wp_remote_post($webhook_url, array(
            'headers' => $headers,
            'body' => wp_json_encode($payload),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
            return;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code >= 200 && $status_code < 300) {
            wp_send_json_success(array(
                'message' => __('Webhook test successful!', 'antek-chat-connector'),
                'status' => $status_code,
                'response' => $body,
            ));
        } else {
            wp_send_json_error(array(
                'message' => sprintf(__('Webhook test failed with status %d', 'antek-chat-connector'), $status_code),
                'status' => $status_code,
                'response' => $body,
            ));
        }
    }

    /**
     * AJAX handler: Test voice provider connection
     *
     * @since 1.1.0
     */
    public function ajax_test_provider() {
        check_ajax_referer('antek_chat_test', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'antek-chat-connector')));
            return;
        }

        $provider_name = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : 'retell';

        try {
            $provider = Antek_Chat_Voice_Provider_Factory::create($provider_name);

            if (!$provider->is_configured()) {
                wp_send_json_error(array(
                    'message' => __('Provider is not fully configured. Please check API key and agent ID.', 'antek-chat-connector'),
                ));
                return;
            }

            // Test by generating a token
            $token_result = $provider->generate_access_token();

            if (is_wp_error($token_result)) {
                wp_send_json_error(array(
                    'message' => $token_result->get_error_message(),
                ));
            } else {
                wp_send_json_success(array(
                    'message' => __('Provider connection successful!', 'antek-chat-connector'),
                    'provider' => $provider_name,
                ));
            }
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage(),
            ));
        }
    }
}
