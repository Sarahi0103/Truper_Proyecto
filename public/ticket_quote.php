<?php
require_once '../config/config.php';

$folio = trim((string)($_GET['folio'] ?? 'COT-000000'));
$issuedAt = trim((string)($_GET['issued_at'] ?? date('Y-m-d H:i')));
$client = trim((string)($_GET['client'] ?? 'PUBLICO'));
$total = (float)($_GET['total'] ?? 0);
$format = (($_GET['format'] ?? 'thermal') === 'a4') ? 'a4' : 'thermal';

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
</head>
<body>
<div class="ticket">
    <div class="format-switch">
        <a href="/ticket_quote.php?<?php echo http_build_query(['folio' => $folio, 'issued_at' => $issuedAt, 'client' => $client, 'total' => ticket_quote_number($total), 'items' => rawurlencode(base64_encode(json_encode($items, JSON_UNESCAPED_UNICODE))), 'format' => 'thermal']); ?>">Térmico</a> |
        <a href="/ticket_quote.php?<?php echo http_build_query(['folio' => $folio, 'issued_at' => $issuedAt, 'client' => $client, 'total' => ticket_quote_number($total), 'items' => rawurlencode(base64_encode(json_encode($items, JSON_UNESCAPED_UNICODE))), 'format' => 'a4']); ?>">A4</a> |
        <a href="#" onclick="window.print(); return false;">Imprimir</a>
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
</body>
</html>
