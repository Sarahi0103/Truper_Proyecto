<?php
/**
 * Email Service
 * Sistema de notificaciones por email para pedidos
 */

class EmailService {
    private $pdo;
    private $logger;
    private $fromEmail;
    private $fromName;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->logger = new AppLogger();
        
        // Configuración de email (pueden venir de config o DB)
        $this->fromEmail = $_ENV['EMAIL_FROM'] ?? 'noreply@ferreteriafox.com';
        $this->fromName = $_ENV['EMAIL_FROM_NAME'] ?? 'Ferretería FOX';
    }
    
    /**
     * Enviar email de confirmación de pedido
     * 
     * @param array $orderData Datos del pedido
     * @param array $customerData Datos del cliente
     * @return bool Resultado del envío
     */
    public function sendOrderConfirmation($orderData, $customerData) {
        $toEmail = $customerData['email'] ?? '';
        $toName = $customerData['name'] ?? $customerData['customer_name'] ?? 'Cliente';
        
        if (empty($toEmail)) {
            $this->logger->warning("No email address for order confirmation");
            return false;
        }
        
        $subject = "Pedido Confirmado - {$orderData['folio']}";
        
        $body = $this->renderOrderConfirmationTemplate($orderData, $customerData);
        
        return $this->sendEmail($toEmail, $toName, $subject, $body);
    }
    
    /**
     * Enviar email de actualización de estado de pedido
     * 
     * @param array $orderData Datos del pedido
     * @param string $newStatus Nuevo estado
     * @return bool Resultado del envío
     */
    public function sendOrderStatusUpdate($orderData, $newStatus) {
        $toEmail = $orderData['customer_email'] ?? '';
        $toName = $orderData['customer_name'] ?? 'Cliente';
        
        if (empty($toEmail)) {
            $this->logger->warning("No email address for order status update");
            return false;
        }
        
        $statusLabels = [
            'pending' => 'Pendiente',
            'confirmed' => 'Confirmado',
            'processing' => 'En Proceso',
            'shipped' => 'Enviado',
            'delivered' => 'Entregado',
            'cancelled' => 'Cancelado'
        ];
        
        $statusLabel = $statusLabels[$newStatus] ?? $newStatus;
        $subject = "Actualización de Pedido - {$orderData['folio']}: {$statusLabel}";
        
        $body = $this->renderStatusUpdateTemplate($orderData, $statusLabel);
        
        return $this->sendEmail($toEmail, $toName, $subject, $body);
    }
    
    /**
     * Enviar email de solicitud RMA
     * 
     * @param array $rmaData Datos de la solicitud RMA
     * @return bool Resultado del envío
     */
    public function sendRMAConfirmation($rmaData) {
        $toEmail = $rmaData['customer_email'] ?? '';
        $toName = $rmaData['customer_name'] ?? 'Cliente';
        
        if (empty($toEmail)) {
            return false;
        }
        
        $subject = "Solicitud de Devolución Recibida - RMA #{$rmaData['id']}";
        $body = $this->renderRMAConfirmationTemplate($rmaData);
        
        return $this->sendEmail($toEmail, $toName, $subject, $body);
    }
    
    /**
     * Enviar email de actualización de RMA
     * 
     * @param array $rmaData Datos de la solicitud RMA
     * @return bool Resultado del envío
     */
    public function sendRMAStatusUpdate($rmaData) {
        $toEmail = $rmaData['customer_email'] ?? '';
        $toName = $rmaData['customer_name'] ?? 'Cliente';
        
        if (empty($toEmail)) {
            return false;
        }
        
        $subject = "Actualización de Solicitud de Devolución - RMA #{$rmaData['id']}";
        $body = $this->renderRMAStatusUpdateTemplate($rmaData);
        
        return $this->sendEmail($toEmail, $toName, $subject, $body);
    }
    
    /**
     * Enviar email usando PHP mail o SMTP
     * 
     * @param string $toEmail Email del destinatario
     * @param string $toName Nombre del destinatario
     * @param string $subject Asunto
     * @param string $body Cuerpo HTML del email
     * @return bool Resultado del envío
     */
    private function sendEmail($toEmail, $toName, $subject, $body) {
        try {
            // Headers del email
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: {$this->fromName} <{$this->fromEmail}>\r\n";
            $headers .= "Reply-To: {$this->fromEmail}\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();
            
            // Enviar email
            $result = mail($toEmail, $subject, $body, $headers);
            
            if ($result) {
                $this->logger->info("Email sent to {$toEmail}: {$subject}");
            } else {
                $this->logger->error("Failed to send email to {$toEmail}");
            }
            
            return $result;
        } catch (Exception $e) {
            $this->logger->error("Email service error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Renderizar template de confirmación de pedido
     */
    private function renderOrderConfirmationTemplate($orderData, $customerData) {
        $total = number_format((float)($orderData['total_amount'] ?? 0), 2);
        $date = date('d/m/Y', strtotime($orderData['issued_date'] ?? 'now'));
        $paymentMethod = $orderData['payment_method'] ?? 'No especificado';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 10px; overflow: hidden; }
                .header { background: linear-gradient(135deg, #ff7f00, #ff5500); color: #fff; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { padding: 30px; }
                .order-info { background: #f9f9f9; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .order-info p { margin: 10px 0; }
                .btn { display: inline-block; background: #ff7f00; color: #fff; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
                .footer { background: #333; color: #fff; padding: 20px; text-align: center; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>✅ Pedido Confirmado</h1>
                </div>
                <div class='content'>
                    <p>Hola <strong>{$toName}</strong>,</p>
                    <p>Tu pedido ha sido confirmado exitosamente. Gracias por tu compra en Ferretería FOX.</p>
                    
                    <div class='order-info'>
                        <p><strong>Folio del Pedido:</strong> {$orderData['folio']}</p>
                        <p><strong>Fecha:</strong> {$date}</p>
                        <p><strong>Total:</strong> \${$total} MXN</p>
                        <p><strong>Método de Pago:</strong> {$paymentMethod}</p>
                    </div>
                    
                    <p>Te notificaremos cuando tu pedido sea procesado y enviado.</p>
                    <p>Puedes rastrear tu pedido en cualquier momento desde tu cuenta.</p>
                    
                    <a href='https://ferreteriafox.com/order_history.php' class='btn'>Ver Mi Pedido</a>
                </div>
                <div class='footer'>
                    <p>&copy; 2026 Ferretería FOX. Todos los derechos reservados.</p>
                    <p>Este es un email automático, por favor no responder.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Renderizar template de actualización de estado
     */
    private function renderStatusUpdateTemplate($orderData, $statusLabel) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 10px; overflow: hidden; }
                .header { background: linear-gradient(135deg, #3b82f6, #2563eb); color: #fff; padding: 30px; text-align: center; }
                .content { padding: 30px; }
                .status-badge { display: inline-block; background: #22c55e; color: #fff; padding: 8px 20px; border-radius: 20px; font-weight: bold; margin: 20px 0; }
                .btn { display: inline-block; background: #3b82f6; color: #fff; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
                .footer { background: #333; color: #fff; padding: 20px; text-align: center; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>📦 Actualización de Pedido</h1>
                </div>
                <div class='content'>
                    <p>Hola,</p>
                    <p>Tu pedido <strong>{$orderData['folio']}</strong> ha sido actualizado.</p>
                    
                    <div class='status-badge'>{$statusLabel}</div>
                    
                    <p>Te mantendremos informado sobre el progreso de tu pedido.</p>
                    
                    <a href='https://ferreteriafox.com/order_tracking.php?folio={$orderData['folio']}' class='btn'>Rastrear Pedido</a>
                </div>
                <div class='footer'>
                    <p>&copy; 2026 Ferretería FOX. Todos los derechos reservados.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Renderizar template de confirmación RMA
     */
    private function renderRMAConfirmationTemplate($rmaData) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 10px; overflow: hidden; }
                .header { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: #fff; padding: 30px; text-align: center; }
                .content { padding: 30px; }
                .rma-info { background: #f9f9f9; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .footer { background: #333; color: #fff; padding: 20px; text-align: center; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔄 Solicitud de Devolución Recibida</h1>
                </div>
                <div class='content'>
                    <p>Hola,</p>
                    <p>Hemos recibido tu solicitud de devolución RMA #{$rmaData['id']}.</p>
                    
                    <div class='rma-info'>
                        <p><strong>Pedido:</strong> {$rmaData['order_folio']}</p>
                        <p><strong>Motivo:</strong> {$rmaData['reason']}</p>
                        <p><strong>Estado:</strong> Pendiente de revisión</p>
                    </div>
                    
                    <p>Revisaremos tu solicitud y te notificaremos cuando tengamos una respuesta.</p>
                </div>
                <div class='footer'>
                    <p>&copy; 2026 Ferretería FOX. Todos los derechos reservados.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Renderizar template de actualización RMA
     */
    private function renderRMAStatusUpdateTemplate($rmaData) {
        $statusColors = [
            'approved' => '#22c55e',
            'rejected' => '#ef4444',
            'completed' => '#3b82f6'
        ];
        $color = $statusColors[$rmaData['status']] ?? '#888';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 10px; overflow: hidden; }
                .header { background: {$color}; color: #fff; padding: 30px; text-align: center; }
                .content { padding: 30px; }
                .status-badge { display: inline-block; background: {$color}; color: #fff; padding: 8px 20px; border-radius: 20px; font-weight: bold; margin: 20px 0; }
                .footer { background: #333; color: #fff; padding: 20px; text-align: center; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔄 Actualización RMA #{$rmaData['id']}</h1>
                </div>
                <div class='content'>
                    <p>Tu solicitud de devolución ha sido actualizada.</p>
                    
                    <div class='status-badge'>{$rmaData['status']}</div>
                    
                    <p>Revisa tu cuenta para más detalles sobre tu solicitud.</p>
                </div>
                <div class='footer'>
                    <p>&copy; 2026 Ferretería FOX. Todos los derechos reservados.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
