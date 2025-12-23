<?php
/**
 * Async Job Processor Class
 *
 * Handles asynchronous job queuing and processing
 *
 * @package Antek_Chat_Connector
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Async Job Processor class
 *
 * Provides background job processing with retry logic:
 * - Job types: transcribe, tts, process_media, webhook_callback
 * - Retry with exponential backoff
 * - HTTP 202 Accepted for long-running operations
 * - Job status polling
 * - Callback URL support
 *
 * @since 1.1.0
 */
class Antek_Chat_Async_Job_Processor {

    /**
     * Queue job
     *
     * Adds a new job to the queue
     *
     * @param string $job_type Job type (transcribe, tts, process_media, webhook_callback).
     * @param array  $data Input data for the job.
     * @param string $callback_url Optional callback URL for result delivery.
     * @param string $session_id Optional session ID.
     * @return string|WP_Error Job ID or error
     * @since 1.1.0
     */
    public function queue_job($job_type, $data, $callback_url = null, $session_id = null) {
        global $wpdb;

        // Validate job type
        $valid_types = ['transcribe', 'tts', 'process_media', 'webhook_callback'];
        if (!in_array($job_type, $valid_types, true)) {
            return new WP_Error(
                'invalid_job_type',
                sprintf(__('Invalid job type: %s', 'antek-chat-connector'), $job_type)
            );
        }

        // Generate job ID
        $job_id = wp_generate_uuid4();

        // Get jobs table
        $jobs_table = $wpdb->prefix . 'antek_chat_jobs';

        // Insert job record
        $result = $wpdb->insert(
            $jobs_table,
            [
                'job_id' => $job_id,
                'job_type' => $job_type,
                'status' => 'pending',
                'session_id' => $session_id,
                'user_id' => get_current_user_id(),
                'input_data' => wp_json_encode($data),
                'callback_url' => $callback_url,
                'retry_count' => 0,
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s']
        );

        if ($result === false) {
            return new WP_Error(
                'job_queue_failed',
                __('Failed to queue job', 'antek-chat-connector')
            );
        }

        // Schedule job processing
        $this->schedule_job_processing($job_id);

        $this->log('Job queued', 'info', ['job_id' => $job_id, 'type' => $job_type]);

        return $job_id;
    }

    /**
     * Process job
     *
     * Executes a queued job
     *
     * @param string $job_id Job ID to process.
     * @return array|WP_Error Job result or error
     * @since 1.1.0
     */
    public function process_job($job_id) {
        global $wpdb;

        $jobs_table = $wpdb->prefix . 'antek_chat_jobs';

        // Get job record
        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $jobs_table WHERE job_id = %s",
            $job_id
        ), ARRAY_A);

        if (!$job) {
            return new WP_Error(
                'job_not_found',
                __('Job not found', 'antek-chat-connector')
            );
        }

        // Check if already processed
        if (in_array($job['status'], ['completed', 'processing'], true)) {
            return new WP_Error(
                'job_already_processed',
                __('Job already processed or processing', 'antek-chat-connector')
            );
        }

        // Update status to processing
        $wpdb->update(
            $jobs_table,
            ['status' => 'processing'],
            ['job_id' => $job_id],
            ['%s'],
            ['%s']
        );

        $this->log('Processing job', 'info', ['job_id' => $job_id, 'type' => $job['job_type']]);

        // Decode input data
        $input_data = json_decode($job['input_data'], true);

        try {
            // Process based on job type
            $result = $this->execute_job($job['job_type'], $input_data, $job);

            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }

            // Update job as completed
            $wpdb->update(
                $jobs_table,
                [
                    'status' => 'completed',
                    'output_data' => wp_json_encode($result),
                    'completed_at' => current_time('mysql'),
                ],
                ['job_id' => $job_id],
                ['%s', '%s', '%s'],
                ['%s']
            );

            $this->log('Job completed', 'info', ['job_id' => $job_id]);

            // Trigger callback if configured
            if (!empty($job['callback_url'])) {
                $this->trigger_callback($job['callback_url'], $job_id, $result);
            }

            // Trigger WordPress action
            do_action('antek_chat_job_completed', $job_id, $result, $job);

