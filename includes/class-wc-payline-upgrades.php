<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Payline_Upgrades
{

    public static function upgrade_to_1_5_6()
    {
        $oldOptionName = 'woocommerce_payline_settings';
        $newOptionName = 'woocommerce_payline_cpt_settings';
        $oldOptionValue = get_option($oldOptionName);

        if(empty($oldOptionValue) || get_option($newOptionName) !== false){
            return;
        }

        $newPaylineSettings = [];

        if(!empty($oldOptionValue)){
            update_option( $newOptionName, $oldOptionValue );
            delete_option($oldOptionName);
        }

        $settingsKeyToClear = [
            "merchant_id",
            "access_key",
            "environment",
            "smartdisplay_parameter",
            "proxy_settings",
            "proxy_host",
            "proxy_port",
            "proxy_login",
            "proxy_password",
            "user_error_message_refused",
            "user_error_message_cancelled",
            "user_error_message_error",
            "error_messages",
            "debug",
        ];

        $cptSettings = get_option('woocommerce_payline_cpt_settings');
        $nxSettings = get_option('woocommerce_payline_nx_settings');
        $recSettings = get_option('woocommerce_payline_rec_settings');
        foreach ($settingsKeyToClear as $settingKey){
            if(empty($cptSettings[$settingKey])) {
                continue;
            }
            $newPaylineSettings[$settingKey] = $cptSettings[$settingKey];
            unset($cptSettings[$settingKey]);

            if(isset($nxSettings[$settingKey])) {
                unset($nxSettings[$settingKey]);
            }
            if(isset($recSettings[$settingKey])) {
                unset($recSettings[$settingKey]);
            }
        }

        if(isset($cptSettings['primary_contracts']) && is_string($cptSettings['primary_contracts'])) {
            $cptContractList = $cptSettings['primary_contracts'];
            $cptSettings['primary_contracts'] = explode(";", $cptContractList);
        }

        if(isset($nxSettings['primary_contracts']) && is_string($nxSettings['primary_contracts'])){
            $nxContractList = $nxSettings['primary_contracts'];
            $nxSettings['primary_contracts'] = explode(";", $nxContractList);
        }

        if(isset($recSettings['primary_contracts']) && is_string($recSettings['primary_contracts'])){
            $recContractList = $recSettings['primary_contracts'];
            $recSettings['primary_contracts'] = explode(";", $recContractList);
        }

        $newPaylineSettings["enabled"] = "yes";
        //Save it the first time, to be able to call SDK in following getPointOfSales
        update_option( $oldOptionName, $newPaylineSettings, true );

        if(class_exists('\Payline\Api\MonextCompatClient')) {
            $foundPos = self::upgradePointOfSalesREST($oldOptionValue);
        } else {
            $foundPos = self::upgradePointOfSalesSOAP($oldOptionValue);
        }

        if ($foundPos !== null) {
            $newPaylineSettings['pos'] = $foundPos;
        }

        //Save it the second time, to save new pos value
        update_option( $oldOptionName, $newPaylineSettings, true );
        update_option( 'woocommerce_payline_cpt_settings', $cptSettings, true );
        update_option( 'woocommerce_payline_nx_settings', $nxSettings, true );
        update_option( 'woocommerce_payline_rec_settings', $recSettings, true );

    }

    /**
     * Migration PointOfSales avec l'API SOAP (legacy)
     * @param array $oldOptionValue
     * @return string|null
     */
    protected static function upgradePointOfSalesSOAP($oldOptionValue)
    {
        $gateway = new WC_Gateway_Payline();
        $posListForSelect = WC_Payline_SDK::getPointOfSales();
        $mainContract = $oldOptionValue['main_contract'] ?? false;

        if (!$posListForSelect || !$mainContract) {
            return null;
        }

        foreach ($posListForSelect as $pos) {
            if (isset($pos['contracts']['contract'])
                && is_array($pos['contracts'])
                && is_array($pos['contracts']['contract'])
            ){
                $contractsList = $pos['contracts']['contract'];
                $firstKey = key($contractsList);
                if(!is_numeric($firstKey) && isset($contractsList['contractNumber'])) {
                    $contractsList = [$contractsList];
                }

                foreach ($contractsList as $contract) {
                    if ($contract['contractNumber'] == $mainContract) {
                        $gateway->updatePointOfSalesList($pos['label']);
                        update_option( 'woocommerce_payline_pos_list',  serialize($posListForSelect));
                        $gateway->updateContractList($pos['contracts']);
                        return $pos['label'];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Migration PointOfSales avec l'API REST
     * @param array $oldOptionValue
     * @return string|null
     */
    protected static function upgradePointOfSalesREST($oldOptionValue)
    {
        $gateway = new WC_Gateway_Payline();
        $posList = WC_Payline_SDK::getPointOfSales();
        $mainContract = $oldOptionValue['main_contract'] ?? false;

        if (is_wp_error($posList) || empty($posList['pointsOfSale']) || !$mainContract) {
            return null;
        }

        $posListForSelect = [];
        foreach ($posList['pointsOfSale'] as $pos) {
            $posListForSelect[$pos['reference']] = $pos['name'];

            if ($pos['reference'] === ($oldOptionValue['pos'] ?? null)) {
                continue;
            }

            $contractsList = WC_Payline_SDK::getPaymentMethods($pos['reference']);
            if (is_wp_error($contractsList) || empty($contractsList['paymentMethods'])) {
                continue;
            }

            foreach ($contractsList['paymentMethods'] as $contract) {
                if ($contract['id'] == $mainContract) {
                    $gateway->updatePointOfSalesList($pos['reference']);
                    update_option( 'woocommerce_payline_pos_list',  serialize($posListForSelect));
                    $gateway->newUpdateContractList($pos['reference']);
                    return $pos['reference'];
                }
            }
        }

        return null;
    }

    public static function upgrade_to_1_6_0()
    {
        if(!empty(get_option('woocommerce_payline_pos_list'))) {
            $gateway = new WC_Gateway_Payline();
            $gateway->updatePointOfSalesList();

            $currentPosRef = array_search($gateway->settings['pos'], unserialize(get_option('woocommerce_payline_pos_list')));
            if(!empty($currentPosRef)) {
                $payline_options = get_option('woocommerce_payline_settings');
                $payline_options['pos'] = $currentPosRef;
                update_option('woocommerce_payline_settings', $payline_options, true );
            }
        }
    }
}
