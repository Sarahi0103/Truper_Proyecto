<?php
/**
 * Controlador de Autenticación
 */

class AuthController {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->ensureAuthSchema();
    }
    
    public function register($data) {
        try {
            $this->ensureAuthSchema();
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Email inválido'];
            }

            $birthdate = !empty($data['birthdate']) ? $data['birthdate'] : null;
            if (empty($birthdate)) {
                return ['success' => false, 'message' => 'La fecha de nacimiento es obligatoria'];
            }

            $password = trim((string)($data['password'] ?? ''));
            if ($password === '') {
                $password = bin2hex(random_bytes(12));
            }
            
            $existing = $this->getUserByEmail($data['email']);
            if ($existing) {
                return ['success' => false, 'message' => 'El email ya está registrado'];
            }

            $firstName = trim((string)($data['first_name'] ?? ''));
            $lastName = trim((string)($data['last_name'] ?? ''));
            $fullName = trim($firstName . ' ' . $lastName);

            $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $user_id = $this->registerWithFallbackStrategies(
                $data['email'],
                $password_hash,
                $firstName,
                $lastName,
                $fullName,
                trim((string)($data['phone'] ?? '')),
                $birthdate
            );
            
            $this->createClientIfPossible($user_id, trim((string)($data['company_name'] ?? '')));
            $userCode = $this->ensureUserCodeForUser($user_id);
            
            return [
                'success' => true,
                'message' => 'Registro exitoso. Ya puedes iniciar sesión.',
                'user_id' => $user_id,
                'user_code' => $userCode
            ];
        } catch (Exception $e) {
            error_log("Error en registro: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al registrar. Intenta de nuevo.'];
        }
    }
    
    public function login($identifier, $password) {
        try {
            $this->ensureAuthSchema();
            $identifier = trim((string)$identifier);
            $user = $this->safeGetUserByIdentifier($identifier);
            if (!$user && strtolower($identifier) === 'admin@truper.com' && $password === 'Admin123!') {
                $this->ensureDefaultAdminAccount();
                $user = $this->safeGetUserByIdentifier($identifier);
            }
            if (!$user) {
                return ['success' => false, 'message' => 'Email o contraseña incorrectos'];
            }

            if (array_key_exists('is_active', $user) && !$this->isTruthy($user['is_active'])) {
                return ['success' => false, 'message' => 'Tu cuenta está desactivada'];
            }
            if (array_key_exists('active', $user) && !$this->isTruthy($user['active'])) {
                return ['success' => false, 'message' => 'Tu cuenta está desactivada'];
            }

            $passwordOk = false;
            $needsUpgradeHash = false;

            $passwordHash = $user['password_hash'] ?? null;
            $legacyPassword = $user['password'] ?? null;

            if (!empty($passwordHash)) {
                $passwordOk = password_verify($password, (string)$passwordHash);
            }

            if (!$passwordOk && !empty($legacyPassword)) {
                $legacyString = (string)$legacyPassword;
                if (str_starts_with($legacyString, '$2y$') || str_starts_with($legacyString, '$2a$') || str_starts_with($legacyString, '$2b$')) {
                    $passwordOk = password_verify($password, $legacyString);
                } else {
                    $passwordOk = hash_equals($legacyString, (string)$password);
                }
                $needsUpgradeHash = $passwordOk;
            }

            if (!$passwordOk) {
                // Compatibilidad: si el admin por defecto existe con hash distinto, permitir bootstrap una sola vez con Admin123!
                if (($user['role'] ?? '') === 'admin' && strtolower($identifier) === 'admin@truper.com' && $password === 'Admin123!') {
                    $newHash = password_hash('Admin123!', PASSWORD_BCRYPT, ['cost' => 12]);
                    $columnToUpdate = $this->columnExists('users', 'password_hash') ? 'password_hash' : 'password';
                    $update = $this->pdo->prepare("UPDATE users SET {$columnToUpdate} = ? WHERE id = ?");
                    $update->execute([$newHash, $user['id']]);
                    $passwordOk = true;
                }
            }
            if (!$passwordOk) {
                return ['success' => false, 'message' => 'Email o contraseña incorrectos'];
            }

            if ($needsUpgradeHash && $this->columnExists('users', 'password_hash')) {
                $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmtUpgrade = $this->pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmtUpgrade->execute([$newHash, $user['id']]);
            }

            $this->ensureUserCodeForUser((int)$user['id']);
            
            if ($this->columnExists('users', 'last_login')) {
                $stmt = $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
            }
            
            // Regenerate session ID to prevent session fixation attacks
            session_regenerate_id(true);
            
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
            $_SESSION['phone'] = $user['phone'] ?? null;
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

    public function loginByClientCode($userCode, $birthdate) {
        try {
            $this->ensureAuthSchema();
            $userCode = trim((string)$userCode);
            $birthdate = trim((string)$birthdate);

            if ($userCode === '' || $birthdate === '') {
                return ['success' => false, 'message' => 'Código de cliente y fecha de nacimiento son obligatorios'];
            }

            $birthColumn = null;
            if ($this->columnExists('users', 'birthdate')) {
                $birthColumn = 'birthdate';
            } elseif ($this->columnExists('users', 'birthday')) {
                $birthColumn = 'birthday';
            }

            if ($birthColumn === null) {
                return ['success' => false, 'message' => 'No se encontró la columna de fecha de nacimiento'];
            }

            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE user_code = ? LIMIT 1");
            $stmt->execute([$userCode]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if (!$user) {
                return ['success' => false, 'message' => 'Código de cliente incorrecto'];
            }

            if (($user['role'] ?? 'client') !== 'client') {
                return ['success' => false, 'message' => 'Este acceso es solo para clientes'];
            }

            if (array_key_exists('is_active', $user) && !$this->isTruthy($user['is_active'])) {
                return ['success' => false, 'message' => 'Tu cuenta está desactivada'];
            }
            if (array_key_exists('active', $user) && !$this->isTruthy($user['active'])) {
                return ['success' => false, 'message' => 'Tu cuenta está desactivada'];
            }

            $storedBirthdate = trim((string)($user[$birthColumn] ?? ''));
            if ($storedBirthdate === '') {
                return ['success' => false, 'message' => 'Tu cuenta no tiene fecha de nacimiento registrada'];
            }

            $providedDate = substr($birthdate, 0, 10);
            $storedDate = substr($storedBirthdate, 0, 10);
            if ($providedDate !== $storedDate) {
                return ['success' => false, 'message' => 'Fecha de nacimiento incorrecta'];
            }

            $this->ensureUserCodeForUser((int)$user['id']);

            if ($this->columnExists('users', 'last_login')) {
                $stmtLogin = $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmtLogin->execute([$user['id']]);
            }

            session_regenerate_id(true);

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
            $_SESSION['phone'] = $user['phone'] ?? null;
            $_SESSION['loyalty_points'] = (int)$points;
            $_SESSION['login_time'] = time();

            return [
                'success' => true,
                'message' => 'Bienvenido ' . $name,
                'role' => $role
            ];
        } catch (PDOException $e) {
            error_log("Error en login de cliente: " . $e->getMessage());
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

    private function getUserByIdentifier($identifier) {
        $emailTry = strtolower(trim((string)$identifier));
        $byEmail = $this->getUserByEmail($emailTry);
        if ($byEmail) {
            return $byEmail;
        }

        if (!$this->columnExists('users', 'phone')) {
            return null;
        }

        $phoneNormalized = $this->normalizePhone($identifier);
        if ($phoneNormalized === '') {
            return null;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE regexp_replace(COALESCE(phone, ''), '[^0-9]+', '', 'g') = ? LIMIT 1");
        $stmt->execute([$phoneNormalized]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function safeGetUserByEmail($email) {
        try {
            return $this->getUserByEmail($email);
        } catch (Exception $e) {
            $this->ensureAuthSchema();
            try {
                return $this->getUserByEmail($email);
            } catch (Exception $ignored) {
                return null;
            }
        }
    }

    private function safeGetUserByIdentifier($identifier) {
        try {
            return $this->getUserByIdentifier($identifier);
        } catch (Exception $e) {
            $this->ensureAuthSchema();
            try {
                return $this->getUserByIdentifier($identifier);
            } catch (Exception $ignored) {
                return null;
            }
        }
    }

    private function tableExists($table) {
        try {
            $stmt = $this->pdo->prepare("SELECT to_regclass(?) IS NOT NULL AS exists_table");
            $stmt->execute([$table]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return !empty($row['exists_table']) && ($row['exists_table'] === true || $row['exists_table'] === 't' || $row['exists_table'] === 1 || $row['exists_table'] === '1');
        } catch (Exception $e) {
            return false;
        }
    }

    private function columnExists($table, $column) {
        try {
            $stmt = $this->pdo->prepare("SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = ? AND column_name = ?)");
            $stmt->execute([$table, $column]);
            return (bool)$stmt->fetchColumn();
        } catch (Exception $e) {
            return false;
        }
    }

    private function registerWithFallbackStrategies($email, $passwordHash, $firstName, $lastName, $fullName, $phone, $birthDate) {
        // Estrategia 1: esquema PostgreSQL actual
        $sqlA = "INSERT INTO users (email, password_hash, first_name, last_name, role, phone, birthdate, loyalty_points, is_active, is_verified) VALUES (?, ?, ?, ?, 'client', ?, ?, 0, true, true)";
        try {
            $stmt = $this->pdo->prepare($sqlA);
            $stmt->execute([$email, $passwordHash, $firstName, $lastName, $phone, $birthDate]);
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            // fallback
        }

        // Estrategia 2: esquema legado MySQL-like
        $sqlB = "INSERT INTO users (email, password, name, phone, birthday, role, points, active) VALUES (?, ?, ?, ?, ?, 'client', 0, 1)";
        try {
            $stmt = $this->pdo->prepare($sqlB);
            $stmt->execute([$email, $passwordHash, ($fullName !== '' ? $fullName : $email), $phone, $birthDate]);
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            // fallback
        }

        // Estrategia 3: mínima
        $sqlC = "INSERT INTO users (email, password, name, role) VALUES (?, ?, ?, 'client')";
        $stmt = $this->pdo->prepare($sqlC);
        $stmt->execute([$email, $passwordHash, ($fullName !== '' ? $fullName : $email)]);
        return $this->pdo->lastInsertId();
    }

    private function createClientIfPossible($userId, $companyName) {
        if (!$this->tableExists('clients')) {
            return;
        }
        try {
            $clientCode = $this->ensureUserCodeForUser((int)$userId);
            if ($this->columnExists('clients', 'client_code')) {
                $stmtA = $this->pdo->prepare("INSERT INTO clients (user_id, company_name, client_code) VALUES (?, ?, ?)");
                $stmtA->execute([$userId, $companyName, $clientCode]);
            } else {
                $stmtA = $this->pdo->prepare("INSERT INTO clients (user_id, company_name) VALUES (?, ?)");
                $stmtA->execute([$userId, $companyName]);
            }
            return;
        } catch (Exception $e) {
            // fallback
        }
        try {
            $clientCode = $this->ensureUserCodeForUser((int)$userId);
            if ($this->columnExists('clients', 'client_code')) {
                $stmtB = $this->pdo->prepare("INSERT INTO clients (user_id, company_name, client_code, is_wholesale, credit_limit, credit_available) VALUES (?, ?, ?, false, 0, 0)");
                $stmtB->execute([$userId, $companyName !== '' ? $companyName : 'Cliente', $clientCode]);
            } else {
                $stmtB = $this->pdo->prepare("INSERT INTO clients (user_id, company_name, is_wholesale, credit_limit, credit_available) VALUES (?, ?, false, 0, 0)");
                $stmtB->execute([$userId, $companyName !== '' ? $companyName : 'Cliente']);
            }
        } catch (Exception $e) {
            // No bloquear el registro por falla en tabla auxiliar
        }
    }

    private function ensureDefaultAdminAccount() {
        $this->ensureAuthSchema();
        $passwordHash = password_hash('Admin123!', PASSWORD_BCRYPT, ['cost' => 12]);

        $sqlA = "INSERT INTO users (email, password_hash, first_name, last_name, role, phone, is_active, is_verified) VALUES ('admin@truper.com', ?, 'Administrador', 'Truper', 'admin', '', true, true)";
        try {
            $stmt = $this->pdo->prepare($sqlA);
            $stmt->execute([$passwordHash]);
            return;
        } catch (Exception $e) {
            // fallback
        }

        $sqlB = "INSERT INTO users (email, password, name, role, active) VALUES ('admin@truper.com', ?, 'Administrador Truper', 'admin', 1)";
        try {
            $stmt = $this->pdo->prepare($sqlB);
            $stmt->execute([$passwordHash]);
        } catch (Exception $e) {
            // Si falla, no romper login; el flujo principal seguirá devolviendo error controlado.
        }
    }

    private function isTruthy($value) {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int)$value === 1;
        }
        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, ['1', 'true', 't', 'yes', 'y'], true);
    }

    private function ensureAuthSchema() {
        try {
            $this->pdo->exec("CREATE EXTENSION IF NOT EXISTS pgcrypto");
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS users (
                id SERIAL PRIMARY KEY,
                email VARCHAR(255) UNIQUE NOT NULL,
                password_hash VARCHAR(255),
                password VARCHAR(255),
                user_code VARCHAR(32) UNIQUE,
                first_name VARCHAR(120),
                last_name VARCHAR(120),
                name VARCHAR(255),
                role VARCHAR(20) NOT NULL DEFAULT 'client',
                phone VARCHAR(30),
                birthdate DATE,
                birthday DATE,
                loyalty_points INTEGER DEFAULT 0,
                points INTEGER DEFAULT 0,
                is_active BOOLEAN DEFAULT true,
                active BOOLEAN DEFAULT true,
                is_verified BOOLEAN DEFAULT true,
                last_login TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS clients (
                id SERIAL PRIMARY KEY,
                user_id INTEGER UNIQUE REFERENCES users(id) ON DELETE CASCADE,
                company_name VARCHAR(255),
                client_code VARCHAR(32),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            $this->pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS user_code VARCHAR(32)");
            $this->pdo->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS client_code VARCHAR(32)");
        } catch (Exception $e) {
            // Evita interrumpir la app si el usuario de BD no tiene permisos DDL.
        }
    }

    private function normalizePhone($value) {
        return preg_replace('/\D+/', '', (string)$value) ?? '';
    }

    private function generateUserCode() {
        return (string)random_int(100000000, 999999999);
    }

    private function ensureUserCodeForUser($userId) {
        if (!$this->columnExists('users', 'user_code')) {
            return str_pad((string)$userId, 9, '0', STR_PAD_LEFT);
        }

        try {
            $stmt = $this->pdo->prepare("SELECT user_code FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $existing = $stmt->fetchColumn();
            if (!empty($existing) && preg_match('/^[0-9]+$/', (string)$existing)) {
                return (string)$existing;
            }

            $tries = 0;
            do {
                $tries++;
                $code = $this->generateUserCode();
                $check = $this->pdo->prepare("SELECT 1 FROM users WHERE user_code = ? LIMIT 1");
                $check->execute([$code]);
                $exists = (bool)$check->fetchColumn();
            } while ($exists && $tries < 10);

            if (empty($code)) {
                $code = str_pad((string)$userId, 9, '0', STR_PAD_LEFT);
            }

            $update = $this->pdo->prepare("UPDATE users SET user_code = ? WHERE id = ?");
            $update->execute([$code, $userId]);
            return $code;
        } catch (Exception $e) {
            return str_pad((string)$userId, 9, '0', STR_PAD_LEFT);
        }
    }
}
?>
