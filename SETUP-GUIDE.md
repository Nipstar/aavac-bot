# AAVAC Bot - Complete Setup Guide

Welcome to AAVAC Bot! This guide will walk you through setting up your advanced AI voice and chat connector with multimodal capabilities.

## Table of Contents

1. [Installation](#installation)
2. [Initial Configuration](#initial-configuration)
3. [Voice Provider Setup](#voice-provider-setup)
4. [Webhook Configuration](#webhook-configuration)
5. [Automation Integration (n8n/Make/Zapier)](#automation-integration)
6. [Advanced Settings](#advanced-settings)
7. [Embedding the Widget](#embedding-the-widget)
8. [Testing Your Setup](#testing-your-setup)
9. [Troubleshooting](#troubleshooting)

---

## Installation

### Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.7 or higher
- SSL certificate (HTTPS) for voice features
- Modern web browser (Chrome, Firefox, Safari, Edge)

### Installation Steps

1. **Upload Plugin**
   ```
   - Download the plugin ZIP file
   - Go to WordPress Admin → Plugins → Add New
   - Click "Upload Plugin"
   - Choose the ZIP file and click "Install Now"
   - Click "Activate Plugin"
   ```

2. **Verify Installation**
   - Look for "AAVAC Bot" in the WordPress admin menu
   - Click it to access the settings page

3. **Database Setup**
   - The plugin automatically creates necessary database tables on activation
   - Check WordPress debug log if you encounter any issues

---

## Initial Configuration

### Step 1: Connection Settings

1. Navigate to **AAVAC Bot → Connection** tab

2. **n8n Webhook URL** (Required for chat)
   - Enter your n8n webhook URL (e.g., `https://your-n8n.com/webhook/chat`)
   - This is where chat messages will be sent for AI processing
   - Leave empty for now if you haven't set up n8n yet (see [Automation Integration](#automation-integration))

3. **Widget Settings**
   - ✅ **Enable Widget** - Turn on the chat widget
   - Click **Save Changes**

---

## Voice Provider Setup

AAVAC Bot supports two voice providers: **Retell AI** (recommended) and **ElevenLabs**. Choose one based on your needs.

### Option A: Retell AI Setup (Recommended)

**Why Retell AI?**
- Purpose-built for conversational AI
- 24kHz audio quality
- Built-in telephony support
- Advanced call analytics

**Setup Steps:**

1. **Get Retell AI Credentials**
   - Sign up at [Retell AI](https://www.retellai.com/)
   - Create a new agent in the Retell dashboard
   - Copy your **API Key** from Settings → API Keys
   - Copy your **Agent ID** from your agent details

2. **Configure in AAVAC Bot**
   - Go to **AAVAC Bot → Voice Provider** tab
   - ✅ **Enable Voice Features**
   - **Select Provider**: Choose "Retell AI"
   - **API Key**: Paste your Retell API key
   - **Agent ID**: Paste your Retell agent ID
   - Click **Test Connection** to verify
   - Click **Save Changes**

3. **Retell Webhook Setup**
   - In Retell dashboard, go to your agent settings
   - Set webhook URL to: `https://your-site.com/wp-json/antek-chat/v1/webhook`
   - Enable webhook events: `call_started`, `call_ended`, `transcript`

### Option B: ElevenLabs Setup

**Why ElevenLabs?**
- Ultra-low latency (~300ms)
- Highest quality voice synthesis
- Interruption handling
- WebRTC support

**Setup Steps:**

1. **Get ElevenLabs Credentials**
   - Sign up at [ElevenLabs](https://elevenlabs.io/)
   - Navigate to Conversational AI section
   - Create a new agent
   - Copy your **Agent ID**
   - For private agents: Copy your **API Key** from Profile → API Keys

2. **Configure in AAVAC Bot**
   - Go to **AAVAC Bot → Voice Provider** tab
   - ✅ **Enable Voice Features**
   - **Select Provider**: Choose "ElevenLabs"
   - **Agent ID**: Paste your ElevenLabs agent ID
   - **Public Agent**: Check this if your agent is public (no API key needed)
   - **API Key**: (Only if private agent) Paste your ElevenLabs API key
   - **Connection Type**:
     - Choose "WebSocket" for broader compatibility
     - Choose "WebRTC" for lowest latency (requires modern browser)
   - Click **Test Connection** to verify
   - Click **Save Changes**

---

## Webhook Configuration

Configure how external services (n8n, Retell, ElevenLabs) send events to your site.

### Step 1: Choose Authentication Method

Go to **AAVAC Bot → Webhooks** tab

**Authentication Methods:**

#### 1. API Key (Recommended for most users)

**Best for**: n8n, Make, Zapier integrations

**Setup**:
- Select **API Key** from authentication dropdown
- Click **Generate Random Key**
- Copy the generated key
- In your external service (n8n, etc.), add HTTP header:
  ```
  X-API-Key: your-generated-key-here
  ```

#### 2. HMAC-SHA256 (Most Secure)

**Best for**: High-security requirements

**Setup**:
- Select **HMAC-SHA256** from authentication dropdown
- Click **Generate Secret**
- Copy the secret
- In your external service, sign the webhook payload:
  ```javascript
  const signature = crypto
    .createHmac('sha256', 'your-secret')
    .update(JSON.stringify(payload))
    .digest('hex');

  // Add header
  headers['X-Webhook-Signature'] = signature;
  ```

#### 3. Basic Auth

**Best for**: Services that support Basic Authentication

**Setup**:
- Select **Basic Auth** from authentication dropdown
- Enter username and password
- In your external service, use Basic Authentication with these credentials

#### 4. None (Development Only)

**⚠️ Warning**: Only use during development on localhost

### Step 2: Webhook Endpoint

Your webhook endpoint is:
```
https://your-site.com/wp-json/antek-chat/v1/webhook
```

Copy this URL and use it in your external services.

### Step 3: IP Whitelist (Optional)

Add IP addresses that are allowed to send webhooks (one per line):
```
192.168.1.100
10.0.0.0/8
```

Supports CIDR notation for IP ranges.

### Step 4: Test Your Webhook

1. In **Webhooks** tab, scroll to **Test Webhook** section
2. Select provider (Retell, ElevenLabs, or Custom)
3. Select event type
4. Click **Send Test Webhook**
5. Verify you see "✅ Webhook test successful!"

---

## Automation Integration

### n8n Setup (Recommended)

**What is n8n?**
n8n is a workflow automation tool that connects your chat to AI services (OpenAI, Claude, etc.)

#### Step 1: Create n8n Workflow

1. **Webhook Trigger**
   - Add "Webhook" node as trigger
   - Method: POST
   - Path: `/webhook/chat`
   - Copy the webhook URL

2. **Process Message**
   - Add "HTTP Request" node
   - URL: `https://api.openai.com/v1/chat/completions`
   - Method: POST
   - Authentication: Bearer Token (your OpenAI API key)
   - Body:
     ```json
     {
       "model": "gpt-4",
       "messages": [
         {"role": "user", "content": "{{$node.Webhook.json.message}}"}
       ]
     }
     ```

3. **Format Response**
   - Add "Set" node
   - Set `response` to `{{$node.HTTP_Request.json.choices[0].message.content}}`

4. **Return Response**
   - Add "Respond to Webhook" node
   - Response Body: `{"response": "{{$node.Set.json.response}}"}`

5. **Activate** your workflow

#### Step 2: Connect to AAVAC Bot

1. Copy the n8n webhook URL from step 1
2. Go to **AAVAC Bot → Connection** tab
3. Paste URL in **n8n Webhook URL** field
4. Save changes

#### Step 3: Test Integration

1. Open your site in a browser
2. Click the chat widget
3. Send a message
4. Verify response appears in chat
5. Check n8n execution log to debug if needed

### Make.com Setup

1. Create a new scenario in Make.com
2. Add **Webhooks → Custom Webhook** trigger
3. Copy webhook URL
4. Add AI service modules (OpenAI, Claude, etc.)
5. Add **Webhooks → Webhook Response** at the end
6. Return JSON: `{"response": "AI response here"}`
7. Paste Make webhook URL in **AAVAC Bot → Connection**

### Zapier Setup

1. Create new Zap with **Webhooks by Zapier** trigger
2. Choose "Catch Hook"
3. Copy webhook URL
4. Add AI action (OpenAI, Claude, etc.)
5. Add **Webhooks by Zapier** action
6. Choose "POST"
7. Return JSON response
8. Paste Zapier webhook URL in **AAVAC Bot → Connection**

---

## Advanced Settings

### Rate Limiting

Go to **AAVAC Bot → Advanced** tab

**Recommended Settings:**
- **Text Messages per Hour**: 50 (prevents spam)
- **Voice Tokens per Minute**: 10 (prevents abuse)
- **File Uploads per Hour**: 10 (prevents storage abuse)

Adjust based on your traffic and use case.

### Async Job Processing

**Enable Async Jobs** if you have:
- Long-running AI operations (>5 seconds)
- Media transcoding needs
- Background processing requirements

**Settings:**
- **Max Retries**: 3 (recommended)
- **Callback Timeout**: 30 seconds
- **Cleanup Age**: 7 days (removes old job records)

### Media Upload Settings

**File Size Limit**: 50 MB (default)
- Increase for video uploads
- Decrease to save server storage

**Allowed File Types**:
- ✅ Images (JPG, PNG, GIF, WebP)
- ✅ Audio (MP3, WAV, OGG)
- ✅ Documents (PDF, DOC, DOCX)
- ✅ Video (MP4, WebM, MOV)

Uncheck types you don't want to support.

**Storage Location**: `wp-content/antek-media` (default)
- Files stored outside web root for security
- Protected by .htaccess
- Access via token-based URLs only

---

## Embedding the Widget

### Method 1: Automatic Display

The widget appears automatically on all pages once enabled.

**Position**: Configure in **Appearance** tab
- Bottom Right (default)
- Bottom Left
- Top Right
- Top Left

### Method 2: Shortcode

Add widget to specific pages/posts:

```
[antek_chat]
```

**With Options:**
```
[antek_chat position="bottom-left" voice_enabled="true"]
```

### Method 3: Template Tag

Add to theme files (PHP):

```php
<?php
if (function_exists('antek_chat_widget')) {
    antek_chat_widget(array(
        'position' => 'bottom-right',
        'voice_enabled' => true
    ));
}
?>
```

### Method 4: Gutenberg Block

1. Edit page in Gutenberg editor
2. Click "+" to add block
3. Search for "AAVAC Chat"
4. Insert and configure

---

## Testing Your Setup

### Text Chat Test

1. **Open Widget**
   - Visit your site in incognito/private window
   - Click chat widget icon (bottom-right by default)

2. **Send Test Message**
   - Type "Hello" and press Enter
   - Wait for response (should appear within 2-5 seconds)

3. **Verify Response**
   - Check that response appears
   - Check n8n execution log for debugging

**Troubleshooting Text Chat:**
- No response? Check n8n webhook URL in Connection settings
- Error message? Check WordPress debug log
- Rate limited? Check Advanced settings
- 500 error? Check server error logs

### Voice Call Test

1. **Start Voice Call**
   - Open chat widget
   - Click microphone icon (🎙️)
   - Allow microphone access when prompted

2. **Test Conversation**
   - Say "Hello" or "Can you hear me?"
   - Wait for voice response
   - Check that transcript appears in chat

3. **Verify Audio**
   - Check microphone input (you should see speaking indicator)
   - Check audio output (agent voice should be clear)
   - Check transcript accuracy

**Troubleshooting Voice:**
- No microphone access? Check browser permissions
- No connection? Click "Test Connection" in Voice Provider settings
- Poor audio? Try WebRTC connection type (ElevenLabs)
- Transcript issues? Check provider dashboard logs

### File Upload Test

1. **Upload File**
   - Click attachment icon (📎)
   - Choose a test image
   - Wait for upload progress bar
   - Verify thumbnail appears

2. **Send with Message**
   - Type a message
   - Press Enter
   - Verify both message and image appear in chat

3. **Test Different Types**
   - Upload image (JPG/PNG)
   - Upload document (PDF)
   - Upload audio (MP3)
   - Verify all display correctly

**Troubleshooting Uploads:**
- Upload fails? Check file size limit in Advanced settings
- File type error? Check allowed types in Advanced settings
- Upload hangs? Check server upload_max_filesize in php.ini
- 413 error? Increase nginx/Apache max body size

---

## Troubleshooting

### Common Issues

#### Issue: Widget doesn't appear

**Solutions:**
1. Check widget is enabled: **Connection → Enable Widget**
2. Clear browser cache (Ctrl+Shift+R)
3. Check console for JavaScript errors (F12)
4. Verify no theme CSS conflicts

#### Issue: No response to messages

**Solutions:**
1. Verify n8n webhook URL is correct
2. Check n8n workflow is activated
3. Test webhook manually with Postman:
   ```bash
   curl -X POST https://your-n8n.com/webhook/chat \
     -H "Content-Type: application/json" \
     -d '{"message": "test"}'
   ```
4. Check WordPress debug log for errors

#### Issue: Voice call fails to connect

**Solutions:**
1. Check HTTPS is enabled (voice requires SSL)
2. Test connection: **Voice Provider → Test Connection**
3. Verify API credentials are correct
4. Check browser console for errors
5. Try different browser (Chrome recommended)

#### Issue: Rate limit errors

**Solutions:**
1. Increase limits: **Advanced → Rate Limiting**
2. Check if legitimate traffic or attack
3. Clear rate limit cache: **Advanced → Clear Rate Limits**

#### Issue: File uploads fail

**Solutions:**
1. Check file size: **Advanced → Max File Size**
2. Check file type allowed: **Advanced → Allowed Types**
3. Verify upload directory permissions (755)
4. Check PHP upload_max_filesize setting
5. Check disk space on server

### Debug Mode

Enable WordPress debug mode to see detailed errors:

1. Edit `wp-config.php`
2. Add/update these lines:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```
3. Check debug log at `wp-content/debug.log`

### Getting Help

**Before requesting support, gather:**
- WordPress version
- PHP version
- Plugin version
- Browser and version
- Error messages from debug log
- Steps to reproduce issue

**Support Channels:**
- GitHub Issues: [Report bug](https://github.com/antek-automation/aavac-bot/issues)
- Email: support@antekautomation.com
- Documentation: https://www.antekautomation.com/docs/aavac-bot

---

## Security Best Practices

### 1. Encryption Key

**Critical**: Set a custom encryption key in `wp-config.php`:

```php
define('ANTEK_CHAT_ENCRYPTION_KEY', 'your-random-32-character-string-here');
```

Generate secure key:
```bash
openssl rand -base64 32
```

### 2. Webhook Authentication

**Always use authentication** in production:
- ✅ API Key (good)
- ✅ HMAC-SHA256 (best)
- ✅ Basic Auth (good)
- ❌ None (development only)

### 3. IP Whitelist

Restrict webhooks to known IP addresses:
- Add provider IPs to whitelist
- Add n8n/Make/Zapier IPs
- Blocks unauthorized webhook attempts

### 4. SSL Certificate

**Required for voice features**:
- Install valid SSL certificate
- Force HTTPS in WordPress settings
- Redirect HTTP to HTTPS

### 5. Regular Updates

- Update plugin when new versions release
- Update WordPress core
- Update PHP version
- Monitor security notifications

### 6. Rate Limiting

**Enable and configure** rate limits:
- Prevents abuse
- Protects API quotas
- Reduces costs
- Prevents DoS attacks

### 7. File Upload Security

**Configure safely**:
- Set reasonable file size limits
- Restrict file types to necessary ones only
- Regularly cleanup old files
- Monitor storage usage

---

## Performance Optimization

### 1. Caching

**Compatible with**:
- WP Rocket
- W3 Total Cache
- WP Super Cache

**Configuration**:
- Exclude `/wp-json/antek-chat/*` from cache
- Exclude widget JavaScript from minification
- Clear cache after plugin updates

### 2. CDN

**Voice provider SDKs** load from CDN:
- Retell SDK: jsDelivr
- ElevenLabs SDK: elevenlabs.io
- Faster loading
- Global distribution

### 3. Database Optimization

**Automatic cleanup**:
- Old jobs removed after 7 days (configurable)
- Old media removed after 90 days
- Session data optimized

**Manual cleanup**:
- **Advanced → Cleanup Old Jobs**
- **Advanced → Cleanup Old Media**

### 4. Server Requirements

**Recommended specs**:
- 2+ CPU cores
- 2GB+ RAM
- SSD storage
- PHP 8.0+ with OPcache
- MySQL 8.0+ with query cache

---

## Next Steps

✅ **You're all set!** Your AAVAC Bot is configured and ready.

**Recommended next steps:**

1. **Customize Appearance**
   - Go to **Appearance** tab
   - Match widget colors to your brand
   - Add custom CSS if needed

2. **Configure Popup**
   - Go to **Popup Settings** tab
   - Set trigger (time, scroll, exit intent)
   - Write welcome message
   - Choose display frequency

3. **Monitor Performance**
   - Check **Advanced** tab for statistics
   - Review job processing status
   - Monitor storage usage
   - Review webhook event log

4. **Optimize n8n Workflow**
   - Add conversation history context
   - Implement intent detection
   - Add fallback responses
   - Connect to CRM/database

5. **Test Thoroughly**
   - Test on mobile devices
   - Test with screen readers
   - Test voice on different browsers
   - Test file uploads of various types

---

## Changelog

### Version 1.1.0
- ✨ Added voice provider factory (Retell AI + ElevenLabs)
- ✨ Added multimodal support (file uploads)
- ✨ Added REST API endpoints
- ✨ Added encryption for API keys
- ✨ Added rate limiting
- ✨ Added async job processing
- ✨ Added webhook authentication (API Key, HMAC, Basic Auth)
- 🔒 Enhanced security across the board

### Version 1.0.0
- 🎉 Initial release
- ✨ Basic chat widget
- ✨ n8n webhook integration
- ✨ ElevenLabs voice support
- ✨ Popup system
- ✨ Appearance customization

---

## Support & Resources

**Documentation**: https://www.antekautomation.com/docs/aavac-bot
**GitHub**: https://github.com/antek-automation/aavac-bot
**Support**: support@antekautomation.com
**Website**: https://www.antekautomation.com

---

*Made with ❤️ by [Antek Automation](https://www.antekautomation.com)*
