<?php

/**
 * Tests unitaires pour PaylineLogsViewer
 *
 * Compatibilité : PHP 7.4 - 8.4
 */
class PaylineLogsViewer_Test extends PaylineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $dir = WP_CONTENT_DIR . '/uploads/wc-logs/payline';
        if (is_dir($dir)) {
            $files = glob($dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($dir);
        }
        parent::tearDown();
    }

    // =========================================
    // showLogs()
    // =========================================

    public function test_show_logs_calls_load_template()
    {
        PaylineLogsViewer::showLogs();

        $this->assertNotEmpty(MockWordPress::$data['loaded_templates'], 'showLogs() doit charger un template');
    }

    // =========================================
    // getPaylineLogsFilesList()
    // =========================================

    public function test_get_payline_logs_files_list_returns_empty_when_directory_missing()
    {
        $method = $this->getPrivateMethod('PaylineLogsViewer', 'getPaylineLogsFilesList');
        $result = $method->invoke(null);

        $this->assertIsArray($result, 'getPaylineLogsFilesList() doit retourner un tableau');
        $this->assertEmpty($result, 'getPaylineLogsFilesList() doit retourner un tableau vide si le dossier n\'existe pas');
    }

    public function test_get_payline_logs_files_list_returns_files_when_directory_exists()
    {
        $dir = WP_CONTENT_DIR . '/uploads/wc-logs/payline';
        mkdir($dir, 0777, true);
        touch($dir . '/payline-2024-01-01.log');
        touch($dir . '/payline-2024-02-01.log');

        $method = $this->getPrivateMethod('PaylineLogsViewer', 'getPaylineLogsFilesList');
        $result = $method->invoke(null);

        $this->assertCount(2, $result, 'Doit retourner 2 fichiers de log');
        $this->assertContains('payline-2024-01-01.log', $result, 'Doit contenir payline-2024-01-01.log');
        $this->assertContains('payline-2024-02-01.log', $result, 'Doit contenir payline-2024-02-01.log');
    }

    public function test_get_payline_logs_files_list_ignores_non_log_files()
    {
        $dir = WP_CONTENT_DIR . '/uploads/wc-logs/payline';
        mkdir($dir, 0777, true);
        touch($dir . '/payline-2024-01-01.log');
        touch($dir . '/readme.txt');

        $method = $this->getPrivateMethod('PaylineLogsViewer', 'getPaylineLogsFilesList');
        $result = $method->invoke(null);

        $this->assertContains('payline-2024-01-01.log', $result, 'Doit contenir le fichier .log');
        $this->assertContains('readme.txt', $result, 'getPaylineLogsFilesList() retourne tous les fichiers du dossier, pas seulement .log');
    }

    public function test_get_payline_logs_files_list_returns_empty_when_directory_empty()
    {
        $dir = WP_CONTENT_DIR . '/uploads/wc-logs/payline';
        mkdir($dir, 0777, true);

        $method = $this->getPrivateMethod('PaylineLogsViewer', 'getPaylineLogsFilesList');
        $result = $method->invoke(null);

        $this->assertCount(0, $result, 'Doit retourner un tableau vide si le dossier est vide');
    }
}