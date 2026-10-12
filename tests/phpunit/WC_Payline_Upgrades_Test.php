<?php

/**
 * Tests unitaires pour WC_Payline_Upgrades
 *
 * Compatibilité : PHP 7.4 - 8.4
 */
class WC_Payline_Upgrades_Test extends PaylineTestCase
{
    // =========================================
    // upgrade_to_1_5_6()
    // =========================================

    public function test_upgrade_to_1_5_6_does_nothing_when_cpt_settings_exist()
    {
        MockOptions::$data['woocommerce_payline_settings'] = ['merchant_id' => 'MERCH'];
        MockOptions::$data['woocommerce_payline_cpt_settings'] = ['enabled' => 'yes'];

        WC_Payline_Upgrades::upgrade_to_1_5_6();

        $this->assertEquals(
            ['enabled' => 'yes'],
            MockOptions::$data['woocommerce_payline_cpt_settings'],
            'cpt_settings ne doit pas être modifié quand il existe déjà'
        );
    }

    public function test_upgrade_to_1_5_6_copies_old_to_cpt_settings()
    {
        $this->markTestIncomplete(
            'upgrade_to_1_5_6() appelle WC_Payline_SDK::getPointOfSales() (API réelle) — à mocker pour test isolé'
        );
    }

    public function test_upgrade_to_1_5_6_deletes_old_settings_option()
    {
        $this->markTestIncomplete(
            'upgrade_to_1_5_6() appelle WC_Payline_SDK::getPointOfSales() (API réelle) — à mocker pour test isolé'
        );
    }

    public function test_upgrade_to_1_5_6_contracts_string_to_array()
    {
        $this->markTestIncomplete(
            'upgrade_to_1_5_6() appelle WC_Payline_SDK::getPointOfSales() (API réelle) — à mocker pour test isolé'
        );
    }

    public function test_upgrade_to_1_5_6_clears_global_settings_from_cpt()
    {
        $this->markTestIncomplete(
            'upgrade_to_1_5_6() appelle WC_Payline_SDK::getPointOfSales() (API réelle) — à mocker pour test isolé'
        );
    }
}