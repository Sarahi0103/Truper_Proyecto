<?php
/**
 * Supply Chain Service
 * Sistema de abastecimiento, gestión de proveedores, predicción de demanda
 */

class SupplyChainService {
    private $pdo;
    private $logger;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->logger = new AppLogger();
    }
    
    /**
     * Crear pedido de compra
     * 
     * @param array $orderData Datos del pedido
     * @return array Resultado de la operación
     */
    public function createPurchaseOrder($orderData) {
        try {
            $stmt = $this->pdo->prepare("SELECT create_purchase_order(?, ?, ?, ?) as result");
            $stmt->execute([
                $orderData['supplier_id'],
                $orderData['expected_delivery_date'],
                $orderData['notes'] ?? null,
                $orderData['created_by']
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $orderId = (int)$result['result'];
            
            $this->logger->info("Purchase order created: {$orderId}");
            
            return [
                'success' => true,
                'order_id' => $orderId
            ];
        } catch (Exception $e) {
            $this->logger->error("Error creating purchase order: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al crear pedido de compra'
            ];
        }
    }
    
    /**
     * Agregar item a pedido de compra
     * 
     * @param int $purchaseOrderId ID del pedido
     * @param array $itemData Datos del item
     * @return array Resultado de la operación
     */
    public function addPurchaseOrderItem($purchaseOrderId, $itemData) {
        try {
            $stmt = $this->pdo->prepare("SELECT add_purchase_order_item(?, ?, ?, ?) as result");
            $stmt->execute([
                $purchaseOrderId,
                $itemData['product_id'],
                $itemData['quantity'],
                $itemData['unit_cost']
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $itemId = (int)$result['result'];
            
            return [
                'success' => true,
                'item_id' => $itemId
            ];
        } catch (Exception $e) {
            $this->logger->error("Error adding purchase order item: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al agregar item al pedido'
            ];
        }
    }
    
    /**
     * Recibir pedido de compra
     * 
     * @param int $purchaseOrderId ID del pedido
     * @return array Resultado de la operación
     */
    public function receivePurchaseOrder($purchaseOrderId) {
        try {
            $stmt = $this->pdo->prepare("SELECT receive_purchase_order(?) as result");
            $stmt->execute([$purchaseOrderId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $received = (bool)$result['result'];
            
            if ($received) {
                $this->logger->info("Purchase order {$purchaseOrderId} received");
                return [
                    'success' => true
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Pedido no encontrado'
                ];
            }
        } catch (Exception $e) {
            $this->logger->error("Error receiving purchase order: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al recibir pedido'
            ];
        }
    }
    
    /**
     * Obtener sugerencias de reabastecimiento
     * 
     * @return array Sugerencias de reabastecimiento
     */
    public function getRestockSuggestions() {
        try {
            $stmt = $this->pdo->query("SELECT * FROM get_restock_suggestions()");
            $suggestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'suggestions' => $suggestions
            ];
        } catch (Exception $e) {
            $this->logger->error("Error getting restock suggestions: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al obtener sugerencias'
            ];
        }
    }
    
    /**
     * Obtener proveedores activos
     * 
     * @return array Lista de proveedores
     */
    public function getActiveSuppliers() {
        try {
            $stmt = $this->pdo->query("
                SELECT * FROM suppliers
                WHERE is_active = true
                ORDER BY name ASC
            ");
            $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'suppliers' => $suppliers
            ];
        } catch (Exception $e) {
            $this->logger->error("Error getting suppliers: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al obtener proveedores'
            ];
        }
    }
    
    /**
     * Crear proveedor
     * 
     * @param array $supplierData Datos del proveedor
     * @return array Resultado de la operación
     */
    public function createSupplier($supplierData) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO suppliers (name, contact_name, email, phone, address, rfc, tax_regime, payment_terms, lead_time_days, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                RETURNING id
            ");
            
            $stmt->execute([
                $supplierData['name'],
                $supplierData['contact_name'] ?? null,
                $supplierData['email'] ?? null,
                $supplierData['phone'] ?? null,
                $supplierData['address'] ?? null,
                $supplierData['rfc'] ?? null,
                $supplierData['tax_regime'] ?? null,
                $supplierData['payment_terms'] ?? null,
                $supplierData['lead_time_days'] ?? 7,
                $supplierData['notes'] ?? null
            ]);
            
            $supplierId = $stmt->fetchColumn();
            
            $this->logger->info("Supplier created: {$supplierId}");
            
            return [
                'success' => true,
                'supplier_id' => $supplierId
            ];
        } catch (Exception $e) {
            $this->logger->error("Error creating supplier: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al crear proveedor'
            ];
        }
    }
    
    /**
     * Obtener pedidos de compra pendientes
     * 
     * @return array Pedidos pendientes
     */
    public function getPendingPurchaseOrders() {
        try {
            $stmt = $this->pdo->query("
                SELECT po.*, s.name as supplier_name
                FROM purchase_orders po
                LEFT JOIN suppliers s ON po.supplier_id = s.id
                WHERE po.status IN ('pending', 'sent')
                ORDER BY po.order_date ASC
            ");
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'orders' => $orders
            ];
        } catch (Exception $e) {
            $this->logger->error("Error getting pending purchase orders: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al obtener pedidos pendientes'
            ];
        }
    }
}
