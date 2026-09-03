<?php
/**
 * Stock Reservation Service
 * Sistema de reservas temporales de stock para evitar overselling
 * Usa funciones de PostgreSQL para gestión de reservas
 */

class StockReservationService {
    private $pdo;
    private $logger;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->logger = new AppLogger();
    }
    
    /**
     * Crear reserva de stock para un producto
     * 
     * @param int $productId ID del producto
     * @param int $quantity Cantidad a reservar
     * @param int|null $userId ID del usuario (si está autenticado)
     * @param string|null $sessionId ID de sesión (para usuarios no autenticados)
     * @param int $expiresMinutes Minutos para expiración (default: 15)
     * @return array Resultado de la operación
     */
    public function createReservation($productId, $quantity, $userId = null, $sessionId = null, $expiresMinutes = 15) {
        try {
            $stmt = $this->pdo->prepare("SELECT create_stock_reservation(?, ?, ?, ?, ?) AS result");
            $stmt->execute([$productId, $quantity, $userId, $sessionId, $expiresMinutes]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $decoded = json_decode($result['result'], true);
            
            if ($decoded['success'] ?? false) {
                $this->logger->info("Stock reservation created: Product {$productId}, Qty {$quantity}, Reservation ID {$decoded['reservation_id']}");
            } else {
                $this->logger->warning("Stock reservation failed: Product {$productId}, Qty {$quantity}, Reason: {$decoded['message']}");
            }
            
            return $decoded;
        } catch (Exception $e) {
            $this->logger->error("Error creating stock reservation: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al crear reserva de stock'
            ];
        }
    }
    
    /**
     * Confirmar reserva cuando se completa el pago
     * 
     * @param int $reservationId ID de la reserva
     * @param int $orderId ID del pedido
     * @return array Resultado de la operación
     */
    public function confirmReservation($reservationId, $orderId) {
        try {
            $stmt = $this->pdo->prepare("SELECT confirm_stock_reservation(?, ?) AS result");
            $stmt->execute([$reservationId, $orderId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $decoded = json_decode($result['result'], true);
            
            if ($decoded['success'] ?? false) {
                $this->logger->info("Stock reservation confirmed: Reservation {$reservationId}, Order {$orderId}");
            } else {
                $this->logger->warning("Stock reservation confirmation failed: Reservation {$reservationId}");
            }
            
            return $decoded;
        } catch (Exception $e) {
            $this->logger->error("Error confirming stock reservation: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al confirmar reserva de stock'
            ];
        }
    }
    
    /**
     * Cancelar reserva
     * 
     * @param int $reservationId ID de la reserva
     * @return array Resultado de la operación
     */
    public function cancelReservation($reservationId) {
        try {
            $stmt = $this->pdo->prepare("SELECT cancel_stock_reservation(?) AS result");
            $stmt->execute([$reservationId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $decoded = json_decode($result['result'], true);
            
            if ($decoded['success'] ?? false) {
                $this->logger->info("Stock reservation cancelled: Reservation {$reservationId}");
            }
            
            return $decoded;
        } catch (Exception $e) {
            $this->logger->error("Error cancelling stock reservation: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al cancelar reserva de stock'
            ];
        }
    }
    
    /**
     * Obtener stock disponible (considerando reservas activas)
     * 
     * @param int $productId ID del producto
     * @return int Stock disponible
     */
    public function getAvailableStock($productId) {
        try {
            $stmt = $this->pdo->prepare("SELECT get_available_stock(?) AS available");
            $stmt->execute([$productId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return (int)($result['available'] ?? 0);
        } catch (Exception $e) {
            $this->logger->error("Error getting available stock: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Crear múltiples reservas para un carrito
     * 
     * @param array $cartItems Items del carrito (con product_id y quantity)
     * @param int|null $userId ID del usuario
     * @param string|null $sessionId ID de sesión
     * @return array Resultado con reservas creadas
     */
    public function createCartReservations($cartItems, $userId = null, $sessionId = null) {
        $reservations = [];
        $allSuccessful = true;
        
        foreach ($cartItems as $item) {
            $productId = $item['product_id'] ?? $item['id'] ?? null;
            $quantity = $item['quantity'] ?? 1;
            
            if (!$productId || $quantity <= 0) {
                continue;
            }
            
            $result = $this->createReservation($productId, $quantity, $userId, $sessionId);
            
            if (!($result['success'] ?? false)) {
                $allSuccessful = false;
                // Cancelar todas las reservas creadas anteriormente
                foreach ($reservations as $prevReservation) {
                    if (isset($prevReservation['reservation_id'])) {
                        $this->cancelReservation($prevReservation['reservation_id']);
                    }
                }
                return [
                    'success' => false,
                    'message' => "Stock insuficiente para producto ID {$productId}",
                    'available' => $result['available'] ?? 0,
                    'requested' => $result['requested'] ?? $quantity
                ];
            }
            
            $reservations[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'reservation_id' => $result['reservation_id']
            ];
        }
        
        return [
            'success' => $allSuccessful,
            'reservations' => $reservations
        ];
    }
    
    /**
     * Confirmar todas las reservas de un pedido
     * 
     * @param array $reservations Lista de reservas
     * @param int $orderId ID del pedido
     * @return array Resultado de la operación
     */
    public function confirmCartReservations($reservations, $orderId) {
        $confirmed = [];
        $failed = [];
        
        foreach ($reservations as $reservation) {
            $result = $this->confirmReservation($reservation['reservation_id'], $orderId);
            
            if ($result['success'] ?? false) {
                $confirmed[] = $reservation['reservation_id'];
            } else {
                $failed[] = $reservation['reservation_id'];
            }
        }
        
        return [
            'success' => empty($failed),
            'confirmed' => $confirmed,
            'failed' => $failed
        ];
    }
    
    /**
     * Cancelar todas las reservas de una sesión
     * 
     * @param string $sessionId ID de sesión
     * @return int Número de reservas canceladas
     */
    public function cancelSessionReservations($sessionId) {
        try {
            $stmt = $this->pdo->prepare("UPDATE stock_reservations SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE session_id = ? AND status = 'pending'");
            $stmt->execute([$sessionId]);
            
            $count = $stmt->rowCount();
            $this->logger->info("Cancelled {$count} reservations for session {$sessionId}");
            
            return $count;
        } catch (Exception $e) {
            $this->logger->error("Error cancelling session reservations: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Limpiar reservas expiradas (para cron job)
     * 
     * @return int Número de reservas limpiadas
     */
    public function cleanupExpiredReservations() {
        try {
            $stmt = $this->pdo->query("SELECT cleanup_expired_reservations() AS cleaned");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $count = (int)($result['cleaned'] ?? 0);
            $this->logger->info("Cleaned up {$count} expired stock reservations");
            
            return $count;
        } catch (Exception $e) {
            $this->logger->error("Error cleaning up expired reservations: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Obtener reserva por ID
     * 
     * @param int $reservationId ID de la reserva
     * @return array|null Datos de la reserva
     */
    public function getReservation($reservationId) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM stock_reservations WHERE id = ?");
            $stmt->execute([$reservationId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Error getting reservation: " . $e->getMessage());
            return null;
        }
    }
}
