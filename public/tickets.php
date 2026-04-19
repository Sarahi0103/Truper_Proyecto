<?php
require_once '../config/config.php';
require_admin();

$user_name = htmlspecialchars($_SESSION['name'] ?? 'Administrador', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Tickets - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/theme.css">
    <style>
        .tickets-container { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin: 2rem 0; }
        .tickets-filters { display: flex; gap: 1rem; flex-wrap: wrap; margin: 1rem 0; }
        .tickets-filters input, .tickets-filters select { padding: 0.5rem; border: 1px solid var(--ui-border); border-radius: 6px; }
        .ticket-card { border: 1px solid var(--ui-border); border-radius: 8px; padding: 1.5rem; background: var(--ui-surface); }
        .ticket-folio { font-size: 1.5rem; font-weight: 700; color: var(--color-naranja); font-family: monospace; letter-spacing: 2px; }
        .ticket-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin: 1rem 0; }
        .ticket-label { font-size: 0.85rem; color: var(--ui-text-muted); text-transform: uppercase; }
        .ticket-value { font-weight: 600; color: var(--ui-text); }
        .ticket-status { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; }
        .status-completed { background: var(--color-green); color: white; }
        .status-pending { background: var(--color-yellow); color: white; }
        .status-active { background: var(--color-blue); color: white; }
        .status-archived { background: var(--color-gray); color: white; }
        .ticket-items { margin-top: 1.5rem; }
        .ticket-item { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 0.5rem; padding: 0.75rem; background: var(--ui-surface-soft); border-radius: 6px; margin: 0.5rem 0; font-size: 0.9rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin: 2rem 0; }
        .stat-card { background: linear-gradient(135deg, var(--color-naranja) 0%, var(--color-orange) 100%); color: white; padding: 1.5rem; border-radius: 8px; }
        .stat-number { font-size: 2rem; font-weight: 700; }
        .stat-label { font-size: 0.9rem; opacity: 0.9; }
        .btn-action { padding: 0.5rem 1rem; margin: 0.25rem; border-radius: 6px; border: none; cursor: pointer; font-size: 0.85rem; }
        .btn-download { background: var(--color-blue); color: white; }
        .btn-archive { background: var(--color-gray); color: white; }
        .btn-verify { background: var(--color-green); color: white; }
        .modal-tickets { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; }
        .modal-tickets.active { display: flex; }
        .modal-content { background: white; padding: 2rem; border-radius: 12px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; }
        .form-ticket { display: grid; gap: 1rem; }
        .form-ticket label { font-weight: 600; color: var(--ui-text); }
        .form-ticket input, .form-ticket select, .form-ticket textarea { padding: 0.75rem; border: 1px solid var(--ui-border); border-radius: 6px; font-size: 1rem; }
        .table-tickets { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        .table-tickets th { background: var(--ui-surface); padding: 1rem; text-align: left; font-weight: 700; border-bottom: 2px solid var(--ui-border); }
        .table-tickets td { padding: 1rem; border-bottom: 1px solid var(--ui-border); }
        .table-tickets tr:hover { background: var(--ui-surface-soft); }
    </style>
</head>
<body>
<div class="dashboard-wrapper">
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>Admin Panel</h2>
            <p class="text-muted">Hola, <?php echo $user_name; ?></p>
        </div>
        <nav class="sidebar-nav">
            <a href="admin_supply.php" class="nav-link">📦 Abastecimiento</a>
            <a href="dashboard.php" class="nav-link">📊 Dashboard</a>
            <a href="cashier.php" class="nav-link">💰 Caja</a>
            <a href="tickets.php" class="nav-link active">🎟️ Tickets</a>
            <a href="api/auth.php?action=logout" class="nav-link text-danger">🚪 Cerrar Sesión</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1>🎟️ Gestión de Tickets de Ventas</h1>
            <p>Historial de transacciones, folios únicos y auditoría</p>
        </div>

        <!-- Estadísticas -->
        <section class="stats-grid" id="statsContainer">
            <div class="stat-card">
                <div class="stat-number" id="statTotalTickets">0</div>
                <div class="stat-label">Tickets Este Mes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="statTotalSales">$0</div>
                <div class="stat-label">Total de Ventas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="statReturnCount">0</div>
                <div class="stat-label">Devoluciones</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="statAvgTicket">$0</div>
                <div class="stat-label">Ticket Promedio</div>
            </div>
        </section>

        <!-- Acciones -->
        <div style="display: flex; gap: 1rem; margin-bottom: 2rem;">
            <button class="btn btn-primary" onclick="openCreateTicketModal()">➕ Crear Ticket</button>
            <button class="btn btn-secondary" onclick="downloadTickets()">📥 Descargar CSV</button>
            <button class="btn btn-secondary" onclick="archivePreviousMonth()">📦 Archivar Mes Anterior</button>
        </div>

        <!-- Filtros -->
        <div class="tickets-filters">
            <input type="text" id="filterFolio" placeholder="Buscar por folio..." onchange="applyFilters()">
            <select id="filterType" onchange="applyFilters()">
                <option value="">Todos los tipos</option>
                <option value="sale">Venta</option>
                <option value="return">Devolución</option>
                <option value="adjustment">Ajuste</option>
                <option value="credit">Crédito</option>
            </select>
            <select id="filterStatus" onchange="applyFilters()">
                <option value="">Todos los estados</option>
                <option value="completed">Completado</option>
                <option value="pending">Pendiente</option>
                <option value="failed">Fallido</option>
            </select>
            <input type="date" id="filterStartDate" onchange="applyFilters()">
            <input type="date" id="filterEndDate" onchange="applyFilters()">
        </div>

        <!-- Tabla de Tickets -->
        <table class="table-tickets">
            <thead>
                <tr>
                    <th>Folio</th>
                    <th>Cliente</th>
                    <th>Tipo</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th>Fecha</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="ticketsTableBody">
                <tr><td colspan="7" style="text-align: center; padding: 2rem;">Cargando...</td></tr>
            </tbody>
        </table>

        <!-- Paginación -->
        <div id="paginationContainer" style="display: flex; justify-content: center; gap: 0.5rem; margin: 2rem 0;"></div>
    </main>
</div>

<!-- Modal Crear Ticket -->
<div class="modal-tickets" id="modalCreateTicket">
    <div class="modal-content">
        <h2>Crear Nuevo Ticket</h2>
        <form class="form-ticket" id="formCreateTicket" onsubmit="submitCreateTicket(event)">
            <label>Cliente (ID)</label>
            <input type="number" name="user_id" required>
            
            <label>Tipo de Ticket</label>
            <select name="ticket_type" required>
                <option value="sale">Venta</option>
                <option value="return">Devolución</option>
                <option value="adjustment">Ajuste</option>
                <option value="credit">Crédito</option>
            </select>
            
            <label>Subtotal</label>
            <input type="number" name="subtotal" step="0.01" required>
            
            <label>Impuesto</label>
            <input type="number" name="tax_amount" step="0.01" value="0">
            
            <label>Descuento</label>
            <input type="number" name="discount_amount" step="0.01" value="0">
            
            <label>Total</label>
            <input type="number" name="total_amount" step="0.01" required>
            
            <label>Método de Pago</label>
            <select name="payment_method">
                <option value="cash">Efectivo</option>
                <option value="card">Tarjeta</option>
                <option value="transfer">Transferencia</option>
                <option value="credit">Crédito</option>
            </select>
            
            <label>Notas</label>
            <textarea name="notes" rows="3"></textarea>
            
            <div style="display: flex; gap: 1rem;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">Crear Ticket</button>
                <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeCreateTicketModal()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Ver Detalles -->
<div class="modal-tickets" id="modalViewTicket">
    <div class="modal-content">
        <h2>Detalles del Ticket</h2>
        <div id="ticketDetailsContent"></div>
        <button class="btn btn-secondary" onclick="closeViewTicketModal()" style="width: 100%; margin-top: 1rem;">Cerrar</button>
    </div>
</div>

<script src="js/main.js"></script>
<script>
window.csrfToken = '<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>';
const apiBase = '/api/tickets.php';
let currentPage = 1;

// Cargar tickets al iniciar
document.addEventListener('DOMContentLoaded', () => {
    loadTickets();
    loadStatistics();
});

async function loadTickets(page = 1) {
    const filters = {
        folio: document.getElementById('filterFolio')?.value,
        ticket_type: document.getElementById('filterType')?.value,
        payment_status: document.getElementById('filterStatus')?.value,
        start_date: document.getElementById('filterStartDate')?.value,
        end_date: document.getElementById('filterEndDate')?.value
    };
    
    const params = new URLSearchParams({ page, per_page: 20, ...filters });
    const response = await apiCall(`${apiBase}?action=list&${params}`, 'GET');
    
    if (!response.success) {
        alert('Error: ' + response.message);
        return;
    }
    
    const tbody = document.getElementById('ticketsTableBody');
    tbody.innerHTML = response.tickets.map(ticket => `
        <tr>
            <td><strong style="color: var(--color-naranja); font-family: monospace;">${ticket.folio}</strong></td>
            <td>${ticket.customer_name || 'N/A'}</td>
            <td>${ticket.ticket_type}</td>
            <td>$${parseFloat(ticket.total_amount).toFixed(2)}</td>
            <td><span class="ticket-status status-${ticket.payment_status}">${ticket.payment_status}</span></td>
            <td>${new Date(ticket.issued_date).toLocaleDateString('es-CL')}</td>
            <td>
                <button class="btn-action btn-verify" onclick="viewTicket(${ticket.id})">Ver</button>
                <button class="btn-action btn-download" onclick="downloadTicket('${ticket.folio}')">Descargar</button>
            </td>
        </tr>
    `).join('');
    
    renderPagination(response.pagination);
    currentPage = page;
}

async function loadStatistics() {
    const response = await apiCall(`${apiBase}?action=get-stats`, 'GET');
    
    if (response.success) {
        const stats = response.statistics;
        document.getElementById('statTotalTickets').textContent = stats.ticket_count || 0;
        document.getElementById('statTotalSales').textContent = '$' + (stats.total_sales || 0).toFixed(2);
        document.getElementById('statReturnCount').textContent = stats.return_count || 0;
        const avg = stats.ticket_count > 0 ? (stats.total_sales / stats.ticket_count).toFixed(2) : 0;
        document.getElementById('statAvgTicket').textContent = '$' + avg;
    }
}

function openCreateTicketModal() {
    document.getElementById('modalCreateTicket').classList.add('active');
}

function closeCreateTicketModal() {
    document.getElementById('modalCreateTicket').classList.remove('active');
    document.getElementById('formCreateTicket').reset();
}

async function submitCreateTicket(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData);
    data.csrf_token = window.csrfToken;
    
    const response = await apiCall(`${apiBase}?action=create`, 'POST', data);
    
    if (response.success) {
        alert('✅ Ticket creado: ' + response.folio);
        closeCreateTicketModal();
        loadTickets();
        loadStatistics();
    } else {
        alert('❌ Error: ' + response.message);
    }
}

