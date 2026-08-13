<?php

/**
 * Tests unitaires pour WC_Block_Payline_CPT
 *
 * Couvre les méthodes spécifiques du Block CPT :
 * - can_make_payment() : Vérification version WC + widget_integration
 * - get_payment_method_additionnal_data() : Données widget + draft order
 *
 * Compatibilité : PHP 7.4 - 8.4
 */
class WC_Block_Payline_CPT_Test extends PaylineTestCase
{
    /** @var WC_Block_Payline_CPT */
    private $block;

    protected function setUp(): void
    {
        parent::setUp();

        \Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils::$is_checkout_block_default = false;

        MockOptions::$data['woocommerce_payline_cpt_settings']['enabled'] = 'yes';
        MockOptions::$data['woocommerce_payline_cpt_settings']['primary_contracts'] = 'CB-123456';
        MockOptions::$data['woocommerce_payline_cpt_settings']['widget_integration'] = 'redirection';
        MockOptions::$data['woocommerce_payline_cpt_settings']['title'] = 'Paiement CPT';
        MockOptions::$data['woocommerce_payline_cpt_settings']['description'] = 'Description CPT';

        $this->block = new WC_Block_Payline_CPT();
        $this->block->initialize();
    }

    // =========================================
    // can_make_payment() - Override
    // =========================================

    public function test_can_make_payment_returns_true_with_redirection()
    {
        $method = $this->getPrivateMethod(WC_Block_Payline_CPT::class, 'can_make_payment');
        $result = $method->invoke($this->block);

        $this->assertTrue($result, 'can_make_payment() doit retourner true avec redirection');
    }

    public function test_can_make_payment_returns_false_when_parent_returns_false()
    {
        MockOptions::$data['woocommerce_payline_cpt_settings']['primary_contracts'] = '';

        $this->block = new WC_Block_Payline_CPT();
        $this->block->initialize();

        $method = $this->getPrivateMethod(WC_Block_Payline_CPT::class, 'can_make_payment');
        $result = $method->invoke($this->block);

        $this->assertFalse($result, 'can_make_payment() doit retourner false si le parent retourne false');
    }

    public function test_can_make_payment_returns_true_with_widget_and_checkout_block_false()
    {
        MockOptions::$data['woocommerce_payline_cpt_settings']['widget_integration'] = 'widget';

        $this->block = new WC_Block_Payline_CPT();
        $this->block->initialize();

        \Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils::$is_checkout_block_default = false;

        $method = $this->getPrivateMethod(WC_Block_Payline_CPT::class, 'can_make_payment');
        $result = $method->invoke($this->block);

        $this->assertTrue($result, 'can_make_payment() doit retourner true avec widget et checkout_block=false');
    }

    /**
     * Test : can_make_payment() retourne false avec widget, WC < 10.6.0 et checkout block
     */
    public function test_can_make_payment_returns_false_with_widget_old_wc_checkout_block()
    {
        $this->markTestIncomplete(
            'Le define("WC_VERSION", "9.0.0") échoue car la constante est déjà définie dans bootstrap.php. ' .
            'Pour tester ce scénario, il faudrait mocker version_compare() ou surcharger la version via un hook.'
        );
    }

    // =========================================
    // get_payment_method_additionnal_data()
    // =========================================

    public function test_get_payment_method_additionnal_data_returns_redirection_default()
    {
        $method = $this->getPrivateMethod(WC_Block_Payline_CPT::class, 'get_payment_method_additionnal_data');
        $result = $method->invoke($this->block);

        $this->assertArrayHasKey('payline_widget_div', $result, 'Le résultat doit contenir "payline_widget_div"');
        $this->assertArrayHasKey('widget_integration', $result, 'Le résultat doit contenir "widget_integration"');
        $this->assertEquals('redirection', $result['widget_integration'], 'widget_integration doit être "redirection" par défaut');
    }

    public function test_get_payment_method_additionnal_data_widget_div_empty_default()
    {
        $method = $this->getPrivateMethod(WC_Block_Payline_CPT::class, 'get_payment_method_additionnal_data');
        $result = $method->invoke($this->block);

        $this->assertEquals('', $result['payline_widget_div'], 'payline_widget_div doit être vide par défaut');
    }

    public function test_get_payment_method_additionnal_data_includes_widget_integration_from_settings()
    {
        MockOptions::$data['woocommerce_payline_cpt_settings']['widget_integration'] = 'redirection';

        $this->block = new WC_Block_Payline_CPT();
        $this->block->initialize();

        $method = $this->getPrivateMethod(WC_Block_Payline_CPT::class, 'get_payment_method_additionnal_data');
        $result = $method->invoke($this->block);

        $this->assertEquals('redirection', $result['widget_integration'], 'widget_integration doit correspondre au setting');
    }

    public function test_get_payment_method_additionnal_data_returns_redirection_when_not_checkout()
    {
        MockOptions::$data['woocommerce_payline_cpt_settings']['widget_integration'] = 'widget';
        MockWordPress::$data['is_checkout'] = false;

        $this->block = new WC_Block_Payline_CPT();
        $this->block->initialize();

        $method = $this->getPrivateMethod(WC_Block_Payline_CPT::class, 'get_payment_method_additionnal_data');
        $result = $method->invoke($this->block);

        $this->assertEquals('redirection', $result['widget_integration'], 'widget_integration doit être "redirection" hors checkout');
        $this->assertEquals('', $result['payline_widget_div'], 'payline_widget_div doit être vide hors checkout');
    }

    public function test_get_payment_method_additionnal_data_returns_redirection_when_widget_integration_empty()
    {
        MockOptions::$data['woocommerce_payline_cpt_settings']['widget_integration'] = '';
        MockWordPress::$data['is_checkout'] = true;

        $this->block = new WC_Block_Payline_CPT();
        $this->block->initialize();

        $method = $this->getPrivateMethod(WC_Block_Payline_CPT::class, 'get_payment_method_additionnal_data');
        $result = $method->invoke($this->block);

        $this->assertEquals('redirection', $result['widget_integration'], 'widget_integration doit être "redirection" quand le setting est vide');
        $this->assertEquals('', $result['payline_widget_div'], 'payline_widget_div doit être vide');
    }
}