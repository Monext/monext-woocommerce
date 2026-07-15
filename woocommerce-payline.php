<?php
/**
 * Plugin Name: Monext
 * Plugin URI: https://docs.payline.com/display/DT/Plugin+WooCommerce
 * Description: integrations of Monext payment solution in your WooCommerce store
 * Version: 1.5.10
 * Author: Monext
 * Text Domain: monext-online-woocommerce
 * Author URI: http://www.monext.fr
 * License: LGPL-3.0+
 * GitHub Plugin URI: https://github.com/Monext/monext-woocommerce/
 * Github Branch: master
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * Requires Plugins: woocommerce
 * WC tested up to: 10.9.4
 * WC requires at least: 10.6
 *  Copyright 2017  Monext  (email : support@payline.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use Automattic\WooCommerce\StoreApi\Utilities\OrderController;

if (!defined('ABSPATH')) exit;

define('WCPAYLINE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WCPAYLINE_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WCPAYLINE_PLUGIN_CLASS', plugin_basename(__FILE__));
define('WCPAYLINE_PLUGIN_VERSION', '1.5.10');

function woocommerce_payline_activation()
{
    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        deactivate_plugins(plugin_basename(__FILE__));

        load_plugin_textdomain('payline', false, dirname(plugin_basename(__FILE__)) . '/languages/');

        $message = sprintf(__('Sorry! In order to use WooCommerce %s Payment plugin, you need to install and activate the WooCommerce plugin.', 'payline'), 'Payline');
        wp_die($message, 'WooCommerce Payline Gateway Plugin', array('back_link' => true));
    } elseif (!wc_payline_vendors_loaded()) {
        deactivate_plugins(plugin_basename(__FILE__));
        load_plugin_textdomain('payline', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        $message = __('The plugin is not installed correctly. You should have downloaded the package archive named woocommerce-payline_vx.y.z.zip from GitHub:', 'payline').' <a href="https://github.com/Monext/monext-woocommerce/releases" target="_blank">https://github.com/Monext/monext-woocommerce/releases</a>';
        wp_die($message, 'Monext Plugin install failed', array('back_link' => true));
    }

    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'woocommerce_payline_activation');


add_action( 'admin_notices', 'monext_woocommerce_missing_vendor_notice' );
function monext_woocommerce_missing_vendor_notice() {
    if (!wc_payline_vendors_loaded()) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>Monext WooCommerce :</strong> ';
        echo __('The plugin is not installed correctly. You should have downloaded the package archive named woocommerce-payline_vx.y.z.zip from GitHub:', 'payline');
        echo ' <a href="https://github.com/Monext/monext-woocommerce/releases" target="_blank">https://github.com/Monext/monext-woocommerce/releases</a>';
        echo '</p></div>';

        deactivate_plugins(plugin_basename(__FILE__));
    }
}

function woocommerce_payline_desactivation() {
    delete_option('woocommerce_payline_settings');
    delete_option('woocommerce_payline_pos_list');
    delete_option('wc_payline_version');
    delete_option('woocommerce_payline_pos_contracts_list');
    delete_option('woocommerce_payline_cpt_settings');
    delete_option('woocommerce_payline_rec_settings');
    delete_option('woocommerce_payline_nx_settings');

    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'woocommerce_payline_desactivation');


/**
 * inserts class gateway
 */
