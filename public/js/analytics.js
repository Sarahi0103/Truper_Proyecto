/**
 * Script para estadísticas y análisis
 */

/**
 * Generar gráfico de estadísticas
 */
function generateChart(containerId, data, type = 'bar') {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    // Canvas para gráfica (requeriría Chart.js)
    const canvas = document.createElement('canvas');
    canvas.id = containerId + '_canvas';
    container.innerHTML = '';
    container.appendChild(canvas);
    
    // Aquí se integraría Chart.js para gráficas reales
    // Por ahora mostramos tabla simple
    generateStatsTable(containerId, data);
}

/**
 * Generar tabla de estadísticas
 */
function generateStatsTable(containerId, data) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    let html = '<table class="stats-table"><thead><tr>';
    
    // Headers
    Object.keys(data[0] || {}).forEach(key => {
        html += `<th>${key}</th>`;
    });
    html += '</tr></thead><tbody>';
    
    // Rows
    data.forEach(row => {
        html += '<tr>';
        Object.values(row).forEach(value => {
            html += `<td>${value}</td>`;
        });
        html += '</tr>';
    });
    
    html += '</tbody></table>';
    container.innerHTML = html;
}

/**
 * Cargar predicciones
 */
async function loadPredictions() {
    const response = await apiCall('/analytics.php?action=predictions');
    
    if (response && response.predictions) {
        displayPredictions(response.predictions);
    }
}

/**
 * Mostrar predicciones
 */
