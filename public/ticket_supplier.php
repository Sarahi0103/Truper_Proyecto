<?php
require_once '../config/config.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$format = ($_GET['format'] ?? 'thermal') === 'a4' ? 'a4' : 'thermal';
if ($id <= 0) {
    http_response_code(400);
    echo 'ID de ticket invalido';
    exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS supplier_orders (
    id SERIAL PRIMARY KEY,
    folio VARCHAR(50) UNIQUE NOT NULL,
    supplier_name VARCHAR(180) NOT NULL,
    expected_date DATE NOT NULL,
    items_json TEXT NOT NULL,
    total_estimated DECIMAL(12, 2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'pending',
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$stmt = $pdo->prepare('SELECT * FROM supplier_orders WHERE id = ?');
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) {
    http_response_code(404);
    echo 'Ticket no encontrado';
    exit;
}

$items = json_decode($order['items_json'] ?? '[]', true);
if (!is_array($items)) {
    $items = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket proveedor <?php echo htmlspecialchars($order['folio'], ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        body { font-family: monospace; margin: 0; padding: 10px; }
        .ticket { width: <?php echo $format === 'a4' ? '760px' : '300px'; ?>; margin: 0 auto; }
        h1 { text-align: center; font-size: 18px; margin: 0 0 8px; }
        .line { border-top: 1px dashed #000; margin: 8px 0; }
        .row { margin-bottom: 6px; }
        .format-switch { text-align: center; margin-bottom: 8px; }
        @media print { .format-switch { display: none; } }
    </style>
</head>
<body onload="window.print()">
<div class="ticket">
    <div class="format-switch">
        <a href="/ticket_supplier.php?id=<?php echo $id; ?>&format=thermal">Térmico</a> |
        <a href="/ticket_supplier.php?id=<?php echo $id; ?>&format=a4">A4</a>
    </div>
    <h1>ORDEN A PROVEEDOR</h1>
    <div class="row"><strong>Folio:</strong> <?php echo htmlspecialchars($order['folio'], ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="row"><strong>Proveedor:</strong> <?php echo htmlspecialchars($order['supplier_name'], ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="row"><strong>Fecha recepción:</strong> <?php echo htmlspecialchars($order['expected_date'], ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="row"><strong>Creación:</strong> <?php echo htmlspecialchars($order['created_at'], ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="line"></div>
    <?php foreach ($items as $it): ?>
        <div class="row">SKU: <?php echo htmlspecialchars($it['sku'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="row">Cant: <?php echo (int)($it['quantity'] ?? 0); ?> | Costo: $<?php echo number_format((float)($it['estimated_cost'] ?? 0), 2, '.', ''); ?></div>
        <div class="line"></div>
    <?php endforeach; ?>
    <div class="row"><strong>Total estimado: $<?php echo number_format((float)$order['total_estimated'], 2, '.', ''); ?></strong></div>
</div>
</body>
</html>
