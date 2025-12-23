<?php
/**
 * Voice Provider Interface
 *
 * Abstract base class for all voice providers (Retell AI, ElevenLabs)
 *
 * @package Antek_Chat_Connector
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Voice Provider Interface
 *
 * Defines the contract that all voice provider implementations must follow.
 * This enables runtime provider switching via Factory Pattern.
 *
 * @since 1.1.0
 */
abstract class Antek_Chat_Voice_Provider_Interface {

    /**
     * Provider name
     *
     * @var string
     */
    protected $provider_name;

    /**
     * Encryption manager instance
     *
     * @var Antek_Chat_Encryption_Manager
     */
    protected $encryption;

    /**
     * Constructor
     *
     * @since 1.1.0
     */
    public function __construct() {
        $this->encryption = new Antek_Chat_Encryption_Manager();
    }

    /**
     * Get provider name
     *
     * Returns a unique identifier for this provider (e.g., 'retell', 'elevenlabs')
     *
     * @return string Provider name
     * @since 1.1.0
     */
    abstract public function get_provider_name();

    /**
     * Get provider display label
     *
     * Returns a human-readable label for this provider
     *
     * @return string Provider label
     * @since 1.1.0
     */
    abstract public function get_provider_label();

    /**
     * Generate access token
     *
     * Generates a short-lived access token for frontend use.
     * This method should call the provider's API and return token data.
     *
     * @param array $options Optional parameters (agent_id override, etc.).
     * @return array|WP_Error Token data array or WP_Error on failure
     *                        Should include: access_token, expires_in, agent_id
     * @since 1.1.0
     */
    abstract public function generate_access_token($options = []);

    /**
     * Validate access token
     *
     * Checks if a token is valid (optional, not all providers support this)
     *
     * @param string $token Access token to validate.
     * @return bool True if valid, false otherwise
     * @since 1.1.0
     */
    public function validate_token($token) {
        // Default implementation: assume tokens are valid
        // Override in provider if validation endpoint exists
        return !empty($token);
    }

    /**
     * Get configuration for frontend
     *
     * Returns provider-specific configuration that will be passed to JavaScript.
     * IMPORTANT: Never include API keys - only public configuration.
     *
     * @return array Configuration array
     * @since 1.1.0
     */
    abstract public function get_client_config();

    /**
     * Get agent ID
     *
     * Returns the configured agent/voice ID for this provider
     *
     * @return string|null Agent ID or null if not configured
     * @since 1.1.0
     */
    abstract public function get_agent_id();

    /**
     * Check if provider is enabled
     *
     * Returns whether this provider is currently enabled and configured
     *
     * @return bool True if enabled and configured, false otherwise
     * @since 1.1.0
     */
    abstract public function is_enabled();

    /**
     * Check if provider is configured
     *
     * Returns whether this provider has all required settings configured
     *
     * @return bool True if configured, false otherwise
     * @since 1.1.0
     */
    abstract public function is_configured();

    /**
     * Get webhook signature verification method
     *
     * Returns the method used for webhook signature verification
     *
     * @return string 'hmac' or 'none'
     * @since 1.1.0
     */
    abstract public function get_webhook_signature_method();

    /**
     * Verify webhook signature
     *
     * Verifies that a webhook request came from this provider
     *
     * @param WP_REST_Request $request REST request object.
     * @return bool True if signature is valid, false otherwise
     * @since 1.1.0
     */
    abstract public function verify_webhook_signature($request);

    /**
     * Normalize webhook event
     *
     * Converts provider-specific webhook events into a standard format.
     * This enables frontend code to be provider-agnostic.
     *
     * Standard events:
     * - voice_connected
     * - voice_disconnected
     * - user_speaking: {is_speaking: bool}
     * - agent_speaking: {is_speaking: bool}
     * - transcript: {text: string, is_final: bool}
     * - agent_response: {text: string}
     * - error: {code: string, message: string}
     *
     * @param array $raw_event Provider-specific event data.
     * @return array Normalized event array with 'event' and 'data' keys
     * @since 1.1.0
     */
    abstract public function normalize_webhook_event($raw_event);

    /**
     * Get required settings fields
     *
     * Returns an array of setting field names required for this provider
     *
     * @return array Array of setting field names
     * @since 1.1.0
     */
    abstract public function get_required_settings();

    /**
     * Test provider connection
     *
     * Tests the API connection and credentials
     *
     * @return bool|WP_Error True on success, WP_Error on failure
     * @since 1.1.0
     */
    abstract public function test_connection();

