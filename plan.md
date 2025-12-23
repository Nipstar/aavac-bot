
# Antek Chat Connector - WordPress Plugin Development Plan

## Overview
A flexible WordPress chat/voice widget plugin that connects to n8n workflows via webhook and integrates with ElevenLabs for voice capabilities. Designed to be fully customizable to match any WordPress theme with popup/promotional features.

## Plugin Architecture

### Directory Structure
```
antek-chat-connector/
├── antek-chat-connector.php (main plugin file)
├── includes/
│   ├── class-plugin-core.php
│   ├── class-admin-settings.php
│   ├── class-webhook-handler.php
│   ├── class-elevenlabs-integration.php
│   ├── class-widget-renderer.php
│   └── class-session-manager.php
├── admin/
│   ├── css/
│   │   └── admin-styles.css
│   ├── js/
│   │   └── admin-scripts.js
│   └── views/
│       ├── settings-page.php
│       ├── appearance-settings.php
│       └── popup-settings.php
├── public/
│   ├── css/
│   │   └── widget-styles.css
│   ├── js/
│   │   ├── chat-widget.js
│   │   ├── voice-interface.js
│   │   └── popup-controller.js
│   └── templates/
│       ├── chat-widget.php
│       └── voice-button.php
└── assets/
    └── icons/
```

## Database Schema

### WordPress Options (wp_options)

**antek_chat_settings:**
```php
[
    'n8n_webhook_url' => '',
    'elevenlabs_api_key' => '',
    'elevenlabs_voice_id' => '',
    'widget_enabled' => true,
    'voice_enabled' => false,
]
```

**antek_chat_appearance:**
```php
[
    'primary_color' => '#FF6B4A',
    'secondary_color' => '#8FA68E',
    'background_color' => '#FDFBF6',
    'text_color' => '#2C2C2C',
    'border_radius' => '12px',
    'widget_position' => 'bottom-right',
    'widget_size' => 'medium',
    'custom_css' => '',
    'font_family' => 'inherit',
]
```

**antek_chat_popup:**
```php
[
    'popup_enabled' => false,
    'popup_delay' => 3000,
    'popup_trigger' => 'time', // time|scroll|exit
    'popup_message' => '',
    'popup_pages' => ['all'],
    'popup_frequency' => 'once', // once|session|always
]
```

### Custom Table (wp_antek_chat_sessions)
```sql
CREATE TABLE wp_antek_chat_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) UNIQUE,
    user_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    conversation_data LONGTEXT, -- JSON
    created_at DATETIME,
    updated_at DATETIME,
    INDEX (session_id),
    INDEX (user_id)
)
```

## Core Components

### 1. Main Plugin File (antek-chat-connector.php)
```php
<?php
/**
 * Plugin Name: Antek Chat Connector
 * Plugin URI: https://antekautomation.co.uk
 * Description: Flexible chat and voice widget connecting to n8n workflows with ElevenLabs integration
 * Version: 1.0.0
 * Author: Antek Automation
 * Author URI: https://antekautomation.co.uk
 * License: GPL v2 or later
 * Text Domain: antek-chat-connector
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Define plugin constants
define('ANTEK_CHAT_VERSION', '1.0.0');
define('ANTEK_CHAT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ANTEK_CHAT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include core classes
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/class-plugin-core.php';
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/class-admin-settings.php';
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/class-webhook-handler.php';
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/class-elevenlabs-integration.php';
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/class-widget-renderer.php';
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/class-session-manager.php';

// Activation hook
register_activation_hook(__FILE__, 'antek_chat_activate');
function antek_chat_activate() {
    // Create database table
    // Set default options
    // Flush rewrite rules
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'antek_chat_deactivate');
function antek_chat_deactivate() {
    // Clean up if needed
}

// Initialize plugin
function antek_chat_init() {
    $plugin = new Antek_Chat_Plugin_Core();
    $plugin->run();
}
add_action('plugins_loaded', 'antek_chat_init');
```

### 2. Webhook Handler Class
```php
class Antek_Chat_Webhook_Handler {
    private $webhook_url;
    
    public function __construct() {
        $settings = get_option('antek_chat_settings');
        $this->webhook_url = $settings['n8n_webhook_url'] ?? '';
    }
    
    /**
     * Send message to n8n webhook
     */
    public function send_message($session_id, $message, $metadata = []) {
        if (empty($this->webhook_url)) {
            return new WP_Error('no_webhook', 'Webhook URL not configured');
        }
        
        $payload = [
            'session_id' => $session_id,
            'message' => sanitize_text_field($message),
            'timestamp' => current_time('timestamp'),
            'metadata' => $metadata,
            'site_url' => get_site_url(),
        ];
        
        $response = wp_remote_post($this->webhook_url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($payload),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }
    
    /**
     * Handle incoming webhook from n8n (if needed for async responses)
     */
    public function receive_webhook() {
        $payload = file_get_contents('php://input');
        $data = json_decode($payload, true);
        
        // Store response for session
        // Trigger event for frontend to fetch
        
        wp_send_json_success($data);
    }
}
```

