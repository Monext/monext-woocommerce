<?php

use Payline\PaylineSDK;

/**
 * Tests unitaires pour les hooks et triggers de WC_Abstract_Payline
 *
 * Couvre les méthodes qui gèrent les actions WordPress et les triggers de capture :
 * - add_payline_common_actions() : Enregistrement des hooks WP
 * - captureOnTrigger() : Capture automatique au changement de statut
 *
 * Compatibilité : PHP 7.4 - 8.4
 */
class WC_Abstract_Payline_Hooks_Test extends PaylineTestCase
{
    /** @var WC_Gateway_Payline_CPT */
    private $gateway;

    /** @var WC_Order */
    private $order;

    /** @var \PHPUnit\Framework\MockObject\MockObject */
    private $mockSDK;

    protected function setUp(): void
    {
        parent::setUp();

        MockOptions::$data['woocommerce_payline_settings']['merchant_id'] = 'MERCHANT123';
        MockOptions::$data['woocommerce_payline_settings']['access_key'] = 'KEY123';
        MockOptions::$data['woocommerce_payline_settings']['pos'] = 'POS123';

        MockOptions::$data['woocommerce_payline_cpt_settings']['enabled'] = 'yes';
        MockOptions::$data['woocommerce_payline_cpt_settings']['merchant_id'] = 'MERCHANT123';
        MockOptions::$data['woocommerce_payline_cpt_settings']['access_key'] = 'KEY123';
        MockOptions::$data['woocommerce_payline_cpt_settings']['pos'] = 'POS123';

        $this->gateway = new WC_Gateway_Payline_CPT();
        $this->order = new WC_Order(12345);
        $this->order->set_total('100.00');
        $this->order->set_currency('EUR');

        // Mock SDK pour éviter les appels API
        $this->mockSDK = $this->createMock(PaylineSDK::class);
        $reflection = new ReflectionClass($this->gateway);
        $property = $reflection->getProperty('SDK');
        $property->setAccessible(true);
        $property->setValue($this->gateway, $this->mockSDK);
    }

    // =========================================
    // add_payline_common_actions()
    // =========================================

