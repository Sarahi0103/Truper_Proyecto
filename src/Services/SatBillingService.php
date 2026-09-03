<?php
/**
 * SAT Billing Service
 * Integración real con Facturapi para timbrado CFDI 4.0
 * Truper Platform
 */

// require_once __DIR__ . '/../../vendor/autoload.php'; // Comentado ya que no existe vendor
require_once __DIR__ . '/../utils/SatCatalogs.php';

class SatBillingService {
    private $facturapi;
    private $pdo;
    private $logger;
    
    // Mapeo de métodos de pago SAT (c_FormaPago)
    private $paymentMethodMap = [
        'card' => '04', // Tarjeta de crédito
        'transfer' => '03', // Transferencia electrónica
        'cash' => '01', // Efectivo
        'mercadopago' => '04', // Tarjeta (MP usa tarjetas principalmente)
        'spei' => '03' // SPEI
    ];
    
    // Mapeo de moneda
    private $currencyMap = [
        'MXN' => 'MXN',
        'USD' => 'USD'
    ];

    public function __construct($pdo) {
        $this->pdo = $pdo;
        if (class_exists('AppLogger')) {
            $this->logger = new AppLogger();
        }
        
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
     * Emitir factura CFDI 4.0
     */
    public function issueInvoice($orderData) {
        try {
            $this->pdo->beginTransaction();
            
            // Obtener configuración fiscal de la empresa
            $companyRfc = $this->getSetting('company_rfc');
            $companyTaxName = $this->getSetting('company_tax_name');
            $companyTaxRegime = $this->getSetting('company_tax_regime');
            $companyZipCode = $this->getSetting('company_zip_code');
            
            if (!$companyRfc || !$companyTaxName || !$companyTaxRegime || !$companyZipCode) {
                throw new Exception('Configuración fiscal incompleta. Configure RFC, Razón Social, Régimen Fiscal y C.P. fiscal.');
            }
            
            // Calcular totales con impuestos
            $items = $orderData['items'];
            $subtotal = 0;
            $totalTax = 0;
            $total = 0;
            
            $facturapiItems = [];
            
            foreach ($items as $item) {
                $lineSubtotal = $item['unit_price'] * $item['quantity'];
                $taxRate = $item['tax_rate'] ?? 16;
                $taxAmount = $lineSubtotal * ($taxRate / 100);
                $lineTotal = $lineSubtotal + $taxAmount;
                
                $subtotal += $lineSubtotal;
                $totalTax += $taxAmount;
                $total += $lineTotal;
                
                $facturapiItems[] = [
                    'description' => $item['name'] ?? 'Producto',
                    'quantity' => $item['quantity'],
                    'product_key' => '50202300', // Clave genérica de productos (debe personalizarse)
                    'unit_key' => 'H87', // Pieza
                    'unit_name' => 'Pieza',
                    'price' => $item['unit_price'],
                    'sku' => $item['sku'] ?? '',
                    'tax_included' => false,
                    'taxes' => [
                        [
                            'type' => 'IVA',
                            'rate' => $taxRate / 100
                        ]
                    ]
                ];
            }
            
            // Mapear método de pago SAT
            $paymentMethod = $this->paymentMethodMap[$orderData['payment_method']] ?? '01';
            
            // Crear factura en Facturapi
            $invoice = $this->facturapi->invoices->create([
                'type' => 'I', // Ingreso
                'series' => 'A',
                'customer' => [
                    'legal_name' => $orderData['tax_name'],
                    'tax_id' => $orderData['rfc'],
                    'tax_regime' => $orderData['tax_regime'],
                    'zip_code' => $orderData['zip_code'],
                    'email' => $orderData['email'] ?? ''
                ],
                'items' => $facturapiItems,
                'payment_form' => $paymentMethod,
                'payment_method' => 'PUE', // Pago en una sola exhibición
                'use' => $orderData['cfdi_use'] ?? 'G03',
                'currency' => 'MXN',
                'date' => date('c'),
                'related' => [
                    [
                        'type' => 'order',
                        'number' => $orderData['order_number']
                    ]
                ]
            ]);
            
            // Guardar datos de la factura en la orden
            $stmt = $this->pdo->prepare("
                UPDATE orders 
                SET sat_uuid = ?,
                    sat_xml_url = ?,
                    sat_pdf_url = ?,
                    sat_status = 'issued',
                    requires_invoice = true,
                    tax_rfc = ?,
                    tax_name = ?,
                    tax_regime = ?,
                    tax_zip = ?,
                    cfdi_use = ?,
                    subtotal_amount = ?,
                    tax_amount = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $invoice->id,
                $invoice->download_url . '.xml',
                $invoice->download_url . '.pdf',
                $orderData['rfc'],
                $orderData['tax_name'],
                $orderData['tax_regime'],
                $orderData['zip_code'],
                $orderData['cfdi_use'],
                $subtotal,
                $totalTax,
                $orderData['order_id']
            ]);
            
            $this->pdo->commit();
            
            $this->logger->info("Factura emitida: {$invoice->id} para orden {$orderData['order_number']}");
            
            return [
                'success' => true,
                'uuid' => $invoice->id,
                'xml_url' => $invoice->download_url . '.xml',
                'pdf_url' => $invoice->download_url . '.pdf',
                'subtotal' => $subtotal,
                'tax_amount' => $totalTax,
                'total' => $total
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->logger->error("Error emitiendo factura: " . $e->getMessage());
            throw new Exception('Error al emitir factura: ' . $e->getMessage());
        }
    }

    /**
     * Cancelar factura SAT
     */
    public function cancelInvoice($uuid, $reason, $notes = '') {
        try {
            $this->pdo->beginTransaction();
            
            // Validar motivo de cancelación
            $validReasons = ['01', '02', '03', '04'];
            if (!in_array($reason, $validReasons)) {
                throw new Exception('Motivo de cancelación inválido');
            }
            
            // Cancelar en Facturapi
            $cancellation = $this->facturapi->invoices->cancel($uuid, [
                'motive' => $reason,
                'replacement' => null // Si hay factura sustituta, poner el UUID aquí
            ]);
            
            // Actualizar estado en BD
            $stmt = $this->pdo->prepare("
                UPDATE orders 
                SET sat_status = 'cancelled',
                    sat_cancellation_reason = ?,
                    sat_cancellation_status = 'cancelled',
                    notes = CONCAT(COALESCE(notes, ''), '\n[CANCELACIÓN SAT ', NOW(), ']: ', ?),
                    updated_at = NOW()
                WHERE sat_uuid = ?
            ");
            $stmt->execute([$reason, $notes, $uuid]);
            
            $this->pdo->commit();
            
            $this->logger->info("Factura cancelada: {$uuid} con motivo {$reason}");
            
            return [
                'success' => true,
                'uuid' => $uuid,
                'reason' => $reason
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->logger->error("Error cancelando factura: " . $e->getMessage());
            throw new Exception('Error al cancelar factura: ' . $e->getMessage());
        }
    }

    /**
     * Obtener factura por UUID
     */
    public function getInvoice($uuid) {
        try {
            $invoice = $this->facturapi->invoices->retrieve($uuid);
            
            return [
                'success' => true,
                'invoice' => $invoice
            ];
        } catch (Exception $e) {
            $this->logger->error("Error obteniendo factura: " . $e->getMessage());
            throw new Exception('Error al obtener factura: ' . $e->getMessage());
        }
    }

    /**
     * Reemitir factura (cancelar y emitir nueva)
     */
    public function reissueInvoice($orderId, $newOrderData) {
        try {
            $this->pdo->beginTransaction();
            
            // Obtener factura actual
            $stmt = $this->pdo->prepare("SELECT sat_uuid FROM orders WHERE id = ?");
            $stmt->execute([$orderId]);
            $currentUuid = $stmt->fetchColumn();
            
            if ($currentUuid) {
                // Cancelar factura anterior
                $this->cancelInvoice($currentUuid, '02', 'Reemisión de factura');
            }
            
            // Emitir nueva factura
            $newOrderData['order_id'] = $orderId;
            $result = $this->issueInvoice($newOrderData);
            
            $this->pdo->commit();
            
            return $result;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Validar RFC contra SAT (formato y estructura)
     */
    public function validateRfc($rfc) {
        // Validar formato básico
        if (!preg_match('/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/', $rfc)) {
            return [
                'valid' => false,
                'message' => 'Formato de RFC inválido'
            ];
        }
        
        // Validar longitud
        if (strlen($rfc) !== 12 && strlen($rfc) !== 13) {
            return [
                'valid' => false,
                'message' => 'RFC debe tener 12 (moral) o 13 (física) caracteres'
            ];
        }
        
        // Validar contra catálogo de RFC genéricos
        $genericRfcs = ['XAXX010101000', 'XEXX010101000'];
        if (in_array($rfc, $genericRfcs)) {
            return [
                'valid' => true,
                'is_generic' => true,
                'message' => 'RFC genérico (Público en General)'
            ];
        }
        
        return [
            'valid' => true,
            'is_generic' => false,
            'message' => 'RFC válido'
        ];
    }
}
?>
