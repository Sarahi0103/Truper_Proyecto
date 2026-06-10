<?php
require_once '../config/config.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

$error = '';
$success = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Por favor ingresa tu email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email inválido';
    } else {
        try {
            // Verificar si el email existe
            $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generar token de restablecimiento
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                
                $tokenStmt = $pdo->prepare("SELECT generate_password_reset_token(?, ?, ?)");
                $tokenStmt->execute([$user['id'], $ip_address, $user_agent]);
                $token = $tokenStmt->fetchColumn();
                
                // Generar enlace de restablecimiento
                $reset_link = "https://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $token;
                
                // Enviar email (simulado - en producción usar servicio real)
                $subject = "Restablecer tu contraseña - Truper Platform";
                $message = "Hola " . $user['name'] . ",\n\n";
                $message .= "Hemos recibido una solicitud para restablecer tu contraseña.\n\n";
                $message .= "Haz clic en el siguiente enlace para restablecer tu contraseña:\n";
                $message .= $reset_link . "\n\n";
                $message .= "Este enlace expirará en 1 hora.\n\n";
                $message .= "Si no solicitaste este cambio, ignora este email.\n\n";
                $message .= "Saludos,\nEquipo Truper Platform";
                
                // En producción usar mail() o servicio de email real
                // mail($email, $subject, $message);
                
                $success = 'Se ha enviado un enlace de restablecimiento a tu email. El enlace expirará en 1 hora.';
            } else {
                // Por seguridad, no revelar si el email existe
                $success = 'Se ha enviado un enlace de restablecimiento a tu email si existe una cuenta asociada.';
            }
        } catch (Exception $e) {
            $error = 'Error al procesar la solicitud. Por favor intenta nuevamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Recuperar Contraseña - Truper Platform</title>
    <meta name="description" content="Recupera tu contraseña de Truper Platform">
    <link rel="icon" type="image/png" href="/truper_logo2.png">
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
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="logo">
            <img src="/truper_logo2.png" alt="Truper Logo">
            <h1>Recuperar Contraseña</h1>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if (!$success): ?>
            <form method="POST">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required placeholder="tu@email.com" autocomplete="email">
                </div>
                
                <button type="submit" class="btn">Enviar Enlace de Recuperación</button>
            </form>
        <?php endif; ?>
        
        <div class="back-link">
            <a href="/login.php">← Volver al Login</a>
        </div>
    </div>
</body>
</html>
