<?php
/**
 * Modelo de Ventas Mayoreo - TRUPPER
 */

require_once __DIR__ . '/../config/database.php';

class WholesaleSale {
    private $conn;
    private $table = 'wholesale_sales';

    public function __construct() {
        $this->conn = $GLOBALS['db'];
    }

    /**
     * Crear solicitud de venta mayoreo
     */
    public function createRequest($user_id, $company_name, $contact_email, $contact_phone, $business_type, $description) {
        $query = "INSERT INTO wholesale_requests (user_id, company_name, contact_email, contact_phone, business_type, description, status, created_at) 
                  VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("isssss", $user_id, $company_name, $contact_email, $contact_phone, $business_type, $description);
        
        if ($stmt->execute()) {
            return ['success' => true, 'request_id' => $stmt->insert_id];
        }
        return ['success' => false];
    }

    /**
     * Obtener todas las solicitudes de mayoreo
     */
    public function getRequests($status = null) {
        $query = "SELECT wr.*, u.name, u.phone FROM wholesale_requests wr 
                  JOIN users u ON wr.user_id = u.id";
        
        if ($status) {
            $query .= " WHERE wr.status = ?";
        }
        
        $query .= " ORDER BY wr.created_at DESC";
        
        if ($status) {
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("s", $status);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } else {
            $result = $this->conn->query($query);
            return $result->fetch_all(MYSQLI_ASSOC);
        }
    }

    /**
     * Actualizar estado de solicitud
     */
    public function updateRequestStatus($request_id, $status) {
        $query = "UPDATE wholesale_requests SET status = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("si", $status, $request_id);
        
        return $stmt->execute();
    }

    /**
     * Crear cotización wholesale
     */
    public function createQuote($request_id, $user_id, $items, $discount_percent = 0) {
        // items es array: [['product_id' => 1, 'quantity' => 100], ...]
        
        $total = 0;
        foreach ($items as $item) {
            // Obtener precio de producto
            $query = "SELECT sell_price FROM products WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $item['product_id']);
            $stmt->execute();
            $product = $stmt->get_result()->fetch_assoc();
            
            if ($product) {
                // Aplicar descuento mayoreo (generalmente 20-30% menos)
                $wholesale_price = $product['sell_price'] * 0.7; // 30% descuento
                $subtotal = $item['quantity'] * $wholesale_price;
                $total += $subtotal;
            }
        }
        
        // Aplicar descuento adicional si existe
        $final_total = $total * (1 - ($discount_percent / 100));
        
        $query = "INSERT INTO wholesale_quotes (request_id, user_id, total_amount, discount_percent, final_amount, status, created_at) 
                  VALUES (?, ?, ?, ?, ?, 'pending', NOW())";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("idddi", $request_id, $user_id, $total, $discount_percent, $final_total);
        
        if ($stmt->execute()) {
            $quote_id = $stmt->insert_id;
            
            // Insertar items de la cotización
            foreach ($items as $item) {
                $this->addQuoteItem($quote_id, $item['product_id'], $item['quantity']);
            }
            
            return ['success' => true, 'quote_id' => $quote_id];
        }
        
        return ['success' => false];
    }

    /**
     * Agregar item a cotización
     */
    private function addQuoteItem($quote_id, $product_id, $quantity) {
        $query = "SELECT sell_price FROM products WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        
        $wholesale_price = $product['sell_price'] * 0.7;
        $subtotal = $quantity * $wholesale_price;
        
        $query = "INSERT INTO wholesale_quote_items (quote_id, product_id, quantity, unit_price, subtotal) 
                  VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iiidd", $quote_id, $product_id, $quantity, $wholesale_price, $subtotal);
        
        return $stmt->execute();
    }

    /**
     * Obtener cotizaciones
     */
    public function getQuotes($status = null) {
        $query = "SELECT wq.*, wr.company_name FROM wholesale_quotes wq 
                  JOIN wholesale_requests wr ON wq.request_id = wr.id";
        
        if ($status) {
            $query .= " WHERE wq.status = ?";
        }
        
        $query .= " ORDER BY wq.created_at DESC";
        
        if ($status) {
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("s", $status);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } else {
            $result = $this->conn->query($query);
            return $result->fetch_all(MYSQLI_ASSOC);
        }
    }

    /**
     * Convertir cotización a pedido
     */
    public function convertQuoteToOrder($quote_id) {
        $query = "SELECT * FROM wholesale_quotes WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $quote_id);
        $stmt->execute();
        $quote = $stmt->get_result()->fetch_assoc();
        
        if (!$quote) {
            return ['success' => false];
        }
        
        // Crear orden
        require_once __DIR__ . '/Order.php';
        $order = new Order();
        $result = $order->create($quote['user_id'], $quote['final_amount'], 'pending');
        
        if (!$result['success']) {
            return ['success' => false];
        }
        
        $order_id = $result['order_id'];
        
        // Copiar items de la cotización
        $query = "SELECT * FROM wholesale_quote_items WHERE quote_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $quote_id);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        foreach ($items as $item) {
            $order->addItem($order_id, $item['product_id'], $item['quantity'], $item['unit_price']);
        }
        
        // Actualizar estado de cotización
        $this->updateQuoteStatus($quote_id, 'converted');
        
        return ['success' => true, 'order_id' => $order_id];
    }

    /**
     * Actualizar estado de cotización
     */
    public function updateQuoteStatus($quote_id, $status) {
        $query = "UPDATE wholesale_quotes SET status = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("si", $status, $quote_id);
        
        return $stmt->execute();
    }
}
?>
