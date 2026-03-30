<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas y Análisis - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
    <!-- HEADER -->
    <header>
        <div class="header-content">
            <a href="dashboard.php" class="logo">🏪 Truper</a>
            <nav class="nav-menu">
                <a href="dashboard.php">Dashboard</a>
                <a href="orders.php">Pedidos</a>
                <a href="tasks.php">Tareas</a>
                <a href="analytics.php" class="active">Estadísticas</a>
                <a href="profile.php">Perfil</a>
            </nav>
        </div>
        <div class="user-menu">
            <div class="user-info">
                <div class="user-name">Usuario</div>
                <div class="user-role">Admin</div>
            </div>
            <button class="btn-logout" onclick="logout()">Cerrar Sesión</button>
        </div>
    </header>

    <main>
        <div class="container-fluid">
            <div class="d-flex justify-between align-center">
                <h1>Estadísticas y Análisis</h1>
                <button class="btn btn-primary" onclick="exportStats('csv')">
                    📥 Descargar Reporte
                </button>
            </div>

            <!-- TABS -->
            <div class="tabs">
                <button class="tab-button active" data-tab="purchaseStats">Compras</button>
                <button class="tab-button" data-tab="predictionsTab">Predicciones</button>
                <button class="tab-button" data-tab="seasonalTab">Temporadas</button>
                <button class="tab-button" data-tab="clientsTab">Clientes</button>
            </div>

            <!-- ESTADÍSTICAS DE COMPRAS -->
            <div id="purchaseStats" class="tab-content active">
                <div class="card">
                    <div class="card-header">Estadísticas de Compras por Mes</div>
                    <div class="card-body">
                        <div style="margin-bottom: 1.5rem;">
                            <label>Seleccionar Año:</label>
                            <select id="yearFilter" onchange="loadPurchaseStats()">
                                <option value="2024">2024</option>
                                <option value="2023">2023</option>
                                <option value="2022">2022</option>
                            </select>
                        </div>
                        <div id="purchaseStatsContainer"></div>
                    </div>
                </div>
            </div>

            <!-- PREDICCIONES DEL SISTEMA -->
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

            <!-- WIDGETS INFORMATIVOS -->
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
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2024 Truper Platform. Todos los derechos reservados.</p>
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
            const response = await apiCall(`/analytics/purchase-stats?year=${year}`);
            if (response && response.stats) {
                generateChart('purchaseStatsContainer', response.stats);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            loadPurchaseStats();
        });
    </script>
</body>
</html>
