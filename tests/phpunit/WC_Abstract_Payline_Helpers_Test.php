<?php

/**
 * Tests unitaires pour WC_Abstract_Payline : Méthodes utilitaires (helpers)
 *
 * Teste les méthodes utilitaires simples avec entrées/sorties prévisibles.
 * Ces fonctions n'ont pas de dépendances externes complexes (pas de WC_Order,
 * pas d'appels API), ce qui permet de tester exhaustivement toutes les cas
 * sans setup lourd ni mocks.
 *
 * Méthodes testées :
 * - cleanSubstr() : Nettoyage et substring sécurisé (UTF-8)
 * - changeColor() : Modification couleur HEX (éclaircir/foncer)
 * - encryptWalletId() : Hash MD5 pour wallet ID
 * - get_supported_languages() : Liste des langues supportées par Payline
 * - is_test_mode() : Détection environnement de test (HOMO vs PROD)
 * - getCaptureTriggerOptions() : Options de déclenchement capture paiement
 */
class WC_Abstract_Payline_Helpers_Test extends PaylineTestCase
{
    /** @var WC_Gateway_Payline_CPT */
    private $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        // Instancie la gateway concrète pour accéder aux méthodes protected
        $this->gateway = new WC_Gateway_Payline_CPT();
    }

    // =========================================
    // cleanSubstr()
    // =========================================

    /**
     * @dataProvider cleanSubstrProvider
     * @throws ReflectionException
     */
    public function test_clean_substr($input, $offset, $length, $expected)
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'cleanSubstr');

        $result = $method->invoke($this->gateway, $input, $offset, $length);

        $this->assertEquals($expected, $result, 'cleanSubstr doit retourner la valeur attendue');
    }

    public static function cleanSubstrProvider(): array
    {
        return array(
            'string normal' => array('Hello World', 0, 5, 'Hello'),
            'string avec offset' => array('Hello World', 6, 5, 'World'),
            'longueur null (tout le reste)' => array('Hello World', 6, null, 'World'),
            'longueur > string' => array('Hi', 0, 10, 'Hi'),
            'offset > string' => array('Hi', 10, 5, ''),
            'caractères spéciaux UTF-8' => array('Café résumé', 0, 4, 'Café'),
            'caractères accentués milieu' => array('élève modèle', 6, 6, 'modèle'),
        );
    }

    /**
     * @throws ReflectionException
     */
    public function test_clean_substr_removes_newlines()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'cleanSubstr');

        $input = "Hello\nWorld\r\nTest";
        $result = $method->invoke($this->gateway, $input, 0, null);

        $this->assertEquals('HelloWorldTest', $result, 'cleanSubstr doit retirer les sauts de ligne');
    }

    /**
     * @throws ReflectionException
     */
    public function test_clean_substr_removes_tabs()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'cleanSubstr');

        $input = "Hello\tWorld";
        $result = $method->invoke($this->gateway, $input, 0, null);

        $this->assertEquals('HelloWorld', $result, 'cleanSubstr doit retirer les tabulations');
    }

    /**
     * @throws ReflectionException
     */
    public function test_clean_substr_handles_empty_string()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'cleanSubstr');

        $result = $method->invoke($this->gateway, '', 0, 5);

        $this->assertEquals('', $result, 'cleanSubstr doit retourner une chaîne vide pour une entrée vide');
    }

    // =========================================
    // changeColor()
    // =========================================

    public function test_change_color_returns_valid_hex_format()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'changeColor');

        $result = $method->invoke($this->gateway, '#ff5733', 20, true);

        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $result, 'changeColor doit retourner un format HEX valide');
    }

    public function test_change_color_lighter_increases_rgb_values()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'changeColor');

        $original = '#404040'; // Gris moyen (64, 64, 64)
        $lighter = $method->invoke($this->gateway, $original, 50, true);

        // Convertir en décimal pour comparer
        $originalR = hexdec(substr($original, 1, 2));
        $lighterR = hexdec(substr($lighter, 1, 2));

        $this->assertGreaterThan($originalR, $lighterR, 'La couleur plus claire doit avoir des valeurs RGB plus élevées');
    }

    public function test_change_color_darker_decreases_rgb_values()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'changeColor');

        $original = '#c0c0c0'; // Gris clair (192, 192, 192)
        $darker = $method->invoke($this->gateway, $original, 50, false);

        $originalR = hexdec(substr($original, 1, 2));
        $darkerR = hexdec(substr($darker, 1, 2));

        $this->assertLessThan($originalR, $darkerR, 'La couleur plus foncée doit avoir des valeurs RGB plus basses');
    }

    public function test_change_color_with_zero_strength_returns_same_color()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'changeColor');

        $original = '#ff5733';
        $result = $method->invoke($this->gateway, $original, 0, true);

        $this->assertEquals($original, $result, 'changeColor avec une force de 0 doit retourner la couleur originale');
    }

    public function test_change_color_handles_short_hex_format()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'changeColor');

        // #f00 devrait être traité comme #ff0000
        $result = $method->invoke($this->gateway, '#f00', 0, true);

        $this->assertEquals('#ff0000', $result, 'changeColor doit convertir le format court #f00 en #ff0000');
    }

    public function test_change_color_handles_hex_without_hash()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'changeColor');

        $result = $method->invoke($this->gateway, 'ff5733', 0, true);

        $this->assertEquals('#ff5733', $result, 'changeColor doit accepter un HEX sans le dièse');
    }

    /**
     * @throws ReflectionException
     */
    public function test_change_color_clamps_values_to_valid_range()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'changeColor');

        // Blanc + lighter 100% ne doit pas dépasser #ffffff
        $result = $method->invoke($this->gateway, '#ffffff', 100, true);
        $this->assertEquals('#ffffff', $result, 'changeColor doit limiter les valeurs au maximum #ffffff');

        // Noir + darker 100% ne doit pas aller en dessous de #000000
        $result = $method->invoke($this->gateway, '#000000', 100, false);
        $this->assertEquals('#000000', $result, 'changeColor doit limiter les valeurs au minimum #000000');
    }

    /**
     * @dataProvider changeColorProvider
     * @throws ReflectionException
     */
    public function test_change_color_calculates_correctly($hex, $strength, $lighter, $expected)
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'changeColor');

        $result = $method->invoke($this->gateway, $hex, $strength, $lighter);

        $this->assertEquals($expected, $result, 'changeColor doit calculer la couleur correctement');
    }

    public static function changeColorProvider(): array
    {
        return array(
            // Cas limites
            'noir unchanged' => array('#000000', 0, true, '#000000'),
            'blanc unchanged' => array('#ffffff', 0, false, '#ffffff'),
            // Éclaircissement
            'noir lighter 50%' => array('#000000', 50, true, '#7f7f7f'),
            // Assombrissement
            'blanc darker 50%' => array('#ffffff', 50, false, '#7f7f7f'),
        );
    }

    // =========================================
    // encryptWalletId()
    // =========================================

    public function test_encrypt_wallet_id_returns_md5_hash()
    {
        $result = $this->gateway->encryptWalletId('12345');

        // MD5 = 32 caractères hexadécimaux
        $this->assertEquals(32, strlen($result), 'encryptWalletId doit retourner un hash de 32 caractères');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $result, 'encryptWalletId doit retourner un hash hexadécimal');
    }

    public function test_encrypt_wallet_id_is_deterministic()
    {
        $result1 = $this->gateway->encryptWalletId('customer_123');
        $result2 = $this->gateway->encryptWalletId('customer_123');

        $this->assertEquals($result1, $result2, 'encryptWalletId doit être déterministe');
    }

    public function test_encrypt_wallet_id_different_for_different_ids()
    {
        $result1 = $this->gateway->encryptWalletId('customer_1');
        $result2 = $this->gateway->encryptWalletId('customer_2');

        $this->assertNotEquals($result1, $result2, 'encryptWalletId doit retourner des valeurs différentes pour des IDs différents');
    }

    public function test_encrypt_wallet_id_matches_expected_md5()
    {
        $customerId = 'test_customer';
        $expected = md5($customerId);

        $result = $this->gateway->encryptWalletId($customerId);

        $this->assertEquals($expected, $result, 'encryptWalletId doit correspondre au MD5 de l\'ID');
    }

    // =========================================
    // get_supported_languages()
    // =========================================

    public function test_get_supported_languages_returns_array()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'get_supported_languages');

        $result = $method->invoke($this->gateway);

        $this->assertIsArray($result, 'get_supported_languages() doit retourner un tableau');
    }

    public function test_get_supported_languages_without_all_returns_empty()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'get_supported_languages');

        $result = $method->invoke($this->gateway, false);

        $this->assertEmpty($result, 'get_supported_languages() doit retourner un tableau vide quand all=false');
    }

    public function test_get_supported_languages_with_all_contains_all_option()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'get_supported_languages');

        $result = $method->invoke($this->gateway, true);

        $this->assertArrayHasKey('', $result, 'get_supported_languages() doit contenir une clé vide pour "All"');
        $this->assertEquals('All', $result[''], 'La clé vide doit avoir la valeur "All"');
    }

    // =========================================
    // is_test_mode()
    // =========================================
    // Note: completeSettings() merge les settings globaux (woocommerce_payline_settings)
    // avec les settings spécifiques. Il faut donc modifier les DEUX.

    public function test_is_test_mode_returns_true_for_homo_environment()
    {
        MockOptions::$data['woocommerce_payline_settings']['environment'] = 'HOMO';
        MockOptions::$data['woocommerce_payline_cpt_settings']['environment'] = 'HOMO';
        $this->gateway = new WC_Gateway_Payline_CPT();

        $result = $this->gateway->is_test_mode();

        $this->assertTrue($result, 'is_test_mode() doit retourner true pour l\'environnement HOMO');
    }

    public function test_is_test_mode_returns_false_for_prod_environment()
    {
        MockOptions::$data['woocommerce_payline_settings']['environment'] = 'PROD';
        MockOptions::$data['woocommerce_payline_cpt_settings']['environment'] = 'PROD';
        $this->gateway = new WC_Gateway_Payline_CPT();

        $result = $this->gateway->is_test_mode();

        $this->assertFalse($result, 'is_test_mode() doit retourner false pour l\'environnement PROD');
    }

    public function test_is_test_mode_returns_false_when_environment_empty()
    {
        MockOptions::$data['woocommerce_payline_settings']['environment'] = '';
        MockOptions::$data['woocommerce_payline_cpt_settings']['environment'] = '';
        $this->gateway = new WC_Gateway_Payline_CPT();

        $result = $this->gateway->is_test_mode();

        $this->assertFalse($result, 'is_test_mode() doit retourner false quand l\'environnement est vide');
    }

    public function test_is_test_mode_returns_false_when_environment_missing()
    {
        unset(MockOptions::$data['woocommerce_payline_settings']['environment']);
        unset(MockOptions::$data['woocommerce_payline_cpt_settings']['environment']);
        $this->gateway = new WC_Gateway_Payline_CPT();

        $result = $this->gateway->is_test_mode();

        $this->assertFalse($result, 'is_test_mode() doit retourner false quand l\'environnement est absent');
    }

    // =========================================
    // getCaptureTriggerOptions()
    // =========================================

    public function test_get_capture_trigger_options_returns_array()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getCaptureTriggerOptions');

        $result = $method->invoke($this->gateway);

        $this->assertIsArray($result, 'getCaptureTriggerOptions() doit retourner un tableau');
    }

    public function test_get_capture_trigger_options_excludes_cancelled_status()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getCaptureTriggerOptions');

        $result = $method->invoke($this->gateway);

        $this->assertArrayNotHasKey('cancelled', $result, 'getCaptureTriggerOptions() ne doit pas contenir "cancelled"');
    }

    public function test_get_capture_trigger_options_excludes_refunded_status()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getCaptureTriggerOptions');

        $result = $method->invoke($this->gateway);

        $this->assertArrayNotHasKey('refunded', $result, 'getCaptureTriggerOptions() ne doit pas contenir "refunded"');
    }

    public function test_get_capture_trigger_options_excludes_failed_status()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getCaptureTriggerOptions');

        $result = $method->invoke($this->gateway);

        $this->assertArrayNotHasKey('failed', $result, 'getCaptureTriggerOptions() ne doit pas contenir "failed"');
    }

    public function test_get_capture_trigger_options_excludes_on_hold_status()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getCaptureTriggerOptions');

        $result = $method->invoke($this->gateway);

        $this->assertArrayNotHasKey('on-hold', $result, 'getCaptureTriggerOptions() ne doit pas contenir "on-hold"');
    }

    public function test_get_capture_trigger_options_excludes_pending_status()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getCaptureTriggerOptions');

        $result = $method->invoke($this->gateway);

        $this->assertArrayNotHasKey('pending', $result, 'getCaptureTriggerOptions() ne doit pas contenir "pending"');
    }

    public function test_get_capture_trigger_options_includes_processing_status()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getCaptureTriggerOptions');

        $result = $method->invoke($this->gateway);

        $this->assertArrayHasKey('processing', $result, 'getCaptureTriggerOptions() doit contenir "processing"');
    }

    public function test_get_capture_trigger_options_includes_completed_status()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getCaptureTriggerOptions');

        $result = $method->invoke($this->gateway);

        $this->assertArrayHasKey('completed', $result, 'getCaptureTriggerOptions() doit contenir "completed"');
    }

    public function test_get_capture_trigger_options_values_contain_status_label()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'getCaptureTriggerOptions');

        $result = $method->invoke($this->gateway);

        // Vérifie que les valeurs contiennent "When order status is"
        foreach ($result as $key => $label) {
            $this->assertStringContainsString('When order status is', $label, "Chaque label doit contenir 'When order status is'");
        }
    }
}
