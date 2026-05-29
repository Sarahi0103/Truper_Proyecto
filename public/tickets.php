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
    <link rel="stylesheet" href="css/styles.css?v=2.1">
    <link rel="stylesheet" href="css/theme.css?v=2.1">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/responsive-complete.css">
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
                <a href="cart.php">Carrito</a>
                <a href="dashboard.php">Dashboard</a>
                <a href="orders.php">Pedidos</a>
                <a href="wholesale.php">Mayoreo</a>
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?><a href="cashier.php">Caja</a><?php endif; ?>
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?><a href="admin_supply.php">Abastecimiento</a><?php endif; ?>
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?><a href="tickets.php" class="active">Tickets</a><?php endif; ?>
                <a href="tasks.php">Tareas</a>
                <a href="analytics.php">Estadísticas</a>
                <a href="profile.php">Perfil</a>
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

    <script src="js/main.js"></script>
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

        async function archiveCurrentMonth() {
            if (!confirm('¿Estás seguro de que quieres archivar los tickets de este mes? Esta acción no se puede deshacer.')) {
                return;
            }

            const yearSelect = document.getElementById('ticketYearFilter');
            const monthSelect = document.getElementById('ticketMonthFilter');
            const year = yearSelect ? yearSelect.value : new Date().getFullYear();
            const month = monthSelect ? monthSelect.value : (new Date().getMonth() + 1);

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
                    showAlert(`Se archivaron ${data.archived_count} tickets correctamente.`, 'success');
                    loadTicketHistory();
                } else {
                    showAlert(data.message || 'Error al archivar', 'error');
                }
            } catch (error) {
                console.error('Error archivando tickets:', error);
                showAlert('Error en la solicitud', 'error');
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

        // Auto-run filters initialization
        document.addEventListener('DOMContentLoaded', initTicketFilters);
    </script>
    <script src="js/mobile-optimize.js"></script>
</body>
</html>
