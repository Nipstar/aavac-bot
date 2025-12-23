# Building a WordPress Multimodal Chat Plugin: Complete Technical Reference

A WordPress plugin supporting both text and voice interactions with Retell AI and ElevenLabs requires a layered architecture: a **provider abstraction layer** for voice platforms, a **secure token generation system** using WordPress REST API, **WebSocket connection management** for real-time streaming, and a **flexible webhook system** for n8n/Make/Zapier automation backends. Both Retell AI and ElevenLabs use similar client-server patterns where API keys never touch the browser—server-side endpoints generate short-lived access tokens that frontend SDKs consume.

## Retell AI provides production-grade telephony voice agents

Retell AI is purpose-built for conversational voice agents with **~800ms latency**, native telephony support, and comprehensive call workflow features. The platform uses a dual-SDK architecture: `retell-sdk` for server-side token generation and `retell-client-js-sdk` for browser-based calls.

**Authentication follows a two-tier model.** The API key (Bearer token) must remain server-side, while the browser receives short-lived access tokens that expire in 30 seconds if a call isn't initiated. This pattern requires a WordPress REST endpoint to proxy token requests:

```php
// WordPress REST endpoint for Retell access tokens
add_action('rest_api_init', function() {
    register_rest_route('voice-widget/v1', '/retell-token', [
        'methods' => 'POST',
        'callback' => 'generate_retell_token',
        'permission_callback' => function() { return is_user_logged_in(); }
    ]);
});

function generate_retell_token($request) {
    $response = wp_remote_post('https://api.retellai.com/v2/create-web-call', [
        'headers' => [
            'Authorization' => 'Bearer ' . get_option('retell_api_key'),
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode(['agent_id' => $request['agent_id']])
    ]);
    return json_decode(wp_remote_retrieve_body($response));
}
```

**The JavaScript SDK handles WebSocket connections automatically** through LiveKit infrastructure. Key client events include `call_started`, `call_ended`, `agent_start_talking`, `agent_stop_talking`, and `update` (for real-time transcripts). The SDK supports sample rates from **8kHz to 48kHz** and optional raw audio emission for visualizations:

```javascript
import { RetellWebClient } from 'retell-client-js-sdk';

const retellClient = new RetellWebClient();
await retellClient.startCall({
    accessToken: tokenFromServer,
    sampleRate: 24000,
    emitRawAudioSamples: true  // For waveform visualizations
});

retellClient.on('update', (update) => {
    console.log('Transcript:', update.transcript);  // Last 5 sentences
});
```

**Webhooks fire for three event types:** `call_started`, `call_ended`, and `call_analyzed` (with post-call analysis). Webhook payloads include full call metadata, transcripts, and disconnection reasons. Signature verification uses HMAC-SHA256 via the `x-retell-signature` header. Retell's webhook timeout is **10 seconds** with up to 3 automatic retries.

**Pricing starts at $0.07/min** for the voice engine plus LLM costs ($0.006-$0.50/min depending on model). The free tier includes 60 minutes and 20 concurrent calls. Retell supports 18-30+ languages and integrates ElevenLabs, Azure TTS, PlayHT, Cartesia, and OpenAI voices as backend providers.

## ElevenLabs Conversational AI orchestrates complete voice dialogues

Unlike ElevenLabs' standard TTS API (which converts text to audio one-way), their Conversational AI platform integrates **ASR, LLM processing, TTS, and turn-taking** into a unified bidirectional system. The architecture achieves **sub-100ms turnaround** through a proprietary turn-taking model that handles natural conversation flow.

**The authentication model supports both public and private agents.** Public agents can connect directly with just an agent ID, while private agents require server-generated signed URLs (for WebSocket) or conversation tokens (for WebRTC):

```javascript
// Public agent - direct connection
const conversation = await Conversation.startSession({
    agentId: 'your-agent-id',
    connectionType: 'webrtc'  // Better audio quality than websocket
});

// Private agent - server-generated token required
const response = await fetch('/wp-json/voice-widget/v1/elevenlabs-token');
const { signedUrl } = await response.json();
const conversation = await Conversation.startSession({
    signedUrl,
    connectionType: 'websocket'
});
```

**WebSocket communication uses specific message types.** Client-to-server messages include `user_audio_chunk` (base64-encoded PCM audio), `contextual_update` (non-interrupting context injection), and `pong` (keepalive). Server-to-client events include `user_transcript`, `agent_response`, `audio` (base64 chunks), and `interruption`. Audio must be **16-bit PCM** at supported sample rates (8kHz, 16kHz, 22.05kHz, 24kHz, 44.1kHz, or 48kHz).

**The SDK provides rich session control methods:** `setVolume()`, `getInputVolume()`, `getOutputVolume()`, `sendUserMessage()` (text instead of voice), `sendContextualUpdate()`, `setMicMuted()`, and device switching via `changeInputDevice()`/`changeOutputDevice()`.

