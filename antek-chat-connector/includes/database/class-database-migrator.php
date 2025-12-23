<?php
/**
 * Database Migrator Class
 *
 * Handles database schema migrations for multimodal support
 *
 * @package Antek_Chat_Connector
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database Migrator class
 *
 * Manages database schema updates with version tracking
 *
 * @since 1.1.0
 */
class Antek_Chat_Database_Migrator {

    /**
     * Current database version
     *
     * @var string
     */
    const DB_VERSION = '1.1.0';

    /**
     * Run all pending migrations
     *
     * @return bool|WP_Error True on success, WP_Error on failure
     * @since 1.1.0
     */
    public function run_migrations() {
        $current_version = get_option('antek_chat_db_version', '1.0.0');

        // If already at latest version, skip
        if (version_compare($current_version, self::DB_VERSION, '>=')) {
            return true;
        }

        // Run migrations in order
        if (version_compare($current_version, '1.1.0', '<')) {
            $result = $this->migrate_to_1_1_0();
            if (is_wp_error($result)) {
                return $result;
            }
        }

        // Update version
        update_option('antek_chat_db_version', self::DB_VERSION);

        return true;
    }

    /**
     * Migration to version 1.1.0
     *
     * Adds multimodal support:
     * - Extends sessions table with provider and encryption_key_version columns
     * - Creates media table for file uploads
     * - Creates jobs table for async processing
     * - Creates webhooks table for event logging
     *
     * @return bool|WP_Error True on success, WP_Error on failure
     * @since 1.1.0
     */
    private function migrate_to_1_1_0() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Step 1: Extend sessions table
        $sessions_table = $wpdb->prefix . 'antek_chat_sessions';

