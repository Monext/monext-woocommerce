<?php

/**
 * Tests unitaires pour WC_Payline_SDK
 *
 * Couvre l'intégration avec le SDK Monext (monext/monext-php v25.9).
 * Les tests utilisent des mocks pour éviter les appels API réels.
 *
 * Méthodes testées :
 * - isValidResponse() : Validation réponses API
 * - getExtensionVersion() : Version du plugin
 * - getMethodSettings() : Configuration gateway
 * - getSDK() : Instanciation SDK
 * - getPointOfSales() : Récupération POS
 * - getMerchantSettings() : Configuration compte Monext
 * - checkCredentials() : Validation credentials
 */
class WC_Payline_SDK_Test extends PaylineTestCase
{

    // -------------------------------
    // isValidResponse()
    // -------------------------------
    public function test_is_valid_response_success()
    {
        $isValid = $this->getPrivateMethod(WC_Payline_SDK::class, 'isValidResponse');
        $result = ['result' => ['code' => '00000']];

        $this->assertTrue($isValid->invoke(null, $result), 'isValidResponse() doit retourner true pour le code 00000');
    }

    public function test_is_valid_response_fallback()
    {
        $isValid = $this->getPrivateMethod(WC_Payline_SDK::class, 'isValidResponse');
        $result = ['result' => ['code' => '12345']];

        $this->assertTrue($isValid->invoke(null, $result, ['12345']), 'isValidResponse() doit accepter un code dans la liste fallback');
        $this->assertFalse($isValid->invoke(null, $result, ['5321']), 'isValidResponse() doit rejeter un code absent de la liste fallback');
    }

    /**
     * @dataProvider invalidResponseProvider
     */
    public function test_is_valid_response_returns_false_for_invalid_data($input)
    {
        $isValid = $this->getPrivateMethod(WC_Payline_SDK::class, 'isValidResponse');

        $this->assertFalse($isValid->invoke(null, $input), 'isValidResponse() doit retourner false pour des données invalides');
    }

    public static function invalidResponseProvider(): array
    {
        return array(
            'code erreur 99999' => array(
                array('result' => array('code' => '99999'))
            ),
            'input null' => array(
                null
            ),
            'input string' => array(
                'string'
            ),
            'missing result key (empty)' => array(
                array()
            ),
            'missing result key (other key)' => array(
                array('other' => 'data')
            ),
            'result without code key (empty result)' => array(
                array('result' => array())
            ),
            'result without code key (message only)' => array(
                array('result' => array('message' => 'error'))
            ),
        );
    }

