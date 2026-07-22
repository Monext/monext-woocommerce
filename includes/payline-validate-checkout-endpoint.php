<?php

class PaylineValidateCheckoutEndpoint extends WC_Checkout
{

    public function handle_request() {
        // Vérifier le nonce
        check_ajax_referer('payline_checkout_validator', 'nonce');

        $errors = $this->validate();
        if (!empty($errors)) {
            wp_send_json_error(array('message' => implode(' ', $errors)));
            return;
        }

        wp_send_json_success(true);
    }

    public function validate()
    {
        $errors = new WP_Error();

        // And we must call get_posted_data because it handles the shipping address.
        $data = $this->get_posted_data();
        // It throws some notices when checking fields etc., also from other plugins via hooks.
        // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        @$this->validate_checkout($data, $errors);

        remove_filter('woocommerce_is_checkout', true);

        // Retourne les messages d'erreur (tableau vide si pas d'erreurs)
        return $errors->get_error_messages();
    }
}