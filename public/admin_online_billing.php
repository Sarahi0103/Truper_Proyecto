<?php
/**
 * Interfaz de Administración: Facturación Fiscal & Pasarelas de Pago SAT
 * Ferretería FOX / Truper Platform
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/utils/SatCatalogs.php';
require_once __DIR__ . '/../src/Services/SatBillingService.php';
require_once __DIR__ . '/../src/Services/GlobalInvoiceService.php';
require_once __DIR__ . '/../src/Services/PaymentComplementService.php';

require_login();
$userRole = strtolower($_SESSION['role'] ?? '');
$isAdmin = in_array($userRole, ['admin', 'employee'], true);

if (!$isAdmin) {
    header('Location: dashboard.php');
    exit;
}

$isOnlineMode = isset($_GET['mode']) && $_GET['mode'] === 'online';
$user_name = htmlspecialchars($_SESSION['first_name'] ?? $_SESSION['name'] ?? 'Administrador Truper', ENT_QUOTES, 'UTF-8');
$user_role = htmlspecialchars($_SESSION['role'] ?? 'ADMIN');
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Facturación & Pasarelas SAT — Ferretería FOX</title>
    <link rel="stylesheet" href="css/styles.css?v=4.2">
    <link rel="stylesheet" href="css/theme.css?v=4.1">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/responsive-complete.css?v=5.0">
    <link rel="stylesheet" href="css/modals.css">
    <style>
        body { font-family: var(--theme-font, 'Outfit', sans-serif); background: #0b0b0e; color: #fff; }

        .billing-container {
            max-width: 1320px;
            margin: 2rem auto 4rem;
            padding: 0 1.25rem;
        }

        .back-header {
            margin-bottom: 1.5rem;
        }

        .page-hero {
            background: linear-gradient(135deg, rgba(20,20,26,0.95), rgba(12,12,16,0.98));
            border: 1px solid rgba(255, 127, 0, 0.25);
            border-radius: 16px;
            padding: 2rem 2.25rem;
            margin-bottom: 1.75rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            position: relative;
        }

        .page-hero h1 {
            font-size: 1.85rem;
            font-weight: 800;
            color: #fff;
            margin: 0.75rem 0 0.4rem;
            letter-spacing: -0.02em;
        }

        .page-hero p.text-muted {
            font-size: 0.95rem;
            color: #888899;
            margin: 0;
            line-height: 1.5;
            max-width: 800px;
        }

        .hero-status-tag {
            position: absolute;
            top: 1.75rem;
            right: 2rem;
            background: rgba(34, 197, 94, 0.12);
            border: 1px solid #22c55e;
            color: #22c55e;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        @media (max-width: 768px) {
            .hero-status-tag {
                position: static;
                display: inline-block;
                margin-top: 1rem;
            }
        }

        /* Tabs Navigation */
        .tabs-header-nav {
            display: flex;
            gap: 0.6rem;
            margin-bottom: 1.75rem;
            border-bottom: 1px solid #22222a;
            padding-bottom: 0.6rem;
            overflow-x: auto;
        }

        .tab-btn {
            background: #14141a;
            border: 1px solid #282834;
            color: #888899;
            padding: 0.75rem 1.4rem;
            border-radius: 10px;
            font-size: 0.88rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
        }

        .tab-btn:hover {
            background: rgba(255, 127, 0, 0.1);
            color: #fff;
            border-color: rgba(255, 127, 0, 0.4);
        }

        .tab-btn.active {
            background: linear-gradient(135deg, rgba(255, 127, 0, 0.22), rgba(255, 127, 0, 0.08));
            border-color: #ff7f00;
            color: #fff;
            box-shadow: 0 4px 15px rgba(255, 127, 0, 0.25);
        }

        /* Tab Contents */
        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Cards Layout */
        .glass-card {
            background: #121217;
            border: 1px solid #22222a;
            border-radius: 16px;
            padding: 1.75rem;
            margin-bottom: 1.75rem;
            box-shadow: 0 8px 24px rgba(0,0,0,0.3);
        }

        .card-header-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--theme-accent, #ff7f00);
            margin: 0 0 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            border-bottom: 1px solid #22222a;
            padding-bottom: 0.8rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.25rem;
        }

        .form-group-full {
            grid-column: 1 / -1;
        }

        .field-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 700;
            color: #888899;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.45rem;
        }

        .field-input, .field-select {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            background: #181822;
            border: 1px solid #2a2a36;
            color: #fff;
            font-size: 0.92rem;
            box-sizing: border-box;
            transition: all 0.2s ease;
            font-family: inherit;
        }

        .field-input:focus, .field-select:focus {
            outline: none;
            border-color: #ff7f00;
            box-shadow: 0 0 0 3px rgba(255, 127, 0, 0.15);
        }

        .field-help {
            font-size: 0.78rem;
            color: #666677;
            margin-top: 0.35rem;
        }

        .btn-action {
            background: linear-gradient(135deg, #ff7f00, #e66c00);
            color: #fff;
            border: none;
            padding: 0.8rem 1.75rem;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            font-size: 0.92rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 4px 15px rgba(255, 127, 0, 0.3);
            transition: all 0.2s ease;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 127, 0, 0.4);
        }

        /* Legal / SAT Guide Cards */
        .guide-box {
            background: rgba(15, 23, 42, 0.6);
            border-left: 4px solid #ff7f00;
            padding: 1.25rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.25rem;
        }

        .guide-box h4 {
            margin: 0 0 0.5rem;
            font-size: 1.05rem;
            color: #ff7f00;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 700;
        }

        .guide-box p {
            margin: 0 0 0.5rem;
            font-size: 0.9rem;
            line-height: 1.55;
            color: #cbd5e1;
        }

        .guide-box p:last-child { margin-bottom: 0; }

        /* KPI Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 1.75rem;
        }

        .kpi-card {
            background: #121217;
            border: 1px solid #22222a;
            border-radius: 14px;
            padding: 1.25rem 1.5rem;
            text-align: center;
        }

        .kpi-val {
            font-size: 1.85rem;
            font-weight: 800;
            color: #fff;
            margin-top: 0.3rem;
        }

        .kpi-lbl {
            font-size: 0.75rem;
            font-weight: 700;
            color: #888899;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Table Styling */
        .table-responsive {
            overflow-x: auto;
            border-radius: 10px;
            border: 1px solid #22222a;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
            text-align: left;
        }

        th {
            background: #171720;
            padding: 1rem;
            border-bottom: 1px solid #282834;
            color: #888899;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.06em;
            font-weight: 700;
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid #1a1a24;
            vertical-align: middle;
            color: #e2e8f0;
        }

        tbody tr:hover {
            background: rgba(255, 127, 0, 0.04);
        }

        .badge-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-paid { background: rgba(34, 197, 94, 0.15); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3); }
        .badge-pending { background: rgba(234, 179, 8, 0.15); color: #eab308; border: 1px solid rgba(234, 179, 8, 0.3); }
        .badge-cancelled { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
        .badge-stamped { background: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); }

        .btn-cancel-sat {
            background: rgba(239, 68, 68, 0.12);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-cancel-sat:hover {
            background: #ef4444;
            color: #fff;
        }

        /* ── Sandbox Styles ──────────────────────────────────────────────────── */
        .sandbox-banner {
            background: linear-gradient(135deg, rgba(234,179,8,0.12), rgba(234,179,8,0.04));
            border: 1.5px solid rgba(234,179,8,0.55);
            border-radius: 14px;
            padding: 1.1rem 1.5rem;
            margin-bottom: 1.75rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: sandboxPulse 3s ease-in-out infinite;
        }

        @keyframes sandboxPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(234,179,8,0.0); }
            50% { box-shadow: 0 0 20px 4px rgba(234,179,8,0.12); }
        }

        .sandbox-banner-icon {
            font-size: 2rem;
            line-height: 1;
            flex-shrink: 0;
        }

        .sandbox-banner-text strong {
            color: #eab308;
            font-size: 1rem;
            font-weight: 800;
            display: block;
            margin-bottom: 0.2rem;
        }

        .sandbox-banner-text span {
            color: #888899;
            font-size: 0.84rem;
            line-height: 1.5;
        }

        .sandbox-kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
            margin-bottom: 1.75rem;
        }

        .sandbox-kpi {
            background: #0e0e14;
            border: 1px solid rgba(234,179,8,0.2);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            text-align: center;
        }

        .sandbox-kpi .val {
            font-size: 1.7rem;
            font-weight: 800;
            color: #eab308;
            display: block;
        }

        .sandbox-kpi .lbl {
            font-size: 0.72rem;
            font-weight: 700;
            color: #666677;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-top: 0.25rem;
            display: block;
        }

        .badge-sandbox {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            background: rgba(234,179,8,0.15);
            color: #eab308;
            border: 1px solid rgba(234,179,8,0.4);
            letter-spacing: 0.04em;
            vertical-align: middle;
            margin-left: 4px;
        }

        .sandbox-form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.1rem;
        }

        .sale-type-selector {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }

        .sale-type-btn {
            flex: 1;
            padding: 0.85rem 1rem;
            border-radius: 10px;
            border: 2px solid #22222a;
            background: #14141a;
            color: #888899;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
        }

        .sale-type-btn.active {
            border-color: #eab308;
            background: rgba(234,179,8,0.1);
            color: #eab308;
        }

        .btn-sandbox {
            background: linear-gradient(135deg, #eab308, #ca8a04);
            color: #0b0b0e;
            border: none;
            padding: 0.85rem 1.75rem;
            border-radius: 8px;
            font-weight: 800;
            cursor: pointer;
            font-size: 0.92rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 4px 15px rgba(234,179,8,0.3);
            transition: all 0.2s ease;
        }

        .btn-sandbox:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 22px rgba(234,179,8,0.45);
        }

        .btn-sandbox-danger {
            background: rgba(239,68,68,0.12);
            color: #ef4444;
            border: 1px solid rgba(239,68,68,0.35);
            padding: 0.65rem 1.25rem;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.2s ease;
        }

        .btn-sandbox-danger:hover {
            background: #ef4444;
            color: #fff;
        }

        /* Client preview card */
        .client-preview-card {
            background: linear-gradient(135deg, #0f1117, #0a0c12);
            border: 1px solid rgba(255,127,0,0.2);
            border-radius: 14px;
            overflow: hidden;
            margin-top: 1.5rem;
        }

        .client-preview-header {
            background: linear-gradient(90deg, rgba(255,127,0,0.12), transparent);
            border-bottom: 1px solid rgba(255,127,0,0.15);
            padding: 0.85rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.82rem;
            font-weight: 700;
            color: #ff7f00;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .client-preview-body {
            padding: 1.25rem;
        }

        .checkout-invoice-block {
            background: #111118;
            border: 1px solid #22222a;
            border-radius: 10px;
            padding: 1.25rem;
        }

        .checkout-invoice-block h4 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #fff;
            margin: 0 0 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkout-field {
            margin-bottom: 0.85rem;
        }

        .checkout-field label {
            display: block;
            font-size: 0.78rem;
            color: #666677;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.3rem;
        }

        .checkout-field-value {
            background: #0e0e15;
            border: 1px solid #2a2a36;
            border-radius: 7px;
            padding: 0.6rem 0.9rem;
            font-size: 0.88rem;
            color: #e2e8f0;
            width: 100%;
            box-sizing: border-box;
        }

        /* Botón Visualizar (Azul) */
        .btn-view-invoice {
            background: rgba(37,99,235,0.18);
            border: 1px solid rgba(59,130,246,0.5);
            color: #60a5fa;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-view-invoice:hover {
            background: #2563eb;
            color: #fff;
            transform: translateY(-1px);
        }

        /* Botón Cancelar (Amarillo / Ámbar) */
        .btn-cancel-invoice,
        .btn-cancel-sat,
        .sandbox-cancel-btn {
            background: rgba(245,158,11,0.16);
            color: #fbbf24;
            border: 1px solid rgba(245,158,11,0.45);
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-cancel-invoice:hover,
        .btn-cancel-sat:hover,
        .sandbox-cancel-btn:hover {
            background: #d97706;
            color: #fff;
            border-color: #d97706;
            transform: translateY(-1px);
        }

        /* Botón Borrar / Eliminar (Rojo Intenso) */
        .btn-delete-invoice {
            background: rgba(239,68,68,0.16);
            color: #f87171;
            border: 1px solid rgba(239,68,68,0.5);
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-delete-invoice:hover {
            background: #dc2626;
            color: #fff;
            border-color: #dc2626;
            transform: translateY(-1px);
        }

        .sandbox-download-btn {
            background: #ff7f00;
            color: #fff;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
        }

        .sandbox-download-btn:hover {
            background: #e06d00;
            transform: translateY(-1px);
        }

        .sandbox-xml-btn {
            background: #1e293b;
            border: 1px solid #475569;
            color: #cbd5e1;
            padding: 5px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
        }

        .sandbox-xml-btn:hover {
            background: #334155;
            color: #fff;
        }
    </style>
</head>
<body class="catalog-minimal">

    <!-- HEADER ESTRUCTURA EXACTA SISTEMA -->
    <header>
        <div class="header-content">
            <a href="dashboard.php" class="logo"><img src="img/logo_fox.png" alt="Ferretería FOX" style="height: 42px; width: auto; object-fit: contain;"></a>
            <button class="hamburger-btn" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
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

                <?php if ($isAdmin): ?>
                <!-- Dropdowns de Administración Separados -->
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn active">Admin Tienda <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content" style="min-width: 220px;">
                        <a href="admin_online_orders.php">🌐 Pedidos Online</a>
                        <a href="order_tracking.php">🚚 Seguimiento y Guías</a>
                        <a href="admin_online_billing.php" class="active" style="color:#ff7f00; font-weight:700;">🏛️ Facturación & Pagos SAT</a>
                        <a href="rma_manager.php">🔄 Devoluciones RMA</a>
                    </div>
                </div>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Admin Local <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content" style="min-width: 220px;">
                        <a href="ticket_validation.php">🏬 Validación Mostrador</a>
                        <a href="tickets.php">🎫 Historial de Tickets</a>
                        <a href="cashier.php">💵 Caja / Punto de Venta</a>
                        <a href="orders.php">📋 Ventas / Pedidos Mostrador</a>
                        <a href="tasks.php">👥 Tareas de Empleados</a>
                        <a href="b2b_approval.php">🤝 Aprobación B2B</a>
                    </div>
                </div>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Solo Admin <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content" style="min-width: 220px;">
                        <a href="admin_supply.php?nocache=true">Abastecimiento / Precios</a>
                        <a href="accounting_reports.php">Reportes Contables</a>
                        <a href="gastos.php">Egresos / Gastos</a>
                        <a href="analytics.php">Estadísticas</a>
                    </div>
                </div>
                <?php endif; ?>
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

    <main class="billing-container">
        <!-- Back Button -->
        <div class="back-header">
            <button onclick="history.back()" class="btn-back btn-back-dark btn-back-as-button">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Regresar
            </button>
        </div>

        <!-- Page Hero (Identico a RMA / Seguimiento) -->
        <div class="page-hero">
            <div class="module-badge module-admin"><span class="module-glyph">FT</span> Facturación & Pagos SAT</div>
            <h1>Módulo de Facturación Fiscal y Pasarelas de Cobro SAT</h1>
            <p class="text-muted">Gestión integral de cobros en línea, timbrado fiscal CFDI 4.0, cancelaciones formales y cumplimiento normativo.</p>
            <div class="hero-status-tag" id="envBadgeDisplay">ENTORNO: PRODUCCIÓN (EN VIVO)</div>
        </div>

        <!-- Navigation Tabs -->
        <div class="tabs-header-nav">
            <button class="tab-btn active" onclick="switchTab('gatewayTab', this)">Pasarelas de Cobro en Línea</button>
            <button class="tab-btn" onclick="switchTab('satConfigTab', this)">Configuración Fiscal CFDI 4.0</button>
            <button class="tab-btn" onclick="switchTab('satGuideTab', this)">Guía Legal y Normativa SAT</button>
            <button class="tab-btn" onclick="switchTab('monitorTab', this)">Monitor de Facturas y Cancelaciones</button>
            <button class="tab-btn" onclick="switchTab('globalInvoiceTab', this)">Factura Global</button>
            <button class="tab-btn" onclick="switchTab('backupTab', this)">Respaldos BD & CSD SAT</button>
            <button class="tab-btn" id="sandboxTabBtn" onclick="switchTab('sandboxTab', this)" style="border-color: rgba(234,179,8,0.45); color: #eab308;">Sandbox de Pruebas</button>
        </div>

        <!-- Tab 1: Pasarelas de Cobro -->
        <div id="gatewayTab" class="tab-content active">
            <div class="glass-card">
                <h3 class="card-header-title">Vinculación de Tarjetas y Cuentas de Cobro en Línea</h3>
                <form id="gatewayForm" autocomplete="off" onsubmit="saveConfig(event)">
                    <div class="form-grid">
                        <div class="form-group-full">
                            <label class="field-label">Entorno de Procesamiento de Pagos</label>
                            <select id="payment_environment" class="field-select" autocomplete="off">
                                <option value="production" selected>Entorno de Producción / En Vivo (Cobros Reales)</option>
                                <option value="sandbox">Entorno de Verificación Técnica / Sandbox</option>
                            </select>
                            <p class="field-help">Modo de producción en vivo para procesar cargos bancarios reales y timbrado fiscal ante el SAT.</p>
                        </div>

                        <!-- Mercado Pago -->
                        <div>
                            <label class="field-label">Mercado Pago — Public Key</label>
                            <input type="text" id="mercadopago_public_key" class="field-input" placeholder="APP_USR-xxxx-xxxx-xxxx" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
                        </div>
                        <div>
                            <label class="field-label">Mercado Pago — Access Token</label>
                            <input type="password" id="mercadopago_access_token" class="field-input" placeholder="APP_USR-xxxx-xxxx-xxxx" autocomplete="new-password">
                        </div>

                        <!-- Stripe -->
                        <div>
                            <label class="field-label">Stripe — Publishable Key</label>
                            <input type="text" id="stripe_public_key" class="field-input" placeholder="pk_live_xxxx..." autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
                        </div>
                        <div>
                            <label class="field-label">Stripe — Secret Key</label>
                            <input type="password" id="stripe_secret_key" class="field-input" placeholder="sk_live_xxxx..." autocomplete="new-password">
                        </div>

                        <!-- Depósitos / Transferencias SPEI -->
                        <div>
                            <label class="field-label">Banco Emisor para SPEI</label>
                            <input type="text" id="bank_name" class="field-input" placeholder="Ej: BBVA Bancomer" autocomplete="off">
                        </div>
                        <div>
                            <label class="field-label">CLABE Interbancaria de la Sucursal</label>
                            <input type="text" id="bank_clabe" class="field-input" placeholder="18 dígitos (Ej: 012180001234567890)" maxlength="18" autocomplete="off">
                        </div>
                        <div class="form-group-full">
                            <label class="field-label">Titular de la Cuenta Bancaria</label>
                            <input type="text" id="bank_account_holder" class="field-input" placeholder="Razón Social o Nombre exacto en la cuenta" autocomplete="off">
                        </div>
                    </div>
                    <div style="margin-top: 1.75rem; text-align: right;">
                        <button type="submit" class="btn-action">Guardar Configuración de Cobro</button>
                    </div>
                </form>
            </div>

            <!-- Sección de Cuentas de Pago del Administrador -->
            <div class="glass-card" style="margin-top: 2rem;">
                <h3 class="card-header-title">Cuentas y Tarjetas para Recibir Pagos</h3>
                <p style="color: #888; margin-bottom: 1.5rem;">Configura las cuentas bancarias y tarjetas donde recibirás los pagos de tus clientes a través de Stripe y Mercado Pago.</p>
                
                <div id="paymentAccountsList" style="margin-bottom: 2rem;">
                    <p style="color: #666;">Cargando cuentas...</p>
                </div>

                <button onclick="openPaymentAccountModal()" class="btn-action" style="margin-bottom: 1rem;">+ Agregar Nueva Cuenta/Tarjeta</button>
            </div>
        </div>

        <!-- Tab 2: Configuración Fiscal SAT (CFDI 4.0 & Facturapi) -->
        <div id="satConfigTab" class="tab-content">
            <div class="glass-card">
                <h3 class="card-header-title">Configuración de Emisor Fiscal SAT (CFDI 4.0)</h3>
                <form id="satConfigForm" autocomplete="off" onsubmit="saveConfig(event)">
                    <div class="form-grid">
                        <div class="form-group-full">
                            <label class="field-label">Facturapi API Key (PAC Timbrado SAT)</label>
                            <input type="password" id="facturapi_api_key" class="field-input" placeholder="sk_live_xxxx..." autocomplete="new-password">
                            <p class="field-help">Facturapi gestiona la firma electrónica del SAT, timbrado en vivo, generación de XML/PDF y cancelaciones formales.</p>
                        </div>

                        <div>
                            <label class="field-label">RFC de la Sucursal / Empresa *</label>
                            <input type="text" id="company_rfc" class="field-input" placeholder="Ej: FFO880326XXX" style="text-transform: uppercase;" autocomplete="off">
                        </div>
                        <div>
                            <label class="field-label">Razón Social Exacta (sin Régimen Capital)</label>
                            <input type="text" id="company_tax_name" class="field-input" placeholder="Ej: FERRETERIA FOX Y TRUPER" autocomplete="off">
                        </div>

                        <div>
                            <label class="field-label">Régimen Fiscal (SAT) *</label>
                            <select id="company_tax_regime" class="field-select" autocomplete="off">
                                <?php 
                                    $regimes = SatCatalogs::getTaxRegimes();
                                    foreach ($regimes as $code => $label):
                                ?>
                                <option value="<?php echo $code; ?>"><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="field-label">Código Postal Domicilio Fiscal *</label>
                            <input type="text" id="company_zip_code" class="field-input" placeholder="Ej: 44100" maxlength="5" autocomplete="off">
                        </div>

                        <div class="form-group-full">
                            <label class="field-label">Estatus de Certificados de Sello Digital (CSD)</label>
                            <input type="text" id="csd_status" class="field-input" readonly style="background: rgba(34,197,94,0.1); color: #22c55e; border-color: #22c55e; font-weight: 700;">
                        </div>
                    </div>
                    <div style="margin-top: 1.75rem; text-align: right;">
                        <button type="submit" class="btn-action">Guardar Perfil Fiscal SAT</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tab 3: Guía Legal y Normativa SAT -->
        <div id="satGuideTab" class="tab-content">
            <div class="glass-card">
                <h3 class="card-header-title">Centro de Cumplimiento Legal y Normativa SAT (CFDI 4.0)</h3>
                
                <div class="guide-box">
                    <h4>1. ¿Cómo funciona la emisión de CFDI 4.0 en la tienda web?</h4>
                    <p>Cuando un cliente marca la casilla <strong>"Requiero Factura Fiscal"</strong> en el checkout, el sistema valida su RFC, Nombre Fiscal y C.P. Domicilio Fiscal contra el catálogo oficial del SAT. Al confirmarse el pago, la orden se envía al PAC (Facturapi) para generar el archivo XML firmado con sello digital y el PDF oficial con código QR.</p>
                    <p>Las ventas donde el cliente no solicita factura individual se concentran automáticamente en el reporte para la <strong>Factura Global del Día/Mes (Público en General)</strong>.</p>
                </div>

                <div class="guide-box">
                    <h4>2. Catálogo Oficial de Motivos de Cancelación SAT (OBLIGATORIO)</h4>
                    <p>Para evitar multas o discrepancias fiscales con la SHCP/SAT, al cancelar una factura en el sistema debes seleccionar uno de los siguientes 4 motivos oficiales:</p>
                    <p><strong>• 01 - Comprobante emitido con errores con relación:</strong> Aplica cuando la factura contenía un error pero se va a emitir una nueva factura sustituta (requiere ingresar el Folio Fiscal UUID previo).</p>
                    <p><strong>• 02 - Comprobante emitido con errores sin relación:</strong> Aplica cuando hubo error en RFC o precios pero no se sustituye de forma inmediata.</p>
                    <p><strong>• 03 - No se realizó la operación:</strong> Es el motivo utilizado en e-commerce cuando el cliente canceló el pedido, devolvió los productos o no se completó la entrega.</p>
                    <p><strong>• 04 - Operación nominativa relacionada en una factura global:</strong> Aplica al ajustar la factura global mensual del negocio.</p>
                </div>

                <div class="guide-box">
                    <h4>3. Regla del Buzón Tributario y Plazos de Aceptación (24 horas)</h4>
                    <p>Si la factura a cancelar supera los <strong>$1,000.00 MXN</strong> y han transcurrido más de <strong>24 horas</strong> desde su timbrado, la solicitud de cancelación entrará en estado <em>"Pendiente de Aceptación"</em>. El cliente recibirá un aviso en su Buzón Tributario y tendrá 3 días hábiles para aceptarla o rechazarla. Si no responde en 3 días, la cancelación se aprueba automáticamente (positiva ficta).</p>
                </div>

                <div class="guide-box" style="border-left-color: #22c55e;">
                    <h4>4. Recomendación para Operación Cero Riesgo</h4>
                    <p>Todas las cancelaciones ejecutadas desde la pestaña <strong>"Monitor de Facturas"</strong> envían la instrucción formal al SAT y automáticamente reincorporan las piezas de mercancía al stock de inventario (`products.stock_quantity`), garantizando sincronización contable y física.</p>
                </div>
            </div>
        </div>

        <!-- Tab 4: Monitor & Cancelaciones SAT -->
        <div id="monitorTab" class="tab-content">
            <!-- KPI Cards -->
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-lbl">Total Facturas Emitidas</div>
                    <div class="kpi-val" id="kpiTotalInvoices" style="color: #3b82f6;">0</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-lbl">Facturas Activas SAT</div>
                    <div class="kpi-val" id="kpiActiveSat" style="color: #22c55e;">0</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-lbl">Cancelaciones Registradas</div>
                    <div class="kpi-val" id="kpiCancelledSat" style="color: #ef4444;">0</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-lbl">Ventas Público General</div>
                    <div class="kpi-val" id="kpiPublicGeneral" style="color: #eab308;">0</div>
                </div>
            </div>

            <div class="glass-card">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.25rem;">
                    <h3 class="card-header-title" style="margin:0; border:none; padding:0;">Monitor de Órdenes, Pagos y Facturas SAT</h3>
                    <div style="display: flex; gap: 0.5rem; flex: 1; max-width: 400px;">
                        <input type="text" id="searchInput" class="field-input" placeholder="Buscar por Folio, RFC o Cliente..." onkeyup="if(event.key==='Enter') loadInvoices()">
                        <button onclick="loadInvoices()" class="btn-action" style="padding: 0.6rem 1.2rem;">Buscar</button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Folio Pedido</th>
                                <th>Cliente</th>
                                <th>Monto Total</th>
                                <th>Cobro en Línea</th>
                                <th>RFC / Datos Fiscales</th>
                                <th>Folio Fiscal SAT (UUID)</th>
                                <th>Estatus SAT</th>
                                <th>Acciones SAT</th>
                            </tr>
                        </thead>
                        <tbody id="invoicesTableBody">
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 2rem; color: #888899;">Cargando registros de facturación...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab 5: Respaldos BD & CSD SAT -->
        <div id="backupTab" class="tab-content">
            <div class="glass-card">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.25rem;">
                    <div>
                        <h3 class="card-header-title" style="margin:0; border:none; padding:0;">🛡️ Sistema de Respaldo de Base de Datos & Llaves CSD SAT</h3>
                        <p class="field-help" style="margin-top:0.25rem;">Genera copias de seguridad instantáneas de las tablas de PostgreSQL, tickets, facturas y datos CSD.</p>
                    </div>
                    <button onclick="triggerInstantBackup()" class="btn-action" style="background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 4px 15px rgba(16,185,129,0.3);">
                        ⚡ Generar Respaldo Ahora (1-Clic)
                    </button>
                </div>

                <div class="table-responsive" style="margin-top: 1rem;">
                    <table>
                        <thead>
                            <tr>
                                <th>Nombre del Archivo</th>
                                <th>Fecha de Generación</th>
                                <th>Tamaño de Archivo</th>
                                <th>Estado de Seguridad</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody id="backupsTableBody">
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 2rem; color: #888899;">Cargando lista de respaldos...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab 6: Factura Global -->
        <div id="globalInvoiceTab" class="tab-content">
            <div class="glass-card">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.25rem;">
                    <div>
                        <h3 class="card-header-title" style="margin:0; border:none; padding:0;">📋 Factura Global (Público en General)</h3>
                        <p class="field-help" style="margin-top:0.25rem;">Genera facturas globales para ventas sin factura individual (Requisito SAT CFDI 4.0)</p>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <button onclick="generateGlobalInvoice('daily')" class="btn-action" style="background: linear-gradient(135deg, #3b82f6, #2563eb); box-shadow: 0 4px 15px rgba(59,130,246,0.3);">
                            📅 Generar Factura Global Diaria
                        </button>
                        <button onclick="generateGlobalInvoice('monthly')" class="btn-action" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); box-shadow: 0 4px 15px rgba(139,92,246,0.3);">
                            📆 Generar Factura Global Mensual
                        </button>
                    </div>
                </div>

                <div class="table-responsive" style="margin-top: 1rem;">
                    <table>
                        <thead>
                            <tr>
                                <th>UUID Factura Global</th>
                                <th>Fecha</th>
                                <th>Monto Total</th>
                                <th>Ventas Incluidas</th>
                                <th>XML</th>
                                <th>PDF</th>
                            </tr>
                        </thead>
                        <tbody id="globalInvoicesTableBody">
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2rem; color: #888899;">Cargando facturas globales...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!-- Tab 7: Sandbox de Pruebas de Facturación                          -->
        <!-- ══════════════════════════════════════════════════════════════════ -->
        <div id="sandboxTab" class="tab-content">

            <!-- Banner de advertencia sandbox -->
            <div class="sandbox-banner">
                <div class="sandbox-banner-icon" style="font-size:1.5rem;color:#eab308;">&#9888;</div>
                <div class="sandbox-banner-text">
                    <strong>MODO SANDBOX — Entorno de Pruebas de Facturacion</strong>
                    <span>Las facturas generadas aqui <strong>no se timbran ante el SAT</strong> ni afectan datos reales.
                    Usalo para simular el flujo completo (tienda online y mostrador local) y verificar como aparecerian las facturas en el Monitor y la Factura Global.</span>
                </div>
            </div>

            <!-- KPIs Sandbox -->
            <div class="sandbox-kpi-grid">
                <div class="sandbox-kpi">
                    <span class="val" id="sbxKpiTotal">0</span>
                    <span class="lbl">Facturas Simuladas</span>
                </div>
                <div class="sandbox-kpi">
                    <span class="val" id="sbxKpiStamped" style="color:#3b82f6;">0</span>
                    <span class="lbl">Con UUID CFDI (Test)</span>
                </div>
                <div class="sandbox-kpi">
                    <span class="val" id="sbxKpiCancelled" style="color:#ef4444;">0</span>
                    <span class="lbl">Canceladas (Test)</span>
                </div>
                <div class="sandbox-kpi">
                    <span class="val" id="sbxKpiPublic" style="color:#888899;">0</span>
                    <span class="lbl">Público General</span>
                </div>
                <div class="sandbox-kpi">
                    <span class="val" id="sbxKpiMonto" style="color:#22c55e;">$0</span>
                    <span class="lbl">Monto Simulado</span>
                </div>
            </div>

            <!-- Usuarios sandbox disponibles -->
            <div class="glass-card" style="margin-bottom:1.5rem;">
                <h3 class="card-header-title">Clientes de Prueba (Sandbox)</h3>
                <p style="color:#666677;font-size:0.84rem;margin-bottom:1.25rem;">Estos clientes existen solo en el simulador. Sus datos fiscales se usan para generar las facturas de prueba. No pueden iniciar sesion en la tienda.</p>
                <div id="sbxUserCards" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:0.85rem;">
                    <div style="color:#888899;padding:1rem;">Cargando clientes sandbox...</div>
                </div>
            </div>

            <!-- Formulario de generacion de venta de prueba -->
            <div class="glass-card">
                <h3 class="card-header-title">Generador de Venta / Factura de Prueba</h3>

                <!-- Selector de tipo de venta -->
                <label class="field-label" style="margin-bottom:0.6rem;">Tipo de Venta a Simular</label>
                <div class="sale-type-selector">
                    <button type="button" class="sale-type-btn active" id="sbxTypeBtnOnline" onclick="sbxSetSaleType('online')">
                        Tienda Online
                    </button>
                    <button type="button" class="sale-type-btn" id="sbxTypeBtnLocal" onclick="sbxSetSaleType('local')">
                        Venta Mostrador Local
                    </button>
                </div>

                <form id="sandboxForm" onsubmit="sbxGenerateInvoice(event)">
                    <div class="sandbox-form-grid">
                        <!-- Selector de cliente sandbox -->
                        <div class="form-group-full" style="grid-column:1/-1;">
                            <label class="field-label">Cliente de Prueba *</label>
                            <select id="sbxClientSelect" class="field-select" onchange="sbxOnClientChange()" required>
                                <option value="">-- Selecciona un cliente sandbox --</option>
                            </select>
                            <input type="hidden" id="sbxClientId" value="">
                            <!-- Info del cliente seleccionado -->
                            <div id="sbxClientInfo" style="display:none;margin-top:0.75rem;padding:0.85rem 1rem;background:#0e0e14;border:1px solid rgba(234,179,8,0.2);border-radius:8px;font-size:0.84rem;">
                                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0.5rem;">
                                    <div><span style="color:#666677;font-weight:700;">RFC:</span> <span id="sbxInfoRfc" style="color:#eab308;font-family:monospace;"></span></div>
                                    <div><span style="color:#666677;font-weight:700;">Regimen:</span> <span id="sbxInfoRegimen" style="color:#e2e8f0;"></span></div>
                                    <div><span style="color:#666677;font-weight:700;">CP Fiscal:</span> <span id="sbxInfoCp" style="color:#e2e8f0;"></span></div>
                                    <div><span style="color:#666677;font-weight:700;">Email:</span> <span id="sbxInfoEmail" style="color:#888899;"></span></div>
                                </div>
                            </div>
                        </div>

                        <!-- Producto -->
                        <div>
                            <label class="field-label">Producto / Descripcion *</label>
                            <input type="text" id="sbxProductName" class="field-input" placeholder="Ej: Taladro Truper 1/2&quot;" value="Taladro Percutor Truper 1/2&quot;" required>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                            <div>
                                <label class="field-label">Cantidad</label>
                                <input type="number" id="sbxProductQty" class="field-input" min="1" value="1">
                            </div>
                            <div>
                                <label class="field-label">Precio Unitario ($)</label>
                                <input type="number" id="sbxProductPrice" class="field-input" min="0.01" step="0.01" value="850.00">
                            </div>
                        </div>

                        <!-- Metodo de Pago -->
                        <div>
                            <label class="field-label">Metodo de Pago Simulado</label>
                            <select id="sbxPaymentMethod" class="field-select">
                                <option value="sandbox_card">Tarjeta de Credito / Debito</option>
                                <option value="sandbox_spei">SPEI / Transferencia Bancaria</option>
                                <option value="sandbox_cash">Efectivo (Mostrador)</option>
                                <option value="sandbox_mercadopago">Mercado Pago</option>
                            </select>
                        </div>

                        <!-- Requiere Factura? -->
                        <div style="display:flex; align-items:center; gap:0.75rem; background:rgba(255,255,255,0.03); border:1px solid #22222a; border-radius:8px; padding:0.85rem 1rem;">
                            <input type="checkbox" id="sbxRequiresInvoice" style="width:18px;height:18px;accent-color:#ff7f00;cursor:pointer;" onchange="sbxToggleInvoiceFields()">
                            <label for="sbxRequiresInvoice" style="color:#fff;font-weight:700;cursor:pointer;margin:0;">
                                El cliente requiere Factura Fiscal (CFDI 4.0)
                            </label>
                        </div>
                    </div>

                    <!-- Datos fiscales (visibles solo cuando requiere factura) -->
                    <div id="sbxFiscalFields" style="display:none; margin-top:1.25rem; padding:1.25rem; background:#0e0e14; border-radius:10px; border:1px solid rgba(255,127,0,0.2);">
                        <div class="field-label" style="color:#ff7f00; margin-bottom:1rem;">Datos Fiscales del Cliente (CFDI 4.0)</div>
                        <div class="sandbox-form-grid">
                            <div>
                                <label class="field-label">RFC del Receptor *</label>
                                <input type="text" id="sbxTaxRfc" class="field-input" placeholder="Ej: GAPE800101HXX" style="text-transform:uppercase;" maxlength="13">
                            </div>
                            <div>
                                <label class="field-label">Nombre / Razón Social Fiscal *</label>
                                <input type="text" id="sbxTaxName" class="field-input" placeholder="Nombre exacto ante el SAT">
                            </div>
                            <div>
                                <label class="field-label">CP Domicilio Fiscal *</label>
                                <input type="text" id="sbxTaxZip" class="field-input" placeholder="44100" maxlength="5">
                            </div>
                            <div>
                                <label class="field-label">Régimen Fiscal del Receptor</label>
                                <select id="sbxTaxRegime" class="field-select">
                                    <option value="616">616 — Sin Obligaciones Fiscales (Persona Física)</option>
                                    <option value="601">601 — General de Ley Personas Morales</option>
                                    <option value="612">612 — Personas Físicas con Actividades Empresariales</option>
                                    <option value="626">626 — Régimen Simplificado de Confianza (RESICO)</option>
                                    <option value="621">621 — Incorporación Fiscal</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Totales en tiempo real -->
                    <div id="sbxTotalsPreview" style="margin-top:1.25rem; background:#0a0a10; border:1px solid #22222a; border-radius:10px; padding:1rem 1.25rem; display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; text-align:center;">
                        <div>
                            <div style="font-size:0.75rem;color:#666677;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Subtotal</div>
                            <div id="sbxPreviewSubtotal" style="font-size:1.25rem;font-weight:800;color:#fff;">$850.00</div>
                        </div>
                        <div>
                            <div style="font-size:0.75rem;color:#666677;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">IVA 16%</div>
                            <div id="sbxPreviewIva" style="font-size:1.25rem;font-weight:800;color:#888899;">$136.00</div>
                        </div>
                        <div>
                            <div style="font-size:0.75rem;color:#666677;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Total</div>
                            <div id="sbxPreviewTotal" style="font-size:1.35rem;font-weight:800;color:#eab308;">$986.00</div>
                        </div>
                    </div>

                    <div style="margin-top:1.5rem; display:flex; gap:0.75rem; flex-wrap:wrap;">
                        <button type="submit" class="btn-sandbox">Generar Factura de Prueba</button>
                        <button type="button" class="btn-sandbox" style="background:linear-gradient(135deg,#3b82f6,#2563eb);box-shadow:0 4px 15px rgba(59,130,246,.3);" onclick="sbxGenerateGlobal()">Simular Factura Global Diaria</button>
                    </div>
                </form>
            </div>

            <!-- Vista previa del cliente en tienda / checkout -->
            <div class="glass-card">
                <h3 class="card-header-title">Vista del Cliente — Como ve la factura en la Tienda</h3>
                <p style="color:#666677; font-size:0.85rem; margin-bottom:1.25rem;">
                    Así es como el cliente verá el formulario de facturación al finalizar su compra en la tienda online o al solicitar ticket en el mostrador.
                </p>

                <div style="max-width:560px; margin:0 auto;">
                    <!-- Checkout invoice request block (replica visual) -->
                    <div class="checkout-invoice-block">
                        <h4>Datos para Factura Electronica (CFDI 4.0)</h4>
                        <div style="display:flex; align-items:center; gap:0.6rem; margin-bottom:1rem; padding:0.6rem 0.85rem; background:rgba(255,127,0,0.06); border-radius:6px; border:1px solid rgba(255,127,0,0.15);">
                            <input type="checkbox" id="sbxPreviewCheckbox" style="width:16px;height:16px;accent-color:#ff7f00;" onchange="sbxTogglePreviewFields()">
                            <label for="sbxPreviewCheckbox" style="font-size:0.87rem;color:#fff;font-weight:600;cursor:pointer;margin:0;">Requiero Factura Fiscal</label>
                        </div>
                        <div id="sbxPreviewInvoiceForm" style="display:none;">
                            <div class="checkout-field">
                                <label>RFC del Receptor</label>
                                <div class="checkout-field-value" id="sbxPreviewRfcDisplay">GAPE800101HXX</div>
                            </div>
                            <div class="checkout-field">
                                <label>Nombre / Razón Social</label>
                                <div class="checkout-field-value" id="sbxPreviewNameDisplay">JUAN GARCIA PEREZ</div>
                            </div>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                                <div class="checkout-field">
                                    <label>CP Domicilio Fiscal</label>
                                    <div class="checkout-field-value" id="sbxPreviewZipDisplay">44100</div>
                                </div>
                                <div class="checkout-field">
                                    <label>Régimen Fiscal</label>
                                    <div class="checkout-field-value">616 — Sin Obligaciones</div>
                                </div>
                            </div>
                            <div style="background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.2);border-radius:8px;padding:0.75rem;margin-top:0.75rem;font-size:0.82rem;color:#22c55e;">
                                ✅ Datos validados contra catálogo SAT. La factura CFDI 4.0 se enviará a <span id="sbxPreviewEmailDisplay">sandbox@test.com</span> al confirmar el pago.
                            </div>
                        </div>
                        <div id="sbxPreviewPublicMsg" style="font-size:0.83rem;color:#888899;padding:0.75rem;background:#0e0e14;border-radius:7px;border:1px solid #1c1c26;">
                            ℹ️ Si no solicitas factura, tu venta se incluirá en la <strong>Factura Global del Mes</strong> (Público en General – RFC: XAXX010101000).
                        </div>
                    </div>

                    <!-- Resumen de order simulado -->
                    <div style="margin-top:1rem; background:#0e0e14; border:1px solid #1c1c26; border-radius:10px; padding:1rem 1.25rem;">
                        <div style="font-size:0.78rem;font-weight:700;color:#666677;text-transform:uppercase;letter-spacing:.05em;margin-bottom:0.75rem;">Resumen de la Orden</div>
                        <div id="sbxOrderPreviewRow" style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem 0;border-bottom:1px solid #1c1c26;">
                            <span style="font-size:0.88rem;color:#e2e8f0;">Taladro Percutor Truper 1/2" × 1</span>
                            <span style="font-size:0.88rem;font-weight:700;color:#fff;">$850.00</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:0.4rem 0;">
                            <span style="font-size:0.82rem;color:#666677;">IVA (16%)</span>
                            <span style="font-size:0.82rem;color:#888899;">$136.00</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-top:1px solid #22222a;margin-top:0.25rem;">
                            <span style="font-size:0.95rem;font-weight:800;color:#fff;">Total</span>
                            <span id="sbxOrderPreviewTotal" style="font-size:0.95rem;font-weight:800;color:#ff7f00;">$986.00</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de facturas sandbox generadas -->
            <div class="glass-card">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:1.25rem;">
                    <div>
                        <h3 class="card-header-title" style="margin:0;border:none;padding:0;">Facturas de Prueba Generadas</h3>
                        <p class="field-help" style="margin-top:0.25rem;">Todas las facturas sandbox aparecen tambien en el Monitor de Facturas con badge <strong style="color:#eab308;">[SANDBOX]</strong>.</p>
                    </div>
                    <div style="display:flex;gap:0.5rem;">
                        <button onclick="sbxLoadInvoices()" class="btn-action" style="padding:0.55rem 1rem;font-size:0.82rem;">Actualizar</button>
                        <button onclick="sbxClearSandbox()" class="btn-sandbox-danger">Limpiar Sandbox</button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Folio</th>
                                <th>Tipo</th>
                                <th>Cliente</th>
                                <th>Producto</th>
                                <th>Total</th>
                                <th>UUID (Test)</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="sbxTableBody">
                            <tr>
                                <td colspan="8" style="text-align:center;padding:2rem;color:#888899;">Cargando facturas de prueba...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <script src="js/main.js"></script>
    <script src="js/modals.js"></script>
    <script>
        window.csrfToken = '<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8"); ?>';

        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function switchTab(tabId, btnEl) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

            // Sandbox tab keeps its own active border color
            btnEl.classList.add('active');
            if (tabId === 'sandboxTab') {
                btnEl.style.borderColor = '#eab308';
                btnEl.style.color = '#eab308';
                btnEl.style.background = 'rgba(234,179,8,0.14)';
                btnEl.style.boxShadow = '0 4px 15px rgba(234,179,8,0.25)';
            }
            document.getElementById(tabId).classList.add('active');

            if (tabId === 'monitorTab') {
                loadInvoices();
            } else if (tabId === 'backupTab') {
                loadBackupsList();
            } else if (tabId === 'globalInvoiceTab') {
                loadGlobalInvoices();
            } else if (tabId === 'sandboxTab') {
                sbxLoadSandboxUsers();
                sbxLoadInvoices();
            }
        }

        async function loadGlobalInvoices() {
            const tbody = document.getElementById('globalInvoicesTableBody');
            if (!tbody) return;
            try {
                const res = await fetch('api/admin_online_billing_api.php?action=list_global_invoices');
                const data = await res.json();
                if (data.success && Array.isArray(data.invoices)) {
                    if (data.invoices.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:2rem; color:#888899;">No hay facturas globales generadas.</td></tr>';
                        return;
                    }

                    tbody.innerHTML = data.invoices.map(inv => `
                        <tr>
                            <td><strong style="color:#ff7f00;">${escapeHtml(inv.invoice_uuid)}</strong></td>
                            <td>${escapeHtml(inv.invoice_date)}</td>
                            <td>$${parseFloat(inv.total_amount).toFixed(2)}</td>
                            <td>${inv.sales_count}</td>
                            <td><a href="${escapeHtml(inv.xml_url)}" target="_blank" class="btn-action" style="padding:0.3rem 0.6rem; font-size:0.75rem;">XML</a></td>
                            <td><a href="${escapeHtml(inv.pdf_url)}" target="_blank" class="btn-action" style="padding:0.3rem 0.6rem; font-size:0.75rem;">PDF</a></td>
                        </tr>
                    `).join('');
                }
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:#ef4444; padding:2rem;">Error al cargar facturas globales.</td></tr>';
            }
        }

        async function generateGlobalInvoice(type) {
            const promptTitle = type === 'daily' ? 'Factura Global Diaria' : 'Factura Global Mensual';
            const promptMsg = type === 'daily'
                ? 'Ingrese la fecha para la factura global (YYYY-MM-DD), o deje vacío para ayer:'
                : 'Ingrese el mes para la factura global (YYYY-MM), o deje vacío para el mes anterior:';

            showPrompt(promptTitle, promptMsg, '', async (date) => {
                showAlert('Generando factura global...', 'info');
                
                try {
                    const url = `api/admin_online_billing_api.php?action=generate_global_invoice&type=${type}${date ? '&date=' + encodeURIComponent(date) : ''}`;
                    const res = await fetch(url, { method: 'POST' });
                    const data = await res.json();
                    
                    if (data.success) {
                        if (data.invoice) {
                            showAlert(`Factura global generada: ${data.invoice.uuid} (${data.invoice.sales_count} ventas)`, 'success');
                        } else {
                            showAlert(data.message || 'No hubo ventas para facturar', 'info');
                        }
                        loadGlobalInvoices();
                    } else {
                        showAlert('Error: ' + (data.message || 'Error desconocido'), 'error');
                    }
                } catch (e) {
                    showAlert('Error al generar factura global', 'error');
                }
            });
        }

        async function loadBackupsList() {
            const tbody = document.getElementById('backupsTableBody');
            if (!tbody) return;
            try {
                const res = await fetch('api/admin_backup.php?action=list');
                const data = await res.json();
                if (data.success && Array.isArray(data.backups)) {
                    if (data.backups.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:2rem; color:#888899;">No hay respaldos previos. Haz clic en "Generar Respaldo Ahora".</td></tr>';
                        return;
                    }

                    tbody.innerHTML = data.backups.map(b => `
                        <tr>
                            <td><strong style="color:#ff7f00;">${escapeHtml(b.filename)}</strong></td>
                            <td>${escapeHtml(b.date_formatted)}</td>
                            <td>${b.filesize_kb} KB</td>
                            <td><span style="background:rgba(34,197,94,0.12); color:#22c55e; padding:3px 8px; border-radius:99px; font-size:0.75rem; font-weight:bold;">🟢 Encriptado & Protegido</span></td>
                            <td>
                                <a href="api/admin_backup.php?action=download&file=${encodeURIComponent(b.filename)}" class="btn-action" style="padding:0.4rem 0.8rem; font-size:0.8rem; text-decoration:none;" target="_blank">⬇️ Descargar</a>
                            </td>
                        </tr>
                    `).join('');
                }
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; color:#ef4444; padding:2rem;">Error al cargar la lista de respaldos.</td></tr>';
            }
        }

        async function triggerInstantBackup() {
            showAlert('Iniciando proceso de respaldo de Base de Datos y SAT CSD...', 'info');
            try {
                const res = await fetch('api/admin_backup.php?action=run');
                const data = await res.json();
                if (data.success) {
                    showAlert(`Respaldo generado exitosamente (${data.filesize_kb} KB)`, 'success');
                    loadBackupsList();
                } else {
                    showAlert('Error al generar respaldo: ' + (data.message || 'Error desconocido'), 'error');
                }
            } catch (e) {
                showAlert('Error de conexión al generar respaldo', 'error');
            }
        }

        async function loadConfig() {
            try {
                const res = await fetch('api/admin_online_billing_api.php?action=get_config');
                const data = await res.json();
                if (data.success && data.config) {
                    const cfg = data.config;
                    document.getElementById('payment_environment').value = cfg.payment_environment || 'production';
                    document.getElementById('mercadopago_public_key').value = cfg.mercadopago_public_key || '';
                    document.getElementById('mercadopago_access_token').value = cfg.mercadopago_access_token || '';
                    document.getElementById('stripe_public_key').value = cfg.stripe_public_key || '';
                    document.getElementById('stripe_secret_key').value = cfg.stripe_secret_key || '';
                    document.getElementById('bank_name').value = cfg.bank_name || '';
                    document.getElementById('bank_clabe').value = cfg.bank_clabe || '';
                    document.getElementById('bank_account_holder').value = cfg.bank_account_holder || '';

                    document.getElementById('facturapi_api_key').value = cfg.facturapi_api_key || '';
                    document.getElementById('company_rfc').value = cfg.company_rfc || '';
                    document.getElementById('company_tax_name').value = cfg.company_tax_name || '';
                    document.getElementById('company_tax_regime').value = cfg.company_tax_regime || '601';
                    document.getElementById('company_zip_code').value = cfg.company_zip_code || '';
                    document.getElementById('csd_status').value = cfg.csd_status || 'Activo y Vigente (SAT México)';

                    const envBadge = document.getElementById('envBadgeDisplay');
                    if (cfg.payment_environment === 'sandbox') {
                        envBadge.textContent = 'ENTORNO: VERIFICACIÓN TÉCNICA (SANDBOX)';
                        envBadge.style.borderColor = '#ff7f00';
                        envBadge.style.color = '#ff7f00';
                        envBadge.style.background = 'rgba(255, 127, 0, 0.12)';
                    } else {
                        envBadge.textContent = 'ENTORNO: PRODUCCIÓN (EN VIVO)';
                        envBadge.style.borderColor = '#22c55e';
                        envBadge.style.color = '#22c55e';
                        envBadge.style.background = 'rgba(34, 197, 94, 0.12)';
                    }
                }
            } catch (e) {
                console.error('Error cargando configuración:', e);
            }
        }

        async function saveConfig(event) {
            event.preventDefault();
            const configPayload = {
                payment_environment: document.getElementById('payment_environment').value,
                mercadopago_public_key: document.getElementById('mercadopago_public_key').value,
                mercadopago_access_token: document.getElementById('mercadopago_access_token').value,
                stripe_public_key: document.getElementById('stripe_public_key').value,
                stripe_secret_key: document.getElementById('stripe_secret_key').value,
                bank_name: document.getElementById('bank_name').value,
                bank_clabe: document.getElementById('bank_clabe').value,
                bank_account_holder: document.getElementById('bank_account_holder').value,

                facturapi_api_key: document.getElementById('facturapi_api_key').value,
                company_rfc: document.getElementById('company_rfc').value,
                company_tax_name: document.getElementById('company_tax_name').value,
                company_tax_regime: document.getElementById('company_tax_regime').value,
                company_zip_code: document.getElementById('company_zip_code').value,
                csd_status: document.getElementById('csd_status').value
            };

            try {
                const res = await fetch('api/admin_online_billing_api.php?action=save_config', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(configPayload)
                });
                const data = await res.json();
                if (data.success) {
                    showAlert(data.message || 'Configuración guardada correctamente', 'success');
                    loadConfig();
                } else {
                    showAlert('Error: ' + (data.message || 'No se pudo guardar'), 'error');
                }
            } catch (e) {
                showAlert('Error de conexión al guardar configuración', 'error');
            }
        }

        async function loadInvoices() {
            const tbody = document.getElementById('invoicesTableBody');
            const search = document.getElementById('searchInput').value.trim();
            tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; padding: 2rem; color: #888899;">Cargando datos...</td></tr>`;

            try {
                const res = await fetch(`api/admin_online_billing_api.php?action=list_invoices&search=${encodeURIComponent(search)}`);
                const data = await res.json();

                if (!data.success || !Array.isArray(data.orders)) {
                    tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; color: #ef4444;">Error al obtener facturas.</td></tr>`;
                    return;
                }

                const orders = data.orders;
                if (orders.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; padding: 2rem; color: #888899;">No se encontraron órdenes registradas.</td></tr>`;
                    return;
                }

                let totalInvoices = orders.length;
                let activeSat = 0;
                let cancelledSat = 0;
                let publicGeneral = 0;

                tbody.innerHTML = orders.map(ord => {
                    if (ord.status === 'cancelled') cancelledSat++;
                    else if (ord.requires_invoice && ord.sat_uuid) activeSat++;
                    else publicGeneral++;

                    const isCancelled   = ord.status === 'cancelled';
                    const isSandbox     = String(ord.order_number).startsWith('SBX-');
                    const payBadgeClass = ord.payment_status === 'paid' ? 'badge-paid' : (isCancelled ? 'badge-cancelled' : 'badge-pending');
                    const satBadgeClass = isCancelled ? 'badge-cancelled' : (ord.requires_invoice ? 'badge-stamped' : 'badge-pending');

                    const viewUrl = isSandbox
                        ? `api/sandbox_billing_api.php?action=download_pdf&order_id=${ord.id}`
                        : `/api/invoice.php?action=download_pdf&order_id=${ord.id}&folio=${encodeURIComponent(ord.order_number)}`;

                    let actionsHtml = `<div style="display:flex;gap:5px;align-items:center;flex-wrap:wrap;">`;
                    actionsHtml += `<a href="${viewUrl}" target="_blank" class="btn-view-invoice" title="Visualizar Factura / Comprobante">Visualizar</a>`;

                    if (isSandbox) {
                        if (!isCancelled) {
                            actionsHtml += `<button class="sandbox-cancel-btn" onclick="sbxCancelFromMonitor(${ord.id}, '${escapeHtml(ord.order_number)}')">Cancelar</button>`;
                        } else {
                            actionsHtml += `<span style="font-size:0.75rem;color:#eab308;font-weight:700;">Cancelada (Test)</span>`;
                        }
                    } else {
                        if (!isCancelled) {
                            actionsHtml += `<button class="btn-cancel-sat" onclick="openSatCancelModal(${ord.id}, '${escapeHtml(ord.order_number)}')">Cancelar SAT</button>`;
                        } else {
                            actionsHtml += `<span style="font-size:0.75rem;color:#ef4444;font-weight:700;">Motivo ${escapeHtml(ord.sat_cancellation_reason || '03')}</span>`;
                        }
                    }

                    actionsHtml += `<button class="btn-delete-invoice" onclick="deleteInvoiceFromMonitor(${ord.id}, '${escapeHtml(ord.order_number)}')" title="Borrar registro permanentemente">Borrar</button>`;
                    actionsHtml += `</div>`;

                    return `
                        <tr style="${isSandbox ? 'background:rgba(234,179,8,0.03);' : ''}">
                            <td style="font-family: monospace; font-weight: 700; color: ${isSandbox ? '#eab308' : '#ff7f00'};">
                                ${escapeHtml(ord.order_number)}
                                ${isSandbox ? '<span class="badge-sandbox">SANDBOX</span>' : ''}
                            </td>
                            <td>${escapeHtml(ord.client_name)}</td>
                            <td style="font-weight: 700; color: #fff;">$${Number(ord.total_amount || 0).toFixed(2)}</td>
                            <td><span class="badge-status ${payBadgeClass}">${escapeHtml(ord.payment_status || 'Pendiente')}</span></td>
                            <td>
                                <strong>${escapeHtml(ord.tax_rfc || 'Público General')}</strong><br>
                                <span style="font-size: 0.78rem; color: #888899;">${escapeHtml(ord.tax_name || 'Venta de Mostrador')}</span>
                            </td>
                            <td style="font-family: monospace; font-size: 0.78rem; color: #cbd5e1;">${escapeHtml(ord.sat_uuid || 'Sin Timbrar (Público General)')}</td>
                            <td><span class="badge-status ${satBadgeClass}">${isCancelled ? 'Cancelada SAT' : (ord.requires_invoice ? 'CFDI 4.0 Activo' : 'Factura Global')}</span></td>
                            <td>${actionsHtml}</td>
                        </tr>
                    `;
                }).join('');

                document.getElementById('kpiTotalInvoices').textContent = totalInvoices;
                document.getElementById('kpiActiveSat').textContent = activeSat;
                document.getElementById('kpiCancelledSat').textContent = cancelledSat;
                document.getElementById('kpiPublicGeneral').textContent = publicGeneral;

            } catch (e) {
                console.error(e);
                tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; color: #ef4444;">Error de conexión con el servidor.</td></tr>`;
            }
        }

        function deleteInvoiceFromMonitor(orderId, orderNumber) {
            confirmAction(
                'Eliminar Registro de Facturación',
                `<p>¿Deseas eliminar permanentemente el registro <strong>${escapeHtml(orderNumber)}</strong>?</p>
                 <p style="color:#ef4444;font-size:0.85rem;">Esta acción eliminará la orden del monitor y de la base de datos.</p>`,
                '',
                async function() {
                    try {
                        const res = await fetch('api/admin_online_billing_api.php?action=delete_invoice', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': window.csrfToken
                            },
                            body: JSON.stringify({
                                order_id: orderId,
                                csrf_token: window.csrfToken
                            })
                        });
                        const data = await res.json();
                        if (data.success) {
                            showAlert(data.message || 'Registro eliminado correctamente.', 'success');
                            loadInvoices();
                            if (typeof sbxLoadInvoices === 'function') {
                                sbxLoadInvoices();
                            }
                        } else {
                            showAlert('Error: ' + (data.message || 'No se pudo eliminar'), 'error');
                        }
                    } catch(e) {
                        showAlert('Error de conexión al eliminar el registro.', 'error');
                    }
                }
            );
        }

        function openSatCancelModal(orderId, orderNumber) {
            confirmAction(
                'Cancelar Factura / Pedido ante el SAT',
                `<p>¿Estás seguro de que deseas cancelar la factura y pedido <strong>${orderNumber}</strong>?</p>
                 <div style="margin-top: 1rem; text-align: left;">
                     <label for="satMotiveSelect" style="display: block; font-size: 0.85rem; color: #cbd5e1; margin-bottom: 0.4rem; font-weight:700;">Selecciona el Motivo Oficial SAT (CFDI 4.0):</label>
                     <select id="satMotiveSelect" style="width:100%; padding:0.6rem; border-radius:6px; background:#0f0f15; color:#fff; border:1px solid rgba(255,255,255,0.2);">
                         <option value="03" selected>03 - No se realizó la operación (Cliente canceló o devolución)</option>
                         <option value="02">02 - Comprobante emitido con errores sin relación</option>
                         <option value="01">01 - Comprobante emitido con errores con relación</option>
                         <option value="04">04 - Operación nominativa relacionada en factura global</option>
                     </select>
                     <label for="satCancelNotes" style="display: block; font-size: 0.85rem; color: #cbd5e1; margin-top: 0.8rem; margin-bottom: 0.4rem; font-weight:700;">Notas de la cancelación:</label>
                     <input type="text" id="satCancelNotes" value="Cancelado por administración" style="width:100%; padding:0.6rem; border-radius:6px; background:#0f0f15; color:#fff; border:1px solid rgba(255,255,255,0.2); box-sizing:border-box;" />
                 </div>`,
                '',
                async function() {
                    const satReason = document.getElementById('satMotiveSelect').value;
                    const notes = document.getElementById('satCancelNotes').value;

                    try {
                        const res = await fetch('api/admin_online_billing_api.php?action=cancel_sat_invoice', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                order_id: orderId,
                                sat_reason: satReason,
                                notes: notes
                            })
                        });
                        const data = await res.json();
                        if (data.success) {
                            showAlert(data.message || 'Factura cancelada correctamente ante el SAT', 'success');
                            loadInvoices();
                        } else {
                            showAlert('Error: ' + (data.message || 'No se pudo cancelar'), 'error');
                        }
                    } catch (e) {
                        showAlert('Error de conexión al procesar la cancelación SAT', 'error');
                    }
                }
            );
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadConfig();
            loadPaymentAccounts();
        });

        // ═══════════════════════════════════════════════════════════════════
        // SANDBOX DE PRUEBAS — JavaScript completo
        // ═══════════════════════════════════════════════════════════════════

        let sbxSaleType = 'online';
        let sbxUsers    = [];  // cache de usuarios sandbox

        /** Carga los usuarios sandbox desde la BD y popula el selector */
        async function sbxLoadSandboxUsers() {
            try {
                const res  = await fetch('api/sandbox_billing_api.php?action=get_sandbox_users');
                const data = await res.json();
                if (!data.success) return;

                sbxUsers = data.users || [];

                // Poblar el <select> del formulario
                const sel = document.getElementById('sbxClientSelect');
                if (sel) {
                    sel.innerHTML = '<option value="">-- Selecciona un cliente sandbox --</option>';
                    sbxUsers.forEach(u => {
                        const opt = document.createElement('option');
                        opt.value = u.client_id;
                        opt.dataset.rfc    = u.rfc   || '';
                        opt.dataset.name   = (u.first_name + ' ' + u.last_name).trim();
                        opt.dataset.email  = u.email  || '';
                        opt.dataset.regime = u.tax_regime || '616';
                        opt.dataset.zip    = u.zip_code_fiscal || '';
                        opt.dataset.code   = u.user_code || '';
                        opt.textContent = `${u.user_code} — ${u.first_name} ${u.last_name} (${u.rfc || 'Sin RFC'})`;
                        sel.appendChild(opt);
                    });
                }

                // Renderizar tarjetas de clientes sandbox
                const regLabels = { '616':'Sin Obligaciones (PF)', '601':'Ley Personas Morales', '612':'Act.Empresariales', '626':'RESICO', '621':'Incorp.Fiscal' };
                const container = document.getElementById('sbxUserCards');
                if (container && sbxUsers.length > 0) {
                    container.innerHTML = sbxUsers.map(u => `
                        <div style="background:#0e0e14;border:1px solid rgba(234,179,8,0.18);border-radius:10px;padding:1rem;
                                    cursor:pointer;transition:all .2s;" 
                             onclick="sbxSelectUserFromCard(${u.client_id})"
                             onmouseover="this.style.borderColor='rgba(234,179,8,0.6)';this.style.background='rgba(234,179,8,0.06)'"
                             onmouseout="this.style.borderColor='rgba(234,179,8,0.18)';this.style.background='#0e0e14'">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
                                <span style="font-size:0.72rem;font-weight:700;color:#666677;text-transform:uppercase;letter-spacing:.05em;">${escapeHtml(u.user_code)}</span>
                                <span class="badge-sandbox">${u.tax_regime || '616'}</span>
                            </div>
                            <div style="font-weight:700;color:#fff;margin-bottom:0.25rem;">${escapeHtml(u.first_name + ' ' + u.last_name)}</div>
                            <div style="font-family:monospace;font-size:0.82rem;color:#eab308;margin-bottom:0.25rem;">${escapeHtml(u.rfc || 'XAXX010101000')}</div>
                            <div style="font-size:0.75rem;color:#666677;">${regLabels[u.tax_regime] || u.tax_regime} &mdash; CP ${u.zip_code_fiscal || '00000'}</div>
                            <div style="margin-top:0.6rem;">
                                <span style="font-size:0.72rem;background:rgba(34,197,94,0.1);color:#22c55e;border:1px solid rgba(34,197,94,0.25);padding:2px 8px;border-radius:20px;">Usar este cliente &rsaquo;</span>
                            </div>
                        </div>
                    `).join('');
                }
            } catch(e) {
                console.error('Error cargando usuarios sandbox:', e);
            }
        }

        /** Selecciona un cliente desde la tarjeta (click) */
        function sbxSelectUserFromCard(clientId) {
            const sel = document.getElementById('sbxClientSelect');
            if (sel) {
                sel.value = clientId;
                sbxOnClientChange();
                // Scroll al formulario
                const form = document.getElementById('sandboxForm');
                if (form) form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        /** Se dispara al cambiar el selector de cliente: auto-llena los datos fiscales */
        function sbxOnClientChange() {
            const sel = document.getElementById('sbxClientSelect');
            const opt = sel ? sel.options[sel.selectedIndex] : null;
            const infoBox = document.getElementById('sbxClientInfo');

            if (!opt || !opt.value) {
                document.getElementById('sbxClientId').value = '';
                if (infoBox) infoBox.style.display = 'none';
                return;
            }

            document.getElementById('sbxClientId').value = opt.value;

            // Mostrar datos fiscales del cliente seleccionado
            if (infoBox) {
                document.getElementById('sbxInfoRfc').textContent    = opt.dataset.rfc    || 'N/A';
                document.getElementById('sbxInfoRegimen').textContent = opt.dataset.regime || 'N/A';
                document.getElementById('sbxInfoCp').textContent     = opt.dataset.zip    || 'N/A';
                document.getElementById('sbxInfoEmail').textContent  = opt.dataset.email  || 'N/A';
                infoBox.style.display = 'block';
            }

            // Sincronizar con la vista previa del checkout
            const rfcEl = document.getElementById('sbxPreviewRfcDisplay');
            if (rfcEl) rfcEl.textContent  = opt.dataset.rfc  || 'RFC';
            const nmEl = document.getElementById('sbxPreviewNameDisplay');
            if (nmEl) nmEl.textContent   = opt.dataset.name || 'Nombre';
            const zpEl = document.getElementById('sbxPreviewZipDisplay');
            if (zpEl) zpEl.textContent   = opt.dataset.zip  || 'CP';
            const emEl = document.getElementById('sbxPreviewEmailDisplay');
            if (emEl) emEl.textContent   = opt.dataset.email || 'Email';
        }

        /** Cambia el tipo de venta en el formulario sandbox */
        function sbxSetSaleType(type) {
            sbxSaleType = type;
            document.getElementById('sbxTypeBtnOnline').classList.toggle('active', type === 'online');
            document.getElementById('sbxTypeBtnLocal').classList.toggle('active', type === 'local');
            // Ajustar metodo de pago por defecto
            const pm = document.getElementById('sbxPaymentMethod');
            if (type === 'local') {
                pm.value = 'sandbox_cash';
            } else {
                pm.value = pm.value === 'sandbox_cash' ? 'sandbox_card' : pm.value;
            }
        }

        /** Muestra / oculta campos de RFC según checkbox */
        function sbxToggleInvoiceFields() {
            const checked = document.getElementById('sbxRequiresInvoice').checked;
            document.getElementById('sbxFiscalFields').style.display = checked ? 'block' : 'none';
            sbxUpdateTotals();
        }

        /** Actualiza preview de totales en tiempo real */
        function sbxUpdateTotals() {
            const qty   = parseFloat(document.getElementById('sbxProductQty').value)  || 1;
            const price = parseFloat(document.getElementById('sbxProductPrice').value) || 0;
            const sub   = qty * price;
            const iva   = sub * 0.16;
            const total = sub + iva;
            document.getElementById('sbxPreviewSubtotal').textContent = '$' + sub.toFixed(2);
            document.getElementById('sbxPreviewIva').textContent      = '$' + iva.toFixed(2);
            document.getElementById('sbxPreviewTotal').textContent     = '$' + total.toFixed(2);
            // Actualizar resumen de orden
            const pname = document.getElementById('sbxProductName').value || 'Producto';
            document.getElementById('sbxOrderPreviewRow').innerHTML =
                `<span style="font-size:0.88rem;color:#e2e8f0;">${escapeHtml(pname)} × ${qty}</span>
                 <span style="font-size:0.88rem;font-weight:700;color:#fff;">$${sub.toFixed(2)}</span>`;
            document.getElementById('sbxOrderPreviewTotal').textContent = '$' + total.toFixed(2);
        }

        /** Controla la vista previa del checkout del cliente */
        function sbxTogglePreviewFields() {
            const chk = document.getElementById('sbxPreviewCheckbox').checked;
            document.getElementById('sbxPreviewInvoiceForm').style.display = chk ? 'block' : 'none';
            document.getElementById('sbxPreviewPublicMsg').style.display   = chk ? 'none'  : 'block';
            if (chk) {
                // Sincronizar datos del formulario
                document.getElementById('sbxPreviewRfcDisplay').textContent  =
                    document.getElementById('sbxTaxRfc').value  || 'GAPE800101HXX';
                document.getElementById('sbxPreviewNameDisplay').textContent =
                    document.getElementById('sbxTaxName').value || 'JUAN GARCIA PEREZ';
                document.getElementById('sbxPreviewZipDisplay').textContent  =
                    document.getElementById('sbxTaxZip').value  || '44100';
                document.getElementById('sbxPreviewEmailDisplay').textContent =
                    document.getElementById('sbxClientEmail').value || 'sandbox@test.com';
            }
        }

        /** Genera una factura / venta de prueba */
        async function sbxGenerateInvoice(event) {
            event.preventDefault();
            const requiresInv = document.getElementById('sbxRequiresInvoice').checked;
            const clientId    = parseInt(document.getElementById('sbxClientId').value);

            if (!clientId) {
                showAlert('Selecciona un cliente de prueba antes de generar la factura.', 'error');
                return;
            }

            const payload = {
                client_id:        clientId,
                product_name:     document.getElementById('sbxProductName').value,
                product_qty:      parseInt(document.getElementById('sbxProductQty').value) || 1,
                product_price:    parseFloat(document.getElementById('sbxProductPrice').value) || 0,
                payment_gateway:  document.getElementById('sbxPaymentMethod').value,
                sale_type:        sbxSaleType,
                requires_invoice: requiresInv,
            };

            const btn = event.target.querySelector('button[type="submit"]');
            if (btn) { btn.disabled = true; btn.textContent = 'Generando...'; }

            try {
                const res  = await fetch('api/sandbox_billing_api.php?action=generate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                let data;
                try { data = await res.json(); } catch(parseErr) {
                    showAlert('El servidor devolvio una respuesta invalida. Revisa los logs.', 'error');
                    return;
                }

                if (data.success) {
                    const folio  = data.order_number || '';
                    const client = data.client_name  || '';
                    const uuid   = data.sat_uuid     || '';

                    if (data.order_id) {
                        // En el entorno real y en el sandbox, se descarga/abre automaticamente la factura
                        window.open('api/sandbox_billing_api.php?action=download_pdf&order_id=' + encodeURIComponent(data.order_id), '_blank');
                    }

                    const msg = data.requires_invoice
                        ? 'Factura CFDI simulada ' + folio + ' generada y descargada. Cliente: ' + client + ' | UUID: ' + uuid
                        : 'Venta Publico General simulada ' + folio + ' generada. Cliente: ' + client;
                    showAlert(msg, 'success');
                    sbxLoadInvoices();
                } else {
                    showAlert('Error: ' + (data.message || 'No se pudo generar'), 'error');
                }
            } catch (e) {
                console.error('Sandbox generate error:', e);
                showAlert('Error al conectar con la API del sandbox.', 'error');
            } finally {
                if (btn) { btn.disabled = false; btn.textContent = 'Generar Factura de Prueba'; }
            }
        }

        /** Carga y renderiza la tabla de facturas sandbox */
        async function sbxLoadInvoices() {
            var tbody = document.getElementById('sbxTableBody');
            if (!tbody) { console.warn('sbxTableBody not found'); return; }
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:2rem;color:#888899;">Cargando...</td></tr>';

            var res, data;
            try {
                res  = await fetch('api/sandbox_billing_api.php?action=list');
                data = await res.json();
            } catch(err) {
                console.error('sbxLoadInvoices fetch error:', err);
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#ef4444;padding:2rem;">Error de red: ' + escapeHtml(String(err.message || err)) + '</td></tr>';
                return;
            }

            if (!data.success) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#ef4444;padding:2rem;">' + escapeHtml(data.message || 'Error desconocido') + '</td></tr>';
                return;
            }

            // KPIs
            var k = data.kpis || {};
            var el;
            el = document.getElementById('sbxKpiTotal');     if (el) el.textContent = k.total      || 0;
            el = document.getElementById('sbxKpiStamped');   if (el) el.textContent = k.stamped    || 0;
            el = document.getElementById('sbxKpiCancelled'); if (el) el.textContent = k.cancelled  || 0;
            el = document.getElementById('sbxKpiPublic');    if (el) el.textContent = k.public_gen || 0;
            el = document.getElementById('sbxKpiMonto');     if (el) el.textContent = '$' + (parseFloat(k.total_mxn) || 0).toFixed(2);

            var orders = Array.isArray(data.orders) ? data.orders : [];
            if (orders.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:2rem;color:#888899;">No hay facturas de prueba. Usa el formulario para comenzar.</td></tr>';
                return;
            }

            var payMap = { 'sandbox_card':'Tarjeta','sandbox_spei':'SPEI','sandbox_cash':'Efectivo','sandbox_mercadopago':'Mercado Pago' };
            var html = '';
            for (var i = 0; i < orders.length; i++) {
                var o = orders[i];
                var cancelled = (o.status === 'cancelled');
                var hasUuid   = (o.sat_uuid && !cancelled);
                var sale      = (o.sale_type === 'local') ? 'Mostrador' : 'Online';
                var pay       = payMap[o.payment_gateway] || (o.payment_gateway || '-');
                var uuid      = o.sat_uuid ? '<span style="color:#3b82f6;font-family:monospace;">' + escapeHtml(o.sat_uuid) + '</span>' : '<span style="color:#666677;">Pub. General</span>';
                var st        = cancelled ? '<span class="badge-status badge-cancelled">Cancelada</span>' : hasUuid ? '<span class="badge-status badge-stamped">CFDI Simulado</span>' : '<span class="badge-status badge-pending">Fac. Global</span>';

                var act = '<div style="display:flex;gap:5px;align-items:center;flex-wrap:wrap;">';
                if (cancelled) {
                    act += '<a href="api/sandbox_billing_api.php?action=download_pdf&order_id=' + o.id + '" target="_blank" class="sandbox-download-btn" style="background:#475569;" title="Ver Comprobante Cancelado">PDF</a>';
                    act += '<span style="color:#ef4444;font-size:0.75rem;font-weight:700;">Cancelada</span>';
                } else if (hasUuid) {
                    act += '<a href="api/sandbox_billing_api.php?action=download_pdf&order_id=' + o.id + '" target="_blank" class="sandbox-download-btn" title="Descargar Factura PDF (CFDI 4.0)">Descargar PDF</a>';
                    act += '<a href="api/sandbox_billing_api.php?action=download_xml&order_id=' + o.id + '" target="_blank" class="sandbox-xml-btn" title="Descargar Factura XML SAT (CFDI 4.0)">XML</a>';
                    act += '<button class="sandbox-cancel-btn" onclick="sbxCancelInvoice(' + o.id + ',\'' + escapeHtml(o.order_number || '') + '\')">Cancelar</button>';
                } else {
                    act += '<a href="api/sandbox_billing_api.php?action=download_pdf&order_id=' + o.id + '" target="_blank" class="sandbox-download-btn" style="background:#3b82f6;" title="Descargar Ticket/Nota de Venta">Descargar PDF</a>';
                    act += '<button class="sandbox-cancel-btn" onclick="sbxCancelInvoice(' + o.id + ',\'' + escapeHtml(o.order_number || '') + '\')">Cancelar</button>';
                }
                act += '<button class="btn-delete-invoice" onclick="deleteInvoiceFromMonitor(' + o.id + ',\'' + escapeHtml(o.order_number || '') + '\')" title="Borrar registro de prueba">Borrar</button>';
                act += '</div>';

                html += '<tr style="' + (cancelled ? 'opacity:0.55;' : '') + '">' +
                    '<td style="font-family:monospace;font-weight:700;color:#eab308;">' + escapeHtml(o.order_number || '') + ' <span class="badge-sandbox">TEST</span></td>' +
                    '<td>' + sale + '</td>' +
                    '<td><strong>' + escapeHtml(o.client_name || '') + '</strong><br><span style="font-size:0.75rem;color:#666677;">' + escapeHtml(o.client_email || '') + '</span></td>' +
                    '<td>' + escapeHtml(o.product_name || '-') + '<br><span style="font-size:0.75rem;color:#666677;">x' + (o.product_qty||1) + ' &mdash; ' + escapeHtml(pay) + '</span></td>' +
                    '<td style="font-weight:700;color:#eab308;">$' + Number(o.total_amount||0).toFixed(2) + '</td>' +
                    '<td style="font-size:0.72rem;">' + uuid + '</td>' +
                    '<td>' + st + '</td>' +
                    '<td>' + act + '</td>' +
                    '</tr>';
            }
            tbody.innerHTML = html;
        }


        /** Cancela una factura sandbox desde la tabla del sandbox */
        async function sbxCancelInvoice(orderId, orderNumber) {
            confirmAction(
                'Cancelar Factura Sandbox',
                `<p>¿Cancelar la factura de prueba <strong>${orderNumber}</strong>?</p>
                 <p style="color:#888899;font-size:0.85rem;">Esta acción no tiene efecto fiscal real. Solo marca el registro como cancelado en la BD sandbox.</p>`,
                '',
                async function() {
                    try {
                        const res = await fetch('api/sandbox_billing_api.php?action=cancel', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ order_id: orderId, sat_reason: '03' }),
                        });
                        const data = await res.json();
                        if (data.success) {
                            showAlert(data.message, 'success');
                            sbxLoadInvoices();
                        } else {
                            showAlert('Error: ' + (data.message || 'No se pudo cancelar'), 'error');
                        }
                    } catch (e) {
                        showAlert('Error de conexión.', 'error');
                    }
                }
            );
        }

        /** Cancela una factura sandbox desde el Monitor de Facturas */
        async function sbxCancelFromMonitor(orderId, orderNumber) {
            try {
                const res = await fetch('api/sandbox_billing_api.php?action=cancel', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order_id: orderId, sat_reason: '03' }),
                });
                const data = await res.json();
                if (data.success) {
                    showAlert('Factura sandbox cancelada (sin efecto SAT real).', 'success');
                    loadInvoices();
                } else {
                    showAlert('Error: ' + (data.message || ''), 'error');
                }
            } catch (e) {
                showAlert('Error de conexión.', 'error');
            }
        }

        /** Limpia todas las facturas sandbox */
        async function sbxClearSandbox() {
            confirmAction(
                '🗑️ Limpiar Sandbox',
                `<p>¿Eliminar <strong>todas</strong> las facturas de prueba (SBX-*)?</p>
                 <p style="color:#888899;font-size:0.85rem;">Solo se borran registros sandbox. Los datos reales no se afectan.</p>`,
                '',
                async function() {
                    try {
                        const res  = await fetch('api/sandbox_billing_api.php?action=clear', { method: 'POST' });
                        const data = await res.json();
                        if (data.success) {
                            showAlert(data.message, 'success');
                            sbxLoadInvoices();
                        } else {
                            showAlert('Error: ' + (data.message || ''), 'error');
                        }
                    } catch (e) {
                        showAlert('Error de conexión.', 'error');
                    }
                }
            );
        }

        /** Genera una factura global sandbox para el día actual */
        async function sbxGenerateGlobal() {
            showAlert('Generando Factura Global de prueba para hoy...', 'info');
            try {
                const res  = await fetch('api/sandbox_billing_api.php?action=generate_global', { method: 'POST' });
                const data = await res.json();
                if (data.success) {
                    if (data.invoice) {
                        showAlert(
                            `✅ Factura Global Sandbox generada — UUID: <strong>${data.invoice.uuid}</strong> · ${data.invoice.sales_count} ventas · $${data.invoice.total.toFixed(2)}`,
                            'success'
                        );
                    } else {
                        showAlert(data.message || 'No hay ventas sandbox de Público General hoy.', 'info');
                    }
                } else {
                    showAlert('Error: ' + (data.message || ''), 'error');
                }
            } catch (e) {
                showAlert('Error de conexión.', 'error');
            }
        }

        // Actualizar totales en tiempo real al cambiar cantidad/precio
        document.addEventListener('DOMContentLoaded', () => {
            const qtyInput   = document.getElementById('sbxProductQty');
            const priceInput = document.getElementById('sbxProductPrice');
            if (qtyInput)   qtyInput.addEventListener('input',   sbxUpdateTotals);
            if (priceInput) priceInput.addEventListener('input', sbxUpdateTotals);
            sbxUpdateTotals();
        });

        // ===== GESTIÓN DE CUENTAS DE PAGO DEL ADMINISTRADOR =====
        let paymentAccounts = [];

        function loadPaymentAccounts() {
            fetch('/api/admin_payment_config.php?action=get_payment_accounts')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        paymentAccounts = data.accounts || [];
                        renderPaymentAccounts();
                    }
                })
                .catch(err => {
                    console.error('Error cargando cuentas:', err);
                    document.getElementById('paymentAccountsList').innerHTML = '<p style="color: #ef4444;">Error al cargar cuentas</p>';
                });
        }

        function renderPaymentAccounts() {
            const container = document.getElementById('paymentAccountsList');
            if (paymentAccounts.length === 0) {
                container.innerHTML = '<p style="color: #888;">No hay cuentas configuradas. Agrega tu primera cuenta para recibir pagos.</p>';
                return;
            }

            container.innerHTML = `
                <div style="display: grid; gap: 1rem;">
                    ${paymentAccounts.map(account => `
                        <div style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 1rem; display: flex; justify-content: space-between; align-items: center;">
                            <div style="flex: 1;">
                                <div style="font-weight: 700; color: #fff; margin-bottom: 0.25rem;">
                                    ${account.account_name} ${account.is_primary ? '<span style="background: #22c55e; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; margin-left: 8px;">PRINCIPAL</span>' : ''}
                                </div>
                                <div style="font-size: 0.85rem; color: #888;">
                                    ${account.payment_gateway === 'stripe' ? '💳 Stripe' : account.payment_gateway === 'mercadopago' ? '💳 Mercado Pago' : '🏦 Cuenta Bancaria'} 
                                    ${account.bank_name ? ' - ' + account.bank_name : ''}
                                    ${account.last_4 ? ' - ****' + account.last_4 : ''}
                                </div>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                ${!account.is_primary ? `<button onclick="setPrimaryAccount(${account.id})" style="background: rgba(34,197,94,0.2); border: 1px solid #22c55e; color: #22c55e; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.85rem;">Establecer Principal</button>` : ''}
                                <button onclick="editPaymentAccount(${account.id})" style="background: rgba(255,127,0,0.2); border: 1px solid #ff7f00; color: #ff7f00; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.85rem;">Editar</button>
                                <button onclick="deletePaymentAccount(${account.id})" style="background: rgba(239,68,68,0.2); border: 1px solid #ef4444; color: #ef4444; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.85rem;">Eliminar</button>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        }

        function openPaymentAccountModal(accountId = null) {
            const modalHtml = `
                <div id="paymentAccountModal" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 1000; display: flex; align-items: center; justify-content: center;">
                    <div style="background: #121217; border: 1px solid #2a2a36; border-radius: 12px; padding: 2rem; max-width: 500px; width: 90%; max-height: 85vh; overflow-y: auto; box-sizing: border-box;">
                        <h3 style="color: #fff; margin-bottom: 1.5rem;">${accountId ? 'Editar Cuenta de Pago' : 'Agregar Nueva Cuenta de Pago'}</h3>
                        <form id="paymentAccountForm">
                            <input type="hidden" id="paymentAccountId" value="${accountId || ''}">
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; color: #fff; margin-bottom: 0.5rem; font-weight: 600;">Nombre de la Cuenta</label>
                                <input type="text" id="accountName" class="field-input" placeholder="Ej: Cuenta Principal BBVA" required>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; color: #fff; margin-bottom: 0.5rem; font-weight: 600;">Pasarela de Pago</label>
                                <select id="paymentGateway" class="field-select" onchange="togglePaymentGatewayFields()" required>
                                    <option value="">Selecciona...</option>
                                    <option value="stripe">Stripe</option>
                                    <option value="mercadopago">Mercado Pago</option>
                                    <option value="bank_account">Cuenta Bancaria Directa</option>
                                </select>
                            </div>
                            <div id="stripeFields" style="display: none; margin-bottom: 1rem;">
                                <label style="display: block; color: #fff; margin-bottom: 0.5rem; font-weight: 600;">ID de Cuenta Stripe</label>
                                <input type="text" id="stripeAccountId" class="field-input" placeholder="acct_xxxxxxxx">
                            </div>
                            <div id="mercadopagoFields" style="display: none; margin-bottom: 1rem;">
                                <label style="display: block; color: #fff; margin-bottom: 0.5rem; font-weight: 600;">ID de Cuenta Mercado Pago</label>
                                <input type="text" id="mpAccountId" class="field-input" placeholder="collector_xxxxxxxx">
                            </div>
                            <div id="bankFields" style="display: none;">
                                <div style="margin-bottom: 1rem;">
                                    <label style="display: block; color: #fff; margin-bottom: 0.5rem; font-weight: 600;">Banco</label>
                                    <input type="text" id="bankName" class="field-input" placeholder="Ej: BBVA Bancomer">
                                </div>
                                <div style="margin-bottom: 1rem;">
                                    <label style="display: block; color: #fff; margin-bottom: 0.5rem; font-weight: 600;">CLABE</label>
                                    <input type="text" id="bankClabe" class="field-input" placeholder="18 dígitos" maxlength="18">
                                </div>
                                <div style="margin-bottom: 1rem;">
                                    <label style="display: block; color: #fff; margin-bottom: 0.5rem; font-weight: 600;">Últimos 4 dígitos</label>
                                    <input type="text" id="last4" class="field-input" placeholder="****" maxlength="4">
                                </div>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; color: #fff; margin-bottom: 0.5rem; font-weight: 600;">Titular</label>
                                <input type="text" id="accountHolder" class="field-input" placeholder="Nombre del titular">
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; color: #fff; margin-bottom: 0.5rem; font-weight: 600;">RFC (opcional)</label>
                                <input type="text" id="accountRfc" class="field-input" placeholder="RFC del titular" maxlength="13">
                            </div>
                            <div style="margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.6rem; background: rgba(255,255,255,0.04); padding: 10px 14px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.08);">
                                <input type="checkbox" id="accountIsPrimary" style="width: 18px; height: 18px; accent-color: #22c55e; cursor: pointer;">
                                <label for="accountIsPrimary" style="color: #fff; font-weight: 600; cursor: pointer; margin: 0; font-size: 0.9rem;">
                                    ⭐ Establecer como Cuenta / Tarjeta Principal (Receptora de Pagos SAT)
                                </label>
                            </div>
                            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                                <button type="submit" class="btn-action" style="flex: 1;">Guardar</button>
                                <button type="button" onclick="closePaymentAccountModal()" style="flex: 1; background: #2a2a36; color: #fff; border: none; padding: 0.8rem; border-radius: 8px; cursor: pointer;">Cancelar</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            document.getElementById('paymentAccountForm').addEventListener('submit', savePaymentAccount);
            
            if (accountId) {
                const account = paymentAccounts.find(a => Number(a.id) === Number(accountId));
                if (account) {
                    document.getElementById('accountName').value = account.account_name || '';
                    document.getElementById('paymentGateway').value = account.payment_gateway || '';
                    document.getElementById('stripeAccountId').value = account.provider_account_id || '';
                    document.getElementById('mpAccountId').value = account.provider_account_id || '';
                    document.getElementById('bankName').value = account.bank_name || '';
                    document.getElementById('bankClabe').value = account.clabe || '';
                    document.getElementById('last4').value = account.last_4 || '';
                    document.getElementById('accountHolder').value = account.account_holder || '';
                    document.getElementById('accountRfc').value = account.rfc || '';
                    const isPrim = account.is_primary === true || account.is_primary === 't' || account.is_primary === 1 || Number(account.is_primary) === 1;
                    document.getElementById('accountIsPrimary').checked = Boolean(isPrim);
                    togglePaymentGatewayFields();
                }
            } else {
                if (paymentAccounts.length === 0) {
                    const chk = document.getElementById('accountIsPrimary');
                    if (chk) chk.checked = true;
                }
            }
        }

        function editPaymentAccount(accountId) {
            openPaymentAccountModal(accountId);
        }

        function closePaymentAccountModal() {
            const modal = document.getElementById('paymentAccountModal');
            if (modal) modal.remove();
        }

        function togglePaymentGatewayFields() {
            const gateway = document.getElementById('paymentGateway').value;
            document.getElementById('stripeFields').style.display = gateway === 'stripe' ? 'block' : 'none';
            document.getElementById('mercadopagoFields').style.display = gateway === 'mercadopago' ? 'block' : 'none';
            document.getElementById('bankFields').style.display = gateway === 'bank_account' ? 'block' : 'none';
        }

        function savePaymentAccount(e) {
            e.preventDefault();
            const accountId = document.getElementById('paymentAccountId').value;
            const action = accountId ? 'update_payment_account' : 'add_payment_account';
            const url = `/api/admin_payment_config.php?action=${action}${accountId ? '&account_id=' + accountId : ''}`;
            
            const isPrimary = document.getElementById('accountIsPrimary') ? document.getElementById('accountIsPrimary').checked : false;

            const accountData = {
                account_name: document.getElementById('accountName').value,
                payment_gateway: document.getElementById('paymentGateway').value,
                provider_account_id: document.getElementById('stripeAccountId').value || document.getElementById('mpAccountId').value,
                bank_name: document.getElementById('bankName').value,
                clabe: document.getElementById('bankClabe').value,
                last_4: document.getElementById('last4').value,
                account_holder: document.getElementById('accountHolder').value,
                rfc: document.getElementById('accountRfc').value,
                is_primary: isPrimary
            };

            fetch(url, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.csrfToken
                },
                body: JSON.stringify({ ...accountData, csrf_token: window.csrfToken })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closePaymentAccountModal();
                    loadPaymentAccounts();
                    showAlert('Cuenta guardada correctamente', 'success');
                } else {
                    showAlert('Error: ' + data.message, 'error');
                }
            })
            .catch(err => {
                showAlert('Error de conexión', 'error');
            });
        }

        function setPrimaryAccount(accountId) {
            fetch(`/api/admin_payment_config.php?action=set_primary_account&account_id=${accountId}`, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.csrfToken
                },
                body: JSON.stringify({ csrf_token: window.csrfToken })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadPaymentAccounts();
                    showAlert('Cuenta principal establecida', 'success');
                } else {
                    showAlert('Error: ' + data.message, 'error');
                }
            });
        }

        function deletePaymentAccount(accountId) {
            if (confirm('¿Estás seguro de eliminar esta cuenta?')) {
                fetch(`/api/admin_payment_config.php?action=delete_payment_account&account_id=${accountId}`, {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': window.csrfToken
                    },
                    body: JSON.stringify({ csrf_token: window.csrfToken })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadPaymentAccounts();
                        showAlert('Cuenta eliminada', 'success');
                    } else {
                        showAlert('Error: ' + data.message, 'error');
                    }
                });
            }
        }
    </script>
</body>
</html>
