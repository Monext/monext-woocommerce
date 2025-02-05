<?php

use Monolog\Logger;
use Payline\PaylineSDK;
use Automattic\WooCommerce\Utilities\LoggingUtil;


abstract class WC_Abstract_Payline extends WC_Payment_Gateway {

    const BAD_CONNECT_SETTINGS_ERR = "Unauthorized";
    const BAD_PROXY_SETTINGS_ERR = "Could not connect to host";

    const PAYLINE_DATE_FORMAT = 'd/m/Y H:i';

    /** @var Payline\PaylineSDK $SDK */
    protected $SDK;

    protected $urlTypes = ['notification', 'return', 'cancel', 'webhook'];

    protected $paymentMode = '';

    protected $extensionVersion = '1.5.2';

    /** @var int Payline internal API version */
    protected $APIVersion = 26;

    protected $callGetMerchantSettings = true;

    protected $posData;
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

        $this->icon = apply_filters('woocommerce_payline_icon', WCPAYLINE_PLUGIN_URL . 'assets/images/payline_front.png');
        $this->has_fields = false;
        $this->supports           = array('products',
            'refunds'
        );

        $this->order_button_text  = __( 'Pay via Payline', 'payline' );

        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // Define user set variables
        $this->title = $this->settings['title'];
        $this->description = $this->settings['description'];
        $this->testmode = (isset($this->settings['ctx_mode']) && $this->settings['ctx_mode'] === 'TEST');
        $this->debugEnable = (isset($this->settings['debug']) && $this->settings['debug'] == 'yes') ? true : false;

        // The module settings page URL
        $link = add_query_arg('page', 'wc-settings', admin_url('admin.php'));
        $link = add_query_arg('tab', 'checkout', $link);
        $link = add_query_arg('section', 'payline', $link);
        $this->admin_link = $link;

        // Actions
        $this->add_payline_common_actions();

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

        // Generate form action
        add_action('woocommerce_receipt_' . $this->id, array($this, 'generate_payline_form'));

