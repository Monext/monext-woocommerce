<?php
/**
 * Mock dynamique pour les données des plugins
 *
 * Simule get_plugin_data() et get_plugins() pour éviter
 * les appels filesystem pendant les tests.
 *
 * Usage :
 *   MockPlugins::reset();                              // Reset aux valeurs initiales
 *   MockPlugins::$data['payline']['Version'] = '2.0'; // Modifier pour un test
 *
 * Fixtures :
 *   - payline : Métadonnées du plugin Payline
 *     - Version : '1.5.8' (utilisé par getExtensionVersion())
 *
 *   - woocommerce : Métadonnées du plugin WooCommerce
 *     - Version : '10.3.5'
 */
class MockPlugins {
    private static $fixtures = [
        'payline' => [
            'Name' => 'Payline',
            'PluginURI' => 'https://docs.payline.com/display/DT/Plugin+WooCommerce',
            'Version' => '1.5.8',
            'Description' => 'integrations of Payline payment solution in your WooCommerce store',
            'Author' => 'Monext',
            'AuthorURI' => 'http://www.monext.fr',
            'TextDomain' => 'monext-online-woocommerce',
            'DomainPath' => '',
            'Network' => false,
            'RequiresWP' => '',
            'RequiresPHP' => '',
            'UpdateURI' => '',
            'RequiresPlugins' => 'woocommerce',
            'Title' => 'Payline',
            'AuthorName' => 'Monext',
            'WC requires at least' => '',
            'WC tested up to' => '4.9.2',
            'Woo' => '',
        ],
        'woocommerce' => [
            'WC requires at least' => '',
            'WC tested up to' => '',
            'Woo' => '',
            'Name' => 'WooCommerce',
            'PluginURI' => 'https://woocommerce.com/',
            'Version' => '10.3.5',
            'Description' => 'An ecommerce toolkit that helps you sell anything. Beautifully.',
            'Author' => 'Automattic',
            'AuthorURI' => 'https://woocommerce.com',
            'TextDomain' => 'woocommerce',
            'DomainPath' => '/i18n/languages/',
            'Network' => false,
            'RequiresWP' => '6.7',
            'RequiresPHP' => '7.4',
            'UpdateURI' => '',
            'RequiresPlugins' => '',
            'Title' => 'WooCommerce',
            'AuthorName' => 'Automattic',
        ]
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
}
