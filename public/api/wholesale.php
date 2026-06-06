<?php
require_once '../../config/config.php';

require_login();
header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];
$rawInput = file_get_contents('php://input');
$decodedInput = json_decode($rawInput, true);
$input = is_array($decodedInput) ? $decodedInput : (is_array($_POST) ? $_POST : []);

$response = [];

function ensure_wholesale_client_id($pdo, int $userId): int {
    if ($userId <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare('SELECT id FROM clients WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $clientId = (int)$stmt->fetchColumn();
    if ($clientId > 0) {
        return $clientId;
    }

    $company = 'Cliente';
    try {
        $userStmt = $pdo->prepare("SELECT COALESCE(name, '') AS full_name, COALESCE(first_name, '') AS first_name, COALESCE(last_name, '') AS last_name FROM users WHERE id = ? LIMIT 1");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();
        $candidate = trim((string)($user['full_name'] ?? ''));
        if ($candidate === '') {
            $candidate = trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? ''));
        }
        if ($candidate !== '') {
            $company = $candidate;
        }
    } catch (Exception $ignored) {
    }

    try {
        $insert = $pdo->prepare("INSERT INTO clients (user_id, company_name) VALUES (?, ?) ON CONFLICT (user_id) DO NOTHING");
        $insert->execute([$userId, $company]);
    } catch (Exception $ignored) {
        try {
            $insert = $pdo->prepare('INSERT INTO clients (user_id, company_name) VALUES (?, ?)');
            $insert->execute([$userId, $company]);
        } catch (Exception $ignoredTwice) {
        }
    }

    $stmt = $pdo->prepare('SELECT id FROM clients WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS wholesalers (
        id SERIAL PRIMARY KEY,
        client_id INTEGER NOT NULL,
        business_type VARCHAR(100),
        min_order_quantity INTEGER DEFAULT 50,
        discount_percentage DECIMAL(5,2) DEFAULT 15,
        payment_terms VARCHAR(100),
        is_approved BOOLEAN DEFAULT false,
        requested_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        approved_date TIMESTAMP,
        approved_by INTEGER,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS wholesaler_products (
        id SERIAL PRIMARY KEY,
        wholesaler_id INTEGER NOT NULL REFERENCES wholesalers(id) ON DELETE CASCADE,
        product_type VARCHAR(20) NOT NULL DEFAULT 'catalog', -- 'catalog' o 'marketplace'
        product_id INTEGER NOT NULL,
        quantity INTEGER NOT NULL DEFAULT 50,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    try { $pdo->exec("ALTER TABLE wholesalers ADD COLUMN IF NOT EXISTS requested_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP"); } catch (Exception $ignored) {}
    try { $pdo->exec("ALTER TABLE wholesalers ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"); } catch (Exception $ignored) {}
    try { $pdo->exec("ALTER TABLE wholesalers ADD COLUMN IF NOT EXISTS approved_date TIMESTAMP"); } catch (Exception $ignored) {}
    try { $pdo->exec("ALTER TABLE wholesalers ADD COLUMN IF NOT EXISTS approved_by INTEGER"); } catch (Exception $ignored) {}

    switch ($action) {
        case 'products':
            $catalogProds = [];
            $mktProds = [];
            
            try {
                $stmt = $pdo->query("SELECT id, name, sku, COALESCE(unit_price, sell_price, 0) AS unit_price FROM products WHERE (is_active = true OR active = true) AND NOT EXISTS (SELECT 1 FROM product_categories pc WHERE LOWER(pc.name) = LOWER(products.category) AND pc.is_active = false) ORDER BY name LIMIT 1000");
                $catalogProds = $stmt->fetchAll();
            } catch (Exception $e) {
                try {
                    $stmt = $pdo->query("SELECT id, name, sku, COALESCE(unit_price, sell_price, 0) AS unit_price FROM products WHERE NOT EXISTS (SELECT 1 FROM product_categories pc WHERE LOWER(pc.name) = LOWER(products.category) AND pc.is_active = false) ORDER BY name LIMIT 1000");
                    $catalogProds = $stmt->fetchAll();
                } catch (Exception $e2) {}
            }
            
            try {
                $stmt = $pdo->query("SELECT id, name, sku, COALESCE(unit_price, 0) AS unit_price FROM marketplace_ce_products WHERE is_active = true AND NOT EXISTS (SELECT 1 FROM product_categories pc WHERE LOWER(pc.name) = LOWER(marketplace_ce_products.category) AND pc.is_active = false) ORDER BY name LIMIT 1000");
                $mktProds = $stmt->fetchAll();
            } catch (Exception $e) {
                try {
                    $stmt = $pdo->query("SELECT id, name, sku, COALESCE(unit_price, 0) AS unit_price FROM marketplace_ce_products WHERE NOT EXISTS (SELECT 1 FROM product_categories pc WHERE LOWER(pc.name) = LOWER(marketplace_ce_products.category) AND pc.is_active = false) ORDER BY name LIMIT 1000");
                    $mktProds = $stmt->fetchAll();
                } catch (Exception $e2) {}
            }
            
            $response = [
                'success' => true,
                'catalog_products' => $catalogProds ?: [],
                'marketplace_products' => $mktProds ?: []
            ];
            break;

        case 'request':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $clientId = ensure_wholesale_client_id($pdo, (int)$_SESSION['user_id']);
            if ($clientId <= 0) {
                $response = ['success' => false, 'message' => 'Perfil de cliente no encontrado'];
                break;
            }

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO wholesalers (client_id, business_type, min_order_quantity, discount_percentage, payment_terms, is_approved)
                                       VALUES (?, ?, ?, ?, ?, false) RETURNING id");
                $stmt->execute([
                    $clientId,
                    sanitize($input['business_type'] ?? ''),
                    (int)($input['min_order_quantity'] ?? 50),
                    (float)($input['discount_percentage'] ?? 15),
                    sanitize($input['payment_terms'] ?? 'Contado')
                ]);
                
                $wholesalerId = (int)$stmt->fetchColumn();
                
                $requestedProducts = $input['products'] ?? [];
                if (is_array($requestedProducts) && count($requestedProducts) > 0) {
                    $prodStmt = $pdo->prepare("INSERT INTO wholesaler_products (wholesaler_id, product_type, product_id, quantity) VALUES (?, ?, ?, ?)");
                    foreach ($requestedProducts as $p) {
                        $pId = (int)($p['product_id'] ?? 0);
                        $pQty = (int)($p['quantity'] ?? 50);
                        $pType = sanitize($p['product_type'] ?? 'catalog');
                        if ($pId > 0 && $pQty > 0) {
                            $prodStmt->execute([$wholesalerId, $pType, $pId, $pQty]);
                        }
                    }
                }
                
                $pdo->commit();
                $response = ['success' => true, 'message' => 'Solicitud de mayoreo enviada con éxito'];
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        case 'approve':
            require_admin();
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $id = (int)($input['id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE wholesalers SET is_approved = true, approved_date = NOW(), approved_by = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$_SESSION['user_id'], $id]);
            $response = ['success' => true, 'message' => 'Solicitud aprobada'];
            break;

        case 'delete':
            require_admin();
            if ($method !== 'POST' && $method !== 'DELETE') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) {
                $response = ['success' => false, 'message' => 'ID de solicitud invalido'];
                break;
            }

            // Verificar que exista y este aprobada antes de eliminar
            $checkStmt = $pdo->prepare("SELECT id, is_approved FROM wholesalers WHERE id = ?");
            $checkStmt->execute([$id]);
            $wholesale = $checkStmt->fetch();

            if (!$wholesale) {
                $response = ['success' => false, 'message' => 'Solicitud no encontrada'];
                break;
            }

            if (!$wholesale['is_approved']) {
                $response = ['success' => false, 'message' => 'Solo se pueden eliminar solicitudes aprobadas'];
                break;
            }

            $stmt = $pdo->prepare("DELETE FROM wholesalers WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() > 0) {
                try {
                    log_action(
                        $_SESSION['user_id'],
                        'DELETE_WHOLESALE',
                        'Solicitud de mayoreo #' . $id . ' eliminada',
                        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                    );
                } catch (Exception $ignored) {}

                $response = ['success' => true, 'message' => 'Solicitud eliminada con exito'];
            } else {
                $response = ['success' => false, 'message' => 'No se pudo eliminar la solicitud'];
            }
            break;

        case 'list':
            $isAdmin = (($_SESSION['role'] ?? '') === 'admin');
            if ($isAdmin) {
                $stmt = $pdo->prepare("SELECT w.*, u.first_name, u.last_name
                                       FROM wholesalers w
                                       JOIN clients c ON c.id = w.client_id
                                       JOIN users u ON u.id = c.user_id
                                       ORDER BY w.requested_date DESC");
                $stmt->execute();
                $items = $stmt->fetchAll();
            } else {
                $clientId = ensure_wholesale_client_id($pdo, (int)$_SESSION['user_id']);
                if ($clientId <= 0) {
                    $response = ['success' => true, 'items' => []];
                    break;
                }

                $stmt = $pdo->prepare('SELECT * FROM wholesalers WHERE client_id = ? ORDER BY requested_date DESC');
                $stmt->execute([$clientId]);
                $items = $stmt->fetchAll();
            }

            foreach ($items as &$item) {
                $item['products'] = [];
                try {
                    $prodStmt = $pdo->prepare("
                        SELECT wp.product_id, wp.product_type, wp.quantity, 
                               CASE WHEN wp.product_type = 'marketplace' THEN m.name ELSE p.name END as product_name,
                               CASE WHEN wp.product_type = 'marketplace' THEN m.sku ELSE p.sku END as product_sku,
                               CASE WHEN wp.product_type = 'marketplace' THEN COALESCE(m.unit_price, 0) ELSE COALESCE(p.unit_price, p.sell_price, 0) END as product_price
                        FROM wholesaler_products wp
                        LEFT JOIN products p ON wp.product_id = p.id AND wp.product_type = 'catalog'
                        LEFT JOIN marketplace_ce_products m ON wp.product_id = m.id AND wp.product_type = 'marketplace'
                        WHERE wp.wholesaler_id = ?
                    ");
                    $prodStmt->execute([$item['id']]);
                    $item['products'] = $prodStmt->fetchAll();
                } catch (Exception $e) {
                    $item['products'] = [];
                }
            }

            $response = ['success' => true, 'items' => $items];
            break;

        default:
            $response = ['success' => false, 'message' => 'Accion no valida'];
    }
} catch (Exception $e) {
    error_log('Wholesale API error: ' . $e->getMessage());
    $response = ['success' => false, 'message' => 'Error del servidor'];
    if (($_SESSION['role'] ?? '') === 'admin') {
        $response['debug'] = [
            'action' => (string)$action,
            'detail' => (string)$e->getMessage()
        ];
    }
}

echo json_encode($response);
