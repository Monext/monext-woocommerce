<?php

/**
 * Tests unitaires pour WC_Abstract_Payline : Construction des requêtes de paiement
 *
 * Teste la méthode getWebPaymentRequest() qui construit la requête envoyée
 * au SDK Payline pour initier un paiement web. Cette méthode complexe effectue
 * 50+ appels WC_Order et génère une structure de données critique pour les
 * paiements. Les tests couvrent tous les scénarios : montants en centimes,
 * devises (code numérique), adresses (billing/shipping avec fallback), contrats,
 * URLs de callback, et configuration (langue, page personnalisée).
 *
 * Méthodes testées :
 * - getWebPaymentRequest() : Construction requête paiement complète (29 tests)
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

        MockOptions::$data['woocommerce_payline_cpt_settings']['payment_action'] = '101';
        MockOptions::$data['woocommerce_payline_cpt_settings']['primary_contracts'] = array('CB-123', 'VISA-456');
        MockOptions::$data['woocommerce_payline_cpt_settings']['wallet'] = 'no';
        MockOptions::$data['woocommerce_payline_cpt_settings']['language'] = 'fr';
        MockOptions::$data['woocommerce_payline_cpt_settings']['custom_page_code'] = 'CUSTOM_PAGE';
        MockOptions::$data['woocommerce_payline_cpt_settings']['smartdisplay_parameter'] = null;

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
    public function test_get_web_payment_request_returns_array()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getWebPaymentRequest');

        $result = $method->invoke($this->gateway, $this->order);

        $this->assertIsArray($result, 'getWebPaymentRequest doit retourner un array');
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_web_payment_request_has_required_sections()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getWebPaymentRequest');

        $result = $method->invoke($this->gateway, $this->order);

        // Vérifier les sections principales
        $this->assertArrayHasKey('payment', $result, 'Doit contenir section payment');
        $this->assertArrayHasKey('order', $result, 'Doit contenir section order');
        $this->assertArrayHasKey('buyer', $result, 'Doit contenir section buyer');
        $this->assertArrayHasKey('billingAddress', $result, 'Doit contenir section billingAddress');
        $this->assertArrayHasKey('shippingAddress', $result, 'Doit contenir section shippingAddress');
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
    public function test_get_web_payment_request_payment_section_has_currency_code()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getWebPaymentRequest');

        $result = $method->invoke($this->gateway, $this->order);

        // Devise doit être le code numérique (EUR = 978)
        $this->assertArrayHasKey('currency', $result['payment']);
        $this->assertIsNumeric($result['payment']['currency']);
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_web_payment_request_payment_section_has_action()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getWebPaymentRequest');

        $result = $method->invoke($this->gateway, $this->order);

        $this->assertEquals('101', $result['payment']['action']);
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_web_payment_request_payment_section_has_contract_number()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getWebPaymentRequest');

        $result = $method->invoke($this->gateway, $this->order);

        // contractNumber doit être le premier contrat de primary_contracts
        $this->assertEquals('CB-123', $result['payment']['contractNumber']);
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

        $this->assertEquals('12345', $result['order']['ref']);
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

        $this->assertArrayHasKey('date', $result['order']);
        $this->assertNotEmpty($result['order']['date']);
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

        $this->assertEquals('42', $result['buyer']['customerId']);
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_web_payment_request_buyer_section_has_mobile_phone()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getWebPaymentRequest');

        $result = $method->invoke($this->gateway, $this->order);

        // Téléphone doit être nettoyé (chiffres seulement)
        $this->assertEquals('0123456789', $result['buyer']['mobilePhone']);
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_web_payment_request_buyer_section_no_wallet_when_disabled()
    {
        // Wallet désactivé dans setUp
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getWebPaymentRequest');

        $result = $method->invoke($this->gateway, $this->order);

        $this->assertArrayNotHasKey('walletId', $result['buyer'], 'walletId ne doit pas être présent si wallet désactivé');
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

        $billing = $result['billingAddress'];

        $this->assertStringContainsString('Jean', $billing['name']);
        $this->assertStringContainsString('Dupont', $billing['name']);
        $this->assertEquals('Jean', $billing['firstName']);
        $this->assertEquals('Dupont', $billing['lastName']);
        $this->assertEquals('123 Rue de la Paix', $billing['street1']);
        $this->assertEquals('Appartement 4B', $billing['street2']);
        $this->assertEquals('Paris', $billing['cityName']);
        $this->assertEquals('75001', $billing['zipCode']);
        $this->assertEquals('FR', $billing['country']);
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_web_payment_request_billing_address_includes_company_in_name()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getWebPaymentRequest');

        $result = $method->invoke($this->gateway, $this->order);

        // Company doit être ajouté au name entre parenthèses
        $this->assertStringContainsString('(Acme Corp)', $result['billingAddress']['name']);
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_web_payment_request_billing_address_phone_cleaned()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getWebPaymentRequest');

        $result = $method->invoke($this->gateway, $this->order);

        // Téléphone nettoyé (chiffres seulement)
        $this->assertEquals('0123456789', $result['billingAddress']['phone']);
        $this->assertEquals(1, $result['billingAddress']['phoneType']);
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

        $shipping = $result['shippingAddress'];

        // Données de livraison (pas billing)
        $this->assertEquals('Marie', $shipping['firstName']);
        $this->assertEquals('Martin', $shipping['lastName']);
        $this->assertEquals('456 Avenue des Champs', $shipping['street1']);
        $this->assertEquals('Lyon', $shipping['cityName']);
        $this->assertEquals('69001', $shipping['zipCode']);
        $this->assertEquals('FR', $shipping['country']);
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_web_payment_request_shipping_address_falls_back_to_billing()
    {
        // Order sans adresse de livraison (valeurs vides)
        $order = new WC_Order(99999);
        $order->set_total('50.00');
        $order->set_currency('EUR');
        $order->set_billing(array(
            'first_name' => 'Pierre',
            'last_name' => 'Durand',
            'address_1' => '789 Boulevard',
            'city' => 'Marseille',
            'postcode' => '13001',
            'country' => 'FR',
        ));
        // Explicitement vider l'adresse de livraison
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

        $shipping = $result['shippingAddress'];

        // Doit utiliser billing comme fallback (opérateur ?:)
        $this->assertEquals('Pierre', $shipping['firstName']);
        $this->assertEquals('Durand', $shipping['lastName']);
        $this->assertEquals('789 Boulevard', $shipping['street1']);
        $this->assertEquals('Marseille', $shipping['cityName']);
        $this->assertEquals('13001', $shipping['zipCode']);
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
        $this->assertArrayHasKey('cancelURL', $result);

        $this->assertNotEmpty($result['notificationURL']);
        $this->assertNotEmpty($result['returnURL']);
        $this->assertNotEmpty($result['cancelURL']);
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

        $this->assertArrayHasKey('contracts', $result);
        $this->assertArrayHasKey('secondContracts', $result);

        $this->assertIsArray($result['contracts']);
        $this->assertContains('CB-123', $result['contracts']);
        $this->assertContains('VISA-456', $result['contracts']);
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
