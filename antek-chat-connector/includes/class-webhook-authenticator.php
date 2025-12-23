<?php
/**
 * Webhook Authenticator Class
 *
 * Multi-authentication webhook verification
 *
 * @package Antek_Chat_Connector
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Ensure dependencies are loaded
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/class-encryption-manager.php';

/**
 * Webhook Authenticator class
 *
 * Provides multiple authentication methods for webhook verification:
 * - API Key (X-API-Key header)
 * - HMAC-SHA256 (X-Webhook-Signature header)
 * - Basic Auth (Authorization header)
 * - None (development only, shows warning)
 *
 * @since 1.1.0
 */
class Antek_Chat_Webhook_Authenticator {

    /**
     * Encryption manager instance
     *
     * @var Antek_Chat_Encryption_Manager
     */
    private $encryption;

    /**
     * Constructor
     *
     * @since 1.1.0
     */
    public function __construct() {
        $this->encryption = new Antek_Chat_Encryption_Manager();
    }

    /**
     * Verify webhook request
     *
     * Main authentication method that routes to appropriate verification
     *
     * @param WP_REST_Request $request REST request object.
     * @return bool|WP_Error True if verified, WP_Error on failure
     * @since 1.1.0
     */
    public function verify($request) {
        $auth_method = $this->get_auth_method();

        // Log authentication attempt
        $this->log_auth_attempt($request, $auth_method);

        switch ($auth_method) {
            case 'api_key':
                return $this->verify_api_key($request);

            case 'hmac':
                return $this->verify_hmac($request);

            case 'basic':
                return $this->verify_basic_auth($request);

            case 'none':
                // No authentication (development only)
                if (WP_DEBUG) {
                    error_log('[Antek Chat][WARNING] Webhook authentication disabled - use only in development');
                }
                return true;

            default:
                return new WP_Error(
                    'invalid_auth_method',
                    __('Invalid authentication method configured', 'antek-chat-connector')
                );
        }
    }

    /**
     * Verify API key authentication
     *
     * Checks X-API-Key header against stored encrypted key
     *
     * @param WP_REST_Request $request REST request object.
     * @return bool|WP_Error True if valid, WP_Error on failure
     * @since 1.1.0
     */
    private function verify_api_key($request) {
        // Get API key from header
        $provided_key = $request->get_header('X-API-Key');

        if (empty($provided_key)) {
            // Try alternative header names
            $provided_key = $request->get_header('X-Api-Key');
        }

        if (empty($provided_key)) {
            return new WP_Error(
                'api_key_missing',
                __('API key not provided in request', 'antek-chat-connector'),
                ['status' => 401]
            );
        }

        // Get stored API key
        $settings = get_option('antek_chat_automation_settings', []);

        if (empty($settings['webhook_api_key'])) {
            return new WP_Error(
                'api_key_not_configured',
                __('Webhook API key not configured', 'antek-chat-connector'),
                ['status' => 500]
            );
        }

        // Decrypt stored key
        $stored_key = $this->encryption->decrypt($settings['webhook_api_key']);

        if (is_wp_error($stored_key)) {
            return new WP_Error(
                'api_key_decryption_failed',
                __('Failed to decrypt stored API key', 'antek-chat-connector'),
                ['status' => 500]
            );
        }

        // Timing-safe comparison
        if (!hash_equals($stored_key, $provided_key)) {
            return new WP_Error(
                'api_key_invalid',
                __('Invalid API key', 'antek-chat-connector'),
                ['status' => 401]
            );
        }

        return true;
    }

