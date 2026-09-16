<?php
/**
 * Seguimiento de Pedidos — público por folio / admin con tabla completa
 * Truper Platform
 */
require_once '../config/config.php';
require_once '../src/utils/SatCatalogs.php';

$isOnlineMode = ($_GET['mode'] ?? '') === 'online';

$isLogged = isset($_SESSION['user_id']);
$isAdmin  = $isLogged && (($_SESSION['role'] ?? '') === 'admin' || ($_SESSION['role'] ?? '') === 'employee');

// KPI stats only for admin/logged-in users
$totalOrders = $inPrepOrders = $inTransitOrders = $deliveredOrders = 0;
try {
    if ($isAdmin) {
        $totalOrders     = (int)$pdo->query("SELECT COUNT(*) FROM sales_tickets WHERE deleted_at IS NULL")->fetchColumn();
        $inPrepOrders    = (int)$pdo->query("SELECT COUNT(*) FROM sales_tickets WHERE deleted_at IS NULL AND COALESCE(order_status,'in_preparation')='in_preparation'")->fetchColumn();
        $inTransitOrders = (int)$pdo->query("SELECT COUNT(*) FROM sales_tickets WHERE deleted_at IS NULL AND order_status IN ('packed','in_transit')")->fetchColumn();
        $deliveredOrders = (int)$pdo->query("SELECT COUNT(*) FROM sales_tickets WHERE deleted_at IS NULL AND order_status='delivered'")->fetchColumn();
    } elseif ($isLogged) {
        $uId = (int)$_SESSION['user_id'];
        $s = $pdo->prepare("SELECT COUNT(*) FROM sales_tickets WHERE deleted_at IS NULL AND user_id=?"); $s->execute([$uId]); $totalOrders=(int)$s->fetchColumn();
        $s = $pdo->prepare("SELECT COUNT(*) FROM sales_tickets WHERE deleted_at IS NULL AND user_id=? AND COALESCE(order_status,'in_preparation')='in_preparation'"); $s->execute([$uId]); $inPrepOrders=(int)$s->fetchColumn();
        $s = $pdo->prepare("SELECT COUNT(*) FROM sales_tickets WHERE deleted_at IS NULL AND user_id=? AND order_status IN ('packed','in_transit')"); $s->execute([$uId]); $inTransitOrders=(int)$s->fetchColumn();
        $s = $pdo->prepare("SELECT COUNT(*) FROM sales_tickets WHERE deleted_at IS NULL AND user_id=? AND order_status='delivered'"); $s->execute([$uId]); $deliveredOrders=(int)$s->fetchColumn();
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Seguimiento de Pedidos & Logística - Ferretería FOX</title>
    <link rel="stylesheet" href="css/styles.css?v=4.1">
    <link rel="stylesheet" href="css/theme.css?v=4.1">
    <link rel="stylesheet" href="css/responsive-complete.css?v=5.0">
    <style>
        body { font-family: var(--theme-font, 'Outfit', 'Inter', sans-serif); color: #fff; background: #0b0b0e; }
        .tracking-container { max-width: 1320px; margin: 2rem auto; padding: 0 1.25rem; }
        
        /* Hero Header */
        .page-title-box {
            background: linear-gradient(135deg, rgba(20,20,26,0.9), rgba(12,12,16,0.95));
            border: 1px solid rgba(255, 127, 0, 0.25);
            border-radius: 16px;
            padding: 1.75rem 2rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.25rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .page-title { margin: 0; font-size: 1.75rem; font-weight: 800; color: #ffffff; letter-spacing: -0.02em; }
        .page-subtitle { color: #888899; font-size: 0.92rem; margin-top: 0.3rem; }
        .badge-header { background: rgba(255,127,0,0.12); color: var(--theme-accent, #ff7f00); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; padding: 4px 10px; border-radius: 6px; border: 1px solid rgba(255,127,0,0.25); display: inline-block; margin-bottom: 0.4rem; }

        /* Summary Metric KPI Cards */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.75rem; }
        .kpi-card { background: #121217; border: 1px solid #22222a; border-radius: 12px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; }
        .kpi-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
        .kpi-num { font-size: 1.5rem; font-weight: 800; color: #fff; line-height: 1; margin-bottom: 0.2rem; }
        .kpi-label { font-size: 0.8rem; color: #888899; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }

        /* Filter Card */
        .filters-card { background: #121217; border: 1px solid #22222a; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem; }
        .grid-filters { display: grid; grid-template-columns: 2.5fr 1.5fr 1fr; gap: 1rem; align-items: flex-end; }
        .form-group label { display: block; margin-bottom: 0.4rem; font-size: 0.82rem; font-weight: 700; color: #aaaab8; text-transform: uppercase; letter-spacing: 0.03em; }
        .form-group input, .form-group select { width: 100%; background: #181820; border: 1px solid #2e2e3a; color: #fff; padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.92rem; outline: none; transition: border-color 0.2s; }
        .form-group input:focus, .form-group select:focus { border-color: var(--theme-accent, #ff7f00); }

        /* Orders Table */
        .orders-table-card { background: #121217; border: 1px solid #22222a; border-radius: 14px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem; }
        th { background: #171720; padding: 1rem 1.25rem; border-bottom: 1px solid #282834; color: #888899; text-transform: uppercase; font-size: 0.75rem; letter-spacing: .06em; font-weight: 700; }
        td { padding: 1.1rem 1.25rem; border-bottom: 1px solid #1a1a24; vertical-align: middle; }
        tr:hover { background: rgba(255,127,0,0.02); }

        .btn-svg { display: inline-flex; align-items: center; gap: 0.4rem; padding: 6px 12px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; text-decoration: none; cursor: pointer; border: none; transition: all 0.2s ease; }
        .btn-green { background: #15803d; color: #fff; }
        .btn-green:hover { background: #16a34a; }
        .btn-dark { background: #22222a; color: #eee; border: 1px solid #333342; }
        .btn-dark:hover { background: #2a2a35; border-color: #444456; }
        .btn-accent { background: rgba(255,127,0,0.12); color: var(--theme-accent, #ff7f00); border: 1px solid rgba(255,127,0,0.3); }
        .btn-accent:hover { background: rgba(255,127,0,0.22); }

        /* Log Modal */
        .log-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(8px); z-index: 9999; justify-content: center; align-items: center; padding: 1rem; }
        .log-modal-content { background: #14141a; border: 1px solid #333342; border-radius: 14px; width: 100%; max-width: 650px; max-height: 85vh; overflow-y: auto; padding: 1.75rem; box-shadow: 0 20px 50px rgba(0,0,0,0.6); }

        @media (max-width: 900px) { .kpi-grid { grid-template-columns: repeat(2, 1fr); } .grid-filters { grid-template-columns: 1fr; } }
    </style>
</head>
<body class="catalog-minimal">

    <?php if (!$isAdmin || $isOnlineMode): ?>
    <!-- Barra de regreso -->
    <div style="background:linear-gradient(90deg,rgba(18,18,24,.98),rgba(10,10,14,.99));border-bottom:1px solid rgba(255,127,0,.2);padding:.5rem 1.4rem;display:flex;align-items:center;gap:1rem;">
        <a href="<?php echo $isOnlineMode ? 'tienda.php' : 'index.php'; ?>" style="display:inline-flex;align-items:center;gap:6px;color:#ff7f00;font-weight:700;font-size:.84rem;text-decoration:none;padding:5px 14px;border:1px solid rgba(255,127,0,.3);border-radius:8px;background:rgba(255,127,0,.07);transition:all .18s;"
            onmouseenter="this.style.background='rgba(255,127,0,.18)';this.style.borderColor='#ff7f00';this.style.color='#fff'"
            onmouseleave="this.style.background='rgba(255,127,0,.07)';this.style.borderColor='rgba(255,127,0,.3)';this.style.color='#ff7f00'">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            <?php echo $isOnlineMode ? 'Regresar a la Tienda en Línea' : 'Regresar al Catálogo Principal'; ?>
        </a>
        <span style="font-size:.73rem;font-weight:700;color:#ff7f00;text-transform:uppercase;letter-spacing:.05em;opacity:.7;">Seguimiento de Pedido</span>
    </div>
    <?php endif; ?>

    <header>
        <div class="header-content">
            <a href="<?php echo $isOnlineMode ? 'tienda.php' : 'index.php'; ?>" class="logo"><img src="img/logo_fox.png" alt="Ferretería FOX" style="height: 42px; width: auto; object-fit: contain;"></a>
            <button class="hamburger-btn" aria-label="Toggle menu"><span></span><span></span><span></span></button>
            <nav class="nav-menu">
                <?php if ($isOnlineMode): ?>
                    <a href="tienda.php">Tienda en Línea</a>
                    <a href="marketplace_ce.php?mode=online">Marketplace CE</a>
                    <a href="order_tracking.php?mode=online" class="active">Seguimiento de Pedido</a>
                    <a href="cart.php?mode=online">Carrito</a>
                <?php else: ?>
                    <a href="index.php">Catálogo</a>
                    <a href="marketplace_ce.php">Marketplace CE</a>
                    <a href="order_tracking.php" class="active">Seguimiento de Pedido</a>
                    <a href="cart.php">Carrito</a>
                <?php endif; ?>

                <?php if ($isLogged && !$isOnlineMode): ?>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Mi Cuenta <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content">
                        <a href="dashboard.php">Dashboard</a>
                        <a href="orders.php">Mis Pedidos</a>
                        <a href="wholesale.php">Mayoreo</a>
                        <a href="profile.php">Perfil</a>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($isAdmin && !$isOnlineMode): ?>
                <!-- Dropdowns de Administración Separados -->
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn active">Admin Tienda <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content" style="min-width: 220px;">
                        <a href="admin_online_orders.php">🌐 Pedidos Online</a>
                        <a href="order_tracking.php" style="color: #ff7f00; font-weight: 700;">🚚 Seguimiento y Guías</a>
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
                
                <?php endif; ?>
            </nav>
        </div>
        <?php if ($isLogged && !$isOnlineMode): ?>
        <div class="user-menu">
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Usuario', ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="user-role"><?php echo ($_SESSION['role'] ?? 'admin') === 'employee' ? 'PERSONAL' : 'ADMIN'; ?></div>
            </div>
            <button class="btn-logout" onclick="logout()">Cerrar Sesión</button>
        </div>
        <?php endif; ?>
    </header>

       <main class="tracking-container">

        <?php if (!$isAdmin && (!$isLogged || $isOnlineMode)): ?>
        <!-- ══════════════════════════════════════════════════════ -->
        <!-- VISTA PÚBLICA / INVITADO: sólo búsqueda por folio     -->
        <!-- ══════════════════════════════════════════════════════ -->
        <div style="max-width:550px;margin:4rem auto;padding:0 1.2rem;">
            <div style="text-align:center;margin-bottom:2.2rem;">
                <div style="display: inline-flex; align-items: center; justify-content: center; width: 72px; height: 72px; border-radius: 50%; background: rgba(255, 127, 0, 0.08); border: 1px solid rgba(255, 127, 0, 0.25); color: #ff7f00; margin-bottom: 1.25rem; box-shadow: 0 8px 30px rgba(255, 127, 0, 0.12); transition: all 0.3s ease;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="filter: drop-shadow(0 2px 4px rgba(255,127,0,0.25));">
                        <path d="M12 2a8 8 0 0 0-8 8c0 5.25 8 12 8 12s8-6.75 8-12a8 8 0 0 0-8-8z"/>
                        <circle cx="12" cy="10" r="3"/>
                    </svg>
                </div>
                <h1 style="font-size:1.9rem;font-weight:900;color:#fff;margin:0 0 .5rem;letter-spacing:-0.02em;">Seguimiento de Pedido</h1>
                <p style="color:#a0a0b0;font-size:.96rem;line-height:1.4;">Ingresa tu folio de pedido para consultar el estado de tu envío en tiempo real.</p>
            </div>

            <div style="background: rgba(255,255,255,0.02); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.06); border-radius: 20px; padding: 2.25rem 2rem; box-shadow: 0 20px 50px rgba(0,0,0,.5);">
                <label style="display:block;font-size:.78rem;font-weight:800;color:rgba(255,255,255,0.5);text-transform:uppercase;letter-spacing:.08em;margin-bottom:.6rem;">Número de Folio / Ticket</label>
                <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
                    <input type="text" id="guestFolioInput"
                           placeholder="Ej: FOX-2026-884912 o TKT-12345"
                           style="flex:1;min-width:200px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.1);color:#fff;padding:.85rem 1.1rem;border-radius:12px;font-size:0.98rem;outline:none;transition:all .3s cubic-bezier(0.4, 0, 0.2, 1);box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);"
                           onfocus="this.style.borderColor='#ff7f00'; this.style.boxShadow='0 0 10px rgba(255,127,0,0.25), inset 0 2px 4px rgba(0,0,0,0.1)'" 
                           onblur="this.style.borderColor='rgba(255,255,255,0.1)'; this.style.boxShadow='inset 0 2px 4px rgba(0,0,0,0.2)'"
                           onkeydown="if(event.key==='Enter') searchGuestOrder()">
                    <button onclick="searchGuestOrder()"
                            style="background:linear-gradient(135deg,#ff8f00,#e05c00);color:#fff;border:none;padding:.85rem 1.8rem;border-radius:12px;font-size:.95rem;font-weight:800;cursor:pointer;white-space:nowrap;transition:all .3s cubic-bezier(0.175, 0.885, 0.32, 1.275);box-shadow: 0 4px 15px rgba(255,127,0,0.3);"
                            onmouseenter="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 6px 20px rgba(255,127,0,0.45)'" 
                            onmouseleave="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(255,127,0,0.3)'">
                        Consultar
                    </button>
                </div>
                <div id="guestFolioResult" style="margin-top:1.5rem;"></div>
            </div>

            <p style="text-align:center;margin-top:2rem;">
                <a href="/tienda.php" style="color:rgba(255,127,0,0.85);text-decoration:none;font-weight:700;font-size:0.88rem;display:inline-flex;align-items:center;gap:6px;transition:color .2s;"
                   onmouseenter="this.style.color='#ff7f00'" onmouseleave="this.style.color='rgba(255,127,0,0.85)'">
                    ← Volver al catálogo principal
                </a>
            </p>
        </div>

        <?php else: ?>
        <!-- ══════════════════════════════════════════════════════ -->
        <!-- VISTA ADMIN / USUARIO LOGGEADO: tabla completa        -->
        <!-- ══════════════════════════════════════════════════════ -->
        <div class="back-header" style="margin-bottom: 1.25rem;">
            <button onclick="history.back()" class="btn-back">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Regresar
            </button>
        </div>

        <div class="page-title-box">
            <div>
                <span class="badge-header">Plataforma Logística</span>
                <h1 class="page-title">Seguimiento Integral de Pedidos</h1>
                <div class="page-subtitle">Monitoreo en tiempo real, trazabilidad y etiquetas ciegas.</div>
            </div>
            <?php if ($isAdmin): ?>
            <button class="btn-svg btn-green" onclick="openConfirmArrivalModal()" style="padding:10px 18px;font-weight:700;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                Confirmar Recepción por Folio
            </button>
            <?php endif; ?>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card"><div class="kpi-icon" style="background:rgba(255,127,0,.15);color:#ff7f00;">📊</div><div><div class="kpi-num" id="kpiTotal"><?php echo $totalOrders; ?></div><div class="kpi-label">Pedidos Registrados</div></div></div>
            <div class="kpi-card"><div class="kpi-icon" style="background:rgba(234,179,8,.15);color:#eab308;">⏳</div><div><div class="kpi-num" id="kpiInPrep"><?php echo $inPrepOrders; ?></div><div class="kpi-label">En Preparación</div></div></div>
            <div class="kpi-card"><div class="kpi-icon" style="background:rgba(168,85,247,.15);color:#c084fc;">🚚</div><div><div class="kpi-num" id="kpiInTransit"><?php echo $inTransitOrders; ?></div><div class="kpi-label">En Ruta</div></div></div>
            <div class="kpi-card"><div class="kpi-icon" style="background:rgba(34,197,94,.15);color:#4ade80;">✅</div><div><div class="kpi-num" id="kpiDelivered"><?php echo $deliveredOrders; ?></div><div class="kpi-label">Entregados</div></div></div>
        </div>

        <div class="filters-card">
            <div class="grid-filters" style="grid-template-columns: 2fr 1.2fr 1.2fr 1fr;">
                <div class="form-group">
                    <label>Búsqueda</label>
                    <input type="text" id="searchInput" placeholder="Folio (FOX-2026-...), cliente o código..." oninput="loadTrackingOrders()">
                </div>
                <div class="form-group">
                    <label>Canal de Venta</label>
                    <select id="channelFilter" onchange="loadTrackingOrders()">
                        <option value="all">🌐🏬 Todos los Canales</option>
                        <option value="pos">🏬 Tienda Local (Mostrador)</option>
                        <option value="online">🌐 Tienda en Línea (Web)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Estatus</label>
                    <select id="statusFilter" onchange="loadTrackingOrders()">
                        <option value="">Todos los Estatus</option>
                        <option value="in_preparation">En Preparación</option>
                        <option value="packed">Empacado</option>
                        <option value="in_transit">En Ruta</option>
                        <option value="delivered">Entregado</option>
                        <option value="canceled">Cancelado</option>
                    </select>
                </div>
                <div class="form-group">
                    <button class="btn-svg btn-dark" onclick="loadTrackingOrders()" style="height:44px;width:100%;justify-content:center;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
                        Actualizar
                    </button>
                </div>
            </div>
        </div>

        <div class="orders-table-card">
            <div style="overflow-x:auto;">
                <table>
                    <thead><tr>
                        <th>Folio Único</th><th>Canal</th><th>Cliente</th><th>Fecha</th>
                        <th>Monto</th><th>Comprobante</th><th>Estatus</th>
                        <th style="text-align:center;">Acciones</th>
                    </tr></thead>
                    <tbody id="ordersTableBody">
                        <tr><td colspan="8" style="text-align:center;padding:3rem;" class="text-muted">Cargando pedidos...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </main>

    <!-- MODAL DE AUDITORÍA Y TRAZABILIDAD (LOG) -->
    <div id="logModal" class="log-modal">
        <div class="log-modal-content">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #282834; padding-bottom:1rem; margin-bottom:1rem;">
                <h3 style="margin:0; color:var(--theme-accent, #ff7f00); font-weight:800; font-size:1.15rem;" id="logModalTitle">Traza de Auditoría de Pedido</h3>
                <button onclick="closeLogModal()" style="background:none; border:none; color:#aaa; font-size:1.5rem; cursor:pointer;">&times;</button>
            </div>
            <div id="logModalBody">Cargando historial de registros...</div>
        </div>
    </div>

    <!-- MODAL CONFIRMAR RECEPCIÓN POR FOLIO -->
    <div id="confirmArrivalModal" class="log-modal">
        <div class="log-modal-content" style="max-width:480px;">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #282834; padding-bottom:1rem; margin-bottom:1rem;">
                <h3 style="margin:0; color:#22c55e; font-weight:800; font-size:1.15rem;">Confirmación de Recepción por Folio</h3>
                <button onclick="closeConfirmArrivalModal()" style="background:none; border:none; color:#aaa; font-size:1.5rem; cursor:pointer;">&times;</button>
            </div>
            <p style="font-size:0.88rem; color:#aaa; line-height:1.4; margin-bottom:1.25rem;">Ingresa el Folio Único de Pedido recibido por el establecimiento o escuela para validar la entrega en el sistema.</p>
            <div class="form-group" style="margin-bottom:1rem;">
                <label>Folio Único de Pedido</label>
                <input type="text" id="confirmFolioInput" placeholder="Ej: FOX-2026-884912" style="text-transform:uppercase;">
            </div>
            <div class="form-group" style="margin-bottom:1.25rem;">
                <label>Notas de Recepción (Opcional)</label>
                <input type="text" id="confirmNotesInput" placeholder="Ej: Recibido por Lic. María González (Dirección General)">
            </div>
            <button class="btn-svg btn-green" onclick="submitArrivalConfirmation()" style="width:100%; justify-content:center; padding:12px; font-weight:700;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                Validar y Confirmar Entrega
            </button>
        </div>
    </div>

    <!-- MODAL TRACKING DE ENVÍO -->
    <div id="shippingTrackingModal" class="log-modal">
        <div class="log-modal-content" style="max-width:600px;">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #282834; padding-bottom:1rem; margin-bottom:1rem;">
                <h3 style="margin:0; color:#3b82f6; font-weight:800; font-size:1.15rem;">📦 Tracking de Envío</h3>
                <button onclick="closeShippingTrackingModal()" style="background:none; border:none; color:#aaa; font-size:1.5rem; cursor:pointer;">&times;</button>
            </div>
            <div id="shippingTrackingBody">Cargando información de tracking...</div>
        </div>
    </div>

    <script src="js/main.js?v=2.6"></script>
    <script src="js/modals.js"></script>
    <script>
        const isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;

        function escapeHtml(text) {
            if (!text) return '';
            return String(text)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        async function loadTrackingOrders() {
            const tableBody = document.getElementById('ordersTableBody');
            if (!tableBody) return;

            try {
                const search = document.getElementById('searchInput')?.value?.trim() || '';
                const status = document.getElementById('statusFilter')?.value || '';
                const channel = document.getElementById('channelFilter')?.value || 'all';

                const url = `/admin_supply.php?action=order-tracking-list&search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}&channel=${encodeURIComponent(channel)}&_=${Date.now()}`;
                const res = await apiCall(url, 'GET', null, { silent: true });

                if (res.kpis) {
                    if (document.getElementById('kpiTotal')) document.getElementById('kpiTotal').textContent = res.kpis.total ?? 0;
                    if (document.getElementById('kpiInPrep')) document.getElementById('kpiInPrep').textContent = res.kpis.in_preparation ?? 0;
                    if (document.getElementById('kpiInTransit')) document.getElementById('kpiInTransit').textContent = res.kpis.in_transit ?? 0;
                    if (document.getElementById('kpiDelivered')) document.getElementById('kpiDelivered').textContent = res.kpis.delivered ?? 0;
                }

                if (!res.orders || res.orders.length === 0) {
                    const isGuest = !<?php echo $isLogged ? 'true' : 'false'; ?>;
                    const msg = (isGuest && !search)
                        ? '🔍 Ingresa el Folio de Pedido o Número de Ticket en la caja de búsqueda superior para consultar tu pedido en tiempo real (sin necesidad de iniciar sesión).'
                        : 'Sin registros bajo los parámetros seleccionados.';
                    tableBody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:2.5rem; color:#aaaab8;">${msg}</td></tr>`;
                    return;
                }

                const statusLabels = {
                    'in_preparation': '⏳ En Preparación',
                    'packed': '📦 Empacado',
                    'in_transit': '🚚 En Ruta de Entrega',
                    'delivered': '✅ Entregado / Confirmado',
                    'canceled': '❌ Cancelado',
                    'cancelled': '❌ Cancelado'
                };

                let html = '';
                res.orders.forEach(ord => {
                    const currentSt = ord.order_status || 'in_preparation';
                    const isPos = ord.channel === 'pos';
                    const channelBadge = isPos 
                        ? `<span style="background:rgba(59,130,246,0.12); color:#60a5fa; border:1px solid rgba(59,130,246,0.3); padding:4px 8px; border-radius:6px; font-size:0.75rem; font-weight:700; display:inline-flex; align-items:center; gap:4px;">🏬 Tienda Local</span>`
                        : `<span style="background:rgba(255,127,0,0.12); color:#ff7f00; border:1px solid rgba(255,127,0,0.3); padding:4px 8px; border-radius:6px; font-size:0.75rem; font-weight:700; display:inline-flex; align-items:center; gap:4px;">🌐 Tienda en Línea</span>`;

                    let statusSelectorOrBadge = `<span style="font-weight:700; color:#fff;">${statusLabels[currentSt] || currentSt}</span>`;

                    if (isAdmin) {
                        statusSelectorOrBadge = `
                            <select onchange="updateSingleOrderStatus('${ord.folio}', this.value)" style="background:#171720; color:#fff; border:1px solid #2e2e3a; padding:6px 10px; border-radius:6px; font-size:0.85rem; font-weight:600;">
                                <option value="in_preparation" ${currentSt==='in_preparation'?'selected':''}>⏳ En Preparación / Almacén</option>
                                <option value="packed" ${currentSt==='packed'?'selected':''}>📦 Empacado (Etiqueta Ciega)</option>
                                <option value="in_transit" ${currentSt==='in_transit'?'selected':''}>🚚 En Ruta / Paquetera</option>
                                <option value="delivered" ${currentSt==='delivered'?'selected':''}>✅ Entregado / Confirmado</option>
                                <option value="canceled" ${(currentSt==='canceled'||currentSt==='cancelled')?'selected':''}>❌ Cancelado</option>
                            </select>
                        `;
                    }

                    html += `
                        <tr>
                            <td>
                                <strong style="color:var(--theme-accent, #ff7f00); font-family:monospace; font-size:0.95rem; letter-spacing:0.04em;">${escapeHtml(ord.folio)}</strong>
                            </td>
                            <td>${channelBadge}</td>
                            <td>
                                <div style="font-weight:700; color:#fff;">${escapeHtml(ord.customer_name || 'Cliente')}</div>
                                <div style="font-size:0.8rem; color:#888899; margin-top:2px;">Cód: ${escapeHtml(ord.user_code)} | ${escapeHtml(ord.customer_segment || 'menudeo')}</div>
                            </td>
                            <td style="color:#aaaab8; font-size:0.85rem;">${ord.issued_date ? ord.issued_date.substring(0,16) : 'N/A'}</td>
                            <td><strong style="font-size:0.95rem;">$${Number(ord.total_amount || 0).toFixed(2)}</strong></td>
                            <td>
                                ${ord.invoice_required ? '<span style="background:rgba(34,197,94,0.12); color:#4ade80; border:1px solid rgba(34,197,94,0.3); padding:4px 8px; border-radius:6px; font-size:0.75rem; font-weight:700;">Factura CFDI 4.0</span>' : '<span style="background:rgba(255,255,255,0.05); color:#aaa; border:1px solid #333; padding:4px 8px; border-radius:6px; font-size:0.75rem;">Nota de Venta</span>'}
                            </td>
                            <td>${statusSelectorOrBadge}</td>
                            <td style="text-align:center;">
                                <div style="display:flex; gap:0.4rem; justify-content:center; flex-wrap:wrap;">
                                    <a href="/api/print_blind_label.php?folio=${encodeURIComponent(ord.folio)}" target="_blank" class="btn-svg btn-dark" style="font-size:0.78rem;" title="Imprimir Etiqueta Ciega 4x6''">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                                        Etiqueta Ciega
                                    </a>
                                    <button class="btn-svg btn-accent" onclick="viewOrderLog('${ord.folio}')" style="font-size:0.78rem;" title="Ver historial de auditoría">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                                        Historial
                                    </button>
                                    <button class="btn-svg btn-dark" onclick="viewShippingTracking('${ord.folio}')" style="font-size:0.78rem;" title="Ver tracking de envío">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11"></polygon><path d="M23 11v8a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2v-8"></path></svg>
                                        Tracking
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                });

                tableBody.innerHTML = html;
            } catch (err) {
                console.error("loadTrackingOrders error:", err);
                tableBody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:2.5rem; color:#aaaab8;">Sin registros de pedidos.</td></tr>';
            }
        }

        async function updateSingleOrderStatus(folio, nextStatus) {
            const statusNames = {
                'in_preparation': 'En Preparación / Almacén',
                'packed': 'Empacado',
                'in_transit': 'En Ruta de Entrega',
                'delivered': 'Entregado / Confirmado',
                'canceled': 'Cancelado',
                'cancelled': 'Cancelado'
            };
            const friendly = statusNames[nextStatus] || nextStatus;
            const res = await apiCall('/admin_supply.php?action=update-order-status', 'POST', { folio: folio, status: nextStatus }, { silent: true });
            if (res && res.success) {
                showAlert(`¡Estatus del pedido ${folio} actualizado a: ${friendly}!`, 'success');
                loadTrackingOrders();
                if (document.getElementById('guestFolioInput') && document.getElementById('guestFolioInput').value.trim().toUpperCase() === folio.toUpperCase()) {
                    searchGuestOrder();
                }
            } else {
                showAlert(res?.message || 'Error al actualizar estatus', 'error');
            }
        }

        async function viewOrderLog(folio) {
            document.getElementById('logModalTitle').textContent = `Traza de Auditoría: Folio ${folio}`;
            document.getElementById('logModalBody').innerHTML = 'Cargando historial de eventos...';
            document.getElementById('logModal').style.display = 'flex';

            const res = await apiCall(`/api/order_log.php?folio=${encodeURIComponent(folio)}`, 'GET', null, { silent: true });
            if (!res || !res.success || !Array.isArray(res.logs) || res.logs.length === 0) {
                document.getElementById('logModalBody').innerHTML = '<div style="text-align:center; padding:2rem; color:#888;">No hay registros de cambios para este folio.</div>';
                return;
            }

            let html = '<div style="display:flex; flex-direction:column; gap:0.75rem;">';
            res.logs.forEach(lg => {
                html += `
                    <div style="background:#181820; border:1px solid #2a2a38; padding:0.85rem 1rem; border-radius:8px;">
                        <div style="display:flex; justify-content:space-between; font-size:0.82rem; color:var(--theme-accent, #ff7f00); font-weight:700;">
                            <span>Estatus: ${escapeHtml(lg.status)}</span>
                            <span style="color:#888899;">${escapeHtml(lg.created_at || '')}</span>
                        </div>
                        <div style="font-size:0.88rem; color:#fff; margin-top:0.3rem;">Usuario: <strong>${escapeHtml(lg.changed_by || 'Sistema')}</strong></div>
                        ${lg.notes ? `<div style="font-size:0.82rem; color:#aaaab8; margin-top:0.3rem; font-style:italic;">Nota: "${escapeHtml(lg.notes)}"</div>` : ''}
                    </div>
                `;
            });
            html += '</div>';
            document.getElementById('logModalBody').innerHTML = html;
        }

        function closeLogModal() { document.getElementById('logModal').style.display = 'none'; }
        function openConfirmArrivalModal() { document.getElementById('confirmArrivalModal').style.display = 'flex'; }
        function closeConfirmArrivalModal() { document.getElementById('confirmArrivalModal').style.display = 'none'; }
        function closeShippingTrackingModal() { document.getElementById('shippingTrackingModal').style.display = 'none'; }

        async function viewShippingTracking(folio) {
            document.getElementById('shippingTrackingBody').innerHTML = 'Cargando información de tracking...';
            document.getElementById('shippingTrackingModal').style.display = 'flex';

            try {
                const res = await apiCall(`/api/shipping_tracking.php?action=get&folio=${encodeURIComponent(folio)}`, 'GET', null, { silent: true });
                
                if (!res || !res.success) {
                    document.getElementById('shippingTrackingBody').innerHTML = `
                        <div style="text-align:center; padding:2rem; color:#888;">
                            <div style="font-size:2rem; margin-bottom:1rem;">📦</div>
                            <div>No hay información de tracking disponible para este pedido.</div>
                        </div>
                    `;
                    return;
                }

                const tracking = res.tracking;
                const carrierNames = {
                    'fedex': 'FedEx',
                    'dhl': 'DHL',
                    'estafeta': 'Estafeta',
                    'redpack': 'Redpack'
                };

                let eventsHTML = '';
                if (tracking.tracking_events && tracking.tracking_events.length > 0) {
                    // Timeline visual vertical
                    eventsHTML = `
                        <div style="position:relative; padding-left:2rem;">
                            <div style="position:absolute; left:0.5rem; top:0; bottom:0; width:2px; background:#333;"></div>
                            ${tracking.tracking_events.map((event, idx) => {
                                const statusColors = {
                                    'created': '#3b82f6',
                                    'confirmed': '#22c55e',
                                    'processing': '#f59e0b',
                                    'shipped': '#8b5cf6',
                                    'in_transit': '#06b6d4',
                                    'delivered': '#10b981',
                                    'cancelled': '#ef4444'
                                };
                                const statusColor = statusColors[event.status] || '#888';
                                const isLast = idx === tracking.tracking_events.length - 1;
                                
                                return `
                                    <div style="position:relative; padding-bottom:1.5rem; ${isLast ? 'padding-bottom:0;' : ''}">
                                        <div style="position:absolute; left:-1.6rem; top:0; width:1rem; height:1rem; background:${statusColor}; border-radius:50%; border:3px solid #14141a; z-index:1;"></div>
                                        <div style="background:#181820; border:1px solid #2a2a38; padding:1rem; border-radius:8px;">
                                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                                                <span style="font-size:0.85rem; font-weight:700; color:${statusColor}; text-transform:uppercase;">${escapeHtml(event.status)}</span>
                                                <span style="font-size:0.75rem; color:#888899;">${escapeHtml(event.timestamp || '')}</span>
                                            </div>
                                            <div style="font-size:0.9rem; color:#fff; margin-bottom:0.3rem;">${escapeHtml(event.description || '')}</div>
                                            ${event.location ? `<div style="font-size:0.82rem; color:#aaaab8;">📍 ${escapeHtml(event.location)}</div>` : ''}
                                        </div>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    `;
                } else {
                    eventsHTML = '<div style="text-align:center; padding:1.5rem; color:#888; background:#181820; border-radius:8px;">No hay eventos de tracking disponibles.</div>';
                }

                document.getElementById('shippingTrackingBody').innerHTML = `
                    <div style="background:#121217; border:1px solid #22222a; border-radius:12px; padding:1.25rem; margin-bottom:1rem;">
                        <div style="font-size:0.85rem; color:#888899; margin-bottom:0.5rem;">Paquetería</div>
                        <div style="font-size:1.1rem; font-weight:700; color:#fff;">${carrierNames[tracking.carrier] || tracking.carrier || 'N/A'}</div>
                    </div>
                    <div style="background:#121217; border:1px solid #22222a; border-radius:12px; padding:1.25rem; margin-bottom:1rem;">
                        <div style="font-size:0.85rem; color:#888899; margin-bottom:0.5rem;">Número de Tracking</div>
                        <div style="font-size:1.1rem; font-weight:700; color:#ff7f00; font-family:monospace;">${escapeHtml(tracking.tracking_number)}</div>
                    </div>
                    <div style="background:#121217; border:1px solid #22222a; border-radius:12px; padding:1.25rem; margin-bottom:1rem;">
                        <div style="font-size:0.85rem; color:#888899; margin-bottom:0.5rem;">Estado Actual</div>
                        <div style="font-size:1.1rem; font-weight:700; color:#fff;">${escapeHtml(tracking.tracking_status)}</div>
                    </div>
                    <div style="margin-top:1.5rem;">
                        <h4 style="margin:0 0 1rem 0; color:#fff; font-size:0.95rem;">Historial de Eventos</h4>
                        ${eventsHTML}
                    </div>
                `;
            } catch (err) {
                console.error("viewShippingTracking error:", err);
                document.getElementById('shippingTrackingBody').innerHTML = '<div style="text-align:center; padding:2rem; color:#f87171;">Error al cargar información de tracking.</div>';
            }
        }

        async function submitArrivalConfirmation() {
            const folio = document.getElementById('confirmFolioInput').value.trim();
            const notes = document.getElementById('confirmNotesInput').value.trim();

            if (!folio) {
                showAlert('Ingresa el folio del pedido', 'warning');
                return;
            }

            const res = await apiCall('/admin_supply.php?action=update-order-status', 'POST', { folio: folio, status: 'delivered', notes: notes });
            if (res && res.success) {
                closeConfirmArrivalModal();
                showAlert('¡Entrega confirmada y validada exitosamente!', 'success');
                loadTrackingOrders();
            } else {
                showAlert(res?.message || 'Error al confirmar llegada', 'error');
            }
        }

        // ── Guest order search (no login required) ───────────────────────────
        async function searchGuestOrder() {
            const folio  = (document.getElementById('guestFolioInput')?.value || '').trim();
            const result = document.getElementById('guestFolioResult');
            if (!folio) {
                result.innerHTML = `<div style="display:flex;align-items:center;gap:.5rem;color:#f87171;font-size:.9rem;padding:.6rem 0;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    Por favor ingresa el número de folio o ticket.
                </div>`;
                return;
            }
            result.innerHTML = `<div style="display:flex;align-items:center;gap:.6rem;color:#888899;padding:.75rem 0;font-size:.9rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ff7f00" stroke-width="2.5" style="animation:spin 1s linear infinite;flex-shrink:0;"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38"/></svg>
                Consultando tu pedido...
            </div>`;
            try {
                const url = `/admin_supply.php?action=order-tracking-list&search=${encodeURIComponent(folio)}&_=${Date.now()}`;
                const res = await apiCall(url, 'GET', null, { silent: true });
                if (!res || !res.orders || res.orders.length === 0) {
                    result.innerHTML = `<div style="background:rgba(239,68,68,.07);border:1px solid rgba(239,68,68,.2);border-radius:12px;padding:1.25rem;display:flex;gap:.75rem;align-items:flex-start;">
                        <div style="font-size:1.5rem;flex-shrink:0;">🔍</div>
                        <div>
                            <div style="font-weight:700;color:#f87171;margin-bottom:.25rem;">No se encontró ningún pedido</div>
                            <div style="font-size:.88rem;color:#888899;">Verifica que el número de folio sea correcto. Ejemplo: <code style="background:#1a1a22;padding:2px 6px;border-radius:4px;color:#ff7f00;">FOX-2026-884912</code></div>
                        </div>
                    </div>`;
                    return;
                }

                const ord = res.orders[0];
                const statusConfig = {
                    'in_preparation': { label: 'En Preparación', icon: '⏳', color: '#eab308', bg: 'rgba(234,179,8,.12)', border: 'rgba(234,179,8,.3)', desc: 'Tu pedido está siendo preparado y revisado en almacén.' },
                    'packed':         { label: 'Empacado',       icon: '📦', color: '#a78bfa', bg: 'rgba(167,139,250,.12)', border: 'rgba(167,139,250,.3)', desc: 'Tu pedido ya está empacado con etiqueta ciega y listo para envío.' },
                    'in_transit':     { label: 'En Ruta / Tránsito', icon: '🚚', color: '#38bdf8', bg: 'rgba(56,189,248,.12)', border: 'rgba(56,189,248,.3)', desc: 'Tu pedido está en camino a tu domicilio.' },
                    'shipped':        { label: 'Enviado por Paquetería', icon: '🚚', color: '#38bdf8', bg: 'rgba(56,189,248,.12)', border: 'rgba(56,189,248,.3)', desc: 'Tu pedido fue despachado y se encuentra en ruta.' },
                    'delivered':      { label: 'Entregado',      icon: '✅', color: '#4ade80', bg: 'rgba(74,222,128,.12)', border: 'rgba(74,222,128,.3)', desc: '¡Tu pedido fue entregado exitosamente!' },
                    'canceled':       { label: 'Cancelado',      icon: '❌', color: '#f87171', bg: 'rgba(248,113,113,.12)', border: 'rgba(248,113,113,.3)', desc: 'Este pedido fue cancelado. Contáctanos para más información.' },
                    'cancelled':      { label: 'Cancelado',      icon: '❌', color: '#f87171', bg: 'rgba(248,113,113,.12)', border: 'rgba(248,113,113,.3)', desc: 'Este pedido fue cancelado. Contáctanos para más información.' }
                };

                const rawStatus = ord.order_status || 'in_preparation';
                let stepKey = rawStatus;
                if (rawStatus === 'shipped') stepKey = 'in_transit';
                if (rawStatus === 'pending' || rawStatus === 'confirmed' || rawStatus === 'processing') stepKey = 'in_preparation';

                const st = statusConfig[rawStatus] || statusConfig[stepKey] || { label: rawStatus, icon: '📋', color: '#888', bg: 'rgba(255,255,255,.05)', border: 'rgba(255,255,255,.1)', desc: '' };

                // ── Build status timeline (4 steps standard) ─────────────
                const steps = ['in_preparation','packed','in_transit','delivered'];
                const curIdx = steps.indexOf(stepKey);
                const isCanceled = rawStatus === 'canceled' || rawStatus === 'cancelled';

                const timelineHTML = isCanceled ? `
                    <div style="display:flex;align-items:center;justify-content:center;gap:.5rem;padding:.75rem;background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.2);border-radius:10px;color:#f87171;font-weight:700;">
                        ❌ Pedido Cancelado
                    </div>` : `
                    <div style="display:flex;align-items:center;gap:0;">
                        ${steps.map((step, i) => {
                            const done   = i <= curIdx;
                            const active = i === curIdx;
                            const cfg    = statusConfig[step];
                            const lineColor = i < curIdx ? '#ff7f00' : 'rgba(255,255,255,.1)';
                            return `
                            <div style="display:flex;align-items:center;flex:1;">
                                <div style="display:flex;flex-direction:column;align-items:center;gap:.3rem;min-width:0;flex-shrink:0;">
                                    <div style="width:${active?'42px':'36px'};height:${active?'42px':'36px'};border-radius:50%;
                                        background:${done ? (active ? '#ff7f00' : 'rgba(255,127,0,.25)') : '#1a1a24'};
                                        border:2px solid ${done ? '#ff7f00' : 'rgba(255,255,255,.12)'};
                                        display:flex;align-items:center;justify-content:center;
                                        font-size:${active?'1.1rem':'.9rem'};
                                        box-shadow:${active ? '0 0 18px rgba(255,127,0,.5)' : 'none'};
                                        transition:all .3s;flex-shrink:0;">
                                        ${cfg.icon}
                                    </div>
                                    <div style="font-size:.65rem;font-weight:${active?'800':'600'};color:${done ? (active ? '#ff7f00' : '#aaa') : '#555'};text-align:center;line-height:1.2;max-width:60px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                        ${cfg.label}
                                    </div>
                                </div>
                                ${i < steps.length-1 ? `<div style="flex:1;height:3px;background:linear-gradient(90deg,${lineColor},${i < curIdx-1 ? '#ff7f00' : 'rgba(255,255,255,.1)'});border-radius:2px;margin:0 4px;margin-bottom:20px;"></div>` : ''}
                            </div>`;
                        }).join('')}
                    </div>`;

                // ── Build items table ────────────────────────────────────
                const items = Array.isArray(ord.items) ? ord.items : [];
                const itemsHTML = items.length > 0 ? `
                    <div style="margin-top:1.25rem;">
                        <div style="font-size:.78rem;font-weight:700;color:#888899;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.65rem;display:flex;align-items:center;gap:.4rem;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                            Productos del Pedido (${items.length})
                        </div>
                        <div style="background:#0d0d12;border:1px solid #1e1e2a;border-radius:10px;overflow:hidden;">
                            <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
                                <thead>
                                    <tr style="background:#131319;border-bottom:1px solid #1e1e2a;">
                                        <th style="padding:.6rem .9rem;text-align:left;color:#666;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;font-weight:700;">Producto</th>
                                        <th style="padding:.6rem .9rem;text-align:center;color:#666;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;font-weight:700;">Cant.</th>
                                        <th style="padding:.6rem .9rem;text-align:right;color:#666;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;font-weight:700;">Precio</th>
                                        <th style="padding:.6rem .9rem;text-align:right;color:#666;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;font-weight:700;">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${items.map((it,i) => `
                                     <tr style="border-bottom:${i<items.length-1?'1px solid #181824':'none'};">
                                        <td style="padding:.65rem .9rem;color:#e0e0ea;font-weight:600;">${escapeHtml(it.product_name||'Producto')}</td>
                                        <td style="padding:.65rem .9rem;text-align:center;color:#aaa;">${it.quantity||1}</td>
                                        <td style="padding:.65rem .9rem;text-align:right;color:#aaa;">$${Number(it.unit_price||0).toFixed(2)}</td>
                                        <td style="padding:.65rem .9rem;text-align:right;color:#ff7f00;font-weight:700;">$${Number(it.total||0).toFixed(2)}</td>
                                    </tr>`).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>` : '';

                // ── Build address section ────────────────────────────────
                const addr = ord.shipping_address || {};
                const hasAddr = Object.values(addr).some(v => v && String(v).trim());
                const addrHTML = hasAddr ? `
                    <div>
                        <div style="font-size:.72rem;font-weight:700;color:#888899;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.4rem;display:flex;align-items:center;gap:.35rem;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            Dirección de Entrega
                        </div>
                        <div style="font-size:.88rem;color:#ccc;line-height:1.6;">
                            ${[addr.street||addr.calle||addr.direccion||addr.address, addr.neighborhood||addr.colonia, addr.city||addr.ciudad, addr.state||addr.estado, addr.zip||addr.postal_code||addr.cp].filter(Boolean).map(escapeHtml).join(', ')||'—'}
                        </div>
                    </div>` : '';

                // ── Build tracking folio section ─────────────────────────
                const carrierHTML = ord.carrier ? `
                    <div style="background:#0d0d12;border:1px solid rgba(255,127,0,0.25);border-radius:10px;padding:.8rem .9rem;">
                        <div style="font-size:.68rem;color:#888899;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.3rem;display:flex;align-items:center;gap:4px;">
                            🚚 Paquetería
                        </div>
                        <div style="font-size:1rem;font-weight:800;color:#ff7f00;">${escapeHtml(ord.carrier)}</div>
                    </div>` : '';

                const trackingHTML = ord.tracking_folio ? `
                    <div style="background:#0d0d12;border:1px solid rgba(56,189,248,.25);border-radius:10px;padding:.8rem .9rem;">
                        <div style="font-size:.68rem;color:#888899;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.3rem;display:flex;align-items:center;gap:4px;">
                            📦 Guía / Rastreo
                        </div>
                        <div style="font-family:monospace;font-size:.95rem;font-weight:800;color:#38bdf8;letter-spacing:.05em;">${escapeHtml(ord.tracking_folio)}</div>
                    </div>` : '';

                const deliveryHTML = ord.estimated_delivery ? `
                    <div style="background:#0d0d12;border:1px solid rgba(34,197,94,0.25);border-radius:10px;padding:.8rem .9rem;">
                        <div style="font-size:.68rem;color:#888899;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.3rem;display:flex;align-items:center;gap:4px;">
                            📅 Entrega Estimada
                        </div>
                        <div style="font-size:.92rem;font-weight:700;color:#4ade80;">${escapeHtml(ord.estimated_delivery.substring(0,10))}</div>
                    </div>` : '';

                // ── Build history timeline ───────────────────────────────
                const history = Array.isArray(ord.history) ? ord.history : [];
                const histHTML = history.length > 0 ? `
                    <div style="margin-top:1.25rem;">
                        <div style="font-size:.78rem;font-weight:700;color:#888899;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.65rem;display:flex;align-items:center;gap:.4rem;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            Historial y Actualizaciones del Pedido
                        </div>
                        <div style="display:flex;flex-direction:column;gap:.5rem;">
                            ${history.map(h => {
                                const hCfg = statusConfig[h.status] || { label: h.status, icon: '📋', color: '#aaa' };
                                return `<div style="display:flex;gap:.75rem;align-items:flex-start;background:#0f0f15;border:1px solid #1a1a24;border-radius:8px;padding:0.6rem 0.85rem;">
                                    <div style="width:28px;height:28px;border-radius:50%;background:rgba(255,127,0,.1);border:1px solid rgba(255,127,0,.2);display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0;">${hCfg.icon}</div>
                                    <div style="flex:1;">
                                        <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap;">
                                            <span style="font-weight:700;font-size:.85rem;color:${hCfg.color};">${hCfg.label}</span>
                                            <span style="font-size:.74rem;color:#777;">${(h.created_at||'').substring(0,16)}</span>
                                        </div>
                                        ${h.notes ? `<div style="font-size:.82rem;color:#bbb;margin-top:.2rem;">${escapeHtml(h.notes)}</div>` : ''}
                                        ${h.changed_by ? `<div style="font-size:.72rem;color:#666;margin-top:.15rem;">Actualizado por: ${escapeHtml(h.changed_by)}</div>` : ''}
                                    </div>
                                </div>`;
                            }).join('')}
                        </div>
                    </div>` : '';

                // ── Final render ─────────────────────────────────────────
                result.innerHTML = `
                <div style="margin-top:.5rem;animation:fadeInUp .35s ease;">
                    <!-- Header con folio + estado -->
                    <div style="background:linear-gradient(135deg,rgba(20,20,28,.95),rgba(12,12,18,.98));border:1px solid ${st.border};border-radius:16px;overflow:hidden;box-shadow:0 12px 40px rgba(0,0,0,.4);">
                        <!-- Status banner -->
                        <div style="background:${st.bg};border-bottom:1px solid ${st.border};padding:.85rem 1.25rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
                            <div style="display:flex;align-items:center;gap:.6rem;">
                                <span style="font-size:1.4rem;">${st.icon}</span>
                                <div>
                                    <div style="font-weight:800;font-size:1rem;color:${st.color};">${st.label}</div>
                                    <div style="font-size:.8rem;color:#888;">${st.desc}</div>
                                </div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:.68rem;color:#666;text-transform:uppercase;letter-spacing:.05em;">Folio de Pedido</div>
                                <div style="font-size:.98rem;font-weight:800;color:#ff7f00;font-family:monospace;">${escapeHtml(ord.folio)}</div>
                            </div>
                        </div>

                        <!-- Barra de Acciones y Descarga de Ticket -->
                        <div style="background:rgba(255,127,0,0.06);border-bottom:1px solid rgba(255,127,0,0.18);padding:.85rem 1.25rem;display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;">
                            <div style="font-size:0.88rem;color:#e0e0ea;display:flex;align-items:center;gap:8px;">
                                <span style="font-size:1.2rem;">🎟️</span> <strong>Comprobante Oficial:</strong> Puedes descargar o imprimir el ticket de tu pedido.
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                <a href="/ticket_client.php?folio=${encodeURIComponent(ord.folio)}&auto_pdf=1" target="_blank" style="background:linear-gradient(135deg,#ff8f00,#e05c00);color:#fff;padding:8px 18px;border-radius:20px;font-weight:800;font-size:0.86rem;text-decoration:none;display:inline-flex;align-items:center;gap:6px;box-shadow:0 3px 14px rgba(255,127,0,0.38);transition:all .2s;">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                    Descargar Ticket (PDF)
                                </a>
                                <a href="/ticket_client.php?folio=${encodeURIComponent(ord.folio)}" target="_blank" style="background:#14141e;border:1px solid rgba(255,255,255,0.18);color:#e0e0ea;padding:8px 14px;border-radius:20px;font-weight:700;font-size:0.82rem;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                                    🖨️ Ver / Imprimir
                                </a>
                                ${ord.invoice_required ? `
                                <a href="/api/invoice.php?action=download_pdf&folio=${encodeURIComponent(ord.folio)}" target="_blank" style="background:#2563eb;color:#fff;padding:8px 14px;border-radius:20px;font-weight:700;font-size:0.82rem;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                                    📕 Factura SAT
                                </a>` : ''}
                            </div>
                        </div>

                        <div style="padding:1.25rem;display:flex;flex-direction:column;gap:1.1rem;">

                            <!-- Timeline de estatus -->
                            <div>
                                <div style="font-size:.78rem;font-weight:700;color:#888899;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.85rem;display:flex;align-items:center;gap:.4rem;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                                    Progreso del Envío
                                </div>
                                ${timelineHTML}
                            </div>

                            <!-- Info grid -->
                            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:.75rem;">
                                <div style="background:#0d0d12;border:1px solid #1a1a22;border-radius:10px;padding:.8rem .9rem;">
                                    <div style="font-size:.68rem;color:#555;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.3rem;">Cliente</div>
                                    <div style="font-weight:700;color:#f0f0f5;font-size:.9rem;">${escapeHtml(ord.customer_name||'—')}</div>
                                </div>
                                <div style="background:#0d0d12;border:1px solid #1a1a22;border-radius:10px;padding:.8rem .9rem;">
                                    <div style="font-size:.68rem;color:#555;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.3rem;">Fecha</div>
                                    <div style="font-weight:600;color:#ccc;font-size:.88rem;">${(ord.issued_date||'—').substring(0,16).replace('T',' ')}</div>
                                </div>
                                <div style="background:#0d0d12;border:1px solid #1a1a22;border-radius:10px;padding:.8rem .9rem;">
                                    <div style="font-size:.68rem;color:#555;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.3rem;">Total</div>
                                    <div style="font-weight:800;color:#ff7f00;font-size:1.05rem;">$${Number(ord.total_amount||0).toFixed(2)}</div>
                                </div>
                                <div style="background:#0d0d12;border:1px solid #1a1a22;border-radius:10px;padding:.8rem .9rem;">
                                    <div style="font-size:.68rem;color:#555;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.3rem;">Comprobante</div>
                                    <div style="font-size:.84rem;color:#ccc;font-weight:600;display:flex;align-items:center;justify-content:space-between;gap:6px;">
                                        <span>${ord.invoice_required ? '🧾 Factura CFDI 4.0' : '📄 Nota de Venta'}</span>
                                        <a href="/ticket_client.php?folio=${encodeURIComponent(ord.folio)}&auto_pdf=1" target="_blank" style="color:#ff7f00;font-size:0.75rem;font-weight:800;text-decoration:none;" title="Descargar Ticket PDF">Descargar ⬇️</a>
                                    </div>
                                </div>
                                ${carrierHTML}
                                ${trackingHTML}
                                ${deliveryHTML}
                                ${addrHTML ? `<div style="background:#0d0d12;border:1px solid #1a1a22;border-radius:10px;padding:.8rem .9rem;grid-column:1/-1;">${addrHTML}</div>` : ''}
                            </div>

                            <!-- Productos -->
                            ${itemsHTML}

                            <!-- Historial -->
                            ${histHTML}

                        </div>
                    </div>
                </div>`;

            } catch(err) {
                console.error('searchGuestOrder error:', err);
                result.innerHTML = `<div style="background:rgba(239,68,68,.07);border:1px solid rgba(239,68,68,.2);border-radius:10px;padding:1rem;color:#f87171;font-size:.9rem;">
                    Error al consultar el pedido. Por favor intenta nuevamente.
                </div>`;
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const initialFolio = (urlParams.get('folio') || urlParams.get('search') || '').trim();
            if (initialFolio) {
                const guestInput = document.getElementById('guestFolioInput');
                if (guestInput) {
                    guestInput.value = initialFolio;
                    searchGuestOrder();
                    if (urlParams.get('download') === '1' || urlParams.get('auto_pdf') === '1') {
                        setTimeout(() => {
                            const frame = document.createElement('iframe');
                            frame.style.display = 'none';
                            frame.src = `/ticket_client.php?folio=${encodeURIComponent(initialFolio)}&auto_pdf=1`;
                            document.body.appendChild(frame);
                        }, 800);
                    }
                }
                const searchInput = document.getElementById('searchInput');
                if (searchInput) {
                    searchInput.value = initialFolio;
                }
            }
            if (document.getElementById('ordersTableBody')) {
                loadTrackingOrders();
                setInterval(loadTrackingOrders, 8000);
            }
        });
    </script>
    <style>
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes fadeInUp { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:translateY(0); } }
    </style>
    <script src="js/modals.js"></script>
</body>
</html>
