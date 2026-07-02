<?php
require_once '../config/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$format = ($_GET['format'] ?? 'thermal') === 'a4' ? 'a4' : 'thermal';
$autoPdf = isset($_GET['auto_pdf']) && $_GET['auto_pdf'] === '1';
if ($id <= 0) {
    http_response_code(400);
    echo 'ID de ticket invalido';
    exit;
}

$userCodeSelect = db_column_exists('users', 'user_code') ? 'COALESCE(u.user_code, \'\') AS user_code' : "'' AS user_code";
$sql = "SELECT o.*, c.user_id, u.first_name, u.last_name, {$userCodeSelect}
    FROM orders o
    JOIN clients c ON c.id = o.client_id
    JOIN users u ON u.id = c.user_id
    WHERE o.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    echo 'Ticket no encontrado';
    exit;
}

if (($_SESSION['role'] ?? '') !== 'admin' && ($_SESSION['role'] ?? '') !== 'employee' && (int)$order['user_id'] !== (int)$_SESSION['user_id']) {
    http_response_code(403);
    echo 'No autorizado';
    exit;
}

$itemsStmt = $pdo->prepare("SELECT oi.quantity, oi.unit_price, oi.line_total, p.name, p.sku FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?");
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();

function display_product_code($sku) {
    return preg_replace('/^XLS-/i', '', (string)$sku);
}

