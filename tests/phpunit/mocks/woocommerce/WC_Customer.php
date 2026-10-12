<?php

if (!class_exists('WC_Customer')) {
    class WC_Customer
    {
        private $customer_id;

        public function __construct($customer_id = 0)
        {
            $this->customer_id = $customer_id;
        }

        public function get_id()
        {
            return $this->customer_id;
        }

        public function get_billing_email()
        {
            return 'customer@example.com';
        }

        public function get_billing_first_name()
        {
            return 'John';
        }

        public function get_first_name()
        {
            return 'John';
        }

        public function get_billing_last_name()
        {
            return 'Doe';
        }

        public function get_last_name()
        {
            return 'Doe';
        }
    }
}