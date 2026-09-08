<?php
/**
 * Módulo de Gestión de Devoluciones, Garantías y Monedero Digital (RMA)
 * Truper Platform - Fase 6
 */
require_once '../config/config.php';
require_admin();

$message = '';
$error = '';

// Handle approval / rejection of RMA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rmaId = (int)($_POST['rma_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $refundAmount = (float)($_POST['refund_amount'] ?? 0);

    if ($rmaId > 0 && in_array($action, ['approve', 'reject'], true)) {
        try {
            $stmt = $pdo->prepare("SELECT r.*, u.wallet_balance FROM returns_rma r LEFT JOIN users u ON r.user_id = u.id WHERE r.id = ?");
            $stmt->execute([$rmaId]);
            $rma = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($rma) {
                if ($action === 'approve') {
                    // Update RMA status
                    $updRma = $pdo->prepare("UPDATE returns_rma SET status = 'approved', refund_amount = ? WHERE id = ?");
                    $updRma->execute([$refundAmount, $rmaId]);

                    // Add refund credit to digital wallet balance
                    if (!empty($rma['user_id']) && $refundAmount > 0) {
                        $updWallet = $pdo->prepare("UPDATE users SET wallet_balance = COALESCE(wallet_balance, 0) + ? WHERE id = ?");
                        $updWallet->execute([$refundAmount, $rma['user_id']]);
                    }

                    // Log event
                    try {
                        $log = $pdo->prepare("INSERT INTO order_tracking_history (order_folio, status, notes, changed_by) VALUES (?, 'rma_approved', ?, ?)");
                        $log->execute([$rma['order_folio'], "Devolución RMA aprobada por $" . number_format($refundAmount, 2) . " abonada a Monedero Digital", $_SESSION['name'] ?? 'Admin']);
                    } catch (Exception $e) {}

                    $message = "Devolución RMA #{$rmaId} APROBADA. Saldo de $" . number_format($refundAmount, 2) . " acreditado al Monedero Digital del cliente.";
                } else {
                    $updRma = $pdo->prepare("UPDATE returns_rma SET status = 'rejected' WHERE id = ?");
                    $updRma->execute([$rmaId]);
                    $message = "Devolución RMA #{$rmaId} RECHAZADA.";
                }
            }
        } catch (Exception $e) {
            $error = 'Error al procesar RMA: ' . $e->getMessage();
        }
    }
}

