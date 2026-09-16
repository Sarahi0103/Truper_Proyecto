<?php
/**
 * API de Administración de Configuración de Pagos
 * Gestión de bancos mexicanos y métodos de pago
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Services/MexicanBanksService.php';

header('Content-Type: application/json');

// Verificar autenticación y rol de admin
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'employee') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

$bankService = new MexicanBanksService($pdo);
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        // ===== BANCOS =====
        case 'get_banks':
            $banks = $bankService->getActiveBanks();
            echo json_encode(['success' => true, 'banks' => $banks]);
            break;
            
        case 'get_bank':
            $bankId = $_GET['bank_id'] ?? 0;
            $bank = $bankService->getBankById($bankId);
            echo json_encode(['success' => true, 'bank' => $bank]);
            break;
            
        case 'add_bank':
            require_csrf_token();
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Validar CLABE
            $clabeValidation = $bankService->validateClabe($input['clabe']);
            if (!$clabeValidation['valid']) {
                echo json_encode(['success' => false, 'message' => $clabeValidation['message']]);
                exit;
            }
            
            $result = $bankService->addBank($input);
            echo json_encode($result);
            break;
            
        case 'update_bank':
            require_csrf_token();
            $bankId = $_GET['bank_id'] ?? 0;
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!empty($input['clabe'])) {
                $clabeValidation = $bankService->validateClabe($input['clabe']);
                if (!$clabeValidation['valid']) {
                    echo json_encode(['success' => false, 'message' => $clabeValidation['message']]);
                    exit;
                }
            }
            
            $result = $bankService->updateBank($bankId, $input);
            echo json_encode($result);
            break;
            
        case 'delete_bank':
            require_csrf_token();
            $bankId = $_GET['bank_id'] ?? 0;
            $result = $bankService->deleteBank($bankId);
            echo json_encode($result);
            break;
            
        case 'get_banks_by_method':
            $method = $_GET['method'] ?? '';
            $banks = $bankService->getBanksByPaymentMethod($method);
            echo json_encode(['success' => true, 'banks' => $banks]);
            break;
            
        // ===== MÉTODOS DE PAGO =====
        case 'get_payment_methods':
            $methods = $bankService->getActivePaymentMethods();
            echo json_encode(['success' => true, 'methods' => $methods]);
            break;
            
        case 'get_payment_method':
            $methodCode = $_GET['method_code'] ?? '';
            $method = $bankService->getPaymentMethod($methodCode);
            echo json_encode(['success' => true, 'method' => $method]);
            break;
            
        case 'calculate_fee':
            $amount = floatval($_GET['amount'] ?? 0);
            $methodCode = $_GET['method_code'] ?? '';
            $fee = $bankService->calculatePaymentFee($amount, $methodCode);
            echo json_encode(['success' => true, 'fee' => $fee]);
            break;
            
        // ===== CONFIGURACIÓN FISCAL SAT =====
        case 'get_sat_config':
            $config = $bankService->getSatFiscalConfig();
            echo json_encode(['success' => true, 'config' => $config]);
            break;
            
        case 'update_sat_config':
            require_csrf_token();
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Validar RFC
            $rfcValid = SecurityValidator::validateRFC($input['company_rfc'] ?? '');
            if (!$rfcValid) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'RFC inválido para el SAT (12 o 13 caracteres)']);
                exit;
            }
            $input['company_rfc'] = $rfcValid;

            if (!empty($input['company_zip_code'])) {
                $zipValid = SecurityValidator::validatePostalCode($input['company_zip_code']);
                if (!$zipValid) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'El código postal fiscal debe contener 5 dígitos']);
                    exit;
                }
                $input['company_zip_code'] = $zipValid;
            }

            $input['company_tax_name'] = SecurityValidator::sanitizeText($input['company_tax_name'] ?? '', 150);
            $input['company_email'] = SecurityValidator::validateEmail($input['company_email'] ?? '') ?: null;
            $input['company_phone'] = SecurityValidator::sanitizeDigits($input['company_phone'] ?? '');
            $input['company_address'] = SecurityValidator::sanitizeText($input['company_address'] ?? '', 250);
            
            $result = $bankService->updateSatFiscalConfig($input);
            echo json_encode($result);
            break;
            
        // ===== CUENTAS DE PAGO DEL ADMINISTRADOR =====
        case 'get_payment_accounts':
            $stmt = $pdo->query("SELECT id, account_type, account_name, payment_gateway, provider_account_id, bank_name, clabe, last_4, account_holder, rfc, is_primary, is_active FROM admin_payment_accounts WHERE is_active = true ORDER BY is_primary DESC, id ASC");
            $accounts = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            echo json_encode(['success' => true, 'accounts' => $accounts]);
            break;
            
        case 'add_payment_account':
            require_csrf_token();
            $input = json_decode(file_get_contents('php://input'), true);
            
            $accountName = SecurityValidator::sanitizeText($input['account_name'] ?? '', 100);
            if (empty($accountName) || mb_strlen($accountName) < 3) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'El nombre de la cuenta es obligatorio (mínimo 3 caracteres)']);
                exit;
            }

            $allowedGateways = ['stripe', 'mercadopago', 'bank_account'];
            $gateway = SecurityValidator::sanitizeAlphaNum($input['payment_gateway'] ?? '');
            if (!in_array($gateway, $allowedGateways)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Pasarela de pago no válida']);
                exit;
            }

            $clabe = SecurityValidator::sanitizeDigits($input['clabe'] ?? '');
            if (!empty($clabe)) {
                $clabeCheck = SecurityValidator::validateClabe($clabe);
                if (!$clabeCheck['valid']) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => $clabeCheck['message']]);
                    exit;
                }
            }

            $last4 = SecurityValidator::sanitizeDigits($input['last_4'] ?? '');
            if (!empty($last4) && strlen($last4) !== 4) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Los últimos 4 dígitos deben contener exactamente 4 números']);
                exit;
            }

            $rfc = SecurityValidator::sanitizeAlphaNum($input['rfc'] ?? '');
            if (!empty($rfc)) {
                $rfcValid = SecurityValidator::validateRFC($rfc);
                if (!$rfcValid) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'El RFC no tiene un formato válido']);
                    exit;
                }
                $rfc = $rfcValid;
            }

            $bankName = SecurityValidator::sanitizeText($input['bank_name'] ?? '', 80);
            $accountHolder = SecurityValidator::sanitizeText($input['account_holder'] ?? '', 100);
            $providerAccountId = SecurityValidator::sanitizeText($input['provider_account_id'] ?? '', 120);

            $isPrimary = !empty($input['is_primary']) && $input['is_primary'] !== 'false';

            $stmt = $pdo->prepare("
                INSERT INTO admin_payment_accounts (account_type, account_name, payment_gateway, provider_account_id, 
                    bank_name, clabe, last_4, account_holder, rfc, is_primary, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, true)
            ");
            
            $stmt->execute([
                $gateway === 'bank_account' ? 'bank_account' : 'payment_gateway',
                $accountName,
                $gateway,
                $providerAccountId ?: null,
                $bankName ?: null,
                $clabe ?: null,
                $last4 ?: null,
                $accountHolder ?: null,
                $rfc ?: null,
                $isPrimary ? 1 : 0
            ]);
            
            $newId = $pdo->lastInsertId();
            if ($isPrimary && $newId) {
                try {
                    $pdo->prepare("SELECT set_primary_payment_account(?)")->execute([$newId]);
                } catch (Exception $e) {
                    $pdo->prepare("UPDATE admin_payment_accounts SET is_primary = false WHERE id <> ?")->execute([$newId]);
                    $pdo->prepare("UPDATE admin_payment_accounts SET is_primary = true WHERE id = ?")->execute([$newId]);
                }
            }

            echo json_encode(['success' => true, 'account_id' => $newId]);
            break;
            
        case 'update_payment_account':
            require_csrf_token();
            $accountId = (int)($_GET['account_id'] ?? 0);
            $input = json_decode(file_get_contents('php://input'), true);

            $accountName = SecurityValidator::sanitizeText($input['account_name'] ?? '', 100);
            if (empty($accountName) || mb_strlen($accountName) < 3) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'El nombre de la cuenta es obligatorio (mínimo 3 caracteres)']);
                exit;
            }

            $allowedGateways = ['stripe', 'mercadopago', 'bank_account'];
            $gateway = SecurityValidator::sanitizeAlphaNum($input['payment_gateway'] ?? '');
            if (!in_array($gateway, $allowedGateways)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Pasarela de pago no válida']);
                exit;
            }

            $clabe = SecurityValidator::sanitizeDigits($input['clabe'] ?? '');
            if (!empty($clabe)) {
                $clabeCheck = SecurityValidator::validateClabe($clabe);
                if (!$clabeCheck['valid']) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => $clabeCheck['message']]);
                    exit;
                }
            }

            $last4 = SecurityValidator::sanitizeDigits($input['last_4'] ?? '');
            if (!empty($last4) && strlen($last4) !== 4) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Los últimos 4 dígitos deben contener exactamente 4 números']);
                exit;
            }

            $rfc = SecurityValidator::sanitizeAlphaNum($input['rfc'] ?? '');
            if (!empty($rfc)) {
                $rfcValid = SecurityValidator::validateRFC($rfc);
                if (!$rfcValid) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'El RFC no tiene un formato válido']);
                    exit;
                }
                $rfc = $rfcValid;
            }

            $bankName = SecurityValidator::sanitizeText($input['bank_name'] ?? '', 80);
            $accountHolder = SecurityValidator::sanitizeText($input['account_holder'] ?? '', 100);
            $providerAccountId = SecurityValidator::sanitizeText($input['provider_account_id'] ?? '', 120);
            
            $stmt = $pdo->prepare("
                UPDATE admin_payment_accounts 
                SET account_name = ?, payment_gateway = ?, provider_account_id = ?, 
                    bank_name = ?, clabe = ?, last_4 = ?, account_holder = ?, rfc = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $accountName,
                $gateway,
                $providerAccountId ?: null,
                $bankName ?: null,
                $clabe ?: null,
                $last4 ?: null,
                $accountHolder ?: null,
                $rfc ?: null,
                $accountId
            ]);

            if (isset($input['is_primary'])) {
                $isPrimary = !empty($input['is_primary']) && $input['is_primary'] !== 'false';
                if ($isPrimary && $accountId) {
                    try {
                        $pdo->prepare("SELECT set_primary_payment_account(?)")->execute([$accountId]);
                    } catch (Exception $e) {
                        $pdo->prepare("UPDATE admin_payment_accounts SET is_primary = false WHERE id <> ?")->execute([$accountId]);
                        $pdo->prepare("UPDATE admin_payment_accounts SET is_primary = true WHERE id = ?")->execute([$accountId]);
                    }
                }
            }
            
            echo json_encode(['success' => true]);
            break;
            
        case 'set_primary_account':
            require_csrf_token();
            $accountId = $_GET['account_id'] ?? 0;
            $stmt = $pdo->prepare("SELECT set_primary_payment_account(?)");
            $stmt->execute([$accountId]);
            echo json_encode(['success' => true]);
            break;
            
        case 'delete_payment_account':
            require_csrf_token();
            $accountId = $_GET['account_id'] ?? 0;
            $stmt = $pdo->prepare("UPDATE admin_payment_accounts SET is_active = false, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$accountId]);
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no reconocida']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
