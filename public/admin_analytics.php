<?php
/**
 * Dashboard de Analytics para Administrador
 * Métricas de ventas, productos, comportamiento del cliente
 */

require_once '../config/config.php';
require_login();

$userRole = $_SESSION['role'] ?? '';
if (!in_array($userRole, ['admin', 'employee'])) {
    header('Location: index.php');
    exit;
}

$user_name = htmlspecialchars($_SESSION['name'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - Ferretería FOX</title>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <link rel="stylesheet" href="css/styles.css?v=4.1">
    <link rel="stylesheet" href="css/theme.css?v=4.1">
    <link rel="stylesheet" href="css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        body {
            background: #08080a;
            color: #fff;
            margin: 0;
            padding: 0;
        }
        
        .analytics-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }
        
        .analytics-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #222;
        }
        
        .analytics-title {
            font-size: 2rem;
            font-weight: 800;
            margin: 0;
            background: linear-gradient(90deg, #fff, #ff7f00);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .date-filter {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .date-filter select {
            background: #111;
            border: 1px solid #333;
            color: #fff;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .kpi-card {
            background: #111;
            border: 1px solid #222;
            border-radius: 12px;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        
        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #ff7f00, #ffaa00);
        }
        
        .kpi-label {
            color: #888;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }
        
        .kpi-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 0.5rem;
        }
        
        .kpi-change {
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .kpi-change.positive {
            color: #22c55e;
        }
        
        .kpi-change.negative {
            color: #ef4444;
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .chart-card {
            background: #111;
            border: 1px solid #222;
            border-radius: 12px;
            padding: 1.5rem;
        }
        
        .chart-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #fff;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        .table-card {
            background: #111;
            border: 1px solid #222;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .table-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #fff;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            background: #1a1a1a;
            padding: 0.75rem 1rem;
            text-align: left;
            font-size: 0.85rem;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .data-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #222;
        }
        
        .data-table tr:hover {
            background: #151515;
        }
        
        @media (max-width: 900px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <a href="index.php" class="logo"><img src="img/logo_fox.png" alt="Ferretería FOX" style="height: 42px;"></a>
            <nav class="nav-menu">
                <a href="tienda.php">Tienda en Línea</a>
                <a href="admin_online_orders.php">Pedidos Online</a>
                <a href="admin_analytics.php" class="active">Analytics</a>
                <a href="orders.php">Pedidos Local</a>
                <a href="admin_online_billing.php">Facturación SAT</a>
            </nav>
            <div class="user-menu">
                <span><?php echo $user_name; ?></span>
                <button onclick="logout()" class="btn-logout">Cerrar Sesión</button>
            </div>
        </div>
    </header>

    <div class="analytics-container">
        <div class="analytics-header">
            <h1 class="analytics-title">📊 Dashboard de Analytics</h1>
            <div class="date-filter">
                <select id="periodFilter" onchange="loadAnalytics()">
                    <option value="7">Últimos 7 días</option>
                    <option value="30" selected>Últimos 30 días</option>
                    <option value="90">Últimos 90 días</option>
                    <option value="365">Último año</option>
                </select>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-label">Ventas Totales</div>
                <div class="kpi-value" id="totalSales">$0</div>
                <div class="kpi-change" id="salesChange">
                    <span>--</span>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Pedidos</div>
                <div class="kpi-value" id="totalOrders">0</div>
                <div class="kpi-change" id="ordersChange">
                    <span>--</span>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Ticket Promedio</div>
                <div class="kpi-value" id="avgTicket">$0</div>
                <div class="kpi-change" id="ticketChange">
                    <span>--</span>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Tasa de Conversión</div>
                <div class="kpi-value" id="conversionRate">0%</div>
                <div class="kpi-change" id="conversionChange">
                    <span>--</span>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-title">Ventas por Período</div>
                <div class="chart-container">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <div class="chart-title">Pedidos por Estado</div>
                <div class="chart-container">
                    <canvas id="ordersChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Top Products -->
        <div class="table-card">
            <div class="chart-title">🏆 Productos Más Vendidos</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>SKU</th>
                        <th>Unidades</th>
                        <th>Ingresos</th>
                    </tr>
                </thead>
                <tbody id="topProductsBody">
                    <tr><td colspan="4" style="text-align:center; padding:2rem;">Cargando...</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Top Customers -->
        <div class="table-card">
            <div class="chart-title">👥 Mejores Clientes</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Pedidos</th>
                        <th>Total Gastado</th>
                        <th>Última Compra</th>
                    </tr>
                </thead>
                <tbody id="topCustomersBody">
                    <tr><td colspan="4" style="text-align:center; padding:2rem;">Cargando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <script src="js/main.js?v=2.6"></script>
    <script>
        let salesChart = null;
        let ordersChart = null;

        async function loadAnalytics() {
            const period = document.getElementById('periodFilter').value;
            
            try {
                const response = await fetch(`api/admin_analytics_api.php?period=${period}`);
                const data = await response.json();
                
                if (data.success) {
                    updateKPIs(data.kpis);
                    updateSalesChart(data.sales_chart);
                    updateOrdersChart(data.orders_chart);
                    updateTopProducts(data.top_products);
                    updateTopCustomers(data.top_customers);
                }
            } catch (error) {
                console.error('Error loading analytics:', error);
            }
        }

        function updateKPIs(kpis) {
            document.getElementById('totalSales').textContent = '$' + (kpis.total_sales || 0).toLocaleString('es-MX', {minimumFractionDigits: 2});
            document.getElementById('totalOrders').textContent = kpis.total_orders || 0;
            document.getElementById('avgTicket').textContent = '$' + (kpis.avg_ticket || 0).toLocaleString('es-MX', {minimumFractionDigits: 2});
            document.getElementById('conversionRate').textContent = (kpis.conversion_rate || 0).toFixed(1) + '%';
            
            // Cambios
            updateChange('salesChange', kpis.sales_change);
            updateChange('ordersChange', kpis.orders_change);
            updateChange('ticketChange', kpis.ticket_change);
            updateChange('conversionChange', kpis.conversion_change);
        }

        function updateChange(elementId, change) {
            const element = document.getElementById(elementId);
            if (change === null || change === undefined) {
                element.innerHTML = '<span>--</span>';
                return;
            }
            
            const isPositive = change >= 0;
            element.className = 'kpi-change ' + (isPositive ? 'positive' : 'negative');
            element.innerHTML = `
                <span>${isPositive ? '↑' : '↓'}</span>
                <span>${Math.abs(change).toFixed(1)}%</span>
                <span>vs período anterior</span>
            `;
        }

        function updateSalesChart(chartData) {
            const ctx = document.getElementById('salesChart').getContext('2d');
            
            if (salesChart) {
                salesChart.destroy();
            }
            
            salesChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels || [],
                    datasets: [{
                        label: 'Ventas',
                        data: chartData.data || [],
                        borderColor: '#ff7f00',
                        backgroundColor: 'rgba(255, 127, 0, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: '#888'
                            },
                            grid: {
                                color: '#222'
                            }
                        },
                        x: {
                            ticks: {
                                color: '#888'
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        function updateOrdersChart(chartData) {
            const ctx = document.getElementById('ordersChart').getContext('2d');
            
            if (ordersChart) {
                ordersChart.destroy();
            }
            
            ordersChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: chartData.labels || [],
                    datasets: [{
                        data: chartData.data || [],
                        backgroundColor: [
                            '#22c55e',
                            '#3b82f6',
                            '#f59e0b',
                            '#8b5cf6',
                            '#ef4444'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: '#fff'
                            }
                        }
                    }
                }
            });
        }

        function updateTopProducts(products) {
            const tbody = document.getElementById('topProductsBody');
            
            if (!products || products.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:2rem;">No hay datos</td></tr>';
                return;
            }
            
            tbody.innerHTML = products.map(p => `
                <tr>
                    <td>${p.product_name || 'N/A'}</td>
                    <td style="font-family:monospace;">${p.sku || 'N/A'}</td>
                    <td>${p.units_sold || 0}</td>
                    <td>$${(p.revenue || 0).toLocaleString('es-MX', {minimumFractionDigits: 2})}</td>
                </tr>
            `).join('');
        }

        function updateTopCustomers(customers) {
            const tbody = document.getElementById('topCustomersBody');
            
            if (!customers || customers.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:2rem;">No hay datos</td></tr>';
                return;
            }
            
            tbody.innerHTML = customers.map(c => `
                <tr>
                    <td>${c.customer_name || 'N/A'}</td>
                    <td>${c.order_count || 0}</td>
                    <td>$${(c.total_spent || 0).toLocaleString('es-MX', {minimumFractionDigits: 2})}</td>
                    <td>${c.last_order ? new Date(c.last_order).toLocaleDateString('es-MX') : 'N/A'}</td>
                </tr>
            `).join('');
        }

        function logout() {
            window.location.href = 'api/auth.php?action=logout';
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadAnalytics();
        });
    </script>
</body>
</html>
