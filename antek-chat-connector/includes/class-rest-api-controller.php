<?php
/**
 * REST API Controller Class
 *
 * Centralized REST endpoint registration and routing
 *
 * @package Antek_Chat_Connector
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Ensure dependencies are loaded
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/class-rate-limiter.php';
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/providers/class-voice-provider-factory.php';

/**
 * REST API Controller class
 *
 * Handles REST API endpoint registration and request routing.
 * Implements rate limiting, authentication, and provider abstraction.
 *
 * @since 1.1.0
 */
class Antek_Chat_REST_API_Controller {

    /**
     * API namespace
     *
     * @var string
     */
    private $namespace = 'antek-chat/v1';

    /**
     * Rate limiter instance
     *
     * @var Antek_Chat_Rate_Limiter
     */
    private $rate_limiter;

    /**
     * Constructor
     *
     * @since 1.1.0
     */
    public function __construct() {
        $this->rate_limiter = Antek_Chat_Rate_Limiter::create_from_preset('voice_tokens');

        // Allow REST API access for our endpoints (bypasses some security plugins)
        add_filter('rest_authentication_errors', [$this, 'allow_public_endpoints'], 10, 1);
    }

    /**
     * Allow public access to specific REST endpoints
     *
     * This filter runs before permission callbacks and allows us to bypass
     * REST API authentication for specific public endpoints.
     *
     * @param WP_Error|null|bool $result Error from another authentication handler, null if not authenticated, true if authenticated.
     * @return WP_Error|null|bool
     * @since 1.1.2
     */
    public function allow_public_endpoints($result) {
        // If already authenticated or has error, don't interfere
        if (true === $result || is_wp_error($result)) {
            return $result;
        }

        // Get current request URI
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

        // Normalize slashes for comparison (handle //wp-json// case)
        $request_uri = preg_replace('#/+#', '/', $request_uri);

        // List of public endpoints that don't require authentication
        $public_endpoints = [
            'antek-chat/v1/providers',
            'antek-chat/v1/webhook',
            'antek-chat/v1/message',
            'antek-chat/v1/token',  // For voice token generation with nonce
        ];

        // Check if current request matches any public endpoint
        foreach ($public_endpoints as $endpoint) {
            if (strpos($request_uri, $endpoint) !== false) {
                error_log('AAVAC Bot: Allowing public endpoint: ' . $endpoint);
                // Allow access without authentication
                return true;
            }
        }

        // Not one of our public endpoints, return original result
        return $result;
    }

