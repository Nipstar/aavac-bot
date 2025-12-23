# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**AAVAC Bot** (Advanced AI Voice & Chat) - A WordPress plugin providing multimodal chat and voice capabilities with Retell AI voice provider, secure encryption, REST API, media uploads, and enterprise-grade webhook authentication.

**Version**: 1.2.0
**Attribution**: Antek Automation - https://www.antekautomation.com
**License**: GPL v2 or later

## Recent Changes (v1.2.0)

**n8n Provider Integration:**
- Added n8n-retell provider for proxying Retell AI through n8n workflows
- Provider selection UI: Direct Retell API or n8n middleware
- Supports BOTH simple webhook and session-based text chat modes
- n8n configuration: base URL, voice endpoint, text endpoints, agent IDs
- Re-enabled Retell SDK CDN loading for voice calls
- Factory pattern ensures clean provider switching
- Full backward compatibility with existing direct Retell integration

**Key Implementation:**
- New file: `class-n8n-retell-provider.php` (650 lines)
- Provider registered in factory alongside existing Retell provider
- Settings UI in Voice Provider Settings with show/hide logic
- Settings sanitization for all n8n fields
- REST API updated to use factory pattern for provider selection
- Activation hook includes n8n defaults

**Text Chat Modes:**
- Simple webhook: Uses existing Connection settings webhook
- Session-based: Creates n8n chat session → caches chat_id → sends messages
- WordPress transients cache chat_id for 24 hours

## Previous Fixes (v1.1.19)

**Custom Colors Override Fix:**
- Fixed theme CSS overriding custom colors with `!important` rules
- Added high specificity CSS selectors to beat theme styles
- Color detection only runs when explicitly set to elementor/divi
- Added microphone.svg icon for voice button
- Custom colors now always respected regardless of theme

**Previous Fixes (v1.1.8):**
- Fixed critical 500 error in text chat message endpoint
- Fixed wrong method names causing PHP fatal errors
- Simplified asset loading - Retell SDK loads in HEAD
- Message endpoint returns friendly defaults when webhook not configured

**Previous Fixes (v1.1.7):**
- Fixed plugin crash when theme color detection disabled or fails
- All color detection wrapped in try-catch blocks with fallback colors

**Previous Fixes (v1.1.6):**
- Retell Web SDK loads from CDN before provider code
- Fixed script dependency order: SDK → Factory → Provider → Widget

**Previous Fixes (v1.1.5):**
- Cache-busting & verification with file version markers
- WP_DEBUG-aware script versioning

**Ultra-Defensive Validation:**
- Step-by-step validation with specific error messages
- Explicit logging of every field before validation
- AgentId validation with clear error messages
- Handles both `agentId` and `agent_id` formats

**Development Mode:**
- When WP_DEBUG is enabled, scripts load with unique timestamp on every page load
- Completely bypasses browser and CDN caches during development

## Previous Fixes (v1.1.4)

**Critical JavaScript Fix:**
- Added null checking in `voice-provider-factory.js` line 130 before `Object.entries()` to prevent crashes when provider data is invalid

**Voice Button Visibility:**
- Redesigned voice button: 140px diameter, white border, orange/red gradient
- Purple gradient background for voice mode
- Added pulsing ring animation and glow effects
- White text on colored backgrounds for better contrast
- Button changes to green gradient when call is active

**Error Handling:**
- Replaced alert() dialogs with inline error notifications
- Better error messages with auto-dismiss after 5 seconds
- Visual feedback when voice provider not configured

## Development Commands

### Building & Packaging

```bash
# Build distributable ZIP for WordPress installation
./build-plugin.sh

# Output: dist/aavac-bot-{version}.zip with checksums
# The script cleans dev files, copies docs, creates versioned package
```

### Installation & Testing

