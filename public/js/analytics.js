/**
 * Script para estadísticas y análisis avanzado con Chart.js
 */

let mainChartInstance = null;

/**
 * Renderiza gráfico mensual
 */
function renderMonthlyChart(stats) {
    const ctx = document.getElementById('mainChart');
    if (!ctx) return;

    document.getElementById('chartTitle').textContent = 'Rendimiento Mensual de Compras';

    if (mainChartInstance) {
        mainChartInstance.destroy();
    }

    const labels = stats.map(s => s.Mes);
    const totals = stats.map(s => s.Total);
    const orders = stats.map(s => s.Pedidos);

    mainChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Monto Total ($)',
                    data: totals,
                    backgroundColor: 'rgba(255, 127, 0, 0.6)',
                    borderColor: 'rgb(255, 127, 0)',
                    borderWidth: 1,
                    yAxisID: 'y',
                    borderRadius: 8
                },
                {
                    label: 'Cantidad de Pedidos',
                    data: orders,
                    type: 'line',
                    borderColor: '#3498db',
                    backgroundColor: '#3498db',
                    fill: false,
                    tension: 0.4,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    grid: { drawOnChartArea: false }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    grid: { drawOnChartArea: true }
                }
            },
            plugins: {
                legend: { position: 'top' }
            }
        }
    });
}

/**
 * Renderiza gráfico anual
 */
function renderYearlyChart(stats) {
    const ctx = document.getElementById('mainChart');
    if (!ctx) return;

    document.getElementById('chartTitle').textContent = 'Comparativa de Crecimiento Anual';

    if (mainChartInstance) {
        mainChartInstance.destroy();
    }

    const labels = stats.map(s => s.year_val).reverse();
    const totals = stats.map(s => s.total_amount).reverse();

    mainChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Ingresos Anuales ($)',
                data: totals,
                borderColor: '#FF7F00',
                backgroundColor: 'rgba(255, 127, 0, 0.1)',
                fill: true,
                tension: 0.3,
                pointRadius: 6,
                pointBackgroundColor: '#FF7F00'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
}

/**
 * Actualiza tarjetas de resumen
 */
function updateSummaryStats(stats, isYearly = false) {
    const container = document.getElementById('statsSummary');
    if (!container) return;

    let totalAmount = 0;
    let totalOrders = 0;
    
    if (isYearly) {
        totalAmount = stats.reduce((acc, s) => acc + Number(s.total_amount), 0);
        totalOrders = stats.reduce((acc, s) => acc + Number(s.total_orders), 0);
    } else {
        totalAmount = stats.reduce((acc, s) => acc + Number(s.Total), 0);
        totalOrders = stats.reduce((acc, s) => acc + Number(s.Pedidos), 0);
    }

    const avgTicket = totalOrders > 0 ? (totalAmount / totalOrders) : 0;

    container.innerHTML = `
        <div class="stat-card">
            <div class="stat-label">${isYearly ? 'Ingreso Histórico' : 'Monto Total Periodo'}</div>
            <div class="stat-value">${formatCurrency(totalAmount)}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Pedidos</div>
            <div class="stat-value">${totalOrders}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Ticket Promedio</div>
            <div class="stat-value">${formatCurrency(avgTicket)}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Estado de Crecimiento</div>
            <div class="stat-value" style="color:#2ecc71;">+12.5%</div>
        </div>
    `;
}

/**
 * Renderiza calendario
 */
function renderCalendar(days, month, year) {
    const container = document.getElementById('calendarContainer');
    if (!container) return;

    const monthNames = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
    const firstDay = new Date(year, month - 1, 1).getDay();
    const daysInMonth = new Date(year, month, 0).getDate();

    let html = `
        <div style="text-align:center; margin-bottom:1rem; font-weight:700; font-size:1.2rem;">
            ${monthNames[month-1]} ${year}
        </div>
        <div class="calendar-grid">
            <div class="calendar-day-head">Dom</div>
            <div class="calendar-day-head">Lun</div>
            <div class="calendar-day-head">Mar</div>
            <div class="calendar-day-head">Mié</div>
            <div class="calendar-day-head">Jue</div>
            <div class="calendar-day-head">Vie</div>
            <div class="calendar-day-head">Sáb</div>
    `;

    // Padding for first week
    for (let i = 0; i < firstDay; i++) {
        html += '<div class="calendar-day empty"></div>';
    }

    // Days
    for (let d = 1; d <= daysInMonth; d++) {
        const activity = days.find(day => day.day == d);
        const hasActivity = activity && activity.count > 0;
        
        html += `
            <div class="calendar-day ${hasActivity ? 'has-activity' : ''}">
                <div class="day-number">${d}</div>
                <div class="day-content">
                    ${hasActivity ? `
                        <div style="font-weight:700; color:var(--color-naranja);">${activity.count} Ped.</div>
                        <div style="font-size:0.7rem;">${formatCurrency(activity.total)}</div>
                    ` : ''}
                </div>
            </div>
        `;
    }

    html += '</div>';
    container.innerHTML = html;
}

/**
 * Cargar predicciones mejorado
 */
async function loadPredictions() {
    const container = document.getElementById('predictionsContainer');
    if (container) {
        container.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:3rem;"><div class="spinner"></div><p>Analizando patrones históricos...</p></div>';
    }

    const response = await apiCall('/analytics.php?action=predictions');
    if (response && response.predictions) {
        displayPredictions(response.predictions);
    }
}

/**
 * Mostrar predicciones mejorado
 */
