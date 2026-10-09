<?php

use Payline\PaylineSDK;

/**
 * Tests unitaires pour WC_Abstract_Payline : Paiements avec SDK Monext
 *
 * Teste les méthodes qui initient des paiements et gèrent les tokens via le SDK
 * Monext. Ces méthodes appellent doWebPayment() et nécessitent un mock du SDK
 * pour simuler les réponses API (succès/échec) sans appels réseau réels.
 *
 * Méthodes testées :
 * - getRawRedirectUrl() : Obtenir URL de redirection vers page de paiement
 * - getNewTokenForOrder() : Créer nouveau token de paiement pour commande
 * - process_refund() : Effectuer remboursement via doRefund() (compléter)
 */
class WC_Abstract_Payline_SDK_Payment_Test extends PaylineTestCase
{
    /** @var WC_Gateway_Payline_CPT */
    private $gateway;

    /** @var WC_Order */
    private $order;

    /** @var PHPUnit\Framework\MockObject\MockObject */
    private $mockSDK;

    protected function setUp(): void
    {
        parent::setUp();

        // Configurer les settings nécessaires
        MockOptions::$data['woocommerce_payline_cpt_settings']['merchant_id'] = 'MERCHANT123';
        MockOptions::$data['woocommerce_payline_cpt_settings']['access_key'] = 'ACCESS_KEY_123';
        MockOptions::$data['woocommerce_payline_cpt_settings']['contract_number'] = 'CONTRACT123';
        MockOptions::$data['woocommerce_payline_cpt_settings']['primary_contracts'] = array('CB-123', 'VISA-456');
        MockOptions::$data['woocommerce_payline_cpt_settings']['payment_action'] = '101';
        MockOptions::$data['woocommerce_payline_cpt_settings']['language'] = 'fr';

        $this->gateway = new WC_Gateway_Payline_CPT();

        // Créer commande de test
        $this->order = new WC_Order(12345);
        $this->order->set_total('100.00');
        $this->order->set_currency('EUR');
        $this->order->set_billing(array(
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean@example.com',
            'phone' => '0123456789',
            'address_1' => '123 Rue',
            'city' => 'Paris',
            'postcode' => '75001',
            'country' => 'FR',
        ));

        // Créer mock du SDK Payline
        $this->mockSDK = $this->createMock(PaylineSDK::class);

        // Injecter le mock dans la gateway
        $reflection = new ReflectionClass($this->gateway);
        $property = $reflection->getProperty('SDK');
        $property->setAccessible(true);
        $property->setValue($this->gateway, $this->mockSDK);
    }

    // =========================================
    // getRawRedirectUrl() - Succès
    // =========================================

    /**
     * @throws ReflectionException
     */
    public function test_get_raw_redirect_url_returns_cached_url_if_present()
    {
        // Mettre une URL en cache
        $cachedUrl = 'https://monext.com/cached/ABC123';
        $tokenData = array(
            'token' => 'CACHED_TOKEN',
            'redirectURL' => $cachedUrl,
        );
        MockOptions::$data['plnTokenForOrder_12345'] = json_encode($tokenData);

        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getRawRedirectUrl');

        // SDK ne devrait PAS être appelé (cache hit)
        $this->mockSDK->expects($this->never())->method('doWebPayment');

        $result = $method->invoke($this->gateway, $this->order->get_id());

        $this->assertEquals($cachedUrl, $result, 'getRawRedirectUrl() doit retourner l\'URL en cache');
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_raw_redirect_url_calls_doWebPayment_when_no_cache()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getRawRedirectUrl');

        // Mock doWebPayment() succès
        $this->mockSDK
            ->expects($this->once())
            ->method('doWebPayment')
            ->with($this->isType('array'))
            ->willReturn(array(
                'result' => array('code' => '00000', 'shortMessage' => 'ACCEPTED'),
                'redirectURL' => 'https://monext.com/pay/NEW123',
                'token' => 'NEW_TOKEN_123',
            ));

        $result = $method->invoke($this->gateway, $this->order->get_id());

        $this->assertEquals('https://monext.com/pay/NEW123', $result, 'getRawRedirectUrl() doit retourner l\'URL de redirection');
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_raw_redirect_url_saves_token_to_cache_on_success()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getRawRedirectUrl');

        $this->mockSDK
            ->method('doWebPayment')
            ->willReturn(array(
                'result' => array('code' => '00000', 'shortMessage' => 'ACCEPTED'),
                'redirectURL' => 'https://monext.com/pay/XYZ789',
                'token' => 'TOKEN_XYZ789',
            ));

        $method->invoke($this->gateway, $this->order->get_id());

        // Vérifier que le token a été sauvegardé
        $savedToken = MockOptions::$data['plnTokenForOrder_12345'];
        $this->assertNotEmpty($savedToken, 'Le token doit être sauvegardé en cache');

        $decodedToken = json_decode($savedToken, true);
        $this->assertEquals('TOKEN_XYZ789', $decodedToken['token'], 'Le token sauvegardé doit correspondre');
        $this->assertEquals('https://monext.com/pay/XYZ789', $decodedToken['redirectURL'], 'L\'URL de redirection doit être sauvegardée');
    }

