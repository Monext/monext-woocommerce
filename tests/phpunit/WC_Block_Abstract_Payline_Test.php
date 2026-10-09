<?php

/**
 * Tests unitaires pour WC_Block_Abstract_Payline
 *
 * Couvre les méthodes de la classe abstraite des Blocks WooCommerce :
 * - initialize() : Chargement des settings
 * - is_active() : Vérification activation
 * - get_payment_method_data() : Données passées au frontend
 * - get_payment_method_additionnal_data() : Données additionnelles
 * - can_make_payment() : Vérification contrats
 * - get_icons() : Configuration icône
 *
 * Compatibilité : PHP 7.4 - 8.4
 */
class WC_Block_Abstract_Payline_Test extends PaylineTestCase
{
    /** @var WC_Block_Payline_NX */
    private $block;

    protected function setUp(): void
    {
        parent::setUp();

        MockOptions::$data['woocommerce_payline_nx_settings']['enabled'] = 'yes';
        MockOptions::$data['woocommerce_payline_nx_settings']['primary_contracts'] = 'CB-123456';
        MockOptions::$data['woocommerce_payline_nx_settings']['title'] = 'Paiement NX';
        MockOptions::$data['woocommerce_payline_nx_settings']['description'] = 'Description NX';

        $this->block = new WC_Block_Payline_NX();
        $this->block->initialize();
    }

    // =========================================
    // initialize()
    // =========================================

    /**
     * Test : initialize() charge les settings depuis les options
     */
    public function test_initialize_loads_settings_from_options()
    {
        $this->block->initialize();

        $settings = $this->getPrivateProperty(WC_Block_Abstract_Payline::class, 'settings');
        $settings->setAccessible(true);
        $loadedSettings = $settings->getValue($this->block);

        $this->assertEquals('yes', $loadedSettings['enabled'], 'Le setting enabled doit être "yes"');
        $this->assertEquals('CB-123456', $loadedSettings['primary_contracts'], 'Le setting primary_contracts doit être "CB-123456"');
    }

    /**
     * Test : initialize() initialise la propriété gateway
     */
    public function test_initialize_sets_gateway_class()
    {
        $this->block->initialize();

        $gateway = $this->getPrivateProperty(WC_Block_Abstract_Payline::class, 'gateway');
        $gateway->setAccessible(true);
        $gatewayClass = $gateway->getValue($this->block);

        $this->assertEquals('WC_Gateway_Payline_NX', $gatewayClass, 'La classe gateway doit être "WC_Gateway_Payline_NX"');
    }

    /**
     * Test : initialize() retourne tableau vide si pas d'options
     */
    public function test_initialize_returns_empty_array_when_no_options()
    {
        MockOptions::$data['woocommerce_payline_nx_settings'] = [];

        $this->block = new WC_Block_Payline_NX();
        $this->block->initialize();

        $settings = $this->getPrivateProperty(WC_Block_Abstract_Payline::class, 'settings');
        $settings->setAccessible(true);
        $loadedSettings = $settings->getValue($this->block);

        $this->assertIsArray($loadedSettings, 'initialize() doit retourner un tableau même sans options');
    }

    // =========================================
    // is_active()
    // =========================================

    /**
     * Test : is_active() retourne true si enabled=yes
     */
    public function test_is_active_returns_true_when_enabled()
    {
        $method = $this->getPrivateMethod(WC_Block_Abstract_Payline::class, 'is_active');
        $result = $method->invoke($this->block);

        $this->assertTrue($result, 'is_active() doit retourner true quand enabled=yes');
    }

    /**
     * Test : is_active() retourne false si disabled
     */
    public function test_is_active_returns_false_when_disabled()
    {
        MockOptions::$data['woocommerce_payline_nx_settings']['enabled'] = 'no';

        $this->block = new WC_Block_Payline_NX();
        $this->block->initialize();

        $method = $this->getPrivateMethod(WC_Block_Abstract_Payline::class, 'is_active');
        $result = $method->invoke($this->block);

        $this->assertFalse($result, 'is_active() doit retourner false quand enabled=no');
    }

    // =========================================
    // can_make_payment()
    // =========================================

    /**
     * Test : can_make_payment() retourne true avec contrats
     */
    public function test_can_make_payment_returns_true_with_contracts()
    {
        $method = $this->getPrivateMethod(WC_Block_Abstract_Payline::class, 'can_make_payment');
        $result = $method->invoke($this->block);

        $this->assertTrue($result, 'can_make_payment() doit retourner true avec des contrats configurés');
    }

    /**
     * Test : can_make_payment() retourne false sans contrats
     */
    public function test_can_make_payment_returns_false_without_contracts()
    {
        MockOptions::$data['woocommerce_payline_nx_settings']['primary_contracts'] = '';

        $this->block = new WC_Block_Payline_NX();
        $this->block->initialize();

        $method = $this->getPrivateMethod(WC_Block_Abstract_Payline::class, 'can_make_payment');
        $result = $method->invoke($this->block);

        $this->assertFalse($result, 'can_make_payment() doit retourner false quand primary_contracts est vide');
    }

