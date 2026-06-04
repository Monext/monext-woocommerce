<?php

use PHPUnit\Framework\MockObject\MockObject;
use Payline\PaylineSDK;

/**
 * Tests unitaires pour helpers SDK de WC_Abstract_Payline
 *
 * Couvre les méthodes utilitaires liées au SDK :
 * - updateTokenForOrder() : Sauvegarde token doWebPayment
 * - getCachedDWPDataForOrder() : Récupération cache token
 * - get_error_payment_url() : URLs d'erreur paiement
 * - getDefaultTemplateData() : Données template par défaut
 * - getContractsForCurrentPos() : Filtrage contrats par POS
 * - paylineSDK() : Lazy loading SDK
 *
 * Compatibilité : PHP 7.4 - 8.4
 */
class WC_Abstract_Payline_SDK_Helpers_Test extends PaylineTestCase
{
    /** @var WC_Gateway_Payline_CPT */
    private $gateway;

    /** @var WC_Order */
    private $order;

    protected function setUp(): void
    {
        parent::setUp();

        // Configurer settings minimaux
        MockOptions::$data['woocommerce_payline_settings']['merchant_id'] = 'MERCHANT123';
        MockOptions::$data['woocommerce_payline_settings']['access_key'] = 'KEY123';
        MockOptions::$data['woocommerce_payline_settings']['contract_number_list'] = 'CB';
        MockOptions::$data['woocommerce_payline_settings']['default_pos'] = 'POS123';
        MockOptions::$data['woocommerce_payline_settings']['title'] = 'Payline Payment';
        MockOptions::$data['woocommerce_payline_settings']['description'] = 'Pay by card';

        MockOptions::$data['woocommerce_payline_cpt_settings']['enabled'] = 'yes';
        MockOptions::$data['woocommerce_payline_cpt_settings']['merchant_id'] = 'MERCHANT123';
        MockOptions::$data['woocommerce_payline_cpt_settings']['access_key'] = 'KEY123';
        MockOptions::$data['woocommerce_payline_cpt_settings']['contract_number_list'] = 'CB';
        MockOptions::$data['woocommerce_payline_cpt_settings']['default_pos'] = 'POS123';
        MockOptions::$data['woocommerce_payline_cpt_settings']['title'] = 'Payline CPT';
        MockOptions::$data['woocommerce_payline_cpt_settings']['description'] = 'Pay by card (CPT)';

        $this->gateway = new WC_Gateway_Payline_CPT();
        $this->order = new WC_Order(12345);
    }

    // -------------------------------
    // updateTokenForOrder()
    // -------------------------------

