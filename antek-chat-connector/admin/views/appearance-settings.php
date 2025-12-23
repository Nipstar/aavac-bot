<?php
/**
 * Appearance Settings View
 *
 * @package Antek_Chat_Connector
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$appearance = get_option('antek_chat_appearance', array());
?>

<form method="post" action="options.php">
    <?php settings_fields('antek_chat_appearance'); ?>

    <div style="margin-bottom: 20px; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1;">
        <p style="margin: 0 0 10px 0;">
            <strong><?php esc_html_e('Theme Color Detection', 'antek-chat-connector'); ?></strong>
        </p>
        <p class="description" style="margin: 0 0 10px 0;">
            <?php esc_html_e('Automatically detect colors from your active WordPress theme to match your site design.', 'antek-chat-connector'); ?>
        </p>
        <button type="button" id="detect-theme-colors" class="button button-secondary">
            <span class="dashicons dashicons-art" style="vertical-align: middle;"></span>
            <?php esc_html_e('Detect Theme Colors', 'antek-chat-connector'); ?>
        </button>
        <span id="detect-colors-result" style="margin-left: 10px;"></span>
    </div>

    <h3><?php esc_html_e('Color Settings', 'antek-chat-connector'); ?></h3>
    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row">
                    <label for="color_source"><?php esc_html_e('Color Source', 'antek-chat-connector'); ?></label>
                </th>
                <td>
                    <?php
                    $color_detector = new Antek_Chat_Theme_Color_Detector();
                    $theme_type = $color_detector->get_active_theme_type();
                    $detected_colors = $color_detector->get_theme_colors();
                    $color_source = isset($appearance['color_source']) ? $appearance['color_source'] : 'auto';
                    ?>

                    <select name="antek_chat_appearance[color_source]" id="color_source">
                        <option value="auto" <?php selected($color_source, 'auto'); ?>>
                            <?php esc_html_e('Auto-detect from theme', 'antek-chat-connector'); ?>
                        </option>
                        <option value="elementor" <?php selected($color_source, 'elementor'); ?>>
                            <?php esc_html_e('Use Elementor Global Colors', 'antek-chat-connector'); ?>
                        </option>
                        <option value="divi" <?php selected($color_source, 'divi'); ?>>
                            <?php esc_html_e('Use Divi Theme Colors', 'antek-chat-connector'); ?>
                        </option>
                        <option value="custom" <?php selected($color_source, 'custom'); ?>>
                            <?php esc_html_e('Use Custom Colors (below)', 'antek-chat-connector'); ?>
                        </option>
                    </select>

                    <p class="description">
                        <strong><?php esc_html_e('Detected:', 'antek-chat-connector'); ?></strong>
                        <?php if ($theme_type === 'elementor'): ?>
                            ✅ <?php esc_html_e('Elementor', 'antek-chat-connector'); ?>
                            (<?php esc_html_e('Primary:', 'antek-chat-connector'); ?> <?php echo esc_html($detected_colors['primary']); ?>)
                        <?php elseif ($theme_type === 'divi'): ?>
                            ✅ <?php esc_html_e('Divi', 'antek-chat-connector'); ?>
                            (<?php esc_html_e('Primary:', 'antek-chat-connector'); ?> <?php echo esc_html($detected_colors['primary']); ?>)
                        <?php else: ?>
                            ⚪ <?php esc_html_e('No page builder detected - using custom colors', 'antek-chat-connector'); ?>
                        <?php endif; ?>
                    </p>
                </td>
            </tr>
        </tbody>
    </table>

    <h3><?php esc_html_e('Custom Color Override', 'antek-chat-connector'); ?></h3>
    <p class="description"><?php esc_html_e('These colors are used when "Custom Colors" is selected above, or as fallback if no theme is detected.', 'antek-chat-connector'); ?></p>

    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row">
                    <label for="primary_color"><?php esc_html_e('Primary Color', 'antek-chat-connector'); ?></label>
                </th>
                <td>
                    <input type="text"
                           name="antek_chat_appearance[primary_color]"
                           id="primary_color"
                           value="<?php echo esc_attr(isset($appearance['primary_color']) ? $appearance['primary_color'] : '#FF6B4A'); ?>"
                           class="antek-color-picker">
                    <p class="description">
                        <?php esc_html_e('Used for buttons and accent elements', 'antek-chat-connector'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="secondary_color"><?php esc_html_e('Secondary Color', 'antek-chat-connector'); ?></label>
                </th>
                <td>
                    <input type="text"
                           name="antek_chat_appearance[secondary_color]"
                           id="secondary_color"
                           value="<?php echo esc_attr(isset($appearance['secondary_color']) ? $appearance['secondary_color'] : '#8FA68E'); ?>"
                           class="antek-color-picker">
                    <p class="description">
                        <?php esc_html_e('Used for secondary elements', 'antek-chat-connector'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="background_color"><?php esc_html_e('Background Color', 'antek-chat-connector'); ?></label>
                </th>
                <td>
                    <input type="text"
                           name="antek_chat_appearance[background_color]"
                           id="background_color"
                           value="<?php echo esc_attr(isset($appearance['background_color']) ? $appearance['background_color'] : '#FDFBF6'); ?>"
                           class="antek-color-picker">
                    <p class="description">
                        <?php esc_html_e('Widget background color', 'antek-chat-connector'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="text_color"><?php esc_html_e('Text Color', 'antek-chat-connector'); ?></label>
                </th>
                <td>
                    <input type="text"
                           name="antek_chat_appearance[text_color]"
                           id="text_color"
                           value="<?php echo esc_attr(isset($appearance['text_color']) ? $appearance['text_color'] : '#2C2C2C'); ?>"
                           class="antek-color-picker">
                    <p class="description">
                        <?php esc_html_e('Main text color', 'antek-chat-connector'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="widget_position"><?php esc_html_e('Widget Position', 'antek-chat-connector'); ?></label>
                </th>
                <td>
                    <select name="antek_chat_appearance[widget_position]" id="widget_position">
                        <option value="bottom-right" <?php selected(isset($appearance['widget_position']) ? $appearance['widget_position'] : 'bottom-right', 'bottom-right'); ?>>
                            <?php esc_html_e('Bottom Right', 'antek-chat-connector'); ?>
                        </option>
                        <option value="bottom-left" <?php selected(isset($appearance['widget_position']) ? $appearance['widget_position'] : '', 'bottom-left'); ?>>
                            <?php esc_html_e('Bottom Left', 'antek-chat-connector'); ?>
                        </option>
                        <option value="top-right" <?php selected(isset($appearance['widget_position']) ? $appearance['widget_position'] : '', 'top-right'); ?>>
                            <?php esc_html_e('Top Right', 'antek-chat-connector'); ?>
                        </option>
                        <option value="top-left" <?php selected(isset($appearance['widget_position']) ? $appearance['widget_position'] : '', 'top-left'); ?>>
                            <?php esc_html_e('Top Left', 'antek-chat-connector'); ?>
                        </option>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="widget_size"><?php esc_html_e('Widget Size', 'antek-chat-connector'); ?></label>
                </th>
                <td>
                    <select name="antek_chat_appearance[widget_size]" id="widget_size">
                        <option value="small" <?php selected(isset($appearance['widget_size']) ? $appearance['widget_size'] : '', 'small'); ?>>
                            <?php esc_html_e('Small', 'antek-chat-connector'); ?>
                        </option>
                        <option value="medium" <?php selected(isset($appearance['widget_size']) ? $appearance['widget_size'] : 'medium', 'medium'); ?>>
                            <?php esc_html_e('Medium', 'antek-chat-connector'); ?>
                        </option>
                        <option value="large" <?php selected(isset($appearance['widget_size']) ? $appearance['widget_size'] : '', 'large'); ?>>
                            <?php esc_html_e('Large', 'antek-chat-connector'); ?>
                        </option>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="border_radius"><?php esc_html_e('Border Radius', 'antek-chat-connector'); ?></label>
                </th>
                <td>
                    <input type="text"
                           name="antek_chat_appearance[border_radius]"
                           id="border_radius"
                           value="<?php echo esc_attr(isset($appearance['border_radius']) ? $appearance['border_radius'] : '12px'); ?>"
                           placeholder="12px">
                    <p class="description">
                        <?php esc_html_e('Corner roundness (e.g., 0px, 8px, 16px)', 'antek-chat-connector'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="font_family"><?php esc_html_e('Font Family', 'antek-chat-connector'); ?></label>
                </th>
                <td>
                    <input type="text"
                           name="antek_chat_appearance[font_family]"
                           id="font_family"
                           value="<?php echo esc_attr(isset($appearance['font_family']) ? $appearance['font_family'] : 'inherit'); ?>"
                           class="regular-text"
                           placeholder="inherit">
                    <p class="description">
                        <?php esc_html_e('Font family for widget text (use "inherit" to match your theme)', 'antek-chat-connector'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="custom_css"><?php esc_html_e('Custom CSS', 'antek-chat-connector'); ?></label>
                </th>
                <td>
                    <textarea name="antek_chat_appearance[custom_css]"
                              id="custom_css"
                              rows="10"
                              class="large-text code"><?php echo esc_textarea(isset($appearance['custom_css']) ? $appearance['custom_css'] : ''); ?></textarea>
                    <p class="description">
                        <?php esc_html_e('Add custom CSS to further customize the widget appearance', 'antek-chat-connector'); ?>
                    </p>
                </td>
            </tr>
        </tbody>
    </table>

    <?php submit_button(); ?>
</form>
