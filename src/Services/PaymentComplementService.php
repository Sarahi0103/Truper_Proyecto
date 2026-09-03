<?php
/**
 * Payment Complement Service
 * Genera complementos de pago CFDI 4.0 para pagos parciales (requisito SAT)
 * Truper Platform
 */

if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

class PaymentComplementService {
    private $facturapi;
    private $pdo;
    private $logger;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        if (class_exists('AppLogger')) {
            $this->logger = new AppLogger();
        }
        
        // Obtener API key de Facturapi
        $apiKey = $this->getSetting('facturapi_api_key') ?: getenv('FACTURAPI_KEY');
        if ($apiKey && class_exists('\\Facturapi\\Facturapi')) {
            $this->facturapi = new \Facturapi\Facturapi($apiKey);
        }
    }

    private function getSetting($key) {
        try {
            $stmt = $this->pdo->prepare("SELECT config_value FROM system_config WHERE config_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            if ($val !== false && $val !== null) {
                return $val;
            }

            $stmt2 = $this->pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
            $stmt2->execute([$key]);
            return $stmt2->fetchColumn();
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Emitir complemento de pago
     */
    public function issuePaymentComplement($originalInvoiceUuid, $paymentData) {
        try {
            $this->pdo->beginTransaction();
            
            // Obtener la factura original
            $originalInvoice = $this->facturapi->invoices->retrieve($originalInvoiceUuid);
            
            if (!$originalInvoice) {
                throw new Exception('Factura original no encontrada');
            }
            
            // Mapear método de pago SAT
            $paymentMethodMap = [
                'card' => '04',
                'transfer' => '03',
                'cash' => '01',
                'spei' => '03'
            ];
            
            $paymentForm = $paymentMethodMap[$paymentData['payment_method']] ?? '01';
            
            // Crear complemento de pago en Facturapi
            $complement = $this->facturapi->invoices->create([
                'type' => 'P', // Pago
                'series' => 'P',
                'customer' => $originalInvoice->customer,
                'related' => [
                    [
                        'type' => 'invoice',
                        'uuid' => $originalInvoiceUuid
                    ]
                ],
                'payment_form' => $paymentForm,
                'payment_method' => 'PUE', // Pago en una sola exhibición
                'currency' => 'MXN',
                'date' => date('c'),
                'items' => [
                    [
                        'description' => 'Pago parcial',
                        'quantity' => 1,
                        'product_key' => '84111506',
                        'unit_key' => 'ACT',
                        'unit_name' => 'Actividad',
                        'price' => $paymentData['amount'],
                        'tax_included' => true
                    ]
                ]
            ]);
            
            // Guardar complemento en base de datos
            $stmt = $this->pdo->prepare("
                INSERT INTO payment_complements 
                (original_invoice_uuid, complement_uuid, amount, payment_method, payment_date, xml_url, pdf_url, created_at)
                VALUES (?, ?, ?, ?, NOW(), ?, ?, NOW())
            ");
            $stmt->execute([
                $originalInvoiceUuid,
                $complement->id,
                $paymentData['amount'],
                $paymentData['payment_method'],
                $complement->download_url . '.xml',
                $complement->download_url . '.pdf'
            ]);
            
            $this->pdo->commit();
            
            $this->logger->info("Complemento de pago emitido: {$complement->id} para factura {$originalInvoiceUuid}");
            
            return [
                'success' => true,
                'complement_uuid' => $complement->id,
                'xml_url' => $complement->download_url . '.xml',
                'pdf_url' => $complement->download_url . '.pdf',
                'amount' => $paymentData['amount']
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->logger->error("Error emitiendo complemento de pago: " . $e->getMessage());
            throw new Exception('Error al emitir complemento de pago: ' . $e->getMessage());
        }
    }

    /**
     * Obtener complementos de una factura
     */
    public function getPaymentComplements($originalInvoiceUuid) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM payment_complements 
                WHERE original_invoice_uuid = ?
                ORDER BY payment_date DESC
            ");
            $stmt->execute([$originalInvoiceUuid]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Error obteniendo complementos de pago: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Calcular saldo pendiente de una factura
     */
    public function calculatePendingBalance($originalInvoiceUuid) {
        try {
            // Obtener factura original
            $originalInvoice = $this->facturapi->invoices->retrieve($originalInvoiceUuid);
            
            if (!$originalInvoice) {
                throw new Exception('Factura original no encontrada');
            }
            
            $totalAmount = $originalInvoice->total;
            
            // Sumar complementos de pago
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(amount), 0) as paid_amount 
                FROM payment_complements 
                WHERE original_invoice_uuid = ?
            ");
            $stmt->execute([$originalInvoiceUuid]);
            $paidAmount = (float)$stmt->fetchColumn();
            
            $pendingBalance = $totalAmount - $paidAmount;
            
            return [
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'pending_balance' => max(0, $pendingBalance),
                'is_fully_paid' => $pendingBalance <= 0
            ];
            
        } catch (Exception $e) {
            $this->logger->error("Error calculando saldo pendiente: " . $e->getMessage());
            throw $e;
        }
    }
}
?>
