<?php
/**
 * Multimodal Session Manager Class
 *
 * Extends Session Manager with media attachment support
 *
 * @package Antek_Chat_Connector
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Ensure parent class is loaded
require_once ANTEK_CHAT_PLUGIN_DIR . 'includes/class-session-manager.php';

/**
 * Multimodal Session Manager class
 *
 * Extends base session manager to support media attachments and provider tracking
 *
 * @since 1.1.0
 */
class Antek_Chat_Multimodal_Session_Manager extends Antek_Chat_Session_Manager {

    /**
     * Save conversation with optional media attachments
     *
     * @param string $session_id Session ID.
     * @param string $message User message.
     * @param string $response Bot response.
     * @param array  $media_ids Optional array of media IDs to attach.
     * @param string $provider Optional provider name (retell, elevenlabs).
     * @return bool Success status
     * @since 1.1.0
     */
    public function save_conversation_with_media($session_id, $message, $response, $media_ids = [], $provider = 'elevenlabs') {
        global $wpdb;
        $table = $wpdb->prefix . 'antek_chat_sessions';

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE session_id = %s",
            $session_id
        ));

        $conversation_data = [];
        if ($existing && !empty($existing->conversation_data)) {
            $conversation_data = json_decode($existing->conversation_data, true);
            if (!is_array($conversation_data)) {
                $conversation_data = [];
            }
        }

        // Generate message ID for tracking
        $message_id = wp_generate_uuid4();

        // Build conversation entry
        $entry = [
            'id' => $message_id,
            'timestamp' => current_time('mysql'),
            'type' => 'exchange',
            'message' => sanitize_text_field($message),
            'response' => sanitize_textarea_field($response),
            'provider' => sanitize_text_field($provider),
        ];

        // Add media if provided
        if (!empty($media_ids) && is_array($media_ids)) {
            $entry['media'] = $this->get_media_metadata($media_ids);
        }

        $conversation_data[] = $entry;

        // Prepare update data
        $update_data = [
            'conversation_data' => wp_json_encode($conversation_data),
            'updated_at' => current_time('mysql'),
            'provider' => $provider,
        ];

        if ($existing) {
            $result = $wpdb->update(
                $table,
                $update_data,
                ['session_id' => $session_id],
                ['%s', '%s', '%s'],
                ['%s']
            ) !== false;
        } else {
            // Get IP address safely
            $ip_address = isset($_SERVER['REMOTE_ADDR'])
                ? sanitize_text_field($_SERVER['REMOTE_ADDR'])
                : '';

            // Get user agent safely
            $user_agent = isset($_SERVER['HTTP_USER_AGENT'])
                ? sanitize_text_field($_SERVER['HTTP_USER_AGENT'])
                : '';

            $result = $wpdb->insert(
                $table,
                [
                    'session_id' => $session_id,
                    'user_id' => get_current_user_id(),
                    'ip_address' => $ip_address,
                    'user_agent' => $user_agent,
                    'conversation_data' => wp_json_encode($conversation_data),
                    'provider' => $provider,
                    'encryption_key_version' => 1,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ],
                ['%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
            ) !== false;
        }

        // If media IDs provided, link them to this message
        if ($result && !empty($media_ids)) {
            $this->link_media_to_message($media_ids, $message_id, $session_id);
        }

        return $result;
    }

    /**
     * Get media metadata for media IDs
     *
     * @param array $media_ids Array of media IDs.
     * @return array Array of media metadata
     * @since 1.1.0
     */
    private function get_media_metadata($media_ids) {
        global $wpdb;
        $media_table = $wpdb->prefix . 'antek_chat_media';

        if (empty($media_ids)) {
            return [];
        }

        // Build placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($media_ids), '%d'));

        $media_data = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, file_type, original_filename, stored_filename, file_size, mime_type, metadata
                FROM $media_table
                WHERE id IN ($placeholders)",
                $media_ids
            ),
            ARRAY_A
        );

        // Transform into client-friendly format
        $result = [];
        foreach ($media_data as $media) {
            $result[] = [
                'id' => $media['id'],
                'type' => $media['file_type'],
                'filename' => $media['original_filename'],
                'size' => $media['file_size'],
                'mime_type' => $media['mime_type'],
                'metadata' => !empty($media['metadata']) ? json_decode($media['metadata'], true) : [],
                'url' => $this->get_media_url($media['stored_filename']),
            ];
        }

        return $result;
    }

    /**
     * Get secure media URL
     *
     * Generates a URL for accessing media via REST endpoint with token
     *
     * @param string $stored_filename Stored filename.
     * @return string Media URL
     * @since 1.1.0
     */
    private function get_media_url($stored_filename) {
        // For now, return REST endpoint URL
        // In future, add token-based authentication
        return rest_url('antek-chat/v1/media/' . $stored_filename);
    }

    /**
     * Link media to message
     *
     * Updates media table with message ID reference
     *
     * @param array  $media_ids Array of media IDs.
     * @param string $message_id Message ID.
     * @param string $session_id Session ID.
     * @return bool Success status
     * @since 1.1.0
     */
    private function link_media_to_message($media_ids, $message_id, $session_id) {
        global $wpdb;
        $media_table = $wpdb->prefix . 'antek_chat_media';

        if (empty($media_ids)) {
            return false;
        }

        // Build placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($media_ids), '%d'));

        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE $media_table
                SET message_id = %s, session_id = %s
                WHERE id IN ($placeholders)",
                array_merge(
                    [$message_id, $session_id],
                    $media_ids
                )
            )
        );

        return $updated !== false;
    }

    /**
     * Get conversation with media
     *
     * Returns conversation history including media metadata
     *
     * @param string $session_id Session ID.
     * @return array Conversation history with media
     * @since 1.1.0
     */
    public function get_conversation_with_media($session_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'antek_chat_sessions';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT conversation_data FROM $table WHERE session_id = %s",
            $session_id
        ));

        if ($row && !empty($row->conversation_data)) {
            $data = json_decode($row->conversation_data, true);
            return is_array($data) ? $data : [];
        }

        return [];
    }

    /**
     * Get session provider
     *
     * Returns the voice provider used for this session
     *
     * @param string $session_id Session ID.
     * @return string|null Provider name or null if not set
     * @since 1.1.0
     */
    public function get_session_provider($session_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'antek_chat_sessions';

        $provider = $wpdb->get_var($wpdb->prepare(
            "SELECT provider FROM $table WHERE session_id = %s",
            $session_id
        ));

        return $provider ? $provider : 'elevenlabs'; // Default fallback
    }

    /**
     * Set session provider
     *
     * Updates the provider for this session
     *
     * @param string $session_id Session ID.
     * @param string $provider Provider name (retell, elevenlabs).
     * @return bool Success status
     * @since 1.1.0
     */
    public function set_session_provider($session_id, $provider) {
        global $wpdb;
        $table = $wpdb->prefix . 'antek_chat_sessions';

        $updated = $wpdb->update(
            $table,
            ['provider' => sanitize_text_field($provider)],
            ['session_id' => $session_id],
            ['%s'],
            ['%s']
        );

        return $updated !== false;
    }

    /**
     * Get all media for session
     *
     * Returns all uploaded media for a session
     *
     * @param string $session_id Session ID.
     * @return array Array of media records
     * @since 1.1.0
     */
    public function get_session_media($session_id) {
        global $wpdb;
        $media_table = $wpdb->prefix . 'antek_chat_media';

        $media = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $media_table WHERE session_id = %s ORDER BY created_at ASC",
                $session_id
            ),
            ARRAY_A
        );

        // Add URLs to each media item
        foreach ($media as &$item) {
            $item['url'] = $this->get_media_url($item['stored_filename']);

            // Parse JSON metadata
            if (!empty($item['metadata'])) {
                $item['metadata'] = json_decode($item['metadata'], true);
            }
        }

        return $media;
    }

    /**
     * Delete session with media cleanup
     *
     * Deletes session and associated media files
     *
     * @param string $session_id Session ID.
     * @return bool Success status
     * @since 1.1.0
     */
    public function delete_session_with_media($session_id) {
        global $wpdb;

        // Get all media for this session
        $media = $this->get_session_media($session_id);

        // Delete physical files
        foreach ($media as $item) {
            if (!empty($item['upload_path'])) {
                $file_path = WP_CONTENT_DIR . '/antek-media/' . basename($item['upload_path']);
                if (file_exists($file_path)) {
                    @unlink($file_path);
                }
            }
        }

        // Delete media records
        $media_table = $wpdb->prefix . 'antek_chat_media';
        $wpdb->delete(
            $media_table,
            ['session_id' => $session_id],
            ['%s']
        );

        // Delete session record
        $sessions_table = $wpdb->prefix . 'antek_chat_sessions';
        $result = $wpdb->delete(
            $sessions_table,
            ['session_id' => $session_id],
            ['%s']
        );

        return $result !== false;
    }

    /**
     * Get session statistics
     *
     * Returns statistics about the session (message count, media count, etc.)
     *
     * @param string $session_id Session ID.
     * @return array Statistics
     * @since 1.1.0
     */
    public function get_session_stats($session_id) {
        global $wpdb;

        // Get conversation data
        $conversation = $this->get_conversation_with_media($session_id);

        // Get media count
        $media_table = $wpdb->prefix . 'antek_chat_media';
        $media_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $media_table WHERE session_id = %s",
            $session_id
        ));

        // Get session info
        $sessions_table = $wpdb->prefix . 'antek_chat_sessions';
        $session_info = $wpdb->get_row($wpdb->prepare(
            "SELECT created_at, updated_at, provider FROM $sessions_table WHERE session_id = %s",
            $session_id
        ), ARRAY_A);

        return [
            'message_count' => count($conversation),
            'media_count' => (int) $media_count,
            'provider' => $session_info['provider'] ?? 'elevenlabs',
            'created_at' => $session_info['created_at'] ?? null,
            'updated_at' => $session_info['updated_at'] ?? null,
            'duration' => $this->calculate_session_duration($session_info['created_at'] ?? null, $session_info['updated_at'] ?? null),
        ];
    }

    /**
     * Calculate session duration
     *
     * @param string|null $created_at Created timestamp.
     * @param string|null $updated_at Updated timestamp.
     * @return int|null Duration in seconds or null if timestamps invalid
     * @since 1.1.0
     */
    private function calculate_session_duration($created_at, $updated_at) {
        if (empty($created_at) || empty($updated_at)) {
            return null;
        }

        $created = strtotime($created_at);
        $updated = strtotime($updated_at);

        return $updated - $created;
    }

    /**
     * Cleanup old media files
     *
     * Removes media files older than specified days
     *
     * @param int $days Number of days to keep.
     * @return int Number of files deleted
     * @since 1.1.0
     */
    public function cleanup_old_media($days = 90) {
        global $wpdb;
        $media_table = $wpdb->prefix . 'antek_chat_media';

        $date = date('Y-m-d H:i:s', strtotime("-$days days"));

        // Get old media records
        $old_media = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, upload_path FROM $media_table WHERE created_at < %s",
                $date
            ),
            ARRAY_A
        );

        $deleted_count = 0;

        // Delete physical files
        foreach ($old_media as $media) {
            if (!empty($media['upload_path'])) {
                $file_path = WP_CONTENT_DIR . '/antek-media/' . basename($media['upload_path']);
                if (file_exists($file_path)) {
                    if (@unlink($file_path)) {
                        $deleted_count++;
                    }
                }
            }
        }

        // Delete media records
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $media_table WHERE created_at < %s",
            $date
        ));

        return $deleted_count;
    }
}
