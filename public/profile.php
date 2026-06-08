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

if (db_column_exists('users', 'user_code')) {
    $selectParts[] = 'user_code';
} else {
    $selectParts[] = "'' AS user_code";
}

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
    <title>Perfil - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css?v=3.0">
    <link rel="stylesheet" href="css/theme.css?v=3.0">
    <link rel="stylesheet" href="css/responsive-complete.css?v=3.0">
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
            <a href="index.php" class="logo"><img src="img/logo_truper.1.1.png" alt="Truper" style="height: 40px; width: auto; object-fit: contain;"></a>
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
            </div>
            <button class="btn-logout" onclick="logout()">Cerrar Sesión</button>
        </div>
    </header>

    <main>
        <div class="container" style="max-width: 600px;">
            <h1>Mi Perfil</h1>

            <div class="tabs">
                <button class="tab-button active" data-tab="profileInfo">Información Personal</button>
                <?php if (!$is_admin): ?>
                <button class="tab-button" data-tab="loyaltyInfo">Puntos de Lealtad</button>
                <?php endif; ?>
                <?php if (!$is_client): ?>
                <button class="tab-button" data-tab="passwordChange">Cambiar Contraseña</button>
                <?php endif; ?>
            </div>

            <!-- INFORMACIÓN PERSONAL -->
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

                                <div class="form-group">
                                    <label>Fecha de Nacimiento</label>
                                    <input type="date" name="birthdate" value="<?php echo htmlspecialchars($profile['birthdate'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </div>

                                <div class="form-group profile-grid-full">
                                    <label>Empresa</label>
                                    <input type="text" name="company_name" value="<?php echo htmlspecialchars($company_name, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>

                                <div class="form-group profile-grid-full">
                                    <label>Dirección</label>
                                    <textarea name="address" rows="3"><?php echo htmlspecialchars($profile['address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
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

            <!-- CAMBIAR CONTRASEÑA -->
            <?php if (!$is_client): ?>
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
                                <small class="text-muted">Mínimo 8 caracteres</small>
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
            <?php endif; ?>
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
            <p>&copy; 2026 Truper Platform. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script src="js/main.js?v=3.0"></script>
    <script>
        function goToOrdersWithDiscount() {
            window.location.href = 'orders.php?tab=newOrder';
        }

        function logout() {
            if (confirm('¿Deseas cerrar sesión?')) {
                window.location.href = 'api/auth.php?action=logout';
            }
        }
    </script>
    <script src="js/mobile-optimize.js?v=3.0"></script>
</body>
</html>
