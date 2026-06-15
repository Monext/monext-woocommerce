<?php

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;

class WC_Block_Payline_CPT extends WC_Block_Abstract_Payline {

	/**
	 * @var string
	 */
	protected $name = 'payline_cpt';

	/**
	 * @var string
	 */
	protected $settingsOptionName = 'woocommerce_payline_cpt_settings';

	/**
	 * @var string
	 */
	protected $handle = 'wc-payment-method-payline-cpt';

	/**
	 * @var class-string
	 */
	protected $gatewayClass = WC_Gateway_Payline_CPT::class;

    /**
     * @return array|string[]
     * @throws RouteException
     */
    public function get_payment_method_additionnal_data()
    {
        $payline_widget_div = '';
        $widget_integration = 'redirection';

        if (is_user_logged_in() && is_checkout() && !empty($this->settings['widget_integration']) && ($this->settings['widget_integration'] != 'redirection'))
        {
            /** @var WC_Abstract_Payline $gateway */
            $gateway = new $this->gateway;
            $gateway->processWidgetScripts();
            $widget_integration = $gateway->settings['widget_integration'];

            $order_id = null;
            if ( function_exists('WC') && WC()->session && method_exists( WC()->session, 'get' ) ) {
                $order_id = WC()->session->get( 'store_api_draft_order' );
            }

            if(empty($order_id)){
                $order_id = $gateway->getNewDraftedOrderId();
            }

            if (!empty($order_id)) {
                $payline_widget_div = $gateway->getPaylineWidget($order_id);
            }
        }

        return [
            'payline_widget_div' => $payline_widget_div,
            'widget_integration' => $widget_integration
        ];
    }
}