        // Check if columns already exist
        $provider_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW COLUMNS FROM `{$sessions_table}` LIKE %s",
                'provider'
            )
        );

        if (empty($provider_exists)) {
            $wpdb->query(
                "ALTER TABLE `{$sessions_table}`
                ADD COLUMN `provider` VARCHAR(32) DEFAULT 'elevenlabs' AFTER `conversation_data`,
                ADD COLUMN `encryption_key_version` INT DEFAULT 1 AFTER `provider`,
                ADD INDEX `idx_provider` (`provider`)"
            );

            if ($wpdb->last_error) {
                return new WP_Error(
                    'migration_sessions_failed',
                    sprintf(
                        __('Failed to update sessions table: %s', 'antek-chat-connector'),
                        $wpdb->last_error
                    )
                );
            }
        }

        // Step 2: Create media table
        $media_table = $wpdb->prefix . 'antek_chat_media';

        $media_sql = "CREATE TABLE IF NOT EXISTS `{$media_table}` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `session_id` VARCHAR(64) NOT NULL,
            `message_id` VARCHAR(64) NULL,
            `file_type` ENUM('image', 'audio', 'document', 'video') NOT NULL,
            `original_filename` VARCHAR(255) NULL,
            `stored_filename` VARCHAR(255) UNIQUE NOT NULL,
            `file_size` BIGINT NULL,
            `mime_type` VARCHAR(100) NULL,
            `upload_path` TEXT NULL,
            `metadata` JSON NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_session_id` (`session_id`),
            INDEX `idx_created_at` (`created_at`),
            INDEX `idx_file_type` (`file_type`)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($media_sql);

        if ($wpdb->last_error) {
            return new WP_Error(
                'migration_media_failed',
                sprintf(
                    __('Failed to create media table: %s', 'antek-chat-connector'),
                    $wpdb->last_error
                )
            );
        }

        // Step 3: Create jobs table
        $jobs_table = $wpdb->prefix . 'antek_chat_jobs';

        $jobs_sql = "CREATE TABLE IF NOT EXISTS `{$jobs_table}` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `job_id` VARCHAR(64) UNIQUE NOT NULL,
            `job_type` ENUM('transcribe', 'tts', 'process_media', 'webhook_callback') NOT NULL,
            `status` ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
            `session_id` VARCHAR(64) NULL,
            `user_id` BIGINT UNSIGNED NULL,
            `input_data` JSON NULL,
            `output_data` JSON NULL,
            `error_message` TEXT NULL,
            `retry_count` INT DEFAULT 0,
            `max_retries` INT DEFAULT 3,
            `callback_url` VARCHAR(2048) NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `started_at` DATETIME NULL,
            `completed_at` DATETIME NULL,
            INDEX `idx_job_id` (`job_id`),
            INDEX `idx_status` (`status`),
            INDEX `idx_session_id` (`session_id`),
            INDEX `idx_created_at` (`created_at`)
        ) {$charset_collate};";

        dbDelta($jobs_sql);

        if ($wpdb->last_error) {
            return new WP_Error(
                'migration_jobs_failed',
                sprintf(
                    __('Failed to create jobs table: %s', 'antek-chat-connector'),
                    $wpdb->last_error
                )
            );
        }

        // Step 4: Create webhooks table
        $webhooks_table = $wpdb->prefix . 'antek_chat_webhooks';

        $webhooks_sql = "CREATE TABLE IF NOT EXISTS `{$webhooks_table}` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `request_id` VARCHAR(64) UNIQUE NOT NULL,
            `provider` VARCHAR(32) NULL,
            `event_type` VARCHAR(100) NULL,
            `payload` JSON NULL,
            `auth_method` VARCHAR(32) NULL COMMENT 'api_key|hmac|basic|none',
            `verified` BOOLEAN DEFAULT false,
            `processed` BOOLEAN DEFAULT false,
            `response_status` INT NULL,
            `error_message` TEXT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `processed_at` DATETIME NULL,
            INDEX `idx_request_id` (`request_id`),
            INDEX `idx_event_type` (`event_type`),
            INDEX `idx_provider` (`provider`),
            INDEX `idx_created_at` (`created_at`)
        ) {$charset_collate};";

        dbDelta($webhooks_sql);

        if ($wpdb->last_error) {
            return new WP_Error(
                'migration_webhooks_failed',
                sprintf(
                    __('Failed to create webhooks table: %s', 'antek-chat-connector'),
                    $wpdb->last_error
                )
            );
        }

        return true;
    }

    /**
     * Rollback migration to version 1.0.0
     *
     * Removes multimodal tables and columns
     * WARNING: This will delete all media, jobs, and webhook logs!
     *
     * @return bool|WP_Error True on success, WP_Error on failure
     * @since 1.1.0
     */
    public function rollback_to_1_0_0() {
        global $wpdb;

        // Drop new tables
        $media_table = $wpdb->prefix . 'antek_chat_media';
        $jobs_table = $wpdb->prefix . 'antek_chat_jobs';
        $webhooks_table = $wpdb->prefix . 'antek_chat_webhooks';

        $wpdb->query("DROP TABLE IF EXISTS `{$media_table}`");
        $wpdb->query("DROP TABLE IF EXISTS `{$jobs_table}`");
        $wpdb->query("DROP TABLE IF EXISTS `{$webhooks_table}`");

        // Remove columns from sessions table
        $sessions_table = $wpdb->prefix . 'antek_chat_sessions';

        $wpdb->query(
            "ALTER TABLE `{$sessions_table}`
            DROP COLUMN IF EXISTS `provider`,
            DROP COLUMN IF EXISTS `encryption_key_version`,
            DROP INDEX IF EXISTS `idx_provider`"
        );

        // Update version
        update_option('antek_chat_db_version', '1.0.0');

        return true;
    }

    /**
     * Check database health
     *
     * Verifies all required tables and columns exist
     *
     * @return array Health check results
     * @since 1.1.0
     */
    public function check_database_health() {
        global $wpdb;

        $health = [
            'status' => 'healthy',
            'tables' => [],
            'issues' => [],
        ];

        // Check sessions table
        $sessions_table = $wpdb->prefix . 'antek_chat_sessions';
        $sessions_exists = $wpdb->get_var("SHOW TABLES LIKE '{$sessions_table}'") === $sessions_table;

        $health['tables']['sessions'] = [
            'exists' => $sessions_exists,
            'columns' => [],
        ];

        if ($sessions_exists) {
            $provider_column = $wpdb->get_results(
                $wpdb->prepare(
                    "SHOW COLUMNS FROM `{$sessions_table}` LIKE %s",
                    'provider'
                )
            );
            $health['tables']['sessions']['columns']['provider'] = !empty($provider_column);

            if (empty($provider_column)) {
                $health['issues'][] = 'Sessions table missing provider column';
                $health['status'] = 'degraded';
            }
        } else {
            $health['issues'][] = 'Sessions table does not exist';
            $health['status'] = 'critical';
        }

        // Check media table
        $media_table = $wpdb->prefix . 'antek_chat_media';
        $media_exists = $wpdb->get_var("SHOW TABLES LIKE '{$media_table}'") === $media_table;
        $health['tables']['media'] = ['exists' => $media_exists];

        if (!$media_exists) {
            $health['issues'][] = 'Media table does not exist';
            $health['status'] = 'degraded';
        }

        // Check jobs table
        $jobs_table = $wpdb->prefix . 'antek_chat_jobs';
        $jobs_exists = $wpdb->get_var("SHOW TABLES LIKE '{$jobs_table}'") === $jobs_table;
        $health['tables']['jobs'] = ['exists' => $jobs_exists];

        if (!$jobs_exists) {
            $health['issues'][] = 'Jobs table does not exist';
            $health['status'] = 'degraded';
        }

        // Check webhooks table
        $webhooks_table = $wpdb->prefix . 'antek_chat_webhooks';
        $webhooks_exists = $wpdb->get_var("SHOW TABLES LIKE '{$webhooks_table}'") === $webhooks_table;
        $health['tables']['webhooks'] = ['exists' => $webhooks_exists];

        if (!$webhooks_exists) {
            $health['issues'][] = 'Webhooks table does not exist';
            $health['status'] = 'degraded';
        }

        return $health;
    }

    /**
     * Get database version
     *
     * @return string Current database version
     * @since 1.1.0
     */
    public function get_current_version() {
        return get_option('antek_chat_db_version', '1.0.0');
    }

    /**
     * Get target database version
     *
     * @return string Target database version
     * @since 1.1.0
     */
    public function get_target_version() {
        return self::DB_VERSION;
    }

    /**
     * Check if migration is needed
     *
     * @return bool True if migration needed, false otherwise
     * @since 1.1.0
     */
    public function needs_migration() {
        $current = $this->get_current_version();
        $target = $this->get_target_version();

        return version_compare($current, $target, '<');
    }
}
