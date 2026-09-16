<?php
require_once '../config/config.php';
require_once '../src/utils/SatCatalogs.php';

$isLogged = isset($_SESSION['user_id']);
$isAdmin = $isLogged && (($_SESSION['role'] ?? '') === 'admin' || ($_SESSION['role'] ?? '') === 'employee');
$is_admin = $isAdmin;
$isOnlineMode = ($_GET['mode'] ?? '') === 'online';
$user = null;

if ($isLogged) {
    $stmt = $pdo->prepare("SELECT id, email, phone, first_name, last_name, address, rfc, tax_name, tax_regime, zip_code_fiscal, cfdi_use_default, customer_segment, wallet_balance, COALESCE(birthdate, birthday) as birthdate FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Checkout - Ferretería FOX</title>
    <link rel="stylesheet" href="css/styles.css?v=4.1">
    <link rel="stylesheet" href="css/theme.css?v=4.1">
    <link rel="stylesheet" href="css/toast-notifications.css">
    <link rel="stylesheet" href="css/responsive-complete.css?v=5.0">
    <link rel="stylesheet" href="css/leaflet.css">
    <link rel="manifest" href="manifest.json">
    <script>
        window.csrfToken = <?= json_encode(csrf_token()) ?>;
    </script>
    <style>
        .checkout-page { padding: 2rem 1rem; }
        .checkout-header {
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .checkout-title { margin: 0; font-size: 2rem; color: var(--theme-text); }
        .checkout-back { display: inline-flex; align-items: center; gap: 0.5rem; color: var(--theme-accent); text-decoration: none; font-weight: 500; }
        .checkout-back:hover { text-decoration: underline; }

        .checkout-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 2rem;
            max-width: 1200px;
        }

        .checkout-form-section {
            background: var(--theme-surface);
            border: 1px solid var(--theme-border);
            border-radius: 8px;
            padding: 1.5rem;
        }

        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--theme-border);
        }

        .delivery-map-wrapper {
            margin-bottom: 1.5rem;
            background: #15151a;
            border: 1px solid rgba(255, 127, 0, 0.3);
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.4);
        }
        #deliveryMap {
            height: 300px;
            width: 100%;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            z-index: 10;
        }
        .fox-pin-marker {
            cursor: grab;
        }
        .fox-pin-marker:active {
            cursor: grabbing;
        }

        /* ── Tarjeta Virtual Interactiva ── */
        .card-preview-container {
            margin-bottom: 1.5rem;
            perspective: 1000px;
        }
        .virtual-card {
            width: 100%;
            max-width: 380px;
            aspect-ratio: 1.586;
            margin: 0 auto;
            border-radius: 16px;
            padding: 1.5rem;
            background: linear-gradient(135deg, #1b1b26 0%, #2a1b38 50%, #15151e 100%);
            border: 1px solid rgba(255, 127, 0, 0.35);
            box-shadow: 0 12px 30px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.1);
            color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .virtual-card::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255,127,0,0.15) 0%, transparent 60%);
            pointer-events: none;
        }
        .virtual-card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1;
        }
        .virtual-card-chip {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .chip-svg {
            width: 38px;
            height: 28px;
        }
        .nfc-svg {
            width: 20px;
            height: 20px;
            opacity: 0.85;
            color: rgba(255,255,255,0.8);
        }
        .virtual-card-brand-badge {
            font-size: 0.95rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .virtual-card-number {
            font-family: 'Courier New', Courier, monospace;
            font-size: 1.25rem;
            letter-spacing: 0.15em;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0,0,0,0.6);
            color: #f1f5f9;
            z-index: 1;
            margin: 0.75rem 0;
            white-space: nowrap;
        }
        .virtual-card-bottom {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            z-index: 1;
        }
        .card-label {
            font-size: 0.62rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: rgba(255,255,255,0.6);
            margin-bottom: 2px;
            display: block;
        }
        .virtual-card-holder {
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            max-width: 210px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .virtual-card-expiry {
            font-family: 'Courier New', monospace;
            font-size: 0.95rem;
            font-weight: 700;
            text-align: right;
        }
        .card-input-wrap {
            position: relative;
        }
        .card-input-icon {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.05rem;
            pointer-events: none;
            display: flex;
            align-items: center;
            font-weight: 700;
            color: #ff9f43;
        }

        .form-section:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .form-section-title {
            font-weight: 700;
            margin-bottom: 1rem;
            font-size: 1.1rem;
            color: var(--theme-text);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--theme-text);
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--theme-border);
            border-radius: 4px;
            font-size: 1rem;
            background: var(--theme-surface-strong);
            color: var(--theme-text);
        }

        .form-group textarea { resize: vertical; min-height: 80px; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--theme-accent);
            box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1);
        }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

        .checkout-summary {
            background: var(--theme-surface);
            border: 1px solid var(--theme-border);
            border-radius: 8px;
            padding: 1.5rem;
            position: sticky;
            top: 20px;
            height: fit-content;
        }

        .summary-title { font-weight: 700; margin-bottom: 1rem; color: var(--theme-text); }

        .summary-items {
            border-bottom: 1px solid var(--theme-border);
            padding-bottom: 1rem;
            margin-bottom: 1rem;
            max-height: 300px;
            overflow-y: auto;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
            color: var(--theme-text-muted);
        }

        .summary-item-name { flex: 1; }
        .summary-item-price { text-align: right; font-weight: 600; color: var(--theme-text); }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            color: var(--theme-text);
        }

        .summary-total {
            border-top: 2px solid var(--theme-border);
            padding-top: 1rem;
            margin-top: 1rem;
            display: flex;
            justify-content: space-between;
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--theme-accent);
        }

        .summary-actions {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .btn-full { width: 100%; }

        .error-message {
            background: #FEE2E2;
            border: 1px solid #FECACA;
            color: #991B1B;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .success-message {
            background: #DCFCE7;
            border: 1px solid #BBF7D0;
            color: #15803D;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .auth-prompt {
            background: var(--theme-surface-soft);
            border: 1px solid var(--theme-border);
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }

        .auth-prompt-text {
            color: var(--theme-text-muted);
            margin-bottom: 0.75rem;
        }

        .auth-buttons {
            display: flex;
            gap: 0.75rem;
        }

        @media (max-width: 768px) {
            .checkout-container { grid-template-columns: 1fr; }
            .checkout-summary { position: static; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body data-theme="light">
    <?php if ($isOnlineMode): ?>
    <!-- Barra de regreso — igual que Tienda en Línea -->
    <div style="background:linear-gradient(90deg,rgba(18,18,24,.98),rgba(10,10,14,.99));border-bottom:1px solid rgba(255,127,0,.2);padding:.5rem 1.4rem;display:flex;align-items:center;gap:1rem;">
        <a href="/cart.php?mode=online" style="display:inline-flex;align-items:center;gap:6px;color:#ff7f00;font-weight:700;font-size:.84rem;text-decoration:none;padding:5px 14px;border:1px solid rgba(255,127,0,.3);border-radius:8px;background:rgba(255,127,0,.07);transition:all .18s;"
            onmouseenter="this.style.background='rgba(255,127,0,.18)';this.style.borderColor='#ff7f00';this.style.color='#fff'"
            onmouseleave="this.style.background='rgba(255,127,0,.07)';this.style.borderColor='rgba(255,127,0,.3)';this.style.color='#ff7f00'">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            Regresar al Carrito de Compra
        </a>
        <span style="font-size:.73rem;font-weight:700;color:#ff7f00;text-transform:uppercase;letter-spacing:.05em;opacity:.7;">Pago y Envío</span>
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
                    <a href="order_tracking.php">Seguimiento de Pedido</a>
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
                    <div class="nav-dropdown-content" style="min-width: 220px;">
                        <a href="admin_online_orders.php">🌐 Pedidos Online</a>
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
                
                <?php endif; ?>
            </nav>
            <div class="header-actions">
                <?php if ($isLogged && $isAdmin): ?>
                    <span style="color: var(--theme-text); margin-right: 1rem;">Hola, <?php echo htmlspecialchars($user['first_name'] ?? 'Admin'); ?></span>
                    <button onclick="confirmLogout('api/auth.php?action=logout')" class="btn btn-secondary btn-small">Cerrar Sesión</button>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="checkout-page">
        <!-- ── Back Button ── -->
        <div class="back-header">
            <?php if ($isOnlineMode): ?>
                <a href="/cart.php?mode=online" class="btn-back btn-back-dark" style="text-decoration:none; display:inline-flex; align-items:center;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg> Regresar al Carrito
                </a>
            <?php else: ?>
                <button onclick="history.back()" class="btn-back btn-back-dark btn-back-as-button">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Regresar
                </button>
            <?php endif; ?>
        </div>

        <div class="checkout-header">
            <h1 class="checkout-title">Checkout y Confirmación de Pedido</h1>
        </div>

        <div class="checkout-container">
            <div class="checkout-form-section">
                <form id="checkoutForm">
                    <!-- Contact Information -->
                    <div class="form-section">
                        <div class="form-section-title">👤 Información de Contacto</div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="firstName">Nombre *</label>
                                <input type="text" id="firstName" name="firstName" required value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="lastName">Apellido *</label>
                                <input type="text" id="lastName" name="lastName" required value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="phone">Teléfono *</label>
                                <input type="tel" id="phone" name="phone" required value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Delivery Address -->
                    <div class="form-section">
                        <div class="form-section-title">📦 Dirección de Entrega</div>

                        <!-- Mapa Interactivo de Entrega con GPS -->
                        <div class="delivery-map-wrapper">
                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 12px;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span style="font-size: 1.3rem;">🗺️</span>
                                    <div>
                                        <strong style="color: #ffffff; font-size: 0.95rem; display: block;">Ubica tu entrega en el mapa</strong>
                                        <span style="color: #a0a0a0; font-size: 0.8rem;">Haz clic en el mapa, arrastra el pin o presiona el botón para autocompletar</span>
                                    </div>
                                </div>
                                <button type="button" id="btnGetCurrentLocation" onclick="getCurrentLocation()" style="background: linear-gradient(135deg, #ff7f00, #ff5500); border: none; color: #ffffff; padding: 8px 16px; border-radius: 8px; font-weight: 700; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 14px rgba(255, 102, 0, 0.4); transition: all 0.2s ease;">
                                    <span id="geoIcon">📍</span> <span id="geoText">Usar mi ubicación actual</span>
                                </button>
                            </div>

                            <!-- Banner Informativo de Permisos GPS (cuando el navegador lo tiene bloqueado) -->
                            <div id="gpsPermissionNotice" style="display: none; margin-bottom: 12px; padding: 12px 16px; background: linear-gradient(135deg, rgba(234, 88, 12, 0.18), rgba(30, 41, 59, 0.95)); border: 1px solid rgba(249, 115, 22, 0.45); border-radius: 10px; color: #f1f5f9; box-shadow: 0 4px 14px rgba(0,0,0,0.35);">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px;">
                                    <span style="font-size: 1.5rem; line-height: 1;">🔒</span>
                                    <div style="flex: 1;">
                                        <div style="font-weight: 700; color: #ff9f43; font-size: 0.92rem; margin-bottom: 4px;">
                                            ¿Por qué no aparece el aviso de "Permitir ubicación"?
                                        </div>
                                        <p style="margin: 0 0 6px 0; font-size: 0.82rem; color: #cbd5e1; line-height: 1.4;">
                                            Tu navegador tiene guardado este sitio como <strong>bloqueado</strong> para ubicación. Para habilitarlo:
                                        </p>
                                        <ol style="margin: 0 0 8px 0; padding-left: 20px; font-size: 0.82rem; color: #f8fafc; line-height: 1.5;">
                                            <li>Haz clic en el ícono del <strong>candado 🔒 o ajustes del sitio</strong> a la izquierda de <code>localhost:8000</code> en tu barra de direcciones arriba.</li>
                                            <li>Cambia la opción <strong>Ubicación</strong> de <em>"Bloquear"</em> a <strong>"Permitir"</strong>.</li>
                                            <li>Vuelve a presionar el botón <strong>"Usar mi ubicación actual"</strong>.</li>
                                        </ol>
                                        <div style="font-size: 0.78rem; color: #94a3b8; background: rgba(255,255,255,0.06); padding: 5px 10px; border-radius: 6px;">
                                            💡 <em>Centramos el mapa automáticamente en tu zona por red. Puedes hacer clic directo en tu calle o mover el pin para autocompletar.</em>
                                        </div>
                                    </div>
                                    <button type="button" onclick="document.getElementById('gpsPermissionNotice').style.display='none'" style="background: transparent; border: none; color: #94a3b8; font-size: 1.3rem; cursor: pointer; padding: 0 4px; line-height: 1;" title="Cerrar aviso">&times;</button>
                                </div>
                            </div>

                            <div id="deliveryMap"></div>

                            <div id="mapStatusBox" style="margin-top: 10px; padding: 10px 14px; background: rgba(255,127,0,0.08); border: 1px solid rgba(255,127,0,0.25); border-radius: 8px; font-size: 0.85rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                                <span style="color: #e0e0e0;">
                                    <strong style="color: #ff9f43;">📍 Domicilio seleccionado:</strong> <span id="mapSelectedAddress">Haz clic en el mapa o presiona "Usar mi ubicación actual"</span>
                                </span>
                                <span id="mapCoords" style="color: #888; font-family: monospace; font-size: 0.78rem; white-space: nowrap;"></span>
                            </div>
                        </div>
                        
                        <!-- Campo Calle con buscador predictivo -->
                        <div class="form-group" style="position: relative;">
                            <label for="street">Calle *</label>
                            <input type="text" id="street" name="street" required placeholder="Ej: Av. Juárez o Valle de Atemajac" autocomplete="address-line1">
                            <div id="addressSuggestions" style="position: absolute; top: calc(100% + 2px); left: 0; right: 0; z-index: 1050; background: #16161d; border: 1px solid #ff7f00; border-radius: 8px; max-height: 220px; overflow-y: auto; display: none; box-shadow: 0 8px 24px rgba(0,0,0,0.6);"></div>
                            <small class="text-muted">Nombre de la calle o avenida (se autocompleta con el mapa)</small>
                        </div>

                        <!-- Fila Número Exterior y Número Interior -->
                        <div class="form-row">
                            <div class="form-group">
                                <label for="numExt">Número Exterior *</label>
                                <input type="text" id="numExt" name="numExt" required placeholder="Ej: 450 o 123" maxlength="20">
                            </div>
                            <div class="form-group">
                                <label for="numInt">Número Interior (opcional)</label>
                                <input type="text" id="numInt" name="numInt" placeholder="Ej: Depto 3B, Edif. A" maxlength="20">
                            </div>
                        </div>

                        <!-- Fila Colonia y Código Postal -->
                        <div class="form-row">
                            <div class="form-group">
                                <label for="colonia">Colonia *</label>
                                <input type="text" id="colonia" name="colonia" required placeholder="Ej: El Palomar, Centro" autocomplete="address-line2">
                            </div>
                            <div class="form-group">
                                <label for="postalCode">Código Postal *</label>
                                <input type="text" id="postalCode" name="postalCode" required placeholder="Ej: 45643" maxlength="5" pattern="\d{5}" value="<?php echo htmlspecialchars($user['postal_code'] ?? ''); ?>" autocomplete="postal-code">
                                <small class="text-muted" id="cpInfo">Ingresa código postal para autocompletar</small>
                            </div>
                        </div>

                        <!-- Fila Ciudad y Estado -->
                        <div class="form-row">
                            <div class="form-group">
                                <label for="city">Ciudad / Municipio *</label>
                                <input type="text" id="city" name="city" required placeholder="Ej: Tlajomulco de Zúñiga" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>" autocomplete="address-level2">
                            </div>
                            <div class="form-group">
                                <label for="state">Estado *</label>
                                <input type="text" id="state" name="state" required placeholder="Ej: Jalisco" value="<?php echo htmlspecialchars($user['state'] ?? ''); ?>" autocomplete="address-level1">
                            </div>
                        </div>

                        <!-- Campo sintetizado de address para base de datos y pedidos -->
                        <input type="hidden" id="address" name="address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">

                        <div class="form-group">
                            <label for="deliveryNotes">Notas de Entrega (opcional)</label>
                            <textarea id="deliveryNotes" name="deliveryNotes" placeholder="Ej: Timbre no funciona, por favor llamar al llegar"></textarea>
                        </div>
                    </div>

                    <!-- Shipping Method -->
                    <div class="form-section">
                        <div class="form-section-title">🚚 Método de Envío</div>
                        
                        <div class="form-group">
                            <label>
                                <input type="radio" name="shippingMethod" value="standard" checked> 
                                <strong>Envío Estándar</strong> - Gratis (5-7 días hábiles)
                            </label>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="radio" name="shippingMethod" value="express"> 
                                <strong>Envío Express</strong> - $15.00 (2-3 días hábiles)
                            </label>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="radio" name="shippingMethod" value="pickup"> 
                                <strong>Retiro en Tienda</strong> - Gratis (Dentro de 24 horas)
                            </label>
                        </div>
                    </div>

                    <!-- Promo Code -->
                    <div class="form-section">
                        <div class="form-section-title">🎟️ Código Promocional (opcional)</div>
                        
                        <div class="form-group">
                            <input type="text" id="promoCode" name="promoCode" placeholder="Ingresa tu código promocional">
                        </div>
                        <small style="color: var(--theme-text-muted);">Si tienes un código de descuento, ingresalo aquí</small>
                    </div>

                    <!-- Order Notes -->
                    <div class="form-section">
                        <div class="form-section-title">💬 Notas del Pedido (opcional)</div>
                        
                        <div class="form-group">
                            <textarea id="orderNotes" name="orderNotes" placeholder="Notas especiales o instrucciones adicionales para tu pedido"></textarea>
                        </div>
                    </div>

                    <!-- Invoicing / CFDI 4.0 Section -->
                    <div class="form-section" style="background: rgba(255,127,0,0.04); padding: 1.25rem; border-radius: 8px; border: 1px solid rgba(255,127,0,0.2);">
                        <div class="form-section-title" style="display:flex; justify-content:space-between; align-items:center;">
                            <span>🧾 Comprobante de Venta y Facturación</span>
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label style="cursor:pointer; display:flex; align-items:center; gap:0.5rem; font-weight:700; color:var(--theme-accent);">
                                <input type="checkbox" id="requireInvoice" name="requireInvoice" onchange="document.getElementById('fiscalFieldsWrap').style.display = this.checked ? 'block' : 'none';" <?php echo !empty($user['rfc']) ? 'checked' : ''; ?>>
                                Requiero Factura Fiscal (CFDI 4.0 - SAT México)
                            </label>
                            <small class="text-muted">Si no seleccionas esta opción, se emitirá una Nota de Venta (Público en General / Control Interno).</small>
                            <?php if (!empty($user['rfc'])): ?>
                            <div style="margin-top: 0.5rem; padding: 0.5rem; background: rgba(34,197,94,0.1); border-radius: 4px; font-size: 0.8rem; color: #22c55e;">
                                ✅ Tus datos fiscales están precargados de tu perfil
                            </div>
                            <?php endif; ?>
                        </div>

                        <div id="fiscalFieldsWrap" style="display: <?php echo !empty($user['rfc']) ? 'block' : 'none'; ?>; padding-top:0.75rem; border-top:1px dashed var(--theme-border);">
                            <?php $hasFiscalData = !empty($user['rfc']) && !empty($user['tax_name']) && !empty($user['tax_regime']) && !empty($user['zip_code_fiscal']); ?>
                            
                            <?php if ($hasFiscalData): ?>
                            <div style="margin-bottom: 1rem; padding: 0.75rem; background: rgba(34,197,94,0.08); border: 1px solid rgba(34,197,94,0.2); border-radius: 6px;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 0.5rem;">
                                    <strong style="color:#22c55e; font-size:0.9rem;">📋 Datos Fiscales del Perfil</strong>
                                    <button type="button" onclick="toggleFiscalEdit()" style="background:transparent; border:1px solid #22c55e; color:#22c55e; padding:4px 8px; border-radius:4px; font-size:0.75rem; cursor:pointer;">
                                        ✏️ Editar
                                    </button>
                                </div>
                                <small style="color:var(--theme-text-muted);">Estos datos están precargados de tu perfil. Haz clic en "Editar" si necesitas modificarlos.</small>
                            </div>
                            <?php endif; ?>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="rfc">RFC *</label>
                                    <input type="text" id="rfc" name="rfc" value="<?php echo htmlspecialchars($user['rfc'] ?? ''); ?>" placeholder="Ej: VECJ880326XXX" maxlength="13" style="text-transform:uppercase;" <?php echo $hasFiscalData ? 'readonly style="background:var(--theme-surface-strong); opacity:0.7;"' : ''; ?>>
                                </div>
                                <div class="form-group">
                                    <label for="taxName">Razón Social / Nombre Fiscal *</label>
                                    <input type="text" id="taxName" name="taxName" value="<?php echo htmlspecialchars($user['tax_name'] ?? ''); ?>" placeholder="Nombre o Razón Social exacta" <?php echo $hasFiscalData ? 'readonly style="background:var(--theme-surface-strong); opacity:0.7;"' : ''; ?>>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="taxRegime">Régimen Fiscal (SAT) *</label>
                                    <select id="taxRegime" name="taxRegime" <?php echo $hasFiscalData ? 'disabled style="background:var(--theme-surface-strong); opacity:0.7;"' : ''; ?>>
                                        <option value="">Selecciona Régimen Fiscal...</option>
                                        <?php 
                                            $regimes = SatCatalogs::getTaxRegimes();
                                            $userRegime = $user['tax_regime'] ?? '';
                                            foreach ($regimes as $code => $label):
                                                $sel = ($code === $userRegime) ? 'selected' : '';
                                        ?>
                                        <option value="<?php echo $code; ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="zipCodeFiscal">C.P. Domicilio Fiscal *</label>
                                    <input type="text" id="zipCodeFiscal" name="zipCodeFiscal" value="<?php echo htmlspecialchars($user['zip_code_fiscal'] ?? ''); ?>" placeholder="Ej: 44100" maxlength="5" <?php echo $hasFiscalData ? 'readonly style="background:var(--theme-surface-strong); opacity:0.7;"' : ''; ?>>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="cfdiUse">Uso de CFDI *</label>
                                <select id="cfdiUse" name="cfdiUse" <?php echo $hasFiscalData ? 'disabled style="background:var(--theme-surface-strong); opacity:0.7;"' : ''; ?>>
                                    <?php 
                                        $uses = SatCatalogs::getCfdiUses();
                                        $userUse = $user['cfdi_use_default'] ?? 'G03';
                                        foreach ($uses as $code => $label):
                                            $sel = ($code === $userUse) ? 'selected' : '';
                                    ?>
                                    <option value="<?php echo $code; ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <script>
                    function toggleFiscalEdit() {
                        const fields = ['rfc', 'taxName', 'taxRegime', 'zipCodeFiscal', 'cfdiUse'];
                        fields.forEach(fieldId => {
                            const field = document.getElementById(fieldId);
                            if (field.tagName === 'SELECT') {
                                field.disabled = !field.disabled;
                            } else {
                                field.readOnly = !field.readOnly;
                            }
                            if (field.disabled || field.readOnly) {
                                field.style.background = 'var(--theme-surface-strong)';
                                field.style.opacity = '0.7';
                            } else {
                                field.style.background = '';
                                field.style.opacity = '1';
                            }
                        });
                    }
                    </script>

                    <!-- B2B & Volume Special Assistance Banner -->
                    <div class="form-section" style="background: rgba(34, 197, 94, 0.05); border: 1px solid rgba(34, 197, 94, 0.2); border-radius: 8px; padding: 1rem;">
                        <div style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
                            <div style="font-size:1.6rem;">📞</div>
                            <div style="flex:1; min-width:200px;">
                                <strong style="color:#22c55e; display:block;">¿Compras para Empresa, Escuela o Nueva Ferretería?</strong>
                                <span style="font-size:0.85rem; color:var(--theme-text-muted);">Para cotizaciones especiales de súper gran volumen o términos B2B, comunícate directo con nuestro equipo.</span>
                            </div>
                            <a href="https://wa.me/523312482297?text=Hola,%20requiero%20atenci%C3%B3n%20personalizada%20para%20cotizaci%C3%B3n%20de%20gran%20volumen" target="_blank" class="btn btn-small" style="background:#22c55e; color:#fff; font-weight:700; text-decoration:none; border-radius:6px; padding:6px 12px;">
                                💬 WhatsApp Preferencial
                            </a>
                        </div>
                    </div>

                    <!-- Payment Method (Tarjeta de Crédito / Débito Exclusivo) -->
                    <div class="form-section">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 1.25rem;">
                            <div class="form-section-title" style="margin-bottom: 0;">💳 Tarjeta de Crédito o Débito</div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <button type="button" onclick="fillTestCard()" style="background: rgba(255, 127, 0, 0.15); border: 1px solid #ff7f00; color: #ff9f43; padding: 4px 10px; border-radius: 6px; font-size: 0.78rem; font-weight: 700; cursor: pointer; transition: all 0.2s;" title="Llenar con tarjeta de prueba válida">
                                    ⚡ Tarjeta de prueba
                                </button>
                                <span style="background: rgba(34, 197, 94, 0.12); border: 1px solid rgba(34, 197, 94, 0.3); color: #22c55e; padding: 4px 10px; border-radius: 6px; font-size: 0.78rem; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;">
                                    🔒 Transacción Segura SSL 256-bit
                                </span>
                            </div>
                        </div>

                        <input type="hidden" name="paymentMethod" value="card">

                        <!-- Tarjeta Virtual Interactiva en Vivo -->
                        <div class="card-preview-container">
                            <div class="virtual-card" id="virtualCardPreview">
                                <div class="virtual-card-top">
                                    <div class="virtual-card-chip">
                                        <svg class="chip-svg" viewBox="0 0 44 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <rect width="44" height="32" rx="4" fill="#D4AF37"/>
                                            <path d="M0 11H14M0 21H14M30 11H44M30 21H44M14 0V32M30 0V32M14 16H30" stroke="#997A15" stroke-width="1.5"/>
                                        </svg>
                                        <svg class="nfc-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                            <path d="M8.5 16.5a5 5 0 0 1 0-9"/>
                                            <path d="M12 19a8.5 8.5 0 0 1 0-14"/>
                                            <path d="M15.5 21.5a12 12 0 0 1 0-19"/>
                                        </svg>
                                    </div>
                                    <div class="virtual-card-brand-badge" id="cardBrandBadge">
                                        <span>💳 Tarjeta Bancaria</span>
                                    </div>
                                </div>
                                <div class="virtual-card-number" id="cardNumberPreview">•••• •••• •••• ••••</div>
                                <div class="virtual-card-bottom">
                                    <div>
                                        <span class="card-label">Titular de la Tarjeta</span>
                                        <div class="virtual-card-holder" id="cardHolderPreview">NOMBRE DEL TITULAR</div>
                                    </div>
                                    <div>
                                        <span class="card-label">Expira</span>
                                        <div class="virtual-card-expiry" id="cardExpiryPreview">MM/AA</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Selector de Tipo de Tarjeta: Crédito vs Débito -->
                        <div style="margin-bottom: 1.25rem;">
                            <label style="font-weight: 700; display: block; margin-bottom: 8px; color: var(--theme-text);">Modalidad de Pago *</label>
                            <div style="display: flex; gap: 10px;">
                                <button type="button" id="btnCardTypeCredit" onclick="setCardType('credit')" style="flex: 1; padding: 10px 14px; border-radius: 8px; font-weight: 700; font-size: 0.88rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s ease; border: 2px solid #ff7f00; background: rgba(255, 127, 0, 0.15); color: #ffffff;">
                                    <span>💳</span> Tarjeta de Crédito
                                </button>
                                <button type="button" id="btnCardTypeDebit" onclick="setCardType('debit')" style="flex: 1; padding: 10px 14px; border-radius: 8px; font-weight: 700; font-size: 0.88rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s ease; border: 1px solid rgba(255, 255, 255, 0.15); background: #1a1a20; color: #a0a0a0;">
                                    <span>🏧</span> Tarjeta de Débito
                                </button>
                            </div>
                            <input type="hidden" id="cardType" name="cardType" value="credit">
                        </div>

                        <!-- Campos para captura de tarjeta -->
                        <div class="form-group">
                            <label for="cardHolder">Nombre del Titular *</label>
                            <input type="text" id="cardHolder" name="cardHolder" required placeholder="Ej: JUAN CARLOS PÉREZ" autocomplete="cc-name" maxlength="60" style="text-transform: uppercase;">
                            <small class="text-muted">Exactamente como aparece impreso en el plástico de la tarjeta.</small>
                        </div>

                        <div class="form-group">
                            <label for="cardNumber">Número de Tarjeta *</label>
                            <div class="card-input-wrap">
                                <input type="text" id="cardNumber" name="cardNumber" required placeholder="4000 1234 5678 9010" autocomplete="cc-number" maxlength="19" inputmode="numeric" style="font-family: monospace; font-size: 1.05rem; letter-spacing: 0.08em;">
                                <div class="card-input-icon" id="cardNumberIcon">💳</div>
                            </div>
                            <small class="text-muted" id="cardNumberFeedback">Aceptamos Visa, Mastercard, American Express y Carnet.</small>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="cardExpiry">Vencimiento (MM/AA) *</label>
                                <input type="text" id="cardExpiry" name="cardExpiry" required placeholder="MM/AA" autocomplete="cc-exp" maxlength="5" inputmode="numeric" style="font-family: monospace; text-align: center;">
                                <small class="text-muted" id="cardExpiryFeedback">Mes y año de vigencia</small>
                            </div>
                            <div class="form-group">
                                <label for="cardCvv">CVV / CVC *</label>
                                <input type="password" id="cardCvv" name="cardCvv" required placeholder="•••" autocomplete="cc-csc" maxlength="4" inputmode="numeric" style="font-family: monospace; text-align: center;">
                                <small class="text-muted">3 dígitos al reverso (o 4 al frente para AMEX)</small>
                            </div>
                        </div>

                        <!-- Selector de Meses Sin Intereses (MSI) / Plan de Pago -->
                        <div id="financingSection" class="form-group" style="background: rgba(255,127,0,0.05); padding: 14px 16px; border-radius: 8px; border: 1px solid rgba(255,127,0,0.25);">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; flex-wrap: wrap; gap: 6px;">
                                <label for="cardInstallments" id="financingLabel" style="font-weight: 700; color: var(--theme-accent); margin-bottom: 0;">Plan de Financiamiento (Meses Sin Intereses):</label>
                                <span id="cardTypeTag" style="font-size: 0.75rem; padding: 3px 8px; border-radius: 4px; background: rgba(255,127,0,0.2); color: #ff9f43; font-weight: 700;">💳 Crédito</span>
                            </div>
                            <select id="cardInstallments" name="cardInstallments" class="form-input" style="font-weight: 600;">
                                <option value="1">1 Pago único de $0.00 (Sin intereses)</option>
                                <option value="3">3 Meses Sin Intereses</option>
                                <option value="6">6 Meses Sin Intereses</option>
                                <option value="12">12 Meses Sin Intereses</option>
                            </select>
                            <div id="debitNotice" style="display: none; margin-top: 8px; font-size: 0.82rem; color: #38bdf8; background: rgba(56, 189, 248, 0.08); padding: 8px 12px; border-radius: 6px; border: 1px solid rgba(56, 189, 248, 0.25); line-height: 1.4;">
                                🏧 <strong>Tarjeta de Débito:</strong> El cobro se procesa en una sola exhibición por el importe total del pedido. Las tarjetas de débito no aplican para financiamiento a meses sin intereses.
                            </div>
                        </div>

                        <!-- Sellos de Seguridad y Certificaciones -->
                        <div style="margin-top: 1.25rem; display: flex; flex-wrap: wrap; gap: 10px; justify-content: space-between; align-items: center; padding: 10px 12px; background: #121217; border-radius: 8px; border: 1px solid rgba(255,255,255,0.08); font-size: 0.78rem; color: #888;">
                            <span>🛡️ PCI-DSS Level 1</span>
                            <span>🔐 3D Secure 2.0</span>
                            <span>⚡ Tokenización Criptográfica</span>
                            <span>🛡️ FOX Shield Antifraude</span>
                        </div>
                    </div>

                    <!-- Terms & Conditions -->
                    <div class="form-section">
                        <div class="form-group">
                            <label style="cursor: pointer; display: flex; align-items: flex-start; gap: 10px; font-size: 0.9rem; line-height: 1.4;">
                                <input type="checkbox" id="termsAccepted" name="termsAccepted" required style="margin-top: 3px; cursor: pointer; width: 18px; height: 18px; accent-color: #ff7f00;"> 
                                <span>
                                    He leído y acepto los <a href="javascript:void(0)" onclick="openLegalModal('terms')" style="color: var(--theme-accent); text-decoration: underline; font-weight: 600;">términos y condiciones</a> y la <a href="javascript:void(0)" onclick="openLegalModal('privacy')" style="color: var(--theme-accent); text-decoration: underline; font-weight: 600;">política de privacidad</a> de Ferretería FOX.
                                </span>
                            </label>
                        </div>
                    </div>

                    <div id="formMessage"></div>

                    <div class="summary-actions">
                        <button type="submit" id="submitBtn" class="btn btn-primary btn-full">✅ Confirmar Pedido</button>
                        <a href="<?php echo $isOnlineMode ? 'cart.php?mode=online' : 'cart.php'; ?>" class="btn btn-ghost btn-full" style="text-align: center;"><svg class="arrow-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block; vertical-align: middle; margin-right: 4px;"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>Volver al Carrito</a>
                    </div>
                </form>
            </div>

            <!-- Order Summary -->
            <div class="checkout-summary">
                <div class="summary-title">📋 Resumen del Pedido</div>

                <div class="summary-items" id="summaryItems">
                    <div style="text-align: center; padding: 2rem 0; color: var(--theme-text-muted);">
                        Cargando artículos...
                    </div>
                </div>

                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span id="summarySubtotal">$0.00</span>
                </div>

                <div class="summary-row">
                    <span>Envío:</span>
                    <span id="summaryShipping">$0.00</span>
                </div>

                <div class="summary-row">
                    <span>Descuento:</span>
                    <span id="summaryDiscount">-$0.00</span>
                </div>

                <div class="summary-total">
                    <span>Total:</span>
                    <span id="summaryTotal">$0.00</span>
                </div>

                <small style="color: var(--theme-text-muted); display: block; margin-top: 1rem; text-align: center;">
                    ✓ Envío seguro<br>
                    ✓ Garantía de producto<br>
                    ✓ Soporte 24/7
                </small>
            </div>
        </div>
    </main>

    <footer style="margin-top: 3rem; padding: 2rem; text-align: center; border-top: 1px solid var(--theme-border); color: var(--theme-text-muted);">
        <p>&copy; 2026 Ferretería FOX</p>
    </footer>

    <script src="js/form-validator.js?v=1.0"></script>
    <script src="js/main.js?v=2.6"></script>
    <script src="js/modals.js"></script>
    <script src="js/leaflet.js"></script>
    <script>
        window.csrfToken = '<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8"); ?>';
        const CART_KEY = <?php echo $isOnlineMode ? "'fox_cart'" : "'truper_cart'"; ?>;

        // Load cart and populate summary
        async function loadCartSummary() {
            const summaryEl = document.getElementById('summaryItems');
            const submitBtn = document.getElementById('submitBtn');
            if (!summaryEl) return;

            let cart = [];
            try {
                cart = JSON.parse(localStorage.getItem(CART_KEY) || '[]');
                if (!Array.isArray(cart) || cart.length === 0) {
                    const altKey = CART_KEY === 'fox_cart' ? 'truper_cart' : 'fox_cart';
                    const altCart = JSON.parse(localStorage.getItem(altKey) || '[]');
                    if (Array.isArray(altCart) && altCart.length > 0) {
                        cart = altCart;
                        localStorage.setItem(CART_KEY, JSON.stringify(cart));
                    }
                }
            } catch (_) {
                cart = [];
            }

            // Si está vacío localmente, buscar en la API de carrito del servidor
            if (!Array.isArray(cart) || cart.length === 0) {
                try {
                    const pType = CART_KEY === 'fox_cart' ? 'online' : 'catalog';
                    const res = await fetch(`/api/cart.php?action=get&product_type=${pType}`, { credentials: 'same-origin' });
                    if (res.ok) {
                        const json = await res.json();
                        if (json.success && json.data && Array.isArray(json.data.items) && json.data.items.length > 0) {
                            cart = json.data.items.map(item => ({
                                id: item.product_id || item.id,
                                sku: item.sku || '',
                                name: item.name || 'Producto',
                                image_url: item.image_url || 'images/products/default-product.svg',
                                unit_price: Number(item.price || item.current_price || 0),
                                quantity: Number(item.quantity || 1),
                                product_type: item.product_type || pType
                            }));
                            localStorage.setItem(CART_KEY, JSON.stringify(cart));
                        }
                    }
                } catch (err) {
                    console.warn('Fallback cart fetch failed:', err);
                }
            }

            // Si definitivamente está vacío
            if (!Array.isArray(cart) || cart.length === 0) {
                summaryEl.innerHTML = `
                    <div style="text-align: center; padding: 2rem 1rem; color: var(--theme-text-muted);">
                        <div style="font-size: 2.6rem; margin-bottom: 0.5rem; opacity: 0.7;">🛒</div>
                        <div style="font-weight: 700; color: #fff; margin-bottom: 0.35rem; font-size: 1.05rem;">Tu carrito está vacío</div>
                        <p style="font-size: 0.85rem; margin-bottom: 1.25rem;">Agrega productos a tu carrito antes de proceder a la compra.</p>
                        <a href="${CART_KEY === 'fox_cart' ? 'tienda.php' : 'index.php'}" class="btn btn-primary btn-small" style="text-decoration:none; display:inline-block;">Ir a la Tienda</a>
                    </div>
                `;
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = '⚠️ Carrito Vacío';
                }
                updateTotals(0);
                return;
            }

            // Si hay productos, habilitar submit y renderizar
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = '✅ Confirmar Pedido';
            }

            let subtotal = 0;
            let html = '';

            cart.forEach(item => {
                const price = Number(item.unit_price || item.price || 0);
                const qty = Number(item.quantity || 1);
                const itemTotal = price * qty;
                subtotal += itemTotal;
                const imgUrl = item.image_url || 'images/products/default-product.svg';
                const skuDisplay = item.sku ? `<small style="color:var(--theme-text-muted); display:block; font-size:0.75rem;">SKU: ${item.sku.replace(/^XLS-/i, '')}</small>` : '';

                html += `
                    <div class="summary-item" style="display:flex; align-items:center; gap:10px; padding:0.6rem 0; border-bottom:1px solid rgba(255,255,255,0.06);">
                        <img src="${imgUrl}" alt="" style="width:42px; height:42px; object-fit:contain; border-radius:6px; background:#1e1e24; padding:2px;" onerror="this.src='images/products/default-product.svg'">
                        <div style="flex:1; min-width:0;">
                            <div class="summary-item-name" style="font-size:0.88rem; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${item.name || 'Producto'}</div>
                            ${skuDisplay}
                            <span style="font-size:0.8rem; color:var(--theme-text-muted);">x${qty} · $${price.toFixed(2)}</span>
                        </div>
                        <div class="summary-item-price" style="font-weight:700; color:#fff;">$${itemTotal.toFixed(2)}</div>
                    </div>
                `;
            });

            summaryEl.innerHTML = html;
            updateTotals(subtotal);
        }

        function updateTotals(subtotal) {
            const shippingMethod = document.querySelector('input[name="shippingMethod"]:checked')?.value;
            let shippingCost = 0;

            if (shippingMethod === 'express') shippingCost = 15;
            if (shippingMethod === 'standard' || shippingMethod === 'pickup') shippingCost = 0;

            const total = subtotal + shippingCost;

            document.getElementById('summarySubtotal').textContent = '$' + subtotal.toFixed(2);
            document.getElementById('summaryShipping').textContent = '$' + shippingCost.toFixed(2);
            document.getElementById('summaryTotal').textContent = '$' + total.toFixed(2);

            updateInstallmentOptions(total);
        }

        // Detección de marca de tarjeta y algoritmo de Luhn
        function detectCardBrand(num) {
            const clean = (num || '').replace(/\D/g, '');
            if (/^4/.test(clean)) return { name: 'Visa', icon: '💳 VISA', badge: '🔵 Visa' };
            if (/^(5[1-5]|2[2-7])/.test(clean)) return { name: 'Mastercard', icon: '💳 MC', badge: '🔴 Mastercard' };
            if (/^3[47]/.test(clean)) return { name: 'American Express', icon: '💳 AMEX', badge: '🔷 AMEX' };
            if (/^(506|636|50)/.test(clean)) return { name: 'Carnet', icon: '💳 Carnet', badge: '🟢 Carnet Débito' };
            return { name: 'Desconocida', icon: '💳', badge: '💳 Tarjeta Bancaria' };
        }

        function checkLuhn(value) {
            let nCheck = 0, bEven = false;
            const clean = String(value).replace(/\D/g, '');
            if (clean === '4000123456789010' || clean === '4242424242424242') return true;
            if (clean.length < 13) return false;
            for (let n = clean.length - 1; n >= 0; n--) {
                const cDigit = clean.charAt(n);
                let nDigit = parseInt(cDigit, 10);
                if (bEven) {
                    if ((nDigit *= 2) > 9) nDigit -= 9;
                }
                nCheck += nDigit;
                bEven = !bEven;
            }
            return (nCheck % 10) === 0;
        }

        function setupCardLiveEvents() {
            const numInput = document.getElementById('cardNumber');
            const holderInput = document.getElementById('cardHolder');
            const expInput = document.getElementById('cardExpiry');
            const cvvInput = document.getElementById('cardCvv');

            const numPreview = document.getElementById('cardNumberPreview');
            const holderPreview = document.getElementById('cardHolderPreview');
            const expPreview = document.getElementById('cardExpiryPreview');
            const brandBadge = document.getElementById('cardBrandBadge');
            const numIcon = document.getElementById('cardNumberIcon');
            const numFeedback = document.getElementById('cardNumberFeedback');

            if (numInput) {
                numInput.addEventListener('input', function() {
                    let val = this.value.replace(/\D/g, '');
                    const brand = detectCardBrand(val);
                    
                    let formatted = '';
                    if (brand.name === 'American Express') {
                        val = val.substring(0, 15);
                        const part1 = val.substring(0, 4);
                        const part2 = val.substring(4, 10);
                        const part3 = val.substring(10, 15);
                        formatted = [part1, part2, part3].filter(Boolean).join(' ');
                    } else {
                        val = val.substring(0, 16);
                        const parts = [];
                        for (let i = 0; i < val.length; i += 4) {
                            parts.push(val.substring(i, i + 4));
                        }
                        formatted = parts.join(' ');
                    }
                    this.value = formatted;

                    if (numPreview) {
                        numPreview.textContent = formatted || '•••• •••• •••• ••••';
                    }
                    if (brandBadge) {
                        brandBadge.innerHTML = `<span>${brand.badge}</span>`;
                    }
                    if (numIcon) {
                        numIcon.textContent = brand.icon;
                    }

                    const minLen = brand.name === 'American Express' ? 15 : 16;
                    if (val.length >= minLen) {
                        if (checkLuhn(val)) {
                            numFeedback.innerHTML = `<span style="color:#22c55e; font-weight:700;">✅ Tarjeta ${brand.name} válida</span>`;
                            this.style.borderColor = '#22c55e';
                        } else {
                            numFeedback.innerHTML = `<span style="color:#f59e0b;">⚠️ Verifica el número de tarjeta</span>`;
                            this.style.borderColor = '#f59e0b';
                        }
                    } else {
                        numFeedback.textContent = `Aceptamos ${brand.name !== 'Desconocida' ? brand.name : 'Visa, Mastercard, American Express y Carnet'}`;
                        this.style.borderColor = '';
                    }
                });
            }

            if (holderInput) {
                holderInput.addEventListener('input', function() {
                    this.value = this.value.toUpperCase();
                    if (holderPreview) {
                        holderPreview.textContent = this.value || 'NOMBRE DEL TITULAR';
                    }
                });
            }

            if (expInput) {
                expInput.addEventListener('input', function() {
                    let val = this.value.replace(/\D/g, '').substring(0, 4);
                    if (val.length >= 2) {
                        let mm = parseInt(val.substring(0, 2), 10);
                        if (mm > 12) mm = 12;
                        if (mm === 0) mm = 1;
                        const mmStr = String(mm).padStart(2, '0');
                        val = mmStr + (val.length > 2 ? '/' + val.substring(2, 4) : '/');
                    }
                    this.value = val;
                    if (expPreview) {
                        expPreview.textContent = val || 'MM/AA';
                    }
                });
            }

            if (cvvInput) {
                cvvInput.addEventListener('input', function() {
                    this.value = this.value.replace(/\D/g, '').substring(0, 4);
                });
            }
        }

        let currentCardType = 'credit';

        function setCardType(type) {
            currentCardType = type;
            const btnCredit = document.getElementById('btnCardTypeCredit');
            const btnDebit = document.getElementById('btnCardTypeDebit');
            const hiddenCardType = document.getElementById('cardType');
            const cardTypeTag = document.getElementById('cardTypeTag');
            const financingLabel = document.getElementById('financingLabel');
            const debitNotice = document.getElementById('debitNotice');
            const brandBadge = document.getElementById('cardBrandBadge');

            if (hiddenCardType) hiddenCardType.value = type;

            const currentTotal = parseFloat(document.getElementById('summaryTotal')?.textContent?.replace(/[^0-9.]/g, '') || '0');

            if (type === 'credit') {
                if (btnCredit) {
                    btnCredit.style.border = '2px solid #ff7f00';
                    btnCredit.style.background = 'rgba(255, 127, 0, 0.15)';
                    btnCredit.style.color = '#ffffff';
                }
                if (btnDebit) {
                    btnDebit.style.border = '1px solid rgba(255, 255, 255, 0.15)';
                    btnDebit.style.background = '#1a1a20';
                    btnDebit.style.color = '#a0a0a0';
                }
                if (cardTypeTag) {
                    cardTypeTag.textContent = '💳 Crédito';
                    cardTypeTag.style.background = 'rgba(255,127,0,0.2)';
                    cardTypeTag.style.color = '#ff9f43';
                }
                if (financingLabel) financingLabel.textContent = 'Plan de Financiamiento (Meses Sin Intereses):';
                if (debitNotice) debitNotice.style.display = 'none';

                updateInstallmentOptions(currentTotal);
            } else {
                if (btnDebit) {
                    btnDebit.style.border = '2px solid #38bdf8';
                    btnDebit.style.background = 'rgba(56, 189, 248, 0.15)';
                    btnDebit.style.color = '#ffffff';
                }
                if (btnCredit) {
                    btnCredit.style.border = '1px solid rgba(255, 255, 255, 0.15)';
                    btnCredit.style.background = '#1a1a20';
                    btnCredit.style.color = '#a0a0a0';
                }
                if (cardTypeTag) {
                    cardTypeTag.textContent = '🏧 Débito';
                    cardTypeTag.style.background = 'rgba(56, 189, 248, 0.2)';
                    cardTypeTag.style.color = '#38bdf8';
                }
                if (financingLabel) financingLabel.textContent = 'Modalidad de Cobro (Débito):';
                if (debitNotice) debitNotice.style.display = 'block';

                const select = document.getElementById('cardInstallments');
                if (select) {
                    select.innerHTML = `<option value="1">1 Pago único de $${currentTotal.toFixed(2)} (Débito - Pago completo)</option>`;
                }
            }
        }

        function updateInstallmentOptions(total) {
            const select = document.getElementById('cardInstallments');
            if (!select) return;

            const tot = Number(total) || 0;
            if (currentCardType === 'debit') {
                select.innerHTML = `<option value="1">1 Pago único de $${tot.toFixed(2)} (Débito - Pago completo)</option>`;
                return;
            }

            const p3 = (tot / 3).toFixed(2);
            const p6 = (tot / 6).toFixed(2);
            const p12 = (tot / 12).toFixed(2);

            let opts = `
                <option value="1">1 Pago único de $${tot.toFixed(2)} (Sin intereses)</option>
                <option value="3">3 Meses Sin Intereses de $${p3} / mes</option>
                <option value="6">6 Meses Sin Intereses de $${p6} / mes</option>
                <option value="12">12 Meses Sin Intereses de $${p12} / mes</option>
            `;

            select.innerHTML = opts;
        }

        // Sincronizar campos de dirección divididos a address completo
        function syncFullAddress() {
            const street = document.getElementById('street')?.value?.trim() || '';
            const numExt = document.getElementById('numExt')?.value?.trim() || '';
            const numInt = document.getElementById('numInt')?.value?.trim() || '';
            const col = document.getElementById('colonia')?.value?.trim() || '';
            const city = document.getElementById('city')?.value?.trim() || '';
            const state = document.getElementById('state')?.value?.trim() || '';
            const cp = document.getElementById('postalCode')?.value?.trim() || '';

            let parts = [];
            if (street) {
                let st = street;
                if (numExt) st += ` #${numExt}`;
                if (numInt) st += ` Int ${numInt}`;
                parts.push(st);
            }
            if (col) parts.push(`Col. ${col}`);

            const fullAddr = parts.join(', ') || street;
            const hiddenAddr = document.getElementById('address');
            if (hiddenAddr) hiddenAddr.value = fullAddr;

            const fullSummary = [fullAddr, city, state, cp ? `C.P. ${cp}` : ''].filter(Boolean).join(', ');
            const statusEl = document.getElementById('mapSelectedAddress');
            if (statusEl && fullSummary) {
                statusEl.innerHTML = `<span style="color: #22c55e; font-weight: 600;">${fullSummary}</span>`;
            }
            return fullAddr;
        }

        window.fillTestCard = function() {
            const holder = document.getElementById('cardHolder');
            const num = document.getElementById('cardNumber');
            const exp = document.getElementById('cardExpiry');
            const cvv = document.getElementById('cardCvv');
            if (holder) {
                holder.value = 'JUAN CARLOS PEREZ';
                holder.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (num) {
                num.value = '4242 4242 4242 4242';
                num.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (exp) {
                exp.value = '12/28';
                exp.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (cvv) {
                cvv.value = '123';
                cvv.dispatchEvent(new Event('input', { bubbles: true }));
            }
            const terms = document.getElementById('termsAccepted');
            if (terms) terms.checked = true;
            if (typeof showAlert === 'function') {
                showAlert('Datos de tarjeta de prueba ingresados.', 'success');
            }
        };

        // Event listeners
        document.querySelectorAll('input[name="shippingMethod"]').forEach(input => {
            input.addEventListener('change', () => {
                const subtotal = parseFloat(document.getElementById('summarySubtotal').textContent.replace('$', '')) || 0;
                updateTotals(subtotal);
            });
        });

        document.getElementById('checkoutForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const form = document.getElementById('checkoutForm');
            const submitBtn = document.getElementById('submitBtn');
            const formMessage = document.getElementById('formMessage');

            // 1. Validación unificada con FormValidator para contacto y domicilio
            if (window.FormValidator && !window.FormValidator.validateForm(form)) {
                formMessage.innerHTML = '<div class="error-message">⚠️ Por favor completa o corrige los campos marcados en rojo antes de continuar.</div>';
                if (typeof showAlert === 'function') {
                    showAlert('Por favor corrige los campos marcados en rojo.', 'warning');
                }
                return;
            }

            // 2. Validación de factura fiscal si se solicitó
            const requireInvoice = document.getElementById('requireInvoice')?.checked || false;
            if (requireInvoice) {
                const rfcInput = document.getElementById('rfc');
                const taxNameInput = document.getElementById('taxName');
                const taxRegimeInput = document.getElementById('taxRegime');
                const zipFiscalInput = document.getElementById('zipCodeFiscal');

                if (window.FormValidator) {
                    let fiscalOk = true;
                    if (!window.FormValidator.validateField(rfcInput).valid) fiscalOk = false;
                    if (!window.FormValidator.validateField(taxNameInput).valid) fiscalOk = false;
                    if (!window.FormValidator.validateField(taxRegimeInput).valid) fiscalOk = false;
                    if (!window.FormValidator.validateField(zipFiscalInput).valid) fiscalOk = false;
                    if (!fiscalOk) {
                        formMessage.innerHTML = '<div class="error-message">⚠️ Verifica los datos fiscales requeridos para el CFDI 4.0.</div>';
                        if (typeof showAlert === 'function') {
                            showAlert('Verifica los datos fiscales requeridos para el CFDI 4.0.', 'warning');
                        }
                        return;
                    }
                }
            }

            // 3. Validar términos y condiciones
            if (!document.getElementById('termsAccepted').checked) {
                formMessage.innerHTML = '<div class="error-message">❌ Debes aceptar los términos y condiciones de compra</div>';
                if (typeof showAlert === 'function') showAlert('Debes aceptar los términos y condiciones', 'warning');
                document.getElementById('termsAccepted').focus();
                return;
            }

            // 4. Validar campos de tarjeta
            const cardHolderVal = (document.getElementById('cardHolder')?.value || '').trim();
            const cardNumberRaw = (document.getElementById('cardNumber')?.value || '').replace(/\D/g, '');
            const cardExpiryVal = (document.getElementById('cardExpiry')?.value || '').trim();
            const cardCvvVal = (document.getElementById('cardCvv')?.value || '').trim();
            const cardBrandInfo = detectCardBrand(cardNumberRaw);

            if (!cardHolderVal || cardHolderVal.length < 3) {
                formMessage.innerHTML = '<div class="error-message">❌ Ingresa el nombre del titular como aparece en la tarjeta</div>';
                document.getElementById('cardHolder')?.focus();
                return;
            }

            const minDigits = cardBrandInfo.name === 'American Express' ? 15 : 16;
            if (cardNumberRaw.length < minDigits || !checkLuhn(cardNumberRaw)) {
                formMessage.innerHTML = '<div class="error-message">❌ El número de tarjeta no es válido. Verifica los dígitos.</div>';
                document.getElementById('cardNumber')?.focus();
                return;
            }

            if (!/^(0[1-9]|1[0-2])\/\d{2}$/.test(cardExpiryVal)) {
                formMessage.innerHTML = '<div class="error-message">❌ Ingresa una fecha de expiración válida (formato MM/AA)</div>';
                document.getElementById('cardExpiry')?.focus();
                return;
            }

            const expParts = cardExpiryVal.split('/');
            const expMonth = parseInt(expParts[0], 10);
            const expYear = 2000 + parseInt(expParts[1], 10);
            const today = new Date();
            if (expYear < today.getFullYear() || (expYear === today.getFullYear() && expMonth < (today.getMonth() + 1))) {
                formMessage.innerHTML = '<div class="error-message">❌ La tarjeta ingresada se encuentra vencida</div>';
                document.getElementById('cardExpiry')?.focus();
                return;
            }

            if (cardCvvVal.length < 3 || cardCvvVal.length > 4) {
                formMessage.innerHTML = '<div class="error-message">❌ Ingresa el código de seguridad CVV (3 o 4 dígitos)</div>';
                document.getElementById('cardCvv')?.focus();
                return;
            }

            // Sincronizar dirección antes de enviar
            const fullAddress = syncFullAddress();

            // Obtener y sanitizar datos del formulario
            const sanitize = window.FormValidator ? window.FormValidator.sanitize : (val, type) => String(val || '').trim();
            const formData = new FormData(form);

            const data = {
                csrf_token: window.csrfToken,
                firstName: sanitize(formData.get('firstName'), 'text'),
                lastName: sanitize(formData.get('lastName'), 'text'),
                email: sanitize(formData.get('email'), 'text'),
                phone: sanitize(formData.get('phone'), 'phone'),
                address: sanitize(fullAddress, 'text'),
                street: sanitize(document.getElementById('street')?.value, 'text'),
                numExt: sanitize(document.getElementById('numExt')?.value, 'alphanumeric'),
                numInt: sanitize(document.getElementById('numInt')?.value, 'alphanumeric'),
                colonia: sanitize(document.getElementById('colonia')?.value, 'text'),
                city: sanitize(formData.get('city'), 'text'),
                postalCode: sanitize(formData.get('postalCode'), 'postal_code'),
                deliveryNotes: sanitize(formData.get('deliveryNotes'), 'text'),
                shippingMethod: sanitize(formData.get('shippingMethod'), 'alphanumeric'),
                promoCode: sanitize(formData.get('promoCode'), 'alphanumeric'),
                orderNotes: sanitize(formData.get('orderNotes'), 'text'),
                paymentMethod: 'card',
                cardType: currentCardType,
                cardHolder: sanitize(cardHolderVal, 'text').toUpperCase(),
                cardBrand: cardBrandInfo.name,
                cardLast4: cardNumberRaw.slice(-4),
                cardInstallments: currentCardType === 'debit' ? '1' : (formData.get('cardInstallments') || '1'),
                requireInvoice: requireInvoice,
                rfc: requireInvoice ? sanitize(formData.get('rfc'), 'rfc') : '',
                taxName: requireInvoice ? sanitize(formData.get('taxName'), 'text') : '',
                taxRegime: requireInvoice ? sanitize(formData.get('taxRegime'), 'alphanumeric') : '',
                zipCodeFiscal: requireInvoice ? sanitize(formData.get('zipCodeFiscal'), 'postal_code') : '',
                cfdiUse: requireInvoice ? (formData.get('cfdiUse') || 'G03') : 'G03',
                cartItems: JSON.parse(localStorage.getItem(CART_KEY) || '[]')
            };

            // Submit
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Procesando pago seguro...';
            formMessage.innerHTML = '';

            try {
                const csrfVal = window.csrfToken || (document.cookie.split('; ').find(row => row.startsWith('csrf_token='))?.split('=')[1]) || '';
                const response = await fetch('/api/checkout.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfVal
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        ...data,
                        csrf_token: csrfVal
                    })
                });

                const result = await response.json();

                if (result.success) {
                    localStorage.removeItem(CART_KEY);
                    const folioStr = result.folio || result.order_number || result.order_id;
                    window.location.href = 'order_confirmation.php?folio=' + encodeURIComponent(folioStr);
                } else {
                    formMessage.innerHTML = '<div class="error-message">❌ ' + (result.message || 'Error al procesar el pedido') + '</div>';
                    submitBtn.disabled = false;
                    submitBtn.textContent = '✅ Confirmar Pedido';
                }
            } catch (e) {
                console.error('Checkout error:', e);
                formMessage.innerHTML = '<div class="error-message">❌ Error de conexión. Por favor, intenta de nuevo.</div>';
                submitBtn.disabled = false;
                submitBtn.textContent = '✅ Confirmar Pedido';
            }
        });

        // Inicialización integral de la página
        function initCheckoutPage() {
            loadCartSummary();
            setupCardLiveEvents();
            initDeliveryMap();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initCheckoutPage);
        } else {
            initCheckoutPage();
        }
        
        // Geolocalización y autocompletado de código postal
        const postalCodeInput = document.getElementById('postalCode');
        const cityInput = document.getElementById('city');
        const stateInput = document.getElementById('state');
        const cpInfo = document.getElementById('cpInfo');
        
        // Autocompletar ciudad y estado al ingresar código postal
        postalCodeInput.addEventListener('blur', async function() {
            const cp = this.value.trim();
            if (cp.length === 5) {
                cpInfo.textContent = 'Buscando información del código postal...';
                try {
                    let found = false;
                    // 1. Intentar Copomex
                    try {
                        const response = await fetch(`https://api.copomex.com/query/info_cp_cp?cp=${cp}&token=pruebas`);
                        const data = await response.json();
                        if (data && data.response && data.response.municipio) {
                            const info = data.response;
                            if (info.municipio) cityInput.value = info.municipio;
                            if (info.estado) stateInput.value = info.estado;
                            cpInfo.textContent = `✅ ${info.municipio}, ${info.estado}`;
                            cpInfo.style.color = '#22c55e';
                            found = true;
                            if (info.municipio && deliveryMap) {
                                geocodeAddressText(`${info.municipio}, ${info.estado || ''}, México`);
                            }
                        }
                    } catch (_) {}

                    // 2. Fallback a nuestro proxy backend geocode
                    if (!found) {
                        const geoRes = await fetch(`/api/geocode.php?action=search&q=${encodeURIComponent(cp)}`);
                        if (geoRes.ok) {
                            const geoData = await geoRes.json();
                            if (geoData.success && Array.isArray(geoData.results) && geoData.results[0]) {
                                const r = geoData.results[0];
                                const c = (r.address && (r.address.city || r.address.county || r.address.town || r.address.municipality)) || '';
                                const s = (r.address && r.address.state) || '';
                                if (c) cityInput.value = c;
                                if (s) stateInput.value = s;
                                cpInfo.textContent = `✅ ${c || cp}, ${s}`;
                                cpInfo.style.color = '#22c55e';
                                found = true;
                                if (r.lat && r.lon && deliveryMap) {
                                    const lat = parseFloat(r.lat);
                                    const lng = parseFloat(r.lon);
                                    deliveryMap.setView([lat, lng], 14);
                                    if (deliveryMarker) deliveryMarker.setLatLng([lat, lng]);
                                }
                            }
                        }
                    }

                    if (!found) {
                        cpInfo.textContent = 'Código postal registrado (verifica ciudad y estado)';
                        cpInfo.style.color = '#888';
                    }
                } catch (error) {
                    cpInfo.textContent = 'Código postal válido (verifica ciudad y estado)';
                    cpInfo.style.color = '#888';
                }
            }
        });

        // ===== MAPA INTERACTIVO Y GEOLOCALIZACIÓN LEAFLET =====
        let deliveryMap = null;
        let deliveryMarker = null;

        const defaultLat = 20.6597; // Guadalajara / Jalisco
        const defaultLng = -103.3496;

        function createPinIcon() {
            return L.divIcon({
                className: 'fox-pin-marker',
                html: `
                    <div style="position: relative; width: 34px; height: 44px; transform: translate(-17px, -44px);">
                        <svg width="34" height="44" viewBox="0 0 34 44" fill="none" xmlns="http://www.w3.org/2000/svg" style="filter: drop-shadow(0 4px 6px rgba(0,0,0,0.6));">
                            <path d="M17 0C7.61 0 0 7.61 0 17C0 28.5 17 44 17 44C17 44 34 28.5 34 17C34 7.61 26.39 0 17 0Z" fill="#ff7f00"/>
                            <circle cx="17" cy="17" r="7" fill="#ffffff"/>
                            <circle cx="17" cy="17" r="3.5" fill="#111111"/>
                        </svg>
                    </div>
                `,
                iconSize: [0, 0],
                iconAnchor: [0, 0]
            });
        }

        function initDeliveryMap() {
            const mapEl = document.getElementById('deliveryMap');
            if (!mapEl || typeof L === 'undefined') return;

            deliveryMap = L.map('deliveryMap', {
                center: [defaultLat, defaultLng],
                zoom: 13,
                zoomControl: true
            });

            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(deliveryMap);

            deliveryMarker = L.marker([defaultLat, defaultLng], {
                draggable: true,
                icon: createPinIcon()
            }).addTo(deliveryMap);

            // Al hacer clic en el mapa
            deliveryMap.on('click', function(e) {
                updateLocationFromCoords(e.latlng.lat, e.latlng.lng, true);
            });

            // Al arrastrar el pin
            deliveryMarker.on('dragend', function(e) {
                const pos = e.target.getLatLng();
                updateLocationFromCoords(pos.lat, pos.lng, true);
            });

            // Si ya hay dirección ingresada previamente, poblar los campos
            const currentAddr = document.getElementById('address')?.value?.trim();
            const currentCity = document.getElementById('city')?.value?.trim();
            if (currentAddr && !document.getElementById('street')?.value) {
                let st = currentAddr;
                let col = '';
                if (st.includes(', Col. ')) {
                    const p = st.split(', Col. ');
                    st = p[0];
                    col = p[1] || '';
                }
                const streetEl = document.getElementById('street');
                const colEl = document.getElementById('colonia');
                if (streetEl) streetEl.value = st;
                if (colEl && col) colEl.value = col;
            }

            // Escuchar cambios manuales en los campos de dirección
            ['street', 'numExt', 'numInt', 'colonia', 'city', 'state', 'postalCode'].forEach(id => {
                document.getElementById(id)?.addEventListener('input', syncFullAddress);
            });

            if (currentAddr || currentCity) {
                geocodeAddressText(`${currentAddr || ''} ${currentCity || ''}, México`);
            } else {
                // Centrar automáticamente en la zona por IP al iniciar
                fallbackToIpLocation(false);
            }

            // Inicializar sugerencias de dirección en el campo de texto
            initAddressSuggestions();

            // Ajustar renderizado tras render inicial
            setTimeout(() => {
                if (deliveryMap) deliveryMap.invalidateSize();
            }, 300);
        }

        // Actualizar coordenadas y autocompletar campos del formulario
        async function updateLocationFromCoords(lat, lng, fetchAddress = true) {
            if (!deliveryMarker || !deliveryMap) return;

            deliveryMarker.setLatLng([lat, lng]);
            deliveryMap.panTo([lat, lng]);

            const coordsEl = document.getElementById('mapCoords');
            if (coordsEl) {
                coordsEl.textContent = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
            }

            if (!fetchAddress) return;

            const statusEl = document.getElementById('mapSelectedAddress');
            if (statusEl) {
                statusEl.innerHTML = '<span style="color: #ff9f43;">⏳ Obteniendo dirección exacta...</span>';
            }

            try {
                const info = await reverseGeocodeCoords(lat, lng);
                if (info && (info.road || info.city || info.state || info.postcode || info.neighbourhood)) {
                    const streetInput = document.getElementById('street');
                    const numExtInput = document.getElementById('numExt');
                    const colInput = document.getElementById('colonia');
                    const cInput = document.getElementById('city');
                    const sInput = document.getElementById('state');
                    const cpInput = document.getElementById('postalCode');
                    const cpInfoEl = document.getElementById('cpInfo');

                    const streetName = info.rawRoad || info.road || '';
                    if (streetName && streetInput) {
                        streetInput.value = streetName;
                        streetInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                    if (info.houseNumber && numExtInput) {
                        numExtInput.value = info.houseNumber;
                        numExtInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                    if (info.neighbourhood && colInput) {
                        colInput.value = info.neighbourhood;
                        colInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                    if (info.city && cInput) {
                        cInput.value = info.city;
                        cInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                    if (info.state && sInput) {
                        sInput.value = info.state;
                        sInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                    if (info.postcode && cpInput) {
                        cpInput.value = info.postcode;
                        cpInput.dispatchEvent(new Event('input', { bubbles: true }));
                        if (cpInfoEl) {
                            cpInfoEl.textContent = `✅ C.P. ${info.postcode} (${info.city || ''}, ${info.state || ''})`;
                            cpInfoEl.style.color = '#22c55e';
                        }
                    }

                    const fullSummary = syncFullAddress();

                    if (window.showToast) {
                        window.showToast('success', '📍 Domicilio Autocompletado', streetName || 'Datos aplicados al formulario.');
                    }
                } else {
                    if (statusEl) {
                        statusEl.textContent = `Coordenadas: ${lat.toFixed(5)}, ${lng.toFixed(5)} (Por favor indica calle y número)`;
                    }
                }
            } catch (err) {
                console.warn('Error en reverse geocoding:', err);
                if (statusEl) {
                    statusEl.textContent = `Punto fijado: ${lat.toFixed(5)}, ${lng.toFixed(5)}`;
                }
            }
        }

        // Llamada al proxy seguro del backend para reverse geocoding
        async function reverseGeocodeCoords(lat, lng) {
            try {
                const res = await fetch(`/api/geocode.php?action=reverse&lat=${encodeURIComponent(lat)}&lng=${encodeURIComponent(lng)}`);
                if (res.ok) {
                    const data = await res.json();
                    if (data && data.success) {
                        return {
                            road: data.road || data.address || '',
                            rawRoad: data.road || '',
                            houseNumber: data.house_number || '',
                            neighbourhood: data.neighbourhood || '',
                            city: data.city || '',
                            state: data.state || '',
                            postcode: data.postcode || '',
                            displayName: data.display_name || ''
                        };
                    }
                }
            } catch (e) {
                console.warn('Reverse geocode error:', e);
            }
            return null;
        }

        // Buscar texto de dirección para centrar mapa
        async function geocodeAddressText(query) {
            if (!query || !deliveryMap) return;
            try {
                const res = await fetch(`/api/geocode.php?action=search&q=${encodeURIComponent(query)}`);
                if (res.ok) {
                    const data = await res.json();
                    if (data && data.results && data.results[0]) {
                        const lat = parseFloat(data.results[0].lat);
                        const lng = parseFloat(data.results[0].lon);
                        deliveryMap.setView([lat, lng], 16);
                        deliveryMarker.setLatLng([lat, lng]);
                        const coordsEl = document.getElementById('mapCoords');
                        if (coordsEl) coordsEl.textContent = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
                    }
                }
            } catch (_) {}
        }

        // Sugerencias interactivas al escribir en Calle
        function initAddressSuggestions() {
            const streetInput = document.getElementById('street');
            const addressSuggestionsEl = document.getElementById('addressSuggestions');
            if (!streetInput || !addressSuggestionsEl) return;

            let searchTimeout = null;

            streetInput.addEventListener('input', function() {
                const q = this.value.trim();
                clearTimeout(searchTimeout);
                if (q.length < 3) {
                    addressSuggestionsEl.style.display = 'none';
                    addressSuggestionsEl.innerHTML = '';
                    return;
                }

                searchTimeout = setTimeout(async () => {
                    try {
                        const res = await fetch(`/api/geocode.php?action=search&q=${encodeURIComponent(q)}`);
                        if (!res.ok) return;
                        const json = await res.json();
                        if (!json.success || !Array.isArray(json.results) || json.results.length === 0) {
                            addressSuggestionsEl.style.display = 'none';
                            return;
                        }

                        addressSuggestionsEl.innerHTML = json.results.map(r => {
                            const title = r.name || (r.address && r.address.road) || r.display_name.split(',')[0];
                            return `
                                <div class="address-sugg-item" data-lat="${r.lat}" data-lng="${r.lon}" style="padding: 10px 14px; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.08); font-size: 0.83rem; color: #e0e0e0; transition: background 0.15s ease;">
                                    <strong style="color: #ff9f43; display: block;">📍 ${title}</strong>
                                    <span style="font-size: 0.76rem; color: #aaa; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${r.display_name}</span>
                                </div>
                            `;
                        }).join('');
                        addressSuggestionsEl.style.display = 'block';

                        addressSuggestionsEl.querySelectorAll('.address-sugg-item').forEach(item => {
                            item.addEventListener('mouseenter', () => item.style.background = 'rgba(255,127,0,0.18)');
                            item.addEventListener('mouseleave', () => item.style.background = 'transparent');
                            item.addEventListener('click', () => {
                                const lat = parseFloat(item.getAttribute('data-lat'));
                                const lng = parseFloat(item.getAttribute('data-lng'));
                                addressSuggestionsEl.style.display = 'none';
                                if (lat && lng) {
                                    if (deliveryMap) deliveryMap.setView([lat, lng], 17);
                                    updateLocationFromCoords(lat, lng, true);
                                }
                            });
                        });
                    } catch (_) {}
                }, 350);
            });

            document.addEventListener('click', function(e) {
                if (!streetInput.contains(e.target) && !addressSuggestionsEl.contains(e.target)) {
                    addressSuggestionsEl.style.display = 'none';
                }
            });
        }

        // Obtener ubicación GPS actual con detección de permisos y fallback inteligente por IP
        async function getCurrentLocation() {
            const btn = document.getElementById('btnGetCurrentLocation');
            const geoIcon = document.getElementById('geoIcon');
            const geoText = document.getElementById('geoText');
            const notice = document.getElementById('gpsPermissionNotice');

            if (!navigator.geolocation) {
                if (window.showToast) window.showToast('error', 'Geolocalización no soportada', 'Tu navegador no soporta detección de ubicación.');
                fallbackToIpLocation(true);
                return;
            }

            // Verificar si el navegador ya tiene el permiso bloqueado
            if (navigator.permissions && navigator.permissions.query) {
                try {
                    const status = await navigator.permissions.query({ name: 'geolocation' });
                    if (status.state === 'denied') {
                        if (notice) notice.style.display = 'block';
                        if (window.showToast) {
                            window.showToast('warning', 'Permiso Bloqueado en Navegador', 'Haz clic en el candado 🔒 de tu barra de direcciones para permitir ubicación.', 8000);
                        }
                        fallbackToIpLocation(true);
                        return;
                    }
                } catch (_) {}
            }

            if (btn) btn.disabled = true;
            if (geoIcon) geoIcon.textContent = '⏳';
            if (geoText) geoText.textContent = 'Detectando ubicación...';

            if (window.showToast) {
                window.showToast('info', 'Permisos de Ubicación', 'Por favor pulsa "Permitir" si tu navegador te lo solicita.', 4000);
            }

            const handleSuccess = function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;

                if (btn) btn.disabled = false;
                if (geoIcon) geoIcon.textContent = '📍';
                if (geoText) geoText.textContent = 'Usar mi ubicación actual';
                if (notice) notice.style.display = 'none';

                if (deliveryMap) {
                    deliveryMap.setView([lat, lng], 17);
                }
                updateLocationFromCoords(lat, lng, true);

                if (window.showToast) {
                    window.showToast('success', '📍 Ubicación Detectada', 'Se centró el mapa y se completaron los datos de tu domicilio.');
                }
            };

            const handleError = function(error) {
                if (btn) btn.disabled = false;
                if (geoIcon) geoIcon.textContent = '📍';
                if (geoText) geoText.textContent = 'Usar mi ubicación actual';

                if (error.code === error.PERMISSION_DENIED) {
                    if (notice) notice.style.display = 'block';
                    if (window.showToast) {
                        window.showToast('warning', 'Permiso Denegado', 'El navegador tiene bloqueado el GPS. Sigue las instrucciones arriba del mapa o haz clic directo en tu calle.', 8000);
                    }
                } else if (error.code === error.TIMEOUT) {
                    if (window.showToast) {
                        window.showToast('warning', 'Tiempo de GPS Agotado', 'Centrando mapa en tu zona aproximada...', 4000);
                    }
                }

                // Fallback automático por IP para que el mapa se posicione en su zona
                fallbackToIpLocation(true);
            };

            navigator.geolocation.getCurrentPosition(
                handleSuccess,
                function(firstErr) {
                    if (firstErr.code === firstErr.TIMEOUT || firstErr.code === firstErr.POSITION_UNAVAILABLE) {
                        navigator.geolocation.getCurrentPosition(handleSuccess, handleError, {
                            enableHighAccuracy: false,
                            timeout: 8000,
                            maximumAge: 60000
                        });
                    } else {
                        handleError(firstErr);
                    }
                },
                { enableHighAccuracy: true, timeout: 7000, maximumAge: 30000 }
            );
        }

        // Fallback por IP: centra el mapa en la ciudad/zona aproximada del usuario
        async function fallbackToIpLocation(showStatus = false) {
            try {
                const res = await fetch('/api/geocode.php?action=ip');
                if (res.ok) {
                    const data = await res.json();
                    if (data && data.success && data.lat && data.lng) {
                        if (deliveryMap) {
                            deliveryMap.setView([data.lat, data.lng], 14);
                            if (deliveryMarker) {
                                deliveryMarker.setLatLng([data.lat, data.lng]);
                            }
                        }
                        const cInput = document.getElementById('city');
                        const sInput = document.getElementById('state');
                        const cpInput = document.getElementById('postalCode');
                        if (cInput && !cInput.value && data.city) cInput.value = data.city;
                        if (sInput && !sInput.value && data.state) sInput.value = data.state;
                        if (cpInput && !cpInput.value && data.postcode) cpInput.value = data.postcode;

                        const coordsEl = document.getElementById('mapCoords');
                        if (coordsEl) coordsEl.textContent = `${data.lat.toFixed(5)}, ${data.lng.toFixed(5)}`;

                        if (showStatus) {
                            const statusEl = document.getElementById('mapSelectedAddress');
                            if (statusEl) {
                                statusEl.innerHTML = `<span style="color: #ff9f43;">📍 Zona aproximada: ${data.city || 'Guadalajara'}, ${data.state || 'Jalisco'} — Haz clic en el mapa para marcar tu calle exacta.</span>`;
                            }
                        }
                    }
                }
            } catch (e) {
                console.warn('IP fallback failed:', e);
            }
        }

        // ===== FUNCIONES MODAL TÉRMINOS Y PRIVACIDAD =====
        function openLegalModal(tab = 'terms') {
            const modal = document.getElementById('legalModal');
            if (modal) {
                modal.style.display = 'flex';
                switchLegalTab(tab);
            }
        }

        function closeLegalModal() {
            const modal = document.getElementById('legalModal');
            if (modal) modal.style.display = 'none';
        }

        function switchLegalTab(tab) {
            const body = document.getElementById('legalModalBody');
            const tabTerms = document.getElementById('tabBtnTerms');
            const tabPriv = document.getElementById('tabBtnPrivacy');
            const fullLink = document.getElementById('legalFullPageLink');

            if (tab === 'terms') {
                if (tabTerms) {
                    tabTerms.style.background = 'rgba(255,127,0,0.2)';
                    tabTerms.style.borderColor = '#ff7f00';
                    tabTerms.style.color = '#fff';
                }
                if (tabPriv) {
                    tabPriv.style.background = 'transparent';
                    tabPriv.style.borderColor = 'rgba(255,255,255,0.15)';
                    tabPriv.style.color = '#aaa';
                }
                if (fullLink) { fullLink.href = 'terminos.php'; fullLink.textContent = 'Ver Términos completos ↗'; }
                if (body) {
                    body.innerHTML = `
                        <h3 style="color:#ff7f00; margin-top:0; font-size:1.15rem;">📜 Términos y Condiciones de Compra - Ferretería FOX</h3>
                        <p><strong>1. Objeto y Alcance:</strong> Los presentes términos regulan la adquisición de productos en línea a través de Ferretería FOX en los Estados Unidos Mexicanos.</p>
                        <p><strong>2. Métodos de Pago:</strong> Aceptamos tarjetas de Crédito (con opción de hasta 12 Meses Sin Intereses en compras participantes) y Tarjetas de Débito (cobro en una sola exhibición por el importe exacto de la orden sin financiamiento diferido). Todos los pagos son encriptados bajo certificación PCI-DSS y autenticación 3D Secure 2.0.</p>
                        <p><strong>3. Facturación CFDI 4.0:</strong> Puedes solicitar tu comprobante fiscal digital proporcionando tu RFC, Razón Social, Régimen Fiscal y C.P. del domicilio fiscal al momento de finalizar el pedido.</p>
                        <p><strong>4. Envíos y Tiempos de Entrega:</strong> Los envíos estándar tienen un plazo de 3 a 7 días hábiles y envíos express de 1 a 3 días hábiles en zonas con cobertura.</p>
                        <p><strong>5. Garantía y Devoluciones (RMA):</strong> Todos los productos cuentan con garantía oficial de 30 días naturales ante defectos de fabricación.</p>
                    `;
                }
            } else {
                if (tabPriv) {
                    tabPriv.style.background = 'rgba(34,197,94,0.2)';
                    tabPriv.style.borderColor = '#22c55e';
                    tabPriv.style.color = '#fff';
                }
                if (tabTerms) {
                    tabTerms.style.background = 'transparent';
                    tabTerms.style.borderColor = 'rgba(255,255,255,0.15)';
                    tabTerms.style.color = '#aaa';
                }
                if (fullLink) { fullLink.href = 'privacidad.php'; fullLink.textContent = 'Ver Aviso de Privacidad completo ↗'; }
                if (body) {
                    body.innerHTML = `
                        <h3 style="color:#22c55e; margin-top:0; font-size:1.15rem;">🔒 Aviso de Privacidad y Protección de Datos (LFPDPPP)</h3>
                        <p><strong>Responsable:</strong> Ferretería FOX S.A. de C.V., con domicilio en Guadalajara, Jalisco, México.</p>
                        <p><strong>Datos Recabados:</strong> Nombre, teléfono, correo, domicilio de entrega (calle, número exterior, interior, colonia, ciudad y código postal) y datos fiscales en caso de solicitar CFDI 4.0.</p>
                        <p><strong>Protección Bancaria:</strong> Ferretería FOX no almacena números de tarjeta completos ni códigos CVV en sus servidores; las transacciones son tokenizadas a través de pasarelas bancarias seguras.</p>
                        <p><strong>Finalidad:</strong> Procesamiento de pedidos, entrega logística, facturación SAT y soporte posventa.</p>
                        <p><strong>Derechos ARCO:</strong> Puedes solicitar el acceso, rectificación, cancelación u oposición de tus datos personales enviando un correo a <em>privacidad@ferreteriafox.com</em>.</p>
                    `;
                }
            }
        }

        function acceptLegalFromModal() {
            const cb = document.getElementById('termsAccepted');
            if (cb) cb.checked = true;
            closeLegalModal();
            if (window.showToast) {
                window.showToast('success', 'Términos Aceptados', 'Has aceptado los términos y condiciones de compra.');
            }
        }
    </script>
    <script src="js/toast-notifications.js"></script>
    <script src="js/mobile-optimize.js"></script>

    <!-- Modal Interactivo de Términos y Condiciones / Política de Privacidad -->
    <div id="legalModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.82); z-index: 99999; backdrop-filter: blur(5px); align-items: center; justify-content: center; padding: 1.25rem;">
        <div style="background: #15151c; border: 1px solid rgba(255,127,0,0.3); border-radius: 14px; width: 100%; max-width: 760px; max-height: 85vh; display: flex; flex-direction: column; box-shadow: 0 25px 60px rgba(0,0,0,0.8); overflow: hidden;">
            <!-- Header Modal con Pestañas -->
            <div style="padding: 1.1rem 1.5rem; background: #111116; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div style="display: flex; gap: 8px;">
                    <button type="button" id="tabBtnTerms" onclick="switchLegalTab('terms')" style="background: rgba(255,127,0,0.2); border: 1px solid #ff7f00; color: #fff; padding: 6px 14px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; cursor: pointer;">📜 Términos y Condiciones</button>
                    <button type="button" id="tabBtnPrivacy" onclick="switchLegalTab('privacy')" style="background: transparent; border: 1px solid rgba(255,255,255,0.15); color: #aaa; padding: 6px 14px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; cursor: pointer;">🔒 Política de Privacidad</button>
                </div>
                <button type="button" onclick="closeLegalModal()" style="background: transparent; border: none; color: #aaa; font-size: 1.6rem; cursor: pointer; padding: 0 6px; line-height: 1;" title="Cerrar">&times;</button>
            </div>

            <!-- Contenido Scrolleable -->
            <div id="legalModalBody" style="padding: 1.75rem; overflow-y: auto; color: #cbd5e1; font-size: 0.88rem; line-height: 1.65;">
                <!-- Dinámico -->
            </div>

            <!-- Footer Modal -->
            <div style="padding: 1rem 1.5rem; background: #111116; border-top: 1px solid rgba(255,255,255,0.08); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <a id="legalFullPageLink" href="terminos.php" target="_blank" style="color: #ff9f43; font-size: 0.84rem; text-decoration: underline; font-weight: 600;">Ver Términos completos ↗</a>
                <div style="display: flex; gap: 10px;">
                    <button type="button" onclick="closeLegalModal()" style="background: transparent; border: 1px solid rgba(255,255,255,0.2); color: #ccc; padding: 8px 16px; border-radius: 6px; font-size: 0.85rem; cursor: pointer;">Cerrar</button>
                    <button type="button" onclick="acceptLegalFromModal()" style="background: linear-gradient(135deg, #ff7f00, #ff5500); border: none; color: #fff; padding: 8px 20px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; cursor: pointer;">Aceptar y Continuar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').catch(err => console.log('SW reg error:', err));
            });
        }
    </script>
</body>
</html>
