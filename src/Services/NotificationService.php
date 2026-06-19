<?php
/**
 * Sistema de notificaciones básico
 * Gestiona alertas de stock bajo, pedidos, etc.
 */

class NotificationService {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->ensureTable();
    }

    private function ensureTable() {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS notifications (
                    id SERIAL PRIMARY KEY,
                    user_id INTEGER,
                    type VARCHAR(50) NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    message TEXT NOT NULL,
                    data_json TEXT,
                    is_read BOOLEAN DEFAULT false,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");

            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_notifications_user_id ON notifications(user_id)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_notifications_is_read ON notifications(is_read)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_notifications_created_at ON notifications(created_at)");
        } catch (Exception $e) {
            error_log("Error creating notifications table: " . $e->getMessage());
        }
    }

    /**
     * Crea una nueva notificación
     */
    public function create($userId, $type, $title, $message, $data = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message, data_json)
                VALUES (?, ?, ?, ?, ?) RETURNING id
            ");
            $stmt->execute([
                $userId,
                $type,
                $title,
                $message,
                $data ? json_encode($data) : null
            ]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Error creating notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene notificaciones de un usuario
     */
    public function getUserNotifications($userId, $limit = 20, $unreadOnly = false) {
        try {
            $sql = "SELECT * FROM notifications WHERE user_id = ?";
            $params = [$userId];

            if ($unreadOnly) {
                $sql .= " AND is_read = false";
            }

            $sql .= " ORDER BY created_at DESC LIMIT ?";
            $params[] = $limit;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting notifications: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Marca notificación como leída
     */
    public function markAsRead($notificationId, $userId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE notifications 
                SET is_read = true 
                WHERE id = ? AND user_id = ?
            ");
            return $stmt->execute([$notificationId, $userId]);
        } catch (Exception $e) {
            error_log("Error marking notification as read: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Marca todas las notificaciones de un usuario como leídas
     */
    public function markAllAsRead($userId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE notifications 
                SET is_read = true 
                WHERE user_id = ? AND is_read = false
            ");
            return $stmt->execute([$userId]);
        } catch (Exception $e) {
            error_log("Error marking all notifications as read: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cuenta notificaciones no leídas
     */
    public function getUnreadCount($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM notifications 
                WHERE user_id = ? AND is_read = false
            ");
            $stmt->execute([$userId]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Error getting unread count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Notifica stock bajo
     */
    public function notifyLowStock($sku, $currentStock, $reorderLevel, $userId = null) {
        if ($currentStock <= $reorderLevel) {
            $title = "Stock bajo: {$sku}";
            $message = "El producto {$sku} tiene solo {$currentStock} unidades (reorden: {$reorderLevel})";
            
            // Si no se especifica usuario, notificar a todos los admins
            if ($userId === null) {
                $admins = $this->getAdminUsers();
                foreach ($admins as $admin) {
                    $this->create($admin['id'], 'STOCK_LOW', $title, $message, [
                        'sku' => $sku,
                        'current_stock' => $currentStock,
                        'reorder_level' => $reorderLevel
                    ]);
                }
            } else {
                $this->create($userId, 'STOCK_LOW', $title, $message, [
                    'sku' => $sku,
                    'current_stock' => $currentStock,
                    'reorder_level' => $reorderLevel
                ]);
            }
        }
    }

    /**
     * Notifica nuevo pedido
     */
    public function notifyNewOrder($orderId, $clientName, $totalAmount, $userId = null) {
        $title = "Nuevo pedido #{$orderId}";
        $message = "Pedido de {$clientName} por $" . number_format($totalAmount, 2);
        
        if ($userId === null) {
            $admins = $this->getAdminUsers();
            foreach ($admins as $admin) {
                $this->create($admin['id'], 'NEW_ORDER', $title, $message, [
                    'order_id' => $orderId,
                    'client_name' => $clientName,
                    'total_amount' => $totalAmount
                ]);
            }
        } else {
            $this->create($userId, 'NEW_ORDER', $title, $message, [
                'order_id' => $orderId,
                'client_name' => $clientName,
                'total_amount' => $totalAmount
            ]);
        }
    }

    /**
     * Obtiene usuarios admin
     */
    private function getAdminUsers() {
        try {
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE role = 'admin'");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting admin users: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Elimina notificaciones antiguas
     */
    public function deleteOldNotifications($daysOld = 30) {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM notifications 
                WHERE created_at < NOW() - INTERVAL '{$daysOld} days'
            ");
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error deleting old notifications: " . $e->getMessage());
            return false;
        }
    }
}