// Convertir truper_logo2.png a base64
$logoPath = __DIR__ . '/truper_logo2.png';
if (!file_exists($logoPath)) {
    $logoPath = __DIR__ . '/../truper_logo2.png';
}
$logoBase64 = '';
if (file_exists($logoPath)) {
    $logoData = file_get_contents($logoPath);
    $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <script src="/js/jspdf.umd.min.js"></script>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket cliente <?php echo htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="css/theme.css?v=4.1">
    <link rel="stylesheet" href="css/responsive-complete.css?v=5.0">
    <style>
        body { font-family: monospace; margin: 0; padding: 10px; background: var(--ui-bg); color: var(--ui-text); }
        .ticket { width: min(100%, <?php echo $format === 'a4' ? '760px' : '300px'; ?>); margin: 0 auto; background: var(--ui-surface); border: 1px solid var(--ui-border); border-radius: 6px; padding: 10px; box-sizing: border-box; }
        h1 { text-align: center; font-size: 18px; margin: 0 0 8px; }
        .line { border-top: 1px dashed var(--ui-border); margin: 8px 0; }
        .row { margin-bottom: 5px; }
        .format-switch { text-align: center; margin-bottom: 8px; }
        .format-switch a { color: var(--theme-accent); }
        
        /* Timeline styles */
        .order-timeline {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 20px 0;
            padding: 10px 0;
            position: relative;
        }
        .order-timeline::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--ui-border);
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
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--ui-surface-soft);
            border: 2px solid var(--ui-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            color: var(--ui-text-muted);
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .timeline-step.active .timeline-icon {
            background: var(--color-naranja, #ff6600);
            border-color: var(--color-naranja, #ff6600);
            color: #fff;
            box-shadow: 0 0 10px rgba(255,102,0,0.5);
        }
        .timeline-label {
            font-size: 9px;
            font-weight: bold;
            margin-top: 6px;
            color: var(--ui-text-muted);
            text-align: center;
            text-transform: uppercase;
        }
        .timeline-step.active .timeline-label {
            color: var(--color-naranja, #ff6600);
        }

        @media print {
            html, body { background: #fff !important; color: #000 !important; }
            .ticket { background: #fff !important; color: #000 !important; border: 1px solid #000 !important; }
            .line { border-top-color: #000 !important; }
            .format-switch, .theme-toggle, .order-timeline { display: none !important; }
        }
    </style>
</head>
<body <?php echo !$autoPdf ? 'onload="window.print()"' : ''; ?>>
<div class="ticket">
    <div class="format-switch">
        <a href="/ticket_client.php?id=<?php echo $id; ?>&format=thermal">Térmico</a> |
        <a href="/ticket_client.php?id=<?php echo $id; ?>&format=a4">A4</a> |
        <a href="#" onclick="window.print(); return false;">Imprimir</a> |
        <a href="#" onclick="downloadClientTicketPdf(); return false;">Descargar PDF</a>
    </div>
    <h1>TICKET CLIENTE</h1>
    
    <?php
    $status = strtolower($order['status'] ?? 'pending');
    $step1 = true; // Recibido
    $step2 = in_array($status, ['confirmed', 'processing', 'shipped', 'delivered']);
    $step3 = in_array($status, ['shipped', 'delivered']);
    $step4 = ($status === 'delivered');
    
    $progressPct = 0;
    if ($step4) $progressPct = 100;
    elseif ($step3) $progressPct = 66.6;
    elseif ($step2) $progressPct = 33.3;
    ?>
    <div class="order-timeline">
        <div class="timeline-progress-line" style="width: <?php echo $progressPct; ?>%;"></div>
        <div class="timeline-step <?php echo $step1 ? 'active' : ''; ?>">
            <div class="timeline-icon">📝</div>
            <div class="timeline-label">Recibido</div>
        </div>
        <div class="timeline-step <?php echo $step2 ? 'active' : ''; ?>">
            <div class="timeline-icon">🔧</div>
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
    </div>

    <div class="row"><strong>Folio:</strong> <?php echo htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="row"><strong>Fecha:</strong> <?php echo htmlspecialchars($order['created_at'] ?? $order['order_date'], ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="row"><strong>Cliente:</strong> <?php echo htmlspecialchars(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="row"><strong>Código cliente:</strong> <?php echo htmlspecialchars(($order['user_code'] ?? '') !== '' ? $order['user_code'] : 'N/A', ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="line"></div>
    <?php foreach ($items as $it): ?>
        <div class="row"><?php echo htmlspecialchars($it['name'], ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="row">SKU: <?php echo htmlspecialchars(display_product_code($it['sku']), ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="row"><?php echo (int)$it['quantity']; ?> x $<?php echo number_format((float)$it['unit_price'], 2, '.', ''); ?> = $<?php echo number_format((float)$it['line_total'], 2, '.', ''); ?></div>
        <div class="line"></div>
    <?php endforeach; ?>
    <div class="row"><strong>Total: $<?php echo number_format((float)$order['total_amount'], 2, '.', ''); ?></strong></div>
</div>
<script src="js/main.js?v=2.6"></script>
<script>
const ticketData = {
    folio: <?php echo json_encode($order['order_number']); ?>,
    issuedAt: <?php echo json_encode($order['created_at'] ?? $order['order_date']); ?>,
    client: <?php echo json_encode(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')); ?>,
    clientCode: <?php echo json_encode(($order['user_code'] ?? '') !== '' ? $order['user_code'] : 'N/A'); ?>,
    total: <?php echo json_encode((float)$order['total_amount']); ?>,
    items: <?php echo json_encode(array_map(function($it) {
        return [
            'name' => $it['name'],
            'sku' => display_product_code($it['sku']),
            'quantity' => (int)$it['quantity'],
            'price' => (float)$it['unit_price'],
            'line_total' => (float)$it['line_total']
        ];
    }, $items), JSON_UNESCAPED_UNICODE); ?>
};

function money(value) {
    return '$' + Number(value || 0).toFixed(2);
}

function downloadClientTicketPdf() {
    if (!window.jspdf || !window.jspdf.jsPDF) {
        return;
    }

    const { jsPDF } = window.jspdf;
    
    // Base64 logo from PHP
    const logoBase64 = <?php echo json_encode($logoBase64); ?>;
    
    // Dynamic height calculation
    const items = Array.isArray(ticketData.items) ? ticketData.items : [];
    let dynamicHeight = 75; // Base height for header, meta, totals, margins
    items.forEach(item => {
        const nameLength = String(item.name || '').length;
        const nameLines = Math.ceil(nameLength / 32);
        dynamicHeight += (nameLines * 4.5) + 8;
    });
    dynamicHeight = Math.max(120, Math.round(dynamicHeight));

    const doc = new jsPDF({ unit: 'mm', format: [80, dynamicHeight] });
    let y = 6;

    // Logo on top-left
    if (logoBase64) {
        doc.addImage(logoBase64, 'JPEG', 6, y, 12, 14);
    }

    // Header text beside logo
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(11);
    doc.setTextColor(33, 37, 41);
    doc.text('FERRETERÍA FOX', 20, y + 4);
    
    doc.setFontSize(9);
    doc.text('TICKET CLIENTE', 20, y + 8);
    
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(7.5);
    doc.setTextColor(100, 116, 139);
    doc.text('Folio: ' + ticketData.folio, 20, y + 12);
    
    y += 16;
    doc.setDrawColor(203, 213, 225);
    doc.setLineWidth(0.3);
    doc.line(6, y, 74, y);
    y += 5;

    // Meta info
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(8.5);
    doc.setTextColor(71, 85, 105);
    doc.text('Fecha: ' + ticketData.issuedAt, 6, y);
    y += 4.5;
    doc.text('Cliente: ' + ticketData.client, 6, y);
    y += 4.5;
    doc.text('Código cliente: ' + ticketData.clientCode, 6, y);
    y += 4.5;
    
    doc.line(6, y, 74, y);
    y += 5;

    // Title
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(9);
    doc.setTextColor(33, 37, 41);
    doc.text('Detalle de productos', 6, y);
    y += 5;
    
    // Items
    doc.setFont('helvetica', 'normal');
    doc.setTextColor(71, 85, 105);

    if (items.length > 0) {
        items.forEach((item, idx) => {
            const qty = Number(item.quantity || 0);
            const name = String(item.name || 'Producto');
            const code = String(item.sku || 'N/A');
            const unitPrice = Number(item.price ?? 0);
            const lineTotal = qty * unitPrice;

            // Handle name wrapping properly
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(8.5);
            doc.setTextColor(33, 37, 41);
            
            const splitName = doc.splitTextToSize(name, 68);
            splitName.forEach(line => {
                doc.text(line, 6, y);
                y += 4;
            });
            
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(8);
            doc.setTextColor(100, 116, 139);
            doc.text('Código: ' + code.replace(/^XLS-/i, ''), 6, y);
            y += 4;
            
            doc.setFontSize(8.5);
            doc.setTextColor(71, 85, 105);
            doc.text(qty + ' x ' + money(unitPrice), 6, y);
            doc.text(money(lineTotal), 74, y, { align: 'right' });
            y += 5;

            if (idx < (items.length - 1)) {
                doc.setDrawColor(241, 245, 249);
                doc.setLineWidth(0.2);
                doc.line(6, y - 1, 74, y - 1);
                y += 2;
            }
        });
    }

    doc.setDrawColor(203, 213, 225);
    doc.setLineWidth(0.3);
    doc.line(6, y, 74, y);
    y += 6;

    // Totals
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(11);
    doc.setTextColor(255, 102, 0);
    doc.text('TOTAL: ' + money(ticketData.total), 74, y, { align: 'right' });
    y += 7;
    
    // Footer
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(8.5);
    doc.setTextColor(148, 163, 184);
    doc.text('Gracias por su compra', 40, y, { align: 'center' });

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