// Fetch RMA records
$rmaList = [];
try {
    $sql = "SELECT r.*, u.first_name, u.last_name, u.email FROM returns_rma r LEFT JOIN users u ON r.user_id = u.id ORDER BY r.id DESC";
    $rmaList = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Gestión de Devoluciones (RMA) - Ferretería FOX</title>
    <link rel="stylesheet" href="css/styles.css?v=4.2">
    <link rel="stylesheet" href="css/theme.css?v=4.1">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        body { font-family: var(--theme-font, 'Outfit', sans-serif); background: #0b0b0e; color: #fff; }
        .rma-container { max-width: 1320px; margin: 2rem auto; padding: 0 1.25rem; }
        .rma-card { background: #121217; border: 1px solid #22222a; border-radius: 14px; padding: 1.5rem; margin-bottom: 1.5rem; }
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
                    <button class="nav-dropdown-btn active">Admin Tienda <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content" style="min-width: 220px;">
                        <a href="admin_online_orders.php">🌐 Pedidos Online</a>
                        <a href="order_tracking.php">🚚 Seguimiento y Guías</a>
                        <a href="admin_online_billing.php">🏛️ Facturación & Pagos SAT</a>
                        <a href="rma_manager.php" style="color: #ff7f00; font-weight: 700;">🔄 Devoluciones RMA</a>
                    </div>
                </div>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Admin Local <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content" style="min-width: 220px;">
                        <a href="ticket_validation.php">🏬 Validación Mostrador</a>
                        <a href="tickets.php">🎫 Historial de Tickets</a>
                        <a href="cashier.php">💵 Caja / Punto de Venta</a>
                        <a href="orders.php">📋 Ventas / Pedidos Mostrador</a>
                        <a href="tasks.php">👥 Tareas de Empleados</a>
                        <a href="b2b_approval.php">🤝 Aprobación B2B</a>
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

    <main class="rma-container">
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
            <div class="module-badge module-admin"><span class="module-glyph">RM</span> Devoluciones RMA</div>
            <h1>Módulo de Devoluciones, Garantías y Monedero Digital (RMA)</h1>
            <p class="text-muted">Resolución de solicitudes postventa, emisión de crédito en Monedero Digital o reembolso directo.</p>
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

        <div class="rma-card">
            <h3 style="margin-top: 0; font-size: 1.1rem; color: var(--theme-accent, #ff7f00);">Solicitudes de Devolución RMA Registradas</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Folio RMA</th>
                            <th>Folio Pedido</th>
                            <th>Cliente</th>
                            <th>Motivo de Devolución</th>
                            <th>Monto Solicitado</th>
                            <th>Estatus</th>
                            <th style="text-align: center;">Acciones & Reembolso</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rmaList)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 2.5rem; color: #888899;">No hay solicitudes de devolución RMA en el sistema.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rmaList as $rma): ?>
                                <tr>
                                    <td><strong>#RMA-<?php echo $rma['id']; ?></strong></td>
                                    <td><strong style="color:var(--theme-accent, #ff7f00); font-family:monospace;"><?php echo htmlspecialchars($rma['order_folio']); ?></strong></td>
                                    <td>
                                        <div style="font-weight: 700; color: #fff;"><?php echo htmlspecialchars(($rma['first_name'] ?? 'Cliente') . ' ' . ($rma['last_name'] ?? '')); ?></div>
                                        <div style="font-size: 0.8rem; color: #888899;"><?php echo htmlspecialchars($rma['email'] ?? ''); ?></div>
                                    </td>
                                    <td style="color:#ccc; font-size:0.88rem; max-width:250px;"><?php echo htmlspecialchars($rma['reason']); ?></td>
                                    <td><strong style="color:#4ade80;">$<?php echo number_format((float)$rma['refund_amount'], 2); ?></strong></td>
                                    <td>
                                        <?php if ($rma['status'] === 'pending'): ?>
                                            <span style="background: rgba(234,179,8,0.15); color: #eab308; padding: 4px 8px; border-radius: 6px; font-weight: 700; font-size: 0.8rem;">⏳ Pendiente</span>
                                        <?php elseif ($rma['status'] === 'approved'): ?>
                                            <span style="background: rgba(34,197,94,0.15); color: #4ade80; padding: 4px 8px; border-radius: 6px; font-weight: 700; font-size: 0.8rem;">✅ Acreditado Monedero</span>
                                        <?php else: ?>
                                            <span style="background: rgba(239,68,68,0.15); color: #f87171; padding: 4px 8px; border-radius: 6px; font-weight: 700; font-size: 0.8rem;">❌ Rechazado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($rma['status'] === 'pending'): ?>
                                            <form method="POST" style="display: inline-flex; gap: 0.4rem; align-items: center;">
                                                <input type="hidden" name="rma_id" value="<?php echo $rma['id']; ?>">
                                                <input type="number" step="0.01" name="refund_amount" value="<?php echo $rma['refund_amount'] > 0 ? $rma['refund_amount'] : ''; ?>" placeholder="Monto $" style="width:90px; background:#171720; color:#fff; border:1px solid #333; padding:5px; border-radius:6px; font-size:0.8rem;" required>
                                                <button type="submit" name="action" value="approve" class="btn-svg btn-green">Acreditar Monedero</button>
                                                <button type="submit" name="action" value="reject" class="btn-svg btn-red">Rechazar</button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color:#777; font-size:0.82rem;">Completado</span>
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
