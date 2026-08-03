<?php
/**
 * API / Generador de Cotizaciones Oficiales B2B en PDF / Formato Imprimible
 * Truper Platform
 */
require_once '../../config/config.php';
require_once '../../src/utils/SatCatalogs.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$isLogged = isset($_SESSION['user_id']);
$user = null;
$userSegment = 'menudeo';

if ($isLogged) {
    $stmt = $pdo->prepare("SELECT id, email, phone, first_name, last_name, rfc, tax_name, tax_regime, zip_code_fiscal, customer_segment, user_code FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $userSegment = $user['customer_segment'] ?? 'menudeo';
}

$quoteFolio = 'COT-B2B-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 8));
$issueDate = date('Y-m-d H:i');
$expirationDate = date('Y-m-d', strtotime('+15 days'));

$segLabels = [
    'menudeo' => 'Menudeo / Público General',
    'contratista' => 'Contratista Preferencial (B2B)',
    'escuela_establecimiento' => 'Escuela / Establecimiento (B2B)',
    'mayoreo_ferretero' => 'Mayoreo Ferretero (B2B)'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cotización Oficial <?php echo htmlspecialchars($quoteFolio); ?> - Truper Platform</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #222; background: #fff; margin: 0; padding: 20px; font-size: 13px; }
        .quote-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #ff7f00; padding-bottom: 15px; margin-bottom: 20px; }
        .logo img { height: 45px; width: auto; }
        .company-info { text-align: right; font-size: 11px; color: #555; }
        .quote-title { font-size: 20px; font-weight: bold; color: #ff7f00; margin-bottom: 5px; }
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; background: #f9f9f9; padding: 12px 15px; border-radius: 6px; border: 1px solid #eee; margin-bottom: 20px; }
        .meta-block strong { color: #111; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px 10px; text-align: left; }
        th { background: #ff7f00; color: #fff; font-weight: bold; font-size: 12px; }
        .text-right { text-align: right; }
        .totals-table { width: 300px; margin-left: auto; margin-bottom: 20px; }
        .totals-table td { border: none; padding: 4px 8px; }
        .totals-table .total-row td { border-top: 2px solid #ff7f00; font-weight: bold; font-size: 15px; color: #ff7f00; }
        .validity-notice { background: #fff8f0; border: 1px solid #ffe4cc; color: #994d00; padding: 10px 15px; border-radius: 6px; font-size: 11px; margin-bottom: 20px; }
        .print-btn { background: #ff7f00; color: #fff; border: none; padding: 10px 20px; font-weight: bold; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 15px; text-align: right;">
        <button onclick="window.print()" class="print-btn">🖨️ Imprimir / Guardar en PDF</button>
        <button onclick="window.close()" class="print-btn" style="background:#555;">Cerrar</button>
    </div>

    <div class="quote-header">
        <div class="logo">
            <img src="/truper_logo1.png" alt="Truper Platform">
        </div>
        <div class="company-info">
            <div class="quote-title">COTIZACIÓN OFICIAL B2B</div>
            <div><strong>Folio:</strong> <?php echo htmlspecialchars($quoteFolio); ?></div>
            <div><strong>Fecha:</strong> <?php echo $issueDate; ?> | <strong>Vigencia:</strong> <?php echo $expirationDate; ?> (15 días)</div>
        </div>
    </div>

    <div class="meta-grid">
        <div class="meta-block">
            <div><strong>Cliente:</strong> <?php echo htmlspecialchars(trim(($user['first_name'] ?? 'Cliente') . ' ' . ($user['last_name'] ?? 'B2B'))); ?></div>
            <div><strong>Código Cliente:</strong> <?php echo htmlspecialchars($user['user_code'] ?? 'N/A'); ?></div>
            <div><strong>Segmento Aplicado:</strong> <?php echo htmlspecialchars($segLabels[$userSegment] ?? $userSegment); ?></div>
            <div><strong>Email:</strong> <?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></div>
            <div><strong>Teléfono:</strong> <?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></div>
        </div>
        <div class="meta-block">
            <div><strong>Datos Fiscales (CFDI 4.0):</strong></div>
            <div><strong>RFC:</strong> <?php echo htmlspecialchars(!empty($user['rfc']) ? $user['rfc'] : 'XAXX010101000 (Público General)'); ?></div>
            <div><strong>Razón Social:</strong> <?php echo htmlspecialchars($user['tax_name'] ?? 'N/A'); ?></div>
            <div><strong>Régimen Fiscal:</strong> <?php echo htmlspecialchars(!empty($user['tax_regime']) ? SatCatalogs::getTaxRegimeLabel($user['tax_regime']) : 'N/A'); ?></div>
            <div><strong>C.P. Fiscal:</strong> <?php echo htmlspecialchars($user['zip_code_fiscal'] ?? 'N/A'); ?></div>
        </div>
    </div>

    <div class="validity-notice">
        📌 <strong>Nota Importante para Departamentos de Compras:</strong> Esta cotización mantiene sus precios congelados durante 15 días naturales a partir de su expedición. Para proceder con el surtido o transferencia SPEI, contacta a tu ejecutivo o confirma el pedido directamente en la plataforma.
    </div>

    <table id="quoteItemsTable">
        <thead>
            <tr>
                <th>Código (SKU)</th>
                <th>Descripción del Producto</th>
                <th class="text-right">Precio Unit. (B2B)</th>
                <th class="text-right">Cantidad</th>
                <th class="text-right">Importe</th>
            </tr>
        </thead>
        <tbody id="itemsBody">
            <tr>
                <td colspan="5" style="text-align:center; padding:20px; color:#777;">Cargando artículos del carrito...</td>
            </tr>
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td>Subtotal:</td>
            <td class="text-right" id="subtotalVal">$0.00</td>
        </tr>
        <tr>
            <td>IVA (16%):</td>
            <td class="text-right" id="taxVal">$0.00</td>
        </tr>
        <tr class="total-row">
            <td>Total Cotizado:</td>
            <td class="text-right" id="totalVal">$0.00</td>
        </tr>
    </table>

    <div style="margin-top: 30px; border-top: 1px solid #ddd; padding-top: 15px; font-size: 11px; color: #666; display: flex; justify-content: space-between;">
        <div>
            <strong>Ferretería FOX / Truper Platform México</strong><br>
            Atención a Clientes B2B & Mayoreo: (33) 1248-2297 | ventas@truper.local
        </div>
        <div style="text-align:right;">
            Documento emitido electrónicamente sin tachaduras ni enmendaduras.
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            try {
                const cart = JSON.parse(localStorage.getItem('truper_cart') || '[]');
                const tbody = document.getElementById('itemsBody');
                if (!cart || cart.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:20px;">No hay productos en el carrito para cotizar.</td></tr>';
                    return;
                }

                let subtotal = 0;
                let html = '';

                cart.forEach(item => {
                    const price = parseFloat(item.price) || 0;
                    const qty = parseInt(item.quantity || 1, 10);
                    const lineTotal = price * qty;
                    subtotal += lineTotal;

                    html += `
                        <tr>
                            <td><strong>${item.sku || 'N/A'}</strong></td>
                            <td>${item.name || 'Producto'}</td>
                            <td class="text-right">$${price.toFixed(2)}</td>
                            <td class="text-right">${qty}</td>
                            <td class="text-right"><strong>$${lineTotal.toFixed(2)}</strong></td>
                        </tr>
                    `;
                });

                tbody.innerHTML = html;

                const tax = subtotal * 0.16;
                const total = subtotal + tax;

                document.getElementById('subtotalVal').textContent = '$' + subtotal.toFixed(2);
                document.getElementById('taxVal').textContent = '$' + tax.toFixed(2);
                document.getElementById('totalVal').textContent = '$' + total.toFixed(2);
            } catch (e) {
                console.error(e);
            }
        });
    </script>
</body>
</html>
