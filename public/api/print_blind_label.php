<?php
/**
 * Generador de Etiqueta Ciega de Seguridad de Empaque (4x6 pulgadas)
 * Privacidad Absoluta de Datos y Precios
 * Truper Platform
 */
require_once '../../config/config.php';

if (PHP_SAPI !== 'cli') {
    require_admin();
}

$folio = sanitize($_GET['folio'] ?? '');
if (empty($folio)) {
    die('Folio de pedido requerido para generar la etiqueta ciega.');
}

$stmt = $pdo->prepare("SELECT st.id, st.folio, st.issued_date, st.customer_name, st.shipping_address_json FROM sales_tickets st WHERE st.folio = ? LIMIT 1");
$stmt->execute([$folio]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die('Pedido no encontrado.');
}

$addressData = json_decode($order['shipping_address_json'] ?? '[]', true) ?: [];
$destCity = $addressData['city'] ?? 'MEXICO';
$destZip = $addressData['postalCode'] ?? '44100';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Etiqueta Ciega - Folio <?php echo htmlspecialchars($order['folio']); ?></title>
    <style>
        @page { size: 4in 6in; margin: 0; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; margin: 0; padding: 15px; width: 4in; height: 6in; box-sizing: border-box; background: #fff; color: #000; display: flex; flex-direction: column; justify-content: space-between; }
        .label-border { border: 3px solid #000; padding: 12px; height: 100%; box-sizing: border-box; display: flex; flex-direction: column; justify-content: space-between; border-radius: 8px; }
        .label-header { border-bottom: 2px solid #000; padding-bottom: 8px; text-align: center; }
        .brand { font-size: 16px; font-weight: 900; letter-spacing: 1px; text-transform: uppercase; }
        .privacy-badge { background: #000; color: #fff; font-size: 9px; padding: 3px 6px; font-weight: bold; margin-top: 4px; display: inline-block; border-radius: 4px; }
        .barcode-section { text-align: center; margin: 15px 0; border-top: 1px dashed #000; border-bottom: 1px dashed #000; padding: 15px 0; }
        .folio-large { font-size: 24px; font-weight: 900; letter-spacing: 2px; margin-bottom: 8px; font-family: monospace; }
        .barcode-box { font-family: 'Libre Barcode 128', 'Code 128', monospace; font-size: 42px; line-height: 1; letter-spacing: 4px; }
        .dest-section { font-size: 12px; }
        .dest-title { font-weight: bold; font-size: 11px; text-transform: uppercase; border-bottom: 1px solid #000; padding-bottom: 2px; margin-bottom: 6px; }
        .dest-details { font-size: 13px; line-height: 1.3; }
        .no-data-notice { font-size: 8px; text-align: center; font-style: italic; margin-top: 8px; border-top: 1px solid #ccc; padding-top: 4px; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body onload="window.print()">
    <div class="no-print" style="margin-bottom: 10px; text-align: right;">
        <button onclick="window.print()" style="background:#ff7f00; color:#fff; border:none; padding:8px 16px; font-weight:bold; border-radius:4px; cursor:pointer;">🖨️ Imprimir Etiqueta (4x6")</button>
    </div>

    <div class="label-border">
        <div class="label-header">
            <div class="brand">ENVÍO NACIONAL / LOCAL</div>
            <div class="privacy-badge">🔒 ETIQUETA CIEGA DE SEGURIDAD</div>
        </div>

        <div class="barcode-section">
            <div style="font-size: 10px; text-transform: uppercase; color: #444;">Folio Único de Pedido:</div>
            <div class="folio-large"><?php echo htmlspecialchars($order['folio']); ?></div>
            <div class="barcode-box">*<?php echo htmlspecialchars($order['folio']); ?>*</div>
            <div style="font-size: 9px; margin-top: 4px; font-weight: bold;">Rastreo: <?php echo htmlspecialchars($order['folio']); ?></div>
        </div>

        <div class="dest-section">
            <div class="dest-title">Destino de Entrega:</div>
            <div class="dest-details">
                <strong>Ciudad / Estado:</strong> <?php echo htmlspecialchars($destCity); ?><br>
                <strong>Código Postal:</strong> <?php echo htmlspecialchars($destZip); ?><br>
                <strong>Fecha de Empaque:</strong> <?php echo date('d/m/Y H:i'); ?>
            </div>
        </div>

        <div class="no-data-notice">
            ⚠️ <strong>CONFIDENCIALIDAD GARANTIZADA:</strong> Esta caja no expone contenidos, importes ni datos financieros en su exterior. Para confirmar entrega en establecimiento, ingrese únicamente el Folio Único.
        </div>
    </div>
</body>
</html>
