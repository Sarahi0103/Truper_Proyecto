<?php
/**
 * Backorder Service
 * Gestión de pedidos pendientes por falta de stock y resurtidos
 * Truper Platform
 */

class BackorderService {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Registrar faltante de un producto en un pedido
     */
    public function registerBackorder($orderId, $productId, $requestedQty, $estimatedArrival = null, $notes = '') {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO order_backorders 
                (order_id, product_id, requested_qty, fulfilled_qty, status, estimated_arrival, notes, created_at, updated_at)
                VALUES (?, ?, ?, 0, 'waiting_stock', ?, ?, NOW(), NOW())
                RETURNING id
            ");
            $stmt->execute([$orderId, $productId, $requestedQty, $estimatedArrival, $notes]);
            return [
                'success' => true,
                'backorder_id' => $stmt->fetchColumn()
            ];
        } catch (Exception $e) {
            error_log("Error in BackorderService::registerBackorder: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Obtener backorders pendientes con datos de producto y cliente
     */
    public function getPendingBackorders() {
        try {
            $stmt = $this->pdo->query("
                SELECT bo.*, p.sku, p.name AS product_name, p.stock_quantity AS current_stock,
                       o.order_number, u.first_name, u.last_name, u.email, u.phone
                FROM order_backorders bo
                JOIN products p ON bo.product_id = p.id
                JOIN orders o ON bo.order_id = o.id
                JOIN clients c ON o.client_id = c.id
                JOIN users u ON c.user_id = u.id
                WHERE bo.status IN ('waiting_stock', 'partially_fulfilled')
                ORDER BY bo.created_at ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getPendingBackorders: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Procesar surtido de un backorder
     */
    public function fulfillBackorder($backorderId, $fulfilledQuantity) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM order_backorders WHERE id = ?");
            $stmt->execute([$backorderId]);
            $backorder = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$backorder) {
                return ['success' => false, 'message' => 'Backorder no encontrado'];
            }

            $newFulfilled = $backorder['fulfilled_qty'] + $fulfilledQuantity;
            $newStatus = ($newFulfilled >= $backorder['requested_qty']) ? 'fulfilled' : 'partially_fulfilled';

            $stmtUpdate = $this->pdo->prepare("
                UPDATE order_backorders 
                SET fulfilled_qty = ?, status = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmtUpdate->execute([$newFulfilled, $newStatus, $backorderId]);

            return [
                'success' => true,
                'status' => $newStatus,
                'fulfilled_qty' => $newFulfilled
            ];
        } catch (Exception $e) {
            error_log("Error in fulfillBackorder: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
