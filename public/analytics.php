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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Estadísticas y Análisis - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/theme.css">
    <link rel="stylesheet" href="css/responsive-complete.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- HEADER -->
    <header>
        <div class="header-content">
            <a href="dashboard.php" class="logo"><img src="images/truper-logo.svg" alt="Truper"></a>
            <nav class="nav-menu">
                <a href="index.php">Catálogo</a>
                <a href="marketplace_ce.php">Marketplace CE</a>
                <a href="cart.php">Carrito</a>
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
            <button class="btn-logout" onclick="logout()">Cerrar Sesión</button>
        </div>
    </header>

    <main>
        <div class="container-fluid admin-supply-shell">
            <div class="page-hero">
                <div class="module-badge module-admin"><span class="module-glyph">IA</span> Inteligencia de Negocios</div>
                <h1><?php echo $is_admin ? 'Estadísticas y Análisis' : 'Mis Estadísticas'; ?></h1>
                <p class="text-muted">Análisis detallado impulsado por IA para la toma de decisiones estratégicas.</p>
            </div>

            <!-- TABS -->
            <div class="tabs">
                <button class="tab-button active" data-tab="purchaseStats">Métricas</button>
                <?php if ($is_admin): ?>
                <button class="tab-button" data-tab="predictionsTab">Predicciones IA</button>
                <button class="tab-button" data-tab="seasonalTab">Análisis Temporal</button>
                <button class="tab-button" data-tab="clientsTab">Clientes</button>
                <?php endif; ?>
            </div>

            <div id="purchaseStats" class="tab-content active">
                <div class="view-toggle">
                    <button class="btn btn-secondary active" id="btnMonthly" onclick="setView('monthly')">Mensual</button>
                    <button class="btn btn-secondary" id="btnYearly" onclick="setView('yearly')">Anual</button>
                    <button class="btn btn-secondary" id="btnCalendar" onclick="setView('calendar')">Calendario</button>
                </div>

                <div id="filterSection" style="margin-bottom: 1.5rem; display: flex; gap: 1rem; align-items: center;">
                    <div id="yearFilterGroup">
                        <label style="display:block; font-size:0.8rem; color:var(--theme-text-muted); margin-bottom:0.3rem;">Año</label>
                        <select id="yearFilter" onchange="refreshCurrentView()" style="min-width:120px;">
                            <option value="">Cargando...</option>
                        </select>
                    </div>
                    <div id="monthFilterGroup" style="display:none;">
                        <label style="display:block; font-size:0.8rem; color:var(--theme-text-muted); margin-bottom:0.3rem;">Mes</label>
                        <select id="monthFilter" onchange="refreshCurrentView()" style="min-width:120px;">
                            <option value="1">Enero</option>
                            <option value="2">Febrero</option>
                            <option value="3">Marzo</option>
                            <option value="4">Abril</option>
                            <option value="5">Mayo</option>
                            <option value="6">Junio</option>
                            <option value="7">Julio</option>
                            <option value="8">Agosto</option>
                            <option value="9">Septiembre</option>
                            <option value="10">Octubre</option>
                            <option value="11">Noviembre</option>
                            <option value="12">Diciembre</option>
                        </select>
                    </div>
                </div>

                <div id="statsSummary" class="stats-grid">
                    <!-- Summary cards populated by JS -->
                </div>

                <div class="card" id="chartCard">
                    <div class="card-header d-flex justify-between align-center">
                        <span id="chartTitle">Rendimiento Mensual</span>
                        <div class="chart-actions">
                            <!-- Optional actions -->
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="chartContainer" class="chart-container">
                            <canvas id="mainChart"></canvas>
                        </div>
                    </div>
                </div>

                <div id="calendarCard" class="card" style="display:none;">
                    <div class="card-header">Calendario de Actividad</div>
                    <div class="card-body">
                        <div id="calendarContainer" class="calendar-container"></div>
                    </div>
                </div>
            </div>

            <!-- PREDICCIONES DEL SISTEMA -->
            <?php if ($is_admin): ?>
            <div id="predictionsTab" class="tab-content">
                <div class="card" style="background: linear-gradient(135deg, var(--theme-card) 0%, rgba(255,127,0,0.05) 100%);">
                    <div class="card-header d-flex justify-between align-center">
                        <span>Predicciones de Demanda con IA</span>
                        <button class="btn btn-primary" onclick="loadPredictions()" style="background:var(--color-naranja); border:none;">
                            ✨ Generar Análisis Inteligente
                        </button>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">Utilizando redes neuronales ligeras y análisis de series temporales para predecir tus necesidades de inventario.</p>
                        <div id="predictionsContainer" class="grid grid-3" style="margin-top: 2rem; gap: 1.5rem;">
                            <div class="empty-state" style="grid-column: 1/-1; text-align:center; padding: 3rem;">
                                <img src="images/ai-icon.svg" alt="IA" style="width:64px; opacity:0.3; margin-bottom:1rem;">
                                <p>Haz clic en el botón superior para procesar los datos históricos.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ANÁLISIS POR TEMPORADA -->
            <div id="seasonalTab" class="tab-content">
                <div class="card">
                    <div class="card-header">Reporte de Estacionalidad</div>
                    <div class="card-body">
                        <p class="text-muted mb-3">Descubre patrones estacionales basados en el clima y eventos del calendario histórico.</p>
                        <button class="btn btn-secondary" onclick="generateSeasonalReport()">Sincronizar Datos</button>
                        <div id="seasonalReport" style="margin-top: 2rem;"></div>
                    </div>
                </div>
            </div>

            <!-- ANÁLISIS DE CLIENTES -->
            <div id="clientsTab" class="tab-content">
                <div class="card">
                    <div class="card-header">Segmentación de Clientes (Top 100)</div>
                    <div class="card-body">
                        <div id="clientAnalytics" class="table-responsive"></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- WIDGETS INFORMATIVOS -->
            <?php if ($is_admin): ?>
            <div class="grid grid-2" style="margin-top: 2rem; gap: 1.5rem;">
                <div class="card">
                    <div class="card-body">
                        <h4 style="color:var(--color-naranja); margin-bottom:1rem;">Motor de Inteligencia Truper</h4>
                        <ul style="list-style:none; padding:0; line-height:2;">
                            <li><span style="color:var(--color-naranja); margin-right:8px;">●</span> Análisis de regresión sobre 24 meses</li>
                            <li><span style="color:var(--color-naranja); margin-right:8px;">●</span> Ponderación por factores climáticos (API Weather)</li>
                            <li><span style="color:var(--color-naranja); margin-right:8px;">●</span> Detección automática de tendencias</li>
                            <li><span style="color:var(--color-naranja); margin-right:8px;">●</span> Aprendizaje continuo post-venta</li>
                        </ul>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h4 style="color:var(--color-naranja); margin-bottom:1rem;">Métricas de Impacto</h4>
                        <div style="display: inline-flex; gap: 0.3rem; align-items: center;">
                            <input type="number" id="estimatedHours" min="0" max="99" placeholder="H" style="width: 50px; padding: 0.4rem; text-align: center; background: #ffffff !important; color: #000000 !important; border: 2px solid #ff7f00 !important; font-weight: 800;" title="Horas">
                            <span style="font-weight: bold; color: #ff7f00;">h</span>
                            <input type="number" id="estimatedMins" min="0" max="59" placeholder="M" style="width: 50px; padding: 0.4rem; text-align: center; background: #ffffff !important; color: #000000 !important; border: 2px solid #ff7f00 !important; font-weight: 800;" title="Minutos">
                            <span style="font-weight: bold; color: #ff7f00;">m</span>
                        </div>
                        <div style="background:rgba(255,127,0,0.05); padding:1rem; border-radius:12px; text-align:center;">
                            <div style="font-weight:700; font-size:1.4rem;">+22%</div>
                            <div style="font-size:0.75rem; color:var(--theme-text-muted);">Eficiencia de Caja</div>
                        </div>
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
                <h4>Truper IA</h4>
                <p>Módulo de Inteligencia de Negocios v2.0</p>
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

        let currentView = 'monthly';

        function setView(view) {
            currentView = view;
            document.querySelectorAll('.view-toggle .btn').forEach(b => b.classList.remove('active'));
            document.getElementById('btn' + view.charAt(0).toUpperCase() + view.slice(1)).classList.add('active');
            
            document.getElementById('chartCard').style.display = view === 'calendar' ? 'none' : 'block';
            document.getElementById('calendarCard').style.display = view === 'calendar' ? 'block' : 'none';
            document.getElementById('monthFilterGroup').style.display = view === 'calendar' ? 'block' : 'none';
            
            refreshCurrentView();
        }

        async function refreshCurrentView() {
            if (currentView === 'monthly') {
                await loadPurchaseStats();
            } else if (currentView === 'yearly') {
                await loadYearlyStats();
            } else if (currentView === 'calendar') {
                await loadCalendarStats();
            }
        }

        async function loadPurchaseStats() {
            const year = document.getElementById('yearFilter').value;
            const response = await apiCall(`/analytics.php?action=purchase-stats&year=${year}`);
            if (response && response.stats) {
                renderMonthlyChart(response.stats);
                updateSummaryStats(response.stats);
            }
        }

        async function loadYearlyStats() {
            const response = await apiCall('/analytics.php?action=yearly-stats');
            if (response && response.stats) {
                renderYearlyChart(response.stats);
                updateSummaryStats(response.stats, true);
            }
        }

        async function loadCalendarStats() {
            const month = document.getElementById('monthFilter').value;
            const year = document.getElementById('yearFilter').value;
            const response = await apiCall(`/analytics.php?action=calendar-data&month=${month}&year=${year}`);
            if (response && response.days) {
                renderCalendar(response.days, month, year);
            }
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
                .map((year) => `<option value="${year}" ${year == new Date().getFullYear() ? 'selected' : ''}>${year}</option>`)
                .join('');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            const monthFilter = document.getElementById('monthFilter');
            if (monthFilter) monthFilter.value = now.getMonth() + 1;

            loadAvailableYears().then(() => {
                setView('monthly');
                <?php if ($is_admin): ?>
                loadClientAnalytics();
                <?php endif; ?>
            });
        });
    </script>
    <script src="js/mobile-optimize.js"></script>
</body>
</html>