**Pricing runs $0.08-0.10/minute** on paid plans, with a 95% discount for silence periods exceeding 10 seconds. LLM costs (Gemini, Claude, OpenAI) are passed through separately. ElevenLabs excels at voice quality with **5,000+ voices across 31 languages** and supports Professional Voice Cloning for custom brand voices.

## Key differences between Retell AI and ElevenLabs shape provider selection

| Capability | Retell AI | ElevenLabs |
|------------|-----------|------------|
| **Primary strength** | Enterprise telephony, compliance | Voice quality, developer flexibility |
| **Latency** | ~800ms end-to-end | Sub-100ms turnaround |
| **Base pricing** | $0.07/min | $0.08-0.10/min |
| **Native telephony** | Yes (phone, SIP, web) | Requires Twilio integration |
| **Compliance** | SOC2, HIPAA, GDPR | SOC2, GDPR |
| **Post-call analysis** | Built-in | No |
| **Knowledge base** | Auto-sync every 24 hours | Document upload, RAG |
| **SDK approach** | Separate server/client packages | Unified client SDK |

**Retell AI is better for** high-volume call centers, HIPAA-regulated use cases, and scenarios requiring warm transfer or native telephony. **ElevenLabs is better for** applications prioritizing voice quality, creative content, and simpler web-first integrations.

## n8n's MCP implementation enables AI tool orchestration

Model Context Protocol (MCP) is an open standard from Anthropic (November 2024) that provides a **standardized way for LLM applications to access external tools and data sources**. It uses JSON-RPC 2.0 format and exposes three primitives: Resources (read-only data), Tools (executable functions), and Prompts (reusable templates).

**n8n implements MCP through two nodes.** The MCP Server Trigger node makes n8n act as an MCP server, exposing workflows to external MCP clients via SSE or Streamable HTTP (not stdio). The MCP Client Tool node allows n8n workflows to connect to external MCP servers.

```json
// Claude Desktop configuration for n8n MCP
{
  "mcpServers": {
    "n8n": {
      "command": "npx",
      "args": [
        "mcp-remote",
        "https://your-n8n.com/mcp/production/path",
        "--header",
        "Authorization: Bearer ${AUTH_TOKEN}"
      ],
      "env": { "AUTH_TOKEN": "your-mcp-token" }
    }
  }
}
```

**For WordPress integration, webhooks are simpler than direct MCP.** The MCP protocol requires session management and JSON-RPC message formatting, while webhooks provide straightforward HTTP request/response patterns. Use webhooks for synchronous request-response flows and MCP when you need AI agents to discover and invoke tools dynamically:

```php
// WordPress calling n8n workflow via webhook
class N8N_Integration {
    public function call_workflow($data) {
        $response = wp_remote_post(get_option('n8n_webhook_url'), [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . get_option('n8n_auth_token')
            ],
            'body' => json_encode($data),
            'timeout' => 30
        ]);
        return json_decode(wp_remote_retrieve_body($response), true);
    }
}
```

**n8n webhook configuration requires attention to response modes.** "When Last Node Finishes" returns processed data synchronously. "Using Respond to Webhook Node" enables custom responses mid-workflow. For long-running AI operations, implement the HTTP 202 Accepted pattern with callback URLs or polling endpoints.

## Provider abstraction enables runtime voice platform switching

The recommended architecture uses a **Factory Pattern** to abstract provider-specific implementations behind a unified interface. This allows runtime provider selection, graceful fallbacks, and future provider additions without modifying existing code:

```javascript
// Provider interface and factory
class VoiceProviderInterface {
    async startCall(options) { throw new Error('Implement in subclass'); }
    async endCall() { throw new Error('Implement in subclass'); }
    on(event, callback) { throw new Error('Implement in subclass'); }
}

class VoiceProviderFactory {
    static providers = {
        'retell': RetellProvider,
        'elevenlabs': ElevenLabsProvider
    };
    
    static create(type, config) {
        const Provider = this.providers[type];
        if (!Provider) throw new Error(`Unknown provider: ${type}`);
        return new Provider(config);
    }
}
```

**Each provider implementation normalizes events to a common format.** Retell's `agent_start_talking` and ElevenLabs' `onModeChange` both emit as `agentSpeaking`. Retell's `update.transcript` and ElevenLabs' `onMessage` both emit as `transcript`. This normalization happens in provider subclasses:

```javascript
class RetellProvider extends VoiceProviderInterface {
    constructor(config) {
        super();
        this.client = new RetellWebClient();
        this.config = config;
        
        // Normalize events to common interface
        this.client.on('agent_start_talking', () => this.emit('agentSpeaking', true));
        this.client.on('agent_stop_talking', () => this.emit('agentSpeaking', false));
        this.client.on('update', (u) => this.emit('transcript', u.transcript));
    }
    
    async startCall(options) {
        const token = await this.fetchAccessToken(options.agentId);
        await this.client.startCall({ accessToken: token, sampleRate: 24000 });
    }
}
```

