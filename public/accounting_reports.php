<?php
/**
 * Módulo de Conciliación y Exportación Contable Mensual (para la Contadora)
 * Truper Platform - Fase 6
 */
require_once '../config/config.php';
require_admin();

$year = (int)($_GET['year'] ?? date('Y'));
$month = (int)($_GET['month'] ?? date('m'));
$exportFormat = $_GET['format'] ?? '';

// Generate CSV export for contadora
if ($exportFormat === 'csv') {
    $filename = "Conciliacion_Contable_FOX_{$year}_" . str_pad($month, 2, '0', STR_PAD_LEFT) . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");

    $output = fopen('php://output', 'w');
    // UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Headers requested by accountant
    fputcsv($output, [
        'Folio Interno',
        'Fecha / Hora',
        'UUID (Folio Fiscal SAT)',
        'Tipo Comprobante',
        'RFC Cliente',
        'Razón Social / Cliente',
        'Subtotal MXN',
        'IVA (16%) MXN',
        'Total MXN',
        'Método de Pago',
        'Comisión Pasarela MXN',
        'Estatus'
    ]);

    $sql = "
        SELECT st.folio, st.issued_date, COALESCE(st.uuid_fiscal, 'VENTA-GENERAL-SIN-TIMBRE') AS uuid_fiscal,
               CASE WHEN st.invoice_required THEN 'Factura CFDI 4.0' ELSE 'Nota de Venta' END AS tipo_comprobante,
               COALESCE(u.rfc, 'XAXX010101000') AS rfc_cliente,
               st.customer_name,
               st.total_amount,
               st.payment_method,
               COALESCE(st.pasarela_commission, 0.00) AS pasarela_commission,
               COALESCE(st.order_status, 'in_preparation') AS order_status
        FROM sales_tickets st
        LEFT JOIN users u ON st.user_id = u.id
        WHERE st.deleted_at IS NULL
          AND EXTRACT(YEAR FROM st.issued_date) = ?
          AND EXTRACT(MONTH FROM st.issued_date) = ?
        ORDER BY st.id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$year, $month]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $total = (float)$row['total_amount'];
        $subtotal = round($total / 1.16, 2);
        $iva = round($total - $subtotal, 2);

        fputcsv($output, [
            $row['folio'],
            substr((string)$row['issued_date'], 0, 19),
            $row['uuid_fiscal'],
            $row['tipo_comprobante'],
            $row['rfc_cliente'],
            $row['customer_name'],
            number_format($subtotal, 2, '.', ''),
            number_format($iva, 2, '.', ''),
            number_format($total, 2, '.', ''),
            $row['payment_method'],
            number_format((float)$row['pasarela_commission'], 2, '.', ''),
            strtoupper($row['order_status'])
        ]);
    }

    fclose($output);
    exit;
}

// Fetch preview data for UI
$sqlPreview = "
    SELECT st.folio, st.issued_date, COALESCE(st.uuid_fiscal, 'PENDIENTE-GLOBAL') AS uuid_fiscal,
           CASE WHEN st.invoice_required THEN 'CFDI 4.0' ELSE 'Nota Venta' END AS tipo_comprobante,
           COALESCE(u.rfc, 'XAXX010101000') AS rfc_cliente,
           st.customer_name,
           st.total_amount,
           st.payment_method,
           COALESCE(st.pasarela_commission, 0.00) AS pasarela_commission,
           COALESCE(st.order_status, 'in_preparation') AS order_status
    FROM sales_tickets st
    LEFT JOIN users u ON st.user_id = u.id
    WHERE st.deleted_at IS NULL
      AND EXTRACT(YEAR FROM st.issued_date) = ?
      AND EXTRACT(MONTH FROM st.issued_date) = ?
    ORDER BY st.id DESC
";

$stmtP = $pdo->prepare($sqlPreview);
$stmtP->execute([$year, $month]);
$previewRows = $stmtP->fetchAll(PDO::FETCH_ASSOC);

