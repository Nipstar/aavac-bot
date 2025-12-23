<?php
/**
 * Debug Logger Class
 *
 * Handles debug logging for troubleshooting
 *
 * @package Antek_Chat_Connector
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Debug Logger class
 */
class Antek_Chat_Debug_Logger {

    /**
     * Log table name
     *
     * @var string
     */
    private static $table_name = 'antek_chat_debug_logs';

    /**
     * Maximum number of logs to keep
     *
     * @var int
     */
    private static $max_logs = 1000;

    /**
     * Log a message
     *
     * @param string $category Category (api, theme, chat, voice, etc)
     * @param string $message Log message
     * @param string $level Level (info, warning, error)
     * @param array  $data Additional data
     */
    public static function log($category, $message, $level = 'info', $data = array()) {
        global $wpdb;

        $table = $wpdb->prefix . self::$table_name;

        $wpdb->insert(
            $table,
            array(
                'category' => sanitize_text_field($category),
                'level' => sanitize_text_field($level),
                'message' => sanitize_text_field($message),
                'data' => wp_json_encode($data),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );

        // Also log to WordPress debug.log if WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[AAVAC Bot %s] %s: %s', strtoupper($level), $category, $message));
            if (!empty($data)) {
                error_log('[AAVAC Bot Data] ' . print_r($data, true));
            }
        }

        // Clean old logs
        self::cleanup_old_logs();
    }

    /**
     * Get recent logs
     *
     * @param int $limit Number of logs to retrieve
     * @param string $category Optional category filter
     * @param string $level Optional level filter
     * @return array
     */
    public static function get_logs($limit = 100, $category = null, $level = null) {
        global $wpdb;

        $table = $wpdb->prefix . self::$table_name;
        
        $where = array('1=1');
        $values = array();

        if ($category) {
            $where[] = 'category = %s';
            $values[] = $category;
        }

        if ($level) {
            $where[] = 'level = %s';
            $values[] = $level;
        }

        $sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where) . " ORDER BY id DESC LIMIT %d";
        $values[] = $limit;

        return $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);
    }

    /**
     * Clear all logs
     */
    public static function clear_logs() {
        global $wpdb;
        $table = $wpdb->prefix . self::$table_name;
        $wpdb->query("TRUNCATE TABLE $table");
    }

    /**
     * Export logs as text file
     *
     * @return string
     */
    public static function export_logs() {
        $logs = self::get_logs(500);
        
        $output = "AAVAC Bot Debug Logs\n";
        $output .= "Generated: " . current_time('Y-m-d H:i:s') . "\n";
        $output .= str_repeat('=', 80) . "\n\n";

        foreach ($logs as $log) {
            $output .= sprintf(
                "[%s] [%s] %s: %s\n",
                $log['created_at'],
                strtoupper($log['level']),
                $log['category'],
                $log['message']
            );

            if (!empty($log['data']) && $log['data'] !== '[]') {
                $output .= "Data: " . $log['data'] . "\n";
            }

            $output .= str_repeat('-', 80) . "\n";
        }

        return $output;
    }

    /**
     * Cleanup old logs
     */
    private static function cleanup_old_logs() {
        global $wpdb;

        $table = $wpdb->prefix . self::$table_name;

        // Only cleanup occasionally (10% chance)
        if (rand(1, 10) !== 1) {
            return;
        }

        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE id NOT IN (
                SELECT id FROM (
                    SELECT id FROM $table ORDER BY id DESC LIMIT %d
                ) as keep_logs
            )",
            self::$max_logs
        ));
    }

    /**
     * Create logs table
     */
    public static function create_table() {
        global $wpdb;

        $table = $wpdb->prefix . self::$table_name;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(50) NOT NULL,
            level VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            data LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_category (category),
            INDEX idx_level (level),
            INDEX idx_created (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
