<?php
/**
 * Mexican Banks Service
 * Gestión de bancos mexicanos y métodos de pago
 */

class MexicanBanksService {
    private $pdo;
    private $logger;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        if (class_exists('AppLogger')) {
            $this->logger = new AppLogger();
        }
    }
    
    /**
     * Obtener todos los bancos activos
     */
    public function getActiveBanks() {
        try {
            $stmt = $this->pdo->query("SELECT * FROM get_active_mexican_banks()");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Error obteniendo bancos activos: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener banco por ID
     */
    public function getBankById($bankId) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM mexican_banks WHERE id = ? AND is_active = true");
            $stmt->execute([$bankId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Error obteniendo banco: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtener bancos que soportan un método específico
     */
    public function getBanksByPaymentMethod($method) {
        try {
            $column = '';
            switch ($method) {
                case 'spei':
                    $column = 'supports_spei';
                    break;
                case 'card':
                    $column = 'supports_card';
                    break;
                case 'transfer':
                    $column = 'supports_transfer';
                    break;
                default:
                    return [];
            }
            
            $stmt = $this->pdo->prepare("SELECT * FROM mexican_banks WHERE {$column} = true AND is_active = true ORDER BY display_order ASC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Error obteniendo bancos por método: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Agregar nuevo banco
     */
    public function addBank($bankData) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO mexican_banks (bank_name, bank_code, clabe, account_number, account_holder, rfc, 
                    is_active, display_order, supports_spei, supports_card, supports_transfer, logo_url)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $bankData['bank_name'],
                $bankData['bank_code'],
                $bankData['clabe'],
                $bankData['account_number'] ?? null,
                $bankData['account_holder'],
                $bankData['rfc'] ?? null,
                $bankData['is_active'] ?? true,
                $bankData['display_order'] ?? 0,
                $bankData['supports_spei'] ?? true,
                $bankData['supports_card'] ?? false,
                $bankData['supports_transfer'] ?? true,
                $bankData['logo_url'] ?? null
            ]);
            
            $this->logger->info("Banco agregado: {$bankData['bank_name']}");
            
            return ['success' => true, 'bank_id' => $this->pdo->lastInsertId()];
        } catch (Exception $e) {
            $this->logger->error("Error agregando banco: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Actualizar banco
     */
    public function updateBank($bankId, $bankData) {
        try {
            $fields = [];
            $values = [];
            
            foreach (['bank_name', 'bank_code', 'clabe', 'account_number', 'account_holder', 'rfc', 
                     'is_active', 'display_order', 'supports_spei', 'supports_card', 'supports_transfer', 'logo_url'] as $field) {
                if (array_key_exists($field, $bankData)) {
                    $fields[] = "{$field} = ?";
                    $values[] = $bankData[$field];
                }
            }
            
            if (empty($fields)) {
                return ['success' => false, 'message' => 'No hay campos para actualizar'];
            }
            
            $values[] = $bankId;
            $sql = "UPDATE mexican_banks SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($values);
            
            $this->logger->info("Banco actualizado: ID {$bankId}");
            
            return ['success' => true];
        } catch (Exception $e) {
            $this->logger->error("Error actualizando banco: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Eliminar banco (soft delete)
     */
    public function deleteBank($bankId) {
        try {
            $stmt = $this->pdo->prepare("UPDATE mexican_banks SET is_active = false, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$bankId]);
            
            $this->logger->info("Banco desactivado: ID {$bankId}");
            
            return ['success' => true];
        } catch (Exception $e) {
            $this->logger->error("Error desactivando banco: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Obtener métodos de pago activos
     */
    public function getActivePaymentMethods() {
        try {
            $stmt = $this->pdo->query("SELECT * FROM get_active_payment_methods()");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Error obteniendo métodos de pago: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener método de pago por código
     */
    public function getPaymentMethod($methodCode) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM payment_methods_config WHERE method_code = ? AND is_enabled = true");
            $stmt->execute([$methodCode]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Error obteniendo método de pago: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Calcular comisión de método de pago
     */
    public function calculatePaymentFee($amount, $methodCode) {
        $method = $this->getPaymentMethod($methodCode);
        if (!$method) {
            return 0;
        }
        
        $feePercentage = floatval($method['fee_percentage'] ?? 0);
        $feeFixed = floatval($method['fee_fixed'] ?? 0);
        
        $totalFee = ($amount * ($feePercentage / 100)) + $feeFixed;
        
        return round($totalFee, 2);
    }
    
    /**
     * Validar CLABE
     */
    public function validateClabe($clabe) {
        // Validar longitud
        if (strlen($clabe) !== 18) {
            return ['valid' => false, 'message' => 'La CLABE debe tener 18 dígitos'];
        }
        
        // Validar que sean solo números
        if (!preg_match('/^\d{18}$/', $clabe)) {
            return ['valid' => false, 'message' => 'La CLABE debe contener solo números'];
        }
        
        // Validar dígito de control (algoritmo de CLABE)
        $sum = 0;
        $weights = [3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7];
        
        for ($i = 0; $i < 17; $i++) {
            $digit = intval($clabe[$i]);
            $sum += $digit * $weights[$i];
        }
        
        $controlDigit = (10 - ($sum % 10)) % 10;
        
        if ($controlDigit !== intval($clabe[17])) {
            return ['valid' => false, 'message' => 'CLABE inválida - dígito de control incorrecto'];
        }
        
        return ['valid' => true, 'message' => 'CLABE válida'];
    }
    
    /**
     * Obtener configuración fiscal SAT
     */
    public function getSatFiscalConfig() {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM sat_fiscal_config WHERE is_active = true LIMIT 1");
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Error obteniendo configuración fiscal: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Actualizar configuración fiscal SAT
     */
    public function updateSatFiscalConfig($config) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO sat_fiscal_config (company_rfc, company_tax_name, company_tax_regime, company_zip_code, 
                    company_email, company_phone, company_address, facturapi_api_key, pac_provider, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, true)
                ON CONFLICT (id) DO UPDATE SET
                    company_rfc = EXCLUDED.company_rfc,
                    company_tax_name = EXCLUDED.company_tax_name,
                    company_tax_regime = EXCLUDED.company_tax_regime,
                    company_zip_code = EXCLUDED.company_zip_code,
                    company_email = EXCLUDED.company_email,
                    company_phone = EXCLUDED.company_phone,
                    company_address = EXCLUDED.company_address,
                    facturapi_api_key = EXCLUDED.facturapi_api_key,
                    pac_provider = EXCLUDED.pac_provider,
                    updated_at = NOW()
            ");
            
            $stmt->execute([
                $config['company_rfc'],
                $config['company_tax_name'],
                $config['company_tax_regime'],
                $config['company_zip_code'],
                $config['company_email'] ?? null,
                $config['company_phone'] ?? null,
                $config['company_address'] ?? null,
                $config['facturapi_api_key'] ?? null,
                $config['pac_provider'] ?? 'facturapi'
            ]);
            
            $this->logger->info("Configuración fiscal actualizada");
            
            return ['success' => true];
        } catch (Exception $e) {
            $this->logger->error("Error actualizando configuración fiscal: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
