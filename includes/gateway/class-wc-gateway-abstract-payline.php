<?php

use Automattic\WooCommerce\StoreApi\Utilities\OrderController;
use Automattic\WooCommerce\Blocks\Domain\Services\DraftOrders;
use Automattic\WooCommerce\Enums\OrderInternalStatus;
use Monolog\Logger;
use Payline\PaylineSDK;
use Automattic\WooCommerce\Utilities\LoggingUtil;


abstract class WC_Abstract_Payline extends WC_Payment_Gateway {

    const BAD_CONNECT_SETTINGS_ERR = "Unauthorized";
    const BAD_PROXY_SETTINGS_ERR = "Could not connect to host";

    const PAYLINE_DATE_FORMAT = 'd/m/Y H:i';

    const PAYLINE_EXTEND_WIDGET_TOKEN_KEY = 'widget_token';

    /**
     * https://docs.payline.com/display/DT/Codes+-+Title
     * @var string
     */
    const DEFAULT_USER_TITLE = '4'; // M. / Monsieur

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

    var $_currencies = array(
        'EUR' => '978', // Euro
        'AFN' => '971', // Afghani
        'ALL' => '8', // Lek
        'DZD' => '12', // Algerian Dinar
        'USD' => '840', // US Dollar
        'AOA' => '973', // Kwanza
        'XCD' => '951', // East Caribbean Dollar
        'ARS' => '32', // Argentine Peso
        'AMD' => '51', // Armenian Dram
        'AWG' => '533', // Aruban Guilder
        'AUD' => '36', // Australian Dollar
        'AZN' => '944', // Azerbaijanian Manat
        'BSD' => '44', // Bahamian Dollar
        'BHD' => '48', // Bahraini Dinar
        'BDT' => '50', // Taka
        'BBD' => '52', // Barbados Dollar
        'BYR' => '974', // Belarussian Ruble
        'BZD' => '84', // Belize Dollar
        'XOF' => '952', // CFA Franc BCEAO �
        'BMD' => '60', // Bermudian Dollar (customarily known as Bermuda Dollar)
        'INR' => '356', // Indian Rupee
        'BTN' => '64', // Ngultrum
        'BOB' => '68', // Boliviano
        'BOV' => '984', // Mvdol
        'BAM' => '977', // Convertible Marks
        'BWP' => '72', // Pula
        'NOK' => '578', // Norwegian Krone
        'BRL' => '986', // Brazilian Real
        'BND' => '96', // Brunei Dollar
        'BGN' => '975', // Bulgarian Lev
        'BIF' => '108', // Burundi Franc
        'KHR' => '116', // Riel
        'XAF' => '950', // CFA Franc BEAC �
        'CAD' => '124', // Canadian Dollar
        'CVE' => '132', // Cape Verde Escudo
        'KYD' => '136', // Cayman Islands Dollar
        'CLP' => '152', // Chilean Peso
        'CLF' => '990', // Unidades de formento
        'CNY' => '156', // Yuan Renminbi
        'COP' => '170', // Colombian Peso
        'COU' => '970', // Unidad de Valor Real
        'KMF' => '174', // Comoro Franc
        'CDF' => '976', // Franc Congolais
        'NZD' => '554', // New Zealand Dollar
        'CRC' => '188', // Costa Rican Colon
        'HRK' => '191', // Croatian Kuna
        'CUP' => '192', // Cuban Peso
        'CYP' => '196', // Cyprus Pound
        'CZK' => '203', // Czech Koruna
        'DKK' => '208', // Danish Krone
        'DJF' => '262', // Djibouti Franc
        'DOP' => '214', // Dominican Peso
        'EGP' => '818', // Egyptian Pound
        'SVC' => '222', // El Salvador Colon
        'ERN' => '232', // Nakfa
        'EEK' => '233', // Kroon
        'ETB' => '230', // Ethiopian Birr
        'FKP' => '238', // Falkland Islands Pound
        'FJD' => '242', // Fiji Dollar
        'XPF' => '953', // CFP Franc
        'GMD' => '270', // Dalasi
        'GEL' => '981', // Lari
        'GHC' => '288', // Cedi
        'GIP' => '292', // Gibraltar Pound
        'GTQ' => '320', // Quetzal
        'GNF' => '324', // Guinea Franc
        'GWP' => '624', // Guinea-Bissau Peso
        'GYD' => '328', // Guyana Dollar
        'HTG' => '332', // Gourde
        'HNL' => '340', // Lempira
        'HKD' => '344', // Hong Kong Dollar
        'HUF' => '348', // Forint
        'ISK' => '352', // Iceland Krona
        'IDR' => '360', // Rupiah
        'XDR' => '960', // SDR
        'IRR' => '364', // Iranian Rial
        'IQD' => '368', // Iraqi Dinar
        'ILS' => '376', // New Israeli Sheqel
        'JMD' => '388', // Jamaican Dollar
        'JPY' => '392', // Yen
        'JOD' => '400', // Jordanian Dinar
        'KZT' => '398', // Tenge
        'KES' => '404', // Kenyan Shilling
        'KPW' => '408', // North Korean Won
        'KRW' => '410', // Won
        'KWD' => '414', // Kuwaiti Dinar
        'KGS' => '417', // Som
        'LAK' => '418', // Kip
        'LVL' => '428', // Latvian Lats
        'LBP' => '422', // Lebanese Pound
        'ZAR' => '710', // Rand
        'LSL' => '426', // Loti
        'LRD' => '430', // Liberian Dollar
        'LYD' => '434', // Libyan Dinar
        'CHF' => '756', // Swiss Franc
        'LTL' => '440', // Lithuanian Litas
        'MOP' => '446', // Pataca
        'MKD' => '807', // Denar
        'MGA' => '969', // Malagascy Ariary
        'MWK' => '454', // Kwacha
        'MYR' => '458', // Malaysian Ringgit
        'MVR' => '462', // Rufiyaa
        'MTL' => '470', // Maltese Lira
        'MRO' => '478', // Ouguiya
        'MUR' => '480', // Mauritius Rupee
        'MXN' => '484', // Mexican Peso
        'MXV' => '979', // Mexican Unidad de Inversion (UID)
        'MDL' => '498', // Moldovan Leu
        'MNT' => '496', // Tugrik
        'MAD' => '504', // Moroccan Dirham
        'MZN' => '943', // Metical
        'MMK' => '104', // Kyat
        'NAD' => '516', // Namibian Dollar
        'NPR' => '524', // Nepalese Rupee
        'ANG' => '532', // Netherlands Antillian Guilder
        'NIO' => '558', // Cordoba Oro
        'NGN' => '566', // Naira
        'OMR' => '512', // Rial Omani
        'PKR' => '586', // Pakistan Rupee
        'PAB' => '590', // Balboa
        'PGK' => '598', // Kina
        'PYG' => '600', // Guarani
        'PEN' => '604', // Nuevo Sol
        'PHP' => '608', // Philippine Peso
        'PLN' => '985', // Zloty
        'QAR' => '634', // Qatari Rial
        'ROL' => '642', // Old Leu
        'RON' => '946', // New Leu
        'RUB' => '643', // Russian Ruble
        'RWF' => '646', // Rwanda Franc
        'SHP' => '654', // Saint Helena Pound
        'WST' => '882', // Tala
        'STD' => '678', // Dobra
        'SAR' => '682', // Saudi Riyal
        'RSD' => '941', // Serbian Dinar
        'SCR' => '690', // Seychelles Rupee
        'SLL' => '694', // Leone
        'SGD' => '702', // Singapore Dollar
        'SKK' => '703', // Slovak Koruna
        'SIT' => '705', // Tolar
        'SBD' => '90', // Solomon Islands Dollar
        'SOS' => '706', // Somali Shilling
        'LKR' => '144', // Sri Lanka Rupee
        'SDG' => '938', // Sudanese Dinar
        'SRD' => '968', // Surinam Dollar
        'SZL' => '748', // Lilangeni
        'SEK' => '752', // Swedish Krona
        'CHW' => '948', // WIR Franc
        'CHE' => '947', // WIR Euro
        'SYP' => '760', // Syrian Pound
        'TWD' => '901', // New Taiwan Dollar
        'TJS' => '972', // Somoni
        'TZS' => '834', // Tanzanian Shilling
        'THB' => '764', // Baht
        'TOP' => '776', // Pa'anga
        'TTD' => '780', // Trinidad and Tobago Dollar
        'TND' => '788', // Tunisian Dinar
        'TRY' => '949', // New Turkish Lira
        'TMM' => '795', // Manat
        'UGX' => '800', // Uganda Shilling
        'UAH' => '980', // Hryvnia
        'AED' => '784', // UAE Dirham
        'GBP' => '826', // Pound Sterling
        'USS' => '998', // (Same day)
        'USN' => '997', // (Next day)
        'UYU' => '858', // Peso Uruguayo
        'UYI' => '940', // Uruguay Peso en Unidades Indexadas
        'UZS' => '860', // Uzbekistan Sum
        'VUV' => '548', // Vatu
        'VEB' => '862', // Bolivar
        'VND' => '704', // Dong
        'YER' => '886', // Yemeni Rial
        'ZMK' => '894', // Kwacha
        'ZWD' => '716', // Zimbabwe Dollar
        'XAU' => '959', // Gold
        'XBA' => '955', // Bond Markets Units European Composite Unit (EURCO)
        'XBB' => '956', // European Monetary Unit (E.M.U.-6)
        'XBC' => '957', // European Unit of Account 9(E.U.A.-9)
        'XBD' => '958', // European Unit of Account 17(E.U.A.-17)
        'XPD' => '964', // Palladium
        'XPT' => '962', // Platinum
        'XAG' => '961', // Silver
        'XTS' => '963', // Codes specifically reserved for testing purposes
        'XXX' => '999', // The codes assigned for transactions where no currency is involved
    );

