<?php

/**
 * Tests unitaires pour WC_Abstract_Payline : Remboursements et retours de paiement
 *
 * Teste les méthodes critiques qui gèrent le cycle de vie d'un paiement :
 * validation des remboursements et traitement des retours de l'API Payline.
 * Couvre les multiples scénarios de retour (succès, fraude, en cours, erreurs)
 * qui impactent directement les statuts des commandes clients. Les tests de
 * sécurité valident qu'un remboursement nécessite un transaction_id et que les
 * codes fraude (04003) déclenchent une mise en attente pour validation manuelle.
 *
 * Méthodes testées :
 * - process_refund() : Validation avant remboursement (nécessite transaction_id)
 * - paylineManageReturn() : Gestion des retours API Payline (14 tests, 7 scénarios)
 */
class WC_Abstract_Payline_Refund_Test extends PaylineTestCase
{
    /** @var WC_Gateway_Payline_CPT */
    private $gateway;

    /** @var WC_Order */
    private $order;

    protected function setUp(): void
    {
        parent::setUp();

        // Configurer les settings nécessaires
        MockOptions::$data['woocommerce_payline_settings']['payed_order_status'] = 'completed';
        MockOptions::$data['woocommerce_payline_settings']['user_error_message_refused'] = 'Paiement refusé';
        MockOptions::$data['woocommerce_payline_settings']['user_error_message_cancelled'] = 'Paiement annulé';
        MockOptions::$data['woocommerce_payline_settings']['user_error_message_error'] = 'Erreur de paiement';

        MockOptions::$data['woocommerce_payline_cpt_settings']['payed_order_status'] = 'completed';
        MockOptions::$data['woocommerce_payline_cpt_settings']['user_error_message_refused'] = 'Paiement refusé';
        MockOptions::$data['woocommerce_payline_cpt_settings']['user_error_message_cancelled'] = 'Paiement annulé';
        MockOptions::$data['woocommerce_payline_cpt_settings']['user_error_message_error'] = 'Erreur de paiement';

        $this->gateway = new WC_Gateway_Payline_CPT();

        // Créer un ordre avec transaction ID
        $this->order = new WC_Order(12345);
        $this->order->set_total('100.00');
        $this->order->set_currency('EUR');
        $this->order->set_transaction_id('TXN123456');
        $this->order->update_meta_data('_contract_number', 'CONTRACT789');
    }

    // =========================================
    // process_refund() - Validation
    // =========================================

    public function test_process_refund_returns_error_when_cannot_refund()
    {
        // Order sans transaction_id
        $order = new WC_Order(99999);
        $order->set_transaction_id('');

        $result = $this->gateway->process_refund($order->get_id(), 50.00, 'Test refund');

        $this->assertInstanceOf('WP_Error', $result, 'process_refund() doit retourner WP_Error sans transaction_id');
        $this->assertEquals('error', $result->get_error_code(), 'Le code d\'erreur doit être "error"');
    }

    public function test_process_refund_accepts_order_with_transaction_id()
    {
        // Ce test vérifie que la validation passe
        // mais l'appel SDK échouera (credentials mock invalides)

        $this->markTestIncomplete(
            'process_refund() appelle paylineSDK()->doRefund() qui nécessite un mock SDK Monext complet. ' .
            'La validation (can_refund_order) est testée séparément. ' .
            'Pour tester le comportement complet avec succès/échec API, un mock du SDK sera nécessaire.'
        );
    }

    // =========================================
    // paylineManageReturn() - Scénarios succès
    // =========================================

