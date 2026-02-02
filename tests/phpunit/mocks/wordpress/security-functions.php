<?php
/**
 * Fonctions WordPress de sécurité
 *
 * Fonctions mockées :
 * - wp_create_nonce() : Crée un nonce
 * - wp_nonce_url() : Ajoute un nonce à une URL
 * - esc_attr() : Échappe un attribut HTML
 * - wp_kses_post() : Filtre du contenu (no-op en test)
 */

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        return 'test_nonce_' . md5($action);
    }
}

if (!function_exists('wp_nonce_url')) {
    function wp_nonce_url($actionurl, $action = -1, $name = '_wpnonce') {
        $nonce = wp_create_nonce($action);
        $separator = (strpos($actionurl, '?') !== false) ? '&' : '?';
        return $actionurl . $separator . $name . '=' . $nonce;
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($data) {
        return $data; // Pas de filtrage en test
    }
}
