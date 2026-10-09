<?php

/**
 * Tests unitaires pour PaylineWallet
 *
 * Couvre la fonctionnalité "Mon Wallet" dans l'espace client WooCommerce.
 * Permet aux clients de gérer leurs cartes enregistrées.
 *
 * Méthodes testées :
 * - isWalletEnabled() : Vérification activation wallet
 * - getEnvSettingValue() : Récupération environnement
 * - addQueryVars() : Ajout query var WordPress
 * - addUserAccountMenuItem() : Ajout menu compte client
 * - is_wallet_endpoint_url() : Détection page wallet
 * - getPageTitle() : Titre de la page
 * - payline_add_front_styles() : Chargement assets
 * - getPageContent() : Rendu contenu wallet
 */
class Payline_Wallet_Test extends PaylineTestCase
{
    const WALLET_ENDPOINT = 'my-payline-wallet';
    const WALLET_TITLE = 'My Wallet';

    // -------------------------------
    // isWalletEnabled()
    // -------------------------------
    public function test_is_wallet_enabled_returns_true()
    {
        $this->assertTrue(PaylineWallet::isWalletEnabled(), 'Le wallet doit être activé par défaut');
    }

    public function test_is_wallet_enabled_returns_false_when_disabled()
    {
        MockOptions::$data['woocommerce_payline_cpt_settings']['wallet'] = 'no';

        $this->assertFalse(PaylineWallet::isWalletEnabled(), 'Le wallet doit être désactivé quand wallet=no');
    }

    public function test_is_wallet_enabled_returns_false_when_option_missing()
    {
        unset(MockOptions::$data['woocommerce_payline_cpt_settings']['wallet']);

        $this->assertFalse(PaylineWallet::isWalletEnabled(), 'Le wallet doit être désactivé quand l\'option est absente');
    }

    public function test_is_wallet_enabled_returns_false_when_settings_empty()
    {
        MockOptions::$data['woocommerce_payline_cpt_settings'] = [];

        $this->assertFalse(PaylineWallet::isWalletEnabled(), 'Le wallet doit être désactivé quand les settings sont vides');
    }

    // -------------------------------
    // getEnvSettingValue()
    // -------------------------------
    public function test_get_env_setting_value_returns_homo()
    {
        $this->assertEquals('HOMO', PaylineWallet::getEnvSettingValue(), 'L\'environnement par défaut doit être HOMO');
    }

    public function test_get_env_setting_value_returns_prod()
    {
        MockOptions::$data['woocommerce_payline_settings']['environment'] = 'PROD';

        $this->assertEquals('PROD', PaylineWallet::getEnvSettingValue(), 'L\'environnement doit être PROD');
    }

    public function test_get_env_setting_value_returns_null_when_missing()
    {
        unset(MockOptions::$data['woocommerce_payline_settings']['environment']);

        $this->assertNull(PaylineWallet::getEnvSettingValue(), 'L\'environnement doit être null quand il est absent');
    }

    // -------------------------------
    // addQueryVars()
    // -------------------------------
    public function test_add_query_vars()
    {
        $vars = array('existing_var' => 'value');
        $result = PaylineWallet::addQueryVars($vars);

        $this->assertArrayHasKey(self::WALLET_ENDPOINT, $result, 'Le wallet endpoint doit être ajouté aux query vars');
        $this->assertEquals(self::WALLET_ENDPOINT, $result[self::WALLET_ENDPOINT], 'La valeur du wallet endpoint doit correspondre');
        $this->assertArrayHasKey('existing_var', $result, 'Les vars existants doivent être préservés');
    }

    // -------------------------------
    // addUserAccountMenuItem()
    // -------------------------------
    public function test_add_user_account_menu_item_when_wallet_enabled()
    {
        $menuItems = $this->getBaseMenuItems();

        $result = PaylineWallet::addUserAccountMenuItem($menuItems);

        $this->assertArrayHasKey(self::WALLET_ENDPOINT, $result, 'Le wallet doit être ajouté au menu');
        $this->assertEquals(self::WALLET_TITLE, $result[self::WALLET_ENDPOINT], 'Le titre du wallet doit correspondre');
        // L'ordre est vérifié dans test_add_user_account_menu_item_preserves_order
    }