function woocommerce_payline_init() {
	if (!wc_payline_vendors_loaded()) {
		return;
	}

    require_once __DIR__ . '/vendor/autoload.php';

	// Load translation files
	load_plugin_textdomain('payline', false, dirname(plugin_basename(__FILE__)) . '/languages/');

	if (!class_exists('WC_Block_Abstract_Payline')) {
		require_once 'includes/blocks/class-wc-blocs-abstract-payline.php';
	}

	if (!class_exists('WC_Block_Payline_CPT')) {
		require_once 'includes/blocks/class-wc-blocs-payline-cpt.php';
	}

	if (!class_exists('WC_Block_Payline_NX')) {
		require_once 'includes/blocks/class-wc-blocs-payline-nx.php';
	}

	if (!class_exists('WC_Block_Payline_REC')) {
		require_once 'includes/blocks/class-wc-blocs-payline-rec.php';
	}

	if (!class_exists('PaylineLogsViewer')) {
		require_once 'includes/admin/payline-logs-viewer.php';
	}

	if (!class_exists('WC_Payline_Upgrades')) {
		require_once 'includes/class-wc-payline-upgrades.php';
	}

    if (!class_exists('WC_Abstract_Payline')) {
        require_once 'includes/gateway/class-wc-gateway-abstract-payline.php';
    }

    if (!class_exists('WC_Payline_SDK')) {
        require_once 'includes/class-wc-payline-payment-gateway.php';
    }

    if (!class_exists('WC_Gateway_Payline')) {
        require_once 'includes/gateway/class-wc-gateway-payline.php';
    }

    if ( !class_exists( 'WC_Abstract_Recurring_Payline_NX', false ) ) {
        include_once 'includes/gateway/class-wc-gateway-abstract-recurring-payline.php';
    }

    if (!class_exists('WC_Gateway_Payline_CPT')) {
        require_once 'includes/gateway/class-wc-gateway-payline-cpt.php';
    }

    if (!class_exists('WC_Gateway_Payline_NX')) {
        require_once 'includes/gateway/class-wc-gateway-payline-nx.php';
    }

    if (!class_exists('WC_Gateway_Payline_REC')) {
        require_once 'includes/gateway/class-wc-gateway-payline-rec.php';
    }

    if (!class_exists('PaylineWallet')) {
        require_once 'includes/front/payline-wallet.php';
    }

    if (!class_exists('PaylineValidateCheckoutEndpoint')) {
        require_once 'includes/payline-validate-checkout-endpoint.php';
    }

    if(!get_option( 'wc_payline_version' )){
        update_option( 'wc_payline_version', '1.0.0' );
    }

    woocommerce_payline_upgrade();

    /**
     * Adding wallet into “My Account” section
     *
     */
    add_filter('woocommerce_get_query_vars', array( 'PaylineWallet', 'addQueryVars' ) );
    add_filter('woocommerce_account_menu_items', array( 'PaylineWallet', 'addUserAccountMenuItem' ) );
    add_action('woocommerce_account_my-payline-wallet_endpoint', array( 'PaylineWallet', 'getPageContent' ));
    add_filter('woocommerce_endpoint_my-payline-wallet_title', array('PaylineWallet','getPageTitle'), 42, 2);
    add_action('wp_enqueue_scripts', array( 'PaylineWallet', 'payline_add_front_styles' ));

    /**
     * PaylineLogsViewer endpoint déclaration
     *
     */
    add_action('wp_ajax_load_log', array( 'PaylineLogsViewer', 'doAjaxGetLogs' ));
    add_action('wp_ajax_nopriv_load_log', array( 'PaylineLogsViewer', 'doAjaxGetLogs' ));
}
add_action('woocommerce_init', 'woocommerce_payline_init');

/**
 * Vérifie si les dépendances vendor sont présentes.
 *
 * @return bool
 */
function wc_payline_vendors_loaded(): bool {
    return file_exists(__DIR__ . '/vendor/autoload.php');
}

/**
 * adds method to woocommerce methods
 * @param $methods
 * @return mixed
 */
function woocommerce_payline_add_method($methods)
{
    if (!wc_payline_vendors_loaded()) {
        return $methods;
    }

    global $current_section;

    $current_section_fallback = false;
    if(!empty($current_section)) {
        $current_section_fallback = $current_section;
    }elseif (!empty($_GET['section'])) {
        $current_section_fallback = sanitize_key($_GET['section']);
    }

    $methods[] = 'WC_Gateway_Payline';

    $cpt_settings = get_option('woocommerce_payline_cpt_settings');
    if ($current_section_fallback === 'payline_cpt' || (!empty($cpt_settings) && $cpt_settings["enabled"] === "yes")) {
        $methods[] = 'WC_Gateway_Payline_CPT';
    }

    $nx_settings = get_option('woocommerce_payline_nx_settings');
    if ($current_section_fallback === 'payline_nx' || (!empty($nx_settings) && $nx_settings["enabled"] === "yes")) {
        $methods[] = 'WC_Gateway_Payline_NX';
    }

    $rec_settings = get_option('woocommerce_payline_rec_settings');
    if ($current_section_fallback === 'payline_rec' || (!empty($rec_settings) && $rec_settings["enabled"] === "yes")) {
        $methods[] = 'WC_Gateway_Payline_REC';
    }

    return $methods;
}
add_filter('woocommerce_payment_gateways', 'woocommerce_payline_add_method');