    protected $testmode;
    protected $debugEnable;

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
        return !empty($res['result']['shortMessage']) && $res['result']['shortMessage'] == 'ONHOLD_PARTNER';
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
        if ($is_available && !empty($this->settings['primary_contracts'])) {
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
                echo __('Payment method unavailable');
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
            $errorMsg  = $result['result']['longMessage'];
        }

        $message = sprintf( __( 'You can\'t be redirected to payment page (error code %s : %s). Please contact us.', 'payline' ),  $errorCode, $errorMsg);
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
    protected function getCachedDWPDataForOrder(WC_Order $order, $key= null, $available = false) {
        $tokenDecoded = $this->getArrayTokenForOrder($order);
        (new OrderController())->update_order_from_cart($order);

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
                sprintf(
                    __('An error occured while displaying the payment form (error code %s : %s). Please contact us.', 'payline'),
                    $result['result']['code'], $result['result']['longMessage']
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
        $doWebPaymentRequest['version'] = $this->APIVersion;
        $doWebPaymentRequest['payment']['amount'] = round($order->get_total() * 100);
        $doWebPaymentRequest['payment']['currency'] = $this->_currencies[$order->get_currency()];
        $doWebPaymentRequest['payment']['action'] = (isset($this->settings['payment_action']))? $this->settings['payment_action'] : '101';
        $doWebPaymentRequest['payment']['mode'] = $this->paymentMode;

        if (empty($this->settings['primary_contracts'])) {
            return [];
        } else {
            $mainContract = reset($this->settings['primary_contracts']);
        }
        $doWebPaymentRequest['payment']['contractNumber'] = $mainContract;

        // ORDER

        $doWebPaymentRequest['order']['ref'] = $this->cleanSubstr($order->get_id(), 0, 50);
        $doWebPaymentRequest['order']['country'] = $order->get_billing_country();
        $doWebPaymentRequest['order']['taxes'] = round(($order->get_total_tax() - $order->get_shipping_tax()) * 100);
        $doWebPaymentRequest['order']['amount'] = $doWebPaymentRequest['payment']['amount'];
        $doWebPaymentRequest['order']['date'] = date(self::PAYLINE_DATE_FORMAT);
        $doWebPaymentRequest['order']['currency'] = $doWebPaymentRequest['payment']['currency'];
        $doWebPaymentRequest['order']['deliveryCharge'] = round(($order->get_shipping_total() + $order->get_shipping_tax()) * 100);
        $doWebPaymentRequest['order']['deliveryMode'] = 1;

        // BUYER
        $doWebPaymentRequest['buyer']['title'] = self::DEFAULT_USER_TITLE ;

        $doWebPaymentRequest['buyer']['lastName'] = $this->cleanSubstr($order->get_billing_last_name(), 0, 100);
        $doWebPaymentRequest['buyer']['firstName'] = $this->cleanSubstr($order->get_billing_first_name(), 0, 100);
        $doWebPaymentRequest['buyer']['customerId'] = $order->get_user_id() ? $this->cleanSubstr($order->get_user_id(), 0, 50) : 0;
        $billingEmail = !empty($order->get_billing_email()) ? $order->get_billing_email() : WC()->customer->get_billing_email();
        $doWebPaymentRequest['buyer']['email'] = $this->cleanSubstr($billingEmail, 0, 150);
        $doWebPaymentRequest['buyer']['ip'] = $_SERVER['REMOTE_ADDR'];
        $doWebPaymentRequest['buyer']['mobilePhone'] = $this->cleanSubstr(preg_replace("/[^0-9.]/", '', $order->get_billing_phone()), 0, 15);
        if(!empty($this->settings['wallet']) && $this->settings['wallet'] == 'yes' && is_user_logged_in() && $order->get_user_id() > 0){
	        $doWebPaymentRequest['buyer']['walletId'] = $this->encryptWalletId($order->get_user_id());
        }

        // BILLING ADDRESS
        $doWebPaymentRequest['billingAddress']['title'] = self::DEFAULT_USER_TITLE ;
        $doWebPaymentRequest['billingAddress']['name'] = $order->get_billing_first_name() . " " . $order->get_billing_last_name();
        if ($order->get_billing_company() != null && strlen($order->get_billing_company()) > 0) {
            $doWebPaymentRequest['billingAddress']['name'] .= ' (' . $order->get_billing_company() . ')';
        }
        $doWebPaymentRequest['billingAddress']['name'] = $this->cleanSubstr($doWebPaymentRequest['billingAddress']['name'], 0, 100);
        $doWebPaymentRequest['billingAddress']['firstName'] = $this->cleanSubstr($order->get_billing_first_name(), 0, 100);
        $doWebPaymentRequest['billingAddress']['lastName'] = $this->cleanSubstr($order->get_billing_last_name(), 0, 100);
        $doWebPaymentRequest['billingAddress']['street1'] = $this->cleanSubstr($order->get_billing_address_1(), 0, 100);
        $doWebPaymentRequest['billingAddress']['street2'] = $this->cleanSubstr($order->get_billing_address_2(), 0, 100);
        $doWebPaymentRequest['billingAddress']['cityName'] = $this->cleanSubstr($order->get_billing_city(), 0, 40);
        $doWebPaymentRequest['billingAddress']['zipCode'] = $this->cleanSubstr($order->get_billing_postcode(), 0, 20);
        $doWebPaymentRequest['billingAddress']['country'] = $order->get_billing_country();
        $doWebPaymentRequest['billingAddress']['phoneType'] = 1;
        $doWebPaymentRequest['billingAddress']['phone'] = $this->cleanSubstr(preg_replace("/[^0-9.]/", '', $order->get_billing_phone()), 0, 15);

        // SHIPPING ADDRESS
        $doWebPaymentRequest['shippingAddress']['name'] = $order->get_shipping_first_name() . " " . $order->get_shipping_last_name();
        if ($order->get_shipping_company() != null && strlen($order->get_shipping_company()) > 0) {
            $doWebPaymentRequest['shippingAddress']['name'] .= ' (' . $order->get_shipping_company() . ')';
        }

        $doWebPaymentRequest['shippingAddress']['name'] = $this->cleanSubstr($doWebPaymentRequest['shippingAddress']['name'], 0, 100);
        $doWebPaymentRequest['shippingAddress']['firstName'] = $this->cleanSubstr($order->get_shipping_first_name()?: $order->get_billing_first_name(), 0, 100);
        $doWebPaymentRequest['shippingAddress']['lastName'] = $this->cleanSubstr($order->get_shipping_last_name()?: $order->get_billing_last_name(), 0, 100);
        $doWebPaymentRequest['shippingAddress']['street1'] = $this->cleanSubstr($order->get_shipping_address_1()?: $order->get_billing_address_1(), 0, 100);
        $doWebPaymentRequest['shippingAddress']['street2'] = $this->cleanSubstr($order->get_shipping_address_2()?: $order->get_billing_address_2(), 0, 100);
        $doWebPaymentRequest['shippingAddress']['cityName'] = $this->cleanSubstr($order->get_shipping_city()?: $order->get_billing_city(), 0, 40);
        $doWebPaymentRequest['shippingAddress']['zipCode'] = $this->cleanSubstr($order->get_shipping_postcode()?: $order->get_billing_postcode(), 0, 20);
        $doWebPaymentRequest['shippingAddress']['country'] = $order->get_shipping_country()?: $order->get_billing_country();
        $doWebPaymentRequest['shippingAddress']['phone'] = $this->cleanSubstr(preg_replace("/[^0-9.]/", '', $order->get_shipping_phone()), 0, 15);
	    $doWebPaymentRequest['shippingAddress']['phoneType'] = 1;

        $totalOrderLines = 0;
        // ORDER DETAILS
        $items = $order->get_items();
        /** @var WC_Order_Item_Product $item */
	    foreach ($items as $item) {
            $orderLine = array(
                'ref' => $this->cleanSubstr($item['name'], 0, 50),
                'price' => round(round(($item->get_subtotal() + $item->get_subtotal_tax())/$item['qty'],2) * 100),
                'quantity' => (int)$item['qty'],
                'comment' => (string)$item['name'],
                'taxRate' => ($item['total'] > 0)?round(($item['total_tax'] / $item['total']) * 100 * 100): 0
            );
            $this->paylineSDK()->addOrderDetail($orderLine);

            $totalOrderLines+=$orderLine['price'] * $orderLine['quantity'];
        }

        //Allow Klarna with cart discount
        //Round $adjustment to avoid php biais as 4.5474735088646E-13 ( https://github.com/Monext/monext-woocommerce/issues/5 )
        $adjustment = round($doWebPaymentRequest['order']['amount'] - $totalOrderLines - $doWebPaymentRequest['order']['deliveryCharge']);
        if ($adjustment) {
            $prixHT = ($order->get_total() - $order->get_total_tax() - $order->get_shipping_total());
            $taxRate = ($prixHT > 0) ? round(($order->get_cart_tax() / $prixHT) * 100 * 100) : 0;

		    $this->paylineSDK()->addOrderDetail(array(
			    'ref' => 'CART_DISCOUNT',
			    'price' => $adjustment,
			    'quantity' => 1,
			    'comment' => 'Cart amount adjustment',
			    'category' =>  'main',
			    'taxRate' => $taxRate
		    ));
	    }

        $this->paylineSDK()->addPrivateData(array('key' => 'OrderSaleChannel', 'value' => 'DESKTOP'));
        if($this->settings['smartdisplay_parameter'] != null){
            $this->paylineSDK()->addPrivateData(array('key' => 'display.rule.param', 'value' => $this->settings['smartdisplay_parameter']));
        }

        // TRANSACTION OPTIONS
        $doWebPaymentRequest['notificationURL'] = $this->get_request_url('notification');
        $doWebPaymentRequest['returnURL'] = $this->get_request_url('return');
        $doWebPaymentRequest['cancelURL'] = $this->get_request_url('cancel');

        $doWebPaymentRequest['languageCode'] = $this->settings['language'];
        $doWebPaymentRequest['customPaymentPageCode'] = $this->settings['custom_page_code'];

        // CONTRACTS
        if(!empty($this->settings['primary_contracts'])) {
            $doWebPaymentRequest['contracts'] = $this->settings['primary_contracts'];
            $doWebPaymentRequest['secondContracts'] = $this->settings['primary_contracts'];
        }

        // Callback payline_do_web_payment_request_params
        $requestParams = apply_filters('payline_do_web_payment_request_params', $doWebPaymentRequest, $order);

        return $requestParams;
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
     * Todo: TO DELETE
     * @param int $order_id
     */
    function generate_payline_form($order_id) {
    $ctaButton = $this->getConfigValueIfExists('widget_settings_cta_label');
    $textUnderCta = $this->getConfigValueIfExists('widget_settings_text_under_cta');
    $widgetCustomCss = $this->generateWidgetCustomCss();

    if (!empty($widgetCustomCss)) {
        echo '<style type="text/css">' . $widgetCustomCss . '</style>';
    }

        echo '<script type="text/javascript">

const ctaLabel = "' . (!empty($ctaButton) ? strip_tags($ctaButton) : '') . '";
const textUnderCta = "' . (!empty($textUnderCta) ? strip_tags($textUnderCta) : '') . '";

window.eventDidshowstate = function (e) {
    if ( e.state && e.state === "PAYMENT_METHODS_LIST" ) {
        if (ctaLabel != "") {
            jQuery(".PaylineWidget .pl-pay-btn, .PaylineWidget .pl-btn").html(ctaLabel.replace("{{amount}}", Payline.Api.getContextInfo("PaylineFormattedAmount")));
        }

        if (textUnderCta) {
            jQuery(".PaylineWidget .pl-pay-btn, .PaylineWidget .pl-btn").after(jQuery("<p>").html(textUnderCta).addClass("pl-text-under-cta"))
        }
    }
}
hideReceivedContext = function() {
    jQuery(".storefront-breadcrumb").hide();
    jQuery(".order_details").hide();
    jQuery("h1.entry-title").html("'. __('Payment', 'payline') .'")
    jQuery("#site-header-cart").hide();
};

eventFinalstatehasbeenreached= function (e) {
    if ( e.state === "PAYMENT_SUCCESS" ) {
        //--> Redirect to success page
        //--> Ticket is hidden by CSS
        //--> Wait for DOM update to simulate a click on the ticket confirmation button
        window.setTimeout(() => {
            const ticketConfirmationButton = document.getElementById("pl-ticket-default-ticket_btn");
            if ( ticketConfirmationButton ) {
                ticketConfirmationButton.click();
            }
        }, 0);
    }
};

cancelPaylinePayment = function ()
{
    Payline . Api . endToken(); // end the token s life
    window . location . href = Payline . Api . getCancelAndReturnUrls() . cancelUrl; // redirect the user to cancelUrl
}
            </script>';

        $order = wc_get_order($order_id);


        $requestParams = $this->getWebPaymentRequest($order);

        $this->debug($requestParams, array(__METHOD__));

        $token = $this->getCachedDWPDataForOrder($order, 'token', true);

        if ( preg_match('/inshop-(.*)/', $this->settings['widget_integration'],$match) ) {
            $widgetJS  =  PaylineSDK::PROD_WDGT_JS;
            $widgetCSS  =  PaylineSDK::PROD_WDGT_CSS;
            if ($this->settings['environment'] ==PaylineSDK::ENV_HOMO) {
                $widgetJS  =  PaylineSDK::HOMO_WDGT_JS;
                $widgetCSS  =  PaylineSDK::HOMO_WDGT_CSS;
            }
            printf( '<script src="%s"></script>', $widgetJS);
            printf('<link href="%s" rel="stylesheet" />', $widgetCSS);


            // Prevent to send the request again on refresh.
            if ( !empty( $_GET['paylinetoken'] ) ) {
                $token = $_GET['paylinetoken'];
            } elseif ( !$token ) {
                $result = $this->paylineSDK()->doWebPayment( $requestParams );
                $this->debug($result, array(__METHOD__));
                do_action( 'payline_after_do_web_payment', $result, $this );

                if ( $result['result']['code'] === '00000' ) {
                    $this->updateTokenForOrder($order, $result);
                    $token = $result['token'];
                } else {
                    echo '<div class="PaylineWidget"><p class="pl-message pl-message-error">' . sprintf( __( 'An error occured while displaying the payment form (error code %s : %s). Please contact us.', 'payline' ), $result['result']['code'], $result['result']['longMessage'] ) . '</p></div>';
                    exit;
                }
            }

            printf(
                '<div id="PaylineWidget" data-token="%s" data-template="%s" data-embeddedredirectionallowed="true" data-event-didshowstate="eventDidshowstate" data-event-finalstatehasbeenreached="eventFinalstatehasbeenreached"></div>',
                $token,
                $match[1]
            );

            echo '<script type="text/javascript">
            jQuery(document).ready(function($){
                hideReceivedContext();
            });
            </script>
            <p></p><button onclick="javascript:cancelPaylinePayment()">' .
                __('Cancel payment', 'payline') .
                '</button></p>';

            exit;
        } else {
            // EXECUTE
            $result = $this->paylineSDK()->doWebPayment( $requestParams );

            $this->debug($result, array(__METHOD__));

            // Add payline_after_do_web_payment for widget
            do_action( 'payline_after_do_web_payment', $result, $this );

            if ( $result['result']['code'] === '00000' ) {
                // save association between order and payment session token so that the callback can check that the response is valid.
                //update_option( $tokenOptionKey, $result['token'] );
                $this->updateTokenForOrder($order, $result);


                header( 'Location: ' . $result['redirectURL'] );

                exit;
            } else {
                $message = sprintf( __( 'You can\'t be redirected to payment page (error code %s : %s). Please contact us.', 'payline' ), $result['result']['code'],  $result['result']['longMessage']);
                wp_redirect($this->get_error_payment_url($order, $message));
                die();
            }
        }
    }


    /**
     * @return void
     */
    protected function payline_callback_cancel($message='') {

	    $res = $this->paylineSDK()->getWebPaymentDetails(array('token'=>$_GET['paylinetoken'],'version'=>$this->APIVersion));
	    $order = wc_get_order($res['order']['ref']);
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
	        $noticeMessage = __( 'Payment was canceled.', 'payline' );
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
     * @return void
     */
    protected function payline_callback_webhook() {

        if(empty($_GET['transactionId'])) {
            return false;
        }

        $res = $this->paylineSDK()->getTransactionDetails(array('transactionId'=> $_GET['transactionId'],
            //'orderRef'=>$_GET['orderRef'],
            'version'=>$this->APIVersion));

        if(empty($res['order']['ref'])) {
            return false;
        }

        $order = wc_get_order($res['order']['ref']);
        if($order && $order->get_id()) {
            $this->paylineManageReturn($order, $res);
            die('Success webhook');
        }

        die('Error webhook');
    }

    /**
     *
     * @return void
     */
    function payline_callback() {
        if(!isset($_GET['url_type']) || !in_array($_GET['url_type'], $this->urlTypes)) {
            $this->payline_callback_cancel('Unknow url type.');
            return;
        }

        $urlType = $_GET['url_type'];
        if($urlType=='cancel'){
            $this->payline_callback_cancel('Payment was canceled.');
        }

        if($urlType=='webhook'){
            $this->payline_callback_webhook();
        }

        if(isset($_GET['order_id'])){
            //Todo: See how to delete
            $this->generate_payline_form($_GET['order_id']);
            exit;
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


        $res = $this->paylineSDK()->getWebPaymentDetails(array('token'=>$token,'version'=>$this->APIVersion));
        $this->debug($res, array(__METHOD__));

        if($res['result']['code'] == PaylineSDK::ERR_CODE) {
            $this->paylineSDK()->getLogger()->error('Unable to call Payline for token '.$token);
            wp_redirect(wc_get_cart_url());
            die();
        } else {
            $orderId = $res['order']['ref'];
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

            if($urlType=='notification') {
                //Nothing to do on notification if a transaction already exists for payline CPT
                $transactionId = $order->get_transaction_id();
	            $paymentMethod = $order->get_payment_method();
	            if ($transactionId && $paymentMethod == 'payline_cpt') {
		            die();
	            }

	            if (!empty($paymentMethod) && $paymentMethod != 'payline_cpt'){
		            die();
	            }
            }

            $expectedToken = $this->getCachedDWPDataForOrder($order, 'token');
            if($expectedToken != $token){
                $message = sprintf(__('Token %s does not match expected %s for order %s', 'payline'), wc_clean($token), $expectedToken, $orderId);
                $this->paylineSDK()->getLogger()->error($message);
                $order->add_order_note($message);
                wp_redirect(wc_get_cart_url());
                die($message);
            }

            do_action( $this->id . '_payment_callback', $res, $order );

            $message = $this->paylineManageReturn($order, $res);

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

            $status = strtolower($res['result']['shortMessage']);
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
        $paymentParams['currency'] = $this->_currencies[$order->get_currency()];
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
                sprintf( __( 'Refunded %1$s - Refund ID: %2$s', 'payline' ), $amount, $res['transaction']['id'] )
            );
            return true;
        } else {
            return new WP_Error( 'error',$res['result']['longMessage'] );
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
            $templateData['reset_message'] = sprintf(__( 'Your %s configuration parameters are reset.', 'payline'), 'Payline');
        }

        //--> Display errors messages
        if (!extension_loaded('soap')) {
            $this->callGetMerchantSettings = false;
            $dispErrors[] = sprintf(__( 'The SOAP extension is not enabled in your PHP installation and is required', 'payline'));
        }

        if(array_key_exists('merchant_id', $this->settings) && ($this->settings['merchant_id'] == null || strlen($this->settings['merchant_id']) == 0)){
            $this->callGetMerchantSettings = false;
            $dispErrors[] = sprintf(__( '%s is mandatory', 'payline'), __('Merchant ID', 'payline' ));
        }
        if(array_key_exists('access_key', $this->settings) && ($this->settings['access_key'] == null || strlen($this->settings['access_key']) == 0)){
            $this->callGetMerchantSettings = false;
            $dispErrors[] = sprintf(__( '%s is mandatory', 'payline'), __('Access Key', 'payline' ));
        }

        if(array_key_exists('primary_contracts', $this->settings) && ($this->settings['primary_contracts'] == null || empty($this->settings['primary_contracts']))){
            $this->callGetMerchantSettings = false;
            $dispErrors[] = __( 'Please select at least one contract in contracts tab', 'payline');
        }

        if(!isset($this->settings['pos']) || empty($this->settings['pos'])){
            $this->callGetMerchantSettings = false;
            $dispErrors[] = sprintf(__( '%s is mandatory', 'payline'), __('Point of Sales', 'payline' ));
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
                if(strcmp(WC_Gateway_Payline_CPT::BAD_CONNECT_SETTINGS_ERR, $res['result']['longMessage']) == 0){
                    $this->disp_errors .= "<p>".sprintf(__( 'Unable to connect to Monext, check your %s', 'payline'), __('MONEXT GATEWAY ACCESS', 'payline' ))."</p>";
                }elseif(strcmp(WC_Gateway_Payline_CPT::BAD_PROXY_SETTINGS_ERR, $res['result']['longMessage']) == 0){
                    $this->disp_errors .= "<p>".sprintf(__( 'Unable to connect to Monext, check your %s', 'payline'), __('PROXY SETTINGS', 'payline' ))."</p>";
                }else{
                    $dispErrors[] = "<p>".sprintf(__( 'Unable to connect to Monext (code %s : %s)', 'payline'), $res['result']['code'], $res['result']['longMessage']);
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
            $options[$cleanStatusKey]= sprintf(__('When order status is "%s"', 'payline'), __($status, 'woocommerce'));
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
        $associatedTransactions = $txDetailsRes['associatedTransactionsList']['associatedTransactions'];
        if(is_array(reset($associatedTransactions))) {
            foreach ($txDetailsRes['associatedTransactionsList']['associatedTransactions'] as $transaction) {
                if ($transaction['type'] == 'CAPTURE'){
                    return;
                }
            }
        }

        $paymentParams = array();
        $paymentParams['amount'] = round($order->get_total() * 100);
        $paymentParams['currency'] = $this->_currencies[$order->get_currency()];
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
                sprintf( 'Capture error :"%1$s"', $res['result']['longMessage'] )
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
        $contractsList = $this->getContractsForCurrentPos();
        $walletId = $this->encryptWalletId(get_current_user_id());
        $customer = new WC_Customer( get_current_user_id() );

        foreach ($contractsList as $contract) {
            if(!empty($contract['wallet'])) {
                $contracts[] = $contract['contractNumber'];
            }
        }

        $params = array(
            'version' => $this->APIVersion,
            'contractNumber' => current($contracts),
            'walletId' => $walletId,
            'contracts' => $contracts,
            'buyer' => array(
                'lastName' => $this->cleanSubstr($customer->get_last_name(), 0, 100),
                'firstName' => $this->cleanSubstr($customer->get_first_name(), 0, 100),
                'walletId' => $walletId,
            ),
            'updatePersonalDetails' => 0,

            'notificationURL' => $this->get_request_url('notification'),
            'returnURL' => $this->get_request_url('return'),
            'cancelURL' => $this->get_request_url('cancel'),
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

    protected function getContractsByPosLabel($posLabel, $enabledContracts = array(), $useCache = false)
    {
        $posList = WC_Payline_SDK::getPointOfSales();
        foreach ($posList as $pos) {
            if (trim($pos['label']) == $posLabel && isset($pos['contracts']) && is_array($pos['contracts']) && isset($pos['contracts']['contract']) && is_array($pos['contracts']['contract'])) {
                // Retrieve contracts and sort them
                $finalContractsList = array();
                $disabledContracts = array();
                $contractsList = $pos['contracts']['contract'];

                $firstKey = key($contractsList);
                if(!is_numeric($firstKey) && isset($contractsList['contractNumber'])) {
                    $contractsList = [$contractsList];
                }

                // Assign "enabled attriburte
                foreach ($contractsList as &$contract) {
                    $contractId = $contract['cardType'] . '-' . $contract['contractNumber'];
                    $contract['enabled'] = (in_array($contractId, $enabledContracts));
                    $contract['wallet'] = (in_array($contract['cardType'], ['AMEX', 'CB']));
                    if (!$contract['enabled']) {
                        $disabledContracts[] = $contract;
                    }
                }
                // Sort contracts, enabled first
                foreach ($enabledContracts as $enabledContractId) {
                    foreach ($contractsList as &$contract) {
                        $contractId = $contract['cardType'] . '-' . $contract['contractNumber'];
                        if ($contractId == $enabledContractId) {
                            $finalContractsList[] = $contract;
                            break;
                        }
                    }
                }

                $finalContractsList = array_merge($finalContractsList, $disabledContracts);

                return $finalContractsList;
            }
        }

        return array();
    }
}
