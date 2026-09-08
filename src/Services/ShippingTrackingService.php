<?php
/**
 * Shipping Tracking Service
 * Sistema de tracking de envíos con integración de paqueterías
 */

class ShippingTrackingService {
    private $pdo;
    private $logger;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->logger = new AppLogger();
    }
    
    /**
     * Crear registro de tracking para un pedido
     * 
     * @param array $trackingData Datos de tracking
     * @return array Resultado de la operación
     */
    public function createTracking($trackingData) {
        try {
            // Verificar si ya existe registro de tracking para este pedido
            $chk = $this->pdo->prepare("SELECT id FROM shipping_tracking WHERE order_id = ? LIMIT 1");
            $chk->execute([$trackingData['order_id']]);
            $existingId = $chk->fetchColumn();

            if ($existingId) {
                $stmt = $this->pdo->prepare("
                    UPDATE shipping_tracking 
                    SET carrier = ?, tracking_number = ?, shipping_date = COALESCE(?, shipping_date, CURRENT_TIMESTAMP), 
                        estimated_delivery = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([
                    $trackingData['carrier'],
                    $trackingData['tracking_number'],
                    $trackingData['shipping_date'] ?? null,
                    $trackingData['estimated_delivery'] ?? null,
                    $existingId
                ]);
                $trackingId = $existingId;
                $this->logger->info("Tracking updated for order {$trackingData['order_id']}: {$trackingData['tracking_number']}");
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO shipping_tracking 
                    (order_id, carrier, tracking_number, shipping_date, estimated_delivery, shipping_address)
                    VALUES (?, ?, ?, ?, ?, ?)
                    RETURNING id
                ");
                
                $stmt->execute([
                    $trackingData['order_id'],
                    $trackingData['carrier'],
                    $trackingData['tracking_number'],
                    $trackingData['shipping_date'] ?? null,
                    $trackingData['estimated_delivery'] ?? null,
                    json_encode($trackingData['shipping_address'] ?? [])
                ]);
                
                $trackingId = $stmt->fetchColumn();
                $this->logger->info("Tracking created for order {$trackingData['order_id']}: {$trackingData['tracking_number']}");
            }
            
            return [
                'success' => true,
                'tracking_id' => $trackingId
            ];
        } catch (Exception $e) {
            $this->logger->error("Error saving tracking: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al guardar tracking: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener tracking de un pedido
     * 
     * @param int $orderId ID del pedido
     * @return array|null Datos de tracking
     */
    public function getOrderTracking($orderId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, order_id, carrier, tracking_number, shipping_date, 
                       estimated_delivery, actual_delivery, tracking_status, tracking_events
                FROM shipping_tracking
                WHERE order_id = ?
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$orderId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            $this->logger->error("Error getting order tracking: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Actualizar estado de tracking
     * 
     * @param int $trackingId ID del tracking
     * @param string $status Nuevo estado
     * @param string $description Descripción del evento
     * @param string $location Ubicación
     * @return array Resultado de la operación
     */
    public function updateTrackingStatus($trackingId, $status, $description = '', $location = '') {
        try {
            $stmt = $this->pdo->prepare("SELECT add_tracking_event(?, ?, ?, ?)");
            $stmt->execute([$trackingId, $status, $description, $location]);
            
            // Si el estado es 'delivered', actualizar fecha de entrega
            if ($status === 'delivered') {
                $this->pdo->prepare("UPDATE shipping_tracking SET actual_delivery = NOW() WHERE id = ?")
                    ->execute([$trackingId]);
            }
            
            $this->logger->info("Tracking {$trackingId} updated to status: {$status}");
            
            return ['success' => true];
        } catch (Exception $e) {
            $this->logger->error("Error updating tracking status: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al actualizar tracking'
            ];
        }
    }
    
    /**
     * Simular tracking desde API de paquetería (placeholder)
     * En producción, esto se conectaría a las APIs reales de FedEx, DHL, etc.
     * 
     * @param string $carrier Código de paquetería
     * @param string $trackingNumber Número de tracking
     * @return array Datos de tracking simulados
     */
    public function fetchTrackingFromCarrier($carrier, $trackingNumber) {
        // Placeholder para integración real con APIs de paqueterías
        // Aquí se implementaría la lógica para conectar con:
        // - FedEx API
        // - DHL API
        // - Estafeta API
        // - Redpack API
        // etc.
        
        $this->logger->info("Fetching tracking from {$carrier} for {$trackingNumber}");
        
        return [
            'status' => 'in_transit',
            'events' => [
                [
                    'status' => 'picked_up',
                    'description' => 'Paquete recogido',
                    'location' => 'Origen',
                    'timestamp' => date('Y-m-d H:i:s', strtotime('-2 days'))
                ],
                [
                    'status' => 'in_transit',
                    'description' => 'En tránsito',
                    'location' => 'Centro de distribución',
                    'timestamp' => date('Y-m-d H:i:s', strtotime('-1 day'))
                ]
            ]
        ];
    }
    
    /**
     * Obtener lista de paqueterías configuradas
     * 
     * @return array Lista de paqueterías
     */
    public function getCarriers() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, name, code, is_active
                FROM shipping_carriers
                WHERE is_active = true
                ORDER BY name
            ");
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Error getting carriers: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Sincronizar tracking desde API de paquetería
     * 
     * @param int $trackingId ID del tracking
     * @return array Resultado de la operación
     */
    public function syncTracking($trackingId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT carrier, tracking_number
                FROM shipping_tracking
                WHERE id = ?
            ");
            $stmt->execute([$trackingId]);
            $tracking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$tracking) {
                return ['success' => false, 'error' => 'Tracking no encontrado'];
            }
            
            // Obtener tracking desde API de paquetería
            $carrierData = $this->fetchTrackingFromCarrier($tracking['carrier'], $tracking['tracking_number']);
            
            // Actualizar eventos de tracking
            foreach ($carrierData['events'] as $event) {
                $this->updateTrackingStatus(
                    $trackingId,
                    $event['status'],
                    $event['description'],
                    $event['location']
                );
            }
            
            return ['success' => true];
        } catch (Exception $e) {
            $this->logger->error("Error syncing tracking: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al sincronizar tracking'
            ];
        }
    }
}
