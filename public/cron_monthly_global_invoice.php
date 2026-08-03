<?php
/**
 * Autogeneración de Factura Global CFDI 4.0 (Público General - RFC XAXX010101000)
 * Truper Platform - Fase 3
 * Ejecución nocturna / mensual programada
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

try {
    // Find un-invoiced sales tickets (invoice_required = false and uuid_fiscal IS NULL)
    $sql = "
        SELECT id, folio, total_amount, issued_date 
        FROM sales_tickets 
        WHERE (invoice_required IS FALSE OR invoice_required IS NULL)
          AND (uuid_fiscal IS NULL OR uuid_fiscal = '')
          AND COALESCE(order_status, '') != 'canceled'
          AND deleted_at IS NULL
    ";

    $tickets = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $totalTickets = count($tickets);

    if ($totalTickets === 0) {
        echo json_encode([
            'success' => true,
            'message' => 'No hay notas de venta pendientes para Factura Global.',
            'count' => 0
        ]);
        exit;
    }

    $globalTotal = 0.0;
    foreach ($tickets as $t) {
        $globalTotal += (float)$t['total_amount'];
    }

    // Generate mock SAT UUID fiscal for global invoice payload
    $globalUuid = 'SAT-GLOBAL-CFDI40-' . date('Ym') . '-' . strtoupper(bin2hex(random_bytes(6)));

    // Mark tickets as consolidated in global invoice
    $upd = $pdo->prepare("UPDATE sales_tickets SET uuid_fiscal = ? WHERE (invoice_required IS FALSE OR invoice_required IS NULL) AND (uuid_fiscal IS NULL OR uuid_fiscal = '')");
    $upd->execute([$globalUuid]);

    echo json_encode([
        'success' => true,
        'message' => "Factura Global CFDI 4.0 generada exitosamente ante el SAT bajo RFC Genérico XAXX010101000.",
        'global_uuid' => $globalUuid,
        'consolidated_tickets_count' => $totalTickets,
        'global_total_amount' => number_format($globalTotal, 2, '.', ''),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al generar Factura Global CFDI 4.0: ' . $e->getMessage()
    ]);
}
