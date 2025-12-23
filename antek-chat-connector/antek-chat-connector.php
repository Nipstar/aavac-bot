<?php
/**
 * Plugin Name: AAVAC Bot
 * Plugin URI: https://www.antekautomation.com
 * Description: Advanced AI Voice & Chat connector powered by Retell AI, with secure encryption, media uploads, and enterprise-grade webhook authentication
 * Version: 1.2.3
 * Author: Antek Automation
 * Author URI: https://www.antekautomation.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: antek-chat-connector
 * Domain Path: /languages
 *
 * @package Antek_Chat_Connector
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ANTEK_CHAT_VERSION', '1.2.3');
define('ANTEK_CHAT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ANTEK_CHAT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ANTEK_CHAT_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include core classes
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/class-plugin-core.php';
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/class-admin-settings.php';
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/class-webhook-handler.php';
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/class-widget-renderer.php';
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/class-session-manager.php';
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/class-debug-logger.php';
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/class-theme-color-detector.php';

// Include new multimodal classes (v1.1.0+)
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/class-encryption-manager.php';
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/class-rate-limiter.php';
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/class-multimodal-session-manager.php';
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/database/class-database-migrator.php';

// Include voice provider classes (v1.1.0+)
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/interfaces/interface-voice-provider.php';
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/providers/class-retell-provider.php';
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/providers/class-n8n-retell-provider.php';
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/providers/class-voice-provider-factory.php';

// Include REST API controller (v1.1.0+)
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/class-rest-api-controller.php';

// Include webhook and async processing classes (v1.1.0+)
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/class-webhook-authenticator.php';
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/class-async-job-processor.php';
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/class-media-manager.php';

/**
 * Migrate voice settings from old to new structure
 *
 * @since 1.2.2
 */
function antek_chat_migrate_voice_settings() {
    $old_settings = get_option('antek_chat_voice_settings', []);
    $new_settings = get_option('antek_chat_voice', []);

    // Only migrate if new settings empty but old settings exist
    if (empty($new_settings) && !empty($old_settings)) {
        $migrated = [
            'enabled' => $old_settings['voice_enabled'] ?? false,
            'retell_agent_id' => $old_settings['retell_agent_id'] ?? '',
            'n8n_voice_token_url' => $old_settings['n8n_voice_token_url'] ?? '',
        ];
        update_option('antek_chat_voice', $migrated);
        error_log('AAVAC Bot: Migrated voice settings from v1.1.0 to v1.2.2');
    }
}
add_action('plugins_loaded', 'antek_chat_migrate_voice_settings');

/**
 * Activation hook - sets up plugin on activation
 *
 * @since 1.0.0
 * @updated 1.1.0 Added database migrations and multimodal options
 */
