<?php
/**
 * Webhook Handler Class
 *
 * Handles communication with n8n webhook
 *
 * @package Antek_Chat_Connector
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Webhook handler class
 *
 * @since 1.0.0
 */
class Antek_Chat_Webhook_Handler {

    /**
     * Webhook URL
     *
     * @var string
     */
    private $webhook_url;

    /**
     * Initialize the webhook handler
     *
     * @since 1.0.0
     */
    public function __construct() {
        $settings = get_option('antek_chat_settings');
        $this->webhook_url = isset($settings['n8n_webhook_url']) ? $settings['n8n_webhook_url'] : '';
    }

    /**
     * Send message to n8n webhook
     *
     * @param string $session_id Session ID
     * @param string $message User message
     * @param array $metadata Additional metadata
     * @return array|WP_Error Response data or error
     * @since 1.0.0
     */
    public function send_message($session_id, $message, $metadata = array()) {
        if (empty($this->webhook_url)) {
            return new WP_Error('no_webhook', __('Webhook URL not configured', 'antek-chat-connector'));
        }

        $payload = array(
            'session_id' => sanitize_text_field($session_id),
            'message' => sanitize_text_field($message),
            'timestamp' => current_time('timestamp'),
            'site_url' => get_site_url(),
            'metadata' => $metadata,
        );

        $response = wp_remote_post($this->webhook_url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode($payload),
            'timeout' => 30,
            'sslverify' => true,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code < 200 || $response_code >= 300) {
            return new WP_Error(
                'webhook_error',
                sprintf(
                    /* translators: %d: HTTP response code */
                    __('Webhook returned error code: %d', 'antek-chat-connector'),
                    $response_code
                )
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_response', __('Invalid JSON response from webhook', 'antek-chat-connector'));
        }

        // Normalize n8n response format
        // Handle array format: [{"output": "text"}] -> {"response": "text", "metadata": {}}
        if (is_array($data) && isset($data[0]) && is_array($data[0])) {
            if (isset($data[0]['output'])) {
                $data = array(
                    'response' => $data[0]['output'],
                    'metadata' => isset($data[0]['metadata']) ? $data[0]['metadata'] : array(),
                );
            }
        }

        // Ensure response field exists
        if (!isset($data['response'])) {
            return new WP_Error('invalid_response', __('Webhook response missing "response" field', 'antek-chat-connector'));
        }

        return $data;
    }

    /**
     * Receive webhook from n8n (for async responses)
     *
     * @since 1.0.0
     */
    public function receive_webhook() {
        $payload = file_get_contents('php://input');
        $data = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => __('Invalid JSON', 'antek-chat-connector')));
            return;
        }

        // Store response for session
        // This could be used for async processing in the future

        wp_send_json_success($data);
    }
}
