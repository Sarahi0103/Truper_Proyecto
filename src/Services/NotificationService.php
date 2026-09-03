<?php
/**
 * Sistema de notificaciones con soporte push
 * Gestiona alertas de stock bajo, pedidos, notificaciones push en tiempo real
 */

class NotificationService {
    private $pdo;
    private $logger;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->logger = new AppLogger();
        $this->ensureTables();
    }

    private function ensureTables() {
        try {
            // Tabla de notificaciones
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS notifications (
                    id SERIAL PRIMARY KEY,
                    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
                    type VARCHAR(50) NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    message TEXT NOT NULL,
                    data JSONB,
                    is_read BOOLEAN DEFAULT FALSE,
                    read_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");

            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_notifications_user_id ON notifications(user_id)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_notifications_is_read ON notifications(is_read)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_notifications_type ON notifications(type)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_notifications_created_at ON notifications(created_at DESC)");

            // Tabla de suscripciones push
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS push_subscriptions (
                    id SERIAL PRIMARY KEY,
                    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
                    endpoint VARCHAR(500) NOT NULL,
                    p256dh_key TEXT NOT NULL,
                    auth_key TEXT NOT NULL,
                    user_agent VARCHAR(500),
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(user_id, endpoint)
                )
            ");

            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_push_subscriptions_user_id ON push_subscriptions(user_id)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_push_subscriptions_is_active ON push_subscriptions(is_active)");
        } catch (Exception $e) {
            $this->logger->error("Error creating notifications tables: " . $e->getMessage());
        }
    }

    /**
     * Crea una nueva notificación
     */
    public function create($userId, $type, $title, $message, $data = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message, data)
                VALUES (?, ?, ?, ?, ?) RETURNING id
            ");
            $stmt->execute([
                $userId,
                $type,
                $title,
                $message,
                $data ? json_encode($data) : null
            ]);
            $notificationId = (int)$stmt->fetchColumn();
            
            // Enviar notificación push si el usuario tiene suscripción
            $this->sendPushNotification($userId, $title, $message, $data);
            
            return $notificationId;
        } catch (Exception $e) {
            $this->logger->error("Error creating notification: " . $e->getMessage());
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
                SET is_read = true, read_at = CURRENT_TIMESTAMP
                WHERE id = ? AND user_id = ?
            ");
            return $stmt->execute([$notificationId, $userId]);
        } catch (Exception $e) {
            $this->logger->error("Error marking notification as read: " . $e->getMessage());
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
                WHERE created_at < NOW() - INTERVAL '{$daysOld} days' AND is_read = TRUE
            ");
            return $stmt->execute();
        } catch (Exception $e) {
            $this->logger->error("Error deleting old notifications: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Registrar suscripción push de un usuario
     */
    public function registerPushSubscription($userId, $endpoint, $p256dhKey, $authKey, $userAgent = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO push_subscriptions (user_id, endpoint, p256dh_key, auth_key, user_agent)
                VALUES (?, ?, ?, ?, ?)
                ON CONFLICT (user_id, endpoint) 
                DO UPDATE SET 
                    p256dh_key = EXCLUDED.p256dh_key,
                    auth_key = EXCLUDED.auth_key,
                    is_active = TRUE,
                    updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$userId, $endpoint, $p256dhKey, $authKey, $userAgent]);
            return true;
        } catch (Exception $e) {
            $this->logger->error("Error registering push subscription: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar notificación push a un usuario
     */
    private function sendPushNotification($userId, $title, $message, $data = null) {
        try {
            // Obtener suscripciones activas del usuario
            $stmt = $this->pdo->prepare("
                SELECT endpoint, p256dh_key, auth_key 
                FROM push_subscriptions 
                WHERE user_id = ? AND is_active = TRUE
            ");
            $stmt->execute([$userId]);
            $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($subscriptions)) {
                return false;
            }

            // Preparar payload
            $payload = json_encode([
                'title' => $title,
                'body' => $message,
                'data' => $data,
                'icon' => '/truper_logo2.png',
                'badge' => '/truper_logo2.png'
            ]);

            // Enviar a cada suscripción (requiere librería web-push)
            foreach ($subscriptions as $sub) {
                // Aquí se integraría con librería web-push
                // Por ahora solo log
                $this->logger->info("Push notification prepared for user {$userId}: {$title}");
            }

            return true;
        } catch (Exception $e) {
            $this->logger->error("Error sending push notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Crear notificación masiva para múltiples usuarios
     */
    public function createBulk($userIds, $type, $title, $message, $data = null) {
        try {
            $createdCount = 0;
            foreach ($userIds as $userId) {
                if ($this->create($userId, $type, $title, $message, $data)) {
                    $createdCount++;
                }
            }
            return $createdCount;
        } catch (Exception $e) {
            $this->logger->error("Error creating bulk notifications: " . $e->getMessage());
            return 0;
        }
    }
}
