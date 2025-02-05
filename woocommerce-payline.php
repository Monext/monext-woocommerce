<?php
/**
 * Plugin Name: Payline
 * Plugin URI: https://docs.payline.com/display/DT/Plugin+WooCommerce
 * Description: integrations of Payline payment solution in your WooCommerce store
 * Version: 1.5.2
 * Author: Monext
 * Text Domain: monext-online-woocommerce
 * Author URI: http://www.monext.fr
 * License: LGPL-3.0+
 * GitHub Plugin URI: https://github.com/PaylineByMonext/payline-woocommerce/
 * Github Branch: master
 * WC tested up to: 4.9.2
 * 
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

if (!defined('ABSPATH')) exit;

define('WCPAYLINE_PLUGIN_URL', plugin_dir_url(__FILE__));

//require_once plugin_dir_path(__FILE__) . 'includes/admin/payline-logs-viewer.php';

function woocommerce_payline_activation() {
	if (!is_plugin_active('woocommerce/woocommerce.php')) {
		deactivate_plugins(plugin_basename(__FILE__));

		load_plugin_textdomain('payline', false, dirname(plugin_basename(__FILE__)) . '/languages/');
		
		$message = sprintf(__('Sorry! In order to use WooCommerce %s Payment plugin, you need to install and activate the WooCommerce plugin.', 'payline'), 'Payline');
		wp_die($message, 'WooCommerce Payline Gateway Plugin', array('back_link' => true));
	}
}
register_activation_hook(__FILE__, 'woocommerce_payline_activation');


/**
 * inserts class gateway
 */
function woocommerce_payline_init() {
	// Load translation files
	load_plugin_textdomain('payline', false, dirname(plugin_basename(__FILE__)) . '/languages/');

    if ( ! class_exists( 'WC_Abstract_Payline', false ) ) {
        include_once 'includes/gateway/class-wc-gateway-abstract-payline.php';
    }

    if ( ! class_exists( 'WC_Abstract_Recurring_Payline_NX', false ) ) {
        include_once 'includes/gateway/class-wc-gateway-abstract-recurring-payline.php';
    }
	
	if (!class_exists('WC_Gateway_Payline')) {
		require_once 'includes/gateway/class-wc-gateway-payline.php';
	}

    if (!class_exists('WC_Gateway_Payline_NX')) {
        require_once 'includes/gateway/class-wc-gateway-payline-nx.php';
    }

    if (!class_exists('WC_Gateway_Payline_REC')) {
        require_once 'includes/gateway/class-wc-gateway-payline-rec.php';
    }

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

	require_once 'vendor/autoload.php';
}
add_action('woocommerce_init', 'woocommerce_payline_init');



/**
 * adds method to woocommerce methods
 * @param $methods
 * @return mixed
 */
function woocommerce_payline_add_method($methods) {
    $methods[] = 'WC_Block_Payline_CPT';
    $methods[] = 'WC_Block_Payline_REC';
    $methods[] = 'WC_Block_Payline_NX';
    $methods[] = 'WC_Gateway_Payline';
    $methods[] = 'WC_Gateway_Payline_NX';
    $methods[] = 'WC_Gateway_Payline_REC';

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
	$links[] = '<a href="'.admin_url('admin.php?page=wc-settings&tab=checkout&section=payline').'">' . __('Settings CPT') .'</a><br />';
    $links[] = '<a href="'.admin_url('admin.php?page=wc-settings&tab=checkout&section=payline_nx').'">' . __('Settings NX') .'</a>';
    $links[] = '<a href="'.admin_url('admin.php?page=wc-settings&tab=checkout&section=payline_rec').'">' . __('Settings REC') .'</a>';
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
    if ( is_checkout() && is_wc_endpoint_url( 'order-pay' ) ) {
        unset( $available_gateways['payline'] );
        unset( $available_gateways['payline_nx'] );
        unset( $available_gateways['payline_rec'] );
    }

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
 * Register Payline payment methods for Gutenberg blocks
 *
 * @return void
 * @since 1.5.0
 */
function payline_register_payment_methods() {
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
	add_management_page(
		__('Payline Logs Viewer'),
		__('Payline Logs Viewer'),
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

add_action('wp_ajax_load_log', array( 'PaylineLogsViewer', 'doAjaxGetLogs' ));
add_action('wp_ajax_nopriv_load_log', array( 'PaylineLogsViewer', 'doAjaxGetLogs' ));