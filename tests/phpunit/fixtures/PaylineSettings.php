<?php
/**
 * Fixtures centralisées pour les settings Payline
 *
 * Élimine la duplication des données de configuration entre les différentes
 * gateways (payline, payline_cpt, payline_nx, payline_rec).
 *
 * ARCHITECTURE :
 * - BASE_SETTINGS : Paramètres communs à toutes les gateways
 * - CREDENTIALS_STANDARD : Credentials mock standards
 * - CREDENTIALS_NX : Credentials mock pour NX/CPT/REC
 * - TYPE_SPECIFIC : Paramètres spécifiques par type de gateway
 *
 * Usage :
 *   PaylineSettings::getSettings('payline')       → Settings gateway standard
 *   PaylineSettings::getSettings('payline_cpt')   → Settings gateway CPT
 *   PaylineSettings::getSettings('payline_nx')    → Settings gateway NX
 *   PaylineSettings::getAllSettings()             → Toutes les gateways
 */
class PaylineSettings
{
    /**
     * Paramètres de base communs à toutes les gateways
     */
    const BASE_SETTINGS = [
        'environment' => 'HOMO',           // Homologation/test Monext
        'smartdisplay_parameter' => '',    // Paramètre smartDisplay (vide par défaut)
        'pos' => '',                       // Point de vente actif (vide par défaut)
        'custom_page_code' => '',          //
    ];

    /**
     * Credentials mock standards (invalides pour API réelle)
     */
    const CREDENTIALS_STANDARD = [
        'merchant_id' => 'MERCH',
        'access_key' => 'KEY',
    ];

    /**
     * Credentials mock pour NX/CPT/REC (invalides pour API réelle)
     */
    const CREDENTIALS_NX = [
        'merchant_id' => 'NX_MERCH',
        'access_key' => 'NX_KEY',
    ];

    /**
     * Paramètres spécifiques par type de gateway
     */
    const TYPE_SPECIFIC = [
        'payline' => [
            // Gateway standard : seulement les credentials de base
        ],
        'payline_cpt' => [
            'widget_integration' => 'redirection',
            'wallet' => 'yes',
        ],
        'payline_nx' => [
            'widget_integration' => 'redirection',
        ],
        'payline_rec' => [
            'widget_integration' => 'redirection',
        ],
    ];

    /**
     * Gateway pour tester le cas où les credentials sont manquants
     */
    const WRONG_SDK_CALL = [
        'environment' => 'HOMO',
        'widget_integration' => 'redirection',
        'smartdisplay_parameter' => '',
        'pos' => '',
        // Pas de merchant_id ni access_key (intentionnel pour tests)
    ];

    /**
     * Retourne les settings pour une gateway spécifique
     *
     * @param string $type Type de gateway ('payline', 'payline_cpt', 'payline_nx', etc.)
     * @return array Settings complets pour la gateway
     */
    public static function getSettings(string $type): array
    {
        // Cas spécial : gateway sans credentials
        if ($type === 'wrong_sdk_call') {
            return self::WRONG_SDK_CALL;
        }

        // Déterminer les credentials selon le type
        $credentials = ($type === 'payline')
            ? self::CREDENTIALS_STANDARD
            : self::CREDENTIALS_NX;

        // Merge : BASE + CREDENTIALS + TYPE_SPECIFIC
        return array_merge(
            self::BASE_SETTINGS,
            $credentials,
            self::TYPE_SPECIFIC[$type] ?? []
        );
    }

    /**
     * Retourne tous les settings pour toutes les gateways
     *
     * Format attendu par MockOptions :
     * [
     *   'woocommerce_payline_settings' => [...],
     *   'woocommerce_payline_cpt_settings' => [...],
     *   ...
     * ]
     *
     * @return array
     */
    public static function getAllSettings(): array
    {
        return [
            'woocommerce_payline_settings' => self::getSettings('payline'),
            'woocommerce_payline_nx_settings' => self::getSettings('payline_nx'),
            'woocommerce_payline_cpt_settings' => self::getSettings('payline_cpt'),
            'woocommerce_wrong_sdk_call_settings' => self::getSettings('wrong_sdk_call'),
        ];
    }

    /**
     * Retourne les credentials standards
     *
     * Utile pour configurer dynamiquement dans les tests
     *
     * @return array
     */
    public static function getStandardCredentials(): array
    {
        return self::CREDENTIALS_STANDARD;
    }

    /**
     * Retourne les credentials NX
     *
     * @return array
     */
    public static function getNxCredentials(): array
    {
        return self::CREDENTIALS_NX;
    }
}
