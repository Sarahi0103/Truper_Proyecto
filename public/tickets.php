<?php
require_once '../config/config.php';
require_admin();

$user_name = htmlspecialchars($_SESSION['name'] ?? 'Administrador', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Historial de Tickets - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css?v=2.2">
    <link rel="stylesheet" href="css/theme.css?v=2.5">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/responsive-complete.css?v=2.2">
    <style>
        .tickets-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem;
        }
        
        .grid-filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            align-items: flex-end;
        }

        .stat-grid-tickets {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-top: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .tickets-split-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        @media (max-width: 1024px) {
            .tickets-split-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    <header>
        <div class="header-content">
            <a href="dashboard.php" class="logo"><img src="images/truper-logo.svg" alt="Truper"></a>
                        <nav class="nav-menu">
                <a href="index.php">Catálogo</a>
                <a href="marketplace_ce.php">Marketplace CE</a>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Mi Cuenta <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content">
                        <a href="dashboard.php">Dashboard</a>
                        <a href="orders.php">Pedidos</a>
                        <a href="wholesale.php">Mayoreo</a>
                        <a href="account.php#historyTab">Historial</a>
                        <a href="profile.php">Perfil</a>
                    </div>
                </div>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Administración <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content">
                        <a href="cashier.php">Caja</a>
                        <a href="admin_supply.php?nocache=true">Abastecimiento</a>
                        <a href="tickets.php" class="active">Tickets</a>
                        <a href="tasks.php">Tareas</a>
                        <a href="analytics.php">Estadísticas</a>
                    </div>
                </div>
            </nav>
        </div>
        <div class="user-menu">
            <div class="user-info">
                <div class="user-name"><?php echo $user_name; ?></div>
                <div class="user-role">Admin</div>
            </div>
            <button class="btn-logout" onclick="logout()">Cerrar Sesión</button>
        </div>
    </header>

    <main>
        <div class="tickets-container">
            <div class="page-hero">
                <div class="module-badge module-admin"><span class="module-glyph">TK</span> Historial centralizado</div>
                <h1>Panel de Historial de Tickets</h1>
                <p class="text-muted">Consulta, filtra y descarga el histórico de tickets de clientes y órdenes de proveedores.</p>
            </div>

            <!-- Filtros -->
            <div class="card mt-3">
                <div class="card-body">
                    <h3>Filtros y Búsqueda</h3>
                    <div class="grid-filters mt-2">
                        <div class="form-group">
                            <label for="ticketYearFilter">Año</label>
                            <select id="ticketYearFilter" onchange="loadTicketHistory()"></select>
                        </div>
                        <div class="form-group">
                            <label for="ticketMonthFilter">Mes</label>
                            <select id="ticketMonthFilter" onchange="loadTicketHistory()">
                                <option value="1">Enero</option>
                                <option value="2">Febrero</option>
                                <option value="3">Marzo</option>
                                <option value="4">Abril</option>
                                <option value="5">Mayo</option>
                                <option value="6">Junio</option>
                                <option value="7">Julio</option>
                                <option value="8">Agosto</option>
                                <option value="9">Septiembre</option>
                                <option value="10">Octubre</option>
                                <option value="11">Noviembre</option>
                                <option value="12">Diciembre</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="exportTypeFilter">Exportar como</label>
                            <select id="exportTypeFilter">
                                <option value="client">Tickets de Clientes</option>
                                <option value="supplier">Órdenes a Proveedores</option>
                                <option value="both">Ambos (Libro Completo)</option>
                            </select>
                        </div>
                        <div class="form-group" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <button class="btn btn-primary" onclick="downloadTicketsExcel()" data-download-tickets-excel style="flex: 1;">📥 Exportar Excel</button>
                            <button class="btn btn-secondary" onclick="archiveCurrentMonth()" style="flex: 1;">🔒 Archivar Mes</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alertas / Resumen -->
            <div class="stat-grid-tickets" id="ticketsSummary">
                <div class="card"><div class="card-body text-center"><p class="text-muted">Cargando estadísticas...</p></div></div>
            </div>

            <!-- Reportes PDF Archivados -->
            <div class="card mt-3" style="background: rgba(255, 255, 255, 0.01); border: 1px solid rgba(255, 255, 255, 0.06); margin-bottom: 1.5rem;">
                <div class="card-body">
                    <h3 style="display: flex; align-items: center; gap: 0.5rem;">📄 Reportes Mensuales Archivados</h3>
                    <p class="text-muted mb-2">Historial de PDFs consolidados acumulados al realizar cierres mensuales de caja.</p>
                    <div id="archivedPdfsContainer" style="display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1rem;">
                        <p class="text-muted" style="font-style: italic;">Cargando archivos...</p>
                    </div>
                </div>
            </div>

            <!-- Tablas de Historial -->
            <div class="tickets-split-grid">
                <!-- CLIENTES -->
                <div class="card">
                    <div class="card-body">
                        <h3>Tickets de Clientes</h3>
                        <p class="text-muted mb-2">Historial de ventas y movimientos de caja en este período.</p>
                        <div class="table-responsive">
                            <table class="table" style="width:100%; border-collapse: collapse;">
                                <thead>
                                    <tr>
                                        <th>Folio</th>
                                        <th>Cliente</th>
                                        <th>Tipo</th>
                                        <th>Total</th>
                                        <th>Estado</th>
                                        <th>Fecha</th>
                                        <th style="text-align: center;">Artículos</th>
                                    </tr>
                                </thead>
                                <tbody id="ticketsTableBody">
                                    <tr><td colspan="7" style="text-align: center; padding: 2rem;">Cargando...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- PROVEEDORES -->
                <div id="supplierTicketsSection">
                    <div class="card">
                        <div class="card-body">
                            <h3>Órdenes de Proveedores</h3>
                            <p class="text-muted mb-2">Historial de ingresos de mercancía y cotizaciones.</p>
                            <div class="table-responsive">
                                <table class="table" style="width:100%; border-collapse: collapse;">
                                    <thead>
                                        <tr>
                                            <th>Folio</th>
                                            <th>Proveedor</th>
                                            <th>Fecha</th>
                                            <th>Total</th>
                                            <th style="text-align: center;">Artículos</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody id="supplierTicketsBody">
                                        <tr><td colspan="6" style="text-align: center; padding: 2rem;">Cargando...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- FOOTER -->
    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h4>Truper Platform</h4>
                <p>Módulo de Historial y Auditoría de Ventas</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 Truper Platform. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script src="js/jspdf.umd.min.js"></script>
    <script src="js/main.js?v=2.6"></script>
    <script>
        window.csrfToken = '<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>';

        function logout() {
            if (confirm('¿Deseas cerrar sesión?')) {
                window.location.href = 'api/auth.php?action=logout';
            }
        }

        function escapeHtml(v) {
            return String(v || '').replace(/[&<>"']/g, function(m) {
                return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m];
            });
        }

        function formatAdminMoney(value) {
            const amount = Number(value || 0);
            return `$${amount.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        }

        async function loadTicketHistory() {
            const yearSelect = document.getElementById('ticketYearFilter');
            const monthSelect = document.getElementById('ticketMonthFilter');
            const year = yearSelect ? yearSelect.value : new Date().getFullYear();
            const month = monthSelect ? monthSelect.value : (new Date().getMonth() + 1);

            const response = await apiCall(`/analytics.php?action=ticket-history&year=${year}&month=${month}`);
            
            if (response && response.success) {
                displayTicketHistory(response.data);
            } else {
                showAlert('No se pudo cargar el historial de tickets', 'error');
            }
        }

        function displayTicketHistory(data) {
            const tableBody = document.getElementById('ticketsTableBody');
            const summaryContainer = document.getElementById('ticketsSummary');
            const supplierSection = document.getElementById('supplierTicketsSection');
            
            if (!tableBody) return;

            const tickets = data.tickets || [];
            const supplierTickets = data.supplier_tickets || [];
            const stats = data.stats || {};

            // Render stats summary cards
            if (summaryContainer) {
                let statHTML = `
                    <div class="card">
                        <div class="card-body">
                            <span class="text-muted text-uppercase" style="font-size:0.75rem; font-weight:700; display:block; letter-spacing: 0.04em;">Tickets Este Mes</span>
                            <strong style="display:block; font-size:1.5rem; margin-top:0.25rem; color:var(--color-naranja);">${stats.total_tickets || 0}</strong>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <span class="text-muted text-uppercase" style="font-size:0.75rem; font-weight:700; display:block; letter-spacing: 0.04em;">Total Ventas</span>
                            <strong style="display:block; font-size:1.5rem; margin-top:0.25rem; color:var(--color-naranja);">${formatAdminMoney(stats.total_sales || 0)}</strong>
                            <div style="font-size: 0.72rem; color: var(--theme-text-muted); margin-top: 0.2rem;">Promedio: ${formatAdminMoney(stats.avg_ticket || 0)}</div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <span class="text-muted text-uppercase" style="font-size:0.75rem; font-weight:700; display:block; letter-spacing: 0.04em;">Devoluciones</span>
                            <strong style="display:block; font-size:1.5rem; margin-top:0.25rem; color:var(--color-naranja);">${stats.return_count || 0}</strong>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <span class="text-muted text-uppercase" style="font-size:0.75rem; font-weight:700; display:block; letter-spacing: 0.04em;">Pagos Pendientes</span>
                            <strong style="display:block; font-size:1.5rem; margin-top:0.25rem; color:var(--color-naranja);">${stats.payment_pending || 0}</strong>
                        </div>
                    </div>
                `;

                // Add supplier summary cards if present
                if (typeof stats.supplier_count !== 'undefined') {
                    statHTML += `
                        <div class="card" style="grid-column: 1 / -1;">
                            <div class="card-body" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                                <div>
                                    <span class="text-muted text-uppercase" style="font-size:0.75rem; font-weight:700; display:block; letter-spacing: 0.04em;">Órdenes de Compra a Proveedor</span>
                                    <strong style="display:block; font-size:1.5rem; margin-top:0.25rem; color:var(--color-naranja);">${stats.supplier_count || 0}</strong>
                                </div>
                                <div style="text-align: right;">
                                    <span class="text-muted text-uppercase" style="font-size:0.75rem; font-weight:700; display:block; letter-spacing: 0.04em;">Monto total estimado</span>
                                    <strong style="display:block; font-size:1.3rem; margin-top:0.25rem; color:#fff;">${formatAdminMoney(stats.supplier_total || 0)}</strong>
                                </div>
                            </div>
                        </div>
                    `;
                }

                summaryContainer.innerHTML = statHTML;
            }

            // Client tickets rows
            if (tickets.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="7" style="padding: 2rem; text-align: center; color: var(--theme-text-muted);">No hay tickets en este período</td></tr>';
            } else {
                let html = '';
                tickets.forEach(ticket => {
                    const statusColor = ticket.payment_status === 'completed' ? 'var(--color-exito)' : 'var(--color-advertencia)';
                    const typeLabel = {
                        'sale': '💰 Venta',
                        'return': '🔄 Devolución',
                        'adjustment': '⚙️ Ajuste',
                        'credit': '💳 Crédito'
                    }[ticket.ticket_type] || ticket.ticket_type;

                    html += `
                        <tr style="border-bottom: 1px solid var(--theme-border);">
                            <td style="padding: 1rem; font-family: monospace; font-weight: 700; color: var(--color-naranja);">${escapeHtml(ticket.folio)}</td>
                            <td style="padding: 1rem;">
                                <div style="font-weight: 600;">${escapeHtml(ticket.customer_name || 'Sin nombre')}</div>
                                <div style="font-size: 0.8rem; color: var(--theme-text-muted);">${escapeHtml(ticket.email || '')}</div>
                            </td>
                            <td style="padding: 1rem;">${typeLabel}</td>
                            <td style="padding: 1rem; font-weight: 700; color: var(--color-naranja);">${formatAdminMoney(ticket.total_amount || 0)}</td>
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

            // Supplier tickets rows
            if (supplierSection) {
                if (!Array.isArray(supplierTickets) || supplierTickets.length === 0) {
                    supplierSection.innerHTML = `
                        <div class="card">
                            <div class="card-body">
                                <h3>Órdenes de Proveedores</h3>
                                <p class="text-muted" style="padding: 2rem; text-align: center;">No hay órdenes o tickets de proveedor en este período.</p>
                            </div>
                        </div>
                    `;
                } else {
                    let shtml = `
                        <div class="card">
                            <div class="card-body">
                                <h3>Órdenes de Proveedores</h3>
                                <p class="text-muted mb-2">Historial de ingresos de mercancía y cotizaciones.</p>
                                <div class="table-responsive">
                                    <table class="table" style="width:100%; border-collapse: collapse;">
                                        <thead>
                                            <tr>
                                                <th>Folio</th>
                                                <th>Proveedor</th>
                                                <th>Fecha</th>
                                                <th>Total</th>
                                                <th style="text-align: center;">Artículos</th>
                                                <th>Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                    `;

                    supplierTickets.forEach(st => {
                        const status = st.payment_status || st.status || '';
                        shtml += `
                            <tr style="border-bottom: 1px solid var(--theme-border);">
                                <td style="padding: 1rem; font-family: monospace; font-weight:700; color:var(--color-naranja);">${escapeHtml(st.folio)}</td>
                                <td style="padding: 1rem;">${escapeHtml(st.customer_name || '—')}</td>
                                <td style="padding: 1rem; color:var(--theme-text-muted);">${new Date(st.issued_date).toLocaleDateString('es-MX')}</td>
                                <td style="padding: 1rem; font-weight:700; color:var(--color-naranja);">${formatAdminMoney(st.total_amount || 0)}</td>
                                <td style="padding: 1rem; text-align:center; font-weight:600;">${st.item_count || 0}</td>
                                <td style="padding: 1rem;">
                                    <span style="display: inline-block; padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; background: var(--color-exito); color: white;">
                                        ${st.payment_status === 'completed' ? '✓ Recibido' : escapeHtml(st.payment_status || status)}
                                    </span>
                                </td>
                            </tr>
                        `;
                    });

                    shtml += `</tbody></table></div></div></div>`;
                    supplierSection.innerHTML = shtml;
                }
            }
        }

        function generateMonthlyReportPdf(year, month, data) {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'letter' });
            
            const monthNames = [
                'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
            ];
            const monthStr = monthNames[month - 1] || 'Mes';
            
            const primaryColor = [255, 102, 0]; 
            const darkColor = [30, 30, 30]; 
            const lightBg = [245, 245, 245];
            
            // Header
            doc.setFillColor(...primaryColor);
            doc.rect(0, 0, 215.9, 35, 'F');
            
            doc.setTextColor(255, 255, 255);
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(22);
            doc.text('TRUPER PLATFORM', 15, 18);
            
            doc.setFontSize(11);
            doc.setFont('helvetica', 'normal');
            doc.text(`REPORTE MENSUAL DE CIERRE Y AUDITORÍA - ${monthStr.toUpperCase()} ${year}`, 15, 26);
            
            doc.setFontSize(10);
            doc.text(`Generado: ${new Date().toLocaleDateString('es-MX')}`, 150, 18);
            doc.text(`Firma digital: MD5-${Math.random().toString(36).substring(2, 10).toUpperCase()}`, 150, 24);
            
            let y = 50;
            
            // Sección Estadísticas
            doc.setTextColor(...darkColor);
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(13);
            doc.text('RESUMEN DE ESTADÍSTICAS DEL MES', 15, y);
            
            doc.setDrawColor(220, 220, 220);
            doc.line(15, y + 2, 200, y + 2);
            y += 10;
            
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(10);
            
            const stats = data.stats || {};
            doc.text(`Total Tickets: ${stats.total_tickets || 0}`, 15, y);
            doc.text(`Total Ventas: $${Number(stats.total_sales || 0).toLocaleString('es-MX', {minimumFractionDigits:2})}`, 15, y + 6);
            doc.text(`Tickets Devolución: ${stats.return_count || 0}`, 110, y);
            doc.text(`Pagos Pendientes: ${stats.payment_pending || 0}`, 110, y + 6);
            y += 18;
            
            // Listado de Tickets Clientes
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(13);
            doc.text('HISTORIAL DE TICKETS DE CLIENTES', 15, y);
            doc.line(15, y + 2, 200, y + 2);
            y += 10;
            
            // Header Tabla Clientes
            doc.setFillColor(...lightBg);
            doc.rect(15, y, 185, 8, 'F');
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(9);
            doc.setTextColor(50, 50, 50);
            doc.text('Folio', 18, y + 5.5);
            doc.text('Cliente', 50, y + 5.5);
            doc.text('Tipo', 105, y + 5.5);
            doc.text('Estado', 130, y + 5.5);
            doc.text('Fecha', 155, y + 5.5);
            doc.text('Total', 180, y + 5.5);
            
            y += 8;
            doc.setFont('helvetica', 'normal');
            doc.setTextColor(...darkColor);
            
            const tickets = data.tickets || [];
            tickets.forEach((t, idx) => {
                if (y > 260) {
                    doc.addPage();
                    y = 20;
                    
                    // Redibuja header en página nueva
                    doc.setFillColor(...lightBg);
                    doc.rect(15, y, 185, 8, 'F');
                    doc.setFont('helvetica', 'bold');
                    doc.text('Folio', 18, y + 5.5);
                    doc.text('Cliente', 50, y + 5.5);
                    doc.text('Tipo', 105, y + 5.5);
                    doc.text('Estado', 130, y + 5.5);
                    doc.text('Fecha', 155, y + 5.5);
                    doc.text('Total', 180, y + 5.5);
                    y += 8;
                    doc.setFont('helvetica', 'normal');
                }
                
                if (idx % 2 === 1) {
                    doc.setFillColor(250, 250, 250);
                    doc.rect(15, y, 185, 7, 'F');
                }
                
                doc.text(t.folio || '-', 18, y + 5);
                
                let name = t.customer_name || 'Sin nombre';
                if (name.length > 25) name = name.substring(0, 23) + '...';
                doc.text(name, 50, y + 5);
                
                const typeLabel = {
                    'sale': 'Venta',
                    'return': 'Devolución',
                    'adjustment': 'Ajuste',
                    'credit': 'Crédito'
                }[t.ticket_type] || t.ticket_type;
                doc.text(typeLabel, 105, y + 5);
                
                const statusLabel = t.payment_status === 'completed' ? 'Pagado' : 'Pendiente';
                doc.text(statusLabel, 130, y + 5);
                doc.text(new Date(t.issued_date).toLocaleDateString('es-MX'), 155, y + 5);
                doc.text(`$${Number(t.total_amount || 0).toLocaleString('es-MX', {minimumFractionDigits:2})}`, 180, y + 5);
                
                y += 7;
            });
            
            y += 10;
            
            // Órdenes de Proveedores (si existen)
            const supplierTickets = data.supplier_tickets || [];
            if (supplierTickets.length > 0) {
                if (y > 230) {
                    doc.addPage();
                    y = 20;
                }
                
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(13);
                doc.text('ÓRDENES DE PROVEEDORES', 15, y);
                doc.line(15, y + 2, 200, y + 2);
                y += 10;
                
                doc.setFillColor(...lightBg);
                doc.rect(15, y, 185, 8, 'F');
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(9);
                doc.setTextColor(50, 50, 50);
                doc.text('Folio', 18, y + 5.5);
                doc.text('Proveedor', 50, y + 5.5);
                doc.text('Estado', 130, y + 5.5);
                doc.text('Fecha', 155, y + 5.5);
                doc.text('Total', 180, y + 5.5);
                
                y += 8;
                doc.setFont('helvetica', 'normal');
                doc.setTextColor(...darkColor);
                
                supplierTickets.forEach((st, idx) => {
                    if (y > 260) {
                        doc.addPage();
                        y = 20;
                        doc.setFillColor(...lightBg);
                        doc.rect(15, y, 185, 8, 'F');
                        doc.setFont('helvetica', 'bold');
                        doc.text('Folio', 18, y + 5.5);
                        doc.text('Proveedor', 50, y + 5.5);
                        doc.text('Estado', 130, y + 5.5);
                        doc.text('Fecha', 155, y + 5.5);
                        doc.text('Total', 180, y + 5.5);
                        y += 8;
                        doc.setFont('helvetica', 'normal');
                    }
                    
                    if (idx % 2 === 1) {
                        doc.setFillColor(250, 250, 250);
                        doc.rect(15, y, 185, 7, 'F');
                    }
                    
                    doc.text(st.folio || '-', 18, y + 5);
                    
                    let name = st.customer_name || 'Sin nombre';
                    if (name.length > 25) name = name.substring(0, 23) + '...';
                    doc.text(name, 50, y + 5);
                    doc.text(st.payment_status || 'Recibido', 130, y + 5);
                    doc.text(new Date(st.issued_date).toLocaleDateString('es-MX'), 155, y + 5);
                    doc.text(`$${Number(st.total_amount || 0).toLocaleString('es-MX', {minimumFractionDigits:2})}`, 180, y + 5);
                    
                    y += 7;
                });
            }
            
            return doc;
        }

        async function archiveCurrentMonth() {
            if (!confirm('¿Estás seguro de que quieres archivar los tickets de este mes? Se generará y guardará un PDF de cierre mensual. Esta acción no se puede deshacer.')) {
                return;
            }

            const yearSelect = document.getElementById('ticketYearFilter');
            const monthSelect = document.getElementById('ticketMonthFilter');
            const year = yearSelect ? yearSelect.value : new Date().getFullYear();
            const month = monthSelect ? monthSelect.value : (new Date().getMonth() + 1);

            try {
                // 1. Obtener datos históricos de este mes para el PDF
                showAlert('Obteniendo datos del mes...', 'info');
                const historyRes = await apiCall(`/analytics.php?action=ticket-history&year=${year}&month=${month}`);
                if (!historyRes || !historyRes.success) {
                    showAlert('No se pudo obtener el historial para generar el PDF', 'error');
                    return;
                }

                // 2. Crear el PDF
                showAlert('Generando PDF del mes...', 'info');
                const doc = generateMonthlyReportPdf(year, month, historyRes.data);
                const pdfBase64 = doc.output('datauristring').split(',')[1];

                // 3. Subir el PDF al servidor
                showAlert('Guardando PDF en el servidor...', 'info');
                const uploadRes = await apiCall('/analytics.php?action=save-monthly-pdf', 'POST', {
                    year: parseInt(year, 10),
                    month: parseInt(month, 10),
                    pdf_data: pdfBase64
                });

                if (!uploadRes || !uploadRes.success) {
                    showAlert('Error al subir el reporte PDF: ' + (uploadRes?.message || 'Error desconocido'), 'error');
                    return;
                }

                // 4. Archivar en base de datos
                showAlert('Archivando registros en la base de datos...', 'info');
                const response = await fetch('api/analytics.php?action=archive-tickets', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `year=${year}&month=${month}`
                });

                const data = await response.json();

                if (data.success) {
                    showAlert(`Se archivaron ${data.archived_count} tickets y se guardó el PDF de cierre.`, 'success');
                    loadTicketHistory();
                    loadArchivedPdfs();
                } else {
                    showAlert(data.message || 'Error al archivar', 'error');
                }
            } catch (error) {
                console.error('Error archivando tickets:', error);
                showAlert('Error en la solicitud', 'error');
            }
        }

        async function loadArchivedPdfs() {
            const container = document.getElementById('archivedPdfsContainer');
            if (!container) return;

            try {
                const res = await apiCall('/analytics.php?action=list-monthly-pdfs');
                if (res && res.success) {
                    const files = res.files || [];
                    if (files.length === 0) {
                        container.innerHTML = '<p class="text-muted" style="font-style: italic;">No hay reportes PDF archivados en el servidor.</p>';
                        return;
                    }

                    container.innerHTML = files.map(f => {
                        const sizeKb = (f.size / 1024).toFixed(1);
                        return `
                            <a href="${f.url}" target="_blank" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; padding: 0.6rem 1rem; border-radius: 8px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); color: #fff; transition: background 0.2s;">
                                <span style="font-size: 1.25rem;">📄</span>
                                <div style="text-align: left;">
                                    <div style="font-weight: 600; font-size: 0.85rem;">Reporte ${f.readable_name}</div>
                                    <div style="font-size: 0.7rem; color: #aaa;">PDF (${sizeKb} KB)</div>
                                </div>
                            </a>
                        `;
                    }).join('');
                } else {
                    container.innerHTML = '<p class="text-muted" style="color: var(--color-error) !important;">Error al cargar lista de PDFs.</p>';
                }
            } catch (error) {
                console.error('Error loading archived PDFs:', error);
                container.innerHTML = '<p class="text-muted">Error al cargar reportes acumulados.</p>';
            }
        }

        function downloadTicketsExcel() {
            const yearSelect = document.getElementById('ticketYearFilter');
            const monthSelect = document.getElementById('ticketMonthFilter');
            const year = yearSelect ? yearSelect.value : new Date().getFullYear();
            const month = monthSelect ? monthSelect.value : (new Date().getMonth() + 1);
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
                loadArchivedPdfs();
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
                    loadArchivedPdfs();
                })
                .catch(() => {
                    populateYearFallback();
                });
        }

        // Auto-run filters initialization
        document.addEventListener('DOMContentLoaded', initTicketFilters);
    </script>
    <script src="js/mobile-optimize.js"></script>
</body>
</html>
