<!-- Vista de detalle de orden -->
<?php
require_once __DIR__ . '/../backend/config/security.php';
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/models/Order.php';
require_once __DIR__ . '/../backend/models/BarcodeReader.php';

Security::requireAuth();

$order_id = $_GET['id'] ?? null;
if (!$order_id) {
    header("Location: /views/my_orders.php");
    exit();
}

$order_model = new Order();
$order = $order_model->getOrderDetail($order_id);
$items = $order_model->getOrderItems($order_id);

if (!$order) {
    header("Location: /views/my_orders.php");
    exit();
}

$payment_tracker = new PaymentTracker();
$payment_status = $payment_tracker->getPaymentStatus($order_id);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Orden #<?php echo $order_id; ?> - TRUPPER</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">TRUPPER</div>
            <ul class="nav-menu">
                <li><a href="/views/my_orders.php">← Volver</a></li>
                <li><a href="/views/dashboard.php">Dashboard</a></li>
            </ul>
        </div>
    </nav>

    <div class="container" style="padding: 2rem;">
        <h1>Detalle de Orden #<?php echo $order_id; ?></h1>
        
        <div style="background: white; padding: 2rem; border-radius: 8px; margin: 2rem 0;">
            <h3>Información de la Orden</h3>
            <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></p>
            <p><strong>Cliente:</strong> <?php echo htmlspecialchars($order['name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($order['email']); ?></p>
            <p><strong>Estado:</strong> <span class="status-badge status-<?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span></p>
        </div>

        <div style="background: white; padding: 2rem; border-radius: 8px; margin: 2rem 0;">
            <h3>Items de la Orden</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>SKU</th>
                        <th>Cantidad</th>
                        <th>Precio Unitario</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                        <td><?php echo htmlspecialchars($item['sku']); ?></td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                        <td>$<?php echo number_format($item['subtotal'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="background: white; padding: 2rem; border-radius: 8px; margin: 2rem 0;">
            <h3>Resumen de Pago</h3>
            <p><strong>Total de la Orden:</strong> $<?php echo number_format($order['total'], 2); ?></p>
            <p><strong>Monto Pagado:</strong> $<?php echo number_format($payment_status['paid_amount'], 2); ?></p>
            <p><strong>Pendiente:</strong> $<?php echo number_format($payment_status['pending_amount'], 2); ?></p>
            <p><strong>Porcentaje Pagado:</strong> <?php echo number_format($payment_status['payment_percentage'], 2); ?>%</p>
            <p><strong>Estado de Pago:</strong> <span class="status-badge status-<?php echo $payment_status['is_paid'] ? 'paid' : 'pending'; ?>"><?php echo $payment_status['is_paid'] ? 'PAGADA' : 'PENDIENTE'; ?></span></p>
        </div>
    </div>
</body>
</html>
