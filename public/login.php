<?php
require_once '../config/config.php';
if (is_logged_in()) {
    header('Location: /orders.php?tab=newOrder');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="auth-page">
    <div class="login-container">
        <div class="auth-shell">
            <aside class="auth-side">
                <div class="login-logo"><img src="images/truper-logo.svg" alt="Truper" style="height: 46px;"></div>
                <h2>Bienvenido</h2>
                <p>Gestiona pedidos, pagos, tareas y analítica de tu negocio en un solo lugar.</p>
                <ul>
                    <li>Control de pedidos y estatus de pago</li>
                    <li>Módulo de mayoreo y promociones</li>
                    <li>Predicciones de compra por temporada</li>
                </ul>
            </aside>

            <div class="auth-form-wrap">
                <div class="login-box">
                    <div class="login-header">
                        <h1 class="login-title">Iniciar Sesión</h1>
                        <p class="login-subtitle">Accede a tu cuenta de Truper Platform</p>
                    </div>

            <?php if (isset($_GET['registered'])): ?>
                <div class="alert alert-success">
                    Registro exitoso. Ahora inicia sesión.
                </div>
            <?php endif; ?>

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
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" required placeholder="tu@email.com" maxlength="255" autocomplete="email">
                        </div>

                        <div class="form-group">
                            <label for="password">Contraseña</label>
                            <input type="password" id="password" name="password" required placeholder="Tu contraseña" minlength="8" autocomplete="current-password">
                        </div>

                        <button type="submit" class="btn btn-primary btn-block">Iniciar Sesión</button>
                    </form>

                    <div class="form-group mt-3">
                        <p class="text-center">¿No tienes cuenta? 
                            <a href="register.php" style="color: #FF7F00; text-decoration: none; font-weight: bold;">
                                Regístrate aquí
                            </a>
                        </p>
                    </div>

                    <div class="form-group">
                        <p class="text-center text-muted" style="font-size: 0.85rem;">
                            Admin por defecto: admin@truper.com
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/main.js"></script>
</body>
</html>