function antek_chat_activate() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'antek_chat_sessions';

    // Create base sessions table (backward compatible)
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(64) UNIQUE NOT NULL,
        user_id BIGINT UNSIGNED NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        conversation_data LONGTEXT,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        INDEX (session_id),
        INDEX (user_id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    // Create debug logs table
    require_once plugin_dir_path(__FILE__) . 'includes/class-debug-logger.php';
    Antek_Chat_Debug_Logger::create_table();

    // Run database migrations for v1.1.0+ (adds multimodal support)
    $migrator = new Antek_Chat_Database_Migrator();
    $migration_result = $migrator->run_migrations();

    // Log activation
    Antek_Chat_Debug_Logger::log('system', 'Plugin activated successfully', 'info', array('version' => '1.1.0'));

    if (is_wp_error($migration_result)) {
        // Log error but don't fail activation
        error_log('Antek Chat: Database migration error - ' . $migration_result->get_error_message());
    }

    // Set default options if they don't exist
    if (!get_option('antek_chat_settings')) {
        add_option('antek_chat_settings', array(
            'n8n_webhook_url' => '',
            'widget_enabled' => true,
            'voice_enabled' => false,
        ));
    }

    if (!get_option('antek_chat_appearance')) {
        add_option('antek_chat_appearance', array(
            'primary_color' => '#FF6B4A',
            'secondary_color' => '#8FA68E',
            'background_color' => '#FDFBF6',
            'text_color' => '#2C2C2C',
            'border_radius' => '12px',
            'widget_position' => 'bottom-right',
            'widget_size' => 'medium',
            'custom_css' => '',
            'font_family' => 'inherit',
        ));
    }

    if (!get_option('antek_chat_popup')) {
        add_option('antek_chat_popup', array(
            'popup_enabled' => false,
            'popup_delay' => 3000,
            'popup_trigger' => 'time',
            'popup_message' => '',
            'popup_pages' => array('all'),
            'popup_frequency' => 'once',
        ));
    }

    // Add new voice provider settings (v1.1.0+)
    if (!get_option('antek_chat_voice_settings')) {
        add_option('antek_chat_voice_settings', array(
            'voice_enabled' => false,
            'voice_provider' => 'retell',
            'retell_api_key' => '',
            'retell_agent_id' => '',
            'retell_chat_agent_id' => '', // Optional separate agent for text chat
            'use_retell_chat' => true, // Use Retell for text chat by default
            // n8n-Retell provider settings (v1.2.0+)
            'n8n_base_url' => '',
            'n8n_voice_endpoint' => '/webhook/wordpress-retell-create-call',
            'n8n_retell_agent_id' => '',
            'n8n_text_mode' => 'simple',
            'n8n_text_session_endpoint' => '/webhook/retell-create-chat-session',
            'n8n_text_message_endpoint' => '/webhook/retell-send-message',
            'n8n_retell_text_agent_id' => '',
        ));
    }

    // Add new automation/webhook settings (v1.1.0+)
    if (!get_option('antek_chat_automation_settings')) {
        add_option('antek_chat_automation_settings', array(
            'automation_webhook_url' => '',
            'automation_auth_method' => 'none', // none, bearer, api_key
            'automation_auth_token' => '',
            'webhook_auth_method' => 'api_key', // none, api_key, hmac, basic
            'webhook_api_key' => '',
            'webhook_secret' => '',
        ));
    }

    // Add advanced settings (v1.1.0+)
    if (!get_option('antek_chat_advanced_settings')) {
        add_option('antek_chat_advanced_settings', array(
            // Rate limiting
            'rate_limit_messages_per_hour' => 50,
            'rate_limit_tokens_per_minute' => 10,
            'rate_limit_uploads_per_hour' => 10,
            // Async processing
            'async_jobs_enabled' => true,
            'async_max_retries' => 3,
            'async_callback_timeout' => 30,
            'async_cleanup_days' => 7,
            // Media upload
            'media_max_file_size_mb' => 50,
            'media_allowed_types' => array('image', 'audio', 'document'),
            'media_storage_location' => 'wp-content/antek-media',
        ));
    }

    // Initialize encryption version
    if (!get_option('antek_chat_encryption_version')) {
        add_option('antek_chat_encryption_version', 1);
    }

    // Add new restructured settings (v1.2.1+)
    if (!get_option('antek_chat_connection')) {
        add_option('antek_chat_connection', array(
            'widget_enabled' => true,
            'chat_mode' => 'n8n', // 'n8n' or 'retell'
            'n8n_webhook_url' => '',
        ));
    }

    if (!get_option('antek_chat_retell_text')) {
        add_option('antek_chat_retell_text', array(
            'enabled' => false,
            'retell_agent_id' => '',
            'n8n_create_session_url' => '',
            'n8n_send_message_url' => '',
        ));
    }

    if (!get_option('antek_chat_voice')) {
        add_option('antek_chat_voice', array(
            'enabled' => false,
            'retell_agent_id' => '',
            'n8n_voice_token_url' => '',
        ));
    }

    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'antek_chat_activate');

/**
 * Deactivation hook - cleanup on deactivation
 *
 * @since 1.0.0
 */
function antek_chat_deactivate() {
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'antek_chat_deactivate');

/**
 * Initialize plugin
 *
 * @since 1.0.0
 * @updated 1.1.0 Added REST API initialization
 */
function antek_chat_init() {
    // Load plugin text domain for translations
    load_plugin_textdomain('antek-chat-connector', false, dirname(ANTEK_CHAT_PLUGIN_BASENAME) . '/languages');

    // Initialize core plugin
    $plugin = new Antek_Chat_Plugin_Core();
    $plugin->run();
}
add_action('plugins_loaded', 'antek_chat_init');

/**
 * Register REST API routes
 *
 * @since 1.1.0
 */
function antek_chat_register_rest_routes() {
    $rest_controller = new Antek_Chat_REST_API_Controller();
    $rest_controller->register_routes();
}
add_action('rest_api_init', 'antek_chat_register_rest_routes');

/**
 * Process async job
 *
 * Handles background job processing via WordPress cron
 *
 * @param string $job_id Job ID to process.
 * @since 1.1.0
 */
function antek_chat_process_async_job($job_id) {
    $processor = new Antek_Chat_Async_Job_Processor();
    $processor->process_job($job_id);
}
add_action('antek_chat_process_job', 'antek_chat_process_async_job');

/**
 * Shortcode: [antek_chat]
 *
 * @param array $atts Shortcode attributes
 * @return string Widget HTML
 * @since 1.0.0
 */
function antek_chat_shortcode($atts) {
    $atts = shortcode_atts(array(
        'position' => null,
        'voice_enabled' => null,
    ), $atts, 'antek_chat');

    ob_start();
    $renderer = new Antek_Chat_Widget_Renderer();
    $renderer->render($atts);
    return ob_get_clean();
}
add_shortcode('antek_chat', 'antek_chat_shortcode');

/**
 * Template tag for theme integration
 *
 * @param array $args Optional arguments to override settings
 * @since 1.0.0
 */
function antek_chat_widget($args = array()) {
    $renderer = new Antek_Chat_Widget_Renderer();
    $renderer->render($args);
}
