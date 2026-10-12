<?php

/**
 * Tests unitaires pour WC_Abstract_Payline : Manipulation de commandes
 *
 * Teste les méthodes qui manipulent l'état d'une commande WooCommerce
 * (statuts, tokens, métadonnées). Ces méthodes sont indépendantes du SDK
 * Payline et testent uniquement la logique métier locale. Nécessite le
 * mock WC_Order défini dans bootstrap.php.
 *
 * Méthodes testées :
 * - getTokenOptionKey() : Génération clé d'option pour token de paiement
 * - can_refund_order() : Vérification possibilité de remboursement
 * - paylineSetOrderPayed() : Marquer commande comme payée
 * - paylineSetOrderOnHold() : Marquer commande en attente (fraude, validation)
 * - getArrayTokenForOrder() : Récupération token sérialisé depuis options
 */
class WC_Abstract_Payline_Order_Test extends PaylineTestCase
{
    /** @var WC_Gateway_Payline_CPT */
    private $gateway;

    /** @var WC_Order */
    private $order;

    protected function setUp(): void
    {
        parent::setUp();

        // Configurer les settings nécessaires pour paylineSetOrderPayed()
        MockOptions::$data['woocommerce_payline_settings']['payed_order_status'] = 'completed';
        MockOptions::$data['woocommerce_payline_cpt_settings']['payed_order_status'] = 'completed';

        $this->gateway = new WC_Gateway_Payline_CPT();
        $this->order = new WC_Order(12345);
    }

    // =========================================
    // getTokenOptionKey()
    // =========================================

    /**
     * @throws ReflectionException
     */
    public function test_get_token_option_key_returns_key_with_order_id()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getTokenOptionKey');

        $result = $method->invoke($this->gateway, $this->order);

        $this->assertEquals('plnTokenForOrder_12345', $result, 'La clé doit contenir l\'ID de la commande');
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_token_option_key_with_different_order_id()
    {
        $order = new WC_Order(99999);
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getTokenOptionKey');

        $result = $method->invoke($this->gateway, $order);

        $this->assertEquals('plnTokenForOrder_99999', $result, 'La clé doit contenir l\'ID 99999');
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_token_option_key_with_zero_order_id()
    {
        $order = new WC_Order(0);
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getTokenOptionKey');

        $result = $method->invoke($this->gateway, $order);

        // L'ID 0 génère un ID aléatoire dans le mock, donc on vérifie juste le format
        $this->assertStringStartsWith('plnTokenForOrder_', $result, 'La clé doit commencer par le préfixe attendu');
    }

    // =========================================
    // can_refund_order()
    // =========================================

    public function test_can_refund_order_returns_false_when_no_transaction_id()
    {
        // Order sans transaction_id
        $this->order->set_transaction_id('');

        $result = $this->gateway->can_refund_order($this->order);

        $this->assertFalse($result, 'Ne peut pas rembourser sans transaction ID');
    }

    public function test_can_refund_order_returns_true_when_transaction_id_present()
    {
        // can_refund_order() vérifie SEULEMENT le transaction_id
        // (le contract_number est récupéré mais pas utilisé dans la condition)
        $this->order->set_transaction_id('TXN123456');

        $result = $this->gateway->can_refund_order($this->order);

        $this->assertTrue($result, 'Peut rembourser avec transaction ID');
    }

    public function test_can_refund_order_returns_false_when_empty_transaction_id()
    {
        $this->order->set_transaction_id('');

        $result = $this->gateway->can_refund_order($this->order);

        $this->assertFalse($result, 'Ne peut pas rembourser sans transaction ID');
    }

    public function test_can_refund_order_with_contract_number_metadata()
    {
        // Vérifie que même avec contract_number, c'est le transaction_id qui compte
        $this->order->set_transaction_id('TXN123456');
        $this->order->update_meta_data('_contract_number', 'CONTRACT789');

        $result = $this->gateway->can_refund_order($this->order);

        $this->assertTrue($result, 'Peut rembourser avec transaction ID (contract_number non vérifié)');
    }

    // =========================================
    // paylineSetOrderPayed()
    // =========================================

    /**
     * @throws ReflectionException
     */
    public function test_payline_set_order_payed_changes_status_to_completed()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'paylineSetOrderPayed');

        $method->invoke($this->gateway, $this->order);