/**
 * add a link from plugin list to parameters
 * @param $links
 * @param $file
 * @return mixed
 */
function woocommerce_payline_add_link($links, $file) {
	$links[] = '<a href="'.admin_url('admin.php?page=wc-settings&tab=checkout&section=payline').'">' . __('Settings') .'</a>';
    $links[] = '<a href="'.admin_url('tools.php?page=payline-logs').'">' . __('Logs Viewer') .'</a>';
	return $links;
}
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'woocommerce_payline_add_link',  10, 2);

/**
 * @param $statuses
 * @return mixed
 */
function woocommerce_payline_add_custom_status_for_order_again( $statuses ){

    if(isset( $_GET['order_again'], $_GET['_wpnonce'], $_GET['payline_cancel']) && wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'woocommerce-order_again' ) ) {
        $statuses[] = 'cancelled';
        $statuses[] = 'failed';
        $statuses[] = 'pending';

    }

    return $statuses;
}
add_filter( 'woocommerce_valid_order_statuses_for_order_again', 'woocommerce_payline_add_custom_status_for_order_again', 10, 1 );


/**
 * @param $available_gateways
 * @return mixed
 */
function woocommerce_payline_enable_gateway_order_pay( $available_gateways ) {
    unset( $available_gateways['payline'] );
    return $available_gateways;
}

add_filter( 'woocommerce_available_payment_gateways', 'woocommerce_payline_enable_gateway_order_pay' );

/**
 * Payline module is not published on https://wordpress.org/plugins/
 * avoid confusing with bitpay
 *
 * @param $value
 * @return mixed
 */
function payline_site_transient_update_plugins( $value) {

    if(basename(dirname(__FILE__)) != 'payline-woocommerce') {
        return $value;
    }

    if(empty($value) || empty($value->response) || !is_array($value->response)) {
        return $value;
    }

    foreach ($value->response as $pluginFile => $pluginUpdate) {
        if($pluginUpdate->plugin == "payline-woocommerce/woocommerce-payline.php") {
            unset($value->response[$pluginFile]);
        }
    }

    return $value;
}
add_filter( 'site_transient_update_plugins', 'payline_site_transient_update_plugins' );


/**
 * @param $should_update
 * @param $plugin
 * @return false
 */
function payline_auto_update_plugin( $should_update, $plugin ) {
    if ( ! isset( $plugin->plugin, $plugin->new_version ) ) {
        return $should_update;
    }

    if ( basename(dirname(__FILE__)) . DIRECTORY_SEPARATOR . basename(__FILE__) == $plugin->plugin ) {
        return false;
    }
    return $should_update;

}
add_filter( 'auto_update_plugin', 'payline_auto_update_plugin', 100, 2 );


add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

add_action( 'woocommerce_blocks_loaded', 'payline_register_payment_methods' );

/**
 * Hook method to update draft order as block checkout way
 * @return void
 */
function payline_checkout_update_order_review()
{
    $order_id = WC()->session->get( 'store_api_draft_order' );
    if (!$order_id) {
        return;
    }

    $order = wc_get_order( $order_id );
    if(!$order){
        WC()->session->__unset('store_api_draft_order');
        return;
    }

    (new OrderController())->update_order_from_cart($order);
}
add_action('woocommerce_checkout_update_order_review', 'payline_checkout_update_order_review');

/**
 * Reuse the existing draft order in classic checkout instead of creating a new one.
 *
 * @param int|null    $order_id Existing order ID to use, or null to let WooCommerce create one.
 * @param WC_Checkout $checkout
 * @return int|null
 */
function payline_reuse_draft_order_for_classic_checkout( $order_id, $checkout )
{
    $draft_order_id = WC()->session->get( 'store_api_draft_order' );
    if ( ! $draft_order_id ) {
        return $order_id;
    }

    $order = wc_get_order( $draft_order_id );
    if ( ! $order || $order->get_status() !== 'checkout-draft' ) {
        WC()->session->__unset( 'store_api_draft_order' );
        return $order_id;
    }

    (new OrderController())->update_order_from_cart($order);
    return $draft_order_id;
}
add_filter( 'woocommerce_create_order', 'payline_reuse_draft_order_for_classic_checkout', 10, 2 );

/**
 * Register Payline payment methods for Gutenberg blocks
 *
 * @return void
 * @since 1.5.0
 */
