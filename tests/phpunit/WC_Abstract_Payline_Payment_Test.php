<?php

/**
 * Tests unitaires pour WC_Abstract_Payline : Construction des requêtes de paiement
 *
 * Teste la méthode getWebPaymentRequest() qui construit la requête envoyée
 * au SDK Payline pour initier un paiement web. Cette méthode complexe effectue
 * 50+ appels WC_Order et génère une structure de données critique pour les
 * paiements. Les tests couvrent tous les scénarios : montants en centimes,
 * devises, adresses, contrats, URLs de callback, et configuration (langue, page personnalisée).
 *
 * Méthodes testées :
 * - getWebPaymentRequest() : Construction requête paiement complète (20 tests)
 */
class WC_Abstract_Payline_Payment_Test extends PaylineTestCase
{
    /** @var WC_Gateway_Payline_CPT */
    private $gateway;

    /** @var WC_Order */
    private $order;

    protected function setUp(): void
    {
        parent::setUp();

        // Configurer les settings nécessaires
        MockOptions::$data['woocommerce_payline_settings']['payment_action'] = '101';
        MockOptions::$data['woocommerce_payline_settings']['primary_contracts'] = array('CB-123', 'VISA-456');
        MockOptions::$data['woocommerce_payline_settings']['wallet'] = 'no';
        MockOptions::$data['woocommerce_payline_settings']['language'] = 'fr';
        MockOptions::$data['woocommerce_payline_settings']['custom_page_code'] = 'CUSTOM_PAGE';
        MockOptions::$data['woocommerce_payline_settings']['smartdisplay_parameter'] = null;
        MockOptions::$data['woocommerce_payline_settings']['pos'] = 'POS123';

        MockOptions::$data['woocommerce_payline_cpt_settings']['payment_action'] = '101';
        MockOptions::$data['woocommerce_payline_cpt_settings']['primary_contracts'] = array('CB-123', 'VISA-456');
        MockOptions::$data['woocommerce_payline_cpt_settings']['wallet'] = 'no';
        MockOptions::$data['woocommerce_payline_cpt_settings']['language'] = 'fr';
        MockOptions::$data['woocommerce_payline_cpt_settings']['custom_page_code'] = 'CUSTOM_PAGE';
        MockOptions::$data['woocommerce_payline_cpt_settings']['smartdisplay_parameter'] = null;
        MockOptions::$data['woocommerce_payline_cpt_settings']['pos'] = 'POS123';

        $this->gateway = new WC_Gateway_Payline_CPT();

        // Créer un ordre avec des données complètes
        $this->order = new WC_Order(12345);
        $this->order->set_total('100.00');
        $this->order->set_currency('EUR');
        $this->order->set_billing(array(
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean.dupont@example.com',
            'phone' => '0123456789',
            'address_1' => '123 Rue de la Paix',
            'address_2' => 'Appartement 4B',
            'city' => 'Paris',
            'postcode' => '75001',
            'country' => 'FR',
            'company' => 'Acme Corp',
        ));
        $this->order->set_shipping(array(
            'first_name' => 'Marie',
            'last_name' => 'Martin',
            'address_1' => '456 Avenue des Champs',
            'address_2' => '',
            'city' => 'Lyon',
            'postcode' => '69001',
            'country' => 'FR',
            'company' => '',
        ));
        $this->order->set_user_id(42);
    }

    // =========================================
    // getWebPaymentRequest() - Structure globale
    // =========================================

    /**
     * @throws ReflectionException
     */
    public function test_get_web_payment_request_has_required_sections()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getWebPaymentRequest');

        $result = $method->invoke($this->gateway, $this->order);

