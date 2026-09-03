<?php
/**
 * Refund Service
 * Sistema de reembolsos automáticos y notas de crédito
 */

class RefundService {
    private $pdo;
    private $logger;
    private $paymentGateway;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->logger = new AppLogger();
        $this->paymentGateway = new PaymentGatewayService($pdo);
    }
    
    /**
     * Crear solicitud de reembolso
     * 
     * @param array $refundData Datos del reembolso
     * @return array Resultado de la operación
     */
    public function createRefund($refundData) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO refunds (order_id, payment_id, refund_amount, refund_reason, refund_method)
                VALUES (?, ?, ?, ?, ?)
                RETURNING id
            ");
            
            $stmt->execute([
                $refundData['order_id'],
                $refundData['payment_id'] ?? null,
                $refundData['refund_amount'],
                $refundData['refund_reason'],
                $refundData['refund_method'] ?? 'cash'
            ]);
            
            $refundId = $stmt->fetchColumn();
            
            $this->logger->info("Refund request created: {$refundId} for order {$refundData['order_id']}");
            
            return [
                'success' => true,
                'refund_id' => $refundId
            ];
        } catch (Exception $e) {
            $this->logger->error("Error creating refund: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al crear solicitud de reembolso'
            ];
        }
    }
    
    /**
     * Procesar reembolso automáticamente
     * 
     * @param int $refundId ID del reembolso
     * @param int $processedBy ID del usuario que procesa
     * @return array Resultado de la operación
     */
    public function processRefund($refundId, $processedBy) {
        try {
            // Obtener datos del reembolso
            $stmt = $this->pdo->prepare("
                SELECT r.*, p.payment_method, p.transaction_id, p.amount as payment_amount
                FROM refunds r
                LEFT JOIN payments p ON r.payment_id = p.id
                WHERE r.id = ?
            ");
            $stmt->execute([$refundId]);
            $refund = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$refund) {
                return [
                    'success' => false,
                    'error' => 'Reembolso no encontrado'
                ];
            }
            
            $refundTransactionId = null;
            
            // Procesar según método de pago original
            switch ($refund['payment_method']) {
                case 'card':
                case 'stripe':
                    // Reembolso vía Stripe
                    $stripeResult = $this->paymentGateway->processStripeRefund(
                        $refund['refund_amount'],
                        $refund['transaction_id']
                    );
                    
                    if ($stripeResult['success']) {
                        $refundTransactionId = $stripeResult['refund_id'];
                    } else {
                        return [
                            'success' => false,
                            'error' => 'Error al procesar reembolso Stripe: ' . ($stripeResult['message'] ?? 'Desconocido')
                        ];
                    }
                    break;
                    
                case 'mercadopago':
                    // Reembolso vía Mercado Pago
                    $mpResult = $this->paymentGateway->processMercadoPagoRefund(
                        $refund['refund_amount'],
                        $refund['transaction_id']
                    );
                    
                    if ($mpResult['success']) {
                        $refundTransactionId = $mpResult['refund_id'];
                    } else {
                        return [
                            'success' => false,
                            'error' => 'Error al procesar reembolso Mercado Pago: ' . ($mpResult['message'] ?? 'Desconocido')
                        ];
                    }
                    break;
                    
                case 'cash':
                case 'transfer':
                    // Reembolso manual (efectivo o transferencia)
                    $refundTransactionId = 'MANUAL-' . time();
                    break;
                    
                default:
                    return [
                        'success' => false,
                        'error' => 'Método de pago no soportado para reembolso automático'
                    ];
            }
            
            // Actualizar estado del reembolso
            $stmt = $this->pdo->prepare("
                UPDATE refunds
                SET refund_status = 'completed',
                    refund_transaction_id = ?,
                    processed_by = ?,
                    processed_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$refundTransactionId, $processedBy, $refundId]);
            
            // Actualizar estado del pago
            $stmt = $this->pdo->prepare("
                UPDATE payments
                SET payment_status = 'refunded'
                WHERE id = ?
            ");
            $stmt->execute([$refund['payment_id']]);
            
            // Actualizar estado de la orden si es reembolso total
            $stmt = $this->pdo->prepare("
                SELECT SUM(refund_amount) as total_refunded
                FROM refunds
                WHERE order_id = ? AND refund_status = 'completed'
            ");
            $stmt->execute([$refund['order_id']]);
            $totalRefunded = $stmt->fetchColumn();
            
            if ($totalRefunded >= $refund['payment_amount']) {
                $stmt = $this->pdo->prepare("
                    UPDATE orders
                    SET payment_status = 'refunded',
                        status = 'refunded'
                    WHERE id = ?
                ");
                $stmt->execute([$refund['order_id']]);
            }
            
            $this->logger->info("Refund processed successfully: {$refundId}");
            
            return [
                'success' => true,
                'refund_transaction_id' => $refundTransactionId
            ];
        } catch (Exception $e) {
            $this->logger->error("Error processing refund: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al procesar reembolso'
            ];
        }
    }
    
    /**
     * Crear nota de crédito
     * 
     * @param array $creditNoteData Datos de la nota de crédito
     * @return array Resultado de la operación
     */
    public function createCreditNote($creditNoteData) {
        try {
            $expiresDays = $creditNoteData['expires_days'] ?? 365;
            
            $stmt = $this->pdo->prepare("
                INSERT INTO credit_notes (order_id, client_id, amount, remaining_balance, reason, expires_at)
                VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP + INTERVAL '{$expiresDays} days')
                RETURNING id, credit_note_number
            ");
            
            $stmt->execute([
                $creditNoteData['order_id'],
                $creditNoteData['client_id'],
                $creditNoteData['amount'],
                $creditNoteData['amount'],
                $creditNoteData['reason']
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->logger->info("Credit note created: {$result['credit_note_number']} for order {$creditNoteData['order_id']}");
            
            return [
                'success' => true,
                'credit_note_id' => $result['id'],
                'credit_note_number' => $result['credit_note_number']
            ];
        } catch (Exception $e) {
            $this->logger->error("Error creating credit note: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al crear nota de crédito'
            ];
        }
    }
    
    /**
     * Usar nota de crédito
     * 
     * @param int $creditNoteId ID de la nota de crédito
     * @param float $amountToUse Monto a usar
     * @param int $orderId ID de la orden donde se aplica
     * @return array Resultado de la operación
     */
    public function useCreditNote($creditNoteId, $amountToUse, $orderId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT remaining_balance
                FROM credit_notes
                WHERE id = ? AND status = 'active'
            ");
            $stmt->execute([$creditNoteId]);
            $creditNote = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$creditNote) {
                return [
                    'success' => false,
                    'error' => 'Nota de crédito no encontrada o no activa'
                ];
            }
            
            if ($creditNote['remaining_balance'] < $amountToUse) {
                return [
                    'success' => false,
                    'error' => 'Saldo insuficiente en nota de crédito'
                ];
            }
            
            $newBalance = $creditNote['remaining_balance'] - $amountToUse;
            
            $stmt = $this->pdo->prepare("
                UPDATE credit_notes
                SET remaining_balance = ?,
                    status = CASE WHEN ? = 0 THEN 'used' ELSE status END,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$newBalance, $newBalance, $creditNoteId]);
            
            $this->logger->info("Credit note used: {$creditNoteId}, amount: {$amountToUse}");
            
            return [
                'success' => true,
                'remaining_balance' => $newBalance
            ];
        } catch (Exception $e) {
            $this->logger->error("Error using credit note: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al usar nota de crédito'
            ];
        }
    }
    
    /**
     * Obtener reembolsos pendientes
     * 
     * @return array Reembolsos pendientes
     */
    public function getPendingRefunds() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT r.*, o.order_number, c.company_name as client_name
                FROM refunds r
                JOIN orders o ON r.order_id = o.id
                JOIN clients c ON o.client_id = c.id
                WHERE r.refund_status = 'pending'
                ORDER BY r.created_at ASC
            ");
            $stmt->execute();
            $refunds = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'refunds' => $refunds
            ];
        } catch (Exception $e) {
            $this->logger->error("Error getting pending refunds: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al obtener reembolsos pendientes'
            ];
        }
    }
}
