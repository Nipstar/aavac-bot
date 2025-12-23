<?php
/**
 * Encryption Manager Class
 *
 * Handles AES-256-CBC encryption/decryption for sensitive data like API keys
 *
 * @package Antek_Chat_Connector
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Encryption Manager class
 *
 * Provides secure encryption/decryption using AES-256-CBC with proper IV handling
 * and key versioning support for rotation scenarios.
 *
 * @since 1.1.0
 */
class Antek_Chat_Encryption_Manager {

    /**
     * Encryption method
     *
     * @var string
     */
    private $method = 'aes-256-cbc';

    /**
     * Current encryption key version
     *
     * @var int
     */
    private $current_version = 1;

    /**
     * Get encryption key for specified version
     *
     * Key hierarchy:
     * 1. Custom constant ANTEK_CHAT_ENCRYPTION_KEY (recommended for production)
     * 2. WordPress LOGGED_IN_KEY constant (fallback)
     * 3. Site URL hash (last resort, not recommended)
     *
     * @param int $version Key version number.
     * @return string Encryption key
     * @since 1.1.0
     */
    private function get_encryption_key($version = 1) {
        // Try custom encryption key first (recommended approach)
        if (defined('ANTEK_CHAT_ENCRYPTION_KEY') && !empty(ANTEK_CHAT_ENCRYPTION_KEY)) {
            return ANTEK_CHAT_ENCRYPTION_KEY;
        }

        // Fallback to WordPress security constant
        if (defined('LOGGED_IN_KEY') && !empty(LOGGED_IN_KEY)) {
            return LOGGED_IN_KEY;
        }

        // Last resort: site URL hash (not ideal, but better than nothing)
        // This will trigger a notice in admin to add proper encryption key
        $this->maybe_show_encryption_warning();
        return hash('sha256', get_site_url());
    }

