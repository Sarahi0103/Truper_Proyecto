<?php
/**
 * Servicio de caché para el estado del cajón
 * Implementa caché APCu para estado actual del cajón (TTL: 1 minuto)
 */

class CashierCacheService {
    private $ttl = 60; // 1 minuto en segundos

    /**
     * Obtener estado del cajón en caché
     */
    public function getCashierStatus($userId) {
        $cacheKey = 'cashier_status_' . $userId;
        $cached = apcu_fetch($cacheKey, $success);
        if ($success) {
            return $cached;
        }
        return null;
    }

    /**
     * Guardar estado del cajón en caché
     */
    public function setCashierStatus($userId, $status) {
        $cacheKey = 'cashier_status_' . $userId;
        return apcu_store($cacheKey, $status, $this->ttl);
    }

    /**
     * Invalidar caché del cajón
     */
    public function invalidateCashierStatus($userId) {
        $cacheKey = 'cashier_status_' . $userId;
        return apcu_delete($cacheKey);
    }

    /**
     * Invalidar todo el caché de cajón
     */
    public function invalidateAll() {
        $iterator = new APCUIterator('/^cashier_/');
        apcu_delete($iterator);
        return true;
    }

    /**
     * Validar monto (prevenir negativos o excesivos)
     */
    public function validateAmount($amount) {
        if (!is_numeric($amount)) {
            return false;
        }

        $amount = floatval($amount);

        // Prevenir montos negativos
        if ($amount < 0) {
            return false;
        }

        // Prevenir montos excesivos (máximo $1,000,000)
        if ($amount > 1000000) {
            return false;
        }

        return true;
    }

    /**
     * Validar monto de discrepancia para alertas
     */
    public function shouldAlertDiscrepancy($difference) {
        return abs(floatval($difference)) > 100; // Alertar si diferencia > $100
    }

    /**
     * Generar código de confirmación para doble autenticación
     */
    public function generateConfirmationCode() {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Guardar código de confirmación en caché (TTL: 5 minutos)
     */
    public function setConfirmationCode($userId, $code) {
        $cacheKey = 'cashier_confirm_' . $userId;
        return apcu_store($cacheKey, $code, 300); // 5 minutos
    }

    /**
     * Verificar código de confirmación
     */
    public function verifyConfirmationCode($userId, $code) {
        $cacheKey = 'cashier_confirm_' . $userId;
        $storedCode = apcu_fetch($cacheKey, $success);

        if (!$success) {
            return false;
        }

        if ($storedCode === $code) {
            // Eliminar código después de usarlo
            apcu_delete($cacheKey);
            return true;
        }

        return false;
    }
}
