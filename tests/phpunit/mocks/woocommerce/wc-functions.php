<?php
/**
 * Fonctions WooCommerce de base
 *
 * Fonctions mockées :
 * - wc_add_notice() : Ajoute une notice WooCommerce (no-op)
 * - wc_get_cart_url() : URL du panier
 * - wc_get_checkout_url() : URL du checkout
 * - wc_get_order_statuses() : Liste des statuts de commande
 * - wc_get_order() : Récupère une commande (avec cache)
 * - WC() : Objet global WooCommerce
 */

if (!function_exists('wc_add_notice')) {
    function wc_add_notice($message, $type = 'success') {
        // No-op en test
    }
}

if (!function_exists('wc_get_cart_url')) {
    function wc_get_cart_url() {
        return 'https://example.com/cart';
    }
}

if (!function_exists('wc_get_checkout_url')) {
    function wc_get_checkout_url() {
        return 'https://example.com/checkout';
    }
}

if (!function_exists('wc_get_order_statuses')) {
    function wc_get_order_statuses() {
        return array(
            'wc-pending' => 'Pending payment',
            'wc-processing' => 'Processing',
            'wc-on-hold' => 'On hold',
            'wc-completed' => 'Completed',
            'wc-cancelled' => 'Cancelled',
            'wc-refunded' => 'Refunded',
            'wc-failed' => 'Failed',
        );
    }
}

/**
 * Fonction globale wc_get_order avec cache pour les tests
 */
if (!function_exists('wc_get_order')) {
    function wc_get_order($order_id) {
        static $order_cache = array();

        if ($order_id instanceof WC_Order) {
            // Sauvegarder dans le cache
            $order_cache[$order_id->get_id()] = $order_id;
            return $order_id;
        }

        // Retourner du cache si disponible
        if (isset($order_cache[$order_id])) {
            return $order_cache[$order_id];
        }

        // Créer nouveau et mettre en cache
        $order = new WC_Order($order_id);
        $order_cache[$order_id] = $order;
        return $order;
    }
}

/**
 * Fonction WC() pour WooCommerce global
 */
if (!function_exists('WC')) {
    function WC() {
        static $wc = null;
        if ($wc === null) {
            $wc = new stdClass();
            // Mock cart avec get_cart_hash()
            $wc->cart = new MockWcCart();
            $wc->session = new stdClass();
        }
        return $wc;
    }
}

/**
 * Mock du cart WooCommerce
 */
class MockWcCart {
    /**
     * @var array Liste des items du panier
     */
    public static $cart_items = [];

    public function get_cart_hash() {
        return md5('test_cart_hash');
    }

    /**
     * Retourne les items du panier
     *
     * @return array
     */
    public function get_cart() {
        return self::$cart_items;
    }
}
