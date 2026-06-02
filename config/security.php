<?php
/**
 * SISTEMA COMPLETO DE SEGURIDAD PARA TRUPER
 * Implementa múltiples capas de protección
 */

// ===== RATE LIMITING =====
class RateLimiter {
    private $pdo;
    private $prefix = 'rate_limit_';
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function checkLimit($key, $max_attempts = 5, $time_window = 300) {
        $cache_key = $this->prefix . md5($key);
        $attempts_key = $cache_key . '_attempts';
        $reset_key = $cache_key . '_reset';
        
        // Usar session para almacenar temporalmente
        if (!isset($_SESSION[$attempts_key])) {
            $_SESSION[$attempts_key] = 0;
            $_SESSION[$reset_key] = time() + $time_window;
        }
        
        // Reset si pasó el tiempo
        if (time() > $_SESSION[$reset_key]) {
            $_SESSION[$attempts_key] = 0;
            $_SESSION[$reset_key] = time() + $time_window;
        }
        
        $_SESSION[$attempts_key]++;
        
        if ($_SESSION[$attempts_key] > $max_attempts) {
            return false;
        }
        
        return true;
    }
    
    public function isBlocked($key) {
        $cache_key = $this->prefix . md5($key);
        return ($_SESSION[$cache_key . '_attempts'] ?? 0) > 10;
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
        if (strlen($digits) < 10) {
            return false;
        }
        return $digits;
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
}

// ===== ENCRYPTION =====
class CryptoHelper {
    
    public static function encryptData($data, $key = null) {
        $key = $key ?? (getenv('ENCRYPTION_KEY') ?: hash('sha256', 'default-key'));
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    public static function decryptData($data, $key = null) {
        try {
            $key = $key ?? (getenv('ENCRYPTION_KEY') ?: hash('sha256', 'default-key'));
            $data = base64_decode($data);
            $iv = substr($data, 0, openssl_cipher_iv_length('aes-256-cbc'));
            $encrypted = substr($data, openssl_cipher_iv_length('aes-256-cbc'));
            return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
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

// ===== SECURITY HEADERS =====
function setSecurityHeaders() {
    // Prevenir clickjacking
    header("X-Frame-Options: SAMEORIGIN");
    
    // Prevenir MIME sniffing
    header("X-Content-Type-Options: nosniff");
    
    // XSS Protection
    header("X-XSS-Protection: 1; mode=block");
    
    // Content Security Policy
    // header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'");
    
    // Referrer Policy
    header("Referrer-Policy: strict-origin-when-cross-origin");
    
    // Permissions Policy
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    
    // HSTS (1 año)
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
    }
}

// ===== TWO-FACTOR AUTHENTICATION =====
class TwoFactorAuth {
    
    public static function generateSecret() {
        return bin2hex(random_bytes(16));
    }
    
    public static function generateTOTP($secret) {
        $time = floor(time() / 30);
        $code = 0;
        
        for ($i = 0; $i < 64; $i++) {
            $hmac = hash_hmac('sha1', pack('N*', 0, $time), $secret, true);
            $offset = ord($hmac[19]) & 0xf;
            $code = (ord($hmac[$offset]) & 0x7f) << 24;
            $code |= (ord($hmac[$offset+1]) & 0xff) << 16;
            $code |= (ord($hmac[$offset+2]) & 0xff) << 8;
            $code |= ord($hmac[$offset+3]) & 0xff;
            $code = $code % 1000000;
        }
        
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }
    
    public static function verifyTOTP($secret, $code) {
        for ($i = -1; $i <= 1; $i++) {
            $time = floor((time() + ($i * 30)) / 30);
            $hmac = hash_hmac('sha1', pack('N*', 0, $time), $secret, true);
            $offset = ord($hmac[19]) & 0xf;
            $test_code = (ord($hmac[$offset]) & 0x7f) << 24;
            $test_code |= (ord($hmac[$offset+1]) & 0xff) << 16;
            $test_code |= (ord($hmac[$offset+2]) & 0xff) << 8;
            $test_code |= ord($hmac[$offset+3]) & 0xff;
            $test_code = $test_code % 1000000;
            $test_code = str_pad($test_code, 6, '0', STR_PAD_LEFT);
            
            if ($test_code === (string)$code) {
                return true;
            }
        }
        return false;
    }
}

// ===== INICIALIZAR =====
setSecurityHeaders();
