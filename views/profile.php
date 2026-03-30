<!-- TRUPPER - Mi Perfil -->
<?php
require_once __DIR__ . '/../backend/config/security.php';
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/models/User.php';

Security::requireAuth();

$user_model = new User();
$user = $user_model->getById($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - TRUPPER</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/forms.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">TRUPPER</div>
            <ul class="nav-menu">
                <li><a href="/views/dashboard.php">Dashboard</a></li>
                <li><a href="/views/profile.php">Perfil</a></li>
                <li><a href="/backend/controllers/logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="form-container">
            <h1>Mi Perfil</h1>
            
            <form action="/backend/controllers/profile_controller.php" method="POST" class="form">
                <div class="form-group">
                    <label for="name">Nombre Completo</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email (no editable)</label>
                    <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                </div>

                <div class="form-group">
                    <label for="phone">Teléfono</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="birthday">Fecha de Nacimiento</label>
                    <input type="date" id="birthday" name="birthday" value="<?php echo $user['birthday']; ?>" required>
                </div>

                <button type="submit" name="action" value="update_profile" class="btn-primary">Guardar Cambios</button>
            </form>

            <hr style="margin: 2rem 0;">

            <h2>Cambiar Contraseña</h2>
            
            <form action="/backend/controllers/profile_controller.php" method="POST" class="form">
                <div class="form-group">
                    <label for="current_password">Contraseña Actual</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>

                <div class="form-group">
                    <label for="new_password">Nueva Contraseña</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirmar Contraseña</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>

                <button type="submit" name="action" value="change_password" class="btn-primary">Cambiar Contraseña</button>
            </form>
        </div>
    </div>
</body>
</html>
