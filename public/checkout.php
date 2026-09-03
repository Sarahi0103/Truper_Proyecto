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
    <link rel="manifest" href="manifest.json">
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
                    <div class="nav-dropdown-content" style="min-width: 200px;">
                        <a href="orders.php">Ventas / Pedidos</a>
                        <a href="order_tracking.php">Seguimiento / Logística</a>
                        <a href="rma_manager.php">Devoluciones RMA</a>
                        <a href="admin_online_billing.php">Facturación & Pagos SAT</a>
                        <a href="admin_payment_config.php">Configuración de Pagos</a>
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

                <?php if (!$isLogged): ?>
                    <a href="login.php" class="btn btn-primary btn-small">Ingresar</a>
                <?php else: ?>
                    <span style="color: var(--theme-text); margin-right: 1rem;">Hola, <?php echo htmlspecialchars($user['first_name'] ?? 'Usuario'); ?></span>
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

        <?php if (!$isLogged): ?>
            <div class="auth-prompt" style="background: rgba(255, 127, 0, 0.08); border: 1px solid rgba(255, 127, 0, 0.25); padding: 1.25rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <div class="auth-prompt-text" style="color: #ff9f43; font-weight: 600; margin-bottom: 0.25rem;">💡 Puedes realizar tu pedido como Invitado sin registrarte llenando el formulario de abajo.</div>
                <div class="auth-prompt-text" style="font-size: 0.9rem; color: var(--theme-text-muted);">O si prefieres, inicia sesión o regístrate para acumular puntos de lealtad y seguir tus pedidos:</div>
                <div class="auth-buttons" style="margin-top: 0.75rem; display: flex; gap: 0.75rem;">
                    <a href="login.php?return_to=checkout.php" class="btn btn-primary" style="flex: 1; text-align: center;">Iniciar Sesión</a>
                    <a href="register.php?return_to=checkout.php" class="btn btn-secondary" style="flex: 1; text-align: center;">Registrarse</a>
                </div>
            </div>
        <?php endif; ?>

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
                        
                        <div class="form-group">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <label for="address" style="margin-bottom: 0;">Calle y Número *</label>
                                <button type="button" id="btnGetCurrentLocation" onclick="getCurrentLocation()" style="background: rgba(255,102,0,0.15); border: 1px solid rgba(255,102,0,0.4); color: #ff7f00; padding: 4px 12px; border-radius: 6px; font-weight: 700; font-size: 0.82rem; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s ease;">
                                    <span id="geoIcon">📍</span> <span id="geoText">Usar mi ubicación actual</span>
                                </button>
                            </div>
                            <input type="text" id="address" name="address" required placeholder="Ej: Av. Juárez 450, Col. Centro" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" autocomplete="street-address">
                            <div id="addressSuggestions" style="position: relative; z-index: 1000;"></div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="city">Ciudad *</label>
                                <input type="text" id="city" name="city" required placeholder="Ej: Guadalajara, Jalisco" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>" autocomplete="address-level2">
                            </div>
                            <div class="form-group">
                                <label for="postalCode">Código Postal *</label>
                                <input type="text" id="postalCode" name="postalCode" required placeholder="Ej: 44100" maxlength="5" pattern="\d{5}" value="<?php echo htmlspecialchars($user['postal_code'] ?? ''); ?>" autocomplete="postal-code">
                                <small class="text-muted" id="cpInfo">Ingresa tu código postal para autocompletar ciudad</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="state">Estado *</label>
                            <input type="text" id="state" name="state" required placeholder="Ej: Jalisco" value="<?php echo htmlspecialchars($user['state'] ?? ''); ?>" autocomplete="address-level1">
                        </div>

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

                    <!-- Payment Method -->
                    <div class="form-section">
                        <div class="form-section-title">💳 Método de Pago</div>
                        
                        <?php if ($isLogged && (float)($user['wallet_balance'] ?? 0) > 0): ?>
                        <div class="form-group" style="background: rgba(34,197,94,0.1); padding: 0.75rem; border-radius: 6px; border: 1px solid #22c55e; margin-bottom: 0.75rem;">
                            <label style="cursor:pointer; font-weight:700; color:#22c55e;">
                                <input type="radio" name="paymentMethod" value="wallet"> 
                                🟩 Monedero Digital / Crédito en Tienda (Saldo disponible: $<?php echo number_format((float)$user['wallet_balance'], 2); ?> MXN)
                            </label>
                        </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label>
                                <input type="radio" name="paymentMethod" value="card" checked onchange="handlePaymentMethodChange()"> 
                                <strong>Tarjeta de Crédito / Débito</strong>
                            </label>
                            <small style="color: var(--theme-text-muted);">Stripe / Mercado Pago - Pago seguro en línea</small>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="radio" name="paymentMethod" value="spei" onchange="handlePaymentMethodChange()"> 
                                <strong>Transferencia SPEI</strong>
                            </label>
                            <small style="color: var(--theme-text-muted);">Transferencia bancaria instantánea</small>
                        </div>
                        
                        <!-- Selección de banco para SPEI -->
                        <div id="bankSelectionSection" style="display: none; margin-left: 24px; padding: 16px; background: rgba(255,127,0,0.05); border-radius: 8px; border: 1px solid rgba(255,127,0,0.2); margin-top: 12px;">
                            <div class="form-group">
                                <label for="selectedBank" style="font-weight: 600; color: var(--theme-accent);">Selecciona tu banco:</label>
                                <select id="selectedBank" name="selectedBank" class="form-input" onchange="handleBankSelection()">
                                    <option value="">Cargando bancos...</option>
                                </select>
                            </div>
                            <div id="bankDetails" style="display: none; padding: 12px; background: rgba(255,255,255,0.05); border-radius: 6px; margin-top: 12px;">
                                <div style="font-weight: 700; color: #fff; margin-bottom: 8px;" id="bankNameDisplay"></div>
                                <div style="font-size: 0.9rem; color: #aaa; line-height: 1.6;">
                                    <div><strong>CLABE:</strong> <span id="bankClabeDisplay" style="font-family: monospace; font-size: 1rem; color: var(--theme-accent);"></span></div>
                                    <div><strong>Titular:</strong> <span id="bankHolderDisplay"></span></div>
                                    <div><strong>RFC:</strong> <span id="bankRfcDisplay"></span></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="radio" name="paymentMethod" value="transfer" onchange="handlePaymentMethodChange()"> 
                                <strong>Transferencia Bancaria Tradicional</strong>
                            </label>
                            <small style="color: var(--theme-text-muted);">Transferencia interbancaria (1-2 días hábiles)</small>
                        </div>
                        
                        <!-- Selección de banco para Transferencia -->
                        <div id="transferBankSection" style="display: none; margin-left: 24px; padding: 16px; background: rgba(255,127,0,0.05); border-radius: 8px; border: 1px solid rgba(255,127,0,0.2); margin-top: 12px;">
                            <div class="form-group">
                                <label for="selectedTransferBank" style="font-weight: 600; color: var(--theme-accent);">Selecciona tu banco:</label>
                                <select id="selectedTransferBank" name="selectedTransferBank" class="form-input" onchange="handleTransferBankSelection()">
                                    <option value="">Cargando bancos...</option>
                                </select>
                            </div>
                            <div id="transferBankDetails" style="display: none; padding: 12px; background: rgba(255,255,255,0.05); border-radius: 6px; margin-top: 12px;">
                                <div style="font-weight: 700; color: #fff; margin-bottom: 8px;" id="transferBankNameDisplay"></div>
                                <div style="font-size: 0.9rem; color: #aaa; line-height: 1.6;">
                                    <div><strong>CLABE:</strong> <span id="transferBankClabeDisplay" style="font-family: monospace; font-size: 1rem; color: var(--theme-accent);"></span></div>
                                    <div><strong>Titular:</strong> <span id="transferBankHolderDisplay"></span></div>
                                    <div><strong>RFC:</strong> <span id="transferBankRfcDisplay"></span></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="radio" name="paymentMethod" value="cash" onchange="handlePaymentMethodChange()"> 
                                <strong>Pago Contra Entrega / Recojo en Tienda</strong>
                            </label>
                            <small style="color: var(--theme-text-muted);">Efectivo o tarjeta al recibir</small>
                        </div>
                    </div>

                    <!-- Terms & Conditions -->
                    <div class="form-section">
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="termsAccepted" name="termsAccepted" required> 
                                He leído y acepto los <a href="#" target="_blank" style="color: var(--theme-accent);">términos y condiciones</a> y <a href="#" target="_blank" style="color: var(--theme-accent);">política de privacidad</a>
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

    <script src="js/main.js?v=2.6"></script>
    <script src="js/modals.js"></script>
    <script>
        window.csrfToken = '<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8"); ?>';
        const CART_KEY = <?php echo $isOnlineMode ? "'fox_cart'" : "'truper_cart'"; ?>;

        // Load cart and populate summary
        function loadCartSummary() {
            try {
                let cart = JSON.parse(localStorage.getItem(CART_KEY) || '[]');
                if (!Array.isArray(cart) || cart.length === 0) {
                    window.location.href = CART_KEY === 'fox_cart' ? 'cart.php?mode=online' : 'cart.php';
                    return;
                }

                let subtotal = 0;
                let html = '';

                cart.forEach(item => {
                    const price = Number(item.unit_price || item.price || 0);
                    const qty = Number(item.quantity || 1);
                    const itemTotal = price * qty;
                    subtotal += itemTotal;
                    html += `
                        <div class="summary-item">
                            <div class="summary-item-name">${item.name || 'Producto'} <strong>x${qty}</strong></div>
                            <div class="summary-item-price">$${itemTotal.toFixed(2)}</div>
                        </div>
                    `;
                });

                document.getElementById('summaryItems').innerHTML = html;
                updateTotals(subtotal);
            } catch (e) {
                console.error('Error loading cart:', e);
            }
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
        }

        // Event listeners
        document.querySelectorAll('input[name="shippingMethod"]').forEach(input => {
            input.addEventListener('change', () => {
                const subtotal = parseFloat(document.getElementById('summarySubtotal').textContent.replace('$', ''));
                updateTotals(subtotal);
            });
        });

        document.getElementById('checkoutForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const submitBtn = document.getElementById('submitBtn');
            const formMessage = document.getElementById('formMessage');

            // Validate
            if (!document.getElementById('termsAccepted').checked) {
                formMessage.innerHTML = '<div class="error-message">❌ Debes aceptar los términos y condiciones</div>';
                return;
            }

            // Get form data
            const formData = new FormData(document.getElementById('checkoutForm'));
            const data = {
                csrf_token: window.csrfToken,
                firstName: formData.get('firstName'),
                lastName: formData.get('lastName'),
                email: formData.get('email'),
                phone: formData.get('phone'),
                address: formData.get('address'),
                city: formData.get('city'),
                postalCode: formData.get('postalCode'),
                deliveryNotes: formData.get('deliveryNotes'),
                shippingMethod: formData.get('shippingMethod'),
                promoCode: formData.get('promoCode'),
                orderNotes: formData.get('orderNotes'),
                paymentMethod: formData.get('paymentMethod'),
                requireInvoice: document.getElementById('requireInvoice')?.checked || false,
                rfc: formData.get('rfc') || '',
                taxName: formData.get('taxName') || '',
                taxRegime: formData.get('taxRegime') || '',
                zipCodeFiscal: formData.get('zipCodeFiscal') || '',
                cfdiUse: formData.get('cfdiUse') || 'G03',
                cartItems: JSON.parse(localStorage.getItem(CART_KEY) || '[]')
            };

            // Submit
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Procesando...';

            try {
                const response = await fetch('/api/checkout.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    // Clear cart and redirect
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

        // Load cart on page load
        document.addEventListener('DOMContentLoaded', loadCartSummary);
        
        // ===== FUNCIONES PARA SELECCIÓN DE BANCOS =====
        let mexicanBanks = [];
        
        // Cargar bancos mexicanos al iniciar
        function loadMexicanBanks() {
            fetch('/api/admin_payment_config.php?action=get_banks')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mexicanBanks = data.banks;
                        populateBankSelects();
                    }
                })
                .catch(err => console.error('Error cargando bancos:', err));
        }
        
        // Poblar selects de bancos
        function populateBankSelects() {
            const speiSelect = document.getElementById('selectedBank');
            const transferSelect = document.getElementById('selectedTransferBank');
            
            if (speiSelect && mexicanBanks.length > 0) {
                const speiBanks = mexicanBanks.filter(b => b.supports_spei);
                speiSelect.innerHTML = '<option value="">Selecciona un banco...</option>' + 
                    speiBanks.map(b => `<option value="${b.id}">${b.bank_name}</option>`).join('');
            }
            
            if (transferSelect && mexicanBanks.length > 0) {
                const transferBanks = mexicanBanks.filter(b => b.supports_transfer);
                transferSelect.innerHTML = '<option value="">Selecciona un banco...</option>' + 
                    transferBanks.map(b => `<option value="${b.id}">${b.bank_name}</option>`).join('');
            }
        }
        
        // Manejar cambio de método de pago
        function handlePaymentMethodChange() {
            const method = document.querySelector('input[name="paymentMethod"]:checked')?.value;
            
            // Ocultar todas las secciones de bancos
            document.getElementById('bankSelectionSection').style.display = 'none';
            document.getElementById('transferBankSection').style.display = 'none';
            
            // Mostrar sección correspondiente
            if (method === 'spei') {
                document.getElementById('bankSelectionSection').style.display = 'block';
            } else if (method === 'transfer') {
                document.getElementById('transferBankSection').style.display = 'block';
            }
        }
        
        // Manejar selección de banco SPEI
        function handleBankSelection() {
            const bankId = document.getElementById('selectedBank').value;
            const bankDetails = document.getElementById('bankDetails');
            
            if (!bankId) {
                bankDetails.style.display = 'none';
                return;
            }
            
            const bank = mexicanBanks.find(b => b.id == bankId);
            if (bank) {
                document.getElementById('bankNameDisplay').textContent = bank.bank_name;
                document.getElementById('bankClabeDisplay').textContent = bank.clabe;
                document.getElementById('bankHolderDisplay').textContent = bank.account_holder;
                document.getElementById('bankRfcDisplay').textContent = bank.rfc || 'N/A';
                bankDetails.style.display = 'block';
            }
        }
        
        // Manejar selección de banco para transferencia
        function handleTransferBankSelection() {
            const bankId = document.getElementById('selectedTransferBank').value;
            const bankDetails = document.getElementById('transferBankDetails');
            
            if (!bankId) {
                bankDetails.style.display = 'none';
                return;
            }
            
            const bank = mexicanBanks.find(b => b.id == bankId);
            if (bank) {
                document.getElementById('transferBankNameDisplay').textContent = bank.bank_name;
                document.getElementById('transferBankClabeDisplay').textContent = bank.clabe;
                document.getElementById('transferBankHolderDisplay').textContent = bank.account_holder;
                document.getElementById('transferBankRfcDisplay').textContent = bank.rfc || 'N/A';
                bankDetails.style.display = 'block';
            }
        }
        
        // Cargar bancos al iniciar
        loadMexicanBanks();
        
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
                    const response = await fetch(`https://api.copomex.com/query/info_cp_cp?cp=${cp}&token=pruebas`);
                    const data = await response.json();
                    
                    if (data && data.response) {
                        const info = data.response;
                        if (info.municipio) {
                            cityInput.value = info.municipio;
                        }
                        if (info.estado) {
                            stateInput.value = info.estado;
                        }
                        cpInfo.textContent = `✅ ${info.municipio}, ${info.estado}`;
                        cpInfo.style.color = '#22c55e';
                    } else {
                        cpInfo.textContent = '⚠️ Código postal no encontrado';
                        cpInfo.style.color = '#ff9f43';
                    }
                } catch (error) {
                    // Fallback: usar datos locales si la API falla
                    cpInfo.textContent = 'Código postal válido (verifica ciudad y estado)';
                    cpInfo.style.color = '#888';
                }
            }
        });
        
        // Geolocalización GPS precisa y autocompletado de dirección
        async function getCurrentLocation() {
            const btn = document.getElementById('btnGetCurrentLocation');
            const geoIcon = document.getElementById('geoIcon');
            const geoText = document.getElementById('geoText');

            if (!navigator.geolocation) {
                if (window.showToast) window.showToast('error', 'Geolocalización no soportada', 'Tu navegador no soporta detección de ubicación.');
                else alert('Tu navegador no soporta detección de ubicación.');
                return;
            }

            // Estado de carga
            if (geoIcon) geoIcon.textContent = '⏳';
            if (geoText) geoText.textContent = 'Detectando GPS...';
            if (btn) btn.disabled = true;

            if (window.showToast) window.showToast('info', 'Obteniendo GPS', 'Detectando coordenadas precisas de tu dispositivo...', 2500);

            navigator.geolocation.getCurrentPosition(
                async function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;

                    try {
                        let addressObj = null;

                        // Intento 1: BigDataCloud Reverse Geocoding (Rápido, libre de CORS y sin límites restrictivos)
                        try {
                            const bdcRes = await fetch(`https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${lat}&longitude=${lng}&localityLanguage=es`);
                            if (bdcRes.ok) {
                                const bdcData = await bdcRes.json();
                                if (bdcData) {
                                    const road = bdcData.locality || bdcData.city || '';
                                    const city = bdcData.city || bdcData.locality || bdcData.principalSubdivision || '';
                                    const state = bdcData.principalSubdivision || '';
                                    const postcode = bdcData.postcode || '';

                                    addressObj = {
                                        road: bdcData.localityInfo?.administrative?.[3]?.name || road,
                                        city: city,
                                        state: state,
                                        postcode: postcode
                                    };
                                }
                            }
                        } catch (e) {
                            console.warn('BigDataCloud fallback to Nominatim:', e);
                        }

                        // Intento 2: Nominatim OpenStreetMap (si Intento 1 no trajo calle completa)
                        if (!addressObj || !addressObj.road) {
                            const osmRes = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`);
                            if (osmRes.ok) {
                                const osmData = await osmRes.json();
                                if (osmData && osmData.address) {
                                    const a = osmData.address;
                                    const road = [a.road || a.pedestrian || a.suburb, a.house_number].filter(Boolean).join(' ');
                                    addressObj = {
                                        road: road || a.neighbourhood || a.suburb || addressObj?.road || '',
                                        city: a.city || a.town || a.municipality || a.county || addressObj?.city || '',
                                        state: a.state || addressObj?.state || '',
                                        postcode: a.postcode || addressObj?.postcode || ''
                                    };
                                }
                            }
                        }

                        if (addressObj) {
                            const addressInput = document.getElementById('address');
                            if (addressObj.road && addressInput) {
                                addressInput.value = addressObj.road;
                            }
                            if (addressObj.city && cityInput) {
                                cityInput.value = addressObj.city;
                            }
                            if (addressObj.state && stateInput) {
                                stateInput.value = addressObj.state;
                            }
                            if (addressObj.postcode && postalCodeInput) {
                                postalCodeInput.value = addressObj.postcode.substring(0, 5);
                                postalCodeInput.dispatchEvent(new Event('input', { bubbles: true }));
                            }

                            if (window.showToast) {
                                window.showToast('success', '📍 Ubicación Detectada', `Dirección completada: ${addressObj.city || 'Ubicación actual'}, C.P. ${addressObj.postcode || ''}`);
                            }
                        } else {
                            throw new Error('No se pudo interpretar la dirección');
                        }
                    } catch (err) {
                        console.error('Geocoding error:', err);
                        if (window.showToast) {
                            window.showToast('warning', 'Ubicación Parcial', `Coordenadas: Lat ${lat.toFixed(4)}, Lon ${lng.toFixed(4)}. Por favor confirma tu calle y número.`);
                        }
                    } finally {
                        if (geoIcon) geoIcon.textContent = '📍';
                        if (geoText) geoText.textContent = 'Usar mi ubicación actual';
                        if (btn) btn.disabled = false;
                    }
                },
                function(error) {
                    console.error('Geolocation error:', error);
                    let msg = 'No se pudo obtener tu ubicación.';
                    if (error.code === error.PERMISSION_DENIED) {
                        msg = 'Permiso de ubicación denegado. Permite el acceso en tu navegador.';
                    } else if (error.code === error.POSITION_UNAVAILABLE) {
                        msg = 'Señal GPS no disponible.';
                    } else if (error.code === error.TIMEOUT) {
                        msg = 'Tiempo de espera agotado al buscar GPS.';
                    }

                    if (window.showToast) window.showToast('error', 'Error de Ubicación', msg);
                    else alert(msg);

                    if (geoIcon) geoIcon.textContent = '📍';
                    if (geoText) geoText.textContent = 'Usar mi ubicación actual';
                    if (btn) btn.disabled = false;
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 }
            );
        }
    </script>
    <script src="js/toast-notifications.js"></script>
    <script src="js/mobile-optimize.js"></script>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').catch(err => console.log('SW reg error:', err));
            });
        }
    </script>
</body>
</html>
