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

                // Check if Retell SDK is loaded (ESM export to window.RetellWebClient)
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

                // Initialize Retell client (from global window.RetellWebClient)
                console.log('[RetellProvider] Initializing RetellWebClient...');
                this.retellClient = new window.RetellWebClient();
                console.log('[RetellProvider] RetellWebClient initialized:', this.retellClient);

                // Set up event handlers
                this.setupEventHandlers();

                // Start call with token
                console.log('[RetellProvider] Starting call with token...');
                await this.retellClient.startCall({
                    accessToken: tokenData.access_token,
                    sampleRate: this.sampleRate,
                    emitRawAudioSamples: false
                });

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
                this.log('Failed to start call', 'error', error);
                this.emit('error', {
                    code: 'call_start_failed',
                    message: error.message
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
                    this.retellClient.stopCall();
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
    }

    // Export to global scope
    window.RetellProvider = RetellProvider;

})(window);
