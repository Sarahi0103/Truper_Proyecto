<?php
/**
 * Controlador de Estadísticas y Predicción
 */

class AnalyticsController {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function getPurchaseStatistics($product_id = null, $year = null) {
        try {
            $year = $year ?? date('Y');
            
            $query = "
                SELECT 
                    ps.*,
                    p.name,
                    p.sku,
                    ROUND(AVG(ps.total_quantity) OVER (PARTITION BY ps.product_id), 2) as avg_quantity
                FROM purchase_statistics ps
                JOIN products p ON ps.product_id = p.id
                WHERE ps.year = ?
            ";
            
            $params = [$year];
            
            if ($product_id) {
                $query .= " AND ps.product_id = ?";
                $params[] = $product_id;
            }
            
            $query .= " ORDER BY ps.month, p.name";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function generatePredictions() {
        try {
            $historical = $this->getHistoricalDataForPrediction();
            if (empty($historical)) {
                return [];
            }
            
            // Agrupar por producto
            $product_data = [];
            foreach ($historical as $record) {
                if (!isset($product_data[$record['product_id']])) {
                    $product_data[$record['product_id']] = [];
                }
                $product_data[$record['product_id']][] = $record;
            }
            
            // Generar predicciones
            $predictions = [];
            foreach ($product_data as $product_id => $data) {
                $prediction = $this->calculatePrediction($product_id, $data);
                if ($prediction) {
                    $predictions[] = $prediction;
                }
            }
            
            return $predictions;
        } catch (Exception $e) {
            error_log("Error generando predicciones: " . $e->getMessage());
            return [];
        }
    }

    private function getHistoricalDataForPrediction() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    product_id,
                    p.name,
                    p.sku,
                    month,
                    total_quantity,
                    season,
                    year
                FROM purchase_statistics
                JOIN products p ON purchase_statistics.product_id = p.id
                WHERE year >= ? 
                ORDER BY product_id, year DESC, month DESC
                LIMIT 240
            ");
            $stmt->execute([date('Y') - 2]);
            $rows = $stmt->fetchAll();
            if (!empty($rows)) {
                return $rows;
            }
        } catch (Exception $e) {
            // Continua al fallback
        }

        try {
            $fallbackStmt = $this->pdo->prepare("
                SELECT
                    oi.product_id,
                    p.name,
                    p.sku,
                    EXTRACT(MONTH FROM o.created_at)::int AS month,
                    SUM(oi.quantity)::int AS total_quantity,
                    EXTRACT(YEAR FROM o.created_at)::int AS year
                FROM order_items oi
                JOIN orders o ON o.id = oi.order_id
                JOIN products p ON p.id = oi.product_id
                WHERE o.created_at >= NOW() - INTERVAL '24 months'
                GROUP BY oi.product_id, p.name, p.sku, EXTRACT(YEAR FROM o.created_at), EXTRACT(MONTH FROM o.created_at)
                ORDER BY oi.product_id, year DESC, month DESC
                LIMIT 240
            ");
            $fallbackStmt->execute();
            $fallbackRows = $fallbackStmt->fetchAll();

            foreach ($fallbackRows as &$row) {
                $row['season'] = $this->seasonByMonth((int)$row['month']);
            }
            unset($row);

            return $fallbackRows;
        } catch (Exception $e) {
            return [];
        }
    }
    