### 3. ElevenLabs Integration Class
```php
class Antek_Chat_ElevenLabs_Integration {
    private $api_key;
    private $voice_id;
    
    public function __construct() {
        $settings = get_option('antek_chat_settings');
        $this->api_key = $settings['elevenlabs_api_key'] ?? '';
        $this->voice_id = $settings['elevenlabs_voice_id'] ?? '';
    }
    
    /**
     * Get configuration for frontend
     */
    public function get_config() {
        return [
            'enabled' => !empty($this->api_key) && !empty($this->voice_id),
            'voice_id' => $this->voice_id,
            'api_key' => $this->api_key, // Consider proxy for security
        ];
    }
    
    /**
     * Proxy text-to-speech request (optional security layer)
     */
    public function text_to_speech($text) {
        $url = "https://api.elevenlabs.io/v1/text-to-speech/{$this->voice_id}";
        
        $response = wp_remote_post($url, [
            'headers' => [
                'xi-api-key' => $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'text' => $text,
                'model_id' => 'eleven_monolingual_v1',
            ]),
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return wp_remote_retrieve_body($response);
    }
}
```

### 4. Session Manager Class
```php
class Antek_Chat_Session_Manager {
    
    /**
     * Get or create session ID
     */
    public function get_session_id() {
        if (isset($_COOKIE['antek_chat_session'])) {
            return sanitize_text_field($_COOKIE['antek_chat_session']);
        }
        
        $session_id = $this->generate_session_id();
        setcookie('antek_chat_session', $session_id, time() + (86400 * 30), '/');
        
        return $session_id;
    }
    
    /**
     * Generate unique session ID
     */
    private function generate_session_id() {
        return wp_generate_uuid4();
    }
    
    /**
     * Save conversation data
     */
    public function save_conversation($session_id, $message, $response) {
        global $wpdb;
        $table = $wpdb->prefix . 'antek_chat_sessions';
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE session_id = %s",
            $session_id
        ));
        
        $conversation_data = [];
        if ($existing) {
            $conversation_data = json_decode($existing->conversation_data, true) ?? [];
        }
        
        $conversation_data[] = [
            'timestamp' => current_time('mysql'),
            'message' => $message,
            'response' => $response,
        ];
        
        if ($existing) {
            $wpdb->update(
                $table,
                [
                    'conversation_data' => json_encode($conversation_data),
                    'updated_at' => current_time('mysql'),
                ],
                ['session_id' => $session_id]
            );
        } else {
            $wpdb->insert(
                $table,
                [
                    'session_id' => $session_id,
                    'user_id' => get_current_user_id(),
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                    'conversation_data' => json_encode($conversation_data),
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ]
            );
        }
    }
    
    /**
     * Get conversation history
     */
    public function get_conversation($session_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'antek_chat_sessions';
        
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT conversation_data FROM $table WHERE session_id = %s",
            $session_id
        ));
        
        if ($row) {
            return json_decode($row->conversation_data, true) ?? [];
        }
        
        return [];
    }
}
```

