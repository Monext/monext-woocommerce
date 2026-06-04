<?php
/**
 * Fonctions WordPress utilitaires
 *
 * Fonctions mockées :
 * - wp_parse_args() : Parse les arguments (array_merge)
 * - wp_parse_str() : Parse une query string
 * - get_plugins() : Liste des plugins (utilise MockPlugins)
 * - get_plugin_data() : Données d'un plugin (utilise MockPlugins)
 */

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = array()) {
        if (is_object($args)) {
            $parsed_args = get_object_vars($args);
        } elseif (is_array($args)) {
            $parsed_args = &$args;
        } else {
            wp_parse_str($args, $parsed_args);
        }
        return array_merge($defaults, $parsed_args);
    }
}

if (!function_exists('wp_parse_str')) {
    function wp_parse_str($input_string, &$result) {
        parse_str($input_string, $result);
    }
}

if (!function_exists('get_plugins')) {
    function get_plugins($path = '') {
        $plugins = [
            'payline/woocommerce-payline.php' => MockPlugins::$data['payline'],
            'woocommerce/woocommerce.php' => MockPlugins::$data['woocommerce'],
        ];

        if ($path === '') {
            return $plugins;
        }

        $pluginName = trim($path, '/');
        $key = $pluginName . '/' . $pluginName . '.php';
        if (isset($plugins[$key])) {
            return [$pluginName . '.php' => $plugins[$key]];
        }
        return [];
    }
}

if (!function_exists('get_plugin_data')) {
    function get_plugin_data($path) {
        return MockPlugins::$data['payline'];
    }
}
