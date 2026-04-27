<?php
require_once '../config/config.php';
require_login();

$user_name = htmlspecialchars($_SESSION['name'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
$user_role = htmlspecialchars($_SESSION['role'] ?? 'client', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Truper Platform</title>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/theme.css">
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
    <!-- HEADER -->
    <header>
        <div class="header-content">
            <a href="dashboard.php" class="logo"><img src="images/truper-logo.svg" alt="Truper"></a>
            <nav class="nav-menu">
                <a href="admin_supply.php#stockTab">Productos</a>
                <a href="dashboard.php" class="active">Dashboard</a>
                <a href="orders.php">Pedidos</a>
                <a href="wholesale.php">Mayoreo</a>
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?><a href="cashier.php">Caja</a><?php endif; ?>
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?><a href="admin_supply.php">Abastecimiento</a><?php endif; ?>
                <a href="tasks.php">Tareas</a>
                <a href="analytics.php">Estadísticas</a>
                <a href="profile.php">Perfil</a>
            </nav>
        </div>
        <div class="user-menu">
            <div class="user-info">
                <div class="user-name"><?php echo $user_name; ?></div>
                <div class="user-role"><?php echo ucfirst($user_role); ?></div>
            </div>
            <button class="btn-logout" onclick="logout()">Cerrar Sesión</button>
        </div>
    </header>

    <main>
        <div class="container-fluid">
            <div class="page-hero">
                <div class="module-badge module-admin"><span class="module-glyph">DB</span> Vista ejecutiva</div>
                <h1>Dashboard</h1>
                <p class="text-muted">Bienvenido de vuelta a Truper Platform</p>
            </div>

            <!-- MÉTRICAS PRINCIPALES -->
            <div class="grid grid-4">
                <div class="card">
                    <div class="metric-card">
                        <div class="metric-label">Órdenes Este Mes</div>
                        <div class="metric-value" id="monthlyOrders">0</div>
                    </div>
                </div>
                <div class="card">
                    <div class="metric-card">
                        <div class="metric-label">Ingresos</div>
                        <div class="metric-value" id="monthlyRevenue">$0</div>
                    </div>
                </div>
                <div class="card">
                    <div class="metric-card">
                        <div class="metric-label">Pagos Pendientes</div>
                        <div class="metric-value" id="pendingPayments">0</div>
                    </div>
                </div>
                <div class="card">
                    <div class="metric-card">
                        <div class="metric-label">Tareas Pendientes</div>
                        <div class="metric-value" id="pendingTasks">0</div>
                    </div>
                </div>
            </div>

            <!-- CONTENIDO PRINCIPAL -->
            <div class="grid grid-2" style="margin-top: 2rem;">
                <!-- ÓRDENES RECIENTES -->
                <div class="card">
                    <div class="card-header context-sales">Órdenes Recientes</div>
                    <div class="card-body">
                        <div id="recentOrders">
                            <p class="text-muted">Cargando...</p>
                        </div>
                        <a href="orders.php" class="btn btn-primary mt-3" style="width: 100%; text-align: center;">
                            Ver Todas las Órdenes
                        </a>
                    </div>
                </div>

                <!-- TOP PRODUCTOS -->
                <div class="card">
                    <div class="card-header context-ops">Productos Más Vendidos</div>
                    <div class="card-body">
                        <div id="topProducts">
                            <p class="text-muted">Cargando...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ACCIONES RÁPIDAS -->
            <div class="card" style="margin-top: 2rem;">
                <div class="card-header context-admin">Acciones Rápidas</div>
                <div class="card-body">
                    <div class="grid grid-4">
                        <a href="orders.php?action=new" class="btn btn-primary" style="text-align: center;">
                            ➕ Nuevo Pedido
                        </a>
                        <a href="tasks.php?action=new" class="btn btn-primary" style="text-align: center;">
                            ✓ Nueva Tarea
                        </a>
                        <a href="profile.php" class="btn btn-primary" style="text-align: center;">
                            👤 Mi Perfil
                        </a>
                        <a href="analytics.php" class="btn btn-primary" style="text-align: center;">
                            📊 Reportes
                        </a>
                        <a href="wholesale.php" class="btn btn-primary" style="text-align: center;">
                            🏷️ Mayoreo
                        </a>
                        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                        <a href="admin_supply.php" class="btn btn-primary" style="text-align: center;">
                            📦 Abastecimiento
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- FOOTER -->
    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h4>Truper</h4>
                <p>Plataforma de Gestión Empresarial</p>
            </div>
            <div class="footer-section">
                <h4>Enlaces</h4>
                <a href="/dashboard.php">Dashboard</a>
                <a href="/analytics.php">Estadísticas</a>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 Truper Platform. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script src="js/main.js"></script>
    <script src="js/analytics.js"></script>
    <script>
        async function loadRecentOrders() {
            const box = document.getElementById('recentOrders');
            if (!box) return;

            const response = await apiCall('/orders.php?action=list');
            if (!response || !response.success || !Array.isArray(response.orders)) {
                box.innerHTML = '<p class="text-muted">No fue posible cargar órdenes recientes.</p>';
                return;
            }

            const rows = response.orders.slice(0, 5);
            if (rows.length === 0) {
                box.innerHTML = '<p class="text-muted">Aún no hay órdenes registradas.</p>';
                return;
            }

            box.innerHTML = rows.map((order) => {
                const amount = Number(order.total_amount || 0).toFixed(2);
                return '<div class="task-item">'
                    + '<strong>' + (order.order_number || 'Sin folio') + '</strong>'
                    + '<div class="text-muted">Estado: ' + (order.status || 'pending') + ' | Pago: ' + (order.payment_status || 'pending') + '</div>'
                    + '<div>$' + amount + '</div>'
                    + '</div>';
            }).join('');
        }

        document.addEventListener('DOMContentLoaded', function() {
            loadDashboardMetrics();
            loadRecentOrders();
        });

        function logout() {
            if (confirm('¿Deseas cerrar sesión?')) {
                window.location.href = 'api/auth.php?action=logout';
            }
        }
    </script>
</body>
</html>