    private function calculatePrediction($product_id, $historical_data) {
        if (empty($historical_data)) {
            return null;
        }

        $product_name = $historical_data[0]['name'] ?? null;
        $product_sku = $historical_data[0]['sku'] ?? null;
        
        // Calcular promedio de demanda base
        $total = array_sum(array_column($historical_data, 'total_quantity'));
        $count = count($historical_data);
        $average = $total / $count;
        
        // 1. Factor de Estacionalidad Actual
        $current_season = $this->getSeason();
        $season_factor = 1.0;
        
        $season_data = array_filter($historical_data, function($d) use ($current_season) {
            return ($d['season'] ?? '') === $current_season;
        });
        
        if (!empty($season_data)) {
            $season_avg = array_sum(array_column($season_data, 'total_quantity')) / count($season_data);
            $season_factor = $season_avg / $average;
        }
        
        // 2. Factor de Tendencia (últimos meses vs promedio)
        $trend_factor = 1.0;
        if ($count >= 3) {
            $recent = array_slice($historical_data, 0, 3);
            $recent_avg = array_sum(array_column($recent, 'total_quantity')) / 3;
            $trend_factor = $recent_avg / $average;
        }

        // 3. Ajuste por Eventos Especiales o Clima (si existen en el historial reciente)
        $external_adjustment = 1.0;
        $factors_desc = [];
        
        foreach ($historical_data as $h) {
            if (!empty($h['weather_condition'])) {
                $external_adjustment *= 1.05; // Leve incremento por correlación climática detectada
                $factors_desc[] = "Correlación climática (" . $h['weather_condition'] . ")";
                break;
            }
            if (!empty($h['special_event'])) {
                $external_adjustment *= 1.1; // Ajuste por eventos especiales históricos
                $factors_desc[] = "Impacto de evento especial (" . $h['special_event'] . ")";
                break;
            }
        }

        // Predicción Final
        $predicted_demand = round($average * $season_factor * $trend_factor * $external_adjustment);
        
        // Confianza: escala por volumen de datos y estabilidad
        $confidence = min(99, 45 + ($count * 4));
        if ($trend_factor > 1.5 || $trend_factor < 0.5) $confidence -= 10; // Menor confianza en cambios bruscos

        // Guardar en log de IA
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO ai_predictions 
                (product_id, prediction_date, predicted_demand, confidence_score, season, factors)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $factors_json = json_encode([
                'base_avg' => round($average, 2),
                'season_f' => round($season_factor, 2),
                'trend_f' => round($trend_factor, 2),
                'ext_adj' => round($external_adjustment, 2),
                'data_points' => $count,
                'insights' => $factors_desc
            ]);
            
            $stmt->execute([
                $product_id,
                date('Y-m-d'),
                $predicted_demand,
                $confidence,
                $current_season,
                $factors_json
            ]);
        } catch (Exception $e) {
            error_log("Error guardando ai_prediction: " . $e->getMessage());
        }
        
        return [
            'product_id' => $product_id,
            'product_name' => $product_name,
            'sku' => $product_sku,
            'predicted_demand' => $predicted_demand,
            'confidence' => $confidence,
            'season' => $current_season,
            'insights' => $factors_desc,
            'factors' => [
                'base_average' => round($average, 2),
                'season_factor' => round($season_factor, 2),
                'trend_factor' => round($trend_factor, 2),
                'external_adjustment' => round($external_adjustment, 2),
                'data_points' => $count,
            ]
        ];
    }
    
