<?php
/**
 * Modelo: Tickets de Ventas
 * Gestiona el ciclo completo de tickets con folio único, auditoría y rotación
 */

class SalesTicket {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Generar folio único: YYYYMM-XXXXX
     * Donde XXXXX es el número secuencial del mes
     */
    public function generateFolio() {
        $yearMonth = date('Y-m');
        $formattedYearMonth = date('Ym');  // YYYYMM sin guión
        
        try {
            // Obtener o crear contador para el mes
            $stmt = $this->pdo->prepare("
                INSERT INTO ticket_folio_counter (year_month, counter)
                VALUES (:year_month, 0)
                ON DUPLICATE KEY UPDATE counter = counter + 1
            ");
            $stmt->execute([':year_month' => $yearMonth]);
            
            // Obtener el siguiente número
            $stmtGet = $this->pdo->prepare("
                SELECT counter FROM ticket_folio_counter 
                WHERE year_month = :year_month
            ");
            $stmtGet->execute([':year_month' => $yearMonth]);
            $result = $stmtGet->fetch(PDO::FETCH_ASSOC);
            
            $counter = (int)($result['counter'] ?? 0) + 1;
            
            // Formato: YYYYMM-XXXXX (ej: 202604-00123)
            $folio = $formattedYearMonth . '-' . str_pad($counter, 5, '0', STR_PAD_LEFT);
            
            return $folio;
        } catch (Exception $e) {
            error_log("Error generando folio: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Crear un nuevo ticket
     */
    public function createTicket($data) {
        try {
            $folio = $this->generateFolio();
            if (!$folio) {
                return ['success' => false, 'message' => 'Error generando folio'];
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO sales_tickets (
                    folio, order_id, user_id, ticket_type, description,
                    subtotal, tax_amount, discount_amount, total_amount,
                    payment_method, payment_status, issued_by, notes
                ) VALUES (
                    :folio, :order_id, :user_id, :ticket_type, :description,
                    :subtotal, :tax_amount, :discount_amount, :total_amount,
                    :payment_method, :payment_status, :issued_by, :notes
                )
            ");
            
            $result = $stmt->execute([
                ':folio' => $folio,
                ':order_id' => $data['order_id'] ?? null,
                ':user_id' => $data['user_id'],
                ':ticket_type' => $data['ticket_type'] ?? 'sale',
                ':description' => $data['description'] ?? null,
                ':subtotal' => (float)($data['subtotal'] ?? 0),
                ':tax_amount' => (float)($data['tax_amount'] ?? 0),
                ':discount_amount' => (float)($data['discount_amount'] ?? 0),
                ':total_amount' => (float)($data['total_amount'] ?? 0),
                ':payment_method' => $data['payment_method'] ?? null,
                ':payment_status' => $data['payment_status'] ?? 'completed',
                ':issued_by' => $data['issued_by'] ?? $_SESSION['user_id'] ?? null,
                ':notes' => $data['notes'] ?? null
            ]);
            
            if (!$result) {
                return ['success' => false, 'message' => 'Error creando ticket'];
            }
            
            $ticketId = $this->pdo->lastInsertId();
            
            // Agregar items si se proporcionan
            if (!empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $item) {
                    $this->addTicketItem($ticketId, $item);
                }
            }
            
            return [
                'success' => true,
                'message' => 'Ticket creado exitosamente',
                'ticket_id' => $ticketId,
                'folio' => $folio
            ];
        } catch (Exception $e) {
            error_log("Error en createTicket: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error del servidor'];
        }
    }
    
    /**
     * Agregar item a un ticket
     */
    public function addTicketItem($ticketId, $item) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO sales_ticket_items (
                    sales_ticket_id, product_id, sku, product_name,
                    quantity, unit_price, line_total
                ) VALUES (
                    :ticket_id, :product_id, :sku, :product_name,
                    :quantity, :unit_price, :line_total
                )
            ");
            
            return $stmt->execute([
                ':ticket_id' => $ticketId,
                ':product_id' => $item['product_id'] ?? null,
                ':sku' => $item['sku'] ?? null,
                ':product_name' => $item['product_name'] ?? '',
                ':quantity' => (int)($item['quantity'] ?? 1),
                ':unit_price' => (float)($item['unit_price'] ?? 0),
                ':line_total' => (float)($item['line_total'] ?? 0)
            ]);
        } catch (Exception $e) {
            error_log("Error en addTicketItem: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener ticket por folio
     */
    public function getTicketByFolio($folio) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT st.*, u.name as customer_name, u.email
                FROM sales_tickets st
                LEFT JOIN users u ON st.user_id = u.id
                WHERE st.folio = :folio
            ");
            $stmt->execute([':folio' => $folio]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$ticket) {
                return null;
            }
            
            // Obtener items
            $itemsStmt = $this->pdo->prepare("
                SELECT * FROM sales_ticket_items
                WHERE sales_ticket_id = :ticket_id
            ");
            $itemsStmt->execute([':ticket_id' => $ticket['id']]);
            $ticket['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $ticket;
        } catch (Exception $e) {
            error_log("Error en getTicketByFolio: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Listar tickets activos (paginados)
     */
    public function listActiveTickets($page = 1, $perPage = 20, $filters = []) {
        try {
            $offset = ($page - 1) * $perPage;
            
            // Construir query con filtros
            $where = "WHERE st.status = 'active'";
            $params = [];
            
            if (!empty($filters['folio'])) {
                $where .= " AND st.folio LIKE :folio";
                $params[':folio'] = '%' . $filters['folio'] . '%';
            }
            
            if (!empty($filters['ticket_type'])) {
                $where .= " AND st.ticket_type = :ticket_type";
                $params[':ticket_type'] = $filters['ticket_type'];
            }
            
            if (!empty($filters['payment_status'])) {
                $where .= " AND st.payment_status = :payment_status";
                $params[':payment_status'] = $filters['payment_status'];
            }
            
            if (!empty($filters['start_date'])) {
                $where .= " AND DATE(st.issued_date) >= :start_date";
                $params[':start_date'] = $filters['start_date'];
            }
            
            if (!empty($filters['end_date'])) {
                $where .= " AND DATE(st.issued_date) <= :end_date";
                $params[':end_date'] = $filters['end_date'];
            }
            
            // Total
            $countStmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM sales_tickets st $where");
            $countStmt->execute($params);
            $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
            $total = (int)$countResult['total'];
            
            // Datos
            $stmt = $this->pdo->prepare("
                SELECT 
                    st.*,
                    u.name as customer_name,
                    COUNT(sti.id) as item_count
                FROM sales_tickets st
                LEFT JOIN users u ON st.user_id = u.id
                LEFT JOIN sales_ticket_items sti ON st.id = sti.sales_ticket_id
                $where
                GROUP BY st.id
                ORDER BY st.issued_date DESC
                LIMIT $offset, $perPage
            ");
            $stmt->execute($params);
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'tickets' => $tickets,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => ceil($total / $perPage)
                ]
            ];
        } catch (Exception $e) {
            error_log("Error en listActiveTickets: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error obteniendo tickets'];
        }
    }
    
    /**
     * Archiver ticket (rotación mensual)
     * Se ejecuta automáticamente el primer día del mes
     */
    public function archivePreviousMonth() {
        try {
            $lastMonth = date('Y-m', strtotime('-1 month'));
            
            // Obtener tickets del mes anterior
            $stmt = $this->pdo->prepare("
                SELECT * FROM sales_tickets
                WHERE status = 'active' AND DATE_FORMAT(issued_date, '%Y-%m') = :last_month
            ");
            $stmt->execute([':last_month' => $lastMonth]);
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $archived = 0;
            
            foreach ($tickets as $ticket) {
                // Guardar en archivo
                $archiveStmt = $this->pdo->prepare("
                    INSERT INTO sales_tickets_archived (
                        original_ticket_id, folio, ticket_data, archive_reason,
                        total_amount, ticket_type
                    ) VALUES (
                        :ticket_id, :folio, :data, :reason,
                        :total, :type
                    )
                ");
                
                $archiveStmt->execute([
                    ':ticket_id' => $ticket['id'],
                    ':folio' => $ticket['folio'],
                    ':data' => json_encode($ticket),
                    ':reason' => 'Rotación mensual automática',
                    ':total' => $ticket['total_amount'],
                    ':type' => $ticket['ticket_type']
                ]);
                
                // Marcar como archivado
                $updateStmt = $this->pdo->prepare("
                    UPDATE sales_tickets 
                    SET status = 'archived', archived_date = NOW()
                    WHERE id = :id
                ");
                $updateStmt->execute([':id' => $ticket['id']]);
                
                $archived++;
            }
            
            return [
                'success' => true,
                'archived' => $archived,
                'message' => "Se archivaron $archived tickets del mes $lastMonth"
            ];
        } catch (Exception $e) {
            error_log("Error en archivePreviousMonth: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error archivando tickets'];
        }
    }
    
    /**
     * Generar reporte de estadísticas mensuales
     */
    public function generateMonthlyStatistics($yearMonth) {
        try {
            // Calcular totales
            $stmt = $this->pdo->prepare("
                SELECT 
                    SUM(CASE WHEN ticket_type = 'sale' THEN total_amount ELSE 0 END) as total_sales,
                    SUM(CASE WHEN ticket_type = 'return' THEN total_amount ELSE 0 END) as total_returns,
                    SUM(CASE WHEN ticket_type = 'adjustment' THEN total_amount ELSE 0 END) as total_adjustments,
                    COUNT(CASE WHEN ticket_type = 'sale' THEN 1 END) as sale_count,
                    COUNT(CASE WHEN ticket_type = 'return' THEN 1 END) as return_count,
                    COUNT(*) as total_tickets
                FROM sales_tickets
                WHERE DATE_FORMAT(issued_date, '%Y-%m') = :year_month
                AND status IN ('active', 'archived')
            ");
            
            $stmt->execute([':year_month' => $yearMonth]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Guardar en tabla de estadísticas
            $insertStmt = $this->pdo->prepare("
                INSERT INTO sales_monthly_statistics (
                    year_month, total_sales, total_returns, total_adjustments,
                    ticket_count, return_count
                ) VALUES (
                    :year_month, :total_sales, :total_returns, :total_adjustments,
                    :ticket_count, :return_count
                )
                ON DUPLICATE KEY UPDATE
                    total_sales = :total_sales,
                    total_returns = :total_returns,
                    total_adjustments = :total_adjustments,
                    ticket_count = :ticket_count,
                    return_count = :return_count,
                    updated_at = NOW()
            ");
            
            $insertStmt->execute([
                ':year_month' => $yearMonth,
                ':total_sales' => $stats['total_sales'] ?? 0,
                ':total_returns' => $stats['total_returns'] ?? 0,
                ':total_adjustments' => $stats['total_adjustments'] ?? 0,
                ':ticket_count' => $stats['total_tickets'] ?? 0,
                ':return_count' => $stats['return_count'] ?? 0
            ]);
            
            return [
                'success' => true,
                'statistics' => $stats
            ];
        } catch (Exception $e) {
            error_log("Error en generateMonthlyStatistics: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error generando estadísticas'];
        }
    }
    
    /**
     * Agregar entrada en audit log
     */
    public function addAuditLog($ticketId, $action, $oldValues = null, $newValues = null, $reason = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO sales_ticket_audit_log (
                    sales_ticket_id, action, action_by, old_values, new_values, reason
                ) VALUES (
                    :ticket_id, :action, :action_by, :old_values, :new_values, :reason
                )
            ");
            
            return $stmt->execute([
                ':ticket_id' => $ticketId,
                ':action' => $action,
                ':action_by' => $_SESSION['user_id'] ?? null,
                ':old_values' => $oldValues ? json_encode($oldValues) : null,
                ':new_values' => $newValues ? json_encode($newValues) : null,
                ':reason' => $reason
            ]);
        } catch (Exception $e) {
            error_log("Error en addAuditLog: " . $e->getMessage());
            return false;
        }
    }
}