### 5. Widget Renderer Class
```php
class Antek_Chat_Widget_Renderer {
    
    public function render() {
        $settings = get_option('antek_chat_settings');
        $appearance = get_option('antek_chat_appearance');
        $popup = get_option('antek_chat_popup');
        
        if (!$settings['widget_enabled']) {
            return;
        }
        
        // Enqueue styles and scripts
        $this->enqueue_assets();
        
        // Render widget HTML
        include ANTEK_CHAT_PLUGIN_DIR . 'public/templates/chat-widget.php';
        
        // Pass config to JavaScript
        wp_localize_script('antek-chat-widget', 'antekChatConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('antek_chat_nonce'),
            'sessionId' => (new Antek_Chat_Session_Manager())->get_session_id(),
            'appearance' => $appearance,
            'popup' => $popup,
            'voiceEnabled' => $settings['voice_enabled'] ?? false,
            'elevenLabs' => (new Antek_Chat_ElevenLabs_Integration())->get_config(),
        ]);
    }
    
    private function enqueue_assets() {
        wp_enqueue_style(
            'antek-chat-widget',
            ANTEK_CHAT_PLUGIN_URL . 'public/css/widget-styles.css',
            [],
            ANTEK_CHAT_VERSION
        );
        
        wp_enqueue_script(
            'antek-chat-widget',
            ANTEK_CHAT_PLUGIN_URL . 'public/js/chat-widget.js',
            ['jquery'],
            ANTEK_CHAT_VERSION,
            true
        );
        
        if (get_option('antek_chat_settings')['voice_enabled'] ?? false) {
            wp_enqueue_script(
                'antek-chat-voice',
                ANTEK_CHAT_PLUGIN_URL . 'public/js/voice-interface.js',
                ['antek-chat-widget'],
                ANTEK_CHAT_VERSION,
                true
            );
        }
        
        wp_enqueue_script(
            'antek-chat-popup',
            ANTEK_CHAT_PLUGIN_URL . 'public/js/popup-controller.js',
            ['antek-chat-widget'],
            ANTEK_CHAT_VERSION,
            true
        );
    }
}
```

### 6. Admin Settings Class
```php
class Antek_Chat_Admin_Settings {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }
    
    public function add_menu_page() {
        add_menu_page(
            'Antek Chat Connector',
            'Chat Connector',
            'manage_options',
            'antek-chat-connector',
            [$this, 'render_settings_page'],
            'dashicons-format-chat',
            30
        );
    }
    
    public function register_settings() {
        register_setting('antek_chat_settings', 'antek_chat_settings');
        register_setting('antek_chat_appearance', 'antek_chat_appearance');
        register_setting('antek_chat_popup', 'antek_chat_popup');
    }
    
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=antek-chat-connector&tab=connection" class="nav-tab nav-tab-active">Connection</a>
                <a href="?page=antek-chat-connector&tab=appearance" class="nav-tab">Appearance</a>
                <a href="?page=antek-chat-connector&tab=popup" class="nav-tab">Popup Settings</a>
                <a href="?page=antek-chat-connector&tab=voice" class="nav-tab">Voice Settings</a>
            </h2>
            
            <?php
            $active_tab = $_GET['tab'] ?? 'connection';
            
            switch ($active_tab) {
                case 'connection':
                    include ANTEK_CHAT_PLUGIN_DIR . 'admin/views/connection-settings.php';
                    break;
                case 'appearance':
                    include ANTEK_CHAT_PLUGIN_DIR . 'admin/views/appearance-settings.php';
                    break;
                case 'popup':
                    include ANTEK_CHAT_PLUGIN_DIR . 'admin/views/popup-settings.php';
                    break;
                case 'voice':
                    include ANTEK_CHAT_PLUGIN_DIR . 'admin/views/voice-settings.php';
                    break;
            }
            ?>
        </div>
        <?php
    }
}
```

## Frontend JavaScript

