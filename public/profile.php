<?php
require_once '../config/config.php';
require_login();

$selectParts = ['email'];

if (db_column_exists('users', 'first_name')) {
    $selectParts[] = 'first_name';
} else {
    $selectParts[] = "'' AS first_name";
}

if (db_column_exists('users', 'last_name')) {
    $selectParts[] = 'last_name';
} else {
    $selectParts[] = "'' AS last_name";
}

if (db_column_exists('users', 'phone')) {
    $selectParts[] = 'phone';
} else {
    $selectParts[] = "'' AS phone";
}

if (db_column_exists('users', 'address')) {
    $selectParts[] = 'address';
} else {
    $selectParts[] = "'' AS address";
}

if (db_column_exists('users', 'birthdate') && db_column_exists('users', 'birthday')) {
    $selectParts[] = 'COALESCE(birthdate, birthday) AS birthdate';
} elseif (db_column_exists('users', 'birthdate')) {
    $selectParts[] = 'birthdate';
} elseif (db_column_exists('users', 'birthday')) {
    $selectParts[] = 'birthday AS birthdate';
} else {
    $selectParts[] = 'NULL AS birthdate';
}

if (db_column_exists('users', 'loyalty_points')) {
    $selectParts[] = 'loyalty_points';
} elseif (db_column_exists('users', 'points')) {
    $selectParts[] = 'points AS loyalty_points';
} else {
    $selectParts[] = '0 AS loyalty_points';
}

require_once __DIR__ . '/../src/utils/SatCatalogs.php';

if (db_column_exists('users', 'customer_segment')) { $selectParts[] = "COALESCE(customer_segment, 'menudeo') AS customer_segment"; } else { $selectParts[] = "'menudeo' AS customer_segment"; }
if (db_column_exists('users', 'rfc')) { $selectParts[] = "COALESCE(rfc, '') AS rfc"; } else { $selectParts[] = "'' AS rfc"; }
if (db_column_exists('users', 'tax_name')) { $selectParts[] = "COALESCE(tax_name, '') AS tax_name"; } else { $selectParts[] = "'' AS tax_name"; }
if (db_column_exists('users', 'tax_regime')) { $selectParts[] = "COALESCE(tax_regime, '') AS tax_regime"; } else { $selectParts[] = "'' AS tax_regime"; }
if (db_column_exists('users', 'zip_code_fiscal')) { $selectParts[] = "COALESCE(zip_code_fiscal, '') AS zip_code_fiscal"; } else { $selectParts[] = "'' AS zip_code_fiscal"; }
if (db_column_exists('users', 'wallet_balance')) { $selectParts[] = "COALESCE(wallet_balance, 0.00) AS wallet_balance"; } else { $selectParts[] = "0.00 AS wallet_balance"; }
if (db_column_exists('users', 'cfdi_use_default')) { $selectParts[] = "COALESCE(cfdi_use_default, 'G03') AS cfdi_use_default"; } else { $selectParts[] = "'G03' AS cfdi_use_default"; }