    public function test_add_user_account_menu_item_when_wallet_disabled()
    {
        MockOptions::$data['woocommerce_payline_cpt_settings']['wallet'] = 'no';

        $menuItems = $this->getBaseMenuItems();

        $result = PaylineWallet::addUserAccountMenuItem($menuItems);

        $this->assertArrayNotHasKey(self::WALLET_ENDPOINT, $result, 'Le wallet ne doit pas être ajouté quand désactivé');
        $this->assertSame($menuItems, $result, 'Le menu doit rester inchangé quand le wallet est désactivé');
    }

    public function test_add_user_account_menu_item_preserves_order()
    {
        $menuItems = array(
            'dashboard' => 'Tableau de bord',
            'orders' => 'Commandes',
            'customer-logout' => 'Se déconnecter',
        );

        $result = PaylineWallet::addUserAccountMenuItem($menuItems);
        $keys = array_keys($result);

        $this->assertEquals(array('dashboard', 'orders', self::WALLET_ENDPOINT, 'customer-logout'), $keys, 'L\'ordre du menu doit être préservé');
    }

    public function test_add_user_account_menu_item_without_logout()
    {
        // Cas où customer-logout n'est pas présent
        $menuItems = array(
            'dashboard' => 'Tableau de bord',
            'orders' => 'Commandes',
        );

        $result = PaylineWallet::addUserAccountMenuItem($menuItems);

        // Le wallet ne devrait pas être ajouté car on l'insère avant customer-logout
        $this->assertArrayNotHasKey(self::WALLET_ENDPOINT, $result, 'Le wallet ne doit pas être ajouté sans customer-logout');
    }

    // -------------------------------
    // is_wallet_endpoint_url()
    // -------------------------------
    public function test_is_wallet_endpoint_url_returns_false_when_wp_null()
    {
        $isWalletEndpoint = $this->getPrivateMethod(PaylineWallet::class, 'is_wallet_endpoint_url');

        // $wp est null par défaut après reset
        $this->assertFalse($isWalletEndpoint->invoke(null), 'Doit retourner false quand $wp est null');
    }

    /**
     * @dataProvider invalidWalletEndpointContextProvider
     */
    public function test_is_wallet_endpoint_url_returns_false_for_invalid_context($contextName, $setupCallback)
    {
        MockWordPress::simulateWalletPage();
        call_user_func($setupCallback);

        $isWalletEndpoint = $this->getPrivateMethod(PaylineWallet::class, 'is_wallet_endpoint_url');

        $this->assertFalse(
            $isWalletEndpoint->invoke(null),
            "Should return false when context is: {$contextName}"
        );
    }

    public static function invalidWalletEndpointContextProvider(): array
    {
        return array(
            'is admin page' => array(
                'is_admin',
                function() {
                    MockWordPress::$data['is_admin'] = true;
                }
            ),
            'not main query' => array(
                'not_main_query',
                function() {
                    MockWordPress::$data['is_main_query'] = false;
                }
            ),
            'not account page' => array(
                'not_account_page',
                function() {
                    MockWordPress::$data['is_account_page'] = false;
                }
            ),
            'not a page' => array(
                'not_page',
                function() {
                    MockWordPress::$data['is_page'] = false;
                }
            ),
            'wrong query var' => array(
                'wrong_endpoint',
                function() {
                    global $wp;
                    $wp->query_vars = array('other-endpoint' => 'value');
                }
            ),
        );
    }

    public function test_is_wallet_endpoint_url_returns_true_on_wallet_page()
    {
        MockWordPress::simulateWalletPage();

        $isWalletEndpoint = $this->getPrivateMethod(PaylineWallet::class, 'is_wallet_endpoint_url');

        $this->assertTrue($isWalletEndpoint->invoke(null), 'Doit retourner true sur la page wallet');
    }

    // -------------------------------
    // getPageTitle()
    // -------------------------------
    public function test_get_page_title_returns_original_when_not_wallet_endpoint()
    {
        $result = PaylineWallet::getPageTitle('Mon titre', 'my-payline-wallet');

        $this->assertEquals('Mon titre', $result, 'Le titre original doit être retourné hors wallet');
    }