### Chat Widget (chat-widget.js)
```javascript
class AntekChatWidget {
    constructor(config) {
        this.config = config;
        this.sessionId = config.sessionId;
        this.isOpen = false;
        this.conversationHistory = [];
        this.init();
    }
    
    init() {
        this.render();
        this.attachEventListeners();
        this.applyCustomStyles();
        this.loadConversationHistory();
    }
    
    render() {
        const widget = document.createElement('div');
        widget.id = 'antek-chat-widget';
        widget.className = `antek-chat-widget ${this.config.appearance.widget_position}`;
        widget.innerHTML = this.getWidgetHTML();
        document.body.appendChild(widget);
    }
    
    getWidgetHTML() {
        return `
            <div class="antek-chat-trigger" id="antek-chat-trigger">
                <svg><!-- Chat icon --></svg>
            </div>
            <div class="antek-chat-window" id="antek-chat-window" style="display: none;">
                <div class="antek-chat-header">
                    <span>Chat with us</span>
                    <button class="antek-chat-close" id="antek-chat-close">&times;</button>
                </div>
                <div class="antek-chat-messages" id="antek-chat-messages"></div>
                <div class="antek-chat-input-wrapper">
                    ${this.config.voiceEnabled ? '<button id="antek-voice-button">🎤</button>' : ''}
                    <input type="text" id="antek-chat-input" placeholder="Type your message...">
                    <button id="antek-chat-send">Send</button>
                </div>
            </div>
        `;
    }
    
    attachEventListeners() {
        document.getElementById('antek-chat-trigger').addEventListener('click', () => this.toggle());
        document.getElementById('antek-chat-close').addEventListener('click', () => this.toggle());
        document.getElementById('antek-chat-send').addEventListener('click', () => this.sendMessage());
        document.getElementById('antek-chat-input').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') this.sendMessage();
        });
        
        if (this.config.voiceEnabled) {
            document.getElementById('antek-voice-button').addEventListener('click', () => this.toggleVoice());
        }
    }
    
    toggle() {
        this.isOpen = !this.isOpen;
        const window = document.getElementById('antek-chat-window');
        window.style.display = this.isOpen ? 'flex' : 'none';
    }
    
    async sendMessage() {
        const input = document.getElementById('antek-chat-input');
        const message = input.value.trim();
        
        if (!message) return;
        
        this.addMessage(message, 'user');
        input.value = '';
        
        try {
            const response = await fetch(this.config.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'antek_chat_send_message',
                    nonce: this.config.nonce,
                    session_id: this.sessionId,
                    message: message,
                }),
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.addMessage(data.data.response, 'bot');
            } else {
                this.addMessage('Sorry, there was an error. Please try again.', 'bot');
            }
        } catch (error) {
            console.error('Chat error:', error);
            this.addMessage('Sorry, there was an error. Please try again.', 'bot');
        }
    }
    
    addMessage(text, sender) {
        const messagesContainer = document.getElementById('antek-chat-messages');
        const messageEl = document.createElement('div');
        messageEl.className = `antek-chat-message antek-chat-message-${sender}`;
        messageEl.textContent = text;
        messagesContainer.appendChild(messageEl);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
        
        this.conversationHistory.push({ text, sender, timestamp: Date.now() });
    }
    
    applyCustomStyles() {
        const style = document.createElement('style');
        style.textContent = `
            :root {
                --antek-primary: ${this.config.appearance.primary_color};
                --antek-secondary: ${this.config.appearance.secondary_color};
                --antek-background: ${this.config.appearance.background_color};
                --antek-text: ${this.config.appearance.text_color};
                --antek-radius: ${this.config.appearance.border_radius};
            }
            ${this.config.appearance.custom_css || ''}
        `;
        document.head.appendChild(style);
    }
    
    loadConversationHistory() {
        // Fetch from localStorage or server
        const saved = localStorage.getItem(`antek_chat_${this.sessionId}`);
        if (saved) {
            this.conversationHistory = JSON.parse(saved);
            this.conversationHistory.forEach(msg => {
                this.addMessage(msg.text, msg.sender);
            });
        }
    }
    
    toggleVoice() {
        if (window.antekVoiceInterface) {
            window.antekVoiceInterface.toggle();
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.antekChat = new AntekChatWidget(antekChatConfig);
});
```

### Voice Interface (voice-interface.js)
```javascript
class AntekVoiceInterface {
    constructor(config) {
        this.config = config.elevenLabs;
        this.isActive = false;
        this.websocket = null;
        this.mediaRecorder = null;
        this.audioContext = null;
    }
    
    async toggle() {
        if (this.isActive) {
            this.stop();
        } else {
            await this.start();
        }
    }
    
    async start() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            this.isActive = true;
            
            // Initialize WebSocket to ElevenLabs
            this.websocket = new WebSocket(
                `wss://api.elevenlabs.io/v1/convai/conversation?agent_id=${this.config.voice_id}`
            );
            
            this.websocket.onopen = () => {
                console.log('Voice connection established');
                this.startRecording(stream);
            };
            
            this.websocket.onmessage = (event) => {
                this.handleVoiceResponse(event.data);
            };
            
            this.websocket.onerror = (error) => {
                console.error('Voice connection error:', error);
                this.stop();
            };
            
        } catch (error) {
            console.error('Microphone access denied:', error);
            alert('Please allow microphone access to use voice chat.');
        }
    }
    
    startRecording(stream) {
        this.mediaRecorder = new MediaRecorder(stream);
        
        this.mediaRecorder.ondataavailable = (event) => {
            if (event.data.size > 0 && this.websocket.readyState === WebSocket.OPEN) {
                // Send audio chunk to ElevenLabs
                this.websocket.send(event.data);
            }
        };
        
        this.mediaRecorder.start(100); // Capture in 100ms chunks
    }
    
    handleVoiceResponse(data) {
        // Parse response from ElevenLabs
        // Play audio response
        // Send transcript to n8n if needed
        
        const response = JSON.parse(data);
        
        if (response.audio) {
            this.playAudio(response.audio);
        }
        
        if (response.transcript) {
            // Send to chat widget
            if (window.antekChat) {
                window.antekChat.addMessage(response.transcript, 'bot');
            }
        }
    }
    
    playAudio(audioData) {
        // Decode and play audio response
        this.audioContext = this.audioContext || new AudioContext();
        
        const audioBuffer = this.base64ToArrayBuffer(audioData);
        this.audioContext.decodeAudioData(audioBuffer, (buffer) => {
            const source = this.audioContext.createBufferSource();
            source.buffer = buffer;
            source.connect(this.audioContext.destination);
            source.start(0);
        });
    }
    
    stop() {
        this.isActive = false;
        
        if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
            this.mediaRecorder.stop();
        }
        
        if (this.websocket) {
            this.websocket.close();
        }
        
        console.log('Voice interface stopped');
    }
    
    base64ToArrayBuffer(base64) {
        const binaryString = window.atob(base64);
        const bytes = new Uint8Array(binaryString.length);
        for (let i = 0; i < binaryString.length; i++) {
            bytes[i] = binaryString.charCodeAt(i);
        }
        return bytes.buffer;
    }
}

