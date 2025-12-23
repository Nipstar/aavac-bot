<?php
/**
 * Theme Color Detection
 * Detects and retrieves colors from Elementor, Divi, or other themes
 *
 * @package Antek_Chat_Connector
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Antek_Chat_Theme_Color_Detector {

    /**
     * Detect which page builder/theme is active
     *
     * @return string Theme type (elementor, divi, none)
     */
    public function get_active_theme_type() {
        if (did_action('elementor/loaded')) {
            return 'elementor';
        }

        if (function_exists('et_setup_theme') || get_template() === 'Divi') {
            return 'divi';
        }

        return 'none';
    }

    /**
     * Get colors from the detected theme/builder
     *
     * @return array Color scheme
     */
    public function get_theme_colors() {
        $theme_type = $this->get_active_theme_type();

        switch ($theme_type) {
            case 'elementor':
                return $this->get_elementor_colors();
            case 'divi':
                return $this->get_divi_colors();
            default:
                return $this->get_fallback_colors();
        }
    }

    /**
     * Extract Elementor global colors
     *
     * @return array Color scheme
     */
    private function get_elementor_colors() {
        $colors = array();

        if (!class_exists('\Elementor\Plugin')) {
            return $this->get_fallback_colors();
        }

        try {
            $kit_id = \Elementor\Plugin::$instance->kits_manager->get_active_id();
            $kit = \Elementor\Plugin::$instance->documents->get($kit_id);

            if (!$kit) {
                return $this->get_fallback_colors();
            }

            $settings = $kit->get_settings();

            // System colors (primary, secondary, text, accent)
            if (isset($settings['system_colors']) && is_array($settings['system_colors'])) {
                foreach ($settings['system_colors'] as $color) {
                    if (isset($color['_id']) && isset($color['color'])) {
                        $colors[$color['_id']] = $color['color'];
                    }
                }
            }

            // Custom colors
            if (isset($settings['custom_colors']) && is_array($settings['custom_colors'])) {
                foreach ($settings['custom_colors'] as $color) {
                    if (isset($color['_id']) && isset($color['color'])) {
                        $colors[$color['_id']] = $color['color'];
                    }
                }
            }

            // Map to standard format
            return array(
                'primary' => isset($colors['primary']) ? $colors['primary'] : '#6EC1E4',
                'secondary' => isset($colors['secondary']) ? $colors['secondary'] : '#54595F',
                'text' => isset($colors['text']) ? $colors['text'] : '#7A7A7A',
                'accent' => isset($colors['accent']) ? $colors['accent'] : '#61CE70',
                'background' => '#FFFFFF',
                'source' => 'elementor'
            );

        } catch (Exception $e) {
            error_log('Antek Chat: Error getting Elementor colors - ' . $e->getMessage());
            return $this->get_fallback_colors();
        }
    }

    /**
     * Extract Divi theme colors
     *
     * @return array Color scheme
     */
    private function get_divi_colors() {
        try {
            // Try to get Divi customizer settings
            $primary = get_theme_mod('et_divi_accent_color', '#2EA3F2');

            return array(
                'primary' => $primary,
                'secondary' => get_theme_mod('et_divi_link_color', '#2EA3F2'),
                'text' => get_theme_mod('et_divi_body_font_color', '#666666'),
                'accent' => $primary,
                'background' => '#FFFFFF',
                'source' => 'divi'
            );
        } catch (Exception $e) {
            error_log('Antek Chat: Divi color detection failed: ' . $e->getMessage());
            return $this->get_fallback_colors();
        }
    }

    /**
     * Get fallback colors from plugin settings
     *
     * @return array Color scheme
     */
    private function get_fallback_colors() {
        $appearance = get_option('antek_chat_appearance', array());

        return array(
            'primary' => isset($appearance['primary_color']) ? $appearance['primary_color'] : '#FF6B4A',
            'secondary' => isset($appearance['secondary_color']) ? $appearance['secondary_color'] : '#8FA68E',
            'text' => isset($appearance['text_color']) ? $appearance['text_color'] : '#2C2C2C',
            'accent' => isset($appearance['primary_color']) ? $appearance['primary_color'] : '#FF6B4A',
            'background' => isset($appearance['background_color']) ? $appearance['background_color'] : '#FDFBF6',
            'source' => 'custom'
        );
    }

    /**
     * Generate CSS variables from detected colors
     *
     * @return string CSS code
     */
    public function generate_css_variables() {
        try {
            $colors = $this->get_theme_colors();

            // Verify we have valid colors
            if (empty($colors) || !is_array($colors)) {
                return '';
            }

            $css = ":root {\n";
            if (isset($colors['primary'])) {
                $css .= "    --antek-primary: " . esc_attr($colors['primary']) . ";\n";
            }
            if (isset($colors['secondary'])) {
                $css .= "    --antek-secondary: " . esc_attr($colors['secondary']) . ";\n";
            }
            if (isset($colors['text'])) {
                $css .= "    --antek-text: " . esc_attr($colors['text']) . ";\n";
            }
            if (isset($colors['accent'])) {
                $css .= "    --antek-accent: " . esc_attr($colors['accent']) . ";\n";
            }
            if (isset($colors['background'])) {
                $css .= "    --antek-background: " . esc_attr($colors['background']) . ";\n";
            }
            $css .= "}\n";

            return $css;

        } catch (Exception $e) {
            error_log('Antek Chat: Color CSS generation failed: ' . $e->getMessage());
            return ''; // Return empty, let fallback handle it
        }
    }
}
