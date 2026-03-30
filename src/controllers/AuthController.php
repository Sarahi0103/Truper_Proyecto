<?php
/**
 * Controlador de Autenticación
 */

class AuthController {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function register($data) {
        try {
            // Validar datos
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Email inválido'];
            }
            
            if (strlen($data['password']) < 8) {
                return ['success' => false, 'message' => 'Contraseña debe tener al menos 8 caracteres'];
            }
            
            if ($data['password'] !== $data['confirm_password']) {
                return ['success' => false, 'message' => 'Las contraseñas no coinciden'];
            }
            
            // Verificar si el email ya existe
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$data['email']]);
            
            if ($stmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'El email ya está registrado'];
            }
            
            // Hash de contraseña
            $password_hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            
            // Insertar usuario
            $stmt = $this->pdo->prepare("
                INSERT INTO users (email, password_hash, first_name, last_name, role, phone, is_active, is_verified)
                VALUES (?, ?, ?, ?, ?, ?, true, false)
            ");
            
            $stmt->execute([
                $data['email'],
                $password_hash,
                htmlspecialchars($data['first_name']),
                htmlspecialchars($data['last_name']),
                'client',
                htmlspecialchars($data['phone'] ?? '')
            ]);
            
            $user_id = $this->pdo->lastInsertId();
            
            // Crear perfil de cliente
            $stmt = $this->pdo->prepare("
                INSERT INTO clients (user_id, company_name)
                VALUES (?, ?)
            ");
            
            $stmt->execute([
                $user_id,
                htmlspecialchars($data['company_name'] ?? '')
            ]);
            
            // Enviar email de verificación
            $this->sendVerificationEmail($data['email'], $user_id);
            
            return [
                'success' => true,
                'message' => 'Registro exitoso. Por favor verifica tu email.',
                'user_id' => $user_id
            ];
        } catch (PDOException $e) {
            error_log("Error en registro: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al registrar. Intenta de nuevo.'];
        }
    }
    
    public function login($email, $password) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, email, password_hash, role, first_name, last_name, is_active, loyalty_points 
                FROM users 
                WHERE email = ? AND is_active = true AND is_verified = true
            ");
            
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($password, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Email o contraseña incorrectos'];
            }
            
            // Actualizar último login
            $stmt = $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            // Crear sesión
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['loyalty_points'] = $user['loyalty_points'];
            $_SESSION['login_time'] = time();
            
            return [
                'success' => true,
                'message' => 'Bienvenido ' . $user['first_name'],
                'role' => $user['role']
            ];
        } catch (PDOException $e) {
            error_log("Error en login: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al iniciar sesión'];
        }
    }
    
    public function logout() {
        session_destroy();
        return ['success' => true, 'message' => 'Sesión cerrada exitosamente'];
    }
    
    private function sendVerificationEmail($email, $user_id) {
        // Aquí iría la lógica para enviar email de verificación
        // Por ahora, verificar automáticamente para desarrollo
        $stmt = $this->pdo->prepare("UPDATE users SET is_verified = true WHERE id = ?");
        $stmt->execute([$user_id]);
    }
    
    public function verifyEmail($user_id, $token) {
        try {
            $stmt = $this->pdo->prepare("UPDATE users SET is_verified = true WHERE id = ?");
            $stmt->execute([$user_id]);
            
            return ['success' => true, 'message' => 'Email verificado correctamente'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error al verificar email'];
        }
    }
}
?>