// Initialize if voice is enabled
if (antekChatConfig.voiceEnabled) {
    window.antekVoiceInterface = new AntekVoiceInterface(antekChatConfig);
}
```

### Popup Controller (popup-controller.js)
```javascript
class AntekPopupController {
    constructor(config) {
        this.config = config.popup;
        this.hasShown = false;
        this.init();
    }
    
    init() {
        if (!this.config.popup_enabled) return;
        
        if (!this.shouldShowPopup()) return;
        
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
    
    shouldShowPopup() {
        const frequency = this.config.popup_frequency;
        const storageKey = 'antek_popup_shown';
        
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
    }
    
    scheduleTimePopup() {
        setTimeout(() => {
            this.showPopup();
        }, this.config.popup_delay);
    }
    
    attachScrollListener() {
        let triggered = false;
        window.addEventListener('scroll', () => {
            if (triggered) return;
            
            const scrollPercent = (window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100;
            if (scrollPercent >= this.config.popup_delay) {
                triggered = true;
                this.showPopup();
            }
        });
    }
    
    attachExitListener() {
        document.addEventListener('mouseleave', (e) => {
            if (e.clientY < 0 && !this.hasShown) {
                this.showPopup();
            }
        });
    }
    
    showPopup() {
        if (this.hasShown) return;
        this.hasShown = true;
        
        // Open chat widget
        if (window.antekChat && !window.antekChat.isOpen) {
            window.antekChat.toggle();
        }
        
        // Add promotional message
        if (this.config.popup_message) {
            setTimeout(() => {
                window.antekChat.addMessage(this.config.popup_message, 'bot');
            }, 500);
        }
        
        // Mark as shown
        const storageKey = 'antek_popup_shown';
        if (this.config.popup_frequency === 'once') {
            localStorage.setItem(storageKey, 'true');
        } else if (this.config.popup_frequency === 'session') {
            sessionStorage.setItem(storageKey, 'true');
        }
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    new AntekPopupController(antekChatConfig);
});
```

## AJAX Handlers (PHP)

```php
// In class-plugin-core.php or main plugin file

add_action('wp_ajax_antek_chat_send_message', 'antek_chat_handle_message');
add_action('wp_ajax_nopriv_antek_chat_send_message', 'antek_chat_handle_message');

function antek_chat_handle_message() {
    check_ajax_referer('antek_chat_nonce', 'nonce');
    
    $session_id = sanitize_text_field($_POST['session_id']);
    $message = sanitize_text_field($_POST['message']);
    
    // Initialize handlers
    $webhook_handler = new Antek_Chat_Webhook_Handler();
    $session_manager = new Antek_Chat_Session_Manager();
    
    // Get conversation history for context
    $history = $session_manager->get_conversation($session_id);
    
    // Send to n8n
    $result = $webhook_handler->send_message($session_id, $message, [
        'user_id' => get_current_user_id(),
        'history' => $history,
        'page_url' => $_POST['page_url'] ?? '',
    ]);
    
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => 'Failed to send message']);
        return;
    }
    
    // Save to session
    $response_text = $result['response'] ?? 'Sorry, I didn\'t understand that.';
    $session_manager->save_conversation($session_id, $message, $response_text);
    
    wp_send_json_success([
        'response' => $response_text,
        'metadata' => $result['metadata'] ?? [],
    ]);
}

