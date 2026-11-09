<?php

use Payline\Api\MonextCompatClient as PaylineSDK;
use Payline\Api\ApiInterface;

/**
 * Tests unitaires pour WC_Payline_SDK
 *
 * Couvre l'intégration avec le SDK Monext (monext/monext-php v25.9).
 * Les tests utilisent des mocks pour éviter les appels API réels.
 *
 * Méthodes testées :
 * - getExtensionVersion() : Version du plugin
 * - getMethodSettings() : Configuration gateway
 * - getSDK() : Instanciation SDK
 */
class WC_Payline_SDK_Test extends PaylineTestCase
{

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
        $this->assertInstanceOf(PaylineSDK::class, $sdk, 'getSDK() doit retourner une instance de PaylineSDK');
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
        $this->assertInstanceOf(PaylineSDK::class, $sdk, 'getSDK() doit retourner un SDK pour un payment_id spécifique');
    }

    public function test_get_sdk_with_prod_environment()
    {
        MockOptions::$data['woocommerce_payline_settings']['environment'] = 'PROD';

        $sdk = WC_Payline_SDK::getSDK();
        $this->assertInstanceOf(PaylineSDK::class, $sdk, 'getSDK() doit fonctionner en environnement PROD');
    }

    public function test_get_sdk_with_proxy_settings()
    {
        MockOptions::$data['woocommerce_payline_settings']['proxy_host'] = 'proxy.example.com';
        MockOptions::$data['woocommerce_payline_settings']['proxy_port'] = '8080';
        MockOptions::$data['woocommerce_payline_settings']['proxy_login'] = 'user';
        MockOptions::$data['woocommerce_payline_settings']['proxy_password'] = 'pass';

        $sdk = WC_Payline_SDK::getSDK();
        $this->assertInstanceOf(PaylineSDK::class, $sdk, 'getSDK() doit fonctionner avec des settings proxy');
    }

    public function test_get_sdk_fallback_woocommerce_info()
    {
        MockPlugins::$data['woocommerce'] = array(
            'Name' => '',
            'Version' => ''
        );

        $sdk = WC_Payline_SDK::getSDK();
        $this->assertInstanceOf(PaylineSDK::class, $sdk);
    }

    // -------------------------------
    // checkCredentials()
    // -------------------------------
}
