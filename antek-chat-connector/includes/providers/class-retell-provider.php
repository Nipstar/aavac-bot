<?php
/**
 * Retell AI Provider Class
 *
 * Implements voice provider interface for Retell AI
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
 * Retell AI Provider class
 *
 * Provides integration with Retell AI voice platform:
 * - Token generation for web calls
 * - Webhook signature verification (HMAC-SHA256)
 * - Event normalization
 * - 24kHz sample rate
 *
 * @since 1.1.0
 */
class Antek_Chat_Retell_Provider extends Antek_Chat_Voice_Provider_Interface {

    /**
     * API base URL
     *
     * @var string
     */
    private $api_base_url = 'https://api.retellai.com';

    /**
     * API version
     *
     * @var string
     */
    private $api_version = 'v2';

    /**
     * Get provider name
     *
     * @return string
     * @since 1.1.0
     */
    public function get_provider_name() {
        return 'retell';
    }

    /**
     * Get provider display label
     *
     * @return string
     * @since 1.1.0
     */
    public function get_provider_label() {
        return __('Retell AI', 'antek-chat-connector');
    }

    /**
     * Generate access token
     *
     * Calls Retell's create-web-call endpoint to generate a short-lived token
     * Token expires in 30 seconds if call is not initiated
     *
     * @param array $options Optional parameters.
     * @return array|WP_Error Token data or WP_Error
     * @since 1.1.0
     */
    public function generate_access_token($options = []) {
        // Get API key
        $api_key = $this->get_api_key('retell_api_key');

        if (is_wp_error($api_key)) {
            return $api_key;
        }

        // Get agent ID (allow override via options)
        $agent_id = isset($options['agent_id'])
            ? sanitize_text_field($options['agent_id'])
            : $this->get_agent_id();

        if (empty($agent_id)) {
            return new WP_Error(
                'agent_id_missing',
                __('Retell Agent ID not configured', 'antek-chat-connector')
            );
        }

        // Build request URL
        $url = sprintf('%s/%s/create-web-call', $this->api_base_url, $this->api_version);

        // Prepare request body
        $body = [
            'agent_id' => $agent_id,
        ];

        // Add optional metadata
        if (isset($options['metadata'])) {
            $body['metadata'] = $options['metadata'];
        }

        // Make API request
        $response = $this->make_api_request($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ], 'POST');

        if (is_wp_error($response)) {
            $this->log('Token generation failed: ' . $response->get_error_message(), 'error');
            return $response;
        }

        // Validate response structure
        if (empty($response['access_token']) || empty($response['call_id'])) {
            return new WP_Error(
                'invalid_response',
                __('Invalid response from Retell API', 'antek-chat-connector'),
                ['response' => $response]
            );
        }

        $this->log('Token generated successfully', 'info', ['call_id' => $response['call_id']]);

