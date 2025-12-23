<?php
/**
 * n8n-Retell Provider Class
 *
 * Implements voice provider interface for Retell AI via n8n proxy
 * Calls n8n webhooks that proxy to Retell AI
 *
 * @package Antek_Chat_Connector
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Ensure interface is loaded
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/interfaces/interface-voice-provider.php';

/**
 * n8n-Retell Provider class
 *
 * Provides integration with Retell AI via n8n middleware:
 * - Token generation for web calls via n8n
 * - Text chat via n8n (simple webhook or session-based)
 * - Event normalization (reuses Retell patterns)
 * - 24kHz sample rate (Retell standard)
 *
 * @since 1.2.0
 */
class Antek_Chat_N8n_Retell_Provider extends Antek_Chat_Voice_Provider_Interface {

    /**
     * Get provider name
     *
     * @return string
     * @since 1.2.0
     */
    public function get_provider_name() {
        return 'n8n-retell';
    }

    /**
     * Get provider display label
     *
     * @return string
     * @since 1.2.0
     */
    public function get_provider_label() {
        return __('n8n (Retell Proxy)', 'antek-chat-connector');
    }

    /**
     * Generate access token via n8n
     *
     * Calls n8n webhook that creates Retell web call and returns access token
     * n8n MUST return genuine Retell access token for frontend SDK
     *
     * @param array $options Optional parameters.
     * @return array|WP_Error Token data or WP_Error
     * @since 1.2.0
     */
    public function generate_access_token($options = []) {
        // Get n8n configuration
        $settings = get_option('antek_chat_voice', []);
        $n8n_base_url = $settings['n8n_base_url'] ?? '';
        $n8n_voice_endpoint = $settings['n8n_voice_endpoint'] ?? '/webhook/wordpress-retell-create-call';

        if (empty($n8n_base_url)) {
            return new WP_Error(
                'n8n_not_configured',
                __('n8n base URL not configured', 'antek-chat-connector')
            );
        }

        // Build n8n URL
        $url = trailingslashit($n8n_base_url) . ltrim($n8n_voice_endpoint, '/');

        // Get agent ID
        $agent_id = isset($options['agent_id'])
            ? sanitize_text_field($options['agent_id'])
            : $this->get_agent_id();

        if (empty($agent_id)) {
            return new WP_Error(
                'agent_id_missing',
                __('Retell Agent ID not configured', 'antek-chat-connector')
            );
        }

        // Prepare request body for n8n
        $metadata = $options['metadata'] ?? [];
        $body = [
            'user_name' => sanitize_text_field($metadata['user_name'] ?? 'Guest'),
            'user_email' => sanitize_email($metadata['user_email'] ?? ''),
            'agent_id' => $agent_id,
            'page_url' => esc_url_raw($metadata['page_url'] ?? ''),
        ];

        $this->log('=== VOICE TOKEN REQUEST ===', 'info', [
            'url' => $url,
            'request_body' => $body
        ]);

        // Make API request to n8n
        $response = $this->make_api_request($url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ], 'POST');

        if (is_wp_error($response)) {
            $this->log('=== VOICE TOKEN FAILED ===', 'error', [
                'error_code' => $response->get_error_code(),
                'error_message' => $response->get_error_message(),
                'error_data' => $response->get_error_data()
            ]);
            return $response;
        }

        $this->log('=== VOICE TOKEN RAW RESPONSE ===', 'info', [
            'response' => $response
        ]);

        // Validate response structure from n8n
        if (empty($response['success']) || $response['success'] !== true) {
            $this->log('=== VOICE TOKEN VALIDATION FAILED ===', 'error', [
                'reason' => 'success field missing or not true',
                'response' => $response
            ]);
            return new WP_Error(
                'n8n_request_failed',
                __('n8n request failed - success field missing or false', 'antek-chat-connector'),
                ['response' => $response]
            );
        }

