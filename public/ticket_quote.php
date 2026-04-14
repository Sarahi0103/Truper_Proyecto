<?php
require_once '../config/config.php';

$quoteId = (int)($_GET['quote_id'] ?? 0);
$folio = trim((string)($_GET['folio'] ?? 'COT-000000'));
$issuedAt = trim((string)($_GET['issued_at'] ?? date('Y-m-d H:i')));
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
}

function ticket_quote_number($value) {
    return number_format((float)$value, 2, '.', '');
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket de cotización <?php echo htmlspecialchars($folio, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        body { font-family: monospace; margin: 0; padding: 10px; background: #fff; color: #111; }
        .ticket { width: <?php echo $format === 'a4' ? '760px' : '300px'; ?>; margin: 0 auto; }
        h1 { text-align: center; font-size: 18px; margin: 0 0 8px; }
        .line { border-top: 1px dashed #000; margin: 8px 0; }
        .row { margin-bottom: 5px; }
        .format-switch { text-align: center; margin-bottom: 8px; }
        .total { font-size: 16px; }
        @media print { .format-switch { display: none; } }
    </style>
    <script src="/js/jspdf.umd.min.js"></script>
</head>
<body>
<div class="ticket">
    <div class="format-switch">
        <a href="/ticket_quote.php?<?php echo http_build_query(['quote_id' => $quoteId, 'folio' => $folio, 'issued_at' => $issuedAt, 'client' => $client, 'total' => ticket_quote_number($total), 'items' => rawurlencode(base64_encode(json_encode($items, JSON_UNESCAPED_UNICODE))), 'format' => 'thermal', 'auto_pdf' => $autoPdf ? '1' : '0']); ?>">Térmico</a> |
        <a href="/ticket_quote.php?<?php echo http_build_query(['quote_id' => $quoteId, 'folio' => $folio, 'issued_at' => $issuedAt, 'client' => $client, 'total' => ticket_quote_number($total), 'items' => rawurlencode(base64_encode(json_encode($items, JSON_UNESCAPED_UNICODE))), 'format' => 'a4', 'auto_pdf' => $autoPdf ? '1' : '0']); ?>">A4</a> |
        <a href="#" onclick="window.print(); return false;">Imprimir</a>
        |
        <a href="#" onclick="downloadTicketPdf(); return false;">Descargar PDF</a>
    </div>
    <h1>TICKET COTIZACION</h1>
    <div class="row"><strong>Folio:</strong> <?php echo htmlspecialchars($folio, ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="row"><strong>Fecha:</strong> <?php echo htmlspecialchars($issuedAt, ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="row"><strong>Cliente:</strong> <?php echo htmlspecialchars($client, ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="line"></div>

    <?php if (empty($items)): ?>
        <div class="row">Sin partidas en este ticket.</div>
    <?php else: ?>
        <?php foreach ($items as $item): ?>
            <?php
                $qty = (int)($item['quantity'] ?? 0);
                $name = (string)($item['name'] ?? 'Producto');
                $price = (float)($item['price'] ?? ($item['unit_price'] ?? 0));
                $lineTotal = $qty * $price;
            ?>
            <div class="row"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="row"><?php echo $qty; ?> x $<?php echo ticket_quote_number($price); ?> = $<?php echo ticket_quote_number($lineTotal); ?></div>
            <div class="line"></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="row total"><strong>Total: $<?php echo ticket_quote_number($total); ?></strong></div>
</div>
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
    const doc = new jsPDF({ unit: 'mm', format: [80, 180] });
    let y = 8;

    doc.setFont('courier', 'bold');
    doc.setFontSize(12);
    doc.text('TICKET COTIZACION', 40, y, { align: 'center' });
    y += 7;
    doc.setFont('courier', 'normal');
    doc.setFontSize(9);
    doc.text('Folio: ' + ticketData.folio, 6, y);
    y += 5;
    doc.text('Fecha: ' + ticketData.issuedAt, 6, y);
    y += 5;
    doc.text('Cliente: ' + ticketData.client, 6, y);
    y += 4;
    doc.line(6, y, 74, y);
    y += 5;

    const items = Array.isArray(ticketData.items) ? ticketData.items : [];
    if (items.length === 0) {
        doc.text('Sin partidas en este ticket.', 6, y);
        y += 6;
    } else {
        items.forEach((item) => {
            const qty = Number(item.quantity || 0);
            const name = String(item.name || 'Producto');
            const unitPrice = Number(item.price ?? item.unit_price ?? 0);
            const lineTotal = qty * unitPrice;

            const productLine = name.length > 36 ? name.slice(0, 36) + '...' : name;
            doc.text(productLine, 6, y);
            y += 4;
            doc.text(qty + ' x ' + money(unitPrice) + ' = ' + money(lineTotal), 6, y);
            y += 5;
        });
    }

    doc.line(6, y, 74, y);
    y += 6;
    doc.setFont('courier', 'bold');
    doc.setFontSize(11);
    doc.text('TOTAL: ' + money(ticketData.total), 74, y, { align: 'right' });

    doc.save('ticket-' + ticketData.folio + '.pdf');
}

<?php if ($autoPdf): ?>
document.addEventListener('DOMContentLoaded', function () {
    setTimeout(downloadTicketPdf, 300);
});
<?php endif; ?>
</script>
</body>
</html>