    /**
     * Verify HMAC-SHA256 signature
     *
     * Verifies X-Webhook-Signature header using stored secret
     *
     * @param WP_REST_Request $request REST request object.
     * @return bool|WP_Error True if valid, WP_Error on failure
     * @since 1.1.0
     */
    private function verify_hmac($request) {
        // Get signature from header
        $provided_signature = $request->get_header('X-Webhook-Signature');

        if (empty($provided_signature)) {
            // Try alternative header names
            $provided_signature = $request->get_header('X-Hub-Signature-256');

            // GitHub-style format: "sha256=<hash>"
            if (!empty($provided_signature) && strpos($provided_signature, 'sha256=') === 0) {
                $provided_signature = substr($provided_signature, 7);
            }
        }

        if (empty($provided_signature)) {
            return new WP_Error(
                'signature_missing',
                __('Webhook signature not provided', 'antek-chat-connector'),
                ['status' => 401]
            );
        }

        // Get stored secret
        $settings = get_option('antek_chat_automation_settings', []);

        if (empty($settings['webhook_secret'])) {
            return new WP_Error(
                'secret_not_configured',
                __('Webhook secret not configured', 'antek-chat-connector'),
                ['status' => 500]
            );
        }

        // Decrypt stored secret
        $secret = $this->encryption->decrypt($settings['webhook_secret']);

        if (is_wp_error($secret)) {
            return new WP_Error(
                'secret_decryption_failed',
                __('Failed to decrypt webhook secret', 'antek-chat-connector'),
                ['status' => 500]
            );
        }

        // Get request body
        $payload = $request->get_body();

        // Calculate expected signature
        $expected_signature = hash_hmac('sha256', $payload, $secret);

        // Timing-safe comparison
        if (!hash_equals($expected_signature, $provided_signature)) {
            return new WP_Error(
                'signature_invalid',
                __('Invalid webhook signature', 'antek-chat-connector'),
                ['status' => 401]
            );
        }

        return true;
    }

    /**
     * Verify Basic Authentication
     *
     * Verifies Authorization: Basic header
     *
     * @param WP_REST_Request $request REST request object.
     * @return bool|WP_Error True if valid, WP_Error on failure
     * @since 1.1.0
     */
    private function verify_basic_auth($request) {
        // Get authorization header
        $auth_header = $request->get_header('Authorization');

        if (empty($auth_header)) {
            return new WP_Error(
                'auth_header_missing',
                __('Authorization header not provided', 'antek-chat-connector'),
                ['status' => 401]
            );
        }

        // Parse Basic Auth header
        if (strpos($auth_header, 'Basic ') !== 0) {
            return new WP_Error(
                'invalid_auth_format',
                __('Invalid authorization format (expected Basic)', 'antek-chat-connector'),
                ['status' => 401]
            );
        }

        $credentials = base64_decode(substr($auth_header, 6));

        if ($credentials === false) {
            return new WP_Error(
                'invalid_credentials_encoding',
                __('Invalid base64 encoding in credentials', 'antek-chat-connector'),
                ['status' => 401]
            );
        }

        list($username, $password) = array_pad(explode(':', $credentials, 2), 2, '');

        if (empty($username) || empty($password)) {
            return new WP_Error(
                'incomplete_credentials',
                __('Username or password missing', 'antek-chat-connector'),
                ['status' => 401]
            );
        }

        // Get stored credentials
        $settings = get_option('antek_chat_automation_settings', []);

        if (empty($settings['webhook_basic_username']) || empty($settings['webhook_basic_password'])) {
            return new WP_Error(
                'credentials_not_configured',
                __('Basic Auth credentials not configured', 'antek-chat-connector'),
                ['status' => 500]
            );
        }

        // Decrypt stored password
        $stored_password = $this->encryption->decrypt($settings['webhook_basic_password']);

        if (is_wp_error($stored_password)) {
            return new WP_Error(
                'password_decryption_failed',
                __('Failed to decrypt stored password', 'antek-chat-connector'),
                ['status' => 500]
            );
        }

        $stored_username = $settings['webhook_basic_username']; // Username not encrypted

        // Timing-safe comparison
        $username_valid = hash_equals($stored_username, $username);
        $password_valid = hash_equals($stored_password, $password);

        if (!$username_valid || !$password_valid) {
            return new WP_Error(
                'invalid_credentials',
                __('Invalid username or password', 'antek-chat-connector'),
                ['status' => 401]
            );
        }

        return true;
    }

    /**
     * Get configured authentication method
     *
     * @return string Authentication method (api_key, hmac, basic, none)
     * @since 1.1.0
     */
    private function get_auth_method() {
        $settings = get_option('antek_chat_automation_settings', []);

        $method = isset($settings['webhook_auth_method'])
            ? sanitize_text_field($settings['webhook_auth_method'])
            : 'api_key'; // Default to API key

        return $method;
    }

