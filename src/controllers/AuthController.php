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
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Email inválido'];
            }
            
            if (strlen($data['password']) < 8) {
                return ['success' => false, 'message' => 'Contraseña debe tener al menos 8 caracteres'];
            }

            if (!preg_match('/[A-Za-z]/', $data['password']) || !preg_match('/\d/', $data['password'])) {
                return ['success' => false, 'message' => 'La contraseña debe incluir letras y números'];
            }
            
            if ($data['password'] !== $data['confirm_password']) {
                return ['success' => false, 'message' => 'Las contraseñas no coinciden'];
            }
            
            $existing = $this->getUserByEmail($data['email']);
            if ($existing) {
                return ['success' => false, 'message' => 'El email ya está registrado'];
            }

            $password_hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);

            $columns = ['email'];
            $values = [$data['email']];

            $passwordColumn = $this->columnExists('users', 'password_hash') ? 'password_hash' : 'password';
            $columns[] = $passwordColumn;
            $values[] = $password_hash;

            $firstName = trim((string)($data['first_name'] ?? ''));
            $lastName = trim((string)($data['last_name'] ?? ''));
            $fullName = trim($firstName . ' ' . $lastName);

            if ($this->columnExists('users', 'first_name')) {
                $columns[] = 'first_name';
                $values[] = $firstName;
            }
            if ($this->columnExists('users', 'last_name')) {
                $columns[] = 'last_name';
                $values[] = $lastName;
            }
            if ($this->columnExists('users', 'name')) {
                $columns[] = 'name';
                $values[] = $fullName !== '' ? $fullName : $data['email'];
            }
            if ($this->columnExists('users', 'role')) {
                $columns[] = 'role';
                $values[] = 'client';
            }
            if ($this->columnExists('users', 'phone')) {
                $columns[] = 'phone';
                $values[] = trim((string)($data['phone'] ?? ''));
            }
            if ($this->columnExists('users', 'birthdate')) {
                $columns[] = 'birthdate';
                $values[] = !empty($data['birthdate']) ? $data['birthdate'] : null;
            }
            if ($this->columnExists('users', 'birthday')) {
                $columns[] = 'birthday';
                $values[] = !empty($data['birthdate']) ? $data['birthdate'] : null;
            }
            if ($this->columnExists('users', 'loyalty_points')) {
                $columns[] = 'loyalty_points';
                $values[] = 0;
            }
            if ($this->columnExists('users', 'points')) {
                $columns[] = 'points';
                $values[] = 0;
            }
            if ($this->columnExists('users', 'is_active')) {
                $columns[] = 'is_active';
                $values[] = true;
            }
            if ($this->columnExists('users', 'active')) {
                $columns[] = 'active';
                $values[] = 1;
            }
            if ($this->columnExists('users', 'is_verified')) {
                $columns[] = 'is_verified';
                $values[] = true;
            }

            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $sql = sprintf(
                'INSERT INTO users (%s) VALUES (%s)',
                implode(', ', $columns),
                $placeholders
            );

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($values);
            
            $user_id = $this->pdo->lastInsertId();

            if ($this->tableExists('clients') && $this->columnExists('clients', 'user_id')) {
                $clientColumns = ['user_id'];
                $clientValues = [$user_id];
                if ($this->columnExists('clients', 'company_name')) {
                    $clientColumns[] = 'company_name';
                    $clientValues[] = trim((string)($data['company_name'] ?? ''));
                }

                $clientSql = sprintf(
                    'INSERT INTO clients (%s) VALUES (%s)',
                    implode(', ', $clientColumns),
                    implode(', ', array_fill(0, count($clientColumns), '?'))
                );

                $clientStmt = $this->pdo->prepare($clientSql);
                $clientStmt->execute($clientValues);
            }
            
            return [
                'success' => true,
                'message' => 'Registro exitoso. Ya puedes iniciar sesión.',
                'user_id' => $user_id
            ];
        } catch (PDOException $e) {
            error_log("Error en registro: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al registrar. Intenta de nuevo.'];
        }
    }
    
    public function login($email, $password) {
        try {
            $user = $this->getUserByEmail($email);
            if (!$user) {
                return ['success' => false, 'message' => 'Email o contraseña incorrectos'];
            }

            if (array_key_exists('is_active', $user) && !$user['is_active']) {
                return ['success' => false, 'message' => 'Tu cuenta está desactivada'];
            }
            if (array_key_exists('active', $user) && !$user['active']) {
                return ['success' => false, 'message' => 'Tu cuenta está desactivada'];
            }

            $hashColumn = array_key_exists('password_hash', $user) ? 'password_hash' : 'password';
            if (empty($user[$hashColumn]) || !password_verify($password, $user[$hashColumn])) {
                return ['success' => false, 'message' => 'Email o contraseña incorrectos'];
            }
            
            if ($this->columnExists('users', 'last_login')) {
                $stmt = $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
            }
            
            $role = $user['role'] ?? 'client';
            $name = trim((string)(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
            if ($name === '') {
                $name = $user['name'] ?? $user['email'];
            }
            $points = $user['loyalty_points'] ?? ($user['points'] ?? 0);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $role;
            $_SESSION['name'] = $name;
            $_SESSION['loyalty_points'] = (int)$points;
            $_SESSION['login_time'] = time();
            
            return [
                'success' => true,
                'message' => 'Bienvenido ' . $name,
                'role' => $role
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
        if ($this->columnExists('users', 'is_verified')) {
            $stmt = $this->pdo->prepare("UPDATE users SET is_verified = true WHERE id = ?");
            $stmt->execute([$user_id]);
        }
    }
    
    public function verifyEmail($user_id, $token) {
        try {
            if ($this->columnExists('users', 'is_verified')) {
                $stmt = $this->pdo->prepare("UPDATE users SET is_verified = true WHERE id = ?");
                $stmt->execute([$user_id]);
            }
            
            return ['success' => true, 'message' => 'Email verificado correctamente'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error al verificar email'];
        }
    }

    private function getUserByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function tableExists($table) {
        $stmt = $this->pdo->prepare("SELECT to_regclass(?) IS NOT NULL AS exists_table");
        $stmt->execute([$table]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return !empty($row['exists_table']) && ($row['exists_table'] === true || $row['exists_table'] === 't' || $row['exists_table'] === 1 || $row['exists_table'] === '1');
    }

    private function columnExists($table, $column) {
        $stmt = $this->pdo->prepare("SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = ? AND column_name = ?)");
        $stmt->execute([$table, $column]);
        return (bool)$stmt->fetchColumn();
    }
}
?>
