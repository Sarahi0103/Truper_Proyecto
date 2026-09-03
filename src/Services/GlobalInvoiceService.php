<?php
/**
 * Global Invoice Service
 * Genera facturas globales para ventas sin factura individual (Requisito SAT)
 * Truper Platform
 */

if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

class GlobalInvoiceService {
    private $facturapi;
    private $pdo;
    private $logger;
    
    // RFC genérico para público en general
    const PUBLIC_RFC = 'XAXX010101000';
    const PUBLIC_NAME = 'PÚBLICO EN GENERAL';
    const PUBLIC_REGIME = '616'; // Sin obligaciones fiscales

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
     * Obtener ventas del día sin factura individual
     */
    private function getSalesWithoutInvoice($date) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    o.id as order_id,
                    o.order_number,
                    o.total_amount,
                    o.created_at,
                    oi.product_id,
                    oi.quantity,
                    oi.unit_price,
                    p.sku,
                    p.name as product_name
                FROM orders o
                INNER JOIN order_items oi ON o.id = oi.order_id
                INNER JOIN products p ON oi.product_id = p.id
                WHERE DATE(o.created_at) = ?
                AND (o.requires_invoice = false OR o.requires_invoice IS NULL)
                AND (o.sat_uuid IS NULL OR o.sat_uuid = '')
                AND o.status NOT IN ('cancelled', 'refunded')
                AND o.payment_status = 'paid'
                ORDER BY o.created_at ASC
            ");
            $stmt->execute([$date]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Error obteniendo ventas sin factura: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Agrupar ventas para factura global
     */
    private function groupSalesForGlobal($sales) {
        $groupedItems = [];
        
        foreach ($sales as $sale) {
            $sku = $sale['sku'] ?? '';
            $key = $sku;
            
            if (!isset($groupedItems[$key])) {
                $groupedItems[$key] = [
                    'description' => $sale['product_name'],
                    'quantity' => 0,
                    'product_key' => '50202300', // Clave genérica
                    'unit_key' => 'H87', // Pieza
                    'unit_name' => 'Pieza',
                    'price' => $sale['unit_price'],
                    'sku' => $sku
                ];
            }
            
            $groupedItems[$key]['quantity'] += $sale['quantity'];
        }
        
        return array_values($groupedItems);
    }

    /**
     * Generar factura global del día
     */
    public function generateDailyGlobalInvoice($date = null) {
        if (!$date) {
            $date = date('Y-m-d', strtotime('-1 day')); // Ayer por defecto
        }
        
        try {
            $this->pdo->beginTransaction();
            
            // Obtener ventas sin factura
            $sales = $this->getSalesWithoutInvoice($date);
            
            if (empty($sales)) {
                $this->logger->info("No hay ventas sin factura para el día {$date}");
                $this->pdo->rollBack();
                return ['success' => true, 'message' => 'No hay ventas para facturar', 'invoice' => null];
            }
            
            // Obtener configuración fiscal de la empresa
            $companyZipCode = $this->getSetting('company_zip_code');
            if (!$companyZipCode) {
                throw new Exception('Código postal fiscal no configurado');
            }
            
            // Agrupar items
            $items = $this->groupSalesForGlobal($sales);
            
            // Calcular totales
            $subtotal = 0;
            foreach ($items as $item) {
                $lineTotal = $item['price'] * $item['quantity'];
                $subtotal += $lineTotal;
            }
            
            $taxAmount = $subtotal * 0.16; // 16% IVA
            $total = $subtotal + $taxAmount;
            
            // Crear factura global en Facturapi
            $invoice = $this->facturapi->invoices->create([
                'type' => 'I', // Ingreso
                'series' => 'G', // Serie para facturas globales
                'customer' => [
                    'legal_name' => self::PUBLIC_NAME,
                    'tax_id' => self::PUBLIC_RFC,
                    'tax_regime' => self::PUBLIC_REGIME,
                    'zip_code' => $companyZipCode
                ],
                'items' => array_map(function($item) {
                    return [
                        'description' => $item['description'],
                        'quantity' => $item['quantity'],
                        'product_key' => $item['product_key'],
                        'unit_key' => $item['unit_key'],
                        'unit_name' => $item['unit_name'],
                        'price' => $item['price'],
                        'sku' => $item['sku'],
                        'tax_included' => false,
                        'taxes' => [
                            [
                                'type' => 'IVA',
                                'rate' => 0.16
                            ]
                        ]
                    ];
                }, $items),
                'payment_form' => '01', // Efectivo (más común en mostrador)
                'payment_method' => 'PUE', // Pago en una sola exhibición
                'use' => 'G03', // Gastos en general
                'currency' => 'MXN',
                'date' => $date . 'T12:00:00-06:00', // Mediodía del día
                'comments' => 'Factura Global del día ' . $date
            ]);
            
            // Guardar factura global en tabla dedicada
            $stmt = $this->pdo->prepare("
                INSERT INTO global_invoices 
                (invoice_uuid, invoice_date, total_amount, sales_count, xml_url, pdf_url, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $invoice->id,
                $date,
                $total,
                count($sales),
                $invoice->download_url . '.xml',
                $invoice->download_url . '.pdf'
            ]);
            
            $globalInvoiceId = $this->pdo->lastInsertId();
            
            // Marcar ventas como facturadas en global
            $orderIds = array_unique(array_column($sales, 'order_id'));
            foreach ($orderIds as $orderId) {
                $stmt = $this->pdo->prepare("
                    UPDATE orders 
                    SET global_invoice_id = ?,
                        sat_uuid = ?,
                        sat_status = 'global',
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$globalInvoiceId, $invoice->id, $orderId]);
            }
            
            $this->pdo->commit();
            
            $this->logger->info("Factura global generada: {$invoice->id} para {$date} con " . count($sales) . " ventas");
            
            return [
                'success' => true,
                'invoice' => [
                    'uuid' => $invoice->id,
                    'date' => $date,
                    'total' => $total,
                    'sales_count' => count($sales),
                    'xml_url' => $invoice->download_url . '.xml',
                    'pdf_url' => $invoice->download_url . '.pdf'
                ]
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->logger->error("Error generando factura global: " . $e->getMessage());
            throw new Exception('Error al generar factura global: ' . $e->getMessage());
        }
    }

    /**
     * Generar factura global del mes
     */
    public function generateMonthlyGlobalInvoice($year, $month) {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));
        
