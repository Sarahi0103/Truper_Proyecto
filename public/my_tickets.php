<?php
require_once '../config/config.php';
require_login();

$userId = $_SESSION['user_id'];
$userName = htmlspecialchars($_SESSION['name'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Mis Tickets - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css?v=2.2">
    <link rel="stylesheet" href="css/theme.css?v=2.5">
    <link rel="stylesheet" href="css/responsive-complete.css?v=2.2">
    <style>
        .tickets-wrapper { display: grid; grid-template-columns: 1fr 3fr; gap: 2rem; margin: 2rem 0; }
        .tickets-sidebar { display: flex; flex-direction: column; gap: 1rem; }
        .stat-box { background: linear-gradient(135deg, var(--color-naranja) 0%, var(--color-orange) 100%); color: white; padding: 1.5rem; border-radius: 8px; }
        .stat-number { font-size: 2rem; font-weight: 700; }
        .stat-label { font-size: 0.9rem; opacity: 0.9; margin-top: 0.5rem; }
        .tickets-list { display: flex; flex-direction: column; gap: 1rem; }
        .ticket-card { border: 1px solid var(--ui-border); border-radius: 8px; padding: 1.5rem; background: var(--ui-surface); cursor: pointer; transition: all 0.3s ease; }
        .ticket-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); border-color: var(--color-naranja); }
        .ticket-folio { font-size: 1.2rem; font-weight: 700; color: var(--color-naranja); font-family: monospace; margin-bottom: 0.5rem; }
        .ticket-info { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.9rem; }
        .ticket-info-item { display: flex; flex-direction: column; }
        .ticket-info-label { color: var(--ui-text-muted); text-transform: uppercase; font-size: 0.75rem; margin-bottom: 0.25rem; }
        .ticket-info-value { font-weight: 600; }
        .ticket-status { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; }
        .status-completed { background: var(--color-green); color: white; }
        .status-pending { background: var(--color-yellow); color: white; }
        .ticket-actions { display: flex; gap: 0.5rem; margin-top: 1rem; }
        .btn-action { padding: 0.5rem 1rem; border: none; border-radius: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 600; }
        .btn-download { background: var(--color-blue); color: white; }
        .btn-whatsapp { background: #25D366; color: white; }
        .btn-view { background: var(--ui-border); color: var(--ui-text); }
        .ticket-detail { background: var(--ui-surface); border: 1px solid var(--ui-border); border-radius: 8px; padding: 2rem; }
        .detail-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; border-bottom: 2px solid var(--ui-border); padding-bottom: 1rem; }
        .detail-title { font-size: 1.5rem; font-weight: 700; }
        .detail-folio { font-family: monospace; color: var(--color-naranja); font-size: 1.2rem; }
        .detail-items { margin: 1.5rem 0; }
        .detail-item { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 1rem; padding: 1rem; background: var(--ui-surface-soft); border-radius: 6px; margin-bottom: 0.5rem; }
        .detail-item-name { font-weight: 600; }
        .detail-item-qty { text-align: center; }
        .detail-item-price { text-align: right; }
        .detail-item-total { text-align: right; font-weight: 600; color: var(--color-naranja); }
        .detail-totals { display: flex; flex-direction: column; gap: 0.5rem; margin-top: 2rem; padding-top: 1rem; border-top: 2px solid var(--ui-border); }
        .detail-total-row { display: flex; justify-content: space-between; font-size: 0.95rem; }
        .detail-total-row.grand { font-size: 1.2rem; font-weight: 700; color: var(--color-naranja); margin-top: 1rem; }
        .history-section { margin-top: 2rem; }
        .history-title { font-size: 1rem; font-weight: 700; margin-bottom: 1rem; color: var(--ui-text); }
        .history-item { display: flex; gap: 1rem; padding: 0.75rem; background: var(--ui-surface-soft); border-left: 3px solid var(--color-naranja); margin-bottom: 0.5rem; border-radius: 4px; }
        .history-icon { color: var(--color-naranja); font-weight: 700; }
        .history-info { flex: 1; }
        .history-action { font-weight: 600; }
        .history-time { font-size: 0.8rem; color: var(--ui-text-muted); margin-top: 0.25rem; }
        .modal-whatsapp { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; }
        .modal-whatsapp.active { display: flex; }
        .modal-content { background: var(--ui-surface); color: var(--ui-text); border: 1px solid var(--ui-border); padding: 2rem; border-radius: 12px; max-width: 400px; width: 90%; }
        .modal-title { font-size: 1.2rem; font-weight: 700; margin-bottom: 1rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 0.5rem; }
        .form-group input { width: 100%; padding: 0.75rem; border: 1px solid var(--ui-border); border-radius: 6px; }
        .modal-actions { display: flex; gap: 1rem; margin-top: 1.5rem; }
        .btn-send { background: #25D366; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; flex: 1; }
        .btn-cancel { background: var(--ui-border); color: var(--ui-text); padding: 0.75rem 1.5rem; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; flex: 1; }
        .empty-state { text-align: center; padding: 3rem; color: var(--ui-text-muted); }
        .empty-icon { font-size: 3rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
<div class="page-wrapper">
    <header class="main-header">
        <div class="header-content">
            <h1>📋 Mis Tickets de Compra</h1>
            <p>Historial completo de tus transacciones con Truper</p>
        </div>
    </header>

    <div class="container">
        <div class="tickets-wrapper">
            <!-- Sidebar izquierdo -->
            <div class="tickets-sidebar">
                <!-- Estadísticas -->
                <div class="stat-box">
                    <div class="stat-number" id="statTotal">0</div>
                    <div class="stat-label">Total de Compras</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number" id="statAmount">$0</div>
                    <div class="stat-label">Monto Total</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number" id="statReturns">0</div>
                    <div class="stat-label">Devoluciones</div>
                </div>

                <!-- Filtros -->
                <div style="background: var(--ui-surface); padding: 1.5rem; border-radius: 8px;">
                    <h3 style="margin-bottom: 1rem;">Filtros</h3>
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <input type="text" id="filterFolio" placeholder="Buscar folio..." style="padding: 0.5rem; border: 1px solid var(--ui-border); border-radius: 6px;">
                        <select id="filterType" style="padding: 0.5rem; border: 1px solid var(--ui-border); border-radius: 6px;">
                            <option value="">Todos los tipos</option>
                            <option value="sale">Venta</option>
                            <option value="return">Devolución</option>
                        </select>
                        <button class="btn btn-primary" onclick="applyFilters()" style="width: 100%;">Filtrar</button>
                    </div>
                </div>
            </div>

            <!-- Contenido principal -->
            <div>
                <!-- Lista de tickets -->
                <div id="ticketsListContainer" class="tickets-list">
                    <div class="empty-state">
                        <div class="empty-icon">📊</div>
                        <p>Cargando tus tickets...</p>
                    </div>
                </div>

                <!-- Detalles de ticket -->
                <div id="ticketDetailContainer" style="display: none;">
                    <div class="ticket-detail" id="ticketDetail"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal WhatsApp -->
<div class="modal-whatsapp" id="modalWhatsApp">
    <div class="modal-content">
        <h2 class="modal-title">📱 Enviar por WhatsApp</h2>
        <form id="formWhatsApp" onsubmit="sendViaWhatsApp(event)">
            <div class="form-group">
                <label>Número de teléfono</label>
                <input type="tel" id="whatsappPhone" placeholder="+56 9 XXXX XXXX" required>
            </div>
            <div class="modal-actions">
                <button type="submit" class="btn-send">Enviar</button>
                <button type="button" class="btn-cancel" onclick="closeWhatsAppModal()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
window.csrfToken = '<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>';
</script>
<script src="js/main.js?v=2.6"></script>
<script>
const apiBase = '/api/client_tickets.php';
let currentTicketId = null;

// Cargar tickets al iniciar
document.addEventListener('DOMContentLoaded', () => {
    loadTickets();
    loadStatistics();
});

async function loadTickets() {
    const response = await apiCall(`${apiBase}?action=list&page=1&per_page=20`, 'GET');
    
    if (!response.success) {
        alert('Error: ' + response.message);
        return;
    }
    
    const container = document.getElementById('ticketsListContainer');
    
    if (response.tickets.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">🛒</div>
                <p>Aún no tienes compras registradas</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = response.tickets.map(ticket => `
        <div class="ticket-card" onclick="viewTicketDetails(${ticket.id})">
            <div class="ticket-folio">${ticket.folio}</div>
            <div class="ticket-info">
                <div class="ticket-info-item">
                    <span class="ticket-info-label">Tipo</span>
                    <span class="ticket-info-value">${ticket.ticket_type === 'sale' ? '🛍️ Venta' : '↩️ Devolución'}</span>
                </div>
                <div class="ticket-info-item">
                    <span class="ticket-info-label">Total</span>
                    <span class="ticket-info-value">$${parseFloat(ticket.total_amount).toFixed(2)}</span>
                </div>
                <div class="ticket-info-item">
                    <span class="ticket-info-label">Estado</span>
                    <span class="ticket-status status-${ticket.payment_status}">${ticket.payment_status}</span>
                </div>
                <div class="ticket-info-item">
                    <span class="ticket-info-label">Fecha</span>
                    <span class="ticket-info-value">${new Date(ticket.issued_date).toLocaleDateString('es-CL')}</span>
                </div>
            </div>
            <div class="ticket-actions">
                <button class="btn-action btn-view" onclick="event.stopPropagation(); viewTicketDetails(${ticket.id})">👁️ Ver</button>
                <button class="btn-action btn-download" onclick="event.stopPropagation(); downloadPDF(${ticket.id})">📥 PDF</button>
                <button class="btn-action btn-whatsapp" onclick="event.stopPropagation(); openWhatsAppModal(${ticket.id})">💬 WhatsApp</button>
            </div>
        </div>
    `).join('');
}

async function loadStatistics() {
    const response = await apiCall(`${apiBase}?action=stats`, 'GET');
    
    if (response.success && response.statistics) {
        const stats = response.statistics;
        document.getElementById('statTotal').textContent = stats.total_tickets || 0;
        document.getElementById('statAmount').textContent = '$' + (parseFloat(stats.total_spent) || 0).toFixed(2);
        document.getElementById('statReturns').textContent = stats.returns || 0;
    }
}

async function viewTicketDetails(ticketId) {
    const response = await apiCall(`${apiBase}?action=get&ticket_id=${ticketId}`, 'GET');
    
    if (!response.success) {
        alert('Error: ' + response.message);
        return;
    }
    
    const ticket = response.ticket;
    currentTicketId = ticketId;
    
    // Obtener historial
    const historyResponse = await apiCall(`${apiBase}?action=history&ticket_id=${ticketId}`, 'GET');
    const history = historyResponse.history || [];
    
    const detailHTML = `
        <div class="detail-header">
            <div>
                <div class="detail-title">Comprobante de Venta</div>
                <div class="detail-folio">Folio: ${ticket.folio}</div>
            </div>
            <button class="btn btn-primary" onclick="closeDetail()">✕ Cerrar</button>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
                <p><strong>Cliente:</strong> ${ticket.customer_name || 'N/A'}</p>
                <p><strong>Email:</strong> ${ticket.email || 'N/A'}</p>
            </div>
            <div>
                <p><strong>Fecha:</strong> ${new Date(ticket.issued_date).toLocaleDateString('es-CL')} ${new Date(ticket.issued_date).toLocaleTimeString('es-CL')}</p>
                <p><strong>Estado:</strong> <span class="ticket-status status-${ticket.payment_status}">${ticket.payment_status}</span></p>
            </div>
        </div>
        
        <h3 style="margin-bottom: 1rem;">Items</h3>
        <div class="detail-items">
            ${(ticket.items || []).map(item => `
                <div class="detail-item">
                    <div class="detail-item-name">${item.product_name}</div>
                    <div class="detail-item-qty">${item.quantity}</div>
                    <div class="detail-item-price">$${parseFloat(item.unit_price).toFixed(2)}</div>
                    <div class="detail-item-total">$${parseFloat(item.total).toFixed(2)}</div>
                </div>
            `).join('')}
        </div>
        
        <div class="detail-totals">
            <div class="detail-total-row">
                <span>Subtotal:</span>
                <span>$${parseFloat(ticket.subtotal_amount).toFixed(2)}</span>
            </div>
            <div class="detail-total-row">
                <span>Impuesto:</span>
                <span>$${parseFloat(ticket.tax_amount).toFixed(2)}</span>
            </div>
            <div class="detail-total-row">
                <span>Descuento:</span>
                <span>-$${parseFloat(ticket.discount_amount).toFixed(2)}</span>
            </div>
            <div class="detail-total-row grand">
                <span>TOTAL:</span>
                <span>$${parseFloat(ticket.total_amount).toFixed(2)}</span>
            </div>
        </div>
        
        <div class="history-section">
            <h3 class="history-title">📊 Historial</h3>
            ${history.length === 0 ? '<p style="color: var(--ui-text-muted);">Sin historial</p>' : ''}
            ${history.map(event => `
                <div class="history-item">
                    <div class="history-icon">${getHistoryIcon(event.type)}</div>
                    <div class="history-info">
                        <div class="history-action">${event.action}: ${event.description || ''}</div>
                        <div class="history-time">${new Date(event.timestamp).toLocaleString('es-CL')}</div>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
    
    document.getElementById('ticketDetail').innerHTML = detailHTML;
    document.getElementById('ticketDetailContainer').style.display = 'block';
}

function getHistoryIcon(type) {
    const icons = {
        'audit': '📝',
        'whatsapp': '💬',
        'download': '📥',
        'auto_created': '✨'
    };
    return icons[type] || '🔔';
}

function downloadPDF(ticketId) {
    const token = encodeURIComponent(window.csrfToken || '');
    window.location.href = `/api/client_tickets.php?action=download-pdf&ticket_id=${ticketId}&csrf_token=${token}`;
}

function openWhatsAppModal(ticketId) {
    currentTicketId = ticketId;
    document.getElementById('modalWhatsApp').classList.add('active');
}

function closeWhatsAppModal() {
    document.getElementById('modalWhatsApp').classList.remove('active');
}

async function sendViaWhatsApp(event) {
    event.preventDefault();
    
    const phone = document.getElementById('whatsappPhone').value;
    
    const response = await apiCall(`${apiBase}?action=send-whatsapp`, 'POST', {
        ticket_id: currentTicketId,
        phone_number: phone
    });
    
    if (response.success) {
        handleSuccessResponse(response, {
            scrollTarget: '#ticketsListContainer',
            successMessage: response.message || 'Comprobante enviado a WhatsApp',
            onSuccess: () => {
                closeWhatsAppModal();
                if (response.whatsapp_url) {
                    window.open(response.whatsapp_url, '_blank');
                }
                loadTickets();
            }
        });
    } else {
        showAlert(response.message || 'Error enviando comprobante', 'error');
    }
}

function closeDetail() {
    document.getElementById('ticketDetailContainer').style.display = 'none';
}

function applyFilters() {
    // Implementar filtros si es necesario
    loadTickets();
}
</script>
    <script src="js/mobile-optimize.js"></script>
</body>
</html>
