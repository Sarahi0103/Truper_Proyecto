<?php
/**
 * Modelo de Pedidos - Truper
 */

require_once __DIR__ . '/../config/database.php';

class Order {
    private $conn;
    private $table = 'orders';

    public function __construct() {
        $this->conn = $GLOBALS['db'];
    }

    /**
     * Crear pedido
     */
    public function create($user_id, $total, $status = 'pending') {
        $orderNumber = 'ORD-' . date('YmdHis') . '-' . random_int(1000, 9999);
        $stmt = $this->conn->prepare("INSERT INTO {$this->table} (client_id, order_number, total_amount, payment_status, balance, status, order_date, created_at) VALUES (:client_id, :order_number, :total_amount, :payment_status, :balance, :status, NOW(), NOW()) RETURNING id");
        $stmt->execute([
            ':client_id' => $user_id,
            ':order_number' => $orderNumber,
            ':total_amount' => $total,
            ':payment_status' => $status === 'paid' ? 'paid' : 'pending',
            ':balance' => $status === 'paid' ? 0 : $total,
            ':status' => $status,
        ]);

        $orderId = $stmt->fetchColumn();
        if ($orderId) {
            return ['success' => true, 'order_id' => (int)$orderId];
        }
        return ['success' => false];
    }

    /**
     * Agregar item al pedido
     */
    public function addItem($order_id, $product_id, $quantity, $unit_price) {
        $query = "INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal, line_total) VALUES (:order_id, :product_id, :quantity, :unit_price, :subtotal, :line_total)";
        $subtotal = $quantity * $unit_price;
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':order_id' => $order_id,
            ':product_id' => $product_id,
            ':quantity' => $quantity,
            ':unit_price' => $unit_price,
            ':subtotal' => $subtotal,
            ':line_total' => $subtotal,
        ]);
    }

    /**
     * Obtener pedidos del usuario
     */
    public function getUserOrders($user_id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE client_id = :user_id ORDER BY created_at DESC");
        $stmt->execute([':user_id' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener pedido completo
     */
    public function getOrderDetail($order_id) {
        $stmt = $this->conn->prepare("SELECT o.*, u.first_name || CASE WHEN u.last_name IS NOT NULL AND u.last_name <> '' THEN ' ' || u.last_name ELSE '' END AS name, u.email FROM {$this->table} o JOIN users u ON o.client_id = u.id WHERE o.id = :id");
        $stmt->execute([':id' => $order_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener items del pedido
     */
    public function getOrderItems($order_id) {
        $stmt = $this->conn->prepare("SELECT oi.*, p.name, p.sku FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = :order_id");
        $stmt->execute([':order_id' => $order_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Actualizar estado del pedido
     */
    public function updateStatus($order_id, $status) {
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET status = :status WHERE id = :id");
        return $stmt->execute([':status' => $status, ':id' => $order_id]);
    }

    /**
     * Registrar pago
     */
    public function recordPayment($order_id, $amount, $payment_method, $reference = null) {
        $stmt = $this->conn->prepare("INSERT INTO payments (order_id, amount, payment_method, reference_number, created_at) VALUES (:order_id, :amount, :payment_method, :reference_number, NOW()) RETURNING id");
        $stmt->execute([
            ':order_id' => $order_id,
            ':amount' => $amount,
            ':payment_method' => $payment_method,
            ':reference_number' => $reference,
        ]);

        $paymentId = $stmt->fetchColumn();
        if ($paymentId) {
            // Actualizar estado del pedido si está completamente pagado
            $this->checkPaymentComplete($order_id);
            return ['success' => true, 'payment_id' => (int)$paymentId];
        }
        return ['success' => false];
    }

    /**
     * Verificar si el pedido está completamente pagado
     */
    private function checkPaymentComplete($order_id) {
        $order = $this->getOrderDetail($order_id);
        
        $stmt = $this->conn->prepare("SELECT COALESCE(SUM(amount), 0) as paid FROM payments WHERE order_id = :order_id");
        $stmt->execute([':order_id' => $order_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['paid'] >= $order['total']) {
            $this->updateStatus($order_id, 'paid');
        }
    }

    /**
     * Obtener órdenes por rango de fechas
     */
    public function getByDateRange($start_date, $end_date) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE DATE(created_at) BETWEEN :start_date AND :end_date ORDER BY created_at DESC");
        $stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>


