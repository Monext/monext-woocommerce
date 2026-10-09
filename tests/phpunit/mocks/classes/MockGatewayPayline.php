<?php
/**
 * Mock de WC_Gateway_Payline pour les tests
 *
 * Simule la classe WC_Gateway_Payline pour éviter les appels
 * API réels pendant les tests de PaylineWallet.
 *
 * Usage :
 *   MockGatewayPayline::reset();                                      // Reset
 *   MockGatewayPayline::$data['createManageWebWallet_result'] = ...; // Modifier
 *
 * Fixtures :
 *   - createManageWebWallet_result : array('token' => 'mock_token_123')
 *     Simule le token retourné par l'API Monext pour le wallet
 */
class MockGatewayPayline {
    private static $fixtures = [
        'createManageWebWallet_result' => ['token' => 'mock_token_123']
    ];

    public static $data = [];

    public static function reset(): void
    {
        self::$data = self::$fixtures;
    }

    public static function getFixtures(): array
    {
        return self::$fixtures;
    }

    public function createManageWebWallet()
    {
        return self::$data['createManageWebWallet_result'];
    }
}