        if (empty($response['access_token']) || empty($response['call_id'])) {
            $this->log('=== VOICE TOKEN VALIDATION FAILED ===', 'error', [
                'reason' => 'missing access_token or call_id',
                'has_access_token' => !empty($response['access_token']),
                'has_call_id' => !empty($response['call_id']),
                'response' => $response
            ]);
            return new WP_Error(
                'invalid_response',
                __('Invalid response from n8n (missing access_token or call_id)', 'antek-chat-connector'),
                ['response' => $response]
            );
        }

        $this->log('=== VOICE TOKEN SUCCESS ===', 'info', [
            'call_id' => $response['call_id'],
            'has_access_token' => !empty($response['access_token'])
        ]);

        return [
            'access_token' => $response['access_token'],
            'call_id' => $response['call_id'],
            'agent_id' => $response['agent_id'] ?? $agent_id,
            'sample_rate' => 24000,
            'expires_in' => 30, // Retell tokens expire in 30 seconds
            'provider' => 'n8n-retell',
        ];
    }

    /**
     * Get client configuration
     *
     * @return array
     * @since 1.2.0
     */
    public function get_client_config() {
        return [
            'provider' => 'n8n-retell',
            'agentId' => $this->get_agent_id(),
            'sampleRate' => 24000,
            'emitRawAudioSamples' => false,
        ];
    }

    /**
     * Get agent ID
     *
     * @return string|null
     * @since 1.2.0
     */
    public function get_agent_id() {
        $settings = get_option('antek_chat_voice', []);
        return isset($settings['n8n_retell_agent_id']) ? sanitize_text_field($settings['n8n_retell_agent_id']) : null;
    }

    /**
     * Check if provider is enabled
     *
     * @return bool
     * @since 1.2.0
     */
    public function is_enabled() {
        $settings = get_option('antek_chat_voice', []);

        // Check if voice is enabled and n8n-retell is selected
        return !empty($settings['voice_enabled'])
            && isset($settings['voice_provider'])
            && $settings['voice_provider'] === 'n8n-retell'
            && $this->is_configured();
    }

    /**
     * Check if provider is configured
     *
     * @return bool
     * @since 1.2.0
     */
    public function is_configured() {
        $settings = get_option('antek_chat_voice', []);

        return !empty($settings['n8n_base_url'])
            && !empty($settings['n8n_voice_endpoint'])
            && !empty($settings['n8n_retell_agent_id']);
    }

    /**
     * Get webhook signature method
     *
     * @return string
     * @since 1.2.0
     */
    public function get_webhook_signature_method() {
        // n8n typically doesn't sign webhooks, or uses custom auth
        // For now, return 'none' - can be extended later
        return 'none';
    }

    /**
     * Verify webhook signature
     *
     * Since n8n webhooks typically don't use signatures (uses URL secrets instead),
     * we return true by default. Can be extended for custom n8n auth if needed.
     *
     * @param WP_REST_Request $request REST request object.
     * @return bool
     * @since 1.2.0
     */
    public function verify_webhook_signature($request) {
        // For n8n webhooks, signature verification is typically not used
        // URL-based authentication (secret in webhook URL) is more common
        // Return true to allow webhooks through
        $this->log('Webhook signature verification skipped (n8n uses URL-based auth)', 'info');
        return true;
    }

    /**
     * Normalize webhook event
     *
     * Converts webhook events to standard format
     * Reuses Retell event mapping since n8n proxies Retell events
     *
     * @param array $raw_event Event data from n8n/Retell.
     * @return array Normalized event
     * @since 1.2.0
     */
    public function normalize_webhook_event($raw_event) {
        if (!isset($raw_event['event'])) {
            return [
                'event' => 'unknown',
                'data' => $raw_event,
            ];
        }

        $event_type = $raw_event['event'];

        // Map Retell events to standard events (same as Retell provider)
        switch ($event_type) {
            case 'call_started':
                return [
                    'event' => 'voice_connected',
                    'data' => [
                        'call_id' => $raw_event['call_id'] ?? null,
                        'agent_id' => $raw_event['agent_id'] ?? null,
                    ],
                ];

            case 'call_ended':
                return [
                    'event' => 'voice_disconnected',
                    'data' => [
                        'call_id' => $raw_event['call_id'] ?? null,
                        'end_reason' => $raw_event['end_reason'] ?? 'unknown',
                        'duration' => $raw_event['call_duration'] ?? 0,
                    ],
                ];

            case 'call_analyzed':
                return [
                    'event' => 'call_analysis',
                    'data' => [
                        'call_id' => $raw_event['call_id'] ?? null,
                        'transcript' => $raw_event['transcript'] ?? '',
                        'summary' => $raw_event['call_summary'] ?? '',
                    ],
                ];

            default:
                return [
                    'event' => 'raw',
                    'data' => $raw_event,
                ];
        }
    }

    /**
     * Get required settings fields
     *
     * @return array
     * @since 1.2.0
     */
    public function get_required_settings() {
        return [
            'n8n_base_url',
            'n8n_voice_endpoint',
            'n8n_retell_agent_id',
        ];
    }

    /**
     * Test provider connection
     *
     * Tests n8n connectivity by calling voice endpoint with test flag
     *
     * @return bool|WP_Error True on success, WP_Error on failure
     * @since 1.2.0
     */
    public function test_connection() {
        $settings = get_option('antek_chat_voice', []);
        $n8n_base_url = $settings['n8n_base_url'] ?? '';

        if (empty($n8n_base_url)) {
            return new WP_Error(
                'n8n_not_configured',
                __('n8n base URL not configured', 'antek-chat-connector')
            );
        }

        // Simple connectivity test - try to reach n8n base URL
        $url = trailingslashit($n8n_base_url);

        $this->log('Testing n8n connection', 'info', ['url' => $url]);

        $response = wp_remote_get($url, [
            'timeout' => 10,
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error(
                'connection_failed',
                sprintf(
                    __('Cannot connect to n8n: %s', 'antek-chat-connector'),
                    $response->get_error_message()
                )
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);

        // Accept any response (even 404) as long as server is reachable
        // n8n root typically returns 404, which is fine
        if ($status_code >= 500) {
            return new WP_Error(
                'server_error',
                sprintf(
                    __('n8n server error (HTTP %d)', 'antek-chat-connector'),
                    $status_code
                )
            );
        }

        $this->log('n8n connection test successful', 'info');

        return true;
    }

    /**
     * Send text message via n8n
     *
     * Supports TWO modes:
     * 1. Simple webhook mode - sends message to configured webhook
     * 2. Session-based mode - creates session, caches chat_id, sends messages
     *
     * @param string $message User message text.
     * @param array  $context Context data (session_id, history, user_id, page_url).
     * @return array|WP_Error Array with 'response' key or WP_Error on failure
     * @since 1.2.0
     */
    public function send_text_message($message, $context = []) {
        $settings = get_option('antek_chat_voice', []);
        $text_mode = $settings['n8n_text_mode'] ?? 'simple';

        $this->log('Sending text message via n8n', 'info', [
            'mode' => $text_mode,
            'session_id' => $context['session_id'] ?? 'none'
        ]);

        if ($text_mode === 'session') {
            return $this->send_session_based_message($message, $context);
        } else {
            return $this->send_simple_webhook_message($message, $context);
        }
    }

    /**
     * Send message via simple webhook (existing behavior)
     *
     * Uses the webhook URL from Connection settings
     *
     * @param string $message User message text.
     * @param array  $context Context data.
     * @return array|WP_Error
     * @since 1.2.0
     */
    private function send_simple_webhook_message($message, $context) {
        // Get webhook URL from connection settings
        $webhook_settings = get_option('antek_chat_settings', []);
        $webhook_url = $webhook_settings['n8n_webhook_url'] ?? '';

        if (empty($webhook_url)) {
            return new WP_Error(
                'webhook_not_configured',
                __('Simple webhook URL not configured in Connection settings', 'antek-chat-connector')
            );
        }

        $session_id = $context['session_id'] ?? null;
        $history = $context['history'] ?? [];
        $page_url = $context['page_url'] ?? '';

        // Build request body (matches existing webhook format)
        $body = [
            'message' => sanitize_text_field($message),
            'session_id' => $session_id,
            'timestamp' => current_time('mysql'),
            'metadata' => [
                'user_id' => get_current_user_id(),
                'history' => $history,
                'page_url' => esc_url_raw($page_url),
            ],
        ];

        $this->log('Sending to simple webhook', 'info', ['url' => $webhook_url]);

        $response = $this->make_api_request($webhook_url, [
            'body' => wp_json_encode($body),
        ], 'POST');

        if (is_wp_error($response)) {
            $this->log('Simple webhook failed: ' . $response->get_error_message(), 'error');
            return $response;
        }

        // Extract response text
        $bot_response = $response['response'] ?? $response['message'] ?? __('Thank you for your message!', 'antek-chat-connector');

        return [
            'response' => $bot_response,
            'metadata' => [
                'provider' => 'n8n-retell',
                'mode' => 'simple',
            ],
        ];
    }

    /**
     * Send message via session-based n8n endpoints
     *
     * Creates session if needed, then sends message with chat_id
     *
     * @param string $message User message text.
     * @param array  $context Context data.
     * @return array|WP_Error
     * @since 1.2.0
     */
    private function send_session_based_message($message, $context) {
        $session_id = $context['session_id'] ?? null;

        if (empty($session_id)) {
            return new WP_Error(
                'session_id_missing',
                __('Session ID required for session-based chat', 'antek-chat-connector')
            );
        }

        // Get or create n8n chat session
        $chat_id = $this->get_or_create_n8n_chat_session($session_id);

        if (is_wp_error($chat_id)) {
            return $chat_id;
        }

        // Get n8n message endpoint
        $settings = get_option('antek_chat_voice', []);
        $n8n_base_url = $settings['n8n_base_url'] ?? '';
        $n8n_message_endpoint = $settings['n8n_text_message_endpoint'] ?? '/webhook/retell-send-message';

        if (empty($n8n_base_url)) {
            return new WP_Error(
                'n8n_not_configured',
                __('n8n base URL not configured', 'antek-chat-connector')
            );
        }

        // Build n8n message URL
        $url = trailingslashit($n8n_base_url) . ltrim($n8n_message_endpoint, '/');

        // Prepare request body
        $body = [
            'chat_id' => $chat_id,
            'message' => sanitize_text_field($message),
        ];

        $this->log('=== TEXT MESSAGE REQUEST (SESSION) ===', 'info', [
            'url' => $url,
            'request_body' => $body
        ]);

        $response = $this->make_api_request($url, [
            'body' => wp_json_encode($body),
        ], 'POST');

        if (is_wp_error($response)) {
            $this->log('=== TEXT MESSAGE FAILED ===', 'error', [
                'error_code' => $response->get_error_code(),
                'error_message' => $response->get_error_message(),
                'error_data' => $response->get_error_data()
            ]);
            return $response;
        }

        $this->log('=== TEXT MESSAGE RAW RESPONSE ===', 'info', [
            'response' => $response
        ]);

        // Validate response
        if (empty($response['success']) || $response['success'] !== true) {
            $this->log('=== TEXT MESSAGE VALIDATION FAILED ===', 'error', [
                'reason' => 'success field missing or not true',
                'response' => $response
            ]);
            return new WP_Error(
                'n8n_message_failed',
                __('n8n message request failed - success field missing or false', 'antek-chat-connector'),
                ['response' => $response]
            );
        }

        $bot_response = $response['response'] ?? __('Thank you for your message!', 'antek-chat-connector');

        $this->log('=== TEXT MESSAGE SUCCESS ===', 'info', [
            'response_length' => strlen($bot_response),
            'has_message_id' => !empty($response['message_id'])
        ]);

        return [
            'response' => $bot_response,
            'metadata' => [
                'provider' => 'n8n-retell',
                'mode' => 'session',
                'chat_id' => $chat_id,
                'message_id' => $response['message_id'] ?? null,
            ],
        ];
    }

    /**
     * Get or create n8n chat session
     *
     * Retrieves existing chat_id from WordPress transients or creates new session via n8n
     *
     * @param string $session_id WordPress session ID.
     * @return string|WP_Error Chat ID or WP_Error on failure
     * @since 1.2.0
     */
    private function get_or_create_n8n_chat_session($session_id) {
        if (empty($session_id)) {
            return new WP_Error(
                'session_id_missing',
                __('Session ID required for chat', 'antek-chat-connector')
            );
        }

        // Check if we have a cached chat_id for this session
        $cache_key = 'antek_n8n_chat_' . $session_id;
        $chat_id = get_transient($cache_key);

        if (!empty($chat_id)) {
            $this->log('Using existing n8n chat session', 'info', [
                'session_id' => $session_id,
                'chat_id' => $chat_id
            ]);
            return $chat_id;
        }

        // Create new chat session via n8n
        $this->log('Creating new n8n chat session', 'info', ['session_id' => $session_id]);

        $settings = get_option('antek_chat_voice', []);
        $n8n_base_url = $settings['n8n_base_url'] ?? '';
        $n8n_session_endpoint = $settings['n8n_text_session_endpoint'] ?? '/webhook/retell-create-chat-session';

        if (empty($n8n_base_url)) {
            return new WP_Error(
                'n8n_not_configured',
                __('n8n base URL not configured', 'antek-chat-connector')
            );
        }

        // Build n8n session URL
        $url = trailingslashit($n8n_base_url) . ltrim($n8n_session_endpoint, '/');

        // Get agent ID (may have separate text agent)
        $agent_id = $settings['n8n_retell_text_agent_id'] ?? $settings['n8n_retell_agent_id'] ?? '';

        if (empty($agent_id)) {
            return new WP_Error(
                'agent_id_missing',
                __('Retell text agent ID not configured', 'antek-chat-connector')
            );
        }

        // Prepare request body
        $body = [
            'agent_id' => $agent_id,
            'user_name' => 'Guest', // Can be enhanced with actual user data
            'user_email' => '',
        ];

        $this->log('=== CREATE SESSION REQUEST ===', 'info', [
            'url' => $url,
            'request_body' => $body
        ]);

        $response = $this->make_api_request($url, [
            'body' => wp_json_encode($body),
        ], 'POST');

        if (is_wp_error($response)) {
            $this->log('=== CREATE SESSION FAILED ===', 'error', [
                'error_code' => $response->get_error_code(),
                'error_message' => $response->get_error_message(),
                'error_data' => $response->get_error_data()
            ]);
            return $response;
        }

        $this->log('=== CREATE SESSION RAW RESPONSE ===', 'info', [
            'response' => $response
        ]);

        // Validate response
        if (empty($response['success']) || $response['success'] !== true) {
            $this->log('=== CREATE SESSION VALIDATION FAILED ===', 'error', [
                'reason' => 'success field missing or not true',
                'response' => $response
            ]);
            return new WP_Error(
                'n8n_session_creation_failed',
                __('Failed to create n8n chat session - success field missing or false', 'antek-chat-connector'),
                ['response' => $response]
            );
        }

        if (empty($response['chat_id'])) {
            $this->log('=== CREATE SESSION VALIDATION FAILED ===', 'error', [
                'reason' => 'missing chat_id',
                'response' => $response
            ]);
            return new WP_Error(
                'invalid_response',
                __('Invalid response from n8n (missing chat_id)', 'antek-chat-connector'),
                ['response' => $response]
            );
        }

        $chat_id = $response['chat_id'];

        // Cache chat_id for 24 hours
        set_transient($cache_key, $chat_id, 24 * HOUR_IN_SECONDS);

        $this->log('=== CREATE SESSION SUCCESS ===', 'info', [
            'session_id' => $session_id,
            'chat_id' => $chat_id,
            'cached_key' => $cache_key
        ]);

        return $chat_id;
    }

    /**
     * Get provider capabilities
     *
     * @return array
     * @since 1.2.0
     */
    public function get_capabilities() {
        return [
            'voice_input',
            'voice_output',
            'transcription',
            'text_chat',
        ];
    }
}
