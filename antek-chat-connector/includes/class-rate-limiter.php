<?php
/**
 * Rate Limiter Class
 *
 * Implements token bucket algorithm for rate limiting
 *
 * @package Antek_Chat_Connector
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rate Limiter class
 *
 * Provides token bucket-based rate limiting with configurable bucket size
 * and refill rate. Stores state in WordPress transients for persistence.
 *
 * @since 1.1.0
 */
class Antek_Chat_Rate_Limiter {

    /**
     * Bucket size (maximum tokens)
     *
     * @var int
     */
    private $bucket_size;

    /**
     * Refill rate (tokens per second)
     *
     * @var float
     */
    private $refill_rate;

    /**
     * Maximum delay before giving up (seconds)
     *
     * @var int
     */
    private $max_delay;

    /**
     * Constructor
     *
     * @param int   $bucket_size Maximum number of tokens in bucket.
     * @param float $refill_rate Number of tokens added per second.
     * @param int   $max_delay Maximum delay in seconds (for Retry-After calculation).
     * @since 1.1.0
     */
    public function __construct($bucket_size = 100, $refill_rate = 10.0, $max_delay = 3600) {
        $this->bucket_size = $bucket_size;
        $this->refill_rate = $refill_rate;
        $this->max_delay = $max_delay;
    }

    /**
     * Consume tokens from bucket
     *
     * Implements token bucket algorithm:
     * 1. Calculate elapsed time since last update
     * 2. Add tokens based on refill rate (capped at bucket size)
     * 3. Check if enough tokens available
     * 4. Deduct tokens if available
     * 5. Store updated bucket state
     *
     * @param string $identifier Unique identifier for rate limit (e.g., session_id + endpoint).
     * @param int    $tokens Number of tokens to consume (default 1).
     * @return bool|WP_Error True if allowed, WP_Error if rate limited
     * @since 1.1.0
     */
    public function consume($identifier, $tokens = 1) {
        $key = 'antek_chat_bucket_' . md5($identifier);
        $now = microtime(true);

        // Get current bucket state
        $bucket = get_transient($key);

        if ($bucket === false) {
            // Initialize new bucket
            $bucket = [
                'tokens' => (float) $this->bucket_size,
                'last_update' => $now,
            ];
        }

        // Calculate elapsed time and tokens to add
        $elapsed = $now - $bucket['last_update'];
        $tokens_to_add = $elapsed * $this->refill_rate;

        // Refill bucket (capped at bucket_size)
        $new_token_count = min(
            $this->bucket_size,
            $bucket['tokens'] + $tokens_to_add
        );

        // Check if enough tokens available
        if ($new_token_count < $tokens) {
            // Calculate retry-after time
            $tokens_needed = $tokens - $new_token_count;
            $retry_after = ceil($tokens_needed / $this->refill_rate);

            // Cap retry-after at max delay
            $retry_after = min($retry_after, $this->max_delay);

            return new WP_Error(
                'rate_limited',
                sprintf(
                    __('Rate limit exceeded. Please try again in %d seconds.', 'antek-chat-connector'),
                    $retry_after
                ),
                [
                    'status' => 429,
                    'headers' => [
                        'X-RateLimit-Limit' => $this->bucket_size,
                        'X-RateLimit-Remaining' => floor($new_token_count),
                        'Retry-After' => $retry_after,
                    ],
                ]
            );
        }

        // Consume tokens
        $bucket['tokens'] = $new_token_count - $tokens;
        $bucket['last_update'] = $now;

        // Store updated bucket state (expires in 1 hour)
        set_transient($key, $bucket, 3600);

        return true;
    }

    /**
     * Get current bucket state
     *
     * Returns information about tokens remaining and refill rate
     *
     * @param string $identifier Unique identifier for rate limit.
     * @return array Bucket state with tokens, last_update, refill_time
     * @since 1.1.0
     */
    public function get_bucket_state($identifier) {
        $key = 'antek_chat_bucket_' . md5($identifier);
        $now = microtime(true);

        $bucket = get_transient($key);

        if ($bucket === false) {
            return [
                'tokens' => $this->bucket_size,
                'bucket_size' => $this->bucket_size,
                'refill_rate' => $this->refill_rate,
                'last_update' => $now,
                'time_to_full' => 0,
            ];
        }

        // Calculate current tokens with refill
        $elapsed = $now - $bucket['last_update'];
        $tokens_to_add = $elapsed * $this->refill_rate;
        $current_tokens = min(
            $this->bucket_size,
            $bucket['tokens'] + $tokens_to_add
        );

        // Calculate time until bucket is full
        $tokens_until_full = $this->bucket_size - $current_tokens;
        $time_to_full = $tokens_until_full > 0
            ? $tokens_until_full / $this->refill_rate
            : 0;

        return [
            'tokens' => $current_tokens,
            'bucket_size' => $this->bucket_size,
            'refill_rate' => $this->refill_rate,
            'last_update' => $bucket['last_update'],
            'time_to_full' => $time_to_full,
        ];
    }

