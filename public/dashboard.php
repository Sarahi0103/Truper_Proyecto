<?php
require_once '../config/config.php';
require_login();

$user_name  = htmlspecialchars(($_SESSION['role'] ?? '') === 'admin' ? 'admin' : ($_SESSION['name'] ?? 'Usuario'), ENT_QUOTES, 'UTF-8');
$user_role  = htmlspecialchars($_SESSION['role'] ?? 'client', ENT_QUOTES, 'UTF-8');
$is_admin   = (($_SESSION['role'] ?? '') === 'admin');
$first_name = explode(' ', $user_name)[0];

// Greeting is rendered client-side (JS) to use the user's local timezone
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Dashboard - Truper Platform</title>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css?v=2.2">
    <link rel="stylesheet" href="css/theme.css?v=2.5">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/responsive-complete.css?v=2.2">
    <style>
        /* ── Dashboard Premium Overrides ── */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');

        .db-shell {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1.5rem 4rem;
            font-family: 'Inter', sans-serif;
        }

        /* Welcome hero */
        .db-hero {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #0d0d0d 0%, #111 60%, #0d0d0d 100%);
            border: 1px solid #1f1f1f;
            border-radius: 20px;
            padding: 2rem 2.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .db-hero::before {
            content: '';
            position: absolute;
            top: -100px; left: -100px;
            width: 350px; height: 350px;
            background: radial-gradient(circle, rgba(255,127,0,.10) 0%, transparent 70%);
            pointer-events: none;
        }

        .db-hero::after {
            content: '';
            position: absolute;
            bottom: -80px; right: -80px;
            width: 250px; height: 250px;
            background: radial-gradient(circle, rgba(255,127,0,.05) 0%, transparent 70%);
            pointer-events: none;
        }

        .db-hero-left { position: relative; z-index: 1; }

        .db-greeting-badge {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            background: rgba(255,127,0,.10);
            border: 1px solid rgba(255,127,0,.2);
            border-radius: 999px;
            padding: .3rem .9rem;
            font-size: .75rem;
            font-weight: 700;
            color: #ff9a33;
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: .85rem;
        }

        .db-greeting-badge .live-dot {
            width: 7px; height: 7px;
            background: #2ecc71;
            border-radius: 50%;
            animation: pulse-dot 1.8s ease-in-out infinite;
        }

        @keyframes pulse-dot {
            0%,100% { transform: scale(1); opacity: 1; }
            50%      { transform: scale(1.5); opacity: .7; }
        }

        .db-hero h1 {
            font-size: 2rem;
            font-weight: 900;
            color: #fff;
            margin: 0 0 .4rem;
            letter-spacing: -.03em;
            line-height: 1.1;
        }

        .db-hero h1 span { color: #ff7f00; }

        .db-hero-sub {
            font-size: .9rem;
            color: #666;
            margin: 0;
            line-height: 1.5;
        }

        .db-hero-right {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: .75rem;
            flex-shrink: 0;
        }

        .db-hero-visual {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }

        .db-hero-stat {
            text-align: right;
        }

        .db-hero-stat-val {
            font-size: 1.5rem;
            font-weight: 800;
            color: #ff9a33;
            letter-spacing: -.03em;
            line-height: 1;
        }

        .db-hero-stat-label {
            font-size: .68rem;
            color: #555;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-top: 2px;
        }

        .db-hero-icon-wrap {
            width: 72px;
            height: 72px;
            background: rgba(255,127,0,.07);
            border: 1px solid rgba(255,127,0,.15);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .db-hero-icon-wrap svg {
            width: 38px;
            height: 38px;
            opacity: .85;
        }

        .db-date-chip {
            font-size: .82rem;
            color: #555;
            font-weight: 600;
            letter-spacing: .02em;
        }

        .db-role-chip {
            background: <?php echo $is_admin ? 'rgba(255,127,0,.12)' : 'rgba(52,152,219,.12)'; ?>;
            border: 1px solid <?php echo $is_admin ? 'rgba(255,127,0,.25)' : 'rgba(52,152,219,.25)'; ?>;
            color: <?php echo $is_admin ? '#ff9a33' : '#3498db'; ?>;
            border-radius: 999px;
            padding: .3rem .9rem;
            font-size: .78rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        /* KPI grid */
        .db-kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.75rem;
        }

        .db-kpi {
            background: #0d0d0d;
            border: 1.5px solid #1f1f1f;
            border-radius: 16px;
            padding: 1.4rem 1.5rem;
            position: relative;
            overflow: hidden;
            transition: transform .2s, border-color .2s, box-shadow .2s;
            cursor: default;
        }

        .db-kpi:hover {
            transform: translateY(-3px);
            border-color: #2e2e2e;
            box-shadow: 0 12px 30px rgba(0,0,0,.5);
        }

        .db-kpi::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: var(--kpi-accent, linear-gradient(90deg, #ff7f00, transparent));
            border-radius: 16px 16px 0 0;
            opacity: 0;
            transition: opacity .2s;
        }

        .db-kpi:hover::before,
        .db-kpi.accent::before { opacity: 1; }

        .db-kpi-icon { font-size: 1.5rem; margin-bottom: .6rem; display: block; }
        .db-kpi-label {
            font-size: .7rem;
            font-weight: 700;
            color: #555;
            text-transform: uppercase;
            letter-spacing: .07em;
            margin-bottom: .35rem;
        }
        .db-kpi-value {
            font-size: 2rem;
            font-weight: 900;
            color: #fff;
            line-height: 1;
            letter-spacing: -.03em;
            margin-bottom: .35rem;
            transition: all .5s;
        }
        .db-kpi.accent .db-kpi-value { color: #ff7f00; }
        .db-kpi-helper { font-size: .72rem; color: #444; }

        /* Section layout */
        .db-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }

        .db-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }

        /* Content cards */
        .db-card {
            background: #0d0d0d;
            border: 1.5px solid #1f1f1f;
            border-radius: 16px;
            overflow: hidden;
        }

        .db-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.1rem 1.5rem;
            background: #111;
            border-bottom: 1px solid #1f1f1f;
            gap: .75rem;
            flex-wrap: wrap;
        }

        .db-card-title {
            font-size: .95rem;
            font-weight: 800;
            color: #fff;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .db-card-body { padding: 1.25rem 1.5rem; }

        /* Order row */
        .db-order-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .85rem 0;
            border-bottom: 1px solid #131313;
            gap: 1rem;
        }
        .db-order-row:last-child { border-bottom: none; }

        .db-order-folio {
            font-size: .82rem;
            font-weight: 800;
            color: #ff7f00;
            font-family: 'Courier New', monospace;
        }

        .db-order-info { flex: 1; min-width: 0; }
        .db-order-name { font-size: .85rem; font-weight: 600; color: #ddd; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .db-order-date { font-size: .72rem; color: #555; }

        .db-order-amount { font-size: .9rem; font-weight: 800; color: #fff; white-space: nowrap; }

        .db-status-badge {
            display: inline-block;
            padding: .15rem .55rem;
            border-radius: 999px;
            font-size: .67rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            white-space: nowrap;
        }
        .db-status-badge.paid     { background: rgba(46,204,113,.12); color: #2ecc71; }
        .db-status-badge.pending  { background: rgba(241,196,15,.12);  color: #f1c40f; }
        .db-status-badge.overdue  { background: rgba(231,76,60,.12);   color: #e74c3c; }

        /* Product row */
        .db-product-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: .75rem 0;
            border-bottom: 1px solid #131313;
        }
        .db-product-row:last-child { border-bottom: none; }

        .db-product-rank {
            font-size: .72rem;
            font-weight: 800;
            color: #333;
            width: 20px;
            text-align: center;
            flex-shrink: 0;
        }

        .db-product-name { flex: 1; font-size: .85rem; font-weight: 600; color: #ddd; }
        .db-product-sku  { font-size: .72rem; color: #555; }
        .db-product-price { font-size: .88rem; font-weight: 800; color: #ff7f00; white-space: nowrap; }

        /* Quick Actions */
        .db-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: .75rem;
        }

        .db-action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            background: #111;
            border: 1.5px solid #1f1f1f;
            border-radius: 14px;
            padding: 1.25rem 1rem;
            text-decoration: none;
            color: #ccc;
            font-size: .82rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all .2s;
            text-align: center;
            line-height: 1.3;
        }

        .db-action-btn:hover {
            background: #1a1a1a;
            border-color: #ff7f00;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,.4);
            text-decoration: none;
        }

        .db-action-icon { font-size: 1.6rem; }

        /* Admin shortcut bar */
        .db-admin-bar {
            background: #0a0a0a;
            border: 1px solid #1f1f1f;
            border-left: 3px solid #ff7f00;
            border-radius: 12px;
            padding: .85rem 1.25rem;
            margin-bottom: 1.25rem;
        }

        .db-admin-bar-section {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            flex-wrap: wrap;
        }

        .db-admin-bar-label {
            font-size: .68rem;
            font-weight: 800;
            color: #ff7f00;
            text-transform: uppercase;
            letter-spacing: .1em;
            white-space: nowrap;
            flex-shrink: 0;
            padding-right: .75rem;
            border-right: 1px solid #2a2a2a;
        }

        .db-admin-links {
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .db-admin-link {
            display: inline-flex;
            align-items: center;
            background: transparent;
            border: 1px solid #242424;
            border-radius: 7px;
            padding: .35rem .85rem;
            text-decoration: none;
            color: #888;
            font-size: .78rem;
            font-weight: 600;
            letter-spacing: .01em;
            transition: all .18s;
            white-space: nowrap;
        }

        .db-admin-link:hover {
            background: #161616;
            border-color: #ff7f00;
            color: #fff;
            text-decoration: none;
        }

        .db-admin-link.active-route {
            background: rgba(255,127,0,.08);
            border-color: rgba(255,127,0,.3);
            color: #ff9a33;
        }

        /* Activity indicator */
        .db-activity-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem;
            text-align: center;
            color: #444;
            gap: .75rem;
        }

        .db-activity-empty-icon { font-size: 2.5rem; opacity: .35; }
        .db-activity-empty-text { font-size: .85rem; line-height: 1.5; max-width: 220px; }

        /* Spinner skeleton */
        @keyframes db-skeleton { 0%,100% { opacity: 1; } 50% { opacity: .3; } }
        .db-skeleton { animation: db-skeleton 1.4s ease infinite; }

        /* View all link */
        .db-view-all {
            display: flex;
            align-items: center;
            gap: .35rem;
            font-size: .78rem;
            font-weight: 700;
            color: #ff7f00;
            text-decoration: none;
            transition: color .2s;
        }
        .db-view-all:hover { color: #ffb347; text-decoration: none; }

        /* Responsive */
        @media (max-width: 900px) {
            .db-grid-2, .db-grid-3 { grid-template-columns: 1fr; }
            .db-kpi-grid { grid-template-columns: repeat(2, 1fr); }
            .db-hero { padding: 1.5rem; }
            .db-hero h1 { font-size: 1.5rem; }
            .db-hero-right { align-items: flex-start; }
        }

        @media (max-width: 480px) {
            .db-shell { padding: 1rem .75rem 3rem; }
            .db-kpi-grid { grid-template-columns: repeat(2, 1fr); }
            .db-kpi-value { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    <header>
        <div class="header-content">
            <a href="dashboard.php" class="logo"><img src="img/logo_truper.1.1.png" alt="Truper" style="height: 40px; width: auto; object-fit: contain;"></a>
            <nav class="nav-menu">
                <a href="index.php">Catálogo</a>
                <a href="marketplace_ce.php">Marketplace CE</a>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Mi Cuenta <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content">
                        <a href="dashboard.php" class="active">Dashboard</a>
                        <a href="orders.php">Pedidos</a>
                        <a href="wholesale.php">Mayoreo</a>
                        <a href="account.php#historyTab">Historial</a>
                        <a href="profile.php">Perfil</a>
                    </div>
                </div>
                <?php if ($is_admin): ?>
                    <div class="nav-dropdown">
                        <button class="nav-dropdown-btn">Administración <span class="arrow">▼</span></button>
                        <div class="nav-dropdown-content">
                            <a href="cashier.php">Caja</a>
                            <a href="admin_supply.php?nocache=true">Abastecimiento</a>
                            <a href="tickets.php">Tickets</a>
                            <a href="tasks.php">Tareas</a>
                            <a href="analytics.php">Estadísticas</a>
                        </div>
                    </div>
                <?php endif; ?>
            </nav>
        </div>
        <div class="user-menu">
            <div class="user-info">
                <div class="user-name"><?php echo $user_name; ?></div>
                <div class="user-role"><?php echo strtoupper($user_role); ?></div>
            </div>
            <button class="btn-logout" onclick="logout()">Cerrar Sesión</button>
        </div>
    </header>

    <main>
        <div class="db-shell">

            <!-- ── Welcome Hero ── -->
            <div class="db-hero">
                <div class="db-hero-left">
                    <div class="db-greeting-badge">
                        <span class="live-dot"></span>
                        Sistema en línea
                    </div>
                    <h1 id="dbGreeting">Bienvenido, <span><?php echo $first_name; ?></span></h1>
                    <p class="db-hero-sub">Bienvenido de vuelta a Truper Platform. Aquí está el resumen de hoy.</p>
                </div>
                <div class="db-hero-right">
                    <div class="db-hero-visual">
                        <div class="db-hero-stat">
                            <div class="db-hero-stat-val" id="dbLiveClock" style="font-size:1rem; color:#666;">—</div>
                            <div class="db-hero-stat-label">Hora local</div>
                        </div>
                        <div class="db-hero-icon-wrap">
                            <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <!-- Chart bars -->
                                <rect x="6" y="28" width="7" height="14" rx="2" fill="rgba(255,127,0,0.4)"/>
                                <rect x="16" y="18" width="7" height="24" rx="2" fill="rgba(255,127,0,0.65)"/>
                                <rect x="26" y="22" width="7" height="20" rx="2" fill="rgba(255,127,0,0.5)"/>
                                <rect x="36" y="10" width="7" height="32" rx="2" fill="#ff7f00"/>
                                <!-- Trend line -->
                                <polyline points="9,28 19,18 29,22 39,10" stroke="#ff9a33" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="50" stroke-dashoffset="50" style="animation:draw-line 1.2s ease forwards .3s">
                                </polyline>
                                <!-- Dots -->
                                <circle cx="9" cy="28" r="2.5" fill="#ff9a33"/>
                                <circle cx="19" cy="18" r="2.5" fill="#ff9a33"/>
                                <circle cx="29" cy="22" r="2.5" fill="#ff9a33"/>
                                <circle cx="39" cy="10" r="2.5" fill="#ff9a33"/>
                            </svg>
                        </div>
                    </div>
                    <div class="db-role-chip">
                        <?php echo $is_admin ? 'Administrador' : ucfirst($user_role); ?>
                    </div>
                </div>
            </div>

            <!-- ── Admin Quick-access bar ── -->
            <?php if ($is_admin): ?>
            <div class="db-admin-bar">
                <div class="db-admin-bar-section">
                    <span class="db-admin-bar-label">Acceso Rápido</span>
                    <div class="db-admin-links">
                        <a href="admin_supply.php?nocache=true" class="db-admin-link">Abastecimiento</a>
                        <a href="cashier.php" class="db-admin-link">Caja</a>
                        <a href="analytics.php" class="db-admin-link">Estadísticas</a>
                        <a href="tickets.php" class="db-admin-link">Tickets</a>
                        <a href="wholesale.php" class="db-admin-link">Mayoreo</a>
                        <a href="tasks.php" class="db-admin-link">Tareas</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── KPI Cards ── -->
            <div class="db-kpi-grid">
                <div class="db-kpi accent" id="kpiOrders">
                    <span class="db-kpi-icon">📦</span>
                    <div class="db-kpi-label">Órdenes este mes</div>
                    <div class="db-kpi-value db-skeleton" id="monthlyOrders">—</div>
                    <div class="db-kpi-helper">Mes actual</div>
                </div>
                <div class="db-kpi" id="kpiRevenue">
                    <span class="db-kpi-icon">💰</span>
                    <div class="db-kpi-label">Ingresos</div>
                    <div class="db-kpi-value db-skeleton" id="monthlyRevenue">—</div>
                    <div class="db-kpi-helper">Mes actual</div>
                </div>
                <div class="db-kpi" id="kpiPending">
                    <span class="db-kpi-icon">⏳</span>
                    <div class="db-kpi-label">Pagos pendientes</div>
                    <div class="db-kpi-value db-skeleton" id="pendingPayments">—</div>
                    <div class="db-kpi-helper">Requieren atención</div>
                </div>
                <div class="db-kpi" id="kpiTasks">
                    <span class="db-kpi-icon">✅</span>
                    <div class="db-kpi-label">Tareas pendientes</div>
                    <div class="db-kpi-value db-skeleton" id="pendingTasks">—</div>
                    <div class="db-kpi-helper">En progreso</div>
                </div>
            </div>

            <!-- ── Main content: Orders + Products ── -->
            <div class="db-grid-2">

                <!-- Recent Orders -->
                <div class="db-card">
                    <div class="db-card-header">
                        <div class="db-card-title">🧾 Órdenes Recientes</div>
                        <a href="orders.php" class="db-view-all">Ver todas →</a>
                    </div>
                    <div class="db-card-body">
                        <div id="recentOrders">
                            <div class="db-activity-empty">
                                <div style="width:28px;height:28px;border:3px solid #1f1f1f;border-top-color:#ff7f00;border-radius:50%;animation:spin .8s linear infinite;margin:0 auto;"></div>
                                <div class="db-activity-empty-text">Cargando órdenes…</div>
                            </div>
                        </div>
                        <a href="orders.php" style="display:block; margin-top:1rem; background:linear-gradient(135deg,#ff7f00,#e06b00); color:#000; font-weight:800; text-align:center; padding:.7rem; border-radius:10px; text-decoration:none; font-size:.85rem; transition:all .2s;" onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform=''">
                            Ver Todas las Órdenes
                        </a>
                    </div>
                </div>

                <!-- Top Products -->
                <div class="db-card">
                    <div class="db-card-header">
                        <div class="db-card-title">🏆 Productos del Catálogo</div>
                        <a href="index.php" class="db-view-all">Ver catálogo →</a>
                    </div>
                    <div class="db-card-body">
                        <div id="topProducts">
                            <div class="db-activity-empty">
                                <div style="width:28px;height:28px;border:3px solid #1f1f1f;border-top-color:#ff7f00;border-radius:50%;animation:spin .8s linear infinite;margin:0 auto;"></div>
                                <div class="db-activity-empty-text">Cargando productos…</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /db-grid-2 -->

            <!-- ── Quick Actions ── -->
            <div class="db-card" style="margin-bottom:1.25rem;">
                <div class="db-card-header">
                    <div class="db-card-title">⚡ Acciones Rápidas</div>
                </div>
                <div class="db-card-body">
                    <div class="db-actions-grid">
                        <a href="orders.php" class="db-action-btn" id="qa-orders">
                            <span class="db-action-icon">📋</span>Mis Pedidos
                        </a>
                        <a href="tasks.php" class="db-action-btn" id="qa-tasks">
                            <span class="db-action-icon">✅</span>Tareas
                        </a>
                        <a href="analytics.php" class="db-action-btn" id="qa-stats">
                            <span class="db-action-icon">📊</span>Estadísticas
                        </a>
                        <a href="profile.php" class="db-action-btn" id="qa-profile">
                            <span class="db-action-icon">👤</span>Mi Perfil
                        </a>
                        <a href="wholesale.php" class="db-action-btn" id="qa-wholesale">
                            <span class="db-action-icon">🏷️</span>Mayoreo
                        </a>
                        <a href="account.php#historyTab" class="db-action-btn" id="qa-history">
                            <span class="db-action-icon">📜</span>Historial
                        </a>
                        <a href="index.php" class="db-action-btn" id="qa-catalog">
                            <span class="db-action-icon">🛍️</span>Catálogo
                        </a>
                        <?php if ($is_admin): ?>
                        <a href="cashier.php" class="db-action-btn" id="qa-cashier">
                            <span class="db-action-icon">🧾</span>Caja
                        </a>
                        <a href="admin_supply.php?nocache=true" class="db-action-btn" id="qa-supply">
                            <span class="db-action-icon">📦</span>Abastecimiento
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ── Admin info widgets ── -->
            <?php if ($is_admin): ?>
            <div class="db-grid-2">
                <div class="db-card">
                    <div class="db-card-header">
                        <div class="db-card-title">🔔 Estado del Sistema</div>
                    </div>
                    <div class="db-card-body">
                        <div style="display:flex; flex-direction:column; gap:.75rem;">
                            <div style="display:flex; align-items:center; justify-content:space-between; padding:.6rem .8rem; background:#111; border-radius:10px;">
                                <span style="font-size:.85rem; color:#ccc;">🗄️ Base de datos</span>
                                <span style="font-size:.75rem; font-weight:700; color:#2ecc71; background:rgba(46,204,113,.1); padding:.2rem .6rem; border-radius:999px;">● En línea</span>
                            </div>
                            <div style="display:flex; align-items:center; justify-content:space-between; padding:.6rem .8rem; background:#111; border-radius:10px;">
                                <span style="font-size:.85rem; color:#ccc;">🌐 Servidor web</span>
                                <span style="font-size:.75rem; font-weight:700; color:#2ecc71; background:rgba(46,204,113,.1); padding:.2rem .6rem; border-radius:999px;">● Activo</span>
                            </div>
                            <div style="display:flex; align-items:center; justify-content:space-between; padding:.6rem .8rem; background:#111; border-radius:10px;">
                                <span style="font-size:.85rem; color:#ccc;">💾 Almacenamiento</span>
                                <span style="font-size:.75rem; font-weight:700; color:#f1c40f; background:rgba(241,196,15,.1); padding:.2rem .6rem; border-radius:999px;">10 GB</span>
                            </div>
                            <div style="display:flex; align-items:center; justify-content:space-between; padding:.6rem .8rem; background:#111; border-radius:10px;">
                                <span style="font-size:.85rem; color:#ccc;">🤖 Motor IA</span>
                                <span style="font-size:.75rem; font-weight:700; color:#3498db; background:rgba(52,152,219,.1); padding:.2rem .6rem; border-radius:999px;">● Activo</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="db-card">
                    <div class="db-card-header">
                        <div class="db-card-title">📌 Módulos Disponibles</div>
                    </div>
                    <div class="db-card-body">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:.5rem;">
                            <?php
                            $modules = [
                                ['📦','Abastecimiento','admin_supply.php'],
                                ['🧾','Caja','cashier.php'],
                                ['📊','Estadísticas','analytics.php'],
                                ['🎫','Tickets','tickets.php'],
                                ['📋','Pedidos','orders.php'],
                                ['🏷️','Mayoreo','wholesale.php'],
                                ['✅','Tareas','tasks.php'],
                                ['🛍️','Catálogo','index.php'],
                            ];
                            foreach ($modules as $m): ?>
                            <a href="<?php echo $m[2]; ?>" style="display:flex; align-items:center; gap:.5rem; padding:.55rem .7rem; background:#111; border:1px solid #1f1f1f; border-radius:9px; text-decoration:none; color:#ccc; font-size:.8rem; font-weight:600; transition:all .2s;" onmouseover="this.style.borderColor='#ff7f00';this.style.color='#fff'" onmouseout="this.style.borderColor='#1f1f1f';this.style.color='#ccc'">
                                <span><?php echo $m[0]; ?></span>
                                <span><?php echo $m[1]; ?></span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── Historial Reciente ── -->
            <div class="db-card" style="margin-bottom:1.25rem;">
                <div class="db-card-header">
                    <div class="db-card-title">📜 Historial de Transacciones</div>
                    <a href="account.php#historyTab" class="db-view-all">Ver todo →</a>
                </div>
                <div class="db-card-body">
                    <div id="dashHistoryRows">
                        <div class="db-activity-empty">
                            <div style="width:28px;height:28px;border:3px solid #1f1f1f;border-top-color:#ff7f00;border-radius:50%;animation:spin .8s linear infinite;margin:0 auto;"></div>
                            <div class="db-activity-empty-text">Cargando historial…</div>
                        </div>
                    </div>
                    <a href="account.php#historyTab" style="display:block; margin-top:1rem; background:linear-gradient(135deg,#ff7f00,#e06b00); color:#000; font-weight:800; text-align:center; padding:.7rem; border-radius:10px; text-decoration:none; font-size:.85rem; transition:all .2s;" onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform=''">
                        Ver Historial Completo en Mi Cuenta
                    </a>
                </div>
            </div>

        </div><!-- /db-shell -->
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

    <script src="js/main.js?v=2.6"></script>
    <style>
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes draw-line { to { stroke-dashoffset: 0; } }
        @keyframes countUp {
            from { opacity: 0; transform: translateY(6px); }
            to   { opacity: 1; transform: translateY(0); }
        }
    </style>
    <script>
        /* ── Clock + Greeting (uses browser local time, NOT server UTC) ── */
        function updateClock() {
            const now  = new Date();
            const days   = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
            const months = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
            const h = String(now.getHours()).padStart(2,'0');
            const m = String(now.getMinutes()).padStart(2,'0');

            // Update clock chip
            const clockEl = document.getElementById('dbLiveClock');
            if (clockEl) {
                clockEl.textContent = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} · ${h}:${m}`;
                clockEl.style.color = '#c0c0c8';
                clockEl.style.fontSize = '0.82rem';
            }

            // Update greeting based on local hour
            const hour = now.getHours();
            let greet;
            if      (hour >= 0  && hour < 12) greet = 'Buenos días';
            else if (hour >= 12 && hour < 19) greet = 'Buenas tardes';
            else                              greet = 'Buenas noches';

            const greetEl = document.getElementById('dbGreeting');
            if (greetEl) {
                // Preserve the orange <span> with the name
                const nameSpan = greetEl.querySelector('span');
                const nameHTML = nameSpan ? nameSpan.outerHTML : '';
                greetEl.innerHTML = `${greet}, ${nameHTML}`;
            }
        }
        updateClock();
        setInterval(updateClock, 30000);

        /* ── Logout ── */
        function logout() {
            if (confirm('¿Deseas cerrar sesión?')) {
                window.location.href = 'api/auth.php?action=logout';
            }
        }

        /* ── Animate KPI value counting up ── */
        function animateCount(el, target, prefix = '', suffix = '', duration = 900) {
            if (!el) return;
            el.classList.remove('db-skeleton');
            el.style.animation = 'countUp .4s ease';
            const start   = 0;
            const step    = (timestamp) => {
                if (!startTime) startTime = timestamp;
                const progress = Math.min((timestamp - startTime) / duration, 1);
                const current  = Math.floor(progress * target);
                el.textContent = prefix + current.toLocaleString('es-MX') + suffix;
                if (progress < 1) window.requestAnimationFrame(step);
                else el.textContent = prefix + target.toLocaleString('es-MX') + suffix;
            };
            let startTime = null;
            window.requestAnimationFrame(step);
        }

        /* ── KPI Metrics ── */
        async function loadDashboardMetrics() {
            const response = await apiCall('/analytics.php?action=yearly-stats');
            const ordEl  = document.getElementById('monthlyOrders');
            const revEl  = document.getElementById('monthlyRevenue');
            const pendEl = document.getElementById('pendingPayments');
            const taskEl = document.getElementById('pendingTasks');

            if (response && response.stats && Array.isArray(response.stats)) {
                const currentYear = new Date().getFullYear();
                const yearData = response.stats.find(s => Number(s.year_val) === currentYear) || {};
                const orders  = Number(yearData.total_orders  || 0);
                const revenue = Number(yearData.total_amount  || 0);
                animateCount(ordEl, orders);
                if (revEl) {
                    revEl.classList.remove('db-skeleton');
                    revEl.style.animation = 'countUp .4s ease';
                    revEl.textContent = '$' + revenue.toLocaleString('es-MX', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
                }
            } else {
                [ordEl, revEl, pendEl, taskEl].forEach(el => {
                    if (el) { el.classList.remove('db-skeleton'); el.textContent = '0'; }
                });
                return;
            }

            // Load pending orders for payment status
            const ordResp = await apiCall('/orders.php?action=list&limit=100');
            if (ordResp && ordResp.success && Array.isArray(ordResp.orders)) {
                const pending = ordResp.orders.filter(o => o.payment_status === 'pending').length;
                animateCount(pendEl, pending);
            } else {
                if (pendEl) { pendEl.classList.remove('db-skeleton'); pendEl.textContent = '0'; }
            }

            // Pending tasks — approximate via tasks API
            const taskResp = await apiCall('/tasks.php?action=list');
            if (taskResp && taskResp.success && Array.isArray(taskResp.tasks)) {
                const open = taskResp.tasks.filter(t => t.status !== 'completed' && t.status !== 'done').length;
                animateCount(taskEl, open);
            } else {
                if (taskEl) { taskEl.classList.remove('db-skeleton'); taskEl.textContent = '0'; }
            }
        }

        /* ── Recent Orders ── */
        async function loadRecentOrders() {
            const box = document.getElementById('recentOrders');
            if (!box) return;

            const response = await apiCall('/orders.php?action=list&limit=6');
            if (!response || !response.success || !Array.isArray(response.orders) || response.orders.length === 0) {
                box.innerHTML = `<div class="db-activity-empty">
                    <div class="db-activity-empty-icon">📭</div>
                    <div class="db-activity-empty-text">Aún no hay órdenes registradas.</div>
                </div>`;
                return;
            }

            const rows = response.orders.slice(0, 6);
            box.innerHTML = rows.map(order => {
                const amount  = Number(order.total_amount || 0);
                const fmt     = '$' + amount.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                const isPaid  = order.payment_status === 'completed' || order.payment_status === 'paid';
                const isPend  = order.payment_status === 'pending';
                const badgeCls = isPaid ? 'paid' : isPend ? 'pending' : 'overdue';
                const badgeTxt = isPaid ? '✓ Pagado' : isPend ? '⏳ Pendiente' : order.payment_status;
                const dateStr  = order.created_at ? new Date(order.created_at).toLocaleDateString('es-MX') : '—';

                return `<div class="db-order-row">
                    <div>
                        <div class="db-order-folio">${order.order_number || '#—'}</div>
                        <div style="font-size:.72rem; color:#555;">${dateStr}</div>
                    </div>
                    <div class="db-order-info">
                        <div class="db-order-name">${order.client_name || 'Cliente'}</div>
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.25rem;">
                        <div class="db-order-amount">${fmt}</div>
                        <span class="db-status-badge ${badgeCls}">${badgeTxt}</span>
                    </div>
                </div>`;
            }).join('');
        }

        /* ── Top Products ── */
        async function loadTopProducts() {
            const box = document.getElementById('topProducts');
            if (!box) return;

            try {
                const response = await apiCall('/products.php?action=list&limit=6');
                if (!response || !response.success || !Array.isArray(response.products) || response.products.length === 0) {
                    box.innerHTML = `<div class="db-activity-empty">
                        <div class="db-activity-empty-icon">📦</div>
                        <div class="db-activity-empty-text">No hay productos en el catálogo todavía.</div>
                    </div>`;
                    return;
                }

                const rows = response.products.slice(0, 6);
                box.innerHTML = rows.map((product, i) => {
                    const price = Number(product.unit_price || 0);
                    const fmt   = '$' + price.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    return `<div class="db-product-row">
                        <div class="db-product-rank">${i + 1}</div>
                        <div class="db-product-info" style="flex:1; min-width:0;">
                            <div class="db-product-name" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${product.name || 'Producto'}</div>
                            <div class="db-product-sku">${product.sku || '—'}</div>
                        </div>
                        <div class="db-product-price">${fmt}</div>
                    </div>`;
                }).join('');
            } catch (e) {
                box.innerHTML = `<div class="db-activity-empty">
                    <div class="db-activity-empty-icon">⚠️</div>
                    <div class="db-activity-empty-text">Error al cargar productos.</div>
                </div>`;
            }
        }

        /* ── Recent History ── */
        async function loadDashboardHistory() {
            const box = document.getElementById('dashHistoryRows');
            if (!box) return;

            try {
                const res = await apiCall('/client_account.php?action=history');
                if (!res || !res.success || !res.items || res.items.length === 0) {
                    box.innerHTML = `<div class="db-activity-empty">
                        <div class="db-activity-empty-icon">📭</div>
                        <div class="db-activity-empty-text">Sin transacciones registradas.</div>
                    </div>`;
                    return;
                }

                const items = res.items.slice(0, 6);
                const badgeMap = {
                    client_order:   { label: 'Pedido',       color: '#ff7f00', bg: 'rgba(255,127,0,.12)' },
                    payment:        { label: 'Pago',          color: '#2ecc71', bg: 'rgba(46,204,113,.12)' },
                    supplier_order: { label: 'Orden Prov.',   color: '#a78bfa', bg: 'rgba(167,139,250,.12)' },
                };

                box.innerHTML = items.map(i => {
                    const bm = badgeMap[i.transaction_type] || { label: i.transaction_type, color: '#888', bg: 'rgba(136,136,136,.1)' };
                    let parsed = {};
                    try { parsed = JSON.parse(i.data_json || '{}'); } catch(e) {}
                    const detail = i.transaction_type === 'client_order'
                        ? `Total: <strong style="color:#ff7f00;">$${Number(parsed.total||0).toFixed(2)}</strong>`
                        : i.transaction_type === 'payment'
                            ? `Abono: <strong style="color:#2ecc71;">$${Number(parsed.amount||0).toFixed(2)}</strong>`
                            : (i.reference_folio || '—');
                    const dateStr = i.created_at ? new Date(i.created_at).toLocaleDateString('es-MX') : '—';
                    return `<div class="db-order-row">
                        <div>
                            <span style="display:inline-block;padding:.15rem .6rem;border-radius:999px;font-size:.67rem;font-weight:700;background:${bm.bg};color:${bm.color};">${bm.label}</span>
                            <div style="font-size:.72rem; color:#555; margin-top:.2rem;">${dateStr}</div>
                        </div>
                        <div class="db-order-info">
                            <div class="db-order-folio" style="font-size:.8rem;">${i.reference_folio || '—'}</div>
                            <div style="font-size:.78rem; color:#888;">${detail}</div>
                        </div>
                    </div>`;
                }).join('');
            } catch(e) {
                box.innerHTML = `<div class="db-activity-empty">
                    <div class="db-activity-empty-icon">⚠️</div>
                    <div class="db-activity-empty-text">Error al cargar historial.</div>
                </div>`;
            }
        }

        /* ── Init ── */
        document.addEventListener('DOMContentLoaded', function () {
            loadDashboardMetrics();
            loadRecentOrders();
            loadTopProducts();
            loadDashboardHistory();
        });
    </script>
    <script src="js/mobile-optimize.js"></script>
</body>
</html>
