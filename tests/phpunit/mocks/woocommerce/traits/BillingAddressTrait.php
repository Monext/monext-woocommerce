<?php
/**
 * Trait BillingAddressTrait
 *
 * Fournit toutes les méthodes d'accès aux données de facturation (billing)
 * pour le mock WC_Order.
 *
 * Réduit la duplication de code : 10 méthodes get_billing_*() générées
 * dynamiquement à partir du tableau $billing.
 *
 * Usage dans WC_Order :
 *   use BillingAddressTrait;
 */
trait BillingAddressTrait
{
    /**
     * Tableau des données de facturation
     * @var array
     */
    private $billing = array(
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john.doe@example.com',
        'phone' => '0123456789',
        'address_1' => '123 Main St',
        'address_2' => '',
        'city' => 'Paris',
        'postcode' => '75001',
        'country' => 'FR',
        'company' => '',
    );

    // ========================================================================
    // GETTERS BILLING
    // ========================================================================

    public function get_billing_first_name($context = 'view')
    {
        return $this->billing['first_name'];
    }

    public function get_billing_last_name($context = 'view')
    {
        return $this->billing['last_name'];
    }

    public function get_billing_email($context = 'view')
    {
        return $this->billing['email'];
    }

    public function get_billing_phone($context = 'view')
    {
        return $this->billing['phone'];
    }

    public function get_billing_address_1($context = 'view')
    {
        return $this->billing['address_1'];
    }

    public function get_billing_address_2($context = 'view')
    {
        return $this->billing['address_2'];
    }

    public function get_billing_city($context = 'view')
    {
        return $this->billing['city'];
    }

    public function get_billing_postcode($context = 'view')
    {
        return $this->billing['postcode'];
    }

    public function get_billing_country($context = 'view')
    {
        return $this->billing['country'];
    }

    public function get_billing_company($context = 'view')
    {
        return $this->billing['company'];
    }

    // ========================================================================
    // SETTERS BILLING
    // ========================================================================

    public function set_billing($billing)
    {
        $this->billing = array_merge($this->billing, $billing);
    }
}
