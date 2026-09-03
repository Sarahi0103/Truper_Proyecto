<?php
/**
 * Kardex Service
 * Registro contable de movimientos de inventario y auditoría de existencias
 * Truper Platform
 */

class KardexService {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Registrar un movimiento de inventario en el Kardex
     * 
     * @param int $productId ID del producto
     * @param string $movementType initial, purchase, sale, return, transfer_in, transfer_out, adjustment, scrap
     * @param int $quantity Cantidad (+ para entradas, - para salidas)
     * @param float $unitCost Costo unitario
     * @param float $unitPrice Precio unitario
     * @param string $referenceFolio Folio de ticket, orden, factura o ajuste
     * @param int|null $warehouseId ID del almacén
     * @param int|null $userId ID del usuario responsable
     * @param string $notes Notas adicionales
     */
    public function recordMovement($productId, $movementType, $quantity, $unitCost = 0, $unitPrice = 0, $referenceFolio = null, $warehouseId = null, $userId = null, $notes = '') {
        try {
            // Obtener el saldo actual de stock del producto
            $stmt = $this->pdo->prepare("SELECT stock_quantity FROM products WHERE id = ? FOR UPDATE");
            $stmt->execute([$productId]);
            $currentStock = (int)$stmt->fetchColumn();

            // Calcular nuevo saldo
            $newBalance = $currentStock + $quantity;
            if ($newBalance < 0) {
                $newBalance = 0; // Prevenir existencias negativas en balance visual
            }

            // Actualizar stock del producto maestro
            $stmtUpdate = $this->pdo->prepare("UPDATE products SET stock_quantity = ?, updated_at = NOW() WHERE id = ?");
            $stmtUpdate->execute([$newBalance, $productId]);

            // Insertar registro en kardex
            $stmtKardex = $this->pdo->prepare("
                INSERT INTO inventory_kardex 
                (product_id, warehouse_id, movement_type, quantity, unit_cost, unit_price, balance_quantity, reference_folio, notes, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                RETURNING id
            ");
            $stmtKardex->execute([
                $productId,
                $warehouseId,
                $movementType,
                $quantity,
                $unitCost,
                $unitPrice,
                $newBalance,
                $referenceFolio,
                $notes,
                $userId
            ]);

            return [
                'success' => true,
                'kardex_id' => $stmtKardex->fetchColumn(),
                'new_balance' => $newBalance
            ];
        } catch (Exception $e) {
            error_log("Error in KardexService::recordMovement: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al registrar movimiento en kardex: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener movimientos de Kardex por producto con paginación y filtros
     */
    public function getMovements($productId = null, $startDate = null, $endDate = null, $movementType = null, $limit = 50, $offset = 0) {
        try {
            $where = ["1=1"];
            $params = [];

            if (!empty($productId)) {
                $where[] = "k.product_id = ?";
                $params[] = $productId;
            }
            if (!empty($startDate)) {
                $where[] = "k.created_at >= ?";
                $params[] = $startDate . " 00:00:00";
            }
            if (!empty($endDate)) {
                $where[] = "k.created_at <= ?";
                $params[] = $endDate . " 23:59:59";
            }
            if (!empty($movementType) && $movementType !== 'all') {
                $where[] = "k.movement_type = ?";
                $params[] = $movementType;
            }

            $whereSql = implode(" AND ", $where);

            $stmt = $this->pdo->prepare("
                SELECT k.*, p.name AS product_name, p.sku AS product_sku,
                       w.name AS warehouse_name,
                       u.first_name || ' ' || u.last_name AS user_name
                FROM inventory_kardex k
                JOIN products p ON k.product_id = p.id
                LEFT JOIN warehouses w ON k.warehouse_id = w.id
                LEFT JOIN users u ON k.created_by = u.id
                WHERE {$whereSql}
                ORDER BY k.created_at DESC, k.id DESC
                LIMIT ? OFFSET ?
            ");

            $bindIndex = 1;
            foreach ($params as $param) {
                $stmt->bindValue($bindIndex++, $param);
            }
            $stmt->bindValue($bindIndex++, (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue($bindIndex++, (int)$offset, PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in KardexService::getMovements: " . $e->getMessage());
            return [];
        }
    }
}
