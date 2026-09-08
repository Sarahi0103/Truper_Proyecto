<?php
/**
 * Panel de Administración de Pedidos en Línea
 * Gestión integral de pedidos online vs locales, trazabilidad y guías
 * Ferretería FOX / Truper Platform
 */

require_once '../config/config.php';
require_login();

$userRole = $_SESSION['role'] ?? '';
if (!in_array($userRole, ['admin', 'employee'])) {
    header('Location: index.php');
    exit;
}

$is_admin = $userRole === 'admin';
$is_staff = $is_admin || $userRole === 'employee';
$user_name = htmlspecialchars($_SESSION['name'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Admin Pedidos en Línea - Ferretería FOX</title>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <link rel="stylesheet" href="css/styles.css?v=4.2">
    <link rel="stylesheet" href="css/theme.css?v=4.1">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/responsive-complete.css?v=5.0">
    <link rel="stylesheet" href="css/modals.css">
    <style>
        body {
            background: #09090d;
            color: #f1f5f9;
            font-family: var(--theme-font, 'Outfit', 'Inter', sans-serif);
            margin: 0;
            padding: 0;
        }

        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1.5rem 1.25rem 4rem;
        }

        /* ── Back Header ── */
        .back-header {
            margin-bottom: 1.5rem;
        }

        /* ── Hero Header ── */
        .admin-hero {
            background: linear-gradient(135deg, rgba(20,20,26,0.95), rgba(12,12,16,0.98));
            border: 1px solid rgba(255, 127, 0, 0.25);
            border-radius: 16px;
            padding: 1.75rem 2rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.25rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
            position: relative;
        }

        .admin-title-group h1 {
            font-size: 1.9rem;
            font-weight: 800;
            margin: 0.4rem 0 0.2rem;
            background: linear-gradient(90deg, #ffffff, #ff9f43);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.02em;
        }

        .admin-title-group p {
            color: #94a3b8;
            font-size: 0.95rem;
            margin: 0;
        }

        .module-badge-online {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(59, 130, 246, 0.15);
            border: 1px solid rgba(59, 130, 246, 0.35);
            color: #60a5fa;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        /* ── KPI Stats Grid ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(20, 20, 26, 0.85);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px;
            padding: 1.35rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.2rem;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
            transition: transform 0.2s ease, border-color 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #ff7f00, transparent);
            opacity: 0.8;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            border-color: rgba(255, 127, 0, 0.4);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background: rgba(255, 127, 0, 0.12);
            border: 1px solid rgba(255, 127, 0, 0.25);
            flex-shrink: 0;
        }

        .stat-content {
            flex: 1;
        }

        .stat-label {
            color: #94a3b8;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 0.25rem;
        }

        .stat-value {
            font-size: 1.85rem;
            font-weight: 800;
            color: #ffffff;
            line-height: 1.1;
        }

        /* ── Tabs Navigation ── */
        .tabs-header {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: 0.6rem;
            overflow-x: auto;
        }

        .tab-btn {
            background: transparent;
            border: 1px solid transparent;
            color: #94a3b8;
            padding: 0.65rem 1.25rem;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.88rem;
            border-radius: 8px;
            transition: all 0.2s;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .tab-btn:hover {
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #ff7f00, #ff6600);
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(255, 127, 0, 0.35);
        }

        /* ── Filter Bar ── */
        .filter-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            align-items: center;
            background: rgba(18, 18, 24, 0.7);
            padding: 1rem 1.25rem;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.06);
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
            flex: 1;
            min-width: 180px;
        }

        .filter-group label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .filter-bar input, .filter-bar select {
            background: #121218;
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #fff;
            padding: 0.7rem 0.9rem;
            border-radius: 8px;
            font-size: 0.9rem;
            outline: none;
            transition: all 0.2s;
        }

        .filter-bar input:focus, .filter-bar select:focus {
            border-color: #ff7f00;
            box-shadow: 0 0 0 3px rgba(255, 127, 0, 0.15);
        }

        /* ── Table Card ── */
        .table-card {
            background: #121217;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
        }

        .orders-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .orders-table th {
            background: #16161f;
            padding: 1rem 1.1rem;
            font-weight: 700;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #94a3b8;
            border-bottom: 2px solid rgba(255, 127, 0, 0.4);
        }

        .orders-table td {
            padding: 1rem 1.1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            vertical-align: middle;
            font-size: 0.9rem;
        }

        .orders-table tr:hover {
            background: rgba(255, 127, 0, 0.03);
        }

        /* Status Badges */
        .status-badge {
            padding: 0.3rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-transform: capitalize;
        }

        .status-pending { background: rgba(245, 158, 11, 0.15); border: 1px solid #f59e0b; color: #fbbf24; }
        .status-confirmed { background: rgba(34, 197, 94, 0.15); border: 1px solid #22c55e; color: #4ade80; }
        .status-processing, .status-in_preparation { background: rgba(234, 179, 8, 0.15); border: 1px solid #eab308; color: #fbbf24; }
        .status-packed { background: rgba(167, 139, 250, 0.18); border: 1px solid #a78bfa; color: #c084fc; }
        .status-shipped, .status-in_transit { background: rgba(56, 189, 248, 0.18); border: 1px solid #38bdf8; color: #38bdf8; }
        .status-delivered { background: rgba(16, 185, 129, 0.18); border: 1px solid #10b981; color: #34d399; }
        .status-cancelled, .status-canceled { background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #f87171; }

        /* Action Buttons */
        .btn-action {
            padding: 0.45rem 0.75rem;
            border-radius: 6px;
            border: 1px solid transparent;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.78rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s ease;
        }

        .btn-view { background: rgba(59, 130, 246, 0.15); border-color: rgba(59, 130, 246, 0.35); color: #60a5fa; }
        .btn-view:hover { background: #3b82f6; color: #fff; }

        .btn-ticket { background: rgba(16, 185, 129, 0.15); border-color: rgba(16, 185, 129, 0.35); color: #34d399; }
        .btn-ticket:hover { background: #10b981; color: #fff; }

        .btn-print { background: rgba(139, 92, 246, 0.15); border-color: rgba(139, 92, 246, 0.35); color: #a78bfa; }
        .btn-print:hover { background: #8b5cf6; color: #fff; }

        .btn-edit { background: rgba(255, 127, 0, 0.15); border-color: rgba(255, 127, 0, 0.4); color: #ff9f43; }
        .btn-edit:hover { background: #ff7f00; color: #fff; }

        .btn-status { background: rgba(148, 163, 184, 0.15); border-color: rgba(148, 163, 184, 0.35); color: #cbd5e1; }
        .btn-status:hover { background: #475569; color: #fff; }

        /* Modals Generic Styling */
        .modal-fox-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 99999;
            justify-content: center;
            align-items: center;
            padding: 1rem;
            box-sizing: border-box;
        }

        .modal-fox-box {
            background: #14141a;
            border: 1px solid rgba(255, 127, 0, 0.35);
            border-radius: 16px;
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 1.75rem;
            box-shadow: 0 25px 60px rgba(0,0,0,0.8), 0 0 30px rgba(255, 127, 0, 0.15);
            color: #ffffff;
            position: relative;
            box-sizing: border-box;
            animation: modalPop 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes modalPop {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .modal-fox-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: 1rem;
            margin-bottom: 1.25rem;
        }

        .modal-fox-header h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 800;
            color: #ff9f43;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-fox-close {
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 1.6rem;
            cursor: pointer;
            padding: 2px 6px;
            border-radius: 6px;
            line-height: 1;
        }

        .modal-fox-close:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body>
    <!-- ── NAV HEADER ── -->
    <header>
        <div class="header-content">
            <a href="index.php" class="logo"><img src="img/logo_fox.png" alt="Ferretería FOX" style="height: 42px; width: auto; object-fit: contain;"></a>
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
                <!-- Dropdowns de Administración Separados -->
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn active">Admin Tienda <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content" style="min-width: 220px;">
                        <a href="admin_online_orders.php" style="color: #ff7f00; font-weight: 700;">🌐 Pedidos Online</a>
                        <a href="order_tracking.php">🚚 Seguimiento y Guías</a>
                        <a href="admin_online_billing.php">🏛️ Facturación & Pagos SAT</a>
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
            </nav>
            <div class="user-menu">
                <div class="user-info">
                    <div class="user-name" style="font-weight: 700; color: var(--color-naranja);"><?php echo $user_name; ?></div>
                    <div class="user-role" style="font-size: 0.72rem; color: #888; text-transform: uppercase;"><?php echo ($userRole ?? 'admin') === 'employee' ? 'PERSONAL' : 'ADMIN'; ?></div>
                </div>
                <button onclick="logout()" class="btn-logout">Cerrar Sesión</button>
            </div>
        </div>
    </header>

    <div class="admin-container">
        <!-- ── Top Left Back Button ── -->
        <div class="back-header">
            <button onclick="history.back()" class="btn-back">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Regresar
            </button>
        </div>

        <!-- ── Hero Banner ── -->
        <div class="admin-hero">
            <div class="admin-title-group">
                <div class="module-badge-online">🌐 Tienda en Línea E-Commerce</div>
                <h1>Panel de Pedidos & Logística</h1>
                <p>Monitorea, asigna paqueterías, cambia estados e imprime tickets y etiquetas oficiales de envío.</p>
            </div>
            <div>
                <button onclick="refreshOrders()" class="btn-action btn-edit" style="padding: 0.65rem 1.25rem; font-size: 0.9rem;">
                    🔄 Actualizar Pedidos
                </button>
            </div>
        </div>

        <!-- ── KPI Stats ── -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="color: #ff9f43;">📅</div>
                <div class="stat-content">
                    <div class="stat-label">Pedidos Hoy</div>
                    <div class="stat-value" id="todayCount">0</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="color: #fbbf24;">⏳</div>
                <div class="stat-content">
                    <div class="stat-label">Por Despachar</div>
                    <div class="stat-value" id="pendingCount">0</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="color: #34d399;">💰</div>
                <div class="stat-content">
                    <div class="stat-label">Ventas del Mes</div>
                    <div class="stat-value" id="monthSales">$0.00</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="color: #60a5fa;">🚚</div>
                <div class="stat-content">
                    <div class="stat-label">En Ruta / Tránsito</div>
                    <div class="stat-value" id="toDeliver">0</div>
                </div>
            </div>
        </div>

        <!-- ── Tabs ── -->
        <div class="tabs-header">
            <button class="tab-btn active" onclick="switchTab('all', this)">Todos los Pedidos</button>
            <button class="tab-btn" onclick="switchTab('in_preparation', this)">⏳ En Preparación</button>
            <button class="tab-btn" onclick="switchTab('packed', this)">📦 Empacados</button>
            <button class="tab-btn" onclick="switchTab('shipped', this)">🚚 Enviados</button>
            <button class="tab-btn" onclick="switchTab('delivered', this)">✅ Entregados</button>
            <button class="tab-btn" onclick="switchTab('cancelled', this)">❌ Cancelados</button>
        </div>

        <!-- ── Filter Bar ── -->
        <div class="filter-bar">
            <div class="filter-group">
                <label>Buscar Folio o Cliente</label>
                <input type="text" id="searchFolio" placeholder="Ej: 202609-00003 o Juan Pérez..." onkeyup="filterOrders()">
            </div>
            <div class="filter-group" style="max-width: 220px;">
                <label>Estado del Pedido</label>
                <select id="filterStatus" onchange="filterOrders()">
                    <option value="">Todos los estados</option>
                    <option value="in_preparation">⏳ En Preparación</option>
                    <option value="packed">📦 Empacado</option>
                    <option value="shipped">🚚 Enviado</option>
                    <option value="delivered">✅ Entregado</option>
                    <option value="cancelled">❌ Cancelado</option>
                </select>
            </div>
            <div class="filter-group" style="max-width: 200px;">
                <label>Fecha de Venta</label>
                <input type="date" id="filterDate" onchange="filterOrders()">
            </div>
            <div style="display: flex; align-self: flex-end; padding-bottom: 2px;">
                <button type="button" onclick="clearFilters()" class="btn-action" style="background: rgba(255,255,255,0.08); color: #cbd5e1; height: 38px;">
                    Limpiar Filtros
                </button>
            </div>
        </div>

        <!-- ── Orders Table ── -->
        <div class="table-card">
            <div style="overflow-x: auto;">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Folio Único</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Total</th>
                            <th>Estado Pedido</th>
                            <th>Paquetería & Guía</th>
                            <th>Pago</th>
                            <th style="text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="ordersTableBody">
                        <tr><td colspan="8" style="text-align:center; padding:3rem; color:#94a3b8;">Cargando pedidos en línea...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ── MODAL 1: DETALLES COMPLETOS DEL PEDIDO (👁️ Ver) ── -->
    <div id="orderDetailsModal" class="modal-fox-overlay">
        <div class="modal-fox-box" style="max-width: 720px;">
            <div class="modal-fox-header">
                <h3>📦 Detalle de Pedido en Línea</h3>
                <button class="modal-fox-close" onclick="closeOrderDetailsModal()">&times;</button>
            </div>

            <div id="orderDetailsContent" style="font-size: 0.92rem;">
                <div style="text-align: center; padding: 2rem; color: #888;">Cargando detalles...</div>
            </div>
        </div>
    </div>

    <!-- ── MODAL 2: ASIGNACIÓN / EDICIÓN DE GUÍA Y PAQUETERÍA (🚚 Guía) ── -->
    <div id="trackingModal" class="modal-fox-overlay">
        <div class="modal-fox-box" style="max-width: 520px;">
            <div class="modal-fox-header">
                <h3 id="trackingModalTitle">📦 Asignar Guía y Paquetería</h3>
                <button class="modal-fox-close" onclick="closeTrackingModal()">&times;</button>
            </div>
            
            <input type="hidden" id="trackingOrderId">
            <div id="trackingFolioDisplay" style="font-size: 0.88rem; color: #94a3b8; margin-bottom: 1.25rem;"></div>
            
            <div style="margin-bottom: 1rem;">
                <label style="display:block; margin-bottom:0.4rem; font-size:0.78rem; font-weight:700; color:#cbd5e1; text-transform:uppercase;">Empresa de Paquetería *</label>
                <select id="trackingCarrier" style="width:100%; background:#181822; border:1px solid rgba(255,255,255,0.15); color:#fff; padding:0.75rem 1rem; border-radius:8px; font-size:0.92rem; outline:none;">
                    <option value="">Seleccionar paquetería...</option>
                    <option value="FedEx">FedEx Express</option>
                    <option value="DHL">DHL Express</option>
                    <option value="Estafeta">Estafeta Mexicana</option>
                    <option value="Redpack">Redpack</option>
                    <option value="UPS">UPS</option>
                    <option value="Paquetexpress">Paquetexpress</option>
                    <option value="99 Minutos">99 Minutos</option>
                    <option value="Reparto Local Propio FOX">Reparto Local Propio FOX</option>
                </select>
            </div>
            
            <div style="margin-bottom: 1rem;">
                <label style="display:block; margin-bottom:0.4rem; font-size:0.78rem; font-weight:700; color:#cbd5e1; text-transform:uppercase;">Número de Guía / Rastreo *</label>
                <input type="text" id="trackingNumber" placeholder="Ej: 7894561230 o FOLIO-RUT-01" style="width:100%; box-sizing:border-box; background:#181822; border:1px solid rgba(255,255,255,0.15); color:#fff; padding:0.75rem 1rem; border-radius:8px; font-size:0.92rem; outline:none; font-family:monospace;">
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <label style="display:block; margin-bottom:0.4rem; font-size:0.78rem; font-weight:700; color:#cbd5e1; text-transform:uppercase;">Fecha Estimada de Llegada</label>
                <input type="date" id="trackingEstimatedDelivery" style="width:100%; box-sizing:border-box; background:#181822; border:1px solid rgba(255,255,255,0.15); color:#fff; padding:0.75rem 1rem; border-radius:8px; font-size:0.92rem; outline:none;">
                <small style="display:block; color:#94a3b8; margin-top:5px; font-size:0.75rem;">El cliente visualizará esta fecha de entrega en su ticket y rastreador en vivo.</small>
            </div>
            
            <div style="display:flex; gap:10px;">
                <button type="button" onclick="closeTrackingModal()" style="flex:1; background:#22222a; color:#cbd5e1; border:1px solid #3a3a48; padding:11px; border-radius:8px; font-weight:700; cursor:pointer;">
                    Cancelar
                </button>
                <button id="trackingSubmitBtn" onclick="submitTracking()" style="flex:2; background:linear-gradient(135deg, #ff7f00, #ff6600); color:#fff; border:none; padding:11px; border-radius:8px; font-weight:800; font-size:0.92rem; cursor:pointer; box-shadow:0 4px 15px rgba(255,102,0,0.35);">
                    🚀 Guardar y Actualizar
                </button>
            </div>
        </div>
    </div>

    <!-- ── MODAL 3: CAMBIO DE ESTADO (⚙️ Estado) ── -->
    <div id="statusModal" class="modal-fox-overlay">
        <div class="modal-fox-box" style="max-width: 480px;">
            <div class="modal-fox-header">
                <h3>⚙️ Actualizar Estado del Pedido</h3>
                <button class="modal-fox-close" onclick="closeStatusModal()">&times;</button>
            </div>

            <input type="hidden" id="statusOrderId">
            <div id="statusFolioDisplay" style="font-size: 0.88rem; color: #94a3b8; margin-bottom: 1.25rem;"></div>

            <div style="margin-bottom: 1.25rem;">
                <label style="display:block; margin-bottom:0.4rem; font-size:0.78rem; font-weight:700; color:#cbd5e1; text-transform:uppercase;">Nuevo Estado del Pedido *</label>
                <select id="statusSelect" style="width:100%; background:#181822; border:1px solid rgba(255,255,255,0.15); color:#fff; padding:0.75rem 1rem; border-radius:8px; font-size:0.92rem; outline:none;">
                    <option value="in_preparation">⏳ En Preparación / Almacén</option>
                    <option value="packed">📦 Empacado (Etiqueta Ciega / Listo para Envío)</option>
                    <option value="shipped">🚚 Enviado (En ruta con paquetería)</option>
                    <option value="delivered">🎉 Entregado al Cliente</option>
                    <option value="cancelled">❌ Cancelado</option>
                </select>
            </div>

            <div style="display:flex; gap:10px;">
                <button type="button" onclick="closeStatusModal()" style="flex:1; background:#22222a; color:#cbd5e1; border:1px solid #3a3a48; padding:11px; border-radius:8px; font-weight:700; cursor:pointer;">
                    Cancelar
                </button>
                <button onclick="submitStatusUpdate()" style="flex:2; background:linear-gradient(135deg, #ff7f00, #ff6600); color:#fff; border:none; padding:11px; border-radius:8px; font-weight:800; font-size:0.92rem; cursor:pointer; box-shadow:0 4px 15px rgba(255,102,0,0.35);">
                    💾 Guardar Estado
                </button>
            </div>
        </div>
    </div>

    <!-- ── SCRIPTS ── -->
    <script src="js/modals.js"></script>
    <script>
        const is_admin = <?php echo $is_admin ? 'true' : 'false'; ?>;
        window.csrfToken = '<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8"); ?>';
        let currentOrders = [];
        let currentFilter = 'all';

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
        }

        async function loadOrders() {
            try {
                const response = await fetch('api/admin_online_orders_api.php?action=list');
                const data = await response.json();
                
                if (data.success) {
                    currentOrders = data.orders || [];
                    applyFilters();
                    updateStats(data.stats || {});
                } else {
                    showAlert(data.message || 'No se pudieron cargar los pedidos en línea', 'error');
                }
            } catch (error) {
                console.error('Error loading orders:', error);
                showAlert('Error al conectar con el servidor para obtener pedidos', 'error');
            }
        }

        function renderOrders(orders) {
            const tbody = document.getElementById('ordersTableBody');
            
            if (orders.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:3rem; color:#94a3b8;">No se encontraron pedidos con los filtros seleccionados</td></tr>';
                return;
            }

            tbody.innerHTML = orders.map(order => {
                const hasTracking = !!(order.carrier && order.tracking_number);
                const estDateStr = order.estimated_delivery ? new Date(order.estimated_delivery).toLocaleDateString('es-MX') : '';
                const totalFormatted = parseFloat(order.total_amount || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                const isPaid = (order.payment_status || '').toLowerCase() === 'completed' || (order.payment_status || '').toLowerCase() === 'paid';

                const statusLabelMap = {
                    'pending': '⏳ En Preparación',
                    'confirmed': '✅ Confirmado',
                    'processing': '⏳ En Preparación',
                    'in_preparation': '⏳ En Preparación',
                    'packed': '📦 Empacado',
                    'shipped': '🚚 Enviado',
                    'in_transit': '🚚 En Ruta',
                    'delivered': '🎉 Entregado',
                    'cancelled': '❌ Cancelado',
                    'canceled': '❌ Cancelado'
                };

                return `
                <tr>
                    <td>
                        <strong style="color:#ff7f00; font-family:monospace; font-size:0.95rem;">${escapeHtml(order.folio)}</strong>
                        <div style="margin-top:2px;">
                            <span style="font-size:0.72rem; color:#60a5fa; font-weight:700;">🌐 E-COMMERCE</span>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:700; color:#fff;">${escapeHtml(order.customer_name || 'Cliente')}</div>
                    </td>
                    <td style="color:#cbd5e1; font-size:0.85rem;">${new Date(order.issued_date).toLocaleDateString('es-MX')}</td>
                    <td style="font-weight:800; color:#ff9f43; font-size:0.95rem;">$${totalFormatted}</td>
                    <td><span class="status-badge status-${escapeHtml(order.order_status)}">${statusLabelMap[order.order_status] || escapeHtml(order.order_status)}</span></td>
                    <td>
                        ${hasTracking ? `
                            <div style="font-size:0.82rem; line-height:1.35;">
                                <strong style="color:#ff9f43;">🚚 ${escapeHtml(order.carrier)}</strong><br>
                                <span style="font-family:monospace; color:#38bdf8; font-weight:700;">${escapeHtml(order.tracking_number)}</span>
                                ${estDateStr ? `<br><small style="color:#94a3b8;">📅 Llegada: ${estDateStr}</small>` : ''}
                            </div>
                        ` : '<span style="color:#94a3b8; font-size:0.78rem; font-style:italic; background:rgba(255,255,255,0.05); padding:2px 6px; border-radius:4px;">⚠️ Sin asignar</span>'}
                    </td>
                    <td>
                        <span class="status-badge" style="background:${isPaid ? 'rgba(34,197,94,0.15)' : 'rgba(245,158,11,0.15)'}; border:1px solid ${isPaid ? '#22c55e' : '#f59e0b'}; color:${isPaid ? '#4ade80' : '#fbbf24'};">
                            ${isPaid ? '✓ Pagado' : '⏳ Pendiente'}
                        </span>
                    </td>
                    <td style="text-align:center;">
                        <div style="display:flex; gap:5px; justify-content:center; flex-wrap:wrap;">
                            <button onclick="openOrderDetailsModal(${order.id}, '${escapeHtml(order.folio)}')" class="btn-action btn-view" title="Ver Detalle Completo">👁️ Ver</button>
                            <a href="ticket_client.php?folio=${encodeURIComponent(order.folio)}" target="_blank" class="btn-action btn-ticket" title="Ver e Imprimir Ticket Oficial">🖨️ Ticket</a>
                            <a href="api/print_blind_label.php?folio=${encodeURIComponent(order.folio)}" target="_blank" class="btn-action btn-print" title="Imprimir Etiqueta Ciega 4x6">🏷️ Etiqueta</a>
                            ${is_admin ? `<button onclick="editTracking(${order.id}, '${escapeHtml(order.folio)}', '${escapeHtml(order.carrier || '')}', '${escapeHtml(order.tracking_number || '')}', '${escapeHtml(order.estimated_delivery || '')}')" class="btn-action btn-edit" title="Asignar o editar paquetería">${hasTracking ? '✏️ Guía' : '🚚 Guía'}</button>` : ''}
                            ${is_admin ? `<button onclick="openStatusModal(${order.id}, '${escapeHtml(order.folio)}', '${escapeHtml(order.order_status || '')}')" class="btn-action btn-status" title="Cambiar Estado">⚙️ Estado</button>` : ''}
                        </div>
                    </td>
                </tr>
            `;
            }).join('');
        }

        function updateStats(stats) {
            document.getElementById('todayCount').textContent = stats.today_count || 0;
            document.getElementById('pendingCount').textContent = stats.pending_count || 0;
            const salesNum = parseFloat(stats.month_sales || 0);
            document.getElementById('monthSales').textContent = '$' + salesNum.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('toDeliver').textContent = stats.to_deliver || 0;
        }

        function switchTab(tab, btnElement) {
            currentFilter = tab;
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            if (btnElement) btnElement.classList.add('active');
            applyFilters();
        }

        function filterOrders() {
            applyFilters();
        }

        function statusMatches(orderStatus, targetStatus) {
            if (!orderStatus || !targetStatus) return false;
            if (orderStatus === targetStatus) return true;
            if (targetStatus === 'in_preparation') {
                return ['in_preparation', 'processing', 'pending', 'confirmed'].includes(orderStatus);
            }
            if (targetStatus === 'packed') {
                return orderStatus === 'packed';
            }
            if (targetStatus === 'shipped') {
                return ['shipped', 'in_transit'].includes(orderStatus);
            }
            if (targetStatus === 'cancelled' || targetStatus === 'canceled') {
                return ['cancelled', 'canceled'].includes(orderStatus);
            }
            return false;
        }

        function applyFilters() {
            const search = (document.getElementById('searchFolio').value || '').toLowerCase().trim();
            const status = document.getElementById('filterStatus').value;
            const date = document.getElementById('filterDate').value;
            
            let filtered = currentOrders;
            
            if (currentFilter !== 'all') {
                filtered = filtered.filter(o => statusMatches(o.order_status, currentFilter));
            }
            
            if (search) {
                filtered = filtered.filter(o => 
                    (o.folio && o.folio.toLowerCase().includes(search)) || 
                    (o.customer_name && o.customer_name.toLowerCase().includes(search)) ||
                    (o.tracking_number && o.tracking_number.toLowerCase().includes(search))
                );
            }
            
            if (status) {
                filtered = filtered.filter(o => statusMatches(o.order_status, status));
            }
            
            if (date) {
                filtered = filtered.filter(o => o.issued_date && o.issued_date.startsWith(date));
            }
            
            renderOrders(filtered);
        }

        function clearFilters() {
            document.getElementById('searchFolio').value = '';
            document.getElementById('filterStatus').value = '';
            document.getElementById('filterDate').value = '';
            switchTab('all', document.querySelectorAll('.tab-btn')[0]);
        }

        function refreshOrders() {
            loadOrders();
        }

        /* ── MODAL 1: DETALLES COMPLETOS ── */
        async function openOrderDetailsModal(orderId, folio) {
            const modal = document.getElementById('orderDetailsModal');
            const content = document.getElementById('orderDetailsContent');
            modal.style.display = 'flex';
            content.innerHTML = '<div style="text-align:center; padding:2rem; color:#888;">Cargando información del pedido...</div>';

            try {
                const response = await fetch(`api/admin_online_orders_api.php?action=get&id=${orderId}&folio=${encodeURIComponent(folio)}`);
                const res = await response.json();

                if (!res.success || !res.order) {
                    content.innerHTML = `<div style="color:#f87171; text-align:center; padding:1.5rem;">${escapeHtml(res.message || 'Error al obtener datos')}</div>`;
                    return;
                }

                const ord = res.order;
                const items = ord.items || [];
                let shippingObj = {};
                try {
                    shippingObj = typeof ord.shipping_address_json === 'string' ? JSON.parse(ord.shipping_address_json) : (ord.shipping_address_json || {});
                } catch(e) {}

                const fullAddress = [
                    shippingObj.address,
                    shippingObj.colonia ? `Col. ${shippingObj.colonia}` : '',
                    shippingObj.city,
                    shippingObj.postalCode ? `CP ${shippingObj.postalCode}` : '',
                    shippingObj.state || 'México'
                ].filter(Boolean).join(', ');

                let itemsHtml = items.map(it => `
                    <tr>
                        <td style="padding:8px 10px; border-bottom:1px solid #282834;">
                            <strong style="color:#fff;">${escapeHtml(it.product_name)}</strong>
                            <div style="font-size:0.75rem; color:#888;">SKU: ${escapeHtml(it.sku || 'N/A')}</div>
                        </td>
                        <td style="padding:8px 10px; border-bottom:1px solid #282834; text-align:center;">${it.quantity}</td>
                        <td style="padding:8px 10px; border-bottom:1px solid #282834; text-align:right;">$${parseFloat(it.unit_price).toFixed(2)}</td>
                        <td style="padding:8px 10px; border-bottom:1px solid #282834; text-align:right; font-weight:700; color:#ff9f43;">$${parseFloat(it.line_total || it.total || (it.quantity * it.unit_price)).toFixed(2)}</td>
                    </tr>
                `).join('');

                content.innerHTML = `
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.25rem;">
                        <div style="background:#181822; padding:1rem; border-radius:10px; border:1px solid rgba(255,255,255,0.06);">
                            <div style="font-size:0.75rem; color:#94a3b8; text-transform:uppercase; font-weight:700;">Datos del Cliente</div>
                            <div style="font-size:1.05rem; font-weight:800; color:#fff; margin-top:2px;">${escapeHtml(ord.customer_name || 'N/A')}</div>
                            <div style="font-size:0.85rem; color:#cbd5e1; margin-top:4px;">📧 ${escapeHtml(ord.user_email || 'Sin correo')}</div>
                            <div style="font-size:0.85rem; color:#cbd5e1; margin-top:2px;">📞 ${escapeHtml(ord.user_phone || 'Sin teléfono')}</div>
                        </div>
                        <div style="background:#181822; padding:1rem; border-radius:10px; border:1px solid rgba(255,255,255,0.06);">
                            <div style="font-size:0.75rem; color:#94a3b8; text-transform:uppercase; font-weight:700;">Resumen del Pedido</div>
                            <div style="font-size:1.05rem; font-weight:800; color:#ff7f00; margin-top:2px; font-family:monospace;">${escapeHtml(ord.folio)}</div>
                            <div style="font-size:0.85rem; color:#cbd5e1; margin-top:4px;">📅 Fecha: ${new Date(ord.issued_date).toLocaleString('es-MX')}</div>
                            <div style="font-size:0.85rem; color:#cbd5e1; margin-top:2px;">💳 Pago: <strong>${escapeHtml(ord.payment_method || 'Digital')}</strong> (${escapeHtml(ord.payment_status || 'pending')})</div>
                        </div>
                    </div>

                    <div style="background:rgba(59,130,246,0.08); border:1px dashed rgba(59,130,246,0.35); padding:1rem; border-radius:10px; margin-bottom:1.25rem;">
                        <div style="font-size:0.75rem; color:#60a5fa; text-transform:uppercase; font-weight:800; margin-bottom:4px;">📍 Dirección de Envío a Domicilio</div>
                        <div style="font-size:0.92rem; color:#fff; line-height:1.4;">${escapeHtml(fullAddress || 'Dirección no especificada')}</div>
                        ${ord.carrier ? `
                            <div style="margin-top:8px; padding-top:8px; border-top:1px solid rgba(255,255,255,0.08); font-size:0.85rem; display:flex; gap:15px; flex-wrap:wrap;">
                                <div>🚚 <strong>Paquetería:</strong> ${escapeHtml(ord.carrier)}</div>
                                <div>🔖 <strong>Guía:</strong> <span style="font-family:monospace; color:#38bdf8; font-weight:700;">${escapeHtml(ord.tracking_number || '')}</span></div>
                                ${ord.estimated_delivery ? `<div>📅 <strong>Llegada aprox:</strong> ${new Date(ord.estimated_delivery).toLocaleDateString('es-MX')}</div>` : ''}
                            </div>
                        ` : '<div style="margin-top:6px; font-size:0.8rem; color:#f59e0b;">⚠️ Paquetería y número de guía aún no asignados.</div>'}
                    </div>

                    <div style="font-size:0.8rem; font-weight:800; color:#94a3b8; text-transform:uppercase; margin-bottom:6px;">Artículos del Pedido (${items.length})</div>
                    <div style="max-height:220px; overflow-y:auto; border:1px solid rgba(255,255,255,0.08); border-radius:8px; margin-bottom:1.25rem;">
                        <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
                            <thead>
                                <tr style="background:#1a1a24; color:#94a3b8; text-align:left;">
                                    <th style="padding:8px 10px;">Producto</th>
                                    <th style="padding:8px 10px; text-align:center;">Cant.</th>
                                    <th style="padding:8px 10px; text-align:right;">P. Unit</th>
                                    <th style="padding:8px 10px; text-align:right;">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${itemsHtml || '<tr><td colspan="4" style="text-align:center; padding:1rem; color:#888;">1 x Venta Mostrador General</td></tr>'}
                            </tbody>
                        </table>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; background:#181822; padding:1rem 1.25rem; border-radius:10px; margin-bottom:1.5rem;">
                        <span style="font-size:1.1rem; font-weight:800; color:#fff;">Total Facturado:</span>
                        <span style="font-size:1.4rem; font-weight:900; color:#ff7f00;">$${parseFloat(ord.total_amount).toFixed(2)} MXN</span>
                    </div>

                    <div style="display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap;">
                        <a href="ticket_client.php?folio=${encodeURIComponent(ord.folio)}" target="_blank" class="btn-action btn-ticket" style="padding:10px 16px;">
                            🖨️ Imprimir Ticket Oficial
                        </a>
                        <a href="api/print_blind_label.php?folio=${encodeURIComponent(ord.folio)}" target="_blank" class="btn-action btn-print" style="padding:10px 16px;">
                            🏷️ Etiqueta 4x6"
                        </a>
                        <button type="button" onclick="closeOrderDetailsModal()" class="btn-action" style="background:#22222a; color:#cbd5e1; padding:10px 18px;">
                            Cerrar
                        </button>
                    </div>
                `;
            } catch(e) {
                console.error(e);
                content.innerHTML = '<div style="color:#f87171; text-align:center; padding:1.5rem;">Error al procesar la solicitud de detalles</div>';
            }
        }

        function closeOrderDetailsModal() {
            document.getElementById('orderDetailsModal').style.display = 'none';
        }

        /* ── MODAL 2: ASIGNAR / EDITAR GUÍA ── */
        function editTracking(orderId, folio, carrier = '', trackingNumber = '', estimatedDelivery = '') {
            document.getElementById('trackingOrderId').value = orderId;
            document.getElementById('trackingFolioDisplay').innerHTML = `Pedido Folio: <strong style="color:#ff7f00; font-family:monospace;">${escapeHtml(folio)}</strong>`;
            document.getElementById('trackingCarrier').value = carrier || '';
            document.getElementById('trackingNumber').value = trackingNumber || '';
            
            if (estimatedDelivery) {
                const d = new Date(estimatedDelivery);
                if (!isNaN(d.getTime())) {
                    document.getElementById('trackingEstimatedDelivery').value = d.toISOString().split('T')[0];
                } else {
                    document.getElementById('trackingEstimatedDelivery').value = estimatedDelivery.substring(0, 10);
                }
            } else {
                document.getElementById('trackingEstimatedDelivery').value = '';
            }

            const isEdit = !!trackingNumber;
            document.getElementById('trackingModalTitle').textContent = isEdit ? '✏️ Editar Guía y Envío' : '📦 Asignar Guía y Paquetería';
            document.getElementById('trackingSubmitBtn').textContent = isEdit ? '💾 Actualizar Datos de Envío' : '🚀 Guardar y Notificar Envío';
            document.getElementById('trackingModal').style.display = 'flex';
        }

        function closeTrackingModal() {
            document.getElementById('trackingModal').style.display = 'none';
        }

        async function submitTracking() {
            const orderId = document.getElementById('trackingOrderId').value;
            const carrier = document.getElementById('trackingCarrier').value;
            const trackingNumber = document.getElementById('trackingNumber').value.trim();
            const estimatedDelivery = document.getElementById('trackingEstimatedDelivery').value;

            if (!carrier || !trackingNumber) {
                showAlert('Por favor selecciona la paquetería e ingresa el número de guía.', 'warning');
                return;
            }

            try {
                const response = await fetch('api/shipping_tracking.php?action=create', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': window.csrfToken
                    },
                    body: JSON.stringify({
                        order_id: orderId,
                        carrier: carrier,
                        tracking_number: trackingNumber,
                        estimated_delivery: estimatedDelivery || null,
                        csrf_token: window.csrfToken
                    })
                });

                const result = await response.json();

                if (result.success) {
                    closeTrackingModal();
                    showAlert('¡Guía y seguimiento guardados exitosamente! El pedido ha sido marcado como enviado.', 'success', () => {
                        loadOrders();
                    });
                } else {
                    showAlert('Error: ' + (result.message || result.error || 'No se pudo guardar el tracking'), 'error');
                }
            } catch (error) {
                showAlert('No se pudo establecer conexión con el servidor. Revisa los registros.', 'error');
            }
        }

        /* ── MODAL 3: CAMBIO DE ESTADO ── */
        function openStatusModal(orderId, folio, currentStatus = 'pending') {
            document.getElementById('statusOrderId').value = orderId;
            document.getElementById('statusFolioDisplay').innerHTML = `Pedido Folio: <strong style="color:#ff7f00; font-family:monospace;">${escapeHtml(folio)}</strong>`;
            let mapped = currentStatus;
            if (mapped === 'processing' || mapped === 'pending' || mapped === 'confirmed') mapped = 'in_preparation';
            if (mapped === 'in_transit') mapped = 'shipped';
            if (mapped === 'canceled') mapped = 'cancelled';
            document.getElementById('statusSelect').value = mapped || 'in_preparation';
            document.getElementById('statusModal').style.display = 'flex';
        }

        function closeStatusModal() {
            document.getElementById('statusModal').style.display = 'none';
        }

        async function submitStatusUpdate() {
            const orderId = document.getElementById('statusOrderId').value;
            const status = document.getElementById('statusSelect').value;

            try {
                const response = await fetch('api/admin_online_orders_api.php?action=update_status', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': window.csrfToken
                    },
                    body: JSON.stringify({ 
                        order_id: orderId, 
                        status: status,
                        csrf_token: window.csrfToken
                    })
                });
                const res = await response.json();

                if (res.success) {
                    closeStatusModal();
                    showAlert('Estado de pedido actualizado correctamente', 'success', () => {
                        loadOrders();
                    });
                } else {
                    showAlert('Error: ' + (res.message || 'No se pudo actualizar el estado'), 'error');
                }
            } catch (err) {
                showAlert('Error de conexión al actualizar el estado', 'error');
            }
        }

        function logout() {
            confirmLogout('api/auth.php?action=logout');
        }

        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeOrderDetailsModal();
                closeTrackingModal();
                closeStatusModal();
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            loadOrders();
        });
    </script>
</body>
</html>
