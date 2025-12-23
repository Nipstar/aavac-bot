<?php
/**
 * Session Manager Class
 *
 * Manages chat sessions and conversation history
 *
 * @package Antek_Chat_Connector
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Session manager class
 *
 * @since 1.0.0
 */
class Antek_Chat_Session_Manager {

    /**
     * Cookie name for session ID
     *
     * @var string
     */
    private $cookie_name = 'antek_chat_session';

    /**
     * Cookie expiration (30 days)
     *
     * @var int
     */
    private $cookie_expiration = 2592000; // 30 days in seconds

    /**
     * Get or create session ID
     *
     * @return string Session ID
     * @since 1.0.0
     */
    public function get_session_id() {
        if (isset($_COOKIE[$this->cookie_name])) {
            return sanitize_text_field($_COOKIE[$this->cookie_name]);
        }

        $session_id = $this->generate_session_id();

        // Set cookie (will be set on next page load)
        setcookie(
            $this->cookie_name,
            $session_id,
            time() + $this->cookie_expiration,
            COOKIEPATH,
            COOKIE_DOMAIN,
            is_ssl(),
            true // HTTP only
        );

        return $session_id;
    }

    /**
     * Generate unique session ID
     *
     * @return string UUID v4
     * @since 1.0.0
     */
    private function generate_session_id() {
        return wp_generate_uuid4();
    }

    /**
     * Save conversation data
     *
     * @param string $session_id Session ID
     * @param string $message User message
     * @param string $response Bot response
     * @return bool Success status
     * @since 1.0.0
     */
    public function save_conversation($session_id, $message, $response) {
        global $wpdb;
        $table = $wpdb->prefix . 'antek_chat_sessions';

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE session_id = %s",
            $session_id
        ));

        $conversation_data = array();
        if ($existing && !empty($existing->conversation_data)) {
            $conversation_data = json_decode($existing->conversation_data, true);
            if (!is_array($conversation_data)) {
                $conversation_data = array();
            }
        }

        $conversation_data[] = array(
            'timestamp' => current_time('mysql'),
            'message' => sanitize_text_field($message),
            'response' => sanitize_textarea_field($response),
        );

        if ($existing) {
            return $wpdb->update(
                $table,
                array(
                    'conversation_data' => wp_json_encode($conversation_data),
                    'updated_at' => current_time('mysql'),
                ),
                array('session_id' => $session_id),
                array('%s', '%s'),
                array('%s')
            ) !== false;
        } else {
            // Get IP address safely
            $ip_address = '';
            if (isset($_SERVER['REMOTE_ADDR'])) {
                $ip_address = sanitize_text_field($_SERVER['REMOTE_ADDR']);
            }

            // Get user agent safely
            $user_agent = '';
            if (isset($_SERVER['HTTP_USER_AGENT'])) {
                $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT']);
            }

            return $wpdb->insert(
                $table,
                array(
                    'session_id' => $session_id,
                    'user_id' => get_current_user_id(),
                    'ip_address' => $ip_address,
                    'user_agent' => $user_agent,
                    'conversation_data' => wp_json_encode($conversation_data),
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ),
                array('%s', '%d', '%s', '%s', '%s', '%s', '%s')
            ) !== false;
        }
    }

    /**
     * Get conversation history
     *
     * @param string $session_id Session ID
     * @return array Conversation history
     * @since 1.0.0
     */
    public function get_conversation($session_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'antek_chat_sessions';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT conversation_data FROM $table WHERE session_id = %s",
            $session_id
        ));

        if ($row && !empty($row->conversation_data)) {
            $data = json_decode($row->conversation_data, true);
            return is_array($data) ? $data : array();
        }

        return array();
    }

    /**
     * Delete old sessions (cleanup utility)
     *
     * @param int $days Number of days to keep
     * @return int Number of rows deleted
     * @since 1.0.0
     */
    public function cleanup_old_sessions($days = 90) {
        global $wpdb;
        $table = $wpdb->prefix . 'antek_chat_sessions';

        $date = date('Y-m-d H:i:s', strtotime("-$days days"));

        return $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE updated_at < %s",
            $date
        ));
    }
}
