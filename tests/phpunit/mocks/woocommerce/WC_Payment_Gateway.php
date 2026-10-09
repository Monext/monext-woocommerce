<?php
/**
 * Mock WC_Payment_Gateway
 *
 * Classe parent WooCommerce pour toutes les passerelles de paiement.
 * Fournit les méthodes de base pour settings, form fields, etc.
 */
if (!class_exists('WC_Payment_Gateway')) {
    class WC_Payment_Gateway {
        public $id = '';
        public $title = '';
        public $description = '';
        public $enabled = 'yes';
        public $method_title = '';
        public $method_description = '';
        public $has_fields = false;
        public $supports = array('products');
        public $icon = '';
        public $form_fields = array();
        public $settings = array();
        protected $plugin_id = 'woocommerce_';

        public function __construct() {
            $this->init_settings();
        }

        public function init_settings() {
            // Charge les settings depuis MockOptions
            $option_key = 'woocommerce_' . $this->id . '_settings';
            $this->settings = get_option($option_key, array());
        }

        public function init_form_fields() {
            $this->form_fields = array();
        }

        public function get_option($key, $empty_value = null) {
            if (isset($this->settings[$key])) {
                return $this->settings[$key];
            }
            return $empty_value;
        }

        public function generate_settings_html($form_fields = array(), $echo = true) {
            $html = '<table class="form-table">';
            foreach ($form_fields as $key => $field) {
                $html .= '<tr><th>' . ($field['title'] ?? $key) . '</th><td></td></tr>';
            }
            $html .= '</table>';
            if ($echo) {
                echo $html;
            }
            return $html;
        }

        protected function get_field_key($key) {
            return $this->plugin_id . $this->id . '_' . $key;
        }

        public function get_option_key() {
            return $this->plugin_id . $this->id . '_settings';
        }

        public function get_tooltip_html($data) {
            return '';
        }

        public function validate_multiselect_field($key, $value) {
            return is_array($value) ? $value : array();
        }

        public function process_admin_options() {
            // No-op en test
        }

        public function is_available() {
            return $this->enabled === 'yes';
        }
    }
}

/**
 * Mock de WC_Gateway_Payline : supprimé
 *
 * La vraie classe est maintenant chargée dans bootstrap.php.
 * createManageWebWallet() est implémenté dans WC_Abstract_Payline.
 */
