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
        $formattedYearMonth = date('Ym');

        try {
            $stmt = $this->pdo->prepare("\n                INSERT INTO ticket_folio_counter (year_month, counter)\n                VALUES (:year_month, 0)\n                ON DUPLICATE KEY UPDATE counter = counter + 1\n            ");
            $stmt->execute([':year_month' => $yearMonth]);

            $stmtGet = $this->pdo->prepare("\n                SELECT counter FROM ticket_folio_counter\n                WHERE year_month = :year_month\n            ");
            $stmtGet->execute([':year_month' => $yearMonth]);
            $result = $stmtGet->fetch(PDO::FETCH_ASSOC);

            $counter = (int)($result['counter'] ?? 0) + 1;
            return $formattedYearMonth . '-' . str_pad($counter, 5, '0', STR_PAD_LEFT);
        } catch (Exception $e) {
            error_log('Error generando folio: ' . $e->getMessage());
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

            $stmt = $this->pdo->prepare("\n                INSERT INTO sales_tickets (\n                    folio, order_id, user_id, ticket_type, description,\n                    subtotal_amount, tax_amount, discount_amount, total_amount,\n                    payment_method, payment_status, issued_by, notes\n                ) VALUES (\n                    :folio, :order_id, :user_id, :ticket_type, :description,\n                    :subtotal_amount, :tax_amount, :discount_amount, :total_amount,\n                    :payment_method, :payment_status, :issued_by, :notes\n                )\n            ");

            $result = $stmt->execute([
                ':folio' => $folio,
                ':order_id' => $data['order_id'] ?? null,
                ':user_id' => $data['user_id'],
                ':ticket_type' => $data['ticket_type'] ?? 'sale',
                ':description' => $data['description'] ?? null,
                ':subtotal_amount' => (float)($data['subtotal_amount'] ?? $data['subtotal'] ?? 0),
                ':tax_amount' => (float)($data['tax_amount'] ?? 0),
                ':discount_amount' => (float)($data['discount_amount'] ?? 0),
                ':total_amount' => (float)($data['total_amount'] ?? 0),
                ':payment_method' => $data['payment_method'] ?? null,
                ':payment_status' => $data['payment_status'] ?? 'completed',
                ':issued_by' => $data['issued_by'] ?? ($_SESSION['user_id'] ?? null),
                ':notes' => $data['notes'] ?? null
            ]);

            if (!$result) {
                return ['success' => false, 'message' => 'Error creando ticket'];
            }

            $ticketId = $this->pdo->lastInsertId();

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
            error_log('Error en createTicket: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error del servidor'];
        }
    }

    /**
     * Agregar item a un ticket
     */
    public function addTicketItem($ticketId, $item) {
        try {
            $stmt = $this->pdo->prepare("\n                INSERT INTO ticket_items (\n                    ticket_id, product_id, product_name,\n                    quantity, unit_price, total, discount\n                ) VALUES (\n                    :ticket_id, :product_id, :product_name,\n                    :quantity, :unit_price, :total, :discount\n                )\n            ");

            return $stmt->execute([
                ':ticket_id' => $ticketId,
                ':product_id' => $item['product_id'] ?? null,
                ':product_name' => $item['product_name'] ?? ($item['name'] ?? ''),
                ':quantity' => (int)($item['quantity'] ?? 1),
                ':unit_price' => (float)($item['unit_price'] ?? $item['price'] ?? 0),
                ':total' => (float)($item['line_total'] ?? $item['total'] ?? 0),
                ':discount' => (float)($item['discount'] ?? 0)
            ]);
        } catch (Exception $e) {
            error_log('Error en addTicketItem: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener ticket por folio
     */
    public function getTicketByFolio($folio) {
        try {
            $stmt = $this->pdo->prepare("\n                SELECT st.*, u.name as customer_name, u.email\n                FROM sales_tickets st\n                LEFT JOIN users u ON st.user_id = u.id\n                WHERE st.folio = :folio\n            ");
            $stmt->execute([':folio' => $folio]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ticket) {
                return null;
            }

            $itemsStmt = $this->pdo->prepare("\n                SELECT * FROM ticket_items\n                WHERE ticket_id = :ticket_id\n            ");
            $itemsStmt->execute([':ticket_id' => $ticket['id']]);
            $ticket['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            return $ticket;
        } catch (Exception $e) {
            error_log('Error en getTicketByFolio: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Listar tickets activos (paginados)
     */
    public function listActiveTickets($page = 1, $perPage = 20, $filters = []) {
        try {
            $offset = ($page - 1) * $perPage;
            $where = 'WHERE st.deleted_at IS NULL AND st.archived_at IS NULL';
            $params = [];

            if (!empty($filters['folio'])) {
                $where .= ' AND st.folio LIKE :folio';
                $params[':folio'] = '%' . $filters['folio'] . '%';
            }

            if (!empty($filters['ticket_type'])) {
                $where .= ' AND st.ticket_type = :ticket_type';
                $params[':ticket_type'] = $filters['ticket_type'];
            }

            if (!empty($filters['payment_status'])) {
                $where .= ' AND st.payment_status = :payment_status';
                $params[':payment_status'] = $filters['payment_status'];
            }

            if (!empty($filters['start_date'])) {
                $where .= ' AND DATE(st.issued_date) >= :start_date';
                $params[':start_date'] = $filters['start_date'];
            }

            if (!empty($filters['end_date'])) {
                $where .= ' AND DATE(st.issued_date) <= :end_date';
                $params[':end_date'] = $filters['end_date'];
            }

            $countStmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM sales_tickets st $where");
            $countStmt->execute($params);
            $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
            $total = (int)($countResult['total'] ?? 0);

            $stmt = $this->pdo->prepare("\n                SELECT \n                    st.*,\n                    u.name as customer_name,\n                    COUNT(sti.id) as item_count\n                FROM sales_tickets st\n                LEFT JOIN users u ON st.user_id = u.id\n                LEFT JOIN ticket_items sti ON st.id = sti.ticket_id\n                $where\n                GROUP BY st.id, u.name\n                ORDER BY st.issued_date DESC\n                LIMIT $offset, $perPage\n            ");
            $stmt->execute($params);
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'tickets' => $tickets,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => (int)ceil($total / max($perPage, 1))
                ]
            ];
        } catch (Exception $e) {
            error_log('Error en listActiveTickets: ' . $e->getMessage());
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

            $stmt = $this->pdo->prepare("\n                SELECT id\n                FROM sales_tickets\n                WHERE deleted_at IS NULL\n                  AND archived_at IS NULL\n                  AND DATE_FORMAT(issued_date, '%Y-%m') = :last_month\n            ");
            $stmt->execute([':last_month' => $lastMonth]);
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $archived = 0;
            foreach ($tickets as $ticket) {
                $updateStmt = $this->pdo->prepare("\n                    UPDATE sales_tickets\n                    SET archived_at = NOW()\n                    WHERE id = :id\n                ");
                $updateStmt->execute([':id' => $ticket['id']]);
                $archived++;
            }

            return [
                'success' => true,
                'archived' => $archived,
                'message' => "Se archivaron $archived tickets del mes $lastMonth"
            ];
        } catch (Exception $e) {
            error_log('Error en archivePreviousMonth: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error archivando tickets'];
        }
    }

    /**
     * Generar reporte de estadísticas mensuales
     */
    public function generateMonthlyStatistics($yearMonth) {
        try {
            $stmt = $this->pdo->prepare("\n                SELECT \n                    SUM(CASE WHEN ticket_type = 'sale' THEN total_amount ELSE 0 END) as total_sales,\n                    SUM(CASE WHEN ticket_type = 'return' THEN total_amount ELSE 0 END) as total_returns,\n                    SUM(CASE WHEN ticket_type = 'adjustment' THEN total_amount ELSE 0 END) as total_adjustments,\n                    COUNT(CASE WHEN ticket_type = 'sale' THEN 1 END) as sale_count,\n                    COUNT(CASE WHEN ticket_type = 'return' THEN 1 END) as return_count,\n                    COUNT(*) as total_tickets\n                FROM sales_tickets\n                WHERE DATE_FORMAT(issued_date, '%Y-%m') = :year_month\n                  AND deleted_at IS NULL\n            ");
            $stmt->execute([':year_month' => $yearMonth]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            $insertStmt = $this->pdo->prepare("\n                INSERT INTO sales_monthly_statistics (\n                    year_month, total_sales, total_returns, total_adjustments,\n                    ticket_count, return_count\n                ) VALUES (\n                    :year_month, :total_sales, :total_returns, :total_adjustments,\n                    :ticket_count, :return_count\n                )\n                ON DUPLICATE KEY UPDATE\n                    total_sales = :total_sales,\n                    total_returns = :total_returns,\n                    total_adjustments = :total_adjustments,\n                    ticket_count = :ticket_count,\n                    return_count = :return_count,\n                    updated_at = NOW()\n            ");

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
            error_log('Error en generateMonthlyStatistics: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error generando estadísticas'];
        }
    }

    /**
     * Agregar entrada en audit log
     */
    public function addAuditLog($ticketId, $action, $oldValues = null, $newValues = null, $reason = null) {
        try {
            $stmt = $this->pdo->prepare("\n                INSERT INTO ticket_audit_log (\n                    ticket_id, action, description, admin_id, old_value, new_value, ip_address, created_at\n                ) VALUES (\n                    :ticket_id, :action, :description, :admin_id, :old_value, :new_value, :ip_address, NOW()\n                )\n            ");

            return $stmt->execute([
                ':ticket_id' => $ticketId,
                ':action' => $action,
                ':description' => $reason,
                ':admin_id' => $_SESSION['user_id'] ?? null,
                ':old_value' => $oldValues ? json_encode($oldValues) : null,
                ':new_value' => $newValues ? json_encode($newValues) : null,
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            error_log('Error en addAuditLog: ' . $e->getMessage());
            return false;
        }
    }
}
