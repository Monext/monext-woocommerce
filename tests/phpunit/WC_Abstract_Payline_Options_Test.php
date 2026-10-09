<?php

use Payline\PaylineSDK;

/**
 * Tests unitaires optionnels Phase 3 pour WC_Abstract_Payline
 *
 * Couvre les méthodes additionnelles testables en unitaire :
 * - generateWidgetCustomCss() : Génération CSS widget
 * - completeSettings() : Merge settings globaux → gateway
 * - getContractsByPosLabel() : Filtrage contrats par POS
 * - paylineOnHoldPartnerWebPaymentDetails() : Logique ONHOLD_PARTNER
 * - paylineCancelWebPaymentDetails() : Logique annulation
 *
 * Compatibilité : PHP 7.4 - 8.4
 */
class WC_Abstract_Payline_Options_Test extends PaylineTestCase
{
    /** @var WC_Gateway_Payline_CPT */
    private $gateway;

    /** @var WC_Order */
    private $order;

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
    }

    // =========================================
    // generateWidgetCustomCss()
    // =========================================

    /**
     * Test : generateWidgetCustomCss() inclut les variables de couleur
     */
    public function test_generate_widget_custom_css_includes_color_vars()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'generateWidgetCustomCss');

        MockOptions::$data['woocommerce_payline_cpt_settings']['widget_settings_customize'] = 'yes';
        MockOptions::$data['woocommerce_payline_cpt_settings']['widget_settings_css_cta_bg_color'] = '#FF0000';

        $this->gateway = new WC_Gateway_Payline_CPT();

        $result = $method->invoke($this->gateway);

        $this->assertIsString($result, 'generateWidgetCustomCss() doit retourner une chaîne');
        $this->assertStringContainsString('#PaylineWidget .pl-pay-btn { background-color: #FF0000; }', $result, 'Doit contenir la couleur CTA configurée');
    }

    /**
     * Test : generateWidgetCustomCss() syntaxe CSS valide
     */
    public function test_generate_widget_custom_css_valid_syntax()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'generateWidgetCustomCss');

        MockOptions::$data['woocommerce_payline_cpt_settings']['widget_settings_customize'] = 'no';

        $this->gateway = new WC_Gateway_Payline_CPT();

        $result = $method->invoke($this->gateway);

        // Quand customization désactivée, retourne juste le CSS de base
        $this->assertIsString($result, 'generateWidgetCustomCss() doit retourner une chaîne');
        $this->assertStringContainsString('#PaylineWidget.pl-container-lightbox {position: fixed;}', $result, 'Doit contenir le CSS de base');
    }

    // =========================================
    // completeSettings()
    // =========================================

    /**
     * Test : completeSettings() merge les settings par défaut
     */
    public function test_complete_settings_merges_defaults()
    {
        MockOptions::$data['woocommerce_payline_settings']['merchant_id'] = 'GLOBAL_MERCH';
        MockOptions::$data['woocommerce_payline_settings']['access_key'] = 'GLOBAL_KEY';

        $this->gateway = new WC_Gateway_Payline_CPT();

        $settings = $this->getPrivateProperty(WC_Abstract_Payline::class, 'settings');
        $settings->setAccessible(true);
        $mergedSettings = $settings->getValue($this->gateway);

        $this->assertEquals('GLOBAL_MERCH', $mergedSettings['merchant_id'], 'merchant_id doit provenir des settings globaux');
        $this->assertEquals('GLOBAL_KEY', $mergedSettings['access_key'], 'access_key doit provenir des settings globaux');
    }

    /**
     * Test : completeSettings() préserve les valeurs existantes
     */
    public function test_complete_settings_preserves_existing_values()
    {
        MockOptions::$data['woocommerce_payline_settings']['merchant_id'] = 'GLOBAL_MERCH';
        MockOptions::$data['woocommerce_payline_cpt_settings']['enabled'] = 'yes';

        $this->gateway = new WC_Gateway_Payline_CPT();

        $settings = $this->getPrivateProperty(WC_Abstract_Payline::class, 'settings');
        $settings->setAccessible(true);
        $mergedSettings = $settings->getValue($this->gateway);

        // Les settings globaux sont mergés après les settings CPT, donc merchant_id vient du global
        $this->assertEquals('GLOBAL_MERCH', $mergedSettings['merchant_id'], 'merchant_id doit être préservé');
    }

    /**
     * Test : completeSettings() sauvegarde dans les options
     */
    public function test_complete_settings_saves_to_options()
    {
        MockOptions::$data['woocommerce_payline_settings']['custom_global_key'] = 'custom_value';

        $this->gateway = new WC_Gateway_Payline_CPT();

        $settings = $this->getPrivateProperty(WC_Abstract_Payline::class, 'settings');
        $settings->setAccessible(true);
        $mergedSettings = $settings->getValue($this->gateway);

        $this->assertArrayHasKey('custom_global_key', $mergedSettings, 'La clé custom doit être présente dans les settings mergés');
        $this->assertEquals('custom_value', $mergedSettings['custom_global_key'], 'La valeur custom doit être préservée');
    }

    // =========================================
    // getContractsByPosLabel()
    // =========================================

    /**
     * Test : getContractsByPosLabel() filtre par POS
     */
    public function test_get_contracts_by_pos_label_filters_by_pos()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getContractsByPosLabel');

        MockOptions::$data['woocommerce_payline_cpt_settings']['pos'] = 'POS_TEST';

        $this->gateway = new WC_Gateway_Payline_CPT();

        $result = $method->invoke($this->gateway, 'POS_TEST', array(), false);

        $this->assertIsArray($result, 'getContractsByPosLabel() doit retourner un tableau');
        $this->assertEmpty($result, 'getContractsByPosLabel() doit retourner un tableau vide sans POS configuré');
    }

    /**
     * Test : getContractsByPosLabel() retourne vide pour POS inexistant
     */
    public function test_get_contracts_by_pos_label_returns_empty_for_missing_pos()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getContractsByPosLabel');

        $this->gateway = new WC_Gateway_Payline_CPT();

        $result = $method->invoke($this->gateway, 'NONEXISTENT_POS', array(), false);

        $this->assertIsArray($result, 'getContractsByPosLabel() doit retourner un tableau');
        $this->assertEmpty($result, 'getContractsByPosLabel() doit retourner un tableau vide pour un POS inexistant');
    }

    /**
     * Test : getContractsByPosLabel() retourne vide quand list contrats null
     */
    public function test_get_contracts_by_pos_label_returns_empty_for_null_contracts()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getContractsByPosLabel');

        $this->gateway = new WC_Gateway_Payline_CPT();

        $result = $method->invoke($this->gateway, 'POS_TEST', null, false);

        $this->assertIsArray($result, 'getContractsByPosLabel() doit retourner un tableau même avec contrats null');
        $this->assertEmpty($result, 'getContractsByPosLabel() doit retourner un tableau vide avec contrats null');
    }

    /**
     * Test : getContractsByPosLabel() utilise le cache
     */
    public function test_get_contracts_by_pos_label_uses_cache()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getContractsByPosLabel');

        $this->gateway = new WC_Gateway_Payline_CPT();

        $result = $method->invoke($this->gateway, 'POS_CACHE', array(), true);

        $this->assertIsArray($result, 'getContractsByPosLabel() doit retourner un tableau');
        $this->assertEmpty($result, 'getContractsByPosLabel() doit retourner un tableau vide sans POS en cache');
    }

    /**
     * Test : getContractsByPosLabel() retourne uniquement les activés
     */
    public function test_get_contracts_by_pos_label_returns_enabled_only()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getContractsByPosLabel');

        $this->gateway = new WC_Gateway_Payline_CPT();

        $result = $method->invoke($this->gateway, 'POS_ENABLED', array('CB-123456'), false);

        $this->assertIsArray($result, 'getContractsByPosLabel() doit retourner un tableau');
        $this->assertEmpty($result, 'getContractsByPosLabel() doit retourner un tableau vide sans POS configuré');
    }

    // =========================================
    // paylineOnHoldPartnerWebPaymentDetails()
    // =========================================

    /**
     * Test : paylineOnHoldPartnerWebPaymentDetails() définit le statut correct
     */
    public function test_payline_on_hold_partner_sets_correct_status()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'paylineOnHoldPartnerWebPaymentDetails');

        $res = array(
            'result' => array(
                'shortMessage' => 'ONHOLD_PARTNER',
            ),
        );

        $result = $method->invoke($this->gateway, $this->order, $res);

        $this->assertTrue($result);
    }

    /**
     * Test : paylineOnHoldPartnerWebPaymentDetails() ajoute une note de commande
     */
    public function test_payline_on_hold_partner_adds_order_note()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'paylineOnHoldPartnerWebPaymentDetails');

        $res = array(
            'result' => array(
                'shortMessage' => 'OTHER_STATUS',
            ),
        );

        $result = $method->invoke($this->gateway, $this->order, $res);

        $this->assertFalse($result);
    }

    // =========================================
    // paylineCancelWebPaymentDetails()
    // =========================================

    /**
     * Test : paylineCancelWebPaymentDetails() définit le statut 'cancelled'
     */
    public function test_payline_cancel_web_payment_sets_cancelled_status()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'paylineCancelWebPaymentDetails');

        $res = array(
            'result' => array(
                'code' => '02314',
                'shortMessage' => 'CANCELLED',
            ),
        );

        $result = $method->invoke($this->gateway, $this->order, $res);

        $this->assertFalse($result);
    }

    /**
     * Test : paylineCancelWebPaymentDetails() ajoute une note d'annulation
     */
    public function test_payline_cancel_web_payment_adds_cancellation_note()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'paylineCancelWebPaymentDetails');

        $res = array(
            'result' => array(
                'code' => '02314',
                'shortMessage' => 'CANCELLED',
            ),
        );

        $result = $method->invoke($this->gateway, $this->order, $res);

        $this->assertFalse($result);
    }
}