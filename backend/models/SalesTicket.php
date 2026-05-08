<?php
/**
 * Modelo: Tickets de Ventas
 * Gestiona el ciclo completo de tickets con folio único, auditoría y rotación.
 */

class SalesTicket {
    private PDO $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function generateFolio() {
        $yearMonth = date('Y-m');
        $formattedYearMonth = date('Ym');

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare("INSERT INTO ticket_folio_counter (year_month, counter) VALUES (:year_month, 1) ON CONFLICT (year_month) DO UPDATE SET counter = ticket_folio_counter.counter + 1 RETURNING counter");
            $stmt->execute([':year_month' => $yearMonth]);
            $counter = (int)$stmt->fetchColumn();
            $this->pdo->commit();

            return $formattedYearMonth . '-' . str_pad($counter, 5, '0', STR_PAD_LEFT);
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Error generando folio: ' . $e->getMessage());
            return null;
        }
    }

    public function createTicket($data) {
        try {
            $folio = $this->generateFolio();
            if (!$folio) {
                return ['success' => false, 'message' => 'Error generando folio'];
            }

            $stmt = $this->pdo->prepare("INSERT INTO sales_tickets (folio, order_id, user_id, ticket_type, description, subtotal_amount, tax_amount, discount_amount, total_amount, payment_method, payment_status, issued_by, notes, issued_date, created_at, updated_at) VALUES (:folio, :order_id, :user_id, :ticket_type, :description, :subtotal_amount, :tax_amount, :discount_amount, :total_amount, :payment_method, :payment_status, :issued_by, :notes, NOW(), NOW(), NOW())");

            $ok = $stmt->execute([
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
                ':notes' => $data['notes'] ?? null,
            ]);

            if (!$ok) {
                return ['success' => false, 'message' => 'Error creando ticket'];
            }

            $ticketId = (int)$this->pdo->lastInsertId();

            if (!empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $item) {
                    $this->addTicketItem($ticketId, $item);
                }
            }

            return [
                'success' => true,
                'message' => 'Ticket creado exitosamente',
                'ticket_id' => $ticketId,
                'folio' => $folio,
            ];
        } catch (Exception $e) {
            error_log('Error en createTicket: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error del servidor'];
        }
    }

    public function addTicketItem($ticketId, $item) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO ticket_items (ticket_id, product_id, product_name, quantity, unit_price, total, discount, created_at) VALUES (:ticket_id, :product_id, :product_name, :quantity, :unit_price, :total, :discount, NOW())");

            return $stmt->execute([
                ':ticket_id' => $ticketId,
                ':product_id' => $item['product_id'] ?? null,
                ':product_name' => $item['product_name'] ?? ($item['name'] ?? ''),
                ':quantity' => (int)($item['quantity'] ?? 1),
                ':unit_price' => (float)($item['unit_price'] ?? $item['price'] ?? 0),
                ':total' => (float)($item['line_total'] ?? $item['total'] ?? 0),
                ':discount' => (float)($item['discount'] ?? 0),
            ]);
        } catch (Exception $e) {
            error_log('Error en addTicketItem: ' . $e->getMessage());
            return false;
        }
    }

    public function getTicketByFolio($folio) {
        try {
            $stmt = $this->pdo->prepare("SELECT st.*, u.first_name || CASE WHEN u.last_name IS NOT NULL AND u.last_name <> '' THEN ' ' || u.last_name ELSE '' END AS customer_name, u.email FROM sales_tickets st LEFT JOIN users u ON st.user_id = u.id WHERE st.folio = :folio");
            $stmt->execute([':folio' => $folio]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ticket) {
                return null;
            }

            $itemsStmt = $this->pdo->prepare("SELECT * FROM ticket_items WHERE ticket_id = :ticket_id");
            $itemsStmt->execute([':ticket_id' => $ticket['id']]);
            $ticket['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            return $ticket;
        } catch (Exception $e) {
            error_log('Error en getTicketByFolio: ' . $e->getMessage());
            return null;
        }
    }

    public function listActiveTickets($page = 1, $perPage = 20, $filters = []) {
        try {
            $offset = max(0, ($page - 1) * $perPage);
            $where = 'WHERE st.deleted_at IS NULL AND st.archived_at IS NULL';
            $params = [];

            if (!empty($filters['folio'])) {
                $where .= ' AND st.folio ILIKE :folio';
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

            $stmt = $this->pdo->prepare("SELECT st.*, u.first_name || CASE WHEN u.last_name IS NOT NULL AND u.last_name <> '' THEN ' ' || u.last_name ELSE '' END AS customer_name, COUNT(sti.id) as item_count FROM sales_tickets st LEFT JOIN users u ON st.user_id = u.id LEFT JOIN ticket_items sti ON st.id = sti.ticket_id $where GROUP BY st.id, u.first_name, u.last_name, u.email ORDER BY st.issued_date DESC LIMIT :limit OFFSET :offset");
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'tickets' => $tickets,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => (int)ceil($total / max($perPage, 1)),
                ],
            ];
        } catch (Exception $e) {
            error_log('Error en listActiveTickets: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error obteniendo tickets'];
        }
    }

    public function archivePreviousMonth() {
        try {
            $lastMonth = date('Y-m', strtotime('-1 month'));

            $stmt = $this->pdo->prepare("SELECT id FROM sales_tickets WHERE deleted_at IS NULL AND archived_at IS NULL AND TO_CHAR(issued_date, 'YYYY-MM') = :last_month");
            $stmt->execute([':last_month' => $lastMonth]);
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $archived = 0;
            foreach ($tickets as $ticket) {
                $updateStmt = $this->pdo->prepare("UPDATE sales_tickets SET archived_at = NOW() WHERE id = :id");
                $updateStmt->execute([':id' => $ticket['id']]);
                $archived++;
            }

            return [
                'success' => true,
                'archived' => $archived,
                'message' => "Se archivaron $archived tickets del mes $lastMonth",
            ];
        } catch (Exception $e) {
            error_log('Error en archivePreviousMonth: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error archivando tickets'];
        }
    }

    public function generateMonthlyStatistics($yearMonth) {
        try {
            $stmt = $this->pdo->prepare("SELECT SUM(CASE WHEN ticket_type = 'sale' THEN total_amount ELSE 0 END) as total_sales, SUM(CASE WHEN ticket_type = 'return' THEN total_amount ELSE 0 END) as total_returns, SUM(CASE WHEN ticket_type = 'adjustment' THEN total_amount ELSE 0 END) as total_adjustments, COUNT(CASE WHEN ticket_type = 'sale' THEN 1 END) as sale_count, COUNT(CASE WHEN ticket_type = 'return' THEN 1 END) as return_count, COUNT(*) as total_tickets FROM sales_tickets WHERE TO_CHAR(issued_date, 'YYYY-MM') = :year_month AND deleted_at IS NULL");
            $stmt->execute([':year_month' => $yearMonth]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $insertStmt = $this->pdo->prepare("INSERT INTO sales_monthly_statistics (year_month, total_sales, total_returns, total_adjustments, ticket_count, return_count, created_at, updated_at) VALUES (:year_month, :total_sales, :total_returns, :total_adjustments, :ticket_count, :return_count, NOW(), NOW()) ON CONFLICT (year_month) DO UPDATE SET total_sales = EXCLUDED.total_sales, total_returns = EXCLUDED.total_returns, total_adjustments = EXCLUDED.total_adjustments, ticket_count = EXCLUDED.ticket_count, return_count = EXCLUDED.return_count, updated_at = NOW()");
            $insertStmt->execute([
                ':year_month' => $yearMonth,
                ':total_sales' => $stats['total_sales'] ?? 0,
                ':total_returns' => $stats['total_returns'] ?? 0,
                ':total_adjustments' => $stats['total_adjustments'] ?? 0,
                ':ticket_count' => $stats['total_tickets'] ?? 0,
                ':return_count' => $stats['return_count'] ?? 0,
            ]);

            return [
                'success' => true,
                'statistics' => $stats,
            ];
        } catch (Exception $e) {
            error_log('Error en generateMonthlyStatistics: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error generando estadísticas'];
        }
    }

    public function addAuditLog($ticketId, $action, $oldValues = null, $newValues = null, $reason = null) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO ticket_audit_log (ticket_id, action, description, admin_id, old_value, new_value, ip_address, created_at) VALUES (:ticket_id, :action, :description, :admin_id, :old_value, :new_value, :ip_address, NOW())");

            return $stmt->execute([
                ':ticket_id' => $ticketId,
                ':action' => $action,
                ':description' => $reason,
                ':admin_id' => $_SESSION['user_id'] ?? null,
                ':old_value' => $oldValues ? json_encode($oldValues) : null,
                ':new_value' => $newValues ? json_encode($newValues) : null,
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);
        } catch (Exception $e) {
            error_log('Error en addAuditLog: ' . $e->getMessage());
            return false;
        }
    }
}
