 Truper - Dashboard Administrativo -->
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
    <link rel="stylesheet" href="/assets/css/style.css?v=4.1">
    <link rel="stylesheet" href="/assets/css/dashboard.css?v=4.1">
    <link rel="stylesheet" href="/assets/css/responsive.css?v=4.1">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">Truper ADMIN</div>
            <button class="hamburger-btn" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
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
                <div class="logo">Truper ADMIN</div>
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
                            <td data-label="Producto"><?php echo htmlspecialchars($product['name']); ?></td>
                            <td data-label="SKU"><?php echo htmlspecialchars($product['sku']); ?></td>
                            <td data-label="Cantidad"><?php echo $product['total_quantity']; ?></td>
                            <td data-label="Costo Total">$<?php echo number_format($product['total_cost'], 2); ?></td>
                            <td data-label="Compras"><?php echo $product['purchase_count']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </main>
    </div>

    <script src="/assets/js/dashboard.js"></script>
    <script>
        // Menú hamburguesa responsivo
        document.addEventListener('DOMContentLoaded', function() {
            const hamburgerBtn = document.querySelector('.hamburger-btn');
            const navMenu = document.querySelector('.nav-menu');

            console.log('Hamburger button:', hamburgerBtn);
            console.log('Nav menu:', navMenu);

            if (hamburgerBtn && navMenu) {
                hamburgerBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Hamburger clicked');
                    navMenu.classList.toggle('active');
                    hamburgerBtn.classList.toggle('active');
                    console.log('Menu active:', navMenu.classList.contains('active'));
                });

                // Cerrar menú al hacer click en un enlace
                navMenu.querySelectorAll('a').forEach(link => {
                    link.addEventListener('click', function() {
                        navMenu.classList.remove('active');
                        hamburgerBtn.classList.remove('active');
                    });
                });

                // Cerrar menú al hacer click fuera
                document.addEventListener('click', function(e) {
                    if (!hamburgerBtn.contains(e.target) && !navMenu.contains(e.target)) {
                        navMenu.classList.remove('active');
                        hamburgerBtn.classList.remove('active');
                    }
                });
            } else {
                console.error('Hamburger button or nav menu not found');
            }
        });
    </script>
</body>
</html>