$monthTotal = 0.0;
$monthSubtotal = 0.0;
$monthIva = 0.0;
$monthCommission = 0.0;

foreach ($previewRows as $r) {
    $t = (float)$r['total_amount'];
    $sub = round($t / 1.16, 2);
    $monthTotal += $t;
    $monthSubtotal += $sub;
    $monthIva += ($t - $sub);
    $monthCommission += (float)$r['pasarela_commission'];
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Reportes Contables & Conciliación - Ferretería FOX</title>
    <link rel="stylesheet" href="css/styles.css?v=4.2">
    <link rel="stylesheet" href="css/theme.css?v=4.1">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        body { font-family: var(--theme-font, 'Outfit', sans-serif); background: #0b0b0e; color: #fff; }
        .acc-container { max-width: 1360px; margin: 2rem auto; padding: 0 1.25rem; }
        .acc-card { background: #121217; border: 1px solid #22222a; border-radius: 14px; padding: 1.5rem; margin-bottom: 1.5rem; }
        .grid-kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .kpi-box { background: #171720; border: 1px solid #2a2a38; border-radius: 10px; padding: 1.1rem; }
        .kpi-title { font-size: 0.75rem; color: #888899; text-transform: uppercase; font-weight: 700; }
        .kpi-val { font-size: 1.4rem; font-weight: 800; color: #fff; margin-top: 0.3rem; }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.88rem; }
        th { background: #171720; padding: 0.85rem 1rem; border-bottom: 1px solid #282834; color: #888899; text-transform: uppercase; font-size: 0.72rem; letter-spacing: .06em; font-weight: 700; }
        td { padding: 0.85rem 1rem; border-bottom: 1px solid #1a1a24; vertical-align: middle; }
        .btn-svg { display: inline-flex; align-items: center; gap: 0.4rem; padding: 8px 16px; border-radius: 8px; font-size: 0.88rem; font-weight: 700; text-decoration: none; cursor: pointer; border: none; }
        .btn-orange { background: var(--theme-accent, #ff7f00); color: #fff; }
        @media (max-width: 800px) { .grid-kpis { grid-template-columns: repeat(2, 1fr); } }

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
                    <div class="nav-dropdown-content" style="min-width: 220px;">
                        <a href="admin_online_orders.php">🌐 Pedidos Online</a>
                        <a href="order_tracking.php">🚚 Seguimiento y Guías</a>
                        <a href="admin_online_billing.php">🏛️ Facturación & Pagos SAT</a>
                        <a href="rma_manager.php">🔄 Devoluciones RMA</a>
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

    <main class="acc-container">
        <!-- ── Back Button ── -->
        <div class="back-header">
            <button onclick="history.back()" class="btn-back btn-back-dark btn-back-as-button">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Regresar
            </button>
        </div>

        <div class="page-hero d-flex justify-between align-center flex-wrap gap-1">
            <div>
                <div class="module-badge module-finance"><span class="module-glyph">CO</span> Reportes Contables</div>
                <h1>Panel de Conciliación Contable Mensual</h1>
                <p class="text-muted">Exportación limpia de libros de ventas, folios fiscales UUID, desglose de IVA y comisiones de pasarela para contabilidad.</p>
            </div>
            <div>
                <a href="accounting_reports.php?year=<?php echo $year; ?>&month=<?php echo $month; ?>&format=csv" class="btn-svg btn-orange">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    Descargar Libro CSV (Excel Contadora)
                </a>
            </div>
        </div>

        <!-- Filter Form -->
        <div class="acc-card" style="padding: 1.1rem;">
            <form method="GET" style="display:flex; gap:1rem; align-items:center; flex-wrap:wrap;">
                <div style="font-weight:700; color:#fff;">Filtrar Período Contable:</div>
                <select name="year" onchange="this.form.submit()" style="background:#171720; color:#fff; border:1px solid #333; padding:8px 12px; border-radius:6px;">
                    <?php for ($y = date('Y'); $y >= 2024; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y === $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
                <select name="month" onchange="this.form.submit()" style="background:#171720; color:#fff; border:1px solid #333; padding:8px 12px; border-radius:6px;">
                    <?php
                    $months = [1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril', 5=>'Mayo', 6=>'Junio', 7=>'Julio', 8=>'Agosto', 9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre'];
                    foreach ($months as $mNum => $mName):
                    ?>
                        <option value="<?php echo $mNum; ?>" <?php echo $mNum === $month ? 'selected' : ''; ?>><?php echo $mName; ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <!-- KPI Metrics Grid -->
        <div class="grid-kpis">
            <div class="kpi-box">
                <div class="kpi-title">Ventas Totales Brutas</div>
                <div class="kpi-val" style="color:var(--theme-accent, #ff7f00);">$<?php echo number_format($monthTotal, 2); ?></div>
            </div>
            <div class="kpi-box">
                <div class="kpi-title">Subtotal (Sin IVA)</div>
                <div class="kpi-val">$<?php echo number_format($monthSubtotal, 2); ?></div>
            </div>
            <div class="kpi-box">
                <div class="kpi-title">IVA Trasladado (16%)</div>
                <div class="kpi-val" style="color:#4ade80;">$<?php echo number_format($monthIva, 2); ?></div>
            </div>
            <div class="kpi-box">
                <div class="kpi-title">Comisiones Pasarela</div>
                <div class="kpi-val" style="color:#60a5fa;">$<?php echo number_format($monthCommission, 2); ?></div>
            </div>
        </div>

        <!-- Preview Table -->
        <div class="acc-card">
            <h3 style="margin-top: 0; font-size: 1.1rem; color: #fff;">Previsualización de Transacciones (<?php echo count($previewRows); ?> registros)</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Fecha</th>
                            <th>UUID Fiscal (SAT)</th>
                            <th>Tipo</th>
                            <th>RFC Cliente</th>
                            <th>Cliente</th>
                            <th>Subtotal</th>
                            <th>IVA (16%)</th>
                            <th>Total</th>
                            <th>Comisión</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($previewRows)): ?>
                            <tr>
                                <td colspan="10" style="text-align: center; padding: 2.5rem; color: #888899;">No hay registros contables en este período.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($previewRows as $r): ?>
                                <?php
                                $tot = (float)$r['total_amount'];
                                $sub = round($tot / 1.16, 2);
                                $iva = round($tot - $sub, 2);
                                ?>
                                <tr>
                                    <td><strong style="color:var(--theme-accent, #ff7f00); font-family:monospace;"><?php echo htmlspecialchars($r['folio']); ?></strong></td>
                                    <td style="color:#aaa; font-size:0.82rem;"><?php echo substr((string)$r['issued_date'], 0, 16); ?></td>
                                    <td style="font-family:monospace; font-size:0.8rem; color:#60a5fa;"><?php echo htmlspecialchars($r['uuid_fiscal']); ?></td>
                                    <td><span style="background:rgba(255,255,255,0.05); padding:3px 6px; border-radius:4px; font-size:0.75rem;"><?php echo htmlspecialchars($r['tipo_comprobante']); ?></span></td>
                                    <td style="font-family:monospace; font-size:0.82rem; font-weight:700;"><?php echo htmlspecialchars($r['rfc_cliente']); ?></td>
                                    <td style="color:#eee; font-weight:600;"><?php echo htmlspecialchars($r['customer_name']); ?></td>
                                    <td>$<?php echo number_format($sub, 2); ?></td>
                                    <td style="color:#4ade80;">$<?php echo number_format($iva, 2); ?></td>
                                    <td><strong style="color:#fff;">$<?php echo number_format($tot, 2); ?></strong></td>
                                    <td style="color:#888;">$<?php echo number_format((float)$r['pasarela_commission'], 2); ?></td>
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
