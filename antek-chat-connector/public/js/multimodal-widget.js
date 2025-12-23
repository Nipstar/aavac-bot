/**
 * Multimodal Widget Component
 *
 * Extends base chat widget with voice calls and media attachments
 *
 * @package Antek_Chat_Connector
 * @since 1.1.0
 */

(function($) {
    'use strict';

    /**
     * Multimodal Chat Widget Class
     *
     * Extends AntekChatWidget with voice and media capabilities
     */
    class AntekMultimodalWidget extends AntekChatWidget {
        constructor(config) {
            super(config);

            // Multimodal-specific properties
            this.voiceProvider = null;
            this.fileUploader = null;
            this.isVoiceActive = false;
            this.uploadedMedia = [];
            this.useRestApi = config.useRestApi || false;

            // Initialize multimodal features
            this.initMultimodal();
        }

        /**
         * Initialize multimodal features
         */
        initMultimodal() {
            // Initialize file uploader if enabled
            if (this.config.mediaEnabled) {
                this.initFileUploader();
            }

            // Initialize voice provider if enabled
            if (this.config.voiceEnabled) {
                this.initVoiceProvider();
            }

            // Add multimodal event listeners
            this.attachMultimodalListeners();
        }

        /**
         * Initialize file uploader
         */
        initFileUploader() {
            const dropZone = document.getElementById('antek-chat-drop-zone');
            const fileInput = document.getElementById('antek-file-input');

            if (!dropZone || !fileInput) {
                console.warn('File upload elements not found');
                return;
            }

            this.fileUploader = new AntekFileUploader({
                restUrl: this.config.restUrl,
                nonce: this.config.nonce,
                dropZone: dropZone,
                fileInput: fileInput,
                settings: {
                    maxFileSizeMB: this.config.maxFileSizeMB || 50,
                    allowedTypes: this.config.allowedFileTypes || ['image', 'audio', 'document', 'video']
                },
                onUploadComplete: (fileData) => this.handleFileUploaded(fileData),
                onUploadError: (error) => this.handleFileUploadError(error)
            });
        }

        /**
         * Initialize voice provider
         */
        async initVoiceProvider() {
            console.log('Antek Chat: Initializing voice provider...', this.config);

            // Check if voice is actually enabled
            if (!this.config.voiceEnabled) {
                console.warn('Antek Chat: Voice is disabled in settings');
                return;
            }

            // Check if VoiceProviderFactory exists
            if (typeof VoiceProviderFactory === 'undefined') {
                console.error('Antek Chat: VoiceProviderFactory not loaded');
                this.showError('Voice provider not loaded');
                return;
            }

            console.log('Antek Chat: Voice provider:', this.config.voiceProvider);

            // Disable voice button while initializing
            const voiceButton = document.querySelector('.antek-chat-voice-call-button');
            if (voiceButton) {
                voiceButton.disabled = true;
                voiceButton.style.opacity = '0.5';
                voiceButton.style.cursor = 'not-allowed';
                voiceButton.title = 'Loading voice provider...';
                console.log('Antek Chat: Voice button disabled during initialization');
            }

            try {
                // Get enabled provider from server
                console.log('Antek Chat: Getting enabled provider from server...');
                const provider = await VoiceProviderFactory.getEnabledProvider(
                    this.config.restUrl,
                    this.config.nonce
                );

                this.voiceProvider = provider;
                console.log('Antek Chat: Voice provider initialized successfully');

                // Setup voice event handlers
                this.setupVoiceEventHandlers();

                // Enable voice button once provider is ready
                if (voiceButton) {
                    voiceButton.disabled = false;
                    voiceButton.style.opacity = '1';
                    voiceButton.style.cursor = 'pointer';
                    voiceButton.title = 'Click to start voice call';
                    console.log('Antek Chat: Voice button enabled - provider ready');
                }

            } catch (error) {
                console.error('Antek Chat: Failed to initialize voice provider:', error);
                this.showError('Voice features unavailable: ' + error.message);

                // Keep button disabled on error
                if (voiceButton) {
                    voiceButton.disabled = true;
                    voiceButton.style.opacity = '0.3';
                    voiceButton.style.cursor = 'not-allowed';
                    voiceButton.title = 'Voice provider failed to load';
                    console.log('Antek Chat: Voice button kept disabled due to error');
                }
            }
        }

        /**
         * Setup voice provider event handlers
         */
        setupVoiceEventHandlers() {
            if (!this.voiceProvider) return;

            // Voice connected
            this.voiceProvider.on('connected', (data) => {
                this.isVoiceActive = true;
                this.updateVoiceUI('connected', data);
                this.addSystemMessage('Voice call connected');
            });

            // Voice disconnected
            this.voiceProvider.on('disconnected', (data) => {
                this.isVoiceActive = false;
                this.updateVoiceUI('disconnected', data);
                this.addSystemMessage('Voice call ended');
            });

            // User speaking
            this.voiceProvider.on('user_speaking', (data) => {
                this.updateVoiceUI('user_speaking', data);
            });

            // Agent speaking
            this.voiceProvider.on('agent_speaking', (data) => {
                this.updateVoiceUI('agent_speaking', data);
            });

            // Transcript received
            this.voiceProvider.on('transcript', (data) => {
                if (data.is_final) {
                    this.addMessage(data.text, 'user');
                }
            });

            // Agent response
            this.voiceProvider.on('agent_response', (data) => {
                this.addMessage(data.text, 'bot');
            });

            // Error
            this.voiceProvider.on('error', (data) => {
                console.error('Voice error:', data);
                this.showError(data.message || 'Voice call error');
                this.stopVoiceCall();
            });
        }

        /**
         * Attach multimodal event listeners
         */
        attachMultimodalListeners() {
            const self = this;
            console.log('Antek Chat: Attaching multimodal listeners...');

            // File attachment button
            $('#antek-attach-button').on('click', function() {
                console.log('Antek Chat: File attach button clicked');
                $('#antek-file-input').click();
            });

            // Voice call button
            const voiceButton = $('#antek-voice-call-button');
            if (voiceButton.length) {
                console.log('Antek Chat: Voice call button found, attaching listener');
                voiceButton.on('click', function() {
                    console.log('Antek Chat: Voice call button clicked. Voice active:', self.isVoiceActive);
                    if (self.isVoiceActive) {
                        self.stopVoiceCall();
                    } else {
                        self.startVoiceCall();
                    }
                });
            } else {
                console.warn('Antek Chat: Voice call button (#antek-voice-call-button) not found in DOM');
            }

            // Clear attachments button
            $(document).on('click', '.antek-clear-attachments', function() {
                self.clearAttachments();
            });

            console.log('Antek Chat: Multimodal listeners attached');
        }

        /**
         * Start voice call
         */
        async startVoiceCall() {
            console.log('Antek Chat: startVoiceCall() called');
            console.log('Antek Chat: Voice provider exists:', !!this.voiceProvider);

            const button = document.getElementById('antek-voice-call-button');
            const statusText = document.querySelector('.antek-voice-status-text');

            if (!this.voiceProvider) {
                console.error('Antek Chat: Voice provider not initialized');

                // Show error in UI
                if (statusText) {
                    statusText.textContent = '⚠️ Voice unavailable';
                    statusText.style.color = '#ffeb3b';
                }

                if (button) {
                    button.style.opacity = '0.5';
                    button.style.cursor = 'not-allowed';
                    button.style.background = 'linear-gradient(135deg, #666 0%, #888 100%)';
                }

                // Show error in a nice way
                const errorDiv = document.createElement('div');
                errorDiv.style.cssText = `
                    position: absolute;
                    top: 20px;
                    left: 50%;
                    transform: translateX(-50%);
                    background: rgba(255, 255, 255, 0.95);
                    color: #d32f2f;
                    padding: 12px 20px;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                    font-size: 14px;
                    font-weight: 600;
                    z-index: 100;
                `;
                errorDiv.textContent = '⚠️ Voice not configured - check settings';

                const voiceMode = document.querySelector('.antek-voice-mode');
                if (voiceMode && !voiceMode.querySelector('.error-message')) {
                    errorDiv.className = 'error-message';
                    voiceMode.appendChild(errorDiv);

                    setTimeout(() => errorDiv.remove(), 5000);
                }

                return;
            }

            try {
                if (this.voiceProvider.isConnected) {
                    // End call
                    console.log('Antek Chat: Ending voice call');
                    await this.voiceProvider.endCall();

                    if (button) {
                        button.classList.remove('active');
                    }
                    if (statusText) {
                        statusText.textContent = 'Click the microphone to start talking';
                        statusText.style.color = 'white';
                    }
                } else {
                    // Start call
                    console.log('Antek Chat: Starting voice call');

                    if (button) {
                        button.classList.add('active');
                    }
                    if (statusText) {
                        statusText.textContent = 'Connecting...';
                        statusText.style.color = 'white';
                    }

                    await this.voiceProvider.startCall({
                        agentId: this.config.voiceProvider?.agentId,
                        sessionId: this.sessionId,
                        metadata: {
                            pageUrl: window.location.href,
                            timestamp: Date.now()
                        }
                    });

                    console.log('Antek Chat: Voice call started');
                    if (statusText) {
                        statusText.textContent = '🎙️ Listening... speak now!';
                    }
                }

            } catch (error) {
                console.error('Antek Chat: Voice call error:', error);

                if (button) {
                    button.classList.remove('active');
                }
                if (statusText) {
                    statusText.textContent = '⚠️ Connection failed';
                    statusText.style.color = '#ffeb3b';
                }

                // Show error notification
                const errorDiv = document.createElement('div');
                errorDiv.style.cssText = `
                    position: absolute;
                    top: 20px;
                    left: 50%;
                    transform: translateX(-50%);
                    background: rgba(255, 255, 255, 0.95);
                    color: #d32f2f;
                    padding: 12px 20px;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                    font-size: 14px;
                    font-weight: 600;
                    z-index: 100;
                    max-width: 300px;
                    text-align: center;
                `;
                errorDiv.textContent = '⚠️ ' + (error.message || 'Failed to connect');

                const voiceMode = document.querySelector('.antek-voice-mode');
                if (voiceMode) {
                    // Remove any existing error
                    const existingError = voiceMode.querySelector('.error-notification');
                    if (existingError) existingError.remove();

                    errorDiv.className = 'error-notification';
                    voiceMode.appendChild(errorDiv);

                    setTimeout(() => errorDiv.remove(), 5000);
                }
            }
        }

        /**
         * Stop voice call
         */
        async stopVoiceCall() {
            if (!this.voiceProvider || !this.isVoiceActive) return;

            try {
                await this.voiceProvider.endCall();
                this.isVoiceActive = false;
                this.updateVoiceUI('disconnected');
            } catch (error) {
                console.error('Failed to stop voice call:', error);
            }
        }

        /**
         * Update voice UI state
         *
         * @param {string} state - UI state (connecting, connected, disconnected, user_speaking, agent_speaking)
         * @param {Object} data - State data
         */
        updateVoiceUI(state, data = {}) {
            const $voiceButton = $('#antek-voice-call-button');
            const $voiceStatus = $('#antek-voice-status');

            // Remove all state classes
            $voiceButton.removeClass('connecting connected disconnected user-speaking agent-speaking');
            $voiceStatus.removeClass('active user-speaking agent-speaking');

            // Add current state class
            $voiceButton.addClass(state);

            // Update button text/icon
            switch (state) {
                case 'connecting':
                    $voiceButton.html('&#8987;'); // Clock icon
                    $voiceStatus.text('Connecting...');
                    break;
                case 'connected':
                    $voiceButton.html('&#128266;'); // Speaker icon
                    $voiceStatus.addClass('active').text('Voice call active');
                    break;
                case 'disconnected':
                    $voiceButton.html('&#127908;'); // Microphone icon
                    $voiceStatus.removeClass('active').text('');
                    break;
                case 'user_speaking':
                    if (data.is_speaking) {
                        $voiceStatus.addClass('user-speaking').text('You are speaking...');
                    }
                    break;
                case 'agent_speaking':
                    if (data.is_speaking) {
                        $voiceStatus.addClass('agent-speaking').text('Agent is speaking...');
                    } else {
                        $voiceStatus.removeClass('agent-speaking').text('Voice call active');
                    }
                    break;
            }
        }

        /**
         * Handle file uploaded
         *
         * @param {Object} fileData - Uploaded file data
         */
        handleFileUploaded(fileData) {
            this.uploadedMedia.push(fileData);
            this.updateAttachmentCounter();
        }

        /**
         * Handle file upload error
         *
         * @param {Object} error - Error data
         */
        handleFileUploadError(error) {
            this.showError(error.error || 'File upload failed');
        }

        /**
         * Update attachment counter
         */
        updateAttachmentCounter() {
            const count = this.uploadedMedia.length;
            const $counter = $('#antek-attachment-counter');

            if (count > 0) {
                $counter.text(count).show();
            } else {
                $counter.hide();
            }
        }

        /**
         * Clear all attachments
         */
        clearAttachments() {
            this.uploadedMedia = [];
            if (this.fileUploader) {
                this.fileUploader.clearPreviews();
            }
            this.updateAttachmentCounter();
        }

        /**
         * Send message (overridden to support media attachments)
         */
        async sendMessage() {
            const message = this.$input.val().trim();

            if ((!message && this.uploadedMedia.length === 0) || this.isLoading) {
                return;
            }

            // Add user message to UI
            if (message) {
                this.addMessage(message, 'user', this.uploadedMedia);
            }

            this.$input.val('');

            // Show loading indicator
            this.showLoading();

            try {
                let response;

                // Use REST API if enabled, otherwise fall back to AJAX
                if (this.useRestApi) {
                    response = await this.sendViaRestApi(message, this.uploadedMedia);
                } else {
                    response = await this.sendToServer(message);
                }

                if (response.success) {
                    const botResponse = response.data.response || response.data.message;
                    this.addMessage(botResponse, 'bot');
                    this.saveToHistory(message, botResponse, this.uploadedMedia);
                } else {
                    this.addMessage(this.config.strings.error, 'bot');
                    console.error('Chat error:', response.data?.message);
                }
            } catch (error) {
                this.addMessage(this.config.strings.error, 'bot');
                console.error('Chat error:', error);
            } finally {
                this.hideLoading();
                this.clearAttachments();
            }
        }

        /**
         * Send message via REST API
         *
         * @param {string} message - Message text
         * @param {Array} media - Array of media attachments
         * @return {Promise} Response promise
         */
        async sendViaRestApi(message, media = []) {
            const mediaIds = media.map(m => m.mediaId);

            // Remove trailing slash from restUrl to avoid double slashes
            const baseUrl = this.config.restUrl.endsWith('/') ? this.config.restUrl.slice(0, -1) : this.config.restUrl;
            const response = await fetch(`${baseUrl}/antek-chat/v1/message`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce
                },
                body: JSON.stringify({
                    session_id: this.sessionId,
                    message: message,
                    media_ids: mediaIds,
                    page_url: window.location.href,
                    metadata: {
                        timestamp: Date.now(),
                        user_agent: navigator.userAgent
                    }
                })
            });

            const data = await response.json();

            return {
                success: response.ok,
                data: data
            };
        }

        /**
         * Add message to chat (overridden to support media attachments)
         *
         * @param {string} text - Message text
         * @param {string} sender - Sender type (user/bot/system)
         * @param {Array} media - Optional media attachments
         */
        addMessage(text, sender, media = []) {
            // Remove welcome message if exists
            this.$messages.find('.antek-chat-welcome').remove();

            const $message = $('<div>')
                .addClass('antek-chat-message')
                .addClass('antek-chat-message-' + sender);

            // Add media previews if present
            if (media && media.length > 0) {
                const $mediaContainer = $('<div>').addClass('antek-message-media');

                media.forEach(item => {
                    const $mediaItem = this.createMediaPreview(item);
                    $mediaContainer.append($mediaItem);
                });

                $message.append($mediaContainer);
            }

            // Add text content
            if (text) {
                const $textContent = $('<div>')
                    .addClass('antek-message-text')
                    .text(text);
                $message.append($textContent);
            }

            this.$messages.append($message);
            this.scrollToBottom();
        }

        /**
         * Create media preview element
         *
         * @param {Object} mediaData - Media data
         * @return {jQuery} Preview element
         */
        createMediaPreview(mediaData) {
            const $preview = $('<div>').addClass('antek-media-preview');

            switch (mediaData.fileType) {
                case 'image':
                    const $img = $('<img>')
                        .attr('src', mediaData.url)
                        .attr('alt', mediaData.fileName)
                        .addClass('antek-media-image');
                    $preview.append($img);
                    break;

                case 'audio':
                    const $audio = $('<audio>')
                        .attr('src', mediaData.url)
                        .attr('controls', true)
                        .addClass('antek-media-audio');
                    $preview.append($audio);
                    break;

                case 'video':
                    const $video = $('<video>')
                        .attr('src', mediaData.url)
                        .attr('controls', true)
                        .addClass('antek-media-video');
                    $preview.append($video);
                    break;

                case 'document':
                default:
                    const $link = $('<a>')
                        .attr('href', mediaData.url)
                        .attr('target', '_blank')
                        .attr('download', mediaData.fileName)
                        .addClass('antek-media-document')
                        .html(`&#128196; ${this.escapeHtml(mediaData.fileName)}`);
                    $preview.append($link);
                    break;
            }

            return $preview;
        }

        /**
         * Add system message
         *
         * @param {string} text - System message text
         */
        addSystemMessage(text) {
            const $message = $('<div>')
                .addClass('antek-chat-message antek-chat-message-system')
                .text(text);

            this.$messages.append($message);
            this.scrollToBottom();
        }

        /**
         * Show error message
         *
         * @param {string} message - Error message
         */
        showError(message) {
            const $error = $('<div>')
                .addClass('antek-chat-error')
                .text(message);

            this.$messages.append($error);
            this.scrollToBottom();

            // Auto-remove after 5 seconds
            setTimeout(() => {
                $error.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }

        /**
         * Save to conversation history (overridden to support media)
         *
         * @param {string} message - Message text
         * @param {string} response - Bot response
         * @param {Array} media - Media attachments
         */
        saveToHistory(message, response, media = []) {
            this.conversationHistory.push({
                message: message,
                response: response,
                media: media.map(m => ({
                    fileName: m.fileName,
                    fileType: m.fileType,
                    url: m.url
                })),
                timestamp: Date.now()
            });

            // Save to localStorage
            try {
                localStorage.setItem(
                    'antek_chat_' + this.sessionId,
                    JSON.stringify(this.conversationHistory)
                );
            } catch (e) {
                console.error('Failed to save conversation history:', e);
            }
        }

        /**
         * Load conversation history (overridden to support media)
         */
        loadConversationHistory() {
            try {
                const saved = localStorage.getItem('antek_chat_' + this.sessionId);

                if (saved) {
                    this.conversationHistory = JSON.parse(saved);

                    // Display last 10 messages
                    const recentMessages = this.conversationHistory.slice(-10);

                    recentMessages.forEach(item => {
                        this.addMessage(item.message, 'user', item.media || []);
                        this.addMessage(item.response, 'bot');
                    });
                }
            } catch (e) {
                console.error('Failed to load conversation history:', e);
            }
        }

        /**
         * Escape HTML
         *
         * @param {string} text - Text to escape
         * @return {string} Escaped text
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /**
         * Cleanup on widget destroy
         */
        destroy() {
            // Stop voice call if active
            if (this.isVoiceActive) {
                this.stopVoiceCall();
            }

            // Clear uploads
            this.clearAttachments();

            // Call parent destroy if exists
            if (super.destroy) {
                super.destroy();
            }
        }
    }

    /**
     * Initialize multimodal widget when DOM is ready
     */
    $(document).ready(function() {
        if (typeof antekChatConfig !== 'undefined' && antekChatConfig.multimodalEnabled) {
            window.antekChat = new AntekMultimodalWidget(antekChatConfig);
        }
    });

    // Export for external use
    window.AntekMultimodalWidget = AntekMultimodalWidget;

})(jQuery);
