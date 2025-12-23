# Changelog

All notable changes to Antek Chat Connector will be documented in this file.

## [1.1.19] - 2025-12-22

### Changed
- **Temporarily disabled voice SDK loading** due to complex dependency issues
- Text chat via Retell Chat API works perfectly (v1.1.11 fix)
- Voice button will show "SDK not available" until proper bundling implemented

### Note
Voice calls require the Retell SDK which has complex dependencies (eventemitter3, livekit-client).
Browser loading via CDN has proven unreliable. Future version will include pre-bundled SDK.

**Current Status:**
- ✅ Text Chat: Fully working via Retell Chat API
- ⏸️ Voice Calls: Disabled until SDK can be properly bundled

## [1.1.16-1.1.18] - 2025-12-22

### Attempted
Multiple approaches to load Retell SDK in browser:
- UMD bundles with manual dependency loading (failed - name mismatches)
- ES modules with import maps (broke widget loading)
- Skypack CDN (broke widget loading due to WordPress hook timing)

All attempts failed due to SDK's complex dependency structure not designed for browser CDN loading.

## [1.1.16] - 2025-12-22

### Fixed
- **CRITICAL**: Switched to ES modules for Retell SDK loading
- Fixed UMD dependency name mismatch (EventEmitter3 vs eventemitter3)
- SDK now loads via import maps with proper dependency resolution
- Provider now accesses window.RetellWebClient directly

### Changed
- Use JSDelivr ESM (+esm) for automatic dependency bundling
- Import maps handle eventemitter3 and livekit-client dependencies
- ES module exports RetellWebClient to window.RetellWebClient
- Removed problematic UMD bundle loading

## [1.1.15] - 2025-12-22

### Fixed
- **CRITICAL**: Added missing Retell SDK dependencies (eventemitter3 and livekit-client)
- SDK dependencies now load in proper order: EventEmitter3 → LiveKit → Retell SDK
- UMD bundle now has access to required global dependencies

### Changed
- Load eventemitter3@5.0.1 before Retell SDK
- Load livekit-client@2.5.1 before Retell SDK
- Proper dependency chain ensures SDK initializes correctly

## [1.1.14] - 2025-12-22

### Fixed
- **CRITICAL**: Fixed Retell SDK not loading - version 2.3.0 doesn't exist!
- Updated SDK to correct version 2.0.7 with proper file path (index.umd.js)
- Fixed SDK namespace - UMD exports to window.retellClientJsSdk.RetellWebClient
- Provider now correctly accesses RetellWebClient from UMD namespace

### Changed
- SDK URL: cdn.jsdelivr.net/npm/retell-client-js-sdk@2.0.7/dist/index.umd.js
- Provider accesses SDK via window.retellClientJsSdk.RetellWebClient

## [1.1.13] - 2025-12-22

### Fixed
- **CRITICAL**: Retell SDK now loads in footer with proper dependency chain
- Added comprehensive error logging to retell-provider.js for debugging
- SDK availability check now uses window.RetellWebClient explicitly
- Fixed script loading order: all voice scripts now load sequentially in footer

### Changed
- Retell SDK moved from HEAD to footer loading for better timing
- All voice scripts now depend on jQuery and load in proper sequence
- Added detailed console logging for SDK availability and call flow

## [1.1.12] - 2025-12-22

### Fixed
- **CRITICAL**: Voice calls now work - added missing retell-provider.js script enqueue
- Fixed script loading order: SDK → Factory → Retell Provider → Widget
- Retell provider now properly registers with factory

### Changed
- Widget renderer now enqueues retell-provider.js with proper dependencies

## [1.1.11] - 2025-12-22

### Fixed
- **CRITICAL**: Retell text chat now properly creates chat sessions via `/create-chat` endpoint
- Fixed REST API to delegate to Retell provider instead of broken direct implementation
- Fixed incorrect use of WordPress session_id as Retell chat_id (was causing all text chat to fail)
- Proper HTTP error codes now returned (400/500) instead of always 200
- Chat sessions now properly reused across messages via transient storage (24-hour TTL)

### Changed
- `send_chat_message()` now delegates to Retell provider when `use_retell_chat` is enabled
- Removed duplicate/broken implementation from REST controller
- Text chat now uses correct Retell API endpoints (no /v2/ prefix for chat endpoints)

### Improved
- Better error handling with proper HTTP status codes for debugging
- More detailed error logging throughout chat flow
- Configuration validation before attempting Retell API calls
- Single source of truth (provider class) eliminates conflicting implementations

## [1.1.10] - 2025-12-22

### Added
- **Retell Chat API Support** for text messages
- "Use Retell for Text Chat" checkbox setting in Voice Provider settings
- Dual-mode text chat: Retell API or webhook (user configurable)
- Automatic API key decryption for Retell Chat requests

### Changed
- `send_chat_message()` now checks `use_retell_chat` setting
- When enabled, text messages sent to Retell Chat API instead of webhook
- Maintains backward compatibility with webhook approach

### Improved
- Better integration with Retell Chat Agent for text conversations
- Comprehensive error logging for both Retell API and webhook modes
- Graceful fallback messages when APIs fail

## [1.1.9] - 2025-12-22

### Fixed
- **Critical:** Custom colors being overridden by theme CSS
- Added `!important` rules to force custom colors
- High specificity CSS selectors to beat theme styles
- Color detection only runs when explicitly set to elementor/divi

### Added
- microphone.svg icon in public/images folder
- `hex_to_rgb()` helper function for box-shadow colors
- Comprehensive color override CSS for all widget elements