    public function getDashboardMetrics() {
        $monthly_orders  = 0;
        $monthly_revenue = 0;
        $pending_payments = 0;
        $pending_tasks   = 0;
        $top_products    = [];

        try {
            // Órdenes del mes
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as total, COALESCE(SUM(total_amount),0) as revenue
                FROM orders
                WHERE EXTRACT(MONTH FROM created_at) = EXTRACT(MONTH FROM NOW())
                AND EXTRACT(YEAR FROM created_at) = EXTRACT(YEAR FROM NOW())
            ");
            $stmt->execute();
            $monthly_stats = $stmt->fetch();
            $monthly_orders  = (int)($monthly_stats['total'] ?? 0);
            $monthly_revenue = (float)($monthly_stats['revenue'] ?? 0);
        } catch (Exception $e) {
            error_log('getDashboardMetrics orders: ' . $e->getMessage());
        }

        try {
            // Órdenes pendientes de pago
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as pending_payment
                FROM orders
                WHERE payment_status IN ('pending', 'partial')
            ");
            $stmt->execute();
            $payment_stats   = $stmt->fetch();
            $pending_payments = (int)($payment_stats['pending_payment'] ?? 0);
        } catch (Exception $e) {
            error_log('getDashboardMetrics payments: ' . $e->getMessage());
        }

        try {
            // Tareas pendientes
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as pending_tasks
                FROM tasks
                WHERE status IN ('pending', 'in_progress')
            ");
            $stmt->execute();
            $task_stats    = $stmt->fetch();
            $pending_tasks = (int)($task_stats['pending_tasks'] ?? 0);
        } catch (Exception $e) {
            error_log('getDashboardMetrics tasks: ' . $e->getMessage());
        }

        try {
            // Top productos vendidos este mes
            $stmt = $this->pdo->prepare("
                SELECT p.name, SUM(oi.quantity) as total_sold
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE EXTRACT(MONTH FROM oi.created_at) = EXTRACT(MONTH FROM NOW())
                GROUP BY p.id, p.name
                ORDER BY total_sold DESC
                LIMIT 10
            ");
            $stmt->execute();
            $top_products = $stmt->fetchAll();
        } catch (Exception $e) {
            error_log('getDashboardMetrics top_products: ' . $e->getMessage());
            $top_products = [];
        }

        return [
            'monthly_orders'   => $monthly_orders,
            'monthly_revenue'  => $monthly_revenue,
            'pending_payments' => $pending_payments,
            'pending_tasks'    => $pending_tasks,
            'top_products'     => $top_products
        ];
    }
    
    private function getSeason() {
        $month = date('m');
        if ($month >= 12 || $month <= 2) return 'Invierno';
        if ($month >= 3 && $month <= 5) return 'Primavera';
        if ($month >= 6 && $month <= 8) return 'Verano';
        return 'Otoño';
    }

    private function seasonByMonth($month) {
        if ($month >= 12 || $month <= 2) return 'Invierno';
        if ($month >= 3 && $month <= 5) return 'Primavera';
        if ($month >= 6 && $month <= 8) return 'Verano';
        return 'Otoño';
    }

    /**
     * Obtener historial de tickets del mes especificado (para Estadísticas)
     * Incluye folio, montos y evidencia de transacciones
     */
    public function getTicketsHistory($year = null, $month = null) {
        try {
            $year = $year ?? date('Y');
            $month = $month ?? date('m');
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    st.id,
                    st.folio,
                    st.ticket_type,
                    st.total_amount,
                    st.payment_status,
                    st.issued_date,
                    st.verified_date,
                    u.first_name || CASE WHEN u.last_name IS NOT NULL AND u.last_name <> '' THEN ' ' || u.last_name ELSE '' END as customer_name,
                    u.email,
                    (SELECT COUNT(*) FROM ticket_items WHERE ticket_id = st.id) as item_count,
                    st.description
                FROM sales_tickets st
                LEFT JOIN users u ON st.user_id = u.id
                WHERE EXTRACT(YEAR FROM st.issued_date) = ?
                AND EXTRACT(MONTH FROM st.issued_date) = ?
                ORDER BY st.issued_date DESC, st.folio DESC
            ");
            
            $stmt->execute([$year, $month]);
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calcular estadísticas del mes para tickets de venta
            $total_sales = 0;
            $total_count = 0;
            $return_count = 0;
            $payment_pending = 0;
            
            foreach ($tickets as $ticket) {
                $total_sales += (float)($ticket['total_amount'] ?? 0);
                $total_count++;
                
                if (($ticket['ticket_type'] ?? '') === 'return') {
                    $return_count++;
                }
                if (($ticket['payment_status'] ?? '') !== 'completed') {
                    $payment_pending++;
                }
            }

            // Obtener órdenes / tickets de proveedor para el mismo periodo
            $supplier_tickets = [];
            try {
                $supplierStmt = $this->pdo->prepare("\n                    SELECT id, folio, supplier_name, total_estimated, status, created_at, items_json\n                    FROM supplier_orders\n                    WHERE EXTRACT(YEAR FROM created_at) = ?\n                    AND EXTRACT(MONTH FROM created_at) = ?\n                    ORDER BY created_at DESC\n                ");
                $supplierStmt->execute([$year, $month]);
                $supplierRows = $supplierStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                foreach ($supplierRows as $s) {
                    $items = [];
                    if (!empty($s['items_json'])) {
                        $decoded = json_decode($s['items_json'], true);
                        if (is_array($decoded)) $items = $decoded;
                    }

                    $supplier_tickets[] = [
                        'id' => $s['id'],
                        'folio' => $s['folio'],
                        'ticket_type' => 'supplier',
                        'total_amount' => (float)($s['total_estimated'] ?? 0),
                        'payment_status' => $s['status'] ?? '',
                        'issued_date' => $s['created_at'],
                        'verified_date' => null,
                        'customer_name' => $s['supplier_name'],
                        'email' => '',
                        'item_count' => count($items),
                        'description' => ''
                    ];
                }
            } catch (Exception $e) {
                // No interrumpir si falla la consulta de órdenes proveedor
                error_log('Error cargando supplier_orders: ' . $e->getMessage());
            }

            // Estadísticas para proveedores
            $supplier_count = 0;
            $supplier_total = 0;
            foreach ($supplier_tickets as $st) {
                $supplier_count++;
                $supplier_total += (float)($st['total_amount'] ?? 0);
            }
            
            return [
                'tickets' => $tickets,
                'supplier_tickets' => $supplier_tickets,
                'stats' => [
                    'total_tickets' => $total_count,
                    'total_sales' => $total_sales,
                    'return_count' => $return_count,
                    'avg_ticket' => $total_count > 0 ? $total_sales / $total_count : 0,
                    'payment_pending' => $payment_pending,
                    'supplier_count' => $supplier_count,
                    'supplier_total' => $supplier_total
                ]
            ];
        } catch (Exception $e) {
            error_log('Error en getTicketsHistory: ' . $e->getMessage());
            return ['tickets' => [], 'stats' => []];
        }
    }

    /**
     * Archivar tickets del mes anterior (para cron job)
     * Se ejecuta al inicio de cada mes para archivar el mes anterior
     */
    public function archiveTicketsOfMonth($year = null, $month = null) {
        try {
            // Si no se especifica, archivar el mes anterior
            if ($year === null || $month === null) {
                $date = new DateTime();
                $date->modify('-1 month');
                $year = $date->format('Y');
                $month = $date->format('m');
            }
            
            // 1. Obtener tickets a archivar
            $getStmt = $this->pdo->prepare("
                SELECT * FROM sales_tickets
                WHERE EXTRACT(YEAR FROM issued_date) = ?
                AND EXTRACT(MONTH FROM issued_date) = ?
                AND archived_at IS NULL
            ");
            $getStmt->execute([$year, $month]);
            $tickets_to_archive = $getStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($tickets_to_archive)) {
                return ['success' => true, 'archived_count' => 0, 'message' => 'No hay tickets para archivar'];
            }
            
            // 2. Guardar en tabla de archivo (sales_tickets_archived)
            $archiveStmt = $this->pdo->prepare("
                INSERT INTO sales_tickets_archived 
                (original_ticket_id, folio, ticket_data, archived_date, archive_reason, total_amount, ticket_type)
                VALUES (?, ?, ?, NOW(), 'Monthly Archive', ?, ?)
            ");
            
            $archived_count = 0;
            foreach ($tickets_to_archive as $ticket) {
                $ticket_data = json_encode($ticket);
                $archiveStmt->execute([
                    $ticket['id'],
                    $ticket['folio'],
                    $ticket_data,
                    $ticket['total_amount'],
                    $ticket['ticket_type']
                ]);
                $archived_count++;
            }
            
            // 3. Marcar como archivados en tabla original
            $markStmt = $this->pdo->prepare("
                UPDATE sales_tickets 
                SET archived_at = NOW()
                WHERE EXTRACT(YEAR FROM issued_date) = ?
                AND EXTRACT(MONTH FROM issued_date) = ?
                AND archived_at IS NULL
            ");
            $markStmt->execute([$year, $month]);
            
            // 4. Guardar estadísticas mensuales consolidadas
            $statsStmt = $this->pdo->prepare("
                INSERT INTO sales_monthly_statistics 
                (year_month, total_sales, total_returns, total_adjustments, ticket_count, return_count, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ON CONFLICT (year_month) DO UPDATE SET
                    total_sales = EXCLUDED.total_sales,
                    total_returns = EXCLUDED.total_returns,
                    total_adjustments = EXCLUDED.total_adjustments,
                    ticket_count = EXCLUDED.ticket_count,
                    return_count = EXCLUDED.return_count,
                    updated_at = NOW()
            ");
            
            $total_sales = 0;
            $total_returns = 0;
            $total_adjustments = 0;
            $return_count = 0;
            
            foreach ($tickets_to_archive as $ticket) {
                $amount = (float)($ticket['total_amount'] ?? 0);
                $total_sales += $amount;
                
                if ($ticket['ticket_type'] === 'return') {
                    $total_returns += $amount;
                    $return_count++;
                } elseif ($ticket['ticket_type'] === 'adjustment') {
                    $total_adjustments += $amount;
                }
            }
            
            $year_month = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT);
            $statsStmt->execute([
                $year_month,
                $total_sales,
                $total_returns,
                $total_adjustments,
                $archived_count,
                $return_count
            ]);
            
            return [
                'success' => true,
                'archived_count' => $archived_count,
                'year' => $year,
                'month' => $month,
                'total_sales' => $total_sales,
                'message' => "Se archivaron $archived_count tickets de $year_month"
            ];
        } catch (Exception $e) {
            error_log('Error en archiveTicketsOfMonth: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error archivando tickets: ' . $e->getMessage()];
        }
    }

    /**
     * Obtener años disponibles en tickets y archivo para filtros dinámicos
     */
    public function getTicketAvailableYears() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT year_value
                FROM (
                    SELECT EXTRACT(YEAR FROM issued_date)::int AS year_value
                    FROM sales_tickets
                    UNION
                    SELECT EXTRACT(YEAR FROM archived_date)::int AS year_value
                    FROM sales_tickets_archived
                ) AS year_sources
                WHERE year_value IS NOT NULL
                ORDER BY year_value DESC
            ");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $years = [];
            foreach ($rows as $row) {
                $yearValue = (int)($row['year_value'] ?? 0);
                if ($yearValue > 0) {
                    $years[] = $yearValue;
                }
            }

            if (empty($years)) {
                $years[] = (int)date('Y');
            }

            return array_values(array_unique($years));
        } catch (Exception $e) {
            error_log('Error en getTicketAvailableYears: ' . $e->getMessage());
            return [(int)date('Y')];
        }
    }
}
?>