$stmt = $pdo->prepare("SELECT " . implode(', ', $selectParts) . " FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$profile = $stmt->fetch() ?: [];

$company_name = '';
if (db_table_exists('clients') && db_column_exists('clients', 'company_name')) {
    try {
        $stmtCompany = $pdo->prepare("SELECT COALESCE(company_name, '') AS company_name FROM clients WHERE user_id = ? LIMIT 1");
        $stmtCompany->execute([$_SESSION['user_id']]);
        $company_name = (string)($stmtCompany->fetchColumn() ?? '');
    } catch (Exception $ignored) {
        $company_name = '';
    }
}

$user_name = htmlspecialchars($_SESSION['name'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
$is_client = (($_SESSION['role'] ?? 'client') === 'client');
$is_admin = (($_SESSION['role'] ?? '') === 'admin');
$is_staff = ($is_admin || ($_SESSION['role'] ?? '') === 'employee');
$loyalty_points = (int)($profile['loyalty_points'] ?? 0);
$current_discount_rate = calculateDiscountByPoints($loyalty_points);

$next_goal_points = null;
if ($loyalty_points < 100) {
    $next_goal_points = 100;
} elseif ($loyalty_points < 250) {
    $next_goal_points = 250;
} elseif ($loyalty_points < 500) {
    $next_goal_points = 500;
} elseif ($loyalty_points < 1000) {
    $next_goal_points = 1000;
}

$birthday_text = 'No registrada';
if (!empty($profile['birthdate'])) {
    $birthdate_raw = substr((string)$profile['birthdate'], 0, 10);
    try {
        $birthdate_obj = new DateTime($birthdate_raw);
        $months_es = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
        ];
        $day = (int)$birthdate_obj->format('d');
        $month = (int)$birthdate_obj->format('m');
        $birthday_text = $day . ' de ' . ($months_es[$month] ?? $birthdate_obj->format('m'));
    } catch (Exception $ignored) {
        $birthday_text = htmlspecialchars($birthdate_raw, ENT_QUOTES, 'UTF-8');
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Perfil - Ferretería FOX</title>
    <link rel="stylesheet" href="css/styles.css?v=4.1">
    <link rel="stylesheet" href="css/theme.css?v=4.1">
    <link rel="stylesheet" href="css/responsive-complete.css?v=5.0">
    <style>
        /* ===== Profile Page — Premium Redesign ===== */
        body {
            color: #ffffff !important;
            font-family: var(--theme-font, 'Outfit', 'Inter', sans-serif) !important;
        }

        .container {
            padding: 2.5rem 1.5rem !important;
            max-width: 550px !important;
            margin: 0 auto !important;
        }

        /* Prevent all tab contents from showing, only display active one */
        .tab-content {
            display: none !important;
        }
        .tab-content.active {
            display: block !important;
        }

        /* Profile grid layout for form */
        .profile-grid {
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            gap: 1.25rem !important;
        }

        .profile-grid-full {
            grid-column: span 2 !important;
        }

        @media (max-width: 600px) {
            .profile-grid {
                grid-template-columns: 1fr !important;
                gap: 1rem !important;
            }
            .profile-grid-full {
                grid-column: span 1 !important;
            }
        }


        h1 {
            font-size: 2.25rem !important;
            font-weight: 800 !important;
            color: #ffffff !important;
            background: linear-gradient(90deg, #ffffff, #ffb347) !important;
            -webkit-background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
            letter-spacing: -0.02em !important;
            margin-bottom: 1.5rem !important;
            text-align: center !important;
        }

        /* Segment-controlled tabs */
        .tabs {
            background: #111111 !important;
            border: 1px solid #222222 !important;
            padding: 0.35rem !important;
            border-radius: 999px !important;
            display: flex !important;
            gap: 0.25rem !important;
            margin: 2rem 0 2.5rem 0 !important;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.5) !important;
        }

        .tab-button {
            flex: 1 !important;
            text-align: center !important;
            padding: 0.75rem 1.25rem !important;
            border-radius: 999px !important;
            border: none !important;
            background: transparent !important;
            color: #888888 !important;
            font-weight: 700 !important;
            transition: all 0.25s ease !important;
            font-size: 0.9rem !important;
            cursor: pointer !important;
        }

        .tab-button:hover {
            color: #ffffff !important;
            background: rgba(255, 255, 255, 0.03) !important;
        }

        .tab-button.active {
            background: var(--theme-accent, #ff7f00) !important;
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(255, 127, 0, 0.3) !important;
        }

        /* Modern card layout specifically for profile */
        .card {
            background: #111111 !important;
            border: 1px solid #222222 !important;
            border-radius: 20px !important;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5) !important;
            overflow: hidden !important;
            margin-bottom: 2.5rem !important;
        }

        .card-header {
            background: #141416 !important;
            border-bottom: 1px solid #222222 !important;
            color: #ffffff !important;
            padding: 1.5rem 1.75rem !important;
            font-weight: 800 !important;
            font-size: 1.2rem !important;
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            text-transform: uppercase !important;
            letter-spacing: 0.03em !important;
        }

        .card-header::before {
            content: '' !important;
            display: inline-block !important;
            width: 4px !important;
            height: 18px !important;
            background: var(--theme-accent, #ff7f00) !important;
            border-radius: 2px !important;
        }

        .card-body {
            padding: 2.25rem 2rem !important;
        }

        /* Form styling */
        .form-group {
            margin-bottom: 1.75rem !important;
        }

        .form-group label {
            display: block !important;
            font-size: 0.8rem !important;
            font-weight: 700 !important;
            margin-bottom: 0.5rem !important;
            color: #888888 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
        }

        input[type="text"], 
        input[type="email"], 
        input[type="password"], 
        input[type="tel"], 
        input[type="date"], 
        textarea {
            width: 100% !important;
            background: #0d0d0f !important;
            border: 1px solid #222222 !important;
            color: #ffffff !important;
            border-radius: 10px !important;
            padding: 0.85rem 1rem !important;
            font-size: 0.95rem !important;
            transition: all 0.2s ease !important;
            box-sizing: border-box;
        }

        input[type="text"]:focus, 
        input[type="email"]:focus, 
        input[type="password"]:focus, 
        input[type="tel"]:focus, 
        input[type="date"]:focus, 
        textarea:focus {
            border-color: var(--theme-accent, #ff7f00) !important;
            box-shadow: 0 0 0 3px rgba(255, 127, 0, 0.15) !important;
            background: #111111 !important;
            outline: none !important;
        }

        input:disabled {
            background: #08080a !important;
            border-color: #1a1a1c !important;
            color: #555555 !important;
            cursor: not-allowed !important;
        }

        .text-muted {
            color: #555555 !important;
            font-size: 0.8rem !important;
            margin-top: 0.5rem !important;
            display: block !important;
            font-weight: 500 !important;
        }

        /* Premium update buttons */
        .btn-primary.btn-block {
            width: 100% !important;
            background: linear-gradient(90deg, #ff6600, #ff9500) !important;
            border: none !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            padding: 0.85rem 1.5rem !important;
            border-radius: 999px !important;
            font-size: 1rem !important;
            letter-spacing: 0.02em !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            box-shadow: 0 4px 12px rgba(255, 102, 0, 0.25) !important;
            margin-top: 1.5rem !important;
        }

        .btn-primary.btn-block:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 18px rgba(255, 102, 0, 0.4) !important;
            background: linear-gradient(90deg, #ff7711, #ffa522) !important;
        }

        /* Loyalty (Lealtad) styling */
        .loyalty-wrap {
            text-align: center;
            padding: 1rem 0;
        }

        .loyalty-star {
            font-size: 3.5rem !important;
            animation: starBounce 2s infinite ease-in-out !important;
            color: var(--theme-accent, #ff7f00) !important;
            margin-bottom: 0.75rem !important;
        }

        @keyframes starBounce {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-8px) scale(1.08); }
        }

        .loyalty-points-number {
            font-size: 3rem !important;
            font-weight: 800 !important;
            color: var(--theme-accent, #ff7f00) !important;
            letter-spacing: -0.02em !important;
        }

        .loyalty-points-label {
            color: #888888 !important;
            font-size: 0.95rem !important;
            font-weight: 600 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            margin-bottom: 2rem !important;
        }

        .loyalty-discount-box {
            margin-bottom: 2rem !important;
            padding: 1.25rem 1.5rem !important;
            border-radius: 12px !important;
            background: rgba(255, 127, 0, 0.08) !important;
            border: 1px solid rgba(255, 127, 0, 0.2) !important;
            color: #ffffff !important;
        }

        .loyalty-discount-title {
            font-size: 1.25rem !important;
            font-weight: 800 !important;
            color: var(--theme-accent, #ff7f00) !important;
            margin-bottom: 0.5rem !important;
        }

        .loyalty-hint {
            color: #888888 !important;
            margin-top: 0.75rem !important;
            font-size: 0.85rem !important;
            font-weight: 500 !important;
        }

        .loyalty-rules {
            background: #0d0d0f !important;
            border: 1px solid #222222 !important;
            padding: 1.5rem !important;
            border-radius: 12px !important;
            text-align: left !important;
        }

        .loyalty-rules h4 {
            margin: 0 0 1rem 0 !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.03em !important;
        }

        .loyalty-rules ul {
            line-height: 1.8 !important;
            margin: 0 !important;
            padding-left: 1.2rem !important;
            color: #aaaaaa !important;
            font-size: 0.95rem !important;
        }

        .loyalty-birthday {
            margin-top: 2rem !important;
            color: #888888 !important;
            font-weight: 500 !important;
            font-size: 0.9rem !important;
        }

        .loyalty-birthday strong {
            color: #ffffff !important;
        }

        /* Success/Error Alerts on forms */
        .toast {
            border-radius: 12px !important;
            padding: 1rem 1.25rem !important;
            font-weight: 600 !important;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1.5rem 1rem !important;
            }

            .tabs {
                border-radius: 16px !important;
                flex-direction: column !important;
                padding: 0.5rem !important;
                gap: 0.35rem !important;
            }

            .tab-button {
                border-radius: 10px !important;
                padding: 0.65rem 1rem !important;
            }

            .card-body {
                padding: 1.5rem 1.25rem !important;
            }
        }
    </style>
</head>
<body class="catalog-minimal">
    <!-- HEADER -->
    <header>
        <div class="header-content">
            <a href="index.php" class="logo"><img src="img/logo_fox.png" alt="Ferretería FOX" style="height: 42px; width: auto; object-fit: contain;"></a>
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
                        <a href="profile.php" class="active">Perfil</a>
                    </div>
                </div>
                <?php if ($is_staff): ?>
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
        </div>
        <div class="user-menu">
            <div class="user-info">
                <div class="user-name"><?php echo $user_name; ?></div>
                <div class="user-role"><?php echo ($_SESSION['role'] ?? '') === 'employee' ? 'PERSONAL' : 'ADMIN'; ?></div>
            </div>
            <button class="btn-logout" onclick="logout()">Cerrar Sesión</button>
        </div>
    </header>

    <main>
        <div class="container" style="max-width: 700px;">
            <!-- ── Back Button ── -->
            <div class="back-header" style="margin-bottom: 1.5rem;">
                <button onclick="history.back()" class="btn-back btn-back-dark btn-back-as-button">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Regresar
                </button>
            </div>

            <!-- ── Perfil Info Card ── -->
            <div id="profileInfo" class="tab-content active">
                <div class="card">
                    <div class="card-header">Información de Perfil</div>
                    <div class="card-body">
                        <form id="profileForm" action="api/profile.php?action=update" method="POST" data-success-scroll="#profileInfo" data-success-message="Perfil actualizado correctamente" data-success-reload="true">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="profile-grid">
                                <div class="form-group">
                                    <label>Nombre</label>
                                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($profile['first_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label>Apellido</label>
                                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($profile['last_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" name="email" value="<?php echo htmlspecialchars($profile['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" disabled>
                                    <small class="text-muted">No se puede cambiar el email</small>
                                </div>

                                <div class="form-group">
                                    <label>Código único de cliente</label>
                                    <input type="text" value="<?php echo htmlspecialchars($profile['user_code'] ?? 'No asignado', ENT_QUOTES, 'UTF-8'); ?>" disabled>
                                    <small class="text-muted">Usa este código para identificación rápida</small>
                                </div>

                                <div class="form-group">
                                    <label>Teléfono</label>
                                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($profile['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </div>

                                <div class="form-group profile-grid-full" style="background: rgba(255,127,0,0.06); padding: 1.25rem; border-radius: 12px; border: 1px solid rgba(255,127,0,0.2); margin-bottom: 1rem;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                                        <div>
                                            <span style="font-size:0.78rem; text-transform:uppercase; letter-spacing:.05em; color:var(--accent);">Segmento de Cliente:</span>
                                            <strong style="display:block; font-size:1.15rem; color:#fff; margin-top:2px;">
                                                <?php 
                                                    $segMap = ['menudeo' => '🛍️ Menudeo', 'contratista' => '👷 Contratista Preferencial', 'escuela' => '🏫 Establecimiento / Escuela', 'mayoreo' => '🏬 Mayoreo Ferretero'];
                                                    echo $segMap[$profile['customer_segment'] ?? 'menudeo'] ?? '🛍️ Menudeo';
                                                ?>
                                            </strong>
                                        </div>
                                        <div>
                                            <span style="font-size:0.78rem; text-transform:uppercase; letter-spacing:.05em; color:#4ade80;">Monedero Digital:</span>
                                            <strong style="display:block; font-size:1.25rem; color:#4ade80; margin-top:2px;">$<?php echo number_format((float)($profile['wallet_balance'] ?? 0), 2); ?> MXN</strong>
                                        </div>
                                    </div>
                                    <div style="margin-top: 0.85rem; padding-top: 0.85rem; border-top: 1px dashed rgba(255,255,255,0.1); font-size: 0.85rem; color: #bbb;">
                                        <strong style="color: #fff;">💳 Estado de Cuenta B2B (Pago Completo por Pedido):</strong> Las compras a crédito o por cotización de proyecto se gestionan bajo la modalidad de <em>Pago Completo por Pedido</em> para agilizar tu entrega en obra sin parcialidades fragmentadas.
                                    </div>
                                </div>

                                <div class="form-group profile-grid-full" style="margin-top: 1rem;">
                                    <h3 style="font-size:1.05rem; margin-bottom:0.75rem; color:var(--accent); border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:0.3rem;">
                                        📋 Datos Fiscales (CFDI 4.0 - SAT México)
                                    </h3>
                                </div>

                                <div class="form-group">
                                    <label>RFC (Con Clave)</label>
                                    <input type="text" name="rfc" value="<?php echo htmlspecialchars($profile['rfc'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ej: VECJ880326XXX" maxlength="13" style="text-transform:uppercase;">
                                    <small class="text-muted">Necesario para emisión de Facturas CFDI 4.0</small>
                                </div>

                                <div class="form-group">
                                    <label>Razón Social / Nombre Fiscal</label>
                                    <input type="text" name="tax_name" value="<?php echo htmlspecialchars($profile['tax_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nombre o Razón Social tal como aparece en tu CSF">
                                </div>

                                <div class="form-group">
                                    <label>Régimen Fiscal (SAT)</label>
                                    <select name="tax_regime" style="background:#111; color:#fff; border:1px solid #333; padding:8px; border-radius:8px; width:100%;">
                                        <option value="">Selecciona tu Régimen Fiscal...</option>
                                        <?php 
                                            $regimes = SatCatalogs::getTaxRegimes();
                                            $userRegime = $profile['tax_regime'] ?? '';
                                            foreach ($regimes as $code => $label):
                                                $sel = ($code === $userRegime) ? 'selected' : '';
                                        ?>
                                        <option value="<?php echo $code; ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Código Postal Fiscal (Domicilio Fiscal)</label>
                                    <input type="text" name="zip_code_fiscal" value="<?php echo htmlspecialchars($profile['zip_code_fiscal'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ej: 44100" maxlength="5" pattern="\d{5}">
                                </div>

                                <div class="form-group profile-grid-full">
                                    <label>Uso de CFDI por Defecto</label>
                                    <select name="cfdi_use_default" style="background:#111; color:#fff; border:1px solid #333; padding:8px; border-radius:8px; width:100%;">
                                        <?php 
                                            $uses = SatCatalogs::getCfdiUses();
                                            $userUse = $profile['cfdi_use_default'] ?? 'G03';
                                            foreach ($uses as $code => $label):
                                                $sel = ($code === $userUse) ? 'selected' : '';
                                        ?>
                                        <option value="<?php echo $code; ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block">Actualizar Perfil</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- PUNTOS DE LEALTAD -->
            <?php if (!$is_admin): ?>
            <div id="loyaltyInfo" class="tab-content">
                <div class="card">
                    <div class="card-header">Programa de Lealtad</div>
                    <div class="card-body">
                        <div class="loyalty-wrap">
                            <div class="loyalty-star">⭐</div>
                            <div class="loyalty-points-number"><?php echo $loyalty_points; ?></div>
                            <div class="loyalty-points-label">Puntos Disponibles</div>

                            <div class="loyalty-discount-box">
                                <div class="loyalty-discount-title">Descuento actual: <?php echo (int)round($current_discount_rate * 100); ?>%</div>
                                <?php if ($next_goal_points !== null): ?>
                                <div>Te faltan <?php echo max(0, $next_goal_points - $loyalty_points); ?> puntos para llegar al siguiente nivel.</div>
                                <?php else: ?>
                                <div>Ya tienes el nivel máximo de descuento por puntos.</div>
                                <?php endif; ?>
                                <div class="loyalty-hint">El descuento se aplica automáticamente al confirmar tu pedido.</div>
                            </div>

                            <div class="loyalty-rules">
                                <h4>Cómo canjear tus puntos:</h4>
                                <ul>
                                    <li>💰 100 puntos = 5% descuento</li>
                                    <li>💰 250 puntos = 10% descuento</li>
                                    <li>💰 500 puntos = 15% descuento</li>
                                    <li>💰 1000+ puntos = 20% descuento</li>
                                </ul>
                            </div>

                            <div class="loyalty-birthday">
                                <h4>Próximo Cumpleaños</h4>
                                <p><?php echo htmlspecialchars($birthday_text, ENT_QUOTES, 'UTF-8'); ?> - ¡Recibirás un bono especial! 🎂</p>
                            </div>

                            <button class="btn btn-primary btn-block mt-3" type="button" onclick="goToOrdersWithDiscount()">Usar Descuento en Pedido</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- MIS DIRECCIONES -->
            <?php if (!$is_admin): ?>
            <div id="addresses" class="tab-content">
                <div class="card">
                    <div class="card-header">Mis Direcciones de Entrega</div>
                    <div class="card-body">
                        <div id="addressList">
                            <div style="text-align: center; padding: 2rem; color: #888;">Cargando direcciones...</div>
                        </div>
                        
                        <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.1);">
                            <h4 style="margin-bottom: 0.75rem;">Agregar Nueva Dirección</h4>
                            <form id="addressForm" action="api/addresses.php?action=create" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                
                                <div class="form-group">
                                    <label>Etiqueta (Ej: Casa, Oficina)</label>
                                    <input type="text" name="address_label" placeholder="Casa">
                                </div>
                                
                                <div class="form-group">
                                    <label>Calle y Número *</label>
                                    <input type="text" name="address_line1" required placeholder="Ej: Calle Principal 123">
                                </div>
                                
                                <div class="form-group">
                                    <label>Colonia/Interior (opcional)</label>
                                    <input type="text" name="address_line2" placeholder="Ej: Colonia Centro, Depto 201">
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Ciudad *</label>
                                        <input type="text" name="city" required placeholder="Ej: Guadalajara">
                                    </div>
                                    <div class="form-group">
                                        <label>Estado *</label>
                                        <input type="text" name="state" required placeholder="Ej: Jalisco">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Código Postal *</label>
                                    <input type="text" name="postal_code" required placeholder="Ej: 44100" maxlength="5" pattern="\d{5}">
                                </div>
                                
                                <div class="form-group">
                                    <label>
                                        <input type="checkbox" name="is_default" value="1">
                                        Establecer como dirección predeterminada
                                    </label>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-block">Agregar Dirección</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- DEVOLUCIONES RMA -->
            <?php if (!$is_admin): ?>
            <div id="rmaRequests" class="tab-content">
                <div class="card">
                    <div class="card-header">Solicitudes de Devolución (RMA)</div>
                    <div class="card-body">
                        <div id="rmaList">
                            <div style="text-align: center; padding: 2rem; color: #888;">Cargando solicitudes...</div>
                        </div>
                        
                        <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.1);">
                            <h4 style="margin-bottom: 0.75rem;">Nueva Solicitud de Devolución</h4>
                            <form id="rmaForm" action="api/rma.php?action=create" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                
                                <div class="form-group">
                                    <label>Pedido (Folio)</label>
                                    <select id="rmaOrderSelect" name="order_id" required>
                                        <option value="">Selecciona un pedido...</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Motivo de Devolución</label>
                                    <select name="reason" required>
                                        <option value="">Selecciona el motivo...</option>
                                        <option value="defecto">Producto defectuoso</option>
                                        <option value="incorrecto">Producto incorrecto</option>
                                        <option value="no_cumple">Producto no cumple con especificaciones</option>
                                        <option value="dañado">Producto llegó dañado</option>
                                        <option value="cambio">Cambio de opinión</option>
                                        <option value="otro">Otro motivo</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Descripción Detallada</label>
                                    <textarea name="description" rows="3" required placeholder="Describe el problema con el producto..."></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label>Cantidad a Devolver</label>
                                    <input type="number" name="quantity" min="1" value="1" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-block">Enviar Solicitud de Devolución</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- CAMBIAR CONTRASEÑA -->
            <div id="passwordChange" class="tab-content">
                <div class="card">
                    <div class="card-header">Cambiar Contraseña</div>
                    <div class="card-body">
                        <form id="passwordForm" action="api/profile.php?action=change-password" method="POST" data-success-scroll="#passwordChange" data-success-message="Contraseña actualizada correctamente">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="form-group">
                                <label>Contraseña Actual</label>
                                <input type="password" name="current_password" required>
                            </div>

                            <div class="form-group">
                                <label>Nueva Contraseña</label>
                                <input type="password" name="new_password" required minlength="8">
                                <small class="text-muted">Mínimo 8 caracteres, debe incluir letras y números</small>
                            </div>

                            <div class="form-group">
                                <label>Confirmar Nueva Contraseña</label>
                                <input type="password" name="confirm_password" required>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block">Cambiar Contraseña</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- FOOTER -->
    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h4>Truper</h4>
                <p>Plataforma de Gestión Empresarial</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 Ferretería FOX. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script src="js/main.js?v=3.0"></script>
    <script src="js/modals.js"></script>
    <script>
        function goToOrdersWithDiscount() {
            window.location.href = 'orders.php?tab=newOrder';
        }

        // Cargar solicitudes RMA del cliente
        async function loadRMARequests() {
            try {
                const response = await fetch('api/rma.php?action=list_client');
                const data = await response.json();
                
                const rmaList = document.getElementById('rmaList');
                if (!rmaList) return;
                
                if (data.success && data.requests && data.requests.length > 0) {
                    rmaList.innerHTML = data.requests.map(rma => `
                        <div style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 1rem; margin-bottom: 0.75rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <strong style="color: var(--theme-accent);">RMA #${rma.id}</strong>
                                <span style="font-size: 0.85rem; padding: 2px 8px; border-radius: 4px; background: ${getStatusColor(rma.status)}; color: #fff;">${rma.status}</span>
                            </div>
                            <div style="font-size: 0.9rem; color: #bbb;">
                                <div><strong>Pedido:</strong> ${rma.order_folio || 'N/A'}</div>
                                <div><strong>Motivo:</strong> ${rma.reason}</div>
                                <div><strong>Fecha:</strong> ${new Date(rma.created_at).toLocaleDateString('es-MX')}</div>
                            </div>
                        </div>
                    `).join('');
                } else {
                    rmaList.innerHTML = '<div style="text-align: center; padding: 2rem; color: #888;">No tienes solicitudes de devolución</div>';
                }
            } catch (error) {
                console.error('Error loading RMA requests:', error);
            }
        }

        function getStatusColor(status) {
            const colors = {
                'pending': '#ff9f43',
                'approved': '#22c55e',
                'rejected': '#ef4444',
                'completed': '#3b82f6'
            };
            return colors[status] || '#888';
        }

        // Cargar pedidos disponibles para RMA
        async function loadOrdersForRMA() {
            try {
                const response = await fetch('api/orders.php?action=list_client');
                const data = await response.json();
                
                const select = document.getElementById('rmaOrderSelect');
                if (!select) return;
                
                if (data.success && data.orders) {
                    select.innerHTML = '<option value="">Selecciona un pedido...</option>' + 
                        data.orders.map(order => `
                            <option value="${order.id}">${order.folio || order.order_number} - ${new Date(order.created_at).toLocaleDateString('es-MX')}</option>
                        `).join('');
                }
            } catch (error) {
                console.error('Error loading orders for RMA:', error);
            }
        }

        // Cargar datos cuando se activa el tab de RMA
        document.addEventListener('DOMContentLoaded', function() {
            const rmaTab = document.querySelector('[data-tab="rmaRequests"]');
            if (rmaTab) {
                rmaTab.addEventListener('click', function() {
                    loadRMARequests();
                    loadOrdersForRMA();
                });
            }
            
            // Cargar direcciones cuando se activa el tab
            const addressesTab = document.querySelector('[data-tab="addresses"]');
            if (addressesTab) {
                addressesTab.addEventListener('click', loadAddresses);
            }
        });

        // Cargar direcciones del cliente
        async function loadAddresses() {
            try {
                const response = await fetch('api/addresses.php?action=list');
                const data = await response.json();
                
                const addressList = document.getElementById('addressList');
                if (!addressList) return;
                
                if (data.success && data.addresses && data.addresses.length > 0) {
                    addressList.innerHTML = data.addresses.map(addr => `
                        <div style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 1rem; margin-bottom: 0.75rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <strong style="color: var(--theme-accent);">${addr.address_label || 'Dirección'}</strong>
                                ${addr.is_default ? '<span style="font-size: 0.75rem; padding: 2px 8px; border-radius: 4px; background: #22c55e; color: #fff;">Predeterminada</span>' : ''}
                            </div>
                            <div style="font-size: 0.9rem; color: #bbb;">
                                <div>${addr.address_line1}</div>
                                ${addr.address_line2 ? `<div>${addr.address_line2}</div>` : ''}
                                <div>${addr.city}, ${addr.state} CP ${addr.postal_code}</div>
                            </div>
                            <div style="margin-top: 0.5rem; display: flex; gap: 0.5rem;">
                                ${!addr.is_default ? `<button onclick="setDefaultAddress(${addr.id})" style="padding: 4px 8px; font-size: 0.75rem; background: rgba(255,127,0,0.1); border: 1px solid rgba(255,127,0,0.3); border-radius: 4px; cursor: pointer; color: #ff7f00;">Hacer Predeterminada</button>` : ''}
                                <button onclick="deleteAddress(${addr.id})" style="padding: 4px 8px; font-size: 0.75rem; background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); border-radius: 4px; cursor: pointer; color: #ef4444;">Eliminar</button>
                            </div>
                        </div>
                    `).join('');
                } else {
                    addressList.innerHTML = '<div style="text-align: center; padding: 2rem; color: #888;">No tienes direcciones guardadas</div>';
                }
            } catch (error) {
                console.error('Error loading addresses:', error);
            }
        }

        async function setDefaultAddress(addressId) {
            try {
                const response = await fetch('api/addresses.php?action=set_default', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ address_id: addressId })
                });
                const data = await response.json();
                
                if (data.success) {
                    loadAddresses();
                } else {
                    alert('Error al establecer dirección predeterminada');
                }
            } catch (error) {
                console.error('Error setting default address:', error);
            }
        }

        async function deleteAddress(addressId) {
            if (!confirm('¿Estás seguro de eliminar esta dirección?')) return;
            
            try {
                const response = await fetch('api/addresses.php?action=delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ address_id: addressId })
                });
                const data = await response.json();
                
                if (data.success) {
                    loadAddresses();
                } else {
                    alert('Error al eliminar dirección');
                }
            } catch (error) {
                console.error('Error deleting address:', error);
            }
        }

        function logout() {
            confirmLogout('api/auth.php?action=logout');
        }
    </script>
    <script src="js/mobile-optimize.js?v=3.0"></script>
</body>
</html>
