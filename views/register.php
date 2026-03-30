<?php
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Truper</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/auth.css">
</head>
<body class="auth-body">
    <div class="auth-container">
        <div class="auth-box">
            <h1 class="auth-title">Truper</h1>
            <h2>Crear Cuenta</h2>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($_GET['error']); ?></div>
            <?php endif; ?>
            
            <form action="/backend/controllers/auth_controller.php" method="POST" class="auth-form">
                <div class="form-group">
                    <label for="name">Nombre Completo</label>
                    <input type="text" id="name" name="name" required placeholder="Tu nombre">
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required placeholder="tu@email.com">
                </div>
                
                <div class="form-group">
                    <label for="phone">TelÃ©fono</label>
                    <input type="tel" id="phone" name="phone" required placeholder="+1-234-567-8900">
                </div>
                
                <div class="form-group">
                    <label for="birthday">Fecha de Nacimiento</label>
                    <input type="date" id="birthday" name="birthday" required>
                </div>
                
                <div class="form-group">
                    <label for="password">ContraseÃ±a</label>
                    <input type="password" id="password" name="password" required placeholder="â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢">
                </div>
                
                <div class="form-group">
                    <label for="password_confirm">Confirmar ContraseÃ±a</label>
                    <input type="password" id="password_confirm" name="password_confirm" required placeholder="â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢">
                </div>
                
                <button type="submit" name="action" value="register" class="btn-primary btn-block">Registrarse</button>
            </form>
            
            <div class="auth-footer">
                <p>Â¿Ya tienes cuenta? <a href="/views/login.php">Inicia sesiÃ³n</a></p>
            </div>
        </div>
    </div>
</body>
</html>