    // =========================================
    // getRawRedirectUrl() - Erreurs
    // =========================================

    /**
     * @throws ReflectionException
     */
    public function test_get_raw_redirect_url_returns_error_url_when_api_fails()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getRawRedirectUrl');

        // Mock doWebPayment() échec
        $this->mockSDK
            ->method('doWebPayment')
            ->willReturn(array(
                'result' => array(
                    'code' => '99999',
                    'shortMessage' => 'TECHNICAL_ERROR',
                    'longMessage' => 'An error occurred during payment processing',
                ),
                'redirectURL' => '',
                'token' => '',
            ));

        $result = $method->invoke($this->gateway, $this->order->get_id());

        // Doit contenir l'URL d'erreur (contient 'order-cancelled' ou 'order-pay')
        $this->assertTrue(
            strpos($result, 'order-cancelled') !== false || strpos($result, 'order-pay') !== false,
            "Expected error URL to contain 'order-cancelled' or 'order-pay', got: $result"
        );
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_raw_redirect_url_does_not_cache_on_api_error()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getRawRedirectUrl');

        $this->mockSDK
            ->method('doWebPayment')
            ->willReturn(array(
                'result' => array(
                    'code' => '99999',
                    'shortMessage' => 'ERROR',
                    'longMessage' => 'XXXX'),
                'redirectURL' => '',
                'token' => '',
            ));

        $method->invoke($this->gateway, $this->order->get_id());

