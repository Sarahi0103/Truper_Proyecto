<?php
/**
 * TRUPER PLATFORM - Performance Metrics Endpoint
 * Provides basic performance metrics for monitoring
 * 
 * Usage: /metrics.php
 * Authentication: Requires admin role (optional, can be disabled)
 */

require_once '../config/config.php';

// Optional: Require admin for access (uncomment to enable)
// require_admin();

header('Content-Type: application/json');

try {
    $metrics = [
        'timestamp' => date('Y-m-d H:i:s'),
        'system' => [
            'php_version' => PHP_VERSION,
            'server_time' => date('Y-m-d H:i:s T'),
            'timezone' => date_default_timezone_get(),
            'memory_limit' => ini_get('memory_limit'),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'load_average' => function_exists('sys_getloadavg') ? sys_getloadavg() : null,
        ],
        'database' => [
            'status' => 'connected',
            'connection_time' => null,
            'query_time' => null,
        ],
        'cache' => [
            'apcu_enabled' => extension_loaded('apcu'),
            'apcu_size' => extension_loaded('apcu') ? apcu_cache_info(false)['mem_size'] ?? 0 : 0,
        ],
        'session' => [
            'active_sessions' => 0,
            'session_handler' => ini_get('session.save_handler'),
        ],
        'application' => [
            'version' => APP_VERSION ?? '1.0.0',
            'environment' => APP_ENV ?? 'production',
            'debug_mode' => APP_DEBUG ?? false,
        ],
    ];

    // Database connection time
    $dbStart = microtime(true);
    try {
        $pdo->query("SELECT 1");
        $dbEnd = microtime(true);
        $metrics['database']['connection_time'] = round(($dbEnd - $dbStart) * 1000, 2); // ms
    } catch (Exception $e) {
        $metrics['database']['status'] = 'error';
        $metrics['database']['error'] = $e->getMessage();
    }

    // Sample query time
    $queryStart = microtime(true);
    try {
        $pdo->query("SELECT COUNT(*) FROM users");
        $queryEnd = microtime(true);
        $metrics['database']['query_time'] = round(($queryEnd - $queryStart) * 1000, 2); // ms
    } catch (Exception $e) {
        // Ignore if table doesn't exist
    }

    // Active sessions (estimate from session files if using file handler)
    if (ini_get('session.save_handler') === 'files') {
        $sessionPath = ini_get('session.save_path');
        if ($sessionPath && is_dir($sessionPath)) {
            $sessionFiles = glob($sessionPath . '/sess_*');
            $metrics['session']['active_sessions'] = count($sessionFiles);
        }
    }

    // Add custom application metrics if available
    if (function_exists('get_custom_metrics')) {
        $metrics['custom'] = get_custom_metrics();
    }

    echo json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error collecting metrics',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
