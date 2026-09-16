<?php
/**
 * SISTEMA COMPLETO DE SEGURIDAD PARA TRUPER
 * Implementa múltiples capas de protección
 */

// ===== RATE LIMITING (DB-backed) =====
// BE-07: Rate limiting stored in database table instead of session to prevent bypass
class RateLimiter {
    private $pdo;
    private $tableName = 'rate_limit_entries';
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->ensureTable();
    }

    private function ensureTable() {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS {$this->tableName} (
                    id SERIAL PRIMARY KEY,
                    rate_key VARCHAR(255) NOT NULL,
                    attempts INTEGER NOT NULL DEFAULT 0,
                    window_start TIMESTAMP NOT NULL DEFAULT NOW(),
                    UNIQUE(rate_key)
                )
            ");
        } catch (Exception $e) {
            error_log('RateLimiter: could not create table: ' . $e->getMessage());
        }
    }
    
    public function checkLimit($key, $max_attempts = 5, $time_window = 300) {
        $cache_key = 'rl_' . md5($key);
        
        try {
            // Clean expired entries and get current count
            $stmt = $this->pdo->prepare("SELECT attempts, window_start FROM {$this->tableName} WHERE rate_key = ? LIMIT 1");
            $stmt->execute([$cache_key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$row) {
                // First attempt — insert
                $insert = $this->pdo->prepare("INSERT INTO {$this->tableName} (rate_key, attempts, window_start) VALUES (?, 1, NOW()) ON CONFLICT (rate_key) DO UPDATE SET attempts = 1, window_start = NOW()");
                $insert->execute([$cache_key]);
                return true;
            }
            
            $windowStart = strtotime($row['window_start']);
            $elapsed = time() - $windowStart;
            
            if ($elapsed > $time_window) {
                // Window expired — reset
                $reset = $this->pdo->prepare("UPDATE {$this->tableName} SET attempts = 1, window_start = NOW() WHERE rate_key = ?");
                $reset->execute([$cache_key]);
                return true;
            }
            
            if ((int)$row['attempts'] >= $max_attempts) {
                return false;
            }
            
            // Increment
            $inc = $this->pdo->prepare("UPDATE {$this->tableName} SET attempts = attempts + 1 WHERE rate_key = ?");
            $inc->execute([$cache_key]);
            return true;
            
        } catch (Exception $e) {
            error_log('RateLimiter DB error, falling back to allow: ' . $e->getMessage());
            return true; // Fail open to avoid blocking legitimate users
        }
    }
    
    public function isBlocked($key) {
        return !$this->checkLimit($key, 0, 0);
    }
}

// ===== INPUT VALIDATION & SANITIZATION =====
class SecurityValidator {
    