    /**
     * Test : can_make_payment() retourne false avec contrats vides
     */
    public function test_can_make_payment_returns_false_with_empty_contracts()
    {
        MockOptions::$data['woocommerce_payline_nx_settings']['primary_contracts'] = null;

        $this->block = new WC_Block_Payline_NX();
        $this->block->initialize();

        $method = $this->getPrivateMethod(WC_Block_Abstract_Payline::class, 'can_make_payment');
        $result = $method->invoke($this->block);

        $this->assertFalse($result, 'can_make_payment() doit retourner false quand primary_contracts est null');
    }

    // =========================================
    // get_icons()
    // =========================================

    /**
     * Test : get_icons() retourne icône par défaut
     */
    public function test_get_icons_returns_default_icon()
    {
        $method = $this->getPrivateMethod(WC_Block_Abstract_Payline::class, 'get_icons');
        $result = $method->invoke($this->block);

        $this->assertArrayHasKey('id', $result, 'get_icons() doit contenir une clé "id"');
        $this->assertArrayHasKey('src', $result, 'get_icons() doit contenir une clé "src"');
        $this->assertArrayHasKey('alt', $result, 'get_icons() doit contenir une clé "alt"');
        $this->assertStringContainsString('icone-monext.svg', $result['src'], 'L\'icône par défaut doit être "icone-monext.svg"');
    }

    /**
     * Test : get_icons() retourne icône custom si définie
     */
    public function test_get_icons_returns_custom_icon_when_set()
    {
        MockOptions::$data['woocommerce_payline_nx_settings']['custom_icon'] = 'https://example.com/custom-icon.png';

        $this->block = new WC_Block_Payline_NX();
        $this->block->initialize();

        $method = $this->getPrivateMethod(WC_Block_Abstract_Payline::class, 'get_icons');
        $result = $method->invoke($this->block);

        $this->assertEquals('https://example.com/custom-icon.png', $result['src'], 'L\'icône custom doit être utilisée quand définie');
    }

    // =========================================
    // get_payment_method_data()
    // =========================================

    /**
     * Test : get_payment_method_data() inclut le titre
     */
    public function test_get_payment_method_data_includes_title()
    {
        $method = $this->getPrivateMethod(WC_Block_Abstract_Payline::class, 'get_payment_method_data');
        $result = $method->invoke($this->block);

        $this->assertEquals('Paiement NX', $result['title'], 'Le titre doit correspondre au setting');
    }

    /**
     * Test : get_payment_method_data() inclut la description nettoyée
     */
    public function test_get_payment_method_data_sanitizes_description()
    {
        MockOptions::$data['woocommerce_payline_nx_settings']['description'] = '<script>alert(1)</script>Safe <br> text';

        $this->block = new WC_Block_Payline_NX();
        $this->block->initialize();

        $method = $this->getPrivateMethod(WC_Block_Abstract_Payline::class, 'get_payment_method_data');
        $result = $method->invoke($this->block);

        $this->assertStringNotContainsString('<script>', $result['description'], 'La description ne doit pas contenir de balises script');
        $this->assertStringContainsString('<br>', $result['description'], 'La description doit conserver les balises br autorisées');
    }

    /**
     * Test : get_payment_method_data() inclut canMakePayment
     */
    public function test_get_payment_method_data_includes_can_make_payment()
    {
        $method = $this->getPrivateMethod(WC_Block_Abstract_Payline::class, 'get_payment_method_data');
        $result = $method->invoke($this->block);

        $this->assertArrayHasKey('canMakePayment', $result, 'get_payment_method_data() doit contenir "canMakePayment"');
        $this->assertTrue($result['canMakePayment'], 'canMakePayment doit être true');
    }

    // =========================================
    // get_payment_method_additionnal_data()
    // =========================================

    /**
     * Test : get_payment_method_additionnal_data() retourne widget_integration
     */
    public function test_get_payment_method_additionnal_data_returns_widget_integration()
    {
        $method = $this->getPrivateMethod(WC_Block_Abstract_Payline::class, 'get_payment_method_additionnal_data');
        $result = $method->invoke($this->block);

        $this->assertArrayHasKey('widget_integration', $result, 'get_payment_method_additionnal_data() doit contenir "widget_integration"');
        $this->assertEquals('redirection', $result['widget_integration'], 'widget_integration doit être "redirection"');
    }

    // =========================================
    // get_payment_method_script_handles()
    // =========================================

    /**
     * Test : get_payment_method_script_handles() retourne le handle
     */
    public function test_get_payment_method_script_handles_returns_handle()
    {
        $method = $this->getPrivateMethod(WC_Block_Abstract_Payline::class, 'get_payment_method_script_handles');
        $result = $method->invoke($this->block);

        $this->assertIsArray($result, 'get_payment_method_script_handles() doit retourner un tableau');
        $this->assertCount(1, $result, 'get_payment_method_script_handles() doit retourner un seul handle');
        $this->assertEquals('wc-payment-method-payline-nx', $result[0], 'Le handle doit être "wc-payment-method-payline-nx"');
    }
}