    /**
     * Reset bucket
     *
     * Resets bucket to full capacity
     * Useful for testing or administrative overrides
     *
     * @param string $identifier Unique identifier for rate limit.
     * @return bool True on success
     * @since 1.1.0
     */
    public function reset_bucket($identifier) {
        $key = 'antek_chat_bucket_' . md5($identifier);

        delete_transient($key);

        return true;
    }

    /**
     * Check if identifier is currently rate limited
     *
     * Non-consuming check (doesn't deduct tokens)
     *
     * @param string $identifier Unique identifier for rate limit.
     * @param int    $tokens Number of tokens needed.
     * @return bool True if rate limited, false if allowed
     * @since 1.1.0
     */
    public function is_rate_limited($identifier, $tokens = 1) {
        $state = $this->get_bucket_state($identifier);

        return $state['tokens'] < $tokens;
    }

    /**
     * Get rate limit headers
     *
     * Returns HTTP headers for rate limit status
     *
     * @param string $identifier Unique identifier for rate limit.
     * @return array HTTP headers
     * @since 1.1.0
     */
    public function get_rate_limit_headers($identifier) {
        $state = $this->get_bucket_state($identifier);

        return [
            'X-RateLimit-Limit' => $this->bucket_size,
            'X-RateLimit-Remaining' => floor($state['tokens']),
            'X-RateLimit-Reset' => time() + ceil($state['time_to_full']),
        ];
    }

    /**
     * Clean up old rate limit buckets
     *
     * Removes expired transients (called by WordPress cron)
     *
     * @return int Number of buckets cleaned up
     * @since 1.1.0
     */
    public function cleanup_expired_buckets() {
        global $wpdb;

        // Delete expired transients matching our pattern
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options}
                WHERE option_name LIKE %s
                AND option_name NOT LIKE %s",
                '_transient_antek_chat_bucket_%',
                '_transient_timeout_antek_chat_bucket_%'
            )
        );

        return $deleted;
    }

    /**
     * Get bucket size
     *
     * @return int Bucket size
     * @since 1.1.0
     */
    public function get_bucket_size() {
        return $this->bucket_size;
    }

    /**
     * Get refill rate
     *
     * @return float Refill rate (tokens per second)
     * @since 1.1.0
     */
    public function get_refill_rate() {
        return $this->refill_rate;
    }

    /**
     * Set bucket size
     *
     * @param int $size New bucket size.
     * @since 1.1.0
     */
    public function set_bucket_size($size) {
        $this->bucket_size = max(1, (int) $size);
    }

    /**
     * Set refill rate
     *
     * @param float $rate New refill rate (tokens per second).
     * @since 1.1.0
     */
    public function set_refill_rate($rate) {
        $this->refill_rate = max(0.1, (float) $rate);
    }

    /**
     * Create rate limiter with preset configuration
     *
     * Factory method for common rate limit scenarios
     *
     * @param string $preset Preset name (text_messages, voice_tokens, file_uploads, webhooks).
     * @return Antek_Chat_Rate_Limiter Rate limiter instance
     * @since 1.1.0
     */
    public static function create_from_preset($preset) {
        $presets = [
            'text_messages' => [
                'bucket_size' => 50,
                'refill_rate' => 50 / 3600, // 50 per hour
                'max_delay' => 3600,
            ],
            'voice_tokens' => [
                'bucket_size' => 10,
                'refill_rate' => 10 / 60, // 10 per minute
                'max_delay' => 300,
            ],
            'file_uploads' => [
                'bucket_size' => 10,
                'refill_rate' => 10 / 3600, // 10 per hour
                'max_delay' => 3600,
            ],
            'webhooks' => [
                'bucket_size' => 100,
                'refill_rate' => 10, // 10 per second (burst tolerant)
                'max_delay' => 60,
            ],
        ];

        if (!isset($presets[$preset])) {
            $preset = 'text_messages'; // Default fallback
        }

        $config = $presets[$preset];

        return new self(
            $config['bucket_size'],
            $config['refill_rate'],
            $config['max_delay']
        );
    }

    /**
     * Get preset configurations
     *
     * Returns array of available preset configurations
     *
     * @return array Preset configurations
     * @since 1.1.0
     */
    public static function get_presets() {
        return [
            'text_messages' => [
                'label' => __('Text Messages', 'antek-chat-connector'),
                'description' => __('50 messages per hour', 'antek-chat-connector'),
                'bucket_size' => 50,
                'refill_rate' => 50 / 3600,
            ],
            'voice_tokens' => [
                'label' => __('Voice Tokens', 'antek-chat-connector'),
                'description' => __('10 tokens per minute', 'antek-chat-connector'),
                'bucket_size' => 10,
                'refill_rate' => 10 / 60,
            ],
            'file_uploads' => [
                'label' => __('File Uploads', 'antek-chat-connector'),
                'description' => __('10 files per hour', 'antek-chat-connector'),
                'bucket_size' => 10,
                'refill_rate' => 10 / 3600,
            ],
            'webhooks' => [
                'label' => __('Webhooks', 'antek-chat-connector'),
                'description' => __('100 requests burst, 10/second sustained', 'antek-chat-connector'),
                'bucket_size' => 100,
                'refill_rate' => 10,
            ],
        ];
    }
}