        // Token ne doit PAS être sauvegardé
        $this->assertArrayNotHasKey('plnTokenForOrder_12345', MockOptions::$data, 'Le token ne doit pas être sauvegardé en cas d\'erreur API');
    }

    // =========================================
    // getNewTokenForOrder() - Succès
    // =========================================

    /**
     * @throws ReflectionException
     */
    public function test_get_new_token_for_order_returns_token_on_success()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getNewTokenForOrder');

        $this->mockSDK
            ->expects($this->once())
            ->method('doWebPayment')
            ->willReturn(array(
                'result' => array('code' => '00000', 'shortMessage' => 'ACCEPTED'),
                'redirectURL' => 'https://monext.com/pay/TOKEN123',
                'token' => 'TOKEN_SUCCESS_123',
            ));

        $result = $method->invoke($this->gateway, $this->order);

        $this->assertEquals('TOKEN_SUCCESS_123', $result, 'getNewTokenForOrder() doit retourner le token');
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_new_token_for_order_saves_token_to_cache()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getNewTokenForOrder');

        $this->mockSDK
            ->method('doWebPayment')
            ->willReturn(array(
                'result' => array('code' => '00000'),
                'redirectURL' => 'https://monext.com/pay/ABC',
                'token' => 'CACHED_TOKEN_ABC',
            ));

        $method->invoke($this->gateway, $this->order);

        $savedToken = MockOptions::$data['plnTokenForOrder_12345'];
        $decodedToken = json_decode($savedToken, true);
        $this->assertEquals('CACHED_TOKEN_ABC', $decodedToken['token'], 'Le token sauvegardé doit correspondre');
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_new_token_for_order_calls_doWebPayment_with_correct_structure()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getNewTokenForOrder');

        // Vérifier la structure des paramètres
        $this->mockSDK
            ->expects($this->once())
            ->method('doWebPayment')
            ->with($this->callback(function ($params) {
                // Vérifier les sections principales
                $hasPayment = isset($params['payment']) && isset($params['payment']['amount']);
                $hasOrder = isset($params['order']) && isset($params['order']['ref']);
                $hasBuyer = isset($params['buyer']) && isset($params['buyer']['email']);
                $hasURLs = isset($params['returnURL']) && isset($params['cancelURL']);

                return $hasPayment && $hasOrder && $hasBuyer && $hasURLs;
            }))
            ->willReturn(array(
                'result' => array('code' => '00000'),
                'token' => 'TOKEN123',
                'redirectURL' => 'https://monext.com/pay',
            ));

        $method->invoke($this->gateway, $this->order);
    }

    // =========================================
    // getNewTokenForOrder() - Erreurs
    // =========================================

    /**
     * @throws ReflectionException
     */
    public function test_get_new_token_for_order_returns_null_on_api_error()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getNewTokenForOrder');

        $this->mockSDK
            ->method('doWebPayment')
            ->willReturn(array(
                'result' => array(
                    'code' => '02304',
                    'shortMessage' => 'INVALID_DATA',
                    'longMessage' => 'Invalid merchant credentials',
                ),
                'token' => '',
            ));

        $result = $method->invoke($this->gateway, $this->order);

        $this->assertNull($result, 'getNewTokenForOrder() doit retourner null en cas d\'erreur API');
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_new_token_for_order_does_not_cache_on_error()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getNewTokenForOrder');

        $this->mockSDK
            ->method('doWebPayment')
            ->willReturn(array(
                'result' => array(
                    'code' => '99999',
                    'shortMessage' => 'ERROR',
                    'longMessage' => 'XXXX'),
                'token' => '',
            ));

        $method->invoke($this->gateway, $this->order);

        // Pas de cache créé
        $this->assertArrayNotHasKey('plnTokenForOrder_12345', MockOptions::$data, 'Aucun cache ne doit être créé en cas d\'erreur');
    }

    // =========================================
    // process_refund() - Avec SDK mock
    // =========================================

    public function test_process_refund_calls_doRefund_with_correct_params()
    {
        $this->order->set_transaction_id('TXN_ORIGINAL_123');
        $this->order->update_meta_data('_contract_number', 'CONTRACT789');

        // Enregistrer l'ordre dans le cache wc_get_order()
        wc_get_order($this->order);

        // Mock doRefund()
        $this->mockSDK
            ->expects($this->once())
            ->method('doRefund')
            ->with($this->callback(function ($params) {
                return $params['transactionID'] === 'TXN_ORIGINAL_123'
                    && (int)$params['payment']['amount'] === 5000  // 50.00 EUR en centimes (accepte float/int)
                    && $params['payment']['contractNumber'] === 'CONTRACT789'
                    && $params['comment'] === 'Customer request';
            }))
            ->willReturn(array(
                'result' => array('code' => '00000', 'shortMessage' => 'ACCEPTED'),
                'transaction' => array('id' => 'REFUND_ID_123'),
            ));

        $result = $this->gateway->process_refund($this->order->get_id(), 50.00, 'Customer request');

        $this->assertTrue($result, 'process_refund() doit retourner true en cas de succès');
    }

    public function test_process_refund_returns_true_on_success()
    {
        $this->order->set_transaction_id('TXN123');
        $this->order->update_meta_data('_contract_number', 'CONTRACT123');

        // Enregistrer l'ordre dans le cache
        wc_get_order($this->order);

        $this->mockSDK
            ->method('doRefund')
            ->willReturn(array(
                'result' => array('code' => '00000'),
                'transaction' => array('id' => 'REFUND_OK'),
            ));

        $result = $this->gateway->process_refund($this->order->get_id(), 25.00, 'Refund');

        $this->assertTrue($result, 'process_refund() doit retourner true quand le remboursement est accepté');
    }

    public function test_process_refund_returns_wp_error_on_api_failure()
    {
        $this->order->set_transaction_id('TXN123');
        $this->order->update_meta_data('_contract_number', 'CONTRACT123');

        // Enregistrer l'ordre dans le cache
        wc_get_order($this->order);

        $this->mockSDK
            ->method('doRefund')
            ->willReturn(array(
                'result' => array(
                    'code' => '02531',
                    'shortMessage' => 'FAILED',
                    'longMessage' => 'Refund amount exceeds original transaction amount',
                ),
                'transaction' => array('id' => ''),
            ));

        $result = $this->gateway->process_refund($this->order->get_id(), 500.00, 'Refund');

        $this->assertInstanceOf('WP_Error', $result, 'process_refund() doit retourner WP_Error en cas d\'échec API');
        $this->assertEquals('error', $result->get_error_code(), 'Le code d\'erreur doit être "error"');
        $this->assertStringContainsString('exceeds original transaction', $result->get_error_message(), 'Le message d\'erreur doit mentionner le dépassement de montant');
    }
}