    public function test_get_page_title_returns_original_for_other_endpoints()
    {
        $result = PaylineWallet::getPageTitle('Orders', 'orders');

        $this->assertEquals('Orders', $result, 'Le titre original doit être retourné pour un autre endpoint');
    }

    public function test_get_page_title_returns_wallet_title_on_wallet_page()
    {
        MockWordPress::simulateWalletPage();

        $result = PaylineWallet::getPageTitle('Original Title', 'my-payline-wallet');

        $this->assertEquals('My Wallet', $result, 'Le titre doit être "My Wallet" sur la page wallet');
    }

    public function test_get_page_title_returns_original_when_wrong_endpoint_on_wallet_page()
    {
        MockWordPress::simulateWalletPage();

        $result = PaylineWallet::getPageTitle('Orders Title', 'orders');

        $this->assertEquals('Orders Title', $result, 'Le titre original doit être retourné pour un endpoint différent');
    }

    public function test_get_page_title_returns_original_when_not_in_loop()
    {
        MockWordPress::simulateWalletPage();
        MockWordPress::$data['in_the_loop'] = false;

        $result = PaylineWallet::getPageTitle('Original Title', 'my-payline-wallet');

        $this->assertEquals('Original Title', $result, 'Le titre original doit être retourné hors de la boucle');
    }

    // -------------------------------
    // payline_add_front_styles()
    // -------------------------------
    public function test_payline_add_front_styles_does_nothing_when_not_wallet_page()
    {
        PaylineWallet::payline_add_front_styles();

        $this->assertEmpty(MockWordPress::$data['enqueued_styles'], 'Aucun style ne doit être enqueued hors page wallet');
        $this->assertEmpty(MockWordPress::$data['enqueued_scripts'], 'Aucun script ne doit être enqueued hors page wallet');
    }

    /**
     * @dataProvider environmentProvider
     */
    public function test_payline_add_front_styles_enqueues_assets_on_wallet_page($environment)
    {
        MockWordPress::simulateWalletPage();
        MockOptions::$data['woocommerce_payline_settings']['environment'] = $environment;

        PaylineWallet::payline_add_front_styles();

        $this->assertArrayHasKey('payline-front-style', MockWordPress::$data['enqueued_styles'], 'Le style payline-front doit être enqueued');
        $this->assertArrayHasKey('widget-min', MockWordPress::$data['enqueued_styles'], 'Le style widget-min doit être enqueued');
        $this->assertArrayHasKey('widget-min', MockWordPress::$data['enqueued_scripts'], 'Le script widget-min doit être enqueued');
    }

    public static function environmentProvider(): array
    {
        return array(
            'environnement HOMO' => array('HOMO'),
            'environnement PROD' => array('PROD'),
        );
    }

    // -------------------------------
    // getPageContent()
    // -------------------------------
    public function test_get_page_content_loads_template_with_token()
    {
        PaylineWallet::getPageContent(new MockGatewayPayline());

        $this->assertCount(1, MockWordPress::$data['loaded_templates'], 'Un template doit être chargé');
        $loadedTemplate = MockWordPress::$data['loaded_templates'][0];

        $this->assertStringContainsString('user-account-wallet.php', $loadedTemplate['file'], 'Le template wallet doit être chargé');
        $this->assertTrue($loadedTemplate['require_once'], 'Le template doit utiliser require_once');
        $this->assertEquals('mock_token_123', $loadedTemplate['args']['token'], 'Le token doit être passé au template');
    }

    public function test_get_page_content_loads_template_with_null_token_when_no_result()
    {
        MockGatewayPayline::$data['createManageWebWallet_result'] = null;

        PaylineWallet::getPageContent(new MockGatewayPayline());

        $loadedTemplate = MockWordPress::$data['loaded_templates'][0];
        $this->assertNull($loadedTemplate['args']['token'], 'Le token doit être null quand il n\'y a pas de résultat');
    }

    public function test_get_page_content_loads_template_with_null_token_when_no_token_key()
    {
        MockGatewayPayline::$data['createManageWebWallet_result'] = ['other_key' => 'value'];

        PaylineWallet::getPageContent(new MockGatewayPayline());

        $loadedTemplate = MockWordPress::$data['loaded_templates'][0];
        $this->assertNull($loadedTemplate['args']['token'], 'Le token doit être null quand la clé token est absente');
    }
}
