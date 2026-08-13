<?php

/**
 * Tests unitaires pour WC_Abstract_Recurring_Payline_NX
 *
 * Utilise WC_Gateway_Payline_NX comme instance concrète.
 *
 * Compatibilité : PHP 7.4 - 8.4
 */
class WC_Abstract_Recurring_Payline_NX_Test extends PaylineTestCase
{
    /** @var WC_Gateway_Payline_NX */
    private $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        MockOptions::$data['woocommerce_payline_settings'] = [
            'merchant_id' => 'MERCH123',
            'access_key' => 'ACCESS_KEY',
            'environment' => 'Homologation',
            'pos' => 'POS1',
        ];

        MockOptions::$data['woocommerce_payline_nx_settings'] = [
            'enabled' => 'yes',
            'title' => 'Paiement NX',
            'primary_contracts' => ['CB-123456'],
            'billing_left' => '3',
            'billing_cycle' => '40',
        ];

        $this->gateway = new WC_Gateway_Payline_NX();
    }

    // =========================================
    // init_form_fields()
    // =========================================

    public function test_init_form_fields_includes_billing_cycle()
    {
        $this->assertArrayHasKey('billing_cycle', $this->gateway->form_fields, 'Le champ billing_cycle doit exister');
        $this->assertEquals('40', $this->gateway->form_fields['billing_cycle']['default'], 'La valeur par défaut doit être 40');
    }

    public function test_init_form_fields_billing_cycle_has_9_options()
    {
        $this->assertArrayHasKey('billing_cycle', $this->gateway->form_fields, 'Le champ billing_cycle doit exister');
        $options = $this->gateway->form_fields['billing_cycle']['options'];
        $this->assertCount(9, $options, 'Il doit y avoir 9 options de cycle');
    }

    // =========================================
    // getDaysForCycles()
    // =========================================

    public function test_get_days_for_cycles_returns_1_for_code_10()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Recurring_Payline_NX::class, 'getDaysForCycles');
        $result = $method->invoke($this->gateway, '10');

        $this->assertEquals(1, $result, 'Le code 10 doit retourner 1 jour');
    }

    public function test_get_days_for_cycles_returns_30_for_code_40()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Recurring_Payline_NX::class, 'getDaysForCycles');
        $result = $method->invoke($this->gateway, '40');

        $this->assertEquals(30, $result, 'Le code 40 doit retourner 30 jours');
    }

    public function test_get_days_for_cycles_returns_720_for_code_90()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Recurring_Payline_NX::class, 'getDaysForCycles');
        $result = $method->invoke($this->gateway, '90');

        $this->assertEquals(720, $result, 'Le code 90 doit retourner 720 jours');
    }

    public function test_get_days_for_cycles_returns_0_for_unknown_code()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Recurring_Payline_NX::class, 'getDaysForCycles');
        $result = $method->invoke($this->gateway, '999');

        $this->assertEquals(0, $result, 'Un code inconnu doit retourner 0');
    }

    // =========================================
    // paylineCancelWebPaymentDetails()
    // =========================================

    public function test_payline_cancel_web_payment_details_returns_false()
    {
        $order = new WC_Order(12345);
        $res = ['result' => ['code' => '00000']];

        $method = $this->getPrivateMethod(WC_Abstract_Recurring_Payline_NX::class, 'paylineCancelWebPaymentDetails');
        $result = $method->invoke($this->gateway, $order, $res);

        $this->assertFalse($result, 'Doit retourner false pour le code 00000');
    }

    // =========================================
    // paylineSuccessWebPaymentDetails()
    // =========================================

    public function test_payline_success_web_payment_details_returns_true_on_02500()
    {
        $order = new WC_Order(12345);
        $res = [
            'result' => ['code' => '02500'],
            'transaction' => ['id' => 'TX-123'],
            'payment' => ['contractNumber' => 'CB-123456'],
        ];

        $method = $this->getPrivateMethod(WC_Abstract_Recurring_Payline_NX::class, 'paylineSuccessWebPaymentDetails');
        $result = $method->invoke($this->gateway, $order, $res);

        $this->assertTrue($result, 'Doit retourner true pour le code 02500');
    }

    public function test_payline_success_web_payment_details_returns_false_on_other_code()
    {
        $order = new WC_Order(12345);
        $res = [
            'result' => ['code' => '00000'],
            'transaction' => ['id' => 'TX-123'],
            'payment' => ['contractNumber' => 'CB-123456'],
        ];

        $method = $this->getPrivateMethod(WC_Abstract_Recurring_Payline_NX::class, 'paylineSuccessWebPaymentDetails');
        $result = $method->invoke($this->gateway, $order, $res);

        $this->assertFalse($result, 'Doit retourner false pour un code autre que 02500');
    }

    // =========================================
    // getContractDescription()
    // =========================================

    public function test_get_contract_description_contains_card()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Recurring_Payline_NX::class, 'getContractDescription');
        $result = $method->invoke($this->gateway);

        $this->assertStringContainsString('card', $result, 'La description du contrat doit contenir "card"');
    }
}