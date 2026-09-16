<?php
/**
 * Payment Gateway Service
 * Integración real con Stripe y Mercado Pago
 * Truper Platform
 */

if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

class PaymentGatewayService {
    private $stripe;
    private $mercadopago;
    private $pdo;
    private $logger;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        if (class_exists('AppLogger')) {
            $this->logger = new AppLogger();
        }
        
        // Inicializar Stripe si hay configuración y SDK disponible
        $stripeKey = $this->getSetting('stripe_secret_key') ?: getenv('STRIPE_SECRET_KEY');
        if ($stripeKey && class_exists('\\Stripe\\StripeClient')) {
            $this->stripe = new \Stripe\StripeClient($stripeKey);
        }
        
        // Inicializar Mercado Pago si hay configuración y SDK disponible
        $mpAccessToken = $this->getSetting('mercadopago_access_token') ?: getenv('MERCADOPAGO_ACCESS_TOKEN');
        if ($mpAccessToken && class_exists('\\MercadoPago\\SDK')) {
            \MercadoPago\SDK::setAccessToken($mpAccessToken);
            $this->mercadopago = new \MercadoPago\SDK();
        }
    }

    private function getSetting($key) {
        try {
            $stmt = $this->pdo->prepare("SELECT config_value FROM system_config WHERE config_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            if ($val !== false && $val !== null) {
                return $val;
            }

            $stmt2 = $this->pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
            $stmt2->execute([$key]);
            return $stmt2->fetchColumn();
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Procesar pago con Stripe
     */
    public function processStripePayment($amount, $paymentMethodId, $orderData) {
        if (!$this->stripe) {
            throw new Exception('Stripe no está configurado');
        }

        try {
            // Crear PaymentIntent
            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => round($amount * 100), // Stripe usa centavos
                'currency' => 'mxn',
                'payment_method' => $paymentMethodId,
                'confirm' => true,
                'description' => 'Orden #' . ($orderData['order_number'] ?? ''),
                'metadata' => [
                    'order_id' => $orderData['order_id'] ?? '',
                    'order_number' => $orderData['order_number'] ?? ''
                ]
            ]);

            if ($paymentIntent->status === 'succeeded') {
                return [
                    'success' => true,
                    'payment_id' => $paymentIntent->id,
                    'status' => 'succeeded',
                    'amount' => $amount
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $paymentIntent->status,
                    'message' => 'El pago no se completó: ' . $paymentIntent->status
                ];
            }
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->logger->error("Stripe API Error: " . $e->getMessage());
            throw new Exception('Error al procesar pago con Stripe: ' . $e->getMessage());
        }
    }

    /**
     * Procesar pago con Mercado Pago
     */
    public function processMercadoPagoPayment($amount, $paymentData, $orderData) {
        if (!$this->mercadopago) {
            throw new Exception('Mercado Pago no está configurado');
        }

        try {
            $preference = new \MercadoPago\Preference();
            
            // Crear item
            $item = new \MercadoPago\Item();
            $item->title = 'Orden #' . ($orderData['order_number'] ?? '');
            $item->quantity = 1;
            $item->currency_id = 'MXN';
            $item->unit_price = $amount;
            $preference->items = array($item);
            
            // Configurar URLs de retorno
            $baseUrl = $this->getBaseUrl();
            $preference->back_urls = array(
                "success" => $baseUrl . "/order_confirmation.php?status=success",
                "failure" => $baseUrl . "/order_confirmation.php?status=failure",
                "pending" => $baseUrl . "/order_confirmation.php?status=pending"
            );
            
            $preference->auto_return = "approved";
            $preference->external_reference = ($orderData['order_number'] ?? '');
            $preference->metadata = array(
                'order_id' => $orderData['order_id'] ?? ''
            );
            
            $preference->save();
            
            return [
                'success' => true,
                'preference_id' => $preference->id,
                'init_point' => $preference->init_point,
                'status' => 'pending_redirect'
            ];
        } catch (Exception $e) {
            $this->logger->error("Mercado Pago Error: " . $e->getMessage());
            throw new Exception('Error al procesar pago con Mercado Pago: ' . $e->getMessage());
        }
    }

    /**
     * Procesar pago por transferencia SPEI
     */
    public function processSPEIPayment($amount, $orderData) {
        // SPEI es asíncrono, solo registramos la intención de pago
        $bankName = $this->getSetting('bank_name') ?? 'BBVA Bancomer';
        $bankClabe = $this->getSetting('bank_clabe') ?? '';
        $accountHolder = $this->getSetting('bank_account_holder') ?? '';
        
        return [
            'success' => true,
            'status' => 'pending_spei',
            'bank_name' => $bankName,
            'clabe' => $bankClabe,
            'account_holder' => $accountHolder,
            'amount' => $amount,
            'message' => 'Complete la transferencia a la cuenta indicada'
        ];
    }

    /**
     * Procesar pago contra entrega
     */
    public function processCashOnDelivery($amount, $orderData) {
        return [
            'success' => true,
            'status' => 'pending_cod',
            'amount' => $amount,
            'message' => 'Pago contra entrega al recibir el pedido'
        ];
    }

    /**
     * Confirmar pago (llamado desde webhooks)
     */
    public function confirmPayment($paymentId, $gateway, $orderId) {
        $ownsTx = !$this->pdo->inTransaction();
        try {
            if ($ownsTx) {
                $this->pdo->beginTransaction();
            }
            
            // Actualizar estado de la orden
            $stmtOrder = $this->pdo->prepare("
                UPDATE orders 
                SET payment_status = 'paid',
                    status = 'confirmed',
                    payment_gateway = ?,
                    payment_transaction_id = ?,
                    payment_amount = total_amount,
                    balance = 0,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmtOrder->execute([$gateway, $paymentId, $orderId]);
            
            if ($stmtOrder->rowCount() === 0) {
                throw new Exception("Orden ID {$orderId} no encontrada para confirmar pago");
            }
            
            // Actualizar o registrar en tabla payments
            $stmtPay = $this->pdo->prepare("
                UPDATE payments 
                SET reference_number = ?,
                    payment_date = NOW(),
                    payment_method = ?
                WHERE id = (
                    SELECT id FROM payments 
                    WHERE order_id = ?
                    ORDER BY id DESC LIMIT 1
                )
            ");
            $stmtPay->execute([$paymentId, $gateway, $orderId]);
            
            if ($stmtPay->rowCount() === 0) {
                $totStmt = $this->pdo->prepare("SELECT total_amount FROM orders WHERE id = ?");
                $totStmt->execute([$orderId]);
                $tot = (float)$totStmt->fetchColumn();
                $insPay = $this->pdo->prepare("
                    INSERT INTO payments (order_id, amount, payment_method, reference_number, payment_date, notes)
                    VALUES (?, ?, ?, ?, NOW(), ?)
                ");
                $insPay->execute([$orderId, $tot, $gateway, $paymentId, "Pago confirmado vía {$gateway}"]);
            }
            
            if ($ownsTx) {
                $this->pdo->commit();
            }
            
            if ($this->logger && method_exists($this->logger, 'info')) {
                $this->logger->info("Pago confirmado: {$paymentId} para orden {$orderId} via {$gateway}");
            }
            
            return ['success' => true];
        } catch (Exception $e) {
            if ($ownsTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($this->logger && method_exists($this->logger, 'error')) {
                $this->logger->error("Error confirmando pago: " . $e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Procesar reembolso
     */
    public function processRefund($orderId, $amount = null) {
        $ownsTx = !$this->pdo->inTransaction();
        try {
            if ($ownsTx) {
                $this->pdo->beginTransaction();
            }
            
            // Obtener información de la orden y pago
            $stmt = $this->pdo->prepare("
                SELECT o.payment_transaction_id, o.payment_gateway, o.total_amount, o.payment_amount,
                       p.reference_number, p.payment_method, p.amount
                FROM orders o
                LEFT JOIN payments p ON p.order_id = o.id
                WHERE o.id = ?
                ORDER BY p.id DESC LIMIT 1
            ");
            $stmt->execute([$orderId]);
            $info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$info) {
                throw new Exception('No se encontró la orden para procesar el reembolso');
            }
            
            $txnId = !empty($info['payment_transaction_id']) ? $info['payment_transaction_id'] : ($info['reference_number'] ?? '');
            $gateway = !empty($info['payment_gateway']) ? $info['payment_gateway'] : ($info['payment_method'] ?? 'card');
            $refundAmount = $amount !== null ? (float)$amount : (float)($info['payment_amount'] ?: ($info['total_amount'] ?: ($info['amount'] ?? 0)));
            
            // Procesar reembolso según gateway
            if ($gateway === 'card' && !empty($txnId)) {
                // Reembolso Stripe
                if ($this->stripe) {
                    $this->stripe->refunds->create([
                        'payment_intent' => $txnId,
                        'amount' => round($refundAmount * 100)
                    ]);
                }
            }
            
            // Actualizar estado de la orden
            $stmtOrder = $this->pdo->prepare("
                UPDATE orders 
                SET payment_status = 'refunded',
                    notes = COALESCE(notes, '') || ' | Reembolso: $' || ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmtOrder->execute([number_format($refundAmount, 2, '.', ''), $orderId]);
            
            // Actualizar notas del pago si existe
            $stmtPay = $this->pdo->prepare("
                UPDATE payments 
                SET notes = COALESCE(notes, '') || ' | Reembolsado: $' || ?
                WHERE id = (
                    SELECT id FROM payments 
                    WHERE order_id = ?
                    ORDER BY id DESC LIMIT 1
                )
            ");
            $stmtPay->execute([number_format($refundAmount, 2, '.', ''), $orderId]);
            
            if ($ownsTx) {
                $this->pdo->commit();
            }
            
            if ($this->logger && method_exists($this->logger, 'info')) {
                $this->logger->info("Reembolso procesado: {$refundAmount} para orden {$orderId}");
            }
            
            return ['success' => true, 'amount' => $refundAmount];
        } catch (Exception $e) {
            if ($ownsTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($this->logger && method_exists($this->logger, 'error')) {
                $this->logger->error("Error procesando reembolso: " . $e->getMessage());
            }
            throw $e;
        }
    }

    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }
}
?>
