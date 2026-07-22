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

abstract class WC_Abstract_Recurring_Payline_NX extends WC_Abstract_Payline {


    protected $paymentMode = 'NX';

    public $id = 'payline_nx';

    protected $defaultName = 'Monext NX';

    function init_form_fields()
    {
        $this->form_fields = array();

        $this->form_fields['payment_settings'] = array(
            'title' => __( 'PAYMENT SETTINGS', 'payline' ),
            'type' => 'title'
        );

        $this->form_fields['enabled'] = array(
            'title' => __('Status', 'payline'),
            'type' => 'checkbox',
            'label' => sprintf(__('Enable %s', 'payline'), $this->defaultName),
            'default' => 'no'
        );

        $this->form_fields['title'] = array(
            'title' => __('Title', 'payline'),
            'type' => 'text',
            'description' => __('This controls the title which the user sees during checkout.', 'payline'),
            'default' => $this->defaultName
        );

        $this->form_fields['widget_integration'] = array(
            'type' => 'hidden',
            'default' => "redirection"
        );

        $this->form_fields['description'] = array(
            'title' => __( 'Description', 'payline' ),
            'type' => 'textarea',
            'description' => __( 'This controls the description which the user sees during checkout.', 'payline' ),
            'default' => sprintf(__('You will be redirected on %s secured pages at the end of your order.', 'payline'), 'Payline')
        );

        $this->form_fields['custom_icon'] = array(
            'title' => __( 'Custom icon', 'payline' ),
            'type' => 'url',
            'description' => __('Paste the direct link to your image hosted in the WordPress media library. Leave this field blank to use the default icon.', 'payline'),
            'default' => ''
        );

        $this->form_fields['billing_cycle'] = array(
            'title' => __('Payment frequencies', 'payline'),
            'type' => 'select',
            'default' => '40',
            'options' => array(
                '10' => __('One transaction per day', 'payline'),
                '20' => __('One transaction every seven days', 'payline'),
                '30' => __('Two transactions per month', 'payline'),
                '40' => __('One transaction per month', 'payline'),
                '50' => __('One transaction every two months', 'payline'),
                '60' => __('One transaction every three months', 'payline'),
                '70' => __('One transaction every six months', 'payline'),
                '80' => __('One transaction per year', 'payline'),
                '90' => __('A transaction every two years', 'payline')
            )
        );
    }

    protected function getDaysForCycles($code)
    {
        $cyclesDays = array('10' => 1,
            '20' => 7,
            '30' => 15,
            '40' => 30,
            '50' => 60,
            '60' => 90,
            '70' => 180,
            '80' => 360,
            '90' => 720
        );

        return !empty($cyclesDays[$code]) ? $cyclesDays[$code] : 0;
    }


    /**
     * @param WC_Order $order
     * @param array $res
     * @return false
     */
    protected function paylineCancelWebPaymentDetails(WC_Order $order, array $res) {
        return false;
    }

    /**
     * @param WC_Order $order
     * @param array $res
     * @return bool
     */
    protected function paylineSuccessWebPaymentDetails(WC_Order $order, array $res) {

        if($res['result']['code'] == '02500') {
            $orderId = $order->get_id();
            // Store transaction details
            $order->update_meta_data( 'Transaction ID', $res['transaction']['id']);
            $order->update_meta_data( '_contract_number', $res['payment']['contractNumber']);
            $order->save();
            $order->payment_complete($res['transaction']['id']);
            return true;
        }

        return false;
    }

    /**
     * @return string
     */
    protected function getContractDescription()
    {
        $baseDesc = __('Contracts displayed on web payment page.', 'payline');

        $additionalDescription = '<br/><strong>'
            . __( 'PLEASE NOTE: Select only “card” payment methods.', 'payline' )
            . '</strong>';

        return $baseDesc . $additionalDescription;
    }

}
