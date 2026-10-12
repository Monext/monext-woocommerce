<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
use Payline\PaylineSDK;

class PaylineWallet {

    protected static $endPoint = 'my-payline-wallet';

    /**
     * @return bool
     */
    public static function isWalletEnabled()
    {
        $settings = get_option('woocommerce_payline_cpt_settings', []);
        return (isset($settings['wallet']) && $settings['wallet'] === 'yes');
    }

    /**
     * @return mixed|null
     */
    public static function getEnvSettingValue()
    {
        $settings = get_option('woocommerce_payline_settings', []);
        return $settings['environment'] ?? null;
    }

    public static function addQueryVars($vars)
    {
        $vars[self::$endPoint] = self::$endPoint;
        return $vars;
    }

    /**
     * @param $menu_items
     * @return array
     */
    public static function addUserAccountMenuItem($menu_items)
    {
        $retVal = [];

        if (self::isWalletEnabled()) {
            foreach ($menu_items as $slug => $item) {
                if ($slug === 'customer-logout') {
                    $retVal[self::$endPoint] = __('My Wallet');
                }
                $retVal[$slug] = $item;
            }
        } else {
            return $menu_items;
        }

        return $retVal;
    }

    /**
     * @return void
     */
    public static function getPageContent($gateway = null)
    {
        $paylineGateway = ($gateway !== null) ? $gateway : new WC_Gateway_Payline();
        $resultManage   = $paylineGateway->createManageWebWallet();

        $tplData            = [];
        $tplData['token']   = null;

        if ( is_array($resultManage) && isset($resultManage['token']) ) {
            $tplData['token'] = $resultManage['token'];
        }

        load_template( WP_PLUGIN_DIR.'/payline/templates/front/user-account-wallet.php', true, $tplData );
    }

    /**
     * Add css to front
     * @return void
     */
    public static function payline_add_front_styles()
    {
        if (self::is_wallet_endpoint_url()) {
            wp_enqueue_style(
                'payline-front-style',
                plugin_dir_url( __FILE__ ) . 'assets/css/front.css',
                array(),
                '1.0.0'
            );

            $widgetJS = (self::getEnvSettingValue() == PaylineSDK::ENV_HOMO)?PaylineSDK::HOMO_WDGT_JS:PaylineSDK::PROD_WDGT_JS;
            $widgetCSS = (self::getEnvSettingValue() == PaylineSDK::ENV_HOMO)?PaylineSDK::HOMO_WDGT_CSS:PaylineSDK::PROD_WDGT_CSS;
            wp_enqueue_script('widget-min', $widgetJS);
            wp_enqueue_style('widget-min', $widgetCSS);
        }
    }

    /**
     * @param $title
     * @return string
     */
    public static function getPageTitle($title, $endpoint)
    {
        if (self::is_wallet_endpoint_url() && $endpoint == self::$endPoint && in_the_loop()) {
            $title = __('My Wallet', 'monext-online-woocommerce');
        }

        return $title;
    }

    /**
     * @return bool
     */
    protected static function is_wallet_endpoint_url()
    {
        global $wp;

        if (!is_null($wp) && !is_admin() && is_main_query() && is_account_page() && is_page() && array_key_exists(self::$endPoint, $wp->query_vars) !== false) {
            return true;
        }
        return false;
    }
}