add_action('wp_ajax_antek_chat_get_history', 'antek_chat_get_history');
add_action('wp_ajax_nopriv_antek_chat_get_history', 'antek_chat_get_history');

function antek_chat_get_history() {
    check_ajax_referer('antek_chat_nonce', 'nonce');
    
    $session_id = sanitize_text_field($_POST['session_id']);
    $session_manager = new Antek_Chat_Session_Manager();
    
    $history = $session_manager->get_conversation($session_id);
    
    wp_send_json_success(['history' => $history]);
}
```

## Admin Settings Views

### Connection Settings (admin/views/connection-settings.php)
```php
<form method="post" action="options.php">
    <?php
    settings_fields('antek_chat_settings');
    $settings = get_option('antek_chat_settings');
    ?>
    
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="n8n_webhook_url">n8n Webhook URL</label>
            </th>
            <td>
                <input type="url" 
                       name="antek_chat_settings[n8n_webhook_url]" 
                       id="n8n_webhook_url" 
                       value="<?php echo esc_attr($settings['n8n_webhook_url'] ?? ''); ?>" 
                       class="regular-text">
                <p class="description">Your n8n webhook URL that will handle chat messages</p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="widget_enabled">Enable Widget</label>
            </th>
            <td>
                <input type="checkbox" 
                       name="antek_chat_settings[widget_enabled]" 
                       id="widget_enabled" 
                       value="1" 
                       <?php checked($settings['widget_enabled'] ?? true, 1); ?>>
                <label for="widget_enabled">Show chat widget on frontend</label>
            </td>
        </tr>
        
        <tr>
            <th scope="row">Test Connection</th>
            <td>
                <button type="button" id="test-webhook" class="button">Test Webhook</button>
                <span id="test-result"></span>
            </td>
        </tr>
    </table>
    
    <?php submit_button(); ?>
</form>
```

### Appearance Settings (admin/views/appearance-settings.php)
```php
<form method="post" action="options.php">
    <?php
    settings_fields('antek_chat_appearance');
    $appearance = get_option('antek_chat_appearance');
    ?>
    
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="primary_color">Primary Color</label>
            </th>
            <td>
                <input type="color" 
                       name="antek_chat_appearance[primary_color]" 
                       id="primary_color" 
                       value="<?php echo esc_attr($appearance['primary_color'] ?? '#FF6B4A'); ?>">
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="secondary_color">Secondary Color</label>
            </th>
            <td>
                <input type="color" 
                       name="antek_chat_appearance[secondary_color]" 
                       id="secondary_color" 
                       value="<?php echo esc_attr($appearance['secondary_color'] ?? '#8FA68E'); ?>">
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="widget_position">Widget Position</label>
            </th>
            <td>
                <select name="antek_chat_appearance[widget_position]" id="widget_position">
                    <option value="bottom-right" <?php selected($appearance['widget_position'] ?? 'bottom-right', 'bottom-right'); ?>>Bottom Right</option>
                    <option value="bottom-left" <?php selected($appearance['widget_position'] ?? '', 'bottom-left'); ?>>Bottom Left</option>
                    <option value="top-right" <?php selected($appearance['widget_position'] ?? '', 'top-right'); ?>>Top Right</option>
                    <option value="top-left" <?php selected($appearance['widget_position'] ?? '', 'top-left'); ?>>Top Left</option>
                </select>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="border_radius">Border Radius</label>
            </th>
            <td>
                <input type="text" 
                       name="antek_chat_appearance[border_radius]" 
                       id="border_radius" 
                       value="<?php echo esc_attr($appearance['border_radius'] ?? '12px'); ?>" 
                       placeholder="12px">
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="custom_css">Custom CSS</label>
            </th>
            <td>
                <textarea name="antek_chat_appearance[custom_css]" 
                          id="custom_css" 
                          rows="10" 
                          class="large-text code"><?php echo esc_textarea($appearance['custom_css'] ?? ''); ?></textarea>
                <p class="description">Add custom CSS to further customize the widget appearance</p>
            </td>
        </tr>
    </table>
    
    <h3>Live Preview</h3>
    <div id="widget-preview" style="background: #f5f5f5; padding: 20px; border: 1px solid #ddd;">
        <!-- Preview widget here -->
    </div>
    
    <?php submit_button(); ?>
