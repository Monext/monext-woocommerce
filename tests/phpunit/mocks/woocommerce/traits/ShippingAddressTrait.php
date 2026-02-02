<?php
/**
 * Trait ShippingAddressTrait
 *
 * Fournit toutes les méthodes d'accès aux données de livraison (shipping)
 * pour le mock WC_Order.
 *
 * Réduit la duplication de code : 9 méthodes get_shipping_*() générées
 * dynamiquement à partir du tableau $shipping.
 *
 * Usage dans WC_Order :
 *   use ShippingAddressTrait;
 */
trait ShippingAddressTrait
{
    /**
     * Tableau des données de livraison
     * @var array
     */
    private $shipping = array(
        'first_name' => 'John',
        'last_name' => 'Doe',
        'address_1' => '123 Main St',
        'address_2' => '',
        'city' => 'Paris',
        'postcode' => '75001',
        'country' => 'FR',
        'company' => '',
    );

    // ========================================================================
    // GETTERS SHIPPING
    // ========================================================================

    public function get_shipping_first_name($context = 'view')
    {
        return $this->shipping['first_name'];
    }

    public function get_shipping_last_name($context = 'view')
    {
        return $this->shipping['last_name'];
    }

    public function get_shipping_address_1($context = 'view')
    {
        return $this->shipping['address_1'];
    }

    public function get_shipping_address_2($context = 'view')
    {
        return $this->shipping['address_2'];
    }

    public function get_shipping_city($context = 'view')
    {
        return $this->shipping['city'];
    }

    public function get_shipping_postcode($context = 'view')
    {
        return $this->shipping['postcode'];
    }

    public function get_shipping_country($context = 'view')
    {
        return $this->shipping['country'];
    }

    public function get_shipping_company($context = 'view')
    {
        return $this->shipping['company'];
    }

    public function get_shipping_phone($context = 'view')
    {
        return isset($this->shipping['phone']) ? $this->shipping['phone'] : '';
    }

    // ========================================================================
    // SETTERS SHIPPING
    // ========================================================================

    public function set_shipping($shipping)
    {
        $this->shipping = array_merge($this->shipping, $shipping);
    }
}
