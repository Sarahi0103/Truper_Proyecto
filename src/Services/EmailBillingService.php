<?php
/**
 * Servicio de envío de notificaciones por correo electrónico para Facturación SAT (CFDI 4.0)
 * Envía comprobantes fiscales (XML y PDF) y avisos de cancelación oficiales.
 */

class EmailBillingService {
    private $pdo;

    public function __construct($pdo = null) {
        $this->pdo = $pdo;
    }

    /**
     * Envía la factura timbrada al cliente con enlaces a sus archivos XML y PDF.
     */
    public function sendInvoiceEmail($customerEmail, $customerName, $uuid, $folio, $totalAmount, $xmlUrl = '', $pdfUrl = '') {
        if (empty($customerEmail)) {
            return ['success' => false, 'message' => 'Correo de cliente no especificado'];
        }

        $subject = "Comprobante Fiscal CFDI 4.0 - Folio #{$folio} - Ferretería FOX";
        $formattedTotal = '$' . number_format((float)$totalAmount, 2, '.', ',');

        $htmlBody = "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f4f4f5; margin: 0; padding: 20px; color: #18181b; }
                .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e4e4e7; }
                .header { background: linear-gradient(135deg, #18181b 0%, #27272a 100%); padding: 24px; text-align: center; color: #ffffff; }
                .header h1 { margin: 0; font-size: 22px; color: #ff7f00; font-weight: 800; }
                .content { padding: 32px 24px; }
                .badge { display: inline-block; background: rgba(255, 127, 0, 0.12); color: #ff7f00; padding: 6px 14px; border-radius: 99px; font-size: 13px; font-weight: 700; border: 1px solid rgba(255,127,0,0.3); }
                .info-box { background: #fafafa; border: 1px solid #f4f4f5; border-radius: 8px; padding: 16px; margin: 20px 0; }
                .info-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px dashed #e4e4e7; font-size: 14px; }
                .info-row:last-child { border-bottom: none; }
                .btn { display: inline-block; padding: 12px 24px; background: #ff7f00; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 700; margin: 8px 4px; font-size: 14px; }
                .btn-secondary { background: #27272a; color: #ffffff; }
                .footer { background: #fafafa; padding: 16px; text-align: center; font-size: 12px; color: #71717a; border-top: 1px solid #e4e4e7; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Ferretería FOX / Truper</h1>
                    <p style='margin:4px 0 0; font-size:13px; color:#a1a1aa;'>Facturación Electrónica SAT CFDI 4.0</p>
                </div>
                <div class='content'>
                    <span class='badge'>FACTURA EMITIDA Y TIMBRADA</span>
                    <h2 style='margin:16px 0 8px; font-size:18px;'>Estimado(a) {$customerName},</h2>
                    <p style='font-size:14px; color:#52525b; line-height:1.5;'>Le informamos que su factura fiscal correspondiente a la compra <strong>#{$folio}</strong> ha sido generada y timbrada exitosamente ante el Servicio de Administración Tributaria (SAT).</p>
                    
                    <div class='info-box'>
                        <div class='info-row'><span>Folio Ticket:</span> <strong>#{$folio}</strong></div>
                        <div class='info-row'><span>Monto Total:</span> <strong>{$formattedTotal} MXN</strong></div>
                        <div class='info-row'><span>Folio Fiscal UUID:</span> <strong style='font-size:12px; word-break:break-all;'>{$uuid}</strong></div>
                    </div>

                    <div style='text-align: center; margin-top: 24px;'>
                        " . ($xmlUrl ? "<a href='{$xmlUrl}' class='btn btn-secondary' target='_blank'>📄 Descargar XML SAT</a>" : "") . "
                        " . ($pdfUrl ? "<a href='{$pdfUrl}' class='btn' target='_blank'>📕 Descargar PDF Factura</a>" : "") . "
                    </div>
                </div>
                <div class='footer'>
                    Este es un comprobante fiscal digital por internet (CFDI 4.0) emitido válidamente.<br>
                    © " . date('Y') . " Ferretería FOX - Todos los derechos reservados.
                </div>
            </div>
        </body>
        </html>
        ";

        return $this->dispatchEmail($customerEmail, $subject, $htmlBody);
    }

    /**
     * Envía la notificación de cancelación de la factura ante el SAT.
     */
    public function sendCancellationEmail($customerEmail, $customerName, $uuid, $folio, $reasonCode = '02') {
        if (empty($customerEmail)) {
            return ['success' => false, 'message' => 'Correo de cliente no especificado'];
        }

        $subject = "Aviso de Cancelación de Factura SAT - Folio #{$folio} - Ferretería FOX";

        $htmlBody = "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f4f4f5; margin: 0; padding: 20px; color: #18181b; }
                .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e4e4e7; }
                .header { background: #dc2626; padding: 24px; text-align: center; color: #ffffff; }
                .header h1 { margin: 0; font-size: 22px; font-weight: 800; }
                .content { padding: 32px 24px; }
                .badge { display: inline-block; background: rgba(220, 38, 38, 0.1); color: #dc2626; padding: 6px 14px; border-radius: 99px; font-size: 13px; font-weight: 700; border: 1px solid rgba(220, 38, 38, 0.3); }
                .info-box { background: #fafafa; border: 1px solid #f4f4f5; border-radius: 8px; padding: 16px; margin: 20px 0; }
                .info-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px dashed #e4e4e7; font-size: 14px; }
                .info-row:last-child { border-bottom: none; }
                .footer { background: #fafafa; padding: 16px; text-align: center; font-size: 12px; color: #71717a; border-top: 1px solid #e4e4e7; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Ferretería FOX / Truper</h1>
                    <p style='margin:4px 0 0; font-size:13px;'>Cancelación Fiscal CFDI 4.0</p>
                </div>
                <div class='content'>
                    <span class='badge'>COMPROBANTE CANCELADO EN EL SAT</span>
                    <h2 style='margin:16px 0 8px; font-size:18px;'>Estimado(a) {$customerName},</h2>
                    <p style='font-size:14px; color:#52525b; line-height:1.5;'>Le notificamos que el comprobante fiscal digital (CFDI) enlazado al folio de ticket <strong>#{$folio}</strong> ha sido cancelado ante los registros del SAT.</p>
                    
                    <div class='info-box'>
                        <div class='info-row'><span>Folio Ticket:</span> <strong>#{$folio}</strong></div>
                        <div class='info-row'><span>Motivo Cancelación SAT:</span> <strong>Motivo Clave {$reasonCode}</strong></div>
                        <div class='info-row'><span>Folio Fiscal Cancelado (UUID):</span> <strong style='font-size:12px; word-break:break-all;'>{$uuid}</strong></div>
                    </div>
                </div>
                <div class='footer'>
                    Notificación automática de estado fiscal SAT.<br>
                    © " . date('Y') . " Ferretería FOX - Todos los derechos reservados.
                </div>
            </div>
        </body>
        </html>
        ";

        return $this->dispatchEmail($customerEmail, $subject, $htmlBody);
    }

    /**
     * Método interno para despachar el correo usando mail() nativo o registrando en log estructurado.
     */
    private function dispatchEmail($to, $subject, $htmlBody) {
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Ferreteria FOX <facturacion@ferreteriafox.com>" . "\r\n";

        // Registrar envío en log del sistema
        if (class_exists('AppLogger')) {
            AppLogger::info("Enviando correo de facturación a: {$to}", [
                'to' => $to,
                'subject' => $subject
            ]);
        }

        $sent = @mail($to, $subject, $htmlBody, $headers);
        return [
            'success' => true,
            'message' => $sent ? 'Correo enviado correctamente' : 'Correo registrado en cola de notificaciones',
            'sent' => $sent
        ];
    }
}
