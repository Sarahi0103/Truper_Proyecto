<!-- Truper - Dashboard Administrativo -->
<?php
require_once __DIR__ . '/../backend/config/security.php';
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/models/Analytics.php';
require_once __DIR__ . '/../backend/models/User.php';
require_once __DIR__ . '/../backend/models/Order.php';

Security::requireAdmin();

$analytics = new Analytics();
$user_model = new User();
$order_model = new Order();

$summary = $analytics->getSummary();
$stats = $analytics->getPurchaseStatsByMonth(12);
$top_products = $analytics->getTopPurchasedProducts(5);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Truper</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/dashboard.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">Truper ADMIN</div>
            <ul class="nav-menu">
                <li><a href="/admin/dashboard.php">Dashboard</a></li>
                <li><a href="/admin/users.php">Usuarios</a></li>
                <li><a href="/admin/products.php">Productos</a></li>
                <li><a href="/admin/orders.php">Órdenes</a></li>
                <li><a href="/admin/analytics.php">Analytics</a></li>
                <li><a href="/backend/controllers/logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>Administración</h3>
            </div>
            
            <nav class="sidebar-nav">
                <a href="/admin/dashboard.php" class="nav-link active">Dashboard</a>
                <a href="/admin/users.php" class="nav-link">Gestionar Usuarios</a>
                <a href="/admin/products.php" class="nav-link">Gestionar Productos</a>
                <a href="/admin/orders.php" class="nav-link">Gestionar Órdenes</a>
                <a href="/admin/tasks.php" class="nav-link">Tareas</a>
                <a href="/admin/wholesale.php" class="nav-link">Mayoreo</a>
                <a href="/admin/analytics.php" class="nav-link">Analytics</a>
            </nav>
        </aside>

        <main class="dashboard-main">
            <div class="dashboard-header">
                <h1>Panel Administrativo Truper</h1>
            </div>

            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <h3>Órdenes Totales</h3>
                    <p class="card-value"><?php echo $summary['total_orders']; ?></p>
                </div>

                <div class="dashboard-card">
                    <h3>Venta Total</h3>
                    <p class="card-value">$<?php echo number_format($summary['total_sales'], 2); ?></p>
                </div>

                <div class="dashboard-card">
                    <h3>Clientes</h3>
                    <p class="card-value"><?php echo $summary['total_clients']; ?></p>
                </div>

                <div class="dashboard-card">
                    <h3>Ganancia Bruta</h3>
                    <p class="card-value">$<?php echo number_format($summary['profit'], 2); ?></p>
                </div>
            </div>

            <section class="recent-orders" style="margin-top: 2rem;">
                <h2>Productos Más Comprados</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>SKU</th>
                            <th>Cantidad</th>
                            <th>Costo Total</th>
                            <th>Compras</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_products as $product): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td><?php echo htmlspecialchars($product['sku']); ?></td>
                            <td><?php echo $product['total_quantity']; ?></td>
                            <td>$<?php echo number_format($product['total_cost'], 2); ?></td>
                            <td><?php echo $product['purchase_count']; ?></td>
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