    // -------------------------------
    // getExtensionVersion()
    // -------------------------------
    public function test_get_extension_version_format()
    {
        $getVersion = $this->getPrivateMethod(WC_Payline_SDK::class, 'getExtensionVersion');

        $version = $getVersion->invoke(null);

        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $version, 'getExtensionVersion() doit retourner un format semver (X.Y.Z)');
    }

    public function test_get_extension_version_uses_plugin_data()
    {
        MockPlugins::$data['payline']['Version'] = '9.9.9';

        $getVersion = $this->getPrivateMethod(WC_Payline_SDK::class, 'getExtensionVersion');

        $version = $getVersion->invoke(null);
        $this->assertEquals('9.9.9', $version, 'getExtensionVersion() doit retourner la version du plugin');
    }

    // -------------------------------
    // getMethodSettings()
    // -------------------------------
    public function test_get_method_settings_without_payment_id()
    {
        $getSettings = $this->getPrivateMethod(WC_Payline_SDK::class, 'getMethodSettings');

        $settings = $getSettings->invoke(null);
        $this->assertSame('MERCH', $settings['merchant_id'], 'getMethodSettings() doit retourner le merchant_id par défaut');
        $this->assertSame('KEY', $settings['access_key'], 'getMethodSettings() doit retourner l\'access_key par défaut');
    }

    public function test_get_method_settings_with_payment_id()
    {
        $getSettings = $this->getPrivateMethod(WC_Payline_SDK::class, 'getMethodSettings');

        $settings = $getSettings->invoke(null, 'payline_nx');
        $this->assertSame('NX_MERCH', $settings['merchant_id'], 'getMethodSettings() doit retourner le merchant_id NX');
        $this->assertSame('NX_KEY', $settings['access_key'], 'getMethodSettings() doit retourner l\'access_key NX');
        $this->assertSame('redirection', $settings['widget_integration'], 'getMethodSettings() doit retourner widget_integration pour NX');
    }

    public function test_get_method_settings_merges_payment_settings()
    {
        MockOptions::$data['woocommerce_payline_nx_settings']['custom_option'] = 'custom_value';

        $getSettings = $this->getPrivateMethod(WC_Payline_SDK::class, 'getMethodSettings');

        $settings = $getSettings->invoke(null, 'payline_nx');

        $this->assertSame('HOMO', $settings['environment'], 'getMethodSettings() doit merger l\'environnement');
        $this->assertSame('custom_value', $settings['custom_option'], 'getMethodSettings() doit merger les options custom');
    }

    public function test_get_method_settings_with_empty_payment_settings()
    {
        MockOptions::$data['woocommerce_payline_empty_settings'] = [];

        $getSettings = $this->getPrivateMethod(WC_Payline_SDK::class, 'getMethodSettings');

        $settings = $getSettings->invoke(null, 'payline_empty');

        $this->assertSame('MERCH', $settings['merchant_id'], 'getMethodSettings() doit fallback sur les settings globaux');
    }

    public function test_get_method_settings_filters_empty_values()
    {
        MockOptions::$data['woocommerce_payline_nx_settings']['merchant_id'] = '';

        $getSettings = $this->getPrivateMethod(WC_Payline_SDK::class, 'getMethodSettings');

        $settings = $getSettings->invoke(null, 'payline_nx');

        $this->assertSame('MERCH', $settings['merchant_id'], 'getMethodSettings() doit ignorer les valeurs vides et garder le global');
    }

    // -------------------------------
    // getSDK()
    // -------------------------------
    public function test_get_sdk_returns_instance()
    {
        $sdk = WC_Payline_SDK::getSDK();
        $this->assertInstanceOf(Payline\PaylineSDK::class, $sdk, 'getSDK() doit retourner une instance de PaylineSDK');
    }

    public function test_get_sdk_returns_null_if_merchant_id_missing()
    {
        unset(MockOptions::$data['woocommerce_payline_settings']['merchant_id']);

        $sdk = WC_Payline_SDK::getSDK();
        $this->assertNull($sdk, 'getSDK() doit retourner null si merchant_id est manquant');
    }

    public function test_get_sdk_returns_null_if_access_key_missing()
    {
        unset(MockOptions::$data['woocommerce_payline_settings']['access_key']);

        $sdk = WC_Payline_SDK::getSDK();
        $this->assertNull($sdk, 'getSDK() doit retourner null si access_key est manquant');
    }

    public function test_get_sdk_returns_null_if_credentials_empty()
    {
        MockOptions::$data['woocommerce_payline_settings']['merchant_id'] = '';
        MockOptions::$data['woocommerce_payline_settings']['access_key'] = '';

        $sdk = WC_Payline_SDK::getSDK();
        $this->assertNull($sdk, 'getSDK() doit retourner null si les credentials sont vides');
    }

    public function test_get_sdk_with_specific_payment_id()
    {
        $sdk = WC_Payline_SDK::getSDK('payline_nx');
        $this->assertInstanceOf(Payline\PaylineSDK::class, $sdk, 'getSDK() doit retourner un SDK pour un payment_id spécifique');
    }

    public function test_get_sdk_with_prod_environment()
    {
        MockOptions::$data['woocommerce_payline_settings']['environment'] = 'PROD';

        $sdk = WC_Payline_SDK::getSDK();
        $this->assertInstanceOf(Payline\PaylineSDK::class, $sdk, 'getSDK() doit fonctionner en environnement PROD');
    }

    public function test_get_sdk_with_proxy_settings()
    {
        MockOptions::$data['woocommerce_payline_settings']['proxy_host'] = 'proxy.example.com';
        MockOptions::$data['woocommerce_payline_settings']['proxy_port'] = '8080';
        MockOptions::$data['woocommerce_payline_settings']['proxy_login'] = 'user';
        MockOptions::$data['woocommerce_payline_settings']['proxy_password'] = 'pass';

        $sdk = WC_Payline_SDK::getSDK();
        $this->assertInstanceOf(Payline\PaylineSDK::class, $sdk, 'getSDK() doit fonctionner avec des settings proxy');
    }

    public function test_get_sdk_fallback_woocommerce_info()
    {
        MockPlugins::$data['woocommerce'] = array(
            'Name' => '',
            'Version' => ''
        );

        $sdk = WC_Payline_SDK::getSDK();
        $this->assertInstanceOf(Payline\PaylineSDK::class, $sdk, 'getSDK() doit utiliser des valeurs par défaut si les métadonnées WC sont vides');
    }

    // -------------------------------
    // getPointOfSales()
    // -------------------------------
    public function test_get_point_of_sales_returns_empty_array_when_credentials_invalid()
    {
        MockOptions::$data['woocommerce_payline_settings']['merchant_id'] = '';

        $result = WC_Payline_SDK::getPointOfSales();

        $this->assertIsArray($result, 'getPointOfSales() doit retourner un tableau');
        $this->assertEmpty($result, 'getPointOfSales() doit retourner un tableau vide quand les credentials sont invalides');
    }

    /**
     * @runInSeparateProcess Nécessaire car getPointOfSales() utilise des variables statiques
     * @preserveGlobalState disabled
     */
    public function test_get_point_of_sales_with_valid_sdk_but_api_error()
    {
        $this->reloadBootstrap();

        $result = WC_Payline_SDK::getPointOfSales();

        $this->assertIsArray($result, 'getPointOfSales() doit retourner un tableau même en cas d\'erreur API');
        $this->assertEmpty($result, 'getPointOfSales() doit retourner un tableau vide en cas d\'erreur API');
    }

    public function test_get_point_of_sales_returns_pos_list_when_valid()
    {
        $this->markTestIncomplete(
            'Ce test ne peut pas être fiable sans mocker checkCredentials() qui utilise un état statique. ' .
            'Le résultat dépend de l\'état du SDK entre les tests. Pour une couverture complète, ' .
            'il faudrait mocker la méthode checkCredentials() ou utiliser un processus isolé avec un mock HTTP.'
        );
    }

    public function test_get_point_of_sales_normalizes_single_pos()
    {
        $this->markTestIncomplete(
            'Ce test ne peut pas être fiable sans mocker checkCredentials() qui utilise un état statique. ' .
            'Même problème que test_get_point_of_sales_returns_pos_list_when_valid.'
        );
    }

    // -------------------------------
    // getMerchantSettings()
    // -------------------------------
    /**
     * @runInSeparateProcess Nécessaire car getMerchantSettings() utilise une variable locale statique
     * @preserveGlobalState disabled
     */
    public function test_get_merchant_settings_returns_null_when_sdk_null()
    {
        $this->reloadBootstrap();

        MockOptions::$data['woocommerce_payline_settings']['merchant_id'] = '';

        $result = WC_Payline_SDK::getMerchantSettings();

        $this->assertNull($result, 'getMerchantSettings() doit retourner null quand le SDK est null');
    }

    /**
     * @runInSeparateProcess Nécessaire car getMerchantSettings() utilise une variable statique
     * @preserveGlobalState disabled
     */
    public function test_get_merchant_settings_calls_api_but_returns_error_response()
    {
        $this->markTestIncomplete(
            'Ce test effectue un appel API réel vers Monext avec des credentials de test (MERCH/KEY). ' .
            'Le résultat dépend de la disponibilité du réseau et de l\'API Monext. ' .
            'Pour un test fiable, il faudrait mocker l\'appel HTTP ou utiliser un serveur de test local.'
        );
    }

    // -------------------------------
    // checkCredentials()
    // -------------------------------
    public function test_check_credentials_returns_false_when_sdk_null()
    {
        MockOptions::$data['woocommerce_payline_settings']['merchant_id'] = '';

        $result = WC_Payline_SDK::checkCredentials();

        $this->assertFalse($result, 'checkCredentials() doit retourner false quand le SDK est null');
    }

    /**
     * @runInSeparateProcess Nécessaire car checkCredentials() utilise des variables statiques
     * @preserveGlobalState disabled
     */
    public function test_check_credentials_with_valid_sdk_but_invalid_api_response()
    {
        $this->markTestIncomplete(
            'Ce test effectue un appel API réel vers Monext. ' .
            'Le résultat dépend de la disponibilité du réseau et de l\'API Monext. ' .
            'Pour un test fiable, il faudrait mocker l\'appel HTTP.'
        );
    }
}