    public static function validateEmail($email) {
        $email = trim((string)$email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        // Prevenir email spoofing
        if (strlen($email) > 254) {
            return false;
        }
        return $email;
    }
    
    public static function validatePassword($password) {
        if (strlen($password) < 8) {
            return ['valid' => false, 'message' => 'Mínimo 8 caracteres'];
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return ['valid' => false, 'message' => 'Debe contener mayúsculas'];
        }
        if (!preg_match('/[a-z]/', $password)) {
            return ['valid' => false, 'message' => 'Debe contener minúsculas'];
        }
        if (!preg_match('/[0-9]/', $password)) {
            return ['valid' => false, 'message' => 'Debe contener números'];
        }
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'"\\|,.<>\/?]/', $password)) {
            return ['valid' => false, 'message' => 'Debe contener caracteres especiales'];
        }
        return ['valid' => true, 'message' => 'Contraseña fuerte'];
    }
    
    public static function sanitizeInput($input) {
        $input = trim((string)$input);
        // Eliminar caracteres de control
        $input = preg_replace('/[\x00-\x1F\x7F]/', '', $input);
        // HTML encode para prevenir XSS
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
    
    public static function validateSKU($sku) {
        $sku = trim((string)$sku);
        // Solo números y guiones
        if (!preg_match('/^[A-Z0-9\-]{3,20}$/i', $sku)) {
            return false;
        }
        return $sku;
    }
    
    public static function validatePhone($phone) {
        $digits = preg_replace('/\D+/', '', (string)$phone);
        // Teléfono en México: 10 dígitos (o con prefijo 52 / +52)
        if (strlen($digits) === 12 && substr($digits, 0, 2) === '52') {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) !== 10) {
            return false;
        }
        return $digits;
    }

    /**
     * Valida formato oficial de RFC mexicano (SAT)
     */
    public static function validateRFC($rfc) {
        $rfc = strtoupper(trim((string)$rfc));
        if ($rfc === 'XAXX010101000' || $rfc === 'XEXX010101000') {
            return $rfc;
        }
        // Persona Moral (12 caracteres) o Persona Física (13 caracteres)
        $pattern = '/^[A-Z&Ñ]{3,4}[0-9]{2}(0[1-9]|1[0-2])(0[1-9]|[12][0-9]|3[01])[A-Z0-9]{3}$/';
        if (!preg_match($pattern, $rfc)) {
            return false;
        }
        return $rfc;
    }

    /**
     * Valida CLABE interbancaria mexicana (18 dígitos y dígito verificador ABM/Banxico)
     */
    public static function validateClabe($clabe) {
        $clabe = trim((string)$clabe);
        if (!preg_match('/^\d{18}$/', $clabe)) {
            return ['valid' => false, 'message' => 'La CLABE debe contener exactamente 18 dígitos numéricos'];
        }

        $weights = [3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7];
        $sum = 0;
        for ($i = 0; $i < 17; $i++) {
            $sum += ((int)$clabe[$i] * $weights[$i]) % 10;
        }
        $expectedCheckDigit = (10 - ($sum % 10)) % 10;
        $actualCheckDigit = (int)$clabe[17];

        if ($expectedCheckDigit !== $actualCheckDigit) {
            return ['valid' => false, 'message' => 'Dígito de control de CLABE incorrecto'];
        }

        return ['valid' => true, 'clabe' => $clabe];
    }

    /**
     * Valida código postal mexicano (5 dígitos)
     */
    public static function validatePostalCode($cp) {
        $cp = trim((string)$cp);
        if (!preg_match('/^\d{5}$/', $cp)) {
            return false;
        }
        return $cp;
    }

    /**
     * Valida datos de tarjeta bancaria (Luhn, vencimiento futuro, CVV)
     */
    public static function validateCard($number, $expiry = null, $cvv = null, $holder = null) {
        $cleanNumber = preg_replace('/\D+/', '', (string)$number);
        if (strlen($cleanNumber) < 13 || strlen($cleanNumber) > 19) {
            return ['valid' => false, 'message' => 'El número de tarjeta debe tener entre 13 y 19 dígitos'];
        }

        // Algoritmo de Luhn (mod 10)
        $sum = 0;
        $alt = false;
        for ($i = strlen($cleanNumber) - 1; $i >= 0; $i--) {
            $n = (int)$cleanNumber[$i];
            if ($alt) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum += $n;
            $alt = !$alt;
        }
        if ($sum % 10 !== 0) {
            return ['valid' => false, 'message' => 'Número de tarjeta inválido (falla verificación Luhn)'];
        }

        // Validar expiración si se suministró
        if ($expiry !== null) {
            $expiry = trim((string)$expiry);
            if (!preg_match('/^(0[1-9]|1[0-2])\/(\d{2}|\d{4})$/', $expiry, $m)) {
                return ['valid' => false, 'message' => 'Formato de vencimiento inválido. Usa MM/AA'];
            }
            $month = (int)$m[1];
            $year = (int)$m[2];
            if ($year < 100) {
                $year += 2000;
            }
            $currentYear = (int)date('Y');
            $currentMonth = (int)date('n');
            if ($year < $currentYear || ($year === $currentYear && $month < $currentMonth)) {
                return ['valid' => false, 'message' => 'La tarjeta ha expirado'];
            }
        }

        // Validar CVV si se suministró
        if ($cvv !== null) {
            $cleanCvv = trim((string)$cvv);
            if (!preg_match('/^\d{3,4}$/', $cleanCvv)) {
                return ['valid' => false, 'message' => 'El código de seguridad CVV debe tener 3 o 4 dígitos'];
            }
        }

        // Validar titular si se suministró
        if ($holder !== null) {
            $cleanHolder = trim((string)$holder);
            if (mb_strlen($cleanHolder) < 3 || mb_strlen($cleanHolder) > 70) {
                return ['valid' => false, 'message' => 'Nombre de titular de tarjeta inválido'];
            }
        }

        return ['valid' => true, 'last4' => substr($cleanNumber, -4)];
    }

    /**
     * Sanitiza texto general eliminando tags HTML, caracteres de control y exceso de espacios
     */
    public static function sanitizeText($input, $maxLength = null) {
        if ($input === null) return '';
        $clean = trim((string)$input);
        // Eliminar caracteres nulos y de control excepto saltos de línea y tabuladores estándar
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $clean);
        // Stripear tags HTML
        $clean = strip_tags($clean);
        // Normalizar comillas y caracteres sin romper acentos
        $clean = htmlspecialchars($clean, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        // Decodificar para guardado seguro en base de datos parametrizada
        $clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        if ($maxLength !== null && $maxLength > 0) {
            $clean = mb_substr($clean, 0, $maxLength);
        }
        return $clean;
    }

    /**
     * Extrae únicamente dígitos numéricos
     */
    public static function sanitizeDigits($input) {
        return preg_replace('/\D+/', '', (string)$input);
    }

    /**
     * Permite caracteres alfanuméricos, espacios, guiones y puntos
     */
    public static function sanitizeAlphaNum($input, $maxLength = null) {
        $clean = trim((string)$input);
        $clean = preg_replace('/[^\p{L}\p{N}\s\-_.]/u', '', $clean);
        if ($maxLength !== null && $maxLength > 0) {
            $clean = mb_substr($clean, 0, $maxLength);
        }
        return $clean;
    }

    /**
     * Sanitiza un arreglo asociativo con reglas definidas
     */
    public static function sanitizeArray(array $data, array $rules) {
        $cleaned = [];
        foreach ($rules as $field => $type) {
            $raw = $data[$field] ?? null;
            if ($raw === null) {
                $cleaned[$field] = null;
                continue;
            }
            switch ($type) {
                case 'email':
                    $cleaned[$field] = self::validateEmail($raw) ?: '';
                    break;
                case 'phone':
                    $cleaned[$field] = self::validatePhone($raw) ?: '';
                    break;
                case 'digits':
                    $cleaned[$field] = self::sanitizeDigits($raw);
                    break;
                case 'postal_code':
                    $cleaned[$field] = self::validatePostalCode($raw) ?: '';
                    break;
                case 'rfc':
                    $cleaned[$field] = self::validateRFC($raw) ?: '';
                    break;
                case 'int':
                    $cleaned[$field] = (int)$raw;
                    break;
                case 'float':
                    $cleaned[$field] = (float)$raw;
                    break;
                case 'bool':
                    $cleaned[$field] = filter_var($raw, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'alphanumeric':
                    $cleaned[$field] = self::sanitizeAlphaNum($raw);
                    break;
                case 'text':
                default:
                    $cleaned[$field] = self::sanitizeText($raw);
                    break;
            }
        }
        return $cleaned;
    }
}

