<?php
require_once '../config/config.php';
require_login();

$user_name = htmlspecialchars($_SESSION['name'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
$user_role = htmlspecialchars($_SESSION['role'] ?? 'client', ENT_QUOTES, 'UTF-8');
$is_admin = (($_SESSION['role'] ?? '') === 'admin');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas y Análisis - Truper Platform</title>
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
                <a href="dashboard.php">Dashboard</a>
                <a href="orders.php">Pedidos</a>
                <a href="wholesale.php">Mayoreo</a>
                <?php if ($is_admin): ?><a href="cashier.php">Caja</a><?php endif; ?>
                <?php if ($is_admin): ?><a href="admin_supply.php">Abastecimiento</a><?php endif; ?>
                <a href="tasks.php">Tareas</a>
                <a href="analytics.php" class="active">Estadísticas</a>
                <a href="profile.php">Perfil</a>
            </nav>
        </div>
        <div class="user-menu">
            <div class="user-info">
                <div class="user-name"><?php echo $user_name; ?></div>
                <div class="user-role"><?php echo ucfirst($user_role); ?></div>
            </div>
            <a href="index.php" class="btn btn-small btn-ghost">Ver portada</a>
            <button class="btn-logout" onclick="logout()">Cerrar Sesión</button>
        </div>
    </header>

    <main>
        <div class="container-fluid">
            <div class="d-flex justify-between align-center">
                <h1><?php echo $is_admin ? 'Estadísticas y Análisis' : 'Mis Estadísticas'; ?></h1>
                <?php if ($is_admin): ?>
                <button class="btn btn-primary" onclick="exportStats('csv')">
                    📥 Descargar Reporte
                </button>
                <?php endif; ?>
            </div>

            <!-- TABS -->
            <div class="tabs">
                <button class="tab-button active" data-tab="purchaseStats">Compras</button>
                <?php if ($is_admin): ?>
                <button class="tab-button" data-tab="predictionsTab">Predicciones</button>
                <button class="tab-button" data-tab="seasonalTab">Temporadas</button>
                <button class="tab-button" data-tab="clientsTab">Clientes</button>
                <?php endif; ?>
            </div>

            <?php if (!$is_admin): ?>
            <div class="grid grid-4" style="margin: 1rem 0 1.5rem;">
                <div class="card"><div class="card-body"><div class="text-muted">Pedidos Totales</div><div id="myTotalOrders" style="font-size:1.8rem;font-weight:700;">0</div></div></div>
                <div class="card"><div class="card-body"><div class="text-muted">Total Comprado</div><div id="myTotalSpent" style="font-size:1.8rem;font-weight:700;">$0</div></div></div>
                <div class="card"><div class="card-body"><div class="text-muted">Ticket Promedio</div><div id="myAvgTicket" style="font-size:1.8rem;font-weight:700;">$0</div></div></div>
                <div class="card"><div class="card-body"><div class="text-muted">Pedidos Activos</div><div id="myPendingOrders" style="font-size:1.8rem;font-weight:700;">0</div></div></div>
            </div>
            <?php endif; ?>

            <!-- ESTADÍSTICAS DE COMPRAS -->
            <div id="purchaseStats" class="tab-content active">
                <div class="card">
                    <div class="card-header">Estadísticas de Compras por Mes</div>
                    <div class="card-body">
                        <div style="margin-bottom: 1.5rem;">
                            <label>Seleccionar Año:</label>
                            <select id="yearFilter" onchange="loadPurchaseStats()">
                                <option value="">Cargando años...</option>
                            </select>
                        </div>
                        <div id="purchaseStatsContainer"></div>
                    </div>
                </div>
            </div>

            <!-- PREDICCIONES DEL SISTEMA -->
            <?php if ($is_admin): ?>
            <div id="predictionsTab" class="tab-content">
                <div class="card">
                    <div class="card-header">Predicciones de Demanda (IA)</div>
                    <div class="card-body">
                        <p class="text-muted mb-3">El sistema aprende de tus comportamientos de compra para generar predicciones precisas.</p>
                        <button class="btn btn-primary" onclick="loadPredictions()">Generar Predicciones</button>
                        <div id="predictionsContainer" style="margin-top: 2rem;"></div>
                    </div>
                </div>
            </div>

            <!-- ANÁLISIS POR TEMPORADA -->
            <div id="seasonalTab" class="tab-content">
                <div class="card">
                    <div class="card-header">Análisis de Compras por Temporada</div>
                    <div class="card-body">
                        <p class="text-muted mb-3">Visualiza cómo cambian tus compras según la temporada y factores externos.</p>
                        <button class="btn btn-primary" onclick="generateSeasonalReport()">Generar Reporte</button>
                        <div id="seasonalReport" style="margin-top: 2rem;"></div>
                    </div>
                </div>
            </div>

            <!-- ANÁLISIS DE CLIENTES -->
            <div id="clientsTab" class="tab-content">
                <div class="card">
                    <div class="card-header">Análisis de Clientes</div>
                    <div class="card-body">
                        <button class="btn btn-primary" onclick="loadClientAnalytics()">Cargar Análisis</button>
                        <div id="clientAnalytics" style="margin-top: 2rem;"></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- WIDGETS INFORMATIVOS -->
            <?php if ($is_admin): ?>
            <div class="grid grid-2" style="margin-top: 2rem;">
                <div class="card">
                    <div class="card-header">Información Importante</div>
                    <div class="card-body">
                        <h4>Cómo funciona nuestro Sistema de IA</h4>
                        <ul style="margin-left: 1.5rem; margin-top: 1rem; line-height: 1.8;">
                            <li>Analiza historial de compras de 2+ años</li>
                            <li>Considera factores de temporada y clima</li>
                            <li>Detecta patrones de crecimiento o decrecimiento</li>
                            <li>Genera predicciones con confianza% basada en datos</li>
                            <li>Aprende constantemente de nuevas compras</li>
                        </ul>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">Beneficios de las Predicciones</div>
                    <div class="card-body">
                        <ul style="margin-left: 1.5rem; margin-top: 1rem; line-height: 1.8;">
                            <li>✓ Evita compras innecesarias</li>
                            <li>✓ Optimiza gastos mensuales</li>
                            <li>✓ Reduce pérdidas por sobrestoque</li>
                            <li>✓ Mejora planificación de compras</li>
                            <li>✓ Aumenta eficiencia operacional</li>
                        </ul>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- FOOTER -->
    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h4>Truper</h4>
                <p>Plataforma de Gestión Empresarial</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 Truper Platform. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script src="js/main.js"></script>
    <script src="js/analytics.js"></script>
    <script>
        function logout() {
            if (confirm('¿Deseas cerrar sesión?')) {
                window.location.href = 'api/auth.php?action=logout';
            }
        }

        async function loadPurchaseStats() {
            const year = document.getElementById('yearFilter').value;
            const response = await apiCall(`/analytics.php?action=purchase-stats&year=${year}`);
            if (response && response.stats) {
                generateChart('purchaseStatsContainer', response.stats);
            }
        }

        async function loadMySummary() {
            const response = await apiCall('/analytics.php?action=my-summary');
            if (!response || !response.summary) return;

            const summary = response.summary;
            const totalOrders = document.getElementById('myTotalOrders');
            const totalSpent = document.getElementById('myTotalSpent');
            const avgTicket = document.getElementById('myAvgTicket');
            const pendingOrders = document.getElementById('myPendingOrders');

            if (totalOrders) totalOrders.textContent = summary.total_orders || 0;
            if (totalSpent) totalSpent.textContent = formatCurrency(summary.total_spent || 0);
            if (avgTicket) avgTicket.textContent = formatCurrency(summary.avg_ticket || 0);
            if (pendingOrders) pendingOrders.textContent = summary.pending_orders || 0;
        }

        async function loadAvailableYears() {
            const yearFilter = document.getElementById('yearFilter');
            if (!yearFilter) return;

            const response = await apiCall('/analytics.php?action=available-years');
            const years = Array.isArray(response?.years) ? response.years : [];

            if (years.length === 0) {
                const currentYear = new Date().getFullYear();
                yearFilter.innerHTML = `<option value="${currentYear}">${currentYear}</option>`;
                return;
            }

            yearFilter.innerHTML = years
                .map((year) => `<option value="${year}">${year}</option>`)
                .join('');
        }

        document.addEventListener('DOMContentLoaded', function() {
            loadAvailableYears().then(loadPurchaseStats);
            <?php if (!$is_admin): ?>
            loadMySummary();
            <?php endif; ?>
        });
    </script>
</body>
</html>
