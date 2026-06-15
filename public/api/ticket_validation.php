<?php
require_once '../../config/config.php';
require_once '../../backend/models/SalesTicket.php';

require_admin();

// Validar CSRF para POST/PUT
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
    require_csrf_token();
}

header('Content-Type: application/json');

$pdo = $GLOBALS['pdo'];
$ticketModel = new SalesTicket($pdo);

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];

try {
    switch ($action) {
        case 'validate':
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }

            $folio = sanitize($input['folio'] ?? '');
            $notes = sanitize($input['notes'] ?? '');
            $adminId = $_SESSION['user_id'];

            if (empty($folio)) {
                echo json_encode(['success' => false, 'message' => 'Folio es requerido']);
                exit;
            }

            $result = $ticketModel->validatePickup($folio, $adminId, $notes);
            echo json_encode($result);
            break;

        case 'search':
            if ($method !== 'GET') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }

            $query = sanitize($_GET['query'] ?? '');
            $page = (int)($_GET['page'] ?? 1);
            $perPage = (int)($_GET['per_page'] ?? 10);

            if (strlen($query) < 3) {
                echo json_encode(['success' => false, 'message' => 'Query debe tener al menos 3 caracteres']);
                exit;
            }

            $result = $ticketModel->getPendingPickups(null, $query, $page, $perPage);
            echo json_encode($result);
            break;

        case 'details':
            if ($method !== 'GET') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }

            $folio = sanitize($_GET['folio'] ?? '');

            if (empty($folio)) {
                echo json_encode(['success' => false, 'message' => 'Folio es requerido']);
                exit;
            }

            $ticket = $ticketModel->getTicketWithPickupInfo($folio);

            if (!$ticket) {
                echo json_encode(['success' => false, 'message' => 'Ticket no encontrado']);
                exit;
            }

            echo json_encode(['success' => true, 'ticket' => $ticket]);
            break;

        case 'history':
            if ($method !== 'GET') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }

            $ticketId = (int)($_GET['ticket_id'] ?? 0);

            if ($ticketId === 0) {
                echo json_encode(['success' => false, 'message' => 'Ticket ID es requerido']);
                exit;
            }

            $result = $ticketModel->getPickupLog($ticketId);
            echo json_encode($result);
            break;

        case 'global-history':
            if ($method !== 'GET') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }

            try {
                $stmt = $pdo->query("
                    SELECT 
                        tpl.id,
                        tpl.ticket_id,
                        tpl.admin_id,
                        tpl.action,
                        tpl.notes,
                        tpl.ip_address,
                        tpl.created_at,
                        st.folio AS ticket_folio,
                        COALESCE(st.customer_name, u.first_name || CASE WHEN u.last_name IS NOT NULL AND u.last_name <> '' THEN ' ' || u.last_name ELSE '' END, u.email, 'Mostrador') AS ticket_customer_name,
                        admin.first_name || CASE WHEN admin.last_name IS NOT NULL AND admin.last_name <> '' THEN ' ' || admin.last_name ELSE '' END AS admin_name
                    FROM ticket_pickup_log tpl
                    JOIN sales_tickets st ON tpl.ticket_id = st.id
                    LEFT JOIN users u ON st.user_id = u.id
                    LEFT JOIN users admin ON tpl.admin_id = admin.id
                    ORDER BY tpl.created_at DESC 
                    LIMIT 50
                ");
                $logs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                echo json_encode([
                    'success' => true,
                    'logs' => $logs
                ]);
            } catch (Exception $e) {
                error_log('Error in global-history: ' . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error al obtener historial global: ' . $e->getMessage()]);
            }
            break;

        case 'reactivate':
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }

            $ticketId = (int)($input['ticket_id'] ?? 0);
            $notes = sanitize($input['notes'] ?? '');
            $adminId = $_SESSION['user_id'];

            if ($ticketId === 0) {
                echo json_encode(['success' => false, 'message' => 'Ticket ID es requerido']);
                exit;
            }

            // Verificar que sea admin superior
            if (!isAdminSuper()) {
                echo json_encode(['success' => false, 'message' => 'Solo administradores superiores pueden reactivar tickets']);
                exit;
            }

            $result = $ticketModel->reactivateTicket($ticketId, $adminId, $notes);
            echo json_encode($result);
            break;

        case 'pending':
            if ($method !== 'GET') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }

            $userId = (int)($_GET['user_id'] ?? 0);
            $page = (int)($_GET['page'] ?? 1);
            $perPage = (int)($_GET['per_page'] ?? 20);

            $result = $ticketModel->getPendingPickups($userId > 0 ? $userId : null, null, $page, $perPage);
            echo json_encode($result);
            break;

        case 'notify':
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }

            $folio = sanitize($input['folio'] ?? '');
            $email = sanitize($input['email'] ?? '');
            $customerName = sanitize($input['customer_name'] ?? '');

            if (empty($folio) || empty($email)) {
                echo json_encode(['success' => false, 'message' => 'Folio y email son requeridos']);
                exit;
            }

            // Simular envío de email (en producción usar servicio real de email)
            $subject = "Tu ticket ha sido validado - Truper";
            $message = "Hola $customerName,\n\nTu ticket #$folio ha sido validado exitosamente. Ya puedes recoger tu pedido.\n\nGracias por tu compra.\n\nTruper";

            // Aquí se integraría con servicio real de email como PHPMailer, SendGrid, etc.
            // Por ahora, solo logueamos el envío
            error_log("Email enviado a $email: $subject");

            echo json_encode(['success' => true, 'message' => 'Notificación enviada']);
            break;

        case 'delete':
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }

            $folio = sanitize($input['folio'] ?? '');
            $reason = sanitize($input['reason'] ?? 'Eliminado por administrador');
            $adminId = $_SESSION['user_id'];

            if (empty($folio)) {
                echo json_encode(['success' => false, 'message' => 'Folio es requerido']);
                exit;
            }

            // Obtener ticket
            $stmt = $pdo->prepare("SELECT id, pickup_status FROM sales_tickets WHERE folio = :folio AND deleted_at IS NULL");
            $stmt->execute([':folio' => $folio]);
            $ticketRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ticketRow) {
                echo json_encode(['success' => false, 'message' => 'Ticket no encontrado']);
                exit;
            }

            // Marcar como eliminado (soft delete)
            $delStmt = $pdo->prepare("UPDATE sales_tickets SET deleted_at = NOW(), pickup_status = 'cancelled', updated_at = NOW() WHERE folio = :folio");
            $delStmt->execute([':folio' => $folio]);

            // Log de auditoría
            $logStmt = $pdo->prepare("INSERT INTO ticket_pickup_log (ticket_id, admin_id, action, notes, ip_address, created_at) VALUES (:ticket_id, :admin_id, 'cancelled', :notes, :ip_address, NOW())");
            $logStmt->execute([
                ':ticket_id' => $ticketRow['id'],
                ':admin_id'  => $adminId,
                ':notes'     => $reason,
                ':ip_address'=> $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            echo json_encode(['success' => true, 'message' => 'Ticket eliminado correctamente']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            break;
    }
} catch (Exception $e) {
    error_log('Error en ticket_validation.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