    /**
     * Register REST API routes
     *
     * @since 1.1.0
     */
    public function register_routes() {
        // Token generation endpoints
        register_rest_route($this->namespace, '/token/(?P<provider>[\w]+)', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_voice_token'],
            'permission_callback' => [$this, 'check_token_permission'],
            'args' => [
                'provider' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => [$this, 'validate_provider'],
                ],
                'agent_id' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'metadata' => [
                    'required' => false,
                ],
            ],
        ]);

        // Webhook receiver endpoint
        register_rest_route($this->namespace, '/webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_webhook'],
            'permission_callback' => '__return_true', // Public endpoint (auth in handler)
        ]);

        // Test webhook endpoint (admin only)
        register_rest_route($this->namespace, '/test-webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'test_webhook'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
            'args' => [
                'provider' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'event_type' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'payload' => [
                    'required' => false,
                    'validate_callback' => 'is_array',
                ],
            ],
        ]);

        // Provider info endpoint
        register_rest_route($this->namespace, '/providers', [
            'methods' => 'GET',
            'callback' => [$this, 'get_providers'],
            'permission_callback' => '__return_true', // Public endpoint
        ]);

        // Chat message endpoint (public)
        register_rest_route($this->namespace, '/message', [
            'methods' => 'POST',
            'callback' => [$this, 'send_chat_message'],
            'permission_callback' => '__return_true', // Public endpoint
            'args' => [
                'session_id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'message' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ],
                'media_ids' => [
                    'required' => false,
                    'type' => 'array',
                    'default' => [],
                ],
                'page_url' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                ],
            ],
        ]);

        // Provider status endpoint (admin only)
        register_rest_route($this->namespace, '/providers/(?P<provider>[\w]+)/status', [
            'methods' => 'GET',
            'callback' => [$this, 'get_provider_status'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
            'args' => [
                'provider' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => [$this, 'validate_provider'],
                ],
            ],
        ]);

        // Test provider connection endpoint (admin only)
        register_rest_route($this->namespace, '/providers/(?P<provider>[\w]+)/test', [
            'methods' => 'POST',
            'callback' => [$this, 'test_provider_connection'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
            'args' => [
                'provider' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => [$this, 'validate_provider'],
                ],
            ],
        ]);

        // Media upload endpoint
        register_rest_route($this->namespace, '/upload', [
            'methods' => 'POST',
            'callback' => [$this, 'upload_media'],
            'permission_callback' => [$this, 'check_token_permission'],
        ]);

        // Media access endpoint
        register_rest_route($this->namespace, '/media/(?P<filename>[\w\-\.]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'serve_media'],
            'permission_callback' => '__return_true', // Token-based auth in handler
            'args' => [
                'filename' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_file_name',
                ],
                'token' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    /**
     * Generate access token
     *
     * Handles token generation for voice providers
     *
     * @param WP_REST_Request $request REST request object.
     * @return WP_REST_Response|WP_Error Response or error
     * @since 1.1.0
     */
    /**
     * Generate voice token for Retell calls via n8n
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function generate_voice_token($request) {
        // Start logging
        error_log('=== VOICE TOKEN REQUEST START ===');

        try {
            // Get voice settings
            $voice_settings = get_option('antek_chat_voice', []);
            error_log('Voice settings retrieved: ' . print_r($voice_settings, true));

            // Check if voice is enabled
            if (empty($voice_settings['enabled'])) {
                error_log('ERROR: Voice not enabled');
                return new WP_REST_Response([
                    'success' => false,
                    'error' => 'Voice features are not enabled. Please enable in settings.',
                ], 400);
            }

            // Get n8n webhook URL
            $n8n_url = isset($voice_settings['n8n_voice_token_url']) ? trim($voice_settings['n8n_voice_token_url']) : '';

            if (empty($n8n_url)) {
                error_log('ERROR: n8n webhook URL not configured');
                return new WP_REST_Response([
                    'success' => false,
                    'error' => 'Voice webhook URL not configured. Please check Voice Settings.',
                ], 400);
            }

            error_log('n8n URL: ' . $n8n_url);

            // Get Retell agent ID
            $agent_id = isset($voice_settings['retell_agent_id']) ? trim($voice_settings['retell_agent_id']) : '';

            if (empty($agent_id)) {
                error_log('ERROR: Retell agent ID not configured');
                return new WP_REST_Response([
                    'success' => false,
                    'error' => 'Retell agent ID not configured. Please check Voice Settings.',
                ], 400);
            }

            error_log('Agent ID: ' . $agent_id);

            // Get request parameters
            $session_id = $request->get_param('session_id');
            if (empty($session_id)) {
                $session_id = 'session_' . uniqid();
            }

            $page_url = $request->get_param('page_url');
            if (empty($page_url)) {
                $page_url = home_url();
            }

            error_log('Session ID: ' . $session_id);
            error_log('Page URL: ' . $page_url);

            // Build request body for n8n
            $request_body = [
                'agent_id' => $agent_id,
                'user_id' => get_current_user_id(),
                'session_id' => $session_id,
                'page_url' => $page_url,
            ];

            $request_json = json_encode($request_body);
            error_log('Request body: ' . $request_json);
            error_log('Calling n8n webhook...');

            // Call n8n webhook
            $response = wp_remote_post($n8n_url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => $request_json,
                'timeout' => 15,
                'sslverify' => true,
            ]);

            // Check for WordPress HTTP errors
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                error_log('ERROR: n8n request failed - ' . $error_message);

                return new WP_REST_Response([
                    'success' => false,
                    'error' => 'Failed to connect to voice service: ' . $error_message,
                ], 500);
            }

            // Get response details
            $http_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            error_log('n8n response code: ' . $http_code);
            error_log('n8n response body: ' . $response_body);

            // Check HTTP status code
            if ($http_code !== 200) {
                error_log('ERROR: n8n returned non-200 status: ' . $http_code);

                return new WP_REST_Response([
                    'success' => false,
                    'error' => 'Voice service error (HTTP ' . $http_code . ')',
                ], 500);
            }

            // Parse JSON response
            $data = json_decode($response_body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('ERROR: Failed to parse JSON response: ' . json_last_error_msg());
                error_log('Raw response: ' . $response_body);

                return new WP_REST_Response([
                    'success' => false,
                    'error' => 'Invalid response from voice service',
                ], 500);
            }

            // Check if response has success flag
            if (empty($data['success'])) {
                error_log('ERROR: Response missing success flag');
                error_log('Response data: ' . print_r($data, true));

                $error_msg = isset($data['error']) ? $data['error'] : 'Unknown error from voice service';

                return new WP_REST_Response([
                    'success' => false,
                    'error' => $error_msg,
                ], 500);
            }

            // Check if we have the access token
            if (empty($data['access_token'])) {
                error_log('ERROR: No access_token in response');
                error_log('Response data: ' . print_r($data, true));

                return new WP_REST_Response([
                    'success' => false,
                    'error' => 'No access token received from voice service',
                ], 500);
            }

            error_log('SUCCESS: Token generated successfully');
            error_log('Call ID: ' . (isset($data['call_id']) ? $data['call_id'] : 'N/A'));

            // Return successful response
            return new WP_REST_Response([
                'success' => true,
                'access_token' => $data['access_token'],
                'call_id' => isset($data['call_id']) ? $data['call_id'] : null,
                'agent_id' => isset($data['agent_id']) ? $data['agent_id'] : $agent_id,
                'sample_rate' => isset($data['sample_rate']) ? $data['sample_rate'] : 24000,
            ], 200);

        } catch (Exception $e) {
            error_log('EXCEPTION in generate_voice_token: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());

            return new WP_REST_Response([
                'success' => false,
                'error' => 'Internal error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle incoming webhook
     *
     * Processes webhook events from voice providers
     *
     * @param WP_REST_Request $request REST request object.
     * @return WP_REST_Response|WP_Error Response or error
     * @since 1.1.0
     */
    public function handle_webhook($request) {
        // Get provider from request (either header or body)
        $provider_name = $request->get_header('X-Provider');

        if (empty($provider_name)) {
            // Try to infer from request body or URL
            $body = $request->get_json_params();
            $provider_name = isset($body['provider']) ? sanitize_text_field($body['provider']) : null;
        }

        if (empty($provider_name)) {
            return new WP_REST_Response([
                'error' => 'provider_missing',
                'message' => __('Provider not specified', 'antek-chat-connector'),
            ], 400);
        }

        try {
            // Get provider instance
            $provider = Antek_Chat_Voice_Provider_Factory::get_provider($provider_name);

            if (is_wp_error($provider)) {
                return new WP_REST_Response([
                    'error' => $provider->get_error_code(),
                    'message' => $provider->get_error_message(),
                ], 400);
            }

            // Verify webhook signature
            $signature_valid = $provider->verify_webhook_signature($request);

            if (!$signature_valid) {
                // Log failed verification
                if (WP_DEBUG) {
                    error_log('[Antek Chat] Webhook signature verification failed for ' . $provider_name);
                }

                return new WP_REST_Response([
                    'error' => 'signature_invalid',
                    'message' => __('Webhook signature verification failed', 'antek-chat-connector'),
                ], 401);
            }

            // Get raw event data
            $raw_event = $request->get_json_params();

            // Normalize event
            $normalized_event = $provider->normalize_webhook_event($raw_event);

            // Process event (pass to existing webhook handler)
            do_action('antek_chat_voice_webhook_received', $normalized_event, $provider_name, $request);

            return new WP_REST_Response([
                'success' => true,
                'message' => __('Webhook processed successfully', 'antek-chat-connector'),
            ], 200);

        } catch (Exception $e) {
            return new WP_REST_Response([
                'error' => 'webhook_processing_failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test webhook
     *
     * Simulates a webhook event for testing (admin only)
     *
     * @param WP_REST_Request $request REST request object.
     * @return WP_REST_Response Response
     * @since 1.1.0
     */
    public function test_webhook($request) {
        $provider_name = $request->get_param('provider');
        $event_type = $request->get_param('event_type');
        $payload = $request->get_param('payload');

        if (empty($payload)) {
            // Generate default payload based on event type
            $payload = $this->generate_test_payload($provider_name, $event_type);
        }

        try {
            // Get provider instance
            $provider = Antek_Chat_Voice_Provider_Factory::get_provider($provider_name);

            if (is_wp_error($provider)) {
                return new WP_REST_Response([
                    'error' => $provider->get_error_code(),
                    'message' => $provider->get_error_message(),
                ], 400);
            }

            // Normalize event
            $normalized_event = $provider->normalize_webhook_event($payload);

            // Trigger webhook action
            do_action('antek_chat_voice_webhook_received', $normalized_event, $provider_name, null);

            return new WP_REST_Response([
                'success' => true,
                'message' => __('Test webhook sent successfully', 'antek-chat-connector'),
                'normalized_event' => $normalized_event,
            ], 200);

        } catch (Exception $e) {
            return new WP_REST_Response([
                'error' => 'test_webhook_failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send chat message
     *
     * Handles incoming chat messages via REST API
     *
     * @param WP_REST_Request $request REST request object.
     * @return WP_REST_Response Response
     * @since 1.1.2
     */
    public function send_chat_message($request) {
        try {
            error_log('Antek Chat: Message endpoint called');

            // Get parameters
            $session_id = $request->get_param('session_id');
            $message = $request->get_param('message');
            $page_url = $request->get_param('page_url') ?: '';

            if (empty($message) || empty($session_id)) {
                return new WP_REST_Response([
                    'success' => false,
                    'error' => __('Message and session ID are required', 'antek-chat-connector'),
                ], 400);
            }

            // Get conversation history for context
            $session_manager = new Antek_Chat_Session_Manager();
            $history = $session_manager->get_conversation($session_id);

            // Check if using voice provider for text chat
            $voice_settings = get_option('antek_chat_voice_settings', []);
            $use_retell_chat = !empty($voice_settings['use_retell_chat']);
            $voice_provider = $voice_settings['voice_provider'] ?? 'retell';

            error_log('Antek Chat: Text chat routing - use_retell_chat=' . ($use_retell_chat ? 'YES' : 'NO') . ', provider=' . $voice_provider);

            // OPTION 1: Use Voice Provider for text chat (Retell or n8n-Retell)
            if ($use_retell_chat) {
                error_log('Antek Chat: Delegating to voice provider: ' . $voice_provider);

                try {
                    // Get voice provider instance via factory (supports 'retell' and 'n8n-retell')
                    $provider = Antek_Chat_Voice_Provider_Factory::create($voice_provider);

                    // Use provider's send_text_message method
                    $result = $provider->send_text_message($message, [
                        'session_id' => $session_id,
                        'page_url' => $page_url,
                        'history' => $history,
                        'user_id' => get_current_user_id(),
                    ]);

                    if (is_wp_error($result)) {
                        error_log('Antek Chat: Provider error - falling back to webhook: ' . $result->get_error_message());
                        // Fall through to webhook mode below instead of returning error
                    } else {
                        // Provider succeeded
                        $bot_response = $result['response'] ?? __('Thank you for your message!', 'antek-chat-connector');

                        // Save to conversation history
                        $session_manager->save_conversation($session_id, $message, $bot_response);

                        return new WP_REST_Response([
                            'success' => true,
                            'response' => $bot_response,
                            'metadata' => $result['metadata'] ?? []
                        ], 200);
                    }

                } catch (Exception $e) {
                    error_log('Antek Chat: Exception using provider - falling back to webhook: ' . $e->getMessage());
                    // Fall through to webhook mode below instead of returning error
                }
            }

            // OPTION 2: Use Webhook (existing behavior)
            error_log('Antek Chat: Using webhook approach');

            $settings = get_option('antek_chat_settings', []);
            $webhook_url = $settings['n8n_webhook_url'] ?? $settings['webhook_url'] ?? '';

            if (empty($webhook_url)) {
                error_log('Antek Chat: No webhook configured');
                return new WP_REST_Response([
                    'success' => false,
                    'error' => __('No webhook or Retell configured', 'antek-chat-connector'),
                ], 400);
            }

            // Send to webhook
            $webhook_response = wp_remote_post($webhook_url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    'message' => $message,
                    'session_id' => $session_id,
                    'timestamp' => current_time('mysql'),
                    'metadata' => [
                        'user_id' => get_current_user_id(),
                        'history' => $history,
                        'page_url' => $page_url,
                    ],
                ]),
                'timeout' => 30,
            ]);

            if (is_wp_error($webhook_response)) {
                error_log('Antek Chat: Webhook error: ' . $webhook_response->get_error_message());
                return new WP_REST_Response([
                    'success' => false,
                    'error' => __('Webhook request failed', 'antek-chat-connector'),
                ], 500);
            }

            $body = wp_remote_retrieve_body($webhook_response);
            $data = json_decode($body, true);

            $bot_response = $data['response'] ?? $data['message'] ?? __('Thank you for your message!', 'antek-chat-connector');

            // Save to conversation history
            $session_manager->save_conversation($session_id, $message, $bot_response);

            return new WP_REST_Response([
                'success' => true,
                'response' => $bot_response,
            ], 200);

        } catch (Exception $e) {
            error_log('Antek Chat: Fatal exception: ' . $e->getMessage());
            error_log('Antek Chat: Stack trace: ' . $e->getTraceAsString());

            return new WP_REST_Response([
                'success' => false,
                'error' => __('An error occurred processing your message', 'antek-chat-connector'),
            ], 500);
        }
    }

    /**
     * Get providers
     *
     * Returns information about available providers
     *
     * @param WP_REST_Request $request REST request object.
     * @return WP_REST_Response Response
     * @since 1.1.0
     */
    public function get_providers($request) {
        error_log('AAVAC Bot: get_providers() called');

        // Get voice settings (modern schema v1.1.0+)
        $voice_settings = get_option('antek_chat_voice_settings', []);

        error_log('AAVAC Bot: Voice settings: ' . json_encode($voice_settings));

        // Check if voice is enabled (FIXED: voice_enabled not enabled)
        $voice_enabled = !empty($voice_settings['voice_enabled']);

        if (!$voice_enabled) {
            error_log('AAVAC Bot: Voice not enabled');
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Voice features are not enabled',
            ], 200);
        }

        // Get provider type and configuration (modern schema)
        $voice_provider = $voice_settings['voice_provider'] ?? 'retell';
        $agent_id = $voice_settings['retell_agent_id'] ?? '';

        // Validate configuration
        if (empty($agent_id)) {
            error_log('AAVAC Bot: Voice configuration incomplete - missing agent_id');
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Voice not configured properly. Check Voice Settings.',
            ], 200);
        }

        error_log('AAVAC Bot: Returning provider config for: ' . $voice_provider);

        // Return valid provider configuration
        return new WP_REST_Response([
            'success' => true,
            'provider' => $voice_provider,
            'config' => [
                'provider' => $voice_provider,
                'agentId' => $agent_id,
                'sampleRate' => 24000,
                'enabled' => true,
            ],
        ], 200);
    }

    /**
     * Get provider status
     *
     * Returns detailed status for a specific provider (admin only)
     *
     * @param WP_REST_Request $request REST request object.
     * @return WP_REST_Response Response
     * @since 1.1.0
     */
    public function get_provider_status($request) {
        $provider_name = $request->get_param('provider');

        try {
            $provider = Antek_Chat_Voice_Provider_Factory::get_provider($provider_name);

            if (is_wp_error($provider)) {
                return new WP_REST_Response([
                    'error' => $provider->get_error_code(),
                    'message' => $provider->get_error_message(),
                ], 400);
            }

            $status = [
                'name' => $provider->get_provider_name(),
                'label' => $provider->get_provider_label(),
                'enabled' => $provider->is_enabled(),
                'configured' => $provider->is_configured(),
                'capabilities' => $provider->get_capabilities(),
                'metadata' => $provider->get_provider_metadata(),
                'config' => $provider->get_client_config(),
            ];

            return new WP_REST_Response($status, 200);

        } catch (Exception $e) {
            return new WP_REST_Response([
                'error' => 'status_check_failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test provider connection
     *
     * Tests API connection for a provider (admin only)
     *
     * @param WP_REST_Request $request REST request object.
     * @return WP_REST_Response Response
     * @since 1.1.0
     */
    public function test_provider_connection($request) {
        $provider_name = $request->get_param('provider');

        $result = Antek_Chat_Voice_Provider_Factory::test_provider_connection($provider_name);

        if (is_wp_error($result)) {
            return new WP_REST_Response([
                'success' => false,
                'error' => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ], 400);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => sprintf(
                __('Connection to %s successful', 'antek-chat-connector'),
                $provider_name
            ),
        ], 200);
    }

    /**
     * Check token permission
     *
     * Permission callback for token generation
     *
     * @param WP_REST_Request $request REST request object.
     * @return bool True if allowed
     * @since 1.1.0
     * @updated 1.2.20 Added nonce validation for public token endpoints
     */
    public function check_token_permission($request) {
        // Allow logged-in users
        if (is_user_logged_in()) {
            error_log('AAVAC Bot: Token permission - user is logged in');
            return true;
        }

        // For guests, verify REST API nonce (sent by JavaScript as X-WP-Nonce header)
        $nonce = $request->get_header('X-WP-Nonce');

        if (!empty($nonce)) {
            error_log('AAVAC Bot: Token permission - nonce present, verifying...');

            // Verify the nonce - wp_verify_nonce returns 1 or 2 on success, false on failure
            $verified = wp_verify_nonce($nonce, 'wp_rest');

            if ($verified) {
                error_log('AAVAC Bot: Token permission - nonce verified successfully');
                return true;
            } else {
                error_log('AAVAC Bot: Token permission - nonce verification failed');
            }
        } else {
            error_log('AAVAC Bot: Token permission - no nonce in request headers');
        }

        // Allow guests with valid session (fallback)
        $session_id = $this->get_session_id($request);
        if (!empty($session_id)) {
            error_log('AAVAC Bot: Token permission - session ID found: ' . $session_id);
            return true;
        }

        error_log('AAVAC Bot: Token permission - DENIED (no nonce, no session, not logged in)');
        return false;
    }

    /**
     * Validate provider parameter
     *
     * @param string $value Provider name.
     * @param WP_REST_Request $request REST request object.
     * @param string $param Parameter name.
     * @return bool True if valid
     * @since 1.1.0
     */
    public function validate_provider($value, $request, $param) {
        // Accept 'voice' as a generic token endpoint
        // Also accept any registered voice provider
        if ($value === 'voice') {
            return true;
        }

        $available_providers = Antek_Chat_Voice_Provider_Factory::get_available_providers();
        return in_array($value, $available_providers, true);
    }

    /**
     * Get session ID from request
     *
     * @param WP_REST_Request $request REST request object.
     * @return string|null Session ID or null
     * @since 1.1.0
     */
    private function get_session_id($request) {
        // Check for session ID in header
        $session_id = $request->get_header('X-Session-ID');

        if (empty($session_id)) {
            // Check cookie
            $session_id = isset($_COOKIE['antek_chat_session'])
                ? sanitize_text_field($_COOKIE['antek_chat_session'])
                : null;
        }

        return $session_id;
    }

    /**
     * Generate test payload
     *
     * Generates sample webhook payload for testing
     *
     * @param string $provider_name Provider name.
     * @param string $event_type Event type.
     * @return array Test payload
     * @since 1.1.0
     */
    private function generate_test_payload($provider_name, $event_type) {
        $payloads = [
            'retell' => [
                'call_started' => [
                    'event' => 'call_started',
                    'call_id' => 'test_call_' . time(),
                    'agent_id' => 'test_agent',
                ],
                'call_ended' => [
                    'event' => 'call_ended',
                    'call_id' => 'test_call_' . time(),
                    'end_reason' => 'user_hangup',
                    'call_duration' => 120,
                ],
            ],
        ];

        if (isset($payloads[$provider_name][$event_type])) {
            return $payloads[$provider_name][$event_type];
        }

        // Default payload
        return [
            'event' => $event_type,
            'timestamp' => time(),
            'test' => true,
        ];
    }

    /**
     * Upload media
     *
     * Handles media file uploads
     *
     * @param WP_REST_Request $request REST request object.
     * @return WP_REST_Response Response
     * @since 1.1.0
     */
    public function upload_media($request) {
        // Get session ID
        $session_id = $this->get_session_id($request);

        if (empty($session_id)) {
            return new WP_REST_Response([
                'error' => 'session_required',
                'message' => __('Session ID required', 'antek-chat-connector'),
            ], 400);
        }

        // Rate limit check
        $rate_limiter = Antek_Chat_Rate_Limiter::create_from_preset('file_uploads');
        $rate_check = $rate_limiter->consume('upload_' . $session_id, 1);

        if (is_wp_error($rate_check)) {
            return new WP_REST_Response([
                'error' => 'rate_limited',
                'message' => $rate_check->get_error_message(),
            ], 429);
        }

        // Get uploaded file
        $files = $request->get_file_params();

        if (empty($files['file'])) {
            return new WP_REST_Response([
                'error' => 'file_missing',
                'message' => __('No file uploaded', 'antek-chat-connector'),
            ], 400);
        }

        // Upload file
        $media_manager = new Antek_Chat_Media_Manager();
        $result = $media_manager->upload_file($files['file'], $session_id);

        if (is_wp_error($result)) {
            return new WP_REST_Response([
                'error' => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ], 400);
        }

        return new WP_REST_Response($result, 200);
    }

    /**
     * Serve media
     *
     * Serves media file with token-based authentication
     *
     * @param WP_REST_Request $request REST request object.
     * @return void
     * @since 1.1.0
     */
    public function serve_media($request) {
        $filename = $request->get_param('filename');
        $token = $request->get_param('token');

        // Serve media file (exits after sending file)
        $media_manager = new Antek_Chat_Media_Manager();
        $media_manager->serve_media($filename, $token);
    }
}
