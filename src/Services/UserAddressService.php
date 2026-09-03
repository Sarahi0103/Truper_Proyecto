<?php
/**
 * User Address Service
 * Sistema de gestión de direcciones múltiples por cliente
 */

class UserAddressService {
    private $pdo;
    private $logger;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->logger = new AppLogger();
    }
    
    /**
     * Obtener todas las direcciones de un usuario
     * 
     * @param int $userId ID del usuario
     * @return array Lista de direcciones
     */
    public function getUserAddresses($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, address_line1, address_line2, city, state, postal_code, 
                       country, address_label, is_default, is_active
                FROM user_addresses
                WHERE user_id = ? AND is_active = true
                ORDER BY is_default DESC, created_at DESC
            ");
            $stmt->execute([$userId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Error getting user addresses: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener dirección predeterminada del usuario
     * 
     * @param int $userId ID del usuario
     * @return array|null Dirección predeterminada o null
     */
    public function getDefaultAddress($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, address_line1, address_line2, city, state, postal_code, 
                       country, address_label
                FROM user_addresses
                WHERE user_id = ? AND is_default = true AND is_active = true
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            $this->logger->error("Error getting default address: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Crear nueva dirección
     * 
     * @param array $addressData Datos de la dirección
     * @return array Resultado de la operación
     */
    public function createAddress($addressData) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_addresses 
                (user_id, address_line1, address_line2, city, state, postal_code, 
                 country, address_label, is_default)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                RETURNING id
            ");
            
            $stmt->execute([
                $addressData['user_id'],
                $addressData['address_line1'],
                $addressData['address_line2'] ?? null,
                $addressData['city'],
                $addressData['state'],
                $addressData['postal_code'],
                $addressData['country'] ?? 'México',
                $addressData['address_label'] ?? null,
                $addressData['is_default'] ?? false
            ]);
            
            $addressId = $stmt->fetchColumn();
            
            // Si es default, quitar default de otras direcciones
            if ($addressData['is_default'] ?? false) {
                $this->setDefaultAddress($addressData['user_id'], $addressId);
            }
            
            $this->logger->info("Address created for user {$addressData['user_id']}");
            
            return [
                'success' => true,
                'address_id' => $addressId
            ];
        } catch (Exception $e) {
            $this->logger->error("Error creating address: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al crear dirección'
            ];
        }
    }
    
    /**
     * Actualizar dirección existente
     * 
     * @param int $addressId ID de la dirección
     * @param array $addressData Datos a actualizar
     * @return array Resultado de la operación
     */
    public function updateAddress($addressId, $addressData) {
        try {
            $setParts = [];
            $params = [];
            
            foreach ($addressData as $key => $value) {
                if ($value !== null && $key !== 'user_id') {
                    $setParts[] = "{$key} = ?";
                    $params[] = $value;
                }
            }
            
            if (empty($setParts)) {
                return ['success' => false, 'error' => 'No hay datos para actualizar'];
            }
            
            $params[] = $addressId;
            
            $sql = "UPDATE user_addresses SET " . implode(', ', $setParts) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            // Si se estableció como default, quitar default de otras
            if ($addressData['is_default'] ?? false) {
                $addrStmt = $this->pdo->prepare("SELECT user_id FROM user_addresses WHERE id = ?");
                $addrStmt->execute([$addressId]);
                $address = $addrStmt->fetch();
                
                if ($address) {
                    $this->setDefaultAddress($address['user_id'], $addressId);
                }
            }
            
            $this->logger->info("Address {$addressId} updated");
            
            return ['success' => true];
        } catch (Exception $e) {
            $this->logger->error("Error updating address: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al actualizar dirección'
            ];
        }
    }
    
    /**
     * Eliminar dirección (desactivar)
     * 
     * @param int $addressId ID de la dirección
     * @return array Resultado de la operación
     */
    public function deleteAddress($addressId) {
        try {
            $stmt = $this->pdo->prepare("UPDATE user_addresses SET is_active = false WHERE id = ?");
            $stmt->execute([$addressId]);
            
            $this->logger->info("Address {$addressId} deactivated");
            
            return ['success' => true];
        } catch (Exception $e) {
            $this->logger->error("Error deactivating address: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al eliminar dirección'
            ];
        }
    }
    
    /**
     * Establecer dirección como predeterminada
     * 
     * @param int $userId ID del usuario
     * @param int $addressId ID de la dirección
     * @return array Resultado de la operación
     */
    public function setDefaultAddress($userId, $addressId) {
        try {
            $stmt = $this->pdo->prepare("SELECT set_default_address(?, ?)");
            $stmt->execute([$userId, $addressId]);
            
            $this->logger->info("Address {$addressId} set as default for user {$userId}");
            
            return ['success' => true];
        } catch (Exception $e) {
            $this->logger->error("Error setting default address: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al establecer dirección predeterminada'
            ];
        }
    }
}