            return $result;

        } catch (Exception $e) {
            $this->log('Job failed: ' . $e->getMessage(), 'error', ['job_id' => $job_id]);

            // Get max retries from settings
            $settings = get_option('antek_chat_advanced_settings', []);
            $max_retries = isset($settings['async_max_retries']) ? (int) $settings['async_max_retries'] : 3;

            $current_retry = (int) $job['retry_count'];

            if ($current_retry < $max_retries) {
                // Schedule retry with exponential backoff
                $this->schedule_retry($job_id, $current_retry + 1);

                // Update job status
                $wpdb->update(
                    $jobs_table,
                    [
                        'status' => 'pending',
                        'retry_count' => $current_retry + 1,
                        'error_message' => $e->getMessage(),
                    ],
                    ['job_id' => $job_id],
                    ['%s', '%d', '%s'],
                    ['%s']
                );
            } else {
                // Max retries exceeded - mark as failed
                $wpdb->update(
                    $jobs_table,
                    [
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                        'completed_at' => current_time('mysql'),
                    ],
                    ['job_id' => $job_id],
                    ['%s', '%s', '%s'],
                    ['%s']
                );

                // Trigger WordPress action
                do_action('antek_chat_job_failed', $job_id, $e->getMessage(), $job);
            }

            return new WP_Error(
                'job_processing_failed',
                $e->getMessage()
            );
        }
    }

    /**
     * Execute job based on type
     *
     * @param string $job_type Job type.
     * @param array  $input_data Input data.
     * @param array  $job Full job record.
     * @return array|WP_Error Result or error
     * @since 1.1.0
     */
    private function execute_job($job_type, $input_data, $job) {
        // Allow plugins to handle custom job types
        $result = apply_filters('antek_chat_execute_job', null, $job_type, $input_data, $job);

        if ($result !== null) {
            return $result;
        }

        // Built-in job type handlers
        switch ($job_type) {
            case 'webhook_callback':
                return $this->process_webhook_callback($input_data);

            case 'transcribe':
                return $this->process_transcription($input_data);

            case 'tts':
                return $this->process_text_to_speech($input_data);

            case 'process_media':
                return $this->process_media($input_data);

            default:
                return new WP_Error(
                    'unsupported_job_type',
                    sprintf(__('Job type not implemented: %s', 'antek-chat-connector'), $job_type)
                );
        }
    }

    /**
     * Process webhook callback
     *
     * @param array $data Input data.
     * @return array Result
     * @since 1.1.0
     */
    private function process_webhook_callback($data) {
        if (empty($data['url'])) {
            throw new Exception('Callback URL missing');
        }

        $url = esc_url_raw($data['url']);
        $payload = isset($data['payload']) ? $data['payload'] : [];

        $settings = get_option('antek_chat_advanced_settings', []);
        $timeout = isset($settings['async_callback_timeout']) ? (int) $settings['async_callback_timeout'] : 30;

        $response = wp_remote_post($url, [
            'timeout' => $timeout,
            'body' => wp_json_encode($payload),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code < 200 || $status_code >= 300) {
            throw new Exception(sprintf('Callback failed with status %d', $status_code));
        }

        return [
            'status' => 'callback_sent',
            'status_code' => $status_code,
            'timestamp' => time(),
        ];
    }

    /**
     * Process transcription job
     *
     * @param array $data Input data.
     * @return array Result
     * @since 1.1.0
     */
    private function process_transcription($data) {
        // Placeholder for transcription processing
        // This would integrate with speech-to-text service
        return apply_filters('antek_chat_process_transcription', [
            'text' => '',
            'confidence' => 0,
        ], $data);
    }

    /**
     * Process text-to-speech job
     *
     * @param array $data Input data.
     * @return array Result
     * @since 1.1.0
     */
    private function process_text_to_speech($data) {
        // Placeholder for TTS processing
        // This would integrate with text-to-speech service
        return apply_filters('antek_chat_process_tts', [
            'audio_url' => '',
            'duration' => 0,
        ], $data);
    }

    /**
     * Process media job
     *
     * @param array $data Input data.
     * @return array Result
     * @since 1.1.0
     */
    private function process_media($data) {
        // Placeholder for media processing
        // This would handle image resizing, video transcoding, etc.
        return apply_filters('antek_chat_process_media', [
            'media_url' => '',
            'processed' => true,
        ], $data);
    }

    /**
     * Get job status
     *
     * @param string $job_id Job ID.
     * @return array|WP_Error Job status or error
     * @since 1.1.0
     */
    public function get_job_status($job_id) {
        global $wpdb;

        $jobs_table = $wpdb->prefix . 'antek_chat_jobs';

        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT job_id, job_type, status, output_data, error_message, retry_count, created_at, completed_at
             FROM $jobs_table WHERE job_id = %s",
            $job_id
        ), ARRAY_A);

        if (!$job) {
            return new WP_Error(
                'job_not_found',
                __('Job not found', 'antek-chat-connector')
            );
        }

        // Parse output data if completed
        if ($job['status'] === 'completed' && !empty($job['output_data'])) {
            $job['output_data'] = json_decode($job['output_data'], true);
        } else {
            unset($job['output_data']);
        }

        return $job;
    }

    /**
     * Trigger callback
     *
     * Sends job result to callback URL
     *
     * @param string $callback_url Callback URL.
     * @param string $job_id Job ID.
     * @param array  $result Job result.
     * @return bool True if successful
     * @since 1.1.0
     */
    private function trigger_callback($callback_url, $job_id, $result) {
        $payload = [
            'job_id' => $job_id,
            'status' => 'completed',
            'result' => $result,
            'timestamp' => time(),
        ];

        $settings = get_option('antek_chat_advanced_settings', []);
        $timeout = isset($settings['async_callback_timeout']) ? (int) $settings['async_callback_timeout'] : 30;

        $response = wp_remote_post($callback_url, [
            'timeout' => $timeout,
            'body' => wp_json_encode($payload),
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Antek-Chat-Signature' => $this->generate_callback_signature($payload),
            ],
            'blocking' => false, // Don't wait for response
        ]);

        if (is_wp_error($response)) {
            $this->log('Callback failed: ' . $response->get_error_message(), 'error', ['job_id' => $job_id]);
            return false;
        }

        return true;
    }

    /**
     * Generate callback signature
     *
     * @param array $payload Callback payload.
     * @return string HMAC signature
     * @since 1.1.0
     */
    private function generate_callback_signature($payload) {
        $secret = defined('ANTEK_CHAT_CALLBACK_SECRET')
            ? ANTEK_CHAT_CALLBACK_SECRET
            : wp_salt('nonce');

        return hash_hmac('sha256', wp_json_encode($payload), $secret);
    }

    /**
     * Schedule job processing
     *
     * @param string $job_id Job ID.
     * @since 1.1.0
     */
    private function schedule_job_processing($job_id) {
        // Use WordPress cron to process job immediately
        wp_schedule_single_event(time(), 'antek_chat_process_job', [$job_id]);
    }

    /**
     * Schedule retry
     *
     * @param string $job_id Job ID.
     * @param int    $retry_count Current retry count.
     * @since 1.1.0
     */
    private function schedule_retry($job_id, $retry_count) {
        // Exponential backoff: 2^retry * 60 seconds
        $delay = pow(2, $retry_count) * 60;

        wp_schedule_single_event(time() + $delay, 'antek_chat_process_job', [$job_id]);

        $this->log('Retry scheduled', 'info', [
            'job_id' => $job_id,
            'retry' => $retry_count,
            'delay' => $delay,
        ]);
    }

    /**
     * Cleanup old jobs
     *
     * Removes completed/failed jobs older than specified days
     *
     * @param int $days Number of days to keep (default 7).
     * @return int Number of jobs deleted
     * @since 1.1.0
     */
    public function cleanup_old_jobs($days = 7) {
        global $wpdb;

        $jobs_table = $wpdb->prefix . 'antek_chat_jobs';
        $date = date('Y-m-d H:i:s', strtotime("-$days days"));

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $jobs_table
             WHERE (status = 'completed' OR status = 'failed')
             AND completed_at < %s",
            $date
        ));

        $this->log('Old jobs cleaned up', 'info', ['count' => $deleted]);

        return $deleted;
    }

    /**
     * Log message
     *
     * @param string $message Log message.
     * @param string $level Log level.
     * @param array  $context Additional context.
     * @since 1.1.0
     */
    private function log($message, $level = 'info', $context = []) {
        if (!WP_DEBUG) {
            return;
        }

        $log_message = sprintf(
            '[Antek Chat][Async Jobs][%s] %s',
            strtoupper($level),
            $message
        );

        if (!empty($context)) {
            $log_message .= ' | ' . wp_json_encode($context);
        }

        error_log($log_message);
    }
}
