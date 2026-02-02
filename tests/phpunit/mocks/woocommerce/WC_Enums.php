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
