/**
 * Retell AI Provider (JavaScript)
 *
 * Retell AI voice provider implementation
 *
 * @package Antek_Chat_Connector
 * @since 1.1.0
 */

(function(window) {
    'use strict';

    /**
     * Retell Provider class
     *
     * Integrates with Retell AI Web SDK
     */
    class RetellProvider extends window.BaseVoiceProvider {
        constructor(config) {
            super(config);

            this.retellClient = null;
            this.callId = null;
            this.agentId = config.agentId || null;
            this.sampleRate = config.sampleRate || 24000;
        }

        /**
         * Get provider name
         *
         * @return {string}
         */
        getProviderName() {
            return 'retell';
        }

        /**
         * Start voice call
         *
         * @param {Object} options Call options
         * @return {Promise<void>}
         */
        async startCall(options = {}) {
            if (this.isConnected) {
                throw new Error('Call already active');
            }

            try {
                this.log('Starting call...', 'info');
                console.log('[RetellProvider] Checking SDK availability...');
                console.log('[RetellProvider] window.RetellWebClient:', typeof window.RetellWebClient);

                // Check if Retell SDK is loaded (bundled as window.RetellWebClient)
                if (typeof window.RetellWebClient === 'undefined') {
                    const error = new Error('Retell SDK not loaded - window.RetellWebClient is undefined');
                    console.error('[RetellProvider] SDK CHECK FAILED:', error);
                    console.error('[RetellProvider] Available on window:', Object.keys(window).filter(k => k.toLowerCase().includes('retell')));
                    this.log('SDK not loaded', 'error', error);
                    throw error;
                }

                console.log('[RetellProvider] SDK check passed, generating token...');

                // Generate access token
                const tokenData = await this.generateToken({
                    agent_id: options.agentId || this.agentId,
                    metadata: options.metadata || {}
                });

                this.callId = tokenData.call_id;
                this.log('Token generated', 'info', { callId: this.callId });
                console.log('[RetellProvider] Token generated:', tokenData);

                // Initialize Retell client (from bundled window.RetellWebClient)
                console.log('[RetellProvider] Initializing RetellWebClient...');
                this.retellClient = new window.RetellWebClient();
                console.log('[RetellProvider] RetellWebClient initialized:', this.retellClient);

                // Set up event handlers
                this.setupEventHandlers();

                // Start call with token
                console.log('[RetellProvider] Starting call with token...');
                const conversationConfig = {
                    accessToken: tokenData.access_token,
                    sampleRate: this.sampleRate || 24000,
                    enableAudio: true
                };
                console.log('[RetellProvider] Conversation config:', conversationConfig);
                console.log('[RetellProvider] Access token:', tokenData.access_token ? 'present' : 'MISSING');
                console.log('[RetellProvider] Sample rate:', this.sampleRate);

                await this.retellClient.startConversation(conversationConfig);

                this.isConnected = true;
                this.emit('connected', { callId: this.callId });

                this.log('Call started', 'info', { callId: this.callId });
                console.log('[RetellProvider] Call started successfully');

            } catch (error) {
                console.error('[RetellProvider] CALL START FAILED:', error);
                console.error('[RetellProvider] Error details:', {
                    message: error.message,
                    stack: error.stack,
                    name: error.name
                });

                // Provide specific error messages for common issues
                let userMessage = 'Voice call failed: ' + error.message;

                if (error.name === 'NotAllowedError' || error.message.includes('Permission denied')) {
                    userMessage = '🎤 Microphone permission denied. Please allow microphone access in your browser settings and try again.';
                    console.error('[RetellProvider] MICROPHONE PERMISSION DENIED');
                } else if (error.name === 'NotFoundError' || error.message.includes('device not found')) {
                    userMessage = '🎤 No microphone found. Please check your audio device is connected.';
                    console.error('[RetellProvider] NO MICROPHONE DEVICE FOUND');
                } else if (error.name === 'NotReadableError' || error.message.includes('already in use')) {
                    userMessage = '🎤 Microphone is already in use by another application. Close other apps and try again.';
                    console.error('[RetellProvider] MICROPHONE IN USE');
                } else if (error.message.includes('startConversation')) {
                    userMessage = '❌ Retell SDK error. Please refresh the page and try again.';
                    console.error('[RetellProvider] RETELL SDK ERROR');
                }

                this.log(userMessage, 'error', error);
                this.emit('error', {
                    code: 'call_start_failed',
                    message: userMessage
                });
                throw error;
            }
        }

        /**
         * End voice call
         *
         * @return {Promise<void>}
         */
        async endCall() {
            if (!this.isConnected) {
                return;
            }

            try {
                this.log('Ending call...', 'info');

                if (this.retellClient) {
                    this.retellClient.stopConversation();
                }

                this.isConnected = false;
                this.emit('disconnected', { callId: this.callId });

                this.log('Call ended', 'info');

            } catch (error) {
                this.log('Error ending call', 'error', error);
            }
        }

        /**
         * Set up event handlers
         *
         * Maps Retell events to standard events
         */
        setupEventHandlers() {
            if (!this.retellClient) return;

            // Call started
            this.retellClient.on('call_started', () => {
                this.log('Retell call started', 'info');
            });

            // Call ended
            this.retellClient.on('call_ended', () => {
                this.log('Retell call ended', 'info');
                this.isConnected = false;
                this.emit('disconnected', { callId: this.callId });
            });

            // Agent start talking
            this.retellClient.on('agent_start_talking', () => {
                this.emit('agent_speaking', { is_speaking: true });
            });

            // Agent stop talking
            this.retellClient.on('agent_stop_talking', () => {
                this.emit('agent_speaking', { is_speaking: false });
            });

            // User start speaking
            this.retellClient.on('user_start_talking', () => {
                this.emit('user_speaking', { is_speaking: true });
            });

            // User stop speaking
            this.retellClient.on('user_stop_talking', () => {
                this.emit('user_speaking', { is_speaking: false });
            });

            // Transcript update
            this.retellClient.on('update', (update) => {
                if (update.transcript) {
                    // User transcript
                    update.transcript.forEach(item => {
                        if (item.role === 'user') {
                            this.emit('transcript', {
                                text: item.content,
                                is_final: true,
                                speaker: 'user'
                            });
                        } else if (item.role === 'agent') {
                            this.emit('agent_response', {
                                text: item.content
                            });
                        }
                    });
                }
            });

            // Error
            this.retellClient.on('error', (error) => {
                this.log('Retell error', 'error', error);
                this.emit('error', {
                    code: 'retell_error',
                    message: error.message || 'Unknown error'
                });
            });

            // Audio level (optional)
            this.retellClient.on('audio', (audio) => {
                // Can emit audio level events if needed
                // this.emit('audio_level', { level: audio.level });
            });
        }

        /**
         * Get current call ID
         *
         * @return {string|null} Call ID
         */
        getCallId() {
            return this.callId;
        }

        /**
         * Get audio volume
         *
         * @return {number} Volume level (0-1)
         */
        getVolume() {
            return this.retellClient?.getVolume() || 0;
        }

        /**
         * Set audio volume
         *
         * @param {number} volume Volume level (0-1)
         */
        setVolume(volume) {
            if (this.retellClient) {
                this.retellClient.setVolume(volume);
            }
        }
    }

    // Register provider with factory
    if (window.VoiceProviderFactory) {
        window.VoiceProviderFactory.register('retell', RetellProvider);
        // n8n-retell uses the same Retell SDK, just proxies token generation through n8n
        window.VoiceProviderFactory.register('n8n-retell', RetellProvider);
    }

    // Export to global scope
    window.RetellProvider = RetellProvider;

})(window);
