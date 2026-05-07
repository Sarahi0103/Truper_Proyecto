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

    if (mainChartInstance) {
        mainChartInstance.destroy();
    }

    const monthLabels = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const labels = stats.map((s) => s.Mes || monthLabels[(Number(s.month_num) || 1) - 1] || 'Mes');
    const totals = stats.map((s) => Number(s.Total ?? s.total_amount ?? 0));
    const orders = stats.map((s) => Number(s.Pedidos ?? s.total_orders ?? 0));

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

    document.getElementById('chartTitle').textContent = 'Comparativa anual separada por año';

    if (mainChartInstance) {
        mainChartInstance.destroy();
    }

    const orderedStats = [...stats].sort((a, b) => Number(a.year_val) - Number(b.year_val));
    const labels = orderedStats.map((s) => s.year_val);
    const totals = orderedStats.map((s) => Number(s.total_amount ?? 0));

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
        totalAmount = stats.reduce((acc, s) => acc + Number(s.Total ?? s.total_amount ?? 0), 0);
        totalOrders = stats.reduce((acc, s) => acc + Number(s.Pedidos ?? s.total_orders ?? 0), 0);
    }

    const avgTicket = totalOrders > 0 ? (totalAmount / totalOrders) : 0;
    const periodLabel = isYearly ? 'Lectura anual' : 'Lectura mensual';

    container.innerHTML = `
        <div class="stat-card stat-card-accent">
            <div class="stat-label">${periodLabel}</div>
            <div class="stat-value">${formatCurrency(totalAmount)}</div>
            <div class="stat-helper">Monto concentrado en la vista actual.</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Movimientos</div>
            <div class="stat-value">${totalOrders}</div>
            <div class="stat-helper">Órdenes o registros incluidos.</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Ticket promedio</div>
            <div class="stat-value">${formatCurrency(avgTicket)}</div>
            <div class="stat-helper">Valor medio por transacción.</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Lectura IA</div>
            <div class="stat-value" style="color:#2ecc71;">Activa</div>
            <div class="stat-helper">Se actualiza con datos históricos reales.</div>
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

    const totalOrders = days.reduce((acc, day) => acc + Number(day.count || 0), 0);
    const totalAmount = days.reduce((acc, day) => acc + Number(day.total || 0), 0);

    let html = `
        <div class="calendar-summary">
            <div>
                <div class="calendar-summary-label">Mes seleccionado</div>
                <div class="calendar-summary-value">${monthNames[month-1]} ${year}</div>
            </div>
            <div>
                <div class="calendar-summary-label">Pedidos</div>
                <div class="calendar-summary-value">${totalOrders}</div>
            </div>
            <div>
                <div class="calendar-summary-label">Monto</div>
                <div class="calendar-summary-value">${formatCurrency(totalAmount)}</div>
            </div>
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
        container.innerHTML = '<p class="text-muted" style="grid-column:1/-1; text-align:center;">No hay suficientes datos históricos para generar predicciones confiables.</p>';
        return;
    }

    let html = '';
    predictions.forEach(pred => {
        const confidenceColor = pred.confidence > 80 ? '#2ecc71' : pred.confidence > 60 ? '#f1c40f' : '#e67e22';
        const factorLines = Array.isArray(pred.insights) && pred.insights.length > 0
            ? pred.insights.map((item) => `<li>${item}</li>`).join('')
            : '<li>Predicción construida con promedio histórico, tendencia y estacionalidad.</li>';
        const factorData = pred.factors || {};
        html += `
            <div class="card ai-card" style="border-top: 4px solid var(--color-naranja);">
                <div class="ai-card-head">
                    <div>
                        <h4 style="margin:0; font-size:1.1rem;">${pred.product_name || `SKU #${pred.product_id}`}</h4>
                        <div class="ai-card-subtitle">${pred.sku ? `SKU ${pred.sku}` : 'Producto analizado por IA'}</div>
                    </div>
                    <span class="badge badge-info" style="background:rgba(52,152,219,0.1); color:#3498db;">${pred.season}</span>
                </div>
                <div style="margin-bottom:1.5rem;">
                    <div style="font-size:0.85rem; color:var(--theme-text-muted);">Demanda proyectada</div>
                    <div style="font-size:1.6rem; font-weight:700;">${pred.predicted_demand} <span style="font-size:0.9rem; font-weight:400;">unidades</span></div>
                </div>
                <div>
                    <div style="display:flex; justify-content:space-between; font-size:0.8rem; margin-bottom:0.4rem;">
                        <span>Índice de confianza</span>
                        <span style="font-weight:700; color:${confidenceColor};">${pred.confidence}%</span>
                    </div>
                    <div class="progress" style="height: 6px; background: rgba(0,0,0,0.05); border-radius: 10px; overflow:hidden;">
                        <div style="width: ${pred.confidence}%; height: 100%; background: ${confidenceColor};"></div>
                    </div>
                </div>
                <div class="ai-factor-box">
                    <div class="ai-factor-title">Por qué la IA llegó a esta estimación</div>
                    <ul class="ai-factor-list">
                        ${factorLines}
                    </ul>
                    <div class="ai-factor-grid">
                        <div><span>Base</span><strong>${Number(factorData.base_average || 0).toFixed(2)}</strong></div>
                        <div><span>Temporada</span><strong>${Number(factorData.season_factor || 1).toFixed(2)}x</strong></div>
                        <div><span>Tendencia</span><strong>${Number(factorData.trend_factor || 1).toFixed(2)}x</strong></div>
                        <div><span>Ajuste externo</span><strong>${Number(factorData.external_adjustment || 1).toFixed(2)}x</strong></div>
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
        const topProducts = Array.isArray(data.top_products) ? data.top_products : [];

        html += `
            <div class="card seasonal-card" style="border-left: 5px solid ${color};">
                <div class="card-header" style="background:transparent; color:${color}; font-weight:700;">${season}</div>
                <div class="card-body">
                    <p style="margin-top:0; color:var(--theme-text-muted);">Concentración histórica por temporada con los productos más repetidos.</p>
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
                        ${topProducts.map(p => `
                            <div style="display:flex; justify-content:space-between; font-size:0.8rem; margin-bottom:0.2rem;">
                                <span>${p.name}</span>
                                <span style="font-weight:600;">${p.quantity}</span>
                            </div>
                        `).join('') || '<div style="font-size:0.8rem; color:var(--theme-text-muted);">Sin productos para esta temporada.</div>'}
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
    if (!Array.isArray(clients) || clients.length === 0) {
        container.innerHTML = '<div class="empty-state" style="padding:2rem; text-align:center;">No hay clientes para analizar todavía.</div>';
        return;
    }

    const totalSpent = clients.reduce((acc, client) => acc + Number(client.total_spent || 0), 0);
    const activeCount = clients.filter((client) => client.is_active === true || client.is_active === 't' || client.is_active === 1).length;

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
                <div class="client-card-name">${client.name || 'Cliente sin nombre'}</div>
                <div class="client-card-id">ID ${client.id}</div>
                <div class="client-card-metric">${formatCurrency(client.total_spent || 0)}</div>
                <div class="client-card-meta">${client.order_count || 0} órdenes · ${client.loyalty_points || 0} puntos</div>
                <div class="badge badge-${active ? 'success' : 'danger'}">${active ? 'Activo' : 'Inactivo'}</div>
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
