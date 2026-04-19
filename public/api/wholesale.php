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

    try { $pdo->exec("ALTER TABLE wholesalers ADD COLUMN IF NOT EXISTS requested_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP"); } catch (Exception $ignored) {}
    try { $pdo->exec("ALTER TABLE wholesalers ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"); } catch (Exception $ignored) {}
    try { $pdo->exec("ALTER TABLE wholesalers ADD COLUMN IF NOT EXISTS approved_date TIMESTAMP"); } catch (Exception $ignored) {}
    try { $pdo->exec("ALTER TABLE wholesalers ADD COLUMN IF NOT EXISTS approved_by INTEGER"); } catch (Exception $ignored) {}

    switch ($action) {
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

            $stmt = $pdo->prepare("INSERT INTO wholesalers (client_id, business_type, min_order_quantity, discount_percentage, payment_terms, is_approved)
                                   VALUES (?, ?, ?, ?, ?, false)");
            $stmt->execute([
                $clientId,
                sanitize($input['business_type'] ?? ''),
                (int)($input['min_order_quantity'] ?? 50),
                (float)($input['discount_percentage'] ?? 15),
                sanitize($input['payment_terms'] ?? 'Contado')
            ]);

            $response = ['success' => true, 'message' => 'Solicitud de mayoreo enviada'];
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

        case 'list':
            if (($_SESSION['role'] ?? '') === 'admin') {
                $stmt = $pdo->prepare("SELECT w.*, u.first_name, u.last_name
                                       FROM wholesalers w
                                       JOIN clients c ON c.id = w.client_id
                                       JOIN users u ON u.id = c.user_id
                                       ORDER BY w.requested_date DESC");
                $stmt->execute();
                $response = ['success' => true, 'items' => $stmt->fetchAll()];
                break;
            }

            $clientId = ensure_wholesale_client_id($pdo, (int)$_SESSION['user_id']);
            if ($clientId <= 0) {
                $response = ['success' => true, 'items' => []];
                break;
            }

            $stmt = $pdo->prepare('SELECT * FROM wholesalers WHERE client_id = ? ORDER BY requested_date DESC');
            $stmt->execute([$clientId]);
            $response = ['success' => true, 'items' => $stmt->fetchAll()];
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
