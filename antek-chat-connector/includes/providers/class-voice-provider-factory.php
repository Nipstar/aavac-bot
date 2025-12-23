<?php
/**
 * Voice Provider Factory Class
 *
 * Factory Pattern implementation for voice provider instantiation
 *
 * @package Antek_Chat_Connector
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Ensure interface is loaded
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/interfaces/interface-voice-provider.php';

/**
 * Voice Provider Factory class
 *
 * Implements Factory Pattern for voice provider instantiation.
 * Enables runtime switching between providers without code changes.
 *
 * @since 1.1.0
 */
class Antek_Chat_Voice_Provider_Factory {

    /**
     * Provider registry
     *
     * Maps provider names to class names
     *
     * @var array
     */
    private static $providers = [
        'retell' => 'Antek_Chat_Retell_Provider',
        'n8n-retell' => 'Antek_Chat_N8n_Retell_Provider',
    ];

    /**
     * Cached provider instances
     *
     * @var array
     */
    private static $instances = [];

    /**
     * Create provider instance
     *
     * Factory method to instantiate a voice provider by name.
     * Uses singleton pattern to cache instances.
     *
     * @param string $type Provider name (retell, elevenlabs).
     * @param array  $config Optional configuration override.
     * @return Antek_Chat_Voice_Provider_Interface Provider instance
     * @throws Exception If provider not found or class doesn't exist.
     * @since 1.1.0
     */
    public static function create($type, $config = []) {
        // Sanitize provider type
        $type = sanitize_text_field($type);

        // Check if provider is registered
        if (!isset(self::$providers[$type])) {
            throw new Exception(
                sprintf(
                    __('Unknown voice provider: %s', 'antek-chat-connector'),
                    $type
                )
            );
        }

        // Check if already instantiated (singleton)
        $cache_key = $type . '_' . md5(wp_json_encode($config));
        if (isset(self::$instances[$cache_key])) {
            return self::$instances[$cache_key];
        }

        // Get class name
        $class_name = self::$providers[$type];

        // Load provider file if not already loaded
        $provider_file = ANTEK_CHAT_PLUGIN_DIR . 'includes/providers/class-' . $type . '-provider.php';

        if (!class_exists($class_name)) {
            if (!file_exists($provider_file)) {
                throw new Exception(
                    sprintf(
                        __('Provider file not found: %s', 'antek-chat-connector'),
                        $provider_file
                    )
                );
            }
            require_once $provider_file;
        }

        // Instantiate provider
        $provider = new $class_name();

        // Verify it implements the interface
        if (!($provider instanceof Antek_Chat_Voice_Provider_Interface)) {
            throw new Exception(
                sprintf(
                    __('Provider %s does not implement Voice_Provider_Interface', 'antek-chat-connector'),
                    $class_name
                )
            );
        }

        // Cache and return
        self::$instances[$cache_key] = $provider;

        return $provider;
    }

    /**
     * Get enabled provider
     *
     * Returns the currently configured and enabled provider instance
     *
     * @return Antek_Chat_Voice_Provider_Interface|WP_Error Provider instance or error
     * @since 1.1.0
     */
    public static function get_enabled_provider() {
        $settings = get_option('antek_chat_voice', []);

        // Check if voice is enabled
        if (empty($settings['voice_enabled'])) {
            return new WP_Error(
                'voice_disabled',
                __('Voice functionality is not enabled', 'antek-chat-connector')
            );
        }

        // Get selected provider
        $provider_name = isset($settings['voice_provider'])
            ? sanitize_text_field($settings['voice_provider'])
            : 'retell'; // Default fallback

        // Check if provider exists
        if (!isset(self::$providers[$provider_name])) {
            return new WP_Error(
                'invalid_provider',
                sprintf(
                    __('Invalid voice provider configured: %s', 'antek-chat-connector'),
                    $provider_name
                )
            );
        }

        try {
            $provider = self::create($provider_name);

            // Verify provider is configured
            if (!$provider->is_configured()) {
                return new WP_Error(
                    'provider_not_configured',
                    sprintf(
                        __('Voice provider %s is not fully configured', 'antek-chat-connector'),
                        $provider->get_provider_label()
                    )
                );
            }

            return $provider;

        } catch (Exception $e) {
            return new WP_Error(
                'provider_instantiation_failed',
                $e->getMessage()
            );
        }
    }

    /**
     * Get all available providers
     *
     * Returns array of registered provider names
     *
     * @return array Provider names
     * @since 1.1.0
     */
    public static function get_available_providers() {
        return array_keys(self::$providers);
    }

