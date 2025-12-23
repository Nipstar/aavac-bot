<?php
/**
 * Media Manager Class
 *
 * Handles file upload validation, storage, and retrieval
 *
 * @package Antek_Chat_Connector
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Media Manager class
 *
 * Provides secure media handling:
 * - File upload validation (MIME type, size)
 * - Secure storage outside web root
 * - Media metadata tracking
 * - Access URL generation with token-based auth
 * - File cleanup
 *
 * @since 1.1.0
 */
class Antek_Chat_Media_Manager {

    /**
     * Upload directory path
     *
     * @var string
     */
    private $upload_dir;

    /**
     * Allowed MIME types
     *
     * @var array
     */
    private $allowed_mimes = [
        // Images
        'image/jpeg' => 'image',
        'image/jpg' => 'image',
        'image/png' => 'image',
        'image/gif' => 'image',
        'image/webp' => 'image',
        // Audio
        'audio/mpeg' => 'audio',
        'audio/mp3' => 'audio',
        'audio/wav' => 'audio',
        'audio/ogg' => 'audio',
        'audio/webm' => 'audio',
        // Documents
        'application/pdf' => 'document',
        'application/msword' => 'document',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'document',
        'text/plain' => 'document',
        // Video
        'video/mp4' => 'video',
        'video/webm' => 'video',
        'video/ogg' => 'video',
    ];

    /**
     * Constructor
     *
     * @since 1.1.0
     */
    public function __construct() {
        $this->upload_dir = WP_CONTENT_DIR . '/antek-media';
        $this->ensure_upload_directory();
    }

    /**
     * Upload file
     *
     * Validates and stores uploaded file
     *
     * @param array  $file Uploaded file array ($_FILES format).
     * @param string $session_id Session ID.
     * @return array|WP_Error Media data or error
     * @since 1.1.0
     */
    public function upload_file($file, $session_id) {
        // Validate file
        $validation = $this->validate_file($file);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Get file info
        $original_filename = sanitize_file_name($file['name']);
        $mime_type = $file['type'];
        $file_size = $file['size'];
        $tmp_path = $file['tmp_name'];

        // Determine file type category
        $file_type = $this->allowed_mimes[$mime_type];

        // Generate unique stored filename
        $extension = pathinfo($original_filename, PATHINFO_EXTENSION);
        $stored_filename = wp_generate_uuid4() . '.' . $extension;
        $upload_path = $this->upload_dir . '/' . $stored_filename;

        // Move file to storage
        if (!move_uploaded_file($tmp_path, $upload_path)) {
            return new WP_Error(
                'file_move_failed',
                __('Failed to move uploaded file', 'antek-chat-connector')
            );
        }

        // Set secure permissions
        chmod($upload_path, 0644);

        // Extract metadata based on file type
        $metadata = $this->extract_metadata($upload_path, $file_type);

        // Insert media record
        global $wpdb;
        $media_table = $wpdb->prefix . 'antek_chat_media';

        $result = $wpdb->insert(
            $media_table,
            [
                'session_id' => $session_id,
                'file_type' => $file_type,
                'original_filename' => $original_filename,
                'stored_filename' => $stored_filename,
                'file_size' => $file_size,
                'mime_type' => $mime_type,
                'upload_path' => $upload_path,
                'metadata' => wp_json_encode($metadata),
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            // Clean up file if database insert fails
            @unlink($upload_path);

            return new WP_Error(
                'database_insert_failed',
                __('Failed to save media metadata', 'antek-chat-connector')
            );
        }

        $media_id = $wpdb->insert_id;

        $this->log('File uploaded', 'info', [
            'media_id' => $media_id,
            'filename' => $original_filename,
            'type' => $file_type,
            'size' => $file_size,
        ]);

        return [
            'id' => $media_id,
            'filename' => $original_filename,
            'type' => $file_type,
            'size' => $file_size,
            'mime_type' => $mime_type,
            'url' => $this->generate_access_url($stored_filename),
            'metadata' => $metadata,
        ];
    }

    /**
     * Validate file
     *
     * Checks file against validation rules
     *
     * @param array $file Uploaded file array.
     * @return bool|WP_Error True if valid, WP_Error otherwise
     * @since 1.1.0
     */
    public function validate_file($file) {
        // Check for upload errors
        if (!isset($file['error']) || is_array($file['error'])) {
            return new WP_Error(
                'invalid_file',
                __('Invalid file upload', 'antek-chat-connector')
            );
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error(
                'upload_error',
                $this->get_upload_error_message($file['error'])
            );
        }

        // Check MIME type
        $mime_type = $file['type'];

        if (!isset($this->allowed_mimes[$mime_type])) {
            return new WP_Error(
                'invalid_mime_type',
                sprintf(
                    __('File type not allowed: %s', 'antek-chat-connector'),
                    $mime_type
                )
            );
        }

        // Check file size
        $settings = get_option('antek_chat_advanced_settings', []);
        $max_size_mb = isset($settings['media_max_file_size_mb'])
            ? (int) $settings['media_max_file_size_mb']
            : 50;

        $max_size_bytes = $max_size_mb * 1024 * 1024;

        if ($file['size'] > $max_size_bytes) {
            return new WP_Error(
                'file_too_large',
                sprintf(
                    __('File exceeds maximum size of %d MB', 'antek-chat-connector'),
                    $max_size_mb
                )
            );
        }

        // Check file type category is allowed
        $file_type = $this->allowed_mimes[$mime_type];
        $allowed_types = isset($settings['media_allowed_types'])
            ? $settings['media_allowed_types']
            : ['image', 'audio', 'document'];

        if (!in_array($file_type, $allowed_types, true)) {
            return new WP_Error(
                'file_type_disabled',
                sprintf(
                    __('File type disabled: %s', 'antek-chat-connector'),
                    $file_type
                )
            );
        }

        // Verify file is actually uploaded
        if (!is_uploaded_file($file['tmp_name'])) {
            return new WP_Error(
                'not_uploaded_file',
                __('Security check failed', 'antek-chat-connector')
            );
        }

        return true;
    }

    /**
     * Get upload error message
     *
     * @param int $error_code PHP upload error code.
     * @return string Error message
     * @since 1.1.0
     */
    private function get_upload_error_message($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return __('File is too large', 'antek-chat-connector');
            case UPLOAD_ERR_PARTIAL:
                return __('File was only partially uploaded', 'antek-chat-connector');
            case UPLOAD_ERR_NO_FILE:
                return __('No file was uploaded', 'antek-chat-connector');
            case UPLOAD_ERR_NO_TMP_DIR:
                return __('Missing temporary folder', 'antek-chat-connector');
            case UPLOAD_ERR_CANT_WRITE:
                return __('Failed to write file to disk', 'antek-chat-connector');
            case UPLOAD_ERR_EXTENSION:
                return __('File upload stopped by extension', 'antek-chat-connector');
            default:
                return __('Unknown upload error', 'antek-chat-connector');
        }
    }

