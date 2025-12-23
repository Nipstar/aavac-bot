/**
 * Popup Controller JavaScript
 *
 * @package Antek_Chat_Connector
 * @since 1.0.0
 */

(function() {
    'use strict';

    /**
     * Popup Controller Class
     */
    class AntekPopupController {
        constructor(config) {
            this.config = config.popup;
            this.hasShown = false;

            this.init();
        }

        /**
         * Initialize popup controller
         */
        init() {
            if (!this.config.popup_enabled) {
                return;
            }

            if (!this.shouldShowPopup()) {
                return;
            }

            // Initialize based on trigger type
            switch (this.config.popup_trigger) {
                case 'time':
                    this.scheduleTimePopup();
                    break;
                case 'scroll':
                    this.attachScrollListener();
                    break;
                case 'exit':
                    this.attachExitListener();
                    break;
            }
        }

        /**
         * Check if popup should be shown
         */
        shouldShowPopup() {
            var frequency = this.config.popup_frequency;
            var storageKey = 'antek_popup_shown';

            try {
                switch (frequency) {
                    case 'once':
                        return !localStorage.getItem(storageKey);
                    case 'session':
                        return !sessionStorage.getItem(storageKey);
                    case 'always':
                        return true;
                    default:
                        return true;
                }
            } catch (e) {
                console.error('Storage error:', e);
                return true;
            }
        }

        /**
         * Schedule time-based popup
         */
        scheduleTimePopup() {
            var delay = parseInt(this.config.popup_delay) || 3000;

            setTimeout(function() {
                this.showPopup();
            }.bind(this), delay);
        }

        /**
         * Attach scroll listener
         */
        attachScrollListener() {
            var triggered = false;
            var threshold = parseInt(this.config.popup_delay) || 50;

            window.addEventListener('scroll', function() {
                if (triggered) {
                    return;
                }

                var scrollPercent = (window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100;

                if (scrollPercent >= threshold) {
                    triggered = true;
                    this.showPopup();
                }
            }.bind(this));
        }

        /**
         * Attach exit intent listener
         */
        attachExitListener() {
            document.addEventListener('mouseleave', function(e) {
                // Only trigger when mouse leaves from the top
                if (e.clientY < 0 && !this.hasShown) {
                    this.showPopup();
                }
            }.bind(this));
        }

        /**
         * Show popup
         */
        showPopup() {
            if (this.hasShown) {
                return;
            }

            this.hasShown = true;

            // Open chat widget if not already open
            if (window.antekChat && !window.antekChat.isOpen) {
                window.antekChat.open();
            }

            // Add promotional message after a short delay
            if (this.config.popup_message) {
                setTimeout(function() {
                    if (window.antekChat) {
                        window.antekChat.addBotMessage(this.config.popup_message);
                    }
                }.bind(this), 500);
            }

            // Mark as shown in storage
            this.markAsShown();
        }

        /**
         * Mark popup as shown in storage
         */
        markAsShown() {
            var storageKey = 'antek_popup_shown';
            var frequency = this.config.popup_frequency;

            try {
                if (frequency === 'once') {
                    localStorage.setItem(storageKey, 'true');
                } else if (frequency === 'session') {
                    sessionStorage.setItem(storageKey, 'true');
                }
                // For 'always', we don't store anything
            } catch (e) {
                console.error('Failed to save popup state:', e);
            }
        }
    }

    /**
     * Initialize popup controller when ready
     */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPopupController);
    } else {
        initPopupController();
    }

    function initPopupController() {
        // Wait a bit for chat widget to initialize
        setTimeout(function() {
            if (typeof antekChatConfig !== 'undefined') {
                window.antekPopupController = new AntekPopupController(antekChatConfig);
            }
        }, 100);
    }

})();
