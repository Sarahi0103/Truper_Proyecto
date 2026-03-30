<?php
/**
 * Utilidades generales - Truper
 */

class Logger {
    private static $log_file = BASE_PATH . '/logs/app.log';

    public static function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $user_id = $_SESSION['user_id'] ?? 'GUEST';
        $log_entry = "[$timestamp] [$level] [User: $user_id] $message\n";
        
        error_log($log_entry, 3, self::$log_file);
    }

    public static function error($message) {
        self::log($message, 'ERROR');
    }

    public static function info($message) {
        self::log($message, 'INFO');
    }

    public static function warning($message) {
        self::log($message, 'WARNING');
    }
}

class EmailService {
    public static function sendBirthdayBonus($email, $name, $bonus_amount) {
        $subject = "¡Felicidades! Bonificación de cumpleaños Truper";
        $message = "
        <html>
            <head>
                <title>Bonificación de Cumpleaños Truper</title>
            </head>
            <body>
                <h2>¡Feliz Cumpleaños, $name!</h2>
                <p>En Truper queremos celebrar tu día especial con un BONO de $bonus_amount puntos.</p>
                <p>Este bono está disponible en tu cuenta para ser usado en tu próxima compra.</p>
                <br>
                <p>Accede a tu cuenta en: <a href='https://truper.com/login'>Truper Portal</a></p>
                <br>
                <p>Saludos,<br>Equipo Truper</p>
            </body>
        </html>";
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        
        return mail($email, $subject, $message, $headers);
    }

    public static function sendOrderConfirmation($email, $order_id, $total) {
        $subject = "Confirmación de Pedido #$order_id - Truper";
        $message = "
        <html>
            <head>
                <title>Pedido Confirmado</title>
            </head>
            <body>
                <h2>Pedido Confirmado</h2>
                <p>Número de pedido: <strong>$order_id</strong></p>
                <p>Total: <strong>\$$total</strong></p>
                <p>Puedes seguir el estado de tu pedido en tu cuenta.</p>
                <br>
                <p>Saludos,<br>Equipo Truper</p>
            </body>
        </html>";
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        
        return mail($email, $subject, $message, $headers);
    }
}

class Invoice {
    public static function generate($order_id) {
        require_once __DIR__ . '/../models/Order.php';
        $order_model = new Order();
        $order = $order_model->getOrderDetail($order_id);
        $items = $order_model->getOrderItems($order_id);
        
        return [
            'order' => $order,
            'items' => $items,
            'invoice_number' => 'INV-' . $order_id . '-' . strtoupper(substr(md5($order_id), 0, 4)),
            'invoice_date' => date('Y-m-d'),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    public static function printTicket($order_id) {
        $invoice = self::generate($order_id);
        $ticket = "
========================================
        Truper - COMPROBANTE DE VENTA
========================================
Folio: " . $invoice['invoice_number'] . "
Fecha: " . $invoice['invoice_date'] . "

Cliente: " . $invoice['order']['name'] . "
Email: " . $invoice['order']['email'] . "

----------------------------------------
PRODUCTOS:
----------------------------------------
";
        
        foreach ($invoice['items'] as $item) {
            $ticket .= sprintf("%-30s %8.2f x %2d = %8.2f\n", 
                substr($item['name'], 0, 30),
                $item['unit_price'],
                $item['quantity'],
                $item['subtotal']
            );
        }
        
        $ticket .= "
----------------------------------------
TOTAL: $" . $invoice['order']['total'] . "
Fecha y Hora: " . $invoice['generated_at'] . "
========================================
";
        
        return $ticket;
    }
}

class ChartData {
    public static function generateChartJSON($data, $type = 'bar') {
        return json_encode(['data' => $data, 'type' => $type]);
    }
}
?>


