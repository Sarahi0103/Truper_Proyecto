<?php
require_once '../config/config.php';
require_admin();

$user_name = htmlspecialchars($_SESSION['name'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
$user_role = htmlspecialchars($_SESSION['role'] ?? 'admin', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Tickets sin Registro - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css?v=4.2">
    <link rel="stylesheet" href="css/theme.css?v=4.1">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/responsive-complete.css?v=5.0">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap');
        
        body {
            font-family: 'Outfit', sans-serif;
        }

        .guest-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .guest-hero {
            background: linear-gradient(135deg, rgba(255, 102, 0, 0.15) 0%, rgba(30, 30, 30, 0.8) 100%);
            border: 1px solid rgba(255, 102, 0, 0.2);
            border-radius: 12px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 102, 0, 0.2);
            color: var(--color-naranja, #ff6600);
            padding: 0.35rem 0.85rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1rem;
        }

        .search-filter-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }

        .status-tabs {
            display: flex;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            padding: 0.3rem;
            gap: 0.25rem;
        }

        .status-tab {
            background: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.6);
            padding: 0.6rem 1.25rem;
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.25s ease;
        }

        .status-tab:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.03);
        }

        .status-tab.active {
            background: var(--color-naranja, #ff6600);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(255, 102, 0, 0.3);
        }

        .search-box {
            position: relative;
            flex: 1;
            max-width: 450px;
            min-width: 280px;
        }

        .search-box input {
            width: 100%;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #ffffff;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--color-naranja, #ff6600);
            box-shadow: 0 0 10px rgba(255, 102, 0, 0.15);
            background: rgba(255, 255, 255, 0.07);
        }

        .search-icon {
            position: absolute;
            left: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.35);
            pointer-events: none;
        }

        .table-wrap {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 10px;
            overflow-x: auto;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.2);
        }

        .guest-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .guest-table th {
            background: rgba(255, 255, 255, 0.04);
            color: var(--color-naranja, #ff6600);
            padding: 1.1rem 1.25rem;
            font-weight: 700;
            font-size: 0.88rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .guest-table td {
            padding: 1.1rem 1.25rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.92rem;
            vertical-align: middle;
        }

        .guest-table tr:hover td {
            background: rgba(255, 255, 255, 0.015);
        }

        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.3rem 0.8rem;
            border-radius: 30px;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-pending {
            background: rgba(255, 165, 0, 0.15);
            color: #ff9f43;
            border: 1px solid rgba(255, 165, 0, 0.25);
        }

        .badge-delivered {
            background: rgba(40, 167, 69, 0.15);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.25);
        }

        .badge-expired {
            background: rgba(220, 53, 69, 0.15);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.25);
        }

        .badge-cancelled {
            background: rgba(108, 117, 125, 0.15);
            color: #8f9aa3;
            border: 1px solid rgba(108, 117, 125, 0.25);
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            padding: 0.45rem 0.85rem;
            font-size: 0.8rem;
            font-weight: 600;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #ffffff;
        }

        .btn-deliver {
            background: #28a745;
        }
        .btn-deliver:hover {
            background: #218838;
            box-shadow: 0 4px 10px rgba(40, 167, 69, 0.2);
        }

        .btn-reactivate {
            background: #17a2b8;
        }
        .btn-reactivate:hover {
            background: #138496;
            box-shadow: 0 4px 10px rgba(23, 162, 184, 0.2);
        }

        .btn-cancel {
            background: #dc3545;
        }
        .btn-cancel:hover {
            background: #c82333;
            box-shadow: 0 4px 10px rgba(220, 53, 69, 0.2);
        }

        .btn-detail {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .btn-detail:hover {
            background: rgba(255, 255, 255, 0.12);
        }

        /* Modal Styles */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(8px);
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: #1e1e1e;
            border: 1px solid rgba(255, 102, 0, 0.3);
            border-radius: 16px;
            width: 100%;
            max-width: 850px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.6), 0 0 30px rgba(255, 102, 0, 0.15);
            animation: modalFade 0.35s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes modalFade {
            from { opacity: 0; transform: scale(0.97) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.01);
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--color-naranja, #ff6600);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .modal-close {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: rgba(255, 255, 255, 0.6);
            font-size: 1.3rem;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .modal-close:hover {
            background: rgba(255, 102, 0, 0.15);
            border-color: rgba(255, 102, 0, 0.3);
            color: #ffffff;
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 2rem;
        }

        .info-card-section {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 768px) {
            .info-card-section {
                grid-template-columns: 1fr;
            }
        }

        .info-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
        }

        .info-card-title {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--color-naranja, #ff6600);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 0;
            margin-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            padding-bottom: 0.5rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem 1.25rem;
        }

        .info-grid.full-width {
            grid-template-columns: 1fr;
        }

        .info-block {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .info-lbl {
            font-size: 0.72rem;
            color: rgba(255, 255, 255, 0.4);
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
        }

        .info-val {
            font-size: 0.95rem;
            color: #ffffff;
            font-weight: 500;
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
        }

        .info-val .badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.2rem 0.65rem;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-pill-success {
            background: rgba(40, 167, 69, 0.15);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.25);
        }

        .badge-pill-pending {
            background: rgba(255, 102, 0, 0.15);
            color: #ffa809;
            border: 1px solid rgba(255, 102, 0, 0.25);
        }

        .items-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 0.75rem;
            border-left: 3px solid var(--color-naranja, #ff6600);
            padding-left: 0.5rem;
        }

        .tbl-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.5rem;
            background: rgba(255, 255, 255, 0.01);
            border-radius: 8px;
            overflow: hidden;
        }

        .tbl-items th {
            background: rgba(255, 255, 255, 0.03);
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            padding: 0.85rem 1rem;
            text-align: left;
        }

        .tbl-items td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.9rem;
        }

        .log-section {
            background: rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.04);
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
        }

        .log-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            max-height: 180px;
            overflow-y: auto;
            margin-top: 0.75rem;
        }

        .log-item {
            background: rgba(255, 255, 255, 0.02);
            padding: 0.65rem 0.85rem;
            border-radius: 8px;
            font-size: 0.85rem;
            border-left: 3px solid var(--color-naranja, #ff6600);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .log-txt {
            color: rgba(255, 255, 255, 0.8);
            padding-right: 1rem;
        }

        .log-time {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.4);
            white-space: nowrap;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            padding: 1.5rem 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.01);
        }

        .btn-modal {
            padding: 0.65rem 1.5rem;
            font-size: 0.9rem;
            font-weight: 700;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }

        .btn-modal-close {
            background: rgba(255, 255, 255, 0.06);
            color: #ffffff;
        }
        .btn-modal-close:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .btn-modal-confirm {
            background: var(--color-naranja, #ff6600);
            color: #ffffff;
        }
        .btn-modal-confirm:hover {
            background: #e05500;
            box-shadow: 0 4px 12px rgba(255, 102, 0, 0.2);
        }

        .btn-modal-confirm:disabled {
            background: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.3);
            cursor: not-allowed;
            box-shadow: none;
        }

        .empty-state {
            padding: 3.5rem;
            text-align: center;
            color: rgba(255, 255, 255, 0.45);
            font-size: 1.1rem;
        }

        .empty-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    <header>
        <div class="header-content">
            <a href="dashboard.php" class="logo"><img src="img/logo_truper.1.1.png" alt="Truper" style="height: 40px; width: auto; object-fit: contain;"></a>
            <button class="hamburger-btn" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <nav class="nav-menu">
                <a href="index.php">Catálogo</a>
                <a href="marketplace_ce.php">Marketplace CE</a>
                <?php if ($user_role === 'admin' || $user_role === 'employee'): ?>
                    <a href="guest_tickets.php" class="active">Tickets sin Registro</a>
                <?php endif; ?>
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
                        <a href="cashier.php">Caja</a>
                        <a href="admin_supply.php?nocache=true">Abastecimiento</a>
                        <a href="tickets.php">Tickets</a>
                        <a href="tasks.php">Tareas</a>
                        <a href="gastos.php">Gastos</a>
                        <?php if ($user_role === 'admin'): ?>
                            <a href="analytics.php">Estadísticas</a>
                        <?php endif; ?>
                    </div>
                </div>
            </nav>
        </div>
        <div class="user-menu">
            <div class="user-info">
                <div class="user-name"><?php echo $user_name; ?></div>
                <div class="user-role"><?php echo $user_role === 'employee' ? 'PERSONAL' : 'ADMIN'; ?></div>
            </div>
            <button class="btn-logout" onclick="logout()">Cerrar Sesión</button>
        </div>
    </header>

    <main>
        <div class="guest-container">
            <!-- Back Button -->
            <div class="back-header" style="margin-bottom: 1.5rem;">
                <button onclick="history.back()" class="btn-back">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Regresar
                </button>
            </div>

            <!-- Page Hero -->
            <div class="guest-hero">
                <span class="hero-badge">🎫 MÓDULO INVITADOS</span>
                <h1 style="margin: 0 0 0.5rem; font-size: 2.2rem; font-weight: 800; color: #ffffff;">Gestión de Tickets sin Registro</h1>
                <p style="margin: 0; color: rgba(255, 255, 255, 0.7); font-size: 1.05rem;">
                    Búsqueda, visualización y control de tickets y entregas físicas correspondientes a compras realizadas por usuarios invitados. Vigencia de 30 días con opción de reactivación.
                </p>
            </div>

            <!-- Control Bar -->
            <div class="search-filter-bar">
                <div class="status-tabs">
                    <button class="status-tab active" onclick="setStatusFilter('all', this)">Todos</button>
                    <button class="status-tab" onclick="setStatusFilter('pending', this)">Pendientes</button>
                    <button class="status-tab" onclick="setStatusFilter('picked_up', this)">Entregados</button>
                    <button class="status-tab" onclick="setStatusFilter('expired', this)">Expirados</button>
                </div>
                <div class="search-box">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="searchInput" placeholder="Buscar por folio, nombre, email o ticket..." oninput="handleSearch(this.value)">
                </div>
            </div>

            <!-- Table -->
            <div class="table-wrap">
                <table class="guest-table">
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Origen</th>
                            <th>Invitado</th>
                            <th>Contacto</th>
                            <th>Nº Orden</th>
                            <th>Total</th>
                            <th>Expiración</th>
                            <th>Estado</th>
                            <th style="text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="ticketsTableBody">
                        <tr>
                            <td colspan="9" class="empty-state">Cargando tickets...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- DETAILS MODAL -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="mdFolio">Ticket</h3>
                <button class="modal-close" onclick="closeModal()">×</button>
            </div>
            <div class="modal-body">
                <!-- Info Section with grouped Cards -->
                <div class="info-card-section">
                    <!-- Client Card -->
                    <div class="info-card">
                        <h4 class="info-card-title">👤 Datos del Invitado</h4>
                        <div class="info-grid full-width">
                            <div class="info-block">
                                <span class="info-lbl">Nombre del Invitado</span>
                                <span class="info-val" id="mdName">John Doe</span>
                            </div>
                            <div class="info-block" style="margin-top: 0.5rem;">
                                <span class="info-lbl">Correo Electrónico</span>
                                <span class="info-val" id="mdEmail" style="word-break: break-all;">john@example.com</span>
                            </div>
                            <div class="info-block" style="margin-top: 0.5rem;">
                                <span class="info-lbl">Teléfono</span>
                                <span class="info-val" id="mdPhone">5512345678</span>
                            </div>
                        </div>
                    </div>

                    <!-- Ticket Details Card -->
                    <div class="info-card">
                        <h4 class="info-card-title">📄 Detalles del Ticket</h4>
                        <div class="info-grid">
                            <div class="info-block">
                                <span class="info-lbl">Fecha Emisión</span>
                                <span class="info-val" id="mdDate">2026-06-20</span>
                            </div>
                            <div class="info-block">
                                <span class="info-lbl">N° de Orden</span>
                                <span class="info-val" id="mdOrderNumber" style="font-family: monospace; font-weight: 700; color: #ffa809;">—</span>
                            </div>
                            <div class="info-block">
                                <span class="info-lbl">Total Ticket</span>
                                <span class="info-val" id="mdTotal" style="color: var(--color-naranja, #ff6600); font-weight: 700;">$0.00</span>
                            </div>
                            <div class="info-block">
                                <span class="info-lbl">Estado de Pago</span>
                                <span class="info-val" id="mdPayment">Pagado</span>
                            </div>
                            <div class="info-block">
                                <span class="info-lbl">Origen</span>
                                <span class="info-val" id="mdSource">Stock</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notes Block -->
                <div class="info-card" style="margin-bottom: 2rem;">
                    <h4 class="info-card-title" style="margin-bottom: 0.75rem;">📝 Notas del Pedido / Dirección de Entrega</h4>
                    <div class="info-val" id="mdNotes" style="font-style: italic; color: rgba(255, 255, 255, 0.85); line-height: 1.4;">N/A</div>
                </div>

                <!-- Products list -->
                <h4 class="items-title">Productos Adquiridos</h4>
                <div class="table-wrap" style="margin-bottom: 2rem; border-color: rgba(255, 255, 255, 0.08);">
                    <table class="tbl-items" style="margin-bottom: 0;">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th style="width: 100px; text-align: center;">Cantidad</th>
                                <th style="width: 130px; text-align: right;">P. Unitario</th>
                                <th style="width: 140px; text-align: right;">Total</th>
                            </tr>
                        </thead>
                        <tbody id="mdProductsBody">
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 1.5rem; color: rgba(255,255,255,0.45);">Cargando productos...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Audit Log -->
                <div class="log-section" style="border-radius: 12px; background: rgba(0, 0, 0, 0.25); border-color: rgba(255, 255, 255, 0.06); padding: 1.5rem;">
                    <h4 style="margin: 0 0 1rem 0; color: #ffffff; font-size: 1.05rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                        📋 Historial de Eventos del Ticket
                    </h4>
                    <div class="log-list" id="mdLogs">
                        <div class="log-item"><span class="log-txt">No hay eventos registrados</span></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="background: rgba(255, 255, 255, 0.01); padding: 1.25rem 2rem;">
                <button class="btn-modal btn-modal-close" onclick="closeModal()">Cerrar</button>
                <button class="btn-modal btn-modal-close" id="mdPrintBtn" onclick="printModalTicket()">🖨️ Imprimir</button>
                <button class="btn-modal btn-modal-confirm" id="mdConfirmBtn" onclick="confirmDeliveryFromModal()">Confirmar Entrega</button>
            </div>
        </div>
    </div>

    <script src="js/main.js?v=2.6"></script>
    <script src="js/modals.js"></script>
    <script>
        window.csrfToken = '<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>';
        
        let currentStatusFilter = 'all';
        let currentSearchQuery = '';
        let searchTimeout = null;
        let loadedTickets = [];
        let selectedTicket = null;

        function logout() {
            confirmLogout('api/auth.php?action=logout');
        }

        async function fetchTickets() {
            try {
                const response = await fetch(`api/guest_tickets_api.php?action=list&status=${currentStatusFilter}&query=${encodeURIComponent(currentSearchQuery)}`);
                const result = await response.json();
                
                if (result.success) {
                    loadedTickets = result.tickets;
                    renderTicketsTable(result.tickets);
                } else {
                    console.error('Error in fetch:', result.message);
                }
            } catch (e) {
                console.error('Connection error:', e);
            }
        }

        function renderTicketsTable(tickets) {
            const tbody = document.getElementById('ticketsTableBody');
            if (tickets.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="9" class="empty-state">
                            <span class="empty-icon">🎫</span>
                            No se encontraron tickets de invitados
                        </td>
                    </tr>
                `;
                return;
            }

            let html = '';
            tickets.forEach(tk => {
                const guestName = `${tk.first_name || ''} ${tk.last_name || ''}`.trim() || 'Invitado';
                const statusBadge = getStatusBadge(tk.pickup_status);
                
                // Origen display
                const source = tk.description || 'Stock';
                const sourceBadge = source === 'Marketplace'
                    ? '<span class="badge-status" style="background:rgba(23, 162, 184, 0.15); color:#17a2b8; border:1px solid rgba(23,162,184,0.25);">🛍️ Marketplace</span>'
                    : '<span class="badge-status" style="background:rgba(40, 167, 69, 0.15); color:#28a745; border:1px solid rgba(40,167,69,0.25);">📦 Stock</span>';

                // Expiration display
                const expDateStr = tk.expiration_date ? new Date(tk.expiration_date).toLocaleDateString('es-MX') : 'N/A';
                
                // Format amount
                const amount = parseFloat(tk.total_amount || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                // Acciones buttons
                let actionsHtml = '';
                if (tk.pickup_status === 'pending') {
                    actionsHtml += `
                        <button class="btn-action btn-deliver" onclick="deliverTicket('${tk.folio}')" title="Confirmar Entrega">✓ Entregar</button>
                    `;
                } else if (tk.pickup_status === 'expired') {
                    actionsHtml += `
                        <button class="btn-action btn-reactivate" onclick="reactivateTicket(${tk.id})" title="Reactivar ticket por 30 días">🔄 Reactivar</button>
                    `;
                }
                
                // Delete button always visible for all status values
                actionsHtml += `
                    <button class="btn-action btn-cancel" onclick="cancelTicket('${tk.folio}')" title="Eliminar Ticket">🗑️ Eliminar</button>
                    <button class="btn-action btn-detail" onclick="openDetails('${tk.folio}')" title="Ver Detalles">👁 Detalle</button>
                `;

                html += `
                    <tr id="row-${tk.folio}">
                        <td style="font-family: monospace; font-weight: 700; color: var(--color-naranja, #ff6600);">${tk.folio}</td>
                        <td>${sourceBadge}</td>
                        <td style="font-weight: 600;">${guestName}</td>
                        <td style="font-size: 0.85rem; color: rgba(255,255,255,0.65);">
                            <div>${tk.email || ''}</div>
                            <div>${tk.phone || ''}</div>
                        </td>
                        <td>${tk.order_number || '—'}</td>
                        <td style="font-weight: 700; color: var(--color-naranja, #ff6600);">$${amount}</td>
                        <td style="font-size: 0.85rem;">${expDateStr}</td>
                        <td>${statusBadge}</td>
                        <td style="text-align: center; white-space: nowrap;">
                            <div style="display: inline-flex; gap: 0.4rem;">
                                ${actionsHtml}
                            </div>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
        }

        function getStatusBadge(status) {
            switch(status) {
                case 'pending': return '<span class="badge-status badge-pending">⏳ Pendiente</span>';
                case 'picked_up': return '<span class="badge-status badge-delivered">✓ Entregado</span>';
                case 'expired': return '<span class="badge-status badge-expired">⌛ Expirado</span>';
                case 'cancelled': return '<span class="badge-status badge-cancelled">✗ Cancelado</span>';
                default: return `<span class="badge-status">${status}</span>`;
            }
        }

        function setStatusFilter(status, btn) {
            document.querySelectorAll('.status-tab').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentStatusFilter = status;
            fetchTickets();
        }

        function handleSearch(val) {
            clearTimeout(searchTimeout);
            currentSearchQuery = val.trim();
            searchTimeout = setTimeout(() => {
                fetchTickets();
            }, 300);
        }

        async function deliverTicket(folio) {
            if (!confirm(`¿Estás seguro de que deseas confirmar la entrega física del ticket ${folio}?`)) {
                return;
            }

            try {
                const response = await fetch('api/guest_tickets_api.php?action=validate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.csrfToken },
                    body: JSON.stringify({ folio: folio, notes: 'Recogido físicamente en tienda por cliente invitado' })
                });
                const result = await response.json();

                if (result.success) {
                    showAlert('Ticket validado y entregado exitosamente', 'success');
                    fetchTickets();
                } else {
                    showAlert(result.message || 'Error al validar ticket', 'error');
                }
            } catch (e) {
                console.error(e);
                showAlert('Error de conexión', 'error');
            }
        }

        async function reactivateTicket(ticketId) {
            if (!confirm(`¿Deseas reactivar este ticket expirado por 30 días adicionales?`)) {
                return;
            }

            try {
                const response = await fetch('api/guest_tickets_api.php?action=reactivate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.csrfToken },
                    body: JSON.stringify({ ticket_id: ticketId, notes: 'Ticket reactivado por administración' })
                });
                const result = await response.json();

                if (result.success) {
                    showAlert('Ticket reactivado exitosamente por 30 días más', 'success');
                    fetchTickets();
                } else {
                    showAlert(result.message || 'Error al reactivar ticket', 'error');
                }
            } catch (e) {
                console.error(e);
                showAlert('Error de conexión', 'error');
            }
        }

        async function cancelTicket(folio) {
            const reason = prompt('Ingrese el motivo de cancelación/eliminación del ticket:', 'Cancelado por administración');
            if (reason === null) return; // cancel clicked

            try {
                const response = await fetch('api/guest_tickets_api.php?action=cancel', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.csrfToken },
                    body: JSON.stringify({ folio: folio, reason: reason })
                });
                const result = await response.json();

                if (result.success) {
                    showAlert('Ticket cancelado correctamente', 'success');
                    fetchTickets();
                } else {
                    showAlert(result.message || 'Error al cancelar ticket', 'error');
                }
            } catch (e) {
                console.error(e);
                showAlert('Error de conexión', 'error');
            }
        }

        async function openDetails(folio) {
            const modal = document.getElementById('detailsModal');
            
            // Clean content
            document.getElementById('mdFolio').textContent = `Cargando Folio: ${folio}...`;
            document.getElementById('mdName').textContent = '—';
            document.getElementById('mdEmail').textContent = '—';
            document.getElementById('mdPhone').textContent = '—';
            document.getElementById('mdDate').textContent = '—';
            document.getElementById('mdOrderNumber').textContent = '—';
            document.getElementById('mdTotal').textContent = '$0.00';
            document.getElementById('mdPayment').textContent = '—';
            document.getElementById('mdSource').textContent = '—';
            document.getElementById('mdNotes').textContent = '—';
            document.getElementById('mdProductsBody').innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 1.5rem; color: rgba(255,255,255,0.45);">Cargando...</td></tr>';
            document.getElementById('mdLogs').innerHTML = '<div style="padding: 0.5rem; text-align: center; color: rgba(255,255,255,0.4);">Cargando logs...</div>';
            
            modal.classList.add('active');

            try {
                const response = await fetch(`api/guest_tickets_api.php?action=details&folio=${encodeURIComponent(folio)}`);
                const result = await response.json();

                if (result.success) {
                    selectedTicket = result.ticket;
                    
                    document.getElementById('mdFolio').textContent = `Folio: ${selectedTicket.folio}`;
                    document.getElementById('mdName').textContent = selectedTicket.customer_name || 'Invitado';
                    document.getElementById('mdEmail').textContent = selectedTicket.email || 'N/A';
                    document.getElementById('mdPhone').textContent = selectedTicket.phone || 'N/A';
                    document.getElementById('mdDate').textContent = new Date(selectedTicket.issued_date).toLocaleString('es-MX');
                    document.getElementById('mdOrderNumber').textContent = selectedTicket.order_number || '—';
                    document.getElementById('mdTotal').textContent = '$' + parseFloat(selectedTicket.total_amount || 0).toFixed(2);
                    
                    const isPaid = selectedTicket.payment_status === 'completed';
                    document.getElementById('mdPayment').innerHTML = isPaid 
                        ? '<span class="badge-pill badge-pill-success">✓ Pagado</span>' 
                        : '<span class="badge-pill badge-pill-pending">⏳ Pendiente</span>';
                        
                    document.getElementById('mdSource').textContent = selectedTicket.description || 'Stock';
                    
                    let extraNotes = selectedTicket.notes || '';
                    if (selectedTicket.pickup_notes) {
                        extraNotes += `\nNotas de entrega: ${selectedTicket.pickup_notes}`;
                    }
                    document.getElementById('mdNotes').textContent = extraNotes || 'Sin notas';

                    // Products rows
                    let pRows = '';
                    if (selectedTicket.items && selectedTicket.items.length > 0) {
                        selectedTicket.items.forEach(item => {
                            const uPrice = parseFloat(item.unit_price || 0).toFixed(2);
                            const total = parseFloat(item.total || 0).toFixed(2);
                            pRows += `
                                <tr>
                                    <td>${item.product_name || 'Producto'}</td>
                                    <td style="text-align: center; font-weight: 700;">${item.quantity || 1}</td>
                                    <td style="text-align: right;">$${uPrice}</td>
                                    <td style="text-align: right; font-weight: 700; color: var(--color-naranja, #ff6600);">$${total}</td>
                                </tr>
                            `;
                        });
                    } else {
                        pRows = '<tr><td colspan="4" style="text-align: center; padding: 1.5rem; color: rgba(255,255,255,0.45);">No hay artículos en este ticket</td></tr>';
                    }
                    document.getElementById('mdProductsBody').innerHTML = pRows;

                    // Logs list
                    let logHtml = '';
                    if (result.logs && result.logs.length > 0) {
                        result.logs.forEach(log => {
                            const actionLabels = {
                                'attempt': '⚠️ Intento de entrega',
                                'validated': '✓ Entrega validada',
                                'reactivated': '🔄 Ticket reactivado',
                                'deleted': '✗ Ticket cancelado'
                            };
                            const actionText = actionLabels[log.action] || log.action;
                            const noteStr = log.notes ? ` (${log.notes})` : '';
                            const logDate = new Date(log.created_at).toLocaleString('es-MX');
                            logHtml += `
                                <div class="log-item">
                                    <span class="log-txt"><strong>${actionText}</strong>${noteStr} por ${log.admin_name || 'Sistema'}</span>
                                    <span class="log-time">${logDate}</span>
                                </div>
                            `;
                        });
                    } else {
                        logHtml = '<div style="padding: 0.5rem; text-align: center; color: rgba(255,255,255,0.4);">No hay historial de visitas</div>';
                    }
                    document.getElementById('mdLogs').innerHTML = logHtml;

                    // Deliver button state
                    const confirmBtn = document.getElementById('mdConfirmBtn');
                    confirmBtn.disabled = !selectedTicket.eligibility.eligible;
                    if (!selectedTicket.eligibility.eligible) {
                        confirmBtn.textContent = selectedTicket.eligibility.reason || 'No entregable';
                    } else {
                        confirmBtn.textContent = isPaid ? 'Confirmar Entrega' : 'Autorizar Pago y Entregar';
                    }

                } else {
                    document.getElementById('mdFolio').textContent = 'Error al cargar detalles';
                    showAlert('No se pudo cargar detalles del ticket', 'error');
                }
            } catch (e) {
                console.error(e);
                document.getElementById('mdFolio').textContent = 'Error de conexión';
            }
        }

        function closeModal() {
            document.getElementById('detailsModal').classList.remove('active');
            selectedTicket = null;
        }

        async function confirmDeliveryFromModal() {
            if (!selectedTicket) return;
            const notes = prompt("Notas de entrega (opcional):", "Entregado en sucursal.");
            if (notes === null) return;

            try {
                const response = await fetch('api/guest_tickets_api.php?action=validate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.csrfToken },
                    body: JSON.stringify({ folio: selectedTicket.folio, notes: notes })
                });
                const result = await response.json();

                if (result.success) {
                    showAlert('Ticket validado y entregado exitosamente', 'success');
                    closeModal();
                    fetchTickets();
                } else {
                    showAlert(result.message || 'Error al entregar ticket', 'error');
                }
            } catch (e) {
                console.error(e);
                showAlert('Error de conexión', 'error');
            }
        }

        function printModalTicket() {
            if (!selectedTicket) return;
            
            let printWindow = window.open('', '_blank', 'width=800,height=600');
            let itemsHtml = '';
            selectedTicket.items.forEach(item => {
                itemsHtml += `
                    <tr>
                        <td style="padding: 6px; border-bottom: 1px solid #ddd;">${item.product_name}</td>
                        <td style="padding: 6px; border-bottom: 1px solid #ddd; text-align: center;">${item.quantity}</td>
                        <td style="padding: 6px; border-bottom: 1px solid #ddd; text-align: right;">$${parseFloat(item.unit_price).toFixed(2)}</td>
                        <td style="padding: 6px; border-bottom: 1px solid #ddd; text-align: right;">$${parseFloat(item.total).toFixed(2)}</td>
                    </tr>
                `;
            });

            const content = `
                <html>
                <head>
                    <title>Ticket ${selectedTicket.folio}</title>
                    <style>
                        body { font-family: 'Helvetica', sans-serif; color: #333; margin: 20px; }
                        .ticket-box { max-width: 600px; margin: 0 auto; border: 1px solid #ccc; padding: 20px; border-radius: 8px; }
                        .header { text-align: center; margin-bottom: 20px; }
                        .logo { height: 40px; margin-bottom: 10px; }
                        .title { font-size: 20px; font-weight: bold; margin: 0 0 5px; color: #ff6600; }
                        .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
                        .info-table td { padding: 5px 0; }
                        .products-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                        .products-table th { background: #f5f5f5; padding: 8px; border-bottom: 2px solid #ddd; font-weight: bold; font-size: 13px; text-transform: uppercase; }
                        .total-row { font-size: 16px; font-weight: bold; color: #ff6600; }
                        .footer { text-align: center; font-size: 11px; color: #777; margin-top: 30px; border-top: 1px dashed #ccc; padding-top: 15px; }
                    </style>
                </head>
                <body onload="window.print(); window.close();">
                    <div class="ticket-box">
                        <div class="header">
                            <img src="img/logo_truper.1.1.png" class="logo"><br>
                            <span class="title">COMPROBANTE DE COMPRA (INVITADO)</span><br>
                            <strong>Folio: ${selectedTicket.folio}</strong>
                        </div>
                        <table class="info-table">
                            <tr>
                                <td><strong>Cliente Invitado:</strong> ${selectedTicket.customer_name || 'Invitado'}</td>
                                <td style="text-align: right;"><strong>Fecha:</strong> ${new Date(selectedTicket.issued_date).toLocaleDateString('es-MX')}</td>
                            </tr>
                            <tr>
                                <td><strong>Email:</strong> ${selectedTicket.email || 'N/A'}</td>
                                <td style="text-align: right;"><strong>Teléfono:</strong> ${selectedTicket.phone || 'N/A'}</td>
                            </tr>
                            <tr>
                                <td><strong>Nº de Orden:</strong> ${selectedTicket.order_number || '—'}</td>
                                <td style="text-align: right;"><strong>Pago:</strong> ${selectedTicket.payment_status === 'completed' ? 'PAGADO' : 'PENDIENTE'}</td>
                            </tr>
                        </table>
                        <h4 style="margin: 15px 0 5px; border-bottom: 2px solid #ff6600; padding-bottom: 5px;">Detalle de Productos</h4>
                        <table class="products-table">
                            <thead>
                                <tr>
                                    <th style="text-align: left;">Producto</th>
                                    <th>Cant</th>
                                    <th style="text-align: right;">Unitario</th>
                                    <th style="text-align: right;">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${itemsHtml}
                                <tr class="total-row">
                                    <td colspan="3" style="text-align: right; padding-top: 15px;">TOTAL:</td>
                                    <td style="text-align: right; padding-top: 15px;">$${parseFloat(selectedTicket.total_amount).toFixed(2)}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="footer">
                            <p>¡Gracias por tu compra en Truper Platform!</p>
                            <p>Este comprobante cuenta con vigencia de 30 días naturales a partir de la fecha de emisión.</p>
                        </div>
                    </div>
                </body>
                </html>
            `;

            printWindow.document.write(content);
            printWindow.document.close();
        }

        document.addEventListener('DOMContentLoaded', () => {
            fetchTickets();
        });
    </script>
</body>
</html>