**WebSocket connection management requires exponential backoff with jitter.** Implement reconnection logic that prevents "thundering herd" problems during outages, buffers messages during disconnection, and maintains heartbeat monitoring:

```javascript
class WebSocketManager {
    handleReconnect() {
        if (this.attempts >= this.maxAttempts) {
            this.emit('maxRetriesReached');
            return;
        }
        
        // Exponential backoff: 2^attempt * baseDelay + random jitter
        const delay = Math.min(
            this.maxDelay,
            Math.pow(2, this.attempts) * 1000 + Math.random() * 1000
        );
        
        setTimeout(() => {
            this.attempts++;
            this.connect();
        }, delay);
    }
}
```

## Browser audio APIs require AudioWorklet for efficient streaming

Modern voice capture uses the **AudioWorklet API** (replacing the deprecated ScriptProcessorNode) for efficient, low-latency audio processing. The microphone stream feeds into an AudioWorklet processor that emits PCM chunks for WebSocket transmission:

```javascript
// audio-processor.js (AudioWorklet)
class AudioProcessor extends AudioWorkletProcessor {
    process(inputs, outputs, parameters) {
        const input = inputs[0];
        if (input.length > 0) {
            this.port.postMessage(input[0]);  // Send Float32Array
        }
        return true;  // Keep processor running
    }
}
registerProcessor('audio-processor', AudioProcessor);

// Main thread
const audioContext = new AudioContext({ sampleRate: 24000 });
await audioContext.audioWorklet.addModule('audio-processor.js');
const processor = new AudioWorkletNode(audioContext, 'audio-processor');
const source = audioContext.createMediaStreamSource(micStream);
source.connect(processor);

processor.port.onmessage = (event) => {
    const pcmData = floatTo16BitPCM(event.data);
    websocket.send(pcmData.buffer);
};
```

**Audio encoding for WebSocket transmission requires Float32 to Int16 conversion.** Both Retell and ElevenLabs expect 16-bit PCM audio, while the Web Audio API produces Float32 samples:

```javascript
function floatTo16BitPCM(float32Array) {
    const int16Array = new Int16Array(float32Array.length);
    for (let i = 0; i < float32Array.length; i++) {
        const s = Math.max(-1, Math.min(1, float32Array[i]));
        int16Array[i] = s < 0 ? s * 0x8000 : s * 0x7FFF;
    }
    return int16Array;
}
```

## WordPress API key security demands encryption at rest

**Never store API keys in plain text in wp_options.** Use AES-256-CBC encryption with WordPress's existing security salts as key material. This pattern mirrors Google Site Kit's approach to credential storage:

```php
class Voice_Widget_Encryption {
    private $method = 'aes-256-cbc';
    
    private function get_key() {
        if (defined('VOICE_WIDGET_ENCRYPTION_KEY')) {
            return VOICE_WIDGET_ENCRYPTION_KEY;
        }
        return defined('LOGGED_IN_KEY') ? LOGGED_IN_KEY : md5(get_site_url());
    }
    
    public function encrypt($value) {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->method));
        $encrypted = openssl_encrypt(
            $value,
            $this->method,
            hash('sha256', $this->get_key(), true),
            0,
            $iv
        );
        return base64_encode($iv . $encrypted);
    }
    
    public function decrypt($value) {
        $data = base64_decode($value);
        $iv_length = openssl_cipher_iv_length($this->method);
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        return openssl_decrypt(
            $encrypted,
            $this->method,
            hash('sha256', $this->get_key(), true),
            0,
            $iv
        );
    }
}
```

**Recommend users add custom encryption constants to wp-config.php** for defense in depth: `define('VOICE_WIDGET_ENCRYPTION_KEY', 'random-64-char-string');`

## Webhook architecture must accommodate multiple automation platforms

A flexible webhook system supporting n8n, Make, Zapier, and custom integrations requires **multiple authentication methods**, **idempotency handling**, and **async processing support**. The core WordPress REST endpoint should accept API key, HMAC signature, or Basic Auth:

```php
class Webhook_Handler {
    public function verify_request($request) {
        $auth_method = get_option('webhook_auth_method', 'api_key');
        
        switch ($auth_method) {
            case 'hmac':
                return $this->verify_hmac($request);
            case 'api_key':
                $key = $request->get_header('X-API-Key') 
                    ?: str_replace('Bearer ', '', $request->get_header('Authorization'));
                return hash_equals(get_option('webhook_api_key'), $key);
            case 'basic':
                return $this->verify_basic_auth($request);
        }
    }
    
    private function verify_hmac($request) {
        $signature = $request->get_header('X-Webhook-Signature')
            ?? $request->get_header('X-Hub-Signature-256');
        $expected = 'sha256=' . hash_hmac('sha256', $request->get_body(), get_option('webhook_secret'));
        return hash_equals($expected, $signature);
    }
}
```

