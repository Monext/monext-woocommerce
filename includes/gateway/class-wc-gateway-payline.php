<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Automattic\WooCommerce\Internal\Admin\Settings\PaymentProviders;
use Payline\PaylineSDK;
use Monolog\Logger;

class WC_Gateway_Payline extends WC_Abstract_Payline {

    public $id = 'payline';

    public $method_title = 'Monext';

    protected $callGetMerchantSettings = true;

    /** @var Payline\PaylineSDK $SDK */
    protected $SDK;

    protected $admin_link = "";

    protected $debugEnable;

    public $availability;

    /**
     * Create instance of payment method
     */
    public function __construct()
    {
        parent::__construct();
        $this->has_fields = false;
        $this->title = 'payline';
        $this->description = __("Accept multiple online payments methods");
        $this->icon = apply_filters('woocommerce_payline_icon', WCPAYLINE_PLUGIN_URL . 'assets/images/icone-monext.svg');
        $this->method_description = $this->description;

        $this->supports = [];
        $this->availability = false;
        $this->init_form_fields();
        $this->init_settings();
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_filter( 'woocommerce_available_payment_gateways', [ $this, 'remove_payline_gateway' ] );
    }

    /**
     * Remove/hide payline config gateway in checkout
     * @param $gateways
     * @return mixed
     */
    public function remove_payline_gateway($gateways)
    {
        foreach ($gateways as $key => $gateway){
            if ($gateway instanceof WC_Gateway_Payline){
                unset($gateways[$key]);
            }
        }
        return $gateways;
    }

    public function admin_options() {
        $templateData = $this->getDefaultTemplateData();
        echo $this->renderTemplate(__DIR__ . '/views/backend/settings/payline.php', $templateData);
    }

    public function init_form_fields()
    {
        $this->form_fields = array_merge(
            $this->getCommonSettingsFields(),
            $this->getAdvancedSettingsFields(),
            $this->getErrorMessagesFields(),
            $this->getProxySettingsFields(),
        );

    }

    /**
     * Function use in template to get Common settings part
     * @return string
     */
    protected function getCommonSettingsFieldsHtml() {
        return $this->generate_settings_html($this->getCommonSettingsFields());
    }

    /**
     * Return Common settings fields
     * @return array
     */
    protected function getCommonSettingsFields() {
        $fields = [];

        $fields['merchant_id'] = array(
            'title' => __('Merchant ID', 'payline'),
            'type' => 'text',
            'default' => '',
            'description' => __('Your Monext account identifier', 'payline')
        );
        $fields['access_key'] = array(
            'title' => __('Access key', 'payline'),
            'type' => 'obfuscated_field',
            'desc' => sprintf(__( 'Password used to call %s web services (available in the %s administration center)', 'payline'), 'Monext', 'Monext')
        );
        $fields['environment'] = array(
            'title' => __('Target environment', 'payline'),
            'type' => 'select',
            'default' => 'Homologation',
            'options' => array(
                PaylineSDK::ENV_HOMO => __('Homologation', 'payline'),
                PaylineSDK::ENV_PROD => __('Production', 'payline')
            ),
            'description' => __('Monext destination environement of your requests', 'payline')
        );

        $fields['pos'] = array(
            'title' => __('Point of Sales', 'payline'),
            'type' => 'select',
            'description' => __('If the list is empty, please check your Monext account identifier and resave', 'payline'),
            'options' => $this->getPointOfSalesList()
        );

        return $fields;
    }

    /**
	 * Generate Obfuscated Input HTML.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 * @since  1.0.0
	 * @return string
	 */
	public function generate_obfuscated_field_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
        $clearValue = isset($this->settings[$key]) ? $this->settings[$key] : '';
        
        $data['value'] = $this->getObfuctatedValue($clearValue);
        $data['type'] = 'text';
        $data['field_name'] = $field_key;
        $data['id'] = $field_key;

		ob_start();
		
        WC_Admin_Settings::output_fields( [$data] );
        
