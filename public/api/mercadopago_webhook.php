<?php
/**
 * Mercado Pago Webhook Handler
 * Recibe notificaciones de confirmación de pagos de Mercado Pago
 * Truper Platform
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/utils/AppLogger.php';
require_once __DIR__ . '/../../src/Services/PaymentGatewayService.php';

header('Content-Type: application/json');

// Obtener el access token de configuración o entorno
$accessToken = null;
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'mercadopago_access_token' LIMIT 1");
    $stmt->execute();
    $accessToken = $stmt->fetchColumn() ?: null;
} catch (Exception $e) {}

if (!$accessToken) {
    $accessToken = getenv('MERCADOPAGO_ACCESS_TOKEN') ?: ($_ENV['MERCADOPAGO_ACCESS_TOKEN'] ?? null);
}

if (!$accessToken) {
    http_response_code(500);
    echo json_encode(['error' => 'Mercado Pago access token no configurado']);
    exit;
}

$logger = new AppLogger();
$paymentService = new PaymentGatewayService($pdo);

// Obtener el payload
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Payload inválido']);
    exit;
}

// Mercado Pago envía notificaciones de tipo 'payment' o 'merchant_order'
if ($data['type'] === 'payment') {
    $paymentId = $data['data']['id'];
    
    try {
        if (!class_exists('\\MercadoPago\\SDK')) {
            http_response_code(503);
            echo json_encode(['error' => 'Mercado Pago SDK no disponible']);
            exit;
        }

        // Consultar el estado del pago en Mercado Pago
        \MercadoPago\SDK::setAccessToken($accessToken);
        $payment = \MercadoPago\Payment::find_by_id($paymentId);
        
        if (!$payment) {
            http_response_code(404);
            echo json_encode(['error' => 'Pago no encontrado']);
            exit;
        }
        
        $externalReference = $payment->external_reference;
        $status = $payment->status;
        
        $logger->info("Mercado Pago Webhook: Payment {$paymentId} status: {$status}, ref: {$externalReference}");
        
        // Obtener order_id desde el external_reference o buscar por order_number
        $orderId = null;
        if ($externalReference) {
            $stmt = $pdo->prepare("SELECT id FROM orders WHERE order_number = ? LIMIT 1");
            $stmt->execute([$externalReference]);
            $orderId = $stmt->fetchColumn();
        }
        
        if ($orderId && $status === 'approved') {
            try {
                $paymentService->confirmPayment($paymentId, 'mercadopago', $orderId);
            } catch (Exception $e) {
                $logger->error("Error confirmando pago MP: " . $e->getMessage());
            }
        } elseif ($orderId && ($status === 'rejected' || $status === 'cancelled')) {
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
                $logger->error("Error actualizando orden fallida MP: " . $e->getMessage());
            }
        }
    } catch (Exception $e) {
        $logger->error("Error procesando webhook Mercado Pago: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Error interno']);
        exit;
    }
}

http_response_code(200);
echo json_encode(['status' => 'success']);
?>
