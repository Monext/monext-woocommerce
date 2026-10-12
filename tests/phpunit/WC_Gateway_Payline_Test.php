<?php

/**
 * Tests unitaires pour WC_Gateway_Payline
 *
 * Teste la vraie classe WC_Gateway_Payline chargée par bootstrap.php.
 *
 * Compatibilité : PHP 7.4 - 8.4
 */
class WC_Gateway_Payline_Test extends PaylineTestCase
{
    /**
     * @dataProvider constructorPropertiesProvider
     */
    public function test_constructor_sets_properties($property, $expected)
    {
        $gateway = new WC_Gateway_Payline();

        $this->assertEquals($expected, $gateway->{$property}, "La propriété '{$property}' doit être égale à " . var_export($expected, true));
    }

    public static function constructorPropertiesProvider(): array
    {
        return array(
            'id to payline' => array('id', 'payline'),
            'title to payline' => array('title', 'payline'),
            'has_fields to false' => array('has_fields', false),
            'availability to false' => array('availability', false),
            'method_title to Monext' => array('method_title', 'Monext'),
        );
    }

    /**
     * @dataProvider removePaylineGatewayProvider
     */
    public function test_remove_payline_gateway($gateways, $expectedKeys)
    {
        $result = (new WC_Gateway_Payline())->remove_payline_gateway($gateways);

        $this->assertEquals($expectedKeys, array_keys($result));
    }

    public static function removePaylineGatewayProvider(): array
    {
        return array(
            'removes payline, keeps others' => array(
                array(
                    'payline' => new WC_Gateway_Payline(),
                    'payline_cpt' => new WC_Gateway_Payline_CPT(),
                ),
                array('payline_cpt'),
            ),
            'keeps gateways when payline absent' => array(
                array(
                    'payline_cpt' => new WC_Gateway_Payline_CPT(),
                ),
                array('payline_cpt'),
            ),
        );
    }

