<?php
/**
 * Fonctions WordPress d'enqueue (scripts/styles)
 *
 * Fonctions mockées (enregistrent dans MockWordPress::$data) :
 * - wp_enqueue_style() : Enregistre un style CSS
 * - wp_enqueue_script() : Enregistre un script JS
 * - load_template() : Charge un template PHP
 */

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style($handle, $src = '', $deps = [], $ver = false, $media = 'all') {
        MockWordPress::$data['enqueued_styles'][$handle] = [
            'src' => $src,
            'deps' => $deps,
            'ver' => $ver,
            'media' => $media
        ];
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script($handle, $src = '', $deps = [], $ver = false, $in_footer = false) {
        MockWordPress::$data['enqueued_scripts'][$handle] = [
            'src' => $src,
            'deps' => $deps,
            'ver' => $ver,
            'in_footer' => $in_footer
        ];
    }
}

if (!function_exists('load_template')) {
    function load_template($template_file, $require_once = true, $args = []) {
        MockWordPress::$data['loaded_templates'][] = [
            'file' => $template_file,
            'require_once' => $require_once,
            'args' => $args
        ];
    }
}
