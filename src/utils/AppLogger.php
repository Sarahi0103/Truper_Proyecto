<?php
/**
 * Utility: AppLogger
 * Provides structured, JSON-formatted logging for the application.
 */

class AppLogger {
    private static ?string $logFile = null;

    private static function getLogFile(): string {
        if (self::$logFile === null) {
            $configuredPath = getenv('LOG_PATH') ?: 'logs/app.log';
            // Absolute path from application root
            if (strpos($configuredPath, '/') === 0 || strpos($configuredPath, ':') !== false) {
                self::$logFile = $configuredPath;
            } else {
                self::$logFile = __DIR__ . '/../../' . $configuredPath;
            }

            // Ensure directory exists
            $dir = dirname(self::$logFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
        return self::$logFile;
    }

    private static function writeLog(string $level, string $message, array $context = []): void {
        $logPath = self::getLogFile();
        $configuredLevel = getenv('LOG_LEVEL') ?: 'info';

        // Check level priority
        $priorities = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];
        $currentPriority = $priorities[strtolower($level)] ?? 1;
        $configuredPriority = $priorities[strtolower($configuredLevel)] ?? 1;

        if ($currentPriority < $configuredPriority) {
            return;
        }

        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context,
            'session_user_id' => $_SESSION['user_id'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ];

        // Format as JSON and write to file
        $json = json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        file_put_contents($logPath, $json, FILE_APPEND | LOCK_EX);

        // En entornos Render/serverless, tambien escribir a error_log para que aparezca en el dashboard
        if (getenv('RENDER') === 'true' || getenv('LOG_TO_ERROR_LOG') === 'true') {
            error_log($json, 0);
        }
    }

    public static function debug(string $message, array $context = []): void {
        self::writeLog('debug', $message, $context);
    }

    public static function info(string $message, array $context = []): void {
        self::writeLog('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void {
        self::writeLog('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void {
        self::writeLog('error', $message, $context);
    }
}
