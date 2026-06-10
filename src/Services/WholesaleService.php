<?php
/**
 * Servicio para sistema de Mayoreo
 * Maneja descuentos escalonados, límites de crédito y validación de RFC
 */

class WholesaleService {
    private $pdo;
    private $cacheService;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->cacheService = new DashboardCacheService();
    }

    /**
     * Calcular descuento mayoreo para un producto
     */
    public function calculateDiscount($productId, $quantity) {
        $cacheKey = 'wholesale_discount_' . $productId . '_' . $quantity;
        $cached = apcu_fetch($cacheKey, $success);

        if ($success) {
            return $cached;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT * FROM calculate_wholesale_discount(?, ?)");
            $stmt->execute([$productId, $quantity]);
            $result = $stmt->fetch();

            if ($result) {
                apcu_store($cacheKey, $result, 1800); // 30 minutos
                return $result;
            }

            return [
                'discount_percent' => 0,
                'discount_price' => null,
                'pricing_tier' => 'retail'
            ];
        } catch (Exception $e) {
            error_log("Error calculating wholesale discount: " . $e->getMessage());
            return [
                'discount_percent' => 0,
                'discount_price' => null,
                'pricing_tier' => 'retail'
            ];
        }
    }

    /**
     * Verificar límite de crédito del cliente
     */
    public function checkCreditLimit($clientId, $amount) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM check_credit_limit(?, ?)");
            $stmt->execute([$clientId, $amount]);
            $result = $stmt->fetch();

            return $result ?: [
                'can_purchase' => false,
                'remaining_credit' => 0,
                'credit_status' => 'blocked'
            ];
        } catch (Exception $e) {
            error_log("Error checking credit limit: " . $e->getMessage());
            return [
                'can_purchase' => false,
                'remaining_credit' => 0,
                'credit_status' => 'error'
            ];
        }
    }

    /**
     * Validar RFC mexicano
     */
    public function validateRFC($rfc) {
        if (!$rfc) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT validate_rfc(?) AS is_valid");
            $stmt->execute([$rfc]);
            return (bool)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Error validating RFC: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Sanitizar RFC
     */
    public function sanitizeRFC($rfc) {
        if (!$rfc) {
            return null;
        }

        // Eliminar espacios y convertir a mayúsculas
        $rfc = strtoupper(trim(preg_replace('/\s+/', '', $rfc)));

        // Validar formato
        if (!$this->validateRFC($rfc)) {
            return null;
        }

        return $rfc;
    }

    /**
     * Validar monto de compra
     */
    public function validateAmount($amount) {
        if (!is_numeric($amount)) {
            return false;
        }

        $amount = floatval($amount);

        // Prevenir montos negativos
        if ($amount < 0) {
            return false;
        }

        // Prevenir montos excesivos (máximo $10,000,000 para mayoreo)
        if ($amount > 10000000) {
            return false;
        }

        return true;
    }

    /**
     * Obtener historial de compras mayoreo del cliente
     */
    public function getPurchaseHistory($clientId, $limit = 50, $offset = 0) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM wholesale_purchase_history
                WHERE client_id = ?
                ORDER BY purchase_date DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$clientId, $limit, $offset]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getting purchase history: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Registrar compra mayoreo en historial
     */
    public function recordPurchase($clientId, $orderId, $totalAmount, $discountPercent, $discountAmount, $finalAmount, $paymentMethod) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO wholesale_purchase_history
                (client_id, order_id, total_amount, discount_applied, discount_amount, final_amount, payment_method)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $clientId,
                $orderId,
                $totalAmount,
                $discountPercent,
                $discountAmount,
                $finalAmount,
                $paymentMethod
            ]);

            // Actualizar saldo del cliente
            $this->updateClientBalance($clientId, $finalAmount);

            return true;
        } catch (Exception $e) {
            error_log("Error recording purchase: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualizar saldo del cliente
     */
    private function updateClientBalance($clientId, $amount) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE clients
                SET current_balance = current_balance + ?,
                    credit_status = CASE
                        WHEN (credit_limit - (current_balance + ?)) < 0 THEN 'blocked'
                        WHEN (credit_limit - (current_balance + ?)) < (credit_limit * 0.2) THEN 'warning'
                        ELSE 'good'
                    END
                WHERE id = ?
            ");
            $stmt->execute([$amount, $amount, $amount, $clientId]);
        } catch (Exception $e) {
            error_log("Error updating client balance: " . $e->getMessage());
        }
    }

    /**
     * Invalidar caché de precios mayoreo
     */
    public function invalidatePriceCache($productId = null) {
        if ($productId) {
            $iterator = new APCUIterator('/^wholesale_discount_' . $productId . '_/');
            apcu_delete($iterator);
        } else {
            $iterator = new APCUIterator('/^wholesale_discount_/');
            apcu_delete($iterator);
        }
        return true;
    }
}
