<?php

use PHPUnit\Framework\TestCase;

/**
 * Classe de base pour tous les tests Payline
 *
 * Reset automatique de tous les mocks et de l'état statique
 * du SDK entre chaque test pour garantir l'isolation.
 *
 * Fournit des helpers pour accéder aux méthodes/propriétés privées
 * et des utilitaires communs aux tests.
 */
abstract class PaylineTestCase extends TestCase
{
    /**
     * Setup exécuté avant chaque test
     *
     * Reset tous les mocks et l'état statique du SDK pour
     * garantir l'isolation entre les tests.
     */
    protected function setUp(): void
    {
        $this->resetAllMocks();
        $this->resetSdkStaticState();
    }

    /**
     * Reset tous les mocks WordPress/WooCommerce
     *
     * Remet les fixtures à leur état initial pour garantir
     * que chaque test démarre avec un état propre.
     */
    private function resetAllMocks()
    {
        MockOptions::reset();
        MockPlugins::reset();
        MockWordPress::reset();

        if (class_exists('MockGatewayPayline')) {
            MockGatewayPayline::reset();
        }
    }

    /**
     * Reset la propriété statique $merchantSettings du SDK
     *
     * Nécessaire car elle persiste entre tests et peut causer
     * des faux positifs si des tests précédents ont fait un
     * appel API réussi.
     */
    private function resetSdkStaticState()
    {
        if (!class_exists('WC_Payline_SDK')) {
            return;
        }

        $ref = new ReflectionClass(WC_Payline_SDK::class);
        $prop = $ref->getProperty('merchantSettings');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }

    /**
     * Helper pour accéder aux méthodes privées/protégées
     *
     * Utilise Reflection pour rendre une méthode privée accessible
     * pendant les tests.
     *
     * @param string $className Nom complet de la classe (ex: WC_Payline_SDK::class)
     * @param string $methodName Nom de la méthode
     * @return ReflectionMethod Méthode accessible
     *
     * @example
     *   $isValid = $this->getPrivateMethod(WC_Payline_SDK::class, 'isValidResponse');
     *   $result = $isValid->invoke(null, $data);
     */
    protected function getPrivateMethod($className, $methodName)
    {
        $method = new ReflectionMethod($className, $methodName);
        $method->setAccessible(true);
        return $method;
    }

    /**
     * Helper pour accéder aux propriétés privées/protégées
     *
     * Utilise Reflection pour rendre une propriété privée accessible
     * pendant les tests.
     *
     * @param string $className Nom complet de la classe
     * @param string $propertyName Nom de la propriété
     * @return ReflectionProperty Propriété accessible
     *
     * @example
     *   $fixtures = $this->getPrivateProperty(MockOptions::class, 'fixtures');
     *   $value = $fixtures->getValue();
     */
    protected function getPrivateProperty($className, $propertyName)
    {
        $property = new ReflectionProperty($className, $propertyName);
        $property->setAccessible(true);
        return $property;
    }

    /**
     * Crée un tableau de menu items par défaut pour les tests Wallet
     *
     * Retourne la structure standard du menu "Mon Compte" WooCommerce
     * utilisée dans les tests de PaylineWallet.
     *
     * @return array Menu items avec clés => libellés
     */
    protected function getBaseMenuItems()
    {
        return array(
            'dashboard' => 'Tableau de bord',
            'orders' => 'Commandes',
            'downloads' => 'Téléchargements',
            'edit-address' => 'Adresses',
            'edit-account' => 'Détails du compte',
            'customer-logout' => 'Se déconnecter',
        );
    }

    /**
     * Configure les settings gateway et réinstancie
     *
     * Helper pour configurer les options WordPress avant d'instancier
     * une gateway de paiement. Les settings globaux et CPT sont mergés
     * par completeSettings() lors de l'instanciation.
     *
     * IMPORTANT : Pour tester les credentials manquants, utiliser
     * unsetGatewaySettings() car les fixtures CPT contiennent aussi
     * merchant_id et access_key.
     *
     * @param array $globalSettings Settings à définir dans woocommerce_payline_settings
     * @param array $cptSettings Settings à définir dans woocommerce_payline_cpt_settings
     * @param string $gatewayClass Classe de gateway à instancier (défaut: WC_Gateway_Payline_CPT)
     * @return WC_Payment_Gateway Instance de la gateway créée
     *
     * @example
     *   $gateway = $this->setGatewaySettings([
     *       'merchant_id' => 'MERCH',
     *       'access_key' => 'KEY',
     *       'pos' => 'POS1',
     *   ]);
     */
    protected function setGatewaySettings(array $globalSettings, array $cptSettings = array(), $gatewayClass = 'WC_Gateway_Payline_CPT')
    {
        foreach ($globalSettings as $key => $value) {
            MockOptions::$data['woocommerce_payline_settings'][$key] = $value;
        }

        foreach ($cptSettings as $key => $value) {
            MockOptions::$data['woocommerce_payline_cpt_settings'][$key] = $value;
        }

        return new $gatewayClass();
    }

    /**
     * Supprime des clés des settings et réinstancie la gateway
     *
     * Helper pour tester les cas où des credentials/options sont manquants.
     *
     * IMPORTANT : completeSettings() merge les settings globaux ET CPT,
     * donc pour tester merchant_id ou access_key manquants, il faut les
     * supprimer des DEUX tableaux.
     *
     * @param array $globalKeys Clés à supprimer de woocommerce_payline_settings
     * @param array $cptKeys Clés à supprimer de woocommerce_payline_cpt_settings
     * @param string $gatewayClass Classe de gateway à instancier (défaut: WC_Gateway_Payline_CPT)
     * @return WC_Payment_Gateway Instance de la gateway créée
     *
     * @example
     *   // Tester merchant_id manquant
     *   $gateway = $this->unsetGatewaySettings(
     *       array('merchant_id'),  // globalKeys
     *       array('merchant_id')   // cptKeys
     *   );
     */
    protected function unsetGatewaySettings(array $globalKeys = array(), array $cptKeys = array(), $gatewayClass = 'WC_Gateway_Payline_CPT')
    {
        foreach ($globalKeys as $key) {
            unset(MockOptions::$data['woocommerce_payline_settings'][$key]);
        }

        foreach ($cptKeys as $key) {
            unset(MockOptions::$data['woocommerce_payline_cpt_settings'][$key]);
        }

        return new $gatewayClass();
    }

    /**
     * Re-charge le bootstrap dans un processus séparé
     *
     * Nécessaire pour les tests avec @runInSeparateProcess car les mocks
     * WordPress/WooCommerce ne sont pas automatiquement disponibles dans
     * le nouveau processus.
     *
     * Utilisation :
     * - Ajouter @runInSeparateProcess et @preserveGlobalState disabled
     * - Appeler $this->reloadBootstrap() au début du test
     *
     * @example
     *   // @runInSeparateProcess
     *   // @preserveGlobalState disabled
     *   public function test_with_separate_process()
     *   {
     *       $this->reloadBootstrap();
     *       // ... test code
     *   }
     */
    protected function reloadBootstrap()
    {
        require_once __DIR__ . '/bootstrap.php';
    }
}
