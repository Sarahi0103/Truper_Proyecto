<?php
/**
 * Modelo de Lector de Códigos de Barras - TRUPPER
 */

require_once __DIR__ . '/../config/database.php';

class BarcodeReader {
    private $conn;

    public function __construct() {
        $this->conn = $GLOBALS['db'];
    }

    /**
     * Registrar escaneo de código de barras
     */
    public function scanBarcode($barcode, $transaction_id = null) {
        // Normalizar código de barras
        $barcode = trim($barcode);
        
        // Buscar producto por código de barras
        $query = "SELECT id, name, sku, sell_price FROM products WHERE sku = ? OR barcode = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ss", $barcode, $barcode);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        
        if (!$product) {
            return [
                'success' => false,
                'message' => 'Producto no encontrado',
                'barcode' => $barcode
            ];
        }
        
        // Registrar escaneo
        $query = "INSERT INTO barcode_scans (product_id, barcode, scanned_at) VALUES (?, ?, NOW())";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("is", $product['id'], $barcode);
        $stmt->execute();
        
        return [
            'success' => true,
            'product' => $product,
            'scan_id' => $stmt->insert_id
        ];
    }

    /**
     * Cargar productos por códigos de barras
     */
    public function uploadBarcodes($barcodes_file) {
        // Leer archivo (CSV o TXT)
        $results = [];
        
        if (!file_exists($barcodes_file)) {
            return ['success' => false, 'message' => 'Archivo no encontrado'];
        }
        
        $file = fopen($barcodes_file, 'r');
        $row = 0;
        
        while (($line = fgetcsv($file)) !== FALSE) {
            $row++;
            if ($row === 1) continue; // Saltar encabezados
            
            if (count($line) >= 2) {
                $sku = trim($line[0]);
                $barcode = trim($line[1]);
                
                // Actualizar código de barras del producto
                $query = "UPDATE products SET barcode = ? WHERE sku = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param("ss", $barcode, $sku);
                
                if ($stmt->execute()) {
                    $results[] = [
                        'sku' => $sku,
                        'barcode' => $barcode,
                        'status' => 'success'
                    ];
                } else {
                    $results[] = [
                        'sku' => $sku,
                        'barcode' => $barcode,
                        'status' => 'error'
                    ];
                }
            }
        }
        
        fclose($file);
        
        return [
            'success' => true,
            'message' => 'Códigos importados',
            'results' => $results,
            'total' => count($results)
        ];
    }

    /**
     * Obtener historial de escaneos
     */
    public function getScanHistory($limit = 100) {
        $query = "SELECT bs.id, bs.barcode, bs.scanned_at, p.name, p.sku 
                  FROM barcode_scans bs
                  JOIN products p ON bs.product_id = p.id
                  ORDER BY bs.scanned_at DESC
                  LIMIT ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Obtener estadísticas de escaneos
     */
    public function getScanStats($days = 30) {
        $query = "SELECT 
                    DATE(bs.scanned_at) as scan_date,
                    COUNT(*) as total_scans,
                    COUNT(DISTINCT bs.product_id) as unique_products
                  FROM barcode_scans bs
                  WHERE bs.scanned_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                  GROUP BY DATE(bs.scanned_at)
                  ORDER BY scan_date DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $days);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

/**
 * Modelo de Control de Pagos - TRUPPER
 */
class PaymentTracker {
    private $conn;

    public function __construct() {
        $this->conn = $GLOBALS['db'];
    }

    /**
     * Registrar pago
     */
    public function recordPayment($order_id, $amount, $payment_method, $payment_date = null) {
        $payment_date = $payment_date ?? date('Y-m-d H:i:s');
        
        $query = "INSERT INTO payment_tracking (order_id, amount_paid, payment_method, payment_date, created_at) 
                  VALUES (?, ?, ?, ?, NOW())";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("idss", $order_id, $amount, $payment_method, $payment_date);
        
        if ($stmt->execute()) {
            $this->updateOrderPaymentStatus($order_id);
            return ['success' => true, 'payment_id' => $stmt->insert_id];
        }
        
        return ['success' => false];
    }

    /**
     * Obtener estado de pago de una orden
     */
    public function getPaymentStatus($order_id) {
        $query = "SELECT o.id, o.total, COALESCE(SUM(pt.amount_paid), 0) as paid_amount,
                  o.total - COALESCE(SUM(pt.amount_paid), 0) as pending_amount
                  FROM orders o
                  LEFT JOIN payment_tracking pt ON o.id = pt.order_id
                  WHERE o.id = ?
                  GROUP BY o.id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result) {
            $result['payment_percentage'] = ($result['paid_amount'] / $result['total']) * 100;
            $result['is_paid'] = $result['pending_amount'] <= 0;
        }
        
        return $result;
    }

    /**
     * Obtener historial de pagos
     */
    public function getPaymentHistory($order_id) {
        $query = "SELECT * FROM payment_tracking WHERE order_id = ? ORDER BY payment_date DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Actualizar estado de pago de orden
     */
    private function updateOrderPaymentStatus($order_id) {
        $payment_status = $this->getPaymentStatus($order_id);
        
        if ($payment_status['is_paid']) {
            $status = 'paid';
        } elseif ($payment_status['paid_amount'] > 0) {
            $status = 'partial';
        } else {
            $status = 'pending';
        }
        
        $query = "UPDATE orders SET payment_status = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("si", $status, $order_id);
        
        return $stmt->execute();
    }

    /**
     * Obtener órdenes pendientes de pago
     */
    public function getPendingPayments() {
        $query = "SELECT o.*, u.name, u.email,
                  COALESCE(SUM(pt.amount_paid), 0) as paid_amount,
                  o.total - COALESCE(SUM(pt.amount_paid), 0) as pending_amount
                  FROM orders o
                  JOIN users u ON o.user_id = u.id
                  LEFT JOIN payment_tracking pt ON o.id = pt.order_id
                  WHERE o.payment_status IN ('pending', 'partial')
                  GROUP BY o.id
                  ORDER BY o.created_at ASC";
        
        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Resumen de pagos por fecha
     */
    public function getPaymentSummary($start_date, $end_date) {
        $query = "SELECT 
                    DATE(payment_date) as payment_date,
                    payment_method,
                    COUNT(*) as transaction_count,
                    SUM(amount_paid) as total_received
                  FROM payment_tracking
                  WHERE DATE(payment_date) BETWEEN ? AND ?
                  GROUP BY DATE(payment_date), payment_method
                  ORDER BY payment_date DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>
