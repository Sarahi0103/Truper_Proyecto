<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
    <!-- HEADER -->
    <header>
        <div class="header-content">
            <a href="dashboard.php" class="logo">🏪 Truper</a>
            <nav class="nav-menu">
                <a href="dashboard.php" class="active">Dashboard</a>
                <a href="orders.php">Pedidos</a>
                <a href="tasks.php">Tareas</a>
                <a href="analytics.php">Estadísticas</a>
                <a href="profile.php">Perfil</a>
            </nav>
        </div>
        <div class="user-menu">
            <div class="user-info">
                <div class="user-name">Usuario</div>
                <div class="user-role">Cliente</div>
            </div>
            <button class="btn-logout" onclick="logout()">Cerrar Sesión</button>
        </div>
    </header>

    <main>
        <div class="container-fluid">
            <h1>Dashboard</h1>
            <p class="text-muted">Bienvenido de vuelta a Truper Platform</p>

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
                    <div class="card-header">Órdenes Recientes</div>
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
                    <div class="card-header">Productos Más Vendidos</div>
                    <div class="card-body">
                        <div id="topProducts">
                            <p class="text-muted">Cargando...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ACCIONES RÁPIDAS -->
            <div class="card" style="margin-top: 2rem;">
                <div class="card-header">Acciones Rápidas</div>
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
                <h4>Contacto</h4>
                <p>Email: soporte@truper.com</p>
                <p>Teléfono: +56 2 1234 5678</p>
            </div>
            <div class="footer-section">
                <h4>Enlaces</h4>
                <a href="/truper_platform/public/dashboard.php">Dashboard</a>
                <a href="/truper_platform/public/analytics.php">Estadísticas</a>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2024 Truper Platform. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script src="js/main.js"></script>
    <script src="js/analytics.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            loadDashboardMetrics();
        });

        function logout() {
            if (confirm('¿Deseas cerrar sesión?')) {
                window.location.href = 'api/auth.php?action=logout';
            }
        }
    </script>
</body>
</html>
