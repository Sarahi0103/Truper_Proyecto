<?php
require_once '../config/config.php';

$id = (int)($_GET['id'] ?? 0);
$folio = trim((string)($_GET['folio'] ?? ''));
$format = ($_GET['format'] ?? 'thermal') === 'a4' ? 'a4' : 'thermal';
$autoPdf = isset($_GET['auto_pdf']) && $_GET['auto_pdf'] === '1';

if ($id <= 0 && empty($folio)) {
    http_response_code(400);
    echo 'Identificador de ticket no proporcionado';
    exit;
}

$ticketFound = null;
$items = [];
$isOnline = false;

// 1. Intentar buscar en sales_tickets (por folio o id)
if (!empty($folio) || $id > 0) {
    $stmtSt = $pdo->prepare("
        SELECT st.*,
               COALESCE(st.customer_name, u.first_name || CASE WHEN u.last_name IS NOT NULL AND u.last_name <> '' THEN ' ' || u.last_name ELSE '' END, 'Cliente Mostrador') as client_name,
               COALESCE(u.email, '') as client_email,
               COALESCE(u.user_code, '') as user_code,
               tr.carrier,
               tr.tracking_number,
               tr.estimated_delivery
        FROM sales_tickets st
        LEFT JOIN users u ON st.user_id = u.id
        LEFT JOIN shipping_tracking tr ON (st.id = tr.order_id OR (st.order_id IS NOT NULL AND st.order_id = tr.order_id))
        WHERE (:folio <> '' AND st.folio = :folio) OR (:id > 0 AND (st.id = :id OR st.order_id = :id))
        LIMIT 1
    ");
    $stmtSt->execute([':folio' => $folio, ':id' => $id]);
    $ticketFound = $stmtSt->fetch(PDO::FETCH_ASSOC);

    if ($ticketFound) {
        // Cargar productos de ticket_items
        $stmtItems = $pdo->prepare("
            SELECT ti.quantity, ti.unit_price, COALESCE(ti.total, ti.quantity * ti.unit_price) as line_total,
                   ti.product_name as name, COALESCE(p.sku, 'PROD-' || ti.product_id) as sku
            FROM ticket_items ti
            LEFT JOIN products p ON ti.product_id = p.id
            WHERE ti.ticket_id = ?
            ORDER BY ti.id ASC
        ");
        $stmtItems->execute([$ticketFound['id']]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        // Si no hay items en ticket_items pero tiene order_id, buscar en order_items
        if (empty($items) && !empty($ticketFound['order_id'])) {
            $stmtOrderItems = $pdo->prepare("
                SELECT oi.quantity, oi.unit_price, oi.line_total, p.name, p.sku
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?
            ");
            $stmtOrderItems->execute([$ticketFound['order_id']]);
            $items = $stmtOrderItems->fetchAll(PDO::FETCH_ASSOC);
        }

        // Determinar canal estricto (Tienda en Línea vs Tienda Local)
        $folioStr = (string)($ticketFound['folio'] ?? '');
        $isOnlineFolio = strpos($folioStr, 'TCK-') === 0 || strpos($folioStr, 'FOX-') === 0;
        $hasCarrier = !empty($ticketFound['carrier']) || !empty($ticketFound['tracking_number']) || !empty($ticketFound['tracking_folio']);
        
        $hasRealShippingAddr = false;
        if (!empty($ticketFound['shipping_address_json']) && $ticketFound['shipping_address_json'] !== '{}' && $ticketFound['shipping_address_json'] !== '[]') {
            try {
                $sData = json_decode($ticketFound['shipping_address_json'], true);
                if (is_array($sData) && !empty($sData['address']) && trim($sData['address']) !== '') {
                    $cust = (string)($ticketFound['customer_name'] ?? '');
                    if (strpos($cust, 'Cotización') === false || !empty($ticketFound['order_id'])) {
                        $hasRealShippingAddr = true;
                    }
                }
            } catch(Exception $e) {}
        }

        $isOnline = $isOnlineFolio || $hasCarrier || $hasRealShippingAddr;
    }
}

// 2. Si no se encontró en sales_tickets, buscar en orders
if (!$ticketFound) {
    $userCodeSelect = db_column_exists('users', 'user_code') ? 'COALESCE(u.user_code, \'\') AS user_code' : "'' AS user_code";
    $sql = "SELECT o.*, c.user_id, u.first_name, u.last_name, {$userCodeSelect}
        FROM orders o
        JOIN clients c ON c.id = o.client_id
        JOIN users u ON u.id = c.user_id
        WHERE (:id > 0 AND o.id = :id) OR (:folio <> '' AND o.order_number = :folio)
        LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id, ':folio' => $folio]);
    $orderRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$orderRow) {
        http_response_code(404);
        echo 'Ticket no encontrado';
        exit;
    }

    $itemsStmt = $pdo->prepare("SELECT oi.quantity, oi.unit_price, oi.line_total, p.name, p.sku FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?");
    $itemsStmt->execute([$orderRow['id']]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $ticketFound = [
        'id' => $orderRow['id'],
        'folio' => $orderRow['order_number'],
        'issued_date' => $orderRow['created_at'] ?? $orderRow['order_date'],
        'client_name' => trim(($orderRow['first_name'] ?? '') . ' ' . ($orderRow['last_name'] ?? '')),
        'total_amount' => $orderRow['total_amount'],
        'payment_status' => $orderRow['payment_status'] ?? 'pending',
        'payment_method' => $orderRow['payment_method'] ?? 'Efectivo',
        'order_status' => $orderRow['status'] ?? 'pending',
        'user_code' => $orderRow['user_code'] ?? '',
        'user_id' => $orderRow['user_id'] ?? null
    ];
}

// Extraer dirección y paquetería si es online
$shippingData = [];
$destAddress = '';
$destCity = '';
$destCP = '';
if (!empty($ticketFound['shipping_address_json'])) {
    try {
        $shippingData = json_decode($ticketFound['shipping_address_json'], true) ?: [];
        $destAddress = $shippingData['address'] ?? '';
        $destCity = $shippingData['city'] ?? '';
        $destCP = $shippingData['postalCode'] ?? '';
    } catch(Exception $e) {}
}

$carrierName = $ticketFound['carrier'] ?? '';
$trackingNum = $ticketFound['tracking_number'] ?? $ticketFound['tracking_folio'] ?? '';
$estDate = $ticketFound['estimated_delivery'] ?? '';

function display_product_code($sku) {
    return preg_replace('/^XLS-/i', '', (string)$sku);
}

// Convertir logo a base64
$logoPath = __DIR__ . '/truper_logo2.png';
if (!file_exists($logoPath)) {
    $logoPath = __DIR__ . '/../truper_logo2.png';
}
if (!file_exists($logoPath)) {
    $logoPath = __DIR__ . '/img/logo_fox.png';
}
$logoBase64 = '';
if (file_exists($logoPath)) {
    $logoData = file_get_contents($logoPath);
    $mime = strpos($logoPath, '.png') !== false ? 'image/png' : 'image/jpeg';
    $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode($logoData);
}
?>
<!DOCTYPE html>
<html lang="es">
    <script src="/js/jspdf.umd.min.js"></script>
    <script>
        if (!window.jspdf && !window.jsPDF) {
            document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"><\/script>');
        }
    </script>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket FOX - <?php echo htmlspecialchars($ticketFound['folio'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo $isOnline ? 'Online' : 'Local'; ?>)</title>
    <link rel="stylesheet" href="css/theme.css?v=4.1">
    <link rel="stylesheet" href="css/responsive-complete.css?v=5.0">
    <style>
        body { font-family: monospace; margin: 0; padding: 12px; background: #0c0c10; color: #fff; }
        .ticket {
            width: min(100%, <?php echo $format === 'a4' ? '780px' : '320px'; ?>);
            margin: 0 auto;
            background: #141419;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 16px;
            box-sizing: border-box;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        h1 { text-align: center; font-size: 17px; margin: 0 0 6px; letter-spacing: 0.5px; }
        .line { border-top: 1px dashed rgba(255,255,255,0.18); margin: 10px 0; }
        .row { margin-bottom: 5px; font-size: 12px; line-height: 1.35; }
        .format-switch { text-align: center; margin-bottom: 12px; font-size: 12px; }
        .format-switch a { color: var(--color-naranja, #ff7f00); text-decoration: none; font-weight: 600; margin: 0 4px; }
        .format-switch a:hover { text-decoration: underline; }

        /* Banner de Canal */
        .channel-badge-banner {
            text-align: center;
            padding: 7px 10px;
            border-radius: 6px;
            font-weight: 800;
            font-size: 12px;
            margin-bottom: 12px;
            letter-spacing: 0.5px;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .channel-badge-banner.online {
            background: rgba(59, 130, 246, 0.16);
            border: 1px solid rgba(59, 130, 246, 0.45);
            color: #60a5fa;
        }
        .channel-badge-banner.local {
            background: rgba(16, 185, 129, 0.16);
            border: 1px solid rgba(16, 185, 129, 0.45);
            color: #34d399;
        }
        .channel-badge-sub {
            font-size: 9.5px;
            font-weight: 600;
            opacity: 0.85;
            text-transform: uppercase;
        }

        /* Bloque de Información de Envío / Mostrador */
        .info-box-channel {
            border-radius: 6px;
            padding: 8px 10px;
            margin: 8px 0;
            font-size: 11px;
            line-height: 1.45;
        }
        .info-box-channel.online {
            background: rgba(255, 127, 0, 0.08);
            border: 1px dashed rgba(255, 127, 0, 0.4);
            color: #ff9f43;
        }
        .info-box-channel.local {
            background: rgba(16, 185, 129, 0.08);
            border: 1px dashed rgba(16, 185, 129, 0.4);
            color: #6ee7b7;
        }

        /* Timeline styles */
        .order-timeline {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 16px 0;
            padding: 8px 0;
            position: relative;
        }
        .order-timeline::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background: rgba(255,255,255,0.12);
            z-index: 1;
            transform: translateY(-50%);
        }
        .timeline-progress-line {
            position: absolute;
            top: 50%;
            left: 0;
            height: 2px;
            background: var(--color-naranja, #ff6600);
            z-index: 1;
            transform: translateY(-50%);
            transition: width 0.4s ease;
        }
        .timeline-step {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 25%;
        }
        .timeline-icon {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: #1e1e24;
            border: 2px solid rgba(255,255,255,0.18);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #888;
            transition: all 0.3s ease;
        }
        .timeline-step.active .timeline-icon {
            background: var(--color-naranja, #ff6600);
            border-color: var(--color-naranja, #ff6600);
            color: #fff;
            box-shadow: 0 0 10px rgba(255,102,0,0.5);
        }
        .timeline-label {
            font-size: 8px;
            font-weight: bold;
            margin-top: 5px;
            color: #888;
            text-align: center;
            text-transform: uppercase;
        }
        .timeline-step.active .timeline-label {
            color: var(--color-naranja, #ff6600);
        }

        @media print {
            html, body { background: #fff !important; color: #000 !important; padding: 0 !important; }
            .ticket { background: #fff !important; color: #000 !important; border: 1px solid #000 !important; box-shadow: none !important; width: 100% !important; max-width: 300px !important; }
            .line { border-top-color: #000 !important; }
            .no-print, .format-switch, .order-timeline { display: none !important; }
            .channel-badge-banner { background: #f0f0f0 !important; color: #000 !important; border: 1px solid #000 !important; }
            .info-box-channel { background: #fafafa !important; color: #000 !important; border: 1px dashed #000 !important; }
        }
    </style>
</head>
<body <?php echo !$autoPdf ? '' : 'onload="window.print()"'; ?>>
<div class="no-print" style="width: min(100%, <?php echo $format === 'a4' ? '780px' : '320px'; ?>); margin: 0 auto 12px;">
    <button onclick="history.back()" class="btn-back" style="display:inline-flex; align-items:center; gap:6px; background:#141419; border:1px solid rgba(255,255,255,0.18); color:#fff; padding:7px 16px; border-radius:8px; font-weight:700; font-size:0.85rem; cursor:pointer; text-decoration:none; transition: all 0.2s;" onmouseenter="this.style.borderColor='#ff7f00'; this.style.color='#ff7f00';" onmouseleave="this.style.borderColor='rgba(255,255,255,0.18)'; this.style.color='#fff';">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M19 12H5M12 19l-7-7 7-7"/>
        </svg>
        Regresar
    </button>
</div>
<div class="ticket">
    <div class="format-switch">
        <a href="?folio=<?php echo urlencode($ticketFound['folio']); ?>&format=thermal">Térmico (80mm)</a> |
        <a href="?folio=<?php echo urlencode($ticketFound['folio']); ?>&format=a4">A4 (Carta)</a> |
        <a href="#" onclick="window.print(); return false;">🖨️ Imprimir</a> |
        <a href="#" onclick="downloadClientTicketPdf(); return false;">📥 Descargar PDF</a>
    </div>

    <!-- Banner Distintivo del Canal (Tienda en Línea vs Tienda Local) -->
    <?php if ($isOnline): ?>
        <div class="channel-badge-banner online">
            <span>🌐 TIENDA EN LÍNEA</span>
            <span class="channel-badge-sub">VENTA DIGITAL CON ENVÍO A DOMICILIO</span>
        </div>
    <?php else: ?>
        <div class="channel-badge-banner local">
            <span>🏬 TIENDA LOCAL</span>
            <span class="channel-badge-sub">VENTA FÍSICA / MOSTRADOR SUCURSAL</span>
        </div>
    <?php endif; ?>

    <div style="text-align: center; margin-bottom: 8px;">
        <h1 style="margin: 0; font-weight: 800; color: #ff7f00;">FERRETERÍA FOX</h1>
        <div style="font-size: 10px; color: #999;">RFC: FFO-840912-XX1 | SUCURSAL MATRIZ</div>
    </div>

    <!-- Timeline Adaptada -->
    <?php
    $status = strtolower($ticketFound['order_status'] ?? $ticketFound['pickup_status'] ?? 'pending');
    $step1 = true;
    $step2 = in_array($status, ['processing', 'in_preparation', 'shipped', 'delivered', 'picked_up']);
    $step3 = in_array($status, ['shipped', 'delivered', 'picked_up']);
    $step4 = in_array($status, ['delivered', 'picked_up']);

    $progressPct = 0;
    if ($step4) $progressPct = 100;
    elseif ($step3) $progressPct = 66.6;
    elseif ($step2) $progressPct = 33.3;
    ?>
    <div class="order-timeline">
        <div class="timeline-progress-line" style="width: <?php echo $progressPct; ?>%;"></div>
        <?php if ($isOnline): ?>
            <div class="timeline-step <?php echo $step1 ? 'active' : ''; ?>">
                <div class="timeline-icon">📝</div>
                <div class="timeline-label">Recibido</div>
            </div>
            <div class="timeline-step <?php echo $step2 ? 'active' : ''; ?>">
                <div class="timeline-icon">📦</div>
                <div class="timeline-label">Preparando</div>
            </div>
            <div class="timeline-step <?php echo $step3 ? 'active' : ''; ?>">
                <div class="timeline-icon">🚚</div>
                <div class="timeline-label">Enviado</div>
            </div>
            <div class="timeline-step <?php echo $step4 ? 'active' : ''; ?>">
                <div class="timeline-icon">✅</div>
                <div class="timeline-label">Entregado</div>
            </div>
        <?php else: ?>
            <div class="timeline-step <?php echo $step1 ? 'active' : ''; ?>">
                <div class="timeline-icon">📝</div>
                <div class="timeline-label">Registrado</div>
            </div>
            <div class="timeline-step <?php echo $step2 ? 'active' : ''; ?>">
                <div class="timeline-icon">💵</div>
                <div class="timeline-label">Cobrado</div>
            </div>
            <div class="timeline-step <?php echo $step3 ? 'active' : ''; ?>">
                <div class="timeline-icon">🏬</div>
                <div class="timeline-label">Listo Mostrador</div>
            </div>
            <div class="timeline-step <?php echo $step4 ? 'active' : ''; ?>">
                <div class="timeline-icon">✅</div>
                <div class="timeline-label">Entregado</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Metadatos de la Venta -->
    <div class="row"><strong>Folio:</strong> <span style="color:#ff7f00; font-weight:700;"><?php echo htmlspecialchars($ticketFound['folio'], ENT_QUOTES, 'UTF-8'); ?></span></div>
    <div class="row"><strong>Fecha:</strong> <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($ticketFound['issued_date'])), ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="row"><strong>Cliente:</strong> <?php echo htmlspecialchars($ticketFound['client_name'] ?? 'Público General', ENT_QUOTES, 'UTF-8'); ?></div>
    <?php if (!empty($ticketFound['user_code'])): ?>
        <div class="row"><strong>Código Cliente:</strong> <?php echo htmlspecialchars($ticketFound['user_code'], ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <div class="row"><strong>Pago:</strong> <?php echo htmlspecialchars($ticketFound['payment_method'] ?? 'Efectivo', ENT_QUOTES, 'UTF-8'); ?> (<?php echo ($ticketFound['payment_status'] ?? '') === 'completed' ? '✓ Pagado' : '⏳ Pendiente'; ?>)</div>

    <!-- Cuadro de Canal (Envío vs Mostrador) -->
    <?php if ($isOnline): ?>
        <div class="info-box-channel online">
            <div style="font-weight: 800; margin-bottom: 2px;">📦 DATOS DE ENVÍO Y PAQUETERÍA:</div>
            <div>🚚 <strong>Paquetería:</strong> <?php echo htmlspecialchars($carrierName ?: 'En preparación de despacho'); ?></div>
            <?php if (!empty($trackingNum)): ?>
                <div>🔖 <strong>Guía:</strong> <span style="font-family: monospace; font-weight: 700; color: #60a5fa;"><?php echo htmlspecialchars($trackingNum); ?></span></div>
            <?php endif; ?>
            <?php if (!empty($destAddress)): ?>
                <div>📍 <strong>Destino:</strong> <?php echo htmlspecialchars($destAddress); ?></div>
            <?php endif; ?>
            <?php if (!empty($destCity)): ?>
                <div>🏙️ <strong>Ciudad/CP:</strong> <?php echo htmlspecialchars($destCity . ($destCP ? " (CP $destCP)" : '')); ?></div>
            <?php endif; ?>
            <?php if (!empty($estDate)): ?>
                <div>📅 <strong>Llegada aprox:</strong> <?php echo htmlspecialchars(date('d/m/Y', strtotime($estDate))); ?></div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="info-box-channel local">
            <div style="font-weight: 800; margin-bottom: 2px;">🏬 ENTREGA EN MOSTRADOR:</div>
            <div>📍 <strong>Sucursal:</strong> Ferretería FOX - Mostrador Principal</div>
            <div>📋 <strong>Estatus:</strong> <?php echo (($ticketFound['pickup_status'] ?? '') === 'picked_up') ? '✓ Mercancía entregada en mostrador' : '⏳ Pendiente de recoger en sucursal'; ?></div>
        </div>
    <?php endif; ?>

    <div class="line"></div>

    <!-- Detalle de Artículos -->
    <div style="font-weight: 800; font-size: 12px; margin-bottom: 6px; color: #bbb;">ARTÍCULOS:</div>
    <?php if (empty($items)): ?>
        <div class="row" style="color:#aaa; font-style:italic;">1 x Venta General de Mostrador</div>
    <?php else: ?>
        <?php foreach ($items as $it): ?>
            <div class="row">
                <div style="font-weight: 700; color: #fff;"><?php echo htmlspecialchars($it['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div style="color: #888; font-size: 11px;">SKU: <?php echo htmlspecialchars(display_product_code($it['sku'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div style="display: flex; justify-content: space-between; font-size: 11.5px; margin-top: 2px;">
                    <span><?php echo (int)$it['quantity']; ?> x $<?php echo number_format((float)$it['unit_price'], 2, '.', ','); ?></span>
                    <strong style="color: #ff9f43;">$<?php echo number_format((float)$it['line_total'], 2, '.', ','); ?></strong>
                </div>
            </div>
            <div style="border-top: 1px dotted rgba(255,255,255,0.08); margin: 6px 0;"></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="line"></div>

    <!-- Totales -->
    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 15px; font-weight: 800; color: #ff7f00; margin-top: 6px;">
        <span>TOTAL:</span>
        <span>$<?php echo number_format((float)$ticketFound['total_amount'], 2, '.', ','); ?> MXN</span>
    </div>

    <div class="line"></div>

    <div style="text-align: center; font-size: 10px; color: #777; margin-top: 10px; line-height: 1.4;">
        <div>¡Gracias por su preferencia!</div>
        <div>Conserve este comprobante para cualquier aclaración o seguimiento.</div>
        <?php if ($isOnline): ?>
            <div style="color: #60a5fa; margin-top: 4px;">Rastree su paquete en: localhost:8000/order_tracking.php</div>
        <?php endif; ?>
    </div>
</div>

<script>
const ticketData = {
    folio: <?php echo json_encode($ticketFound['folio']); ?>,
    isOnline: <?php echo json_encode($isOnline); ?>,
    issuedAt: <?php echo json_encode(date('d/m/Y H:i', strtotime($ticketFound['issued_date']))); ?>,
    client: <?php echo json_encode($ticketFound['client_name'] ?? 'Público General'); ?>,
    clientCode: <?php echo json_encode($ticketFound['user_code'] ?? 'N/A'); ?>,
    carrier: <?php echo json_encode($carrierName); ?>,
    trackingNumber: <?php echo json_encode($trackingNum); ?>,
    destination: <?php echo json_encode(trim($destAddress . ($destCity ? " $destCity" : '') . ($destCP ? " CP $destCP" : ''))); ?>,
    total: <?php echo json_encode((float)$ticketFound['total_amount']); ?>,
    items: <?php echo json_encode(array_map(function($it) {
        return [
            'name' => $it['name'],
            'sku' => display_product_code($it['sku'] ?? ''),
            'quantity' => (int)$it['quantity'],
            'price' => (float)$it['unit_price'],
            'line_total' => (float)$it['line_total']
        ];
    }, $items), JSON_UNESCAPED_UNICODE); ?>
};

function money(value) {
    return '$' + Number(value || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function downloadClientTicketPdf() {
    const jsPdfClass = (window.jspdf && window.jspdf.jsPDF) ? window.jspdf.jsPDF : (window.jsPDF || null);
    if (!jsPdfClass) {
        window.print();
        return;
    }

    const jsPDF = jsPdfClass;
    const logoBase64 = <?php echo json_encode($logoBase64); ?>;
    const items = Array.isArray(ticketData.items) ? ticketData.items : [];
    
    let dynamicHeight = 90;
    if (ticketData.isOnline) dynamicHeight += 20;
    items.forEach(item => {
        const nameLength = String(item.name || '').length;
        const nameLines = Math.ceil(nameLength / 32);
        dynamicHeight += (nameLines * 4.5) + 8;
    });
    dynamicHeight = Math.max(140, Math.round(dynamicHeight));

    const doc = new jsPDF({ unit: 'mm', format: [80, dynamicHeight] });
    let y = 6;

    if (logoBase64) {
        try {
            doc.addImage(logoBase64, 'PNG', 6, y, 12, 14);
        } catch(e) {}
    }

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(11);
    doc.setTextColor(255, 127, 0);
    doc.text('FERRETERÍA FOX', 20, y + 4);

    doc.setFontSize(8);
    doc.setTextColor(33, 37, 41);
    const channelTitle = ticketData.isOnline ? 'TICKET TIENDA EN LÍNEA' : 'TICKET TIENDA LOCAL';
    doc.text(channelTitle, 20, y + 8);

    doc.setFont('helvetica', 'normal');
    doc.setFontSize(7.5);
    doc.setTextColor(100, 116, 139);
    doc.text('Folio: ' + ticketData.folio, 20, y + 12);

    y += 16;
    doc.setDrawColor(203, 213, 225);
    doc.setLineWidth(0.3);
    doc.line(6, y, 74, y);
    y += 5;

    // Metadatos
    doc.setFontSize(8);
    doc.setTextColor(71, 85, 105);
    doc.text('Fecha: ' + ticketData.issuedAt, 6, y); y += 4;
    doc.text('Cliente: ' + ticketData.client, 6, y); y += 4;

    if (ticketData.isOnline) {
        doc.setFont('helvetica', 'bold');
        doc.setTextColor(37, 99, 235);
        doc.text('CANAL: TIENDA EN LÍNEA (ENVÍO)', 6, y); y += 4;
        doc.setFont('helvetica', 'normal');
        doc.setTextColor(71, 85, 105);
        if (ticketData.carrier) {
            doc.text('Paquetería: ' + ticketData.carrier + (ticketData.trackingNumber ? ' (' + ticketData.trackingNumber + ')' : ''), 6, y);
            y += 4;
        }
        if (ticketData.destination) {
            const splitDest = doc.splitTextToSize('Destino: ' + ticketData.destination, 68);
            splitDest.forEach(dl => { doc.text(dl, 6, y); y += 3.5; });
        }
    } else {
        doc.setFont('helvetica', 'bold');
        doc.setTextColor(16, 185, 129);
        doc.text('CANAL: TIENDA LOCAL (MOSTRADOR)', 6, y); y += 4;
        doc.setFont('helvetica', 'normal');
        doc.setTextColor(71, 85, 105);
        doc.text('Modalidad: Retiro en Mostrador', 6, y); y += 4;
    }

    doc.line(6, y, 74, y);
    y += 5;

    // Productos
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(8.5);
    doc.setTextColor(33, 37, 41);
    doc.text('Artículos', 6, y);
    y += 4.5;

    doc.setFont('helvetica', 'normal');
    doc.setTextColor(71, 85, 105);

    if (items.length > 0) {
        items.forEach((item, idx) => {
            const qty = Number(item.quantity || 0);
            const name = String(item.name || 'Producto');
            const code = String(item.sku || 'N/A');
            const unitPrice = Number(item.price ?? 0);
            const lineTotal = qty * unitPrice;

            doc.setFont('helvetica', 'bold');
            doc.setFontSize(8);
            doc.setTextColor(33, 37, 41);
            const splitName = doc.splitTextToSize(name, 68);
            splitName.forEach(line => {
                doc.text(line, 6, y);
                y += 3.5;
            });

            doc.setFont('helvetica', 'normal');
            doc.setFontSize(7.5);
            doc.setTextColor(100, 116, 139);
            doc.text('SKU: ' + code.replace(/^XLS-/i, ''), 6, y);
            y += 3.5;

            doc.setFontSize(8);
            doc.setTextColor(71, 85, 105);
            doc.text(qty + ' x ' + money(unitPrice), 6, y);
            doc.text(money(lineTotal), 74, y, { align: 'right' });
            y += 4.5;

            if (idx < (items.length - 1)) {
                doc.setDrawColor(241, 245, 249);
                doc.setLineWidth(0.2);
                doc.line(6, y - 1, 74, y - 1);
                y += 1.5;
            }
        });
    }

    doc.setDrawColor(203, 213, 225);
    doc.setLineWidth(0.3);
    doc.line(6, y, 74, y);
    y += 5;

    // Total
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(10.5);
    doc.setTextColor(255, 127, 0);
    doc.text('TOTAL: ' + money(ticketData.total), 74, y, { align: 'right' });
    y += 6;

    // Footer
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(7.5);
    doc.setTextColor(148, 163, 184);
    doc.text('¡Gracias por su compra en Ferretería FOX!', 40, y, { align: 'center' });

    doc.save('ticket-' + ticketData.folio + '.pdf');
}

<?php if ($autoPdf): ?>
document.addEventListener('DOMContentLoaded', function () {
    setTimeout(downloadClientTicketPdf, 300);
});
<?php endif; ?>
</script>
</body>
</html>
