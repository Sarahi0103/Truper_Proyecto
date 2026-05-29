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

    document.getElementById('chartTitle').textContent = 'Resumen mensual de actividad';

    if (mainChartInstance) mainChartInstance.destroy();

    const monthLabels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    const labels = stats.map((s) => s.Mes || monthLabels[(Number(s.month_num) || 1) - 1] || 'Mes');
    const totals = stats.map((s) => Number(s.Total ?? s.total_amount ?? 0));
    const orders = stats.map((s) => Number(s.Pedidos ?? s.total_orders ?? 0));

    const chartDefaults = {
        color: '#888',
        font: { family: 'Inter, sans-serif', size: 12 }
    };
    Chart.defaults.color = chartDefaults.color;
    Chart.defaults.font  = chartDefaults.font;

    mainChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Monto Total ($)',
                    data: totals,
                    backgroundColor: 'rgba(255,127,0,0.65)',
                    borderColor: '#ff7f00',
                    borderWidth: 0,
                    yAxisID: 'y',
                    borderRadius: 8,
                    borderSkipped: false
                },
                {
                    label: 'Pedidos',
                    data: orders,
                    type: 'line',
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52,152,219,0.08)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#3498db',
                    pointBorderColor: '#0d0d0d',
                    pointBorderWidth: 2,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    position: 'top',
                    labels: { boxWidth: 12, padding: 16, color: '#888', font: { family: 'Inter' } }
                },
                tooltip: {
                    backgroundColor: '#111',
                    borderColor: '#2a2a2a',
                    borderWidth: 1,
                    titleColor: '#fff',
                    bodyColor: '#aaa',
                    padding: 12,
                    callbacks: {
                        label: ctx => {
                            const label = ctx.dataset.label || '';
                            const val   = ctx.parsed.y;
                            return label.includes('Monto')
                                ? ` ${label}: $${val.toLocaleString('es-MX', { minimumFractionDigits: 2 })}`
                                : ` ${label}: ${val}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(255,255,255,0.04)' },
                    ticks: { color: '#666', font: { family: 'Inter', size: 11 } }
                },
                y: {
                    type: 'linear',
                    position: 'left',
                    grid: { color: 'rgba(255,255,255,0.05)', drawOnChartArea: true },
                    ticks: { color: '#666', font: { family: 'Inter', size: 11 } }
                },
                y1: {
                    type: 'linear',
                    position: 'right',
                    grid: { drawOnChartArea: false },
                    ticks: { color: '#3498db', font: { family: 'Inter', size: 11 } }
                }
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

    document.getElementById('chartTitle').textContent = 'Comparativa anual — Ingresos totales';

    if (mainChartInstance) mainChartInstance.destroy();

    const orderedStats = [...stats].sort((a, b) => Number(a.year_val) - Number(b.year_val));
    const labels = orderedStats.map((s) => s.year_val);
    const totals = orderedStats.map((s) => Number(s.total_amount ?? 0));

    Chart.defaults.color = '#888';
    Chart.defaults.font  = { family: 'Inter, sans-serif', size: 12 };

    mainChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Ingresos Anuales ($)',
                data: totals,
                borderColor: '#ff7f00',
                backgroundColor: 'rgba(255,127,0,0.08)',
                fill: true,
                tension: 0.4,
                pointRadius: 7,
                pointBackgroundColor: '#ff7f00',
                pointBorderColor: '#0d0d0d',
                pointBorderWidth: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#111',
                    borderColor: '#2a2a2a',
                    borderWidth: 1,
                    titleColor: '#fff',
                    bodyColor: '#aaa',
                    padding: 12
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(255,255,255,0.04)' },
                    ticks: { color: '#666', font: { family: 'Inter', size: 11 } }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(255,255,255,0.05)' },
                    ticks: { color: '#666', font: { family: 'Inter', size: 11 } }
                }
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
        totalAmount = stats.reduce((acc, s) => acc + Number(s.total_amount  ?? 0), 0);
        totalOrders = stats.reduce((acc, s) => acc + Number(s.total_orders  ?? 0), 0);
    } else {
        totalAmount = stats.reduce((acc, s) => acc + Number(s.Total ?? s.total_amount ?? 0), 0);
        totalOrders = stats.reduce((acc, s) => acc + Number(s.Pedidos ?? s.total_orders ?? 0), 0);
    }

    const avgTicket   = totalOrders > 0 ? (totalAmount / totalOrders) : 0;
    const periodLabel = isYearly ? 'Total Anual' : 'Total del Período';

    container.innerHTML = `
        <div class="kpi-card accent">
            <span class="kpi-card-icon">💰</span>
            <div class="kpi-card-label">${periodLabel}</div>
            <div class="kpi-card-value">${formatCurrency(totalAmount)}</div>
            <div class="kpi-card-helper">Ingresos concentrados en la vista actual.</div>
        </div>
        <div class="kpi-card">
            <span class="kpi-card-icon">📦</span>
            <div class="kpi-card-label">Movimientos</div>
            <div class="kpi-card-value">${totalOrders.toLocaleString('es-MX')}</div>
            <div class="kpi-card-helper">Órdenes o registros incluidos.</div>
        </div>
        <div class="kpi-card">
            <span class="kpi-card-icon">🎫</span>
            <div class="kpi-card-label">Ticket Promedio</div>
            <div class="kpi-card-value">${formatCurrency(avgTicket)}</div>
            <div class="kpi-card-helper">Valor medio por transacción.</div>
        </div>
        <div class="kpi-card">
            <span class="kpi-card-icon">🤖</span>
            <div class="kpi-card-label">Lectura IA</div>
            <div class="kpi-card-value" style="color:#2ecc71; font-size:1.2rem;">● Activa</div>
            <div class="kpi-card-helper">Actualizada con datos históricos reales.</div>
        </div>
    `;
}

/**
 * Renderiza calendario
 */
function renderCalendar(days, month, year) {
    const container = document.getElementById('calendarContainer');
    if (!container) return;

    const monthNames = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    const firstDay    = new Date(year, month - 1, 1).getDay();
    const daysInMonth = new Date(year, month, 0).getDate();
    const today       = new Date();
    const isThisMonth = today.getFullYear() == year && (today.getMonth() + 1) == month;

    const totalOrders = days.reduce((acc, d) => acc + Number(d.count || 0), 0);
    const totalAmount = days.reduce((acc, d) => acc + Number(d.total || 0), 0);

    let html = `
        <div class="cal-summary">
            <div class="cal-summary-item">
                <div class="cal-summary-label">Mes</div>
                <div class="cal-summary-value">${monthNames[month - 1]} ${year}</div>
            </div>
            <div class="cal-summary-item">
                <div class="cal-summary-label">Pedidos</div>
                <div class="cal-summary-value">${totalOrders}</div>
            </div>
            <div class="cal-summary-item">
                <div class="cal-summary-label">Monto Total</div>
                <div class="cal-summary-value">${formatCurrency(totalAmount)}</div>
            </div>
        </div>
        <div class="cal-grid">
            <div class="cal-day-head">Dom</div>
            <div class="cal-day-head">Lun</div>
            <div class="cal-day-head">Mar</div>
            <div class="cal-day-head">Mié</div>
            <div class="cal-day-head">Jue</div>
            <div class="cal-day-head">Vie</div>
            <div class="cal-day-head">Sáb</div>
    `;

    for (let i = 0; i < firstDay; i++) {
        html += '<div class="cal-day empty"></div>';
    }

    for (let d = 1; d <= daysInMonth; d++) {
        const activity    = days.find(day => day.day == d);
        const hasActivity = activity && Number(activity.count) > 0;
        const isToday     = isThisMonth && d === today.getDate();

        html += `
            <div class="cal-day${hasActivity ? ' has-activity' : ''}" ${isToday ? 'style="box-shadow:0 0 0 2px #ff7f00;"' : ''}>
                <div class="cal-day-num">${d}</div>
                ${hasActivity ? `
                    <div class="cal-day-count">${activity.count} ped.</div>
                    <div class="cal-day-amount">${formatCurrency(activity.total)}</div>
                ` : ''}
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
        container.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:3rem;"><div class="spinner"></div><p>Analizando patrones históricos y factores de temporada...</p></div>';
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
        container.innerHTML = `<div class="analytics-empty" style="grid-column:1/-1;">
            <div class="analytics-empty-icon">📭</div>
            <div class="analytics-empty-text">No hay suficientes datos históricos para generar predicciones confiables. Agrega órdenes para comenzar.</div>
        </div>`;
        return;
    }

    let html = '';
    predictions.forEach(pred => {
        const conf  = Number(pred.confidence || 0);
        const confColor = conf > 80 ? '#2ecc71' : conf > 60 ? '#f1c40f' : '#e67e22';
        const factorLines = Array.isArray(pred.insights) && pred.insights.length > 0
            ? pred.insights.map(item => `<li>${item}</li>`).join('')
            : '<li>Predicción construida con promedio histórico, tendencia y estacionalidad.</li>';
        const fd = pred.factors || {};

        html += `
            <div class="ai-pred-card">
                <div class="ai-pred-top">
                    <div>
                        <div class="ai-pred-name">${pred.product_name || ('SKU #' + pred.product_id)}</div>
                        <div class="ai-pred-sku">${pred.sku ? 'SKU ' + pred.sku : 'Producto analizado por IA'}</div>
                    </div>
                    <span class="ai-pred-season-badge">${pred.season}</span>
                </div>
                <div style="margin-bottom:1.25rem;">
                    <div class="ai-pred-demand-label">Demanda proyectada</div>
                    <div class="ai-pred-demand-value">${pred.predicted_demand} <span class="ai-pred-demand-unit">unidades</span></div>
                </div>
                <div class="confidence-bar-wrap">
                    <div class="confidence-bar-top">
                        <span>Índice de confianza</span>
                        <span style="font-weight:700; color:${confColor};">${conf}%</span>
                    </div>
                    <div class="confidence-bar-track">
                        <div class="confidence-bar-fill" style="width:${conf}%; background:${confColor};"></div>
                    </div>
                </div>
                <div class="ai-factor-box">
                    <div class="ai-factor-title">Por qué la IA llegó a esta estimación</div>
                    <ul class="ai-factor-list">${factorLines}</ul>
                    <div class="ai-factor-mini-grid">
                        <div><span>Base</span><strong>${Number(fd.base_average || 0).toFixed(2)}</strong></div>
                        <div><span>Temporada</span><strong>${Number(fd.season_factor || 1).toFixed(2)}x</strong></div>
                        <div><span>Tendencia</span><strong>${Number(fd.trend_factor || 1).toFixed(2)}x</strong></div>
                        <div><span>Ajuste ext.</span><strong>${Number(fd.external_adjustment || 1).toFixed(2)}x</strong></div>
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

    const seasonMeta = {
        'Primavera': { icon: '🌸', color: '#2ecc71' },
        'Verano':    { icon: '☀️', color: '#f1c40f' },
        'Otoño':     { icon: '🍂', color: '#e67e22' },
        'Invierno':  { icon: '❄️', color: '#3498db' }
    };

    let html = '';
    Object.entries(report).forEach(([season, data]) => {
        const meta = seasonMeta[season] || { icon: '📅', color: '#ff7f00' };
        const topProducts = Array.isArray(data.top_products) ? data.top_products : [];

        html += `
            <div class="seasonal-card" style="border-top:3px solid ${meta.color};">
                <div class="seasonal-card-header" style="color:${meta.color};">
                    ${meta.icon} ${season}
                </div>
                <div class="seasonal-card-body">
                    <div class="seasonal-stats">
                        <div class="seasonal-stat">
                            <div class="seasonal-stat-label">Monto Total</div>
                            <div class="seasonal-stat-value">${formatCurrency(data.total_amount)}</div>
                        </div>
                        <div class="seasonal-stat">
                            <div class="seasonal-stat-label">Volumen</div>
                            <div class="seasonal-stat-value">${data.total_quantity} uds.</div>
                        </div>
                    </div>
                    <div class="seasonal-products-title">Top Productos</div>
                    ${topProducts.length > 0
                        ? topProducts.map(p => `
                            <div class="seasonal-product-row">
                                <span>${p.name}</span>
                                <span>${p.quantity} uds.</span>
                            </div>
                        `).join('')
                        : '<div style="font-size:.8rem; color:#555; padding:.5rem 0;">Sin datos para esta temporada.</div>'
                    }
                </div>
            </div>
        `;
    });

    container.innerHTML = html || `<div class="analytics-empty" style="grid-column:1/-1;">
        <div class="analytics-empty-icon">📭</div>
        <div class="analytics-empty-text">No se encontraron datos estacionales.</div>
    </div>`;
}

/**
 * Mostrar análisis de clientes mejorado
 */
function displayClientAnalytics(clients) {
    const container = document.getElementById('clientAnalytics');
    if (!container) return;

    if (!Array.isArray(clients) || clients.length === 0) {
        container.innerHTML = `<div class="analytics-empty">
            <div class="analytics-empty-icon">👥</div>
            <div class="analytics-empty-text">No hay clientes para analizar todavía. Las métricas aparecerán cuando haya órdenes registradas.</div>
        </div>`;
        return;
    }

    const totalSpent  = clients.reduce((acc, c) => acc + Number(c.total_spent || 0), 0);
    const activeCount = clients.filter(c => c.is_active === true || c.is_active === 't' || c.is_active === 1).length;
    const initials    = (name) => (name || '?').split(' ').slice(0,2).map(w => w[0]).join('').toUpperCase();

    let html = `
        <div class="client-summary-bar">
            <div><span>Clientes analizados</span><strong>${clients.length}</strong></div>
            <div><span>Activos</span><strong>${activeCount}</strong></div>
            <div><span>Ingreso acumulado</span><strong>${formatCurrency(totalSpent)}</strong></div>
        </div>
        <div class="client-cards-grid">
    `;

    clients.slice(0, 12).forEach((client, index) => {
        const active = client.is_active === true || client.is_active === 't' || client.is_active === 1;
        html += `
            <article class="client-card">
                <div class="client-card-rank">#${index + 1}</div>
                <div class="client-card-avatar">${initials(client.name)}</div>
                <div class="client-card-name">${client.name || 'Sin nombre'}</div>
                <div class="client-card-id">ID ${client.id}</div>
                <div class="client-card-amount">${formatCurrency(client.total_spent || 0)}</div>
                <div class="client-card-meta">${client.order_count || 0} órdenes &middot; ${client.loyalty_points || 0} pts</div>
                <span class="client-badge ${active ? 'active' : 'inactive'}">${active ? '● Activo' : '● Inactivo'}</span>
            </article>
        `;
    });

    html += '</div>';
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

/**
 * Cargar analítica de clientes
 */
async function loadClientAnalytics() {
    const response = await apiCall('/analytics.php?action=client-analytics');
    if (response && Array.isArray(response.clients)) {
        displayClientAnalytics(response.clients);
        return;
    }

    const container = document.getElementById('clientAnalytics');
    if (container) {
        container.innerHTML = '<div class="empty-state" style="padding:2rem; text-align:center;">No se pudo cargar la analítica de clientes.</div>';
    }
}

/**
 * Cargar historial de tickets (para tab de Estadísticas)
 */
async function loadTicketHistory() {
    const year = document.getElementById('ticketYearFilter')?.value || new Date().getFullYear();
    const month = document.getElementById('ticketMonthFilter')?.value || new Date().getMonth() + 1;

    const response = await apiCall(`/analytics.php?action=ticket-history&year=${year}&month=${month}`);
    
    if (response && response.success) {
        displayTicketHistory(response.data);
    } else {
        showAlert('Error', 'No se pudo cargar el historial de tickets', 'error');
    }
}

/**
 * Mostrar historial de tickets en tabla
 */
function displayTicketHistory(data) {
    const tableBody = document.getElementById('ticketsTableBody');
    const summaryContainer = document.getElementById('ticketsSummary');
    const supplierSection = document.getElementById('supplierTicketsSection');
    
    if (!tableBody) return;

    const tickets = data.tickets || [];
    const supplierTickets = data.supplier_tickets || [];
    const stats = data.stats || {};

    // Mostrar estadísticas principales
    if (summaryContainer) {
        let statHTML = `
            <div class="stat-card stat-card-accent">
                <div class="stat-label">Tickets Este Mes</div>
                <div class="stat-value">${stats.total_tickets || 0}</div>
            </div>
            <div class="stat-card stat-card-accent">
                <div class="stat-label">Total Ventas</div>
                <div class="stat-value">${formatCurrency(stats.total_sales || 0)}</div>
                <div class="stat-helper">Promedio: ${formatCurrency(stats.avg_ticket || 0)}</div>
            </div>
            <div class="stat-card stat-card-accent">
                <div class="stat-label">Devoluciones</div>
                <div class="stat-value">${stats.return_count || 0}</div>
            </div>
            <div class="stat-card stat-card-accent">
                <div class="stat-label">Pagos Pendientes</div>
                <div class="stat-value">${stats.payment_pending || 0}</div>
            </div>
        `;

        // Si hay estadísticas de proveedor, mostrar tarjetas adicionales
        if (typeof stats.supplier_count !== 'undefined') {
            statHTML += `
                <div class="stat-card">
                    <div class="stat-label">Órdenes Proveedor</div>
                    <div class="stat-value">${stats.supplier_count || 0}</div>
                    <div class="stat-helper">Monto total: ${formatCurrency(stats.supplier_total || 0)}</div>
                </div>
            `;
        }

        summaryContainer.innerHTML = statHTML;
    }

    // Mostrar tickets en tabla (clientes)
    if (tickets.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="7" style="padding: 2rem; text-align: center; color: var(--theme-text-muted);">No hay tickets en este período</td></tr>';
    } else {
        let html = '';
        tickets.forEach(ticket => {
            const statusColor = ticket.payment_status === 'completed' ? 'var(--color-green)' : 'var(--color-yellow)';
            const typeLabel = {
                'sale': '💰 Venta',
                'return': '🔄 Devolución',
                'adjustment': '⚙️ Ajuste',
                'credit': '💳 Crédito'
            }[ticket.ticket_type] || ticket.ticket_type;

            html += `
                <tr style="border-bottom: 1px solid var(--theme-border); transition: background 0.2s;">
                    <td style="padding: 1rem; font-family: monospace; font-weight: 700; color: var(--color-naranja);">${ticket.folio}</td>
                    <td style="padding: 1rem;">
                        <div style="font-weight: 600;">${ticket.customer_name || 'Sin nombre'}</div>
                        <div style="font-size: 0.8rem; color: var(--theme-text-muted);">${ticket.email || ''}</div>
                    </td>
                    <td style="padding: 1rem;">${typeLabel}</td>
                    <td style="padding: 1rem; font-weight: 700; color: var(--color-naranja);">${formatCurrency(ticket.total_amount || 0)}</td>
                    <td style="padding: 1rem;">
                        <span style="display: inline-block; padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; background: ${statusColor}; color: white;">
                            ${ticket.payment_status === 'completed' ? '✓ Pagado' : '⏳ Pendiente'}
                        </span>
                    </td>
                    <td style="padding: 1rem; font-size: 0.9rem; color: var(--theme-text-muted);">
                        ${new Date(ticket.issued_date).toLocaleDateString('es-MX')}
                    </td>
                    <td style="padding: 1rem; text-align: center; font-weight: 600;">${ticket.item_count || 0}</td>
                </tr>
            `;
        });

        tableBody.innerHTML = html;
    }

    // Mostrar tickets de proveedor en sección separada
    if (supplierSection) {
        if (!Array.isArray(supplierTickets) || supplierTickets.length === 0) {
            supplierSection.innerHTML = '<div class="card"><div class="card-body"><p class="text-muted">No hay órdenes o tickets de proveedor en este período.</p></div></div>';
        } else {
            let shtml = `
                <div class="card">
                    <div class="card-header">Tickets / Órdenes a Proveedor</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table" style="width:100%; border-collapse: collapse;">
                                <thead style="background: var(--theme-surface); border-bottom: 2px solid var(--theme-border);">
                                    <tr>
                                        <th>Folio</th>
                                        <th>Proveedor</th>
                                        <th>Fecha</th>
                                        <th>Monto</th>
                                        <th>Artículos</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
            `;

            supplierTickets.forEach(st => {
                const status = st.payment_status || st.status || '';
                shtml += `
                    <tr>
                        <td style="font-family: monospace; font-weight:700; color:var(--color-naranja);">${st.folio}</td>
                        <td>${st.customer_name || '—'}</td>
                        <td style="color:var(--theme-text-muted);">${new Date(st.issued_date).toLocaleDateString('es-MX')}</td>
                        <td style="font-weight:700; color:var(--color-naranja);">${formatCurrency(st.total_amount || 0)}</td>
                        <td style="text-align:center; font-weight:600;">${st.item_count || 0}</td>
                        <td>${st.payment_status === 'completed' ? '✓' : st.payment_status || status}</td>
                    </tr>
                `;
            });

            shtml += `</tbody></table></div></div></div>`;
            supplierSection.innerHTML = shtml;
        }
    }
}

/**
 * Archivar tickets del mes actual
 */
async function archiveCurrentMonth() {
    if (!confirm('¿Estás seguro de que quieres archivar los tickets de este mes? Esta acción no se puede deshacer.')) {
        return;
    }

    const year = document.getElementById('ticketYearFilter')?.value || new Date().getFullYear();
    const month = document.getElementById('ticketMonthFilter')?.value || new Date().getMonth() + 1;

    try {
        const response = await fetch('api/analytics.php?action=archive-tickets', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `year=${year}&month=${month}`
        });

        const data = await response.json();

        if (data.success) {
            showAlert('Éxito', `Se archivaron ${data.archived_count} tickets correctamente.`, 'success');
            loadTicketHistory();
        } else {
            showAlert('Error', data.message || 'Error al archivar', 'error');
        }
    } catch (error) {
        console.error('Error archivando tickets:', error);
        showAlert('Error', 'Error en la solicitud', 'error');
    }
}

/**
 * Descargar historial de tickets en formato Excel
 */
function downloadTicketsExcel() {
    const year = document.getElementById('ticketYearFilter')?.value || new Date().getFullYear();
    const month = document.getElementById('ticketMonthFilter')?.value || new Date().getMonth() + 1;
    const exportType = document.getElementById('exportTypeFilter')?.value || 'client';
    const button = document.querySelector('[data-download-tickets-excel]');

    if (button) {
        button.disabled = true;
        const originalText = button.innerHTML;
        button.dataset.originalText = originalText;
        button.innerHTML = '⏳ Generando...';
        setTimeout(() => {
            button.disabled = false;
            if (button.dataset.originalText) {
                button.innerHTML = button.dataset.originalText;
                delete button.dataset.originalText;
            }
        }, 1500);
    }

    const url = `api/analytics.php?action=ticket-export&year=${encodeURIComponent(year)}&month=${encodeURIComponent(month)}&type=${encodeURIComponent(exportType)}`;
    const link = document.createElement('a');
    link.href = url;
    link.rel = 'noopener';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

/**
 * Inicializar filtros de tickets
 */
function initTicketFilters() {
    const yearSelect = document.getElementById('ticketYearFilter');
    const monthSelect = document.getElementById('ticketMonthFilter');

    if (!yearSelect) return;

    const populateYearFallback = () => {
        const currentYear = new Date().getFullYear();
        const years = [currentYear, currentYear - 1, currentYear - 2];
        yearSelect.innerHTML = '';
        years.forEach(year => {
            const option = document.createElement('option');
            option.value = year;
            option.textContent = year;
            if (year === currentYear) option.selected = true;
            yearSelect.appendChild(option);
        });

        if (monthSelect) {
            monthSelect.value = new Date().getMonth() + 1;
        }

        loadTicketHistory();
    };

    apiCall('/analytics.php?action=ticket-years')
        .then(response => {
            const currentYear = new Date().getFullYear();
            const years = Array.isArray(response?.years) && response.years.length > 0
                ? response.years.map(year => parseInt(year, 10)).filter(year => !isNaN(year))
                : [currentYear, currentYear - 1, currentYear - 2];

            yearSelect.innerHTML = '';
            years.forEach(year => {
                const option = document.createElement('option');
                option.value = year;
                option.textContent = year;
                if (year === currentYear) option.selected = true;
                yearSelect.appendChild(option);
            });

            if (yearSelect.options.length > 0 && !yearSelect.value) {
                yearSelect.selectedIndex = 0;
            }

            if (monthSelect) {
                monthSelect.value = new Date().getMonth() + 1;
            }

            loadTicketHistory();
        })
        .catch(() => {
            populateYearFallback();
        });
}

// Inicializar filtros de tickets cuando la página carga
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTicketFilters);
} else {
    initTicketFilters();
}