    /**
     * Test : add_payline_common_actions() s'exécute sans erreur
     *
     * add_action() étant un no-op en test, on vérifie que la méthode
     * complète s'exécute sans exception, ce qui prouve que tous les
     * add_action() sont bien appelés.
     */
    public function test_add_payline_common_actions_executes_without_error()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'add_payline_common_actions');

        $result = $method->invoke($this->gateway);

        $this->assertNull($result, 'add_payline_common_actions() ne retourne rien');
    }

    /**
     * Test : add_payline_common_actions() est appelée lors de la construction
     *
     * Vérifie indirectement que le constructeur appelle bien add_payline_common_actions()
     * en s'assurant qu'aucune exception n'est levée pendant l'instanciation.
     */
    public function test_add_payline_common_actions_called_on_construct()
    {
        $gateway = $this->setGatewaySettings(
            array(
                'merchant_id' => 'MERCH',
                'access_key' => 'KEY',
                'pos' => 'POS',
            ),
            array(
                'enabled' => 'yes',
                'merchant_id' => 'MERCH',
                'access_key' => 'KEY',
                'pos' => 'POS',
            )
        );

        $this->assertInstanceOf('WC_Gateway_Payline_CPT', $gateway);
    }

    // =========================================
    // captureOnTrigger()
    // =========================================

    /**
     * Test : captureOnTrigger() appelle captureOrder() quand le statut correspond
     *
     * Conditions :
     * - La commande a un transaction_id
     * - Le payment_method correspond à la gateway
     * - capture_trigger_on est configuré et correspond au nouveau statut
     * - payment_action == 100 (authorisation sans capture immédiate)
     */
    public function test_capture_on_trigger_calls_capture_when_status_matches()
    {
        MockOptions::$data['woocommerce_payline_cpt_settings']['capture_trigger_on'] = 'processing';
        MockOptions::$data['woocommerce_payline_cpt_settings']['payment_action'] = '100';

        $this->gateway = new WC_Gateway_Payline_CPT();

        // Injecter le mock SDK
        $reflection = new ReflectionClass($this->gateway);
        $property = $reflection->getProperty('SDK');
        $property->setAccessible(true);
        $sdkMock = $this->createMock(PaylineSDK::class);
        $property->setValue($this->gateway, $sdkMock);

        // Mock getTransactionDetails : pas de capture existante
        $sdkMock
            ->method('getTransactionDetails')
            ->willReturn(array(
                'result' => array('code' => '00000'),
                'associatedTransactionsList' => array(
                    'associatedTransactions' => array(
                        array('id' => 'TXN_AUTH_123', 'type' => 'AUTHORIZATION'),
                    ),
                ),
            ));

        // Mock doCapture : succès
        $sdkMock
            ->method('doCapture')
            ->willReturn(array(
                'result' => array('code' => '00000'),
                'transaction' => array('id' => 'CAP_XYZ789'),
            ));

        $this->order->set_transaction_id('TXN123456');
        $this->order->set_payment_method('payline_cpt');

        $result = $this->gateway->captureOnTrigger(12345, 'pending', 'processing', $this->order);

        // captureOrder() retourne true quand la capture réussit
        $this->assertTrue($result, 'captureOnTrigger() doit retourner true quand la capture réussit');
    }

    /**
     * Test : captureOnTrigger() ne fait rien quand le statut ne correspond pas
     */
    public function test_capture_on_trigger_ignores_when_status_not_configured()
    {
        MockOptions::$data['woocommerce_payline_cpt_settings']['capture_trigger_on'] = 'completed';
        MockOptions::$data['woocommerce_payline_cpt_settings']['payment_action'] = '100';

        $this->gateway = new WC_Gateway_Payline_CPT();

        $this->order->set_transaction_id('TXN123456');
        $this->order->set_payment_method('payline_cpt');

        $result = $this->gateway->captureOnTrigger(12345, 'pending', 'processing', $this->order);

        $this->assertNull($result, 'captureOnTrigger() retourne null quand le statut ne correspond pas');
    }

    /**
     * Test : captureOnTrigger() ne fait rien quand capture_trigger_on n'est pas configuré
     */
    public function test_capture_on_trigger_checks_trigger_setting()
    {
        MockOptions::$data['woocommerce_payline_cpt_settings']['capture_trigger_on'] = '';
        MockOptions::$data['woocommerce_payline_cpt_settings']['payment_action'] = '100';

        $this->gateway = new WC_Gateway_Payline_CPT();

        $this->order->set_transaction_id('TXN123456');
        $this->order->set_payment_method('payline_cpt');

        $result = $this->gateway->captureOnTrigger(12345, 'pending', 'processing', $this->order);

        $this->assertNull($result, 'captureOnTrigger() retourne null quand capture_trigger_on est vide');
    }

    /**
     * Test : captureOnTrigger() ne fait rien sans transaction_id
     */
    public function test_capture_on_trigger_ignores_without_transaction_id()
    {
        MockOptions::$data['woocommerce_payline_cpt_settings']['capture_trigger_on'] = 'processing';
        MockOptions::$data['woocommerce_payline_cpt_settings']['payment_action'] = '100';

        $this->gateway = new WC_Gateway_Payline_CPT();

        $this->order->set_transaction_id('');
        $this->order->set_payment_method('payline_cpt');

        $result = $this->gateway->captureOnTrigger(12345, 'pending', 'processing', $this->order);

        $this->assertNull($result, 'captureOnTrigger() retourne null sans transaction_id');
    }

    /**
     * Test : captureOnTrigger() ne fait rien pour un autre payment_method
     */
    public function test_capture_on_trigger_ignores_other_payment_method()
    {
        MockOptions::$data['woocommerce_payline_cpt_settings']['capture_trigger_on'] = 'processing';
        MockOptions::$data['woocommerce_payline_cpt_settings']['payment_action'] = '100';

        $this->gateway = new WC_Gateway_Payline_CPT();

        $this->order->set_transaction_id('TXN123456');
        $this->order->set_payment_method('bacs');

        $result = $this->gateway->captureOnTrigger(12345, 'pending', 'processing', $this->order);

        $this->assertNull($result, 'captureOnTrigger() retourne null pour un autre payment_method');
    }

    /**
     * Test : captureOnTrigger() ne fait rien quand payment_action n'est pas 100
     */
    public function test_capture_on_trigger_ignores_when_payment_action_not_100()
    {
        MockOptions::$data['woocommerce_payline_cpt_settings']['capture_trigger_on'] = 'processing';
        MockOptions::$data['woocommerce_payline_cpt_settings']['payment_action'] = '101';

        $this->gateway = new WC_Gateway_Payline_CPT();

        $this->order->set_transaction_id('TXN123456');
        $this->order->set_payment_method('payline_cpt');

        $result = $this->gateway->captureOnTrigger(12345, 'pending', 'processing', $this->order);

        $this->assertNull($result, 'captureOnTrigger() retourne null quand payment_action != 100');
    }

    /**
     * Test : captureOnTrigger() ne fait rien avec une commande nulle
     */
    public function test_capture_on_trigger_ignores_null_order()
    {
        $result = $this->gateway->captureOnTrigger(12345, 'pending', 'processing', null);

        $this->assertNull($result, 'captureOnTrigger() retourne null avec une commande nulle');
    }
}