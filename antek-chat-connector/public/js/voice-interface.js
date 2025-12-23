/**
 * Voice Interface JavaScript
 *
 * @package Antek_Chat_Connector
 * @since 1.0.0
 */

(function() {
    'use strict';

    /**
     * Voice Interface Class
     */
    class AntekVoiceInterface {
        constructor(config) {
            this.config = config.elevenLabs;
            this.isActive = false;
            this.websocket = null;
            this.mediaRecorder = null;
            this.audioContext = null;
            this.$button = null;

            this.init();
        }

        /**
         * Initialize voice interface
         */
        init() {
            if (!this.config.enabled) {
                return;
            }

            this.$button = document.getElementById('antek-voice-button');
        }

        /**
         * Toggle voice recording
         */
        async toggle() {
            if (this.isActive) {
                this.stop();
            } else {
                await this.start();
            }
        }

        /**
         * Start voice recording
         */
        async start() {
            try {
                // Request microphone permission
                var stream = await navigator.mediaDevices.getUserMedia({ audio: true });

                this.isActive = true;
                this.updateButtonState(true);

                // Initialize WebSocket to ElevenLabs
                var wsUrl = this.config.websocket_url + '?agent_id=' + this.config.voice_id;

                this.websocket = new WebSocket(wsUrl);

                this.websocket.onopen = function() {
                    console.log('Voice connection established');
                    this.startRecording(stream);
                }.bind(this);

                this.websocket.onmessage = function(event) {
                    this.handleVoiceResponse(event.data);
                }.bind(this);

                this.websocket.onerror = function(error) {
                    console.error('Voice connection error:', error);
                    this.stop();
                }.bind(this);

                this.websocket.onclose = function() {
                    console.log('Voice connection closed');
                    this.stop();
                }.bind(this);

            } catch (error) {
                console.error('Microphone access denied:', error);
                alert(window.antekChatConfig.strings.micPermission);
                this.isActive = false;
                this.updateButtonState(false);
            }
        }

        /**
         * Start recording audio
         */
        startRecording(stream) {
            try {
                this.mediaRecorder = new MediaRecorder(stream, {
                    mimeType: 'audio/webm'
                });

                this.mediaRecorder.ondataavailable = function(event) {
                    if (event.data.size > 0 && this.websocket && this.websocket.readyState === WebSocket.OPEN) {
                        // Send audio chunk to ElevenLabs
                        this.websocket.send(event.data);
                    }
                }.bind(this);

                // Capture audio in 100ms chunks
                this.mediaRecorder.start(100);

            } catch (error) {
                console.error('Failed to start recording:', error);
                this.stop();
            }
        }

        /**
         * Handle voice response from ElevenLabs
         */
        handleVoiceResponse(data) {
            try {
                var response = JSON.parse(data);

                // Play audio response if available
                if (response.audio) {
                    this.playAudio(response.audio);
                }

                // Display transcript in chat widget
                if (response.transcript && window.antekChat) {
                    window.antekChat.addBotMessage(response.transcript);
                }

                // Handle text response
                if (response.text && window.antekChat) {
                    window.antekChat.addBotMessage(response.text);
                }

            } catch (error) {
                console.error('Failed to parse voice response:', error);
            }
        }

        /**
         * Play audio response
         */
        playAudio(audioData) {
            try {
                // Initialize audio context if needed
                if (!this.audioContext) {
                    this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
                }

                // Convert base64 to array buffer
                var audioBuffer = this.base64ToArrayBuffer(audioData);

                // Decode and play audio
                this.audioContext.decodeAudioData(audioBuffer, function(buffer) {
                    var source = this.audioContext.createBufferSource();
                    source.buffer = buffer;
                    source.connect(this.audioContext.destination);
                    source.start(0);
                }.bind(this), function(error) {
                    console.error('Audio decode error:', error);
                });

            } catch (error) {
                console.error('Failed to play audio:', error);
            }
        }

        /**
         * Stop voice recording
         */
        stop() {
            this.isActive = false;
            this.updateButtonState(false);

            // Stop media recorder
            if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
                this.mediaRecorder.stop();

                // Stop all tracks
                if (this.mediaRecorder.stream) {
                    this.mediaRecorder.stream.getTracks().forEach(function(track) {
                        track.stop();
                    });
                }
            }

            // Close WebSocket
            if (this.websocket) {
                this.websocket.close();
                this.websocket = null;
            }

            console.log('Voice interface stopped');
        }

        /**
         * Update button visual state
         */
        updateButtonState(active) {
            if (this.$button) {
                if (active) {
                    this.$button.classList.add('active');
                } else {
                    this.$button.classList.remove('active');
                }
            }
        }

        /**
         * Convert base64 string to array buffer
         */
        base64ToArrayBuffer(base64) {
            var binaryString = window.atob(base64);
            var bytes = new Uint8Array(binaryString.length);

            for (var i = 0; i < binaryString.length; i++) {
                bytes[i] = binaryString.charCodeAt(i);
            }

            return bytes.buffer;
        }
    }

    /**
     * Initialize voice interface when ready
     */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initVoiceInterface);
    } else {
        initVoiceInterface();
    }

    function initVoiceInterface() {
        if (typeof antekChatConfig !== 'undefined' && antekChatConfig.voiceEnabled) {
            window.antekVoiceInterface = new AntekVoiceInterface(antekChatConfig);
        }
    }

})();
