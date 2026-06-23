<?php
/**
 * Test: Database Connection & Persistent Connection Option
 */

class DatabaseTest {
    public static function run(): array {
        $results = ['name' => 'Database & Persistent Connection Test', 'passed' => false, 'message' => ''];
        try {
            // Load DB
            $pdo = require __DIR__ . '/../config/database.php';
            
            if (!$pdo instanceof PDO) {
                $results['message'] = "La conexión PDO no es una instancia de PDO.";
                return $results;
            }

            // Verify config value vs actual PDO setting
            $envPersistent = strtolower((string)(getenv('DB_PERSISTENT') ?: 'false')) === 'true';
            $actualPersistent = $pdo->getAttribute(PDO::ATTR_PERSISTENT);
            
            if ($envPersistent !== $actualPersistent) {
                $results['message'] = "Diferencia en DB_PERSISTENT. Env: " . ($envPersistent ? 'true' : 'false') . ", PDO real: " . ($actualPersistent ? 'true' : 'false');
                return $results;
            }

            // Basic select query
            $stmt = $pdo->query("SELECT 1");
            $val = $stmt->fetchColumn();
            if ($val !== 1 && $val !== '1') {
                $results['message'] = "La consulta SELECT 1 retornó un valor inesperado: " . var_export($val, true);
                return $results;
            }

            $results['passed'] = true;
            $results['message'] = "Conexión a BD verificada. Conexiones persistentes configuradas como: " . ($actualPersistent ? 'ACTIVADAS' : 'DESACTIVADAS');
        } catch (Exception $e) {
            $results['message'] = "Excepción capturada: " . $e->getMessage();
        }
        return $results;
    }
}