        $this->assertEquals('completed', $this->order->get_status(), 'Le statut doit passer à "completed"');
    }

    /**
     * @throws ReflectionException
     */
    public function test_payline_set_order_payed_on_pending_order()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'paylineSetOrderPayed');
        $this->order->update_status('pending');

        $method->invoke($this->gateway, $this->order);

        $this->assertEquals('completed', $this->order->get_status(), 'Le statut doit passer de "pending" à "completed"');
    }

    /**
     * @throws ReflectionException
     */
    public function test_payline_set_order_payed_on_processing_order()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'paylineSetOrderPayed');
        $this->order->update_status('processing');

        $method->invoke($this->gateway, $this->order);

        $this->assertEquals('completed', $this->order->get_status(), 'Le statut doit passer de "processing" à "completed"');
    }

    // =========================================
    // paylineSetOrderOnHold()
    // =========================================

    /**
     * @throws ReflectionException
     */
    public function test_payline_set_order_on_hold_changes_status_to_on_hold()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'paylineSetOrderOnHold');

        $method->invoke($this->gateway, $this->order);

        $this->assertEquals('on-hold', $this->order->get_status(), 'Le statut doit passer à "on-hold"');
    }

    /**
     * @throws ReflectionException
     */
    public function test_payline_set_order_on_hold_from_pending()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'paylineSetOrderOnHold');
        $this->order->update_status('pending');

        $method->invoke($this->gateway, $this->order);

        $this->assertEquals('on-hold', $this->order->get_status(), 'Le statut doit passer de "pending" à "on-hold"');
    }

    // =========================================
    // getArrayTokenForOrder()
    // =========================================

    /**
     * @throws ReflectionException
     */
    public function test_get_array_token_for_order_returns_null_when_no_token_stored()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getArrayTokenForOrder');

        $result = $method->invoke($this->gateway, $this->order);

        // getArrayTokenForOrder() retourne null (pas array vide) quand pas de token
        $this->assertNull($result, 'Doit retourner null quand aucun token n\'est stocké');
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_array_token_for_order_returns_token_when_stored()
    {
        // Stocker un token dans les options (avec la bonne clé)
        $tokenData = array(
            'token' => 'ABC123TOKEN',
            'expires' => 1704067200 + 3600,
            'cart_hash' => 'hash123',
        );
        MockOptions::$data['plnTokenForOrder_12345'] = json_encode($tokenData);

        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getArrayTokenForOrder');

        $result = $method->invoke($this->gateway, $this->order);

        $this->assertIsArray($result, 'Doit retourner un tableau');
        $this->assertArrayHasKey('token', $result, 'Le tableau doit contenir la clé "token"');
        $this->assertEquals('ABC123TOKEN', $result['token'], 'Le token doit correspondre');
        $this->assertArrayHasKey('expires', $result, 'Le tableau doit contenir la clé "expires"');
        $this->assertArrayHasKey('cart_hash', $result, 'Le tableau doit contenir la clé "cart_hash"');
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_array_token_for_order_returns_null_when_invalid_json()
    {
        // Stocker un JSON invalide
        MockOptions::$data['plnTokenForOrder_12345'] = 'invalid json {{{';

        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getArrayTokenForOrder');

        $result = $method->invoke($this->gateway, $this->order);

        // Retourne null quand JSON invalide
        $this->assertNull($result, 'Doit retourner null quand le JSON est invalide');
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_array_token_for_order_returns_null_when_not_array()
    {
        // Stocker une valeur qui n'est pas un array après décodage
        MockOptions::$data['plnTokenForOrder_12345'] = json_encode('string_value');

        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getArrayTokenForOrder');

        $result = $method->invoke($this->gateway, $this->order);

        // Retourne null quand décodé n'est pas un array
        $this->assertNull($result, 'Doit retourner null quand le JSON décodé n\'est pas un tableau');
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_array_token_for_order_with_complex_token_data()
    {
        $tokenData = array(
            'token' => 'COMPLEX_TOKEN_XYZ',
            'expires' => 1704067200,
            'cart_hash' => 'abcdef123456',
            'metadata' => array(
                'gateway' => 'payline_cpt',
                'version' => '1.0',
            ),
        );
        MockOptions::$data['plnTokenForOrder_12345'] = json_encode($tokenData);

        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getArrayTokenForOrder');

        $result = $method->invoke($this->gateway, $this->order);

        $this->assertIsArray($result);
        $this->assertCount(4, $result);
        $this->assertEquals('COMPLEX_TOKEN_XYZ', $result['token']);
        $this->assertEquals(1704067200, $result['expires']);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertIsArray($result['metadata']);
    }
}
