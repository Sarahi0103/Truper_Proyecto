<?php
require_once __DIR__ . '/../config/config.php';

$quoteId = (int)($_GET['quote_id'] ?? 0);
$folio = trim((string)($_GET['folio'] ?? 'COT-000000'));
$issuedAt = trim((string)($_GET['issued_at'] ?? date('Y-m-d H:i')));
$issuedAtFromDbUtc = false;
$client = trim((string)($_GET['client'] ?? 'PUBLICO'));
$total = (float)($_GET['total'] ?? 0);
$format = (($_GET['format'] ?? 'thermal') === 'a4') ? 'a4' : 'thermal';
$autoPdf = isset($_GET['auto_pdf']) && $_GET['auto_pdf'] === '1';

$itemsRaw = (string)($_GET['items'] ?? '');
$itemsJson = '';
if ($itemsRaw !== '') {
    $decodedB64 = base64_decode(rawurldecode($itemsRaw), true);
    if ($decodedB64 !== false) {
        $itemsJson = $decodedB64;
    }
}

$items = json_decode($itemsJson, true);
if (!is_array($items)) {
    $items = [];
}

if ($quoteId > 0) {
    try {
        $stmtQuote = $pdo->prepare("SELECT user_id, quote_data, total_amount, created_at FROM whatsapp_quotes WHERE id = ? LIMIT 1");
        $stmtQuote->execute([$quoteId]);
        $rowQuote = $stmtQuote->fetch();
        if ($rowQuote) {
            $folio = $folio !== 'COT-000000' ? $folio : ('COT-' . str_pad((string)$quoteId, 6, '0', STR_PAD_LEFT));
            $issuedAt = !empty($rowQuote['created_at']) ? (string)$rowQuote['created_at'] : $issuedAt;
            $issuedAtFromDbUtc = !empty($rowQuote['created_at']);
            $total = (float)($rowQuote['total_amount'] ?? $total);

            $decodedQuoteItems = json_decode((string)($rowQuote['quote_data'] ?? '[]'), true);
            if (is_array($decodedQuoteItems) && !empty($decodedQuoteItems)) {
                $items = $decodedQuoteItems;
            }

            $userId = (int)($rowQuote['user_id'] ?? 0);
            $client = 'U' . str_pad((string)$userId, 5, '0', STR_PAD_LEFT);
            if ($userId > 0 && db_column_exists('users', 'user_code')) {
                $stmtUser = $pdo->prepare("SELECT COALESCE(user_code, '') AS user_code FROM users WHERE id = ? LIMIT 1");
                $stmtUser->execute([$userId]);
                $userRow = $stmtUser->fetch();
                if (!empty($userRow['user_code'])) {
                    $client = (string)$userRow['user_code'];
                }
            }
        }
    } catch (Exception $ignored) {
        // Keep fallback data from URL.
    }
} else if ($folio !== 'COT-000000' && $folio !== '') {
    // Intentar cargar desde sales_tickets por folio
    try {
        $stmtSt = $pdo->prepare("SELECT st.id, st.total_amount, st.issued_date, st.customer_name, u.user_code FROM sales_tickets st LEFT JOIN users u ON st.user_id = u.id WHERE st.folio = ? LIMIT 1");
        $stmtSt->execute([$folio]);
        $rowSt = $stmtSt->fetch();
        if ($rowSt) {
            $total = (float)$rowSt['total_amount'];
            $issuedAt = (string)$rowSt['issued_date'];
            $client = !empty($rowSt['user_code']) ? (string)$rowSt['user_code'] : (!empty($rowSt['customer_name']) ? (string)$rowSt['customer_name'] : 'PUBLICO');
            
            // Get items
            $stmtStItems = $pdo->prepare("SELECT ti.product_name AS name, ti.quantity, ti.unit_price AS price, ti.product_id, p.sku FROM ticket_items ti LEFT JOIN products p ON ti.product_id = p.id WHERE ti.ticket_id = ?");
            $stmtStItems->execute([$rowSt['id']]);
            $rawItems = $stmtStItems->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $items = array_map(function($i) {
                return [
                    'name' => $i['name'],
                    'quantity' => (int)$i['quantity'],
                    'price' => (float)$i['price'],
                    'product_id' => $i['product_id'],
                    'sku' => $i['sku'] ?? ''
                ];
            }, $rawItems);
        }
    } catch (Exception $ignored) {
        // Keep fallback data from URL.
    }
}

