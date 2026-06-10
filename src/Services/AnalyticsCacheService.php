<?php
/**
 * Servicio de caché para Analytics
 * Implementa caché APCu agresivo para datos de estadísticas (TTL: 1 hora)
 */

class AnalyticsCacheService {
    private $ttl = 3600; // 1 hora en segundos

    /**
     * Obtener datos de caché
     */
    public function get($cacheKey) {
        $cached = apcu_fetch($cacheKey, $success);
        if ($success) {
            return $cached;
        }
        return null;
    }

    /**
     * Guardar datos en caché
     */
    public function set($cacheKey, $data) {
        return apcu_store($cacheKey, $data, $this->ttl);
    }

    /**
     * Invalidar caché específica
     */
    public function invalidate($cacheKey) {
        return apcu_delete($cacheKey);
    }

    /**
     * Invalidar todo el caché de analytics
     */
    public function invalidateAll() {
        $iterator = new APCUIterator('/^analytics_/');
        apcu_delete($iterator);
        return true;
    }

    /**
     * Generar clave de caché basada en parámetros
     */
    public function generateCacheKey($type, $params = []) {
        $key = 'analytics_' . $type;
        if (!empty($params)) {
            ksort($params); // Ordenar parámetros para consistencia
            $key .= '_' . md5(json_encode($params));
        }
        return $key;
    }

    /**
     * Validar rango de fechas (máximo 1 año)
     */
    public function validateDateRange($startDate, $endDate) {
        if (!$startDate || !$endDate) {
            return true; // Si no hay fechas, es válido
        }

        $start = strtotime($startDate);
        $end = strtotime($endDate);

        if ($start === false || $end === false) {
            return false;
        }

        $diff = abs($end - $start);
        $oneYear = 365 * 24 * 60 * 60; // 1 año en segundos

        return $diff <= $oneYear;
    }

    /**
     * Rate limiting para exportaciones (máx 5 por hora)
     */
    public function checkExportRateLimit($userId) {
        $key = 'analytics_export_limit_' . $userId;
        $data = apcu_fetch($key, $success);

        if (!$success) {
            // Primer exportación en la hora
            apcu_store($key, ['count' => 1, 'timestamp' => time()], 3600);
            return true;
        }

        $currentTime = time();
        $elapsed = $currentTime - $data['timestamp'];

        // Si pasó más de 1 hora, resetear contador
        if ($elapsed > 3600) {
            apcu_store($key, ['count' => 1, 'timestamp' => $currentTime], 3600);
            return true;
        }

        // Verificar límite de 5 por hora
        if ($data['count'] >= 5) {
            return false;
        }

        // Incrementar contador
        $data['count']++;
        apcu_store($key, $data, 3600 - $elapsed);
        return true;
    }

    /**
     * Obtener tiempo restante para rate limiting
     */
    public function getRateLimitResetTime($userId) {
        $key = 'analytics_export_limit_' . $userId;
        $data = apcu_fetch($key, $success);

        if (!$success) {
            return 0;
        }

        $currentTime = time();
        $elapsed = $currentTime - $data['timestamp'];
        $remaining = 3600 - $elapsed;

        return max(0, $remaining);
    }
}