function payline_register_payment_methods() {
	if (!wc_payline_vendors_loaded()) {
		return;
	}

	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function ( PaymentMethodRegistry $payment_method_registry ) {
				$payment_method_registry->register( new WC_Block_Payline_REC() );
				$payment_method_registry->register( new WC_Block_Payline_CPT );
				$payment_method_registry->register( new WC_Block_Payline_NX );
			} );
	}
}

add_action('admin_menu', 'payline_add_logs_viewer_in_tools_submenu');

/**
 * Add Payline Logs Viewer in Tools submenu
 *
 * @return void
 * @since 1.5.2
 */
function payline_add_logs_viewer_in_tools_submenu()
{
    if (!wc_payline_vendors_loaded()) {
        return;
    }

	add_management_page(
		__('Monext Logs Viewer'),
		__('Monext Logs Viewer'),
		'manage_options',
		'payline-logs',
		['PaylineLogsViewer', 'showLogs']
	);
}

add_action('admin_enqueue_scripts', 'payline_log_enqueue_admin_scripts');

/**
 * Add Payline JS in payline logs viewer page
 *
 * @param $hook_suffix
 * @return void
 * @since 1.5.2
 */
function payline_log_enqueue_admin_scripts( $hook_suffix ) {
	if ( 'tools_page_payline-logs' === $hook_suffix ) {

		wp_enqueue_script(
			'logs-viewer-js',
			WCPAYLINE_PLUGIN_URL . 'assets/js/admin/logs.js',
			[ 'jquery' ],
			'1.0',
			true
		);

		wp_localize_script( 'logs-viewer-js', 'logs_viewer_js_data', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
	}
}

add_action( 'admin_enqueue_scripts', 'payline_log_enqueue_admin_styles');

/**
 * Add Payline CSS in payline logs viewer page
 *
 * @param $hook_suffix
 * @return void
 * @since 1.5.2
 */
function payline_log_enqueue_admin_styles( $hook_suffix ) {
	if ( 'tools_page_payline-logs' === $hook_suffix ) {
		wp_register_style( 'payline_admin_styles', WCPAYLINE_PLUGIN_URL . 'assets/css/admin.css' );
		wp_enqueue_style( 'payline_admin_styles' );
	}
}

/**
 * Update plugin version database. Useful for plugin upgrade scripts
 * @return void
 */
function update_plugin_version($version) {
    delete_option( 'wc_payline_version' );
    update_option( 'wc_payline_version', $version );
}

/**
 * Add multisite support to upgrade scripts
 * @return void
 */
function woocommerce_payline_upgrade()
{
    if(is_multisite()) {
        // Apply upgrade to each sites
        foreach ( get_sites() as $site ) {
            switch_to_blog( $site->blog_id );
            checkVersion();
            restore_current_blog();
        }
    }else{
        checkVersion();
    }
}

/**
 * Compare version and apply upgrades
 * @return void
 */
function checkVersion()
{
    $pluginUpgradeVersion = get_option( 'wc_payline_version' );

    if (version_compare($pluginUpgradeVersion, '1.5.6', '<')) {
        WC_Payline_Upgrades::upgrade_to_1_5_6();
        update_plugin_version('1.5.6');
    }
}

/**
 * Display contract label in order details in admin
 *
 * @param WC_Order $order
 * @return void
 */
function woocommerce_payline_display_contract_label($order)
{
    $contract_number = $order->get_meta('_contract_number', true);
    if (empty($contract_number)) {
        return;
    }

    $contracts_list = unserialize(get_option('woocommerce_payline_pos_contracts_list', ''));
    if (!is_array($contracts_list) || empty($contracts_list)) {
        return;
    }

    $contract_label = '';
    foreach ($contracts_list as $contract) {
        if (isset($contract['contractNumber']) && $contract['contractNumber'] == $contract_number) {
            $contract_label = isset($contract['label']) ? $contract['label'] : '';
            break;
        }
    }

    if (!empty($contract_label)) {
        echo '<div>';
        echo '<strong>'.__('Monext contract used', 'payline').' : </strong>' . esc_html($contract_label);
        echo '</div>';
    }
}

add_action('woocommerce_admin_order_data_after_payment_info', 'woocommerce_payline_display_contract_label');
add_action('wc_ajax_payline_checkout_validator', function () {
    $endpoint = new PaylineValidateCheckoutEndpoint();
    $endpoint->handle_request();
});