<?php

use Automattic\WooCommerce\StoreApi\Utilities\OrderController;
use Automattic\WooCommerce\Blocks\Domain\Services\DraftOrders;
use Automattic\WooCommerce\Enums\OrderInternalStatus;
use Payline\Api\ApiInterface as PaylineSDK;

abstract class WC_Abstract_Payline extends WC_Payment_Gateway {

    const BAD_CONNECT_SETTINGS_ERR = "Unauthorized";
    const BAD_PROXY_SETTINGS_ERR = "Could not connect to host";

    const PAYLINE_DATE_FORMAT = 'd/m/Y H:i';

    const PAYLINE_EXTEND_WIDGET_TOKEN_KEY = 'widget_token';

    /**
     * https://docs.payline.com/display/DT/Codes+-+Title
     * @var string
     */
    const DEFAULT_USER_TITLE = 'MR'; // M. / Monsieur

    /** @var Payline\PaylineSDK $SDK */
    protected $SDK;

    protected $urlTypes = ['notification', 'return', 'cancel', 'webhook', 'resetToken'];

    protected $paymentMode = '';

    protected $extensionVersion = WCPAYLINE_PLUGIN_VERSION;

    /** @var int Payline internal API version */
    protected $APIVersion = 34;

    protected $callGetMerchantSettings = true;

    public $order_button_text;
    protected $disp_errors = "";
    protected $admin_link = "";

    protected $debug = false;

    protected $testmode;
    protected $debugEnable;

    protected $paymentTypes = [
        "CPT" => "ONE_OFF",
        "NX" => "MX_INSTALLMENT",
        "REC" => "MX_RECURRING",
    ];

    public function __construct() {

        $this->icon = apply_filters('woocommerce_payline_icon', WCPAYLINE_PLUGIN_URL . 'assets/images/icone-monext.svg');
        $this->has_fields = false;
        $this->supports           = array('products',
            'refunds'
        );

        $this->order_button_text  = __( 'Pay via Monext', 'payline' );

        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        $this->icon = apply_filters('woocommerce_payline_icon',
            !empty($this->settings['custom_icon']) ? $this->settings['custom_icon'] : WCPAYLINE_PLUGIN_URL . 'assets/images/icone-monext.svg'
        );

        // Define user set variables
        $this->title = (!empty($this->settings['title']))? $this->settings['title'] :'Payline'.$this->paymentMode;
        $this->description = (isset($this->settings['description']))? $this->settings['description']:'';
        $this->testmode = (isset($this->settings['ctx_mode']) && $this->settings['ctx_mode'] === 'TEST');
        $this->debugEnable = (isset($this->settings['debug']) && $this->settings['debug'] == 'yes') ? true : false;
        $this->completeSettings();
        $this->enabled = (!$this->is_account_connected()) ? false : $this->enabled;

        // The module settings page URL
        $link = add_query_arg('page', 'wc-settings', admin_url('admin.php'));
        $link = add_query_arg('tab', 'checkout', $link);
        $link = add_query_arg('section', 'payline', $link);
        $this->admin_link = $link;

        // Actions
        $this->add_payline_common_actions();
    }

    protected function paylineSDK() {
        if(empty($this->SDK)) {
//            $this->SDK = $this->paylineSDK->getSDK($this->id);
            $this->SDK = WC_Payline_SDK::getSDK($this->id);
        }

        return $this->SDK;
    }


    /**
     * @param WC_Order $order
     * @param array $res
     * @return mixed
     */
    abstract protected function paylineSuccessWebPaymentDetails(WC_Order $order, array $res);

    /**
     * @param WC_Order $order
     * @param array $res
     * @return bool
     */
    protected function paylineOnHoldPartnerWebPaymentDetails(WC_Order $order, array $res) {
//        return !empty($res['result']['shortMessage']) && $res['result']['shortMessage'] == 'ONHOLD_PARTNER';
        return !empty($res['result']['title']) && $res['result']['title'] == 'ONHOLD_PARTNER';
    }

    /**
     * @param WC_Order $order
     * @param array $res
     * @return mixed
     */
    protected function paylineSetOrderPayed(WC_Order $order) {
        $finalStatus = $this->settings['payed_order_status'];
        if( $finalStatus=='completed' ) {
            $order->update_status('completed', 'Payment validated');
        }
    }

    protected function paylineSetOrderOnHold(WC_Order $order) {
        $order->update_status('on-hold', __('Payment on hold', 'payline'));
        wc_add_notice( __( 'Payment in progress', 'payline' ), 'notice' );
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
     * @param $content
     * @param array $context
     */
    protected function debug($content, $context=array()) {
        if ($this->debugEnable) {
            $logger = wc_get_logger();
            //TODO: Merge log
            //$logger = $this->getSDK()->getLogger();
            $messages = array();
            $messages[] = $_SERVER['REQUEST_URI'];
            $messages[] = get_class($this);
            if($context) {
                $messages[] = implode(':', $context);
            }
            $messages[] = print_r($content, true);
            $logger->debug(implode(PHP_EOL, $messages));
        }
    }


    protected function add_payline_common_actions()
    {
        // Reset payline admin form action
        add_action($this->id . '_reset_admin_options', array($this, 'reset_admin_options'));

        // Generate form action //Todo: Check if we can delete this
//        add_action('woocommerce_receipt_' . $this->id, array($this, 'generate_payline_form'));

        // Update admin form action
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // Return from payment platform action
        add_action('woocommerce_api_wc_gateway_' . $this->id, array($this, 'payline_callback'));
        add_action('woocommerce_order_status_changed', array($this, 'captureOnTrigger'), 10, 4 );

        //Used to refresh widget token in classic checkout
        add_action('woocommerce_before_checkout_form', array($this, 'getNewDraftedOrderId'));

        // Hooks use to add token to extend data. Usefull on block checkout update
        add_action('woocommerce_store_api_cart_select_shipping_rate', function() {$this->addTokenToReactObject();});
        add_action('woocommerce_store_api_cart_update_order_from_request', function() {$this->addTokenToReactObject();});
        add_action('woocommerce_store_api_checkout_update_order_from_request', function() {$this->addTokenToReactObject();});
        add_action('woocommerce_store_api_checkout_update_order_meta', function() {$this->addTokenToReactObject();});
    }

    function get_icon() {
        $icon = $this->icon ? '<img style="width: 85px;" src="' . WC_HTTPS::force_https_url( $this->icon ) . '" alt="' . $this->title . '" />' : '';
        return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
    }

    function add_form_fields($fieldId, $fieldParams, $relativePos = "after", $posRef = "-")
    {
        $index = ($relativePos == "after") ? count($this->form_fields) : 0;
        if($posRef !=="-") {
            $index = array_search( $posRef, array_keys( $this->form_fields ) );
            if(!is_int($index)) {
                $index= count($this->form_fields);
            }
            if($relativePos == "after" ) {
                $index++;
            }
        }

        $this->form_fields = array_merge(
            array_slice( $this->form_fields, 0, $index, true ),
            [$fieldId => $fieldParams],
            array_slice( $this->form_fields, $index, null, true )
        );
    }

    public function admin_options() {
        $templateData = $this->getDefaultTemplateData();
        $templateData['section'] = $this->id;
        echo $this->renderTemplate(__DIR__ . '/views/backend/settings/payline-payments-settings.php', $templateData);
    }

    function reset_admin_options() {
        global $woocommerce;

        if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'payline_reset')) die('Security check');

