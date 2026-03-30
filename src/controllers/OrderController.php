<?php
/**
 * Controlador de Órdenes y Pedidos
 */

class OrderController {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function createOrder($client_id, $items, $is_wholesale = false) {
        try {
            $this->pdo->beginTransaction();
            
            // Generar número de orden
            $order_number = 'ORD-' . date('Y') . '-' . strtoupper(substr(uniqid(), -6));
            
            $total_amount = 0;
            
            // Calcular total
            foreach ($items as $item) {
                $product = $this->getProduct($item['product_id']);
                $unit_price = calculateProductPrice($product['unit_price'], $item['quantity'], $is_wholesale);
                $subtotal = $unit_price * $item['quantity'];
                
                // Aplicar descuento por cantidad
                $discount = 0;
                if ($item['quantity'] >= 100) {
                    $discount = 0.15; // 15% descuento
                } elseif ($item['quantity'] >= 50) {
                    $discount = 0.10; // 10%
                } elseif ($item['quantity'] >= 20) {
                    $discount = 0.05; // 5%
                }
                
                $item_discount = $subtotal * $discount;
                $line_total = $subtotal - $item_discount;
                $total_amount += $line_total;
            }
            
            // Aplicar descuento por puntos de lealtad
            $client = $this->getClient($client_id);
            $loyalty_discount = calculateDiscountByPoints($client['loyalty_points']);
            $total_amount = $total_amount * (1 - $loyalty_discount);
            
            // Crear orden
            $stmt = $this->pdo->prepare("
                INSERT INTO orders (client_id, order_number, total_amount, balance, is_wholesale, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            
            $stmt->execute([
                $client_id,
                $order_number,
                $total_amount,
                $total_amount,
                $is_wholesale ? 1 : 0
            ]);
            
            $order_id = $this->pdo->lastInsertId();
            
            // Agregar items a la orden
            foreach ($items as $item) {
                $product = $this->getProduct($item['product_id']);
                $unit_price = calculateProductPrice($product['unit_price'], $item['quantity'], $is_wholesale);
                
                $stmt = $this->pdo->prepare("
                    INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal, line_total)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $order_id,
                    $item['product_id'],
                    $item['quantity'],
                    $unit_price,
                    $unit_price * $item['quantity'],
                    $unit_price * $item['quantity']
                ]);
                
                // Actualizar estadísticas de compra
                $this->updatePurchaseStatistics($item['product_id'], $item['quantity']);
            }
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => 'Orden creada exitosamente',
                'order_id' => $order_id,
                'order_number' => $order_number,
                'total' => $total_amount
            ];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error creando orden: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al crear la orden'];
        }
    }
    
    public function recordPayment($order_id, $amount, $payment_method, $reference = null) {
        try {
            // Registrar pago
            $stmt = $this->pdo->prepare("
                INSERT INTO payments (order_id, amount, payment_method, reference_number, processed_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $order_id,
                $amount,
                $payment_method,
                $reference,
                $_SESSION['user_id'] ?? null
            ]);
            
            // Actualizar orden
            $stmt = $this->pdo->prepare("
                SELECT total_amount, balance FROM orders WHERE id = ?
            ");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch();
            
            $new_balance = $order['balance'] - $amount;
            $payment_status = $new_balance <= 0 ? 'paid' : 'partial';
            
            $stmt = $this->pdo->prepare("
                UPDATE orders 
                SET payment_status = ?, balance = ?, payment_amount = payment_amount + ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $payment_status,
                max(0, $new_balance),
                $amount,
                $order_id
            ]);
            
            return [
                'success' => true,
                'message' => 'Pago registrado exitosamente',
                'balance' => max(0, $new_balance)
            ];
        } catch (PDOException $e) {
            error_log("Error registrando pago: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al registrar el pago'];
        }
    }
    
    public function getClientOrders($client_id, $limit = 20, $offset = 0) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM orders 
                WHERE client_id = ? 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?
            ");
            
            $stmt->execute([$client_id, $limit, $offset]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    private function getProduct($product_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        return $stmt->fetch();
    }
    
    private function getClient($client_id) {
        $stmt = $this->pdo->prepare("
            SELECT u.loyalty_points FROM clients c
            JOIN users u ON c.user_id = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$client_id]);
        return $stmt->fetch();
    }
    
    private function updatePurchaseStatistics($product_id, $quantity) {
        $month = date('m');
        $year = date('Y');
        $season = $this->getSeason();
        
        $stmt = $this->pdo->prepare("
            SELECT id, total_quantity FROM purchase_statistics 
            WHERE product_id = ? AND month = ? AND year = ?
        ");
        $stmt->execute([$product_id, $month, $year]);
        $stat = $stmt->fetch();
        
        if ($stat) {
            $stmt = $this->pdo->prepare("
                UPDATE purchase_statistics 
                SET total_quantity = total_quantity + ? 
                WHERE id = ?
            ");
            $stmt->execute([$quantity, $stat['id']]);
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO purchase_statistics (product_id, month, year, total_quantity, season)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$product_id, $month, $year, $quantity, $season]);
        }
    }
    
    private function getSeason() {
        $month = date('m');
        if ($month >= 12 || $month <= 2) return 'Invierno';
        if ($month >= 3 && $month <= 5) return 'Primavera';
        if ($month >= 6 && $month <= 8) return 'Verano';
        return 'Otoño';
    }
}
?>
