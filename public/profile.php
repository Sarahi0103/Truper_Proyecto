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

if (db_column_exists('users', 'birthdate')) {
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/theme.css">
    <style>
        .loyalty-wrap {
            text-align: center;
            padding: 2rem;
        }

        .loyalty-star {
            font-size: 2rem;
            color: var(--theme-accent);
            margin-bottom: 0.5rem;
        }

        .loyalty-points-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--theme-accent);
        }

        .loyalty-points-label {
            color: var(--theme-text-muted);
            margin-bottom: 2rem;
        }

        .loyalty-discount-box {
            margin-bottom: 1.5rem;
            padding: 1rem;
            border-radius: 8px;
            background: var(--theme-accent-soft);
            border: 1px solid rgba(255, 138, 31, 0.4);
            color: var(--theme-text);
        }

        .loyalty-discount-title {
            font-weight: 700;
            margin-bottom: 0.35rem;
        }

        .loyalty-hint {
            color: var(--theme-text-muted);
            margin-top: 0.5rem;
            font-size: 0.9rem;
        }

        .loyalty-rules {
            background: var(--theme-surface-strong);
            border: 1px solid var(--theme-border);
            padding: 1.5rem;
            border-radius: 8px;
            text-align: left;
            color: var(--theme-text);
        }

        .loyalty-rules h4 {
            margin-bottom: 1rem;
            color: var(--theme-text);
        }

        .loyalty-rules ul {
            line-height: 2;
            margin: 0;
            padding-left: 1.2rem;
        }

        .loyalty-birthday {
            margin-top: 2rem;
            color: var(--theme-text);
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    <header>
        <div class="header-content">
            <a href="dashboard.php" class="logo"><img src="images/truper-logo.svg" alt="Truper"></a>
            <nav class="nav-menu">
                <a href="dashboard.php">Dashboard</a>
                <a href="orders.php">Pedidos</a>
                <a href="cart.php">Carrito</a>
                <a href="profile.php" class="active">Perfil</a>
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
                <button class="tab-button" data-tab="loyaltyInfo">Puntos de Lealtad</button>
                <?php if (!$is_client): ?>
                <button class="tab-button" data-tab="passwordChange">Cambiar Contraseña</button>
                <?php endif; ?>
            </div>

            <!-- INFORMACIÓN PERSONAL -->
            <div id="profileInfo" class="tab-content active">
                <div class="card">
                    <div class="card-header">Información de Perfil</div>
                    <div class="card-body">
                        <form id="profileForm" action="api/profile.php?action=update" method="POST" data-success-scroll="#profileInfo" data-success-message="Perfil actualizado correctamente">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
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
                                <label>Dirección</label>
                                <textarea name="address"><?php echo htmlspecialchars($profile['address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label>Empresa</label>
                                <input type="text" name="company_name" value="<?php echo htmlspecialchars($company_name, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>

                            <div class="form-group">
                                <label>Fecha de Nacimiento</label>
                                <input type="date" name="birthdate" value="<?php echo htmlspecialchars($profile['birthdate'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </div>

                            <button type="submit" class="btn btn-primary btn-block">Actualizar Perfil</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- PUNTOS DE LEALTAD -->
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

    <script src="js/main.js"></script>
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
</body>
</html>
