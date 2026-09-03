<?php
/**
 * Coupon Service
 * Sistema de gestión de cupones y descuentos
 */

class CouponService {
    private $pdo;
    private $logger;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->logger = new AppLogger();
    }
    
    /**
     * Validar un cupón de descuento
     * 
     * @param string $code Código del cupón
     * @param int|null $userId ID del usuario
     * @param float $cartTotal Total del carrito
     * @param string|null $userSegment Segmento del cliente
     * @return array Resultado de la validación
     */
    public function validateCoupon($code, $userId = null, $cartTotal = 0, $userSegment = null) {
        try {
            $stmt = $this->pdo->prepare("SELECT validate_coupon(?, ?, ?, ?) AS result");
            $stmt->execute([$code, $userId, $cartTotal, $userSegment]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $decoded = json_decode($result['result'], true);
            
            if ($decoded['valid'] ?? false) {
                $this->logger->info("Coupon {$code} validated for user {$userId}, discount: {$decoded['discount_amount']}");
            } else {
                $this->logger->info("Coupon {$code} validation failed: {$decoded['error']}");
            }
            
            return $decoded;
        } catch (Exception $e) {
            $this->logger->error("Error validating coupon: " . $e->getMessage());
            return [
                'valid' => false,
                'error' => 'Error al validar cupón'
            ];
        }
    }
    
    /**
     * Aplicar cupón a un pedido
     * 
     * @param int $couponId ID del cupón
     * @param int $orderId ID del pedido
     * @param int|null $userId ID del usuario
     * @param float $discountAmount Monto del descuento aplicado
     * @return array Resultado de la operación
     */
    public function applyCoupon($couponId, $orderId, $userId = null, $discountAmount = 0) {
        try {
            $stmt = $this->pdo->prepare("SELECT apply_coupon(?, ?, ?, ?) AS result");
            $stmt->execute([$couponId, $orderId, $userId, $discountAmount]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $decoded = json_decode($result['result'], true);
            
            if ($decoded['success'] ?? false) {
                $this->logger->info("Coupon {$couponId} applied to order {$orderId}");
            }
            
            return $decoded;
        } catch (Exception $e) {
            $this->logger->error("Error applying coupon: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al aplicar cupón'
            ];
        }
    }
    
    /**
     * Obtener lista de cupones activos
     * 
     * @param bool $onlyActive Solo cupones activos
     * @return array Lista de cupones
     */
    public function getCoupons($onlyActive = true) {
        try {
            $where = $onlyActive ? "WHERE is_active = true AND valid_until >= CURRENT_TIMESTAMP" : "";
            
            $stmt = $this->pdo->prepare("
                SELECT id, code, description, discount_type, discount_value, 
                       min_purchase_amount, max_discount_amount, usage_limit, usage_count,
                       valid_from, valid_until, is_active
                FROM coupons
                {$where}
                ORDER BY created_at DESC
            ");
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Error getting coupons: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Crear un nuevo cupón
     * 
     * @param array $couponData Datos del cupón
     * @return array Resultado de la operación
     */
    public function createCoupon($couponData) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO coupons (code, description, discount_type, discount_value, 
                                     min_purchase_amount, max_discount_amount, usage_limit,
                                     valid_from, valid_until, applicable_to_segments, applicable_to_categories)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                RETURNING id
            ");
            
            $stmt->execute([
                $couponData['code'],
                $couponData['description'] ?? null,
                $couponData['discount_type'],
                $couponData['discount_value'],
                $couponData['min_purchase_amount'] ?? 0,
                $couponData['max_discount_amount'] ?? null,
                $couponData['usage_limit'] ?? null,
                $couponData['valid_from'] ?? date('Y-m-d H:i:s'),
                $couponData['valid_until'],
                $couponData['applicable_to_segments'] ?? null,
                $couponData['applicable_to_categories'] ?? null
            ]);
            
            $couponId = $stmt->fetchColumn();
            
            $this->logger->info("Coupon {$couponData['code']} created with ID {$couponId}");
            
            return [
                'success' => true,
                'coupon_id' => $couponId
            ];
        } catch (Exception $e) {
            $this->logger->error("Error creating coupon: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al crear cupón'
            ];
        }
    }
    
    /**
     * Actualizar un cupón existente
     * 
     * @param int $couponId ID del cupón
     * @param array $couponData Datos a actualizar
     * @return array Resultado de la operación
     */
    public function updateCoupon($couponId, $couponData) {
        try {
            $setParts = [];
            $params = [];
            
            foreach ($couponData as $key => $value) {
                if ($value !== null) {
                    $setParts[] = "{$key} = ?";
                    $params[] = $value;
                }
            }
            
            if (empty($setParts)) {
                return ['success' => false, 'error' => 'No hay datos para actualizar'];
            }
            
            $params[] = $couponId;
            
            $sql = "UPDATE coupons SET " . implode(', ', $setParts) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            $this->logger->info("Coupon {$couponId} updated");
            
            return ['success' => true];
        } catch (Exception $e) {
            $this->logger->error("Error updating coupon: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al actualizar cupón'
            ];
        }
    }
    
    /**
     * Eliminar un cupón (desactivar)
     * 
     * @param int $couponId ID del cupón
     * @return array Resultado de la operación
     */
    public function deleteCoupon($couponId) {
        try {
            $stmt = $this->pdo->prepare("UPDATE coupons SET is_active = false WHERE id = ?");
            $stmt->execute([$couponId]);
            
            $this->logger->info("Coupon {$couponId} deactivated");
            
            return ['success' => true];
        } catch (Exception $e) {
            $this->logger->error("Error deactivating coupon: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al desactivar cupón'
            ];
        }
    }
    
    /**
     * Obtener estadísticas de uso de cupones
     * 
     * @return array Estadísticas
     */
    public function getCouponStats() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(*) as total_coupons,
                    COUNT(*) FILTER (WHERE is_active = true) as active_coupons,
                    SUM(usage_count) as total_uses,
                    SUM(discount_amount) as total_discount_given
                FROM coupons
            ");
            
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Usos por cupón
            $stmt = $this->pdo->query("
                SELECT c.code, c.usage_count, COUNT(cu.id) as actual_uses
                FROM coupons c
                LEFT JOIN coupon_usage cu ON c.id = cu.coupon_id
                GROUP BY c.id, c.code
                ORDER BY c.usage_count DESC
                LIMIT 10
            ");
            
            $topCoupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'overview' => $stats,
                'top_coupons' => $topCoupons
            ];
        } catch (Exception $e) {
            $this->logger->error("Error getting coupon stats: " . $e->getMessage());
            return [];
        }
    }
}
