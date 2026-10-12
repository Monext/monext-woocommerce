<?php

/**
 * Tests unitaires pour WC_Block_Payline_NX
 *
 * La classe ne surcharge aucune méthode : vérifie uniquement
 * l'initialisation des propriétés spécifiques NX.
 *
 * Compatibilité : PHP 7.4 - 8.4
 */
class WC_Block_Payline_NX_Test extends PaylineTestCase
{
    /** @var WC_Block_Payline_NX */
    private $block;

    protected function setUp(): void
    {
        parent::setUp();

        MockOptions::$data['woocommerce_payline_nx_settings']['enabled'] = 'yes';
        MockOptions::$data['woocommerce_payline_nx_settings']['primary_contracts'] = 'NX-123456';
        MockOptions::$data['woocommerce_payline_nx_settings']['title'] = 'Paiement NX';
        MockOptions::$data['woocommerce_payline_nx_settings']['description'] = 'Description NX';

        $this->block = new WC_Block_Payline_NX();
        $this->block->initialize();
    }

    public function test_name_is_payline_nx()
    {
        $value = $this->getPrivateProperty(WC_Block_Payline_NX::class, 'name')->getValue($this->block);
        $this->assertEquals('payline_nx', $value, 'La propriété name doit être "payline_nx"');
    }

    public function test_settings_option_name_is_correct()
    {
        $value = $this->getPrivateProperty(WC_Block_Payline_NX::class, 'settingsOptionName')->getValue($this->block);
        $this->assertEquals('woocommerce_payline_nx_settings', $value, 'settingsOptionName doit être "woocommerce_payline_nx_settings"');
    }

    public function test_handle_is_correct()
    {
        $value = $this->getPrivateProperty(WC_Block_Payline_NX::class, 'handle')->getValue($this->block);
        $this->assertEquals('wc-payment-method-payline-nx', $value, 'handle doit être "wc-payment-method-payline-nx"');
    }

    public function test_gateway_class_is_nx()
    {
        $value = $this->getPrivateProperty(WC_Block_Payline_NX::class, 'gatewayClass')->getValue($this->block);
        $this->assertEquals(WC_Gateway_Payline_NX::class, $value, 'gatewayClass doit pointer vers WC_Gateway_Payline_NX');
    }

    public function test_is_active_returns_true_when_enabled()
    {
        $method = $this->getPrivateMethod(WC_Block_Abstract_Payline::class, 'is_active');
        $result = $method->invoke($this->block);
        $this->assertTrue($result, 'is_active() doit retourner true quand enabled=yes');
    }

    public function test_get_payment_method_data_returns_nx_data()
    {
        $method = $this->getPrivateMethod(WC_Block_Abstract_Payline::class, 'get_payment_method_data');
        $result = $method->invoke($this->block);

        $this->assertArrayHasKey('title', $result, 'Les données doivent contenir "title"');
        $this->assertArrayHasKey('description', $result, 'Les données doivent contenir "description"');
        $this->assertArrayHasKey('supports', $result, 'Les données doivent contenir "supports"');
        $this->assertEquals('Paiement NX', $result['title'], 'Le title doit être "Paiement NX"');
    }
}