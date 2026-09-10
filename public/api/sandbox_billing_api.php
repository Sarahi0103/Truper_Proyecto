<?php
/**
 * Sandbox de Pruebas de Facturacion -- API Backend
 * Ferreteria FOX / Truper Platform
 *
 * Simula el flujo completo de facturacion CFDI 4.0 usando usuarios sandbox
 * reales (role='sandbox', email @sandbox.test) como receptores.
 * Las ordenes se insertan en la tabla orders con prefijo SBX- en order_number.
 * NO se llama al SAT ni a Facturapi.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/config.php';

// -- Sesion y permisos -------------------------------------------------------
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$userRole = $_SESSION['role'] ?? '';
$isLogged  = isset($_SESSION['user_id']);
$isAdmin   = in_array($userRole, ['admin', 'employee'], true);

if (!$isLogged || !$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit;
}

// -- Router ------------------------------------------------------------------
$action = $_GET['action'] ?? 'list';

try {
    global $pdo;
    if (!($pdo instanceof PDO)) {
        throw new Exception('Conexion a base de datos no disponible.');
    }

    switch ($action) {

        // ==============================================================
        // 1. LISTAR USUARIOS SANDBOX DISPONIBLES
        // ==============================================================
        case 'get_sandbox_users':
            $stmt = $pdo->query("
                SELECT u.id AS user_id, c.id AS client_id,
                       u.first_name, u.last_name,
                       u.email, u.rfc, u.tax_name, u.tax_regime,
                       u.zip_code_fiscal, u.user_code,
                       u.phone
                FROM users u
                INNER JOIN clients c ON c.user_id = u.id
                WHERE u.role = 'sandbox'
                ORDER BY u.id
            ");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'users' => $users]);
            break;

        // ==============================================================
        // 2. GENERAR ORDEN / FACTURA DE PRUEBA
        // ==============================================================
        case 'generate':
            $body = json_decode(file_get_contents('php://input'), true) ?? [];

            $clientId     = (int)($body['client_id'] ?? 0);
            $productName  = trim($body['product_name']  ?? 'Producto de Prueba');
            $productQty   = max(1, (int)($body['product_qty']   ?? 1));
            $productPrice = max(0.01, (float)($body['product_price'] ?? 100.00));
            $paymentGw    = trim($body['payment_gateway'] ?? 'sandbox_card');
            $saleType     = trim($body['sale_type']       ?? 'online');
            $requireInv   = (bool)($body['requires_invoice'] ?? false);
            $taxRfc       = strtoupper(trim($body['tax_rfc']  ?? ''));
            $taxName      = trim($body['tax_name']  ?? '');
            $taxZip       = trim($body['tax_zip']   ?? '');
            $taxRegime    = trim($body['tax_regime'] ?? '616');
            $cfdiUse      = trim($body['cfdi_use']  ?? 'G03');

            if (!$clientId) {
                echo json_encode(['success' => false, 'message' => 'Selecciona un cliente de prueba.']);
                break;
            }

            // Verificar que el client_id sea sandbox
            $chk = $pdo->prepare("
                SELECT c.id, u.first_name, u.last_name, u.email,
                       u.rfc, u.tax_name, u.tax_regime, u.zip_code_fiscal
                FROM clients c
                INNER JOIN users u ON u.id = c.user_id
                WHERE c.id = :cid AND u.role = 'sandbox'
            ");
            $chk->execute([':cid' => $clientId]);
            $client = $chk->fetch(PDO::FETCH_ASSOC);

            if (!$client) {
                echo json_encode(['success' => false, 'message' => 'Cliente sandbox no encontrado.']);
                break;
            }

            // UUID CFDI simulado (formato: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx)
            $fakeUuid = null;
            if ($requireInv) {
                $fakeUuid = strtoupper(sprintf(
                    '%08x-%04x-4%03x-%04x-%012x',
                    mt_rand(0, 0xffffffff),
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0xfff),
                    mt_rand(0x8000, 0xbfff),
                    mt_rand(0, 0xffffffffffff)
                ));
                // Forzar uso de datos del usuario sandbox si no se proporcionaron
                if (empty($taxRfc))    $taxRfc    = $client['rfc'] ?? 'XAXX010101000';
                if (empty($taxName))   $taxName   = $client['tax_name'] ?? 'PUBLICO EN GENERAL';
                if (empty($taxZip))    $taxZip    = $client['zip_code_fiscal'] ?? '00000';
                if (empty($taxRegime)) $taxRegime = $client['tax_regime'] ?? '616';
            } else {
                $taxRfc    = 'XAXX010101000';
                $taxName   = 'PUBLICO EN GENERAL';
                $taxZip    = '00000';
                $taxRegime = '616';
                $cfdiUse   = '';
            }

            $subtotal = round($productPrice * $productQty, 2);
            $taxAmt   = round($subtotal * 0.16, 2);
            $total    = round($subtotal + $taxAmt, 2);
            $balance  = $total; // saldo inicial

            $orderNumber = 'SBX-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $now         = date('Y-m-d H:i:s');

            // Notas JSON para guardar datos extra del sandbox
            $notesJson = json_encode([
                'sandbox'        => true,
                'sale_type'      => $saleType,
                'product_name'   => $productName,
                'product_qty'    => $productQty,
                'product_price'  => $productPrice,
                'payment_gateway'=> $paymentGw,
                'client_name'    => $client['first_name'] . ' ' . $client['last_name'],
                'client_email'   => $client['email'],
            ], JSON_UNESCAPED_UNICODE);

            $sql = "
                INSERT INTO orders (
                    client_id, order_number,
                    total_amount, subtotal_amount, tax_amount, tax_rate_applied,
                    balance, payment_status, payment_gateway,
                    requires_invoice, tax_rfc, tax_name, tax_regime, tax_zip, cfdi_use,
                    sat_uuid, status, notes,
                    order_date, created_at, updated_at,
                    payment_terms
                ) VALUES (
                    :client_id, :order_number,
                    :total, :subtotal, :tax_amt, 16.00,
                    :balance, 'paid', :payment_gateway,
                    :requires_invoice, :tax_rfc, :tax_name, :tax_regime, :tax_zip, :cfdi_use,
                    :sat_uuid, 'completed', :notes,
                    :now, :now, :now,
                    'immediate'
                ) RETURNING id
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':client_id'       => $clientId,
                ':order_number'    => $orderNumber,
                ':total'           => $total,
                ':subtotal'        => $subtotal,
                ':tax_amt'         => $taxAmt,
                ':balance'         => $balance,
                ':payment_gateway' => $paymentGw,
                ':requires_invoice'=> $requireInv ? 'true' : 'false',
                ':tax_rfc'         => $taxRfc,
                ':tax_name'        => $taxName,
                ':tax_regime'      => $taxRegime,
                ':tax_zip'         => $taxZip,
                ':cfdi_use'        => $cfdiUse,
                ':sat_uuid'        => $fakeUuid,
                ':notes'           => $notesJson,
                ':now'             => $now,
            ]);

            $newId = $stmt->fetchColumn();

            echo json_encode([
                'success'          => true,
                'message'          => 'Factura sandbox generada correctamente.',
                'order_id'         => $newId,
                'order_number'     => $orderNumber,
                'sat_uuid'         => $fakeUuid,
                'total'            => $total,
                'requires_invoice' => $requireInv,
                'client_name'      => $client['first_name'] . ' ' . $client['last_name'],
            ]);
            break;

        // ==============================================================
        // 3. LISTAR FACTURAS SANDBOX
        // ==============================================================
        case 'list':
            $stmt = $pdo->query("
                SELECT
                    o.id,
                    o.order_number,
                    o.total_amount,
                    o.subtotal_amount,
                    o.tax_amount,
                    o.payment_status,
                    o.payment_gateway,
                    o.requires_invoice,
                    o.tax_rfc,
                    o.tax_name,
                    o.tax_regime,
                    o.sat_uuid,
                    o.status,
                    o.sat_cancellation_reason,
                    o.notes,
                    o.created_at,
                    u.first_name,
                    u.last_name,
                    u.email AS client_email
                FROM orders o
                INNER JOIN clients c ON c.id = o.client_id
                INNER JOIN users u ON u.id = c.user_id
                WHERE o.order_number LIKE 'SBX-%'
                ORDER BY o.created_at DESC
                LIMIT 150
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $orders = array_map(function ($row) {
                $notes = [];
                if (!empty($row['notes'])) {
                    $decoded = json_decode($row['notes'], true);
                    if (is_array($decoded)) $notes = $decoded;
                }
                $row['sandbox']         = true;
                $row['sale_type']       = $notes['sale_type']       ?? 'online';
                $row['product_name']    = $notes['product_name']    ?? '-';
                $row['product_qty']     = $notes['product_qty']     ?? 1;
                $row['product_price']   = $notes['product_price']   ?? 0;
                $row['client_name']     = $notes['client_name']     ?? ($row['first_name'] . ' ' . $row['last_name']);
                $row['payment_gateway'] = $notes['payment_gateway'] ?? $row['payment_gateway'];
                $row['requires_invoice'] = filter_var($row['requires_invoice'], FILTER_VALIDATE_BOOLEAN);
                return $row;
            }, $rows);

            // KPIs sandbox
            $kpis = [
                'total'      => count($orders),
                'stamped'    => count(array_filter($orders, fn($o) => !empty($o['sat_uuid']) && $o['status'] !== 'cancelled')),
                'cancelled'  => count(array_filter($orders, fn($o) => $o['status'] === 'cancelled')),
                'public_gen' => count(array_filter($orders, fn($o) => ($o['tax_rfc'] === 'XAXX010101000') && $o['status'] !== 'cancelled')),
                'total_mxn'  => array_sum(array_column(
                    array_filter($orders, fn($o) => $o['status'] !== 'cancelled'),
                    'total_amount'
                )),
            ];

            echo json_encode(['success' => true, 'orders' => $orders, 'kpis' => $kpis]);
            break;

        // ==============================================================
        // 4. CANCELAR FACTURA SANDBOX
        // ==============================================================
        case 'cancel':
            $body      = json_decode(file_get_contents('php://input'), true) ?? [];
            $orderId   = (int)($body['order_id']  ?? 0);
            $satReason = trim($body['sat_reason'] ?? '03');

            if (!$orderId) {
                echo json_encode(['success' => false, 'message' => 'ID de orden invalido.']);
                break;
            }

            // Verificar que sea sandbox
            $chk = $pdo->prepare("SELECT id, order_number FROM orders WHERE id = :id AND order_number LIKE 'SBX-%'");
            $chk->execute([':id' => $orderId]);
            $ord = $chk->fetch(PDO::FETCH_ASSOC);

            if (!$ord) {
                echo json_encode(['success' => false, 'message' => 'Orden sandbox no encontrada.']);
                break;
            }

            // orders.status solo acepta: pending, completed, cancelled (etc segun el sistema)
            // payment_status solo acepta: pending, partial, paid
            $stmt = $pdo->prepare("
                UPDATE orders
                SET status = 'cancelled',
                    sat_cancellation_reason = :reason,
                    sat_cancellation_status = 'sandbox_cancelled',
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([':reason' => $satReason, ':id' => $orderId]);

            echo json_encode([
                'success'  => true,
                'message'  => 'Factura sandbox cancelada (motivo ' . $satReason . '). Sin efecto fiscal real.',
                'order_id' => $orderId,
            ]);
            break;

        // ==============================================================
        // 5. LIMPIAR TODO EL SANDBOX
        // ==============================================================
        case 'clear':
            $stmt = $pdo->prepare("DELETE FROM orders WHERE order_number LIKE 'SBX-%'");
            $stmt->execute();
            $deleted = $stmt->rowCount();

            echo json_encode([
                'success' => true,
                'message' => "Se eliminaron {$deleted} registro(s) sandbox. Los usuarios de prueba se conservan.",
                'deleted' => $deleted,
            ]);
            break;

        // ==============================================================
        // 6. GENERAR FACTURA GLOBAL SANDBOX
        // ==============================================================
        // ==============================================================
        // 6. GENERAR FACTURA GLOBAL SANDBOX
        // ==============================================================
        case 'generate_global':
            $today = date('Y-m-d');

            $stmt = $pdo->prepare("
                SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount), 0) AS total
                FROM orders
                WHERE order_number LIKE 'SBX-%'
                  AND (requires_invoice = false OR requires_invoice IS NULL)
                  AND status != 'cancelled'
                  AND DATE(created_at) = :today
            ");
            $stmt->execute([':today' => $today]);
            $agg = $stmt->fetch(PDO::FETCH_ASSOC);

            if ((int)$agg['cnt'] === 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'No hay ventas sandbox de Publico General para hoy.',
                    'invoice' => null,
                ]);
                break;
            }

            $fakeGlobalUuid = 'SBX-GLOBAL-' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 12));
            echo json_encode([
                'success' => true,
                'message' => 'Factura global sandbox generada.',
                'invoice' => [
                    'uuid'        => $fakeGlobalUuid,
                    'date'        => $today,
                    'total'       => round((float)$agg['total'], 2),
                    'sales_count' => (int)$agg['cnt'],
                    'xml_url'     => '#sandbox-xml',
                    'pdf_url'     => '#sandbox-pdf',
                    'sandbox'     => true,
                ],
            ]);
            break;

        // ==============================================================
        // 7. DESCARGAR XML DE PRUEBA (CFDI 4.0 SIMULADO)
        // ==============================================================
        case 'download_xml':
            $orderId = (int)($_GET['order_id'] ?? 0);
            if (!$orderId) {
                http_response_code(400);
                echo 'ID de orden invalido.';
                exit;
            }

            $stmt = $pdo->prepare("
                SELECT o.*, u.first_name, u.last_name, u.email AS client_email,
                       u.rfc AS user_rfc, u.tax_name AS user_tax_name,
                       u.tax_regime AS user_regime, u.zip_code_fiscal AS user_zip
                FROM orders o
                LEFT JOIN clients c ON c.id = o.client_id
                LEFT JOIN users u ON u.id = c.user_id
                WHERE o.id = :id AND o.order_number LIKE 'SBX-%'
            ");
            $stmt->execute([':id' => $orderId]);
            $ord = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ord) {
                http_response_code(404);
                echo 'Factura sandbox no encontrada.';
                exit;
            }

            $notes = json_decode($ord['notes'] ?? '{}', true) ?? [];
            $pName = $notes['product_name'] ?? 'Producto de Prueba';
            $pQty  = (int)($notes['product_qty'] ?? 1);
            $pPrice = (float)($notes['product_price'] ?? ($ord['subtotal_amount'] / max(1, $pQty)));
            $uuid   = !empty($ord['sat_uuid']) ? $ord['sat_uuid'] : ('SBX-' . strtoupper(substr(bin2hex(random_bytes(8)), 0, 16)));
            $fecha  = date('Y-m-d\TH:i:s', strtotime($ord['created_at'] ?: 'now'));
            $rfcRec = !empty($ord['tax_rfc']) ? $ord['tax_rfc'] : ($ord['user_rfc'] ?? 'XAXX010101000');
            $nomRec = !empty($ord['tax_name']) ? $ord['tax_name'] : ($notes['client_name'] ?? 'PUBLICO EN GENERAL');
            $regRec = !empty($ord['tax_regime']) ? $ord['tax_regime'] : ($ord['user_regime'] ?? '616');
            $cpRec  = !empty($ord['tax_zip']) ? $ord['tax_zip'] : ($ord['user_zip'] ?? '44100');
            $cfdiUse = !empty($ord['cfdi_use']) ? $ord['cfdi_use'] : 'G03';

            $subtotal = number_format((float)$ord['subtotal_amount'], 2, '.', '');
            $taxAmt   = number_format((float)$ord['tax_amount'], 2, '.', '');
            $total    = number_format((float)$ord['total_amount'], 2, '.', '');

            header('Content-Type: text/xml; charset=utf-8');
            header('Content-Disposition: attachment; filename="CFDI_4.0_' . htmlspecialchars($ord['order_number']) . '.xml"');

            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $xml .= '<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd http://www.sat.gob.mx/TimbreFiscalDigital http://www.sat.gob.mx/sitio_internet/cfd/TimbreFiscalDigital/TimbreFiscalDigitalv11.xsd" xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital" Version="4.0" Serie="SBX" Folio="' . htmlspecialchars(substr($ord['order_number'], 4)) . '" Fecha="' . $fecha . '" Sello="SIMULACION_SANDBOX_SELLO_DIGITAL_EMISOR_' . bin2hex(random_bytes(32)) . '" FormaPago="03" NoCertificado="30001000000500003416" Certificado="SIMULACION_CERTIFICADO_CSD_SANDBOX" SubTotal="' . $subtotal . '" Moneda="MXN" Total="' . $total . '" TipoDeComprobante="I" Exportacion="01" MetodoPago="PUE" LugarExpedicion="44100">' . "\n";
            $xml .= '  <cfdi:Emisor Rfc="FOX950505ABC" Nombre="FERRETERIA FOX S.A. DE C.V." RegimenFiscal="601"/>' . "\n";
            $xml .= '  <cfdi:Receptor Rfc="' . htmlspecialchars($rfcRec) . '" Nombre="' . htmlspecialchars($nomRec) . '" DomicilioFiscalReceptor="' . htmlspecialchars($cpRec) . '" RegimenFiscalReceptor="' . htmlspecialchars($regRec) . '" UsoCFDI="' . htmlspecialchars($cfdiUse) . '"/>' . "\n";
            $xml .= '  <cfdi:Conceptos>' . "\n";
            $xml .= '    <cfdi:Concepto ClaveProdServ="27112700" NoIdentificacion="TRU-' . rand(10000, 99999) . '" Cantidad="' . $pQty . '" ClaveUnidad="H87" Unidad="Pieza" Descripcion="' . htmlspecialchars($pName) . '" ValorUnitario="' . number_format($pPrice, 2, '.', '') . '" Importe="' . $subtotal . '" ObjetoImp="02">' . "\n";
            $xml .= '      <cfdi:Impuestos>' . "\n";
            $xml .= '        <cfdi:Traslados>' . "\n";
            $xml .= '          <cfdi:Traslado Base="' . $subtotal . '" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="' . $taxAmt . '"/>' . "\n";
            $xml .= '        </cfdi:Traslados>' . "\n";
            $xml .= '      </cfdi:Impuestos>' . "\n";
            $xml .= '    </cfdi:Concepto>' . "\n";
            $xml .= '  </cfdi:Conceptos>' . "\n";
            $xml .= '  <cfdi:Impuestos TotalImpuestosTrasladados="' . $taxAmt . '">' . "\n";
            $xml .= '    <cfdi:Traslados>' . "\n";
            $xml .= '      <cfdi:Traslado Base="' . $subtotal . '" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="' . $taxAmt . '"/>' . "\n";
            $xml .= '    </cfdi:Traslados>' . "\n";
            $xml .= '  </cfdi:Impuestos>' . "\n";
            $xml .= '  <cfdi:Complemento>' . "\n";
            $xml .= '    <tfd:TimbreFiscalDigital Version="1.1" UUID="' . htmlspecialchars($uuid) . '" FechaTimbrado="' . $fecha . '" RfcProvCertif="SAT970701NN3" SelloCFD="SIMULACION_SELLO_CFD_' . bin2hex(random_bytes(24)) . '" NoCertificadoSAT="30001000000500003417" SelloSAT="SIMULACION_SELLO_SAT_' . bin2hex(random_bytes(32)) . '"/>' . "\n";
            $xml .= '  </cfdi:Complemento>' . "\n";
            $xml .= '</cfdi:Comprobante>';

            echo $xml;
            exit;

        // ==============================================================
        // 8. DESCARGAR / VER PDF DE PRUEBA (REPRESENTACION IMPRESA CFDI 4.0)
        // ==============================================================
        case 'download_pdf':
            $orderId = (int)($_GET['order_id'] ?? 0);
            if (!$orderId) {
                http_response_code(400);
                echo 'ID de orden invalido.';
                exit;
            }

            $stmt = $pdo->prepare("
                SELECT o.*, u.first_name, u.last_name, u.email AS client_email,
                       u.rfc AS user_rfc, u.tax_name AS user_tax_name,
                       u.tax_regime AS user_regime, u.zip_code_fiscal AS user_zip,
                       u.phone AS client_phone
                FROM orders o
                LEFT JOIN clients c ON c.id = o.client_id
                LEFT JOIN users u ON u.id = c.user_id
                WHERE o.id = :id AND o.order_number LIKE 'SBX-%'
            ");
            $stmt->execute([':id' => $orderId]);
            $ord = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ord) {
                http_response_code(404);
                echo 'Factura sandbox no encontrada.';
                exit;
            }

            $notes = json_decode($ord['notes'] ?? '{}', true) ?? [];
            $pName = $notes['product_name'] ?? 'Producto Truper Demo';
            $pQty  = (int)($notes['product_qty'] ?? 1);
            $pPrice = (float)($notes['product_price'] ?? ($ord['subtotal_amount'] / max(1, $pQty)));
            $uuid   = !empty($ord['sat_uuid']) ? $ord['sat_uuid'] : 'CFDI-GLOBAL-SANDBOX-TEST';
            $fecha  = $ord['created_at'] ? date('d/m/Y H:i:s', strtotime($ord['created_at'])) : date('d/m/Y H:i:s');
            $rfcRec = !empty($ord['tax_rfc']) ? $ord['tax_rfc'] : ($ord['user_rfc'] ?? 'XAXX010101000');
            $nomRec = !empty($ord['tax_name']) ? $ord['tax_name'] : ($notes['client_name'] ?? 'PUBLICO EN GENERAL');
            $regRec = !empty($ord['tax_regime']) ? $ord['tax_regime'] : ($ord['user_regime'] ?? '616');
            $cpRec  = !empty($ord['tax_zip']) ? $ord['tax_zip'] : ($ord['user_zip'] ?? '44100');
            $cfdiUse = !empty($ord['cfdi_use']) ? $ord['cfdi_use'] : 'G03 - Gastos en general';

            $subtotal = number_format((float)$ord['subtotal_amount'], 2);
            $taxAmt   = number_format((float)$ord['tax_amount'], 2);
            $total    = number_format((float)$ord['total_amount'], 2);
            $isCancelled = ($ord['status'] === 'cancelled');

            header('Content-Type: text/html; charset=utf-8');
            ?>
            <!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <title>Factura <?php echo htmlspecialchars($ord['order_number']); ?> (Sandbox CFDI 4.0)</title>
                <style>
                    * { box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
                    body { background: #f1f5f9; margin: 0; padding: 24px; color: #1e293b; font-size: 12px; }
                    .invoice-box { max-width: 850px; margin: auto; background: #fff; padding: 32px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); position: relative; }
                    .watermark { position: absolute; top: 38%; left: 10%; transform: rotate(-30deg); font-size: 42px; font-weight: 900; color: rgba(234, 179, 8, 0.18); border: 4px dashed rgba(234, 179, 8, 0.35); padding: 12px 30px; border-radius: 12px; pointer-events: none; text-align: center; }
                    .header-grid { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #e2e8f0; padding-bottom: 18px; margin-bottom: 20px; }
                    .logo-section h1 { margin: 0; font-size: 22px; color: #ff7f00; letter-spacing: 0.5px; }
                    .logo-section p { margin: 3px 0; font-size: 11px; color: #64748b; }
                    .folio-card { background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px 16px; text-align: right; min-width: 250px; }
                    .folio-card .folio-title { font-size: 10px; color: #64748b; font-weight: 700; text-transform: uppercase; }
                    .folio-card .folio-num { font-size: 18px; font-weight: 800; color: #ff7f00; font-family: monospace; }
                    .folio-card .uuid { font-size: 9px; color: #3b82f6; font-family: monospace; word-break: break-all; margin-top: 4px; }
                    .parties-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
                    .party-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 16px; }
                    .party-title { font-size: 11px; font-weight: 700; color: #0f172a; text-transform: uppercase; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; margin-bottom: 8px; }
                    .party-box p { margin: 3px 0; font-size: 11px; }
                    .party-box strong { color: #334155; }
                    table.invoice-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                    table.invoice-table th { background: #0f172a; color: #fff; text-align: left; padding: 8px 10px; font-size: 11px; text-transform: uppercase; }
                    table.invoice-table td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; font-size: 11px; }
                    .totals-grid { display: flex; justify-content: flex-end; margin-bottom: 24px; }
                    .totals-table { width: 280px; border-collapse: collapse; }
                    .totals-table td { padding: 6px 10px; font-size: 12px; }
                    .totals-table tr.total-row td { background: #f8fafc; border-top: 2px solid #0f172a; font-weight: 800; font-size: 14px; color: #ff7f00; }
                    .timbre-section { background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px; display: flex; gap: 16px; align-items: center; }
                    .qr-box { width: 90px; height: 90px; background: #fff; border: 1px solid #94a3b8; display: flex; align-items: center; justify-content: center; font-size: 9px; text-align: center; color: #64748b; padding: 4px; font-weight: 700; }
                    .timbre-data { font-size: 9px; color: #475569; font-family: monospace; line-height: 1.4; word-break: break-all; }
                    .timbre-data strong { color: #0f172a; }
                    .no-print-bar { max-width: 850px; margin: 0 auto 16px; display: flex; justify-content: space-between; align-items: center; background: #0f172a; color: #fff; padding: 10px 18px; border-radius: 8px; }
                    .btn-print { background: #ff7f00; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 12px; }
                    .btn-print:hover { background: #e06d00; }
                    @media print {
                        body { background: #fff; padding: 0; }
                        .no-print-bar { display: none !important; }
                        .invoice-box { box-shadow: none; border: none; padding: 0; }
                    }
                </style>
            </head>
            <body>
                <div class="no-print-bar">
                    <div>
                        <strong>MODO SANDBOX:</strong> Factura de Prueba Simulada (CFDI 4.0)
                    </div>
                    <div>
                        <button class="btn-print" onclick="window.print()">Imprimir / Guardar como PDF</button>
                    </div>
                </div>

                <div class="invoice-box">
                    <div class="watermark">SANDBOX TEST<br><span style="font-size:18px;">SIN VALIDEZ FISCAL</span></div>

                    <div class="header-grid">
                        <div class="logo-section">
                            <h1>FERRETERÍA FOX</h1>
                            <p><strong>FERRETERIA FOX S.A. DE C.V.</strong></p>
                            <p>RFC: FOX950505ABC &mdash; Régimen: 601 General de Ley Personas Morales</p>
                            <p>Lugar de Expedición: CP 44100 | Tipo de Comprobante: I - Ingreso</p>
                        </div>
                        <div class="folio-card">
                            <div class="folio-title">Factura CFDI 4.0</div>
                            <div class="folio-num"><?php echo htmlspecialchars($ord['order_number']); ?></div>
                            <div style="font-size:10px; color:#64748b; margin-top:4px;">Fecha: <?php echo $fecha; ?></div>
                            <div class="uuid"><strong>UUID:</strong> <?php echo htmlspecialchars($uuid); ?></div>
                            <?php if ($isCancelled): ?>
                                <div style="margin-top:6px; background:#fee2e2; color:#ef4444; padding:3px 6px; border-radius:4px; font-weight:bold; font-size:10px; text-align:center;">CANCELADA ANTE SAT</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="parties-grid">
                        <div class="party-box">
                            <div class="party-title">Emisor</div>
                            <p><strong>Razón Social:</strong> FERRETERIA FOX S.A. DE C.V.</p>
                            <p><strong>RFC:</strong> FOX950505ABC</p>
                            <p><strong>Régimen Fiscal:</strong> 601 - General de Ley Personas Morales</p>
                            <p><strong>Domicilio Fiscal:</strong> Av. Ferretera 100, Guadalajara, Jalisco, CP 44100</p>
                        </div>
                        <div class="party-box">
                            <div class="party-title">Receptor</div>
                            <p><strong>Nombre / Razón Social:</strong> <?php echo htmlspecialchars($nomRec); ?></p>
                            <p><strong>RFC:</strong> <?php echo htmlspecialchars($rfcRec); ?></p>
                            <p><strong>Régimen Fiscal:</strong> <?php echo htmlspecialchars($regRec); ?></p>
                            <p><strong>CP Receptor:</strong> <?php echo htmlspecialchars($cpRec); ?> | <strong>Uso CFDI:</strong> <?php echo htmlspecialchars($cfdiUse); ?></p>
                        </div>
                    </div>

                    <table class="invoice-table">
                        <thead>
                            <tr>
                                <th>Clave SAT</th>
                                <th>Cant.</th>
                                <th>Unidad</th>
                                <th>Descripción</th>
                                <th>P. Unitario</th>
                                <th>Impuesto</th>
                                <th style="text-align:right;">Importe</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="font-family:monospace;">27112700</td>
                                <td><?php echo $pQty; ?></td>
                                <td>H87 (Pza)</td>
                                <td><strong><?php echo htmlspecialchars($pName); ?></strong><br><span style="color:#64748b;font-size:10px;">Venta simulada en Sandbox Fox Truper</span></td>
                                <td>$<?php echo number_format($pPrice, 2); ?></td>
                                <td>IVA 16% ($<?php echo $taxAmt; ?>)</td>
                                <td style="text-align:right; font-weight:700;">$<?php echo $subtotal; ?></td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="totals-grid">
                        <table class="totals-table">
                            <tr>
                                <td>Subtotal:</td>
                                <td style="text-align:right; font-weight:600;">$<?php echo $subtotal; ?></td>
                            </tr>
                            <tr>
                                <td>IVA Trasladado (16%):</td>
                                <td style="text-align:right; font-weight:600;">$<?php echo $taxAmt; ?></td>
                            </tr>
                            <tr class="total-row">
                                <td>Total MXN:</td>
                                <td style="text-align:right;">$<?php echo $total; ?></td>
                            </tr>
                        </table>
                    </div>

                    <div class="timbre-section">
                        <div class="qr-box">
                            [ CÓDIGO QR SAT CFDI 4.0 ]
                        </div>
                        <div class="timbre-data">
                            <div><strong>Folio Fiscal (UUID):</strong> <?php echo htmlspecialchars($uuid); ?></div>
                            <div><strong>No. Certificado SAT:</strong> 30001000000500003417 &mdash; <strong>No. Certificado Emisor:</strong> 30001000000500003416</div>
                            <div><strong>Cadena Original SAT:</strong> ||1.1|<?php echo htmlspecialchars($uuid); ?>|<?php echo date('Y-m-d\TH:i:s'); ?>|FOX950505ABC|<?php echo $total; ?>|MXN||</div>
                            <div><strong>Sello Digital CFDI:</strong> SIMULACION_SANDBOX_SELLO_CFD_<?php echo bin2hex(random_bytes(16)); ?>...</div>
                            <div><strong>Sello Digital SAT:</strong> SIMULACION_SANDBOX_SELLO_SAT_<?php echo bin2hex(random_bytes(16)); ?>...</div>
                        </div>
                    </div>
                </div>
            </body>
            </html>
            <?php
            exit;

        default:
            echo json_encode(['success' => false, 'message' => 'Accion no reconocida: ' . htmlspecialchars($action)]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
