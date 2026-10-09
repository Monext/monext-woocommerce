<?php
/**
 * Bootstrap pour les tests PHPUnit du plugin Payline
 *
 * ARCHITECTURE MODULAIRE :
 * - Constantes WordPress + Autoload Composer
 * - Chargement des classes Mock (mocks/classes/)
 * - Chargement des fonctions WordPress (mocks/wordpress/)
 * - Chargement des classes et fonctions WooCommerce (mocks/woocommerce/)
 * - Constantes plugin + Classes à tester
 *
 * MOCKS DISPONIBLES :
 *
 * Classes Mock :
 *   - MockOptions : Gestion des options WordPress
 *   - MockPlugins : Métadonnées des plugins
 *   - MockWordPress : Contexte et enqueues
 *   - MockGatewayPayline : Gateway Payline pour tests wallet
 *   - MockWpQuery : Variable globale $wp
 *
 * Classes WordPress/WooCommerce :
 *   - WC_Order : Commande WooCommerce complète (50+ méthodes)
 *   - WC_Payment_Gateway : Classe parent des gateways
 *   - WC_Gateway_Payline : Gateway Payline simple
 *   - WP_Error : Gestion d'erreurs WordPress
 *   - WC_Log_Handler_File : Logger WooCommerce
 *   - MockOrderInternalStatus : Enum statuts de commande
 *   - MockDraftOrders : Constantes commandes brouillon
 *   - MockWcCart : Panier WooCommerce
 *
 * Fonctions WordPress : (45+ fonctions)
 *   Contexte : is_admin(), is_page(), in_the_loop() [5]
 *   Enqueue : wp_enqueue_style(), wp_enqueue_script(), load_template() [3]
 *   URL : home_url(), admin_url(), plugin_dir_url(), add_query_arg() [6]
 *   Sécurité : wp_create_nonce(), wp_nonce_url(), esc_attr() [4]
 *   Hooks : add_action(), do_action(), add_filter(), apply_filters() [4]
 *   Core : get_option(), update_option(), get_bloginfo(), __() [6]
 *   Utilitaires : wp_parse_args(), get_plugins(), get_plugin_data() [5]
 *
 * Fonctions WooCommerce : (7+ fonctions)
 *   - wc_add_notice(), wc_get_cart_url(), wc_get_checkout_url()
 *   - wc_get_order_statuses(), wc_get_order()
 *   - WC() : Objet global WooCommerce
 */

// ========================================================================
// SECTION 1 : CONSTANTES WORDPRESS & AUTOLOAD
// ========================================================================

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', dirname(__DIR__, 2));
}

require __DIR__ . '/../../vendor/autoload.php';

// ========================================================================
// SECTION 2 : CHARGEMENT DES CLASSES MOCK
// ========================================================================

require_once __DIR__ . '/mocks/classes/MockOptions.php';
require_once __DIR__ . '/mocks/classes/MockPlugins.php';
require_once __DIR__ . '/mocks/classes/MockWordPress.php';
require_once __DIR__ . '/mocks/classes/MockGatewayPayline.php';
require_once __DIR__ . '/mocks/classes/MockWcBlocks.php';

// Initialiser les fixtures par défaut
MockOptions::reset();
MockPlugins::reset();
MockWordPress::reset();
MockGatewayPayline::reset();

// ========================================================================
// SECTION 3 : CHARGEMENT DES FONCTIONS WORDPRESS
// ========================================================================

require_once __DIR__ . '/mocks/wordpress/core-functions.php';
require_once __DIR__ . '/mocks/wordpress/context-functions.php';
require_once __DIR__ . '/mocks/wordpress/enqueue-functions.php';
require_once __DIR__ . '/mocks/wordpress/url-functions.php';
require_once __DIR__ . '/mocks/wordpress/security-functions.php';
require_once __DIR__ . '/mocks/wordpress/hook-functions.php';
require_once __DIR__ . '/mocks/wordpress/utility-functions.php';

// ========================================================================
// SECTION 4 : CHARGEMENT DES CLASSES ET FONCTIONS WOOCOMMERCE
// ========================================================================

require_once __DIR__ . '/mocks/woocommerce/WC_Enums.php';
require_once __DIR__ . '/mocks/woocommerce/WP_Error.php';
require_once __DIR__ . '/mocks/woocommerce/WC_Payment_Gateway.php';
require_once __DIR__ . '/mocks/woocommerce/WC_Order.php';
require_once __DIR__ . '/mocks/woocommerce/WC_Customer.php';
require_once __DIR__ . '/mocks/woocommerce/OrderController.php';
require_once __DIR__ . '/mocks/woocommerce/wc-functions.php';

// ========================================================================
// SECTION 5 : CONSTANTES PLUGIN & ENVIRONNEMENT
// ========================================================================

if (!defined('WCPAYLINE_PLUGIN_VERSION')) {
    define('WCPAYLINE_PLUGIN_VERSION', '1.0.0-test');
}

if (!defined('WCPAYLINE_PLUGIN_URL')) {
    define('WCPAYLINE_PLUGIN_URL', 'https://example.com/wp-content/plugins/payline/');
}

if (!defined('WCPAYLINE_PLUGIN_PATH')) {
    define('WCPAYLINE_PLUGIN_PATH', plugin_dir_path(__FILE__));
}

if (!defined('WC_VERSION')) {
    define('WC_VERSION', '9.0.0');
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', '/tmp/wp-content');
}

// Mock $_SERVER pour les tests
if (!isset($_SERVER['REMOTE_ADDR'])) {
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
}

// ========================================================================
// SECTION 6 : CHARGEMENT DES CLASSES À TESTER
// ========================================================================

// Charge la classe de base pour les tests
require_once __DIR__ . '/PaylineTestCase.php';

// Charge les classes à tester (APRÈS les mocks pour éviter les erreurs)
require_once __DIR__ . '/../../includes/class-wc-payline-payment-gateway.php';
require_once __DIR__ . '/../../includes/front/payline-wallet.php';

// Charge les classes gateway (APRÈS WC_Payment_Gateway mock)
require_once __DIR__ . '/../../includes/gateway/class-wc-gateway-abstract-payline.php';
require_once __DIR__ . '/../../includes/gateway/class-wc-gateway-payline-cpt.php';
require_once __DIR__ . '/../../includes/gateway/class-wc-gateway-abstract-recurring-payline.php';
require_once __DIR__ . '/../../includes/gateway/class-wc-gateway-payline-nx.php';
require_once __DIR__ . '/../../includes/gateway/class-wc-gateway-payline-rec.php';
require_once __DIR__ . '/../../includes/gateway/class-wc-gateway-payline.php';

// Charge les classes admin et upgrades
require_once __DIR__ . '/../../includes/admin/payline-logs-viewer.php';
require_once __DIR__ . '/../../includes/class-wc-payline-upgrades.php';

// Charge les classes Blocks WooCommerce
require_once __DIR__ . '/../../includes/blocks/class-wc-blocs-abstract-payline.php';
require_once __DIR__ . '/../../includes/blocks/class-wc-blocs-payline-cpt.php';
require_once __DIR__ . '/../../includes/blocks/class-wc-blocs-payline-nx.php';
require_once __DIR__ . '/../../includes/blocks/class-wc-blocs-payline-rec.php';