function displayPredictions(predictions) {
    const container = document.getElementById('predictionsContainer');
    if (!container) return;
    
    let html = '';
    
    predictions.forEach(pred => {
        html += `
            <div class="card">
                <div class="card-body">
                    <h4>${pred.product_name || `Producto #${pred.product_id}`}</h4>
                    <p><strong>Demanda Predicha:</strong> ${pred.predicted_demand} unidades</p>
                    <p><strong>Confianza:</strong> ${pred.confidence}%</p>
                    <p><strong>Temporada:</strong> ${pred.season}</p>
                    <div class="progress" style="height: 20px; background: #f0f0f0; border-radius: 4px;">
                        <div style="width: ${pred.confidence}%; height: 100%; background: #FF7F00; border-radius: 4px;"></div>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

/**
 * Exportar estadísticas
 */
async function exportStats(format = 'csv') {
    const response = await apiCall(`/analytics.php?action=export&format=${format}`);
    
    if (response && response.success) {
        const link = document.createElement('a');
        link.href = response.file_url;
        link.download = response.filename;
        link.click();
        showAlert('Archivo descargado exitosamente', 'success');
    }
}

/**
 * Cargar métricas del dashboard
 */
async function loadDashboardMetrics() {
    const response = await apiCall('/analytics.php?action=dashboard-metrics');
    
    if (response && response.metrics) {
        updateMetricsUI(response.metrics);
    }
}

/**
 * Actualizar interfaz de métricas
 */
function updateMetricsUI(metrics) {
    // Actualizar órdenes mensuales
    const monthlyOrders = document.getElementById('monthlyOrders');  if (monthlyOrders) {
        monthlyOrders.textContent = metrics.monthly_orders || 0;
    }
    
    // Actualizar ingresos
    const monthlyRevenue = document.getElementById('monthlyRevenue');
    if (monthlyRevenue) {
        monthlyRevenue.textContent = formatCurrency(metrics.monthly_revenue || 0);
    }
    
    // Pagos pendientes
    const pendingPayments = document.getElementById('pendingPayments');
    if (pendingPayments) {
        pendingPayments.textContent = metrics.pending_payments || 0;
    }
    
    // Tareas pendientes
    const pendingTasks = document.getElementById('pendingTasks');
    if (pendingTasks) {
        pendingTasks.textContent = metrics.pending_tasks || 0;
    }
    
    // Top productos — diseño con ranking visual
    const topProducts = document.getElementById('topProducts');
    if (!topProducts) return;

    const products = Array.isArray(metrics.top_products) ? metrics.top_products : [];

    if (products.length === 0) {
        topProducts.innerHTML = '<p class="text-muted" style="padding: 0.5rem 0;">Sin ventas registradas este mes.</p>';
        return;
    }

    topProducts.innerHTML = products.map((product, index) => {
        const rank = index + 1;
        const rankColor = rank === 1 ? '#ff8a1f' : rank === 2 ? '#94a3b8' : rank === 3 ? '#b45309' : 'var(--theme-text-muted)';
        const sold = Number(product.total_sold || 0);
        const name = String(product.name || 'Producto');
        return `<div class="top-product-row" style="display:flex;align-items:center;gap:0.75rem;padding:0.6rem 0.5rem;border-bottom:1px solid var(--theme-border);">
            <span class="top-product-rank" style="width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.82rem;background:rgba(255,138,31,0.12);color:${rankColor};flex-shrink:0;">${rank}</span>
            <span style="flex:1;font-size:0.92rem;color:var(--theme-text);">${name}</span>
            <span class="top-product-sold" style="font-size:0.88rem;color:var(--theme-text-muted);white-space:nowrap;">${sold} uds.</span>
        </div>`;
    }).join('');
}

/**
 * Generar reporte de compras por temporada
 */
async function generateSeasonalReport() {
    const response = await apiCall('/analytics.php?action=seasonal-report');
    
    if (response && response.report) {
        displaySeasonalReport(response.report);
    }
}

/**
 * Mostrar reporte de temporada
 */
function displaySeasonalReport(report) {
    const container = document.getElementById('seasonalReport');
    if (!container) return;
    
    let html = '<div class="grid grid-2">';
    
    Object.entries(report).forEach(([season, data]) => {
        html += `
            <div class="card">
                <div class="card-header">${season}</div>
                <div class="card-body">
                    <p><strong>Total Comprado:</strong> ${formatCurrency(data.total_amount)}</p>
                    <p><strong>Cantidad:</strong> ${data.total_quantity} unidades</p>
                    <p><strong>Productos Principales:</strong></p>
                    <ul>
        `;
        
        data.top_products.forEach(product => {
            html += `<li>${product.name}: ${product.quantity}</li>`;
        });
        
        html += `
                    </ul>
                    <p><strong>Factores de Compra:</strong></p>
                    <ul>
        `;
        
        data.factors.forEach(factor => {
            html += `<li>${factor}</li>`;
        });
        
        html += `
                    </ul>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

/**
 * Cargar análisis de clientes
 */
async function loadClientAnalytics() {
    const response = await apiCall('/analytics.php?action=client-analytics');
    
    if (response && response.clients) {
        displayClientAnalytics(response.clients);
    }
}

/**
 * Mostrar análisis de clientes
 */
function displayClientAnalytics(clients) {
    const container = document.getElementById('clientAnalytics');
    if (!container) return;

    // Helper: normalize PostgreSQL boolean (true, false, 't', 'f', 1, 0)
    function isTruthy(val) {
        if (val === true || val === 1 || val === '1') return true;
        if (typeof val === 'string') return val.toLowerCase() === 't' || val.toLowerCase() === 'true';
        return false;
    }
    
    let html = `
        <table>
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Total Comprado</th>
                    <th>Órdenes</th>
                    <th>Puntos Lealtad</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    clients.forEach(client => {
        const active = isTruthy(client.is_active);
        html += `
            <tr>
                <td>${client.name || '—'}</td>
                <td>${formatCurrency(client.total_spent || 0)}</td>
                <td>${client.order_count || 0}</td>
                <td>${client.loyalty_points || 0}</td>
                <td><span class="badge badge-${active ? 'success' : 'danger'}">${active ? 'Activo' : 'Inactivo'}</span></td>
            </tr>
        `;
    });
    
    html += `
            </tbody>
        </table>
    `;
    
    container.innerHTML = html;
}
