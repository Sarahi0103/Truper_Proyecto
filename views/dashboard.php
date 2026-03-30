<?php
require_once __DIR__ . '/../backend/config/security.php';
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/models/User.php';
require_once __DIR__ . '/../backend/models/Order.php';

Security::requireAuth();

$user_model = new User();
$order_model = new Order();

$user = $user_model->getById($_SESSION['user_id']);
$orders = $order_model->getUserOrders($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Dashboard - TRUPPER</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/dashboard.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">TRUPPER</div>
            <ul class="nav-menu">
                <li><a href="/index.php">Inicio</a></li>
                <li><a href="/views/products.php">Catálogo</a></li>
                <li><a href="/views/dashboard.php">Dashboard</a></li>
                <li><a href="/backend/controllers/logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3><?php echo htmlspecialchars($user['name']); ?></h3>
                <p><?php echo htmlspecialchars($user['email']); ?></p>
            </div>
            
            <nav class="sidebar-nav">
                <a href="/views/dashboard.php" class="nav-link active">Dashboard</a>
                <a href="/views/my_orders.php" class="nav-link">Mis Pedidos</a>
                <a href="/views/profile.php" class="nav-link">Mi Perfil</a>
                <a href="/views/my_points.php" class="nav-link">Mis Puntos</a>
                <a href="/views/wholesale.php" class="nav-link">Solicitar Mayoreo</a>
            </nav>
        </aside>

        <main class="dashboard-main">
            <div class="dashboard-header">
                <h1>¡Bienvenido, <?php echo htmlspecialchars($user['name']); ?>!</h1>
            </div>

            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <h3>Puntos Acumulados</h3>
                    <p class="card-value"><?php echo $user['points']; ?></p>
                    <p class="card-label">Redimibles en tu próxima compra</p>
                </div>

                <div class="dashboard-card">
                    <h3>Pedidos Totales</h3>
                    <p class="card-value"><?php echo count($orders); ?></p>
                    <a href="/views/my_orders.php">Ver todos</a>
                </div>

                <div class="dashboard-card">
                    <h3>Cuenta</h3>
                    <p class="card-label">Miembro desde <?php echo date('d/m/Y', strtotime($user['created_at'])); ?></p>
                    <a href="/views/profile.php">Editar perfil</a>
                </div>
            </div>

            <section class="recent-orders">
                <h2>Pedidos Recientes</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID Pedido</th>
                            <th>Fecha</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Pago</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($orders, 0, 5) as $order): ?>
                        <tr>
                            <td>#<?php echo $order['id']; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></td>
                            <td>$<?php echo number_format($order['total'], 2); ?></td>
                            <td><span class="status-badge status-<?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span></td>
                            <td><span class="status-badge status-<?php echo $order['payment_status'] ?? 'pending'; ?>"><?php echo ucfirst($order['payment_status'] ?? 'pending'); ?></span></td>
                            <td><a href="/views/order_detail.php?id=<?php echo $order['id']; ?>">Ver</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </main>
    </div>

    <script src="/assets/js/dashboard.js"></script>
</body>
</html>