// Recalcular total si no se especificó o viene en 0 y hay items
if ($total <= 0 && !empty($items)) {
    $computedTotal = 0;
    foreach ($items as $it) {
        $computedTotal += (int)($it['quantity'] ?? 1) * (float)($it['price'] ?? ($it['unit_price'] ?? 0));
    }
    $total = $computedTotal;
}

function ticket_quote_number($value) {
    return number_format((float)$value, 2, '.', '');
}

function ticket_quote_datetime_display($value, $fromUtc = false) {
    $raw = trim((string)$value);
    if ($raw === '') {
        return date('Y-m-d H:i');
    }

    try {
        if ($fromUtc) {
            $dt = new DateTime($raw, new DateTimeZone('UTC'));
        } else {
            $dt = new DateTime($raw);
        }
        $dt->setTimezone(new DateTimeZone('America/Mexico_City'));
        return $dt->format('Y-m-d H:i');
    } catch (Exception $ignored) {
        return $raw;
    }
}

$issuedAt = ticket_quote_datetime_display($issuedAt, $issuedAtFromDbUtc);

function ticket_quote_product_code($item) {
    if (!is_array($item)) {
        return 'N/A';
    }

    $candidates = [
        $item['sku'] ?? '',
        $item['code'] ?? '',
        $item['product_code'] ?? ''
    ];
    foreach ($candidates as $candidate) {
        $value = trim((string)$candidate);
        if ($value !== '') {
            return preg_replace('/^XLS-/i', '', $value);
        }
    }

    $productId = (int)($item['product_id'] ?? 0);
    return $productId > 0 ? ('ID-' . $productId) : 'N/A';
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
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Ticket de cotización <?php echo htmlspecialchars($folio, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="css/theme.css?v=4.1">
    <link rel="stylesheet" href="css/responsive-complete.css?v=5.0">
    <style>
        body {
            margin: 0;
            padding: 16px;
            background: var(--ui-bg);
            color: var(--ui-text);
            font-family: Arial, Helvetica, sans-serif;
        }
        .ticket {
            width: min(100%, <?php echo $format === 'a4' ? '760px' : '340px'; ?>);
            margin: 0 auto;
            background: var(--ui-surface);
            border: 1px solid var(--ui-border);
            border-radius: 6px;
            padding: 14px;
            box-sizing: border-box;
        }
        h1 {
            text-align: left;
            font-size: 38px;
            margin: 0 0 12px;
            font-weight: 800;
            letter-spacing: 0.2px;
        }
        .line { border-top: 1px solid var(--ui-border); margin: 12px 0; }
        .row { margin-bottom: 6px; font-size: 34px; }
        .format-switch { text-align: center; margin-bottom: 10px; }
        .format-switch a { color: var(--theme-accent); }
        .section-title {
            font-size: 36px;
            font-weight: 700;
            margin: 4px 0 8px;
        }
        .item-name { font-size: 34px; margin-bottom: 2px; }
        .item-code { font-size: 28px; color: var(--ui-text-muted); margin-bottom: 2px; }
        .item-line { display: flex; justify-content: space-between; gap: 10px; font-size: 33px; }
        .item-separator { border-top: 1px solid #dfdfdf; margin: 8px 0 10px; }
        .total-row { text-align: right; font-size: 40px; font-weight: 800; margin-top: 8px; }
        .thanks { margin-top: 12px; font-size: 33px; }
        @media (max-width: 768px) {
            body { padding: 10px; }
            h1 { font-size: 28px; }
            .row { font-size: 20px; }
            .section-title { font-size: 22px; }
            .item-name { font-size: 20px; }
            .item-code { font-size: 16px; }
            .item-line { font-size: 18px; }
            .total-row { font-size: 24px; }
            .thanks { font-size: 18px; }
        }
        @media print {
            .format-switch, .theme-toggle { display: none; }
            html, body { background: #fff !important; color: #000 !important; padding: 0; }
            .ticket { border: 1px solid #000 !important; border-radius: 0; width: 100%; background: #fff !important; color: #000 !important; }
            .line, .item-separator { border-top-color: #000 !important; }
            .item-code { color: #000 !important; }
        }
    </style>
    <script src="/js/jspdf.umd.min.js"></script>
</head>
<body>
    <div class="format-switch">
        <a href="#" onclick="window.print(); return false;">🖨️ Imprimir</a>
        |
        <a href="#" onclick="downloadTicketPdf(); return false;">📥 Descargar PDF</a>
        |
        <a href="/api/quotes_pdf.php?folio=<?php echo urlencode($folio); ?>" target="_blank" style="color: #ff6600; font-weight: 700;">📄 Cotización Formal</a>
        |
        <a href="https://wa.me/523312482297?text=<?php echo urlencode('Hola Ferretería FOX, adjunto mi cotización/ticket folio: ' . $folio); ?>" target="_blank" style="color: #25D366; font-weight: 700;">📱 WhatsApp</a>
    </div>
    <div class="ticket">
        <h1>FERRETERÍA FOX - TICKET</h1>
    <div class="row"><strong>Folio:</strong> <?php echo htmlspecialchars($folio, ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="row"><strong>Fecha:</strong> <?php echo htmlspecialchars($issuedAt, ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="row"><strong>Cliente:</strong> <?php echo htmlspecialchars($client, ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="line"></div>
    <div class="section-title">Detalle de productos</div>

    <?php if (!empty($items)): ?>
        <?php foreach ($items as $idx => $item): ?>
            <?php
                $qty = (int)($item['quantity'] ?? 0);
                $name = (string)($item['name'] ?? 'Producto');
                $price = (float)($item['price'] ?? ($item['unit_price'] ?? 0));
                $code = ticket_quote_product_code($item);
                $lineTotal = $qty * $price;
            ?>
            <div class="item-name"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="item-code">Código: <?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="item-line">
                <span><?php echo $qty; ?> x $<?php echo ticket_quote_number($price); ?></span>
                <span>$<?php echo ticket_quote_number($lineTotal); ?></span>
            </div>
            <?php if ($idx < (count($items) - 1)): ?>
                <div class="item-separator"></div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="line"></div>
    <div class="total-row">Total: $<?php echo ticket_quote_number($total); ?></div>
    <div class="thanks">Gracias por su compra</div>
</div>
<script src="js/main.js?v=2.6"></script>
<script>
const ticketData = {
    folio: <?php echo json_encode($folio); ?>,
    issuedAt: <?php echo json_encode($issuedAt); ?>,
    client: <?php echo json_encode($client); ?>,
    total: <?php echo json_encode((float)$total); ?>,
    items: <?php echo json_encode($items, JSON_UNESCAPED_UNICODE); ?>
};

function money(value) {
    return '$' + Number(value || 0).toFixed(2);
}

function downloadTicketPdf() {
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
        doc.addImage(logoBase64, 'PNG', 6, y, 12, 14);
    }

    // Header text beside logo
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(11);
    doc.setTextColor(33, 37, 41);
    doc.text('FERRETERÍA FOX', 20, y + 4);
    
    doc.setFontSize(9);
    doc.text('TICKET DE COTIZACIÓN', 20, y + 8);
    
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
            const code = String(item.sku || item.code || item.product_code || ((item.product_id || 0) ? ('ID-' + item.product_id) : 'N/A'));
            const unitPrice = Number(item.price ?? item.unit_price ?? 0);
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
    setTimeout(downloadTicketPdf, 300);
});
<?php endif; ?>
</script>
    <script src="js/mobile-optimize.js"></script>
</body>
</html>
