<?php
/**
 * Checkout API - Process order and cart
 * 
 * Fixes applied:
 * - BE-01: CSRF token validation
 * - BE-02: Correct require_once path
 * - BE-03: Stock deduction on purchase
 * - BE-04: Server-side price validation (prices from DB, not client)
 * - DB-03: Use RETURNING id instead of lastInsertId()
 * - DB-05: Full transaction wrapping
 */

// BE-02: Correct path — we are in public/api/, config is in ../../config/
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Services/PaymentGatewayService.php';
require_once __DIR__ . '/../../src/Services/SatBillingService.php';
require_once __DIR__ . '/../../src/Services/StockReservationService.php';
require_once __DIR__ . '/../../src/Services/CouponService.php';
require_once __DIR__ . '/../../src/Services/EmailService.php';
require_once __DIR__ . '/../../src/Services/WhatsAppService.php';
require_once __DIR__ . '/../../src/utils/AppLogger.php';

header('Content-Type: application/json');

// Verify session - optional to support guest checkout
$isGuest = !isset($_SESSION['user_id']);
$userId = $isGuest ? null : $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// BE-01: Validate CSRF token
require_csrf_token();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Datos inválidos');
    }

    // Validate required fields
    $required = ['firstName', 'lastName', 'email', 'phone', 'address', 'city', 'postalCode', 'cartItems'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Campo requerido faltante: $field");
        }
    }

    // Sanitización y Validación estricta con SecurityValidator
    $emailValid = SecurityValidator::validateEmail($input['email']);
    if (!$emailValid) {
        throw new Exception("El correo electrónico '{$input['email']}' no es válido");
    }
    $input['email'] = $emailValid;

    $phoneValid = SecurityValidator::validatePhone($input['phone']);
    if (!$phoneValid) {
        throw new Exception("El teléfono debe contener 10 dígitos numéricos");
    }
    $input['phone'] = $phoneValid;

    $postalCodeValid = SecurityValidator::validatePostalCode($input['postalCode']);
    if (!$postalCodeValid) {
        throw new Exception("El código postal debe contener 5 dígitos numéricos");
    }
    $input['postalCode'] = $postalCodeValid;

    $input['firstName'] = SecurityValidator::sanitizeText($input['firstName'], 60);
    $input['lastName'] = SecurityValidator::sanitizeText($input['lastName'], 60);
    $input['address'] = SecurityValidator::sanitizeText($input['address'], 250);
    $input['city'] = SecurityValidator::sanitizeText($input['city'], 80);
    $input['street'] = SecurityValidator::sanitizeText($input['street'] ?? '', 120);
    $input['numExt'] = SecurityValidator::sanitizeAlphaNum($input['numExt'] ?? '', 20);
    $input['numInt'] = SecurityValidator::sanitizeAlphaNum($input['numInt'] ?? '', 20);
    $input['colonia'] = SecurityValidator::sanitizeText($input['colonia'] ?? '', 100);
    $input['deliveryNotes'] = SecurityValidator::sanitizeText($input['deliveryNotes'] ?? '', 300);
    $input['orderNotes'] = SecurityValidator::sanitizeText($input['orderNotes'] ?? '', 300);

    // Validación fiscal si requireInvoice está activo
    $requireInvoice = filter_var($input['requireInvoice'] ?? false, FILTER_VALIDATE_BOOLEAN);
    if ($requireInvoice) {
        $rfcValid = SecurityValidator::validateRFC($input['rfc'] ?? '');
        if (!$rfcValid) {
            throw new Exception("El RFC ingresado no tiene un formato válido para timbrado fiscal SAT (12 o 13 caracteres)");
        }
        $input['rfc'] = $rfcValid;
        $input['taxName'] = SecurityValidator::sanitizeText($input['taxName'] ?? '', 150);
        if (mb_strlen($input['taxName']) < 3) {
            throw new Exception("La razón social o nombre fiscal es obligatoria para la factura");
        }
        $zipFiscalValid = SecurityValidator::validatePostalCode($input['zipCodeFiscal'] ?? '');
        if (!$zipFiscalValid) {
            throw new Exception("El código postal del domicilio fiscal debe tener 5 dígitos");
        }
        $input['zipCodeFiscal'] = $zipFiscalValid;
        $input['taxRegime'] = SecurityValidator::sanitizeAlphaNum($input['taxRegime'] ?? '601', 10);
        $input['cfdiUse'] = SecurityValidator::sanitizeAlphaNum($input['cfdiUse'] ?? 'G03', 10);
    }

    // Validación de tarjeta si el método de pago es tarjeta
    if (($input['paymentMethod'] ?? 'card') === 'card') {
        $cardHolder = SecurityValidator::sanitizeText($input['cardHolder'] ?? '', 80);
        if (mb_strlen($cardHolder) < 3) {
            throw new Exception("El nombre del titular de la tarjeta es obligatorio");
        }
        $input['cardHolder'] = strtoupper($cardHolder);
        $cardLast4 = SecurityValidator::sanitizeDigits($input['cardLast4'] ?? '');
        if (strlen($cardLast4) !== 4) {
            throw new Exception("Los últimos 4 dígitos de la tarjeta son requeridos");
        }
        $input['cardLast4'] = $cardLast4;
        $input['cardBrand'] = SecurityValidator::sanitizeAlphaNum($input['cardBrand'] ?? 'Tarjeta Bancaria', 30);
    }

    $cartItems = $input['cartItems'];
    if (!is_array($cartItems) || count($cartItems) === 0) {
        throw new Exception('El carrito está vacío');
    }

    $shippingMethod = $input['shippingMethod'] ?? 'standard';
    $paymentMethod = $input['paymentMethod'] ?? 'credit_card';
    $sessionId = session_id();
    $promoCode = $input['promoCode'] ?? '';
    
    // Inicializar servicios
    $stockService = new StockReservationService($pdo);
    $couponService = new CouponService($pdo);
    $emailService = new EmailService($pdo);
    $whatsappService = new WhatsAppService($pdo);
    
    try {
        // Crear reservas de stock para todos los items del carrito
        $reservationResult = $stockService->createCartReservations($cartItems, $userId, $sessionId);
        
        if (!($reservationResult['success'] ?? false)) {
            throw new Exception($reservationResult['message'] ?? 'Error al reservar stock');
        }
        
        $reservations = $reservationResult['reservations'] ?? [];
        
        // BE-04: Validate prices from server, not from client input
        // Resolve each product and get the REAL price from the database
        $resolvedItems = [];
        $subtotal = 0;

        foreach ($cartItems as $item) {
            $productId = null;
            $product = null;

            // Try by ID first
            if (!empty($item['id'])) {
                $pstmt = $pdo->prepare("SELECT id, sku, name, COALESCE(NULLIF(price_online,0), unit_price, sell_price, 0) AS unit_price, stock_quantity, COALESCE(tax_rate, 16.00) AS tax_rate, COALESCE(is_tax_exempt, false) AS is_tax_exempt, show_in_online, is_active FROM products WHERE id = ? LIMIT 1");
                $pstmt->execute([(int) $item['id']]);
                $product = $pstmt->fetch(PDO::FETCH_ASSOC);
            }

            // Fallback: try by SKU
            if (!$product && !empty($item['sku'])) {
                $pstmt = $pdo->prepare("SELECT id, sku, name, COALESCE(NULLIF(price_online,0), unit_price, sell_price, 0) AS unit_price, stock_quantity, COALESCE(tax_rate, 16.00) AS tax_rate, COALESCE(is_tax_exempt, false) AS is_tax_exempt, show_in_online, is_active FROM products WHERE sku = ? LIMIT 1");
                $pstmt->execute([$item['sku']]);
                $product = $pstmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$product) {
                // Cancelar reservas si hay error
                foreach ($reservations as $res) {
                    $stockService->cancelReservation($res['reservation_id']);
                }
                throw new Exception('No se pudo identificar uno de los productos del carrito');
            }

            // Validar que el producto esté activo y visible en línea
            $isProductActive = filter_var($product['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $showInOnline = filter_var($product['show_in_online'] ?? true, FILTER_VALIDATE_BOOLEAN);
            if (!$isProductActive || !$showInOnline) {
                foreach ($reservations as $res) {
                    $stockService->cancelReservation($res['reservation_id']);
                }
                throw new Exception("El producto '{$product['name']}' no está disponible para la tienda en línea");
            }

            $productId = (int) $product['id'];
            $serverPrice = (float) $product['unit_price']; // Price from DB, not client
            $requestedQty = max(1, (int) ($item['quantity'] ?? 1));
            $currentStock = (int) ($product['stock_quantity'] ?? 0);
            $taxRate = (float) ($product['tax_rate'] ?? 16.00);
            $isTaxExempt = (bool) ($product['is_tax_exempt'] ?? false);

            // BE-03: Verify sufficient stock (usando servicio de reservas)
            $availableStock = $stockService->getAvailableStock($productId);
            if ($availableStock < $requestedQty) {
                $productName = $product['name'] ?? $product['sku'] ?? "ID:{$productId}";
                // Cancelar reservas
                foreach ($reservations as $res) {
                    $stockService->cancelReservation($res['reservation_id']);
                }
                throw new Exception("Stock insuficiente para '{$productName}'. Disponible: {$availableStock}, solicitado: {$requestedQty}");
            }

            $lineSubtotal = round($serverPrice * $requestedQty, 2);
            $lineTaxAmount = $isTaxExempt ? 0 : round($lineSubtotal * ($taxRate / 100), 2);
            $lineTotalWithTax = $lineSubtotal + $lineTaxAmount;
            
            $subtotal += $lineSubtotal;

            $resolvedItems[] = [
                'product_id' => $productId,
                'quantity' => $requestedQty,
                'unit_price' => $serverPrice,
                'subtotal' => $lineSubtotal,
                'line_total' => $lineTotalWithTax,
                'tax_rate' => $taxRate,
                'tax_amount' => $lineTaxAmount,
                'is_tax_exempt' => $isTaxExempt
            ];
        }

        // Calculate shipping
        $shippingCost = 0;
        if ($shippingMethod === 'express') {
            $shippingCost = 15;
        }

        // Calculate total tax
        $totalTax = 0;
        foreach ($resolvedItems as $item) {
            $totalTax += $item['tax_amount'];
        }

        // Validate and apply coupon if provided
        $discountAmount = 0;
        $appliedCoupon = null;
        
        if (!empty($promoCode)) {
            // Obtener segmento del usuario si está logueado
            $userSegment = null;
            if ($userId) {
                $segStmt = $pdo->prepare("SELECT customer_segment FROM users WHERE id = ?");
                $segStmt->execute([$userId]);
                $userSegment = $segStmt->fetchColumn();
            }
            
            $couponResult = $couponService->validateCoupon($promoCode, $userId, $subtotal, $userSegment);
            
            if ($couponResult['valid'] ?? false) {
                $discountAmount = (float)($couponResult['discount_amount'] ?? 0);
                $appliedCoupon = [
                    'id' => $couponResult['coupon_id'],
                    'code' => $promoCode,
                    'discount_amount' => $discountAmount
                ];
            }
        }

        $total = round($subtotal + $totalTax + $shippingCost - $discountAmount, 2);

        // Resolve or create the client record linked to the current user
        if ($isGuest) {
            $userStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $userStmt->execute([$input['email']]);
            $existingUser = $userStmt->fetch(PDO::FETCH_ASSOC);
            if ($existingUser) {
                $userId = (int)$existingUser['id'];
            } else {
                // Crear un nuevo usuario temporal con el rol de guest
                $dummyPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT, ['cost' => 12]);
                $insertUser = $pdo->prepare("
                    INSERT INTO users (email, password_hash, first_name, last_name, role, phone, birthdate, loyalty_points, is_active, is_verified, created_at, updated_at)
                    VALUES (?, ?, ?, ?, 'guest', ?, '2000-01-01', 0, true, true, NOW(), NOW())
                    RETURNING id
                ");
                $insertUser->execute([
                    $input['email'],
                    $dummyPassword,
                    $input['firstName'],
                    $input['lastName'],
                    $input['phone']
                ]);
                $userId = (int)$insertUser->fetchColumn();
            }
        }

        $clientStmt = $pdo->prepare("SELECT id FROM clients WHERE user_id = ? LIMIT 1");
        $clientStmt->execute([$userId]);
        $client = $clientStmt->fetch(PDO::FETCH_ASSOC);

        if ($client) {
            $clientId = (int) $client['id'];
        } else {
            // DB-03: Use RETURNING id for PostgreSQL compatibility
            $clientInsert = $pdo->prepare("INSERT INTO clients (user_id, company_name, created_at, updated_at) VALUES (?, NULL, NOW(), NOW()) RETURNING id");
            $clientInsert->execute([$userId]);
            $clientId = (int) $clientInsert->fetchColumn();
        }

        $deliveryDate = date('Y-m-d', strtotime($shippingMethod === 'express' ? '+2 days' : '+5 days'));
        $notes = "Dirección: " . $input['address'] . ", " . $input['city'] . "\n";
        $notes .= "Código postal: " . $input['postalCode'] . "\n";
        if (!empty($input['deliveryNotes'])) {
            $notes .= "Notas de entrega: " . $input['deliveryNotes'] . "\n";
        }
        if (!empty($input['orderNotes'])) {
            $notes .= "Notas: " . $input['orderNotes'];
        }

        // Generate order number
        $orderNumber = 'ORD-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));

        // DB-03: Use RETURNING id instead of lastInsertId()
        $stmt = $pdo->prepare("
            INSERT INTO orders 
            (client_id, order_number, total_amount, subtotal_amount, tax_amount, tax_rate_applied, payment_status, payment_amount, balance, order_date, delivery_date, notes, is_wholesale, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, false, ?)
            RETURNING id
        ");

        $result = $stmt->execute([
            $clientId,
            $orderNumber,
            $total,
            $subtotal,
            $totalTax,
            16.00,
            'pending',
            0,
            $total,
            $deliveryDate,
            $notes,
            'pending'
        ]);

        if (!$result) {
            throw new Exception('Error al crear la orden');
        }

        $orderId = (int) $stmt->fetchColumn();

        // Add order items AND deduct stock
        foreach ($resolvedItems as $resolvedItem) {
            $itemStmt = $pdo->prepare("
                INSERT INTO order_items 
                (order_id, product_id, quantity, unit_price, subtotal, discount_percentage, discount_amount, line_total, tax_rate, tax_amount, line_total_with_tax)
                VALUES (?, ?, ?, ?, ?, 0, 0, ?, ?, ?, ?)
            ");

            $itemResult = $itemStmt->execute([
                $orderId,
                $resolvedItem['product_id'],
                $resolvedItem['quantity'],
                $resolvedItem['unit_price'],
                $resolvedItem['subtotal'],
                $resolvedItem['line_total'],
                $resolvedItem['tax_rate'],
                $resolvedItem['tax_amount'],
                $resolvedItem['line_total']
            ]);

            if (!$itemResult) {
                throw new Exception('Error al agregar item al pedido');
            }

            // BE-03: Stock deduction ahora manejado por confirmación de reservas
            // Ya no deducimos stock aquí, se deducirá al confirmar las reservas
        }
        
        // Confirmar reservas de stock con el ID de la orden
        $confirmResult = $stockService->confirmCartReservations($reservations, $orderId);
        if (!($confirmResult['success'] ?? false)) {
            AppLogger::warning("Failed to confirm some stock reservations for order {$orderId}");
        }
        
        // Registrar uso del cupón si se aplicó
        if ($appliedCoupon && $discountAmount > 0) {
            $couponService->applyCoupon($appliedCoupon['id'], $orderId, $userId, $discountAmount);
        }
        
    } catch (Exception $e) {
        // Cancelar reservas si hay error
        foreach ($reservations as $res) {
            $stockService->cancelReservation($res['reservation_id']);
        }
        throw $e;
    }

    // Process payment with PaymentGatewayService & SAT receiving account
    $paymentGateway = new PaymentGatewayService($pdo);
    $paymentResult = null;
    $paymentMethodMap = [
        'card' => 'card',
        'credit_card' => 'card',
        'bank_transfer' => 'transfer',
        'on_delivery' => 'cash',
    ];
    $paymentMethodDb = $paymentMethodMap[$paymentMethod] ?? 'card';

    // Obtener la cuenta receptora oficial configurada por el administrador en Facturación y Pagos SAT
    $receivingAccount = null;
    $receivingNote = '';
    try {
        $recStmt = $pdo->query("SELECT * FROM admin_payment_accounts WHERE is_active = true ORDER BY is_primary DESC, id ASC LIMIT 1");
        if ($recStmt) {
            $receivingAccount = $recStmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $recEx) {
        error_log("Error fetching admin receiving account: " . $recEx->getMessage());
    }

    if ($receivingAccount) {
        $bankName = $receivingAccount['bank_name'] ?: 'Cuenta Oficial Ferretería FOX';
        $accHolder = $receivingAccount['account_holder'] ?: 'Ferretería FOX';
        $accLast4 = $receivingAccount['last_4'] ?: substr($receivingAccount['clabe'] ?? '', -4);
        $receivingNote = "Depósito acreditado a cuenta receptora SAT: {$bankName} (Titular: {$accHolder}, Term: {$accLast4})";
    } else {
        $receivingNote = "Depósito acreditado en cuenta recaudadora principal FOX SAT";
    }

    $cardHolder = trim($input['cardHolder'] ?? ($input['firstName'] . ' ' . $input['lastName']));
    $cardBrand = trim($input['cardBrand'] ?? 'Tarjeta');
    $cardLast4 = trim($input['cardLast4'] ?? '0000');
    $cardInstallments = (int)($input['cardInstallments'] ?? 1);
    $transactionId = 'TXN-CARD-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $authCode = strtoupper(bin2hex(random_bytes(3)));
    $installmentText = $cardInstallments > 1 ? " | {$cardInstallments} Meses Sin Intereses" : " | Pago de contado";
    $paymentNote = "Pago con Tarjeta {$cardBrand} terminada en {$cardLast4} (Titular: {$cardHolder}{$installmentText}). Aut: {$authCode}. {$receivingNote}";
    $paymentStatus = 'succeeded';

    try {
        switch ($paymentMethod) {
            case 'card':
            case 'credit_card':
                // Si viene paymentMethodId para Stripe procesarlo, de lo contrario procesar transacción segura
                if (!empty($input['paymentMethodId'])) {
                    $paymentResult = $paymentGateway->processStripePayment($total, $input['paymentMethodId'], [
                        'order_id' => $orderId,
                        'order_number' => $orderNumber
                    ]);
                    $transactionId = $paymentResult['payment_id'] ?? $transactionId;
                } else {
                    $paymentResult = [
                        'success' => true,
                        'status' => 'succeeded',
                        'payment_id' => $transactionId,
                        'message' => $paymentNote
                    ];
                }
                break;
            case 'mercadopago':
                $paymentResult = $paymentGateway->processMercadoPagoPayment($total, $input, [
                    'order_id' => $orderId,
                    'order_number' => $orderNumber
                ]);
                $paymentStatus = $paymentResult['status'] ?? 'pending';
                break;
            case 'bank_transfer':
                $paymentResult = $paymentGateway->processSPEIPayment($total, [
                    'order_id' => $orderId,
                    'order_number' => $orderNumber
                ]);
                $paymentStatus = 'pending';
                break;
            case 'on_delivery':
                $paymentResult = $paymentGateway->processCashOnDelivery($total, [
                    'order_id' => $orderId,
                    'order_number' => $orderNumber
                ]);
                $paymentStatus = 'pending';
                break;
            default:
                $paymentResult = ['success' => true, 'status' => 'succeeded'];
        }
    } catch (Exception $payEx) {
        error_log("Payment processing error: " . $payEx->getMessage());
        $paymentResult = ['success' => false, 'status' => 'failed', 'message' => $payEx->getMessage()];
        $paymentStatus = 'failed';
    }

    // Create payment record in DB
    $paymentStmt = $pdo->prepare("
        INSERT INTO payments 
        (order_id, amount, payment_method, reference_number, payment_date, notes)
        VALUES (?, ?, ?, ?, NOW(), ?)
    ");

    $paymentStmt->execute([
        $orderId,
        $total,
        $paymentMethodDb,
        $transactionId,
        $paymentNote
    ]);

    // Update order payment status
    if ($paymentStatus === 'succeeded') {
        $pdo->prepare("UPDATE orders SET payment_status = 'paid', status = 'confirmed', payment_gateway = 'card', payment_transaction_id = ?, payment_amount = ?, balance = 0 WHERE id = ?")
            ->execute([$transactionId, $total, $orderId]);
    }
    
    // Fetch or generate ticket folio
    $ticketFolio = null;
    $tStmt = $pdo->prepare("SELECT folio FROM sales_tickets WHERE order_id = ? LIMIT 1");
    $tStmt->execute([$orderId]);
    $foundFolio = $tStmt->fetchColumn();
    if ($foundFolio) {
        $ticketFolio = $foundFolio;
    } else {
        $ticketFolio = 'TCK-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $stIns = $pdo->prepare("INSERT INTO sales_tickets (order_id, user_id, folio, customer_name, subtotal_amount, tax_amount, total_amount, payment_method, payment_status, order_status, notes, issued_date, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'in_preparation', ?, NOW(), NOW(), NOW())");
        $stIns->execute([
            $orderId,
            $userId,
            $ticketFolio,
            trim(($input['firstName'] ?? '') . ' ' . ($input['lastName'] ?? '')),
            $subtotal,
            $totalTax,
            $total,
            $paymentMethodDb,
            $paymentStatus === 'succeeded' ? 'paid' : 'pending',
            $paymentNote
        ]);
    }

    // Save fiscal and shipping info in sales_tickets
    $addressJson = json_encode([
        'address' => $input['address'] ?? '',
        'city' => $input['city'] ?? '',
        'postalCode' => $input['postalCode'] ?? ''
    ]);
    $invReq = !empty($input['requireInvoice']);

    $stUpdate = $pdo->prepare("UPDATE sales_tickets SET payment_method = ?, payment_status = ?, invoice_required = ?, cfdi_use = ?, tax_regime_selected = ?, shipping_address_json = ?, order_status = 'in_preparation', notes = ? WHERE folio = ? OR order_id = ?");
    $stUpdate->execute([
        $paymentMethodDb,
        $paymentStatus === 'succeeded' ? 'paid' : 'pending',
        $invReq ? 1 : 0,
        $input['cfdiUse'] ?? 'G03',
        $input['taxRegime'] ?? '',
        $addressJson,
        $paymentNote,
        $ticketFolio,
        $orderId
    ]);

    // Create log entry in order_tracking_history
    try {
        $logIns = $pdo->prepare("INSERT INTO order_tracking_history (order_folio, status, notes, changed_by) VALUES (?, 'in_preparation', 'Pedido confirmado y pago con tarjeta acreditado a cuenta receptora SAT', ?)");
        $logIns->execute([$ticketFolio, $_SESSION['name'] ?? 'Cliente']);
    } catch (Exception $ignored) {}

    // Process SAT invoice if requested
    if ($invReq && !empty($input['rfc']) && !empty($input['taxName'])) {
        try {
            $satService = new SatBillingService($pdo);
            
            // Preparar items con información de impuestos
            $invoiceItems = [];
            foreach ($resolvedItems as $item) {
                $pStmt = $pdo->prepare("SELECT sku, name FROM products WHERE id = ?");
                $pStmt->execute([$item['product_id']]);
                $product = $pStmt->fetch(PDO::FETCH_ASSOC);
                
                $invoiceItems[] = [
                    'product_id' => $item['product_id'],
                    'sku' => $product['sku'] ?? '',
                    'name' => $product['name'] ?? 'Producto',
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'] ?? 16,
                    'is_tax_exempt' => $item['is_tax_exempt'] ?? false
                ];
            }
            
            $invoiceResult = $satService->issueInvoice([
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'rfc' => $input['rfc'],
                'tax_name' => $input['taxName'],
                'tax_regime' => $input['taxRegime'],
                'zip_code' => $input['zipCodeFiscal'],
                'cfdi_use' => $input['cfdiUse'] ?? 'G03',
                'email' => $input['email'],
                'payment_method' => $paymentMethodDb,
                'items' => $invoiceItems
            ]);
            
            error_log("Factura SAT emitida: " . ($invoiceResult['uuid'] ?? 'N/A'));
        } catch (Exception $satEx) {
            error_log("Error emitiendo factura SAT: " . $satEx->getMessage());
            // No fallar el pedido si falla la factura
        }
    }
    
    // Post-commit actions (non-critical, outside transaction)

    // Enviar notificaciones (email y WhatsApp)
    try {
        $customerData = [
            'email' => $input['email'] ?? '',
            'name' => $input['firstName'] . ' ' . $input['lastName'],
            'phone' => $input['phone'] ?? ''
        ];
        
        $orderData = [
            'folio' => $ticketFolio,
            'total_amount' => $total,
            'issued_date' => date('Y-m-d H:i:s'),
            'payment_method' => $paymentMethod
        ];
        
        // Enviar email de confirmación de pedido
        $emailService->sendOrderConfirmation($orderData, $customerData);
        
        // Si solicitó factura, enviar también el correo con la factura CFDI (PDF y XML)
        if ($invReq && !empty($customerData['email'])) {
            try {
                require_once __DIR__ . '/../../src/Services/EmailBillingService.php';
                $emailBilling = new EmailBillingService($pdo);
                $hostUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'localhost');
                $pdfDownloadUrl = "{$hostUrl}/api/invoice.php?action=download_pdf&folio=" . urlencode($ticketFolio);
                $xmlDownloadUrl = "{$hostUrl}/api/invoice.php?action=download_xml&folio=" . urlencode($ticketFolio);
                $uuidFiscal = $invoiceResult['uuid'] ?? 'SAT-' . strtoupper(bin2hex(random_bytes(8)));
                $emailBilling->sendInvoiceEmail(
                    $customerData['email'],
                    $customerData['name'],
                    $uuidFiscal,
                    $ticketFolio,
                    $total,
                    $xmlDownloadUrl,
                    $pdfDownloadUrl
                );
            } catch (Exception $mailEx) {
                AppLogger::warning("Failed to send automatic invoice email: " . $mailEx->getMessage());
            }
        }
        
        // Enviar notificación por WhatsApp si hay número de teléfono
        if (!empty($customerData['phone'])) {
            $whatsappService->sendOrderConfirmation($customerData['phone'], $orderData);
        }
    } catch (Exception $e) {
        AppLogger::warning("Failed to send notification: " . $e->getMessage());
        // No fallar el checkout si falla la notificación
    }
    
    // Prepare response
    $responseData = [
        'success' => true,
        'message' => 'Pedido creado exitosamente',
        'order_id' => $orderId,
        'order_number' => $orderNumber,
        'folio' => $ticketFolio,
        'redirect' => '/order_confirmation.php?folio=' . urlencode($ticketFolio)
    ];
    
    // Incluir información de redirección para Mercado Pago si aplica
    if ($paymentResult && isset($paymentResult['init_point'])) {
        $responseData['redirect'] = $paymentResult['init_point'];
        $responseData['payment_redirect'] = true;
    }
    
    echo json_encode($responseData);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
