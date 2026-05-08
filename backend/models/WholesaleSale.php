<?php
/**
 * Modelo de Ventas Mayoreo - Truper
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
        $stmt = $this->conn->prepare("INSERT INTO wholesale_requests (user_id, company_name, contact_email, contact_phone, business_type, description, status, created_at) VALUES (:user_id, :company_name, :contact_email, :contact_phone, :business_type, :description, 'pending', NOW()) RETURNING id");
        $stmt->execute([
            ':user_id' => $user_id,
            ':company_name' => $company_name,
            ':contact_email' => $contact_email,
            ':contact_phone' => $contact_phone,
            ':business_type' => $business_type,
            ':description' => $description,
        ]);

        $requestId = $stmt->fetchColumn();
        if ($requestId) {
            return ['success' => true, 'request_id' => (int)$requestId];
        }
        return ['success' => false];
    }

    /**
     * Obtener todas las solicitudes de mayoreo
     */
    public function getRequests($status = null) {
        $sql = "SELECT wr.*, u.first_name || CASE WHEN u.last_name IS NOT NULL AND u.last_name <> '' THEN ' ' || u.last_name ELSE '' END AS name, u.phone FROM wholesale_requests wr JOIN users u ON wr.user_id = u.id";
        $params = [];
        if ($status) {
            $sql .= " WHERE wr.status = :status";
            $params[':status'] = $status;
        }
        $sql .= " ORDER BY wr.created_at DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Actualizar estado de solicitud
     */
    public function updateRequestStatus($request_id, $status) {
        $stmt = $this->conn->prepare("UPDATE wholesale_requests SET status = :status WHERE id = :id");
        return $stmt->execute([':status' => $status, ':id' => $request_id]);
    }

    /**
     * Crear cotización wholesale
     */
    public function createQuote($request_id, $user_id, $items, $discount_percent = 0) {
        // items es array: [['product_id' => 1, 'quantity' => 100], ...]
        
        $total = 0;
        foreach ($items as $item) {
            // Obtener precio de producto
            $stmt = $this->conn->prepare("SELECT COALESCE(sell_price, unit_price) AS sell_price FROM products WHERE id = :id");
            $stmt->execute([':id' => $item['product_id']]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product) {
                // Aplicar descuento mayoreo (generalmente 20-30% menos)
                $wholesale_price = $product['sell_price'] * 0.7; // 30% descuento
                $subtotal = $item['quantity'] * $wholesale_price;
                $total += $subtotal;
            }
        }
        
        // Aplicar descuento adicional si existe
        $final_total = $total * (1 - ($discount_percent / 100));
        
        $stmt = $this->conn->prepare("INSERT INTO wholesale_quotes (request_id, user_id, total_amount, discount_percent, final_amount, status, created_at) VALUES (:request_id, :user_id, :total_amount, :discount_percent, :final_amount, 'pending', NOW()) RETURNING id");
        $stmt->execute([
            ':request_id' => $request_id,
            ':user_id' => $user_id,
            ':total_amount' => $total,
            ':discount_percent' => $discount_percent,
            ':final_amount' => $final_total,
        ]);

        $quote_id = $stmt->fetchColumn();
        if ($quote_id) {
            
            // Insertar items de la cotización
            foreach ($items as $item) {
                $this->addQuoteItem($quote_id, $item['product_id'], $item['quantity']);
            }
            
            return ['success' => true, 'quote_id' => (int)$quote_id];
        }
        
        return ['success' => false];
    }

    /**
     * Agregar item a cotización
     */
    private function addQuoteItem($quote_id, $product_id, $quantity) {
        $stmt = $this->conn->prepare("SELECT COALESCE(sell_price, unit_price) AS sell_price FROM products WHERE id = :id");
        $stmt->execute([':id' => $product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $wholesale_price = $product['sell_price'] * 0.7;
        $subtotal = $quantity * $wholesale_price;
        
        $stmt = $this->conn->prepare("INSERT INTO wholesale_quote_items (quote_id, product_id, quantity, unit_price, subtotal) VALUES (:quote_id, :product_id, :quantity, :unit_price, :subtotal)");
        return $stmt->execute([
            ':quote_id' => $quote_id,
            ':product_id' => $product_id,
            ':quantity' => $quantity,
            ':unit_price' => $wholesale_price,
            ':subtotal' => $subtotal,
        ]);
    }

    /**
     * Obtener cotizaciones
     */
    public function getQuotes($status = null) {
        $sql = "SELECT wq.*, wr.company_name FROM wholesale_quotes wq JOIN wholesale_requests wr ON wq.request_id = wr.id";
        $params = [];
        if ($status) {
            $sql .= " WHERE wq.status = :status";
            $params[':status'] = $status;
        }
        $sql .= " ORDER BY wq.created_at DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Convertir cotización a pedido
     */
    public function convertQuoteToOrder($quote_id) {
        $stmt = $this->conn->prepare("SELECT * FROM wholesale_quotes WHERE id = :id");
        $stmt->execute([':id' => $quote_id]);
        $quote = $stmt->fetch(PDO::FETCH_ASSOC);
        
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
        $stmt = $this->conn->prepare("SELECT * FROM wholesale_quote_items WHERE quote_id = :quote_id");
        $stmt->execute([':quote_id' => $quote_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
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
        $stmt = $this->conn->prepare("UPDATE wholesale_quotes SET status = :status WHERE id = :id");
        return $stmt->execute([':status' => $status, ':id' => $quote_id]);
    }
}
?>


