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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Acceso Administrador - Truper Platform</title>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <link rel="stylesheet" href="css/styles.css?v=2.2">
    <link rel="stylesheet" href="css/theme.css?v=2.5">
    <link rel="stylesheet" href="css/responsive-complete.css?v=2.2">
    <style>
        /* ===== Admin Login — Premium Redesign ===== */
        body.auth-page {
            background: radial-gradient(circle at 10% 20%, rgba(255, 127, 0, 0.1), transparent 45%),
                        radial-gradient(circle at 90% 80%, rgba(255, 127, 0, 0.05), transparent 45%),
                        linear-gradient(135deg, #0e0e10 0%, #08080a 100%) !important;
            color: #ffffff !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            min-height: 100vh !important;
            padding: 1.5rem !important;
            box-sizing: border-box !important;
            font-family: var(--theme-font, 'Outfit', 'Inter', sans-serif) !important;
        }

        .login-container {
            width: 100% !important;
            display: flex !important;
            justify-content: center !important;
        }

        .auth-shell {
            background: #111111 !important;
            border: 1px solid #222222 !important;
            border-radius: 24px !important;
            box-shadow: 0 15px 45px rgba(0, 0, 0, 0.6) !important;
            display: grid !important;
            grid-template-columns: 1fr 1.2fr !important;
            overflow: hidden !important;
            max-width: 900px !important;
            width: 100% !important;
        }

        .auth-side {
            background: radial-gradient(circle at 0% 0%, rgba(255, 127, 0, 0.12), transparent 70%), #0a0a0c !important;
            border-right: 1px solid #222222 !important;
            padding: 3.5rem 3rem !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: center !important;
        }

        .login-logo {
            margin-bottom: 2rem !important;
        }

        .auth-side h2 {
            font-size: 2rem !important;
            font-weight: 800 !important;
            color: #ffffff !important;
            letter-spacing: -0.02em !important;
            margin: 0 0 1rem 0 !important;
            background: linear-gradient(90deg, #ffffff, #ffb347) !important;
            -webkit-background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
        }

        .auth-side p {
            color: #aaaaaa !important;
            font-size: 0.95rem !important;
            line-height: 1.6 !important;
            margin: 0 0 2rem 0 !important;
        }

        .auth-side ul {
            list-style: none !important;
            padding: 0 !important;
            margin: 0 !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 0.9rem !important;
        }

        .auth-side ul li {
            color: #888888 !important;
            font-size: 0.9rem !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            font-weight: 500 !important;
        }

        .auth-side ul li::before {
            content: "•" !important;
            color: var(--theme-accent, #ff7f00) !important;
            font-weight: bold !important;
            font-size: 1.25rem !important;
        }

        .auth-form-wrap {
            padding: 3.5rem 3rem !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            background: #111111 !important;
        }

        .login-box {
            width: 100% !important;
            max-width: 400px !important;
        }

        .auth-back-row {
            margin-bottom: 1.5rem !important;
        }

        .auth-back-link {
            color: #888888 !important;
            text-decoration: none !important;
            font-weight: 700 !important;
            font-size: 0.9rem !important;
            transition: all 0.2s ease !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.25rem !important;
        }

        .auth-back-link:hover {
            color: var(--theme-accent, #ff7f00) !important;
        }

        .login-header {
            margin-bottom: 2rem !important;
        }

        .login-title {
            font-size: 1.85rem !important;
            font-weight: 800 !important;
            color: #ffffff !important;
            margin: 0 0 0.5rem 0 !important;
            letter-spacing: -0.02em !important;
        }

        .login-subtitle {
            color: #666666 !important;
            font-size: 0.9rem !important;
            margin: 0 !important;
            font-weight: 500 !important;
        }

        .form-group {
            margin-bottom: 1.5rem !important;
        }

        .form-group label {
            display: block !important;
            font-size: 0.8rem !important;
            font-weight: 700 !important;
            color: #888888 !important;
            margin-bottom: 0.5rem !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
        }

        .form-group input {
            width: 100% !important;
            box-sizing: border-box !important;
            padding: 0.85rem 1rem !important;
            background: #0d0d0f !important;
            border: 1px solid #222222 !important;
            border-radius: 10px !important;
            color: #ffffff !important;
            font-size: 0.95rem !important;
            outline: none !important;
            transition: all 0.2s ease !important;
        }

        .form-group input::placeholder {
            color: #555555 !important;
        }

        .form-group input:focus {
            border-color: var(--theme-accent, #ff7f00) !important;
            box-shadow: 0 0 0 3px rgba(255, 127, 0, 0.15) !important;
            background: #111111 !important;
        }

        /* Error alert */
        .alert-error {
            background: rgba(231, 76, 60, 0.1) !important;
            border: 1px solid rgba(231, 76, 60, 0.25) !important;
            color: #e74c3c !important;
            padding: 0.75rem 1rem !important;
            border-radius: 10px !important;
            font-size: 0.88rem !important;
            font-weight: 600 !important;
            margin-bottom: 1.5rem !important;
            text-align: center !important;
        }

        /* Submit Button */
        .btn-primary.btn-block {
            width: 100% !important;
            background: linear-gradient(90deg, #ff6600, #ff9500) !important;
            border: none !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            padding: 0.85rem 1.5rem !important;
            border-radius: 999px !important;
            font-size: 0.95rem !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            box-shadow: 0 4px 12px rgba(255, 102, 0, 0.25) !important;
        }

        .btn-primary.btn-block:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 18px rgba(255, 102, 0, 0.4) !important;
            background: linear-gradient(90deg, #ff7711, #ffa522) !important;
        }

        .text-muted {
            color: #555555 !important;
            margin-top: 1rem !important;
        }

        @media (max-width: 768px) {
            .auth-shell {
                grid-template-columns: 1fr !important;
                border-radius: 16px !important;
            }

            .auth-side {
                border-right: none !important;
                border-bottom: 1px solid #222222 !important;
                padding: 2.5rem 1.5rem !important;
            }

            .auth-form-wrap {
                padding: 2.5rem 1.5rem !important;
            }
        }
    </style>
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

    <script src="js/main.js?v=2.6"></script>
    <script src="js/mobile-optimize.js"></script>
</body>
</html>