async function viewTicket(ticketId) {
    // Para implementar: obtener detalles del ticket y mostrar
    alert('Funcionalidad de ver detalles en desarrollo');
}

function downloadTicket(folio) {
    window.location.href = `/api/tickets.php?action=download&folio=${folio}`;
}

function downloadTickets() {
    alert('Descarga de CSV en desarrollo');
}

async function archivePreviousMonth() {
    if (!confirm('¿Archivar tickets del mes anterior? Esta acción no se puede deshacer.')) return;
    
    const response = await apiCall(`${apiBase}?action=archive-previous-month`, 'POST', { csrf_token: window.csrfToken });
    
    if (response.success) {
        alert('✅ ' + response.message);
        loadTickets();
        loadStatistics();
    } else {
        alert('❌ Error: ' + response.message);
    }
}

function applyFilters() {
    loadTickets(1);
}

function renderPagination(pagination) {
    const container = document.getElementById('paginationContainer');
    container.innerHTML = '';
    
    for (let i = 1; i <= pagination.total_pages; i++) {
        const btn = document.createElement('button');
        btn.textContent = i;
        btn.className = i === currentPage ? 'btn btn-primary' : 'btn btn-secondary';
        btn.onclick = () => loadTickets(i);
        container.appendChild(btn);
    }
}

function closeViewTicketModal() {
    document.getElementById('modalViewTicket').classList.remove('active');
}
</script>
</body>
</html>
