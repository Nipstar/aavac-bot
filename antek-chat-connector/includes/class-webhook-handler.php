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
     * Send message (routes to appropriate handler based on chat mode)
     *
     * @param string $session_id Session ID
     * @param string $message User message
     * @param array $metadata Additional metadata
     * @return array|WP_Error Response data or error
     * @since 1.0.0
     * @updated 1.2.1 Added support for multiple chat modes
     */
    public function send_message($session_id, $message, $metadata = array()) {
        $connection_settings = get_option('antek_chat_connection', array());
        $chat_mode = isset($connection_settings['chat_mode']) ? $connection_settings['chat_mode'] : 'n8n';

        if ($chat_mode === 'retell') {
            return $this->handle_retell_text_message($session_id, $message, $metadata);
        } else {
            return $this->handle_n8n_message($session_id, $message, $metadata);
        }
    }

    /**
     * Handle n8n webhook message
     *
     * @param string $session_id Session ID
     * @param string $message User message
     * @param array $metadata Additional metadata
     * @return array|WP_Error Response data or error
     * @since 1.2.1
     */
    private function handle_n8n_message($session_id, $message, $metadata = array()) {
        $connection_settings = get_option('antek_chat_connection', array());
        $webhook_url = isset($connection_settings['n8n_webhook_url']) ? $connection_settings['n8n_webhook_url'] : '';

        if (empty($webhook_url)) {
            return array(
                'response' => __('Chat not configured. Please set n8n webhook URL in settings.', 'antek-chat-connector')
            );
        }

        $payload = array(
            'session_id' => sanitize_text_field($session_id),
            'message' => sanitize_text_field($message),
            'timestamp' => current_time('timestamp'),
            'site_url' => get_site_url(),
            'metadata' => $metadata,
        );

        $response = wp_remote_post($webhook_url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode($payload),
            'timeout' => 30,
            'sslverify' => true,
        ));

        if (is_wp_error($response)) {
            return array(
                'response' => __('Connection error. Please try again.', 'antek-chat-connector')
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code < 200 || $response_code >= 300) {
            return array(
                'response' => __('Service temporarily unavailable. Please try again.', 'antek-chat-connector')
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'response' => __('Unable to process response. Please try again.', 'antek-chat-connector')
            );
        }

        // Normalize n8n response format
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
            return array(
                'response' => __('No response received. Please try again.', 'antek-chat-connector')
            );
        }

        return $data;
    }

    /**
     * Handle Retell Text Chat message
     *
     * @param string $session_id Session ID
     * @param string $message User message
     * @param array $metadata Additional metadata
     * @return array|WP_Error Response data or error
     * @since 1.2.1
     */
    private function handle_retell_text_message($session_id, $message, $metadata = array()) {
        $retell_settings = get_option('antek_chat_retell_text', array());

        if (!isset($retell_settings['enabled']) || !$retell_settings['enabled']) {
            return array(
                'response' => __('Retell Text Chat not enabled. Please enable it in settings.', 'antek-chat-connector')
            );
        }

        // Get agent ID from settings
        $agent_id = isset($retell_settings['retell_agent_id']) ? $retell_settings['retell_agent_id'] : '';

        if (empty($agent_id)) {
            error_log('AAVAC Bot: Retell agent ID not configured');
            return array(
                'response' => __('Retell agent not configured. Please check settings.', 'antek-chat-connector')
            );
        }

        // Get or create chat session
        $chat_id = get_transient('retell_chat_' . $session_id);

        if (!$chat_id) {
            // Create new Retell chat session
            $create_url = isset($retell_settings['n8n_create_session_url']) ? $retell_settings['n8n_create_session_url'] : '';
            if (empty($create_url)) {
                error_log('AAVAC Bot: Retell session endpoint not configured');
                return array(
                    'response' => __('Retell session endpoint not configured.', 'antek-chat-connector')
                );
            }

            // CRITICAL FIX: Properly send agent_id in request body
            $create_request_body = array(
                'agent_id' => $agent_id,
                'user_id' => get_current_user_id(),
                'session_id' => $session_id,
            );

            error_log('AAVAC Bot: Creating Retell session with data: ' . wp_json_encode($create_request_body));

            $create_response = wp_remote_post($create_url, array(
                'headers' => array('Content-Type' => 'application/json'),
                'body' => wp_json_encode($create_request_body),
                'timeout' => 10,
            ));

            if (is_wp_error($create_response)) {
                error_log('AAVAC Bot: n8n session request failed - ' . $create_response->get_error_message());
                return array(
                    'response' => __('Failed to create chat session. Please try again.', 'antek-chat-connector')
                );
            }

            $http_code = wp_remote_retrieve_response_code($create_response);
            $body = wp_remote_retrieve_body($create_response);

            error_log('AAVAC Bot: n8n session response (' . $http_code . '): ' . $body);

            if ($http_code !== 200) {
                error_log('AAVAC Bot: n8n returned error code: ' . $http_code);
                return array(
                    'response' => __('Chat service returned an error.', 'antek-chat-connector')
                );
            }

            $create_data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('AAVAC Bot: Failed to parse n8n response: ' . json_last_error_msg());
                return array(
                    'response' => __('Invalid response from chat service.', 'antek-chat-connector')
                );
            }

            $chat_id = isset($create_data['chat_id']) ? $create_data['chat_id'] : '';

            if (empty($chat_id)) {
                error_log('AAVAC Bot: No chat_id in response: ' . $body);
                return array(
                    'response' => __('Invalid session response. Please try again.', 'antek-chat-connector')
                );
            }

            // Cache chat_id for 24 hours
            set_transient('retell_chat_' . $session_id, $chat_id, 24 * HOUR_IN_SECONDS);

            error_log('AAVAC Bot: Chat session created - ID: ' . $chat_id);
        }

        // Send message to existing chat session
        $send_url = isset($retell_settings['n8n_send_message_url']) ? $retell_settings['n8n_send_message_url'] : '';
        if (empty($send_url)) {
            error_log('AAVAC Bot: Retell message endpoint not configured');
            return array(
                'response' => __('Retell message endpoint not configured.', 'antek-chat-connector')
            );
        }

        $send_request_body = array(
            'chat_id' => $chat_id,
            'message' => $message,
        );

        error_log('AAVAC Bot: Sending message with data: ' . wp_json_encode($send_request_body));

        $send_response = wp_remote_post($send_url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode($send_request_body),
            'timeout' => 15,
        ));

        if (is_wp_error($send_response)) {
            error_log('AAVAC Bot: Message send failed - ' . $send_response->get_error_message());
            return array(
                'response' => __('Failed to send message. Please try again.', 'antek-chat-connector')
            );
        }

        $http_code = wp_remote_retrieve_response_code($send_response);
        $body = wp_remote_retrieve_body($send_response);

        error_log('AAVAC Bot: Message response (' . $http_code . '): ' . $body);

        if ($http_code !== 200) {
            error_log('AAVAC Bot: n8n returned error code: ' . $http_code);
            return array(
                'response' => __('Chat service returned an error.', 'antek-chat-connector')
            );
        }

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('AAVAC Bot: Failed to parse response: ' . json_last_error_msg());
            return array(
                'response' => __('Invalid response from chat service.', 'antek-chat-connector')
            );
        }

        return array(
            'response' => isset($data['response']) ? $data['response'] : __('No response received.', 'antek-chat-connector'),
            'metadata' => isset($data['metadata']) ? $data['metadata'] : array()
        );
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