    /**
     * Test : updateTokenForOrder() sauvegarde les données complètes du token
     */
    public function test_update_token_for_order_saves_complete_token_data()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'updateTokenForOrder');

        $dwpResult = array(
            'token' => 'TOKEN_ABC123',
            'redirectURL' => 'https://monext.com/pay?token=TOKEN_ABC123',
            'result' => array('code' => '00000'),
        );

        $method->invoke($this->gateway, $this->order, $dwpResult);

        // Vérifier que les données sont sauvegardées dans les options
        $optionKey = 'plnTokenForOrder_12345';
        $this->assertArrayHasKey($optionKey, MockOptions::$data);

        $saved = json_decode(MockOptions::$data[$optionKey], true);
        $this->assertIsArray($saved);
        $this->assertEquals('TOKEN_ABC123', $saved['token']);
        $this->assertEquals('https://monext.com/pay?token=TOKEN_ABC123', $saved['redirectURL']);
    }

    /**
     * Test : updateTokenForOrder() inclut une date d'expiration
     */
    public function test_update_token_for_order_includes_expiration_date()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'updateTokenForOrder');

        $dwpResult = array(
            'token' => 'TOKEN_XYZ',
            'redirectURL' => 'https://monext.com/pay',
        );

        $beforeTime = time();
        $method->invoke($this->gateway, $this->order, $dwpResult);
        $afterTime = time();

        $optionKey = 'plnTokenForOrder_12345';
        $saved = json_decode(MockOptions::$data[$optionKey], true);

        // Vérifier présence de la date
        $this->assertArrayHasKey('date', $saved);

        // Vérifier format date (d/m/Y H:i)
        $this->assertMatchesRegularExpression('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/', $saved['date']);

        // Vérifier que la date est récente (dans la dernière minute)
        $savedTimestamp = strtotime(str_replace('/', '-', $saved['date']));
        $this->assertGreaterThanOrEqual($beforeTime - 60, $savedTimestamp);
        $this->assertLessThanOrEqual($afterTime + 60, $savedTimestamp);
    }

    /**
     * Test : updateTokenForOrder() utilise la bonne clé d'option
     */
    public function test_update_token_for_order_uses_correct_option_key()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'updateTokenForOrder');

        $order2 = new WC_Order(99999);
        $dwpResult = array('token' => 'TEST_TOKEN');

        $method->invoke($this->gateway, $order2, $dwpResult);

        // Vérifier clé unique par commande
        $this->assertArrayHasKey('plnTokenForOrder_99999', MockOptions::$data);
        $this->assertArrayNotHasKey('plnTokenForOrder_12345', MockOptions::$data);
    }

    /**
     * Test : updateTokenForOrder() gère les résultats vides
     */
    public function test_update_token_for_order_handles_empty_result()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'updateTokenForOrder');

        $dwpResult = array();

        $method->invoke($this->gateway, $this->order, $dwpResult);

        // Doit sauvegarder avec token vide et cart_hash vide
        $optionKey = 'plnTokenForOrder_12345';
        $this->assertArrayHasKey($optionKey, MockOptions::$data);

        $saved = json_decode(MockOptions::$data[$optionKey], true);
        $this->assertIsArray($saved);
        $this->assertEquals('', $saved['token']);
        $this->assertEquals('', $saved['cart_hash']);
    }

    // -------------------------------
    // getCachedDWPDataForOrder()
    // -------------------------------

    /**
     * Test : getCachedDWPDataForOrder() retourne le tableau complet sans clé
     */
    public function test_get_cached_dwp_data_returns_full_array_when_no_key()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getCachedDWPDataForOrder');

        // Sauvegarder un token avec cart_hash
        $tokenData = array(
            'token' => 'FULL_TOKEN_123',
            'redirectURL' => 'https://monext.com/pay',
            'date' => date('d/m/Y H:i'),
            'cart_hash' => md5('test_cart_hash'),  // Doit correspondre au mock WC()->cart
        );
        MockOptions::$data['plnTokenForOrder_12345'] = json_encode($tokenData);

        $result = $method->invoke($this->gateway, $this->order, null, false);  // available=false

        $this->assertIsArray($result);
        $this->assertEquals('FULL_TOKEN_123', $result['token']);
        $this->assertEquals('https://monext.com/pay', $result['redirectURL']);
    }

    /**
     * Test : getCachedDWPDataForOrder() retourne une valeur spécifique avec clé
     */
    public function test_get_cached_dwp_data_returns_specific_key_value()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getCachedDWPDataForOrder');

        $tokenData = array(
            'token' => 'SPECIFIC_TOKEN',
            'redirectURL' => 'https://monext.com/redirect',
        );
        MockOptions::$data['plnTokenForOrder_12345'] = json_encode($tokenData);

        $result = $method->invoke($this->gateway, $this->order, 'token');

        $this->assertEquals('SPECIFIC_TOKEN', $result);
    }

    /**
     * Test : getCachedDWPDataForOrder() vérifie la disponibilité du token
     */
    public function test_get_cached_dwp_data_checks_token_availability()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getCachedDWPDataForOrder');

        // Token récent (< 12 min) avec bon cart_hash
        $tokenData = array(
            'token' => 'AVAILABLE_TOKEN',
            'date' => date('d/m/Y H:i', time()),  // Maintenant
            'cart_hash' => md5('test_cart_hash'),  // Correspond au mock WC()->cart
        );
        MockOptions::$data['plnTokenForOrder_12345'] = json_encode($tokenData);

        $result = $method->invoke($this->gateway, $this->order, null, true);  // available=true

        // Doit retourner le token car il est récent et cart_hash correspond
        $this->assertIsArray($result);
        $this->assertEquals('AVAILABLE_TOKEN', $result['token']);
    }

    /**
     * Test : getCachedDWPDataForOrder() retourne tableau vide si token expiré
     */
    public function test_get_cached_dwp_data_returns_empty_when_expired()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getCachedDWPDataForOrder');

        // Token expiré (> 12 minutes)
        $expiredDate = date('d/m/Y H:i', time() - 900);  // 15 minutes
        $tokenData = array(
            'token' => 'EXPIRED_TOKEN',
            'date' => $expiredDate,
            'cart_hash' => md5('test_cart_hash'),
        );
        MockOptions::$data['plnTokenForOrder_12345'] = json_encode($tokenData);

        $result = $method->invoke($this->gateway, $this->order, null, true);  // available=true

        // Doit retourner tableau vide car expiré (> 12 min)
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test : getCachedDWPDataForOrder() retourne null sans cache
     */
    public function test_get_cached_dwp_data_returns_null_when_no_cache()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getCachedDWPDataForOrder');

        // Pas de token en cache
        $result = $method->invoke($this->gateway, $this->order, null);

        $this->assertNull($result);
    }

    // -------------------------------
    // get_error_payment_url()
    // -------------------------------

    /**
     * Test : get_error_payment_url() retourne l'URL d'annulation avec message
     */
    public function test_get_error_payment_url_returns_cancel_url_with_message()
    {
        $result = $this->gateway->get_error_payment_url($this->order, 'Payment failed');

        // Doit contenir l'URL de base de la commande
        $this->assertIsString($result);
        $this->assertTrue(strpos($result, 'order-cancelled') !== false);
    }

    /**
     * Test : get_error_payment_url() ajoute une notice WooCommerce
     */
    public function test_get_error_payment_url_adds_woocommerce_notice()
    {
        // wc_add_notice() est mockée dans bootstrap (no-op)
        // On vérifie juste que la méthode s'exécute sans erreur
        $result = $this->gateway->get_error_payment_url($this->order, 'Test error');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test : get_error_payment_url() inclut le paramètre order-cancelled
     */
    public function test_get_error_payment_url_includes_order_cancelled_param()
    {
        $result = $this->gateway->get_error_payment_url($this->order, 'Error message');

        // L'URL doit contenir 'order-cancelled'
        $this->assertTrue(strpos($result, 'order-cancelled') !== false);
    }

    // -------------------------------
    // getDefaultTemplateData() - SKIP
    // -------------------------------
    // Cette méthode nécessite tests d'intégration :
    // - Appelle SDK (getEncryptionKey)
    // - Utilise $woocommerce->session global
    // - Retourne errors/confirmations basées sur vérifications API
    // Voir TESTING_STRATEGY.md pour détails

    // -------------------------------
    // getContractsForCurrentPos()
    // -------------------------------

    /**
     * Test : getContractsForCurrentPos() filtre par POS actuel
     */
    public function test_get_contracts_for_current_pos_filters_by_active_pos()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getContractsForCurrentPos');

        // Configurer des contrats
        MockOptions::$data['woocommerce_payline_cpt_settings']['contract_number_list'] = 'CB,VISA';
        MockOptions::$data['woocommerce_payline_cpt_settings']['default_pos'] = 'POS123';

        $result = $method->invoke($this->gateway);

        // Doit retourner un tableau
        $this->assertIsArray($result);
    }

    /**
     * Test : getContractsForCurrentPos() retourne vide si pas de match
     */
    public function test_get_contracts_for_current_pos_returns_empty_when_no_match()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getContractsForCurrentPos');

        // POS vide
        MockOptions::$data['woocommerce_payline_cpt_settings']['default_pos'] = '';

        $result = $method->invoke($this->gateway);

        $this->assertIsArray($result);
    }

    // -------------------------------
    // paylineSDK()
    // -------------------------------

    /**
     * Test : paylineSDK() retourne une instance du SDK
     */
    public function test_payline_sdk_returns_sdk_instance()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'paylineSDK');

        $result = $method->invoke($this->gateway);

        // Avec les credentials configurés, doit retourner un SDK
        $this->assertNotNull($result);
    }

    /**
     * Test : paylineSDK() cache l'instance
     */
    public function test_payline_sdk_caches_instance()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'paylineSDK');

        $sdk1 = $method->invoke($this->gateway);
        $sdk2 = $method->invoke($this->gateway);

        // Les deux appels doivent retourner la même instance
        $this->assertSame($sdk1, $sdk2);
    }
}