        $this->assertArrayHasKey('payment', $result);
        $this->assertArrayHasKey('order', $result);
        $this->assertArrayHasKey('buyer', $result);
        $this->assertArrayHasKey('billingAddress', $result['buyer']);
        $this->assertArrayHasKey('delivery', $result);
    }

    // =========================================
    // getWebPaymentRequest() - Section Payment
    // =========================================

    /**
     * @throws ReflectionException
     */
    public function test_get_web_payment_request_payment_section_has_amount_in_cents()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getWebPaymentRequest');

        $result = $method->invoke($this->gateway, $this->order);

        // Montant doit être en centimes (100.00 EUR = 10000 centimes)
        $this->assertEquals(10000, $result['payment']['amount']);
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_web_payment_request_order_has_currency_code()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getWebPaymentRequest');

        $result = $method->invoke($this->gateway, $this->order);

        $this->assertArrayHasKey('currency', $result['order']);
        $this->assertEquals('EUR', $result['order']['currency']);
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_web_payment_request_payment_section_has_action()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getWebPaymentRequest');

        $result = $method->invoke($this->gateway, $this->order);

        $this->assertEquals('AUTOMATIC', $result['payment']['capture']);
    }

    // =========================================
    // getWebPaymentRequest() - Section Order
    // =========================================

    /**
     * @throws ReflectionException
     */
    public function test_get_web_payment_request_order_section_has_ref()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getWebPaymentRequest');

        $result = $method->invoke($this->gateway, $this->order);

        $this->assertEquals('12345', $result['order']['reference']);
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_web_payment_request_order_section_has_country()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getWebPaymentRequest');

        $result = $method->invoke($this->gateway, $this->order);

        $this->assertEquals('FR', $result['order']['country']);
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_web_payment_request_order_section_has_amount_matching_payment()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getWebPaymentRequest');

        $result = $method->invoke($this->gateway, $this->order);

        // Amount dans order doit être identique à amount dans payment
        $this->assertEquals($result['payment']['amount'], $result['order']['amount']);
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_web_payment_request_order_section_has_date()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getWebPaymentRequest');

        $result = $method->invoke($this->gateway, $this->order);

        $this->assertArrayHasKey('date', $result['order'], 'La section order doit contenir une date');
        $this->assertMatchesRegularExpression('/^\d{2}\/\d{2}\/\d{4}/', $result['order']['date'], 'La date doit commencer par JJ/MM/AAAA');
    }

    // =========================================
    // getWebPaymentRequest() - Section Buyer
    // =========================================

    /**
     * @throws ReflectionException
     */
    public function test_get_web_payment_request_buyer_section_has_names()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getWebPaymentRequest');

        $result = $method->invoke($this->gateway, $this->order);

        $this->assertEquals('Jean', $result['buyer']['firstName']);
        $this->assertEquals('Dupont', $result['buyer']['lastName']);
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_web_payment_request_buyer_section_has_email()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getWebPaymentRequest');

        $result = $method->invoke($this->gateway, $this->order);

        $this->assertEquals('jean.dupont@example.com', $result['buyer']['email']);
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_web_payment_request_buyer_section_has_customer_id()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getWebPaymentRequest');

        $result = $method->invoke($this->gateway, $this->order);

        $this->assertEquals('42', $result['buyer']['id']);
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_web_payment_request_buyer_section_has_mobile_phone()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getWebPaymentRequest');

        $result = $method->invoke($this->gateway, $this->order);

        $this->assertEquals('0123456789', $result['buyer']['mobile']);
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_web_payment_request_handles_empty_buyer_data()
    {
        $order = new WC_Order(99999);
        $order->set_total('50.00');
        $order->set_currency('EUR');
        $order->set_billing(array(
            'first_name' => '',
            'last_name' => '',
            'email' => 'test@example.com',
            'phone' => '',
            'address_1' => '',
            'city' => '',
            'postcode' => '',
            'country' => '',
        ));
        $order->set_shipping(array(
            'first_name' => '',
            'last_name' => '',
            'address_1' => '',
            'city' => '',
            'postcode' => '',
            'country' => '',
        ));

        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getWebPaymentRequest');
        $result = $method->invoke($this->gateway, $order);

        $this->assertArrayHasKey('buyer', $result, 'La section buyer doit exister même avec des données minimales');
        $this->assertArrayHasKey('billingAddress', $result, 'La section billingAddress doit exister même avec des données minimales');
    }

    // =========================================
    // getWebPaymentRequest() - Billing Address
    // =========================================

    /**
     * @throws ReflectionException
     */
    public function test_get_web_payment_request_billing_address_has_complete_data()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getWebPaymentRequest');

        $result = $method->invoke($this->gateway, $this->order);

        $billing = $result['buyer']['billingAddress'];

        $this->assertStringContainsString('Jean', $billing['label']);
        $this->assertStringContainsString('Dupont', $billing['label']);
        $this->assertEquals('Jean', $billing['firstName']);
        $this->assertEquals('Dupont', $billing['lastName']);
        $this->assertEquals('123 Rue de la Paix', $billing['street']);
        $this->assertEquals('Appartement 4B', $billing['complement']);
        $this->assertEquals('Paris', $billing['city']);
        $this->assertEquals('75001', $billing['zip']);
        $this->assertEquals('FR', $billing['country']);
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_web_payment_request_billing_address_phone_cleaned()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getWebPaymentRequest');

        $result = $method->invoke($this->gateway, $this->order);

        $this->assertEquals('0123456789', $result['buyer']['billingAddress']['mobile']);
    }

    // =========================================
    // getWebPaymentRequest() - Shipping Address
    // =========================================

    /**
     * @throws ReflectionException
     */
    public function test_get_web_payment_request_shipping_address_uses_shipping_data()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getWebPaymentRequest');

        $result = $method->invoke($this->gateway, $this->order);

        $shipping = $result['delivery']['address'];

        $this->assertEquals('Marie', $shipping['firstName']);
        $this->assertEquals('Martin', $shipping['lastName']);
        $this->assertEquals('456 Avenue des Champs', $shipping['street']);
        $this->assertEquals('Lyon', $shipping['city']);
        $this->assertEquals('69001', $shipping['zip']);
        $this->assertEquals('FR', $shipping['country']);
    }

    // =========================================
    // getWebPaymentRequest() - URLs Callback
    // =========================================

    /**
     * @throws ReflectionException
     */
    public function test_get_web_payment_request_has_callback_urls()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getWebPaymentRequest');

        $result = $method->invoke($this->gateway, $this->order);

        $this->assertArrayHasKey('notificationURL', $result);
        $this->assertArrayHasKey('returnURL', $result);

        $this->assertNotEmpty($result['notificationURL']);
        $this->assertNotEmpty($result['returnURL']);
    }

    // =========================================
    // getWebPaymentRequest() - Contracts
    // =========================================

    /**
     * @throws ReflectionException
     */
    public function test_get_web_payment_request_has_contracts()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getWebPaymentRequest');

        $result = $method->invoke($this->gateway, $this->order);

        $this->assertArrayHasKey('paymentMethod', $result);
        $this->assertIsArray($result['paymentMethod']['paymentMethodIDs']);
        $this->assertContains('CB-123', $result['paymentMethod']['paymentMethodIDs']);
        $this->assertContains('VISA-456', $result['paymentMethod']['paymentMethodIDs']);
    }

    // =========================================
    // getWebPaymentRequest() - Configuration
    // =========================================

    /**
     * @throws ReflectionException
     */
    public function test_get_web_payment_request_has_language_code()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getWebPaymentRequest');

        $result = $method->invoke($this->gateway, $this->order);

        $this->assertEquals('fr', $result['languageCode']);
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_web_payment_request_has_custom_page_code()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getWebPaymentRequest');

        $result = $method->invoke($this->gateway, $this->order);

        $this->assertEquals('CUSTOM_PAGE', $result['customPaymentPageCode']);
    }
}
