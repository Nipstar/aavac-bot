# AAVAC Bot - Advanced AI Voice & Chat Connector

[![Version](https://img.shields.io/badge/version-1.1.10-blue.svg)](https://github.com/antek-automation/aavac-bot)
[![WordPress](https://img.shields.io/badge/wordpress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/php-7.4%2B-purple.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL%20v2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

Transform your WordPress site into an intelligent conversational platform with voice calls, AI chat, and multimodal interactions.

![AAVAC Bot Demo](https://via.placeholder.com/800x400?text=AAVAC+Bot+Demo)

## 🚀 Features

### 💬 **AI-Powered Chat**
- Real-time chat widget with n8n/Make/Zapier integration
- Connect to any AI (OpenAI, Claude, custom models)
- Conversation history and context awareness
- Customizable appearance and positioning

### 🎙️ **Voice Calls**
- **Retell AI** - Purpose-built conversational AI (24kHz, telephony support)
- **ElevenLabs** - Ultra-low latency voice synthesis (~300ms, WebRTC)
- Real-time transcription with live display
- Seamless voice-to-text-to-voice experience
- Runtime provider switching without code changes

### 📎 **File Uploads**
- Drag-and-drop file attachments
- Support for images, audio, video, documents
- Real-time upload progress
- Secure token-based media access
- In-chat media preview

### 🔒 **Enterprise Security**
- **AES-256-CBC encryption** for API keys at rest
- **Multi-auth webhooks** (API Key, HMAC-SHA256, Basic Auth)
- **Rate limiting** with token bucket algorithm
- **Request deduplication** prevents replay attacks
- **IP whitelisting** for webhook sources

### ⚙️ **Advanced Features**
- **REST API** with 8 endpoints for programmatic access
- **Async job processing** for long-running operations
- **Provider abstraction** via Factory Pattern
- **Event normalization** across different voice providers
- **Backward compatible** with v1.0.0

### 🎨 **Customization**
- Match your brand colors and styling
- Custom CSS support
- Popup system (time, scroll, exit intent triggers)
- Shortcode and template tag support
- Responsive design (mobile-first)

---

## 📋 Table of Contents

- [Quick Start](#quick-start)
- [Installation](#installation)
- [Configuration](#configuration)
- [Documentation](#documentation)
- [Architecture](#architecture)
- [API Reference](#api-reference)
- [Development](#development)
- [Support](#support)
- [Contributing](#contributing)
- [License](#license)

---

## ⚡ Quick Start

Get up and running in 5 minutes:

### 1. Install Plugin

```bash
# Upload to WordPress
wp-content/plugins/aavac-bot/

# Or via WordPress admin
Plugins → Add New → Upload → aavac-bot.zip
```

### 2. Setup n8n Workflow

```javascript
// 1. Webhook Trigger (POST)
// 2. OpenAI Node
{
  "model": "gpt-4",
  "messages": [{"role": "user", "content": "{{$json.message}}"}]
}
// 3. Respond to Webhook
{"response": "{{$json.choices[0].message.content}}"}
```

### 3. Configure WordPress

```
AAVAC Bot → Connection
- Paste n8n webhook URL
- Enable Widget
- Save
```

### 4. Test!

Visit your site, click chat icon, send "Hello" → Get AI response! 🎉

**📖 Full Quick Start**: See [QUICK-START.md](./QUICK-START.md)

---

## 📦 Installation

### Requirements

- WordPress 5.0+
- PHP 7.4+ (8.0+ recommended)
- MySQL 5.7+ / MariaDB 10.2+
- HTTPS (required for voice features)
- Modern browser (Chrome, Firefox, Safari, Edge)

### Install from ZIP

1. Download latest release from [Releases](https://github.com/antek-automation/aavac-bot/releases)
2. WordPress Admin → Plugins → Add New → Upload Plugin
3. Choose ZIP file and click "Install Now"
4. Click "Activate Plugin"

### Install via Git

```bash
cd wp-content/plugins/
git clone https://github.com/antek-automation/aavac-bot.git
```

Then activate in WordPress admin.

### Verify Installation

1. Check **AAVAC Bot** appears in admin menu
2. Visit settings page
3. Check database tables created:
   - `wp_antek_chat_sessions`
   - `wp_antek_chat_media`
   - `wp_antek_chat_jobs`
   - `wp_antek_chat_webhooks`

---

## ⚙️ Configuration

### Basic Setup (Text Chat)

```php
// 1. Connection Settings
n8n Webhook URL: https://your-n8n.com/webhook/chat
✅ Enable Widget

// 2. Test
Visit site → Click chat → Send message
```

### Voice Setup (Retell AI)

```php
// 1. Voice Provider Settings
✅ Enable Voice Features
Provider: Retell AI
API Key: key_***
Agent ID: agent_***

// 2. Test
Click 🎙️ → Allow mic → Speak → Hear response
```

### Voice Setup (ElevenLabs)

```php
// 1. Voice Provider Settings
✅ Enable Voice Features
Provider: ElevenLabs
Agent ID: agent_***
✅ Public Agent (or add API Key)
Connection: WebSocket (or WebRTC)

// 2. Test
Click 🎙️ → Allow mic → Speak → Hear response
```

### Webhook Security

```php
// 1. Webhooks Tab
Authentication: API Key
→ Generate Random Key

// 2. In n8n
Add Header: X-API-Key = generated-key

// 3. Test
Webhooks tab → Send Test Webhook → ✅ Success
```

**📖 Full Configuration**: See [SETUP-GUIDE.md](./SETUP-GUIDE.md)

---

## 📚 Documentation

| Document | Description |
|----------|-------------|
| [QUICK-START.md](./QUICK-START.md) | Get running in 5 minutes |
| [SETUP-GUIDE.md](./SETUP-GUIDE.md) | Complete setup guide (700+ lines) |
| [IMPLEMENTATION-SUMMARY.md](./IMPLEMENTATION-SUMMARY.md) | Technical architecture & API docs |
| [CLAUDE.md](./CLAUDE.md) | Developer guide for AI assistants |

### Key Topics

- **Installation & Setup** - Get started quickly
- **Voice Providers** - Retell AI vs ElevenLabs comparison
- **Webhook Configuration** - Secure your integrations
- **n8n Integration** - Build AI workflows
- **API Reference** - REST endpoints documentation
- **Security Best Practices** - Protect your site
- **Troubleshooting** - Common issues & solutions

---

## 🏗️ Architecture

### Technology Stack

```
Frontend:
├── JavaScript ES6+ (Vanilla JS + jQuery)
├── Voice Provider Factory Pattern
├── WebSocket/WebRTC audio streaming
└── Drag-and-drop file uploads

Backend:
├── PHP 7.4+ (WordPress standards)
├── WordPress REST API
├── Factory Pattern (provider abstraction)
├── AES-256-CBC encryption
└── Token bucket rate limiting

External:
├── Retell AI / ElevenLabs (voice)
├── n8n / Make / Zapier (automation)
└── OpenAI / Claude / Custom AI (chat)
```

### System Design

```
┌─────────────┐
│   Browser   │ ← User interacts
└──────┬──────┘
       │ REST API / WebSocket
┌──────▼──────┐
│  WordPress  │ ← AAVAC Bot plugin
│   + Plugin  │
└──────┬──────┘
       │
   ┌───┴────┐
   │        │
   ▼        ▼
┌─────┐  ┌──────────┐
│ n8n │  │ Voice    │
│     │  │ Provider │
└──┬──┘  └────┬─────┘
   │          │
   ▼          ▼
┌─────┐  ┌──────────┐
│ AI  │  │ Retell / │
│Model│  │ElevenLabs│
└─────┘  └──────────┘
```

### Class Structure

```php
// Voice Provider Abstraction
interface Antek_Chat_Voice_Provider_Interface {
    public function generate_access_token();
    public function verify_webhook_signature();
    public function normalize_webhook_event();
}

class Antek_Chat_Retell_Provider implements Interface { }
class Antek_Chat_ElevenLabs_Provider implements Interface { }
class Antek_Chat_Voice_Provider_Factory { }

// Security & Processing
class Antek_Chat_Encryption_Manager { }
class Antek_Chat_Rate_Limiter { }
class Antek_Chat_Webhook_Authenticator { }
class Antek_Chat_Async_Job_Processor { }

// Media & Sessions
class Antek_Chat_Media_Manager { }
class Antek_Chat_Multimodal_Session_Manager { }

// API & Admin
class Antek_Chat_REST_API_Controller { }
class Antek_Chat_Admin_Settings { }
class Antek_Chat_Widget_Renderer { }
```

---

## 🔌 API Reference

### REST Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/antek-chat/v1/token/{provider}` | POST | Generate voice access token |
| `/antek-chat/v1/webhook` | POST | Receive provider webhooks |
| `/antek-chat/v1/upload` | POST | Upload media file |
| `/antek-chat/v1/media/{filename}` | GET | Serve media file |
| `/antek-chat/v1/message` | POST | Send chat message |
| `/antek-chat/v1/providers` | GET | List available providers |
| `/antek-chat/v1/jobs/{id}` | GET | Get job status |
| `/antek-chat/v1/test-webhook` | POST | Test webhook config |

### Authentication

```javascript
// All requests require WordPress nonce
fetch('/wp-json/antek-chat/v1/token/retell', {
    method: 'POST',
    headers: {
        'X-WP-Nonce': antekChatConfig.nonce,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        session_id: 'session_123',
        metadata: { page_url: window.location.href }
    })
});
```

### Webhook Payload

```json
{
    "event": "call_started",
    "session_id": "session_123",
    "provider": "retell",
    "timestamp": "2025-12-20T10:30:00Z",
    "data": {
        "call_id": "call_abc123",
        "agent_id": "agent_xyz"
    }
}
```

**📖 Full API Docs**: See [IMPLEMENTATION-SUMMARY.md](./IMPLEMENTATION-SUMMARY.md)

---

## 🛠️ Development

### Local Setup

```bash
# Clone repository
git clone https://github.com/antek-automation/aavac-bot.git
cd aavac-bot

# Install WordPress
# Copy plugin to wp-content/plugins/aavac-bot/

# Enable debug mode
# Add to wp-config.php:
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Project Structure

```
aavac-bot/
├── antek-chat-connector.php    # Main plugin file
├── includes/                    # PHP classes
│   ├── class-encryption-manager.php
│   ├── class-rate-limiter.php
│   ├── class-rest-api-controller.php
│   ├── providers/              # Voice providers
│   └── interfaces/             # Abstractions
├── admin/                      # Admin interface
│   ├── views/                  # Settings tabs
│   └── css/js/                 # Admin assets
├── public/                     # Frontend
│   ├── js/                     # JavaScript
│   │   ├── providers/         # Voice providers
│   │   ├── file-uploader.js
│   │   └── multimodal-widget.js
│   └── css/                    # Stylesheets
└── database/                   # Migrations
```

### Coding Standards

- Follow [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- Use WordPress security functions (nonce, sanitize, escape)
- Prefix all functions/classes with `antek_chat_`
- Document with PHPDoc and JSDoc
- Security-first approach

### Testing

```bash
# PHP Unit Tests (coming soon)
phpunit tests/

# JavaScript Tests (coming soon)
npm test

# Manual Testing Checklist
- [ ] Text chat works
- [ ] Voice calls connect (Retell)
- [ ] Voice calls connect (ElevenLabs)
- [ ] File uploads work
- [ ] Media displays in chat
- [ ] Webhooks authenticated
- [ ] Rate limiting enforced
- [ ] Encryption/decryption works
```

---

## 🤝 Support

### Getting Help

1. **Documentation**: Check [SETUP-GUIDE.md](./SETUP-GUIDE.md) first
2. **GitHub Issues**: [Report bugs](https://github.com/antek-automation/aavac-bot/issues)
3. **Email Support**: support@antekautomation.com
4. **Website**: https://www.antekautomation.com

### Before Asking

Please provide:
- WordPress version
- PHP version
- Plugin version
- Browser and version
- Error messages from debug log
- Steps to reproduce

### Common Issues

| Issue | Solution |
|-------|----------|
| No chat response | Check n8n workflow activated |
| Voice fails | Verify HTTPS enabled |
| Upload fails | Check file size/type limits |
| Rate limited | Increase limits in Advanced tab |
| Webhook error | Test connection in Webhooks tab |

---

## 🤝 Contributing

Contributions welcome! Please follow these guidelines:

### How to Contribute

1. Fork the repository
2. Create feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open Pull Request

### Contribution Guidelines

- Follow WordPress coding standards
- Add tests for new features
- Update documentation
- Keep backward compatibility
- Security-first mindset

### Areas We Need Help

- [ ] Unit tests (PHPUnit)
- [ ] Integration tests
- [ ] Translations (i18n)
- [ ] Documentation improvements
- [ ] UI/UX enhancements
- [ ] Performance optimization

---

## 📄 License

This project is licensed under the **GPL v2 or later**.

```
AAVAC Bot - Advanced AI Voice & Chat Connector
Copyright (C) 2025 Antek Automation

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

Full license: https://www.gnu.org/licenses/gpl-2.0.html

---

## 🎯 Roadmap

### v1.2.0 (Planned)

- [ ] Gutenberg block for widget embedding
- [ ] Analytics dashboard (conversations, call duration)
- [ ] Custom voice provider support
- [ ] WebRTC direct peer connection
- [ ] Multi-language support (i18n)

### v1.3.0 (Future)

- [ ] Agent handoff (human takeover)
- [ ] Sentiment analysis
- [ ] CRM integrations (HubSpot, Salesforce)
- [ ] Video chat support
- [ ] Screen sharing

### v2.0.0 (Future)

- [ ] Multi-agent orchestration
- [ ] Custom AI model training
- [ ] Advanced analytics & reporting
- [ ] White-label options

Vote on features: [GitHub Discussions](https://github.com/antek-automation/aavac-bot/discussions)

---

## 🙏 Credits

**Built with**:
- [WordPress](https://wordpress.org/) - CMS platform
- [Retell AI](https://www.retellai.com/) - Voice AI provider
- [ElevenLabs](https://elevenlabs.io/) - Voice synthesis
- [n8n](https://n8n.io/) - Workflow automation

**Inspired by**:
- Claude Code AI assistant
- Modern conversational AI platforms
- Enterprise chatbot solutions

---

## 📞 Contact

**Antek Automation**
- Website: https://www.antekautomation.com
- Email: support@antekautomation.com
- GitHub: [@antek-automation](https://github.com/antek-automation)

---

## ⭐ Show Your Support

If you find AAVAC Bot useful, please:
- ⭐ Star this repository
- 🐦 Share on social media
- 📝 Write a review
- 🤝 Contribute to development

---

*Made with ❤️ by [Antek Automation](https://www.antekautomation.com)*

**Transform conversations. Empower users. Build the future of AI interaction.**