        return [
            'access_token' => $response['access_token'],
            'call_id' => $response['call_id'],
            'agent_id' => $agent_id,
            'sample_rate' => 24000,
            'expires_in' => 30, // Retell tokens expire in 30 seconds
            'provider' => 'retell',
        ];
    }

    /**
     * Get client configuration
     *
     * @return array
     * @since 1.1.0
     */
    public function get_client_config() {
        return [
            'provider' => 'retell',
            'agentId' => $this->get_agent_id(),
            'sampleRate' => 24000,
            'emitRawAudioSamples' => false, // Can be enabled for visualizations
        ];
    }

    /**
     * Get agent ID
     *
     * @return string|null
     * @since 1.1.0
     */
    public function get_agent_id() {
        $settings = get_option('antek_chat_voice_settings', []);
        return isset($settings['retell_agent_id']) ? sanitize_text_field($settings['retell_agent_id']) : null;
    }

    /**
     * Check if provider is enabled
     *
     * @return bool
     * @since 1.1.0
     */
    public function is_enabled() {
        $settings = get_option('antek_chat_voice_settings', []);

        // Check if voice is enabled and Retell is selected
        return !empty($settings['voice_enabled'])
            && isset($settings['voice_provider'])
            && $settings['voice_provider'] === 'retell'
            && $this->is_configured();
    }

    /**
     * Check if provider is configured
     *
     * @return bool
     * @since 1.1.0
     */
    public function is_configured() {
        $settings = get_option('antek_chat_voice_settings', []);

        return !empty($settings['retell_api_key'])
            && !empty($settings['retell_agent_id']);
    }

    /**
     * Get webhook signature method
     *
     * @return string
     * @since 1.1.0
     */
    public function get_webhook_signature_method() {
        return 'hmac';
    }

    /**
     * Verify webhook signature
     *
     * Retell uses HMAC-SHA256 signature in x-retell-signature header
     *
     * @param WP_REST_Request $request REST request object.
     * @return bool
     * @since 1.1.0
     */
    public function verify_webhook_signature($request) {
        $signature = $request->get_header('x-retell-signature');

        if (empty($signature)) {
            $this->log('Webhook signature missing', 'warning');
            return false;
        }

        // Get API key for signature verification
        $api_key = $this->get_api_key('retell_api_key');

        if (is_wp_error($api_key)) {
            $this->log('Cannot verify signature: ' . $api_key->get_error_message(), 'error');
            return false;
        }

        // Get raw body
        $payload = $request->get_body();

        // Calculate expected signature
        $expected = hash_hmac('sha256', $payload, $api_key);

        // Timing-safe comparison
        $valid = hash_equals($expected, $signature);

        if (!$valid) {
            $this->log('Webhook signature verification failed', 'warning');
        }

        return $valid;
    }

    /**
     * Normalize webhook event
     *
     * Converts Retell events to standard format
     *
     * @param array $raw_event Retell event data.
     * @return array Normalized event
     * @since 1.1.0
     */
    public function normalize_webhook_event($raw_event) {
        if (!isset($raw_event['event'])) {
            return [
                'event' => 'unknown',
                'data' => $raw_event,
            ];
        }

        $event_type = $raw_event['event'];

        // Map Retell events to standard events
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
                // Post-call analysis event
                return [
                    'event' => 'call_analysis',
                    'data' => [
                        'call_id' => $raw_event['call_id'] ?? null,
                        'transcript' => $raw_event['transcript'] ?? '',
                        'summary' => $raw_event['call_summary'] ?? '',
                    ],
                ];

            default:
                // Pass through unknown events
                return [
                    'event' => 'provider_specific',
                    'data' => [
                        'provider_event' => $event_type,
                        'raw_data' => $raw_event,
                    ],
                ];
        }
    }

    /**
     * Get required settings
     *
     * @return array
     * @since 1.1.0
     */
    public function get_required_settings() {
        return [
            'retell_api_key' => [
                'label' => __('Retell API Key', 'antek-chat-connector'),
                'type' => 'password',
                'required' => true,
                'encrypted' => true,
            ],
            'retell_agent_id' => [
                'label' => __('Retell Agent ID', 'antek-chat-connector'),
                'type' => 'text',
                'required' => true,
                'encrypted' => false,
            ],
        ];
    }

    /**
     * Test connection
     *
     * Tests Retell API connectivity and credentials
     *
     * @return bool|WP_Error
     * @since 1.1.0
     */
    public function test_connection() {
        Antek_Chat_Debug_Logger::log('provider', 'Retell connection test started', 'info');

        // Get API key
        $api_key = $this->get_api_key('retell_api_key');

        if (is_wp_error($api_key)) {
            Antek_Chat_Debug_Logger::log('provider', 'Retell API key error', 'error', [
                'error' => $api_key->get_error_message()
            ]);
            return $api_key;
        }

        // Test by getting agent info (simpler than list-agents)
        $agent_id = $this->get_agent_id();

        if (empty($agent_id)) {
            Antek_Chat_Debug_Logger::log('provider', 'Retell agent ID missing', 'error');
            return new WP_Error(
                'agent_id_missing',
                __('Retell Agent ID not configured', 'antek-chat-connector')
            );
        }

        // Test connection by listing agents
        // Retell API endpoint: GET /list-agents
        $url = sprintf('%s/list-agents', $this->api_base_url);

        Antek_Chat_Debug_Logger::log('provider', 'Retell API request', 'info', [
            'url' => $url,
            'agent_id' => $agent_id,
            'has_api_key' => !empty($api_key)
        ]);

        $response = $this->make_api_request($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
            ],
        ], 'GET');

        if (is_wp_error($response)) {
            Antek_Chat_Debug_Logger::log('provider', 'Retell connection test failed', 'error', [
                'error' => $response->get_error_message(),
                'url' => $url
            ]);
            return new WP_Error(
                'connection_test_failed',
                sprintf(
                    __('Retell connection test failed: %s', 'antek-chat-connector'),
                    $response->get_error_message()
                )
            );
        }

        Antek_Chat_Debug_Logger::log('provider', 'Retell connection test successful', 'info', [
            'agent_id' => $agent_id
        ]);

        $this->log('Connection test successful', 'info');

        return true;
    }

    /**
     * Get provider capabilities
     *
     * @return array
     * @since 1.1.0
     */
    public function get_capabilities() {
        return [
            'voice_input',
            'voice_output',
            'transcription',
            'telephony', // Retell supports native telephony
            'post_call_analysis', // Built-in call analysis
            'webhook_events', // Rich webhook events
        ];
    }

    /**
     * Get provider metadata
     *
     * @return array
     * @since 1.1.0
     */
    public function get_provider_metadata() {
        $base_metadata = parent::get_provider_metadata();

        return array_merge($base_metadata, [
            'latency' => '~800ms',
            'sample_rate' => '24000 Hz',
            'pricing' => '$0.07/min + LLM costs',
            'features' => [
                'Native telephony support',
                'Post-call analysis',
                'Warm transfer capability',
                'SOC2, HIPAA, GDPR compliant',
                '18-30+ language support',
            ],
            'documentation_url' => 'https://docs.retellai.com',
            'dashboard_url' => 'https://beta.retellai.com',
        ]);
    }

    /**
     * Send text message to Retell AI
     *
     * Sends a text message to Retell AI conversational agent and returns response
     * Uses /create-chat-completion endpoint for text-based conversations
     *
     * @param string $message User message text.
     * @param array  $context Context data (session_id, history, user_id, page_url).
     * @return array|WP_Error Array with 'response' key or WP_Error on failure
     * @since 1.1.0
     */
    public function send_text_message($message, $context = []) {
        $session_id = $context['session_id'] ?? null;

        Antek_Chat_Debug_Logger::log('chat', 'Retell text message started', 'info', [
            'session_id' => $session_id,
            'message_length' => strlen($message)
        ]);

        // Get or create chat session
        $chat_id = $this->get_or_create_chat_session($session_id);

        if (is_wp_error($chat_id)) {
            return $chat_id;
        }

        // Get API key
        $api_key = $this->get_api_key('retell_api_key');

        if (is_wp_error($api_key)) {
            return $api_key;
        }

        // Build request URL for chat completion
        $url = sprintf('%s/create-chat-completion', $this->api_base_url);

        // Prepare request body
        $body = [
            'chat_id' => $chat_id,
            'content' => sanitize_text_field($message),
        ];

        Antek_Chat_Debug_Logger::log('chat', 'Sending to Retell chat completion', 'info', [
            'url' => $url,
            'chat_id' => $chat_id,
            'session_id' => $session_id
        ]);

        // Make API request
        $response = $this->make_api_request($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ], 'POST');

        if (is_wp_error($response)) {
            $this->log('Chat completion failed: ' . $response->get_error_message(), 'error');
            Antek_Chat_Debug_Logger::log('chat', 'Retell chat completion failed', 'error', [
                'error' => $response->get_error_message(),
                'session_id' => $session_id
            ]);
            return $response;
        }

        // Extract agent response from messages array
        if (empty($response['messages']) || !is_array($response['messages'])) {
            Antek_Chat_Debug_Logger::log('chat', 'Retell invalid response format', 'error', [
                'response' => $response,
                'session_id' => $session_id
            ]);
            return new WP_Error(
                'invalid_response',
                __('Invalid response from Retell AI', 'antek-chat-connector'),
                ['response' => $response]
            );
        }

        // Get the last assistant message (Retell's response)
        $agent_message = '';
        foreach (array_reverse($response['messages']) as $msg) {
            if (isset($msg['role']) && $msg['role'] === 'agent' && !empty($msg['content'])) {
                $agent_message = $msg['content'];
                break;
            }
        }

        if (empty($agent_message)) {
            Antek_Chat_Debug_Logger::log('chat', 'No agent message in response', 'error', [
                'response' => $response,
                'session_id' => $session_id
            ]);
            return new WP_Error(
                'no_response',
                __('No response from Retell AI', 'antek-chat-connector')
            );
        }

        Antek_Chat_Debug_Logger::log('chat', 'Retell chat response received', 'info', [
            'response_length' => strlen($agent_message),
            'session_id' => $session_id
        ]);

        return [
            'response' => $agent_message,
            'metadata' => [
                'provider' => 'retell',
                'chat_id' => $chat_id,
                'message_count' => count($response['messages']),
            ],
        ];
    }

    /**
     * Get or create chat session for text chat
     *
     * Retrieves existing chat_id from WordPress options or creates new chat session
     *
     * @param string|null $session_id WordPress session ID.
     * @return string|WP_Error Chat ID or WP_Error on failure
     * @since 1.1.0
     */
    private function get_or_create_chat_session($session_id) {
        if (empty($session_id)) {
            return new WP_Error(
                'session_id_missing',
                __('Session ID required for chat', 'antek-chat-connector')
            );
        }

        // Check if we have a chat_id for this session
        $chat_mapping_key = 'antek_retell_chat_' . $session_id;
        $chat_id = get_transient($chat_mapping_key);

        if (!empty($chat_id)) {
            Antek_Chat_Debug_Logger::log('chat', 'Using existing Retell chat session', 'info', [
                'session_id' => $session_id,
                'chat_id' => $chat_id
            ]);
            return $chat_id;
        }

        // Create new chat session
        Antek_Chat_Debug_Logger::log('chat', 'Creating new Retell chat session', 'info', [
            'session_id' => $session_id
        ]);

        $api_key = $this->get_api_key('retell_api_key');

        if (is_wp_error($api_key)) {
            return $api_key;
        }

        // Check for optional text chat agent ID, fallback to main agent ID
        $settings = get_option('antek_chat_voice_settings', []);
        $agent_id = !empty($settings['retell_chat_agent_id'])
            ? sanitize_text_field($settings['retell_chat_agent_id'])
            : $this->get_agent_id();

        Antek_Chat_Debug_Logger::log('chat', 'Using Retell agent for text chat', 'info', [
            'agent_id' => $agent_id,
            'is_custom_text_agent' => !empty($settings['retell_chat_agent_id']),
            'session_id' => $session_id
        ]);

        if (empty($agent_id)) {
            return new WP_Error(
                'agent_id_missing',
                __('Retell Agent ID not configured', 'antek-chat-connector')
            );
        }

        // Build request URL
        $url = sprintf('%s/create-chat', $this->api_base_url);

        // Prepare request body
        $body = [
            'agent_id' => $agent_id,
        ];

        Antek_Chat_Debug_Logger::log('chat', 'Calling Retell create-chat', 'info', [
            'url' => $url,
            'agent_id' => $agent_id,
            'session_id' => $session_id
        ]);

        // Make API request
        $response = $this->make_api_request($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ], 'POST');

        if (is_wp_error($response)) {
            Antek_Chat_Debug_Logger::log('chat', 'Failed to create Retell chat', 'error', [
                'error' => $response->get_error_message(),
                'session_id' => $session_id
            ]);
            return $response;
        }

        if (empty($response['chat_id'])) {
            Antek_Chat_Debug_Logger::log('chat', 'No chat_id in create-chat response', 'error', [
                'response' => $response,
                'session_id' => $session_id
            ]);
            return new WP_Error(
                'invalid_response',
                __('Invalid response from Retell create-chat', 'antek-chat-connector'),
                ['response' => $response]
            );
        }

        $chat_id = $response['chat_id'];

        // Store chat_id for this session (expire in 24 hours)
        set_transient($chat_mapping_key, $chat_id, 24 * HOUR_IN_SECONDS);

        Antek_Chat_Debug_Logger::log('chat', 'Retell chat session created', 'info', [
            'session_id' => $session_id,
            'chat_id' => $chat_id
        ]);

        return $chat_id;
    }
}
