<?php
/**
 * Kardex Test Suite
 * Truper Platform
 */

class KardexTest {
    public static function run() {
        global $pdo;

        try {
            require_once __DIR__ . '/../src/Services/KardexService.php';
            $service = new KardexService($pdo);

            // Obtener un producto existente
            $stmt = $pdo->query("SELECT id FROM products LIMIT 1");
            $productId = $stmt->fetchColumn();

            if (!$productId) {
                return [
                    'name' => 'Kardex Test Suite',
                    'passed' => false,
                    'message' => 'No existen productos en la base de datos para probar kardex'
                ];
            }

            // Registrar movimiento de prueba
            $result = $service->recordMovement(
                $productId,
                'adjustment',
                5,
                150.00,
                199.00,
                'TEST-KARDEX-' . uniqid(),
                null,
                1,
                'Prueba unitaria de Kardex'
            );

            if (!$result['success']) {
                return [
                    'name' => 'Kardex Movement Registration',
                    'passed' => false,
                    'message' => $result['message'] ?? 'Fallo al registrar movimiento'
                ];
            }

            // Consultar movimientos
            $movements = $service->getMovements($productId, null, null, 'adjustment', 10);
            if (empty($movements)) {
                return [
                    'name' => 'Kardex Query Test',
                    'passed' => false,
                    'message' => 'No se encontraron movimientos registrados en la consulta'
                ];
            }

            return [
                'name' => 'Kardex Traceability Test',
                'passed' => true,
                'message' => "Movimiento de Kardex registrado exitosamente (ID: {$result['kardex_id']}, Nuevo saldo: {$result['new_balance']})."
            ];
        } catch (Exception $e) {
            return [
                'name' => 'Kardex Test Suite',
                'passed' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
