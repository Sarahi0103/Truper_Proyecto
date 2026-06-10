<?php
/**
 * Servicio de caché para Dashboard
 * Implementa caché APCu para métricas del dashboard (TTL: 5 minutos)
 */

class DashboardCacheService {
    private $ttl = 300; // 5 minutos en segundos

    /**
     * Obtener métricas del dashboard en caché
     */
    public function getMetrics($userId, $role) {
        $cacheKey = 'dashboard_metrics_' . $userId . '_' . $role;
        $cached = apcu_fetch($cacheKey, $success);
        if ($success) {
            return $cached;
        }
        return null;
    }

    /**
     * Guardar métricas del dashboard en caché
     */
    public function setMetrics($userId, $role, $metrics) {
        $cacheKey = 'dashboard_metrics_' . $userId . '_' . $role;
        return apcu_store($cacheKey, $metrics, $this->ttl);
    }

    /**
     * Invalidar caché del dashboard
     */
    public function invalidateDashboard($userId = null) {
        if ($userId) {
            $cacheKey = 'dashboard_metrics_' . $userId . '_*';
            $iterator = new APCUIterator('/^dashboard_metrics_' . $userId . '_/');
            apcu_delete($iterator);
        } else {
            $iterator = new APCUIterator('/^dashboard_metrics_/');
            apcu_delete($iterator);
        }
        return true;
    }

    /**
     * Validar rango de fechas (máximo 1 año)
     */
    public function validateDateRange($startDate, $endDate) {
        if (!$startDate || !$endDate) {
            return true;
        }

        $start = strtotime($startDate);
        $end = strtotime($endDate);

        if ($start === false || $end === false) {
            return false;
        }

        $diff = abs($end - $start);
        $oneYear = 365 * 24 * 60 * 60;

        return $diff <= $oneYear;
    }

    /**
     * Rate limiting para consultas del dashboard (máx 100/minuto)
     */
    public function checkRateLimit($userId) {
        $key = 'dashboard_rate_limit_' . $userId;
        $data = apcu_fetch($key, $success);

        if (!$success) {
            apcu_store($key, ['count' => 1, 'timestamp' => time()], 60);
            return true;
        }

        $currentTime = time();
        $elapsed = $currentTime - $data['timestamp'];

        if ($elapsed > 60) {
            apcu_store($key, ['count' => 1, 'timestamp' => $currentTime], 60);
            return true;
        }

        if ($data['count'] >= 100) {
            return false;
        }

        $data['count']++;
        apcu_store($key, $data, 60 - $elapsed);
        return true;
    }

    /**
     * Generar clave de caché para widgets específicos
     */
    public function generateWidgetCacheKey($widgetType, $params = []) {
        $key = 'dashboard_widget_' . $widgetType;
        if (!empty($params)) {
            ksort($params);
            $key .= '_' . md5(json_encode($params));
        }
        return $key;
    }

    /**
     * Obtener datos de widget en caché
     */
    public function getWidgetData($widgetType, $params = []) {
        $cacheKey = $this->generateWidgetCacheKey($widgetType, $params);
        $cached = apcu_fetch($cacheKey, $success);
        if ($success) {
            return $cached;
        }
        return null;
    }

    /**
     * Guardar datos de widget en caché
     */
    public function setWidgetData($widgetType, $params, $data) {
        $cacheKey = $this->generateWidgetCacheKey($widgetType, $params);
        return apcu_store($cacheKey, $data, $this->ttl);
    }

    /**
     * Sanitizar parámetros de filtros
     */
    public function sanitizeFilters($filters) {
        $sanitized = [];

        if (isset($filters['start_date'])) {
            $sanitized['start_date'] = date('Y-m-d', strtotime($filters['start_date']));
        }

        if (isset($filters['end_date'])) {
            $sanitized['end_date'] = date('Y-m-d', strtotime($filters['end_date']));
        }

        if (isset($filters['limit'])) {
            $sanitized['limit'] = min(max((int)$filters['limit'], 1), 50);
        } else {
            $sanitized['limit'] = 50;
        }

        if (isset($filters['offset'])) {
            $sanitized['offset'] = max((int)$filters['offset'], 0);
        } else {
            $sanitized['offset'] = 0;
        }

        return $sanitized;
    }
}
