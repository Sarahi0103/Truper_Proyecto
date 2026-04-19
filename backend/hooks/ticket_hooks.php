<?php
/**
 * Hook para creación automática de tickets cuando se completa una orden
 * Se debe llamar desde Order::complete() o cashier.php
 * 
 * Uso:
 * include 'hooks/ticket_on_order_complete.php';
 * onOrderCompleted($orderId, $orderData);
 */

require_once __DIR__ . '/../backend/models/TicketIntegration.php';

/**
 * Hook que se dispara cuando se completa una orden
 * Crea automáticamente un ticket y lo vincula con la orden
 */
function onOrderCompleted($orderId, $orderData = []) {
    global $pdo;
    
    try {
        $integration = new TicketIntegration($pdo);
        
        // Crear ticket automático
        $result = $integration->onOrderCompleted($orderId, $orderData);
        
        if ($result['success']) {
            // Log del evento
            error_log("✅ Ticket creado automáticamente para orden #$orderId: " . $result['folio']);
            
            // Retornar información del ticket creado
            return [
                'success' => true,
                'ticket_id' => $result['ticket_id'],
                'folio' => $result['folio'],
                'message' => 'Ticket generado: ' . $result['folio']
            ];
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("❌ Error en onOrderCompleted: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Hook para enviar ticket por WhatsApp
 * Se puede llamar desde cualquier lugar
 */
function sendTicketViaWhatsApp($ticketId, $phoneNumber) {
    global $pdo;
    
    try {
        $integration = new TicketIntegration($pdo);
        
        $result = $integration->onTicketSentViaWhatsApp($ticketId, $phoneNumber);
        
        if ($result['success']) {
            error_log("✅ Ticket #$ticketId enviado por WhatsApp a $phoneNumber");
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("❌ Error en sendTicketViaWhatsApp: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Hook para registrar descarga de PDF
 */
function trackTicketDownload($ticketId) {
    global $pdo;
    
    try {
        $integration = new TicketIntegration($pdo);
        $integration->onTicketDownloaded($ticketId, 'pdf');
        return ['success' => true];
    } catch (Exception $e) {
        error_log("❌ Error en trackTicketDownload: " . $e->getMessage());
        return ['success' => false];
    }
}
?>
