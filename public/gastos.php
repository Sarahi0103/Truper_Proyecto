<?php
require_once '../config/config.php';
require_admin();

$user_name = htmlspecialchars($_SESSION['name'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
$user_role = htmlspecialchars($_SESSION['role'] ?? 'client', ENT_QUOTES, 'UTF-8');
$is_admin  = (($_SESSION['role'] ?? '') === 'admin');
$is_admin_or_employee = ($is_admin || $user_role === 'employee');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Gastos - Truper Platform</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css?v=4.1">
    <link rel="stylesheet" href="css/theme.css?v=4.1">
    <link rel="stylesheet" href="css/responsive-complete.css?v=5.0">
    <link rel="stylesheet" href="css/dashboard.css?v=3.0">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --accent: #ff7f00;
            --accent-dim: rgba(255,127,0,0.12);
            --accent-border: rgba(255,127,0,0.3);
            --red: #e74c3c;
            --red-dim: rgba(231,76,60,0.12);
            --green: #2ecc71;
            --green-dim: rgba(46,204,113,0.12);
            --blue: #3498db;
            --blue-dim: rgba(52,152,219,0.12);
            --surface: #121212;
            --surface2: #1a1a1a;
            --border: #222;
            --text: #fff;
            --muted: #666;
        }

        body { background: var(--surface); color: var(--text); font-family: 'Inter', sans-serif; }

        .expenses-shell {
            max-width: 1280px;
            margin: 0 auto;
            padding: 2rem 1.5rem 4rem;
        }

        /* ── Hero ── */
        .expenses-hero {
            background: linear-gradient(135deg, #1a1a1a 0%, #0d0d0d 100%);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 2.5rem 2rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: var(--red-dim);
            border: 1px solid rgba(231,76,60,0.3);
            border-radius: 999px;
            padding: 0.3rem 0.9rem;
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--red);
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 0.75rem;
        }
        .hero-badge .dot { width:7px; height:7px; border-radius:50%; background:var(--red); }
        .expenses-hero h1 { font-size: clamp(1.6rem, 4vw, 2.2rem); font-weight: 900; margin: 0 0 0.35rem; }
        .expenses-hero h1 span { color: var(--accent); }
        .expenses-hero p { color: var(--muted); font-size: 0.9rem; margin: 0; }

        /* ── KPI Grid ── */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .kpi-card {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 1.4rem 1.5rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .kpi-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.3); }
        .kpi-label { font-size: 0.72rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 0.5rem; }
        .kpi-value { font-size: 1.7rem; font-weight: 900; line-height: 1; }
        .kpi-sub { font-size: 0.75rem; color: var(--muted); margin-top: 0.35rem; }

        /* ── Two-column layout ── */
        .expenses-grid {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 1.5rem;
            align-items: start;
        }
        @media (max-width: 900px) {
            .expenses-grid { grid-template-columns: 1fr; }
        }

        /* ── Form Card ── */
        .form-card {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.75rem;
            position: sticky;
            top: 1rem;
        }
        .form-card h3 { font-size: 1rem; font-weight: 800; color: var(--text); margin: 0 0 1.5rem; display: flex; align-items: center; gap: 0.5rem; }
        .form-group { margin-bottom: 1.1rem; }
        .form-group label { display: block; font-size: 0.78rem; font-weight: 700; color: #aaa; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.45rem; }
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.7rem 0.9rem;
            background: #0d0d0d;
            border: 1px solid #2a2a2a;
            border-radius: 8px;
            color: var(--text);
            font-family: 'Inter', sans-serif;
            font-size: 0.92rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
        }
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-dim);
        }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .form-group select option { background: #1a1a1a; }
        .btn-submit {
            width: 100%;
            padding: 0.85rem 1.5rem;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 0.5rem;
        }
        .btn-submit:hover { background: #e67300; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(255,127,0,0.35); }
        .btn-submit:disabled { background: #333; cursor: not-allowed; transform: none; box-shadow: none; }

        /* ── Expenses List Card ── */
        .list-card {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
        }
        .list-card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .list-card-header h3 { font-size: 1rem; font-weight: 800; margin: 0; }
        .filter-bar { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .filter-bar select,
        .filter-bar input {
            padding: 0.45rem 0.75rem;
            background: #0d0d0d;
            border: 1px solid #2a2a2a;
            border-radius: 7px;
            color: var(--text);
            font-size: 0.82rem;
        }

        .expenses-table { width: 100%; border-collapse: collapse; }
        .expenses-table th {
            padding: 0.9rem 1.25rem;
            text-align: left;
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            background: #111;
            border-bottom: 1px solid var(--border);
        }
        .expenses-table td {
            padding: 0.9rem 1.25rem;
            border-bottom: 1px solid #1a1a1a;
            font-size: 0.88rem;
            vertical-align: middle;
        }
        .expenses-table tr:last-child td { border-bottom: none; }
        .expenses-table tr:hover td { background: rgba(255,255,255,0.02); }

        .category-badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
            background: var(--accent-dim);
            border: 1px solid var(--accent-border);
            color: var(--accent);
        }
        .amount-cell { font-weight: 800; color: var(--red); font-size: 0.95rem; }

        .btn-delete {
            background: var(--red-dim);
            border: 1px solid rgba(231,76,60,0.3);
            color: var(--red);
            padding: 0.3rem 0.7rem;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-delete:hover { background: var(--red); color: #fff; }

        /* ── Chart section ── */
        .chart-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        @media (max-width: 768px) { .chart-row { grid-template-columns: 1fr; } }

        .chart-card {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.5rem;
        }
        .chart-card h4 { font-size: 0.85rem; font-weight: 700; color: #aaa; text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 1rem; }

        /* ── Alert ── */
        .alert-box {
            padding: 0.85rem 1.25rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.88rem;
            margin-bottom: 1rem;
            display: none;
        }
        .alert-success { background: var(--green-dim); border: 1px solid rgba(46,204,113,0.3); color: var(--green); display: block; }
        .alert-error   { background: var(--red-dim);   border: 1px solid rgba(231,76,60,0.3);  color: var(--red);   display: block; }

        /* ── Pagination ── */
        .pagination { display: flex; gap: 0.5rem; align-items: center; justify-content: center; padding: 1rem; flex-wrap: wrap; }
        .page-btn {
            padding: 0.4rem 0.9rem;
            border-radius: 7px;
            border: 1px solid var(--border);
            background: #0d0d0d;
            color: var(--text);
            font-size: 0.82rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .page-btn:hover, .page-btn.active { background: var(--accent); border-color: var(--accent); color: #fff; }
        .page-btn:disabled { opacity: 0.3; cursor: not-allowed; }

        .empty-state { text-align: center; padding: 3rem 1rem; color: var(--muted); }
        .empty-state .icon { font-size: 2.5rem; margin-bottom: 0.75rem; }

        @media (max-width: 600px) {
            .expenses-table th:nth-child(3),
            .expenses-table td:nth-child(3) { display: none; }
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    <header>
        <div class="header-content">
            <a href="dashboard.php" class="logo"><img src="img/logo_truper.1.1.png" alt="Truper" style="height: 40px; width: auto; object-fit: contain;"></a>
            <button class="hamburger-btn" aria-label="Toggle menu">
                <span></span><span></span><span></span>
            </button>
            <nav class="nav-menu">
                <a href="index.php">Catálogo</a>
                <a href="marketplace_ce.php">Marketplace CE</a>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Mi Cuenta <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content">
                        <a href="dashboard.php">Dashboard</a>
                        <a href="orders.php">Pedidos</a>
                        <a href="wholesale.php">Mayoreo</a>
                        <a href="account.php#historyTab">Historial</a>
                        <a href="profile.php">Perfil</a>
                    </div>
                </div>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Administración <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content">
                        <?php if ($is_admin_or_employee): ?><a href="cashier.php">Caja</a><?php endif; ?>
                        <?php if ($is_admin_or_employee): ?><a href="admin_supply.php?nocache=true">Abastecimiento</a><?php endif; ?>
                        <?php if ($is_admin_or_employee): ?><a href="tickets.php">Tickets</a><?php endif; ?>
                        <a href="tasks.php">Tareas</a>
                        <?php if ($is_admin_or_employee): ?><a href="gastos.php" class="active">Gastos</a><?php endif; ?>
                        <?php if ($is_admin): ?>
                            <a href="analytics.php">Estadísticas</a>
                        <?php endif; ?>
                    </div>
                </div>
            </nav>
        </div>
        <div class="user-menu">
            <div class="user-info">
                <div class="user-name"><?php echo $user_name; ?></div>
                <div class="user-role"><?php echo ($user_role === 'employee') ? 'PERSONAL' : strtoupper($user_role); ?></div>
            </div>
            <button class="btn-logout" onclick="if(confirm('¿Cerrar sesión?')) window.location.href='api/auth.php?action=logout'">Cerrar Sesión</button>
        </div>
    </header>

    <main>
        <div class="expenses-shell">
            <!-- ── Back Button ── -->
            <div class="back-header">
                <button onclick="history.back()" class="btn-back btn-back-dark btn-back-as-button">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Regresar
                </button>
            </div>

            <!-- Hero -->
            <div class="page-hero" style="display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 280px;">
                    <div class="module-badge module-admin" style="margin-bottom: 1rem !important;"><span class="module-glyph">AD</span> Módulo administrativo</div>
                    <h1 style="margin-top: 0; color: #fff !important; font-size: 2.5rem !important; font-weight: 900 !important;"><span>Gastos</span> del Negocio</h1>
                    <p style="color: #888888 !important; font-size: 1.15rem !important; max-width: 700px; margin-bottom: 0;">Registra y controla todos los gastos. Visualiza la ganancia neta descontando los egresos acumulados.</p>
                </div>
                <div style="display:flex; gap:0.75rem; flex-wrap:wrap; z-index: 5;">
                    <select id="monthPicker" onchange="loadAll()" style="padding:0.6rem 1.2rem; background:#111 !important; border:1.5px solid #333 !important; border-radius:10px !important; color:#fff !important; font-weight:600; cursor:pointer; font-size:0.9rem;">
                        <!-- months filled by JS -->
                    </select>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="kpi-grid" id="kpiGrid">
                <div class="kpi-card" style="border-color:rgba(46,204,113,0.3);">
                    <div class="kpi-label">💰 Ingresos del Mes</div>
                    <div class="kpi-value" id="kpiIncome" style="color:#2ecc71;">—</div>
                    <div class="kpi-sub">Total de ventas validadas</div>
                </div>
                <div class="kpi-card" style="border-color:rgba(231,76,60,0.3);">
                    <div class="kpi-label">💸 Gastos del Mes</div>
                    <div class="kpi-value" id="kpiExpenses" style="color:#e74c3c;">—</div>
                    <div class="kpi-sub" id="kpiExpensesSub">0 registros</div>
                </div>
                <div class="kpi-card" id="kpiNetCard" style="border-color:rgba(255,127,0,0.3);">
                    <div class="kpi-label">📊 Ganancia Neta</div>
                    <div class="kpi-value" id="kpiNet" style="color:#ff7f00;">—</div>
                    <div class="kpi-sub">Ingresos − Gastos</div>
                </div>
                <div class="kpi-card" style="border-color:#222;">
                    <div class="kpi-label">📅 Gastos del Año</div>
                    <div class="kpi-value" id="kpiYear" style="color:#aaa;">—</div>
                    <div class="kpi-sub" id="kpiYearSub">0 registros</div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="chart-row">
                <div class="chart-card">
                    <h4>📈 Tendencia de Gastos (12 meses)</h4>
                    <canvas id="trendChart" style="max-height:220px;"></canvas>
                </div>
                <div class="chart-card">
                    <h4>🍩 Gastos por Categoría</h4>
                    <canvas id="catChart" style="max-height:220px;"></canvas>
                </div>
            </div>

            <!-- Alert -->
            <div id="alertBox" class="alert-box"></div>

            <!-- Main 2-col grid -->
            <div class="expenses-grid">

                <!-- ── Formulario ── -->
                <div class="form-card">
                    <h3>➕ Registrar Gasto</h3>

                    <div class="form-group">
                        <label for="expTitle">Título *</label>
                        <input type="text" id="expTitle" placeholder="Ej: Renta del local" maxlength="255">
                    </div>
                    <div class="form-group">
                        <label for="expCategory">Categoría</label>
                        <select id="expCategory">
                            <option value="General">General</option>
                            <option value="Renta">Renta</option>
                            <option value="Servicios">Servicios (Luz, Agua, Internet)</option>
                            <option value="Nómina">Nómina / Sueldos</option>
                            <option value="Proveedores">Proveedores</option>
                            <option value="Transporte">Transporte / Envíos</option>
                            <option value="Marketing">Marketing / Publicidad</option>
                            <option value="Equipo">Equipo / Herramientas</option>
                            <option value="Mantenimiento">Mantenimiento</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="expAmount">Monto ($) *</label>
                        <input type="number" id="expAmount" placeholder="0.00" min="0.01" step="0.01">
                    </div>
                    <div class="form-group">
                        <label for="expDesc">Descripción</label>
                        <textarea id="expDesc" placeholder="Detalles adicionales (opcional)..."></textarea>
                    </div>
                    <button class="btn-submit" id="btnSubmit" onclick="submitExpense()">
                        💾 Guardar Gasto
                    </button>
                </div>

                <!-- ── Lista de Gastos ── -->
                <div class="list-card">
                    <div class="list-card-header">
                        <h3>📋 Registro de Gastos</h3>
                        <div class="filter-bar">
                            <select id="filterCategory" onchange="currentPage=1; loadExpenses()">
                                <option value="">Todas las categorías</option>
                                <option value="General">General</option>
                                <option value="Renta">Renta</option>
                                <option value="Servicios">Servicios</option>
                                <option value="Nómina">Nómina</option>
                                <option value="Proveedores">Proveedores</option>
                                <option value="Transporte">Transporte</option>
                                <option value="Marketing">Marketing</option>
                                <option value="Equipo">Equipo</option>
                                <option value="Mantenimiento">Mantenimiento</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>
                    </div>

                    <div style="overflow-x:auto;">
                        <table class="expenses-table">
                            <thead>
                                <tr>
                                    <th>Título</th>
                                    <th>Categoría</th>
                                    <th>Descripción</th>
                                    <th>Monto</th>
                                    <th>Fecha</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="expensesBody">
                                <tr><td colspan="6" style="text-align:center; padding:2rem; color:#555;">Cargando...</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="pagination" id="paginationEl"></div>
                </div>

            </div>
        </div>
    </main>

    <script src="js/main.js?v=3.0"></script>
    <script src="js/mobile-optimize.js?v=3.0"></script>
    <script>
        window.csrfToken = '<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8"); ?>';

        let currentPage = 1;
        let trendChart  = null;
        let catChart    = null;

        /* ── Month picker ── */
        function initMonthPicker() {
            const sel = document.getElementById('monthPicker');
            if (!sel) return;
            sel.innerHTML = '';
            const now = new Date();
            const startYear = 2026;
            const startMonth = 5; // June is index 5
            
            const currentYear = now.getFullYear();
            const currentMonth = now.getMonth();
            
            let totalMonths = (currentYear - startYear) * 12 + (currentMonth - startMonth);
            if (totalMonths < 0) totalMonths = 0;
            
            for (let i = 0; i <= totalMonths; i++) {
                const d = new Date(currentYear, currentMonth - i, 1);
                if (d.getFullYear() < startYear || (d.getFullYear() === startYear && d.getMonth() < startMonth)) {
                    continue;
                }
                const val = d.toISOString().slice(0, 7);
                const label = d.toLocaleDateString('es-MX', { year: 'numeric', month: 'long' });
                const opt = document.createElement('option');
                opt.value = val;
                opt.textContent = label.charAt(0).toUpperCase() + label.slice(1);
                if (i === 0) opt.selected = true;
                sel.appendChild(opt);
            }
        }

        function getMonth() { return document.getElementById('monthPicker').value; }

        /* ── Load all ── */
        async function loadAll() {
            await Promise.all([loadStats(), loadExpenses()]);
        }

        /* ── Stats + KPIs ── */
        async function loadStats() {
            const month = getMonth();
            const year  = month.slice(0, 4);
            const res = await apiFetch(`api/expenses.php?action=stats&month=${month}&year=${year}`);
            if (!res || !res.success) return;

            const fmt = v => '$' + Number(v||0).toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2});

            document.getElementById('kpiIncome').textContent    = fmt(res.total_income);
            document.getElementById('kpiExpenses').textContent  = fmt(res.total_expenses);
            document.getElementById('kpiExpensesSub').textContent = res.count_month + ' registro(s)';
            document.getElementById('kpiYear').textContent      = fmt(res.total_year);
            document.getElementById('kpiYearSub').textContent   = res.count_year + ' registro(s)';

            const net = res.net_profit;
            const netEl = document.getElementById('kpiNet');
            netEl.textContent = fmt(net);
            netEl.style.color = net >= 0 ? '#2ecc71' : '#e74c3c';
            document.getElementById('kpiNetCard').style.borderColor = net >= 0 ? 'rgba(46,204,113,0.3)' : 'rgba(231,76,60,0.3)';

            renderTrendChart(res.monthly_trend || []);
            renderCatChart(res.by_category || []);
        }

        /* ── Trend Chart ── */
        function renderTrendChart(data) {
            const ctx = document.getElementById('trendChart').getContext('2d');
            if (trendChart) trendChart.destroy();
            const labels = data.map(d => {
                const [y,m] = d.month_key.split('-');
                return new Date(y, m-1).toLocaleDateString('es-MX',{month:'short',year:'2-digit'});
            });
            const values = data.map(d => parseFloat(d.total));
            trendChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Gastos',
                        data: values,
                        backgroundColor: 'rgba(231,76,60,0.6)',
                        borderColor: '#e74c3c',
                        borderWidth: 1,
                        borderRadius: 4,
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: '#666', font:{size:10} }, grid: { color:'#1a1a1a' } },
                        y: { ticks: { color: '#666', callback: v => '$'+Number(v).toLocaleString('es-MX') }, grid: { color:'#1a1a1a' } }
                    }
                }
            });
        }

        /* ── Category Chart ── */
        function renderCatChart(data) {
            const ctx = document.getElementById('catChart').getContext('2d');
            if (catChart) catChart.destroy();
            if (!data.length) return;
            const colors = ['#e74c3c','#ff7f00','#f1c40f','#2ecc71','#3498db','#9b59b6','#1abc9c','#e67e22','#c0392b','#2980b9'];
            catChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.map(d => d.category),
                    datasets: [{
                        data: data.map(d => parseFloat(d.total)),
                        backgroundColor: colors.slice(0, data.length),
                        borderColor: '#121212',
                        borderWidth: 2,
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position:'right', labels:{ color:'#aaa', font:{size:11}, padding:12 } }
                    }
                }
            });
        }

        /* ── Load expenses list ── */
        async function loadExpenses() {
            const month    = getMonth();
            const category = document.getElementById('filterCategory').value;
            let url = `api/expenses.php?action=list&month=${month}&page=${currentPage}&per_page=15`;
            if (category) url += `&category=${encodeURIComponent(category)}`;

            const res = await apiFetch(url);
            const tbody = document.getElementById('expensesBody');

            if (!res || !res.success || !res.expenses.length) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <div class="icon">💸</div>
                                <div>No hay gastos registrados en este período.</div>
                            </div>
                        </td>
                    </tr>`;
                document.getElementById('paginationEl').innerHTML = '';
                return;
            }

            const fmt = v => '$' + Number(v||0).toLocaleString('es-MX', {minimumFractionDigits:2});
            const fmtDate = s => new Date(s).toLocaleDateString('es-MX', {day:'numeric',month:'short',year:'numeric'});

            tbody.innerHTML = res.expenses.map(e => `
                <tr>
                    <td style="font-weight:700; color:#fff;">${escHtml(e.title)}</td>
                    <td><span class="category-badge">${escHtml(e.category)}</span></td>
                    <td style="color:#888; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${escHtml(e.description||'')}">
                        ${e.description ? escHtml(e.description) : '<em style="color:#444;">—</em>'}
                    </td>
                    <td class="amount-cell">${fmt(e.amount)}</td>
                    <td style="color:#666; white-space:nowrap;">${fmtDate(e.created_at)}</td>
                    <td>
                        <button class="btn-delete" onclick="deleteExpense(${e.id}, '${escHtml(e.title)}')">✕</button>
                    </td>
                </tr>
            `).join('');

            renderPagination(res.pagination);
        }

        function renderPagination(p) {
            const el = document.getElementById('paginationEl');
            if (p.total_pages <= 1) { el.innerHTML = ''; return; }
            let html = `<button class="page-btn" onclick="gotoPage(${p.page-1})" ${p.page<=1?'disabled':''}>‹</button>`;
            for (let i = 1; i <= p.total_pages; i++) {
                html += `<button class="page-btn ${i===p.page?'active':''}" onclick="gotoPage(${i})">${i}</button>`;
            }
            html += `<button class="page-btn" onclick="gotoPage(${p.page+1})" ${p.page>=p.total_pages?'disabled':''}>›</button>`;
            el.innerHTML = html;
        }

        function gotoPage(p) { currentPage = p; loadExpenses(); }

        /* ── Submit expense ── */
        async function submitExpense() {
            const title    = document.getElementById('expTitle').value.trim();
            const amount   = parseFloat(document.getElementById('expAmount').value);
            const category = document.getElementById('expCategory').value;
            const desc     = document.getElementById('expDesc').value.trim();

            if (!title)          { showAlert('El título es requerido', 'error'); return; }
            if (!amount || amount <= 0) { showAlert('El monto debe ser mayor a 0', 'error'); return; }

            const btn = document.getElementById('btnSubmit');
            btn.disabled = true;
            btn.textContent = 'Guardando...';

            const res = await apiFetch('api/expenses.php?action=create', 'POST', {
                title, amount, category, description: desc,
                csrf_token: window.csrfToken
            });

            btn.disabled = false;
            btn.textContent = '💾 Guardar Gasto';

            if (res && res.success) {
                showAlert('✅ Gasto registrado correctamente', 'success');
                document.getElementById('expTitle').value  = '';
                document.getElementById('expAmount').value = '';
                document.getElementById('expDesc').value   = '';
                document.getElementById('expCategory').value = 'General';
                currentPage = 1;
                await loadAll();
            } else {
                showAlert(res?.message || 'Error al guardar', 'error');
            }
        }

        /* ── Delete expense ── */
        async function deleteExpense(id, title) {
            if (!confirm(`¿Eliminar el gasto "${title}"? Esta acción no se puede deshacer.`)) return;

            const res = await apiFetch('api/expenses.php?action=delete', 'POST', {
                id, csrf_token: window.csrfToken
            });

            if (res && res.success) {
                showAlert('Gasto eliminado', 'success');
                await loadAll();
            } else {
                showAlert(res?.message || 'Error al eliminar', 'error');
            }
        }

        /* ── Helpers ── */
        async function apiFetch(url, method = 'GET', body = null) {
            try {
                const opts = { method, headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.csrfToken || '' } };
                if (body) opts.body = JSON.stringify(body);
                const r = await fetch(url, opts);
                return await r.json();
            } catch (e) {
                console.error('apiFetch error:', e);
                return null;
            }
        }

        function escHtml(s) {
            if (!s) return '';
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        function showAlert(msg, type) {
            const el = document.getElementById('alertBox');
            el.textContent = msg;
            el.className = 'alert-box alert-' + type;
            setTimeout(() => { el.className = 'alert-box'; }, 5000);
        }

        /* ── Init ── */
        document.addEventListener('DOMContentLoaded', () => {
            initMonthPicker();
            loadAll();
        });
    </script>
</body>
</html>