    /**
     * @dataProvider privateSettingsFieldsProvider
     */
    public function test_settings_fields_methods($methodName, $expectedKeys, $expectedCount)
    {
        $method = $this->getPrivateMethod(WC_Gateway_Payline::class, $methodName);
        $result = $method->invoke(new WC_Gateway_Payline());

        $this->assertCount($expectedCount, $result, "get{$methodName} doit retourner {$expectedCount} champs");
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result, "get{$methodName} doit contenir la clé '{$key}'");
        }
    }

    public static function privateSettingsFieldsProvider(): array
    {
        return array(
            'getCommonSettingsFields' => array(
                'getCommonSettingsFields',
                array('merchant_id', 'access_key', 'environment', 'pos'),
                4,
            ),
            'getAdvancedSettingsFields' => array(
                'getAdvancedSettingsFields',
                array('smartdisplay_parameter', 'debug', 'language'),
                3,
            ),
            'getErrorMessagesFields' => array(
                'getErrorMessagesFields',
                array('user_error_message_refused', 'user_error_message_cancelled', 'user_error_message_error'),
                3,
            ),
            'getProxySettingsFields' => array(
                'getProxySettingsFields',
                array('proxy_host', 'proxy_port', 'proxy_login', 'proxy_password'),
                4,
            ),
        );
    }

    /**
     * @dataProvider obfuscatedValueProvider
     */
    public function test_get_obfuctated_value($input, $nbToShow, $expected)
    {
        $method = $this->getPrivateMethod(WC_Gateway_Payline::class, 'getObfuctatedValue');
        $result = $method->invoke(new WC_Gateway_Payline(), $input, $nbToShow);

        $this->assertEquals($expected, $result, "getObfuctatedValue('{$input}', {$nbToShow}) doit retourner '{$expected}'");
    }

    public static function obfuscatedValueProvider(): array
    {
        return array(
            'masks characters' => array('12345678', 3, '*****678'),
            'returns same for short string' => array('123', 3, '123'),
            'returns empty for empty string' => array('', 3, ''),
            'custom nb_to_show' => array('ABCDEFGH', 2, '******GH'),
        );
    }

    public function test_init_form_fields_merges_all_settings()
    {
        $gateway = new WC_Gateway_Payline();

        $commonKeys      = array_keys($this->getPrivateMethod(WC_Gateway_Payline::class, 'getCommonSettingsFields')->invoke($gateway));
        $advancedKeys    = array_keys($this->getPrivateMethod(WC_Gateway_Payline::class, 'getAdvancedSettingsFields')->invoke($gateway));
        $errorMessageKeys = array_keys($this->getPrivateMethod(WC_Gateway_Payline::class, 'getErrorMessagesFields')->invoke($gateway));
        $proxyKeys       = array_keys($this->getPrivateMethod(WC_Gateway_Payline::class, 'getProxySettingsFields')->invoke($gateway));

        $expectedKeys = array_merge($commonKeys, $advancedKeys, $errorMessageKeys, $proxyKeys);
        $actualKeys   = array_keys($gateway->form_fields);

        $this->assertEquals($expectedKeys, $actualKeys, 'init_form_fields doit merger les 4 groupes de champs');
    }

    public function test_payline_success_web_payment_details_returns_false()
    {
        $gateway = new WC_Gateway_Payline();
        $order   = new WC_Order(123);
        $method  = $this->getPrivateMethod(WC_Gateway_Payline::class, 'paylineSuccessWebPaymentDetails');

        $this->assertFalse($method->invoke($gateway, $order, array()));
        $this->assertFalse($method->invoke($gateway, $order, array('result' => array('code' => '00000'))));
    }

    /**
     * @dataProvider validateAccessKeyProvider
     */
    public function test_validate_access_key_field($originalKey, $postedValue, $expected)
    {
        MockOptions::$data['woocommerce_payline_settings']['access_key'] = $originalKey;

        $gateway = new WC_Gateway_Payline();
        $result  = $gateway->validate_access_key_field('access_key', $postedValue);

        $this->assertEquals($expected, $result);
    }

    public static function validateAccessKeyProvider(): array
    {
        return array(
            'returns original when obfuscated matches' => array(
                'mysecretkey',
                '********key',
                'mysecretkey',
            ),
            'returns new value when not obfuscated' => array(
                'oldkey123',
                'brand_new_key',
                'brand_new_key',
            ),
            'returns new value when obfuscated does not match' => array(
                'oldkey123',
                '*********key',
                '*********key',
            ),
            'returns new value with empty original' => array(
                '',
                'newkey',
                'newkey',
            ),
            'returns empty when both empty' => array(
                '',
                '',
                '',
            ),
        );
    }

    /**
     * @dataProvider updateContractListProvider
     */
    public function test_update_contract_list($contracts, $expectedContracts)
    {
        $gateway = new WC_Gateway_Payline();
        $gateway->updateContractList($contracts);

        $stored = get_option('woocommerce_payline_pos_contracts_list', []);
        if (is_string($stored)) {
            $stored = unserialize($stored);
        }

        $this->assertEquals($expectedContracts, $stored);
    }

    public static function updateContractListProvider(): array
    {
        return array(
            'single contract (associative array)' => array(
                array(
                    'contract' => array(
                        'cardType' => 'CB',
                        'contractNumber' => '123456',
                        'label' => 'CB Contract',
                    ),
                ),
                array(
                    array(
                        'id' => 'CB-123456',
                        'label' => 'CB Contract',
                        'contractNumber' => '123456',
                    ),
                ),
            ),
            'multiple contracts (indexed array)' => array(
                array(
                    'contract' => array(
                        array(
                            'cardType' => 'CB',
                            'contractNumber' => '111',
                            'label' => 'CB Visa',
                        ),
                        array(
                            'cardType' => 'MC',
                            'contractNumber' => '222',
                            'label' => 'MC Master',
                        ),
                    ),
                ),
                array(
                    array(
                        'id' => 'CB-111',
                        'label' => 'CB Visa',
                        'contractNumber' => '111',
                    ),
                    array(
                        'id' => 'MC-222',
                        'label' => 'MC Master',
                        'contractNumber' => '222',
                    ),
                ),
            ),
            'empty contracts' => array(
                array('contract' => array()),
                array(),
            ),
        );
    }

    public function test_get_point_of_sales_list_empty_calls_update_and_returns_prompt()
    {
        MockOptions::$data['woocommerce_payline_pos_list'] = array();

        $gateway = new WC_Gateway_Payline();
        $result  = $gateway->getPointOfSalesList();

        // SDK::getPointOfSales() returns [] (no real credentials), so only the prompt is present
        $this->assertCount(1, $result);
        $this->assertStringContainsString('Point of Sale', $result[0]);
    }

    public function test_get_point_of_sales_list_returns_cached()
    {
        $cachedList = array('POS_A' => 'POS_A', 'POS_B' => 'POS_B');
        MockOptions::$data['woocommerce_payline_pos_list'] = serialize($cachedList);

        $gateway = new WC_Gateway_Payline();
        $result  = $gateway->getPointOfSalesList();

        $this->assertArrayHasKey(0, $result);
        $this->assertStringContainsString('Point of Sale', $result[0]);
        $this->assertArrayHasKey('POS_A', $result);
        $this->assertArrayHasKey('POS_B', $result);
    }
}