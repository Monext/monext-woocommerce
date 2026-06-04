<?php
/**
 * Fonctions WordPress de contexte
 *
 * Fonctions mockées (dépendent de MockWordPress::$data) :
 * - is_admin() : Mode administration
 * - is_main_query() : Requête principale
 * - is_account_page() : Page compte utilisateur
 * - is_page() : Page WordPress
 * - in_the_loop() : Dans la boucle WordPress
 */

if (!function_exists('is_admin')) {
    function is_admin() {
        return MockWordPress::$data['is_admin'];
    }
}

if (!function_exists('is_main_query')) {
    function is_main_query() {
        return MockWordPress::$data['is_main_query'];
    }
}

if (!function_exists('is_account_page')) {
    function is_account_page() {
        return MockWordPress::$data['is_account_page'];
    }
}

if (!function_exists('is_page')) {
    function is_page() {
        return MockWordPress::$data['is_page'];
    }
}

if (!function_exists('in_the_loop')) {
    function in_the_loop() {
        return MockWordPress::$data['in_the_loop'];
    }
}
