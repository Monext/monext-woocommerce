<?php
/**
 * Mocks des classes Enum WooCommerce
 *
 * Classes mockées :
 * - OrderInternalStatus : Constantes de statuts de commande
 * - DraftOrders : Constantes pour les commandes brouillon
 */

// Mock WooCommerce OrderInternalStatus (Enum-like class)
if (!class_exists('MockOrderInternalStatus')) {
    class MockOrderInternalStatus {
        const CANCELLED = 'wc-cancelled';
        const REFUNDED = 'wc-refunded';
        const FAILED = 'wc-failed';
        const ON_HOLD = 'wc-on-hold';
        const PENDING = 'wc-pending';
    }
}

if (!class_exists('Automattic\WooCommerce\Enums\OrderInternalStatus')) {
    class_alias('MockOrderInternalStatus', 'Automattic\WooCommerce\Enums\OrderInternalStatus');
}

// Mock WooCommerce DraftOrders
if (!class_exists('MockDraftOrders')) {
    class MockDraftOrders {
        const DB_STATUS = 'wc-checkout-draft';
    }
}

if (!class_exists('Automattic\WooCommerce\Blocks\Domain\Services\DraftOrders')) {
    class_alias('MockDraftOrders', 'Automattic\WooCommerce\Blocks\Domain\Services\DraftOrders');
}

// Mock WC_Log_Handler_File
if (!class_exists('WC_Log_Handler_File')) {
    class WC_Log_Handler_File {
        public static function get_log_file_path($name) {
            return sys_get_temp_dir() . '/payline.log';
        }
    }
}

// Mock WooCommerce StoreApi OrderController
if (!class_exists('MockOrderController')) {
    class MockOrderController {
        public function update_order_from_cart(\WC_Order $order, $update_totals = true) {
            return $order;
        }

        public function create_order_from_cart() {
            add_filter('woocommerce_default_order_status', function() {
                return 'checkout-draft';
            });

            $order = new \WC_Order();
            $order->set_status('checkout-draft');
            $order->set_created_via('store-api');

            remove_filter('woocommerce_default_order_status', function() {
                return 'checkout-draft';
            });

            return $order;
        }
    }
}

if (!class_exists('Automattic\WooCommerce\StoreApi\Utilities\OrderController')) {
    class_alias('MockOrderController', 'Automattic\WooCommerce\StoreApi\Utilities\OrderController');
}
