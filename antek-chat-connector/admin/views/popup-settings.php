<?php
/**
 * Popup Settings View
 *
 * @package Antek_Chat_Connector
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$popup = get_option('antek_chat_popup', array());
?>

<form method="post" action="options.php">
    <?php settings_fields('antek_chat_popup'); ?>

    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row">
                    <?php esc_html_e('Enable Popup', 'antek-chat-connector'); ?>
                </th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox"
                                   name="antek_chat_popup[popup_enabled]"
                                   id="popup_enabled"
                                   value="1"
                                   <?php checked(isset($popup['popup_enabled']) ? $popup['popup_enabled'] : false, 1); ?>>
                            <?php esc_html_e('Automatically open chat widget with promotional message', 'antek-chat-connector'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="popup_trigger"><?php esc_html_e('Trigger Type', 'antek-chat-connector'); ?></label>
                </th>
                <td>
                    <select name="antek_chat_popup[popup_trigger]" id="popup_trigger">
                        <option value="time" <?php selected(isset($popup['popup_trigger']) ? $popup['popup_trigger'] : 'time', 'time'); ?>>
                            <?php esc_html_e('Time Delay', 'antek-chat-connector'); ?>
                        </option>
                        <option value="scroll" <?php selected(isset($popup['popup_trigger']) ? $popup['popup_trigger'] : '', 'scroll'); ?>>
                            <?php esc_html_e('Scroll Percentage', 'antek-chat-connector'); ?>
                        </option>
                        <option value="exit" <?php selected(isset($popup['popup_trigger']) ? $popup['popup_trigger'] : '', 'exit'); ?>>
                            <?php esc_html_e('Exit Intent', 'antek-chat-connector'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php esc_html_e('When should the popup appear?', 'antek-chat-connector'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="popup_delay"><?php esc_html_e('Delay/Threshold', 'antek-chat-connector'); ?></label>
                </th>
                <td>
                    <input type="number"
                           name="antek_chat_popup[popup_delay]"
                           id="popup_delay"
                           value="<?php echo esc_attr(isset($popup['popup_delay']) ? $popup['popup_delay'] : 3000); ?>"
                           min="0"
                           step="100">
                    <p class="description">
                        <?php esc_html_e('For time delay: milliseconds (e.g., 3000 = 3 seconds)', 'antek-chat-connector'); ?><br>
                        <?php esc_html_e('For scroll trigger: percentage (e.g., 50 = 50% down the page)', 'antek-chat-connector'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="popup_message"><?php esc_html_e('Promotional Message', 'antek-chat-connector'); ?></label>
                </th>
                <td>
                    <textarea name="antek_chat_popup[popup_message]"
                              id="popup_message"
                              rows="3"
                              class="large-text"><?php echo esc_textarea(isset($popup['popup_message']) ? $popup['popup_message'] : ''); ?></textarea>
                    <p class="description">
                        <?php esc_html_e('Message to display when popup triggers (optional)', 'antek-chat-connector'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="popup_frequency"><?php esc_html_e('Frequency', 'antek-chat-connector'); ?></label>
                </th>
                <td>
                    <select name="antek_chat_popup[popup_frequency]" id="popup_frequency">
                        <option value="once" <?php selected(isset($popup['popup_frequency']) ? $popup['popup_frequency'] : 'once', 'once'); ?>>
                            <?php esc_html_e('Once per user (permanent)', 'antek-chat-connector'); ?>
                        </option>
                        <option value="session" <?php selected(isset($popup['popup_frequency']) ? $popup['popup_frequency'] : '', 'session'); ?>>
                            <?php esc_html_e('Once per session', 'antek-chat-connector'); ?>
                        </option>
                        <option value="always" <?php selected(isset($popup['popup_frequency']) ? $popup['popup_frequency'] : '', 'always'); ?>>
                            <?php esc_html_e('Every time', 'antek-chat-connector'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php esc_html_e('How often should the popup appear to the same user?', 'antek-chat-connector'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="popup_pages"><?php esc_html_e('Page Targeting', 'antek-chat-connector'); ?></label>
                </th>
                <td>
                    <input type="text"
                           name="antek_chat_popup[popup_pages][]"
                           id="popup_pages"
                           value="<?php echo esc_attr(isset($popup['popup_pages'][0]) ? $popup['popup_pages'][0] : 'all'); ?>"
                           class="regular-text">
                    <p class="description">
                        <?php esc_html_e('Advanced feature - currently shows on all pages (type "all")', 'antek-chat-connector'); ?>
                    </p>
                </td>
            </tr>
        </tbody>
    </table>

    <?php submit_button(); ?>
</form>
