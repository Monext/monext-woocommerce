<?php

/**
 * Payline module for WooCommerce
 *
 * @class 		WC_Payline
 * @package		WooCommerce
 * @category	Payment Gateways
 *
 * WC tested up to: 4.0.1
 */


class WC_Gateway_Payline_CPT extends WC_Abstract_Payline {

    protected $paymentMode = 'CPT';

    public $id = 'payline_cpt';

    protected $defaultName = 'Monext CPT';

    public $method_title = 'Monext CPT';

    /**
     * @param WC_Refund|bool|WC_Order $order
     * @return mixed|void
     */
    protected function getWebPaymentRequest(WC_Order $order) {

        $requestParams = parent::getWebPaymentRequest($order);

        do_action('payline_before_do_web_payment', $requestParams, $this);

        return $requestParams;
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

        if($res['result']['code'] == '00000') {
            $orderId = $order->get_id();

            // Store transaction details
            $order->update_meta_data( 'Transaction ID', $res['transaction']['id']);
            $order->update_meta_data( 'Card number', $res['card']['number']);
            $order->update_meta_data( 'Payment mean', $res['card']['type']);
            $order->update_meta_data( 'Card expiry', $res['card']['expirationDate']);
            $order->update_meta_data( '_contract_number', $res['payment']['contractNumber']);
            $order->save();
            $order->payment_complete($res['transaction']['id']);
            return true;
        }
        return false;
    }

