<?php
/**
 * Fonctions WordPress de hooks (actions/filters)
 *
 * Fonctions mockées (no-op en test) :
 * - add_action() : Ajoute une action
 * - do_action() : Exécute une action
 * - add_filter() : Ajoute un filtre
 * - apply_filters() : Applique des filtres
 */

if (!function_exists('add_action')) {
    function add_action($hook_name, $callback, $priority = 10, $accepted_args = 1) {
        // No-op en test
    }
}

if (!function_exists('do_action')) {
    function do_action($hook_name, ...$args) {
        // No-op en test
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1) {
        // No-op en test
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook_name, $value, ...$args) {
        return $value;
    }
}
