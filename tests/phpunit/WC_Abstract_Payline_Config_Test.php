<?php

/**
 * Tests unitaires pour WC_Abstract_Payline : Configuration et validation
 *
 * Teste les méthodes qui gèrent la configuration de la gateway et valident
 * que le compte Monext est correctement configuré (credentials, contrats,
 * environnement). Utilise MockOptions pour simuler les settings WordPress
 * et tester les différents scénarios (credentials manquants, environnement
 * HOMO vs PROD, validation des contrats).
 *
 * Méthodes testées :
 * - getConfigValueIfExists() : Récupération sécurisée de valeurs de configuration
 * - is_available() : Vérification disponibilité gateway pour le checkout
 * - is_account_connected() : Vérification connexion compte Monext valide
 * - needs_setup() : Détection si configuration initiale nécessaire
 * - getContractsList() : Récupération liste des contrats depuis la DB
 * - get_request_url() : Génération URLs callback pour retours Payline
 */
class WC_Abstract_Payline_Config_Test extends PaylineTestCase
{
    /** @var WC_Gateway_Payline_CPT */
    private $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new WC_Gateway_Payline_CPT();
    }

    // =========================================
    // getConfigValueIfExists()
    // =========================================

    /**
     * @throws ReflectionException
     */
    public function test_get_config_value_if_exists_returns_value_when_present()
    {
        $this->gateway = $this->setGatewaySettings(
            ['custom_key' => 'custom_value'],
            ['custom_key' => 'custom_value']
        );

        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getConfigValueIfExists');

        $result = $method->invoke($this->gateway, 'custom_key');

        $this->assertEquals('custom_value', $result);
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_config_value_if_exists_returns_false_when_key_missing()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getConfigValueIfExists');

        $result = $method->invoke($this->gateway, 'nonexistent_key');

        $this->assertFalse($result);
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_config_value_if_exists_returns_false_for_empty_string()
    {
        $this->gateway = $this->setGatewaySettings(
            ['empty_key' => ''],
            ['empty_key' => '']
        );

        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getConfigValueIfExists');

        $result = $method->invoke($this->gateway, 'empty_key');

        $this->assertFalse($result);
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_config_value_if_exists_returns_false_for_null()
    {
        MockOptions::$data['woocommerce_payline_settings']['null_key'] = null;
        MockOptions::$data['woocommerce_payline_cpt_settings']['null_key'] = null;
        $this->gateway = new WC_Gateway_Payline_CPT();

        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getConfigValueIfExists');

        $result = $method->invoke($this->gateway, 'null_key');

        $this->assertFalse($result);
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_config_value_if_exists_returns_array_value()
    {
        MockOptions::$data['woocommerce_payline_settings']['array_key'] = array('item1', 'item2');
        MockOptions::$data['woocommerce_payline_cpt_settings']['array_key'] = array('item1', 'item2');
        $this->gateway = new WC_Gateway_Payline_CPT();

        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getConfigValueIfExists');

        $result = $method->invoke($this->gateway, 'array_key');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    // =========================================
    // is_available()
    // =========================================

    public function test_is_available_returns_true_when_enabled_and_contracts_set()
    {
        MockOptions::$data['woocommerce_payline_settings']['merchant_id'] = 'MERCH';
        MockOptions::$data['woocommerce_payline_settings']['access_key'] = 'KEY';
        MockOptions::$data['woocommerce_payline_settings']['pos'] = 'POS1';
        MockOptions::$data['woocommerce_payline_cpt_settings']['enabled'] = 'yes';
        MockOptions::$data['woocommerce_payline_cpt_settings']['primary_contracts'] = array('CB-123');
        $this->gateway = new WC_Gateway_Payline_CPT();

        $result = $this->gateway->is_available();

        $this->assertTrue($result);
    }

    public function test_is_available_returns_false_when_no_primary_contracts()
    {
        MockOptions::$data['woocommerce_payline_settings']['merchant_id'] = 'MERCH';
        MockOptions::$data['woocommerce_payline_settings']['access_key'] = 'KEY';
        MockOptions::$data['woocommerce_payline_settings']['pos'] = 'POS1';
        MockOptions::$data['woocommerce_payline_cpt_settings']['enabled'] = 'yes';
        MockOptions::$data['woocommerce_payline_cpt_settings']['primary_contracts'] = array();
        $this->gateway = new WC_Gateway_Payline_CPT();

        $result = $this->gateway->is_available();

        $this->assertFalse($result);
    }

    public function test_is_available_returns_false_when_primary_contracts_missing()
    {
        MockOptions::$data['woocommerce_payline_settings']['merchant_id'] = 'MERCH';
        MockOptions::$data['woocommerce_payline_settings']['access_key'] = 'KEY';
        MockOptions::$data['woocommerce_payline_settings']['pos'] = 'POS1';
        MockOptions::$data['woocommerce_payline_cpt_settings']['enabled'] = 'yes';
        unset(MockOptions::$data['woocommerce_payline_cpt_settings']['primary_contracts']);
        $this->gateway = new WC_Gateway_Payline_CPT();

        $result = $this->gateway->is_available();

        $this->assertFalse($result);
    }

    // =========================================
    // is_account_connected()
    // =========================================

    public function test_is_account_connected_returns_true_when_all_credentials_set()
    {
        MockOptions::$data['woocommerce_payline_settings']['merchant_id'] = 'MERCH';
        MockOptions::$data['woocommerce_payline_settings']['access_key'] = 'KEY';
        MockOptions::$data['woocommerce_payline_settings']['pos'] = 'POS1';
        $this->gateway = new WC_Gateway_Payline_CPT();

        $result = $this->gateway->is_account_connected();

        $this->assertTrue($result);
    }

    public function test_is_account_connected_returns_false_when_merchant_id_empty()
    {
        MockOptions::$data['woocommerce_payline_settings']['merchant_id'] = '';
        MockOptions::$data['woocommerce_payline_settings']['access_key'] = 'KEY';
        MockOptions::$data['woocommerce_payline_settings']['pos'] = 'POS1';
        $this->gateway = new WC_Gateway_Payline_CPT();

        $result = $this->gateway->is_account_connected();

        $this->assertFalse($result);
    }

    public function test_is_account_connected_returns_false_when_merchant_id_missing()
    {
        // Supprimer merchant_id des settings globaux ET CPT (completeSettings fait un merge)
        $this->gateway = $this->unsetGatewaySettings(
            array('merchant_id'),  // globalKeys
            array('merchant_id')   // cptKeys
        );
        MockOptions::$data['woocommerce_payline_settings']['access_key'] = 'KEY';
        MockOptions::$data['woocommerce_payline_settings']['pos'] = 'POS1';

        $result = $this->gateway->is_account_connected();

        $this->assertFalse($result);
    }

    public function test_is_account_connected_returns_false_when_access_key_empty()
    {
        MockOptions::$data['woocommerce_payline_settings']['merchant_id'] = 'MERCH';
        MockOptions::$data['woocommerce_payline_settings']['access_key'] = '';
        MockOptions::$data['woocommerce_payline_settings']['pos'] = 'POS1';
        $this->gateway = new WC_Gateway_Payline_CPT();

        $result = $this->gateway->is_account_connected();

        $this->assertFalse($result);
    }

    public function test_is_account_connected_returns_false_when_access_key_missing()
    {
        // Supprimer des settings globaux ET CPT (completeSettings fait un merge)
        MockOptions::$data['woocommerce_payline_settings']['merchant_id'] = 'MERCH';
        unset(MockOptions::$data['woocommerce_payline_settings']['access_key']);
        unset(MockOptions::$data['woocommerce_payline_cpt_settings']['access_key']);
        MockOptions::$data['woocommerce_payline_settings']['pos'] = 'POS1';
        $this->gateway = new WC_Gateway_Payline_CPT();

        $result = $this->gateway->is_account_connected();

        $this->assertFalse($result);
    }

    public function test_is_account_connected_returns_false_when_pos_empty()
    {
        MockOptions::$data['woocommerce_payline_settings']['merchant_id'] = 'MERCH';
        MockOptions::$data['woocommerce_payline_settings']['access_key'] = 'KEY';
        MockOptions::$data['woocommerce_payline_settings']['pos'] = '';
        $this->gateway = new WC_Gateway_Payline_CPT();

        $result = $this->gateway->is_account_connected();

        $this->assertFalse($result);
    }

    public function test_is_account_connected_returns_false_when_pos_missing()
    {
        MockOptions::$data['woocommerce_payline_settings']['merchant_id'] = 'MERCH';
        MockOptions::$data['woocommerce_payline_settings']['access_key'] = 'KEY';
        unset(MockOptions::$data['woocommerce_payline_settings']['pos']);
        $this->gateway = new WC_Gateway_Payline_CPT();

        $result = $this->gateway->is_account_connected();

        $this->assertFalse($result);
    }

    // =========================================
    // needs_setup()
    // =========================================

    public function test_needs_setup_returns_false_when_account_connected()
    {
        MockOptions::$data['woocommerce_payline_settings']['merchant_id'] = 'MERCH';
        MockOptions::$data['woocommerce_payline_settings']['access_key'] = 'KEY';
        MockOptions::$data['woocommerce_payline_settings']['pos'] = 'POS1';
        $this->gateway = new WC_Gateway_Payline_CPT();

        $result = $this->gateway->needs_setup();

        $this->assertFalse($result);
    }

    public function test_needs_setup_returns_true_when_merchant_id_missing()
    {
        MockOptions::$data['woocommerce_payline_settings']['merchant_id'] = '';
        MockOptions::$data['woocommerce_payline_settings']['access_key'] = 'KEY';
        MockOptions::$data['woocommerce_payline_settings']['pos'] = 'POS1';
        $this->gateway = new WC_Gateway_Payline_CPT();

        $result = $this->gateway->needs_setup();

        $this->assertTrue($result);
    }

    public function test_needs_setup_returns_true_when_access_key_missing()
    {
        MockOptions::$data['woocommerce_payline_settings']['merchant_id'] = 'MERCH';
        MockOptions::$data['woocommerce_payline_settings']['access_key'] = '';
        MockOptions::$data['woocommerce_payline_settings']['pos'] = 'POS1';
        $this->gateway = new WC_Gateway_Payline_CPT();

        $result = $this->gateway->needs_setup();

        $this->assertTrue($result);
    }

    public function test_needs_setup_returns_true_when_pos_missing()
    {
        MockOptions::$data['woocommerce_payline_settings']['merchant_id'] = 'MERCH';
        MockOptions::$data['woocommerce_payline_settings']['access_key'] = 'KEY';
        MockOptions::$data['woocommerce_payline_settings']['pos'] = '';
        $this->gateway = new WC_Gateway_Payline_CPT();

        $result = $this->gateway->needs_setup();

        $this->assertTrue($result);
    }

    public function test_needs_setup_sets_enabled_to_yes_when_account_connected()
    {
        MockOptions::$data['woocommerce_payline_settings']['merchant_id'] = 'MERCH';
        MockOptions::$data['woocommerce_payline_settings']['access_key'] = 'KEY';
        MockOptions::$data['woocommerce_payline_settings']['pos'] = 'POS1';
        $this->gateway = new WC_Gateway_Payline_CPT();

        $this->gateway->needs_setup();

        $this->assertEquals('yes', $this->gateway->enabled);
    }

    public function test_needs_setup_sets_enabled_to_no_when_account_not_connected()
    {
        MockOptions::$data['woocommerce_payline_settings']['merchant_id'] = '';
        $this->gateway = new WC_Gateway_Payline_CPT();

        $this->gateway->needs_setup();

        $this->assertEquals('no', $this->gateway->enabled);
    }

    // =========================================
    // getContractsList()
    // =========================================

    public function test_get_contracts_list_returns_empty_array_when_option_empty()
    {
        MockOptions::$data['woocommerce_payline_pos_contracts_list'] = array();

        $result = $this->gateway->getContractsList();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_get_contracts_list_returns_empty_array_when_option_missing()
    {
        unset(MockOptions::$data['woocommerce_payline_pos_contracts_list']);

        $result = $this->gateway->getContractsList();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_get_contracts_list_returns_formatted_contracts()
    {
        $contracts = array(
            array('id' => 'CB-123', 'label' => 'Carte Bancaire', 'contractNumber' => '123'),
            array('id' => 'VISA-456', 'label' => 'Visa', 'contractNumber' => '456'),
        );
        MockOptions::$data['woocommerce_payline_pos_contracts_list'] = serialize($contracts);

        $result = $this->gateway->getContractsList();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('123', $result);
        $this->assertArrayHasKey('456', $result);
        $this->assertEquals('Carte Bancaire', $result['123']);
        $this->assertEquals('Visa', $result['456']);
    }

    public function test_get_contracts_list_returns_single_contract()
    {
        $contracts = array(
            array('id' => 'CB-123', 'label' => 'Carte Bancaire', 'contractNumber' => '123'),
        );
        MockOptions::$data['woocommerce_payline_pos_contracts_list'] = serialize($contracts);

        $result = $this->gateway->getContractsList();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('Carte Bancaire', $result['123']);
    }

    // =========================================
    // get_request_url()
    // =========================================

    /**
     * @throws ReflectionException
     */
    public function test_get_request_url_returns_valid_url()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'get_request_url');

        $result = $method->invoke($this->gateway, 'return');

        // Vérifier que c'est une URL valide
        $this->assertNotEmpty($result);
        $this->assertTrue(filter_var($result, FILTER_VALIDATE_URL) !== false, 'La chaîne retournée doit être une URL valide');

        // Vérifier la structure de l'URL
        $parsedUrl = parse_url($result);
        $this->assertNotFalse($parsedUrl, 'L\'URL doit être parsable');
        $this->assertArrayHasKey('scheme', $parsedUrl, 'L\'URL doit avoir un scheme');
        $this->assertArrayHasKey('host', $parsedUrl, 'L\'URL doit avoir un host');
        $this->assertContains($parsedUrl['scheme'], array('http', 'https'), 'Le scheme doit être http ou https');
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_request_url_contains_wc_api_param()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'get_request_url');

        $result = $method->invoke($this->gateway, 'return');

        $this->assertStringContainsString('wc-api=', $result);
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_request_url_contains_url_type_param()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'get_request_url');

        $result = $method->invoke($this->gateway, 'notification');

        $this->assertStringContainsString('url_type=notification', $result);
    }

    /**
     * @throws ReflectionException
     * @dataProvider urlTypesProvider
     */
    public function test_get_request_url_handles_different_url_types($urlType)
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'get_request_url');

        $result = $method->invoke($this->gateway, $urlType);

        $this->assertStringContainsString('url_type=' . $urlType, $result);
    }

    public static function urlTypesProvider(): array
    {
        return array(
            'notification' => array('notification'),
            'return' => array('return'),
            'cancel' => array('cancel'),
            'webhook' => array('webhook'),
            'resetToken' => array('resetToken'),
        );
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_request_url_contains_gateway_class_name()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'get_request_url');

        $result = $method->invoke($this->gateway, 'return');

        $this->assertStringContainsString('WC_Gateway_Payline_CPT', $result);
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_request_url_with_empty_url_type()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'get_request_url');

        $result = $method->invoke($this->gateway, '');

        $this->assertStringContainsString('url_type=', $result);
        $this->assertStringContainsString('wc-api=', $result);
    }
}