```bash
# Local WordPress installation
cp -r antek-chat-connector /path/to/wordpress/wp-content/plugins/
# Then activate via WordPress admin: Plugins → Installed Plugins → Activate

# Enable debug logging (add to wp-config.php)
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

# Watch logs in real-time
tail -f /path/to/wordpress/wp-content/debug.log

# Verify database tables created
# wp_antek_chat_sessions, wp_antek_chat_media, wp_antek_chat_jobs, wp_antek_chat_webhooks
```

### Development Workflow

No build tools required - this is pure PHP/JavaScript. Changes are immediate:

1. Edit files in `antek-chat-connector/` directory
2. Refresh WordPress admin (for admin changes) or frontend (for widget changes)
3. Check browser DevTools console for JavaScript errors
4. Check `wp-content/debug.log` for PHP errors

## Core Architecture

### Factory Pattern for Voice Providers

The plugin uses **Factory Pattern** to abstract voice provider implementations:

```
Interface: Antek_Chat_Voice_Provider_Interface
├── Retell Provider: class-retell-provider.php (417 lines)
└── Factory: class-voice-provider-factory.php (404 lines)
```

**Key Methods All Providers Must Implement:**
- `generate_access_token()` - Creates short-lived tokens for frontend
- `verify_webhook_signature()` - Authenticates incoming webhooks
- `normalize_webhook_event()` - Standardizes events to common format
- `get_client_config()` - Returns provider-specific frontend config

**Frontend Mirror:**
JavaScript providers in `public/js/providers/` follow the same pattern:
- `voice-provider-factory.js` - Creates appropriate provider instance
- `retell-provider.js` - Retell SDK wrapper

**Note**: The factory pattern design allows for additional voice providers to be added in the future by implementing the `Antek_Chat_Voice_Provider_Interface`.

### Security Layer Architecture

```
Encryption Manager (AES-256-CBC)
├── Keys derived from WordPress AUTH_KEY + custom salt
├── IV stored with ciphertext (format: base64(iv + ciphertext))
└── Used for: API keys, sensitive credentials

Rate Limiter (Token Bucket)
├── Presets: text_messages (50/hr), voice_tokens (10/min), file_uploads (10/hr)
├── Storage: WordPress transients
└── Returns: HTTP 429 with Retry-After header

Webhook Authenticator (Multi-Auth)
├── Methods: API Key, HMAC-SHA256, Basic Auth, IP Whitelist
├── Configurable per-endpoint
└── Logs all attempts to wp_antek_chat_webhooks table
```

### Session Management Hierarchy

```
Base: Antek_Chat_Session_Manager (188 lines)
└── Extended: Antek_Chat_Multimodal_Session_Manager (466 lines)
    ├── Adds: Media attachment support
    ├── Adds: Provider tracking
    └── 100% backward compatible with v1.0.0
```

**Session Data Structure:**
```json
{
  "session_id": "uuid",
  "provider": "retell",
  "conversation_data": [
    {
      "timestamp": "2025-12-20 10:30:00",
      "message": "user message",
      "response": "AI response",
      "media": ["media_id_1", "media_id_2"]
    }
  ]
}
```

### REST API Architecture

**8 Endpoints** in `class-rest-api-controller.php` (651 lines):

```
POST   /antek-chat/v1/token/{provider}     - Generate voice access token
POST   /antek-chat/v1/webhook               - Receive provider webhooks
POST   /antek-chat/v1/upload                - Upload media file
GET    /antek-chat/v1/media/{filename}      - Serve media file (token-protected)
POST   /antek-chat/v1/message               - Send chat message
GET    /antek-chat/v1/providers             - List enabled providers
GET    /antek-chat/v1/jobs/{id}             - Get async job status
POST   /antek-chat/v1/test-webhook          - Test webhook configuration
```

All endpoints:
- Protected by WordPress nonce (`X-WP-Nonce` header)
- Rate limited via `Antek_Chat_Rate_Limiter`
- Log requests for debugging
- Return standardized JSON responses

### n8n/Automation Webhook Flow