</form>
```

### Popup Settings (admin/views/popup-settings.php)
```php
<form method="post" action="options.php">
    <?php
    settings_fields('antek_chat_popup');
    $popup = get_option('antek_chat_popup');
    ?>
    
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="popup_enabled">Enable Popup</label>
            </th>
            <td>
                <input type="checkbox" 
                       name="antek_chat_popup[popup_enabled]" 
                       id="popup_enabled" 
                       value="1" 
                       <?php checked($popup['popup_enabled'] ?? false, 1); ?>>
                <label for="popup_enabled">Automatically open chat widget with promotional message</label>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="popup_trigger">Trigger Type</label>
            </th>
            <td>
                <select name="antek_chat_popup[popup_trigger]" id="popup_trigger">
                    <option value="time" <?php selected($popup['popup_trigger'] ?? 'time', 'time'); ?>>Time Delay</option>
                    <option value="scroll" <?php selected($popup['popup_trigger'] ?? '', 'scroll'); ?>>Scroll Percentage</option>
                    <option value="exit" <?php selected($popup['popup_trigger'] ?? '', 'exit'); ?>>Exit Intent</option>
                </select>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="popup_delay">Delay/Threshold</label>
            </th>
            <td>
                <input type="number" 
                       name="antek_chat_popup[popup_delay]" 
                       id="popup_delay" 
                       value="<?php echo esc_attr($popup['popup_delay'] ?? 3000); ?>">
                <p class="description">Milliseconds for time delay, or percentage for scroll trigger</p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="popup_message">Promotional Message</label>
            </th>
            <td>
                <textarea name="antek_chat_popup[popup_message]" 
                          id="popup_message" 
                          rows="3" 
                          class="large-text"><?php echo esc_textarea($popup['popup_message'] ?? ''); ?></textarea>
                <p class="description">Message to display when popup triggers</p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="popup_frequency">Frequency</label>
            </th>
            <td>
                <select name="antek_chat_popup[popup_frequency]" id="popup_frequency">
                    <option value="once" <?php selected($popup['popup_frequency'] ?? 'once', 'once'); ?>>Once per user (permanent)</option>
                    <option value="session" <?php selected($popup['popup_frequency'] ?? '', 'session'); ?>>Once per session</option>
                    <option value="always" <?php selected($popup['popup_frequency'] ?? '', 'always'); ?>>Every time</option>
                </select>
            </td>
        </tr>
    </table>
    
    <?php submit_button(); ?>
</form>
```

## Communication Flow

### User Message Flow
```
1. User types message in widget
2. JavaScript captures message
3. AJAX POST to WordPress
   - Action: antek_chat_send_message
   - Data: session_id, message, metadata
4. PHP Webhook Handler
   - Adds site context
   - Sends to n8n webhook URL
5. n8n Workflow
   - Processes with AI/tools
   - Returns response JSON
6. PHP receives response
   - Saves to session database
   - Returns to JavaScript
7. JavaScript displays response
   - Updates chat UI
   - Saves to localStorage
```

### Voice Flow
```
1. User clicks voice button
2. Request microphone permission
3. Open WebSocket to ElevenLabs
4. Stream audio chunks
5. ElevenLabs processes:
   - Transcribes speech
   - (Optional) Send transcript to n8n
   - Generates audio response
6. Stream audio back to browser
7. Play audio response
8. Display transcript in chat
```

## Shortcode & Template Tag Support

### Shortcode
```php
function antek_chat_shortcode($atts) {
    $atts = shortcode_atts([
        'position' => 'bottom-right',
        'primary_color' => null,
        'voice_enabled' => null,
    ], $atts);
    
    // Override settings with shortcode attributes
    // Render widget
    
    ob_start();
    (new Antek_Chat_Widget_Renderer())->render();
    return ob_get_clean();
}
add_shortcode('antek_chat', 'antek_chat_shortcode');
```

### Template Tag
```php
function antek_chat_widget($args = []) {
    if (function_exists('Antek_Chat_Widget_Renderer')) {
        $renderer = new Antek_Chat_Widget_Renderer();
        $renderer->render($args);
    }
}
```

## Security Features

### Rate Limiting
```php
class Antek_Chat_Rate_Limiter {
    private $max_messages = 50;
    private $time_window = 3600; // 1 hour
    
    public function check($session_id) {
        $key = 'antek_chat_rate_' . $session_id;
        $count = get_transient($key) ?: 0;
        
        if ($count >= $this->max_messages) {
            return false;
        }
        
        set_transient($key, $count + 1, $this->time_window);
        return true;
    }
}
```

### Input Sanitization
```php
// All user inputs sanitized with:
sanitize_text_field()
sanitize_textarea_field()
esc_url()
esc_html()
wp_kses()

