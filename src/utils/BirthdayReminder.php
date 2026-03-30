<?php
/**
 * Sistema de Recordatorios de Cumpleaños
 * Se ejecuta diariamente para enviar notificaciones
 */

require_once 'config/config.php';

class BirthdayReminder {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Buscar cumpleaños de hoy
     */
    public function checkBirthdaysToday() {
        $today = date('m-d');
        
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.email, u.first_name, u.loyalty_points
            FROM users u
            WHERE TO_CHAR(u.birthdate, 'MM-DD') = ?
            AND u.is_active = true
        ");
        
        $stmt->execute([$today]);
        return $stmt->fetchAll();
    }
    
    /**
     * Crear bonificación de cumpleaños
     */
    public function createBirthdayBonus($user_id, $client_id) {
        try {
            // Bonificación: 50 puntos + 10% descuento
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET loyalty_points = loyalty_points + 50 
                WHERE id = ?
            ");
            $stmt->execute([$user_id]);
            
            // Crear código de promoción
            $stmt = $this->pdo->prepare("
                INSERT INTO promotions 
                (client_id, promotion_type, discount_percentage, expiry_date)
                VALUES (?, 'birthday_bonus', 10, ?)
            ");
            
            $expiry = date('Y-m-d', strtotime('+30 days'));
            $stmt->execute([$client_id, $expiry]);
            
            return true;
        } catch (PDOException $e) {
            error_log("Error creating birthday bonus: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enviar notificación de cumpleaños
     */
    public function sendBirthdayNotification($user) {
        // Aquí se implementaría el envío de email
        // Por ahora, solo registramos
        
        error_log("Birthday notification would be sent to: {$user['email']}");
        
        return [
            'user_id' => $user['id'],
            'name' => $user['first_name'],
            'points_added' => 50,
            'discount_percentage' => 10,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Ejecutar proceso completo
     */
    public function processBirthdayReminders() {
        $birthdays = $this->checkBirthdaysToday();
        $processed = 0;
        
        foreach ($birthdays as $user) {
            // Obtener cliente_id
            $stmt = $this->pdo->prepare("SELECT id FROM clients WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            $client = $stmt->fetch();
            
            if ($client) {
                if ($this->createBirthdayBonus($user['id'], $client['id'])) {
                    $this->sendBirthdayNotification($user);
                    $processed++;
                }
            }
        }
        
        error_log("Birthday reminders processed: $processed");
        
        return ['processed' => $processed, 'timestamp' => date('Y-m-d H:i:s')];
    }
}

// Ejecutar si se llama directamente (desde cron job)
if (php_sapi_name() === 'cli' || isset($_GET['run_reminders'])) {
    $reminder = new BirthdayReminder($pdo);
    $result = $reminder->processBirthdayReminders();
    echo json_encode($result);
}
?>
