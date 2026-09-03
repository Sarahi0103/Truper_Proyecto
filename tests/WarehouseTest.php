<?php
/**
 * Warehouse Test Suite
 * Truper Platform
 */

class WarehouseTest {
    public static function run() {
        global $pdo;
        
        try {
            require_once __DIR__ . '/../src/Services/WarehouseService.php';
            $service = new WarehouseService($pdo);

            // 1. Obtener almacenes activos
            $warehouses = $service->getActiveWarehouses();
            if (empty($warehouses)) {
                return [
                    'name' => 'Multi-Warehouse Structure Test',
                    'passed' => false,
                    'message' => 'No se encontraron almacenes activos en la base de datos'
                ];
            }

            $mainWarehouse = $warehouses[0];
            $warehouseId = $mainWarehouse['id'];

            // 2. Obtener un producto válido para la prueba
            $stmtProd = $pdo->query("SELECT id FROM products LIMIT 1");
            $productId = $stmtProd->fetchColumn();

            if (!$productId) {
                // Crear un producto de prueba si la tabla estuviera vacía
                $stmtInsert = $pdo->prepare("INSERT INTO products (sku, name, unit_price, stock_quantity) VALUES ('TEST-WH-SKU', 'Producto Prueba Warehouse', 99.00, 10) RETURNING id");
                $stmtInsert->execute();
                $productId = $stmtInsert->fetchColumn();
            }

            $setResult = $service->setStock($warehouseId, $productId, 45, 'A1', 'E3');
            if (!$setResult) {
                return [
                    'name' => 'Warehouse Stock Set Test',
                    'passed' => false,
                    'message' => 'Fallo al registrar stock por almacén'
                ];
            }

            // 3. Consultar stock del producto
            $productStocks = $service->getStockByProduct($productId);
            if (empty($productStocks)) {
                return [
                    'name' => 'Warehouse Stock Query Test',
                    'passed' => false,
                    'message' => 'Fallo al consultar existencias por almacén'
                ];
            }

            return [
                'name' => 'Warehouse & Multi-Branch Test',
                'passed' => true,
                'message' => "Almacenes activos: " . count($warehouses) . ". Stock verificado correctamente para almacén {$mainWarehouse['code']}."
            ];
        } catch (Exception $e) {
            return [
                'name' => 'Warehouse Test Suite',
                'passed' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
