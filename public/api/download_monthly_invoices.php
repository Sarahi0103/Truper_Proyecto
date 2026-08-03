<?php
/**
 * API Descarga Masiva de Facturas y Comprobantes en ZIP (para Contabilidad)
 * Truper Platform - Módulo de Cliente
 */
require_once '../../config/config.php';
require_login();

$userId = $_SESSION['user_id'];
$year = (int)($_GET['year'] ?? date('Y'));
$month = (int)($_GET['month'] ?? date('m'));

$stmt = $pdo->prepare("
    SELECT folio, issued_date, total_amount, invoice_required, uuid_fiscal 
    FROM sales_tickets 
    WHERE user_id = ? 
      AND deleted_at IS NULL
      AND EXTRACT(YEAR FROM issued_date) = ?
      AND EXTRACT(MONTH FROM issued_date) = ?
    ORDER BY id ASC
");
$stmt->execute([$userId, $year, $month]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($tickets)) {
    die("No hay facturas o comprobantes emitidos en el período {$year}-{$month}.");
}

if (!class_exists('ZipArchive')) {
    die("Extensión ZipArchive no disponible en el servidor PHP.");
}

$zip = new ZipArchive();
$tmpFile = tempnam(sys_get_temp_dir(), 'FOX_INVOICES_');
if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("No se pudo crear el archivo ZIP temporal.");
}

// Add receipts to ZIP
foreach ($tickets as $t) {
    $folioStr = $t['folio'];
    $uuid = $t['uuid_fiscal'] ?: ('SAT-CFDI40-' . $folioStr);
    
    // Add XML mock content
    $xmlContent = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<cfdi:Comprobante xmlns:cfdi=\"http://www.sat.gob.mx/cfd/4\" Version=\"4.0\" Folio=\"{$folioStr}\" Sello=\"DEMO_SELLO\" Total=\"{$t['total_amount']}\" Moneda=\"MXN\" SubTotal=\"{$t['total_amount']}\" Fecha=\"{$t['issued_date']}\">\n  <cfdi:Emisor Rfc=\"FOX950101XXX\" Nombre=\"FERRETERIA FOX SA DE CV\" RegimenFiscal=\"601\"/>\n  <cfdi:Receptor Rfc=\"XAXX010101000\" UsoCFDI=\"G03\"/>\n  <cfdi:Complemento>\n    <tfd:TimbreFiscalDigital xmlns:tfd=\"http://www.sat.gob.mx/TimbreFiscalDigital\" UUID=\"{$uuid}\"/>\n  </cfdi:Complemento>\n</cfdi:Comprobante>";
    
    // Add PDF summary text
    $pdfSummary = "COMPROBANTE DIGITAL FERRETERÍA FOX\nFolio: {$folioStr}\nUUID SAT: {$uuid}\nTotal: $" . number_format((float)$t['total_amount'], 2) . " MXN\nFecha: {$t['issued_date']}\n";

    $zip->addFromString("FACTURA_{$folioStr}.xml", $xmlContent);
    $zip->addFromString("COMPROBANTE_{$folioStr}.txt", $pdfSummary);
}

$zip->close();

// Stream ZIP file
$filename = "Facturas_FOX_{$year}_" . str_pad($month, 2, '0', STR_PAD_LEFT) . ".zip";
header('Content-Type: application/zip');
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header('Content-Length: ' . filesize($tmpFile));
readfile($tmpFile);
@unlink($tmpFile);
exit;