// SQL queries use $wpdb->prepare()
```

### Nonce Verification
```php
// All AJAX requests verify nonce
check_ajax_referer('antek_chat_nonce', 'nonce');

// Nonce passed to JavaScript
wp_create_nonce('antek_chat_nonce');
```

## Additional Features

### Gutenberg Block
```php
function antek_chat_register_block() {
    register_block_type('antek-chat/widget', [
        'render_callback' => 'antek_chat_shortcode',
        'attributes' => [
            'position' => ['type' => 'string', 'default' => 'bottom-right'],
            'voiceEnabled' => ['type' => 'boolean', 'default' => false],
        ],
    ]);
}
add_action('init', 'antek_chat_register_block');
```

### Widget Support
```php
class Antek_Chat_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('antek_chat_widget', 'Antek Chat Widget');
    }
    
    public function widget($args, $instance) {
        echo antek_chat_shortcode([]);
    }
}

function register_antek_chat_widget() {
    register_widget('Antek_Chat_Widget');
}
add_action('widgets_init', 'register_antek_chat_widget');
```

### GDPR Compliance
```php
// Add privacy policy content
function antek_chat_privacy_policy() {
    $content = 'Our chat widget stores conversation data...';
    wp_add_privacy_policy_content('Antek Chat Connector', $content);
}
add_action('admin_init', 'antek_chat_privacy_policy');

// Data export
function antek_chat_export_data($email) {
    // Export user's chat sessions
}
add_filter('wp_privacy_personal_data_exporters', 'register_antek_chat_exporter');

// Data deletion
function antek_chat_delete_data($email) {
    // Delete user's chat sessions
}
add_filter('wp_privacy_personal_data_erasers', 'register_antek_chat_eraser');
```

## n8n Webhook Expected Payload

### Incoming to n8n
```json
{
  "session_id": "uuid-v4-string",
  "message": "User's message text",
  "timestamp": 1234567890,
  "site_url": "https://example.com",
  "metadata": {
    "user_id": 123,
    "history": [
      {
        "timestamp": "2024-01-01 12:00:00",
        "message": "Previous message",
        "response": "Previous response"
      }
    ],
    "page_url": "https://example.com/contact"
  }
}
```

### Expected Response from n8n
```json
{
  "response": "AI response text here",
  "metadata": {
    "intent": "question",
    "confidence": 0.95,
    "actions": []
  }
}
```

## Development Phases

### Phase 1: Core Infrastructure
- Main plugin file with activation/deactivation
- Database table creation
- Basic settings page with tabs
- Webhook handler class
- Session manager class

### Phase 2: Basic Chat Widget
- Frontend HTML/CSS for widget
- JavaScript chat functionality
- AJAX handlers for messages
- Basic styling and positioning

### Phase 3: Customization
- Appearance settings (colors, position, size)
- Custom CSS support
- Live preview in admin
- Responsive design

### Phase 4: Popup System
- Popup controller JavaScript
- Trigger logic (time, scroll, exit)
- Frequency controls
- Page targeting

### Phase 5: Voice Integration
- ElevenLabs API integration
- Voice interface JavaScript
- WebSocket communication
- Audio recording/playback

### Phase 6: Advanced Features
- Conversation history persistence
- Rate limiting and security
- Shortcode/template tag support
- Gutenberg block

### Phase 7: Polish & Documentation
- Admin UI improvements
- Error handling
- Inline help/documentation
- Testing across themes

## Testing Checklist

- [ ] Plugin activation/deactivation
- [ ] Database table creation
- [ ] Settings save/load correctly
- [ ] Webhook connection test
- [ ] Chat message send/receive
- [ ] Session persistence
- [ ] Multiple conversations
- [ ] Rate limiting
- [ ] XSS/SQL injection protection
- [ ] Voice recording starts/stops
- [ ] Audio playback works
- [ ] Popup triggers correctly
- [ ] Popup frequency respected
- [ ] Custom colors apply
- [ ] Responsive on mobile
- [ ] Works with popular themes
- [ ] Browser compatibility
- [ ] Performance under load

## Notes for Claude Code

- Start with the core infrastructure (Phase 1)
- Use WordPress coding standards
- Prefix all functions/classes with `antek_chat_`
- Escape all output with esc_html(), esc_attr(), etc.
- Prepare all SQL queries with $wpdb->prepare()
- Use nonces for all forms and AJAX
- Make strings translatable with __(), _e(), etc.
- Add inline documentation
- Keep JavaScript modular and reusable
- Use CSS custom properties for theming
- Test incrementally after each component
```