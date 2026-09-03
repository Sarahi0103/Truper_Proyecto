<?php
/**
 * Stripe Webhook Handler
 * Recibe notificaciones de confirmación de pagos de Stripe
 * Truper Platform
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/utils/AppLogger.php';
require_once __DIR__ . '/../../src/Services/PaymentGatewayService.php';

header('Content-Type: application/json');

// Obtener el webhook secret de configuración
$stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'stripe_webhook_secret' LIMIT 1");
$stmt->execute();
$webhookSecret = $stmt->fetchColumn();

if (!$webhookSecret) {
    http_response_code(500);
    echo json_encode(['error' => 'Webhook secret no configurado']);
    exit;
}

// Obtener el payload
$payload = @file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (!$sigHeader) {
    http_response_code(400);
    echo json_encode(['error' => 'Firma de webhook no proporcionada']);
    exit;
}

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
} catch (\UnexpectedValueException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Payload inválido']);
    exit;
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Firma inválida']);
    exit;
}

$logger = new AppLogger();
$paymentService = new PaymentGatewayService($pdo);

// Procesar el evento
switch ($event->type) {
    case 'payment_intent.succeeded':
        $paymentIntent = $event->data->object;
        $orderId = $paymentIntent->metadata->order_id ?? null;
        $orderNumber = $paymentIntent->metadata->order_number ?? null;
        
        $logger->info("Stripe Payment Succeeded: " . $paymentIntent->id . " para orden: " . ($orderNumber ?? 'N/A'));
        
        if ($orderId) {
            try {
                $paymentService->confirmPayment($paymentIntent->id, 'card', $orderId);
            } catch (Exception $e) {
                $logger->error("Error confirmando pago: " . $e->getMessage());
            }
        }
        break;

    case 'payment_intent.payment_failed':
        $paymentIntent = $event->data->object;
        $orderId = $paymentIntent->metadata->order_id ?? null;
        $orderNumber = $paymentIntent->metadata->order_number ?? null;
        
        $logger->info("Stripe Payment Failed: " . $paymentIntent->id . " para orden: " . ($orderNumber ?? 'N/A'));
        
        if ($orderId) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE orders 
                    SET payment_status = 'failed',
                        status = 'cancelled',
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$orderId]);
                
                // Reincorporar inventario
                $stmt = $pdo->prepare("
                    UPDATE products p
                    SET stock_quantity = p.stock_quantity + oi.quantity,
                        updated_at = NOW()
                    FROM order_items oi
                    WHERE oi.order_id = ? AND p.id = oi.product_id
                ");
                $stmt->execute([$orderId]);
            } catch (Exception $e) {
                $logger->error("Error actualizando orden fallida: " . $e->getMessage());
            }
        }
        break;

    case 'charge.refunded':
        $charge = $event->data->object;
        $paymentIntentId = $charge->payment_intent;
        
        $logger->info("Stripe Charge Refunded: " . $charge->id);
        
        try {
            $stmt = $pdo->prepare("
                SELECT order_id FROM payments 
                WHERE transaction_id = ? AND payment_method = 'card'
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([$paymentIntentId]);
            $orderId = $stmt->fetchColumn();
            
            if ($orderId) {
                $paymentService->processRefund($orderId, $charge->amount / 100);
            }
        } catch (Exception $e) {
            $logger->error("Error procesando reembolso webhook: " . $e->getMessage());
        }
        break;

    default:
        $logger->info("Evento Stripe no manejado: " . $event->type);
}

http_response_code(200);
echo json_encode(['status' => 'success']);
?>
