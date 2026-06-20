<?php
/**
 * Modelo de Pedidos - Truper
 * 
 * Maneja la creación, gestión y consulta de pedidos en el sistema.
 * Incluye validación de stock, transacciones de base de datos y notificaciones.
 *
 * @package Truper\Models
 * @version 1.0.0
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../src/Services/NotificationService.php';

class Order {
    /** @var PDO Conexión a la base de datos */
    private $conn;
    
    /** @var string Nombre de la tabla de pedidos */
    private $table = 'orders';
    
    /** @var NotificationService Servicio de notificaciones */
    private $notificationService;

    /**
     * Constructor - Inicializa la conexión y servicio de notificaciones
     */
    public function __construct() {
        $this->conn = $GLOBALS['db'];
        $this->notificationService = new NotificationService($this->conn);
    }

    /**
     * Crear pedido con transacción DB y validación de stock
     * 
     * @param int $user_id ID del usuario cliente
     * @param float $total Monto total del pedido
     * @param string $status Estado inicial del pedido (default: 'pending')
     * @param array $items Array de items del pedido con product_id, quantity, unit_price
     * @return array Resultado con success, order_id, order_number o error message
     */
    public function create($user_id, $total, $status = 'pending', $items = []) {
        try {
            // Validar límites de orden
            if (count($items) > 100) {
                return ['success' => false, 'message' => 'El pedido no puede tener más de 100 items'];
            }

            // Iniciar transacción
            $this->conn->beginTransaction();

            // Validar stock disponible para cada item
            // Optimización: Validar cantidades primero
            foreach ($items as $item) {
                $quantity = $item['quantity'];

                // Validar cantidad positiva
                if ($quantity <= 0) {
                    $this->conn->rollBack();
                    return ['success' => false, 'message' => 'Las cantidades deben ser mayores a 0'];
                }

                // Validar cantidad máxima por item
                if ($quantity > 1000) {
                    $this->conn->rollBack();
                    return ['success' => false, 'message' => 'La cantidad por item no puede exceder 1000 unidades'];
                }
            }

            // Optimización: Cargar todos los productos en una sola consulta (evitar N+1)
            $product_ids = array_map(fn($item) => $item['product_id'], $items);
            $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
            $stmt = $this->conn->prepare("SELECT id, stock_quantity, name FROM products WHERE id IN ({$placeholders}) FOR UPDATE");
            $stmt->execute($product_ids);
            $products_map = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $products_map[$row['id']] = $row;
            }

            // Verificar stock disponible para cada item
            foreach ($items as $item) {
                $product_id = $item['product_id'];
                $quantity = $item['quantity'];

                if (!isset($products_map[$product_id])) {
                    $this->conn->rollBack();
                    return ['success' => false, 'message' => "Producto ID {$product_id} no encontrado"];
                }

                $product = $products_map[$product_id];
                if ($product['stock_quantity'] < $quantity) {
                    $this->conn->rollBack();
                    return ['success' => false, 'message' => "Stock insuficiente para {$product['name']}. Disponible: {$product['stock_quantity']}, Solicitado: {$quantity}"];
                }
            }

            // Crear orden
            $orderNumber = 'ORD-' . date('YmdHis') . '-' . random_int(1000, 9999);
            $stmt = $this->conn->prepare("INSERT INTO {$this->table} (client_id, order_number, total_amount, payment_status, balance, status, order_date, created_at, status_updated_at) VALUES (:client_id, :order_number, :total_amount, :payment_status, :balance, :status, NOW(), NOW(), NOW()) RETURNING id");
            $stmt->execute([
                ':client_id' => $user_id,
                ':order_number' => $orderNumber,
                ':total_amount' => $total,
                ':payment_status' => $status === 'paid' ? 'paid' : 'pending',
                ':balance' => $status === 'paid' ? 0 : $total,
                ':status' => $status,
            ]);

            $orderId = $stmt->fetchColumn();
            if (!$orderId) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Error al crear la orden'];
            }

            // Agregar items y actualizar stock
            foreach ($items as $item) {
                $product_id = $item['product_id'];
                $quantity = $item['quantity'];
                $unit_price = $item['unit_price'];

                // Agregar item a la orden
                $subtotal = $quantity * $unit_price;
                $stmt = $this->conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal, line_total) VALUES (:order_id, :product_id, :quantity, :unit_price, :subtotal, :line_total)");
                $stmt->execute([
                    ':order_id' => $orderId,
                    ':product_id' => $product_id,
                    ':quantity' => $quantity,
                    ':unit_price' => $unit_price,
                    ':subtotal' => $subtotal,
                    ':line_total' => $subtotal,
                ]);

                // Actualizar stock
                $stmt = $this->conn->prepare("UPDATE products SET stock_quantity = stock_quantity - :quantity WHERE id = :product_id");
                $stmt->execute([
                    ':quantity' => $quantity,
                    ':product_id' => $product_id,
                ]);
            }

            // Commit transacción
            $this->conn->commit();

            // Enviar notificación de nuevo pedido
            $userStmt = $this->conn->prepare("SELECT first_name, last_name FROM users WHERE id = :user_id");
            $userStmt->execute([':user_id' => $user_id]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            $clientName = trim($user['first_name'] . ' ' . $user['last_name']);

            $this->notificationService->notifyNewOrder($orderId, $clientName, $total);

            return ['success' => true, 'order_id' => (int)$orderId, 'order_number' => $orderNumber];

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Error creating order: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al crear el pedido: ' . $e->getMessage()];
        }
    }

    /**
     * Agregar item al pedido
     * 
     * @param int $order_id ID del pedido
     * @param int $product_id ID del producto
     * @param int $quantity Cantidad del producto
     * @param float $unit_price Precio unitario
     * @return bool True si se agregó exitosamente
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
     * 
     * @param int $user_id ID del usuario
     * @return array Lista de pedidos del usuario
     */
    public function getUserOrders($user_id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE client_id = :user_id ORDER BY created_at DESC");
        $stmt->execute([':user_id' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener pedido completo
     * 
     * @param int $order_id ID del pedido
     * @return array|false Datos del pedido con información del cliente
     */
    public function getOrderDetail($order_id) {
        $stmt = $this->conn->prepare("SELECT o.*, u.first_name || CASE WHEN u.last_name IS NOT NULL AND u.last_name <> '' THEN ' ' || u.last_name ELSE '' END AS name, u.email FROM {$this->table} o JOIN users u ON o.client_id = u.id WHERE o.id = :id");
        $stmt->execute([':id' => $order_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener items del pedido
     * 
     * @param int $order_id ID del pedido
     * @return array Lista de items del pedido con información de productos
     */
    public function getOrderItems($order_id) {
        $stmt = $this->conn->prepare("SELECT oi.*, p.name, p.sku FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = :order_id");
        $stmt->execute([':order_id' => $order_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Actualizar estado del pedido con tracking
     * 
     * @param int $order_id ID del pedido
     * @param string $status Nuevo estado del pedido
     * @return bool True si se actualizó exitosamente
     */
    public function updateStatus($order_id, $status) {
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET status = :status, status_updated_at = NOW() WHERE id = :id");
        return $stmt->execute([':status' => $status, ':id' => $order_id]);
    }

    /**
     * Registrar pago
     * 
     * @param int $order_id ID del pedido
     * @param float $amount Monto del pago
     * @param string $payment_method Método de pago
     * @param string|null $reference Número de referencia (opcional)
     * @return array Resultado con success y payment_id
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
     * 
     * @param string $start_date Fecha de inicio (YYYY-MM-DD)
     * @param string $end_date Fecha de fin (YYYY-MM-DD)
     * @return array Lista de pedidos en el rango de fechas
     */
    public function getByDateRange($start_date, $end_date) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE DATE(created_at) BETWEEN :start_date AND :end_date ORDER BY created_at DESC");
        $stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>