    /**
     * Send text message to provider
     *
     * Sends a text message to the provider's conversational AI and returns a response
     * Used for text-based chat when provider is selected as chat provider
     *
     * @param string $message User message text.
     * @param array  $context Context data (session_id, history, user_id, page_url).
     * @return array|WP_Error Array with 'response' key or WP_Error on failure
     * @since 1.1.0
     */
    public function send_text_message($message, $context = []) {
        // Default implementation returns an error
        // Providers should override this if they support text chat
        return new WP_Error(
            'not_supported',
            __('Text chat not supported by this provider', 'antek-chat-connector')
        );
    }

    /**
     * Get provider capabilities
     *
     * Returns an array of capabilities supported by this provider
     *
     * @return array Array of capability names
     * @since 1.1.0
     */
    public function get_capabilities() {
        return [
            'voice_input',
            'voice_output',
            'transcription',
        ];
    }

    /**
     * Get provider metadata
     *
     * Returns metadata about the provider (latency, pricing, features, etc.)
     *
     * @return array Metadata array
     * @since 1.1.0
     */
    public function get_provider_metadata() {
        return [
            'name' => $this->get_provider_name(),
            'label' => $this->get_provider_label(),
            'capabilities' => $this->get_capabilities(),
            'enabled' => $this->is_enabled(),
            'configured' => $this->is_configured(),
        ];
    }

    /**
     * Get API key (decrypted)
     *
     * Helper method to retrieve and decrypt the API key for this provider
     *
     * @param string $option_name Option name containing encrypted key.
     * @return string|WP_Error Decrypted API key or WP_Error on failure
     * @since 1.1.0
     */
    protected function get_api_key($option_name) {
        $settings = get_option('antek_chat_voice_settings', []);

        if (empty($settings[$option_name])) {
            return new WP_Error(
                'api_key_not_configured',
                sprintf(
                    __('API key not configured for %s', 'antek-chat-connector'),
                    $this->get_provider_label()
                )
            );
        }

        $decrypted = $this->encryption->decrypt($settings[$option_name]);

        if (is_wp_error($decrypted)) {
            return $decrypted;
        }

        if (empty($decrypted)) {
            return new WP_Error(
                'api_key_decryption_failed',
                __('Failed to decrypt API key', 'antek-chat-connector')
            );
        }

        return $decrypted;
    }

    /**
     * Make HTTP request to provider API
     *
     * Helper method for making authenticated API requests
     *
     * @param string $url API endpoint URL.
     * @param array  $args Request arguments for wp_remote_post/get.
     * @param string $method HTTP method (GET, POST, etc.).
     * @return array|WP_Error Response array or WP_Error on failure
     * @since 1.1.0
     */
    protected function make_api_request($url, $args = [], $method = 'POST') {
        $defaults = [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ];

        $args = wp_parse_args($args, $defaults);

        // Choose appropriate WordPress HTTP function
        if ($method === 'POST') {
            $response = wp_remote_post($url, $args);
        } elseif ($method === 'GET') {
            $response = wp_remote_get($url, $args);
        } else {
            $args['method'] = $method;
            $response = wp_remote_request($url, $args);
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Check for HTTP errors
        if ($status_code < 200 || $status_code >= 300) {
            return new WP_Error(
                'api_request_failed',
                sprintf(
                    __('API request failed with status %d: %s', 'antek-chat-connector'),
                    $status_code,
                    $body
                ),
                ['status_code' => $status_code, 'body' => $body]
            );
        }

        // Parse JSON response
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error(
                'json_decode_error',
                sprintf(
                    __('Failed to parse API response: %s', 'antek-chat-connector'),
                    json_last_error_msg()
                )
            );
        }

        return $data;
    }

    /**
     * Log provider activity
     *
     * Helper method for logging provider-specific activity
     *
     * @param string $message Log message.
     * @param string $level Log level (info, warning, error).
     * @param array  $context Additional context data.
     * @since 1.1.0
     */
    protected function log($message, $level = 'info', $context = []) {
        if (!WP_DEBUG) {
            return;
        }

        $log_message = sprintf(
            '[Antek Chat][%s][%s] %s',
            strtoupper($level),
            $this->get_provider_name(),
            $message
        );

        if (!empty($context)) {
            $log_message .= ' | Context: ' . wp_json_encode($context);
        }

        error_log($log_message);
    }
}
