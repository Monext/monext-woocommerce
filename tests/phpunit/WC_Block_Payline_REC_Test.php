<?php

/**
 * Tests unitaires pour WC_Block_Payline_REC
 *
 * La classe ne surcharge aucune méthode : vérifie uniquement
 * l'initialisation des propriétés spécifiques REC.
 *
 * Compatibilité : PHP 7.4 - 8.4
 */
class WC_Block_Payline_REC_Test extends PaylineTestCase
{
    /** @var WC_Block_Payline_REC */
    private $block;

    protected function setUp(): void
    {
        parent::setUp();

        MockOptions::$data['woocommerce_payline_rec_settings']['enabled'] = 'yes';
        MockOptions::$data['woocommerce_payline_rec_settings']['primary_contracts'] = 'REC-123456';
        MockOptions::$data['woocommerce_payline_rec_settings']['title'] = 'Paiement REC';
        MockOptions::$data['woocommerce_payline_rec_settings']['description'] = 'Description REC';

        $this->block = new WC_Block_Payline_REC();
        $this->block->initialize();
    }

    public function test_name_is_payline_rec()
    {
        $value = $this->getPrivateProperty(WC_Block_Payline_REC::class, 'name')->getValue($this->block);
        $this->assertEquals('payline_rec', $value, 'La propriété name doit être "payline_rec"');
    }

    public function test_settings_option_name_is_correct()
    {
        $value = $this->getPrivateProperty(WC_Block_Payline_REC::class, 'settingsOptionName')->getValue($this->block);
        $this->assertEquals('woocommerce_payline_rec_settings', $value, 'settingsOptionName doit être "woocommerce_payline_rec_settings"');
    }

    public function test_handle_is_correct()
    {
        $value = $this->getPrivateProperty(WC_Block_Payline_REC::class, 'handle')->getValue($this->block);
        $this->assertEquals('wc-payment-method-payline-rec', $value, 'handle doit être "wc-payment-method-payline-rec"');
    }

    public function test_gateway_class_is_rec()
    {
        $value = $this->getPrivateProperty(WC_Block_Payline_REC::class, 'gatewayClass')->getValue($this->block);
        $this->assertEquals(WC_Gateway_Payline_REC::class, $value, 'gatewayClass doit pointer vers WC_Gateway_Payline_REC');
    }

    public function test_is_active_returns_true_when_enabled()
    {
        $method = $this->getPrivateMethod(WC_Block_Abstract_Payline::class, 'is_active');
        $result = $method->invoke($this->block);
        $this->assertTrue($result, 'is_active() doit retourner true quand enabled=yes');
    }

    public function test_get_payment_method_data_returns_rec_data()
    {
        $method = $this->getPrivateMethod(WC_Block_Abstract_Payline::class, 'get_payment_method_data');
        $result = $method->invoke($this->block);

        $this->assertArrayHasKey('title', $result, 'Les données doivent contenir "title"');
        $this->assertArrayHasKey('description', $result, 'Les données doivent contenir "description"');
        $this->assertArrayHasKey('supports', $result, 'Les données doivent contenir "supports"');
        $this->assertEquals('Paiement REC', $result['title'], 'Le title doit être "Paiement REC"');
    }
}