// ===== IP SECURITY =====
class IPSecurity {

    public static function getClientIP() {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            // Cloudflare
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Proxy
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public static function isAdminIPWhitelisted($ip = null) {
        $ip = $ip ?? self::getClientIP();

        // Whitelist de IPs admin (configurable)
        $whitelist = explode(',', getenv('ADMIN_IP_WHITELIST') ?: '');
        $whitelist = array_map('trim', $whitelist);

        // Si no hay whitelist, permitir todos (desarrollo)
        if (empty(array_filter($whitelist))) {
            return true;
        }

        // Permitir localhost siempre
        if (in_array($ip, ['127.0.0.1', '::1', 'localhost'])) {
            return true;
        }

        return in_array($ip, array_filter($whitelist));
    }

    public static function isIPBlocked($ip = null) {
        $ip = $ip ?? self::getClientIP();
        $blockedKey = 'blocked_ip_' . md5($ip);
        return isset($_SESSION[$blockedKey]) && $_SESSION[$blockedKey] > time();
    }

    public static function blockIP($ip = null, $duration = 3600) {
        $ip = $ip ?? self::getClientIP();
        $_SESSION['blocked_ip_' . md5($ip)] = time() + $duration;
    }

    public static function incrementFailedAttempts($ip = null) {
        $ip = $ip ?? self::getClientIP();
        $key = 'failed_attempts_' . md5($ip);
        $_SESSION[$key] = ($_SESSION[$key] ?? 0) + 1;
        return $_SESSION[$key];
    }

    public static function getFailedAttempts($ip = null) {
        $ip = $ip ?? self::getClientIP();
        return $_SESSION['failed_attempts_' . md5($ip)] ?? 0;
    }

    public static function resetFailedAttempts($ip = null) {
        $ip = $ip ?? self::getClientIP();
        unset($_SESSION['failed_attempts_' . md5($ip)]);
    }

    public static function shouldBlockAfterFailures($ip = null, $maxAttempts = 10) {
        $ip = $ip ?? self::getClientIP();
        $attempts = self::getFailedAttempts($ip);
        if ($attempts >= $maxAttempts) {
            self::blockIP($ip, 3600); // Bloquear por 1 hora
            return true;
        }
        return false;
    }
}

// ===== ENCRYPTION =====
class CryptoHelper {
    