    /**
     * Check if request is duplicate (idempotency check)
     *
     * Uses transient cache to detect duplicate webhook deliveries
     *
     * @param WP_REST_Request $request REST request object.
     * @return bool True if duplicate, false if unique
     * @since 1.1.0
     */
    public function is_duplicate_request($request) {
        // Get request ID from header
        $request_id = $request->get_header('X-Request-ID');

        if (empty($request_id)) {
            // Try alternative header names
            $request_id = $request->get_header('X-Webhook-ID');
        }

        if (empty($request_id)) {
            // No request ID provided - generate from body hash
            $request_id = md5($request->get_body());
        }

        $request_id = sanitize_text_field($request_id);

        // Check if we've seen this request ID before
        $cache_key = 'antek_chat_webhook_' . md5($request_id);
        $seen = get_transient($cache_key);

        if ($seen !== false) {
            // This is a duplicate request
            return true;
        }

        // Mark request as seen (cache for 24 hours)
        set_transient($cache_key, time(), DAY_IN_SECONDS);

        return false;
    }

    /**
     * Log authentication attempt
     *
     * Records webhook authentication attempts for audit trail
     *
     * @param WP_REST_Request $request REST request object.
     * @param string          $auth_method Authentication method used.
     * @since 1.1.0
     */
    private function log_auth_attempt($request, $auth_method) {
        if (!WP_DEBUG) {
            return;
        }

        $log_data = [
            'timestamp' => current_time('mysql'),
            'method' => $auth_method,
            'ip' => $this->get_client_ip(),
            'user_agent' => $request->get_header('User-Agent'),
            'endpoint' => $request->get_route(),
        ];

        error_log(
            sprintf(
                '[Antek Chat][Webhook Auth] %s',
                wp_json_encode($log_data)
            )
        );
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     * @since 1.1.0
     */
    private function get_client_ip() {
        // Check for proxy headers
        $ip_headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = sanitize_text_field($_SERVER[$header]);
                // Take first IP if comma-separated list
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }

        return '0.0.0.0';
    }

    /**
     * Check IP whitelist
     *
     * Verifies request comes from whitelisted IP
     *
     * @param WP_REST_Request $request REST request object.
     * @return bool True if allowed, false if blocked
     * @since 1.1.0
     */
    public function check_ip_whitelist($request) {
        $settings = get_option('antek_chat_automation_settings', []);

        if (empty($settings['webhook_ip_whitelist'])) {
            // No whitelist configured - allow all
            return true;
        }

        $whitelist = $settings['webhook_ip_whitelist'];

        // Convert to array if string
        if (is_string($whitelist)) {
            $whitelist = array_map('trim', explode("\n", $whitelist));
        }

        if (empty($whitelist)) {
            return true;
        }

        $client_ip = $this->get_client_ip();

        // Check if IP is in whitelist
        foreach ($whitelist as $allowed_ip) {
            $allowed_ip = trim($allowed_ip);

            if (empty($allowed_ip)) {
                continue;
            }

            // Support CIDR notation
            if (strpos($allowed_ip, '/') !== false) {
                if ($this->ip_in_range($client_ip, $allowed_ip)) {
                    return true;
                }
            } elseif ($client_ip === $allowed_ip) {
                return true;
            }
        }

        // IP not in whitelist
        return false;
    }

    /**
     * Check if IP is in CIDR range
     *
     * @param string $ip IP address to check.
     * @param string $cidr CIDR range (e.g., 192.168.1.0/24).
     * @return bool True if in range
     * @since 1.1.0
     */
    private function ip_in_range($ip, $cidr) {
        list($subnet, $mask) = explode('/', $cidr);

        $ip_long = ip2long($ip);
        $subnet_long = ip2long($subnet);
        $mask_long = -1 << (32 - (int) $mask);

        return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
    }

    /**
     * Generate webhook API key
     *
     * Generates a cryptographically secure API key
     *
     * @param int $length Key length in bytes (default 32).
     * @return string Hex-encoded API key
     * @since 1.1.0
     */
    public static function generate_api_key($length = 32) {
        $bytes = random_bytes($length);
        return bin2hex($bytes);
    }

    /**
     * Generate webhook secret
     *
     * Generates a cryptographically secure secret for HMAC
     *
     * @param int $length Secret length in bytes (default 32).
     * @return string Base64-encoded secret
     * @since 1.1.0
     */
    public static function generate_secret($length = 32) {
        $bytes = random_bytes($length);
        return base64_encode($bytes);
    }
}
