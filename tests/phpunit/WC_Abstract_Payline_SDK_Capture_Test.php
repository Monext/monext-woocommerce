<?php

use PHPUnit\Framework\MockObject\MockObject;
use Payline\PaylineSDK;

/**
 * Tests unitaires pour captureOrder() de WC_Abstract_Payline
 *
 * Couvre la capture de montants autorisés via l'API Monext.
 * Tests nécessitent le mock du SDK Payline pour éviter les appels API réels.
 *
 * Méthode testée :
 * - captureOrder() : Capture d'un montant après autorisation
 *
 * Compatibilité : PHP 7.4 - 8.4
 */
class WC_Abstract_Payline_SDK_Capture_Test extends PaylineTestCase
{
    /** @var WC_Gateway_Payline_CPT */
    private $gateway;

    /** @var WC_Order */
    private $order;

    /** @var MockObject */
    private $mockSDK;

    protected function setUp(): void
    {
        parent::setUp();

        // Configurer settings minimaux
        MockOptions::$data['woocommerce_payline_settings']['merchant_id'] = 'MERCHANT123';
        MockOptions::$data['woocommerce_payline_settings']['access_key'] = 'KEY123';
        MockOptions::$data['woocommerce_payline_settings']['contract_number_list'] = 'CB';
        MockOptions::$data['woocommerce_payline_settings']['default_pos'] = 'POS123';

        MockOptions::$data['woocommerce_payline_cpt_settings']['enabled'] = 'yes';
        MockOptions::$data['woocommerce_payline_cpt_settings']['merchant_id'] = 'MERCHANT123';
        MockOptions::$data['woocommerce_payline_cpt_settings']['access_key'] = 'KEY123';
        MockOptions::$data['woocommerce_payline_cpt_settings']['contract_number_list'] = 'CB';
        MockOptions::$data['woocommerce_payline_cpt_settings']['default_pos'] = 'POS123';

        $this->gateway = new WC_Gateway_Payline_CPT();
        $this->order = new WC_Order(12345);
        $this->order->set_total('100.00');
        $this->order->set_currency('EUR');
        $this->order->set_transaction_id('TXN_AUTH_123');

        // Créer mock du SDK Payline
        $this->mockSDK = $this->createMock(PaylineSDK::class);

        // Injecter le mock dans la gateway
        $reflection = new ReflectionClass($this->gateway);
        $property = $reflection->getProperty('SDK');
        $property->setAccessible(true);
        $property->setValue($this->gateway, $this->mockSDK);
    }

    // -------------------------------
    // captureOrder() - Succès
    // -------------------------------

