<?php
/**
 * API de Historial de Tickets del Cliente
 * Endpoint: /api/client_tickets.php
 * Muestra todos los tickets del cliente con historial completo
 */

require_once '../../config/config.php';
require_once '../../backend/models/TicketIntegration.php';

header('Content-Type: application/json');

// Requiere autenticación de cliente
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'No autenticado']));
}

$action = $_GET['action'] ?? 'list';
$userId = $_SESSION['user_id'];

$integration = new TicketIntegration($pdo);
$response = ['success' => false, 'message' => 'Acción no reconocida'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
}

try {
    switch ($action) {
        // Listar todos los tickets del cliente
        case 'list':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                http_response_code(405);
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }
            
            $page = (int)($_GET['page'] ?? 1);
            $perPage = (int)($_GET['per_page'] ?? 10);
            
            $response = $integration->getClientTickets($userId, $page, $perPage);
            break;
        
        // Obtener detalles de un ticket específico
        case 'get':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                http_response_code(405);
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }
            
            $ticketId = (int)($_GET['ticket_id'] ?? 0);
            if (!$ticketId) {
                $response = ['success' => false, 'message' => 'ticket_id requerido'];
                break;
            }
            
            // Verificar que el ticket pertenece al usuario
            $stmt = $pdo->prepare("
                SELECT st.id FROM sales_tickets st
                WHERE st.id = :id AND st.user_id = :user_id
            ");
            $stmt->execute([':id' => $ticketId, ':user_id' => $userId]);
            
            if (!$stmt->fetch()) {
                http_response_code(403);
                $response = ['success' => false, 'message' => 'No autorizado'];
                break;
            }
            
            // Obtener detalles del ticket
            $stmt = $pdo->prepare("
                SELECT st.*, u.first_name || CASE WHEN u.last_name IS NOT NULL AND u.last_name <> '' THEN ' ' || u.last_name ELSE '' END as customer_name, u.email
                FROM sales_tickets st
                LEFT JOIN users u ON st.user_id = u.id
                WHERE st.id = :id
            ");
            $stmt->execute([':id' => $ticketId]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Obtener items
            $stmt = $pdo->prepare("
                SELECT * FROM ticket_items WHERE ticket_id = :ticket_id
            ");
            $stmt->execute([':ticket_id' => $ticketId]);
            $ticket['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response = [
                'success' => true,
                'ticket' => $ticket
            ];
            break;
        
        // Obtener historial de un ticket
        case 'history':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                http_response_code(405);
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }
            
            $ticketId = (int)($_GET['ticket_id'] ?? 0);
            if (!$ticketId) {
                $response = ['success' => false, 'message' => 'ticket_id requerido'];
                break;
            }
            
            // Verificar que el ticket pertenece al usuario
            $stmt = $pdo->prepare("
                SELECT st.id FROM sales_tickets st
                WHERE st.id = :id AND st.user_id = :user_id
            ");
            $stmt->execute([':id' => $ticketId, ':user_id' => $userId]);
            
            if (!$stmt->fetch()) {
                http_response_code(403);
                $response = ['success' => false, 'message' => 'No autorizado'];
                break;
            }
            
            $response = $integration->getTicketHistory($ticketId, 'client');
            break;
        
        // Descargar PDF del ticket
        case 'download-pdf':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                http_response_code(405);
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            require_csrf_token();
            
            $ticketId = (int)($_GET['ticket_id'] ?? 0);
            if (!$ticketId) {
                $response = ['success' => false, 'message' => 'ticket_id requerido'];
                break;
            }
            
            // Verificar que el ticket pertenece al usuario
            $stmt = $pdo->prepare("
                SELECT st.folio FROM sales_tickets st
                WHERE st.id = :id AND st.user_id = :user_id
            ");
            $stmt->execute([':id' => $ticketId, ':user_id' => $userId]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$ticket) {
                http_response_code(403);
                die(json_encode(['success' => false, 'message' => 'No autorizado']));
            }
            
            // Registrar descarga
            $integration->onTicketDownloaded($ticketId, 'pdf');
            
            // Generar PDF (aquí iría la lógica de generación)
            // Por ahora redirigimos a un generador
            http_response_code(302);
            header('Location: /public/generate_ticket_pdf.php?ticket_id=' . $ticketId . '&folio=' . urlencode($ticket['folio']));
            die();
            break;
        
        // Obtener estadísticas del cliente
        case 'stats':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                http_response_code(405);
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_tickets,
                    SUM(CASE WHEN ticket_type = 'sale' THEN 1 ELSE 0 END) as sales,
                    SUM(CASE WHEN ticket_type = 'return' THEN 1 ELSE 0 END) as returns,
                    SUM(CASE WHEN ticket_type = 'sale' THEN total_amount ELSE 0 END) as total_spent,
                    MAX(issued_date) as last_ticket
                FROM sales_tickets
                WHERE user_id = :user_id AND deleted_at IS NULL
            ");
            
            $stmt->execute([':user_id' => $userId]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $response = [
                'success' => true,
                'statistics' => $stats
            ];
            break;
        
        // Enviar ticket por WhatsApp
        case 'send-whatsapp':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $ticketId = (int)($input['ticket_id'] ?? 0);
            $phoneNumber = $input['phone_number'] ?? '';
            
            if (!$ticketId || !$phoneNumber) {
                $response = ['success' => false, 'message' => 'ticket_id y phone_number requeridos'];
                break;
            }
            
            // Verificar que el ticket pertenece al usuario
            $stmt = $pdo->prepare("
                SELECT st.folio FROM sales_tickets st
                WHERE st.id = :id AND st.user_id = :user_id
            ");
            $stmt->execute([':id' => $ticketId, ':user_id' => $userId]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$ticket) {
                http_response_code(403);
                $response = ['success' => false, 'message' => 'No autorizado'];
                break;
            }
            
            // Aquí iría la lógica real de WhatsApp
            // Por ahora solo registramos el envío
            $result = $integration->onTicketSentViaWhatsApp($ticketId, $phoneNumber);
            
            if ($result['success']) {
                // Crear URL de WhatsApp
                $message = "Hola, aquí está tu comprobante de venta: " . $ticket['folio'];
                $whatsappUrl = "https://wa.me/" . preg_replace('/[^0-9]/', '', $phoneNumber) . 
                              "?text=" . urlencode($message);
                
                $response = [
                    'success' => true,
                    'message' => 'Envío registrado',
                    'whatsapp_url' => $whatsappUrl,
                    'folio' => $ticket['folio']
                ];
            } else {
                $response = $result;
            }
            break;
        
        default:
            http_response_code(400);
            $response = ['success' => false, 'message' => 'Acción no reconocida'];
    }
    
} catch (Exception $e) {
    error_log("Error en client_tickets API: " . $e->getMessage());
    http_response_code(500);
    $response = ['success' => false, 'message' => 'Error del servidor'];
}

echo json_encode($response);
