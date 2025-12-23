<?php
/**
 * Advanced Settings View
 *
 * Admin interface for rate limiting, async jobs, and media configuration
 *
 * @package Antek_Chat_Connector
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$settings = get_option('antek_chat_advanced_settings', []);

// Defaults
$rate_limit_messages = isset($settings['rate_limit_messages_per_hour']) ? $settings['rate_limit_messages_per_hour'] : 50;
$rate_limit_tokens = isset($settings['rate_limit_tokens_per_minute']) ? $settings['rate_limit_tokens_per_minute'] : 10;
$rate_limit_uploads = isset($settings['rate_limit_uploads_per_hour']) ? $settings['rate_limit_uploads_per_hour'] : 10;

$async_enabled = isset($settings['async_jobs_enabled']) ? (bool) $settings['async_jobs_enabled'] : true;
$async_retries = isset($settings['async_max_retries']) ? $settings['async_max_retries'] : 3;
$async_timeout = isset($settings['async_callback_timeout']) ? $settings['async_callback_timeout'] : 30;
$async_cleanup = isset($settings['async_cleanup_days']) ? $settings['async_cleanup_days'] : 7;

$media_max_size = isset($settings['media_max_file_size_mb']) ? $settings['media_max_file_size_mb'] : 50;
$media_types = isset($settings['media_allowed_types']) ? $settings['media_allowed_types'] : ['image', 'audio', 'document'];

// Get stats
global $wpdb;
$jobs_table = $wpdb->prefix . 'antek_chat_jobs';
$media_table = $wpdb->prefix . 'antek_chat_media';

$total_jobs = $wpdb->get_var("SELECT COUNT(*) FROM $jobs_table");
$pending_jobs = $wpdb->get_var("SELECT COUNT(*) FROM $jobs_table WHERE status = 'pending'");
$total_media = $wpdb->get_var("SELECT COUNT(*) FROM $media_table");
$media_size = $wpdb->get_var("SELECT SUM(file_size) FROM $media_table");
?>

<div class="wrap antek-chat-settings">
    <h1><?php _e('AAVAC Bot - Advanced Settings', 'antek-chat-connector'); ?></h1>

    <div class="antek-settings-header">
        <p class="description">
            <?php _e('Configure rate limiting, asynchronous job processing, and media upload settings.', 'antek-chat-connector'); ?>
        </p>
    </div>

    <!-- System Status -->
    <div class="antek-section antek-status-cards">
        <h2><?php _e('System Status', 'antek-chat-connector'); ?></h2>

        <div class="antek-cards-grid">
            <div class="antek-stat-card">
                <div class="antek-stat-icon">
                    <span class="dashicons dashicons-list-view"></span>
                </div>
                <div class="antek-stat-content">
                    <div class="antek-stat-value"><?php echo number_format_i18n($total_jobs); ?></div>
                    <div class="antek-stat-label"><?php _e('Total Jobs', 'antek-chat-connector'); ?></div>
                </div>
            </div>

            <div class="antek-stat-card">
                <div class="antek-stat-icon pending">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="antek-stat-content">
                    <div class="antek-stat-value"><?php echo number_format_i18n($pending_jobs); ?></div>
                    <div class="antek-stat-label"><?php _e('Pending Jobs', 'antek-chat-connector'); ?></div>
                </div>
            </div>

            <div class="antek-stat-card">
                <div class="antek-stat-icon media">
                    <span class="dashicons dashicons-format-image"></span>
                </div>
                <div class="antek-stat-content">
                    <div class="antek-stat-value"><?php echo number_format_i18n($total_media); ?></div>
                    <div class="antek-stat-label"><?php _e('Media Files', 'antek-chat-connector'); ?></div>
                </div>
            </div>

            <div class="antek-stat-card">
                <div class="antek-stat-icon storage">
                    <span class="dashicons dashicons-database"></span>
                </div>
                <div class="antek-stat-content">
                    <div class="antek-stat-value"><?php echo size_format($media_size ?: 0); ?></div>
                    <div class="antek-stat-label"><?php _e('Storage Used', 'antek-chat-connector'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <form method="post" action="options.php" id="advanced-settings-form">
        <?php settings_fields('antek_chat_advanced_settings'); ?>

        <!-- Rate Limiting -->
        <div class="antek-section">
            <h2><?php _e('Rate Limiting', 'antek-chat-connector'); ?></h2>
            <p class="description">
                <?php _e('Control request frequency using token bucket algorithm. Protects against abuse and excessive API usage.', 'antek-chat-connector'); ?>
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="rate_limit_messages"><?php _e('Text Messages', 'antek-chat-connector'); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               id="rate_limit_messages"
                               name="antek_chat_advanced_settings[rate_limit_messages_per_hour]"
                               value="<?php echo esc_attr($rate_limit_messages); ?>"
                               min="1"
                               max="1000"
                               class="small-text">
                        <span><?php _e('messages per hour per session', 'antek-chat-connector'); ?></span>
                        <p class="description">
                            <?php _e('Maximum text messages a user can send per hour. Default: 50', 'antek-chat-connector'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="rate_limit_tokens"><?php _e('Voice Tokens', 'antek-chat-connector'); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               id="rate_limit_tokens"
                               name="antek_chat_advanced_settings[rate_limit_tokens_per_minute]"
                               value="<?php echo esc_attr($rate_limit_tokens); ?>"
                               min="1"
                               max="100"
                               class="small-text">
                        <span><?php _e('tokens per minute per session', 'antek-chat-connector'); ?></span>
                        <p class="description">
                            <?php _e('Maximum voice access tokens a user can generate per minute. Default: 10', 'antek-chat-connector'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="rate_limit_uploads"><?php _e('File Uploads', 'antek-chat-connector'); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               id="rate_limit_uploads"
                               name="antek_chat_advanced_settings[rate_limit_uploads_per_hour]"
                               value="<?php echo esc_attr($rate_limit_uploads); ?>"
                               min="1"
                               max="100"
                               class="small-text">
                        <span><?php _e('uploads per hour per session', 'antek-chat-connector'); ?></span>
                        <p class="description">
                            <?php _e('Maximum file uploads a user can perform per hour. Default: 10', 'antek-chat-connector'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Async Job Processing -->
        <div class="antek-section">
            <h2><?php _e('Asynchronous Job Processing', 'antek-chat-connector'); ?></h2>
            <p class="description">
                <?php _e('Background job processing for transcription, text-to-speech, media processing, and webhook callbacks.', 'antek-chat-connector'); ?>
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="async_enabled"><?php _e('Enable Async Jobs', 'antek-chat-connector'); ?></label>
                    </th>
                    <td>
                        <label class="antek-toggle">
                            <input type="checkbox"
                                   id="async_enabled"
                                   name="antek_chat_advanced_settings[async_jobs_enabled]"
                                   value="1"
                                   <?php checked($async_enabled); ?>>
                            <span class="antek-toggle-slider"></span>
                        </label>
                        <p class="description">
                            <?php _e('Process long-running operations in the background using WordPress cron', 'antek-chat-connector'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="async_retries"><?php _e('Max Retries', 'antek-chat-connector'); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               id="async_retries"
                               name="antek_chat_advanced_settings[async_max_retries]"
                               value="<?php echo esc_attr($async_retries); ?>"
                               min="0"
                               max="10"
                               class="small-text">
                        <span><?php _e('retry attempts', 'antek-chat-connector'); ?></span>
                        <p class="description">
                            <?php _e('Number of times to retry failed jobs with exponential backoff. Default: 3', 'antek-chat-connector'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="async_timeout"><?php _e('Callback Timeout', 'antek-chat-connector'); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               id="async_timeout"
                               name="antek_chat_advanced_settings[async_callback_timeout]"
                               value="<?php echo esc_attr($async_timeout); ?>"
                               min="5"
                               max="300"
                               class="small-text">
                        <span><?php _e('seconds', 'antek-chat-connector'); ?></span>
                        <p class="description">
                            <?php _e('HTTP timeout for callback URL requests. Default: 30', 'antek-chat-connector'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="async_cleanup"><?php _e('Job Cleanup', 'antek-chat-connector'); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               id="async_cleanup"
                               name="antek_chat_advanced_settings[async_cleanup_days]"
                               value="<?php echo esc_attr($async_cleanup); ?>"
                               min="1"
                               max="365"
                               class="small-text">
                        <span><?php _e('days', 'antek-chat-connector'); ?></span>
                        <p class="description">
                            <?php _e('Automatically delete completed/failed jobs older than this many days. Default: 7', 'antek-chat-connector'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Actions', 'antek-chat-connector'); ?></th>
                    <td>
                        <button type="button" id="cleanup-jobs-now" class="button button-secondary">
                            <span class="dashicons dashicons-trash"></span>
                            <?php _e('Cleanup Old Jobs Now', 'antek-chat-connector'); ?>
                        </button>
                        <span id="cleanup-jobs-result"></span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Media Upload Settings -->
        <div class="antek-section">
            <h2><?php _e('Media Upload Settings', 'antek-chat-connector'); ?></h2>
            <p class="description">
                <?php _e('Configure file upload validation and storage for images, audio, documents, and video.', 'antek-chat-connector'); ?>
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="media_max_size"><?php _e('Max File Size', 'antek-chat-connector'); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               id="media_max_size"
                               name="antek_chat_advanced_settings[media_max_file_size_mb]"
                               value="<?php echo esc_attr($media_max_size); ?>"
                               min="1"
                               max="500"
                               class="small-text">
                        <span><?php _e('MB per file', 'antek-chat-connector'); ?></span>
                        <p class="description">
                            <?php _e('Maximum file size for uploads. Default: 50 MB', 'antek-chat-connector'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <?php _e('Allowed File Types', 'antek-chat-connector'); ?>
                    </th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox"
                                       name="antek_chat_advanced_settings[media_allowed_types][]"
                                       value="image"
                                       <?php checked(in_array('image', $media_types)); ?>>
                                <?php _e('Images', 'antek-chat-connector'); ?>
                                <span class="description">(JPEG, PNG, GIF, WebP)</span>
                            </label><br>

                            <label>
                                <input type="checkbox"
                                       name="antek_chat_advanced_settings[media_allowed_types][]"
                                       value="audio"
                                       <?php checked(in_array('audio', $media_types)); ?>>
                                <?php _e('Audio', 'antek-chat-connector'); ?>
                                <span class="description">(MP3, WAV, OGG, WebM)</span>
                            </label><br>

                            <label>
                                <input type="checkbox"
                                       name="antek_chat_advanced_settings[media_allowed_types][]"
                                       value="document"
                                       <?php checked(in_array('document', $media_types)); ?>>
                                <?php _e('Documents', 'antek-chat-connector'); ?>
                                <span class="description">(PDF, DOC, DOCX, TXT)</span>
                            </label><br>

                            <label>
                                <input type="checkbox"
                                       name="antek_chat_advanced_settings[media_allowed_types][]"
                                       value="video"
                                       <?php checked(in_array('video', $media_types)); ?>>
                                <?php _e('Video', 'antek-chat-connector'); ?>
                                <span class="description">(MP4, WebM, OGG)</span>
                            </label>
                        </fieldset>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="media_storage"><?php _e('Storage Location', 'antek-chat-connector'); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               id="media_storage"
                               name="antek_chat_advanced_settings[media_storage_location]"
                               value="<?php echo esc_attr($settings['media_storage_location'] ?? 'wp-content/antek-media'); ?>"
                               class="regular-text code"
                               readonly>
                        <p class="description">
                            <?php _e('Media files are stored outside web root with .htaccess protection', 'antek-chat-connector'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Actions', 'antek-chat-connector'); ?></th>
                    <td>
                        <button type="button" id="cleanup-media-now" class="button button-secondary">
                            <span class="dashicons dashicons-trash"></span>
                            <?php _e('Cleanup Old Media (90+ days)', 'antek-chat-connector'); ?>
                        </button>
                        <span id="cleanup-media-result"></span>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button(__('Save Advanced Settings', 'antek-chat-connector')); ?>
    </form>
</div>

<style>
.antek-status-cards {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
}

.antek-status-cards h2 {
    color: white;
    border: none;
    margin-top: 0;
}

.antek-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.antek-stat-card {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.antek-stat-icon {
    width: 50px;
    height: 50px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.antek-stat-icon .dashicons {
    font-size: 24px;
    width: 24px;
    height: 24px;
    color: white;
}

.antek-stat-icon.pending {
    background: rgba(255, 193, 7, 0.3);
}

.antek-stat-icon.media {
    background: rgba(76, 175, 80, 0.3);
}

.antek-stat-icon.storage {
    background: rgba(33, 150, 243, 0.3);
}

.antek-stat-value {
    font-size: 32px;
    font-weight: 700;
    line-height: 1;
}

.antek-stat-label {
    font-size: 14px;
    opacity: 0.9;
    margin-top: 5px;
}

#cleanup-jobs-result,
#cleanup-media-result {
    margin-left: 10px;
}

#cleanup-jobs-result.success,
#cleanup-media-result.success {
    color: #00a32a;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Cleanup old jobs
    $('#cleanup-jobs-now').on('click', function() {
        const $btn = $(this);
        const $result = $('#cleanup-jobs-result');

        if (!confirm('<?php esc_html_e('Are you sure you want to delete old completed/failed jobs?', 'antek-chat-connector'); ?>')) {
            return;
        }

        $btn.prop('disabled', true);
        $result.removeClass('success').text('<?php esc_html_e('Cleaning up...', 'antek-chat-connector'); ?>');

        // Call WordPress AJAX to cleanup jobs
        $.post(ajaxurl, {
            action: 'antek_chat_cleanup_jobs',
            nonce: '<?php echo wp_create_nonce('antek_chat_cleanup'); ?>'
        }, function(response) {
            if (response.success) {
                $result.addClass('success').html(
                    '<span class="dashicons dashicons-yes"></span> ' +
                    response.data.message
                );
            } else {
                $result.html(
                    '<span class="dashicons dashicons-no"></span> ' +
                    response.data.message
                );
            }
        }).always(function() {
            $btn.prop('disabled', false);
        });
    });

    // Cleanup old media
    $('#cleanup-media-now').on('click', function() {
        const $btn = $(this);
        const $result = $('#cleanup-media-result');

        if (!confirm('<?php esc_html_e('Are you sure you want to delete media files older than 90 days?', 'antek-chat-connector'); ?>')) {
            return;
        }

        $btn.prop('disabled', true);
        $result.removeClass('success').text('<?php esc_html_e('Cleaning up...', 'antek-chat-connector'); ?>');

        // Call WordPress AJAX to cleanup media
        $.post(ajaxurl, {
            action: 'antek_chat_cleanup_media',
            nonce: '<?php echo wp_create_nonce('antek_chat_cleanup'); ?>'
        }, function(response) {
            if (response.success) {
                $result.addClass('success').html(
                    '<span class="dashicons dashicons-yes"></span> ' +
                    response.data.message
                );
            } else {
                $result.html(
                    '<span class="dashicons dashicons-no"></span> ' +
                    response.data.message
                );
            }
        }).always(function() {
            $btn.prop('disabled', false);
        });
    });
});
</script>