**Request TO n8n** (from `class-webhook-handler.php`):
```json
{
  "session_id": "uuid",
  "message": "user input",
  "timestamp": 1234567890,
  "site_url": "https://example.com",
  "metadata": {
    "user_id": 123,
    "history": [{"timestamp": "...", "message": "...", "response": "..."}],
    "page_url": "https://example.com/contact"
  }
}
```

**Expected Response FROM n8n:**
```json
{
  "response": "AI response text",
  "metadata": {
    "intent": "question",
    "confidence": 0.95
  }
}
```

### Database Schema

**4 Custom Tables:**

1. `wp_antek_chat_sessions` (v1.0.0 + v1.1.0 columns)
   - Stores conversation history as JSON
   - Columns added in v1.1.0: `provider`, `encryption_key_version`

2. `wp_antek_chat_media` (v1.1.0)
   - File upload metadata and security tokens
   - Columns: file_name, mime_type, file_size, access_token, expires_at

3. `wp_antek_chat_jobs` (v1.1.0)
   - Async job queue with retry logic
   - Columns: job_type, status, payload, result, attempts, callback_url

4. `wp_antek_chat_webhooks` (v1.1.0)
   - Webhook audit log
   - Columns: provider, event_type, payload, signature_valid, response

**Migrations:**
Managed by `class-database-migrator.php` using WordPress `dbDelta()`. Migrations are idempotent and version-tracked in `wp_options` table (`antek_chat_db_version` = '1.1.0').

## WordPress Coding Standards

This plugin strictly follows WordPress standards:

**Security:**
- All output: `esc_html()`, `esc_attr()`, `esc_url()`, `esc_textarea()`
- All input: `sanitize_text_field()`, `sanitize_textarea_field()`, `sanitize_hex_color()`, `esc_url_raw()`, `absint()`
- Database: Always use `$wpdb->prepare()` with placeholders
- AJAX: `check_ajax_referer()` on every request, `current_user_can()` for admin
- Nonces: `wp_create_nonce('antek_chat_nonce')` validated on all frontend requests

**Naming:**
- All functions/classes: `antek_chat_` or `Antek_Chat_` prefix
- Text domain: `antek-chat-connector`
- All strings: `__()`, `_e()`, `esc_html__()` for i18n

**Hooks:**
- Activation: `register_activation_hook()` - Creates tables, sets defaults
- Deactivation: `register_deactivation_hook()` - Currently preserves data
- AJAX: Both `wp_ajax_` and `wp_ajax_nopriv_` for logged-in/out users

## Key Implementation Patterns

### Adding a New Voice Provider

**Currently Implemented**: Retell AI only

To add a new voice provider:

1. Create `includes/providers/class-{provider}-provider.php`
2. Implement `Antek_Chat_Voice_Provider_Interface`:
   - `generate_access_token()` - Call provider's token API
   - `verify_webhook_signature()` - Validate webhook signatures
   - `normalize_webhook_event()` - Map events to standard format
   - `get_capabilities()` - Return feature flags
3. Register in factory: `class-voice-provider-factory.php` → `create_provider()`
4. Add JavaScript wrapper: `public/js/providers/{provider}-provider.js`
5. Update factory JS: `public/js/providers/voice-provider-factory.js`
6. Update admin UI: `admin/views/voice-provider-settings.php`
7. Require the new provider class in `antek-chat-connector.php`

**Reference Implementation**: See `class-retell-provider.php` for a complete example.

### Adding a New REST Endpoint

1. Add method in `class-rest-api-controller.php`
2. Register route in `register_routes()`:
   ```php
   register_rest_route('antek-chat/v1', '/endpoint', [
       'methods' => 'POST',
       'callback' => [$this, 'handle_endpoint'],
       'permission_callback' => [$this, 'verify_nonce']
   ]);
   ```
3. Apply rate limiting: `$this->rate_limiter->consume('preset_name', $session_id)`
4. Return standardized response:
   ```php
   return new WP_REST_Response(['success' => true, 'data' => $result], 200);
   ```