        @ob_clean();
        delete_option('woocommerce_payline_settings');

        $woocommerce->session->payline_reset = true;

        wp_redirect($this->admin_link);
        die();
    }

    function get_supported_languages($all = false) {
        $langs = array();
        if($all) {
            $langs[''] = __('All', 'payline');
        }
        return $langs;
    }



    function validate_multiselect_field ($key, $value) {
        $newValue = isset($_POST[$this->plugin_id . $this->id . '_' . $key]) ? $_POST[$this->plugin_id . $this->id . '_' . $key] : null;
        if(isset($newValue) && is_array($newValue) && in_array('', $newValue)) {
            return array('');
        } else {
            return parent::validate_multiselect_field ($key, $value);
        }
    }

    /**
     * @return bool
     */
    function is_available()
    {
        $is_available = parent::is_available();
        //Added to prevent an unknow pointOfSaleReference in sessioncreate
        $posExist = array_key_exists($this->settings['pos'],unserialize(get_option('woocommerce_payline_pos_list')));
        if ($is_available && !empty($this->settings['primary_contracts']) && $posExist) {
            return true;
        }
        return false;
    }

    /**
     * Create a draft order on demand. Usefull for widget integration in checkout
     * @return int|null
     * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException
     */
    public function getCurrentDraftedOrderId()
    {
        if(is_admin()) {
            return null;
        }

        if ($this->is_available() && !is_wc_endpoint_url() && WC()->session) {
            return WC()->session->get( 'store_api_draft_order');
        }
        return null;
    }

    public function getNewDraftedOrderId()
    {
        //This conditional exit allows the browser to return from the payline redirection page
        if (!$this->is_available() || !isset($this->settings['widget_integration']) || $this->settings['widget_integration'] == 'redirection'){
            return null;
        }

        if ($this instanceof WC_Gateway_Payline_CPT && $this->is_available() && is_checkout() && ! is_wc_endpoint_url() && WC()->session) {
            if (! WC()->session->get( 'store_api_draft_order' ) ) {
                $order = (new OrderController())->create_order_from_cart();
                $order->calculate_totals();
                WC()->session->set( 'store_api_draft_order', $order->get_id() );
                return $order->get_id();
            }
        }
        return null;
    }

    /**
     * Add extention data to Store API Cart return
     * @return void
     */
    protected function addTokenToReactObject()
    {
        if (!$this->is_available() || !isset($this->settings['widget_integration']) || $this->settings['widget_integration'] == 'redirection'){
            return;
        }

        woocommerce_store_api_register_endpoint_data(
            array(
                'endpoint'        => Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
                'namespace'       => 'monext_payline',
                'data_callback' => [ $this, 'extention_data_callback' ],
                'schema_callback' => function() {
                    return array(
                        'properties' => array(
                            self::PAYLINE_EXTEND_WIDGET_TOKEN_KEY => array(
                                'type' => 'string',
                            ),
                        ),
                    );
                },
                'schema_type'     => ARRAY_A,
            )
        );

    }

    /**
     * Callback function used in addTokenToReactObject
     * @return array
     */
    public function extention_data_callback()
    {
        if($orderId = $this->getCurrentDraftedOrderId()) {
            $order = wc_get_order($orderId);

            $token = '';
            if($order) {
                $token = $this->getCachedDWPDataForOrder($order,'token',true);
                if (empty($token)) {
                    $token = $this->getNewTokenForOrder($order);
                }
            }

            return [
                self::PAYLINE_EXTEND_WIDGET_TOKEN_KEY => $token,
            ];
        }
        return [];
    }


    /**
     * Woocommerce native function to get payment method field.
     * @return void
     */
    function payment_fields()
    {
        $this->processWidgetScripts();
        echo "<p>" . strip_tags($this->settings['description'], '<br>') . "</p>";

        if (( isset($_GET['wc-ajax']) && $_GET['wc-ajax'] == 'update_order_review' ) &&
            preg_match('/inshop-(.*)/', $this->settings['widget_integration'], $match)) {
            if ($order_id = $this->getCurrentDraftedOrderId()) {
                echo $this->getPaylineWidget($order_id, $match);
                echo '<script>Payline.Api.init();</script>';
            } else {
                echo esc_html__('Payment method unavailable', 'payline');
            }
        }
    }


    /**
     * @return void
     */
    public function processWidgetScripts()
    {
        if (is_checkout() && !empty($this->settings['widget_integration']) && ($this->settings['widget_integration'] != 'redirection')) {
        $widgetCustomCss = $this->generateWidgetCustomCss();
        if (!empty($widgetCustomCss)) {
            wp_register_style('payline-custom', false);
            wp_add_inline_style('payline-custom', $widgetCustomCss);
            wp_enqueue_style('payline-custom', false);
        }

        wp_enqueue_script(
            'payline-widget-api',
            WCPAYLINE_PLUGIN_URL . 'assets/js/widget/widget-api.js',
            [ 'jquery' ]
        );

        if($this instanceof WC_Gateway_Payline_CPT) {
            wp_localize_script( 'payline-widget-api', 'paylineData', [
                'customizeWidget' => $this->getConfigValueIfExists('widget_settings_customize'),
                'ctaButton' => $this->getConfigValueIfExists('widget_settings_cta_label'),
                'textUnderCta' => $this->getConfigValueIfExists('widget_settings_text_under_cta'),
                'widget_integration' => $this->settings['widget_integration'],
                'payline_checkout_validator_nonce' => wp_create_nonce( 'payline_checkout_validator' ),
            ]);
        }

        $widgetJS = ($this->settings['environment'] ==PaylineSDK::ENV_HOMO)?PaylineSDK::HOMO_WDGT_JS:PaylineSDK::PROD_WDGT_JS;
        $widgetCSS = ($this->settings['environment'] ==PaylineSDK::ENV_HOMO)?PaylineSDK::HOMO_WDGT_CSS:PaylineSDK::PROD_WDGT_CSS;
        wp_enqueue_script('widget-min', $widgetJS);
        wp_enqueue_style('widget-min', $widgetCSS);
        }
    }

    /**
     * //Todo: To simplify
     * @param $order_id
     * @return array
     */
    function process_payment($order_id) {
		$order = wc_get_order($order_id);

        if (preg_match('/inshop-(.*)/', $this->settings['widget_integration'],$match)) {
            $redirect = add_query_arg('order-pay', $order->get_id(), $order->get_checkout_order_received_url()/*get_permalink(woocommerce_get_page_id('pay'))*/);
        }else {
	        $redirect = $this->getRawRedirectUrl($order->get_id());
        }

		return array(
			'result' 	=> 'success',
			'redirect'	=> $redirect
		);
	}


	function getRawRedirectUrl($order_id) {
		$order = wc_get_order($order_id);

        $redirectURL = $this->getCachedDWPDataForOrder($order, 'redirectURL', true);
        if($redirectURL) {
            return $redirectURL;
        }

        $requestParams = $this->getWebPaymentRequest($order);
        $result = $this->paylineSDK()->doWebPayment( $requestParams );
        $this->debug($result, array(__METHOD__));
        do_action( 'payline_after_do_web_payment', $result, $this );

        if ( $result['result']['code'] === '00000' ) {
            $this->updateTokenForOrder($order, $result);
            return $result['redirectURL'];
        } else {
            $errorCode = $result['result']['code'];
            $errorMsg  = $result['result']['detail'];
        }

        /* translators: %1$s: error code, %2$s: error detail */
        $message = sprintf( __( 'You can\'t be redirected to payment page (error code %1$s : %2$s). Please contact us.', 'payline' ),  $errorCode, $errorMsg);
		return $this->get_error_payment_url($order, $message);
	}

    protected function getTokenOptionKey(WC_Order $order) {
        return 'plnTokenForOrder_' . $order->get_id();
    }


    protected function getArrayTokenForOrder(WC_Order $order) {
        $tokenOptionKey = $this->getTokenOptionKey($order);

        $token = get_option($tokenOptionKey);
        if (!empty($token)) {
            if(is_string($token) && $tokenDecoded = json_decode($token, true)) {
                if(is_array($tokenDecoded)) {
                    return $tokenDecoded;
                }
            }
        }

        return null;
    }


    /**
     * @param WC_Order $order
     * @param $key
     * @param $available
     * @return array|mixed|string|null
     */
    protected function getCachedDWPDataForOrder(WC_Order $order, $key= null, $available = false, $refreshData = true) {
        $tokenDecoded = $this->getArrayTokenForOrder($order);

        if($refreshData) {
            (new OrderController())->update_order_from_cart($order);
        }

        $token = $tokenDecoded['token'] ?? null;
        if($token && !empty($tokenDecoded['date'])) {
            $dtToken = DateTime::createFromFormat('d/m/Y H:i', $tokenDecoded['date']);
            $tokenAge = floor((strtotime(date('Y-m-d H:i'))-strtotime($dtToken->format('Y-m-d H:i')))/60);
            if($available && $tokenAge>12) {
                $tokenDecoded = [];
            }
            if (!isset($tokenDecoded['cart_hash']) || ($available && WC()->cart->get_cart_hash() !== $tokenDecoded['cart_hash'])){
                $tokenDecoded = [];
            }
            if (!isset($tokenDecoded['payment_mode']) || ($available && $this->paymentMode !== $tokenDecoded['payment_mode'])){
                $tokenDecoded = [];
            }
        }

        if($key) {
            return $tokenDecoded[$key] ?? null;
        }
        return $tokenDecoded;
    }


    /**
     * @param WC_Order $order
     * @return mixed|null
     */
    protected function getNewTokenForOrder(WC_Order $order)
    {
        $requestParams = $this->getWebPaymentRequest($order);
        if(empty($requestParams['payment']['amount'])) {
            new WP_Error(
                'error',
                    __('An error occured, cannot retrieve payment amount.', 'payline')
            );
            return null;
        }

        $this->debug($requestParams, array(__METHOD__));

        $result = $this->paylineSDK()->doWebPayment( $requestParams );
        $this->debug($result, array(__METHOD__));
        do_action( 'payline_after_do_web_payment', $result, $this );

        if ( $result['result']['code'] === '00000' ) {
            $this->updateTokenForOrder($order, $result);
            return $result['token'];
        } else {
            new WP_Error(
                'error',
                /* translators: %1$s: error code, %2$s: error detail */
                sprintf(
                    __('An error occured while displaying the payment form (error code %1$s : %2$s). Please contact us.', 'payline'),
                    $result['result']['code'], $result['result']['detail']
                )
            );
            return null;
        }
    }

    /**
     * @param WC_Order $order
     * @param array $dwpResult
     * @return void
     */
    protected function updateTokenForOrder(WC_Order $order, array $dwpResult) {
        unset($dwpResult['result']);

        $tokenOptionKey = $this->getTokenOptionKey($order);
        if(!empty( $dwpResult['token'])) {
            $dwpResult['date'] = date(self::PAYLINE_DATE_FORMAT);
            $dwpResult['cart_hash'] = WC()->cart->get_cart_hash();
            $dwpResult['payment_mode'] = $this->paymentMode;
        } else {
            $dwpResult['token'] = '';
            $dwpResult['cart_hash'] = '';
        }

        if($tokenDecoded = $this->getArrayTokenForOrder($order)) {
            $dwpResult['history'] = $tokenDecoded['history'] ?? [];
            $dwpResult['history'][] = ['token'=>$tokenDecoded['token'], 'date'=>$tokenDecoded['date'] ?? date(self::PAYLINE_DATE_FORMAT)];
        }
        $tokenEncoded = json_encode($dwpResult);

        update_option($tokenOptionKey, $tokenEncoded);
    }


    protected function cleanSubstr($string, $offset, $length = null)
    {
        $cleanString = str_replace(array("\r", "\n", "\t"), array('', '', ''), $string);

        if (extension_loaded('mbstring')) {
            return mb_substr($cleanString, $offset, $length, 'UTF-8');
        }

        return substr($cleanString, $offset, $length);
    }

    /**
     * @param WC_Refund|bool|WC_Order $order
     * @return mixed|void
     */
    protected function getWebPaymentRequest(WC_Order $order)
    {
        (new OrderController())->update_order_from_cart($order);

        $doWebPaymentRequest = array();

        $doWebPaymentRequest['pointOfSaleReference'] = $this->settings['pos'];

        $doWebPaymentRequest['paymentMethod'] = [
            "paymentMethodIDs" => $this->settings['primary_contracts'],
        ];

        $itemsArray = [];
        $items = $order->get_items();
        /** @var WC_Order_Item_Product $item */
        foreach ($items as $item) {
            $orderLine = array(
                'reference' => $this->cleanSubstr($item['name'], 0, 50),
                'price' => round(round(($item->get_subtotal() + $item->get_subtotal_tax())/$item['qty'],2) * 100),
                'quantity' => (int)$item['qty'],
                'comment' => (string)$item['name'],
                'taxRate' => ($item['total'] > 0)?round(($item['total_tax'] / $item['total']) * 100 * 100): 0
            );
            $itemsArray[]=$orderLine;
        }

        $doWebPaymentRequest['order'] = [
            "reference" => $this->cleanSubstr($order->get_id(), 0, 50),
            "amount" => round($order->get_total() * 100),
            "taxes" => round(($order->get_total_tax() - $order->get_shipping_tax()) * 100),
            "currency" => $order->get_currency(),
            "date" => date(DateTime::ATOM),
            "country" => $order->get_billing_country(),
            "items" => $itemsArray,
        ];

        $capture = (!empty($this->settings['payment_action']) && $this->settings['payment_action'] == '101') ? 'AUTOMATIC' : 'MANUAL';
        $paymentType = $this->paymentTypes[$this->paymentMode];

        $doWebPaymentRequest['payment'] = [
            "paymentType" => $paymentType,
            "capture" => $capture,
            "amount" => $doWebPaymentRequest['order']['amount'],
        ];

        $billingEmail = !empty($order->get_billing_email()) ? $order->get_billing_email() : WC()->customer->get_billing_email();
        $billingPhone = $this->cleanSubstr(preg_replace("/[^0-9.]/", '', $order->get_billing_phone()), 0, 15);
        $doWebPaymentRequest['buyer'] = [
            "id" => (string)$order->get_user_id(),
            "title" => self::DEFAULT_USER_TITLE,
            "firstName" => $this->cleanSubstr($order->get_billing_first_name(), 0, 100),
            "lastName" => $this->cleanSubstr($order->get_billing_last_name(), 0, 100),
            "email" => $this->cleanSubstr($billingEmail, 0, 150),
            "mobile" => $billingPhone,
            "billingAddress" => [
//                "title" => self::DEFAULT_USER_TITLE,
                "firstName" =>$this->cleanSubstr($order->get_billing_first_name(), 0, 100),
                "lastName" => $this->cleanSubstr($order->get_billing_last_name(), 0, 100),
                "mobile" => $billingPhone,
                "label" => $order->get_billing_first_name() . " " . $order->get_billing_last_name(),
                "street" => $this->cleanSubstr($order->get_billing_address_1(), 0, 100),
                "complement" => $this->cleanSubstr($order->get_billing_address_2(), 0, 100),
                "city" => $this->cleanSubstr($order->get_billing_city(), 0, 40),
                "zip" => $this->cleanSubstr($order->get_billing_postcode(), 0, 20),
                "country" => $order->get_billing_country(),
            ],
        ];

        $doWebPaymentRequest['delivery'] = [
            "charge" => round(($order->get_shipping_total() + $order->get_shipping_tax()) * 100),
            "address" => [
//                "title" => self::DEFAULT_USER_TITLE,
                "firstName" =>$this->cleanSubstr($order->get_shipping_first_name(), 0, 100),
                "lastName" => $this->cleanSubstr($order->get_shipping_last_name(), 0, 100),
                "mobile" => $this->cleanSubstr(preg_replace("/[^0-9.]/", '', $order->get_shipping_phone()), 0, 15),
                "label" => $order->get_shipping_first_name() . " " . $order->get_shipping_last_name(),
                "street" => $this->cleanSubstr($order->get_shipping_address_1(), 0, 100),
                "complement" => $this->cleanSubstr($order->get_shipping_address_2(), 0, 100),
                "city" => $this->cleanSubstr($order->get_shipping_city(), 0, 40),
                "zip" => $this->cleanSubstr($order->get_shipping_postcode(), 0, 20),
                "country" => $order->get_billing_country(),
            ],
        ];

        $doWebPaymentRequest['privateData'] = [
            "OrderSaleChannel" => "DESKTOP"
        ];

        if(!empty($this->settings['smartdisplay_parameter'])) {
            $doWebPaymentRequest['privateData']['display.rule.param'] = $this->settings['smartdisplay_parameter'];
        }

        $doWebPaymentRequest['returnURL'] = $this->get_request_url('return');
        $doWebPaymentRequest['notificationURL'] = $this->get_request_url('notification');
        $doWebPaymentRequest['languageCode'] = $this->settings['language'];
        $doWebPaymentRequest['customPaymentPageCode'] = $this->settings['custom_page_code'];

        if($this->settings['wallet'] == 'yes' && is_user_logged_in() && $order->get_user_id() > 0){
            $doWebPaymentRequest['mxWalletId'] = $this->encryptWalletId($order->get_user_id());
        }

        return $doWebPaymentRequest;
    }

    protected function get_request_url($urlType = '') {
        return add_query_arg(array('wc-api' => get_class($this), 'url_type'=>$urlType), home_url('/'));
    }

    /**
     * Return payment gateway setting by key
     * @param $key
     * @return false|mixed
     */
    protected function getConfigValueIfExists($key) {
        if (array_key_exists($key, $this->settings) && !empty($this->settings[$key])) {
            return $this->settings[$key];
        }
        return false;
    }

    /**
     * Change CTA color on widget preview
     * @param $hex
     * @param $strenght
     * @param $lighter
     * @return string
     */
    protected function changeColor($hex, $strenght, $lighter)
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        if ($lighter) {
            $r = intval($r + (255 - $r) * ($strenght / 100));
            $g = intval($g + (255 - $g) * ($strenght / 100));
            $b = intval($b + (255 - $b) * ($strenght / 100));
        } else {
            $r = intval($r * (1 - $strenght / 100));
            $g = intval($g * (1 - $strenght / 100));
            $b = intval($b * (1 - $strenght / 100));
        }

        $r = max(0, min(255, $r));
        $g = max(0, min(255, $g));
        $b = max(0, min(255, $b));

        $newHex = sprintf("#%02x%02x%02x", $r, $g, $b);

        return $newHex;
    }

    /**
     * @return string
     */
    protected function generateWidgetCustomCss()
    {
        $retVal = ['#PaylineWidget.pl-container-lightbox {position: fixed;}'];

        $enableWidgetCustomization = $this->getConfigValueIfExists('widget_settings_customize');

        if ( $enableWidgetCustomization === 'no' ) {
            return implode("\n", $retVal);
        }

        //--> Button BG Color
        $ctaBgColor = $this->getConfigValueIfExists('widget_settings_css_cta_bg_color');
        $ctaBgCustomColor = $this->getConfigValueIfExists('widget_settings_css_cta_bg_color_custom');

        if ($ctaBgColor === 'custom' && $ctaBgCustomColor !== false) {
            $ctaBgColor = $ctaBgCustomColor;
        }

        if ($ctaBgColor !== false) {
            $retVal[] = '#PaylineWidget .pl-pay-btn { background-color: ' . $ctaBgColor . '; }';

            //--> Button Hover
            $ctaHoverColor  = $ctaBgColor;
            $bgHoverColor   = $this->getConfigValueIfExists('widget_settings_css_cta_bg_color_hover');

            if ($bgHoverColor !== false) {
                $isLight = $bgHoverColor > 0;
                $amount = abs($bgHoverColor);
                $ctaHoverColor = $this->changeColor($ctaBgColor, $amount, $isLight );
            }

            if ($ctaHoverColor !== false) {
                $retVal[] = '#PaylineWidget .pl-pay-btn:hover { background-color: ' . $ctaHoverColor . '; }';
            }
        }

        //--> Button Text Color
        $ctaTextColor = $this->getConfigValueIfExists('widget_settings_css_cta_text_color');
        if ($ctaTextColor !== false) {
            $retVal[] = '#PaylineWidget .pl-pay-btn { color: ' . $ctaTextColor . '; }';
        }

        //--> Button Font Size
        $ctaFontSize = $this->getConfigValueIfExists('widget_settings_css_font_size');
        if ($ctaFontSize !== false) {
            $fontSize = '';
            switch ($ctaFontSize) {
                case 'big':
                    $fontSize = '24px';
                    break;

                case 'average':
                    $fontSize = '20px';
                    break;

                case 'small':
                    $fontSize = '14px';
                    break;
            }
            $retVal[] = '#PaylineWidget .pl-pay-btn { font-size: ' . $fontSize . '; }';
        }

        //--> Border Radius
        $bordeRadius = $this->getConfigValueIfExists('widget_settings_css_border_radius');
        if ($bordeRadius !== false) {
            $cssBorderRadius = '';
            switch ($bordeRadius) {
                case 'none':
                    $cssBorderRadius = '0';
                    break;
                case 'big':
                    $cssBorderRadius = '24px';
                    break;

                case 'average':
                    $cssBorderRadius = '8px';
                    break;

                case 'small':
                    $cssBorderRadius = '6px';
                    break;
            }
            $retVal[] = '#PaylineWidget .pl-pay-btn { border-radius: ' . $cssBorderRadius . '; }';
        }

        //--> Widget Background
        $widgetBgColor = $this->getConfigValueIfExists('widget_settings_css_bg_color');
        if ($widgetBgColor !== false) {
            $bgColor = '';
            switch ($widgetBgColor) {
                case 'lighter':
                    $bgColor = '#fefefe';
                    break;
                case 'darker':
                    $bgColor = '#dfdfdf';
                    break;
            }

            $retVal[] = '#PaylineWidget.PaylineWidget.pl-layout-tab .pl-paymentMethods { background-color: ' . $bgColor . '; }';
            $retVal[] = '#PaylineWidget.PaylineWidget.pl-container-default .pl-pmContainer { background-color: ' . $bgColor . '; }';
            $retVal[] = '#PaylineWidget.PaylineWidget.pl-layout-tab .pl-tab.pl-active { background-color: ' . $bgColor . '; }';
        }

        $retVal[] = '#PaylineWidget .pl-text-under-cta { text-align: center; margin-top: 26px; }';

        return implode("\n", $retVal);
    }

    /**
     * @param $orderId
     * @param $match
     * @return false|string
     */
    public function getPaylineWidget($orderId, $match = null)
    {
        $order = wc_get_order($orderId);
        if(empty($orderId) || empty($order)){
            return 'Error: Cannot get token for this order.';
        }
        $token = $this->getCachedDWPDataForOrder($order, 'token', true);

        // Prevent to send the request again on refresh.
        if ( !empty( $_GET['paylinetoken'] ) ) {
            $token = $_GET['paylinetoken'];
        } elseif ( !$token ) {
            $token = $this->getNewTokenForOrder($order);
            if (empty($token)) {

                return 'Error: Cannot get token for this order.';

                return false;
            }
        }

        if(is_null($match)) {
            preg_match('/inshop-(.*)/', $this->settings['widget_integration'],$match);
        }

        $template = isset($match[1]) ? $match[1] : '';

        return '<div id="PaylineWidget"
                    data-user-connected="' . (is_user_logged_in() ? 'true' : 'false') . '"
                    data-token="'.$token.'"
                    data-template="'.$template.'"
                    data-embeddedredirectionallowed="true"
                    data-event-didshowstate="eventDidshowstate"
                    data-event-beforepayment="beforePayement"
                    data-event-finalstatehasbeenreached="eventFinalstatehasbeenreached">
                </div>';
    }

    /**
     * @param array $res
     * @param string $message
     * @return void
     * @throws WC_Data_Exception
     */
    protected function payline_callback_cancel($res, $message='') {
        //Todo $res['order']['reference'] sera toujours vide
	    $order = wc_get_order($res['order']['reference']);
        if(!$order) {
            wp_redirect(wc_get_cart_url());
            die();
        }

        // No refund on 02314 result code (when order is canceled before payement)
	    if($res['result']['code'] == '00000'){
		    // Order need transaction_id to be refunded in process_refund function
		    if(!empty($res['transaction']['id'])) {
			    $order->set_transaction_id($res['transaction']['id']);
			    $order->save();
		    }
		    $transactionCanceled = $this->process_refund($order->get_id(), round($res['payment']['amount']/100), $message);
        }else{
		    $transactionCanceled = true;
	    }

	    if ($transactionCanceled and !is_wp_error($transactionCanceled)) {
		    $order->update_status('cancelled', $message);
            $noticeMessage = __( $this->settings['user_error_message_cancelled'], 'payline' );
        }else {
	        $noticeMessage = __( 'Payment cannot be canceled now. Please contact us.', 'payline' );
        }

	    wc_add_notice( $noticeMessage , 'error' );
        $errorCartUrl = add_query_arg(
            array('payline_cancel'=>1,
			    'order_again'    => $order->get_id(),
			    '_wpnonce'       => wp_create_nonce( 'woocommerce-order_again' )
            ),
            wc_get_cart_url()
        );

        wp_redirect($errorCartUrl);
        die();
    }

    /**
     * @param string $type
     * @return void
     * @throws WC_Data_Exception
     */
    protected function payline_callback_webhook($type = 'webhook')
    {
        if(empty($_GET['transactionId'])) {
            return false;
        }

        $res = $this->paylineSDK()->getTransactionDetails(array('transactionId'=> $_GET['transactionId'],
            //'orderRef'=>$_GET['orderRef'],
            'version'=>$this->APIVersion));

        if(empty($res['order']['reference'])) {
            return false;
        }

        $order = wc_get_order($res['order']['reference']);
        if($order && $order->get_id()) {
            $this->paylineManageReturn($order, $res);
            die('Success '.$type);
        }

        die('Error '.$type);
    }

    /**
     *
     * @return void
     * @throws WC_Data_Exception
     */
    function payline_callback() {
        //Todo:
        // Si type webhook => payline_callback_webhook
        // Si type cancel => payline_callback_cancel => revenir sur panier
        // Si type notification =>
        // .... if WEBTRS => continue
        // .... elseif TRS/TRSWALLET => getTransactionDetails => paylineManageReturn
        // Est ce que j'ai un token $_GET['paylinetoken'] ou $_GET['token']-
        // .. Si pas de token je vais vers le panier
        // Est ce que ce token est associer a une commande (serach via bdd lien token <=> commande)
        // Quel est l'état du paiement GWPD
        // .. Si type resetToken =>
        // .. Si type else =>
        // ....CANCELLED => Ne rien faire, la commande est toujours en pending
        // ....REFUSED => Une commande doit exister sinon la créer et attacher la transaction (uniquement si on est pas sur une Notification) + revenir sur le panier
        // ....ACCEPTED, INPROGRESS, ONHOLD_PARTNER, PENDING_RISK. => Une commande doit exister sinon la créer et attacher la transaction

        //Todo SUITE
        // Nouvel fonction pour getTransactionDetails de webhook et notif TRS/TRSWALLET

        $urlType = (isset($_GET['url_type']))?$_GET['url_type']:null;

        if($urlType=='webhook'){
            $this->payline_callback_webhook();
        }

        if($urlType=='notification' && in_array($_GET['notificationType'],['TRS', 'TRSWALLET'])){
            $this->payline_callback_webhook('notification');
            die();
        }

        $token = false;
        if (!empty($_GET['token'])) {
            $token = esc_html($_GET['token']);
        } elseif (!empty($_GET['paylinetoken'])) {
            $token = esc_html($_GET['paylinetoken']);
        }

        if(empty($token)){
            exit;
        }

        $result = $this->paylineSDK()->getWebPaymentDetails(array('token'=>$token,'version'=>$this->APIVersion));
        $this->debug($result, array(__METHOD__));
        if($result['result']['code'] == PaylineSDK::ERR_CODE) {
            $this->paylineSDK()->getLogger()->error('Unable to call Payline for token ' . $token);
            wp_redirect(wc_get_cart_url());
            die();

        }else{
            if(isset($result['order'])){
                $orderId = $result['order']['reference'];
            }else{
                $orderId = WC()->session->get('store_api_draft_order');
            }
            //Todo Pas fiable
            $order = wc_get_order($orderId);
            WC()->session->__unset('store_api_draft_order');
            if(!$order) {
                wp_redirect(wc_get_cart_url());
                die();
            }

            if($urlType=='resetToken'){
                $this->getNewTokenForOrder($order);
                wp_redirect(wc_get_checkout_url());
                die();
            }

            if(!isset($urlType) || !in_array($urlType, $this->urlTypes)){
                $this->payline_callback_cancel($result, 'Unknow url type.');
            }elseif($urlType=='cancel' || $result['result']['title'] == 'CANCELLED'){
                $this->payline_callback_cancel($result, 'Payment was canceled.');
            }else{

                $expectedToken = $this->getCachedDWPDataForOrder($order, 'token',false,false);//Todo: Voir comment reappliquer
                if($expectedToken != $token){
                    /* translators: %1$s: received token, %2$s: expected token, %3$s: order ID */
                    $message = sprintf(__('Token %1$s does not match expected %2$s for order %3$s', 'payline'), wc_clean($token), $expectedToken, $orderId);
                    $this->paylineSDK()->getLogger()->error($message);
                    $order->add_order_note($message);
                    wp_redirect(wc_get_cart_url());
                    die($message);
                }
                $message = $this->paylineManageReturn($order, $result);

                $redirectUrl = $this->get_return_url($order);
                if(in_array($order->get_status(), array('failed', 'cancelled'))) {
                    //Reset Old token on error
                    $this->updateTokenForOrder($order, []);
                    $redirectUrl = $this->get_error_payment_url($order, $message);
                }

                wp_redirect($redirectUrl);
                die();
            }
        }
    }

    /**
     * @param WC_Order $order
     * @param array $res
     * @return void
     * @throws WC_Data_Exception
     */
    protected function paylineManageReturn(WC_Order $order, array $res)
    {
        $message = '';
        $status = '';
        $order->set_payment_method($this->id);
        $order->set_payment_method_title($this->defaultName);
        $orderId = $order->get_id();
        if($this->paylineSuccessWebPaymentDetails($order, $res)) {
            $this->paylineSetOrderPayed($order);
        } elseif($this->paylineOnHoldPartnerWebPaymentDetails($order, $res)) {
            $this->paylineSetOrderOnHold($order);
        } elseif ($res['result']['code'] == '04003') {
            $order->update_meta_data( 'Transaction ID', $res['transaction']['id']);
            $order->update_meta_data( 'Card number', $res['card']['number']);
            $order->update_meta_data( 'Payment mean', $res['card']['type']);
            $order->update_meta_data( 'Card expiry', $res['card']['expirationDate']);
            //Implicit save with update_status
            $order->update_status('on-hold', __('Fraud alert. See details in Monext administration center', 'payline'));
        } elseif ($res['result']['code'] == '02306' || $res['result']['code'] == '02533') {
            $order->add_order_note(__('Payment in progress', 'payline'));
            wc_add_notice( __( 'Payment in progress', 'payline' ), 'notice' );
        } else {
            if($this->paylineCancelWebPaymentDetails($order, $res)) {

            } else {
                if($res['transaction']['id']){
                    //Implicit save with update_status cause $status is set on 'failed'
                    $order->update_meta_data('Transaction ID', $res['transaction']['id']);
                }
            }

            $status = strtolower($res['result']['title']);
	        if(in_array($status,['refused', 'cancelled', 'error'])) {
                $settingKey = 'user_error_message_'.$status;
		        $message = $this->settings[$settingKey];
                if(empty($message)){
	                $message = $this->form_fields[$settingKey]['default'];
                }
            }

            if($status) {
                $orderStatus = ($status == 'cancelled') ? $status : "failed";
                $order->update_status($orderStatus, $message);
            }
        }

        return $message;
    }

    /**
     * Todo: Pas sûr de la garder elle
     * @param $order
     * @param $result
     * @return void
     */
    protected function updateOrderTransactionMetaData($order, $result)
    {
        if ($order->getId()) {
            $order->update_meta_data('Transaction ID', $result['transaction']['id']);
            $order->update_meta_data('Card number', $result['card']['number']);
            $order->update_meta_data('Payment mean', $result['card']['type']);
            $order->update_meta_data('Card expiry', $result['card']['expirationDate']);
        }
    }

    /**
     * @param WC_Order $order
     * @param string $message
     * @return string
     */
    public function get_error_payment_url(WC_Order  $order, $message = '')
    {
        $noticeMessage = __( 'There was a error processing your payment.', 'payline' );
        if($message) {
            $noticeMessage .= ': "' . $message . '"';
        }
        wc_add_notice( $noticeMessage , 'error' );

        if( is_user_logged_in()) {
            $errorUrl = add_query_arg(
                array('order_again'=> $order->get_id(),
                    'payline_cancel'=>1,
                    '_wpnonce' =>wp_create_nonce( 'woocommerce-order_again' )
                ),
                wc_get_cart_url()
            );
        } else {
            $errorUrl = $order->get_cancel_order_url();
        }

        return $errorUrl;
    }



    /**
     * Can the order be refunded via Payline?
     *
     * @param  WC_Order $order Order object.
     * @return bool
     */
    public function can_refund_order( $order ) {
        $contractNumber = $order->get_meta('_contract_number' ,true);
        return $order && $order->get_transaction_id();
    }


    /**
     * Process a refund if supported.
     *
     * @param  int    $order_id Order ID.
     * @param  float  $amount Refund amount.
     * @param  string $reason Refund reason.
     * @return bool|WP_Error
     */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        $order = wc_get_order( $order_id );

        if ( ! $this->can_refund_order( $order ) ) {
            return new WP_Error( 'error', __( 'Refund failed.', 'payline' ) );
        }


        $paymentParams = array();
        $paymentParams['amount'] = round($amount*100);
        $paymentParams['currency'] = $order->get_currency();
        $paymentParams['action'] = 421;
        $paymentParams['mode'] =  $this->paymentMode;
        $paymentParams['contractNumber'] = $order->get_meta('_contract_number' ,true);

        $refundParams = array(
            'transactionID' => $order->get_transaction_id(),
            'comment' => $reason,
            'payment'  => $paymentParams,
            'sequenceNumber' => ''
        );

        $res = $this->paylineSDK()->doRefund($refundParams);
        $this->debug($res, array(__METHOD__));
        if($res['result']['code'] == '00000'){
            $order->add_order_note(
                /* translators: %1$s: refund amount, %2$s: refund transaction ID */
                sprintf( __( 'Refunded %1$s - Refund ID: %2$s', 'payline' ), $amount, $res['transaction']['id'] )
            );
            return true;
        } else {
            return new WP_Error( 'error',$res['result']['detail'] );
        }

        return false;
    }

	/**
     * Return md5 encrypted customerId to use as walletId in Payline Web services
	 * @param string $customerId
	 * @return string
	 */
	public function encryptWalletId(string $customerId) {
	    return md5($customerId);
    }

    /**
     * Render given template
     * @param $templatePath
     * @param $data
     * @return false|string
     * @throws Exception
     */
    protected function renderTemplate($templatePath, $data = [])
    {
        // Vérifie si le fichier existe
        if (!file_exists($templatePath)) {
            throw new Exception("Le fichier de template n'existe pas : " . $templatePath);
        }

        // Extrait les données pour les rendre disponibles dans le template
        extract($data);

        // Démarre la temporisation de sortie
        ob_start();

        // Inclut le fichier de template
        include $templatePath;

        // Récupère le contenu du buffer et le nettoie
        $content = ob_get_clean();

        // Retourne le contenu généré
        return $content;
    }

    /**
     * Return useful data for current payment gateway settings page
     * @return array
     */
    protected function getDefaultTemplateData()
    {
        global $woocommerce;

        if(key_exists('reset', $_REQUEST) && $_REQUEST['reset'] == 'true') {
            do_action($this->id . '_reset_admin_options');
        }

        $templateData = [];
        $dispErrors = [];
        $dispConfirm = [];
        $resetLink = add_query_arg('reset', 'true', $this->admin_link);
        $resetLink = wp_nonce_url($resetLink, 'payline_reset');


        if (!empty($woocommerce->session->payline_reset)){
            unset($woocommerce->session->payline_reset);
            /* translators: %s: payment service name */
            $templateData['reset_message'] = sprintf(__( 'Your %s configuration parameters are reset.', 'payline'), 'Payline');
        }

        if(array_key_exists('merchant_id', $this->settings) && ($this->settings['merchant_id'] == null || strlen($this->settings['merchant_id']) == 0)){
            $this->callGetMerchantSettings = false;
            /* translators: %s: setting field name */
            $dispErrors[] = sprintf(__( '%s is mandatory', 'payline'), __('Merchant ID', 'payline' ));
        }
        if(array_key_exists('access_key', $this->settings) && ($this->settings['access_key'] == null || strlen($this->settings['access_key']) == 0)){
            $this->callGetMerchantSettings = false;
            /* translators: %s: setting field name */
            $dispErrors[] = sprintf(__( '%s is mandatory', 'payline'), __('Access Key', 'payline' ));
        }

        if(!isset($this->settings['pos']) || empty($this->settings['pos'])){
            $this->callGetMerchantSettings = false;
            /* translators: %s: setting field name */
            $dispErrors[] = sprintf(__( '%s is mandatory', 'payline'), __('Point of Sales', 'payline' ));
        }

        if(!empty(unserialize(get_option( 'woocommerce_payline_pos_list'))) && empty(unserialize(get_option('woocommerce_payline_pos_contracts_list')))){
            $this->callGetMerchantSettings = false;
            $dispErrors[] = __( 'No contracts seems to be available with this point of sales', 'payline');
        }

        if(array_key_exists('primary_contracts', $this->settings) && ($this->settings['primary_contracts'] == null || empty($this->settings['primary_contracts']))){
            $this->callGetMerchantSettings = false;
            $dispErrors[] = __( 'Please select at least one contract in contracts tab', 'payline');
        }

        //--> Check setup with Monext
        if($this->callGetMerchantSettings){
            $res = $this->paylineSDK()->getEncryptionKey([]);
            if($res['result']['code'] == '00000'){
                $dispConfirm[] = __( 'Your settings is correct, connexion with Monext is established', 'payline');
                if($this->settings['environment'] == PaylineSDK::ENV_HOMO){
                    $dispConfirm[] = __( 'You are in homologation mode, payments are simulated !', 'payline');
                }
            }else{
                if(strcmp(WC_Gateway_Payline_CPT::BAD_CONNECT_SETTINGS_ERR, $res['result']['detail']) == 0){
                    /* translators: %s: settings section name */
                    $this->disp_errors .= "<p>".sprintf(__( 'Unable to connect to Monext, check your %s', 'payline'), __('MONEXT GATEWAY ACCESS', 'payline' ))."</p>";
                }elseif(strcmp(WC_Gateway_Payline_CPT::BAD_PROXY_SETTINGS_ERR, $res['result']['detail']) == 0){
                    /* translators: %s: settings section name */
                    $this->disp_errors .= "<p>".sprintf(__( 'Unable to connect to Monext, check your %s', 'payline'), __('PROXY SETTINGS', 'payline' ))."</p>";
                }else{
                    /* translators: %1$s: error code, %2$s: error detail */
                    $dispErrors[] = "<p>".sprintf(__( 'Unable to connect to Monext (code %1$s : %2$s)', 'payline'), $res['result']['code'], $res['result']['detail']);
                }
            }
        }

        $templateData['errors'] = $dispErrors;
        $templateData['confirmations'] = $dispConfirm;

        //--> Reset link
        $templateData['reset_link'] = $resetLink;
        $templateData['reset_link_label'] = __('Reset configuration', 'payline');

        return $templateData;
    }

    /**
     * Merge globals setting to gateway setting. Usefull for configuration check
     * @return void
     */
    protected function completeSettings()
    {
        $globalsSettings = get_option('woocommerce_payline_settings');
        if(!empty($globalsSettings)){
            unset($globalsSettings['enabled']);
            $this->settings = array_merge($this->settings, $globalsSettings);
        }
    }

    /**
     * Return a list of contracts for payments settings
     * @return array
     */
    public function getContractsList()
    {
        $optionList = get_option( 'woocommerce_payline_pos_contracts_list', []);
        if(!empty($optionList)){
            $optionList = unserialize($optionList);
            $contractsList = [];
            foreach ($optionList as $contract) {
                $contractsList[$contract['contractNumber']] = $contract['label'];
            }
            return $contractsList;
        }
        return $optionList;
    }
    /**
     * @return array
     */
    protected function getCaptureTriggerOptions()
    {
        $options = [];
        foreach (wc_get_order_statuses() as $statusKey => $status) {
            if(in_array($statusKey, [OrderInternalStatus::CANCELLED,
                OrderInternalStatus::REFUNDED,
                OrderInternalStatus::FAILED,
                OrderInternalStatus::ON_HOLD,
                OrderInternalStatus::PENDING,
                DraftOrders::DB_STATUS])) {
                continue;
            }

            $cleanStatusKey = str_replace( 'wc-', '', $statusKey );
            /* translators: %s: order status name */
            $options[$cleanStatusKey]= sprintf(__('When order status is "%s"', 'payline'), $status);
        }
        return $options;
    }

    /**
     * @param $order_id
     * @param $previous_status
     * @param $next_status
     * @param $order
     * @return bool|void
     */
    public function captureOnTrigger($order_id, $previous_status, $next_status, $order)
    {
        if(!$order || $order->get_payment_method() != $this->id || empty($order->get_transaction_id())){
            return;
        }

        $capture_trigger_on = $this->settings['capture_trigger_on'];
        if(!$capture_trigger_on){
            return;
        }

        if($this->settings['payment_action'] != 100 || $capture_trigger_on != $next_status) {
            return;
        }

        # delay to fix issue raised in ticket #40105
        usleep(500000);

        return $this->captureOrder($order);
    }

    /**
     * @param $order
     * @return bool|void
     */
    protected function captureOrder($order)
    {

        //Todo: When transaction table will be created, replace this API call by a database query
        $txDetailsRes = $this->paylineSDK()->getTransactionDetails(array('transactionId'=> $order->get_transaction_id(),'transactionHistory'=> 'Y'));
//        $associatedTransactions = $txDetailsRes['associatedTransactionsList']['associatedTransactions'];
        $associatedTransactions = $txDetailsRes['associatedTransactions'];
        if(is_array(reset($associatedTransactions))) {
            foreach ($txDetailsRes['associatedTransactions'] as $transaction) {
                if ($transaction['type'] == 'CAPTURE'){
                    return;
                }
            }
        }

        $paymentParams = array();
        $paymentParams['amount'] = round($order->get_total() * 100);
        $paymentParams['currency'] = $order->get_currency();
        $paymentParams['action'] = 201;
        $paymentParams['mode'] =  $this->paymentMode;

        $res = $this->paylineSDK()->doCapture(array('transactionID' => $order->get_transaction_id(), 'payment'  => $paymentParams));
        $this->debug($res, array(__METHOD__));

        if($res['result']['code'] == '00000'){
            $order->add_order_note(
                sprintf( 'Capture %1$s %2$s - Capture ID: %3$s', round($order->get_total(), 4), $order->get_currency(), $res['transaction']['id'] )
            );
            return true;
        } else {
            $order->add_order_note(
                sprintf( 'Capture error :"%1$s"', $res['result']['detail'] )
            );
            return false;
        }
    }

    /**
     * If true, add "Complete setup" button on woo payment methods list
     * @return bool
     */
    public function is_account_connected()
    {
        if(empty($this->settings['merchant_id']))
        {
            return false;

        }
        if(empty($this->settings['merchant_id']))
        {
            return false;
        }

        if(empty($this->settings['access_key']))
        {
            return false;
        }

        if(empty($this->settings['pos']))
        {
            return false;
        }

        return true;
    }

    /**
     * If true, add tag "Action needed" on woo payment methods list
     * @return bool
     */
    public function needs_setup()
    {
        if(!$this->is_account_connected())
        {
            $this->enabled = 'no';
            return true;
        }

        $this->enabled = 'yes';
        return false;
    }

    /**
     * If true, add tag "Test mode" on woo payment methods list
     * @return bool
     */
    public function is_test_mode()
    {
        if(empty($this->settings['environment'])){
            return false;
        }
        return ($this->settings['environment'] == PaylineSDK::ENV_HOMO);
    }

    public function createManageWebWallet()
    {
        $contracts = [];
        $currentPos         = $this->settings['pos'];
        $contractsList = $this->getContractsForCurrentPos();
        $walletId = $this->encryptWalletId(get_current_user_id());
        $customer = new WC_Customer( get_current_user_id() );

        foreach ($contractsList as $contract) {
            if(!empty($contract['wallet'])) {
                $contracts[] = $contract['contractNumber'];
            }
        }

        $params = array(
            "pointOfSaleReference" => $currentPos,
            "paymentMethodIDs" => $contracts,
            "mxWalletId" => $walletId,
            'buyer' => array(
                'id' => $this->cleanSubstr($customer->get_id(), 0, 50),
                'firstName' => $this->cleanSubstr($customer->get_first_name(), 0, 100),
                'lastName' => $this->cleanSubstr($customer->get_last_name(), 0, 100),
                'email' => $this->cleanSubstr($customer->get_email(), 0, 150),
                'walletId' => $walletId,
            ),
            'allowUpdatePersonalDetails' => 0,
            'returnURL' => $this->get_request_url('return'),
            'notificationURL' => $this->get_request_url('notification'),
        );

        return $this->paylineSDK()->manageWebWallet($params);
    }

    protected function getContractsForCurrentPos()
    {
        $currentPos         = $this->settings['pos'];
        $enabledContracts   = $this->getContractsList();
        $contractsList      = $this->getContractsByPosLabel($currentPos, $enabledContracts, true);
        return $contractsList;
    }

    protected function getContractsByPosLabel($posLabel, $enabledContracts = array())
    {
        $contractsList = WC_Payline_SDK::getPaymentMethods($posLabel);

        $paymentMethods = [];
        if (!is_wp_error($contractsList) && isset($contractsList['paymentMethods'])) {
            // REST method getPaymentMethods is not stablilized
            if (isset($contractsList['paymentMethods'])) {
                $paymentMethods = $contractsList['paymentMethods'];
            } elseif (!empty($contractsList[0]["paymentInstrumentType"])) {
                $paymentMethods = $contractsList;
            }

            foreach ($paymentMethods as $contract) {
                $posContracts[] = [
                    "id" => $contract['paymentInstrumentType']['code'] . '-' . $contract['id'],
                    "label" => $contract['name'],
                    "contractNumber" => $contract['id'],
                    "enabled" => (in_array($contract['name'], $enabledContracts)),
                    "wallet" => (in_array($contract['paymentInstrumentType']['code'], ['AMEX', 'CB']))
                ];
            }
            return $posContracts;
        }

        return array();
    }
}
