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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Registro de Cliente - Truper Platform</title>
    <meta name="description" content="Crea tu cuenta de cliente Truper. Accede a pedidos, promociones y beneficios exclusivos.">
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
            max-width: 980px;
            display: grid;
            grid-template-columns: 1fr 1.35fr;
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

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(0.85); }
        }

        .auth-heading {
            font-size: 1.9rem;
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
            font-size: 0.9rem;
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
            padding: 3rem 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-card);
        }

        .auth-form-inner {
            width: 100%;
            max-width: 420px;
        }

        .auth-back {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            color: var(--text-muted);
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            margin-bottom: 1.75rem;
            transition: color var(--transition);
        }

        .auth-back:hover { color: var(--accent); }
        .auth-back svg { width: 14px; height: 14px; }

        .form-title {
            font-size: 1.7rem;
            font-weight: 800;
            color: var(--text-primary);
            letter-spacing: -0.025em;
            margin-bottom: 0.35rem;
        }

        .form-subtitle {
            color: var(--text-muted);
            font-size: 0.87rem;
            font-weight: 400;
            margin-bottom: 1.75rem;
            line-height: 1.5;
        }

        /* ===== Alerts ===== */
        .auth-alert {
            padding: 0.8rem 1rem;
            border-radius: 10px;
            font-size: 0.84rem;
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

        .auth-alert svg { width: 16px; height: 16px; flex-shrink: 0; }

        /* ===== Form Grid ===== */
        .register-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0 1rem;
        }

        .field-full { grid-column: 1 / -1; }

        /* ===== Form Fields ===== */
        .field-group {
            margin-bottom: 1.1rem;
        }

        .field-label {
            display: block;
            font-size: 0.73rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.07em;
            margin-bottom: 0.45rem;
        }

        .field-wrap {
            position: relative;
        }

        .field-icon {
            position: absolute;
            left: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
            transition: color var(--transition);
            display: flex;
        }

        .field-icon svg { width: 15px; height: 15px; }

        .field-input {
            width: 100%;
            padding: 0.82rem 0.9rem 0.82rem 2.55rem;
            background: var(--bg-input);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-input);
            color: var(--text-primary);
            font-size: 0.9rem;
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

        .field-input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(0.5);
            opacity: 0.5;
            cursor: pointer;
        }

        /* ===== Terms checkbox ===== */
        .terms-row {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 1rem;
            grid-column: 1 / -1;
        }

        .terms-row input[type="checkbox"] {
            width: 17px;
            height: 17px;
            accent-color: var(--accent);
            cursor: pointer;
            flex-shrink: 0;
        }

        .terms-row label {
            font-size: 0.82rem;
            color: var(--text-muted);
            cursor: pointer;
            line-height: 1.4;
        }

        /* ===== Submit Button ===== */
        .btn-submit {
            width: 100%;
            padding: 0.88rem 1.5rem;
            background: linear-gradient(90deg, #e06500, #ff9200);
            border: none;
            border-radius: 100px;
            color: #ffffff;
            font-size: 0.94rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: all var(--transition);
            box-shadow: 0 4px 16px rgba(255,127,0,0.25);
            letter-spacing: 0.01em;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            position: relative;
            overflow: hidden;
            grid-column: 1 / -1;
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
            margin-top: 1.4rem;
            text-align: center;
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

        /* ===== Responsive ===== */
        @media (max-width: 820px) {
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

            .auth-form-panel { padding: 2.2rem 1.75rem 2.5rem; }
        }

        @media (max-width: 480px) {
            body { padding: 0; align-items: stretch; }

            .auth-wrapper {
                border-radius: 0;
                min-height: 100vh;
                border: none;
            }

            .auth-panel { padding: 2rem 1.25rem 1.75rem; }
            .auth-form-panel { padding: 2rem 1.25rem 2.5rem; }
            .form-title { font-size: 1.5rem; }
            .register-grid { grid-template-columns: 1fr; }
            .field-full { grid-column: 1; }
            .btn-submit { grid-column: 1; }
            .terms-row { grid-column: 1; }
        }
    </style>
</head>
<body>
    <div class="auth-wrapper">

        <!-- Left Panel -->
        <aside class="auth-panel">
            <div class="auth-logo">
                <img src="img/logo_truper.1.1.png" alt="Truper Logo">
            </div>

            <h2 class="auth-heading">Crea tu cuenta<br>de cliente</h2>
            <p class="auth-desc">Registro rápido y sin contraseña. Tu código único te permitirá acceder a todos tus pedidos y beneficios.</p>

            <ul class="auth-features">
                <li>
                    <span class="feat-icon">📋</span>
                    Historial y seguimiento de pedidos
                </li>
                <li>
                    <span class="feat-icon">⭐</span>
                    Programa de puntos y promociones
                </li>
                <li>
                    <span class="feat-icon">🎂</span>
                    Beneficios de cumpleaños y mayoreo
                </li>
                <li>
                    <span class="feat-icon">🔒</span>
                    Acceso sin contraseña, solo tu código
                </li>
            </ul>
        </aside>

        <!-- Right Panel -->
        <div class="auth-form-panel">
            <div class="auth-form-inner">

                <a href="index.php" class="auth-back">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 16L6 10l6-6"/>
                    </svg>
                    Volver a la tienda
                </a>

                <h1 class="form-title">Crear cuenta</h1>
                <p class="form-subtitle">Registro rápido para clientes. No necesitas contraseña.</p>

                <?php if (isset($_GET['error'])): ?>
                <div class="auth-alert error">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                    <?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <?php endif; ?>

                <form id="registerForm" action="api/auth.php?action=register" method="POST" class="register-grid" autocomplete="on">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="field-group">
                        <label class="field-label" for="first_name">Nombre</label>
                        <div class="field-wrap">
                            <span class="field-icon">
                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="10" cy="6" r="3"/><path d="M2 18c0-4 3.6-7 8-7s8 3 8 7"/>
                                </svg>
                            </span>
                            <input class="field-input" type="text" id="first_name" name="first_name" required maxlength="100" placeholder="Juan" autocomplete="given-name">
                        </div>
                    </div>

                    <div class="field-group">
                        <label class="field-label" for="last_name">Apellido</label>
                        <div class="field-wrap">
                            <span class="field-icon">
                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="10" cy="6" r="3"/><path d="M2 18c0-4 3.6-7 8-7s8 3 8 7"/>
                                </svg>
                            </span>
                            <input class="field-input" type="text" id="last_name" name="last_name" required maxlength="100" placeholder="Pérez" autocomplete="family-name">
                        </div>
                    </div>

                    <div class="field-group field-full">
                        <label class="field-label" for="email">Correo electrónico</label>
                        <div class="field-wrap">
                            <span class="field-icon">
                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="2" y="5" width="16" height="12" rx="2"/><path d="M2 7l8 5 8-5"/>
                                </svg>
                            </span>
                            <input class="field-input" type="email" id="email" name="email" required maxlength="255" placeholder="tu@email.com" autocomplete="email">
                        </div>
                    </div>

                    <div class="field-group">
                        <label class="field-label" for="phone">Teléfono</label>
                        <div class="field-wrap">
                            <span class="field-icon">
                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 4a1 1 0 011-1h3l1 4-2 1.5a10 10 0 004.5 4.5L12 11l4 1v3a1 1 0 01-1 1C7.163 16 3 11.837 3 6.5"/>
                                </svg>
                            </span>
                            <input class="field-input" type="tel" id="phone" name="phone" placeholder="+52 33 XXXX XXXX" maxlength="20" autocomplete="tel">
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
                            <input class="field-input" type="date" id="birthdate" name="birthdate" required autocomplete="bday">
                        </div>
                    </div>

                    <div class="field-group field-full">
                        <label class="field-label" for="company_name">Empresa <span style="font-weight:400;text-transform:none;letter-spacing:0;">(Opcional)</span></label>
                        <div class="field-wrap">
                            <span class="field-icon">
                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 8l7-4 7 4v9a1 1 0 01-1 1H4a1 1 0 01-1-1V8z"/><path d="M8 18V12h4v6"/>
                                </svg>
                            </span>
                            <input class="field-input" type="text" id="company_name" name="company_name" placeholder="Nombre de tu empresa">
                        </div>
                    </div>

                    <div class="terms-row">
                        <input type="checkbox" id="terms" name="terms" required>
                        <label for="terms">Acepto los <a href="#" style="color:var(--accent);text-decoration:none;">términos y condiciones</a> de uso</label>
                    </div>

                    <button type="submit" class="btn-submit" id="submitBtn">
                        <svg width="17" height="17" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 10H4M10 4l6 6-6 6"/>
                        </svg>
                        Crear Cuenta
                    </button>
                </form>

                <div class="form-footer">
                    <p>¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a></p>
                </div>

            </div>
        </div>
    </div>

    <script src="js/main.js?v=2.6"></script>
    <script src="js/mobile-optimize.js"></script>
</body>
</html>
