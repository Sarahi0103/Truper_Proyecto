<!-- Truper - Mis Órdenes -->
<?php
require_once __DIR__ . '/../backend/config/security.php';
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/models/Order.php';

Security::requireAuth();

$order_model = new Order();
$orders = $order_model->getUserOrders($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Órdenes - Truper</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/dashboard.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">Truper</div>
            <ul class="nav-menu">
                <li><a href="/views/dashboard.php">Dashboard</a></li>
                <li><a href="/views/my_orders.php">Mis Órdenes</a></li>
                <li><a href="/backend/controllers/logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <h1>Mis Órdenes</h1>
        
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th>Pago</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td>#<?php echo $order['id']; ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                    <td>$<?php echo number_format($order['total'], 2); ?></td>
                    <td><span class="status-badge status-<?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span></td>
                    <td><span class="status-badge status-<?php echo $order['payment_status'] ?? 'pending'; ?>"><?php echo ucfirst($order['payment_status'] ?? 'pending'); ?></span></td>
                    <td>
                        <a href="/views/order_detail.php?id=<?php echo $order['id']; ?>" class="btn-small">Ver</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>


