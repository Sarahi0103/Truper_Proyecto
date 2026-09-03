<?php
/**
 * Loyalty Points Service
 * Sistema de puntos de lealtad y recompensas
 */

class LoyaltyService {
    private $pdo;
    private $logger;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->logger = new AppLogger();
    }
    
    /**
     * Agregar puntos a un usuario
     * 
     * @param int $userId ID del usuario
     * @param int $points Puntos a agregar
     * @param string $transactionType Tipo de transacción
     * @param int|null $referenceId ID de referencia
     * @param string|null $referenceType Tipo de referencia
     * @param string|null $description Descripción
     * @return array Resultado de la operación
     */
    public function addPoints($userId, $points, $transactionType, $referenceId = null, $referenceType = null, $description = null) {
        try {
            $stmt = $this->pdo->prepare("SELECT add_loyalty_points(?, ?, ?, ?, ?, ?) as result");
            $stmt->execute([$userId, $points, $transactionType, $referenceId, $referenceType, $description]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $currentPoints = (int)$result['result'];
            
            $this->logger->info("Added {$points} points to user {$userId}, new balance: {$currentPoints}");
            
            return [
                'success' => true,
                'current_points' => $currentPoints
            ];
        } catch (Exception $e) {
            $this->logger->error("Error adding loyalty points: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al agregar puntos'
            ];
        }
    }
    
    /**
     * Redimir puntos de un usuario
     * 
     * @param int $userId ID del usuario
     * @param int $points Puntos a redimir
     * @param int|null $referenceId ID de referencia
     * @param string|null $referenceType Tipo de referencia
     * @param string|null $description Descripción
     * @return array Resultado de la operación
     */
    public function redeemPoints($userId, $points, $referenceId = null, $referenceType = null, $description = null) {
        try {
            $stmt = $this->pdo->prepare("SELECT redeem_loyalty_points(?, ?, ?, ?, ?) as result");
            $stmt->execute([$userId, $points, $referenceId, $referenceType, $description]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $redeemed = (bool)$result['result'];
            
            if ($redeemed) {
                $this->logger->info("Redeemed {$points} points from user {$userId}");
                return [
                    'success' => true
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Puntos insuficientes'
                ];
            }
        } catch (Exception $e) {
            $this->logger->error("Error redeeming loyalty points: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al redimir puntos'
            ];
        }
    }
    
    /**
     * Obtener balance de puntos de un usuario
     * 
     * @param int $userId ID del usuario
     * @return array Balance de puntos
     */
    public function getUserBalance($userId) {
        try {
            $stmt = $this->pdo->prepare("SELECT get_user_points_balance(?) as result");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return json_decode($result['result'], true);
        } catch (Exception $e) {
            $this->logger->error("Error getting user points balance: " . $e->getMessage());
            return [
                'points' => 0,
                'tier' => 'bronze',
                'total_earned' => 0,
                'total_redeemed' => 0
            ];
        }
    }
    
    /**
     * Calcular puntos de una orden
     * 
     * @param float $orderAmount Monto de la orden
     * @return int Puntos calculados
     */
    public function calculateOrderPoints($orderAmount) {
        return floor($orderAmount / 100);
    }
    
    /**
     * Obtener historial de transacciones de puntos
     * 
     * @param int $userId ID del usuario
     * @param int $limit Límite de resultados
     * @return array Historial de transacciones
     */
    public function getTransactionHistory($userId, $limit = 20) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM point_transactions
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$userId, $limit]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Error getting transaction history: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener recompensas disponibles por nivel
     * 
     * @param string $tier Nivel del usuario
     * @return array Recompensas disponibles
     */
    public function getAvailableRewards($tier) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM loyalty_rewards
                WHERE is_active = true
                AND (tier = ? OR tier = 'all')
                ORDER BY points_required ASC
            ");
            $stmt->execute([$tier]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Error getting available rewards: " . $e->getMessage());
            return [];
        }
    }
}
