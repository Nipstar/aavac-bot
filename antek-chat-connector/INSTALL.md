# Installation Guide

## Quick Start

### Step 1: Install Plugin

1. **Upload to WordPress**:
   - Zip the `antek-chat-connector` folder
   - Go to WordPress admin → Plugins → Add New → Upload Plugin
   - Select the zip file and click "Install Now"
   - Click "Activate Plugin"

   OR

   - Upload the `antek-chat-connector` folder to `/wp-content/plugins/` via FTP
   - Activate through the WordPress Plugins menu

### Step 2: Configure n8n Webhook

1. Go to **Chat Connector** in WordPress admin menu
2. Navigate to the **Connection** tab
3. Enter your n8n webhook URL
4. Click "Test Webhook" to verify connection
5. Check "Show chat widget on frontend"
6. Click "Save Changes"

### Step 3: Customize Appearance (Optional)

1. Go to the **Appearance** tab
2. Choose your colors using the color pickers
3. Select widget position and size
4. Add custom CSS if needed
5. Click "Save Changes"

### Step 4: Setup Popups (Optional)

1. Go to the **Popup Settings** tab
2. Enable popup
3. Choose trigger type (time/scroll/exit)
4. Set delay/threshold
5. Enter promotional message
6. Choose frequency
7. Click "Save Changes"

### Step 5: Enable Voice (Optional)

1. Get ElevenLabs API key from [elevenlabs.io](https://elevenlabs.io)
2. Go to the **Voice Settings** tab
3. Enter API key and Voice/Agent ID
4. Enable voice chat
5. Click "Save Changes"

## n8n Workflow Setup

### Create Webhook Node

1. In n8n, add a **Webhook** node to your workflow
2. Set method to **POST**
3. Copy the webhook URL

### Process the Message

Your workflow receives:

```json
{
  "session_id": "uuid",
  "message": "user message",
  "timestamp": 1234567890,
  "site_url": "https://site.com",
  "metadata": {
    "user_id": 123,
    "history": [],
    "page_url": "https://site.com/page"
  }
}
```

### Return Response

Add a **Respond to Webhook** node with:

```json
{
  "response": "Your AI response here"
}
```

### Example n8n Workflow

```
Webhook → OpenAI Node → Respond to Webhook
```

## Verification Checklist

- [ ] Plugin activated successfully
- [ ] Widget appears on frontend
- [ ] Can open/close widget
- [ ] Can send test message
- [ ] Messages get response from n8n
- [ ] Conversation history persists
- [ ] Widget position is correct
- [ ] Colors match your theme
- [ ] Popup triggers (if enabled)
- [ ] Voice works (if enabled)

## Troubleshooting

### Widget Not Showing

```bash
# Check if widget is enabled
Go to Chat Connector → Connection → Check "Show chat widget on frontend"

# Check JavaScript errors
Open browser console (F12) and look for errors

# Clear cache
Clear WordPress cache and browser cache
```

### Webhook Errors

```bash
# Test webhook connection
Go to Chat Connector → Connection → Click "Test Webhook"

# Check n8n workflow
Ensure workflow is active and webhook node is configured correctly

# Check debug log
Enable WP_DEBUG in wp-config.php and check wp-content/debug.log
```

### Voice Not Working

```bash
# Requirements check
- Site must use HTTPS
- Browser must support WebRTC
- User must grant microphone permission

# Test ElevenLabs credentials
Log in to elevenlabs.io and verify API key is active

# Check browser console
Look for WebSocket connection errors
```

## Advanced Configuration

### Custom Styling

Add custom CSS in Appearance → Custom CSS:

```css
/* Larger trigger button */
.antek-chat-trigger {
    width: 70px;
    height: 70px;
}

/* Different message bubble style */
.antek-chat-message-bot {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}
```

### Rate Limit Adjustment

Edit `includes/class-plugin-core.php`:

```php
// Change from 50 to your desired limit
$max_messages = 100;
$time_window = 3600; // Keep 1 hour window
```

### Session Cleanup

Add to your theme's functions.php:

```php
// Clean up sessions older than 30 days (runs daily)
add_action('wp_scheduled_delete', function() {
    $session_manager = new Antek_Chat_Session_Manager();
    $session_manager->cleanup_old_sessions(30);
});
```

## Support

For issues, questions, or feature requests:

- Check the README.md file
- Review the plan.md file
- Contact: [Antek Automation](https://antekautomation.co.uk)

## Next Steps

1. Customize the appearance to match your brand
2. Set up intelligent responses in n8n
3. Enable voice chat for better UX
4. Configure popups to engage visitors
5. Monitor conversation data in database
6. Optimize based on user feedback
