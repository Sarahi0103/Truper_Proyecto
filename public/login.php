<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        body {
            background: linear-gradient(135deg, #1A1A1A 0%, #333 100%);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <div class="login-logo">🏪</div>
                <h1 class="login-title">Truper</h1>
                <p class="login-subtitle">Plataforma de Gestión Empresarial</p>
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
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required placeholder="tu@email.com">
                </div>

                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required placeholder="Tu contraseña">
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

    <script src="js/main.js"></script>
</body>
</html>
