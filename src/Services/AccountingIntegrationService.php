<?php
/**
 * Accounting Integration Service
 * Integración con sistemas contables, exportación de datos fiscales
 */

class AccountingIntegrationService {
    private $pdo;
    private $logger;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->logger = new AppLogger();
    }
    
    /**
     * Exportar datos para sistema contable
     * 
     * @param string $startDate Fecha inicio
     * @param string $endDate Fecha fin
     * @param string $format Formato (json, csv, xml)
     * @return array Datos exportados
     */
    public function exportAccountingData($startDate, $endDate, $format = 'json') {
        try {
            // Obtener ventas del período
            $stmt = $this->pdo->prepare("
                SELECT 
                    st.folio,
                    st.issued_date,
                    st.customer_name,
                    st.rfc,
                    st.tax_regime_selected,
                    st.cfdi_use,
                    st.subtotal,
                    st.tax_amount,
                    st.total_amount,
                    st.payment_method,
                    st.invoice_required
                FROM sales_tickets st
                WHERE st.issued_date BETWEEN ? AND ?
                AND st.deleted_at IS NULL
                ORDER BY st.issued_date ASC
            ");
            $stmt->execute([$startDate, $endDate]);
            $salesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Obtener compras/egresos del período
            $stmt = $this->pdo->prepare("
                SELECT 
                    id,
                    purchase_date,
                    supplier_name,
                    invoice_number,
                    subtotal,
                    tax_amount,
                    total_amount,
                    tax_regime
                FROM purchases
                WHERE purchase_date BETWEEN ? AND ?
                ORDER BY purchase_date ASC
            ");
            $stmt->execute([$startDate, $endDate]);
            $purchasesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $accountingData = [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ],
                'sales' => $salesData,
                'purchases' => $purchasesData,
                'summary' => [
                    'total_sales' => array_sum(array_column($salesData, 'total_amount')),
                    'total_tax_collected' => array_sum(array_column($salesData, 'tax_amount')),
                    'total_purchases' => array_sum(array_column($purchasesData, 'total_amount')),
                    'total_tax_paid' => array_sum(array_column($purchasesData, 'tax_amount'))
                ]
            ];
            
            if ($format === 'csv') {
                return $this->generateCSV($accountingData);
            } elseif ($format === 'xml') {
                return $this->generateXML($accountingData);
            }
            
            return [
                'success' => true,
                'data' => $accountingData
            ];
        } catch (Exception $e) {
            $this->logger->error("Error exporting accounting data: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al exportar datos contables'
            ];
        }
    }
    
    /**
     * Generar CSV para sistema contable
     * 
     * @param array $data Datos a exportar
     * @return string CSV generado
     */
    private function generateCSV($data) {
        $csv = '';
        
        // Ventas
        $csv .= "VENTAS\n";
        $csv .= "Folio,Fecha,Cliente,RFC,Régimen Fiscal,Uso CFDI,Subtotal,IVA,Total,Método Pago,Requiere Factura\n";
        
        foreach ($data['sales'] as $sale) {
            $csv .= implode(',', [
                $sale['folio'],
                $sale['issued_date'],
                $sale['customer_name'],
                $sale['rfc'],
                $sale['tax_regime_selected'],
                $sale['cfdi_use'],
                $sale['subtotal'],
                $sale['tax_amount'],
                $sale['total_amount'],
                $sale['payment_method'],
                $sale['invoice_required'] ? 'Sí' : 'No'
            ]) . "\n";
        }
        
        // Compras
        $csv .= "\nCOMPRAS\n";
        $csv .= "ID,Fecha,Proveedor,Número Factura,Subtotal,IVA,Total,Régimen Fiscal\n";
        
        foreach ($data['purchases'] as $purchase) {
            $csv .= implode(',', [
                $purchase['id'],
                $purchase['purchase_date'],
                $purchase['supplier_name'],
                $purchase['invoice_number'],
                $purchase['subtotal'],
                $purchase['tax_amount'],
                $purchase['total_amount'],
                $purchase['tax_regime']
            ]) . "\n";
        }
        
        return $csv;
    }
    
    /**
     * Generar XML para sistema contable
     * 
     * @param array $data Datos a exportar
     * @return string XML generado
     */
    private function generateXML($data) {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><accounting_data></accounting_data>');
        
        $period = $xml->addChild('period');
        $period->addChild('start_date', $data['period']['start_date']);
        $period->addChild('end_date', $data['period']['end_date']);
        
        $sales = $xml->addChild('sales');
        foreach ($data['sales'] as $sale) {
            $saleXml = $sales->addChild('sale');
            $saleXml->addChild('folio', $sale['folio']);
            $saleXml->addChild('date', $sale['issued_date']);
            $saleXml->addChild('customer', $sale['customer_name']);
            $saleXml->addChild('rfc', $sale['rfc']);
            $saleXml->addChild('subtotal', $sale['subtotal']);
            $saleXml->addChild('tax_amount', $sale['tax_amount']);
            $saleXml->addChild('total', $sale['total_amount']);
        }
        
        $purchases = $xml->addChild('purchases');
        foreach ($data['purchases'] as $purchase) {
            $purchaseXml = $purchases->addChild('purchase');
            $purchaseXml->addChild('id', $purchase['id']);
            $purchaseXml->addChild('date', $purchase['purchase_date']);
            $purchaseXml->addChild('supplier', $purchase['supplier_name']);
            $purchaseXml->addChild('invoice_number', $purchase['invoice_number']);
            $purchaseXml->addChild('total', $purchase['total_amount']);
        }
        
        return $xml->asXML();
    }
    
    /**
     * Conciliar pagos con sistema contable
     * 
     * @param array $paymentData Datos de pagos a conciliar
     * @return array Resultado de la conciliación
     */
    public function reconcilePayments($paymentData) {
        try {
            $reconciled = 0;
            $errors = [];
            
            foreach ($paymentData as $payment) {
                $stmt = $this->pdo->prepare("
                    UPDATE payments
                    SET reconciled = true,
                        reconciled_at = CURRENT_TIMESTAMP,
                        accounting_reference = ?
                    WHERE id = ?
                ");
                
                $result = $stmt->execute([$payment['accounting_reference'], $payment['payment_id']]);
                
                if ($result) {
                    $reconciled++;
                } else {
                    $errors[] = "Error conciliando pago {$payment['payment_id']}";
                }
            }
            
            $this->logger->info("Reconciled {$reconciled} payments");
            
            return [
                'success' => true,
                'reconciled' => $reconciled,
                'errors' => $errors
            ];
        } catch (Exception $e) {
            $this->logger->error("Error reconciling payments: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al conciliar pagos'
            ];
        }
    }
    
    /**
     * Generar reporte fiscal mensual
     * 
     * @param int $year Año
     * @param int $month Mes
     * @return array Reporte fiscal
     */
    public function generateMonthlyFiscalReport($year, $month) {
        try {
            $startDate = date('Y-m-01', mktime(0, 0, 0, $month, 1, $year));
            $endDate = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));
            
            // Ventas gravadas
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_invoices,
                    SUM(subtotal) as total_subtotal,
                    SUM(tax_amount) as total_tax,
                    SUM(total_amount) as total
                FROM sales_tickets
                WHERE issued_date BETWEEN ? AND ?
                AND invoice_required = true
                AND deleted_at IS NULL
            ");
            $stmt->execute([$startDate, $endDate]);
            $taxedSales = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Ventas exentas
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_invoices,
                    SUM(total_amount) as total
                FROM sales_tickets
                WHERE issued_date BETWEEN ? AND ?
                AND invoice_required = false
                AND deleted_at IS NULL
            ");
            $stmt->execute([$startDate, $endDate]);
            $exemptSales = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'period' => [
                    'year' => $year,
                    'month' => $month,
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ],
                'taxed_sales' => $taxedSales,
                'exempt_sales' => $exemptSales,
                'total_tax_collected' => $taxedSales['total_tax'] ?? 0
            ];
        } catch (Exception $e) {
            $this->logger->error("Error generating fiscal report: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al generar reporte fiscal'
            ];
        }
    }
}
