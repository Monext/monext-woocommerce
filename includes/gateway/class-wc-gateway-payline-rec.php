<?php

use Payline\PaylineSDK;

/**
 * Payline module for WooCommerce
 *
 * @class 		WC_Payline
 * @package		WooCommerce
 * @category	Payment Gateways
 *
 * WC tested up to: 4.0.1
 */


class WC_Gateway_Payline_REC extends WC_Abstract_Recurring_Payline_NX {


    protected $paymentMode = 'REC';

    public $id = 'payline_rec';

    protected $defaultName = 'Monext REC';

    public $method_title = 'Monext REC';

    /**
     * Check if the gateway is available for use.
     *
     * @return bool
     */
    public function is_available() {

        $is_available = parent::is_available();
        $cart = WC()->cart;
        if ($is_available && $cart) {
            $eligibleIds = !empty($this->settings['eligible_product_ids']) ? explode(";", $this->settings['eligible_product_ids']) : array();
            foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
                $product = $cart_item['data'];
                if(!in_array($product->get_id(), $eligibleIds)) {
                    return false;
                }
            }
        }
        return $is_available;
    }

    /**
     *
     */
    function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields['max_records'] = array(
            'title' => __('Maximum records', 'payline'),
            'default' => '12',
            'type' => 'int',
            'description' => __('Set a number maximum of records or leave empty', 'payline')
        );

        $this->form_fields['eligible_product_ids'] = array(
            'title' => __('Eligible product ids', 'payline'),
            'default' => '',
            'type' => 'text',
            'description' => __('Define a list of product ids that can be payed with REC. Values must be separated by ;', 'payline')
        );

        /**
         * Contracts settings
         */
        $this->form_fields['contracts'] = array(
            'title' => __( 'CONTRACTS', 'payline' ),
            'type' => 'title'
        );

        $this->form_fields['primary_contracts'] = array(
            'title' => __('Primary contracts', 'payline'),
            'type' => 'multiselect',
            'options' => $this->getContractsList(),
            'description' => $this->getContractDescription()
        );
    }

    /**
     * @see https://docs.payline.com/pages/viewpage.action?pageId=747147142
     * @param WC_Refund|bool|WC_Order $order
     * @return mixed|void
     */
    protected function getWebPaymentRequest(WC_Order $order) {

        $requestParams = parent::getWebPaymentRequest($order);

        $totalAmount = $requestParams['payment']['amount'];
        $requestParams['recurring']['firstAmount'] = $totalAmount;

        $requestParams['recurring']['amount'] = $totalAmount;
        $requestParams['recurring']['billingCycle'] = $this->settings['billing_cycle'];


        $requestParams['recurring']['billingDay'] = '01'; //  [01 à 30]

        $numberDaysCycle = $this->getDaysForCycles($this->settings['billing_cycle']);

        //$today    = current_time( 'd/m/Y' );
        $requestParams['recurring']['startDate'] = current_datetime()->modify( '+' .$numberDaysCycle. ' day' )->format( 'd/m/Y' );; // dd/mm/yyyy

        if($this->settings['billing_cycle'] && $this->settings['max_records']) {
            $numberDaysToLastDate = $numberDaysCycle * $this->settings['max_records'];
            $requestParams['recurring']['endDate'] = current_datetime()->modify( '+' .$numberDaysToLastDate. ' day' )->format( 'd/m/Y' );;

        }

        //$requestParams['recurring']['billingLeft'] = $this->settings['max_records'];

        do_action('payline_before_do_web_payment_rec', $requestParams, $this);

        return $requestParams;
    }
}
