<?php

/**
 * Tests unitaires pour WC_Gateway_Payline_NX
 *
 * Couvre les méthodes spécifiques du gateway NX :
 * - is_available() : Vérification billing_left > 0
 * - getWebPaymentRequest() : Calcul recurring (billingLeft, firstAmount, amount, startDate, billingDay)
 * - init_form_fields() : Champs spécifiques NX
 *
 * Compatibilité : PHP 7.4 - 8.4
 */
class WC_Gateway_Payline_NX_Test extends PaylineTestCase
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
            'pos' => '10',
            'smartdisplay_parameter' => '',
            'language' => '',
            'custom_page_code' => '',
            'wallet' => 'no',
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
    // Propriétés
    // =========================================

    public function test_id_is_payline_nx()
    {
        $this->assertEquals('payline_nx', $this->gateway->id, 'L\'id doit être "payline_nx"');
    }

    public function test_method_title_is_monext_nx()
    {
        $this->assertEquals('Monext NX', $this->gateway->method_title, 'Le method_title doit être "Monext NX"');
    }

    public function test_payment_mode_is_nx()
    {
        $prop = $this->getPrivateProperty(WC_Gateway_Payline_NX::class, 'paymentMode');
        $this->assertEquals('NX', $prop->getValue($this->gateway), 'Le paymentMode doit être "NX"');
    }

    // =========================================
    // is_available()
    // =========================================

    public function test_is_available_returns_true_with_valid_billing_left()
    {
        MockOptions::$data['woocommerce_payline_settings']['pos'] = 10;
//        MockOptions::$data['woocommerce_payline_pos_list'] = serialize(['10' => 'POS1']);
        MockOptions::$data['woocommerce_payline_nx_settings']['billing_left'] = '3';
        $this->gateway = new WC_Gateway_Payline_NX();

        $this->assertTrue($this->gateway->is_available(), 'is_available() doit retourner true avec billing_left > 0');
    }

    public function test_is_available_returns_false_when_billing_left_is_zero()
    {
        MockOptions::$data['woocommerce_payline_nx_settings']['billing_left'] = '0';
        $this->gateway = new WC_Gateway_Payline_NX();

        $this->assertFalse($this->gateway->is_available(), 'is_available() doit retourner false si billing_left est 0');
    }

    public function test_is_available_returns_false_when_billing_left_is_negative()
    {
        MockOptions::$data['woocommerce_payline_nx_settings']['billing_left'] = '-1';
        $this->gateway = new WC_Gateway_Payline_NX();

        $this->assertFalse($this->gateway->is_available(), 'is_available() doit retourner false si billing_left est négatif');
    }

    // =========================================
    // getWebPaymentRequest()
    // =========================================

    public function test_get_web_payment_request_sets_billing_left_minimum_2()
    {
        MockOptions::$data['woocommerce_payline_nx_settings']['billing_left'] = '0';
        $this->gateway = new WC_Gateway_Payline_NX();

        $order = new WC_Order(12345);
        $order->set_total(10000);

        $method = $this->getPrivateMethod(WC_Gateway_Payline_NX::class, 'getWebPaymentRequest');
        $result = $method->invoke($this->gateway, $order);

        $this->assertArrayHasKey('recurring', $result['payment'], 'Le résultat doit contenir "recurring"');
        $this->assertArrayHasKey('totalInstallments', $result['payment']['recurring'], 'recurring doit contenir "totalInstallments"');
        $this->assertEquals(2, $result['payment']['recurring']['totalInstallments'], 'totalInstallments doit être au minimum 2');
    }

    public function test_get_web_payment_request_calculates_recurring_amount()
    {
        MockOptions::$data['woocommerce_payline_nx_settings']['billing_left'] = '3';
        $this->gateway = new WC_Gateway_Payline_NX();

        $order = new WC_Order(12345);
        $order->set_total(3000);

        $method = $this->getPrivateMethod(WC_Gateway_Payline_NX::class, 'getWebPaymentRequest');
        $result = $method->invoke($this->gateway, $order);

        $this->assertArrayHasKey('recurring', $result['payment'], 'Le résultat doit contenir "recurring"');
        $this->assertArrayHasKey('installmentAmount', $result['payment']['recurring'], 'recurring doit contenir "installmentAmount"');
        $this->assertArrayHasKey('firstInstallmentAmount', $result['payment']['recurring'], 'recurring doit contenir "firstInstallmentAmount"');
        $this->assertArrayHasKey('totalInstallments', $result['payment']['recurring'], 'recurring doit contenir "totalInstallments"');
        $this->assertArrayHasKey('frequency', $result['payment']['recurring'], 'recurring doit contenir "frequency"');
        $this->assertArrayHasKey('installmentDate', $result['payment']['recurring'], 'recurring doit contenir "installmentDate"');
        $this->assertArrayHasKey('startDate', $result['payment']['recurring'], 'recurring doit contenir "startDate"');
    }

    public function test_get_web_payment_request_sets_frequency_from_billing_cycle()
    {
        MockOptions::$data['woocommerce_payline_nx_settings']['billing_cycle'] = '30';
        $this->gateway = new WC_Gateway_Payline_NX();

        $order = new WC_Order(12345);
        $order->set_total(3000);

        $method = $this->getPrivateMethod(WC_Gateway_Payline_NX::class, 'getWebPaymentRequest');
        $result = $method->invoke($this->gateway, $order);

        $this->assertEquals('BIMONTHLY', $result['payment']['recurring']['frequency'], 'frequency doit correspondre à BIMONTHLY pour billing_cycle 30');
    }

    public function test_get_web_payment_request_sets_installment_date_from_billing_cycle()
    {
        MockOptions::$data['woocommerce_payline_nx_settings']['billing_cycle'] = '40';
        $this->gateway = new WC_Gateway_Payline_NX();

        $order = new WC_Order(12345);
        $order->set_total(3000);

        $method = $this->getPrivateMethod(WC_Gateway_Payline_NX::class, 'getWebPaymentRequest');
        $result = $method->invoke($this->gateway, $order);

        $this->assertArrayHasKey('installmentDate', $result['payment']['recurring'], 'installmentDate doit être présent');
        $this->assertMatchesRegularExpression('/^\d{2}$/', $result['payment']['recurring']['installmentDate'], 'installmentDate doit être un jour au format 2 chiffres');
    }

    public function test_get_web_payment_request_sets_start_date_from_billing_cycle()
    {
        MockOptions::$data['woocommerce_payline_nx_settings']['billing_cycle'] = '40';
        $this->gateway = new WC_Gateway_Payline_NX();

        $order = new WC_Order(12345);
        $order->set_total(3000);

        $method = $this->getPrivateMethod(WC_Gateway_Payline_NX::class, 'getWebPaymentRequest');
        $result = $method->invoke($this->gateway, $order);

        $this->assertArrayHasKey('startDate', $result['payment']['recurring'], 'startDate doit être présent');
    }

    public function test_get_web_payment_request_calculates_recurring_amount_with_billing_left()
    {
        MockOptions::$data['woocommerce_payline_nx_settings']['billing_left'] = '4';
        $this->gateway = new WC_Gateway_Payline_NX();

        $order = new WC_Order(12345);
        $order->set_total(40); // 40.00 EUR

        $method = $this->getPrivateMethod(WC_Gateway_Payline_NX::class, 'getWebPaymentRequest');
        $result = $method->invoke($this->gateway, $order);

        // totalAmount = round(40 * 100) = 4000 cents
        // recurringAmount = round((4000 / 100) / 4) * 100 = round(10) * 100 = 1000 cents
        $this->assertEquals(1000, $result['payment']['recurring']['installmentAmount'], 'installmentAmount doit être 1000');
        // firstInstallmentAmount = 4000 - (1000 * 3) = 1000
        $this->assertEquals(1000, $result['payment']['recurring']['firstInstallmentAmount'], 'firstInstallmentAmount doit être 1000');
        $this->assertEquals(4, $result['payment']['recurring']['totalInstallments'], 'totalInstallments doit être 4');
    }

    public function test_get_web_payment_request_handles_odd_billing_left()
    {
        MockOptions::$data['woocommerce_payline_nx_settings']['billing_left'] = '3';
        $this->gateway = new WC_Gateway_Payline_NX();

        $order = new WC_Order(12345);
        $order->set_total(100); // 100.00 EUR

        $method = $this->getPrivateMethod(WC_Gateway_Payline_NX::class, 'getWebPaymentRequest');
        $result = $method->invoke($this->gateway, $order);

        // totalAmount = round(100 * 100) = 10000 cents
        // recurringAmount = round((10000 / 100) / 3) * 100 = round(33.33) * 100 = 3300 cents
        $this->assertEquals(3300, $result['payment']['recurring']['installmentAmount'], 'installmentAmount doit être 3300');
        // firstInstallmentAmount = 10000 - (3300 * 2) = 3400
        $this->assertEquals(3400, $result['payment']['recurring']['firstInstallmentAmount'], 'firstInstallmentAmount doit être 3400');
        $this->assertEquals(3, $result['payment']['recurring']['totalInstallments'], 'totalInstallments doit être 3');
    }

    // =========================================
    // init_form_fields()
    // =========================================

    public function test_init_form_fields_includes_billing_left()
    {
        $this->assertArrayHasKey('billing_left', $this->gateway->form_fields, 'form_fields doit contenir "billing_left"');
        $this->assertEquals('3', $this->gateway->form_fields['billing_left']['default'], 'billing_left doit avoir 3 comme valeur par défaut');
    }

    public function test_init_form_fields_includes_primary_contracts()
    {
        $this->assertArrayHasKey('primary_contracts', $this->gateway->form_fields, 'form_fields doit contenir "primary_contracts"');
    }
}