        try {
            $this->pdo->beginTransaction();
            
            // Obtener ventas del mes sin factura
            $stmt = $this->pdo->prepare("
                SELECT 
                    o.id as order_id,
                    o.order_number,
                    o.total_amount,
                    o.created_at,
                    oi.product_id,
                    oi.quantity,
                    oi.unit_price,
                    p.sku,
                    p.name as product_name
                FROM orders o
                INNER JOIN order_items oi ON o.id = oi.order_id
                INNER JOIN products p ON oi.product_id = p.id
                WHERE o.created_at >= ? AND o.created_at <= ?
                AND (o.requires_invoice = false OR o.requires_invoice IS NULL)
                AND (o.sat_uuid IS NULL OR o.sat_uuid = '')
                AND o.status NOT IN ('cancelled', 'refunded')
                AND o.payment_status = 'paid'
                ORDER BY o.created_at ASC
            ");
            $stmt->execute([$startDate, $endDate]);
            $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($sales)) {
                $this->pdo->rollBack();
                return ['success' => true, 'message' => 'No hay ventas para facturar en el mes', 'invoice' => null];
            }
            
            // Similar lógica a factura global diaria
            $items = $this->groupSalesForGlobal($sales);
            $companyZipCode = $this->getSetting('company_zip_code');
            
            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += $item['price'] * $item['quantity'];
            }
            
            $taxAmount = $subtotal * 0.16;
            $total = $subtotal + $taxAmount;
            
            $invoice = $this->facturapi->invoices->create([
                'type' => 'I',
                'series' => 'GM', // Serie para facturas globales mensuales
                'customer' => [
                    'legal_name' => self::PUBLIC_NAME,
                    'tax_id' => self::PUBLIC_RFC,
                    'tax_regime' => self::PUBLIC_REGIME,
                    'zip_code' => $companyZipCode
                ],
                'items' => array_map(function($item) {
                    return [
                        'description' => $item['description'],
                        'quantity' => $item['quantity'],
                        'product_key' => '50202300',
                        'unit_key' => 'H87',
                        'unit_name' => 'Pieza',
                        'price' => $item['price'],
                        'sku' => $item['sku'],
                        'tax_included' => false,
                        'taxes' => [['type' => 'IVA', 'rate' => 0.16]]
                    ];
                }, $items),
                'payment_form' => '01',
                'payment_method' => 'PUE',
                'use' => 'G03',
                'currency' => 'MXN',
                'date' => $endDate . 'T23:59:59-06:00',
                'comments' => "Factura Global Mensual {$month}/{$year}"
            ]);
            
            $this->pdo->commit();
            
            $this->logger->info("Factura global mensual generada: {$invoice->id} para {$month}/{$year}");
            
            return [
                'success' => true,
                'invoice' => [
                    'uuid' => $invoice->id,
                    'date' => $endDate,
                    'total' => $total,
                    'sales_count' => count($sales),
                    'xml_url' => $invoice->download_url . '.xml',
                    'pdf_url' => $invoice->download_url . '.pdf'
                ]
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->logger->error("Error generando factura global mensual: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener historial de facturas globales
     */
    public function getGlobalInvoicesHistory($limit = 50) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM global_invoices 
                ORDER BY invoice_date DESC 
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Error obteniendo historial de facturas globales: " . $e->getMessage());
            return [];
        }
    }
}
?>