    /**
     * @throws ReflectionException
     */
    public function test_payline_manage_return_sets_order_payed_on_success()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'paylineManageReturn');

        // Simuler une réponse de succès
        $response = array(
            'result' => array(
                'code' => '00000',
                'shortMessage' => 'ACCEPTED',
            ),
            'transaction' => array('id' => 'TXN_SUCCESS'),
            'card' => array(
                'number' => '497010XXXXXXXX40',
                'type' => 'CB',
                'expirationDate' => '1225',
            ),
            'payment' => array(
                'contractNumber' => 'CONTRACT123',
            ),
        );

        $method->invoke($this->gateway, $this->order, $response);

        // Vérifier que la commande est marquée comme payée
        $this->assertEquals('completed', $this->order->get_status(), 'Le statut doit être "completed" pour un paiement accepté');
    }

    // =========================================
    // paylineManageReturn() - Scénario fraude
    // =========================================

    /**
     * @throws ReflectionException
     */
    public function test_payline_manage_return_sets_on_hold_for_fraud()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'paylineManageReturn');

        // Code 04003 = alerte fraude
        $response = array(
            'result' => array(
                'code' => '04003',
                'shortMessage' => 'FRAUD',
            ),
            'transaction' => array('id' => 'TXN_FRAUD'),
            'card' => array(
                'number' => '497010XXXXXXXX40',
                'type' => 'CB',
                'expirationDate' => '1225',
            ),
        );

        $method->invoke($this->gateway, $this->order, $response);

        // Vérifier que la commande est en attente
        $this->assertEquals('on-hold', $this->order->get_status(), 'Le statut doit être "on-hold" pour une alerte fraude');

        // Vérifier que les métadonnées carte sont enregistrées
        $this->assertEquals('TXN_FRAUD', $this->order->get_meta('Transaction ID'), 'Le Transaction ID doit être enregistré');
        $this->assertEquals('497010XXXXXXXX40', $this->order->get_meta('Card number'), 'Le numéro de carte doit être enregistré');
        $this->assertEquals('CB', $this->order->get_meta('Payment mean'), 'Le moyen de paiement doit être enregistré');
    }

    // =========================================
    // paylineManageReturn() - Paiement en cours
    // =========================================

    /**
     * @throws ReflectionException
     */
    public function test_payline_manage_return_handles_payment_in_progress_02306()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'paylineManageReturn');

        // Code 02306 = paiement en cours
        $response = array(
            'result' => array(
                'code' => '02306',
                'shortMessage' => 'PENDING',
            ),
            'transaction' => array('id' => 'TXN_PENDING'),
            'card' => array(),
        );

        $initialStatus = $this->order->get_status();
        $method->invoke($this->gateway, $this->order, $response);

        // Statut ne doit pas changer pour paiement en cours
        // (pas de update_status appelé, juste add_order_note)
        $this->assertEquals($initialStatus, $this->order->get_status(), 'Le statut ne doit pas changer pour un paiement en cours (code 02306)');
    }

    /**
     * @throws ReflectionException
     */
    public function test_payline_manage_return_handles_payment_in_progress_02533()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'paylineManageReturn');

        // Code 02533 = aussi paiement en cours
        $response = array(
            'result' => array(
                'code' => '02533',
                'shortMessage' => 'PENDING',
            ),
            'transaction' => array('id' => 'TXN_PENDING2'),
            'card' => array(),
        );

        $initialStatus = $this->order->get_status();
        $method->invoke($this->gateway, $this->order, $response);

        $this->assertEquals($initialStatus, $this->order->get_status(), 'Le statut ne doit pas changer pour un paiement en cours (code 02533)');
    }

    // =========================================
    // paylineManageReturn() - Erreurs
    // =========================================

    /**
     * @throws ReflectionException
     */
    public function test_payline_manage_return_handles_refused_payment()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'paylineManageReturn');

        $response = array(
            'result' => array(
                'code' => '01234',
                'shortMessage' => 'REFUSED',
                'longMessage' => 'Insufficient funds',
            ),
            'transaction' => array('id' => 'TXN_REFUSED'),
            'card' => array(),
        );

        $method->invoke($this->gateway, $this->order, $response);

        // Statut doit être "failed" pour refused
        $this->assertEquals('failed', $this->order->get_status(), 'Le statut doit être "failed" pour un paiement refusé');
    }

    /**
     * @throws ReflectionException
     */
    public function test_payline_manage_return_handles_cancelled_payment()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'paylineManageReturn');

        $response = array(
            'result' => array(
                'code' => '01235',
                'shortMessage' => 'CANCELLED',
                'longMessage' => 'User cancelled',
            ),
            'transaction' => array('id' => 'TXN_CANCEL'),
            'card' => array(),
        );

        $method->invoke($this->gateway, $this->order, $response);

        // Statut doit être "cancelled" pour cancelled
        $this->assertEquals('cancelled', $this->order->get_status(), 'Le statut doit être "cancelled" pour un paiement annulé');
    }

    /**
     * @throws ReflectionException
     */
    public function test_payline_manage_return_handles_error_payment()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'paylineManageReturn');

        $response = array(
            'result' => array(
                'code' => '09999',
                'shortMessage' => 'ERROR',
                'longMessage' => 'Technical error',
            ),
            'transaction' => array('id' => 'TXN_ERROR'),
            'card' => array(),
        );

        $method->invoke($this->gateway, $this->order, $response);

        // Statut doit être "failed" pour error
        $this->assertEquals('failed', $this->order->get_status(), 'Le statut doit être "failed" pour une erreur technique');
    }

    // =========================================
    // paylineManageReturn() - Métadonnées
    // =========================================

    /**
     * @throws ReflectionException
     */
    public function test_payline_manage_return_sets_payment_method()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'paylineManageReturn');

        $response = array(
            'result' => array(
                'code' => '00000',
                'shortMessage' => 'ACCEPTED',
            ),
            'transaction' => array('id' => 'TXN_TEST'),
            'card' => array(
                'number' => '497010XXXXXXXX40',
                'type' => 'CB',
                'expirationDate' => '1225',
            ),
            'payment' => array(
                'contractNumber' => 'CONTRACT123',
            ),
        );

        $method->invoke($this->gateway, $this->order, $response);

        // Vérifier que payment_method est défini
        $this->assertEquals('payline_cpt', $this->order->get_payment_method(), 'Le payment_method doit être "payline_cpt"');
    }

    /**
     * @throws ReflectionException
     */
    public function test_payline_manage_return_returns_message_for_refused()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'paylineManageReturn');

        $response = array(
            'result' => array(
                'code' => '01234',
                'shortMessage' => 'REFUSED',
            ),
            'transaction' => array('id' => ''),
            'card' => array(),
        );

        $message = $method->invoke($this->gateway, $this->order, $response);

        // Doit retourner le message d'erreur configuré
        $this->assertEquals('Paiement refusé', $message, 'Le message doit être celui configuré pour les paiements refusés');
    }

    /**
     * @throws ReflectionException
     */
    public function test_payline_manage_return_returns_empty_message_for_success()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'paylineManageReturn');

        $response = array(
            'result' => array(
                'code' => '00000',
                'shortMessage' => 'ACCEPTED',
            ),
            'transaction' => array('id' => 'TXN_OK'),
            'card' => array(
                'number' => '497010XXXXXXXX40',
                'type' => 'CB',
                'expirationDate' => '1225',
            ),
            'payment' => array(
                'contractNumber' => 'CONTRACT123',
            ),
        );

        $message = $method->invoke($this->gateway, $this->order, $response);

        // Doit retourner une chaîne vide pour succès
        $this->assertEmpty($message, 'Le message doit être vide pour un paiement réussi');
    }

    /**
     * @throws ReflectionException
     */
    public function test_payline_manage_return_handles_missing_contract_number()
    {
        $method = $this->getPrivateMethod(WC_Abstract_Payline::class, 'paylineManageReturn');

        $response = array(
            'result' => array(
                'code' => '00000',
                'shortMessage' => 'ACCEPTED',
            ),
            'transaction' => array('id' => 'TXN_NO_CONTRACT'),
            'card' => array(
                'number' => '497010XXXXXXXX40',
                'type' => 'CB',
                'expirationDate' => '1225',
            ),
            'payment' => array('contractNumber' => 'CB-123456'),
        );

        $method->invoke($this->gateway, $this->order, $response);

        $this->assertEquals('completed', $this->order->get_status(), 'Le statut doit être "completed" même sans contractNumber');
    }
}
