<?php
/**
 * Mock WP_Error pour les tests
 *
 * Classe WordPress standard pour la gestion d'erreurs.
 * Utilisée par process_refund() et autres méthodes de gestion d'erreurs.
 */
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $errors = array();
        private $error_data = array();

        public function __construct($code = '', $message = '', $data = '') {
            if (!empty($code)) {
                $this->errors[$code][] = $message;
                if (!empty($data)) {
                    $this->error_data[$code] = $data;
                }
            }
        }

        public function get_error_code() {
            $codes = array_keys($this->errors);
            return empty($codes) ? '' : $codes[0];
        }

        public function get_error_message($code = '') {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            return isset($this->errors[$code][0]) ? $this->errors[$code][0] : '';
        }

        public function get_error_data($code = '') {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            return isset($this->error_data[$code]) ? $this->error_data[$code] : null;
        }

        public function add($code, $message, $data = '') {
            $this->errors[$code][] = $message;
            if (!empty($data)) {
                $this->error_data[$code] = $data;
            }
        }
    }
}
