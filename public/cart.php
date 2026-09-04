<?php
require_once '../config/config.php';

$isLogged = isset($_SESSION['user_id']);
$isAdmin = $isLogged && (($_SESSION['role'] ?? '') === 'admin' || ($_SESSION['role'] ?? '') === 'employee');
$is_admin = $isAdmin;
$isOnlineMode = ($_GET['mode'] ?? '') === 'online';

$clientTicketCode = 'PUBLICO';
if ($isLogged && db_column_exists('users', 'user_code')) {
    try {
        $stmtUserCode = $pdo->prepare("SELECT COALESCE(user_code, '') AS user_code FROM users WHERE id = ? LIMIT 1");
        $stmtUserCode->execute([$_SESSION['user_id']]);
        $userData = $stmtUserCode->fetch();
        if (!empty($userData['user_code'])) {
            $clientTicketCode = (string)$userData['user_code'];
        }
    } catch (Exception $ignored) {
        $clientTicketCode = 'PUBLICO';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Mi Carrito - Ferretería FOX</title>
    <link rel="stylesheet" href="css/styles.css?v=4.1">
    <link rel="stylesheet" href="css/theme.css?v=4.1">
    <link rel="stylesheet" href="css/toast-notifications.css">
    <link rel="stylesheet" href="css/responsive-complete.css?v=5.0">
    <style>
        /* ===== Cart Page — Premium Redesign ===== */
        body {
            background: #08080a !important;
            color: #ffffff !important;
        }

        .cart-page {
            padding: 2.5rem 1.5rem !important;
            max-width: 1200px;
            margin: 0 auto;
        }

        .cart-page-header {
            margin-bottom: 2.5rem !important;
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            flex-wrap: wrap !important;
            gap: 1rem !important;
            border-bottom: 1px solid #222222 !important;
            padding-bottom: 1.25rem !important;
        }

        .cart-page-title {
            margin: 0 !important;
            font-size: 2.25rem !important;
            font-weight: 800 !important;
            color: #ffffff !important;
            background: linear-gradient(90deg, #ffffff, #ffb347) !important;
            -webkit-background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
            letter-spacing: -0.02em !important;
        }

        .cart-page-back {
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.5rem !important;
            color: var(--theme-accent, #ff7f00) !important;
            text-decoration: none !important;
            font-weight: 700 !important;
            font-size: 0.95rem !important;
            transition: all 0.2s ease !important;
            background: rgba(255, 127, 0, 0.1) !important;
            padding: 0.5rem 1rem !important;
            border-radius: 999px !important;
            border: 1px solid rgba(255, 127, 0, 0.2) !important;
        }

        .cart-page-back:hover {
            background: var(--theme-accent, #ff7f00) !important;
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(255,127,0,0.3) !important;
            text-decoration: none !important;
        }

        .cart-container {
            display: grid !important;
            grid-template-columns: 1fr 350px !important;
            gap: 2rem !important;
        }

        .cart-items-section {
            background: #111111 !important;
            border: 1px solid #222222 !important;
            border-radius: 20px !important;
            padding: 1.5rem !important;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4) !important;
        }

        .cart-empty {
            text-align: center !important;
            padding: 4rem 2rem !important;
            color: #888888 !important;
        }

        .cart-empty-icon {
            font-size: 3.5rem !important;
            margin-bottom: 1.25rem !important;
            display: block;
        }

        .cart-empty-text {
            font-size: 1.15rem !important;
            margin-bottom: 1.5rem !important;
            color: #aaaaaa !important;
        }

        .cart-empty-btn {
            display: inline-block !important;
            background: linear-gradient(90deg, #ff6600, #ff9500) !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            padding: 0.75rem 2rem !important;
            border-radius: 999px !important;
            text-decoration: none !important;
            box-shadow: 0 4px 10px rgba(255, 102, 0, 0.2) !important;
            transition: all 0.2s ease !important;
        }

        .cart-empty-btn:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(255, 102, 0, 0.35) !important;
            color: #ffffff !important;
        }

        .cart-item {
            display: grid !important;
            grid-template-columns: 90px 1fr auto !important;
            gap: 1.5rem !important;
            padding: 1.5rem 0 !important;
            border-bottom: 1px solid #222222 !important;
            align-items: center !important;
        }

        .cart-item:first-child {
            padding-top: 0 !important;
        }

        .cart-item:last-child {
            border-bottom: none !important;
            padding-bottom: 0 !important;
        }

        .cart-item-image {
            width: 90px !important;
            height: 90px !important;
            background: #0a0a0c !important;
            border: 1px solid #222222 !important;
            border-radius: 12px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            overflow: hidden !important;
        }

        .cart-item-image img {
            width: 100% !important;
            height: 100% !important;
            object-fit: contain !important;
            padding: 0.5rem !important;
        }

        .cart-item-details {
            display: flex !important;
            flex-direction: column !important;
            gap: 0.35rem !important;
        }

        .cart-item-name {
            font-size: 1.1rem !important;
            font-weight: 700 !important;
            color: #ffffff !important;
            margin: 0 !important;
            line-height: 1.4 !important;
        }

        .cart-item-sku {
            font-size: 0.85rem !important;
            color: #666666 !important;
        }

        .cart-item-sku strong {
            color: #888888 !important;
        }

        .cart-item-price {
            font-size: 1.2rem !important;
            font-weight: 800 !important;
            color: var(--theme-accent, #ff7f00) !important;
        }

        .cart-item-actions {
            display: flex !important;
            align-items: center !important;
            gap: 0.75rem !important;
            flex-wrap: nowrap !important;
        }

        .qty-control {
            display: flex !important;
            align-items: center !important;
            border: 1px solid #2a2a30 !important;
            border-radius: 8px !important;
            background: #0d0d0d !important;
            overflow: hidden;
        }

        .qty-btn {
            width: 36px !important;
            height: 36px !important;
            border: none !important;
            background: transparent !important;
            color: #ffffff !important;
            cursor: pointer !important;
            font-weight: bold !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            transition: background 0.2s ease !important;
            font-size: 1.1rem !important;
        }

        .qty-btn:hover {
            background: #222222 !important;
        }

        .qty-input {
            width: 45px !important;
            border: none !important;
            background: transparent !important;
            text-align: center !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            font-size: 0.95rem !important;
            -moz-appearance: textfield !important;
        }

        .qty-input::-webkit-outer-spin-button,
        .qty-input::-webkit-inner-spin-button {
            -webkit-appearance: none !important;
            margin: 0 !important;
        }

        .remove-btn {
            width: 36px !important;
            height: 36px !important;
            border: none !important;
            background: rgba(239, 68, 68, 0.1) !important;
            border: 1px solid rgba(239, 68, 68, 0.2) !important;
            color: #ef4444 !important;
            border-radius: 8px !important;
            cursor: pointer !important;
            font-size: 1rem !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            transition: all 0.2s ease !important;
        }

        .remove-btn:hover {
            background: #ef4444 !important;
            border-color: #ef4444 !important;
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2) !important;
        }

        .cart-summary {
            background: #111111 !important;
            border: 1px solid #222222 !important;
            border-radius: 20px !important;
            padding: 1.75rem !important;
            position: sticky !important;
            top: 24px !important;
            height: fit-content !important;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4) !important;
        }

        .summary-title {
            font-size: 1.35rem !important;
            font-weight: 800 !important;
            margin-bottom: 1.5rem !important;
            color: #ffffff !important;
            border-left: 4px solid var(--theme-accent, #ff7f00) !important;
            padding-left: 0.75rem !important;
            line-height: 1.2 !important;
        }

        .summary-row {
            display: flex !important;
            justify-content: space-between !important;
            margin-bottom: 1rem !important;
            color: #aaaaaa !important;
            font-size: 0.95rem !important;
        }

        .summary-row span:last-child {
            color: #ffffff !important;
            font-weight: 600 !important;
        }

        .summary-total {
            border-top: 1px solid #222222 !important;
            padding-top: 1.25rem !important;
            margin-top: 1.25rem !important;
            display: flex !important;
            justify-content: space-between !important;
            font-weight: 800 !important;
            font-size: 1.4rem !important;
            color: var(--theme-accent, #ff7f00) !important;
        }

        .summary-actions {
            display: flex !important;
            flex-direction: column !important;
            gap: 0.8rem !important;
            margin-top: 1.75rem !important;
        }

        .summary-actions .btn-primary {
            background: linear-gradient(90deg, #ff6600, #ff9500) !important;
            border: none !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            padding: 0.75rem 1.25rem !important;
            border-radius: 999px !important;
            font-size: 0.95rem !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            box-shadow: 0 4px 10px rgba(255, 102, 0, 0.2) !important;
            text-align: center;
        }

        .summary-actions .btn-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 15px rgba(255, 102, 0, 0.35) !important;
            background: linear-gradient(90deg, #ff7711, #ffa522) !important;
        }

        .summary-actions .btn-secondary {
            background: #1a1a1a !important;
            border: 1px solid #2a2a2a !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            padding: 0.75rem 1.25rem !important;
            border-radius: 999px !important;
            font-size: 0.95rem !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            text-align: center;
        }

        .summary-actions .btn-secondary:hover {
            background: var(--theme-accent, #ff7f00) !important;
            border-color: var(--theme-accent, #ff7f00) !important;
            box-shadow: 0 4px 12px rgba(255, 127, 0, 0.3) !important;
        }

        .summary-actions .btn-ghost {
            background: transparent !important;
            border: 1px solid transparent !important;
            color: #888888 !important;
            font-weight: 600 !important;
            padding: 0.75rem 1.25rem !important;
            border-radius: 999px !important;
            font-size: 0.95rem !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            text-align: center;
        }

        .summary-actions .btn-ghost:hover {
            background: rgba(239, 68, 68, 0.1) !important;
            border-color: rgba(239, 68, 68, 0.2) !important;
            color: #ef4444 !important;
        }

        @media (max-width: 768px) {
            .cart-container { grid-template-columns: 1fr !important; }
            .cart-summary { position: static !important; }
            .cart-item {
                grid-template-columns: 70px 1fr !important;
                gap: 1rem !important;
            }
            .cart-item-actions {
                grid-column: 1 / span 2;
                justify-content: flex-end;
                margin-top: 0.5rem;
            }
            .cart-item-image { width: 70px !important; height: 70px !important; }
        }
    </style>
</head><body class="catalog-minimal">
    <?php if ($isOnlineMode): ?>
    <!-- Barra de regreso — igual que Tienda en Línea -->
    <div style="background:linear-gradient(90deg,rgba(18,18,24,.98),rgba(10,10,14,.99));border-bottom:1px solid rgba(255,127,0,.2);padding:.5rem 1.4rem;display:flex;align-items:center;gap:1rem;">
        <a href="tienda.php" style="display:inline-flex;align-items:center;gap:6px;color:#ff7f00;font-weight:700;font-size:.84rem;text-decoration:none;padding:5px 14px;border:1px solid rgba(255,127,0,.3);border-radius:8px;background:rgba(255,127,0,.07);transition:all .18s;"
            onmouseenter="this.style.background='rgba(255,127,0,.18)';this.style.borderColor='#ff7f00';this.style.color='#fff'"
            onmouseleave="this.style.background='rgba(255,127,0,.07)';this.style.borderColor='rgba(255,127,0,.3)';this.style.color='#ff7f00'">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            Regresar a la Tienda en Línea
        </a>
        <span style="font-size:.73rem;font-weight:700;color:#ff7f00;text-transform:uppercase;letter-spacing:.05em;opacity:.7;">Carrito de Tienda en Línea</span>
    </div>
    <?php endif; ?>

    <header>
        <div class="header-content">
            <a href="<?php echo $isOnlineMode ? 'tienda.php' : 'index.php'; ?>" class="logo"><img src="img/logo_fox.png" alt="Ferretería FOX" style="height: 42px; width: auto; object-fit: contain;"></a>
            <button class="hamburger-btn" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <nav class="nav-menu">
                <?php if ($isOnlineMode): ?>
                    <a href="tienda.php">Tienda en Línea</a>
                    <a href="marketplace_ce.php?mode=online">Marketplace CE</a>
                    <a href="order_tracking.php?mode=online">Seguimiento de Pedido</a>
                    <a href="cart.php?mode=online" class="active">Carrito</a>
                <?php else: ?>
                    <a href="index.php">Productos</a>
                    <a href="marketplace_ce.php">Marketplace CE</a>
                    <a href="cart.php" class="active">Carrito</a>
                <?php endif; ?>

                <?php if ($isLogged && !$isOnlineMode): ?>
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
                <?php endif; ?>
                <?php if ($is_admin && !$isOnlineMode): ?>
                    <!-- Dropdowns de Administración Separados -->
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Admin Tienda <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content" style="min-width: 200px;">
                        <a href="orders.php">Ventas / Pedidos</a>
                        <a href="order_tracking.php">Seguimiento / Logística</a>
                        <a href="rma_manager.php">Devoluciones RMA</a>
                        <a href="admin_online_billing.php">Facturación & Pagos SAT</a>
                    </div>
                </div>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Admin Local <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content" style="min-width: 200px;">
                        <a href="cashier.php">Caja / Punto de Venta</a>
                        <a href="b2b_approval.php">Aprobación B2B</a>
                        <a href="tickets.php">Tickets y Cotizaciones</a>
                        <a href="ticket_validation.php">Validación de Tickets</a>
                        <a href="tasks.php">Tareas de Empleados</a>
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
            <div class="header-actions">
                <?php if (!empty(whatsapp_phone_digits())): ?>
                <a href="https://wa.me/<?php echo htmlspecialchars(whatsapp_phone_digits(), ENT_QUOTES, 'UTF-8'); ?>?text=Hola%2C+tengo+una+duda+sobre+mi+carrito." target="_blank" rel="noopener" class="btn btn-secondary btn-small">Dudas por WhatsApp</a>
                <?php endif; ?>
                <?php if (!$isLogged && !$isOnlineMode): ?>
                    <a href="admin_login.php" class="btn btn-primary btn-small">Solo para administradores</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="cart-page">
        <!-- ── Back Button ── -->
        <div class="back-header">
            <?php if ($isOnlineMode): ?>
                <a href="/tienda.php" class="btn-back btn-back-dark" style="text-decoration:none; display:inline-flex; align-items:center;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg> Regresar a la Tienda
                </a>
            <?php else: ?>
                <button onclick="history.back()" style="display:inline-flex; align-items:center; gap:6px; color:#ff7f00; font-weight:700; font-size:.85rem; text-decoration:none; padding:8px 20px; border:1px solid rgba(255,127,0,.25); border-radius:30px; background:rgba(255,127,0,.08); transition:all .18s; cursor:pointer;"
                    onmouseenter="this.style.background='rgba(255,127,0,.18)';this.style.borderColor='#ff7f00';this.style.color='#fff'"
                    onmouseleave="this.style.background='rgba(255,127,0,.08)';this.style.borderColor='rgba(255,127,0,.25)';this.style.color='#ff7f00'">
                    ← Regresar
                </button>
            <?php endif; ?>
        </div>

        <div class="cart-page-header" style="margin-bottom: 1.5rem;">
            <h1 class="cart-page-title">Mi Carrito</h1>
            
            <?php if ($isOnlineMode): ?>
            <!-- Pasos de Compra -->
            <div class="checkout-steps" style="display:flex; justify-content:center; align-items:center; gap:1.2rem; padding:0.4rem 1rem; background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.06); border-radius:30px;">
                <div class="step active" style="display:flex; align-items:center; gap:6px;">
                    <span style="width:20px; height:20px; border-radius:50%; background:#ff7f00; color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:0.75rem; font-weight:700;">1</span>
                    <span style="font-weight:700; color:#fff; font-size:0.8rem;">Carrito</span>
                </div>
                <div style="width:20px; height:1px; background:rgba(255,255,255,0.15);"></div>
                <div class="step" style="display:flex; align-items:center; gap:6px; opacity:0.45;">
                    <span style="width:20px; height:20px; border-radius:50%; background:#1a1a24; border:1px solid rgba(255,255,255,0.2); color:#aaa; display:inline-flex; align-items:center; justify-content:center; font-size:0.75rem; font-weight:700;">2</span>
                    <span style="font-weight:600; color:#aaa; font-size:0.8rem;">Pago</span>
                </div>
                <div style="width:20px; height:1px; background:rgba(255,255,255,0.15);"></div>
                <div class="step" style="display:flex; align-items:center; gap:6px; opacity:0.45;">
                    <span style="width:20px; height:20px; border-radius:50%; background:#1a1a24; border:1px solid rgba(255,255,255,0.2); color:#aaa; display:inline-flex; align-items:center; justify-content:center; font-size:0.75rem; font-weight:700;">3</span>
                    <span style="font-weight:600; color:#aaa; font-size:0.8rem;">Envío</span>
                </div>
            </div>
            <!-- Banner de Reserva Temporal de Stock (15 Minutos) -->
            <div id="reservationBanner" style="display:none; background: rgba(255,127,0,0.08); border: 1px solid rgba(255,127,0,0.25); border-radius: 12px; padding: 0.85rem 1.25rem; margin-bottom: 1.5rem; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                <div style="display:flex; align-items:center; gap:0.6rem; color:#fff; font-size:0.9rem; font-weight:600;">
                    <span>⏳ Reserva de Stock en Almacén:</span>
                    <span id="reservationTimer" style="color:var(--theme-accent, #ff7f00); font-family:monospace; font-size:1.15rem; font-weight:800;">14:59</span>
                </div>
                <div style="font-size:0.82rem; color:#aaa;">Tus productos están apartados temporalmente por 15 minutos.</div>
            </div>
            <?php endif; ?>
        </div>

        <div class="cart-container">
            <div class="cart-items-section">
                <div id="cartList" style="min-height: 200px;"></div>
            </div>

            <div class="cart-summary">
                <div class="summary-title">Resumen de Carrito</div>
                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span id="cartSubtotal">$0.00</span>
                </div>
                <div class="summary-row">
                    <span>Artículos:</span>
                    <span id="cartItemsCount">0</span>
                </div>
                <div class="summary-total">
                    <span>Total:</span>
                    <span id="cartTotalAmount">$0.00</span>
                </div>
                <div class="summary-actions">
                    <?php if ($isOnlineMode): ?>
                        <a href="checkout.php?mode=online" class="btn btn-primary btn-full" style="background:linear-gradient(135deg, #ff7f00, #ff5500); color:#fff; font-weight:800; text-align:center; text-decoration:none; padding:14px; border-radius:8px; display:flex; align-items:center; justify-content:center; gap:8px; margin-bottom:0.75rem; font-size:1.05rem; box-shadow:0 4px 15px rgba(255,102,0,0.3); border:none; text-transform:uppercase; letter-spacing:0.04em;">
                            🔒 Proceder al Pago
                        </a>
                        <!-- Sellos de Confianza -->
                        <div style="margin-top:1.2rem; border-top:1px solid rgba(255,255,255,0.06); padding-top:1.2rem; display:flex; flex-direction:column; gap:0.75rem; text-align:left;">
                            <div style="display:flex; align-items:flex-start; gap:8px; font-size:0.78rem; color:#aaa; line-height:1.4;">
                                <span style="font-size:1rem; line-height:1;">🛡️</span>
                                <span><strong>Pago 100% Seguro:</strong> Cifrado de datos y protección SSL en tu transacción.</span>
                            </div>
                            <div style="display:flex; align-items:flex-start; gap:8px; font-size:0.78rem; color:#aaa; line-height:1.4;">
                                <span style="font-size:1rem; line-height:1;">🚚</span>
                                <span><strong>Envíos a Domicilio:</strong> Entrega garantizada en la dirección proporcionada.</span>
                            </div>
                            <div style="display:flex; align-items:flex-start; gap:8px; font-size:0.78rem; color:#aaa; line-height:1.4;">
                                <span style="font-size:1rem; line-height:1;">✨</span>
                                <span><strong>Garantía Ferretería FOX:</strong> Satisfacción asegurada o cambio de producto.</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Carrito del Catálogo Principal Local -->
                        <a href="checkout.php" class="btn btn-primary btn-full" style="background:linear-gradient(135deg, #ff7f00, #ff5500); color:#fff; font-weight:800; text-align:center; text-decoration:none; padding:14px; border-radius:8px; display:flex; align-items:center; justify-content:center; gap:8px; margin-bottom:0.75rem; font-size:1.05rem; box-shadow:0 4px 15px rgba(255,102,0,0.3); border:none; text-transform:uppercase; letter-spacing:0.04em;">
                            Realizar Pedido / Comprar
                        </a>
                        <button id="printTicket" class="btn btn-secondary btn-full" style="background:#1e1e24; color:#fff; font-weight:700; text-align:center; padding:12px; border-radius:8px; border:1px solid #333; width:100%; display:block; margin-bottom:0.75rem; cursor:pointer; font-size:0.95rem; display:inline-flex; align-items:center; justify-content:center; gap:0.4rem;">⬇️ Enviar cotización</button>
                        
                        <button id="shareWhatsApp" class="btn btn-secondary btn-full"
                                style="background:#1e1e24; color:#fff; font-weight:600; text-align:center; padding:11px; border-radius:8px; border:1px solid #333; display:block; width:100%; margin-bottom:0.75rem; cursor:pointer; font-size:0.92rem; display:inline-flex; align-items:center; justify-content:center; gap:0.4rem;"
                                data-company-whatsapp="<?php echo htmlspecialchars(whatsapp_phone_digits(), ENT_QUOTES, 'UTF-8'); ?>"
                                data-client-code="<?php echo htmlspecialchars($clientTicketCode ?? 'PUBLICO', ENT_QUOTES, 'UTF-8'); ?>">📱 Enviar cotización por WhatsApp</button>
                    <?php endif; ?>
                    <button id="clearCart" class="btn btn-ghost btn-full" style="background:transparent; border:none; color:#888; text-align:center; padding:10px; cursor:pointer; display:block; width:100%; font-size:0.9rem; margin-top:0.5rem; display:inline-flex; align-items:center; justify-content:center; gap:0.3rem;">🗑️ Vaciar Carrito</button>
                </div>
            </div>
        </div>
    </main>

    <footer style="margin-top: 3rem; padding: 2rem; text-align: center; border-top: 1px solid var(--theme-border); color: var(--theme-text-muted);">
        <p>&copy; 2026 Ferretería FOX</p>
    </footer>

    <script src="js/jspdf.umd.min.js"></script>
    <script src="js/main.js?v=2.6"></script>
    <script src="js/modals.js"></script>
    <script>
        window.csrfToken = '<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8"); ?>';
    </script>
    <script src="js/catalog.js"></script>
    <script>
        const CART_KEY = <?php echo $isOnlineMode ? "'fox_cart'" : "'truper_cart'"; ?>;

        function decodeCartText(value) {
            let result = String(value || '');
            if (!result) return '';

            const textarea = document.createElement('textarea');
            for (let i = 0; i < 3; i += 1) {
                textarea.innerHTML = result;
                const decoded = textarea.value;
                if (decoded === result) break;
                result = decoded;
            }

            return result;
        }

        function getStoredCart() {
            try {
                let items = JSON.parse(localStorage.getItem(CART_KEY) || '[]');
                return Array.isArray(items) ? items : [];
            } catch (_) {
                return [];
            }
        }

        async function hydrateCartImages() {
            const cart = getStoredCart();
            const needsHydration = cart.some(item => !item.image_url || item.image_url === 'images/products/default-product.svg');
            
            if (!needsHydration || cart.length === 0) return cart;

            try {
                const response = await fetch('/api/products.php?action=list');
                const { products = [] } = await response.json();
                if (!products.length) return cart;

                const catalogMap = new Map();
                products.forEach(p => {
                    if (p.sku) catalogMap.set(String(p.sku).replace(/^XLS-/i, '').trim(), p);
                    if (p.id) catalogMap.set(String(p.id).trim(), p);
                });

                let changed = false;
                const nextCart = cart.map(item => {
                    const sku = String(item.sku || '').replace(/^XLS-/i, '').trim();
                    const id = String(item.id || '').trim();
                    const match = catalogMap.get(sku) || catalogMap.get(id);

                    if (!match) return item;

                    const newItem = { ...item };
                    if (newItem.image_url === 'images/products/default-product.svg') {
                        newItem.image_url = match.image_url || newItem.image_url;
                        changed = true;
                    }
                    return newItem;
                });

                if (changed) {
                    localStorage.setItem(CART_KEY, JSON.stringify(nextCart));
                    return nextCart;
                }
            } catch (e) {
                console.error("Hydration failed", e);
            }
            return cart;
        }

        let reservationInterval = null;
        let reservationSeconds = 15 * 60;

        function updateReservationTimer(cartLength) {
            const banner = document.getElementById('reservationBanner');
            const timerEl = document.getElementById('reservationTimer');
            if (!banner || !timerEl) return;

            if (cartLength <= 0) {
                banner.style.display = 'none';
                if (reservationInterval) {
                    clearInterval(reservationInterval);
                    reservationInterval = null;
                }
                reservationSeconds = 15 * 60;
                return;
            }

            banner.style.display = 'flex';

            if (!reservationInterval) {
                reservationInterval = setInterval(() => {
                    if (reservationSeconds <= 0) {
                        timerEl.textContent = '00:00 (Expirado)';
                        timerEl.style.color = '#ef4444';
                        clearInterval(reservationInterval);
                        reservationInterval = null;
                        return;
                    }
                    reservationSeconds--;
                    const mins = String(Math.floor(reservationSeconds / 60)).padStart(2, '0');
                    const secs = String(reservationSeconds % 60).padStart(2, '0');
                    timerEl.textContent = `${mins}:${secs}`;
                }, 1000);
            }
        }

        function renderCartPage(cart = getStoredCart()) {
            const cartList = document.getElementById('cartList');
            if (!cartList) return;

            updateReservationTimer(cart.length);

            if (cart.length === 0) {
                cartList.innerHTML = `
                    <div class="cart-empty">
                        <div class="cart-empty-icon" style="font-size: 2.5rem; opacity: 0.6;">📦</div>
                        <p class="cart-empty-text">Tu carrito está vacío</p>
                        <a href="<?php echo $isOnlineMode ? 'tienda.php' : 'index.php'; ?>" class="btn btn-primary cart-empty-btn"><?php echo $isOnlineMode ? 'Explorar Tienda en Línea' : 'Ir al Catálogo'; ?></a>
                    </div>
                `;
                updateSummary(cart);
                return;
            }

            cartList.innerHTML = cart.map((item, idx) => {
                const price = Number(item.unit_price || item.price || 0);
                const originalPrice = Number(item.original_price || 0);
                const discountPercentage = Number(item.discount_percentage || 0);
                const qty = Number(item.quantity || 1);
                const lineTotal = price * qty;
                
                // Mostrar precio original si hay descuento
                let priceDisplay = `$${price.toFixed(2)}`;
                if (originalPrice > 0 && originalPrice > price) {
                    priceDisplay = `
                        <span style="text-decoration: line-through; color: #666; font-size: 0.9em; margin-right: 8px;">$${originalPrice.toFixed(2)}</span>
                        <span style="color: var(--theme-accent, #ff7f00); font-weight: 800;">$${price.toFixed(2)}</span>
                        ${discountPercentage > 0 ? `<span style="background: #22c55e; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.75em; margin-left: 8px;">-${discountPercentage}%</span>` : ''}
                    `;
                } else {
                    priceDisplay = `<span style="color: var(--theme-accent, #ff7f00); font-weight: 800;">$${price.toFixed(2)}</span>`;
                }
                
                // Alerta de stock bajo
                const availableStock = Number(item.available_stock || 0);
                const lowStockThreshold = Number(item.low_stock_threshold || 5);
                const stockWarning = availableStock > 0 && availableStock <= lowStockThreshold 
                    ? `<span style="color: #f59e0b; font-size: 0.85em; display: block; margin-top: 4px;">⚠️ Solo ${availableStock} unidades disponibles</span>` 
                    : '';
                
                return `
                <div class="cart-item">
                    <div class="cart-item-image">
                        <img src="${item.image_url || 'images/products/default-product.svg'}" alt="${decodeCartText(item.name)}" onerror="this.src='images/products/default-product.svg'">
                    </div>
                    <div class="cart-item-details">
                        <p class="cart-item-name">${decodeCartText(item.name)}</p>
                        <span class="cart-item-sku">SKU: ${String(item.sku || '').replace(/^XLS-/i, '')}</span>
                        <span class="cart-item-price">${priceDisplay} x ${qty} = <strong style="color:#fff;">$${lineTotal.toFixed(2)}</strong></span>
                        ${stockWarning}
                    </div>
                    <div class="cart-item-actions">
                        <div class="qty-control">
                            <button class="qty-btn" onclick="changeCartQty('${item.sku}', -1)">−</button>
                            <input type="number" class="qty-input" value="${qty}" onchange="setCartQty('${item.sku}', this.value)" min="1">
                            <button class="qty-btn" onclick="changeCartQty('${item.sku}', 1)">+</button>
                        </div>
                        <button class="remove-btn" onclick="removeFromCart('${item.sku}')">✕</button>
                    </div>
                </div>
            `}).join('');

            updateSummary(cart);
        }

        function updateSummary(cart = getStoredCart()) {
            const items = cart.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
            const total = cart.reduce((sum, item) => sum + (Number(item.unit_price || item.price || 0) * Number(item.quantity || 0)), 0);

            if (document.getElementById('cartItemsCount')) document.getElementById('cartItemsCount').textContent = items;
            if (document.getElementById('cartSubtotal')) document.getElementById('cartSubtotal').textContent = '$' + total.toFixed(2);
            if (document.getElementById('cartTotalAmount')) document.getElementById('cartTotalAmount').textContent = '$' + total.toFixed(2);
        }

        function changeCartQty(sku, delta) {
            const cart = getStoredCart();
            const item = cart.find(p => p.sku === sku);
            if (item) {
                item.quantity = Math.max(1, Number(item.quantity || 1) + delta);
                localStorage.setItem(CART_KEY, JSON.stringify(cart));
                renderCartPage();
            }
        }

        function setCartQty(sku, qty) {
            const cart = getStoredCart();
            const item = cart.find(p => p.sku === sku);
            if (item) {
                item.quantity = Math.max(1, Number(qty) || 1);
                localStorage.setItem(CART_KEY, JSON.stringify(cart));
                renderCartPage();
            }
        }

        function removeFromCart(sku) {
            const cart = getStoredCart();
            const item = cart.find(p => p.sku === sku);
            const itemName = item ? decodeCartText(item.name) : 'este producto';
            confirmDelete(itemName, function() {
                const nextCart = getStoredCart();
                const filtered = nextCart.filter(p => p.sku !== sku);
                localStorage.setItem(CART_KEY, JSON.stringify(filtered));
                renderCartPage();
            });
        }

        document.getElementById('clearCart')?.addEventListener('click', function() {
            confirmAction(
                'Vaciar Carrito',
                '¿Estás seguro de que deseas vaciar todo el carrito? Esta acción no se puede deshacer.',
                '🗑️',
                function() {
                    localStorage.removeItem(CART_KEY);
                    renderCartPage();
                }
            );
        });

        // Project List Importer
        function promptProjectListImport() {
            showPrompt("Importar Lista de Materiales", "Pega aquí tu lista de materiales o SKU de proyecto (Ejemplo: FOX-101 x2, FOX-205 x5):", "", function(raw) {
                if (!raw || !raw.trim()) return;

                let cart = getStoredCart();
                const lines = raw.split(/\r?\n|,|;/);
                let addedCount = 0;

                lines.forEach((line, idx) => {
                    const cleaned = line.trim();
                    if (!cleaned) return;

                    const match = cleaned.match(/([A-Za-z0-9\-]+)\s*(?:x|\*|:)?\s*(\d+)?/i);
                    if (match) {
                        const sku = match[1].toUpperCase();
                        const qty = parseInt(match[2] || '1', 10);

                        const existing = cart.find(item => item.sku === sku || item.name.toUpperCase().includes(sku));
                        if (existing) {
                            existing.quantity += qty;
                        } else {
                            cart.push({
                                id: Date.now() + idx,
                                name: `Material SKU ${sku}`,
                                sku: sku,
                                quantity: qty,
                                unit_price: 120.00,
                                image_url: 'img/no-image.png'
                            });
                        }
                        addedCount++;
                    }
                });

                if (addedCount > 0) {
                    localStorage.setItem(CART_KEY, JSON.stringify(cart));
                    renderCartPage();
                    showAlert(`✅ Se procesaron e importaron ${addedCount} elemento(s) a tu carrito.`, 'success');
                } else {
                    showAlert("No se identificaron códigos SKU válidos en el texto ingresado.", 'warning');
                }
            });
        }

        document.addEventListener('DOMContentLoaded', async function() {
            const cart = await hydrateCartImages();
            renderCartPage(cart);
        });
    </script>
    <script src="js/toast-notifications.js"></script>
    <script src="js/mobile-optimize.js"></script>
</body>
</html>
