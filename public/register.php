<?php
require_once '../config/config.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
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
    <title>Registro de Cliente - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/theme.css">
</head>
<body class="auth-page register-auth-page">
    <div class="login-container">
        <div class="auth-shell register-shell">
            <aside class="auth-side">
                <div class="login-logo"><img src="images/truper-logo.svg" alt="Truper" style="height: 46px;"></div>
                <h2>Registro de Cliente</h2>
                <p>Crea tu cuenta con fecha de nacimiento obligatoria. Tu código único se usará para iniciar sesión.</p>
                <ul>
                    <li>Historial y seguimiento de pedidos</li>
                    <li>Programa de puntos y promociones</li>
                    <li>Beneficios de cumpleaños y mayoreo</li>
                </ul>
            </aside>

            <div class="auth-form-wrap">
                <div class="theme-toggle">
                    <button type="button" data-theme-toggle-btn><span data-theme-toggle-label>Modo claro</span></button>
                </div>
                <div class="login-box register-box">
                    <div class="auth-back-row">
                        <a href="index.php" class="auth-back-link">← Volver a productos</a>
                    </div>
                    <div class="login-header" style="margin-bottom: 1rem;">
                        <h1 class="login-title">Crear Cuenta</h1>
                        <p class="login-subtitle">Registro rápido para clientes. No necesitas contraseña.</p>
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
                            <a href="login.php" style="color: var(--color-naranja); text-decoration: none; font-weight: bold;">Inicia sesión</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/main.js"></script>
</body>
</html>
