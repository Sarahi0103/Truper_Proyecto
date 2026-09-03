<?php
/**
 * Inventory Service
 * Sistema de gestión de inventario separado (online vs local)
 */

class InventoryService {
    private $pdo;
    private $logger;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->logger = new AppLogger();
    }
    
    /**
     * Verificar disponibilidad online de un producto
     * 
     * @param int $productId ID del producto
     * @param int $quantity Cantidad requerida
     * @return array Resultado de la verificación
     */
    public function checkOnlineAvailability($productId, $quantity) {
        try {
            $stmt = $this->pdo->prepare("SELECT check_online_availability(?, ?) AS result");
            $stmt->execute([$productId, $quantity]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return json_decode($result['result'], true);
        } catch (Exception $e) {
            $this->logger->error("Error checking online availability: " . $e->getMessage());
            return [
                'available' => false,
                'message' => 'Error al verificar disponibilidad'
            ];
        }
    }
    
    /**
     * Deducir stock online
     * 
     * @param int $productId ID del producto
     * @param int $quantity Cantidad a deducir
     * @return array Resultado de la operación
     */
    public function deductOnlineStock($productId, $quantity) {
        try {
            $stmt = $this->pdo->prepare("SELECT deduct_online_stock(?, ?) AS result");
            $stmt->execute([$productId, $quantity]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return json_decode($result['result'], true);
        } catch (Exception $e) {
            $this->logger->error("Error deducting online stock: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al deducir stock'
            ];
        }
    }
    
    /**
     * Transferir stock de local a online
     * 
     * @param int $productId ID del producto
     * @param int $quantity Cantidad a transferir
     * @return array Resultado de la operación
     */
    public function transferStockToOnline($productId, $quantity) {
        try {
            $stmt = $this->pdo->prepare("SELECT transfer_stock_to_online(?, ?) AS result");
            $stmt->execute([$productId, $quantity]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return json_decode($result['result'], true);
        } catch (Exception $e) {
            $this->logger->error("Error transferring stock to online: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al transferir stock'
            ];
        }
    }
    
    /**
     * Transferir stock de online a local
     * 
     * @param int $productId ID del producto
     * @param int $quantity Cantidad a transferir
     * @return array Resultado de la operación
     */
    public function transferStockToLocal($productId, $quantity) {
        try {
            $stmt = $this->pdo->prepare("SELECT transfer_stock_to_local(?, ?) AS result");
            $stmt->execute([$productId, $quantity]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return json_decode($result['result'], true);
        } catch (Exception $e) {
            $this->logger->error("Error transferring stock to local: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al transferir stock'
            ];
        }
    }
    
    /**
     * Obtener productos con bajo stock online
     * 
     * @return array Lista de productos con bajo stock
     */
    public function getLowStockOnline() {
        try {
            $stmt = $this->pdo->query("
                SELECT id, name, sku, stock_online, low_stock_threshold_online
                FROM low_stock_online
                ORDER BY stock_online ASC
            ");
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Error getting low stock online: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener inventario completo de un producto
     * 
     * @param int $productId ID del producto
     * @return array|null Datos de inventario
     */
    public function getProductInventory($productId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, name, sku, stock_quantity, stock_online, stock_local,
                       low_stock_threshold_online, low_stock_threshold_local
                FROM products
                WHERE id = ?
            ");
            $stmt->execute([$productId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            $this->logger->error("Error getting product inventory: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Actualizar umbrales de bajo stock
     * 
     * @param int $productId ID del producto
     * @param int $onlineThreshold Umbral online
     * @param int $localThreshold Umbral local
     * @return array Resultado de la operación
     */
    public function updateLowStockThresholds($productId, $onlineThreshold, $localThreshold) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE products
                SET low_stock_threshold_online = ?,
                    low_stock_threshold_local = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$onlineThreshold, $localThreshold, $productId]);
            
            return ['success' => true];
        } catch (Exception $e) {
            $this->logger->error("Error updating low stock thresholds: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al actualizar umbrales'
            ];
        }
    }
}
