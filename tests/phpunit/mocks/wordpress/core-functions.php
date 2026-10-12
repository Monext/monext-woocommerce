<?php
/**
 * Fonctions WordPress de base
 *
 * Fonctions mockées :
 * - get_option() : Récupération d'options (utilise MockOptions)
 * - update_option() : Mise à jour d'options (utilise MockOptions)
 * - get_bloginfo() : Informations du blog
 * - __() : Traduction i18n
 * - is_user_logged_in() : Vérification connexion utilisateur
 */

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        return MockOptions::$data[$key] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null) {
        MockOptions::$data[$option] = $value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($option) {
        unset(MockOptions::$data[$option]);
        return true;
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show) {
        return '6.8.3';
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in() {
        return false;  // Par défaut, utilisateur non connecté dans les tests
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return 1; // Mock user ID
    }
}

if (!function_exists('current_datetime')) {
    function current_datetime($timezone = 'server') {
        return new DateTime('now');
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($response = null) {
        MockWordPress::$data['wp_send_json_success'] = $response;
        die();
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($response = null) {
        MockWordPress::$data['wp_send_json_error'] = $response;
        die();
    }
}

if (!function_exists('wp_die')) {
    function wp_die($message = '') {
        MockWordPress::$data['wp_die_called'] = true;
        die();
    }
}
