<?php
require_once '../config/config.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

// Validar token
if (empty($token)) {
    $error = 'Token inválido o expirado';
} else {
    try {
        $stmt = $pdo->prepare("SELECT * FROM validate_password_reset_token(?)");
        $stmt->execute([$token]);
        $validation = $stmt->fetch();
        
        if (!$validation || !$validation['is_valid']) {
            $error = 'Token inválido o expirado';
        }
    } catch (Exception $e) {
        $error = 'Error al validar token';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    if (empty($password)) {
        $error = 'Por favor ingresa tu nueva contraseña';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $error = 'La contraseña debe incluir letras y números';
    } elseif ($password !== $password_confirm) {
        $error = 'Las contraseñas no coinciden';
    } else {
        try {
            // Obtener user_id del token
            $stmt = $pdo->prepare("SELECT user_id FROM validate_password_reset_token(?)");
            $stmt->execute([$token]);
            $validation = $stmt->fetch();
            
            if ($validation) {
                $user_id = $validation['user_id'];
                
                // Actualizar contraseña
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $updateStmt->execute([$password_hash, $user_id]);
                
                // Marcar token como usado
                $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE token = ?")->execute([$token]);
                
                $success = 'Tu contraseña ha sido restablecida exitosamente. Ahora puedes iniciar sesión.';
            } else {
                $error = 'Error al restablecer contraseña';
            }
        } catch (Exception $e) {
            $error = 'Error al restablecer contraseña';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Restablecer Contraseña - Ferretería FOX</title>
    <meta name="description" content="Restablece tu contraseña de Ferretería FOX">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90' font-family='Georgia, serif' fill='%23ff6600' font-weight='bold'%3EF%3C/text%3E%3C/svg%3E">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        :root {
            --accent: #ff7f00;
            --accent-light: #ff9500;
            --bg-base: #080809;
            --bg-card: #111113;
            --bg-input: #0d0d10;
            --border: #1e1e22;
            --text-primary: #f0f0f5;
            --text-secondary: #9898a8;
            --radius-card: 24px;
            --radius-input: 12px;
        }
        
        body {
            font-family: 'Outfit', system-ui, sans-serif;
            background: radial-gradient(ellipse 70% 50% at 15% 10%, rgba(255,127,0,0.09), transparent),
                        radial-gradient(ellipse 60% 40% at 85% 85%, rgba(255,127,0,0.05), transparent),
                        linear-gradient(160deg, #0c0c0f 0%, #080809 60%, #06060a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            color: var(--text-primary);
        }
        
        .auth-wrapper {
            width: 100%;
            max-width: 480px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-card);
            box-shadow: 0 0 0 1px rgba(255,127,0,0.04), 0 25px 60px rgba(0,0,0,0.7), 0 0 80px rgba(255,127,0,0.03);
            padding: 2.5rem;
            animation: fadeSlideIn 0.5s ease both;
        }
        
        @keyframes fadeSlideIn {
            from { opacity: 0; transform: translateY(18px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo img {
            height: 40px;
            margin-bottom: 1rem;
        }
        
        .logo h1 {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(90deg, #ffffff, #ffb347);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.875rem 1rem;
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: var(--radius-input);
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.2s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(255,127,0,0.1);
        }
        
        .btn {
            width: 100%;
            padding: 0.875rem 1.5rem;
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
            border: none;
            border-radius: var(--radius-input);
            color: white;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255,127,0,0.3);
        }
        
        .btn-secondary {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-secondary);
            margin-top: 1rem;
        }
        
        .btn-secondary:hover {
            border-color: var(--accent);
            color: var(--accent);
            box-shadow: none;
        }
        
        .alert {
            padding: 1rem;
            border-radius: var(--radius-input);
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        
        .alert-error {
            background: rgba(255, 71, 87, 0.1);
            border: 1px solid rgba(255, 71, 87, 0.3);
            color: #ff4757;
        }
        
        .alert-success {
            background: rgba(46, 213, 115, 0.1);
            border: 1px solid rgba(46, 213, 115, 0.3);
            color: #2ed573;
        }
        
        .back-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .back-link a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }
        
        .back-link a:hover {
            color: var(--accent-light);
        }
        
        .password-requirements {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="logo">
            <span class="logo-brand">Ferretería <span class="logo-bold">FOX</span></span>
            <h1>Restablecer Contraseña</h1>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <div class="back-link">
                <a href="/login.php">Ir al Login →</a>
            </div>
        <?php else: ?>
            <?php if (!$error): ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="password">Nueva Contraseña</label>
                        <input type="password" id="password" name="password" required placeholder="••••••••" autocomplete="new-password">
                        <div class="password-requirements">
                            Mínimo 8 caracteres, debe incluir letras y números
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password_confirm">Confirmar Contraseña</label>
                        <input type="password" id="password_confirm" name="password_confirm" required placeholder="••••••••" autocomplete="new-password">
                    </div>
                    
                    <button type="submit" class="btn">Restablecer Contraseña</button>
                </form>
            <?php endif; ?>
            
            <div class="back-link">
                <a href="/login.php">← Volver al Login</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