    /**
     * Show admin notice if using weak encryption key
     *
     * @since 1.1.0
     */
    private function maybe_show_encryption_warning() {
        if (is_admin() && current_user_can('manage_options')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p><strong>' . esc_html__('Antek Chat Connector Security Notice:', 'antek-chat-connector') . '</strong> ';
                echo esc_html__('For enhanced security, please add a custom encryption key to your wp-config.php:', 'antek-chat-connector');
                echo '</p>';
                echo '<p><code>define(\'ANTEK_CHAT_ENCRYPTION_KEY\', \'' . esc_html($this->generate_random_key()) . '\');</code></p>';
                echo '</div>';
            });
        }
    }

    /**
     * Generate a random encryption key
     *
     * @return string Random 64-character hex string
     * @since 1.1.0
     */
    private function generate_random_key() {
        return bin2hex(random_bytes(32));
    }

    /**
     * Encrypt a value
     *
     * Uses AES-256-CBC encryption with:
     * - Random IV (16 bytes) prepended to ciphertext
     * - SHA-256 hashed encryption key (32 bytes)
     * - Base64 encoding for storage
     *
     * @param string $value Value to encrypt.
     * @param int    $version Key version to use (for rotation support).
     * @return string|WP_Error Encrypted value (base64) or WP_Error on failure
     * @since 1.1.0
     */
    public function encrypt($value, $version = 1) {
        // Empty values return empty string
        if (empty($value)) {
            return '';
        }

        // Get encryption key for version
        $key = $this->get_encryption_key($version);
        $key = hash('sha256', $key, true); // 256-bit key

        // Generate random IV
        $iv_length = openssl_cipher_iv_length($this->method);
        $iv = openssl_random_pseudo_bytes($iv_length);

        if ($iv === false) {
            return new WP_Error(
                'encryption_iv_failed',
                __('Failed to generate initialization vector', 'antek-chat-connector')
            );
        }

        // Encrypt the value
        $encrypted = openssl_encrypt(
            $value,
            $this->method,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            return new WP_Error(
                'encryption_failed',
                __('Failed to encrypt value', 'antek-chat-connector'),
                ['openssl_error' => openssl_error_string()]
            );
        }

        // Prepend IV to encrypted data and encode
        $result = base64_encode($iv . $encrypted);

        return $result;
    }

    /**
     * Decrypt a value
     *
     * Extracts IV from prepended data and decrypts using AES-256-CBC
     *
     * @param string $value Encrypted value (base64 encoded).
     * @param int    $version Key version to use (for rotation support).
     * @return string|WP_Error Decrypted value or WP_Error on failure
     * @since 1.1.0
     */
    public function decrypt($value, $version = 1) {
        // Empty values return empty string
        if (empty($value)) {
            return '';
        }

        // Decode from base64
        $data = base64_decode($value, true);

        if ($data === false) {
            return new WP_Error(
                'decryption_decode_failed',
                __('Failed to decode encrypted value', 'antek-chat-connector')
            );
        }

        // Get encryption key
        $key = $this->get_encryption_key($version);
        $key = hash('sha256', $key, true);

        // Extract IV
        $iv_length = openssl_cipher_iv_length($this->method);
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);

        // Decrypt
        $decrypted = openssl_decrypt(
            $encrypted,
            $this->method,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decrypted === false) {
            return new WP_Error(
                'decryption_failed',
                __('Failed to decrypt value', 'antek-chat-connector'),
                ['openssl_error' => openssl_error_string()]
            );
        }

        return $decrypted;
    }

    /**
     * Rotate encryption key
     *
     * Re-encrypts all stored sensitive data with a new key version
     * This is used when the encryption key changes in wp-config.php
     *
     * @param int $old_version Old key version.
     * @param int $new_version New key version.
     * @return int|WP_Error Number of records updated or WP_Error on failure
     * @since 1.1.0
     */
    public function rotate_key($old_version, $new_version) {
        global $wpdb;

        // Get all option names that store encrypted data
        $encrypted_options = [
            'antek_chat_voice_settings',
            'antek_chat_automation_settings',
        ];

        $updated_count = 0;

        foreach ($encrypted_options as $option_name) {
            $settings = get_option($option_name, []);

            if (empty($settings)) {
                continue;
            }

            // Fields that are encrypted within each option
            $encrypted_fields = $this->get_encrypted_fields($option_name);

            foreach ($encrypted_fields as $field) {
                if (!isset($settings[$field]) || empty($settings[$field])) {
                    continue;
                }

                // Decrypt with old key
                $decrypted = $this->decrypt($settings[$field], $old_version);

                if (is_wp_error($decrypted)) {
                    return $decrypted;
                }

                // Re-encrypt with new key
                $encrypted = $this->encrypt($decrypted, $new_version);

                if (is_wp_error($encrypted)) {
                    return $encrypted;
                }

                $settings[$field] = $encrypted;
                $updated_count++;
            }

            // Update option with re-encrypted values
            update_option($option_name, $settings);
        }

        // Update encryption key version in sessions table
        $table_name = $wpdb->prefix . 'antek_chat_sessions';
        $wpdb->update(
            $table_name,
            ['encryption_key_version' => $new_version],
            ['encryption_key_version' => $old_version],
            ['%d'],
            ['%d']
        );

        // Update current version
        update_option('antek_chat_encryption_version', $new_version);

        return $updated_count;
    }

    /**
     * Get list of encrypted fields for an option
     *
     * @param string $option_name Option name.
     * @return array Array of field names that are encrypted
     * @since 1.1.0
     */
    private function get_encrypted_fields($option_name) {
        $field_map = [
            'antek_chat_voice_settings' => [
                'retell_api_key',
                'elevenlabs_api_key',
            ],
            'antek_chat_automation_settings' => [
                'automation_auth_token',
                'webhook_api_key',
                'webhook_secret',
            ],
        ];

        return isset($field_map[$option_name]) ? $field_map[$option_name] : [];
    }

    /**
     * Check if encryption is properly configured
     *
     * @return bool True if using custom encryption key, false if using fallback
     * @since 1.1.0
     */
    public function is_encryption_secure() {
        return defined('ANTEK_CHAT_ENCRYPTION_KEY') && !empty(ANTEK_CHAT_ENCRYPTION_KEY);
    }

    /**
     * Get current encryption key version
     *
     * @return int Current version number
     * @since 1.1.0
     */
    public function get_current_version() {
        return (int) get_option('antek_chat_encryption_version', 1);
    }

    /**
     * Test encryption/decryption
     *
     * Used for health checks and debugging
     *
     * @return bool|WP_Error True if test passed, WP_Error on failure
     * @since 1.1.0
     */
    public function test_encryption() {
        $test_value = 'test_encryption_' . wp_generate_uuid4();

        // Encrypt
        $encrypted = $this->encrypt($test_value);

        if (is_wp_error($encrypted)) {
            return $encrypted;
        }

        // Decrypt
        $decrypted = $this->decrypt($encrypted);

        if (is_wp_error($decrypted)) {
            return $decrypted;
        }

        // Compare
        if ($decrypted !== $test_value) {
            return new WP_Error(
                'encryption_test_failed',
                __('Encryption test failed: decrypted value does not match original', 'antek-chat-connector')
            );
        }

        return true;
    }

    /**
     * Sanitize and encrypt API key during settings save
     *
     * Helper method for use in settings sanitization callbacks
     *
     * @param string $value API key value from form.
     * @param string $old_encrypted_value Existing encrypted value from database.
     * @return string Encrypted value or existing value if input is empty
     * @since 1.1.0
     */
    public function sanitize_api_key($value, $old_encrypted_value = '') {
        // If value is empty, keep existing encrypted value
        if (empty($value)) {
            return $old_encrypted_value;
        }

        // If value is a placeholder (common in password fields), keep existing
        if ($value === '••••••••' || $value === '********') {
            return $old_encrypted_value;
        }

        // Sanitize and encrypt new value
        $sanitized = sanitize_text_field($value);
        $encrypted = $this->encrypt($sanitized);

        if (is_wp_error($encrypted)) {
            // Log error and return old value
            error_log('Antek Chat: Encryption failed - ' . $encrypted->get_error_message());
            return $old_encrypted_value;
        }

        return $encrypted;
    }
}
