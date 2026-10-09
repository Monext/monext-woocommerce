<?php

/**
 * Tests unitaires pour WC_Gateway_Payline_REC
 *
 * Couvre les méthodes spécifiques du gateway REC :
 * - is_available() : Vérification eligible_product_ids
 * - getWebPaymentRequest() : Calcul recurring (firstAmount, amount, endDate)
 * - init_form_fields() : Champs spécifiques REC
 *
 * Compatibilité : PHP 7.4 - 8.4
 */
class WC_Gateway_Payline_REC_Test extends PaylineTestCase
{
    /** @var WC_Gateway_Payline_REC */
    private $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        MockOptions::$data['woocommerce_payline_settings'] = [
            'merchant_id' => 'MERCH123',
            'access_key' => 'ACCESS_KEY',
            'environment' => 'Homologation',
            'pos' => 'POS1',
            'smartdisplay_parameter' => '',
            'language' => '',
            'custom_page_code' => '',
            'wallet' => 'no',
        ];

        MockOptions::$data['woocommerce_payline_rec_settings'] = [
            'enabled' => 'yes',
            'title' => 'Paiement REC',
            'primary_contracts' => ['CB-123456'],
            'billing_cycle' => '40',
            'max_records' => '12',
            'eligible_product_ids' => '100;200',
        ];

        MockWcCart::$cart_items = [];

        $this->gateway = new WC_Gateway_Payline_REC();
    }

    // =========================================
    // Propriétés
    // =========================================

    public function test_id_is_payline_rec()
    {
        $this->assertEquals('payline_rec', $this->gateway->id, 'L\'id doit être "payline_rec"');
    }

    public function test_method_title_is_monext_rec()
    {
        $this->assertEquals('Monext REC', $this->gateway->method_title, 'Le method_title doit être "Monext REC"');
    }

    public function test_payment_mode_is_rec()
    {
        $prop = $this->getPrivateProperty(WC_Gateway_Payline_REC::class, 'paymentMode');
        $this->assertEquals('REC', $prop->getValue($this->gateway), 'Le paymentMode doit être "REC"');
    }

    // =========================================
    // is_available()
    // =========================================

    public function test_is_available_returns_true_with_eligible_products()
    {
        $mockProduct = new class {
            public function get_id() { return 100; }
        };

        $mockProduct2 = new class {
            public function get_id() { return 200; }
        };

        MockWcCart::$cart_items = [
            'key1' => ['data' => $mockProduct],
            'key2' => ['data' => $mockProduct2],
        ];

        $this->assertTrue($this->gateway->is_available(), 'is_available() doit retourner true avec des produits éligibles');
    }

    public function test_is_available_returns_false_with_non_eligible_product()
    {
        $mockProduct = new class {
            public function get_id() { return 999; }
        };

        MockWcCart::$cart_items = [
            'key1' => ['data' => $mockProduct],
        ];

        $this->assertFalse($this->gateway->is_available(), 'is_available() doit retourner false avec un produit non éligible');
    }

    public function test_is_available_returns_true_with_empty_cart()
    {
        MockWcCart::$cart_items = [];

        $this->assertTrue($this->gateway->is_available(), 'is_available() doit retourner true avec un panier vide');
    }

    public function test_is_available_returns_false_with_empty_eligible_product_ids()
    {
        MockOptions::$data['woocommerce_payline_rec_settings']['eligible_product_ids'] = '';
        $this->gateway = new WC_Gateway_Payline_REC();

        $mockProduct = new class {
            public function get_id() { return 999; }
        };

        MockWcCart::$cart_items = [
            'key1' => ['data' => $mockProduct],
        ];

        $this->assertFalse($this->gateway->is_available(), 'is_available() doit retourner false si eligible_product_ids est vide');
    }

    // =========================================
    // getWebPaymentRequest()
    // =========================================

    public function test_get_web_payment_request_sets_first_amount_to_total()
    {
        $order = new WC_Order(12345);
        $order->set_total(5000);

        $method = $this->getPrivateMethod(WC_Gateway_Payline_REC::class, 'getWebPaymentRequest');
        $result = $method->invoke($this->gateway, $order);

        $this->assertArrayHasKey('recurring', $result, 'Le résultat doit contenir "recurring"');
        $expectedAmount = 5000 * 100; // 5000.00 en centimes
        $this->assertEquals($expectedAmount, $result['recurring']['firstAmount'], 'firstAmount doit être le total en centimes');
    }

    public function test_get_web_payment_request_sets_amount_to_total()
    {
        $order = new WC_Order(12345);
        $order->set_total(5000);

        $method = $this->getPrivateMethod(WC_Gateway_Payline_REC::class, 'getWebPaymentRequest');
        $result = $method->invoke($this->gateway, $order);

        $expectedAmount = 5000 * 100; // 5000.00 en centimes
        $this->assertEquals($expectedAmount, $result['recurring']['amount'], 'amount doit être le total en centimes');
    }

    public function test_get_web_payment_request_sets_billing_day_to_01()
    {
        $order = new WC_Order(12345);
        $order->set_total(5000);

        $method = $this->getPrivateMethod(WC_Gateway_Payline_REC::class, 'getWebPaymentRequest');
        $result = $method->invoke($this->gateway, $order);

        $this->assertEquals('01', $result['recurring']['billingDay'], 'billingDay doit être "01"');
    }

    public function test_get_web_payment_request_sets_end_date_with_max_records()
    {
        MockOptions::$data['woocommerce_payline_rec_settings']['max_records'] = '12';
        $this->gateway = new WC_Gateway_Payline_REC();

        $order = new WC_Order(12345);
        $order->set_total(5000);

        $method = $this->getPrivateMethod(WC_Gateway_Payline_REC::class, 'getWebPaymentRequest');
        $result = $method->invoke($this->gateway, $order);

        $this->assertArrayHasKey('endDate', $result['recurring'], 'endDate doit être présent quand max_records est configuré');
        $this->assertMatchesRegularExpression('/^\d{2}\/\d{2}\/\d{4}$/', $result['recurring']['endDate'], 'endDate doit être au format JJ/MM/AAAA');
    }

    public function test_get_web_payment_request_does_not_set_end_date_without_max_records()
    {
        MockOptions::$data['woocommerce_payline_rec_settings']['max_records'] = '';
        $this->gateway = new WC_Gateway_Payline_REC();

        $order = new WC_Order(12345);
        $order->set_total(5000);

        $method = $this->getPrivateMethod(WC_Gateway_Payline_REC::class, 'getWebPaymentRequest');
        $result = $method->invoke($this->gateway, $order);

        $this->assertArrayNotHasKey('endDate', $result['recurring'], 'endDate ne doit pas être présent sans max_records');
    }

    // =========================================
    // init_form_fields()
    // =========================================

    public function test_init_form_fields_includes_max_records()
    {
        $this->assertArrayHasKey('max_records', $this->gateway->form_fields, 'form_fields doit contenir "max_records"');
        $this->assertEquals('12', $this->gateway->form_fields['max_records']['default'], 'max_records doit avoir 12 comme valeur par défaut');
    }

    public function test_init_form_fields_includes_eligible_product_ids()
    {
        $this->assertArrayHasKey('eligible_product_ids', $this->gateway->form_fields, 'form_fields doit contenir "eligible_product_ids"');
    }
}