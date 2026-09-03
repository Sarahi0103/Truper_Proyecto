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
            $stmt = $this->pdo->prepare("SELECT add_to_wishlist(?, ?) as result");
            $stmt->execute([$userId, $productId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $wishlistId = (int)$result['result'];
            
            if ($wishlistId > 0) {
                $this->logger->info("Product {$productId} added to wishlist for user {$userId}");
                return [
                    'success' => true,
                    'wishlist_id' => $wishlistId
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'El producto ya está en tu wishlist'
                ];
            }
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
            $stmt = $this->pdo->prepare("SELECT remove_from_wishlist(?, ?) as result");
            $stmt->execute([$userId, $productId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $removed = (bool)$result['result'];
            
            if ($removed) {
                $this->logger->info("Product {$productId} removed from wishlist for user {$userId}");
                return [
                    'success' => true
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'El producto no está en tu wishlist'
                ];
            }
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
            $stmt = $this->pdo->prepare("SELECT * FROM get_user_wishlist(?)");
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
            $stmt = $this->pdo->prepare("SELECT is_in_wishlist(?, ?) as result");
            $stmt->execute([$userId, $productId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return (bool)$result['result'];
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
