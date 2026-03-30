<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrarse - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box" style="max-width: 500px;">
            <div class="login-header">
                <div class="login-logo">🏪</div>
                <h1 class="login-title">Crear Cuenta</h1>
                <p class="login-subtitle">Únete a Truper Platform</p>
            </div>

            <form id="registerForm" action="api/auth.php?action=register" method="POST">
                <div class="form-group">
                    <label for="first_name">Nombre</label>
                    <input type="text" id="first_name" name="first_name" required>
                </div>

                <div class="form-group">
                    <label for="last_name">Apellido</label>
                    <input type="text" id="last_name" name="last_name" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="phone">Teléfono</label>
                    <input type="tel" id="phone" name="phone" placeholder="+56 9 XXXX XXXX">
                </div>

                <div class="form-group">
                    <label for="company_name">Empresa (Opcional)</label>
                    <input type="text" id="company_name" name="company_name">
                </div>

                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required minlength="8">
                    <small class="text-muted">Mínimo 8 caracteres</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirmar Contraseña</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>

                <div class="form-group">
                    <input type="checkbox" id="terms" name="terms" required>
                    <label for="terms" style="display: inline; margin-left: 0.5rem;">
                        Acepto los términos y condiciones
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Crear Cuenta</button>
            </form>

            <div class="form-group mt-3">
                <p class="text-center">¿Ya tienes cuenta? 
                    <a href="login.php" style="color: #FF7F00; text-decoration: none; font-weight: bold;">
                        Inicia sesión
                    </a>
                </p>
            </div>
        </div>
    </div>

    <script src="js/main.js"></script>
</body>
</html>
