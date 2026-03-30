<?php
/**
 * Modelo de Pedidos - TRUPPER
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
        $query = "INSERT INTO {$this->table} (user_id, total, status, created_at) 
                  VALUES (?, ?, ?, NOW())";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ids", $user_id, $total, $status);
        
        if ($stmt->execute()) {
            return ['success' => true, 'order_id' => $stmt->insert_id];
        }
        return ['success' => false];
    }

    /**
     * Agregar item al pedido
     */
    public function addItem($order_id, $product_id, $quantity, $unit_price) {
        $query = "INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal) 
                  VALUES (?, ?, ?, ?, ?)";
        $subtotal = $quantity * $unit_price;
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iiidd", $order_id, $product_id, $quantity, $unit_price, $subtotal);
        
        return $stmt->execute();
    }

    /**
     * Obtener pedidos del usuario
     */
    public function getUserOrders($user_id) {
        $query = "SELECT * FROM {$this->table} WHERE user_id = ? ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Obtener pedido completo
     */
    public function getOrderDetail($order_id) {
        $query = "SELECT o.*, u.name, u.email FROM {$this->table} o 
                  JOIN users u ON o.user_id = u.id 
                  WHERE o.id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Obtener items del pedido
     */
    public function getOrderItems($order_id) {
        $query = "SELECT oi.*, p.name, p.sku FROM order_items oi 
                  JOIN products p ON oi.product_id = p.id 
                  WHERE oi.order_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Actualizar estado del pedido
     */
    public function updateStatus($order_id, $status) {
        $query = "UPDATE {$this->table} SET status = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("si", $status, $order_id);
        
        return $stmt->execute();
    }

    /**
     * Registrar pago
     */
    public function recordPayment($order_id, $amount, $payment_method, $reference = null) {
        $query = "INSERT INTO payments (order_id, amount, payment_method, reference, created_at) 
                  VALUES (?, ?, ?, ?, NOW())";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("idss", $order_id, $amount, $payment_method, $reference);
        
        if ($stmt->execute()) {
            // Actualizar estado del pedido si está completamente pagado
            $this->checkPaymentComplete($order_id);
            return ['success' => true, 'payment_id' => $stmt->insert_id];
        }
        return ['success' => false];
    }

    /**
     * Verificar si el pedido está completamente pagado
     */
    private function checkPaymentComplete($order_id) {
        $order = $this->getOrderDetail($order_id);
        
        $query = "SELECT SUM(amount) as paid FROM payments WHERE order_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['paid'] >= $order['total']) {
            $this->updateStatus($order_id, 'paid');
        }
    }

    /**
     * Obtener órdenes por rango de fechas
     */
    public function getByDateRange($start_date, $end_date) {
        $query = "SELECT * FROM {$this->table} WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>