    /**
     * Extract metadata
     *
     * Extracts file-specific metadata
     *
     * @param string $file_path File path.
     * @param string $file_type File type category.
     * @return array Metadata
     * @since 1.1.0
     */
    private function extract_metadata($file_path, $file_type) {
        $metadata = [];

        switch ($file_type) {
            case 'image':
                $image_data = @getimagesize($file_path);
                if ($image_data !== false) {
                    $metadata['width'] = $image_data[0];
                    $metadata['height'] = $image_data[1];
                    $metadata['type'] = $image_data['mime'];
                }
                break;

            case 'audio':
            case 'video':
                // Could use getID3 library for media metadata
                // Placeholder for now
                $metadata['duration'] = 0;
                break;

            case 'document':
                // Could extract page count, etc.
                break;
        }

        return $metadata;
    }

    /**
     * Get media for session
     *
     * Returns all uploaded media for a session
     *
     * @param string $session_id Session ID.
     * @return array Array of media records
     * @since 1.1.0
     */
    public function get_media_for_session($session_id) {
        global $wpdb;
        $media_table = $wpdb->prefix . 'antek_chat_media';

        $media = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $media_table WHERE session_id = %s ORDER BY created_at DESC",
            $session_id
        ), ARRAY_A);

        // Add access URLs
        foreach ($media as &$item) {
            $item['url'] = $this->generate_access_url($item['stored_filename']);
            if (!empty($item['metadata'])) {
                $item['metadata'] = json_decode($item['metadata'], true);
            }
        }

        return $media;
    }

    /**
     * Get media by ID
     *
     * @param int $media_id Media ID.
     * @return array|WP_Error Media data or error
     * @since 1.1.0
     */
    public function get_media($media_id) {
        global $wpdb;
        $media_table = $wpdb->prefix . 'antek_chat_media';

        $media = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $media_table WHERE id = %d",
            $media_id
        ), ARRAY_A);

        if (!$media) {
            return new WP_Error(
                'media_not_found',
                __('Media not found', 'antek-chat-connector')
            );
        }

        $media['url'] = $this->generate_access_url($media['stored_filename']);

        if (!empty($media['metadata'])) {
            $media['metadata'] = json_decode($media['metadata'], true);
        }

        return $media;
    }

    /**
     * Delete media
     *
     * Deletes media file and database record
     *
     * @param int $media_id Media ID.
     * @return bool|WP_Error True on success, error on failure
     * @since 1.1.0
     */
    public function delete_media($media_id) {
        global $wpdb;
        $media_table = $wpdb->prefix . 'antek_chat_media';

        // Get media record
        $media = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $media_table WHERE id = %d",
            $media_id
        ), ARRAY_A);

        if (!$media) {
            return new WP_Error(
                'media_not_found',
                __('Media not found', 'antek-chat-connector')
            );
        }

        // Delete physical file
        if (!empty($media['upload_path']) && file_exists($media['upload_path'])) {
            @unlink($media['upload_path']);
        }

        // Delete database record
        $result = $wpdb->delete(
            $media_table,
            ['id' => $media_id],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error(
                'delete_failed',
                __('Failed to delete media record', 'antek-chat-connector')
            );
        }

        $this->log('Media deleted', 'info', ['media_id' => $media_id]);

        return true;
    }

    /**
     * Generate access URL
     *
     * Creates URL for accessing media via REST endpoint
     *
     * @param string $stored_filename Stored filename.
     * @param int    $expiration Token expiration (default 3600 seconds).
     * @return string Access URL
     * @since 1.1.0
     */
    public function generate_access_url($stored_filename, $expiration = 3600) {
        // Generate access token
        $token = $this->generate_access_token($stored_filename, $expiration);

        // Build REST URL
        return rest_url('antek-chat/v1/media/' . $stored_filename . '?token=' . $token);
    }

    /**
     * Generate access token
     *
     * Creates short-lived token for media access
     *
     * @param string $stored_filename Stored filename.
     * @param int    $expiration Token expiration in seconds.
     * @return string Access token
     * @since 1.1.0
     */
    public function generate_access_token($stored_filename, $expiration = 3600) {
        $expires = time() + $expiration;
        $data = $stored_filename . '|' . $expires;

        $secret = wp_salt('nonce');
        $signature = hash_hmac('sha256', $data, $secret);

        return base64_encode($data . '|' . $signature);
    }

    /**
     * Verify access token
     *
     * Validates media access token
     *
     * @param string $token Access token.
     * @param string $stored_filename Stored filename.
     * @return bool True if valid
     * @since 1.1.0
     */
    public function verify_access_token($token, $stored_filename) {
        $decoded = base64_decode($token);

        if ($decoded === false) {
            return false;
        }

        $parts = explode('|', $decoded);

        if (count($parts) !== 3) {
            return false;
        }

        list($token_filename, $expires, $signature) = $parts;

        // Check if token is for this file
        if ($token_filename !== $stored_filename) {
            return false;
        }

        // Check if expired
        if (time() > (int) $expires) {
            return false;
        }

        // Verify signature
        $data = $token_filename . '|' . $expires;
        $secret = wp_salt('nonce');
        $expected_signature = hash_hmac('sha256', $data, $secret);

        return hash_equals($expected_signature, $signature);
    }

    /**
     * Serve media file
     *
     * Streams media file to browser
     *
     * @param string $stored_filename Stored filename.
     * @param string $token Access token.
     * @return void
     * @since 1.1.0
     */
    public function serve_media($stored_filename, $token) {
        // Verify token
        if (!$this->verify_access_token($token, $stored_filename)) {
            wp_die(__('Invalid or expired access token', 'antek-chat-connector'), 403);
        }

        // Get file path
        $file_path = $this->upload_dir . '/' . $stored_filename;

        if (!file_exists($file_path)) {
            wp_die(__('File not found', 'antek-chat-connector'), 404);
        }

        // Get MIME type from database
        global $wpdb;
        $media_table = $wpdb->prefix . 'antek_chat_media';

        $media = $wpdb->get_row($wpdb->prepare(
            "SELECT mime_type FROM $media_table WHERE stored_filename = %s",
            $stored_filename
        ), ARRAY_A);

        $mime_type = $media ? $media['mime_type'] : 'application/octet-stream';

        // Set headers
        header('Content-Type: ' . $mime_type);
        header('Content-Length: ' . filesize($file_path));
        header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
        header('Cache-Control: private, max-age=3600');

        // Stream file
        readfile($file_path);
        exit;
    }

    /**
     * Ensure upload directory exists
     *
     * @since 1.1.0
     */
    private function ensure_upload_directory() {
        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);
        }

        // Create .htaccess to prevent direct access
        $htaccess_path = $this->upload_dir . '/.htaccess';
        if (!file_exists($htaccess_path)) {
            file_put_contents($htaccess_path, "Deny from all\n");
        }

        // Create index.php to prevent directory listing
        $index_path = $this->upload_dir . '/index.php';
        if (!file_exists($index_path)) {
            file_put_contents($index_path, "<?php\n// Silence is golden.\n");
        }
    }

    /**
     * Get allowed MIME types
     *
     * @return array Allowed MIME types
     * @since 1.1.0
     */
    public function get_allowed_mimes() {
        return $this->allowed_mimes;
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
            '[Antek Chat][Media][%s] %s',
            strtoupper($level),
            $message
        );

        if (!empty($context)) {
            $log_message .= ' | ' . wp_json_encode($context);
        }

        error_log($log_message);
    }
}