    function init_form_fields() {

        $this->form_fields = array();

        /*
         * Payment Settings
         */
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

        $this->form_fields['description'] = array(
            'title' => __( 'Description', 'payline' ),
            'type' => 'textarea',
            'description' => __( 'This controls the description which the user sees during checkout.', 'payline' ),
            'default' => sprintf(__('You will be redirected on %s secured pages at the end of your order.', 'payline'), 'Payline')
        );
        $this->form_fields['wallet'] = array(
            'title' => __('Wallet', 'payline'),
            'type' => 'checkbox',
            'label' => __('Enable wallet', 'payline'),
            'description' => __( 'Please note: This service is optional and can be configured via the Monext Online Administration Center', 'payline' ),
            'default' => 'no'
        );

        /*$this->form_fields['language'] = array(
            'title' => __('Default language', 'payline'),
            'type' => 'select',
            'default' => '',
            'options' => array(
                '' => __('Based on browser', 'payline'),
                'fr' => __('fr', 'payline'),
                'en' => __('en', 'payline'),
                'pt' => __('pt', 'payline')
            ),
            'description' => __('Language used to display Payline web payment pages', 'payline')
        );*/


        $this->form_fields['payment_action'] = array(
            'title' => __('Payment action', 'payline'),
            'type' => 'select',
            'default' => '',
            'options' => array(
                '100' => __('Authorization', 'payline'),
                '101' => __('Authorization + Capture', 'payline')
            ),
            'description' => __('Type of transaction created after a payment', 'payline')
        );

        $this->form_fields['capture_trigger_on'] = array(
            'title' => __('Capture payment on defined event', 'payline'),
            'type' => 'select',
            'default' => '',
            'options' => $this->getCaptureTriggerOptions(),
            'description' => __('Choose a status to trigger a payment capture', 'payline')
        );

        $this->form_fields['payed_order_status'] = array(
            'title' => __( 'Payed order status', 'payline' ),
            'type' => 'select',
            'default' => 'default',
            'options' => array(
                'default' => __( 'Default Woocommerce status (processing)', 'payline' ),
                'completed' => __( 'Completed', 'payline' )
            ),
            'description' => __( 'Choose the status of payed order', 'payline' )
        );

        $this->form_fields['custom_page_code'] = array(
            'title' => __('Custom page code', 'payline'),
            'type' => 'text',
            'description' => __('In redirection mode, fill the code of payment page customization created in Monext Administration Center', 'payline')
        );

        $this->form_fields['custom_icon'] = array(
            'title' => __( 'Custom icon', 'payline' ),
            'type' => 'url',
            'description' => __('Collez le lien direct de votre image hébergée dans la bibliothèque des médias WordPress. Laissez ce champ vide pour utiliser l\'icône par défaut.', 'payline'),
            'default' => ''
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
            'description' => __('Contracts displayed on web payment page.', 'payline')
        );

        /**
         * Widget settings
         */

        $this->form_fields['widget_settings'] = array(
            'title' => __( 'PAYMENT FORM', 'payline' ),
            'type' => 'title'
        );


        $isBlockTheme = function_exists('wp_is_block_theme') && wp_is_block_theme();
        $widgetIntegrationOptions = [
            'redirection' => __( 'Redirection mode', 'payline' )
        ];
        $widgetIntegrationAdditionalDescription = '<br/><strong>'.__( 'Block theme do not allow widget integration.', 'payline' ).'</strong>';
        if(!$isBlockTheme) {
            $options = array_merge($widgetIntegrationOptions,  [
                'inshop-tab' => __( 'Widget in-Shop Tab mode', 'payline' ),
                'inshop-column' => __( 'Widget in-Shop Column mode', 'payline' ),
                'inshop-lightbox' => __( 'Widget in-Shop Lightbox mode', 'payline' )
            ]);
            $widgetIntegrationAdditionalDescription = '';
        }

        $this->form_fields['widget_integration'] = array(
            'title' => __( 'Widget integration mode', 'payline' ),
            'type' => 'select',
            'default' => 'redirection',
            'options' => array(
                'inshop-tab' => __( 'In-Shop Tab mode', 'payline' ),
                'inshop-column' => __( 'In-Shop Column mode', 'payline' ),
                'inshop-lightbox' => __( 'In-Shop Lightbox mode', 'payline' ),
                'redirection' => __( 'Redirection mode', 'payline' )
            ),
            'description' => __( 'Integration mode of the payment widget in the shop. Contact Monext support for more details', 'payline' )
        );

         $this->form_fields['widget_settings_customize'] = array(
            'title' => __('Status', 'payline'),
            'type' => 'checkbox',
            'label' => sprintf(__('Customize widget', 'payline'), $this->defaultName),
            'default' => 'yes'
        );

        $this->form_fields['widget_settings_cta_label'] = array(
            'title' => __('CTA Label', 'payline'),
            'type' => 'text',
            'default' => __('Confirm and pay', 'payline'),
            'description' => __('For example : "Confirm and pay {{amount}}" will display <em>Confirm and pay 142.56 EUR</em><br />{{amount}} is optional<br /><strong>No html tags allowed</strong>', 'payline'),
            'custom_attributes' => array(
                'maxlength' => '255'
            )
        );

        $this->form_fields['widget_settings_css_cta_bg_color'] = array(
            'title' => __('CTA background color', 'payline'),
            'type' => 'select',
            'default' => __('', 'payline'),
            'options' => [
                '' => __('Monext default', 'payline'),
                '#000000' => __('Black', 'payline'),
                '#d64c1d' => __('Red', 'payline'),
                '#00786c' => __('Green', 'payline'),
                '#42414f' => __('Dark grey', 'payline'),
                '#e6d001' => __('Yellow', 'payline'),
                'custom' => __('Custom color', 'payline'),
            ]
        );

        $this->form_fields['widget_settings_css_cta_bg_color_custom'] = array(
            'title' => __('CTA background custom color', 'payline'),
            'type' => 'color',
            'default' => __('', 'payline'),
            'description' => __('For example : #FF00BB', 'payline')
        );

        $pcRangeOptions = range(10, 30, 10);
        $pcRangeOptions = array_reverse($pcRangeOptions);
        $rangeOptions = [];
        foreach ($pcRangeOptions AS $value) {
            $rangeOptions['-' . $value] = $value . ' % ' . __(' darker', 'payline');
        }

        $rangeOptions[''] = __('No change', 'payline');

        $pcRangeOptions = array_reverse($pcRangeOptions);

        foreach ($pcRangeOptions AS $value) {
            $rangeOptions[$value] = $value . ' % ' . __(' lighter', 'payline');
        }

        $this->form_fields['widget_settings_css_cta_bg_color_hover'] = array(
            'title' => __('CTA hover', 'payline'),
            'type' => 'select',
            'default' => '-20',
            'options' => $rangeOptions
        );

        $this->form_fields['widget_settings_css_cta_text_color'] = array(
            'title' => __('CTA font color', 'payline'),
            'type' => 'select',
            'default' => __('', 'payline'),
            'options' => [
                '' => __('Monext default', 'payline'),
                '#000000' => __('Black', 'payline'),
                '#ffffff' => __('White', 'payline')
            ]
        );

        $this->form_fields['widget_settings_css_font_size'] = array(
            'title' => __('CTA Font size', 'payline'),
            'type' => 'select',
            'default' => __('', 'payline'),
            'options' => [
                '' => __('Monext default', 'payline'),
                'small' => __('Small', 'payline'),
                'average' => __('Average', 'payline'),
                'big' => __('Big', 'payline')
            ]
        );

        $this->form_fields['widget_settings_css_border_radius'] = array(
            'title' => __('Border radius', 'payline'),
            'type' => 'select',
            'default' => __('', 'payline'),
            'options' => [
                '' => __('Monext default', 'payline'),
                'none' => __('None', 'payline'),
                'small' => __('Small', 'payline'),
                'average' => __('Average', 'payline'),
                'big' => __('Big', 'payline')
            ]
        );

        $this->form_fields['widget_settings_css_bg_color'] = array(
            'title' => __('Widget background', 'payline'),
            'type' => 'select',
            'default' => __('', 'payline'),
            'options' => [
                '' => __('Monext default', 'payline'),
                'lighter' => __('Lighter', 'payline'),
                'darker' => __('Darker', 'payline'),
            ]
        );

        $this->form_fields['widget_settings_text_under_cta'] = array(
            'title' => __('Text under CTA', 'payline'),
            'type' => 'text',
            'default' => __('', 'payline'),
            'description' => __('For example : Clicking on the button automatically implies acceptance of the T&Cs<br /><strong>No html tags allowed, max 255 chars.</strong>', 'payline'),
            'custom_attributes' => array(
                'maxlength' => '255'
            ),
        );

        $this->form_fields['widget_settings_cta_preview'] = array(
            'title' => __('Preview CTA', 'payline'),
            'type' => 'preview_cta',
            'default' => '<button class="" id="buttonPreview">' . __('', 'payline') . '</button><p></p>',
        );
    }

    /**
     * @param $key
     * @param $data
     * @return false|string
     */
    protected function generate_preview_cta_html($key, $data)
    {
        $field_key = $this->get_field_key( $key );

        $defaults  = array(
            'title'             => '',
            'disabled'          => false,
            'class'             => '',
            'css'               => '',
            'placeholder'       => '',
            'type'              => 'text',
            'desc_tip'          => false,
            'description'       => '',
            'custom_attributes' => array(),
        );

        $data = wp_parse_args( $data, $defaults );

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
            </th>
            <td class="forminp">
                <div id="paylineCtaPreviewContainer">

                    <?= $data['default'] ?>
                </div>
            </td>
        </tr>
        <?php

        return ob_get_clean();
    }
}
