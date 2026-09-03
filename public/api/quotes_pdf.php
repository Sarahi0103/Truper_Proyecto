<?php
/**
 * API para Generación y Descarga de Cotizaciones Formales
 * Endpoint: /api/quotes_pdf.php
 * Truper Platform
 */

require_once __DIR__ . '/../../config/config.php';

$quoteFolio = sanitize($_GET['folio'] ?? ($_GET['ticket'] ?? ''));
$ticketId = (int)($_GET['ticket_id'] ?? 0);

if (empty($quoteFolio) && $ticketId <= 0) {
    die("Error: Folio o ID de cotización no especificado.");
}

// Buscar datos del ticket o cotización
$stmt = $pdo->prepare("
    SELECT st.*, u.first_name, u.last_name, u.email, u.phone
    FROM sales_tickets st
    LEFT JOIN users u ON st.user_id = u.id
    WHERE (st.ticket_number = ? OR st.id = ?)
    LIMIT 1
");
$stmt->execute([$quoteFolio, $ticketId]);
$quote = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quote) {
    die("Error: Cotización o Ticket no encontrado.");
}

// Obtener items
$stmtItems = $pdo->prepare("
    SELECT sti.*, p.name AS product_name, p.sku AS product_sku, p.image_url
    FROM sales_ticket_items sti
    LEFT JOIN products p ON sti.product_id = p.id
    WHERE sti.sales_ticket_id = ?
");
$stmtItems->execute([$quote['id']]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

$companyName = "Ferretería FOX / Distribuidor Autorizado Truper";
$companyPhone = "+52 33 1248 2297";
$companyEmail = "ventas@truperfox.com";
$subtotal = (float)$quote['total_amount'] / 1.16;
$iva = (float)$quote['total_amount'] - $subtotal;
$total = (float)$quote['total_amount'];
$validDays = 15;
$validUntil = date('d/m/Y', strtotime($quote['created_at'] . " + {$validDays} days"));

// Si se solicita formato JSON
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'quote' => $quote,
        'items' => $items,
        'summary' => [
            'subtotal' => round($subtotal, 2),
            'iva' => round($iva, 2),
            'total' => round($total, 2),
            'valid_until' => $validUntil
        ]
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cotización Formal - <?= htmlspecialchars($quote['ticket_number']) ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Outfit', sans-serif; }
        body { background: #f4f6f9; color: #1e1e1e; padding: 30px; }
        .quote-sheet { max-width: 850px; margin: 0 auto; background: #fff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); padding: 40px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #ff6600; padding-bottom: 20px; margin-bottom: 25px; }
        .logo-box h1 { color: #ff6600; font-size: 26px; font-weight: 800; letter-spacing: -0.5px; }
        .logo-box p { color: #666; font-size: 13px; margin-top: 4px; }
        .quote-info { text-align: right; }
        .quote-badge { background: #fff3e6; color: #ff6600; font-weight: 700; padding: 6px 14px; border-radius: 20px; font-size: 14px; display: inline-block; margin-bottom: 8px; }
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; background: #fbfbfb; border: 1px solid #eee; border-radius: 8px; padding: 18px; margin-bottom: 25px; font-size: 14px; }
        .meta-grid h4 { color: #ff6600; margin-bottom: 6px; font-size: 13px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        th { background: #1e1e1e; color: #fff; text-align: left; padding: 12px; font-size: 13px; text-transform: uppercase; }
        td { padding: 12px; border-bottom: 1px solid #eee; font-size: 14px; }
        .totals-box { display: flex; justify-content: flex-end; margin-bottom: 30px; }
        .totals-table { width: 320px; }
        .totals-table tr td:first-child { font-weight: 600; color: #666; text-align: right; padding-right: 15px; }
        .totals-table tr td:last-child { font-weight: 700; text-align: right; }
        .total-row td { font-size: 18px; color: #ff6600; border-top: 2px solid #ff6600; }
        .footer-terms { background: #fdfaf6; border-left: 4px solid #ff6600; padding: 15px; font-size: 12px; color: #555; line-height: 1.6; border-radius: 0 8px 8px 0; }
        .action-bar { margin-top: 25px; display: flex; gap: 12px; justify-content: center; }
        .btn { padding: 12px 24px; border-radius: 8px; border: none; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: #ff6600; color: #fff; }
        .btn-whatsapp { background: #25D366; color: #fff; }
        @media print {
            body { background: #fff; padding: 0; }
            .quote-sheet { box-shadow: none; padding: 0; }
            .action-bar { display: none; }
        }
    </style>
</head>
<body>

<div class="quote-sheet">
    <div class="header">
        <div class="logo-box">
            <h1><?= htmlspecialchars($companyName) ?></h1>
            <p>Distribuidores Autorizados - Catálogo Oficial Truper y Líneas Industriales</p>
            <p>Tel: <?= htmlspecialchars($companyPhone) ?> | Email: <?= htmlspecialchars($companyEmail) ?></p>
        </div>
        <div class="quote-info">
            <div class="quote-badge">COTIZACIÓN FORMAL</div>
            <p><strong>Folio:</strong> <?= htmlspecialchars($quote['ticket_number']) ?></p>
            <p><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($quote['created_at'])) ?></p>
            <p><strong>Vigencia hasta:</strong> <?= htmlspecialchars($validUntil) ?></p>
        </div>
    </div>

    <div class="meta-grid">
        <div>
            <h4>Datos del Cliente</h4>
            <p><strong>Nombre / Razón Social:</strong> <?= htmlspecialchars(($quote['first_name'] ?? 'Cliente') . ' ' . ($quote['last_name'] ?? 'Invitado')) ?></p>
            <p><strong>Teléfono:</strong> <?= htmlspecialchars($quote['phone'] ?? 'No especificado') ?></p>
            <p><strong>Correo:</strong> <?= htmlspecialchars($quote['email'] ?? 'No especificado') ?></p>
        </div>
        <div>
            <h4>Condiciones Comerciales</h4>
            <p><strong>Forma de Entrega:</strong> Entrega a Domicilio / Ocurre en Sucursal</p>
            <p><strong>Moneda:</strong> Pesos Mexicanos (MXN)</p>
            <p><strong>Estado:</strong> <?= strtoupper(htmlspecialchars($quote['pickup_status'] ?? 'Pendiente')) ?></p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>SKU</th>
                <th>Descripción del Producto</th>
                <th style="text-align:center;">Cant.</th>
                <th style="text-align:right;">P. Unitario</th>
                <th style="text-align:right;">Importe</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><strong><?= htmlspecialchars($item['product_sku'] ?? 'N/A') ?></strong></td>
                <td><?= htmlspecialchars($item['product_name'] ?? 'Producto Truper') ?></td>
                <td style="text-align:center;"><?= (int)$item['quantity'] ?></td>
                <td style="text-align:right;">$<?= number_format((float)$item['unit_price'], 2) ?></td>
                <td style="text-align:right;">$<?= number_format((float)$item['subtotal'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totals-box">
        <table class="totals-table">
            <tr>
                <td>Subtotal (sin IVA):</td>
                <td>$<?= number_format($subtotal, 2) ?></td>
            </tr>
            <tr>
                <td>IVA (16%):</td>
                <td>$<?= number_format($iva, 2) ?></td>
            </tr>
            <tr class="total-row">
                <td>Total Cotizado:</td>
                <td>$<?= number_format($total, 2) ?> MXN</td>
            </tr>
        </table>
    </div>

    <div class="footer-terms">
        <strong>Términos y Condiciones:</strong>
        <p>1. Los precios mostrados incluyen IVA y están sujetos a disponibilidad física al momento de la confirmación.</p>
        <p>2. Esta cotización tiene una vigencia de 15 días naturales a partir de la fecha de emisión.</p>
        <p>3. Para confirmar su pedido, presente este folio en caja o contáctenos vía WhatsApp.</p>
    </div>

    <div class="action-bar">
        <button class="btn btn-primary" onclick="window.print();">🖨️ Imprimir / Guardar en PDF</button>
        <?php 
            $waMsg = urlencode("Hola Ferretería Fox, deseo confirmar mi pedido con Folio de Cotización: " . $quote['ticket_number'] . " por un total de $" . number_format($total, 2) . " MXN.");
            $waUrl = "https://wa.me/523312482297?text=" . $waMsg;
        ?>
        <a class="btn btn-whatsapp" href="<?= $waUrl ?>" target="_blank">📱 Enviar a WhatsApp</a>
    </div>
</div>

</body>
</html>
