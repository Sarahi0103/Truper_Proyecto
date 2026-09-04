<?php
/**
 * Wishlist Service
 * Sistema de favoritos/wishlist de productos
 */

class WishlistService {
    private $pdo;
    private $logger;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->logger = new AppLogger();
    }
    
    /**
     * Agregar producto a wishlist
     * 
     * @param int $userId ID del usuario
     * @param int $productId ID del producto
     * @return array Resultado de la operación
     */
    public function addToWishlist($userId, $productId) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO wishlist (user_id, product_id, created_at)
                VALUES (?, ?, NOW())
                ON CONFLICT (user_id, product_id) DO NOTHING
                RETURNING id
            ");
            $stmt->execute([$userId, $productId]);
            $wishlistId = $stmt->fetchColumn();
            
            $this->logger->info("Product {$productId} added to wishlist for user {$userId}");
            return [
                'success' => true,
                'wishlist_id' => $wishlistId ? (int)$wishlistId : 1
            ];
        } catch (Exception $e) {
            $this->logger->error("Error adding to wishlist: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al agregar a wishlist'
            ];
        }
    }
    
    /**
     * Eliminar producto de wishlist
     * 
     * @param int $userId ID del usuario
     * @param int $productId ID del producto
     * @return array Resultado de la operación
     */
    public function removeFromWishlist($userId, $productId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$userId, $productId]);
            
            $this->logger->info("Product {$productId} removed from wishlist for user {$userId}");
            return [
                'success' => true
            ];
        } catch (Exception $e) {
            $this->logger->error("Error removing from wishlist: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al eliminar de wishlist'
            ];
        }
    }
    
    /**
     * Obtener wishlist de un usuario
     * 
     * @param int $userId ID del usuario
     * @return array Lista de productos en wishlist
     */
    public function getUserWishlist($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    p.id,
                    p.name,
                    p.sku,
                    COALESCE(p.price_online, p.unit_price, p.sell_price, 0) AS price,
                    COALESCE(p.image_url, 'images/products/default-product.svg') AS image_url,
                    COALESCE(p.stock_online, p.stock_quantity, 0) AS stock,
                    COALESCE(p.category, 'General') AS category,
                    w.created_at
                FROM wishlist w
                JOIN products p ON w.product_id = p.id
                WHERE w.user_id = ? AND p.deleted_at IS NULL
                ORDER BY w.created_at DESC
            ");
            $stmt->execute([$userId]);
            $wishlist = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'wishlist' => $wishlist
            ];
        } catch (Exception $e) {
            $this->logger->error("Error getting wishlist: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al obtener wishlist'
            ];
        }
    }
    
    /**
     * Verificar si producto está en wishlist
     * 
     * @param int $userId ID del usuario
     * @param int $productId ID del producto
     * @return bool True si está en wishlist
     */
    public function isInWishlist($userId, $productId) {
        try {
            $stmt = $this->pdo->prepare("SELECT 1 FROM wishlist WHERE user_id = ? AND product_id = ? LIMIT 1");
            $stmt->execute([$userId, $productId]);
            return (bool)$stmt->fetchColumn();
        } catch (Exception $e) {
            $this->logger->error("Error checking wishlist: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Limpiar wishlist de un usuario
     * 
     * @param int $userId ID del usuario
     * @return array Resultado de la operación
     */
    public function clearWishlist($userId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM wishlist WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            $this->logger->info("Wishlist cleared for user {$userId}");
            
            return [
                'success' => true
            ];
        } catch (Exception $e) {
            $this->logger->error("Error clearing wishlist: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al limpiar wishlist'
            ];
        }
    }
}
