<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

abstract class WC_Block_Abstract_Payline extends AbstractPaymentMethodType {

	/**
	 * @var string
	 */
	protected $settingsOptionName;

	/**
	 * @var bool
	 */
	protected $defaultStatus = false;

	/**
	 * @var string
	 */
	protected $handle;

	/**
	 * @var class-string
	 */
	protected $gatewayClass;


    protected $gateway;


	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( $this->settingsOptionName, [] );
		$this->gateway  = $this->gatewayClass;
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return filter_var( $this->get_setting( 'enabled', $this->defaultStatus ), FILTER_VALIDATE_BOOLEAN );
	}


	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		wp_register_script( $this->handle,
			WCPAYLINE_PLUGIN_URL . 'build/' . $this->handle . '.js',
			[],
            WCPAYLINE_PLUGIN_VERSION,
			true );

		return [ $this->handle ];
	}

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
	public function get_payment_method_data()
    {
        return array_merge([
            'title'       => $this->get_setting( 'title' ),
            'icon'       => $this->get_icons(),
            'description' => strip_tags($this->get_setting( 'description' ), '<br>'),
            'supports'    => $this->get_supported_features(),
            'canMakePayment'    => $this->can_make_payment(),
        ], $this->get_payment_method_additionnal_data());
	}

    /**
     * @return string[]
     */
    public function get_payment_method_additionnal_data()
    {
        return [
            'widget_integration' => 'redirection'
        ];
    }

    /**
     * @return bool
     */
    public function can_make_payment()
    {
        if (empty($this->get_setting('primary_contracts'))) {
            return false;
        }
        return true;
    }

    /**
     * @return array
     */
    protected function get_icons()
    {
        return [
                'id' => $this->name,
                'src' =>  !empty($this->settings['custom_icon']) ? $this->settings['custom_icon'] : WCPAYLINE_PLUGIN_URL . 'assets/images/icone-monext.svg',
                'alt' => __( 'Icon for '.$this->name.' payment gateway' , 'payline' ),
            ];
    }
}
