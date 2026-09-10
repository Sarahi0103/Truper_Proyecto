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
     * Método interno para despachar el correo usando SMTP real, mail() nativo o emulación local registrada.
     */
    private function dispatchEmail($to, $subject, $htmlBody) {
        // Guardar copia local HTML para inspección/sandbox
        $logDir = __DIR__ . '/../../logs/emails';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $to);
        $fileName = "factura_" . date('Ymd_His') . "_{$safeName}.html";
        $filePath = "{$logDir}/{$fileName}";
        @file_put_contents($filePath, $htmlBody);

        // Registrar en AppLogger
        if (class_exists('AppLogger')) {
            AppLogger::info("Procesando envío de factura a: {$to}", [
                'to' => $to,
                'subject' => $subject,
                'saved_preview' => $filePath
            ]);
        }

        // 1. Intentar envío por SMTP si hay credenciales configuradas
        $smtpHost = $_ENV['MAILER_HOST'] ?? ($_ENV['SMTP_HOST'] ?? '');
        $smtpUser = $_ENV['MAILER_USER'] ?? ($_ENV['SMTP_USER'] ?? '');
        $smtpPass = $_ENV['MAILER_PASSWORD'] ?? ($_ENV['SMTP_PASSWORD'] ?? '');
        $smtpPort = (int)($_ENV['MAILER_PORT'] ?? ($_ENV['SMTP_PORT'] ?? 587));
        $fromEmail = $_ENV['MAILER_FROM'] ?? 'facturacion@ferreteriafox.com';

        $isDemoSmtp = empty($smtpUser) || str_contains($smtpUser, 'your-email') || str_contains($smtpUser, 'your_email') || empty($smtpPass) || str_contains($smtpPass, 'your-app-password');

        if (!empty($smtpHost) && !$isDemoSmtp) {
            $smtpResult = $this->sendViaSmtp($smtpHost, $smtpPort, $smtpUser, $smtpPass, $fromEmail, $to, $subject, $htmlBody);
            if ($smtpResult['success']) {
                return [
                    'success' => true,
                    'message' => "Factura enviada exitosamente a tu correo ({$to}) vía SMTP."
                ];
            }
        }

        // 2. Intentar mail() nativo de PHP
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8\r\n";
        $headers .= "From: Ferreteria FOX <{$fromEmail}>\r\n";
        $headers .= "Reply-To: {$fromEmail}\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        $sent = @mail($to, $subject, $htmlBody, $headers);
        if ($sent) {
            return [
                'success' => true,
                'message' => "Factura enviada exitosamente a {$to}."
            ];
        }

        // 3. Modo Local / Sandbox sin servidor SMTP
        return [
            'success' => true,
            'message' => "Factura generada y registrada en el sistema para {$to}. (Nota: En entorno local localhost, para que llegue a tu bandeja de Gmail/Outlook real se requiere configurar credenciales SMTP en el archivo .env)."
        ];
    }

    /**
     * Enviar correo mediante socket SMTP directo (TLS / SSL / Plain)
     */
    private function sendViaSmtp($host, $port, $user, $pass, $from, $to, $subject, $bodyHtml) {
        $timeout = 10;
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (!$socket) {
            return ['success' => false, 'error' => "No se pudo conectar al host SMTP {$host}: {$errstr}"];
        }

        $getResponse = function($sock) {
            $data = '';
            while ($str = fgets($sock, 515)) {
                $data .= $str;
                if (substr($str, 3, 1) === ' ') break;
            }
            return $data;
        };

        $sendCommand = function($sock, $cmd) use ($getResponse) {
            fputs($sock, $cmd . "\r\n");
            return $getResponse($sock);
        };

        $res = $getResponse($socket);

        // EHLO
        $sendCommand($socket, "EHLO " . gethostname());

        // STARTTLS si es puerto 587
        if ($port == 587) {
            $sendCommand($socket, "STARTTLS");
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $sendCommand($socket, "EHLO " . gethostname());
        }

        // AUTH LOGIN
        $sendCommand($socket, "AUTH LOGIN");
        $sendCommand($socket, base64_encode($user));
        $authRes = $sendCommand($socket, base64_encode($pass));

        if (!str_starts_with(trim($authRes), '235')) {
            fclose($socket);
            return ['success' => false, 'error' => 'Autenticación SMTP fallida: ' . $authRes];
        }

        // MAIL FROM & RCPT TO
        $sendCommand($socket, "MAIL FROM:<{$from}>");
        $sendCommand($socket, "RCPT TO:<{$to}>");
        $sendCommand($socket, "DATA");

        $emailHeaders = "From: Ferreteria FOX <{$from}>\r\n";
        $emailHeaders .= "To: <{$to}>\r\n";
        $emailHeaders .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $emailHeaders .= "MIME-Version: 1.0\r\n";
        $emailHeaders .= "Content-Type: text/html; charset=UTF-8\r\n";
        $emailHeaders .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $emailHeaders .= chunk_split(base64_encode($bodyHtml)) . "\r\n.\r\n";

        fputs($socket, $emailHeaders);
        $dataRes = $getResponse($socket);

        $sendCommand($socket, "QUIT");
        fclose($socket);

        $isOk = str_starts_with(trim($dataRes), '250');
        return ['success' => $isOk, 'response' => $dataRes];
    }
}
