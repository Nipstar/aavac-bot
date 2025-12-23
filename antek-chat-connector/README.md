# Antek Chat Connector

A flexible WordPress chat and voice widget plugin that connects to n8n workflows via webhook, with ElevenLabs voice integration and popup promotional capabilities.

## Features

- 💬 **Customizable Chat Widget** - Neo-brutalist design that adapts to your theme
- 🔗 **n8n Integration** - Connect to any n8n workflow via webhook
- 🎤 **Voice Chat** - Optional ElevenLabs voice integration with WebSocket streaming
- 🎯 **Smart Popups** - Time-based, scroll-based, or exit-intent triggers
- 🎨 **Full Customization** - Colors, position, size, borders, and custom CSS
- 💾 **Session Management** - Conversation history persistence
- 🔒 **Security** - Rate limiting, nonce verification, input sanitization
- 📱 **Responsive** - Mobile-friendly design
- ♿ **Accessible** - ARIA labels and keyboard navigation

## Installation

1. Download or clone this repository
2. Upload the `antek-chat-connector` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to 'Chat Connector' in the WordPress admin menu to configure

## Configuration

### Connection Settings

1. **n8n Webhook URL**: Your n8n webhook endpoint that will handle chat messages
2. **Widget Enabled**: Toggle to show/hide the widget on your site
3. **Test Connection**: Verify your webhook is working correctly

#### Expected n8n Response Format

Your n8n workflow should return JSON in this format:

```json
{
  "response": "Your AI response text here",
  "metadata": {}
}
```

### Appearance Settings

Customize the look and feel:

- **Primary Color**: Used for buttons and accent elements
- **Secondary Color**: Used for secondary elements
- **Background Color**: Widget background
- **Text Color**: Main text color
- **Widget Position**: Bottom-right, bottom-left, top-right, or top-left
- **Widget Size**: Small, medium, or large
- **Border Radius**: Corner roundness (e.g., 0px, 12px, 20px)
- **Font Family**: Custom font or 'inherit' to match your theme
- **Custom CSS**: Advanced styling options

### Popup Settings

Configure automatic popup behavior:

- **Enable Popup**: Automatically open widget with promotional message
- **Trigger Type**:
  - Time Delay (milliseconds)
  - Scroll Percentage (% of page scrolled)
  - Exit Intent (mouse leaves window)
- **Promotional Message**: Optional message to display
- **Frequency**: Once per user, once per session, or always

### Voice Settings

Enable voice chat with ElevenLabs:

1. Sign up at [ElevenLabs](https://elevenlabs.io)
2. Get your API key
3. Create a conversational AI agent or select a voice
4. Enter API key and Voice/Agent ID
5. Enable voice chat

**Note**: Voice chat requires HTTPS and microphone permissions.

## Usage

### Automatic Display

Once configured, the widget automatically appears on all pages (when enabled).

### Shortcode

Place the widget anywhere using:

```php
[antek_chat]
```

With custom attributes:

```php
[antek_chat position="bottom-left" voice_enabled="true"]
```

### Template Tag

Add to your theme templates:

```php
<?php antek_chat_widget(); ?>
```

With custom arguments:

```php
<?php
antek_chat_widget(array(
    'position' => 'bottom-left',
    'voice_enabled' => true
));
?>
```

## n8n Integration

### Incoming Payload

Your n8n webhook receives:

```json
{
  "session_id": "unique-uuid",
  "message": "User's message text",
  "timestamp": 1234567890,
  "site_url": "https://yoursite.com",
  "metadata": {
    "user_id": 123,
    "history": [
      {
        "timestamp": "2024-01-01 12:00:00",
        "message": "Previous message",
        "response": "Previous response"
      }
    ],
    "page_url": "https://yoursite.com/contact"
  }
}
```

### Required Response

Return JSON with at least a "response" field:

```json
{
  "response": "Your AI-generated response here",
  "metadata": {
    "intent": "question",
    "confidence": 0.95
  }
}
```

## Technical Details

### Database

Creates custom table: `wp_antek_chat_sessions`

Stores:
- Session ID
- User ID (if logged in)
- IP address
- User agent
- Conversation history (JSON)
- Timestamps

### Rate Limiting

- 50 messages per hour per session
- Uses WordPress transients
- Graceful error messages

### Security

- All inputs sanitized
- All outputs escaped
- SQL injection prevention via prepared statements
- Nonce verification on all AJAX requests
- XSS prevention with wp_kses()

### Browser Support

- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Mobile browsers (iOS Safari, Chrome Android)

### Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- HTTPS (for voice chat)
- Modern browser with JavaScript enabled

## File Structure

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
│   ├── css/admin-styles.css
│   ├── js/admin-scripts.js
│   └── views/
│       ├── connection-settings.php
│       ├── appearance-settings.php
│       ├── popup-settings.php
│       └── voice-settings.php
├── public/
│   ├── css/widget-styles.css
│   ├── js/
│   │   ├── chat-widget.js
│   │   ├── voice-interface.js
│   │   └── popup-controller.js
│   └── templates/chat-widget.php
└── assets/icons/
```

## Troubleshooting

### Widget Not Appearing

1. Check that the plugin is activated
2. Verify "Widget Enabled" is checked in Connection settings
3. Check browser console for JavaScript errors
4. Clear cache (browser and WordPress cache plugins)

### Webhook Not Responding

1. Use the "Test Webhook" button in Connection settings
2. Verify webhook URL is correct
3. Check n8n workflow is active
4. Ensure webhook returns proper JSON format
5. Check WordPress debug.log for errors

### Voice Not Working

1. Ensure site is using HTTPS
2. Verify ElevenLabs API key and Voice ID are correct
3. Check browser has microphone permissions
4. Look for errors in browser console
5. Test microphone with other apps

## Support

For issues and feature requests, contact: [Antek Automation](https://antekautomation.co.uk)

## License

GPL v2 or later

## Credits

Developed by Antek Automation
