<?php
require_once __DIR__ . '/../backend/config/security.php';
$csrfToken = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Truper</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/auth.css">
</head>
<body class="auth-body">
    <div class="auth-container">
        <div class="auth-box">
            <h1 class="auth-title">Truper</h1>
            <h2>Iniciar Sesión</h2>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($_GET['error']); ?></div>
            <?php endif; ?>
            
            <form action="/backend/controllers/auth_controller.php" method="POST" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required placeholder="tu@email.com">
                </div>
                
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required placeholder="••••••••">
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="remember"> Recuérdame
                    </label>
                </div>
                
                <button type="submit" name="action" value="login" class="btn-primary btn-block">Ingresar</button>
            </form>
            
            <div class="auth-footer">
                <p>¿No tienes cuenta? <a href="/views/register.php">Regístrate aquí</a></p>
                <p><a href="#">¿Olvidaste tu contraseña?</a></p>
            </div>
        </div>
    </div>
</body>
</html>


