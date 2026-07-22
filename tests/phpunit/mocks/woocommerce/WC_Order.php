<?php
/**
 * Mock WC_Order pour les tests
 *
 * Permet de tester les méthodes qui manipulent des commandes WooCommerce
 * sans charger WooCommerce complet.
 *
 * Architecture avec Traits :
 * - BillingAddressTrait : 10 méthodes get_billing_*()
 * - ShippingAddressTrait : 9 méthodes get_shipping_*()
 *
 * Propriétés simulées :
 * - id, status, total, currency, transaction_id
 * - payment_method, payment_method_title
 * - meta_data, items, order_notes
 * - billing/shipping (via traits)
 */

require_once __DIR__ . '/traits/BillingAddressTrait.php';
require_once __DIR__ . '/traits/ShippingAddressTrait.php';

if (!class_exists('WC_Order')) {
    class WC_Order {
        use BillingAddressTrait;
        use ShippingAddressTrait;

        private $id;
        private $status = 'pending';
        private $total = '100.00';
        private $currency = 'EUR';
        private $transaction_id = '';
        private $payment_method = '';
        private $payment_method_title = '';
        private $meta_data = array();
        private $items = array();
        private $user_id = 1;
        private $order_notes = array();
        private $created_via = '';

        public function __construct($order_id = 0) {
            $this->id = $order_id > 0 ? $order_id : rand(1000, 9999);
        }

        // ========================================================================
        // ID ET BASIQUES
        // ========================================================================

        public function get_id() {
            return $this->id;
        }

        public function set_id($id) {
            $this->id = $id;
        }

        public function get_status() {
            return $this->status;
        }

        public function update_status($new_status, $note = '', $manual = false) {
            $this->status = $new_status;
            if ($note) {
                $this->add_order_note($note);
            }
            return true;
        }

        public function set_status($status) {
            $this->status = $status;
        }

        public function set_created_via($channel) {
            $this->created_via = $channel;
        }

        public function get_created_via() {
            return $this->created_via;
        }

        public function calculate_totals() {
        }

        // ========================================================================
        // MONTANTS
        // ========================================================================

        public function get_total() {
            return $this->total;
        }

        public function set_total($amount) {
            $this->total = $amount;
        }

        public function get_total_tax() {
            return '20.00';
        }

        public function get_cart_tax() {
            return '15.00';
        }

        public function get_shipping_total() {
            return '5.00';
        }

        public function get_shipping_tax() {
            return '1.00';
        }

        public function get_currency() {
            return $this->currency;
        }

        public function set_currency($currency) {
            $this->currency = $currency;
        }

        // ========================================================================
        // TRANSACTION
        // ========================================================================

        public function get_transaction_id() {
            return $this->transaction_id;
        }

        public function set_transaction_id($transaction_id) {
            $this->transaction_id = $transaction_id;
            return true;
        }

        // ========================================================================
        // MÉTHODE DE PAIEMENT
        // ========================================================================

        public function get_payment_method() {
            return $this->payment_method;
        }

        public function set_payment_method($payment_method) {
            $this->payment_method = $payment_method;
        }

        public function get_payment_method_title() {
            return $this->payment_method_title;
        }

        public function set_payment_method_title($title) {
            $this->payment_method_title = $title;
        }

        public function payment_complete($transaction_id = '') {
            if ($transaction_id) {
                $this->set_transaction_id($transaction_id);
            }
            $this->update_status('processing', 'Payment complete');
            return true;
        }

        // ========================================================================
        // MÉTADONNÉES
        // ========================================================================

        public function get_meta($key, $single = true, $context = 'view') {
            return isset($this->meta_data[$key]) ? $this->meta_data[$key] : '';
        }

        public function update_meta_data($key, $value) {
            $this->meta_data[$key] = $value;
        }

        public function add_meta_data($key, $value, $unique = false) {
            if ($unique && isset($this->meta_data[$key])) {
                return;
            }
            $this->meta_data[$key] = $value;
        }

        public function delete_meta_data($key) {
            unset($this->meta_data[$key]);
        }

        // ========================================================================
        // ADRESSES BILLING & SHIPPING
        // ========================================================================
        // Toutes les méthodes get_billing_*() et get_shipping_*() sont fournies
        // par les traits BillingAddressTrait et ShippingAddressTrait

        // ========================================================================
        // ARTICLES & USER
        // ========================================================================

        public function get_items($types = 'line_item') {
            return $this->items;
        }

        public function set_items($items) {
            $this->items = $items;
        }

        public function get_user_id($context = 'view') {
            return $this->user_id;
        }

        public function set_user_id($user_id) {
            $this->user_id = $user_id;
        }

        // ========================================================================
        // URLS
        // ========================================================================

        public function get_checkout_order_received_url() {
            return 'https://example.com/checkout/order-received/' . $this->id;
        }

        public function get_cancel_order_url($redirect = '') {
            return 'https://example.com/checkout/order-cancelled/' . $this->id;
        }

        // ========================================================================
        // NOTES
        // ========================================================================

        public function add_order_note($note, $is_customer_note = 0, $added_by_user = false) {
            $this->order_notes[] = array(
                'content' => $note,
                'customer_note' => $is_customer_note,
                'added_by' => $added_by_user,
            );
        }

        public function get_order_notes() {
            return $this->order_notes;
        }

        // ========================================================================
        // SAVE
        // ========================================================================

        public function save() {
            return $this->id;
        }
    }
}
