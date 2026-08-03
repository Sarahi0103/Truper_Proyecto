<?php
/**
 * Panel de Aprobación B2B (Contratistas / Escuelas / Establecimientos)
 * Truper Platform - Fase 1
 */
require_once '../config/config.php';
require_admin();

$user_name = htmlspecialchars($_SESSION['name'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
$user_role = htmlspecialchars($_SESSION['role'] ?? 'admin', ENT_QUOTES, 'UTF-8');

$message = '';
$error = '';

// Process Approval / Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appId = (int)($_POST['app_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $assignedSegment = sanitize($_POST['assigned_segment'] ?? 'contratista');
    $notes = sanitize($_POST['notes'] ?? '');

    if ($appId > 0 && in_array($action, ['approve', 'reject'], true)) {
        try {
            $stmt = $pdo->prepare("SELECT user_id, rfc, tax_name, requested_segment FROM b2b_applications WHERE id = ?");
            $stmt->execute([$appId]);
            $app = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($app) {
                if ($action === 'approve') {
                    // Update application status
                    $updApp = $pdo->prepare("UPDATE b2b_applications SET status = 'approved', notes = ?, created_at = NOW() WHERE id = ?");
                    $updApp->execute([$notes, $appId]);

                    // Update user segment and fiscal data
                    $updUser = $pdo->prepare("UPDATE users SET customer_segment = ?, rfc = ?, tax_name = ?, b2b_approved_at = NOW() WHERE id = ?");
                    $updUser->execute([$assignedSegment, $app['rfc'], $app['tax_name'], $app['user_id']]);

                    $message = "Solicitud B2B #{$appId} APROBADA correctamente. Segmento '{$assignedSegment}' asignado.";
                } else {
                    $updApp = $pdo->prepare("UPDATE b2b_applications SET status = 'rejected', notes = ? WHERE id = ?");
                    $updApp->execute([$notes, $appId]);
                    $message = "Solicitud B2B #{$appId} RECHAZADA.";
                }
            }
        } catch (Exception $e) {
            $error = 'Error al procesar solicitud B2B: ' . $e->getMessage();
        }
    }
}

// Fetch pending and historical B2B applications
$applications = [];
try {
    $sql = "SELECT b.*, u.first_name, u.last_name, u.email, u.phone, u.customer_segment AS current_segment 
            FROM b2b_applications b 
            JOIN users u ON b.user_id = u.id 
            ORDER BY CASE WHEN b.status = 'pending' THEN 0 ELSE 1 END, b.id DESC";
    $applications = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Aprobación de Clientes B2B - Ferretería FOX</title>
    <link rel="stylesheet" href="css/styles.css?v=4.2">
    <link rel="stylesheet" href="css/theme.css?v=4.1">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/responsive-complete.css?v=5.0">
    <style>
        body { font-family: var(--theme-font, 'Outfit', sans-serif); background: #0b0b0e; color: #fff; }
        .b2b-container { max-width: 1320px; margin: 2rem auto; padding: 0 1.25rem; }
        .b2b-card { background: #121217; border: 1px solid #22222a; border-radius: 14px; padding: 1.5rem; margin-bottom: 1.5rem; }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem; }
        th { background: #171720; padding: 1rem; border-bottom: 1px solid #282834; color: #888899; text-transform: uppercase; font-size: 0.75rem; letter-spacing: .06em; font-weight: 700; }
        td { padding: 1rem; border-bottom: 1px solid #1a1a24; vertical-align: middle; }
        .btn-svg { display: inline-flex; align-items: center; gap: 0.4rem; padding: 6px 12px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; text-decoration: none; cursor: pointer; border: none; }
        .btn-green { background: #15803d; color: #fff; }
        .btn-red { background: #b91c1c; color: #fff; }

        /* Dark Header explicitly */
        header {
            background: #111111 !important;
            border-bottom: 1px solid #222222 !important;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4) !important;
        }
        header .nav-menu a, header .nav-dropdown-btn {
            color: #ffffff !important;
        }
        header .logo img {
            filter: none !important;
        }
    </style>
</head>
<body class="catalog-minimal">
    <header>
        <div class="header-content">
            <a href="index.php" class="logo"><img src="img/logo_fox.png" alt="Ferretería FOX" style="height: 42px; width: auto; object-fit: contain;"></a>
            <button class="hamburger-btn" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <nav class="nav-menu">
                <a href="index.php">Catálogo</a>
                <a href="marketplace_ce.php">Marketplace CE</a>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Mi Cuenta <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content">
                        <a href="dashboard.php">Dashboard</a>
                        <a href="orders.php">Pedidos</a>
                        <a href="wholesale.php">Mayoreo</a>
                        <a href="account.php#historyTab">Historial</a>
                        <a href="profile.php">Perfil</a>
                    </div>
                </div>
                <!-- Dropdowns de Administración Separados -->
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Admin Tienda <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content" style="min-width: 200px;">
                        <a href="orders.php">Ventas / Pedidos</a>
                        <a href="order_tracking.php">Seguimiento / Logística</a>
                        <a href="rma_manager.php">Devoluciones RMA</a>
                    </div>
                </div>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Admin Local <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content" style="min-width: 200px;">
                        <a href="cashier.php">Caja / Punto de Venta</a>
                        <a href="b2b_approval.php">Aprobación B2B</a>
                        <a href="tickets.php">Tickets y Cotizaciones</a>
                        <a href="ticket_validation.php">Validación de Tickets</a>
                        <a href="tasks.php">Tareas de Empleados</a>
                    </div>
                </div>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Solo Admin <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content" style="min-width: 220px;">
                        <a href="admin_supply.php?nocache=true">Abastecimiento / Precios</a>
                        <a href="accounting_reports.php">Reportes Contables</a>
                        <a href="gastos.php">Egresos / Gastos</a>
                        <a href="analytics.php">Estadísticas</a>
                    </div>
                </div>
                <?php endif; ?>
                </nav>
        </div>
        <div class="user-menu">
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Usuario', ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="user-role"><?php echo ($_SESSION['role'] ?? 'admin') === 'employee' ? 'PERSONAL' : 'ADMIN'; ?></div>
            </div>
            <button class="btn-logout" onclick="logout()">Cerrar Sesión</button>
        </div>
    </header>

    <main class="b2b-container">
        <!-- ── Back Button ── -->
        <div class="back-header">
            <button onclick="history.back()" class="btn-back btn-back-dark btn-back-as-button">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Regresar
            </button>
        </div>

        <div class="page-hero">
            <div class="module-badge module-admin"><span class="module-glyph">B2</span> Aprobación B2B</div>
            <h1>Panel de Aprobación de Clientes B2B</h1>
            <p class="text-muted">Revisión de Constancias de Situación Fiscal (CSF), RFC y asignación de tarifas preferenciales (Contratista / Escuela / Mayoreo).</p>
        </div>

        <?php if ($message): ?>
            <div style="background: rgba(34,197,94,0.15); border: 1px solid #22c55e; color: #4ade80; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 600;">
                ✅ <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background: rgba(239,68,68,0.15); border: 1px solid #ef4444; color: #f87171; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 600;">
                ❌ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="b2b-card">
            <h3 style="margin-top: 0; font-size: 1.1rem; color: var(--theme-accent, #ff7f00);">Solicitudes de Acceso Preferencial B2B</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>RFC & Razón Social</th>
                            <th>Segmento Solicitado</th>
                            <th>Documento CSF</th>
                            <th>Estatus</th>
                            <th style="text-align: center;">Acciones & Evaluación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($applications)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 2.5rem; color: #888899;">No hay solicitudes B2B registradas.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td><strong>#<?php echo $app['id']; ?></strong></td>
                                    <td>
                                        <div style="font-weight: 700; color: #fff;"><?php echo htmlspecialchars(($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? '')); ?></div>
                                        <div style="font-size: 0.8rem; color: #888899;"><?php echo htmlspecialchars($app['email'] ?? ''); ?></div>
                                    </td>
                                    <td>
                                        <strong style="color: var(--theme-accent, #ff7f00); font-family: monospace;"><?php echo htmlspecialchars($app['rfc']); ?></strong>
                                        <div style="font-size: 0.85rem; color: #ccc;"><?php echo htmlspecialchars($app['tax_name']); ?></div>
                                    </td>
                                    <td>
                                        <span style="background: rgba(255,127,0,0.12); color: #ff7f00; border: 1px solid rgba(255,127,0,0.3); padding: 4px 8px; border-radius: 6px; font-weight: 700; font-size: 0.8rem;">
                                            <?php echo htmlspecialchars(strtoupper($app['requested_segment'] ?? 'contratista')); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($app['csf_document_path'])): ?>
                                            <a href="<?php echo htmlspecialchars($app['csf_document_path']); ?>" target="_blank" class="btn-svg" style="background:#1e1e24; color:#60a5fa; border:1px solid #333;">📄 Ver CSF (PDF)</a>
                                        <?php else: ?>
                                            <span style="color:#666; font-size:0.8rem;">No adjuntado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($app['status'] === 'pending'): ?>
                                            <span style="background: rgba(234,179,8,0.15); color: #eab308; padding: 4px 8px; border-radius: 6px; font-weight: 700; font-size: 0.8rem;">⏳ Pendiente</span>
                                        <?php elseif ($app['status'] === 'approved'): ?>
                                            <span style="background: rgba(34,197,94,0.15); color: #4ade80; padding: 4px 8px; border-radius: 6px; font-weight: 700; font-size: 0.8rem;">✅ Aprobado</span>
                                        <?php else: ?>
                                            <span style="background: rgba(239,68,68,0.15); color: #f87171; padding: 4px 8px; border-radius: 6px; font-weight: 700; font-size: 0.8rem;">❌ Rechazado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($app['status'] === 'pending'): ?>
                                            <form method="POST" style="display: inline-flex; gap: 0.4rem; align-items: center;">
                                                <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
                                                <select name="assigned_segment" style="background:#171720; color:#fff; border:1px solid #333; padding:5px; border-radius:6px; font-size:0.8rem;">
                                                    <option value="contratista">Contratista</option>
                                                    <option value="escuela">Escuela / Inst.</option>
                                                    <option value="mayoreo">Mayoreo</option>
                                                </select>
                                                <button type="submit" name="action" value="approve" class="btn-svg btn-green">Aprobar</button>
                                                <button type="submit" name="action" value="reject" class="btn-svg btn-red">Rechazar</button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color:#777; font-size:0.82rem;">Procesado</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    <script src="js/modals.js?v=4.1"></script>\n    <script src="js/main.js?v=2.6"></script>\n</body>
</html>
