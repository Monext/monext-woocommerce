<?php
/**
 * Mock dynamique pour les options WordPress
 *
 * Simule get_option() avec des fixtures par défaut pour éviter
 * les appels DB pendant les tests.
 *
 * ARCHITECTURE V2 (avec PaylineSettings) :
 * - Les fixtures Payline sont générées par PaylineSettings::getAllSettings()
 * - Élimine la duplication des credentials entre gateways
 * - Facilite l'ajout de nouvelles gateways
 *
 * Usage :
 *   MockOptions::reset();                     // Reset aux valeurs initiales
 *   MockOptions::$data['key']['field'] = 'X'; // Modifier pour un test
 *
 * Fixtures disponibles :
 *   - woocommerce_payline_settings : Configuration globale
 *   - woocommerce_payline_nx_settings : Configuration NX (3x/4x)
 *   - woocommerce_payline_cpt_settings : Configuration CPT (comptant)
 *   - woocommerce_wrong_sdk_call_settings : Cas de test pour credentials manquants
 */

require_once __DIR__ . '/../../fixtures/PaylineSettings.php';

class MockOptions {
    /**
     * Fixtures par défaut
     * Utilise PaylineSettings pour générer les settings Payline
     * @var array
     */
    private static $fixtures = [];

    /**
     * Données mockées accessibles aux tests
     * @var array
     */
    public static $data = [];

    /**
     * Initialise les fixtures (appelé automatiquement en bas du fichier)
     */
    public static function initFixtures(): void
    {
        self::$fixtures = PaylineSettings::getAllSettings();
    }

    /**
     * Reset les données aux valeurs par défaut
     */
    public static function reset(): void
    {
        self::$data = self::$fixtures;
    }

    /**
     * Retourne les fixtures par défaut
     * @return array
     */
    public static function getFixtures(): array
    {
        return self::$fixtures;
    }
}

// Initialiser les fixtures au chargement du fichier
MockOptions::initFixtures();