### Improved
- Custom colors now always respected regardless of theme
- Better separation between custom and auto-detected colors
- More specific CSS selectors for theme compatibility

## [1.1.8] - 2025-12-22

### Fixed
- **Critical:** Text chat message endpoint crashing with 500 error
- Fixed REST API `/message` endpoint method errors
- Fixed wrong method names (`get_conversation_history` → `get_conversation`)
- Simplified asset loading with better error handling
- Retell SDK now loads in HEAD (not footer) ensuring availability

### Improved
- Message endpoint returns friendly defaults when webhook not configured
- Comprehensive error logging for debugging
- Always returns valid response (no 500 errors to user)
- Simplified JavaScript config passing
- Removed complex multimodal conditional logic

## [1.1.7] - 2025-12-22

### Fixed
- **Critical:** Plugin crash when theme color detection disabled or fails
- Color detection now wrapped in try-catch blocks with fallback colors
- Graceful degradation when Elementor/Divi colors unavailable

### Improved
- Widget renderer always provides fallback colors (orange/green theme)
- Better error logging for color detection failures
- Plugin loads successfully regardless of color source setting

## [1.1.6] - 2025-12-22

### Fixed
- Retell Web SDK loading from CDN before provider code
- Script dependency order: SDK → Factory → Provider → Widget
- "RetellWebClient is not defined" error resolved

### Added
- Explicit script dependencies in WordPress enqueue system
- Using specific Retell SDK version (2.3.0) instead of @latest
- Error logging for script enqueue process

### Improved
- Voice calls now initialize correctly with SDK loaded first
- More reliable script loading order via WordPress dependencies

## [1.1.5] - 2025-12-22

### Fixed
- Enhanced voice provider validation with detailed logging to diagnose cache issues
- Aggressive cache-busting for development environments via WP_DEBUG detection
- File integrity verification system with version markers

### Added
- File version markers for debugging (visible in browser console)
- WP_DEBUG-aware script versioning with timestamp cache-busting
- Comprehensive server-side logging in voice provider endpoint
- Cache-control headers on voice provider API requests

### Improved
- Error messages more specific and actionable for troubleshooting
- Step-by-step validation logging in getEnabledProvider()
- AgentId validation with explicit error messages
- Version visibility in all console logs and API responses

## [1.0.0] - 2024-12-16

### Added
- Initial release
- Chat widget with neo-brutalist design
- n8n webhook integration
- ElevenLabs voice chat support
- Customizable appearance (colors, position, size)
- Custom CSS support
- Smart popup system (time, scroll, exit intent)
- Session management with conversation history
- Rate limiting (50 messages per hour)
- Security features (nonce verification, input sanitization)
- Shortcode support: `[antek_chat]`
- Template tag support: `antek_chat_widget()`
- Responsive mobile design
- Accessibility features (ARIA labels)
- WordPress coding standards compliance
- Admin settings interface with 4 tabs
- Test webhook functionality
- Color picker for easy customization
- Multiple widget positions (4 corners)
- Three size options (small, medium, large)
- Conversation persistence via localStorage
- Database table for session storage
- WordPress transients for rate limiting
- Auto-cleanup capability for old sessions

### Features

#### Core Functionality
- Real-time chat messaging
- AJAX-powered communication
- Session-based conversation tracking
- Message history persistence

#### Customization
- Full color customization
- Widget positioning options
- Size variants
- Border radius control
- Font family selection
- Custom CSS injection

#### Voice Integration
- WebSocket connection to ElevenLabs
- Real-time voice streaming
- Audio playback
- Microphone permission handling
- Visual recording indicator

#### Popup System
- Time-based triggers
- Scroll percentage triggers
- Exit intent detection
- Frequency controls (once, session, always)
- Custom promotional messages
- Storage-based tracking

#### Security
- Input sanitization
- Output escaping
- SQL injection prevention
- XSS protection
- Rate limiting
- Nonce verification
- HTTPS requirement for voice

#### Developer Features
- WordPress coding standards
- PHPDoc documentation
- Clean class structure
- Extensible architecture
- Hook support
- Template override capability

### Technical Details
- Minimum WordPress: 5.0
- Minimum PHP: 7.4
- Database table: `wp_antek_chat_sessions`
- CSS custom properties for theming
- jQuery dependency
- WordPress Color Picker integration
- WebSocket support for voice
- LocalStorage for conversation history
- SessionStorage for popup tracking

### Known Limitations
- Voice chat requires HTTPS
- Rate limit is per-session (not per-user)
- Popup page targeting is basic (all pages only)
- No built-in analytics dashboard
- No multi-language support yet

### Roadmap (Future Versions)
- [ ] Analytics dashboard
- [ ] Multi-language support (i18n)
- [ ] Advanced page targeting for popups
- [ ] Chat transcripts export
- [ ] Email notifications
- [ ] Canned responses
- [ ] User typing indicators
- [ ] File upload support
- [ ] Emoji picker
- [ ] Dark mode support
- [ ] RTL language support
- [ ] Gutenberg block
- [ ] REST API endpoints
- [ ] Webhooks for events
- [ ] Integration with popular form plugins

## [Unreleased]

### Planned
- Performance optimizations
- Enhanced mobile UX
- Additional trigger types
- More voice providers
- Admin analytics

---

## Version History

- **1.0.0**: Initial release with full feature set

## Upgrade Notes

### From 0.x to 1.0.0
N/A - Initial release

## Contributors

- Antek Automation - Initial development and design

## License

GPL v2 or later