    /**
     * Get provider info
     *
     * Returns metadata about all available providers
     *
     * @param bool $include_disabled Include disabled providers.
     * @return array Provider information
     * @since 1.1.0
     */
    public static function get_provider_info($include_disabled = true) {
        $provider_info = [];

        foreach (self::$providers as $name => $class) {
            try {
                $provider = self::create($name);

                $info = [
                    'name' => $provider->get_provider_name(),
                    'label' => $provider->get_provider_label(),
                    'enabled' => $provider->is_enabled(),
                    'configured' => $provider->is_configured(),
                    'capabilities' => $provider->get_capabilities(),
                    'metadata' => $provider->get_provider_metadata(),
                ];

                // Skip if disabled and not including disabled
                if (!$include_disabled && !$info['enabled']) {
                    continue;
                }

                $provider_info[$name] = $info;

            } catch (Exception $e) {
                // Log error but continue
                if (WP_DEBUG) {
                    error_log(sprintf(
                        '[Antek Chat] Failed to load provider %s: %s',
                        $name,
                        $e->getMessage()
                    ));
                }
            }
        }

        return $provider_info;
    }

    /**
     * Register custom provider
     *
     * Allows third-party code to register additional providers
     *
     * @param string $name Provider name (slug).
     * @param string $class_name Full class name.
     * @return bool True on success, false on failure
     * @since 1.1.0
     */
    public static function register_provider($name, $class_name) {
        $name = sanitize_text_field($name);

        // Check if already registered
        if (isset(self::$providers[$name])) {
            return false;
        }

        // Verify class exists
        if (!class_exists($class_name)) {
            return false;
        }

        // Verify class implements interface
        $reflection = new ReflectionClass($class_name);
        if (!$reflection->implementsInterface('Antek_Chat_Voice_Provider_Interface')) {
            return false;
        }

        // Register provider
        self::$providers[$name] = $class_name;

        return true;
    }

    /**
     * Unregister provider
     *
     * Removes a provider from the registry
     *
     * @param string $name Provider name.
     * @return bool True on success, false on failure
     * @since 1.1.0
     */
    public static function unregister_provider($name) {
        $name = sanitize_text_field($name);

        if (!isset(self::$providers[$name])) {
            return false;
        }

        // Remove from registry
        unset(self::$providers[$name]);

        // Clear cached instances
        foreach (self::$instances as $key => $instance) {
            if (strpos($key, $name . '_') === 0) {
                unset(self::$instances[$key]);
            }
        }

        return true;
    }

    /**
     * Test provider connection
     *
     * Helper method to test a specific provider's connection
     *
     * @param string $provider_name Provider name to test.
     * @return bool|WP_Error True on success, WP_Error on failure
     * @since 1.1.0
     */
    public static function test_provider_connection($provider_name) {
        $provider_name = sanitize_text_field($provider_name);

        try {
            $provider = self::create($provider_name);
            return $provider->test_connection();

        } catch (Exception $e) {
            return new WP_Error(
                'provider_test_failed',
                $e->getMessage()
            );
        }
    }

    /**
     * Get provider by name
     *
     * Convenience method to get a provider instance by name
     *
     * @param string $provider_name Provider name.
     * @return Antek_Chat_Voice_Provider_Interface|WP_Error Provider or error
     * @since 1.1.0
     */
    public static function get_provider($provider_name) {
        $provider_name = sanitize_text_field($provider_name);

        if (!isset(self::$providers[$provider_name])) {
            return new WP_Error(
                'invalid_provider',
                sprintf(
                    __('Provider not found: %s', 'antek-chat-connector'),
                    $provider_name
                )
            );
        }

        try {
            return self::create($provider_name);
        } catch (Exception $e) {
            return new WP_Error(
                'provider_creation_failed',
                $e->getMessage()
            );
        }
    }

    /**
     * Clear provider cache
     *
     * Clears all cached provider instances
     * Useful when settings change
     *
     * @since 1.1.0
     */
    public static function clear_cache() {
        self::$instances = [];
    }

    /**
     * Get provider comparison
     *
     * Returns comparison table data for admin UI
     *
     * @return array Comparison data
     * @since 1.1.0
     */
    public static function get_provider_comparison() {
        return [
            'retell' => [
                'name' => __('Retell AI', 'antek-chat-connector'),
                'latency' => '~800ms',
                'sample_rate' => '24kHz',
                'pricing' => '$0.07/min + LLM',
                'features' => [
                    __('Native telephony', 'antek-chat-connector'),
                    __('Post-call analysis', 'antek-chat-connector'),
                    __('SOC2/HIPAA compliant', 'antek-chat-connector'),
                    __('Text and voice chat', 'antek-chat-connector'),
                ],
                'best_for' => __('Phone systems, compliance-critical applications, unified text/voice conversations', 'antek-chat-connector'),
            ],
        ];
    }
}
