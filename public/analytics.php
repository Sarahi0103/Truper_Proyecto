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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/theme.css">
    <link rel="stylesheet" href="css/responsive-complete.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/analytics.css">
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
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Mi Cuenta <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content">
                        <a href="dashboard.php">Dashboard</a>
                        <a href="orders.php">Pedidos</a>
                        <a href="wholesale.php">Mayoreo</a>
                        <a href="profile.php">Perfil</a>
                    </div>
                </div>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Administración <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content">
                        <?php if ($is_admin): ?><a href="cashier.php">Caja</a><?php endif; ?>
                        <?php if ($is_admin): ?><a href="admin_supply.php?nocache=true">Abastecimiento</a><?php endif; ?>
                        <?php if ($is_admin): ?><a href="tickets.php">Tickets</a><?php endif; ?>
                        <a href="tasks.php">Tareas</a>
                        <a href="analytics.php" class="active">Estadísticas</a>
                    </div>
                </div>
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
        <div class="analytics-shell">

            <!-- ── Hero ── -->
            <div class="analytics-hero">
                <div class="analytics-hero-inner">
                    <div class="analytics-hero-text">
                        <div class="analytics-hero-badge">
                            <span class="dot"></span>
                            Inteligencia de Negocios · v2.0
                        </div>
                        <h1><?php echo $is_admin ? '<span>Estadísticas</span> y Análisis' : 'Mis <span>Estadísticas</span>'; ?></h1>
                        <p>Panel de análisis avanzado en tiempo real. Visualiza el rendimiento, tendencias y predicciones basadas en IA para la toma de decisiones estratégicas.</p>
                    </div>
                    <?php if ($is_admin): ?>
                    <div style="display:flex; flex-direction:column; gap:0.6rem; align-items:flex-end;">
                        <button class="btn-analytics-primary" onclick="exportStats('csv')">
                            📥 Exportar CSV
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── Tab navigation ── -->
            <div class="analytics-tabs" role="tablist" aria-label="Secciones de estadísticas">
                <button class="analytics-tab-btn active" data-tab="metricsTab" role="tab" aria-selected="true">
                    <span class="tab-icon">📊</span> Métricas
                </button>
                <?php if ($is_admin): ?>
                <button class="analytics-tab-btn" data-tab="predictionsTab" role="tab" aria-selected="false">
                    <span class="tab-icon">🤖</span> Predicciones IA
                </button>
                <button class="analytics-tab-btn" data-tab="seasonalTab" role="tab" aria-selected="false">
                    <span class="tab-icon">🍂</span> Estacionalidad
                </button>
                <button class="analytics-tab-btn" data-tab="clientsTab" role="tab" aria-selected="false">
                    <span class="tab-icon">👥</span> Clientes
                </button>
                <button class="analytics-tab-btn" data-tab="goalsTab" role="tab" aria-selected="false">
                    <span class="tab-icon">🎯</span> Objetivos Mensuales
                </button>
                <?php endif; ?>
            </div>

            <!-- ══════════════════════════════════
                 TAB 1: MÉTRICAS
            ══════════════════════════════════ -->
            <div id="metricsTab" class="analytics-tab-panel active">

                <!-- View toggle + filters -->
                <div style="display:flex; align-items:flex-end; gap:1.25rem; flex-wrap:wrap; margin-bottom:1.75rem;">
                    <div>
                        <div style="font-size:0.72rem; font-weight:700; color:#555; text-transform:uppercase; letter-spacing:.06em; margin-bottom:.4rem;">Vista</div>
                        <div class="view-toggle-group">
                            <button class="view-toggle-btn active" id="btnMonthly" onclick="setView('monthly')">Mensual</button>
                            <button class="view-toggle-btn" id="btnYearly" onclick="setView('yearly')">Anual</button>
                            <button class="view-toggle-btn" id="btnCalendar" onclick="setView('calendar')">Calendario</button>
                        </div>
                    </div>

                    <div class="analytics-filter-group">
                        <label for="yearFilter">Año</label>
                        <select id="yearFilter" onchange="refreshCurrentView()">
                            <option value="">Cargando…</option>
                        </select>
                    </div>

                    <div class="analytics-filter-group" id="monthFilterGroup" style="display:none;">
                        <label for="monthFilter">Mes</label>
                        <select id="monthFilter" onchange="refreshCurrentView()">
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

                <!-- KPI cards (populated by JS) -->
                <div id="statsSummary" class="kpi-grid">
                    <!-- Skeleton placeholders while loading -->
                    <div class="kpi-card" style="animation:pulse-skeleton 1.5s ease infinite;">
                        <div class="kpi-card-label">Cargando…</div>
                        <div class="kpi-card-value" style="color:#222;">—</div>
                    </div>
                    <div class="kpi-card" style="animation:pulse-skeleton 1.5s ease infinite .15s;">
                        <div class="kpi-card-label">Cargando…</div>
                        <div class="kpi-card-value" style="color:#222;">—</div>
                    </div>
                    <div class="kpi-card" style="animation:pulse-skeleton 1.5s ease infinite .3s;">
                        <div class="kpi-card-label">Cargando…</div>
                        <div class="kpi-card-value" style="color:#222;">—</div>
                    </div>
                    <div class="kpi-card" style="animation:pulse-skeleton 1.5s ease infinite .45s;">
                        <div class="kpi-card-label">Cargando…</div>
                        <div class="kpi-card-value" style="color:#222;">—</div>
                    </div>
                </div>

                <!-- Chart card (bar/line) -->
                <div class="chart-card" id="chartCard">
                    <div class="chart-card-header">
                        <div class="chart-card-title">
                            <span class="chart-icon">📈</span>
                            <span id="chartTitle">Rendimiento Mensual</span>
                        </div>
                        <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
                            <?php if ($is_admin): ?>
                            <button class="btn-analytics-secondary" onclick="exportStats('csv')" style="font-size:.8rem; padding:.45rem .9rem;">
                                📥 Exportar CSV
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="chart-card-body">
                        <div class="chart-canvas-wrap">
                            <canvas id="mainChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Calendar card -->
                <div id="calendarCard" class="calendar-card" style="display:none;">
                    <div class="calendar-card-header">📅 Calendario de Actividad</div>
                    <div class="calendar-card-body">
                        <div id="calendarContainer"></div>
                    </div>
                </div>

            </div><!-- /metricsTab -->

            <?php if ($is_admin): ?>

            <!-- ══════════════════════════════════
                 TAB 2: PREDICCIONES IA
            ══════════════════════════════════ -->
            <div id="predictionsTab" class="analytics-tab-panel">
                <div class="ai-predictions-header">
                    <div>
                        <h2 style="margin:0 0 .35rem; font-size:1.3rem; font-weight:900; color:#fff;">Predicciones de Demanda con IA</h2>
                        <p style="margin:0; color:#666; font-size:.9rem;">Redes neuronales ligeras + análisis de series temporales para anticipar necesidades de inventario.</p>
                    </div>
                    <button class="btn-analytics-primary" id="btnGenPredictions" onclick="loadPredictions()">
                        ✨ Generar Análisis Inteligente
                    </button>
                </div>

                <div id="predictionsContainer" class="ai-predictions-grid">
                    <div class="analytics-empty" style="grid-column:1/-1;">
                        <div class="analytics-empty-icon">🤖</div>
                        <div class="analytics-empty-text">Haz clic en <strong>Generar Análisis Inteligente</strong> para procesar los datos históricos y obtener predicciones.</div>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════
                 TAB 3: ESTACIONALIDAD
            ══════════════════════════════════ -->
            <div id="seasonalTab" class="analytics-tab-panel">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
                    <div>
                        <h2 style="margin:0 0 .35rem; font-size:1.3rem; font-weight:900; color:#fff;">Análisis de Estacionalidad</h2>
                        <p style="margin:0; color:#666; font-size:.9rem;">Patrones estacionales basados en clima, eventos y datos históricos del catálogo.</p>
                    </div>
                    <button class="btn-analytics-primary" onclick="generateSeasonalReport()">
                        🔄 Sincronizar Datos
                    </button>
                </div>
                <div id="seasonalReport" class="seasonal-grid">
                    <div class="analytics-empty" style="grid-column:1/-1;">
                        <div class="analytics-empty-icon">🍂</div>
                        <div class="analytics-empty-text">Haz clic en <strong>Sincronizar Datos</strong> para cargar el análisis estacional.</div>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════
                 TAB 4: CLIENTES
            ══════════════════════════════════ -->
            <div id="clientsTab" class="analytics-tab-panel">
                <div style="margin-bottom:1.5rem;">
                    <h2 style="margin:0 0 .35rem; font-size:1.3rem; font-weight:900; color:#fff;">Segmentación de Clientes</h2>
                    <p style="margin:0; color:#666; font-size:.9rem;">Análisis de los top 100 clientes por volumen de compras, actividad y puntos de lealtad.</p>
                </div>
                <div id="clientAnalytics">
                    <div class="analytics-empty">
                        <div class="analytics-spinner"></div>
                        <div class="analytics-empty-text">Cargando análisis de clientes…</div>
                    </div>
                </div>
            </div>
            
            <!-- ══════════════════════════════════
                 TAB 5: OBJETIVOS MENSUALES
            ══════════════════════════════════ -->
            <div id="goalsTab" class="analytics-tab-panel">
                <div class="card" style="margin-bottom:2rem;">
                    <div class="card-body" style="padding:2rem;">
                        <h2 style="margin:0 0 .35rem; font-size:1.3rem; font-weight:900; color:#fff;">Objetivos Mensuales y Desglose Semanal</h2>
                        <p style="margin:0 0 1.5rem; color:#666; font-size:.9rem;">Establece el objetivo acumulado del mes para visualizar la progresión y desglose por semanas.</p>
                        
                        <div class="grid grid-3" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:1.25rem; margin-bottom:1.5rem;">
                            <div class="form-group">
                                <label for="goalMonth" style="display:block; margin-bottom:0.5rem; font-weight:600; font-size:0.85rem; color:#aaa;">Mes (YYYY-MM)</label>
                                <input id="goalMonth" type="text" placeholder="2026-05" style="width:100%; padding:0.6rem; border-radius:6px; border:1px solid var(--theme-border); background:var(--theme-surface-strong); color:#fff; box-sizing:border-box;">
                            </div>
                            <div class="form-group">
                                <label for="goalAmount" style="display:block; margin-bottom:0.5rem; font-weight:600; font-size:0.85rem; color:#aaa;">Meta ($)</label>
                                <input id="goalAmount" type="number" step="0.01" min="0" placeholder="0.00" style="width:100%; padding:0.6rem; border-radius:6px; border:1px solid var(--theme-border); background:var(--theme-surface-strong); color:#fff; box-sizing:border-box;">
                            </div>
                            <div class="form-group d-flex align-center" style="display:flex; align-items:flex-end;">
                                <button class="btn-analytics-primary" onclick="saveMonthlyGoal()" style="width:100%; border:none; padding:0.75rem 1.25rem; border-radius:6px; cursor:pointer; font-weight:700;">Guardar Meta</button>
                            </div>
                        </div>
                        
                        <div id="goalSummary" class="text-muted" style="margin-top:1.5rem; padding:1rem; background:var(--theme-surface-strong); border-radius:8px; line-height:1.6;">
                            Sin resumen de meta
                        </div>
                        
                        <div id="goalWeekly" class="text-muted" style="margin-top:1.5rem; overflow-x:auto;">
                            Sin desglose semanal
                        </div>
                    </div>
                </div>
            </div>

            <?php endif; ?>

            <!-- ── Info widgets (only admin) ── -->
            <?php if ($is_admin): ?>
            <div class="info-grid">
                <div class="info-card">
                    <div class="info-card-title">⚙️ Motor de Inteligencia Truper</div>
                    <ul class="info-feature-list">
                        <li><span class="feature-dot"></span>Análisis de regresión sobre 24 meses de historial</li>
                        <li><span class="feature-dot"></span>Ponderación por factores climáticos y temporada</li>
                        <li><span class="feature-dot"></span>Detección automática de tendencias y anomalías</li>
                        <li><span class="feature-dot"></span>Aprendizaje continuo con datos post-venta</li>
                    </ul>
                </div>
                <div class="info-card">
                    <div class="info-card-title">📈 Métricas de Impacto</div>
                    <div class="impact-metric">
                        <div class="impact-metric-value">+22%</div>
                        <div class="impact-metric-label">Eficiencia de Caja estimada</div>
                    </div>
                    <div style="margin-top:1rem; font-size:.82rem; color:#555; line-height:1.6;">
                        Basado en reducción de tiempo en consultas manuales y mejora en la gestión de inventario con alertas predictivas.
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /analytics-shell -->
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
    <style>
        @keyframes pulse-skeleton {
            0%, 100% { opacity: 1; }
            50% { opacity: .4; }
        }
    </style>
    <script>
        /* ── Logout ── */
        function logout() {
            if (confirm('¿Deseas cerrar sesión?')) {
                window.location.href = 'api/auth.php?action=logout';
            }
        }

        /* ── Tab switching ── */
        document.querySelectorAll('.analytics-tab-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const target = this.dataset.tab;

                document.querySelectorAll('.analytics-tab-btn').forEach(b => {
                    b.classList.remove('active');
                    b.setAttribute('aria-selected', 'false');
                });
                document.querySelectorAll('.analytics-tab-panel').forEach(p => p.classList.remove('active'));

                this.classList.add('active');
                this.setAttribute('aria-selected', 'true');
                const panel = document.getElementById(target);
                if (panel) panel.classList.add('active');

                // Auto-load seasonal tab
                if (target === 'seasonalTab') {
                    const container = document.getElementById('seasonalReport');
                    if (container && container.querySelector('.analytics-empty')) {
                        generateSeasonalReport();
                    }
                }

                // Auto-load goals tab
                if (target === 'goalsTab') {
                    loadGoalSummary();
                }
            });
        });

        /* ── View toggling ── */
        let currentView = 'monthly';

        function setView(view) {
            currentView = view;
            document.querySelectorAll('.view-toggle-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('btn' + view.charAt(0).toUpperCase() + view.slice(1)).classList.add('active');

            document.getElementById('chartCard').style.display    = view === 'calendar' ? 'none'  : 'block';
            document.getElementById('calendarCard').style.display = view === 'calendar' ? 'block' : 'none';
            document.getElementById('monthFilterGroup').style.display = view === 'calendar' ? 'block' : 'none';

            refreshCurrentView();
        }

        async function refreshCurrentView() {
            if      (currentView === 'monthly')  await loadPurchaseStats();
            else if (currentView === 'yearly')   await loadYearlyStats();
            else if (currentView === 'calendar') await loadCalendarStats();
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
            const year  = document.getElementById('yearFilter').value;
            const response = await apiCall(`/analytics.php?action=calendar-data&month=${month}&year=${year}`);
            if (response && response.days) renderCalendar(response.days, month, year);
        }

        async function loadAvailableYears() {
            const yearFilter = document.getElementById('yearFilter');
            if (!yearFilter) return;

            const response = await apiCall('/analytics.php?action=available-years');
            const years = Array.isArray(response?.years) ? response.years : [];

            const current = new Date().getFullYear();
            const opts = years.length > 0 ? years : [current];

            yearFilter.innerHTML = opts
                .map(y => `<option value="${y}" ${y == current ? 'selected' : ''}>${y}</option>`)
                .join('');
        }

        document.addEventListener('DOMContentLoaded', function () {
            const now = new Date();
            const monthFilter = document.getElementById('monthFilter');
            if (monthFilter) monthFilter.value = now.getMonth() + 1;

            loadAvailableYears().then(() => {
                setView('monthly');
                <?php if ($is_admin): ?>
                loadClientAnalytics();
                const goalMonth = document.getElementById('goalMonth');
                if (goalMonth && !goalMonth.value) {
                    goalMonth.value = new Date().toISOString().slice(0, 7);
                }
                loadGoalSummary();
                <?php endif; ?>
            });

            // Scroll to ticket tab if URL hash present
            if (window.location.hash === '#ticketsTab') {
                const btn = document.querySelector('[data-tab="ticketsTab"]');
                if (btn) btn.click();
            }
        });

        /* ── Objetivos Mensuales (Lógica Reubicada) ── */
        async function saveMonthlyGoal() {
            const monthKey = document.getElementById('goalMonth').value || new Date().toISOString().slice(0, 7);
            const targetAmount = document.getElementById('goalAmount').value || 0;
            const res = await apiCall('/cashier.php?action=goal-save', 'POST', {
                month_key: monthKey,
                target_amount: targetAmount
            });
            if (res && res.success) {
                showAlert(res.message, 'success');
                loadGoalSummary();
            } else if (res) {
                showAlert(res.message, 'error');
            }
        }

        async function loadGoalSummary() {
            const monthKey = document.getElementById('goalMonth').value || new Date().toISOString().slice(0, 7);
            const res = await apiCall(`/cashier.php?action=goal-summary&month_key=${encodeURIComponent(monthKey)}`);
            const summary = document.getElementById('goalSummary');
            const weekly = document.getElementById('goalWeekly');

            if (!res || !res.success) {
                if (summary) summary.textContent = 'No se pudo consultar la meta mensual';
                if (weekly) weekly.textContent = 'Sin desglose semanal';
                return;
            }

            const g = res.goal || {};
            const formatMoney = (val) => '$' + Number(val || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (summary) {
                summary.innerHTML = `
                    <div><strong>Meta mensual:</strong> ${formatMoney(g.target_amount)}</div>
                    <div><strong>Acumulado logrado:</strong> ${formatMoney(g.achieved_amount)}</div>
                    <div><strong>Monto restante:</strong> ${formatMoney(g.remaining_amount)}</div>
                    <div><strong>Avance del mes:</strong> ${Number(g.progress_pct || 0).toFixed(2)}%</div>
                `;
            }

            const rows = Array.isArray(res.weekly) ? res.weekly : [];
            if (rows.length === 0) {
                if (weekly) weekly.innerHTML = '<p class="text-muted">Sin ventas semanales para este mes.</p>';
                return;
            }

            if (weekly) {
                weekly.innerHTML = `
                    <table style="width:100%; border-collapse:collapse; margin-top:1rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--theme-border);">
                                <th style="padding:0.75rem; text-align:left; background:var(--theme-surface-strong); color:var(--theme-text);">Semana</th>
                                <th style="padding:0.75rem; text-align:right; background:var(--theme-surface-strong); color:var(--theme-text);">Objetivo semanal</th>
                                <th style="padding:0.75rem; text-align:right; background:var(--theme-surface-strong); color:var(--theme-text);">Acumulado logrado</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows.map((r) => `
                                <tr style="border-bottom: 1px solid var(--theme-border);">
                                    <td style="padding:0.75rem; color:var(--theme-text);">${r.week_start}</td>
                                    <td style="padding:0.75rem; text-align:right; color:var(--theme-text);">${formatMoney(r.week_target)}</td>
                                    <td style="padding:0.75rem; text-align:right; color:var(--theme-text);">${formatMoney(r.week_total)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            }
        }
    </script>
    <script src="js/mobile-optimize.js"></script>
</body>
</html>
