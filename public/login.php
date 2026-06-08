<?php
require_once '../config/config.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
$return_to = $_GET['return_to'] ?? ($_SESSION['post_login_redirect'] ?? '');
$force_login_screen = isset($_GET['force']);

if (is_logged_in() && !$force_login_screen) {
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
    <title>Iniciar Sesión Cliente - Truper Platform</title>
    <meta name="description" content="Accede a tu cuenta de cliente Truper. Gestiona pedidos, pagos, promociones y más.">
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ===== Reset & Base ===== */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --accent: #ff7f00;
            --accent-light: #ff9500;
            --accent-dim: rgba(255,127,0,0.15);
            --accent-glow: rgba(255,127,0,0.3);
            --bg-base: #080809;
            --bg-card: #111113;
            --bg-side: #0b0b0d;
            --bg-input: #0d0d10;
            --border: #1e1e22;
            --border-hover: #2e2e35;
            --text-primary: #f0f0f5;
            --text-secondary: #9898a8;
            --text-muted: #5a5a68;
            --radius-card: 24px;
            --radius-input: 12px;
            --transition: 0.22s cubic-bezier(0.4,0,0.2,1);
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Outfit', system-ui, sans-serif;
            background:
                radial-gradient(ellipse 70% 50% at 15% 10%, rgba(255,127,0,0.09), transparent),
                radial-gradient(ellipse 60% 40% at 85% 85%, rgba(255,127,0,0.05), transparent),
                linear-gradient(160deg, #0c0c0f 0%, #080809 60%, #06060a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            color: var(--text-primary);
        }

        /* ===== Layout ===== */
        .auth-wrapper {
            width: 100%;
            max-width: 940px;
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-card);
            box-shadow:
                0 0 0 1px rgba(255,127,0,0.04),
                0 25px 60px rgba(0,0,0,0.7),
                0 0 80px rgba(255,127,0,0.03);
            overflow: hidden;
            animation: fadeSlideIn 0.5s ease both;
        }

        @keyframes fadeSlideIn {
            from { opacity: 0; transform: translateY(18px) scale(0.98); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* ===== Left Panel ===== */
        .auth-panel {
            position: relative;
            background:
                radial-gradient(ellipse 90% 60% at 0% 0%, rgba(255,127,0,0.13), transparent 65%),
                radial-gradient(ellipse 60% 50% at 100% 100%, rgba(255,80,0,0.07), transparent 60%),
                var(--bg-side);
            border-right: 1px solid var(--border);
            padding: 3.5rem 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            overflow: hidden;
        }

        .auth-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background: repeating-linear-gradient(
                -45deg,
                transparent,
                transparent 30px,
                rgba(255,127,0,0.012) 30px,
                rgba(255,127,0,0.012) 31px
            );
            pointer-events: none;
        }

        .auth-logo {
            position: relative;
            margin-bottom: 2.5rem;
        }

        .auth-logo img {
            height: 44px;
            width: auto;
            object-fit: contain;
        }

        .auth-logo-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--accent-dim);
            border: 1px solid rgba(255,127,0,0.2);
            border-radius: 30px;
            padding: 0.35rem 0.85rem;
        }

        .auth-logo-badge span {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--accent);
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .auth-logo-badge::before {
            content: '';
            width: 7px;
            height: 7px;
            background: var(--accent);
            border-radius: 50%;
            box-shadow: 0 0 6px var(--accent);
            animation: pulse 2s ease infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(0.85); }
        }

        .auth-heading {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.2;
            margin-bottom: 0.85rem;
            background: linear-gradient(125deg, #ffffff 30%, #ffb060);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .auth-desc {
            color: var(--text-secondary);
            font-size: 0.92rem;
            line-height: 1.65;
            margin-bottom: 2.2rem;
            font-weight: 400;
        }

        .auth-features {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }

        .auth-features li {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--text-secondary);
            font-size: 0.88rem;
            font-weight: 500;
        }

        .auth-features li .feat-icon {
            width: 32px;
            height: 32px;
            background: var(--accent-dim);
            border: 1px solid rgba(255,127,0,0.18);
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            flex-shrink: 0;
        }

        /* ===== Right Panel (Form) ===== */
        .auth-form-panel {
            padding: 3.5rem 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-card);
        }

        .auth-form-inner {
            width: 100%;
            max-width: 380px;
        }

        .auth-back {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            color: var(--text-muted);
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            margin-bottom: 2rem;
            transition: color var(--transition);
        }

        .auth-back:hover { color: var(--accent); }

        .auth-back svg { width: 14px; height: 14px; }

        .form-title {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--text-primary);
            letter-spacing: -0.025em;
            margin-bottom: 0.4rem;
        }

        .form-subtitle {
            color: var(--text-muted);
            font-size: 0.88rem;
            font-weight: 400;
            margin-bottom: 2rem;
            line-height: 1.5;
        }

        /* ===== Alerts ===== */
        .auth-alert {
            padding: 0.8rem 1rem;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .auth-alert.error {
            background: rgba(231,76,60,0.1);
            border: 1px solid rgba(231,76,60,0.25);
            color: #e06c60;
        }

        .auth-alert.success {
            background: rgba(39,174,96,0.1);
            border: 1px solid rgba(39,174,96,0.25);
            color: #5abf80;
        }

        .auth-alert.info {
            background: var(--accent-dim);
            border: 1px solid rgba(255,127,0,0.2);
            color: var(--accent-light);
        }

        .auth-alert svg { width: 16px; height: 16px; flex-shrink: 0; }

        /* ===== Form Fields ===== */
        .field-group {
            margin-bottom: 1.25rem;
        }

        .field-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.07em;
            margin-bottom: 0.5rem;
        }

        .field-wrap {
            position: relative;
        }

        .field-icon {
            position: absolute;
            left: 0.95rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
            transition: color var(--transition);
            display: flex;
        }

        .field-icon svg { width: 16px; height: 16px; }

        .field-input {
            width: 100%;
            padding: 0.88rem 1rem 0.88rem 2.7rem;
            background: var(--bg-input);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-input);
            color: var(--text-primary);
            font-size: 0.93rem;
            font-family: inherit;
            outline: none;
            transition: all var(--transition);
            -webkit-appearance: none;
        }

        .field-input::placeholder { color: var(--text-muted); }

        .field-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3.5px var(--accent-dim);
            background: #10101380;
        }

        .field-wrap:focus-within .field-icon { color: var(--accent); }

        /* Date input color fix */
        .field-input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(0.5);
            opacity: 0.5;
            cursor: pointer;
        }

        .field-input[type="date"]::-webkit-calendar-picker-indicator:hover {
            filter: invert(0.8) sepia(1) saturate(4) hue-rotate(10deg);
            opacity: 1;
        }

        /* ===== Submit Button ===== */
        .btn-submit {
            width: 100%;
            padding: 0.9rem 1.5rem;
            background: linear-gradient(90deg, #e06500, #ff9200);
            border: none;
            border-radius: 100px;
            color: #ffffff;
            font-size: 0.95rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: all var(--transition);
            box-shadow: 0 4px 16px rgba(255,127,0,0.25);
            margin-top: 0.5rem;
            letter-spacing: 0.01em;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            position: relative;
            overflow: hidden;
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.08), transparent);
            transform: translateX(-100%);
            transition: transform 0.55s ease;
        }

        .btn-submit:hover::before { transform: translateX(100%); }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(255,127,0,0.4);
            background: linear-gradient(90deg, #f07200, #ffa020);
        }

        .btn-submit:active { transform: translateY(0); }

        /* ===== Footer links ===== */
        .form-footer {
            margin-top: 1.6rem;
            text-align: center;
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
        }

        .form-footer p {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .form-footer a {
            color: var(--accent);
            font-weight: 700;
            text-decoration: none;
            transition: color var(--transition);
        }

        .form-footer a:hover { color: var(--accent-light); }

        .divider {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 1.25rem 0 0;
        }

        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .divider span {
            font-size: 0.75rem;
            color: var(--text-muted);
            white-space: nowrap;
        }

        .btn-admin-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.65rem 1rem;
            background: transparent;
            border: 1.5px solid var(--border);
            border-radius: 100px;
            color: var(--text-secondary);
            font-size: 0.82rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            transition: all var(--transition);
        }

        .btn-admin-link:hover {
            border-color: var(--border-hover);
            color: var(--text-primary);
            background: rgba(255,255,255,0.03);
        }

        /* ===== Responsive ===== */
        @media (max-width: 780px) {
            body { padding: 1rem; align-items: flex-start; }

            .auth-wrapper {
                grid-template-columns: 1fr;
                border-radius: 18px;
                margin: 0.5rem 0;
            }

            .auth-panel {
                border-right: none;
                border-bottom: 1px solid var(--border);
                padding: 2.2rem 1.75rem 2rem;
            }

            .auth-heading { font-size: 1.6rem; }
            .auth-desc { margin-bottom: 1.5rem; }

            .auth-form-panel { padding: 2.2rem 1.75rem 2.5rem; }
        }

        @media (max-width: 480px) {
            body { padding: 0; align-items: stretch; }

            .auth-wrapper {
                border-radius: 0;
                min-height: 100vh;
                border: none;
                border-left: none;
                border-right: none;
            }

            .auth-panel { padding: 2rem 1.25rem 1.75rem; }
            .auth-form-panel { padding: 2rem 1.25rem 2.5rem; }
            .form-title { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <div class="auth-wrapper">

        <!-- Left Panel -->
        <aside class="auth-panel">
            <div class="auth-logo">
                <div class="auth-logo-badge">
                    <span>Truper</span>
                </div>
            </div>

            <h2 class="auth-heading">Bienvenido de<br>vuelta</h2>
            <p class="auth-desc">Gestiona pedidos, pagos, tareas y analítica de tu negocio en un solo lugar.</p>

            <ul class="auth-features">
                <li>
                    <span class="feat-icon">📦</span>
                    Control de pedidos y estatus de pago
                </li>
                <li>
                    <span class="feat-icon">🏷️</span>
                    Módulo de mayoreo y promociones
                </li>
                <li>
                    <span class="feat-icon">📊</span>
                    Predicciones de compra por temporada
                </li>
                <li>
                    <span class="feat-icon">🎂</span>
                    Beneficios exclusivos de cumpleaños
                </li>
            </ul>
        </aside>

        <!-- Right Panel -->
        <div class="auth-form-panel">
            <div class="auth-form-inner">

                <a href="index.php" class="auth-back" onclick="if(window.history.length>1){window.history.back();return false;}">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 16L6 10l6-6"/>
                    </svg>
                    Volver a la tienda
                </a>

                <h1 class="form-title">Iniciar sesión</h1>
                <p class="form-subtitle">Accede con tu código único y fecha de nacimiento</p>

                <?php if (isset($_GET['registered'])): ?>
                <div class="auth-alert success">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                    ¡Registro exitoso! Inicia sesión con tu código y fecha de nacimiento.
                </div>
                <?php endif; ?>

                <?php if (!empty($_GET['code'])): ?>
                <div class="auth-alert info">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd"/></svg>
                    Tu código de cliente es: <strong><?php echo htmlspecialchars($_GET['code'], ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                <div class="auth-alert error">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                    <?php
                    $errors = [
                        'invalid'      => 'Código o fecha de nacimiento incorrectos',
                        'expired'      => 'Tu sesión ha expirado. Vuelve a ingresar.',
                        'unauthorized' => 'No tienes acceso a esa página'
                    ];
                    echo $errors[$_GET['error']] ?? 'Error al procesar la solicitud';
                    ?>
                </div>
                <?php endif; ?>

                <form id="loginForm" action="api/auth.php?action=client-login" method="POST" autocomplete="on">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="return_to" value="<?php echo htmlspecialchars((string)$return_to, ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="field-group">
                        <label class="field-label" for="code">Código de cliente</label>
                        <div class="field-wrap">
                            <span class="field-icon">
                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="8" width="14" height="11" rx="2"/><path d="M7 8V6a3 3 0 016 0v2"/>
                                </svg>
                            </span>
                            <input
                                class="field-input"
                                type="text"
                                id="code"
                                name="code"
                                required
                                placeholder="CLI-XXXXXXXX"
                                maxlength="32"
                                autocomplete="username"
                                inputmode="text"
                            >
                        </div>
                    </div>

                    <div class="field-group">
                        <label class="field-label" for="birthdate">Fecha de nacimiento</label>
                        <div class="field-wrap">
                            <span class="field-icon">
                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="14" height="14" rx="2"/><path d="M7 2v4M13 2v4M3 9h14"/>
                                </svg>
                            </span>
                            <input
                                class="field-input"
                                type="date"
                                id="birthdate"
                                name="birthdate"
                                required
                                autocomplete="bday"
                            >
                        </div>
                    </div>

                    <button type="submit" class="btn-submit" id="submitBtn">
                        <svg width="17" height="17" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 10h14M13 5l5 5-5 5"/>
                        </svg>
                        Ingresar
                    </button>
                </form>

                <div class="form-footer">
                    <p>¿No tienes cuenta? <a href="register.php">Regístrate aquí</a></p>

                    <div class="divider"><span>o accede como</span></div>

                    <a href="admin_login.php" class="btn-admin-link">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="10" cy="6" r="3"/><path d="M2 18c0-4 3.6-7 8-7s8 3 8 7"/>
                        </svg>
                        Iniciar sesión como Administrador
                    </a>
                </div>

            </div>
        </div>
    </div>

    <script>
        // Prevent double-submit and add loading state
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<svg width="17" height="17" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="10" cy="10" r="7" stroke-dasharray="44" stroke-dashoffset="44" style="animation:spin 0.8s linear infinite;transform-origin:center;"/></svg> Verificando...';
            btn.style.opacity = '0.8';
        });
    </script>
    <style>
        @keyframes spin { to { stroke-dashoffset: 0; } }
    </style>
</body>
</html>
