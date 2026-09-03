<?php
/**
 * Warehouse Service
 * Gestión de almacenes, sucursales, traspasos y stock multi-bodega
 * Truper Platform
 */

class WarehouseService {
    private $pdo;
    private $logger;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        if (class_exists('AppLogger')) {
            $this->logger = new AppLogger();
        }
    }

    /**
     * Obtener todos los almacenes activos
     */
    public function getActiveWarehouses() {
        try {
            $stmt = $this->pdo->query("
                SELECT id, code, name, address, city, state, postal_code, phone, is_main, is_active
                FROM warehouses
                WHERE is_active = true
                ORDER BY is_main DESC, name ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getActiveWarehouses: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener stock de un producto en todos los almacenes
     */
    public function getStockByProduct($productId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT w.id AS warehouse_id, w.code AS warehouse_code, w.name AS warehouse_name,
                       w.is_main, COALESCE(ws.stock_quantity, 0) AS stock_quantity,
                       ws.aisle, ws.shelf, ws.min_stock, ws.max_stock
                FROM warehouses w
                LEFT JOIN warehouse_stock ws ON w.id = ws.warehouse_id AND ws.product_id = ?
                WHERE w.is_active = true
                ORDER BY w.is_main DESC, w.name ASC
            ");
            $stmt->execute([$productId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getStockByProduct: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Ajustar o registrar stock en un almacén específico
     */
    public function setStock($warehouseId, $productId, $quantity, $aisle = null, $shelf = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO warehouse_stock (warehouse_id, product_id, stock_quantity, aisle, shelf, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW())
                ON CONFLICT (warehouse_id, product_id)
                DO UPDATE SET 
                    stock_quantity = EXCLUDED.stock_quantity,
                    aisle = COALESCE(EXCLUDED.aisle, warehouse_stock.aisle),
                    shelf = COALESCE(EXCLUDED.shelf, warehouse_stock.shelf),
                    updated_at = NOW()
            ");
            return $stmt->execute([$warehouseId, $productId, $quantity, $aisle, $shelf]);
        } catch (Exception $e) {
            error_log("Error in setStock: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Crear un traspaso de mercancía entre almacenes
     */
    public function createTransfer($fromWarehouseId, $toWarehouseId, $items, $userId, $notes = '') {
        if ($fromWarehouseId == $toWarehouseId) {
            return ['success' => false, 'message' => 'El almacén de origen y destino no pueden ser el mismo'];
        }

        if (empty($items)) {
            return ['success' => false, 'message' => 'Debe especificar al menos un producto'];
        }

        try {
            $this->pdo->beginTransaction();

            $transferNumber = 'TRF-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

            $stmt = $this->pdo->prepare("
                INSERT INTO stock_transfers (transfer_number, from_warehouse_id, to_warehouse_id, status, requested_by, notes)
                VALUES (?, ?, ?, 'pending', ?, ?)
                RETURNING id
            ");
            $stmt->execute([$transferNumber, $fromWarehouseId, $toWarehouseId, $userId, $notes]);
            $transferId = $stmt->fetchColumn();

            $stmtItem = $this->pdo->prepare("
                INSERT INTO stock_transfer_items (transfer_id, product_id, quantity, notes)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($items as $item) {
                $productId = (int)$item['product_id'];
                $quantity = (int)$item['quantity'];
                $itemNotes = $item['notes'] ?? '';

                if ($quantity <= 0) continue;
                $stmtItem->execute([$transferId, $productId, $quantity, $itemNotes]);
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'transfer_id' => $transferId,
                'transfer_number' => $transferNumber,
                'message' => 'Traspaso creado exitosamente'
            ];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Error creating stock transfer: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al crear traspaso: ' . $e->getMessage()];
        }
    }

    /**
     * Completar y recibir un traspaso (afecta inventario en ambos almacenes)
     */
    public function completeTransfer($transferId, $receivedByUserId) {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("
                SELECT * FROM stock_transfers WHERE id = ? FOR UPDATE
            ");
            $stmt->execute([$transferId]);
            $transfer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$transfer) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Traspaso no encontrado'];
            }

            if ($transfer['status'] === 'completed') {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'El traspaso ya fue completado previamente'];
            }

            // Obtener items
            $stmtItems = $this->pdo->prepare("SELECT * FROM stock_transfer_items WHERE transfer_id = ?");
            $stmtItems->execute([$transferId]);
            $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

            $stmtDeduct = $this->pdo->prepare("
                UPDATE warehouse_stock 
                SET stock_quantity = GREATEST(0, stock_quantity - ?), updated_at = NOW()
                WHERE warehouse_id = ? AND product_id = ?
            ");

            $stmtAdd = $this->pdo->prepare("
                INSERT INTO warehouse_stock (warehouse_id, product_id, stock_quantity, updated_at)
                VALUES (?, ?, ?, NOW())
                ON CONFLICT (warehouse_id, product_id)
                DO UPDATE SET stock_quantity = warehouse_stock.stock_quantity + EXCLUDED.stock_quantity, updated_at = NOW()
            ");

            foreach ($items as $item) {
                $qty = (int)$item['quantity'];
                $prodId = (int)$item['product_id'];

                // Descontar origen
                $stmtDeduct->execute([$qty, $transfer['from_warehouse_id'], $prodId]);
                // Aumentar destino
                $stmtAdd->execute([$transfer['to_warehouse_id'], $prodId, $qty]);
                
                // Actualizar cantidad recibida en el item
                $this->pdo->prepare("UPDATE stock_transfer_items SET received_quantity = ? WHERE id = ?")
                    ->execute([$qty, $item['id']]);
            }

            // Marcar traspaso como completado
            $stmtUpdate = $this->pdo->prepare("
                UPDATE stock_transfers 
                SET status = 'completed', received_by = ?, received_date = NOW(), updated_at = NOW()
                WHERE id = ?
            ");
            $stmtUpdate->execute([$receivedByUserId, $transferId]);

            $this->pdo->commit();
            return ['success' => true, 'message' => 'Traspaso procesado y aplicado al inventario'];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Error completing stock transfer: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al completar traspaso: ' . $e->getMessage()];
        }
    }
}