### Modifying Admin Settings

1. Add field to appropriate view: `admin/views/{tab}-settings.php`
2. Register in `class-admin-settings.php`:
   - Add to `register_settings()` with `register_setting()`
   - Add sanitization in `sanitize_{tab}_settings()` callback
3. Update defaults in activation hook: `antek-chat-connector.php`
4. Access in code: `get_option('antek_chat_{tab}')`

### Handling Encrypted Data

```php
// Encrypt sensitive data before storing
$encrypted = Antek_Chat_Encryption_Manager::get_instance()->encrypt($api_key);
update_option('provider_api_key', $encrypted);

// Decrypt when needed
$encrypted = get_option('provider_api_key');
$api_key = Antek_Chat_Encryption_Manager::get_instance()->decrypt($encrypted);
```

**Never store raw API keys** - always use encryption manager.

## Critical Files by Change Frequency

**High Priority** (frequently modified):
- `public/css/widget-styles.css` - Widget appearance
- `public/js/chat-widget.js` - Chat UI behavior
- `admin/views/*-settings.php` - Admin configuration UI
- `class-webhook-handler.php` - n8n integration logic

**Medium Priority** (occasionally modified):
- `class-rest-api-controller.php` - API endpoints
- `class-session-manager.php` / `class-multimodal-session-manager.php` - Session logic
- `public/js/providers/*.js` - Voice provider implementations

**Low Priority** (rarely modified):
- `antek-chat-connector.php` - Plugin bootstrap (only for major changes)
- `class-plugin-core.php` - Core orchestration
- `class-encryption-manager.php` - Security infrastructure
- `class-database-migrator.php` - Database schema

## Common Development Tasks

### Testing Voice Providers Locally

Requires HTTPS (use ngrok, LocalWP with SSL, or similar):

```bash
# Start local WordPress with HTTPS
# Configure provider in admin: AAVAC Bot → Voice Provider Settings
# Test in browser DevTools console:

// Generate token
fetch('/wp-json/antek-chat/v1/token/retell', {
    method: 'POST',
    headers: {
        'X-WP-Nonce': antekChatConfig.nonce,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        session_id: antekChatConfig.session_id,
        metadata: { page_url: window.location.href }
    })
})
.then(r => r.json())
.then(console.log);
```

### Debugging Webhook Issues

1. Enable debug logging in `wp-config.php`
2. Check logs: `tail -f wp-content/debug.log`
3. Test webhook in admin: AAVAC Bot → Webhooks → Test Webhook
4. Verify authentication: Check `wp_antek_chat_webhooks` table for signature validation
5. Common issues:
   - Wrong authentication method selected
   - API key mismatch between WordPress and n8n
   - HMAC secret not matching
   - IP not whitelisted

### Adding Media Type Support

Edit `class-media-manager.php` → `get_allowed_mime_types()`:

```php
private function get_allowed_mime_types() {
    return array(
        'image' => array('image/jpeg', 'image/png', 'image/gif', 'image/webp'),
        'audio' => array('audio/mpeg', 'audio/wav', 'audio/ogg'),
        'video' => array('video/mp4', 'video/webm'),
        'document' => array('application/pdf', 'application/msword'),
        'new_type' => array('mime/type') // Add here
    );
}
```

Then update file size limits in same file: `validate_file_upload()`.

## Plugin Activation Process

When activated, `antek-chat-connector.php` runs:

1. Creates 4 database tables via `Antek_Chat_Database_Migrator`
2. Sets default options for 5 settings groups:
   - `antek_chat_settings` - Connection (webhook URL, enable flags)
   - `antek_chat_appearance` - Colors, position, size, custom CSS
   - `antek_chat_popup` - Popup triggers and frequency
   - `antek_chat_voice` - Legacy voice settings (v1.0.0 compat)
   - `antek_chat_voice_provider` - Current provider settings (v1.1.0+)
3. Sets database version: `antek_chat_db_version` = '1.1.0'

