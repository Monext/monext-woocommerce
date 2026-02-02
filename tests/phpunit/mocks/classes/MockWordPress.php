<?php
/**
 * Mock dynamique pour les fonctions WordPress (contexte page)
 *
 * Simule les fonctions WordPress qui dépendent du contexte
 * (is_admin, is_page, etc.) et les enqueues de scripts/styles.
 *
 * Usage :
 *   MockWordPress::reset();                        // Reset aux valeurs initiales
 *   MockWordPress::$data['is_admin'] = true;      // Modifier pour un test
 *   MockWordPress::simulateWalletPage();          // Helper pour page wallet
 *
 * Fixtures :
 *   - is_admin : false (mode frontend par défaut)
 *   - is_main_query : true
 *   - is_account_page : true
 *   - is_page : true
 *   - in_the_loop : true
 *   - enqueued_styles : array() (styles enregistrés)
 *   - enqueued_scripts : array() (scripts enregistrés)
 *   - loaded_templates : array() (templates chargés)
 */
class MockWordPress {
    private static $fixtures = [
        'is_admin' => false,
        'is_main_query' => true,
        'is_account_page' => true,
        'is_page' => true,
        'in_the_loop' => true,
        'enqueued_styles' => [],
        'enqueued_scripts' => [],
        'loaded_templates' => [],
    ];

    public static $data = [];

    public static function reset(): void
    {
        self::$data = self::$fixtures;
        // Reset aussi la variable globale $wp
        global $wp;
        $wp = null;
    }

    public static function getFixtures(): array
    {
        return self::$fixtures;
    }

    /**
     * Configure le contexte WordPress pour simuler la page "Mon Wallet"
     *
     * Nécessaire pour tester les méthodes qui dépendent du contexte WordPress :
     * - PaylineWallet::is_wallet_endpoint_url()
     * - PaylineWallet::getPageTitle()
     * - PaylineWallet::payline_add_front_styles()
     *
     * Configure :
     * - global $wp avec query_vars['my-payline-wallet']
     * - is_admin = false
     * - is_main_query = true
     * - is_account_page = true
     * - is_page = true
     * - in_the_loop = true
     *
     * Usage :
     *   MockWordPress::simulateWalletPage();
     *   $result = PaylineWallet::getPageContent(); // Simule rendu wallet
     */
    public static function simulateWalletPage(): void
    {
        global $wp;
        $wp = new MockWpQuery();
        $wp->query_vars = ['my-payline-wallet' => 'my-payline-wallet'];

        self::$data['is_admin'] = false;
        self::$data['is_main_query'] = true;
        self::$data['is_account_page'] = true;
        self::$data['is_page'] = true;
        self::$data['in_the_loop'] = true;
    }
}

/**
 * Mock de la variable globale $wp
 */
class MockWpQuery {
    public $query_vars = [];
}
