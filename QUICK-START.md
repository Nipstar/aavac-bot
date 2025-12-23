# AAVAC Bot - Quick Start Guide

Get your AI chat and voice bot running in **5 minutes**!

## Prerequisites

- ✅ WordPress site with HTTPS
- ✅ n8n account (free tier works) - [Sign up](https://n8n.io/)
- ✅ Voice provider account (optional):
  - [Retell AI](https://www.retellai.com/) (recommended)
  - OR [ElevenLabs](https://elevenlabs.io/)

---

## Step 1: Install Plugin (1 minute)

1. Upload ZIP to WordPress (Plugins → Add New → Upload)
2. Click **Activate**
3. Find **AAVAC Bot** in admin menu

---

## Step 2: Setup n8n Workflow (2 minutes)

### Create Workflow

```
1. Add "Webhook" node (trigger)
   - HTTP Method: POST
   - Copy webhook URL

2. Add "OpenAI" node
   - Model: gpt-4
   - Message: {{$json.message}}

3. Add "Respond to Webhook" node
   - Body: {"response": "{{$json.choices[0].message.content}}"}

4. Click "Activate workflow"
```

### Connect to WordPress

1. Go to **AAVAC Bot → Connection**
2. Paste n8n webhook URL
3. ✅ Enable Widget
4. Save

---

## Step 3: Test Chat (30 seconds)

1. Visit your website
2. Click chat icon (bottom-right)
3. Type "Hello"
4. Get AI response! 🎉

---

## Step 4: Add Voice (Optional - 1 minute)

### Retell AI

1. Get credentials from [Retell Dashboard](https://beta.retellai.com/)
2. Go to **AAVAC Bot → Voice Provider**
3. Select "Retell AI"
4. Paste API Key and Agent ID
5. Save

### ElevenLabs

1. Get credentials from [ElevenLabs](https://elevenlabs.io/app/conversational-ai)
2. Go to **AAVAC Bot → Voice Provider**
3. Select "ElevenLabs"
4. Paste Agent ID (and API Key if private)
5. Save

### Test Voice

1. Open chat widget
2. Click microphone icon 🎙️
3. Allow mic access
4. Say "Hello"
5. Hear AI response! 🔊

---

## Step 5: Secure Webhooks (30 seconds)

1. Go to **AAVAC Bot → Webhooks**
2. Select **API Key** authentication
3. Click **Generate Random Key**
4. Copy key
5. In n8n, add to your webhook node:
   - **Headers** → Add
   - Name: `X-API-Key`
   - Value: (paste key)
6. Save both places

---

## You're Done! 🎉

Your AI chat bot is live with:
- ✅ Text chat
- ✅ Voice calls (if configured)
- ✅ Secure webhooks
- ✅ n8n integration

---

## Next Steps

**Customize Appearance**
- **Appearance** tab → Change colors to match your brand

**Add File Uploads**
- Already enabled! Just click 📎 to attach files

**Configure Rate Limits**
- **Advanced** tab → Prevent abuse

**Test Everything**
- Text chat ✓
- Voice calls ✓
- File uploads ✓

---

## Common Issues

### No response to messages?

1. Check n8n workflow is **activated**
2. Check webhook URL is correct
3. Test webhook manually in n8n

### Voice not working?

1. Check site has **HTTPS** (required)
2. Click **Test Connection** in Voice Provider tab
3. Try different browser (Chrome recommended)

### Rate limited?

1. **Advanced** tab → Increase limits
2. Or wait 1 hour for reset

---

## Need Help?

📖 **Full Guide**: See [SETUP-GUIDE.md](./SETUP-GUIDE.md)
🐛 **Bug Reports**: [GitHub Issues](https://github.com/antek-automation/aavac-bot/issues)
📧 **Support**: support@antekautomation.com
🌐 **Website**: https://www.antekautomation.com

---

## Pro Tips

### Better AI Responses

In your n8n workflow, add context:

```javascript
{
  "model": "gpt-4",
  "messages": [
    {
      "role": "system",
      "content": "You are a helpful assistant for [Your Company]. Be friendly and concise."
    },
    {
      "role": "user",
      "content": "{{$json.message}}"
    }
  ]
}
```

### Save Conversation History

1. In n8n, add "Database" node after webhook
2. Store: session_id, message, response, timestamp
3. Before OpenAI node, fetch recent history
4. Include in messages array for context

### Add to Specific Pages

Use shortcode:
```
[antek_chat]
```

Or in theme:
```php
<?php antek_chat_widget(); ?>
```

### Customize Welcome Message

1. **Popup Settings** tab
2. ✅ Enable popup
3. Set delay (3 seconds)
4. Write welcome message
5. Save

---

*Ready to build amazing conversational experiences!* 🚀

**Made with ❤️ by [Antek Automation](https://www.antekautomation.com)**