    // BE-08: Throw if no encryption key is configured
    private static function getKey($key = null) {
        $resolved = $key ?? getenv('ENCRYPTION_KEY');
        if (empty($resolved) || $resolved === false) {
            throw new RuntimeException('ENCRYPTION_KEY is not configured. Set it in .env or environment variables.');
        }
        // Ensure key is 32 bytes for AES-256
        return hash('sha256', $resolved, true);
    }

    // BE-09: Fixed encrypt/decrypt to correctly handle binary IV + ciphertext
    public static function encryptData($data, $key = null) {
        $keyBytes = self::getKey($key);
        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        $iv = openssl_random_pseudo_bytes($ivLength);
        // Use OPENSSL_RAW_DATA to get raw binary ciphertext
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $keyBytes, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            throw new RuntimeException('Encryption failed');
        }
        // Concatenate IV + ciphertext and base64-encode
        return base64_encode($iv . $encrypted);
    }
    
    public static function decryptData($data, $key = null) {
        try {
            $keyBytes = self::getKey($key);
            $raw = base64_decode($data, true);
            if ($raw === false) {
                return false;
            }
            $ivLength = openssl_cipher_iv_length('aes-256-cbc');
            if (strlen($raw) < $ivLength) {
                return false;
            }
            $iv = substr($raw, 0, $ivLength);
            $ciphertext = substr($raw, $ivLength);
            // Use OPENSSL_RAW_DATA since we stored raw ciphertext
            $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', $keyBytes, OPENSSL_RAW_DATA, $iv);
            return $decrypted !== false ? $decrypted : false;
        } catch (Exception $e) {
            return false;
        }
    }
}

// ===== SECURITY LOGGING =====
class SecurityLogger {
    private $pdo;
    private $table = 'security_logs';
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->ensureTable();
    }
    
    private function ensureTable() {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS {$this->table} (
                    id SERIAL PRIMARY KEY,
                    event_type VARCHAR(50),
                    ip_address VARCHAR(45),
                    user_id INT,
                    description TEXT,
                    risk_level VARCHAR(20),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
        } catch (Exception $e) {
            error_log("Error creating security_logs table: " . $e->getMessage());
        }
    }
    
    public function logEvent($event_type, $description, $risk_level = 'LOW', $user_id = null) {
        try {
            $ip = IPSecurity::getClientIP();
            $stmt = $this->pdo->prepare("
                INSERT INTO {$this->table} (event_type, ip_address, user_id, description, risk_level)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $event_type,
                $ip,
                $user_id ?? ($_SESSION['user_id'] ?? null),
                $description,
                $risk_level
            ]);
        } catch (Exception $e) {
            error_log("Error logging security event: " . $e->getMessage());
        }
    }
    
    public function logFailedLogin($email, $reason = 'Invalid credentials') {
        $this->logEvent('FAILED_LOGIN', "Email: $email - $reason", 'MEDIUM');
    }
    
    public function logSuspiciousActivity($description) {
        $this->logEvent('SUSPICIOUS_ACTIVITY', $description, 'HIGH');
    }
}

// ===== FILE UPLOAD SECURITY =====
class FileUploadSecurity {
    
    private static $allowed_mimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];
    
    private static $max_size = 5242880; // 5MB
    
    public static function validateUpload($file) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'message' => 'Archivo no válido'];
        }
        
        // Validar tamaño
        if ($file['size'] > self::$max_size) {
            return ['valid' => false, 'message' => 'Archivo muy grande (máximo 5MB)'];
        }
        
        // Validar MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!isset(self::$allowed_mimes[$mime])) {
            return ['valid' => false, 'message' => 'Tipo de archivo no permitido'];
        }
        
        // Validar extensión
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== self::$allowed_mimes[$mime]) {
            return ['valid' => false, 'message' => 'Extensión no coincide con tipo MIME'];
        }
        
        return ['valid' => true, 'message' => 'Archivo válido', 'ext' => $ext];
    }
    
    public static function generateSafeName($file) {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        return uniqid('file_', true) . '.' . strtolower($ext);
    }
}

// BE-10: Security headers are already set in config.php. This function is kept
// for backward compatibility but does nothing to avoid duplicate headers.
function setSecurityHeaders() {
    // Headers are centralized in config.php — this is intentionally a no-op.
    // Keeping the function signature to avoid breaking existing callers.
}
