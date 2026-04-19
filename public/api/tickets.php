<?php
/**
 * API de Gestión de Tickets de Ventas
 * Endpoint: /api/tickets.php
 */

require_once '../../config/config.php';
require_once '../../backend/models/SalesTicket.php';

header('Content-Type: application/json');

require_admin();  // Solo admins pueden acceder

// Validar CSRF para POST/PUT
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
    require_csrf_token();
}

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];

$ticketManager = new SalesTicket($pdo);
$response = ['success' => false, 'message' => 'Acción no reconocida'];

try {
    switch ($action) {
        // Listar tickets activos
        case 'list':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }
            
            $page = (int)($_GET['page'] ?? 1);
            $perPage = (int)($_GET['per_page'] ?? 20);
            
            $filters = [];
            if (!empty($_GET['folio'])) $filters['folio'] = $_GET['folio'];
            if (!empty($_GET['ticket_type'])) $filters['ticket_type'] = $_GET['ticket_type'];
            if (!empty($_GET['payment_status'])) $filters['payment_status'] = $_GET['payment_status'];
            if (!empty($_GET['start_date'])) $filters['start_date'] = $_GET['start_date'];
            if (!empty($_GET['end_date'])) $filters['end_date'] = $_GET['end_date'];
            
            $response = $ticketManager->listActiveTickets($page, $perPage, $filters);
            break;
        
        // Crear nuevo ticket
        case 'create':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }
            
            // Validación básica
            if (empty($input['user_id'])) {
                $response = ['success' => false, 'message' => 'user_id requerido'];
                break;
            }
            
            if (empty($input['total_amount'])) {
                $response = ['success' => false, 'message' => 'total_amount requerido'];
                break;
            }
            
            $input['issued_by'] = $_SESSION['user_id'];
            $response = $ticketManager->createTicket($input);
            
            if ($response['success']) {
                $ticketManager->addAuditLog(
                    $response['ticket_id'],
                    'created',
                    null,
                    ['folio' => $response['folio']],
                    'Ticket creado manualmente'
                );
            }
            break;
        
        // Obtener ticket por folio
        case 'get-by-folio':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }
            
            $folio = $_GET['folio'] ?? null;
            if (!$folio) {
                $response = ['success' => false, 'message' => 'Folio requerido'];
                break;
            }
            
            $ticket = $ticketManager->getTicketByFolio($folio);
            if (!$ticket) {
                $response = ['success' => false, 'message' => 'Ticket no encontrado'];
                break;
            }
            
            $response = [
                'success' => true,
                'ticket' => $ticket
            ];
            break;
        
        // Generar estadísticas mensuales
        case 'generate-stats':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }
            
            $yearMonth = $input['year_month'] ?? date('Y-m');
            $response = $ticketManager->generateMonthlyStatistics($yearMonth);
            break;
        
        // Archivar mes anterior
        case 'archive-previous-month':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }
            
            $response = $ticketManager->archivePreviousMonth();
            break;
        
        // Obtener estadísticas del mes
        case 'get-stats':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }
            
            $yearMonth = $_GET['year_month'] ?? date('Y-m');
            
            $stmt = $pdo->prepare("
                SELECT * FROM sales_monthly_statistics
                WHERE year_month = :year_month
            ");
            $stmt->execute([':year_month' => $yearMonth]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$stats) {
                // Generar si no existe
                $response = $ticketManager->generateMonthlyStatistics($yearMonth);
            } else {
                $response = [
                    'success' => true,
                    'statistics' => $stats
                ];
            }
            break;
        
        // Verificar ticket por folio (para clientes/auditoría)
        case 'verify':
            // Este endpoint puede ser público para verificación de autenticidad
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }
            
            $folio = $_GET['folio'] ?? null;
            if (!$folio) {
                $response = ['success' => false, 'message' => 'Folio requerido'];
                break;
            }
            
            $ticket = $ticketManager->getTicketByFolio($folio);
            if (!$ticket) {
                $response = ['success' => false, 'message' => 'Ticket no encontrado'];
                break;
            }
            
            // Retornar información limitada de verificación
            $response = [
                'success' => true,
                'verified' => true,
                'folio' => $ticket['folio'],
                'total_amount' => $ticket['total_amount'],
                'issued_date' => $ticket['issued_date'],
                'customer_name' => $ticket['customer_name'] ?? 'N/A',
                'item_count' => count($ticket['items'] ?? [])
            ];
            break;
        
        default:
            $response = ['success' => false, 'message' => 'Acción no reconocida: ' . $action];
    }
    
} catch (Exception $e) {
    error_log("Error en tickets API: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'Error del servidor'];
    http_response_code(500);
}

echo json_encode($response);
