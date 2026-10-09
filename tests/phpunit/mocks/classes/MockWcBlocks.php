<?php
/**
 * Mock de AbstractPaymentMethodType de WooCommerce Blocks
 *
 * Permet de tester les classes WC_Block_* sans charger
 * WooCommerce Blocks complet.
 */
namespace Automattic\WooCommerce\Blocks\Payments\Integrations;

abstract class AbstractPaymentMethodType
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $settings = [];

    /**
     * Returns a specific setting value.
     *
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed
     */
    protected function get_setting($key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Returns an array of supported features.
     *
     * @return array
     */
    protected function get_supported_features()
    {
        return [];
    }
}

namespace Automattic\WooCommerce\StoreApi\Exceptions;

class RouteException extends \Exception {}

namespace Automattic\WooCommerce\Blocks\Utils;

class CartCheckoutUtils
{
    public static $is_checkout_block_default = false;

    public static function is_checkout_block_default()
    {
        return self::$is_checkout_block_default;
    }
}