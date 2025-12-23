/**
 * Voice Provider Factory (JavaScript)
 *
 * Factory Pattern implementation for frontend voice provider instantiation
 *
 * @package Antek_Chat_Connector
 * @since 1.1.0
 */

(function(window) {
    'use strict';

    // File integrity marker - DO NOT REMOVE
    const VOICE_PROVIDER_FACTORY_VERSION = '1.1.16';
    const VOICE_PROVIDER_FACTORY_BUILD = '1.1.16-2025-12-22';

    console.log('[VoiceProviderFactory] Loaded version:', VOICE_PROVIDER_FACTORY_VERSION);
    console.log('[VoiceProviderFactory] Build:', VOICE_PROVIDER_FACTORY_BUILD);

    // Expose version for debugging
    window.VOICE_PROVIDER_FACTORY_VERSION = VOICE_PROVIDER_FACTORY_VERSION;

    /**
     * Voice Provider Factory class
     *
     * Implements Factory Pattern for runtime provider switching
     */
    class VoiceProviderFactory {
        /**
         * Provider registry
         * Maps provider names to constructor functions
         */
        static providers = {};

        /**
         * Cached provider instances
         */
        static instances = {};

        /**
         * Register provider
         *
         * @param {string} name Provider name
         * @param {Function} providerClass Provider constructor
         */
        static register(name, providerClass) {
            if (typeof providerClass !== 'function') {
                throw new Error('Provider class must be a constructor function');
            }

            VoiceProviderFactory.providers[name] = providerClass;
        }

        /**
         * Create provider instance
         *
         * @param {string} providerName Provider name ('retell', 'elevenlabs')
         * @param {Object} config Configuration object
         * @return {Object} Provider instance
         * @throws {Error} If provider not found
         */
        static create(providerName, config = {}) {
            console.log('[VoiceProviderFactory] Creating provider:', providerName);
            console.log('[VoiceProviderFactory] Config:', config);
            console.log('[VoiceProviderFactory] Available providers:', Object.keys(VoiceProviderFactory.providers));

            // Check if provider is registered
            if (!VoiceProviderFactory.providers[providerName]) {
                console.error('[VoiceProviderFactory] Provider class not found:', providerName);
                throw new Error(`Unknown voice provider: ${providerName}`);
            }

            // Check cache
            const cacheKey = `${providerName}_${JSON.stringify(config)}`;
            if (VoiceProviderFactory.instances[cacheKey]) {
                console.log('[VoiceProviderFactory] Returning cached provider instance');
                return VoiceProviderFactory.instances[cacheKey];
            }

            // Instantiate provider
            const ProviderClass = VoiceProviderFactory.providers[providerName];
            console.log('[VoiceProviderFactory] Instantiating provider class...');
            const provider = new ProviderClass(config);
            console.log('[VoiceProviderFactory] Provider created successfully:', provider);

            // Cache instance
            VoiceProviderFactory.instances[cacheKey] = provider;

            return provider;
        }

        /**
         * Get available providers
         *
         * @return {Array} Array of provider names
         */
        static getAvailableProviders() {
            return Object.keys(VoiceProviderFactory.providers);
        }

        /**
         * Check if provider is available
         *
         * @param {string} providerName Provider name
         * @return {boolean} True if available
         */
        static isAvailable(providerName) {
            return VoiceProviderFactory.providers.hasOwnProperty(providerName);
        }

        /**
         * Clear cache
         *
         * Clears all cached provider instances
         */
        static clearCache() {
            VoiceProviderFactory.instances = {};
        }

        /**
         * Get enabled provider from WordPress
         *
         * Fetches provider configuration from REST API
         *
         * @param {string} restUrl WordPress REST API base URL
         * @param {string} nonce WordPress nonce for authentication
         * @return {Promise<Object>} Provider instance
         */
        static async getEnabledProvider(restUrl, nonce) {
            const methodName = '[VoiceProviderFactory.getEnabledProvider]';

            try {
                console.log(methodName, 'STARTING - Version 1.1.6');
                console.log(methodName, 'REST URL:', restUrl);
                console.log(methodName, 'Nonce present:', !!nonce);

                const url = restUrl + 'antek-chat/v1/providers';
                console.log(methodName, 'Full URL:', url);

                console.log(methodName, 'Fetching provider configuration...');
                const response = await fetch(url, {
                    method: 'GET',
                    headers: {
                        'X-WP-Nonce': nonce,
                        'Cache-Control': 'no-cache, no-store, must-revalidate',
                        'Pragma': 'no-cache',
                        'Expires': '0'
                    },
                    cache: 'no-store'
                });

                console.log(methodName, 'Response status:', response.status);
                console.log(methodName, 'Response OK:', response.ok);

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();
                console.log(methodName, 'Raw response data:', JSON.stringify(data, null, 2));

                // Log each field explicitly
                console.log(methodName, 'Validation checks:');
                console.log(methodName, '  - data exists:', !!data);
                console.log(methodName, '  - data.success:', data?.success);
                console.log(methodName, '  - data.provider:', data?.provider);
                console.log(methodName, '  - data.config exists:', !!data?.config);
                if (data?.config) {
                    console.log(methodName, '  - data.config:', data.config);
                }

                // Step-by-step validation with specific error messages
                if (!data) {
                    console.error(methodName, 'FAILED: Response data is null/undefined');
                    throw new Error('No data received from server');
                }

                if (data.success !== true) {
                    console.error(methodName, 'FAILED: success field is not true:', data.success);
                    console.error(methodName, 'Error message:', data.error);
                    throw new Error(data.error || 'Provider configuration returned success=false');
                }

                if (!data.provider) {
                    console.error(methodName, 'FAILED: provider field is missing or empty');
                    throw new Error('Provider name not specified in response');
                }

                if (!data.config) {
                    console.error(methodName, 'FAILED: config object is missing');
                    throw new Error('Provider configuration object is missing');
                }

                // Validate config contents
                const agentId = data.config.agentId || data.config.agent_id;
                console.log(methodName, 'Agent ID extracted:', agentId ? '(present)' : '(MISSING)');

                if (!agentId) {
                    console.error(methodName, 'FAILED: agentId not found in config');
                    console.error(methodName, 'Config contents:', data.config);
                    throw new Error('Agent ID not configured for provider: ' + data.provider);
                }

                // Build final config with defensive fallbacks
                const config = {
                    provider: data.config.provider || data.provider,
                    agentId: agentId,
                    isPublic: data.config.isPublic || data.config.is_public || false,
                    ajaxUrl: restUrl.replace('wp-json/', 'wp-admin/admin-ajax.php'),
                    restUrl: restUrl,
                    nonce: nonce,
                    restNonce: nonce
                };

                console.log(methodName, 'Final config built:', config);
                console.log(methodName, 'Creating provider:', data.provider);

                const provider = VoiceProviderFactory.create(data.provider, config);

                console.log(methodName, 'SUCCESS - Provider created:', provider);
                return provider;

            } catch (error) {
                console.error(methodName, 'FATAL ERROR:', error);
                console.error(methodName, 'Error name:', error.name);
                console.error(methodName, 'Error message:', error.message);
                console.error(methodName, 'Error stack:', error.stack);
                throw error;
            }
        }
    }

    /**
     * Base Voice Provider class
     *
     * Abstract base class that all providers should extend
     */
    class BaseVoiceProvider {
        constructor(config) {
            this.config = config;
            this.isConnected = false;
            this.eventListeners = {};
        }

        /**
         * Start voice call
         *
         * @param {Object} options Call options
         * @return {Promise<void>}
         */
        async startCall(options = {}) {
            throw new Error('startCall() must be implemented by provider');
        }

        /**
         * End voice call
         *
         * @return {Promise<void>}
         */
        async endCall() {
            throw new Error('endCall() must be implemented by provider');
        }

        /**
         * Check if connected
         *
         * @return {boolean} Connection status
         */
        isCallActive() {
            return this.isConnected;
        }

        /**
         * Add event listener
         *
         * Standard events:
         * - connected: Voice call connected
         * - disconnected: Voice call ended
         * - user_speaking: User is speaking {is_speaking: bool}
         * - agent_speaking: Agent is speaking {is_speaking: bool}
         * - transcript: User transcript {text: string, is_final: bool}
         * - agent_response: Agent text response {text: string}
         * - error: Error occurred {code: string, message: string}
         *
         * @param {string} event Event name
         * @param {Function} callback Event callback
         */
        on(event, callback) {
            if (!this.eventListeners[event]) {
                this.eventListeners[event] = [];
            }
            this.eventListeners[event].push(callback);
        }

        /**
         * Remove event listener
         *
         * @param {string} event Event name
         * @param {Function} callback Event callback
         */
        off(event, callback) {
            if (!this.eventListeners[event]) return;

            this.eventListeners[event] = this.eventListeners[event].filter(
                cb => cb !== callback
            );
        }

        /**
         * Emit event
         *
         * @param {string} event Event name
         * @param {*} data Event data
         */
        emit(event, data) {
            if (!this.eventListeners[event]) return;

            this.eventListeners[event].forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    console.error(`[Antek Chat] Error in ${event} event handler:`, error);
                }
            });
        }

        /**
         * Generate access token
         *
         * Fetches token from WordPress REST API
         *
         * @param {Object} options Token options
         * @return {Promise<Object>} Token data
         */
        async generateToken(options = {}) {
            const { restUrl, nonce } = this.config;

            if (!restUrl || !nonce) {
                throw new Error('REST URL and nonce required for token generation');
            }

            try {
                const response = await fetch(`${restUrl}/antek-chat/v1/token/${this.getProviderName()}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': nonce
                    },
                    body: JSON.stringify(options)
                });

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.message || 'Token generation failed');
                }

                return await response.json();

            } catch (error) {
                console.error('[Antek Chat] Token generation failed:', error);
                throw error;
            }
        }

        /**
         * Get provider name
         *
         * @return {string} Provider name
         */
        getProviderName() {
            throw new Error('getProviderName() must be implemented by provider');
        }

        /**
         * Log message
         *
         * @param {string} message Log message
         * @param {string} level Log level (info, warn, error)
         * @param {*} data Additional data
         */
        log(message, level = 'info', data = null) {
            const logMethod = console[level] || console.log;
            const logMessage = `[Antek Chat][${this.getProviderName()}] ${message}`;

            if (data) {
                logMethod(logMessage, data);
            } else {
                logMethod(logMessage);
            }
        }
    }

    // Export to global scope
    window.VoiceProviderFactory = VoiceProviderFactory;
    window.BaseVoiceProvider = BaseVoiceProvider;

})(window);