        // Update admin form action
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // Return from payment platform action
        add_action('woocommerce_api_wc_gateway_' . $this->id, array($this, 'payline_callback'));
    }

    function get_icon() {
        $icon = $this->icon ? '<img style="width: 85px;" src="' . WC_HTTPS::force_https_url( $this->icon ) . '" alt="' . $this->title . '" />' : '';
        return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
    }




    function init_form_fields() {

        $this->form_fields = array();

        /*
         * Base settings
         */
        $this->form_fields['base_settings'] = array(
            'title' => __('BASE SETTINGS', 'payline' ),
            'type' => 'title'
        );
        $this->form_fields['enabled'] = array(
            'title' => __('Status', 'payline'),
            'type' => 'checkbox',
            'label' => sprintf(__('Enable %s', 'payline'), $this->method_title),
            'default' => 'yes'
        );
        $this->form_fields['title'] = array(
            'title' => __('Title', 'payline'),
            'type' => 'text',
            'description' => __('This controls the title which the user sees during checkout.', 'payline'),
            'default' => $this->method_title
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
        $this->form_fields['debug'] = array(
            'title' => __( 'Debug logging', 'payline' ),
            'type' => 'checkbox',
            'label' => __( 'Enable', 'payline' ),
            'default' => 'no'
        );

        /*
         * Connexion
         */
        $this->form_fields['payline_gateway_access'] = array(
            'title' => __( 'PAYLINE GATEWAY ACCESS', 'payline' ),
            'type' => 'title'
        );
        $this->form_fields['merchant_id'] = array(
            'title' => __('Merchant ID', 'payline'),
            'type' => 'text',
            'default' => '',
            'description' => __('Your Payline account identifier', 'payline')
        );
        $this->form_fields['access_key'] = array(
            'title' => __('Access key', 'payline'),
            'type' => 'text',
            'default' => '',
            'description' => sprintf(__( 'Password used to call %s web services (available in the %s administration center)', 'payline'), 'Payline', 'Payline')
        );
        $this->form_fields['environment'] = array(
            'title' => __('Target environment', 'payline'),
            'type' => 'select',
            'default' => 'Homologation',
            'options' => array(
                PaylineSDK::ENV_HOMO => __('Homologation', 'payline'),
                PaylineSDK::ENV_PROD => __('Production', 'payline')
            ),
            'description' => __('Payline destination environement of your requests', 'payline')
        );

        /*
         * Proxy Settings
         */
        $this->form_fields['proxy_settings'] = array(
            'title' => __( 'PROXY SETTINGS', 'payline' ),
            'type' => 'title'
        );
        $this->form_fields['proxy_host'] = array(
            'title' => __('Host', 'payline'),
            'type' => 'text',
        );
        $this->form_fields['proxy_port'] = array(
            'title' => __('Port', 'payline'),
            'type' => 'text',
        );
        $this->form_fields['proxy_login'] = array(
            'title' => __('Login', 'payline'),
            'type' => 'text',
        );
        $this->form_fields['proxy_password'] = array(
            'title' => __('Password', 'payline'),
            'type' => 'text',
        );

        /*
         * Payment Settings
         */
        $this->form_fields['payment_settings'] = array(
            'title' => __( 'PAYMENT SETTINGS', 'payline' ),
            'type' => 'title'
        );
        $this->form_fields['language'] = array(
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
        );
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
            'description' => __( 'Integration mode of the payment widget in the shop. Contact payline support for more details', 'payline' )
        );

        $this->form_fields['custom_page_code'] = array(
            'title' => __('Custom page code', 'payline'),
            'type' => 'text',
            'description' => __('In redirection mode, fill the code of payment page customization created in Payline Administration Center', 'payline')
        );
        $this->form_fields['main_contract'] = array(
            'title' => __('Main contract number', 'payline'),
            'type' => 'text',
            'description' => __('Contract number that determines the point of sale used in Payline', 'payline')
        );
        $this->form_fields['primary_contracts'] = array(
            'title' => __('Primary contracts', 'payline'),
            'type' => 'text',
            'description' => __('Contracts displayed on web payment page - step 1. Values must be separated by ;', 'payline')
        );
        $this->form_fields['secondary_contracts'] = array(
            'title' => __('Secondary contracts', 'payline'),
            'type' => 'text',
            'description' => __('Contracts displayed for payment retry. Values must be separated by ;', 'payline')
        );
    }



    public function admin_options() {
        global $woocommerce;

        if(key_exists('reset', $_REQUEST) && $_REQUEST['reset'] == 'true') {
            do_action($this->id . '_reset_admin_options');
        }
        ?>

        <table border="0">
            <tr>
                <td width="40%">
                    <p>
                        <img src="<?php echo WCPAYLINE_PLUGIN_URL . 'assets/images/payline.png'?>" alt="Payline logo" />
                    </p>
                </td>
                <td width="100%">
                    <p>
                        <?php echo "Payline extension v".$this->extensionVersion;?><br/>
                        Developed by <a href="https://www.monext.fr/retail" target="#">Monext</a> for WooCommerce<br/>
                        For any question please contact Payline support<br/>
                    </p>
                </td>
            </tr>
        </table>


        <?php
        if (!empty($woocommerce->session->payline_reset)){
            unset($woocommerce->session->payline_reset);
            echo "<div class='inline updated'><p>".sprintf(__( 'Your %s configuration parameters are reset.', 'payline'), 'Payline')."</p></div>";
        }
        $this->disp_errors = "";

        if (!extension_loaded('soap')) {
            $this->callGetMerchantSettings = false;
            $this->disp_errors .= "<p>".sprintf(__( 'The SOAP extension is not enabled in your PHP installation and is required', 'payline'))."</p>";
        }

        if($this->settings['merchant_id'] == null || strlen($this->settings['merchant_id']) == 0){
            $this->callGetMerchantSettings = false;
            $this->disp_errors .= "<p>".sprintf(__( '%s is mandatory', 'payline'), __('Merchant ID', 'payline' ))."</p>";
        }
        if($this->settings['access_key'] == null || strlen($this->settings['access_key']) == 0){
            $this->callGetMerchantSettings = false;
            $this->disp_errors .= "<p>".sprintf(__( '%s is mandatory', 'payline'), __('Access Key', 'payline' ))."</p>";
        }

        if($this->settings['main_contract'] == null || strlen($this->settings['main_contract']) == 0){
            $this->callGetMerchantSettings = false;
            $this->disp_errors .= "<p>".sprintf(__( '%s is mandatory', 'payline'), __('Main contract number', 'payline' ))."</p>";
        }

        if($this->callGetMerchantSettings){

            $this->SDK = $this->getSDK();
            $res = $this->SDK->getEncryptionKey([]);
            if($res['result']['code'] == '00000'){
                echo "<div class='inline updated'>";
                echo "<p>".__( 'Your settings is correct, connexion with Payline is established', 'payline')."</p>";
                if($this->settings['environment'] == PaylineSDK::ENV_HOMO){
                    echo "<p>".__( 'You are in homologation mode, payments are simulated !', 'payline')."<p>";
                }
                echo "</div>";
            }else{
                if(strcmp(WC_Gateway_Payline::BAD_CONNECT_SETTINGS_ERR, $res['result']['longMessage']) == 0){
                    $this->disp_errors .= "<p>".sprintf(__( 'Unable to connect to Payline, check your %s', 'payline'), __('PAYLINE GATEWAY ACCESS', 'payline' ))."</p>";
                }elseif(strcmp(WC_Gateway_Payline::BAD_PROXY_SETTINGS_ERR, $res['result']['longMessage']) == 0){
                    $this->disp_errors .= "<p>".sprintf(__( 'Unable to connect to Payline, check your %s', 'payline'), __('PROXY SETTINGS', 'payline' ))."</p>";
                }else{
                    $this->disp_errors .= "<p>".sprintf(__( 'Unable to connect to Payline (code %s : %s)', 'payline'), $res['result']['code'], $res['result']['longMessage'])."</p>";
                }
            }
        }

        if($this->disp_errors != ""){
            echo "<div class='inline error'>$this->disp_errors</div>";
        }

        ?>

        <table class="form-table">
            <?php
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
            ?>
        </table>

        <?php
        // Reset settings URL
        $resetLink = add_query_arg('reset', 'true', $this->admin_link);
        $resetLink = wp_nonce_url($resetLink, 'payline_reset');
        ?>

        <a href="<?php echo $resetLink; ?>"><?php _e('Reset configuration', 'payline');?></a>

        <?php
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
        $newValue = $_POST[$this->plugin_id . $this->id . '_' . $key];
        if(isset($newValue) && is_array($newValue) && in_array('', $newValue)) {
            return array('');
        } else {
            return parent::validate_multiselect_field ($key, $value);
        }
    }

    function is_available() {
        return parent::is_available();
    }

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

		$this->SDK = $this->getSDK();
        $redirectURL = $this->getCachedDWPDataForOrder($order, 'redirectURL', true);


        $errorCode = $this->SDK::ERR_CODE;
        $errorMsg  = $this->SDK::ERR_SHORT_MESSAGE;

        if ( !$redirectURL ) {
            $requestParams = $this->getWebPaymentRequest($order);
            $result = $this->SDK->doWebPayment( $requestParams );
            $this->debug($result, array(__METHOD__));
            do_action( 'payline_after_do_web_payment', $result, $this );

            if ( $result['result']['code'] === '00000' ) {
                $this->updateTokenForOrder($order, $result);
                return $result['redirectURL'];
            } else {
                $errorCode = $result['result']['code'];
                $errorMsg  = $result['result']['longMessage'];
            }
        }

        $message = sprintf( __( 'You can\'t be redirected to payment page (error code %s : %s). Please contact us.', 'payline' ),  $errorCode, $errorMsg);
		return $this->get_error_payment_url($order, $message);
	}

    /**
     * @return PaylineSDK
     */
    public function getSDK()
    {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

	    $pathLog = trailingslashit( dirname( WC_Log_Handler_File::get_log_file_path( 'payline' ) ) ) . trailingslashit( 'payline' );
        if (!is_dir($pathLog)) {
            @mkdir($pathLog, 0777, true);
        }
        $usedBy = [];
        $usedBy[] = 'WP '.get_bloginfo('version');

        $woocommerceinfo = get_plugins('/woocommerce');
        $usedBy[] = (!empty($woocommerceinfo)) ? current($woocommerceinfo)['Name'] .' '. current($woocommerceinfo)['Version'] : 'wooComm';
        $usedBy[] = 'v'.$this->extensionVersion;

        $SDK = new PaylineSDK(
            $this->settings['merchant_id'],
            $this->settings['access_key'],
            $this->settings['proxy_host'],
            $this->settings['proxy_port'],
            $this->settings['proxy_login'],
            $this->settings['proxy_password'],
            $this->settings['environment'],
            $pathLog,
            ($this->debugEnable) ? Logger::DEBUG : Logger::INFO
        );
        $SDK->usedBy(implode(' - ',$usedBy));

        return $SDK;
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


    protected function getCachedDWPDataForOrder(WC_Order $order, $key= null, $available = false) {
        $tokenDecoded = $this->getArrayTokenForOrder($order);

        $token = $tokenDecoded['token'] ?? null;
        if($token && !empty($tokenDecoded['date'])) {
            $tokenAge = floor((strtotime(date(self::PAYLINE_DATE_FORMAT))-strtotime($tokenDecoded['date']))/60);
            if($available && $tokenAge>12) {
                $tokenDecoded = [];
            }
        }

        if($key) {
            return $tokenDecoded[$key] ?? null;
        }
        return $tokenDecoded;
    }

    protected function updateTokenForOrder(WC_Order $order, array $dwpResult) {
        unset($dwpResult['result']);

        $tokenOptionKey = $this->getTokenOptionKey($order);
        if(!empty( $dwpResult['token'])) {
            $dwpResult['date'] = date(self::PAYLINE_DATE_FORMAT);
        } else {
            $dwpResult['token'] = '';
        }

        if($tokenDecoded = $this->getArrayTokenForOrder($order)) {
            $dwpResult['history'] = $tokenDecoded['history'] ?? [];
            $dwpResult['history'][] = ['token'=>$tokenDecoded['token'], 'date'=>$tokenDecoded['date']];
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
        $doWebPaymentRequest = array();
        $doWebPaymentRequest['version'] = $this->APIVersion;
        $doWebPaymentRequest['payment']['amount'] = round($order->get_total() * 100);
        $doWebPaymentRequest['payment']['currency'] = $this->_currencies[$order->get_currency()];
        $doWebPaymentRequest['payment']['action'] = $this->settings['payment_action'];
        $doWebPaymentRequest['payment']['mode'] = $this->paymentMode;
        $doWebPaymentRequest['payment']['contractNumber'] = $this->settings['main_contract'];

        // ORDER

        $doWebPaymentRequest['order']['ref'] = $this->cleanSubstr($order->get_id(), 0, 50);
        $doWebPaymentRequest['order']['country'] = $order->get_billing_country();
        $doWebPaymentRequest['order']['taxes'] = round(($order->get_total_tax() - $order->get_shipping_tax()) * 100);
        $doWebPaymentRequest['order']['amount'] = $doWebPaymentRequest['payment']['amount'];
        $doWebPaymentRequest['order']['date'] = date(self::PAYLINE_DATE_FORMAT);
        $doWebPaymentRequest['order']['currency'] = $doWebPaymentRequest['payment']['currency'];
        $doWebPaymentRequest['order']['deliveryCharge'] = round(($order->get_shipping_total() + $order->get_shipping_tax()) * 100);
        //$doWebPaymentRequest['order']['discountAmount'] = round(($order->get_discount_total() + $order->get_discount_tax()) * 100);

        // BUYER
        // TODO: Default value
        $doWebPaymentRequest['buyer']['title'] = '4'; //M. / Monsieur

        $doWebPaymentRequest['buyer']['lastName'] = $this->cleanSubstr($order->get_billing_last_name(), 0, 100);
        $doWebPaymentRequest['buyer']['firstName'] = $this->cleanSubstr($order->get_billing_first_name(), 0, 100);
        $doWebPaymentRequest['buyer']['customerId'] = $order->get_user_id() ? $this->cleanSubstr($order->get_user_id(), 0, 50) : 0;
        $doWebPaymentRequest['buyer']['email'] = $this->cleanSubstr($order->get_billing_email(), 0, 150);
        $doWebPaymentRequest['buyer']['ip'] = $_SERVER['REMOTE_ADDR'];
        $doWebPaymentRequest['buyer']['mobilePhone'] = $this->cleanSubstr(preg_replace("/[^0-9.]/", '', $order->get_billing_phone()), 0, 15);
        if($this->settings['wallet'] == 'yes'){
	        $doWebPaymentRequest['buyer']['walletId'] = $this->encryptWalletId($order->get_user_id());
        }

        // BILLING ADDRESS
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
        $doWebPaymentRequest['shippingAddress']['phone'] = '';

        $totalOrderLines = 0;
        // ORDER DETAILS
        $items = $order->get_items();
        /** @var WC_Order_Item_Product $item */
	    foreach ($items as $item) {
            $orderLine = array(
                'ref' => $this->cleanSubstr($item['name'], 0, 50),
                'price' => round(($item->get_subtotal() + $item->get_subtotal_tax())/$item['qty'],2) * 100,
                'quantity' => (int)$item['qty'],
                'comment' => (string)$item['name'],
                'taxRate' => round(($item['total_tax'] / $item['total']) * 100 * 100)
            );
            $this->SDK->addOrderDetail($orderLine);

            $totalOrderLines+=$orderLine['price'] * $orderLine['quantity'];
        }

        //Allow Klarna with cart discount
        //$adjustment = $doWebPaymentRequest['order']['amount'] - $totalOrderLines - $doWebPaymentRequest['order']['deliveryCharge'] - $doWebPaymentRequest['order']['discountAmount'];
        $adjustment = $doWebPaymentRequest['order']['amount'] - $totalOrderLines - $doWebPaymentRequest['order']['deliveryCharge'];
        if ($adjustment) {
            $prixHT = ($order->get_total() - $order->get_total_tax() - $order->get_shipping_total());
		    $taxRate = round(($order->get_cart_tax() / $prixHT) * 100 * 100);

		    $this->SDK->addOrderDetail(array(
			    'ref' => 'CART_DISCOUNT',
			    'price' => $adjustment,
			    'quantity' => 1,
			    'comment' => 'Cart amount adjustment',
			    'category' =>  'main',
			    'taxRate' => $taxRate
		    ));
	    }

        // TRANSACTION OPTIONS
        $doWebPaymentRequest['notificationURL'] = $this->get_request_url('notification');
        $doWebPaymentRequest['returnURL'] = $this->get_request_url('return');
        $doWebPaymentRequest['cancelURL'] = $this->get_request_url('cancel');

        $doWebPaymentRequest['languageCode'] = $this->settings['language'];
        $doWebPaymentRequest['customPaymentPageCode'] = $this->settings['custom_page_code'];

        // PRIMARY CONTRACTS
        if ($this->settings['primary_contracts'] != null && strlen($this->settings['primary_contracts']) > 0) {
            $contracts = explode(";", $this->settings['primary_contracts']);
            $doWebPaymentRequest['contracts'] = $contracts;
        }

        // SECONDARY CONTRACTS
        if ($this->settings['secondary_contracts'] != null && strlen($this->settings['secondary_contracts']) > 0) {
            $secondContracts = explode(";", $this->settings['secondary_contracts']);
            $doWebPaymentRequest['secondContracts'] = $secondContracts;
        }

        // Callback payline_do_web_payment_request_params
        $requestParams = apply_filters('payline_do_web_payment_request_params', $doWebPaymentRequest, $order);

        return $requestParams;
    }


    protected function get_request_url($urlType = '') {
        return add_query_arg(array('wc-api' => get_class($this), 'url_type'=>$urlType), home_url('/'));
    }



    /**
     * @param int $order_id
     */
    function generate_payline_form($order_id) {



        echo '<script type="text/javascript">
hideReceivedContext = function() {
    jQuery(".storefront-breadcrumb").hide();
    jQuery(".order_details").hide();
    jQuery("h1.entry-title").html("'. __('Payment', 'payline') .'")
    jQuery("#site-header-cart").hide();
};

cancelPaylinePayment = function ()
{
    Payline . Api . endToken(); // end the token s life
    window . location . href = Payline . Api . getCancelAndReturnUrls() . cancelUrl; // redirect the user to cancelUrl
}
            </script>';

        $order = wc_get_order($order_id);

        $this->SDK = $this->getSDK();

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
                $result = $this->SDK->doWebPayment( $requestParams );
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
                '<div id="PaylineWidget" data-token="%s" data-template="%s" data-embeddedredirectionallowed="true"></div>',
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
            $result = $this->SDK->doWebPayment( $requestParams );

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

	    $this->SDK = $this->getSDK();
	    $res = $this->SDK->getWebPaymentDetails(array('token'=>$_GET['paylinetoken'],'version'=>$this->APIVersion));
	    $order = wc_get_order($res['order']['ref']);

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

        $this->SDK = $this->getSDK();
        $res = $this->SDK->getTransactionDetails(array('transactionId'=> $_GET['transactionId'],
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

        $this->SDK = $this->getSDK();

        $res = $this->SDK->getWebPaymentDetails(array('token'=>$token,'version'=>$this->APIVersion));
        $this->debug($res, array(__METHOD__));

        if($res['result']['code'] == PaylineSDK::ERR_CODE) {
            $this->SDK->getLogger()->error('Unable to call Payline for token '.$token);
            wp_redirect(wc_get_cart_url());
            die();
        } else {
            $orderId = $res['order']['ref'];
            $order = wc_get_order($orderId);
            if(!$order) {
                wp_redirect(wc_get_cart_url());
                die();
            }

            if($urlType=='notification') {
                //Nothing to do on notification if a transaction already exists for payline CPT
                $transactionId = $order->get_transaction_id();
                if($transactionId && $order->get_payment_method()=='payline') {
                    die();
                }
            }

            $expectedToken = $this->getCachedDWPDataForOrder($order, 'token');
            if($expectedToken != $token){
                $message = sprintf(__('Token %s does not match expected %s for order %s', 'payline'), wc_clean($token), $expectedToken, $orderId);
                $this->SDK->getLogger()->error($message);
                $order->add_order_note($message);
                wp_redirect(wc_get_cart_url());
                die($message);
            }

            do_action( $this->id . '_payment_callback', $res, $order );

            $message = $this->paylineManageReturn($order, $res);

            $redirectUrl = $this->get_return_url($order);
            if(in_array($order->get_status(), array('failed', 'cancelled'))) {
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
     */
    protected function paylineManageReturn(WC_Order $order, array $res)
    {
        $message = '';
        $status = '';
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
            $order->update_status('on-hold', __('Fraud alert. See details in Payline administration center', 'payline'));
        } elseif ($res['result']['code'] == '02306' || $res['result']['code'] == '02533') {
            $order->add_order_note(__('Payment in progress', 'payline'));
            wc_add_notice( __( 'Payment in progress', 'payline' ), 'notice' );
        } else {
            if($this->paylineCancelWebPaymentDetails($order, $res)) {

            } elseif ($res['result']['code'] == '02319' || $res['result']['code'] == '02014'){
                $message = __('Buyer cancelled his payment', 'payline');
                $status = 'cancelled';
            } elseif ($res['result']['code'] == '02304' || $res['result']['code'] == '02324'){
                $message = __('Payment session expired without transaction', 'payline');
                $status = 'cancelled';

            }elseif ($res['result']['code'] == '02534' || $res['result']['code'] == '02324'){
                $message = __('Payment session expired with no redirection on payment page', 'payline');
                $status = 'cancelled';
            } else {
                if($res['transaction']['id']){
                    //Implicit save with update_status cause $status is set on 'failed'
                    $order->update_meta_data('Transaction ID', $res['transaction']['id']);
                }
                $message = sprintf( __('Payment refused (code %s: %s)','payline'), $res['result']['code'], $res['result']['longMessage']);
                $status = 'failed';
            }
            if($status) {
                $order->update_status($status, $message);
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

        $this->SDK = $this->getSDK();

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

        $res = $this->SDK->doRefund($refundParams);
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

}
