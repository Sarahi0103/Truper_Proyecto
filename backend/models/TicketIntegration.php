<?php
/**
 * Integración de Tickets con Carrito, Órdenes y WhatsApp
 * Asegura que TODO se guarde en historial automáticamente
 */

class TicketIntegration {
    private $pdo;
    private $ticketModel;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        require_once __DIR__ . '/SalesTicket.php';
        $this->ticketModel = new SalesTicket($pdo);
    }
    
    /**
     * EVENTO 1: Crear ticket automáticamente al finalizar compra
     * Se llama después de Order::create()
     */
    public function onOrderCompleted($orderId, $orderData) {
        try {
            // Obtener datos de la orden
            $stmt = $this->pdo->prepare("
                SELECT o.id, o.user_id, o.total_amount, o.tax_amount, 
                       o.discount_amount, o.payment_method, o.created_at
                FROM orders o
                WHERE o.id = :order_id
            ");
            $stmt->execute([':order_id' => $orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                return ['success' => false, 'message' => 'Orden no encontrada'];
            }
            
            // Crear ticket automático
            $ticketData = [
                'order_id' => $orderId,
                'user_id' => $order['user_id'],
                'ticket_type' => 'sale',
                'subtotal' => $orderData['subtotal'] ?? ($order['total_amount'] - $order['tax_amount']),
                'tax_amount' => $order['tax_amount'],
                'discount_amount' => $order['discount_amount'],
                'total_amount' => $order['total_amount'],
                'payment_method' => $order['payment_method'],
                'payment_status' => 'completed',
                'issued_by' => $order['user_id'],
                'notes' => 'Generado automáticamente de orden #' . $orderId
            ];
            
            $result = $this->ticketModel->createTicket($ticketData);
            
            if ($result['success']) {
                // Agregar items del carrito al ticket
                $this->addOrderItemsToTicket($result['ticket_id'], $orderId);
                
                // Registrar en auditoría
                $this->logEvent($result['ticket_id'], 'auto_created_from_order', 
                    'Ticket generado automáticamente al completar orden #' . $orderId);
                
                return [
                    'success' => true,
                    'ticket_id' => $result['ticket_id'],
                    'folio' => $result['folio'],
                    'message' => 'Ticket generado automáticamente'
                ];
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Error en onOrderCompleted: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error generando ticket automático'];
        }
    }
    
    /**
     * EVENTO 2: Registrar cuando se envía ticket por WhatsApp
     */
    public function onTicketSentViaWhatsApp($ticketId, $phoneNumber, $messageId = null) {
        try {
            // Registrar el envío
            $stmt = $this->pdo->prepare("
                INSERT INTO ticket_whatsapp_sends (
                    ticket_id, phone_number, whatsapp_message_id, 
                    sent_at, created_at
                ) VALUES (
                    :ticket_id, :phone, :msg_id, NOW(), NOW()
                )
            ");
            
            $stmt->execute([
                ':ticket_id' => $ticketId,
                ':phone' => $phoneNumber,
                ':msg_id' => $messageId
            ]);
            
            // Registrar evento en auditoría
            $this->logEvent($ticketId, 'sent_whatsapp', 
                'Comprobante enviado por WhatsApp a ' . $phoneNumber);
            
            return [
                'success' => true,
                'message' => 'Envío registrado en historial'
            ];
        } catch (Exception $e) {
            error_log("Error en onTicketSentViaWhatsApp: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error registrando envío'];
        }
    }
    
    /**
     * EVENTO 3: Registrar cuando se genera comprobante manual
     */
    public function onTicketGenerated($ticketId, $generationType = 'manual') {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO ticket_generations (
                    ticket_id, type, generated_by, generated_at
                ) VALUES (
                    :ticket_id, :type, :user_id, NOW()
                )
            ");
            
            $stmt->execute([
                ':ticket_id' => $ticketId,
                ':type' => $generationType,
                ':user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            // Registrar evento
            $this->logEvent($ticketId, 'generated', 
                'Comprobante generado (' . $generationType . ')');
            
            return ['success' => true];
        } catch (Exception $e) {
            error_log("Error en onTicketGenerated: " . $e->getMessage());
            return ['success' => false];
        }
    }
    
    /**
     * EVENTO 4: Registrar descarga de PDF
     */
    public function onTicketDownloaded($ticketId, $format = 'pdf') {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO ticket_downloads (
                    ticket_id, format, downloaded_by, downloaded_at
                ) VALUES (
                    :ticket_id, :format, :user_id, NOW()
                )
            ");
            
            $stmt->execute([
                ':ticket_id' => $ticketId,
                ':format' => $format,
                ':user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            // Registrar evento
            $this->logEvent($ticketId, 'downloaded', 
                'Descargado en formato ' . strtoupper($format));
            
            return ['success' => true];
        } catch (Exception $e) {
            error_log("Error en onTicketDownloaded: " . $e->getMessage());
            return ['success' => false];
        }
    }
    
    /**
     * Obtener historial completo de un ticket (cliente o admin)
     */
    public function getTicketHistory($ticketId, $userRole = 'client') {
        try {
            // Auditoría del ticket
            $auditSql = "
                SELECT 'audit' as type, tal.action, tal.description, 
                       tal.created_at as timestamp
                FROM ticket_audit_log tal
                WHERE tal.ticket_id = :ticket_id
            ";
            
            // Envíos por WhatsApp
            $whatsappSql = "
                SELECT 'whatsapp' as type, 'Enviado por WhatsApp' as action,
                       CONCAT('A: ', phone_number) as description,
                       sent_at as timestamp
                FROM ticket_whatsapp_sends
                WHERE ticket_id = :ticket_id
            ";
            
            // Descargas
            $downloadsSql = "
                SELECT 'download' as type, 'Descargado' as action,
                       format as description,
                       downloaded_at as timestamp
                FROM ticket_downloads
                WHERE ticket_id = :ticket_id
            ";
            
            // Unir todo
            $sql = "
                ($auditSql)
                UNION ALL
                ($whatsappSql)
                UNION ALL
                ($downloadsSql)
                ORDER BY timestamp DESC
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':ticket_id' => $ticketId]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'history' => $history,
                'total_events' => count($history)
            ];
        } catch (Exception $e) {
            error_log("Error en getTicketHistory: " . $e->getMessage());
            return ['success' => false, 'history' => []];
        }
    }
    
    /**
     * Obtener todos los tickets de un cliente con detalles
     */
    public function getClientTickets($userId, $page = 1, $perPage = 10) {
        try {
            $offset = ($page - 1) * $perPage;
            
            // Contar total
            $countSql = "
                SELECT COUNT(*) as total
                FROM sales_tickets
                WHERE user_id = :user_id AND deleted_at IS NULL
            ";
            
            $countStmt = $this->pdo->prepare($countSql);
            $countStmt->execute([':user_id' => $userId]);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Obtener tickets con información adicional
            $sql = "
                SELECT 
                    st.id, st.folio, st.ticket_type, st.total_amount,
                    st.payment_status, st.issued_date,
                    COUNT(ti.id) as item_count,
                    COUNT(DISTINCT tws.id) as whatsapp_sends,
                    COUNT(DISTINCT td.id) as downloads
                FROM sales_tickets st
                LEFT JOIN ticket_items ti ON st.id = ti.ticket_id
                LEFT JOIN ticket_whatsapp_sends tws ON st.id = tws.ticket_id
                LEFT JOIN ticket_downloads td ON st.id = td.ticket_id
                WHERE st.user_id = :user_id AND st.deleted_at IS NULL
                GROUP BY st.id
                ORDER BY st.issued_date DESC
                LIMIT :limit OFFSET :offset
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':user_id', $userId);
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
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
            error_log("Error en getClientTickets: " . $e->getMessage());
            return ['success' => false, 'tickets' => []];
        }
    }
    
    /**
     * Agregar items de la orden al ticket
     */
    private function addOrderItemsToTicket($ticketId, $orderId) {
        try {
            // Obtener items de la orden
            $stmt = $this->pdo->prepare("
                SELECT oi.product_id, oi.product_name, oi.quantity,
                       oi.unit_price, oi.line_total
                FROM order_items oi
                WHERE oi.order_id = :order_id
            ");
            $stmt->execute([':order_id' => $orderId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Insertar en ticket_items
            foreach ($items as $item) {
                $insertStmt = $this->pdo->prepare("
                    INSERT INTO ticket_items (
                        ticket_id, product_id, product_name,
                        quantity, unit_price, total
                    ) VALUES (
                        :ticket_id, :product_id, :product_name,
                        :quantity, :unit_price, :total
                    )
                ");
                
                $insertStmt->execute([
                    ':ticket_id' => $ticketId,
                    ':product_id' => $item['product_id'],
                    ':product_name' => $item['product_name'],
                    ':quantity' => $item['quantity'],
                    ':unit_price' => $item['unit_price'],
                    ':total' => $item['line_total']
                ]);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Error en addOrderItemsToTicket: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Registrar evento en auditoría
     */
    private function logEvent($ticketId, $action, $description) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO ticket_audit_log (
                    ticket_id, action, description, admin_id, ip_address, created_at
                ) VALUES (
                    :ticket_id, :action, :description, :admin_id, :ip, NOW()
                )
            ");
            
            $stmt->execute([
                ':ticket_id' => $ticketId,
                ':action' => $action,
                ':description' => $description,
                ':admin_id' => $_SESSION['user_id'] ?? null,
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            error_log("Error en logEvent: " . $e->getMessage());
        }
    }
    
    /**
     * Crear tablas adicionales necesarias si no existen
     */
    public static function createTables($pdo) {
        try {
            // Tabla de envíos WhatsApp
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS ticket_whatsapp_sends (
                    id SERIAL PRIMARY KEY,
                    ticket_id INT NOT NULL REFERENCES sales_tickets(id) ON DELETE CASCADE,
                    phone_number VARCHAR(20),
                    whatsapp_message_id VARCHAR(100),
                    sent_at TIMESTAMP,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            // Tabla de generaciones de comprobantes
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS ticket_generations (
                    id SERIAL PRIMARY KEY,
                    ticket_id INT NOT NULL REFERENCES sales_tickets(id) ON DELETE CASCADE,
                    type VARCHAR(50),
                    generated_by INT REFERENCES users(id) ON DELETE SET NULL,
                    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            // Tabla de descargas
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS ticket_downloads (
                    id SERIAL PRIMARY KEY,
                    ticket_id INT NOT NULL REFERENCES sales_tickets(id) ON DELETE CASCADE,
                    format VARCHAR(20),
                    downloaded_by INT REFERENCES users(id) ON DELETE SET NULL,
                    downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            // Índices
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_whatsapp_ticket ON ticket_whatsapp_sends(ticket_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_generations_ticket ON ticket_generations(ticket_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_downloads_ticket ON ticket_downloads(ticket_id)");
            
            return ['success' => true, 'message' => 'Tablas creadas correctamente'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
