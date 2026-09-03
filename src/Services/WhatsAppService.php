<?php
/**
 * WhatsApp Service
 * Sistema de notificaciones por WhatsApp
 * Integración con WhatsApp Business API (Twilio o Meta)
 */

class WhatsAppService {
    private $pdo;
    private $logger;
    private $apiKey;
    private $phoneNumberId;
    private $fromNumber;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->logger = new AppLogger();
        
        // Configuración de WhatsApp (pueden venir de config o DB)
        $this->apiKey = $_ENV['WHATSAPP_API_KEY'] ?? '';
        $this->phoneNumberId = $_ENV['WHATSAPP_PHONE_NUMBER_ID'] ?? '';
        $this->fromNumber = $_ENV['WHATSAPP_FROM_NUMBER'] ?? '';
    }
    
    /**
     * Enviar mensaje de WhatsApp
     * 
     * @param string $to Número de teléfono del destinatario (con código de país)
     * @param string $message Mensaje a enviar
     * @return array Resultado del envío
     */
    public function sendMessage($to, $message) {
        if (empty($this->apiKey) || empty($this->phoneNumberId)) {
            $this->logger->warning("WhatsApp no está configurado");
            return ['success' => false, 'error' => 'WhatsApp no configurado'];
        }
        
        try {
            $url = "https://graph.facebook.com/v18.0/{$this->phoneNumberId}/messages";
            
            $data = [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => [
                    'body' => $message
                ]
            ];
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 || $httpCode === 201) {
                $this->logger->info("WhatsApp message sent to {$to}");
                return ['success' => true];
            } else {
                $this->logger->error("WhatsApp API error: HTTP {$httpCode}, Response: {$response}");
                return ['success' => false, 'error' => 'Error en API de WhatsApp'];
            }
        } catch (Exception $e) {
            $this->logger->error("WhatsApp service error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al enviar mensaje'];
        }
    }
    
    /**
     * Enviar notificación de confirmación de pedido
     * 
     * @param string $phoneNumber Número de teléfono del cliente
     * @param array $orderData Datos del pedido
     * @return array Resultado del envío
     */
    public function sendOrderConfirmation($phoneNumber, $orderData) {
        if (empty($phoneNumber)) {
            return ['success' => false, 'error' => 'Número de teléfono requerido'];
        }
        
        // Formatear número de teléfono para WhatsApp (agregar código de país si no tiene)
        $toNumber = $this->formatPhoneNumber($phoneNumber);
        
        $message = $this->renderOrderConfirmationTemplate($orderData);
        
        return $this->sendMessage($toNumber, $message);
    }
    
    /**
     * Enviar notificación de actualización de estado de pedido
     * 
     * @param string $phoneNumber Número de teléfono del cliente
     * @param array $orderData Datos del pedido
     * @param string $newStatus Nuevo estado
     * @return array Resultado del envío
     */
    public function sendOrderStatusUpdate($phoneNumber, $orderData, $newStatus) {
        if (empty($phoneNumber)) {
            return ['success' => false, 'error' => 'Número de teléfono requerido'];
        }
        
        $toNumber = $this->formatPhoneNumber($phoneNumber);
        $message = $this->renderStatusUpdateTemplate($orderData, $newStatus);
        
        return $this->sendMessage($toNumber, $message);
    }
    
    /**
     * Enviar notificación de solicitud RMA
     * 
     * @param string $phoneNumber Número de teléfono del cliente
     * @param array $rmaData Datos de la solicitud RMA
     * @return array Resultado del envío
     */
    public function sendRMAConfirmation($phoneNumber, $rmaData) {
        if (empty($phoneNumber)) {
            return ['success' => false, 'error' => 'Número de teléfono requerido'];
        }
        
        $toNumber = $this->formatPhoneNumber($phoneNumber);
        $message = $this->renderRMAConfirmationTemplate($rmaData);
        
        return $this->sendMessage($toNumber, $message);
    }
    
    /**
     * Formatear número de teléfono para WhatsApp
     * 
     * @param string $phone Número de teléfono
     * @return string Número formateado
     */
    private function formatPhoneNumber($phone) {
        // Eliminar caracteres no numéricos excepto +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Si no tiene código de país, agregar +52 (México)
        if (!str_starts_with($phone, '+')) {
            if (strlen($phone) === 10) {
                $phone = '+52' . $phone;
            } else {
                $phone = '+' . $phone;
            }
        }
        
        return $phone;
    }
    
    /**
     * Renderizar template de confirmación de pedido para WhatsApp
     */
    private function renderOrderConfirmationTemplate($orderData) {
        $total = number_format((float)($orderData['total_amount'] ?? 0), 2);
        $folio = $orderData['folio'] ?? 'N/A';
        
        return "✅ *Pedido Confirmado*\n\n" .
               "Folio: {$folio}\n" .
               "Total: \${$total} MXN\n\n" .
               "Tu pedido ha sido confirmado exitosamente. Gracias por tu compra en Ferretería FOX.\n\n" .
               "Te notificaremos cuando tu pedido sea procesado y enviado.\n\n" .
               "Para rastrear tu pedido, visita: https://ferreteriafox.com/order_tracking.php?folio={$folio}";
    }
    
    /**
     * Renderizar template de actualización de estado para WhatsApp
     */
    private function renderStatusUpdateTemplate($orderData, $newStatus) {
        $folio = $orderData['folio'] ?? 'N/A';
        $statusLabels = [
            'pending' => '⏳ Pendiente',
            'confirmed' => '✅ Confirmado',
            'processing' => '🔄 En Proceso',
            'shipped' => '🚚 Enviado',
            'delivered' => '📦 Entregado',
            'cancelled' => '❌ Cancelado'
        ];
        
        $statusLabel = $statusLabels[$newStatus] ?? $newStatus;
        
        return "📦 *Actualización de Pedido*\n\n" .
               "Folio: {$folio}\n" .
               "Estado: {$statusLabel}\n\n" .
               "Te mantendremos informado sobre el progreso de tu pedido.\n\n" .
               "Para rastrear tu pedido: https://ferreteriafox.com/order_tracking.php?folio={$folio}";
    }
    
    /**
     * Renderizar template de confirmación RMA para WhatsApp
     */
    private function renderRMAConfirmationTemplate($rmaData) {
        $rmaId = $rmaData['id'] ?? 'N/A';
        $orderFolio = $rmaData['order_folio'] ?? 'N/A';
        $reason = $rmaData['reason'] ?? 'No especificado';
        
        return "🔄 *Solicitud de Devolución Recibida*\n\n" .
               "RMA #{$rmaId}\n" .
               "Pedido: {$orderFolio}\n" .
               "Motivo: {$reason}\n\n" .
               "Revisaremos tu solicitud y te notificaremos cuando tengamos una respuesta.";
    }
}
