<?php
/**
 * Fonctions WordPress de gestion d'URLs
 *
 * Fonctions mockées :
 * - home_url() : URL de la home
 * - admin_url() : URL de l'admin
 * - plugin_dir_url() : URL du répertoire du plugin
 * - plugin_dir_path() : Chemin du répertoire du plugin
 * - add_query_arg() : Ajoute des paramètres à une URL
 * - trailingslashit() : Ajoute un slash final
 * - wp_redirect() : Redirige (no-op en test)
 */

if (!function_exists('home_url')) {
    function home_url($path = '') {
        return 'https://example.com' . $path;
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '', $scheme = 'admin') {
        return 'https://example.com/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return 'https://example.com/wp-content/plugins/payline/';
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return trailingslashit(dirname($file));
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit($string) {
        return rtrim($string, '/') . '/';
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg(...$args) {
        if (is_array($args[0])) {
            // add_query_arg(array('key' => 'value'), $url)
            $params = $args[0];
            $url = isset($args[1]) ? $args[1] : '';
        } else {
            // add_query_arg('key', 'value', $url)
            $params = array($args[0] => $args[1]);
            $url = isset($args[2]) ? $args[2] : '';
        }

        $parsed = parse_url($url);
        $query = array();

        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $query);
        }

        $query = array_merge($query, $params);

        $base = '';
        if (isset($parsed['scheme'])) {
            $base .= $parsed['scheme'] . '://';
        }
        if (isset($parsed['host'])) {
            $base .= $parsed['host'];
        }
        if (isset($parsed['path'])) {
            $base .= $parsed['path'];
        }

        return $base . '?' . http_build_query($query);
    }
}

if (!function_exists('wp_redirect')) {
    function wp_redirect($location, $status = 302) {
        // No-op en test (évite les headers already sent)
        return false;
    }
}
