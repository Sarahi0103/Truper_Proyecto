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
    <title>Registrarse - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="register-clean">
    <header>
        <div class="header-content">
            <a href="index.php" class="logo"><img src="images/truper-logo.svg" alt="Truper"></a>
            <nav class="nav-menu">
                <a href="index.php">Productos</a>
                <a href="login.php">Iniciar Sesión</a>
                <a href="register.php" class="active">Registrarse</a>
            </nav>
        </div>
    </header>

    <div class="login-container">
        <div class="register-panel">
            <div class="login-header" style="margin-bottom: 1rem;">
                <div class="login-logo"><img src="images/truper-logo.svg" alt="Truper" style="height: 46px;"></div>
                <h1 class="login-title">Crear Cuenta</h1>
                <p class="login-subtitle">Registro rápido para clientes con beneficios y seguimiento de pedidos.</p>
            </div>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form id="registerForm" action="api/auth.php?action=register" method="POST" class="register-grid">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-group">
                    <label for="first_name">Nombre</label>
                    <input type="text" id="first_name" name="first_name" required maxlength="100" autocomplete="given-name">
                </div>

                <div class="form-group">
                    <label for="last_name">Apellido</label>
                    <input type="text" id="last_name" name="last_name" required maxlength="100" autocomplete="family-name">
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required maxlength="255" autocomplete="email">
                </div>

                <div class="form-group">
                    <label for="phone">Teléfono</label>
                    <input type="tel" id="phone" name="phone" placeholder="+56 9 XXXX XXXX" maxlength="20" autocomplete="tel">
                </div>

                <div class="form-group">
                    <label for="birthdate">Fecha de Nacimiento</label>
                    <input type="date" id="birthdate" name="birthdate" required>
                </div>

                <div class="form-group">
                    <label for="company_name">Empresa (Opcional)</label>
                    <input type="text" id="company_name" name="company_name">
                </div>

                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password" pattern="(?=.*[A-Za-z])(?=.*\d).{8,}" title="Debe incluir al menos 8 caracteres, letras y números">
                    <small class="text-muted">Mínimo 8 caracteres, con letras y números</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirmar Contraseña</label>
                    <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                </div>

                <div class="form-group register-full">
                    <input type="checkbox" id="terms" name="terms" required>
                    <label for="terms" style="display: inline; margin-left: 0.5rem;">
                        Acepto los términos y condiciones
                    </label>
                </div>

                <div class="register-full">
                    <button type="submit" class="btn btn-primary btn-block">Crear Cuenta</button>
                </div>
            </form>

            <div class="form-group mt-3">
                <p class="text-center">¿Ya tienes cuenta? 
                    <a href="login.php" style="color: #FF7F00; text-decoration: none; font-weight: bold;">Inicia sesión</a>
                </p>
            </div>
        </div>
    </div>

    <script src="js/main.js"></script>
</body>
</html>
