<?php
/**
 * Controlador de Órdenes y Pedidos
 */

class OrderController {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function createOrder($client_id, $items, $is_wholesale = false, $context = []) {
        try {
            if (empty($items) || !is_array($items)) {
                return ['success' => false, 'message' => 'No hay productos en el pedido'];
            }

            $this->ensureTransactionHistoryTable();
            $this->pdo->beginTransaction();
            
            // Generar número de orden
            $order_number = 'ORD-' . date('Y') . '-' . strtoupper(substr(uniqid(), -6));
            
            $total_amount = 0;
            $normalizedItems = [];
            
            // Calcular total
            foreach ($items as $item) {
                $productId = $this->extractProductId($item);
                $quantity = (int)($item['quantity'] ?? 0);

                if ($productId <= 0 || $quantity <= 0) {
                    throw new Exception('Item de pedido inválido');
                }

                $product = $this->getProduct($productId);
                if (!$product) {
                    throw new Exception('Producto no encontrado: ' . $productId);
                }

                $isAdmin = (($_SESSION['role'] ?? '') === 'admin');
                $customPrice = ($isAdmin && isset($item['price'])) ? (float)$item['price'] : null;

                if ($customPrice !== null) {
                    $unit_price = $customPrice;
                    $subtotal = $unit_price * $quantity;
                    $discount = 0;
                } else {
                    $unit_price = calculateProductPrice((float)$product['unit_price'], $quantity, (bool)$is_wholesale);
                    $subtotal = $unit_price * $quantity;
                    
                    // Aplicar descuento por cantidad
                    $discount = 0;
                    if ($quantity >= 100) {
                        $discount = 0.15; // 15% descuento
                    } elseif ($quantity >= 50) {
                        $discount = 0.10; // 10%
                    } elseif ($quantity >= 20) {
                        $discount = 0.05; // 5%
                    }
                }
                
                $item_discount = $subtotal * $discount;
                $line_total = $subtotal - $item_discount;
                $total_amount += $line_total;

                $normalizedItems[] = [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => $unit_price,
                    'subtotal' => $subtotal,
                    'discount_percentage' => $discount * 100,
                    'discount_amount' => $item_discount,
                    'line_total' => $line_total,
                ];
            }
            
            // Aplicar descuento por puntos de lealtad
            $client = $this->getClient($client_id);
            $loyalty_discount = calculateDiscountByPoints($client['loyalty_points']);
            $total_amount = $total_amount * (1 - $loyalty_discount);

            // Acumular puntos por compra (1 punto por cada $10 del total final)
            $earned_points = (int)floor(max(0, (float)$total_amount) / 10);
            
            // Crear orden
            $stmt = $this->pdo->prepare("
                INSERT INTO orders (client_id, order_number, total_amount, balance, is_wholesale, status, notes)
                VALUES (?, ?, ?, ?, ?::boolean, 'pending', ?)
            ");
            
            $stmt->execute([
                $client_id,
                $order_number,
                $total_amount,
                $total_amount,
                $is_wholesale ? 1 : 0,
                $context['notes'] ?? null
            ]);
            
            $order_id = $this->pdo->lastInsertId();
            
            // Agregar items a la orden
            $hasDiscountPercentage = $this->columnExists('order_items', 'discount_percentage');
            $hasDiscountAmount = $this->columnExists('order_items', 'discount_amount');

            foreach ($normalizedItems as $item) {
                $columns = ['order_id', 'product_id', 'quantity', 'unit_price', 'subtotal'];
                $values = ['?', '?', '?', '?', '?'];
                $params = [
                    $order_id,
                    $item['product_id'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['subtotal']
                ];

                if ($hasDiscountPercentage) {
                    $columns[] = 'discount_percentage';
                    $values[] = '?';
                    $params[] = $item['discount_percentage'];
                }

                if ($hasDiscountAmount) {
                    $columns[] = 'discount_amount';
                    $values[] = '?';
                    $params[] = $item['discount_amount'];
                }

                $columns[] = 'line_total';
                $values[] = '?';
                $params[] = $item['line_total'];

                $stmt = $this->pdo->prepare("INSERT INTO order_items (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")");
                $stmt->execute($params);
                
                // Actualizar estadísticas de compra
                $this->updatePurchaseStatistics(
                    $item['product_id'],
                    $item['quantity'],
                    $item['line_total'],
                    $context['weather_condition'] ?? null,
                    $context['special_event'] ?? null
                );
            }

            if ($earned_points > 0) {
                $this->addLoyaltyPointsByClient($client_id, $earned_points);
            }
            
            $this->pdo->commit();

            $historyStmt = $this->pdo->prepare("INSERT INTO transaction_history (transaction_type, reference_folio, data_json, created_by) VALUES ('client_order', ?, ?, ?)");
            $historyStmt->execute([
                $order_number,
                json_encode(['order_id' => $order_id, 'total' => $total_amount], JSON_UNESCAPED_UNICODE),
                $_SESSION['user_id'] ?? null
            ]);
            
            return [
                'success' => true,
                'message' => 'Orden creada exitosamente',
                'order_id' => $order_id,
                'order_number' => $order_number,
                'total' => $total_amount,
                'earned_points' => $earned_points,
                'ticket_url' => '/ticket_client.php?id=' . $order_id
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Error creando orden: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al crear la orden: ' . $e->getMessage()];
        }
    }
    
    public function recordPayment($order_id, $amount, $payment_method, $reference = null) {
        try {
            $this->ensureTransactionHistoryTable();
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

            $folioStmt = $this->pdo->prepare("SELECT order_number FROM orders WHERE id = ?");
            $folioStmt->execute([$order_id]);
            $ord = $folioStmt->fetch();
            $historyStmt = $this->pdo->prepare("INSERT INTO transaction_history (transaction_type, reference_folio, data_json, created_by) VALUES ('payment', ?, ?, ?)");
            $historyStmt->execute([
                $ord['order_number'] ?? ('ORD-' . $order_id),
                json_encode(['order_id' => $order_id, 'amount' => $amount, 'method' => $payment_method], JSON_UNESCAPED_UNICODE),
                $_SESSION['user_id'] ?? null
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
        $pointsColumn = '0';
        if ($this->columnExists('users', 'loyalty_points')) {
            $pointsColumn = 'COALESCE(u.loyalty_points, 0)';
        } elseif ($this->columnExists('users', 'points')) {
            $pointsColumn = 'COALESCE(u.points, 0)';
        }

        $stmt = $this->pdo->prepare(" 
            SELECT {$pointsColumn} AS loyalty_points FROM clients c
            JOIN users u ON c.user_id = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$client_id]);
        $client = $stmt->fetch();
        return $client ?: ['loyalty_points' => 0];
    }

    private function addLoyaltyPointsByClient($client_id, $points) {
        $points = (int)$points;
        if ($client_id <= 0 || $points <= 0) {
            return;
        }

        $column = null;
        if ($this->columnExists('users', 'loyalty_points')) {
            $column = 'loyalty_points';
        } elseif ($this->columnExists('users', 'points')) {
            $column = 'points';
        }

        if (!$column) {
            return;
        }

        $stmt = $this->pdo->prepare(" 
            UPDATE users
            SET {$column} = COALESCE({$column}, 0) + ?
            WHERE id = (SELECT user_id FROM clients WHERE id = ? LIMIT 1)
        ");
        $stmt->execute([$points, $client_id]);
    }

    private function extractProductId($item) {
        if (!is_array($item)) {
            return 0;
        }

        if (isset($item['product_id'])) {
            return (int)$item['product_id'];
        }

        if (isset($item['productId'])) {
            return (int)$item['productId'];
        }

        return 0;
    }

    private function columnExists($table, $column) {
        $stmt = $this->pdo->prepare("SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = ? AND column_name = ?)");
        $stmt->execute([$table, $column]);
        return (bool)$stmt->fetchColumn();
    }
    
    private function updatePurchaseStatistics($product_id, $quantity, $amount, $weather_condition = null, $special_event = null) {
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
                SET total_quantity = total_quantity + ?,
                    total_amount = total_amount + ?,
                    weather_condition = COALESCE(?, weather_condition),
                    special_event = COALESCE(?, special_event),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$quantity, $amount, $weather_condition, $special_event, $stat['id']]);
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO purchase_statistics
                (product_id, month, year, total_quantity, total_amount, season, weather_condition, special_event)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$product_id, $month, $year, $quantity, $amount, $season, $weather_condition, $special_event]);
        }
    }
    
    private function getSeason() {
        $month = date('m');
        if ($month >= 12 || $month <= 2) return 'Invierno';
        if ($month >= 3 && $month <= 5) return 'Primavera';
        if ($month >= 6 && $month <= 8) return 'Verano';
        return 'Otoño';
    }

    private function ensureTransactionHistoryTable() {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS transaction_history (
            id SERIAL PRIMARY KEY,
            transaction_type VARCHAR(40) NOT NULL,
            reference_folio VARCHAR(80) NOT NULL,
            data_json TEXT,
            created_by INTEGER REFERENCES users(id),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }
}
?>
