<?php
require_once '../config/config.php';
require_admin();

$user_name = htmlspecialchars($_SESSION['name'] ?? 'Administrador', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Tickets - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css?v=2.1">
    <link rel="stylesheet" href="css/theme.css?v=2.1">
    <link rel="stylesheet" href="css/responsive-complete.css">
    <style>
        .tickets-hero {
            background: linear-gradient(135deg, rgba(255, 127, 0, 0.16), rgba(17, 17, 17, 0.04));
            border: 1px solid rgba(255, 127, 0, 0.16);
            border-radius: 24px;
            padding: 1.5rem;
            box-shadow: var(--ui-shadow);
        }

        .tickets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .tickets-card {
            background: var(--ui-surface);
            border: 1px solid var(--ui-border);
            border-radius: 18px;
            padding: 1.25rem;
            box-shadow: var(--ui-shadow);
            min-height: 100%;
        }

        .tickets-card h3 {
            margin-bottom: 0.6rem;
            color: var(--ui-text);
        }

        .tickets-card p {
            color: var(--ui-text-muted);
            line-height: 1.6;
        }

        .tickets-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            background: rgba(255, 127, 0, 0.12);
            color: var(--color-naranja);
            font-weight: 700;
            font-size: 0.8rem;
            margin-bottom: 1rem;
        }

        .tickets-list {
            display: grid;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .ticket-step {
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
            padding: 0.85rem 1rem;
            border-radius: 14px;
            background: var(--ui-surface-soft);
            border: 1px solid var(--ui-border);
        }

        .ticket-step-number {
            width: 2rem;
            height: 2rem;
            border-radius: 999px;
            background: var(--color-naranja);
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            flex: 0 0 auto;
        }

        .ticket-step-text strong {
            display: block;
            margin-bottom: 0.15rem;
        }

        .tickets-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }

        .tickets-kpi {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-top: 1.25rem;
        }

        .tickets-kpi div {
            padding: 1rem;
            border-radius: 16px;
            background: rgba(255, 127, 0, 0.06);
            border: 1px solid rgba(255, 127, 0, 0.10);
        }

        .tickets-kpi span {
            display: block;
            font-size: 0.78rem;
            color: var(--ui-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .tickets-kpi strong {
            display: block;
            margin-top: 0.35rem;
            font-size: 1.15rem;
            color: var(--ui-text);
        }

        .tickets-note {
            margin-top: 1.25rem;
            padding: 1rem 1.1rem;
            border-radius: 16px;
            background: rgba(17, 17, 17, 0.04);
            border: 1px solid var(--ui-border);
            color: var(--ui-text-muted);
            line-height: 1.6;
        }

        @media (max-width: 768px) {
            .tickets-hero {
                padding: 1.1rem;
                border-radius: 18px;
            }

            .tickets-actions {
                flex-direction: column;
            }

            .tickets-actions .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <a href="dashboard.php" class="logo"><img src="images/truper-logo.svg" alt="Truper"></a>
            <nav class="nav-menu">
                <a href="dashboard.php">Dashboard</a>
                <a href="analytics.php">Estadísticas</a>
                <a href="analytics.php#ticketsTab" class="active">Historial de Tickets</a>
                <a href="admin_supply.php">Abastecimiento</a>
            </nav>
        </div>
        <div class="user-menu">
            <div class="user-info">
                <div class="user-name"><?php echo $user_name; ?></div>
                <div class="user-role">Admin</div>
            </div>
            <button class="btn-logout" onclick="logout()">Cerrar Sesión</button>
        </div>
    </header>

    <main>
        <div class="tickets-hero" style="max-width: 860px; margin: 0 auto; text-align: center;">
            <span class="tickets-badge">Historial centralizado</span>
            <h1 style="margin-bottom: 0.6rem;">El historial de tickets ya vive en Estadísticas</h1>
            <p class="text-muted" style="margin: 0 auto; line-height: 1.7; max-width: 720px;">
                Se retiró el apartado histórico de esta pantalla para evitar duplicidad. Ahora todo el seguimiento, filtros, archivo mensual y descarga se consultan desde la pestaña Historial de Tickets dentro de Estadísticas.
            </p>
            <div class="tickets-actions" style="justify-content: center;">
                <a class="btn btn-primary" href="analytics.php#ticketsTab">Abrir historial en Estadísticas</a>
                <a class="btn btn-secondary" href="analytics.php">Ir a Estadísticas</a>
            </div>
            <div class="tickets-note" style="max-width: 760px; margin-left: auto; margin-right: auto;">
                Esta página quedó solo como acceso directo. Si entras aquí por un enlace antiguo, te conviene usar la vista centralizada para revisar tickets, archivar el mes y exportar Excel.
            </div>
        </div>
    </main>

    <script src="js/main.js"></script>
    <script>
        setTimeout(() => {
            window.location.href = 'analytics.php#ticketsTab';
        }, 2500);

        function logout() {
            if (confirm('¿Deseas cerrar sesión?')) {
                window.location.href = 'api/auth.php?action=logout';
            }
        }
    </script>
    <script src="js/mobile-optimize.js"></script>
</body>
</html>
