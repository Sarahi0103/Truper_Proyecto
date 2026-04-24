<?php
require_once '../config/config.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
$return_to = $_GET['return_to'] ?? ($_SESSION['post_login_redirect'] ?? '');

if (is_logged_in()) {
    $role = $_SESSION['role'] ?? 'client';
    header('Location: ' . resolve_post_login_redirect((string)$return_to, $role));
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Administrador - Truper Platform</title>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/theme.css">
</head>
<body class="auth-page">
    <div class="login-container">
        <div class="auth-shell">
            <aside class="auth-side">
                <div class="login-logo"><img src="images/truper-logo.svg" alt="Truper" style="height: 46px;"></div>
                <h2>Solo Administradores</h2>
                <p>Acceso restringido al panel administrativo, abastecimiento, caja y analítica interna.</p>
                <ul>
                    <li>Control de inventario y abastecimiento</li>
                    <li>Gestión de caja, tareas y reportes</li>
                    <li>Acceso exclusivo con credenciales autorizadas</li>
                </ul>
            </aside>

            <div class="auth-form-wrap">
                <div class="login-box">
                    <div class="auth-back-row">
                        <a href="index.php" class="auth-back-link">← Volver a productos</a>
                    </div>
                    <div class="login-header">
                        <h1 class="login-title">Iniciar Sesión Administrador</h1>
                        <p class="login-subtitle">Ingresa solo con credenciales de administrador</p>
                    </div>

                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-error">
                            <?php
                            $errors = [
                                'invalid' => 'Email o contraseña incorrectos',
                                'expired' => 'Tu sesión ha expirado',
                                'unauthorized' => 'No tienes acceso a esa página'
                            ];
                            echo $errors[$_GET['error']] ?? 'Error al procesar la solicitud';
                            ?>
                        </div>
                    <?php endif; ?>

                    <form id="loginForm" action="api/auth.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="return_to" value="<?php echo htmlspecialchars((string)$return_to, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="form-group">
                            <label for="email">Email o teléfono</label>
                            <input type="text" id="email" name="email" required placeholder="Agrega tu correo designado" maxlength="255" autocomplete="username">
                        </div>

                        <div class="form-group">
                            <label for="password">Contraseña</label>
                            <input type="password" id="password" name="password" required placeholder="Tu contraseña" minlength="8" autocomplete="current-password">
                        </div>

                        <button type="submit" class="btn btn-primary btn-block">Entrar al panel</button>
                    </form>

                    <div class="form-group">
                        <p class="text-center text-muted" style="font-size: 0.85rem;">
                            Acceso exclusivo para administrador autorizado
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/main.js"></script>
</body>
</html>