**Backward Compatibility:**
v1.0.0 settings are preserved and still functional. Settings from v1.1.0+ are additive.

## Usage Examples

### Shortcode

```php
[antek_chat]
[antek_chat position="bottom-left"]
[antek_chat position="top-right" voice_enabled="true"]
```

### Template Tag

```php
<?php antek_chat_widget(); ?>

<?php antek_chat_widget(array(
    'position' => 'bottom-left',
    'voice_enabled' => true
)); ?>
```

## Important Notes

### Frontend Dependencies

The widget requires these scripts loaded in order:
1. jQuery (WordPress built-in)
2. `chat-widget.js` - Base chat functionality
3. `voice-provider-factory.js` - Provider abstraction
4. `retell-provider.js` - Retell AI integration (loaded when voice enabled)
5. `multimodal-widget.js` - Enhanced widget with media support

These are enqueued automatically by `class-widget-renderer.php`.

**Note**: Additional provider JavaScript files can be added to `public/js/providers/` and loaded dynamically by the factory.

### Rate Limiting Behavior

Rate limits are **per session ID**, not per IP. This means:
- Same user across devices = different sessions = separate limits
- Same device, same session = shared limit
- Limits reset based on time window (not fixed intervals)

Token bucket refills at constant rate, allowing bursts up to bucket capacity.

### Provider Event Normalization

Voice providers send different webhook events. The plugin normalizes these to a standard format:

**Retell Events:**
- `call_started` → `voice_connected`
- `call_ended` → `voice_disconnected`
- `call_analyzed` → `voice_analysis_complete`

Access normalized events via: `$provider->normalize_webhook_event($raw_event)`

**Future providers** can implement their own event mapping in the `normalize_webhook_event()` method.

## Troubleshooting Common Issues

**JavaScript crash - "Cannot read properties of undefined":**
- Fixed in v1.1.4: Added null checking before `Object.entries()` in `voice-provider-factory.js`
- Check browser console for the specific error
- Verify REST API endpoint `/antek-chat/v1/providers` returns valid JSON with `providers` object

**Voice button invisible or hard to see:**
- Fixed in v1.1.4: New high-visibility design with:
  - 140px button with white border
  - Orange/red gradient background
  - Purple gradient voice mode background
  - Pulsing ring animation
  - White text on colored backgrounds
- Button turns green when call is active

**Voice provider not initialized:**
- Error message now appears as inline notification instead of alert
- Check Voice Provider Settings in admin
- Verify Retell API key and Agent ID are configured
- Enable "Voice Features" checkbox
- Check browser console for provider initialization errors

**Widget doesn't appear:**
- Check "Enable Widget" in Connection settings
- Verify shortcode placed correctly or template tag added
- Check browser console for JavaScript errors
- Ensure `wp_footer()` called in theme

**Voice calls fail:**
- Requires HTTPS (browser security requirement)
- Verify provider API keys saved and encrypted
- Check provider is enabled in Voice Provider Settings
- Test connection: Click "Test Connection" button in admin

**Webhook returns 401/403:**
- Verify authentication method matches n8n configuration
- Check API key/HMAC secret matches exactly (no extra spaces)
- For IP whitelist, ensure n8n server IP is whitelisted
- Check webhook logs: `SELECT * FROM wp_antek_chat_webhooks ORDER BY id DESC LIMIT 10`

**File upload fails:**
- Check file size under limit (default 10MB)
- Verify MIME type allowed in `class-media-manager.php`
- Check PHP `upload_max_filesize` and `post_max_size` settings
- Ensure uploads directory writable: `wp-content/uploads/antek-chat-media/`

## Documentation Resources

- `README.md` - User-facing overview and quick start
- `QUICK-START.md` - 5-minute setup guide
- `SETUP-GUIDE.md` - Complete configuration guide (700+ lines)
- `IMPLEMENTATION-SUMMARY.md` - Technical architecture and API reference
- `plan.md` - Original development plan (7 phases)
