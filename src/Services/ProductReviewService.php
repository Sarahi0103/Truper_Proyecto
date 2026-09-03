<?php
/**
 * Product Review Service
 * Sistema de reviews y ratings de productos
 */

class ProductReviewService {
    private $pdo;
    private $logger;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->logger = new AppLogger();
    }
    
    /**
     * Crear nueva review de producto
     * 
     * @param array $reviewData Datos de la review
     * @return array Resultado de la operación
     */
    public function createReview($reviewData) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO product_reviews 
                (product_id, user_id, order_id, rating, title, review, pros, cons, would_recommend)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                RETURNING id
            ");
            
            $stmt->execute([
                $reviewData['product_id'],
                $reviewData['user_id'],
                $reviewData['order_id'] ?? null,
                $reviewData['rating'],
                $reviewData['title'] ?? null,
                $reviewData['review'] ?? null,
                $reviewData['pros'] ?? null,
                $reviewData['cons'] ?? null,
                $reviewData['would_recommend'] ?? true
            ]);
            
            $reviewId = $stmt->fetchColumn();
            
            // Marcar como compra verificada si hay order_id
            if (!empty($reviewData['order_id'])) {
                $this->pdo->prepare("SELECT mark_review_as_verified(?, ?)")
                    ->execute([$reviewId, $reviewData['order_id']]);
            }
            
            $this->logger->info("Review created for product {$reviewData['product_id']} by user {$reviewData['user_id']}");
            
            return [
                'success' => true,
                'review_id' => $reviewId
            ];
        } catch (Exception $e) {
            $this->logger->error("Error creating review: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al crear review'
            ];
        }
    }
    
    /**
     * Obtener reviews de un producto
     * 
     * @param int $productId ID del producto
     * @param bool $onlyApproved Solo reviews aprobadas
     * @return array Lista de reviews
     */
    public function getProductReviews($productId, $onlyApproved = true) {
        try {
            $where = $onlyApproved ? "WHERE pr.is_approved = true" : "";
            
            $stmt = $this->pdo->prepare("
                SELECT pr.*, 
                       u.first_name, u.last_name,
                       CASE 
                           WHEN pr.created_at > NOW() - INTERVAL '30 days' THEN true
                           ELSE false
                       END as is_recent
                FROM product_reviews pr
                LEFT JOIN users u ON pr.user_id = u.id
                {$where} AND pr.product_id = ?
                ORDER BY pr.created_at DESC
            ");
            $stmt->execute([$productId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Error getting product reviews: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener rating promedio de un producto
     * 
     * @param int $productId ID del producto
     * @return array Datos de rating
     */
    public function getProductRating($productId) {
        try {
            $stmt = $this->pdo->prepare("SELECT get_product_rating(?) AS result");
            $stmt->execute([$productId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return json_decode($result['result'], true);
        } catch (Exception $e) {
            $this->logger->error("Error getting product rating: " . $e->getMessage());
            return [
                'average_rating' => 0,
                'total_reviews' => 0,
                'rating_distribution' => []
            ];
        }
    }
    
    /**
     * Obtener reviews de un usuario
     * 
     * @param int $userId ID del usuario
     * @return array Lista de reviews
     */
    public function getUserReviews($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT pr.*, p.name as product_name, p.image_url
                FROM product_reviews pr
                LEFT JOIN products p ON pr.product_id = p.id
                WHERE pr.user_id = ?
                ORDER BY pr.created_at DESC
            ");
            $stmt->execute([$userId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Error getting user reviews: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Marcar review como útil
     * 
     * @param int $reviewId ID de la review
     * @param int $userId ID del usuario que vota
     * @return array Resultado de la operación
     */
    public function markReviewHelpful($reviewId, $userId) {
        try {
            // Verificar si ya votó
            $stmt = $this->pdo->prepare("
                SELECT id FROM review_helpful_votes 
                WHERE review_id = ? AND user_id = ?
            ");
            $stmt->execute([$reviewId, $userId]);
            
            if ($stmt->fetch()) {
                return ['success' => false, 'error' => 'Ya votaste como útil'];
            }
            
            // Agregar voto
            $stmt = $this->pdo->prepare("
                INSERT INTO review_helpful_votes (review_id, user_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$reviewId, $userId]);
            
            // Incrementar contador
            $this->pdo->prepare("
                UPDATE product_reviews 
                SET helpful_count = helpful_count + 1
                WHERE id = ?
            ")->execute([$reviewId]);
            
            return ['success' => true];
        } catch (Exception $e) {
            $this->logger->error("Error marking review helpful: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al marcar como útil'
            ];
        }
    }
    
    /**
     * Aprobar review (solo admin)
     * 
     * @param int $reviewId ID de la review
     * @return array Resultado de la operación
     */
    public function approveReview($reviewId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE product_reviews 
                SET is_approved = true, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$reviewId]);
            
            $this->logger->info("Review {$reviewId} approved");
            
            return ['success' => true];
        } catch (Exception $e) {
            $this->logger->error("Error approving review: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al aprobar review'
            ];
        }
    }
    
    /**
     * Eliminar review
     * 
     * @param int $reviewId ID de la review
     * @param int $userId ID del usuario (para verificar propiedad)
     * @return array Resultado de la operación
     */
    public function deleteReview($reviewId, $userId = null) {
        try {
            // Si se proporciona userId, verificar que la review pertenezca al usuario
            if ($userId) {
                $stmt = $this->pdo->prepare("
                    SELECT id FROM product_reviews 
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$reviewId, $userId]);
                
                if (!$stmt->fetch()) {
                    return ['success' => false, 'error' => 'Review no encontrada o no pertenece al usuario'];
                }
            }
            
            $stmt = $this->pdo->prepare("DELETE FROM product_reviews WHERE id = ?");
            $stmt->execute([$reviewId]);
            
            $this->logger->info("Review {$reviewId} deleted");
            
            return ['success' => true];
        } catch (Exception $e) {
            $this->logger->error("Error deleting review: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al eliminar review'
            ];
        }
    }
}