		return ob_get_clean();
	}

    /**
     * Function use in template to get Advanced settings part
     * @return string
     */
    protected function getAdvancedSettingsFieldsHtml() {
        return $this->generate_settings_html($this->getAdvancedSettingsFields());
    }

    /**
     * Return Advanced settings fields
     * @return array
     */
    protected function getAdvancedSettingsFields()
    {
        $fields = [];

        $fields['smartdisplay_parameter'] = array(
            'title' => __('Smartdisplay parameter', 'payline'),
            'type' => 'text',
            'description' => __('Added in doWebPayment privateData as display.rule.param', 'payline')
        );

        $fields['debug'] = array(
            'title' => __( 'Debug logging', 'payline' ),
            'type' => 'checkbox',
            'label' => __( 'Enable', 'payline' ),
            'default' => 'no'
        );

        $fields['language'] = array(
            'title' => __('Default language', 'payline'),
            'type' => 'select',
            'default' => '',
            'options' => array(
                '' => __('Based on browser', 'payline'),
                'fr' => __('fr', 'payline'),
                'en' => __('en', 'payline'),
                'pt' => __('pt', 'payline')
            ),
            'description' => __('Language used to display Monext web payment pages', 'payline')
        );

        return $fields;
    }

    /**
     * Function use in template to get Error Messages part
     * @return string
     */
    protected function getErrorMessagesFieldsHtml()
    {
        return $this->generate_settings_html($this->getErrorMessagesFields());
    }

    /**
     * Return Error Messages fields
     * @return array
     */
    protected function getErrorMessagesFields()
    {
        $fields = [];

        $fields['user_error_message_refused'] = array(
            'title' => __('Type Refused', 'payline'),
            'type' => 'text',
            'default' => __('Your payment has been refused', 'payline')
        );
        $fields['user_error_message_cancelled'] = array(
            'title' => __('Type Cancelled', 'payline'),
            'type' => 'text',
            'default' => __('Your payment has been cancelled', 'payline')
        );
        $fields['user_error_message_error'] = array(
            'title' => __('Type Error', 'payline'),
            'type' => 'text',
            'default' => __('Your payment is in error', 'payline')
        );

        return $fields;
    }

    /**
     * Function use in template to get Proxy settings part
     * @return string
     */
    protected function getProxySettingsFieldsHtml(){
        return $this->generate_settings_html($this->getProxySettingsFields());
    }

    /**
     * Return Proxy settings fields
     * @return array
     */
    protected function getProxySettingsFields()
    {
        $fields = [];

        $fields['proxy_host'] = array(
            'title' => __('Host', 'payline'),
            'type' => 'text',
        );
        $fields['proxy_port'] = array(
            'title' => __('Port', 'payline'),
            'type' => 'text',
        );
        $fields['proxy_login'] = array(
            'title' => __('Login', 'payline'),
            'type' => 'text',
        );
        $fields['proxy_password'] = array(
            'title' => __('Password', 'payline'),
            'type' => 'text',
        );

        return $fields;
    }

    /**
     * Update Point Of Sales at every settings save
     * @return void
     */
    public function process_admin_options()
    {
        parent::process_admin_options();
        $this->updatePointOfSalesList($this->settings['pos']);
    }

    /**
     * Validation personnalisée pour access_key.
     */
    public function validate_access_key_field($key, $value)
    {
        $originalValues = get_option($this->get_option_key(), []);
        $originalAccessKey = isset($originalValues['access_key']) ? $originalValues['access_key'] : '';

        // Si la valeur est obfusquée, on ne sauvegarde pas (on garde l'ancienne)
        if ($value === $this->getObfuctatedValue($originalAccessKey)) {
            return $originalAccessKey;
        }
        return $value;
    }

    /**
     * Return a list of Point Of Sales for Payline global settings
     * @return false|mixed|null
     */
    public function getPointOfSalesList()
    {
        $optionList = get_option( 'woocommerce_payline_pos_list', []);

        if(empty($optionList)) {
            $this->updatePointOfSalesList();
        }elseif (!empty($optionList)) {
            $optionList = unserialize($optionList);
        }

        array_unshift($optionList,  __("Please chose a Point of Sale to get contracts"));
        return $optionList;
    }

    /**
     * Update pos list in bdd
     * if pos are already selected, it's going to update contracts too
     * @param $selectedPos
     * @return void
     */
    public function updatePointOfSalesList($selectedPos = null)
    {
        $posListForSelect = array();
        foreach (WC_Payline_SDK::getPointOfSales() as $pos) {
            $posListForSelect[$pos['label']] = $pos['label'];
            if ($selectedPos && $pos['label'] == $selectedPos
                && isset($pos['contracts']['contract'])
                && is_array($pos['contracts'])
                && is_array($pos['contracts']['contract'])
            ){
                $this->updateContractList($pos['contracts']);
            }
        }
        update_option( 'woocommerce_payline_pos_list',  serialize($posListForSelect));
    }


    /**
     * Update contract list in database
     * @param $contracts
     * @return void
     */
    public function updateContractList($contracts)
    {
        $posContracts = array();
        $contractsList = $contracts['contract'];

        $firstKey = key($contractsList);
        if(!is_numeric($firstKey) && isset($contractsList['contractNumber'])) {
            $contractsList = [$contractsList];
        }

        // Assign logo for each contract
        //self::assignLogoToContracts($contractsList);

        foreach ($contractsList as $contract) {
            $posContracts[] = [
                "id" => $contract['cardType'] . '-' . $contract['contractNumber'],
                "label" => $contract['label'],
                "contractNumber" => $contract['contractNumber']
            ];
        }
        update_option( 'woocommerce_payline_pos_contracts_list',  serialize($posContracts));
    }

    /**
     * @param WC_Order $order
     * @param array $res
     * @return false
     */
    protected function paylineSuccessWebPaymentDetails(WC_Order $order, array $res)
    {
        return false;
    }

    protected function getObfuctatedValue($value, $nbToShow = 3)
    {
        if (!empty($value)) {
            $length = strlen($value);
            if ($length > $nbToShow) {
                $value = str_repeat('*', $length - $nbToShow) . substr($value, -$nbToShow);
            }
            return $value;
        }
        return $value;
    }

}