**Implement idempotency using request IDs and transient caching.** Automation platforms may retry requests, and duplicate processing causes data integrity issues:

```php
public function handle_webhook($request) {
    $request_id = $request->get_header('X-Request-ID') 
        ?? $request->get_json_params()['request_id'] 
        ?? wp_generate_uuid4();
    
    if (get_transient('processed_' . $request_id)) {
        return ['success' => true, 'message' => 'Already processed'];
    }
    
    // Process webhook...
    set_transient('processed_' . $request_id, true, 86400);
}
```

**Long-running operations require the HTTP 202 Accepted pattern.** Return immediately with a job ID and status URL, then process asynchronously using `wp_schedule_single_event()`. Optionally call back to a provided callback URL when complete:

```php
public function async_handler($request) {
    $job_id = wp_generate_uuid4();
    
    wp_schedule_single_event(time(), 'process_async_job', [
        $job_id, 
        $request->get_json_params()
    ]);
    
    return new WP_REST_Response([
        'status' => 'accepted',
        'job_id' => $job_id,
        'status_url' => rest_url('voice-widget/v1/jobs/' . $job_id)
    ], 202);
}
```

## Rate limiting protects both your plugin and upstream APIs

Implement token bucket rate limiting using WordPress transients. This approach allows burst traffic while enforcing overall throughput limits:

```php
class Rate_Limiter {
    private $bucket_size = 100;
    private $refill_rate = 10;  // tokens per second
    
    public function consume($identifier) {
        $key = 'bucket_' . md5($identifier);
        $now = microtime(true);
        
        $bucket = get_transient($key) ?: [
            'tokens' => $this->bucket_size,
            'last_update' => $now
        ];
        
        $elapsed = $now - $bucket['last_update'];
        $new_tokens = min(
            $this->bucket_size,
            $bucket['tokens'] + ($elapsed * $this->refill_rate)
        );
        
        if ($new_tokens < 1) {
            return new WP_Error('rate_limited', 'Too many requests', [
                'status' => 429,
                'headers' => ['Retry-After' => ceil(1 / $this->refill_rate)]
            ]);
        }
        
        $bucket['tokens'] = $new_tokens - 1;
        $bucket['last_update'] = $now;
        set_transient($key, $bucket, 3600);
        return true;
    }
}
```

## Recommended plugin architecture layers the components correctly

The complete architecture separates concerns across four layers:

**1. Settings Layer** handles encrypted credential storage, provider selection, agent configuration, and webhook endpoint setup. Uses WordPress Settings API with sanitization callbacks that encrypt before storage.

**2. REST API Layer** provides token generation endpoints (`/voice-widget/v1/retell-token`, `/voice-widget/v1/elevenlabs-token`), webhook receivers (`/voice-widget/v1/webhook`), and async job status endpoints (`/voice-widget/v1/jobs/{id}`). All endpoints implement rate limiting and appropriate authentication.

**3. JavaScript Widget Layer** uses the provider factory to instantiate the correct voice SDK, manages WebSocket/WebRTC connections with reconnection logic, handles browser audio via AudioWorklet, and provides UI callbacks for transcript updates and speaking state.

**4. Automation Layer** processes incoming webhooks from n8n/Make/Zapier, handles async job queuing and callbacks, and integrates with the voice layer for AI-driven responses.

The key architectural principle: **API keys exist only in PHP memory during request processing**. They're encrypted at rest in wp_options, decrypted server-side for upstream API calls, and never transmitted to the browser. Short-lived access tokens (Retell) or signed URLs (ElevenLabs) are the only credentials the frontend receives.

## Conclusion

Building a WordPress multimodal chat plugin requires coordinating multiple real-time systems: voice provider SDKs for bidirectional audio streaming, webhook endpoints for automation platform integration, and secure credential management throughout. **Retell AI offers superior telephony integration and compliance features**, while **ElevenLabs provides best-in-class voice quality and simpler web-first integration**. The provider abstraction pattern enables supporting both with runtime switching.

The **n8n MCP integration is most practical via webhooks** rather than direct MCP protocol, unless you specifically need AI agents to discover tools dynamically. For the webhook layer, prioritize HMAC signature verification, idempotency handling, and the HTTP 202 pattern for operations exceeding a few seconds.

Critical implementation details to get right: encrypt API keys using AES-256-CBC with WordPress salts, use AudioWorklet (not ScriptProcessorNode) for audio capture, implement exponential backoff with jitter for WebSocket reconnection, and always use timing-safe comparison (`hash_equals()`) for signature verification.