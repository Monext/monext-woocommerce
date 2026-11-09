<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Payline\Api\MonextCompatClient;
use Payline\Api\ApiInterface as PaylineSDK;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class WC_Payline_SDK
{
//    protected static $merchantSettings = null;
    /**
     * @var MonextCompatClient
     */
    protected $monextCompatClient;
    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(MonextCompatClient $monextCompatClient)
    {
        $this->monextCompatClient = $monextCompatClient;
    }

    /**
     * Get Payline instance
     * @return PaylineSDK|MonextCompatClient|bool
     */
    public static function getSDK($paymentId = null)
    {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $pathLog = trailingslashit( dirname( WC_Log_Handler_File::get_log_file_path( 'payline' ) ) ) . trailingslashit( 'payline' );
        if (!is_dir($pathLog)) {
            wp_mkdir_p($pathLog);
        }
        $usedBy = [];
        $usedBy[] = 'WP '.get_bloginfo('version');

        $woocommerceinfo = get_plugins('/woocommerce');
        $usedBy[] = (!empty($woocommerceinfo)) ? current($woocommerceinfo)['Name'] .' '. current($woocommerceinfo)['Version'] : 'wooComm';
        $usedBy[] = 'v'.self::getExtensionVersion();

        $settings = self::getMethodSettings($paymentId);

        if(empty($settings['merchant_id']) || empty($settings['access_key'])) {
            return null;
        }

        $newSDK = new MonextCompatClient();
        $newSDK::configure('https://api-sandbox.retail.monext.com', base64_encode($settings['merchant_id'].':'.$settings['access_key']));
        $newSDK::usedBy(implode(' - ',$usedBy));


        return $newSDK;

    }

    /**
     * Get all point of sales (POS) related to the current account
     * @return array|WP_Error
     */
    public static function getPointOfSales()
    {
        $settings = self::getMethodSettings();
        $merchant_id = $settings['merchant_id'];
        if(empty($merchant_id)) {
            return [];
        }

        $SDK = self::getSDK();
        $result = $SDK->getPointsOfSale($merchant_id);

        if(!empty($result['error'])){
            return new WP_Error( 'error', $result['detail'] );
        }

        return $result;
    }

    public static function getPaymentMethods($posReference)
    {
        $settings = self::getMethodSettings();
        $SDK = self::getSDK();
        $result = $SDK->getPaymentMethods($settings['merchant_id'], $posReference);

        if(!empty($result['error'])){
            return new WP_Error( 'error', $result['detail'] );
        }

        return $result;
    }

//    /**
//     * Check if an API response is valid or not (check code 00000)
//     * @param array $result
//     * @param array $validFallbackCodeList
//     * @return bool
//     */
//    protected static function isValidResponse($result, $validFallbackCodeList = array())
//    {
//        $validClassic  = (is_array($result) && isset($result['result']['code']) && $result['result']['code'] == '00000');
//        $validFallback = (is_array($result) && isset($result['result']['code']) && in_array($result['result']['code'], $validFallbackCodeList));
//
//        return ($validClassic || $validFallback);
//    }

    /**
     * Return plugin version
     * @return mixed
     */
    protected static function getExtensionVersion()
    {
        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return get_plugin_data( WCPAYLINE_PLUGIN_PATH.'woocommerce-payline.php' )['Version'];
    }

    /**
     * Get global and gateway settings
     * @param $paymentId
     * @return false|mixed|null
     */
    protected static function getMethodSettings($paymentId = null)
    {
        $settings = get_option('woocommerce_payline_settings');

        if (!empty($paymentId)){
            $paymentIdSettings = get_option('woocommerce_'.$paymentId.'_settings');
            $paymentIdSettings = array_filter($paymentIdSettings);
            if(!empty($paymentIdSettings)){
                $settings = array_merge($settings, $paymentIdSettings);
            }
	}


        return $settings;
    }
}
