<?php
require_once '../config/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$format = ($_GET['format'] ?? 'thermal') === 'a4' ? 'a4' : 'thermal';
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

if (($_SESSION['role'] ?? '') !== 'admin' && (int)$order['user_id'] !== (int)$_SESSION['user_id']) {
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket cliente <?php echo htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        body { font-family: monospace; margin: 0; padding: 10px; }
        .ticket { width: <?php echo $format === 'a4' ? '760px' : '300px'; ?>; margin: 0 auto; }
        h1 { text-align: center; font-size: 18px; margin: 0 0 8px; }
        .line { border-top: 1px dashed #000; margin: 8px 0; }
        .row { margin-bottom: 5px; }
        .format-switch { text-align: center; margin-bottom: 8px; }
        @media print { .format-switch { display: none; } }
    </style>
</head>
<body onload="window.print()">
<div class="ticket">
    <div class="format-switch">
        <a href="/ticket_client.php?id=<?php echo $id; ?>&format=thermal">Térmico</a> |
        <a href="/ticket_client.php?id=<?php echo $id; ?>&format=a4">A4</a>
    </div>
    <h1>TICKET CLIENTE</h1>
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
</body>
</html>