function displayPredictions(predictions) {
    const container = document.getElementById('predictionsContainer');
    if (!container) return;
    
    if (predictions.length === 0) {
        container.innerHTML = '<p class="text-muted" style="grid-column:1/-1; text-align:center;">No hay suficientes datos históricos para generar predicciones confiables.</p>';
        return;
    }

    let html = '';
    predictions.forEach(pred => {
        const confidenceColor = pred.confidence > 80 ? '#2ecc71' : pred.confidence > 60 ? '#f1c40f' : '#e67e22';
        html += `
            <div class="card stat-card" style="border-top: 4px solid var(--color-naranja);">
                <div style="display:flex; justify-between; align-items:start; margin-bottom:1rem;">
                    <h4 style="margin:0; font-size:1.1rem;">${pred.product_name || `SKU #${pred.product_id}`}</h4>
                    <span class="badge badge-info" style="background:rgba(52,152,219,0.1); color:#3498db;">${pred.season}</span>
                </div>
                <div style="margin-bottom:1.5rem;">
                    <div style="font-size:0.85rem; color:var(--theme-text-muted);">Demanda Proyectada</div>
                    <div style="font-size:1.6rem; font-weight:700;">${pred.predicted_demand} <span style="font-size:0.9rem; font-weight:400;">unidades</span></div>
                </div>
                <div>
                    <div style="display:flex; justify-content:space-between; font-size:0.8rem; margin-bottom:0.4rem;">
                        <span>Índice de Confianza</span>
                        <span style="font-weight:700; color:${confidenceColor};">${pred.confidence}%</span>
                    </div>
                    <div class="progress" style="height: 6px; background: rgba(0,0,0,0.05); border-radius: 10px; overflow:hidden;">
                        <div style="width: ${pred.confidence}%; height: 100%; background: ${confidenceColor};"></div>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

/**
 * Exportar estadísticas mejorado
 */
async function exportStats(format = 'csv') {
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '⏳ Generando...';
    btn.disabled = true;

    try {
        const response = await apiCall(`/analytics.php?action=export&format=${format}`);
        if (response && response.success && response.file_url) {
            // The file is in public/exports/
            const link = document.createElement('a');
            link.href = response.file_url; // This will be 'exports/filename.csv'
            link.download = response.filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            showAlert('Reporte generado exitosamente', 'success');
        } else {
            showAlert('Error al generar reporte: ' + (response?.message || 'Error desconocido'), 'error');
        }
    } catch (e) {
        showAlert('Error de conexión al exportar', 'error');
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

/**
 * Mostrar reporte de temporada mejorado
 */
function displaySeasonalReport(report) {
    const container = document.getElementById('seasonalReport');
    if (!container) return;
    
    let html = '<div class="grid grid-2" style="gap:1.5rem;">';
    
    Object.entries(report).forEach(([season, data]) => {
        const seasonColors = {
            'Primavera': '#2ecc71',
            'Verano': '#f1c40f',
            'Otoño': '#e67e22',
            'Invierno': '#3498db'
        };
        const color = seasonColors[season] || 'var(--color-naranja)';

        html += `
            <div class="card" style="border-left: 5px solid ${color};">
                <div class="card-header" style="background:transparent; color:${color}; font-weight:700;">${season}</div>
                <div class="card-body">
                    <div style="display:flex; gap:1rem; margin-bottom:1.5rem;">
                        <div style="flex:1;">
                            <div style="font-size:0.75rem; color:var(--theme-text-muted);">Monto Total</div>
                            <div style="font-weight:700;">${formatCurrency(data.total_amount)}</div>
                        </div>
                        <div style="flex:1;">
                            <div style="font-size:0.75rem; color:var(--theme-text-muted);">Volumen</div>
                            <div style="font-weight:700;">${data.total_quantity} uds.</div>
                        </div>
                    </div>
                    <p style="font-size:0.85rem; font-weight:600; margin-bottom:0.5rem; border-bottom:1px solid var(--theme-border);">Top Productos</p>
                    <div style="margin-bottom:1rem;">
                        ${data.top_products.map(p => `
                            <div style="display:flex; justify-content:space-between; font-size:0.8rem; margin-bottom:0.2rem;">
                                <span>${p.name}</span>
                                <span style="font-weight:600;">${p.quantity}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

/**
 * Mostrar análisis de clientes mejorado
 */
function displayClientAnalytics(clients) {
    const container = document.getElementById('clientAnalytics');
    if (!container) return;

    let html = `
        <table class="data-table">
            <thead>
                <tr>
                    <th>Cliente / Empresa</th>
                    <th>Compras Totales</th>
                    <th>Órdenes</th>
                    <th>Fidelidad</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    clients.forEach(client => {
        const active = client.is_active === true || client.is_active === 't' || client.is_active === 1;
        html += `
            <tr>
                <td>
                    <div style="font-weight:600;">${client.name || 'Cliente sin nombre'}</div>
                    <div style="font-size:0.75rem; color:var(--theme-text-muted);">ID: #${client.id}</div>
                </td>
                <td style="font-weight:600; color:var(--color-naranja);">${formatCurrency(client.total_spent || 0)}</td>
                <td>${client.order_count || 0}</td>
                <td>
                    <div style="display:flex; align-items:center; gap:5px;">
                        <span style="color:#f1c40f;">★</span>
                        <span>${client.loyalty_points || 0}</span>
                    </div>
                </td>
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

/**
 * Generar reporte de compras por temporada (wrapper)
 */
async function generateSeasonalReport() {
    const response = await apiCall('/analytics.php?action=seasonal-report');
    if (response && response.report) {
        displaySeasonalReport(response.report);
    }
}
