/**
 * Chat Widget JavaScript
 *
 * @package Antek_Chat_Connector
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Chat Widget Class
     * Exposed to global scope for extension by multimodal-widget.js
     */
    window.AntekChatWidget = class AntekChatWidget {
        constructor(config) {
            this.config = config;
            this.sessionId = config.sessionId;
            this.isOpen = false;
            this.conversationHistory = [];
            this.$widget = null;
            this.$window = null;
            this.$messages = null;
            this.$input = null;
            this.isLoading = false;

            this.init();
        }

        /**
         * Initialize the widget
         */
        init() {
            this.$widget = $('#antek-chat-widget');
            this.$window = $('#antek-chat-window');
            this.$messages = $('#antek-chat-messages');
            this.$input = $('#antek-chat-input');

            this.detectThemeColors();
            this.attachEventListeners();
            this.loadConversationHistory();
            this.showWidget();

            // Run diagnostics after initialization
            setTimeout(() => {
                const diagnostics = this.runDiagnostics();
                console.log('Antek Chat: Diagnostics complete', diagnostics);
            }, 500);
        }

        /**
         * Run comprehensive diagnostics on the chat widget
         */
        runDiagnostics() {
            console.log('=== ANTEK CHAT DIAGNOSTICS ===');

            const results = {
                config: {},
                dom: {},
                scripts: {},
                voiceProvider: {},
                issues: []
            };

            // 1. Check config
            console.log('1. Checking config...');
            results.config = {
                exists: typeof this.config !== 'undefined',
                voiceEnabled: this.config?.voiceEnabled,
                multimodalEnabled: this.config?.multimodalEnabled,
                voiceProvider: this.config?.voiceProvider,
                sessionId: this.config?.sessionId
            };
            console.log('Config:', results.config);

            if (!results.config.voiceEnabled) {
                results.issues.push('Voice is disabled in config');
            }

            // 2. Check DOM elements
            console.log('2. Checking DOM elements...');
            const $modeToggle = $('#antek-mode-toggle');
            const $modeButtons = $('.antek-mode-btn');
            const $textButton = $('.antek-mode-btn[data-mode="text"]');
            const $voiceButton = $('.antek-mode-btn[data-mode="voice"]');
            const $voiceCallButton = $('#antek-voice-call-button');
            const $textMode = $('#antek-text-mode');
            const $voiceMode = $('#antek-voice-mode');

            results.dom = {
                modeToggle: {
                    exists: $modeToggle.length > 0,
                    visible: $modeToggle.is(':visible'),
                    display: $modeToggle.css('display')
                },
                modeButtons: {
                    count: $modeButtons.length,
                    textButton: {
                        exists: $textButton.length > 0,
                        visible: $textButton.is(':visible'),
                        hasClickHandler: $._data($textButton[0], 'events')?.click !== undefined
                    },
                    voiceButton: {
                        exists: $voiceButton.length > 0,
                        visible: $voiceButton.is(':visible'),
                        hasClickHandler: $._data($voiceButton[0], 'events')?.click !== undefined
                    }
                },
                voiceCallButton: {
                    exists: $voiceCallButton.length > 0,
                    visible: $voiceCallButton.is(':visible')
                },
                inputModes: {
                    textMode: {
                        exists: $textMode.length > 0,
                        visible: $textMode.is(':visible'),
                        hasActiveClass: $textMode.hasClass('active')
                    },
                    voiceMode: {
                        exists: $voiceMode.length > 0,
                        visible: $voiceMode.is(':visible'),
                        hasActiveClass: $voiceMode.hasClass('active')
                    }
                }
            };
            console.log('DOM elements:', results.dom);

            if (!results.dom.modeToggle.exists) {
                results.issues.push('Mode toggle container not found in DOM');
            }
            if (results.dom.modeButtons.count === 0) {
                results.issues.push('No mode toggle buttons found');
            }
            if (!results.dom.modeButtons.textButton.hasClickHandler || !results.dom.modeButtons.voiceButton.hasClickHandler) {
                results.issues.push('Mode buttons missing click handlers');
            }

            // 3. Check for voice provider scripts
            console.log('3. Checking script availability...');
            results.scripts = {
                VoiceProviderFactory: typeof VoiceProviderFactory !== 'undefined',
                RetellProvider: typeof RetellProvider !== 'undefined',
                RetellWebClient: typeof RetellWebClient !== 'undefined',
                antekVoiceInterface: typeof window.antekVoiceInterface !== 'undefined',
                AntekMultimodalWidget: typeof window.AntekMultimodalWidget !== 'undefined'
            };
            console.log('Scripts loaded:', results.scripts);

            if (results.config.voiceEnabled) {
                if (!results.scripts.VoiceProviderFactory) {
                    results.issues.push('VoiceProviderFactory not loaded');
                }
                if (!results.scripts.AntekMultimodalWidget) {
                    results.issues.push('AntekMultimodalWidget not loaded');
                }
            }

            // 4. Check voice provider initialization
            console.log('4. Checking voice provider...');
            if (window.AntekMultimodalWidget) {
                results.voiceProvider = {
                    widgetExists: true,
                    providerInitialized: window.antekChat?.voiceProvider !== undefined
                };
            } else {
                results.voiceProvider = {
                    widgetExists: false,
                    providerInitialized: false
                };
            }
            console.log('Voice provider:', results.voiceProvider);

            // 5. Summary
            console.log('=== DIAGNOSTIC SUMMARY ===');
            if (results.issues.length > 0) {
                console.error('Issues found:', results.issues);
            } else {
                console.log('No issues found - configuration looks correct');
            }
            console.log('========================');

            return results;
        }

        /**
         * Detect and apply theme colors at runtime
         */
        detectThemeColors() {
            const root = document.documentElement;
            const computedStyles = getComputedStyle(root);

            // Try to detect Elementor variables
            const elementorPrimary = computedStyles.getPropertyValue('--e-global-color-primary').trim();
            if (elementorPrimary) {
                console.log('Antek Chat: Using Elementor colors');
                root.style.setProperty('--antek-primary', elementorPrimary);
                root.style.setProperty('--antek-secondary',
                    computedStyles.getPropertyValue('--e-global-color-secondary').trim());
                root.style.setProperty('--antek-text',
                    computedStyles.getPropertyValue('--e-global-color-text').trim());
                return true;
            }

            // Try to detect Divi variables (Divi 5+)
            const diviPrimary = computedStyles.getPropertyValue('--et-global-color-primary').trim();
            if (diviPrimary) {
                console.log('Antek Chat: Using Divi colors');
                root.style.setProperty('--antek-primary', diviPrimary);
                return true;
            }

            console.log('Antek Chat: Using plugin default colors');
            return false;
        }

        /**
         * Show the widget
         */
        showWidget() {
            this.$widget.addClass('antek-initialized');
        }

        /**
         * Attach event listeners
         */
        attachEventListeners() {
            var self = this;

            // Toggle widget
            $('#antek-chat-trigger').on('click', function() {
                self.toggle();
            });

            // Close button
            $('#antek-chat-close').on('click', function() {
                self.close();
            });

            // Send message
            $('#antek-chat-send').on('click', function() {
                self.sendMessage();
            });

            // Handle Enter key (send on Enter, new line on Shift+Enter)
            this.$input.on('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault(); // Prevent new line
                    self.sendMessage();
                }
                // Shift+Enter allows new line
            });

            // Setup auto-resize for textarea
            this.setupAutoResize();

            // Voice button (if enabled)
            if (this.config.voiceEnabled) {
                console.log('Antek Chat: Voice is enabled, attaching voice listeners');

                const $voiceButton = $('#antek-voice-button');
                console.log('Antek Chat: Voice button found:', $voiceButton.length);
                if ($voiceButton.length) {
                    $voiceButton.on('click', function() {
                        console.log('Antek Chat: Voice button clicked');
                        self.toggleVoice();
                    });
                }

                // Mode toggle buttons
                const $modeButtons = $('.antek-mode-btn');
                console.log('Antek Chat: Mode toggle buttons found:', $modeButtons.length);
                $modeButtons.each(function(index, button) {
                    console.log('Antek Chat: Button', index, 'mode:', $(button).data('mode'));
                });

                $modeButtons.on('click', function() {
                    const mode = $(this).data('mode');
                    console.log('Antek Chat: Mode button clicked, mode:', mode);
                    self.switchMode(mode);
                });

                // Load saved mode preference
                const savedMode = localStorage.getItem('antek_chat_mode') || 'text';
                console.log('Antek Chat: Loading saved mode:', savedMode);
                this.switchMode(savedMode);
            } else {
                console.log('Antek Chat: Voice is disabled, skipping voice listeners');
            }

            // Close on escape key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && self.isOpen) {
                    self.close();
                }
            });
        }

        /**
         * Toggle widget open/close
         */
        toggle() {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        }

        /**
         * Open widget
         */
        open() {
            this.isOpen = true;
            this.$window.fadeIn(200);
            this.$input.focus();
            this.scrollToBottom();
        }

        /**
         * Close widget
         */
        close() {
            this.isOpen = false;
            this.$window.fadeOut(200);
        }

        /**
         * Send message
         */
        async sendMessage() {
            var message = this.$input.val().trim();

            if (!message || this.isLoading) {
                return;
            }

            // Add user message to UI
            this.addMessage(message, 'user');
            this.$input.val('');

            // Reset textarea height after clearing
            if (this.$input[0]) {
                this.$input[0].style.height = 'auto';
            }

            // Show loading indicator
            this.showLoading();

            try {
                var response = await this.sendToServer(message);

                if (response.success) {
                    this.addMessage(response.data.response, 'bot');
                    this.saveToHistory(message, response.data.response);
                } else {
                    this.addMessage(this.config.strings.error, 'bot');
                    console.error('Chat error:', response.data.message);
                }
            } catch (error) {
                this.addMessage(this.config.strings.error, 'bot');
                console.error('Chat error:', error);
            } finally {
                this.hideLoading();
            }
        }

        /**
         * Send message to server
         */
        sendToServer(message) {
            return new Promise(function(resolve, reject) {
                $.ajax({
                    url: window.antekChatConfig.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'antek_chat_send_message',
                        nonce: window.antekChatConfig.nonce,
                        session_id: window.antekChatConfig.sessionId,
                        message: message,
                        page_url: window.location.href
                    },
                    success: function(response) {
                        console.log('Chat response:', response);
                        resolve(response);
                    },
                    error: function(xhr, status, error) {
                        console.error('Chat AJAX error:', {
                            status: status,
                            error: error,
                            response: xhr.responseText,
                            xhr: xhr
                        });
                        reject(error);
                    }
                });
            });
        }

        /**
         * Add message to chat
         */
        addMessage(text, sender) {
            // Remove welcome message if exists
            this.$messages.find('.antek-chat-welcome').remove();

            // IMPORTANT: Add proper class based on sender
            var $message = $('<div>')
                .addClass('antek-chat-message')
                .addClass('antek-chat-message-' + sender)
                .text(text);

            this.$messages.append($message);
            this.scrollToBottom();
        }

        /**
         * Show loading indicator
         */
        showLoading() {
            this.isLoading = true;

            var $loading = $('<div>')
                .addClass('antek-chat-message antek-chat-message-bot antek-chat-loading')
                .attr('id', 'antek-loading')
                .html('<span></span><span></span><span></span>');

            this.$messages.append($loading);
            this.scrollToBottom();
        }

        /**
         * Hide loading indicator
         */
        hideLoading() {
            this.isLoading = false;
            $('#antek-loading').remove();
        }

        /**
         * Scroll messages to bottom
         */
        scrollToBottom() {
            var self = this;
            setTimeout(function() {
                self.$messages.scrollTop(self.$messages[0].scrollHeight);
            }, 100);
        }

        /**
         * Save to conversation history
         */
        saveToHistory(message, response) {
            this.conversationHistory.push({
                message: message,
                response: response,
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
         * Load conversation history
         */
        loadConversationHistory() {
            try {
                var saved = localStorage.getItem('antek_chat_' + this.sessionId);

                if (saved) {
                    this.conversationHistory = JSON.parse(saved);

                    // Display last 10 messages
                    var recentMessages = this.conversationHistory.slice(-10);

                    recentMessages.forEach(function(item) {
                        this.addMessage(item.message, 'user');
                        this.addMessage(item.response, 'bot');
                    }.bind(this));
                }
            } catch (e) {
                console.error('Failed to load conversation history:', e);
            }
        }

        /**
         * Setup auto-resize for textarea
         */
        setupAutoResize() {
            var self = this;

            if (!this.$input || !this.$input.length) {
                console.warn('Antek Chat: Input element not found for auto-resize');
                return;
            }

            // Auto-resize function
            var autoResize = function() {
                var textarea = self.$input[0];
                textarea.style.height = 'auto'; // Reset height
                var newHeight = Math.min(textarea.scrollHeight, 120); // Max 120px (about 5 lines)
                textarea.style.height = newHeight + 'px';
            };

            // Listen for input
            this.$input.on('input', autoResize);

            // Initial resize
            autoResize();

            console.log('Antek Chat: Auto-resize setup complete');
        }

        /**
         * Toggle voice interface
         */
        toggleVoice() {
            if (window.antekVoiceInterface) {
                window.antekVoiceInterface.toggle();
            }
        }

        /**
         * Switch between text and voice modes
         */
        switchMode(mode) {
            console.log('Antek Chat: switchMode() called with mode:', mode);
            console.log('Antek Chat: Voice enabled:', this.config.voiceEnabled);

            // Update toggle button states
            $('.antek-mode-btn').removeClass('active');
            $(`.antek-mode-btn[data-mode="${mode}"]`).addClass('active');
            console.log('Antek Chat: Updated button states');

            // Switch input areas
            if (mode === 'text') {
                console.log('Antek Chat: Switching to text mode');
                $('#antek-text-mode').addClass('active').show();
                $('#antek-voice-mode').removeClass('active').hide();

                // Stop voice if active
                if (window.antekVoiceInterface && window.antekVoiceInterface.isActive) {
                    console.log('Antek Chat: Stopping voice interface');
                    window.antekVoiceInterface.stop();
                }
            } else if (mode === 'voice') {
                console.log('Antek Chat: Switching to voice mode');
                console.log('Antek Chat: Text mode element:', $('#antek-text-mode').length);
                console.log('Antek Chat: Voice mode element:', $('#antek-voice-mode').length);

                $('#antek-text-mode').removeClass('active').hide();
                $('#antek-voice-mode').addClass('active').show();
            }

            // Save preference
            localStorage.setItem('antek_chat_mode', mode);
            console.log('Antek Chat: Mode switched to:', mode);
        }

        /**
         * Public method to add message (for voice interface)
         */
        addBotMessage(text) {
            this.addMessage(text, 'bot');
        }
    }

    /**
     * Initialize widget when DOM is ready
     * Note: If multimodal is enabled, AntekMultimodalWidget will initialize instead
     */
    $(document).ready(function() {
        if (typeof antekChatConfig !== 'undefined') {
            // Check if multimodal widget will handle initialization
            if (!antekChatConfig.multimodalEnabled) {
                window.antekChat = new window.AntekChatWidget(antekChatConfig);
            }
            // Otherwise, multimodal-widget.js will create AntekMultimodalWidget
        }
    });

    /**
     * Manual test function for debugging
     * Usage: Open browser console and run: testAntekVoice()
     */
    window.testAntekVoice = function() {
        console.log('=== MANUAL VOICE TEST ===');

        // Test 1: Check if widget exists
        console.log('1. Widget exists:', typeof window.antekChat !== 'undefined');
        if (!window.antekChat) {
            console.error('ERROR: window.antekChat not found - widget did not initialize');
            return;
        }

        // Test 2: Check config
        console.log('2. Config:', window.antekChat.config);
        console.log('   Voice enabled:', window.antekChat.config.voiceEnabled);

        // Test 3: Find mode toggle buttons
        const $modeButtons = $('.antek-mode-btn');
        console.log('3. Mode toggle buttons found:', $modeButtons.length);
        $modeButtons.each(function(index) {
            const $btn = $(this);
            console.log(`   Button ${index}:`, {
                mode: $btn.data('mode'),
                visible: $btn.is(':visible'),
                active: $btn.hasClass('active'),
                hasClickHandler: $._data($btn[0], 'events')?.click !== undefined
            });
        });

        // Test 4: Try manual mode switch
        console.log('4. Attempting manual mode switch to voice...');
        try {
            window.antekChat.switchMode('voice');
            console.log('   switchMode() called successfully');

            // Check if mode switched
            setTimeout(() => {
                const $voiceMode = $('#antek-voice-mode');
                const $textMode = $('#antek-text-mode');
                console.log('   Voice mode visible:', $voiceMode.is(':visible'));
                console.log('   Text mode visible:', $textMode.is(':visible'));
            }, 100);
        } catch (error) {
            console.error('   ERROR calling switchMode():', error);
        }

        // Test 5: Try clicking voice button programmatically
        console.log('5. Attempting programmatic button click...');
        const $voiceButton = $('.antek-mode-btn[data-mode="voice"]');
        if ($voiceButton.length) {
            try {
                $voiceButton.trigger('click');
                console.log('   Button click triggered');
            } catch (error) {
                console.error('   ERROR triggering click:', error);
            }
        } else {
            console.error('   ERROR: Voice button not found');
        }

        console.log('=== TEST COMPLETE ===');
    };

})(jQuery);