    /**
     * Test : captureOrder() réussit quand aucune capture n'existe
     *
     * Scénario :
     * 1. getTransactionDetails() retourne historique sans CAPTURE
     * 2. doCapture() retourne code 00000
     * 3. Résultat : return true + note ajoutée
     */
    public function test_capture_order_succeeds_when_no_existing_capture()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'captureOrder');

        // Mock getTransactionDetails() : Seulement AUTHORIZATION
        $this->mockSDK
            ->expects($this->once())
            ->method('getTransactionDetails')
            ->with($this->callback(function ($params) {
                return $params['transactionId'] === 'TXN_AUTH_123'
                    && $params['transactionHistory'] === 'Y';
            }))
            ->willReturn(array(
                'result' => array('code' => '00000'),
                'associatedTransactionsList' => array(
                    'associatedTransactions' => array(
                        array(
                            'id' => 'TXN_AUTH_123',
                            'type' => 'AUTHORIZATION',
                            'amount' => 10000,
                            'date' => '05/02/2026',
                        ),
                    ),
                ),
            ));

        // Mock doCapture() : Succès
        $this->mockSDK
            ->expects($this->once())
            ->method('doCapture')
            ->with($this->callback(function ($params) {
                return $params['transactionID'] === 'TXN_AUTH_123'
                    && (int)$params['payment']['amount'] === 10000
                    && $params['payment']['currency'] === '978'  // EUR
                    && $params['payment']['action'] === 201;     // Capture
            }))
            ->willReturn(array(
                'result' => array(
                    'code' => '00000',
                    'longMessage' => 'Capture successful',
                ),
                'transaction' => array('id' => 'CAP_XYZ789'),
            ));

        $result = $method->invoke($this->gateway, $this->order);

        $this->assertTrue($result, 'captureOrder() doit retourner true en cas de succès');
        $this->assertCount(1, $this->order->get_order_notes(), 'Une note doit être ajoutée après une capture réussie');
        $note = $this->order->get_order_notes()[0]['content'];
        $this->assertTrue(strpos($note, 'CAP_XYZ789') !== false, 'La note doit contenir l\'ID de capture');
    }

    /**
     * Test : captureOrder() n'appelle pas doCapture si capture existe déjà
     *
     * Scénario :
     * 1. getTransactionDetails() retourne historique avec CAPTURE
     * 2. Résultat : return sans appeler doCapture()
     */
    public function test_capture_order_returns_early_when_capture_already_exists()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'captureOrder');

        // Mock getTransactionDetails() : AUTHORIZATION + CAPTURE
        $this->mockSDK
            ->expects($this->once())
            ->method('getTransactionDetails')
            ->with($this->anything())
            ->willReturn(array(
                'result' => array('code' => '00000'),
                'associatedTransactionsList' => array(
                    'associatedTransactions' => array(
                        array(
                            'id' => 'TXN_AUTH_123',
                            'type' => 'AUTHORIZATION',
                            'amount' => 10000,
                            'date' => '05/02/2026',
                        ),
                        array(
                            'id' => 'CAP_EXISTING_456',
                            'type' => 'CAPTURE',
                            'amount' => 10000,
                            'date' => '05/02/2026',
                        ),
                    ),
                ),
            ));

        // doCapture() NE DOIT PAS être appelé
        $this->mockSDK
            ->expects($this->never())
            ->method('doCapture');

        $result = $method->invoke($this->gateway, $this->order);

        // Pas de valeur de retour explicite dans le code (return vide)
        $this->assertNull($result, 'captureOrder() doit retourner null quand une capture existe déjà');
        $this->assertCount(0, $this->order->get_order_notes(), 'Aucune note ne doit être ajoutée quand une capture existe déjà');
    }

    // -------------------------------
    // captureOrder() - Erreurs
    // -------------------------------

    /**
     * Test : captureOrder() retourne false quand doCapture échoue
     *
     * Scénario :
     * 1. getTransactionDetails() OK
     * 2. doCapture() retourne code erreur
     * 3. Résultat : return false + note erreur
     */
    public function test_capture_order_returns_false_when_api_fails()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'captureOrder');

        // Mock getTransactionDetails() : Seulement AUTHORIZATION
        $this->mockSDK
            ->expects($this->once())
            ->method('getTransactionDetails')
            ->with($this->anything())
            ->willReturn(array(
                'result' => array('code' => '00000'),
                'associatedTransactionsList' => array(
                    'associatedTransactions' => array(
                        array('id' => 'TXN_AUTH_123', 'type' => 'AUTHORIZATION'),
                    ),
                ),
            ));

        // Mock doCapture() : Échec
        $this->mockSDK
            ->expects($this->once())
            ->method('doCapture')
            ->with($this->anything())
            ->willReturn(array(
                'result' => array(
                    'code' => '02304',
                    'longMessage' => 'Insufficient funds',
                ),
            ));

        $result = $method->invoke($this->gateway, $this->order);

        $this->assertFalse($result, 'captureOrder() doit retourner false en cas d\'erreur API');
        $this->assertCount(1, $this->order->get_order_notes(), 'Une note d\'erreur doit être ajoutée');
        $note = $this->order->get_order_notes()[0]['content'];
        $this->assertTrue(strpos($note, 'Capture error') !== false, 'La note doit mentionner "Capture error"');
        $this->assertTrue(strpos($note, 'Insufficient funds') !== false, 'La note doit contenir le message d\'erreur de l\'API');
    }

    /**
     * Test : captureOrder() envoie le montant en centimes
     *
     * Vérifie la conversion : 100.00 EUR → 10000 centimes
     */
    public function test_capture_order_converts_amount_to_cents()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'captureOrder');

        $this->order->set_total('150.50');

        // Mock getTransactionDetails()
        $this->mockSDK
            ->expects($this->once())
            ->method('getTransactionDetails')
            ->willReturn(array(
                'result' => array('code' => '00000'),
                'associatedTransactionsList' => array(
                    'associatedTransactions' => array(
                        array('id' => 'TXN_AUTH_123', 'type' => 'AUTHORIZATION'),
                    ),
                ),
            ));

        // Mock doCapture() : Vérifier le montant en centimes
        $this->mockSDK
            ->expects($this->once())
            ->method('doCapture')
            ->with($this->callback(function ($params) {
                // 150.50 EUR = 15050 centimes
                return (int)$params['payment']['amount'] === 15050;
            }))
            ->willReturn(array(
                'result' => array('code' => '00000'),
                'transaction' => array('id' => 'CAP_789'),
            ));

        $result = $method->invoke($this->gateway, $this->order);

        $this->assertTrue($result, 'captureOrder() doit retourner true avec un montant converti en centimes');
    }

    /**
     * Test : captureOrder() envoie l'action 201 (code Monext pour capture)
     */
    public function test_capture_order_sends_action_201()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'captureOrder');

        // Mock getTransactionDetails()
        $this->mockSDK
            ->expects($this->once())
            ->method('getTransactionDetails')
            ->willReturn(array(
                'result' => array('code' => '00000'),
                'associatedTransactionsList' => array(
                    'associatedTransactions' => array(
                        array('id' => 'TXN_AUTH_123', 'type' => 'AUTHORIZATION'),
                    ),
                ),
            ));

        // Mock doCapture() : Vérifier action = 201
        $this->mockSDK
            ->expects($this->once())
            ->method('doCapture')
            ->with($this->callback(function ($params) {
                return $params['payment']['action'] === 201;
            }))
            ->willReturn(array(
                'result' => array('code' => '00000'),
                'transaction' => array('id' => 'CAP_789'),
            ));

        $result = $method->invoke($this->gateway, $this->order);

        $this->assertTrue($result, 'captureOrder() doit retourner true avec l\'action 201');
    }

    /**
     * Test : captureOrder() envoie la devise correcte (EUR = 978)
     */
    public function test_capture_order_sends_correct_currency_code()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'captureOrder');

        // Mock getTransactionDetails()
        $this->mockSDK
            ->expects($this->once())
            ->method('getTransactionDetails')
            ->willReturn(array(
                'result' => array('code' => '00000'),
                'associatedTransactionsList' => array(
                    'associatedTransactions' => array(
                        array('id' => 'TXN_AUTH_123', 'type' => 'AUTHORIZATION'),
                    ),
                ),
            ));

        // Mock doCapture() : Vérifier devise EUR = 978
        $this->mockSDK
            ->expects($this->once())
            ->method('doCapture')
            ->with($this->callback(function ($params) {
                return $params['payment']['currency'] === '978';  // EUR
            }))
            ->willReturn(array(
                'result' => array('code' => '00000'),
                'transaction' => array('id' => 'CAP_789'),
            ));

        $result = $method->invoke($this->gateway, $this->order);

        $this->assertTrue($result, 'captureOrder() doit retourner true avec la devise EUR (978)');
    }
}
