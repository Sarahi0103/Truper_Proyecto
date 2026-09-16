<?php
/**
 * Backend API: Administración de Facturación & Pagos SAT
 * Ferretería FOX / Truper Platform
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/utils/AppLogger.php';
require_once __DIR__ . '/../../src/utils/SatCatalogs.php';
require_once __DIR__ . '/../../src/Services/GlobalInvoiceService.php';

// Verificar sesión e perfil administrativo
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$userRole = $_SESSION['role'] ?? '';
$isLogged = isset($_SESSION['user_id']);
$isAdmin = ($userRole === 'admin' || $userRole === 'employee');

if (!$isLogged || !$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Se requieren permisos de administración.']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'get_config';

try {
    global $pdo;
    if (!($pdo instanceof PDO)) {
        throw new Exception("Conexión a base de datos no disponible.");
    }

    switch ($action) {
        case 'get_config':
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
            $settingsRaw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            $config = [
                'mercadopago_public_key' => $settingsRaw['mercadopago_public_key'] ?? '',
                'mercadopago_access_token' => $settingsRaw['mercadopago_access_token'] ?? '',
                'stripe_public_key' => $settingsRaw['stripe_public_key'] ?? '',
                'stripe_secret_key' => $settingsRaw['stripe_secret_key'] ?? '',
                'stripe_webhook_secret' => $settingsRaw['stripe_webhook_secret'] ?? '',
                'payment_environment' => $settingsRaw['payment_environment'] ?? 'production',
                'bank_name' => $settingsRaw['bank_name'] ?? '',
                'bank_clabe' => $settingsRaw['bank_clabe'] ?? '',
                'bank_account_holder' => $settingsRaw['bank_account_holder'] ?? '',
                
                'facturapi_api_key' => $settingsRaw['facturapi_api_key'] ?? '',
                'company_rfc' => $settingsRaw['company_rfc'] ?? '',
                'company_tax_name' => $settingsRaw['company_tax_name'] ?? '',
                'company_tax_regime' => $settingsRaw['company_tax_regime'] ?? '601',
                'company_zip_code' => $settingsRaw['company_zip_code'] ?? '',
                'csd_status' => $settingsRaw['csd_status'] ?? 'Activo y Vigente (SAT México)'
            ];

            echo json_encode(['success' => true, 'config' => $config]);
            break;

        case 'save_config':
            require_csrf_token();
            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $fieldsAllowed = [
                'mercadopago_public_key', 'mercadopago_access_token',
                'stripe_public_key', 'stripe_secret_key', 'stripe_webhook_secret',
                'payment_environment', 'bank_name', 'bank_clabe', 'bank_account_holder',
                'facturapi_api_key', 'company_rfc', 'company_tax_name', 'company_tax_regime',
                'company_zip_code', 'csd_status'
            ];

            // Validaciones específicas
            if (!empty($data['company_rfc'])) {
                $validRfc = SecurityValidator::validateRFC($data['company_rfc']);
                if (!$validRfc) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'El RFC de la empresa no tiene un formato válido para el SAT']);
                    exit;
                }
                $data['company_rfc'] = $validRfc;
            }

            if (!empty($data['company_zip_code'])) {
                $validZip = SecurityValidator::validatePostalCode($data['company_zip_code']);
                if (!$validZip) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'El código postal fiscal debe contener 5 dígitos numéricos']);
                    exit;
                }
                $data['company_zip_code'] = $validZip;
            }

            if (!empty($data['bank_clabe'])) {
                $validClabe = SecurityValidator::validateClabe($data['bank_clabe']);
                if (!$validClabe['valid']) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => $validClabe['message']]);
                    exit;
                }
                $data['bank_clabe'] = $validClabe['clabe'];
            }

            $stmtSave = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value, updated_at)
                VALUES (?, ?, NOW())
                ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value, updated_at = NOW()
            ");

            $savedCount = 0;
            foreach ($fieldsAllowed as $field) {
                if (isset($data[$field])) {
                    $val = SecurityValidator::sanitizeText($data[$field]);
                    $stmtSave->execute([$field, $val]);
                    $savedCount++;
                }
            }

            AppLogger::info("Configuración de pagos y SAT actualizada por usuario ID {$_SESSION['user_id']}");
            echo json_encode(['success' => true, 'message' => "Se guardaron {$savedCount} parámetros de configuración correctamente."]);
            break;

        case 'list_invoices':
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = max(10, min(100, (int)($_GET['per_page'] ?? 20)));
            $offset = ($page - 1) * $perPage;
            $search = trim($_GET['search'] ?? '');

            $whereSql = "WHERE 1=1";
            $params = [];

            if ($search !== '') {
                $whereSql .= " AND (LOWER(o.order_number) LIKE ? OR LOWER(o.tax_rfc) LIKE ? OR LOWER(o.tax_name) LIKE ? OR LOWER(u.first_name) LIKE ?)";
                $sParam = "%" . strtolower($search) . "%";
                $params = [$sParam, $sParam, $sParam, $sParam];
            }

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders o LEFT JOIN users u ON o.client_id = u.id {$whereSql}");
            $countStmt->execute($params);
            $totalRows = (int)$countStmt->fetchColumn();

            $query = "
                SELECT 
                    o.id,
                    o.order_number,
                    o.total_amount,
                    o.status,
                    o.payment_status,
                    o.payment_gateway,
                    o.requires_invoice,
                    o.tax_rfc,
                    o.tax_name,
                    o.tax_regime,
                    o.tax_zip,
                    o.cfdi_use,
                    o.sat_uuid,
                    o.sat_xml_url,
                    o.sat_pdf_url,
                    o.sat_cancellation_reason,
                    o.sat_cancellation_status,
                    o.created_at,
                    COALESCE(NULLIF(TRIM(CONCAT(u.first_name, ' ', u.last_name)), ''), u.email, 'Cliente Invitado') AS client_name
                FROM orders o
                LEFT JOIN users u ON o.client_id = u.id
                {$whereSql}
                ORDER BY o.created_at DESC
                LIMIT {$perPage} OFFSET {$offset}
            ";

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Formatear datos de SAT
            foreach ($orders as &$ord) {
                $ord['sat_uuid_display'] = $ord['sat_uuid'] ?: 'Sin Timbrar (Venta General)';
                $ord['tax_regime_label'] = SatCatalogs::getTaxRegimeLabel($ord['tax_regime'] ?? '');
                $ord['cfdi_use_label'] = SatCatalogs::getCfdiUseLabel($ord['cfdi_use'] ?? '');
            }

            echo json_encode([
                'success' => true,
                'total' => $totalRows,
                'page' => $page,
                'per_page' => $perPage,
                'orders' => $orders
            ]);
            break;

        case 'cancel_sat_invoice':
            require_csrf_token();
            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $orderId = (int)($data['order_id'] ?? 0);
            $satReason = trim((string)($data['sat_reason'] ?? '03'));
            $notes = trim((string)($data['notes'] ?? 'Cancelado por administración'));

            if ($orderId <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID de pedido no válido.']);
                exit;
            }

            $validReasons = ['01', '02', '03', '04'];
            if (!in_array($satReason, $validReasons, true)) {
                $satReason = '03';
            }

            $reasonLabels = [
                '01' => '01 - Comprobante emitido con errores con relación',
                '02' => '02 - Comprobante emitido con errores sin relación',
                '03' => '03 - No se realizó la operación',
                '04' => '04 - Operación nominativa relacionada en una factura global'
            ];

            $pdo->beginTransaction();

            $stmtOrder = $pdo->prepare("SELECT * FROM orders WHERE id = ? FOR UPDATE");
            $stmtOrder->execute([$orderId]);
            $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'El pedido especificado no existe.']);
                exit;
            }

            if ($order['status'] === 'cancelled') {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Este pedido ya se encuentra cancelado.']);
                exit;
            }

            // Actualizar orden
            $updOrder = $pdo->prepare("
                UPDATE orders 
                SET status = 'cancelled',
                    payment_status = 'refunded',
                    sat_cancellation_reason = ?,
                    sat_cancellation_status = 'cancelled',
                    notes = CONCAT(COALESCE(notes, ''), '\n[CANCELACIÓN SAT ', NOW(), ']: Motivo ', ?, ' - ', ?),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updOrder->execute([$satReason, $satReason, $notes, $orderId]);

            // Revertir inventario de productos
            $stmtItems = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
            $stmtItems->execute([$orderId]);
            $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

            $revertedCount = 0;
            foreach ($items as $item) {
                if ($item['product_id'] && $item['quantity'] > 0) {
                    $updStock = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ?, updated_at = NOW() WHERE id = ?");
                    $updStock->execute([$item['quantity'], $item['product_id']]);
                    $revertedCount++;
                }
            }

            // Registrar transacción en el historial
            $histStmt = $pdo->prepare("
                INSERT INTO transaction_history (transaction_type, reference_folio, data_json, created_by, created_at)
                VALUES ('SAT_CANCEL', ?, ?, ?, NOW())
            ");
            $histStmt->execute([
                $order['order_number'],
                json_encode([
                    'order_id' => $orderId,
                    'sat_reason' => $satReason,
                    'sat_reason_label' => $reasonLabels[$satReason] ?? $satReason,
                    'notes' => $notes,
                    'reverted_items_count' => $revertedCount,
                    'uuid' => $order['sat_uuid'] ?? null
                ]),
                $_SESSION['user_id']
            ]);

            $pdo->commit();

            AppLogger::info("Orden {$order['order_number']} cancelada con motivo SAT {$satReason} por usuario {$_SESSION['user_id']}");

            // Notificación por correo automático al cliente
            try {
                require_once __DIR__ . '/../../src/Services/EmailBillingService.php';
                $emailService = new EmailBillingService($pdo);
                $custEmail = $order['customer_email'] ?? $order['email'] ?? '';
                $custName = $order['customer_name'] ?? 'Cliente';
                if (!empty($custEmail)) {
                    $emailService->sendCancellationEmail($custEmail, $custName, $order['sat_uuid'] ?? 'N/A', $order['order_number'], $satReason);
                }
            } catch (Exception $emailEx) {
                AppLogger::warning("No se pudo despachar el correo de cancelación SAT: " . $emailEx->getMessage());
            }

            echo json_encode([
                'success' => true,
                'message' => "El pedido {$order['order_number']} ha sido cancelado formalmente ante el SAT (Motivo: {$satReason}) y se han reincorporado los productos al inventario.",
                'sat_reason_label' => $reasonLabels[$satReason] ?? $satReason
            ]);
            break;

        case 'delete_invoice':
            require_csrf_token();
            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $orderId = (int)($data['order_id'] ?? 0);

            if ($orderId <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID de pedido no válido.']);
                exit;
            }

            $pdo->beginTransaction();
            // Buscar folio antes de borrar
            $stmtFind = $pdo->prepare("SELECT order_number FROM orders WHERE id = ?");
            $stmtFind->execute([$orderId]);
            $ordNum = $stmtFind->fetchColumn();

            if (!$ordNum) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'El registro no existe o ya fue eliminado.']);
                exit;
            }

            $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$orderId]);
            $pdo->prepare("DELETE FROM shipping_tracking WHERE order_id = ?")->execute([$orderId]);
            $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$orderId]);
            $pdo->commit();

            AppLogger::info("Orden {$ordNum} (ID {$orderId}) eliminada por usuario ID {$_SESSION['user_id']}");
            echo json_encode(['success' => true, 'message' => "Registro {$ordNum} eliminado correctamente del monitor."]);
            break;

        case 'list_global_invoices':
            try {
                $globalService = new GlobalInvoiceService($pdo);
                $invoices = $globalService->getGlobalInvoicesHistory(50);
                
                echo json_encode([
                    'success' => true,
                    'invoices' => $invoices
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;

        case 'generate_global_invoice':
            $type = $_GET['type'] ?? 'daily';
            $date = $_GET['date'] ?? null;
            
            try {
                $globalService = new GlobalInvoiceService($pdo);
                
                if ($type === 'monthly' && $date) {
                    // Parse YYYY-MM format
                    $parts = explode('-', $date);
                    if (count($parts) === 2) {
                        $result = $globalService->generateMonthlyGlobalInvoice((int)$parts[0], (int)$parts[1]);
                    } else {
                        $result = ['success' => false, 'message' => 'Formato de fecha inválido para mensual. Use YYYY-MM'];
                    }
                } else {
                    $result = $globalService->generateDailyGlobalInvoice($date);
                }
                
                echo json_encode($result);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
            break;
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    AppLogger::error("Error en API admin_online_billing: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
}
?>
