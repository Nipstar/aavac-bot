# n8n Integration Debugging Script

## Location
`debug-n8n.php` - This file is separate from the plugin ZIP and used for troubleshooting.

## Purpose
Comprehensive diagnostic tool for testing n8n integration with the AAVAC Bot plugin.

## How to Use

### Method 1: WP-CLI (Recommended)
```bash
# Copy debug-n8n.php to your WordPress root directory
cp debug-n8n.php /path/to/wordpress/

# Run the script
cd /path/to/wordpress
wp eval-file debug-n8n.php
```

### Method 2: Copy to Plugin Directory Temporarily
```bash
# Copy to plugin includes folder
cp debug-n8n.php /path/to/wordpress/wp-content/plugins/antek-chat-connector/includes/

# Create a temporary WordPress page that loads it
# Or run via custom admin script
```

### Method 3: Direct Execution (Advanced)
```bash
# Set WordPress path and run
cd /path/to/wordpress
php -d "ABSPATH=$(pwd)/" debug-n8n.php
```

## What It Tests

1. **Settings Configuration** - Validates all n8n settings
2. **Endpoint Connectivity** - Tests network access to n8n webhooks
3. **Voice Token Generation** - End-to-end test of voice call creation
4. **Session Creation** - Tests text chat session initialization
5. **Message Sending** - Tests text message delivery
6. **Response Validation** - Shows expected JSON formats

## Requirements

- WordPress installation with AAVAC Bot plugin v1.2.0+ installed
- n8n provider selected in Voice Provider Settings
- n8n base URL and endpoints configured
- WP-CLI (for easiest execution) or WordPress admin access

## Output

The script provides:
- ✓/✗ Pass/fail indicators for each test
- Actual vs Expected values
- Detailed error messages
- Common troubleshooting steps
- Summary of all failures

## Common Issues Detected

- Missing or invalid n8n URLs
- n8n workflows not activated
- Wrong Retell API endpoint URLs (missing /v2/ prefix)
- Missing "success: true" in n8n responses
- Network connectivity problems
- Incorrect agent IDs

## After Running

1. Check output for specific failures
2. Review WordPress debug.log for detailed errors
3. Check n8n workflow execution logs
4. Fix identified issues and re-run

## Note

This script is safe to run - it only performs test API calls with debug data. It does not modify any production settings or data.
