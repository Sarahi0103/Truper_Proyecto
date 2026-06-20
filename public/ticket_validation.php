<?php
require_once '../config/config.php';
require_admin();

$user_name = htmlspecialchars($_SESSION['name'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
$user_role = htmlspecialchars($_SESSION['role'] ?? 'admin', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Validación de Tickets - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css?v=4.2">
    <link rel="stylesheet" href="css/theme.css?v=2.5">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/responsive-complete.css?v=5.0">
    <style>
        .validation-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .search-section {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .search-input-wrapper {
            position: relative;
            max-width: 600px;
            margin: 0 auto;
        }

        .search-input {
            width: 100%;
            padding: 1.5rem 2rem;
            font-size: 1.25rem;
            border: 2px solid #333;
            border-radius: 8px;
            background: #000;
            color: #fff;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #ff7f00;
            box-shadow: 0 0 20px rgba(255, 127, 0, 0.3);
        }

        .search-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 8px;
            margin-top: 0.5rem;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .search-suggestions.active {
            display: block;
        }

        .suggestion-item {
            padding: 1rem;
            cursor: pointer;
            border-bottom: 1px solid #333;
            transition: background 0.2s ease;
        }

        .suggestion-item:hover {
            background: rgba(255, 127, 0, 0.1);
        }

        .suggestion-item:last-child {
            border-bottom: none;
        }

        .ticket-details-panel {
            background: #1a1a1a;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            display: none;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .ticket-details-panel.active {
            display: block;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #333;
        }

        .ticket-folio {
            font-size: 2rem;
            font-weight: bold;
            color: #ff7f00;
        }

        .ticket-status {
            padding: 0.5rem 1.5rem;
            border-radius: 20px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
            border: 1px solid #ffc107;
        }

        .status-picked_up {
            background: rgba(76, 175, 80, 0.2);
            color: #4caf50;
            border: 1px solid #4caf50;
        }

        .status-cancelled {
            background: rgba(244, 67, 54, 0.2);
            color: #f44336;
            border: 1px solid #f44336;
        }

        .status-expired {
            background: rgba(255, 87, 34, 0.2);
            color: #ff5722;
            border: 1px solid #ff5722;
        }

        .customer-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 0.875rem;
            color: #888;
            margin-bottom: 0.5rem;
        }

        .info-value {
            font-size: 1.125rem;
            color: #fff;
            font-weight: 500;
        }

        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }

        .products-table th,
        .products-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #333;
        }

        .products-table th {
            background: rgba(255, 255, 255, 0.05);
            color: #ff7f00;
            font-weight: bold;
        }

        .products-table tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .validation-actions {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid #333;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn-confirm {
            flex: 1;
            padding: 1.5rem 2rem;
            font-size: 1.25rem;
            font-weight: bold;
            background: linear-gradient(135deg, #ff7f00 0%, #ff9500 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(255, 127, 0, 0.4);
        }

        .btn-confirm:disabled {
            background: #555;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-print {
            padding: 1.5rem 2rem;
            font-size: 1rem;
            font-weight: bold;
            background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(74, 144, 226, 0.4);
        }

        .notes-field {
            flex: 1;
        }

        .notes-field textarea {
            width: 100%;
            padding: 1rem;
            font-size: 1rem;
            border: 2px solid #333;
            border-radius: 8px;
            background: #000;
            color: #fff;
            resize: vertical;
            min-height: 80px;
        }

        .notes-field textarea:focus {
            outline: none;
            border-color: #ff7f00;
        }

        .notify-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-left: 1rem;
        }

        .notify-checkbox input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .audit-log {
            background: #1a1a1a;
            border-radius: 12px;
            padding: 2rem;
            margin-top: 2rem;
            display: none;
        }

        .audit-log.active {
            display: block;
        }

        .audit-log h3 {
            color: #ff7f00;
            margin-bottom: 1.5rem;
        }

        .log-entry {
            padding: 1rem;
            margin-bottom: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            border-left: 4px solid #ff7f00;
        }

        .log-entry:last-child {
            margin-bottom: 0;
        }

        .log-action {
            font-weight: bold;
            color: #ff7f00;
            margin-bottom: 0.5rem;
        }

        .log-meta {
            font-size: 0.875rem;
            color: #888;
        }

        .alert-box {
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: none;
        }

        .alert-box.active {
            display: block;
        }

        .alert-success {
            background: rgba(76, 175, 80, 0.2);
            border: 1px solid #4caf50;
            color: #4caf50;
        }

        .alert-error {
            background: rgba(244, 67, 54, 0.2);
            border: 1px solid #f44336;
            color: #f44336;
        }

        .alert-warning {
            background: rgba(255, 193, 7, 0.2);
            border: 1px solid #ffc107;
            color: #ffc107;
        }

        .expiration-warning {
            background: rgba(255, 87, 34, 0.2);
            border: 1px solid #ff5722;
            color: #ff5722;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: none;
        }

        .expiration-warning.active {
            display: block;
        }

        @media (max-width: 768px) {
            .validation-container {
                padding: 1rem;
            }

            .search-section {
                padding: 1.5rem;
            }

            .ticket-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .customer-info {
                grid-template-columns: 1fr;
            }

            .validation-actions {
                flex-direction: column;
            }

            .btn-confirm {
                width: 100%;
            }
        }

        /* Botón de regreso usa estilos globales de styles.css */

        /* Dashboard and global history styles */
        .dashboard-panels {
            margin-top: 2rem;
            animation: slideIn 0.3s ease;
        }
        .dashboard-panels-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
        }
        @media (min-width: 1200px) {
            .dashboard-panels-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .spinner {
            display: inline-block;
            width: 1.5rem;
            height: 1.5rem;
            border: 3px solid rgba(255,127,0,0.3);
            border-radius: 50%;
            border-top-color: #ff7f00;
            animation: spin 1s ease-in-out infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    <header>
        <div class="header-content">
            <a href="dashboard.php" class="logo"><img src="img/logo_truper.1.1.png" alt="Truper" style="height: 40px; width: auto; object-fit: contain;"></a>
            <button class="hamburger-btn" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
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
                        <a href="tickets.php">Tickets</a>
                        <a href="tasks.php">Tareas</a>
                        <a href="gastos.php">Gastos</a>
                        <?php if ($user_role === 'admin'): ?>
                            <a href="analytics.php">Estadísticas</a>
                        <?php endif; ?>
                    </div>
                </div>
            </nav>
        </div>
        <div class="user-menu">
            <div class="user-info">
                <div class="user-name"><?php echo $user_name; ?></div>
                <div class="user-role"><?php echo $user_role === 'employee' ? 'PERSONAL' : 'ADMIN'; ?></div>
            </div>
            <button onclick="confirmLogout('../admin_logout.php')" class="btn-logout">Cerrar Sesión</button>
        </div>
    </header>

    <main>
        <div class="validation-container">
            <!-- ── Back Button ── -->
            <div class="back-header">
                <button onclick="history.back()" class="btn-back">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Regresar
                </button>
            </div>

            <h1 style="color: #ff7f00; margin-bottom: 2rem; text-align: center;">Validación de Tickets en Sucursal</h1>

            <!-- Alert Messages -->
            <div id="alertSuccess" class="alert-box alert-success"></div>
            <div id="alertError" class="alert-box alert-error"></div>
            <div id="alertWarning" class="alert-box alert-warning"></div>

            <!-- Search Section -->
            <div class="search-section">
                <div class="search-input-wrapper">
                    <input type="text" id="searchInput" class="search-input" placeholder="Ingrese folio, nombre del cliente o teléfono..." autocomplete="off">
                    <div id="searchSuggestions" class="search-suggestions"></div>
                </div>
            </div>

            <!-- Dashboard Overview (shown when no ticket is loaded) -->
            <div id="dashboardPanels" class="dashboard-panels" style="display: none;">
                <div class="dashboard-panels-grid">
                    <!-- Pending Tickets Section -->
                    <div class="card" style="background: #1a1a1a; border-radius: 12px; padding: 2rem; box-shadow: 0 8px 32px rgba(0,0,0,0.3); border: 1px solid #333;">
                        <h3 style="color: #ff7f00; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; margin-top: 0;">
                            ⏳ Tickets Pendientes de Validación
                        </h3>
                        <div id="pendingTicketsList" class="text-muted">Cargando...</div>
                    </div>

                    <!-- Global Validation History Section -->
                    <div class="card" style="background: #1a1a1a; border-radius: 12px; padding: 2rem; box-shadow: 0 8px 32px rgba(0,0,0,0.3); border: 1px solid #333;">
                        <h3 style="color: #ff7f00; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; margin-top: 0;">
                            📋 Historial de Validaciones Recientes
                        </h3>
                        <div id="globalHistoryList" class="text-muted">Cargando...</div>
                    </div>
                </div>
            </div>

            <!-- Ticket Details Panel -->
            <div id="ticketDetailsPanel" class="ticket-details-panel">
                <div id="expirationWarning" class="expiration-warning"></div>

                <div class="ticket-header">
                    <div>
                        <div class="ticket-folio" id="ticketFolio"></div>
                        <div style="color: #888; margin-top: 0.5rem;">Fecha: <span id="ticketDate"></span></div>
                    </div>
                    <div id="ticketStatus" class="ticket-status status-pending">PENDIENTE</div>
                </div>

                <div class="customer-info">
                    <div class="info-item">
                        <div class="info-label">Cliente</div>
                        <div class="info-value" id="customerName"></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <div class="info-value" id="customerEmail"></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Teléfono</div>
                        <div class="info-value" id="customerPhone"></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Total</div>
                        <div class="info-value" id="ticketTotal"></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Estado de Pago</div>
                        <div class="info-value" id="paymentStatus"></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Expira</div>
                        <div class="info-value" id="expirationDate"></div>
                    </div>
                </div>

                <h3 style="color: #ff7f00; margin-bottom: 1rem;">Productos</h3>
                <table class="products-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio Unitario</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody id="productsTableBody"></tbody>
                </table>

                <div class="validation-actions">
                    <div class="notes-field">
                        <textarea id="validationNotes" placeholder="Notas adicionales (opcional)..."></textarea>
                    </div>
                    <div class="notify-checkbox">
                        <input type="checkbox" id="notifyCustomer">
                        <label for="notifyCustomer">Notificar al cliente</label>
                    </div>
                    <div class="action-buttons">
                        <button id="btnConfirm" class="btn-confirm">Confirmar Entrega Física</button>
                        <button id="btnPrint" class="btn-print" onclick="printTicket()">🖨️ Imprimir Ticket</button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="js/main.js?v=2.7"></script>
    <script src="js/mobile-optimize.js"></script>
    <script src="js/modals.js?v=4.2"></script>
    <script>
        window.csrfToken = '<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8"); ?>';

        function escapeHtml(text) {
            if (text === null || text === undefined) return '';
            return text.toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        let currentTicket = null;
        let searchTimeout = null;

        const searchInput = document.getElementById('searchInput');
        const searchSuggestions = document.getElementById('searchSuggestions');
        const ticketDetailsPanel = document.getElementById('ticketDetailsPanel');
        const btnConfirm = document.getElementById('btnConfirm');
        const validationNotes = document.getElementById('validationNotes');
        const notifyCustomer = document.getElementById('notifyCustomer');

        // Search functionality
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();

            if (query.length < 3) {
                searchSuggestions.classList.remove('active');
                return;
            }

            searchTimeout = setTimeout(() => {
                searchTickets(query);
            }, 300);
        });

        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const query = this.value.trim();
                if (query.length >= 3) {
                    searchSuggestions.classList.remove('active');
                    loadTicketDetails(query);
                }
            }
        });

        async function searchTickets(query) {
            try {
                const response = await fetch(`api/ticket_validation.php?action=search&query=${encodeURIComponent(query)}`);
                const data = await response.json();

                if (data.success && data.tickets.length > 0) {
                    displaySuggestions(data.tickets);
                } else {
                    searchSuggestions.classList.remove('active');
                }
            } catch (error) {
                console.error('Error searching tickets:', error);
            }
        }

        function displaySuggestions(tickets) {
            searchSuggestions.innerHTML = tickets.map(ticket => `
                <div class="suggestion-item" data-folio="${escapeHtml(ticket.folio)}">
                    <div style="font-weight: bold; color: #ff7f00;">${escapeHtml(ticket.folio)}</div>
                    <div style="color: #fff;">${escapeHtml(ticket.customer_name)}</div>
                    <div style="font-size: 0.875rem; color: #888;">$${parseFloat(ticket.total_amount).toFixed(2)} - ${escapeHtml(ticket.item_count)} productos</div>
                </div>
            `).join('');

            searchSuggestions.classList.add('active');

            // Add click handlers
            document.querySelectorAll('.suggestion-item').forEach(item => {
                item.addEventListener('click', function() {
                    const folio = this.getAttribute('data-folio');
                    searchInput.value = folio;
                    searchSuggestions.classList.remove('active');
                    loadTicketDetails(folio);
                });
            });
        }

        async function loadTicketDetails(folio) {
            try {
                const response = await fetch(`api/ticket_validation.php?action=details&folio=${encodeURIComponent(folio)}`);
                const data = await response.json();

                if (data.success) {
                    currentTicket = data.ticket;
                    displayTicketDetails(data.ticket);
                    loadAuditLog(data.ticket.id);
                } else {
                    showAlert('error', data.message || 'Ticket no encontrado');
                    ticketDetailsPanel.classList.remove('active');
                    const auditLog = document.getElementById('auditLog');
                    if (auditLog) auditLog.classList.remove('active');
                }
            } catch (error) {
                console.error('Error loading ticket details:', error);
                showAlert('error', 'Error al cargar detalles del ticket');
            }
        }

        function displayTicketDetails(ticket) {
            document.getElementById('ticketFolio').textContent = ticket.folio;
            document.getElementById('ticketDate').textContent = new Date(ticket.issued_date).toLocaleDateString('es-ES');
            document.getElementById('customerName').textContent = ticket.customer_name || 'N/A';
            document.getElementById('customerEmail').textContent = ticket.email || 'N/A';
            document.getElementById('customerPhone').textContent = ticket.phone || 'N/A';
            document.getElementById('ticketTotal').textContent = '$' + parseFloat(ticket.total_amount).toFixed(2);
            document.getElementById('paymentStatus').textContent = ticket.payment_status === 'completed' ? 'PAGADO' : 'PENDIENTE';
            document.getElementById('expirationDate').textContent = ticket.expiration_date ? new Date(ticket.expiration_date).toLocaleDateString('es-ES') : 'N/A';

            // Status badge
            const statusElement = document.getElementById('ticketStatus');
            statusElement.className = 'ticket-status';
            statusElement.classList.add(`status-${ticket.pickup_status}`);
            statusElement.textContent = getStatusText(ticket.pickup_status);

            // Expiration warning
            const expirationWarning = document.getElementById('expirationWarning');
            if (ticket.expiration_date) {
                const expirationDate = new Date(ticket.expiration_date);
                const today = new Date();
                const daysUntilExpiration = Math.ceil((expirationDate - today) / (1000 * 60 * 60 * 24));

                if (daysUntilExpiration <= 5 && daysUntilExpiration > 0) {
                    expirationWarning.textContent = `⚠️ Este ticket expira en ${daysUntilExpiration} días`;
                    expirationWarning.classList.add('active');
                } else if (daysUntilExpiration <= 0) {
                    expirationWarning.textContent = '⚠️ Este ticket ha expirado';
                    expirationWarning.classList.add('active');
                } else {
                    expirationWarning.classList.remove('active');
                }
            } else {
                expirationWarning.classList.remove('active');
            }

            // Products table
            const productsTableBody = document.getElementById('productsTableBody');
            productsTableBody.innerHTML = ticket.items.map(item => `
                <tr>
                    <td>${escapeHtml(item.product_name)}</td>
                    <td>${escapeHtml(item.quantity)}</td>
                    <td>$${parseFloat(item.unit_price).toFixed(2)}</td>
                    <td>$${parseFloat(item.total).toFixed(2)}</td>
                </tr>
            `).join('');

            // Enable/disable confirm button based on eligibility
            btnConfirm.disabled = !ticket.eligibility.eligible;
            if (!ticket.eligibility.eligible) {
                btnConfirm.textContent = ticket.eligibility.reason;
            } else {
                btnConfirm.textContent = 'Confirmar Entrega Física';
            }

            ticketDetailsPanel.classList.add('active');

            // Hide dashboard overview
            const dashboardPanels = document.getElementById('dashboardPanels');
            if (dashboardPanels) {
                dashboardPanels.style.display = 'none';
            }
            // Update search input value
            if (searchInput) {
                searchInput.value = ticket.folio;
            }
        }

        function getStatusText(status) {
            const statusMap = {
                'pending': 'PENDIENTE',
                'picked_up': 'ENTREGADO',
                'cancelled': 'CANCELADO',
                'expired': 'EXPIRADO'
            };
            return statusMap[status] || status.toUpperCase();
        }

        async function loadAuditLog(ticketId) {
            try {
                const response = await fetch(`api/ticket_validation.php?action=history&ticket_id=${ticketId}`);
                const data = await response.json();

                if (data.success) {
                    displayAuditLog(data.logs);
                }
            } catch (error) {
                console.error('Error loading audit log:', error);
            }
        }

        function displayAuditLog(logs) {
            const auditLogContent = document.getElementById('auditLogContent');
            if (!auditLogContent) return;

            if (logs.length === 0) {
                auditLogContent.innerHTML = '<div style="color: #888;">No hay historial de validaciones</div>';
            } else {
                auditLogContent.innerHTML = logs.map(log => `
                    <div class="log-entry">
                        <div class="log-action">${escapeHtml(getActionText(log.action))}</div>
                        <div class="log-meta">
                            ${log.admin_name ? `Por: ${escapeHtml(log.admin_name)}` : ''} | 
                            ${new Date(log.created_at).toLocaleString('es-ES')}
                        </div>
                        ${log.notes ? `<div style="margin-top: 0.5rem; color: #fff;">${escapeHtml(log.notes)}</div>` : ''}
                    </div>
                `).join('');
            }

            const auditLog = document.getElementById('auditLog');
            if (auditLog) auditLog.classList.add('active');
        }

        function getActionText(action) {
            const actionMap = {
                'attempt': 'Intento de validación',
                'validated': 'Validado',
                'cancelled': 'Cancelado',
                'deleted': 'Eliminado',
                'reactivated': 'Reactivado'
            };
            return actionMap[action] || action;
        }

        // Confirm validation
        btnConfirm.addEventListener('click', async function() {
            if (!currentTicket) return;

            const folio = currentTicket.folio;
            const notes = validationNotes.value.trim();
            const notify = notifyCustomer.checked;

            btnConfirm.disabled = true;
            btnConfirm.textContent = 'Procesando...';

            try {
                const response = await fetch('api/ticket_validation.php?action=validate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': window.csrfToken || ''
                    },
                    body: JSON.stringify({
                        folio: folio,
                        notes: notes,
                        csrf_token: window.csrfToken || ''
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('success', 'Ticket validado exitosamente');
                    validationNotes.value = '';
                    notifyCustomer.checked = false;
                    loadTicketDetails(folio); // Reload to show updated status

                    // Implement notification if notify is checked
                    if (notify && currentTicket.email) {
                        sendNotificationEmail(currentTicket, folio);
                    }
                } else {
                    showAlert('error', data.message || 'Error al validar ticket');
                }
            } catch (error) {
                console.error('Error validating ticket:', error);
                showAlert('error', 'Error al validar ticket');
            } finally {
                btnConfirm.disabled = false;
                btnConfirm.textContent = 'Confirmar Entrega Física';
            }
        });

        async function sendNotificationEmail(ticket, folio) {
            try {
                const response = await fetch('api/ticket_validation.php?action=notify', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': window.csrfToken || ''
                    },
                    body: JSON.stringify({
                        folio: folio,
                        email: ticket.email,
                        customer_name: ticket.customer_name,
                        csrf_token: window.csrfToken || ''
                    })
                });
                const data = await response.json();
                if (data.success) {
                    showAlert('success', 'Notificación enviada al cliente');
                } else {
                    showAlert('warning', 'No se pudo enviar notificación: ' + (data.message || 'Error desconocido'));
                }
            } catch (error) {
                console.error('Error sending notification:', error);
                showAlert('warning', 'Error al enviar notificación');
            }
        }

        function printTicket() {
            if (!currentTicket) {
                showAlert('warning', 'No hay ticket seleccionado para imprimir');
                return;
            }

            const printContent = `
                <html>
                <head>
                    <title>Ticket ${currentTicket.folio}</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        .ticket-header { text-align: center; margin-bottom: 20px; }
                        .ticket-info { margin-bottom: 15px; }
                        .ticket-items { margin: 20px 0; }
                        .ticket-items table { width: 100%; border-collapse: collapse; }
                        .ticket-items th, .ticket-items td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        .ticket-footer { margin-top: 30px; text-align: center; font-size: 12px; }
                        .status-badge { padding: 5px 10px; border-radius: 5px; display: inline-block; }
                    </style>
                </head>
                <body>
                    <div class="ticket-header">
                        <h1>TRUPER</h1>
                        <h2>Ticket de Validación</h2>
                        <p><strong>Folio:</strong> ${currentTicket.folio}</p>
                        <p><strong>Fecha:</strong> ${new Date(currentTicket.issued_date).toLocaleDateString('es-MX')}</p>
                    </div>
                    
                    <div class="ticket-info">
                        <p><strong>Cliente:</strong> ${currentTicket.customer_name || 'N/A'}</p>
                        <p><strong>Email:</strong> ${currentTicket.email || 'N/A'}</p>
                        <p><strong>Teléfono:</strong> ${currentTicket.phone || 'N/A'}</p>
                        <p><strong>Total:</strong> $${parseFloat(currentTicket.total_amount).toFixed(2)}</p>
                        <p><strong>Estado de Pago:</strong> ${currentTicket.payment_status === 'completed' ? 'Pagado' : 'Pendiente'}</p>
                        <p><strong>Estado de Entrega:</strong> 
                            <span class="status-badge" style="background: ${currentTicket.pickup_status === 'picked_up' ? '#4caf50' : '#ffc107'}; color: white;">
                                ${currentTicket.pickup_status === 'picked_up' ? 'Entregado' : 'Pendiente'}
                            </span>
                        </p>
                    </div>
                    
                    <div class="ticket-items">
                        <h3>Productos</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Cantidad</th>
                                    <th>Precio Unitario</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${currentTicket.items.map(item => `
                                    <tr>
                                        <td>${item.product_name || item.name || 'N/A'}</td>
                                        <td>${item.quantity || 1}</td>
                                        <td>$${parseFloat(item.unit_price || item.price || 0).toFixed(2)}</td>
                                        <td>$${parseFloat((item.quantity || 1) * (item.unit_price || item.price || 0)).toFixed(2)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="ticket-footer">
                        <p>Este documento es comprobante de validación de ticket.</p>
                        <p>Fecha de impresión: ${new Date().toLocaleString('es-MX')}</p>
                        <p>Truper Platform - Sistema de Gestión</p>
                    </div>
                </body>
                </html>
            `;

            const printWindow = window.open('', '_blank');
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.print();
        }

        function showAlert(type, message) {
            const alertSuccess = document.getElementById('alertSuccess');
            const alertError = document.getElementById('alertError');
            const alertWarning = document.getElementById('alertWarning');

            alertSuccess.classList.remove('active');
            alertError.classList.remove('active');
            alertWarning.classList.remove('active');

            if (type === 'success') {
                alertSuccess.textContent = message;
                alertSuccess.classList.add('active');
            } else if (type === 'error') {
                alertError.textContent = message;
                alertError.classList.add('active');
            } else if (type === 'warning') {
                alertWarning.textContent = message;
                alertWarning.classList.add('active');
            }

            // Auto-hide after 5 seconds
            setTimeout(() => {
                alertSuccess.classList.remove('active');
                alertError.classList.remove('active');
                alertWarning.classList.remove('active');
            }, 5000);
        }

        function goBack() {
            const ticketDetailsPanel = document.getElementById('ticketDetailsPanel');
            if (ticketDetailsPanel && ticketDetailsPanel.classList.contains('active')) {
                ticketDetailsPanel.classList.remove('active');
                const auditLog = document.getElementById('auditLog');
                if (auditLog) auditLog.classList.remove('active');
                
                searchInput.value = '';
                
                const dashboardPanels = document.getElementById('dashboardPanels');
                if (dashboardPanels) {
                    dashboardPanels.style.display = 'block';
                }
                
                const url = new URL(window.location.href);
                url.searchParams.delete('folio');
                window.history.pushState({}, '', url);
                
                loadDashboardOverview();
            } else {
                history.back();
            }
        }

        async function loadDashboardOverview() {
            const dashboardPanels = document.getElementById('dashboardPanels');
            if (!dashboardPanels) return;
            
            dashboardPanels.style.display = 'block';
            
            const pendingList = document.getElementById('pendingTicketsList');
            const historyList = document.getElementById('globalHistoryList');
            
            if (pendingList) pendingList.innerHTML = '<div style="text-align:center; padding:1.5rem;"><span class="spinner"></span> Cargando pendientes...</div>';
            if (historyList) historyList.innerHTML = '<div style="text-align:center; padding:1.5rem;"><span class="spinner"></span> Cargando historial...</div>';
            
            // Load Pending Tickets
            try {
                const response = await fetch('api/ticket_validation.php?action=pending');
                const data = await response.json();
                
                if (data.success && Array.isArray(data.tickets)) {
                    const pending = data.tickets.filter(t => t.pickup_status === 'pending');
                    
                    if (pending.length === 0) {
                        pendingList.innerHTML = '<p class="text-muted" style="text-align:center; padding:1.5rem; margin:0;">No hay tickets pendientes de validación.</p>';
                    } else {
                        pendingList.innerHTML = `
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                                    <thead>
                                        <tr style="border-bottom: 2px solid #333; text-align: left;">
                                            <th style="padding: 0.75rem; color: #ff7f00;">Folio</th>
                                            <th style="padding: 0.75rem; color: #ff7f00;">Cliente</th>
                                            <th style="padding: 0.75rem; color: #ff7f00;">Pago</th>
                                            <th style="padding: 0.75rem; color: #ff7f00;">Fecha</th>
                                            <th style="padding: 0.75rem; color: #ff7f00; text-align: right;">Total</th>
                                            <th style="padding: 0.75rem; color: #ff7f00; text-align: center;">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${pending.map(t => {
                                            const isPaid = t.payment_status === 'completed' || t.payment_status === 'paid';
                                            const payBadge = isPaid
                                                ? `<span style="background:#0f9a0044; border:1px solid #0f9a00; color:#4caf50; padding:0.15rem 0.5rem; border-radius:4px; font-size:0.75rem; font-weight:bold;">Pagado</span>`
                                                : `<span style="background:#ffc10722; border:1px solid #ffc107; color:#ffc107; padding:0.15rem 0.5rem; border-radius:4px; font-size:0.75rem; font-weight:bold;">Pendiente</span>`;
                                            return `
                                            <tr style="border-bottom: 1px solid #2d2d2d;">
                                                <td style="padding: 0.75rem; font-family: monospace; font-weight: bold; color: #ff7f00; cursor: pointer;" onclick="loadTicketDetails('${escapeHtml(t.folio)}')">${escapeHtml(t.folio)}</td>
                                                <td style="padding: 0.75rem;">${escapeHtml(t.customer_name || 'N/A')}</td>
                                                <td style="padding: 0.75rem;">${payBadge}</td>
                                                <td style="padding: 0.75rem; color: #888;">${new Date(t.issued_date).toLocaleDateString('es-MX')}</td>
                                                <td style="padding: 0.75rem; text-align: right; font-weight: bold;">$${parseFloat(t.total_amount || 0).toFixed(2)}</td>
                                                <td style="padding: 0.75rem; text-align: center;">
                                                    <button onclick="loadTicketDetails('${escapeHtml(t.folio)}')" class="btn btn-small" style="background: #ff7f00; color: #fff; border: none; padding: 0.35rem 0.75rem; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 0.8rem; transition: background 0.2s;">Validar</button>
                                                </td>
                                            </tr>
                                        `}).join('')}
                                    </tbody>
                                </table>
                            </div>
                        `;
                    }
                } else {
                    pendingList.innerHTML = '<p class="text-muted" style="text-align:center; padding:1.5rem; margin:0;">Error al cargar pendientes.</p>';
                }
            } catch (err) {
                console.error('Error loading pending overview:', err);
                if (pendingList) pendingList.innerHTML = '<p class="text-muted" style="text-align:center; padding:1.5rem; margin:0;">Error de conexión.</p>';
            }
            
            // Load Global History Log
            try {
                const response = await fetch('api/ticket_validation.php?action=global-history');
                const data = await response.json();
                
                if (data.success && Array.isArray(data.logs)) {
                    if (data.logs.length === 0) {
                        historyList.innerHTML = '<p class="text-muted" style="text-align:center; padding:1.5rem; margin:0;">No hay historial registrado.</p>';
                    } else {
                        historyList.innerHTML = `
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                                    <thead>
                                        <tr style="border-bottom: 2px solid #333; text-align: left;">
                                            <th style="padding: 0.75rem; color: #ff7f00;">Folio</th>
                                            <th style="padding: 0.75rem; color: #ff7f00;">Cliente</th>
                                            <th style="padding: 0.75rem; color: #ff7f00;">Acción</th>
                                            <th style="padding: 0.75rem; color: #ff7f00;">Admin</th>
                                            <th style="padding: 0.75rem; color: #ff7f00; text-align: right;">Fecha/Hora</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${data.logs.map(log => {
                                            let badgeColor = '#888';
                                            let actionText = log.action;
                                            if (log.action === 'validated') { badgeColor = '#4caf50'; actionText = 'Validado'; }
                                            else if (log.action === 'deleted') { badgeColor = '#f44336'; actionText = 'Eliminado'; }
                                            else if (log.action === 'cancelled') { badgeColor = '#f44336'; actionText = 'Cancelado'; }
                                            else if (log.action === 'reactivated') { badgeColor = '#ffc107'; actionText = 'Reactivado'; }
                                            else if (log.action === 'attempt') { badgeColor = '#2196f3'; actionText = 'Intento'; }
                                            
                                            return `
                                                <tr style="border-bottom: 1px solid #2d2d2d;">
                                                    <td style="padding: 0.75rem; font-family: monospace; font-weight: bold; color: #ff7f00; cursor: pointer;" onclick="loadTicketDetails('${escapeHtml(log.ticket_folio)}')">${escapeHtml(log.ticket_folio)}</td>
                                                    <td style="padding: 0.75rem;">${escapeHtml(log.ticket_customer_name || 'N/A')}</td>
                                                    <td style="padding: 0.75rem;">
                                                        <span style="display: inline-block; padding: 0.15rem 0.45rem; border-radius: 4px; font-size: 0.75rem; font-weight: bold; background: ${badgeColor}22; border: 1px solid ${badgeColor}; color: ${badgeColor};">
                                                            ${actionText}
                                                        </span>
                                                    </td>
                                                    <td style="padding: 0.75rem;">${escapeHtml(log.admin_name || 'N/A')}</td>
                                                    <td style="padding: 0.75rem; text-align: right; color: #888;">${new Date(log.created_at).toLocaleString('es-MX')}</td>
                                                </tr>
                                            `;
                                        }).join('')}
                                    </tbody>
                                </table>
                            </div>
                        `;
                    }
                } else {
                    historyList.innerHTML = '<p class="text-muted" style="text-align:center; padding:1.5rem; margin:0;">Error al cargar historial.</p>';
                }
            } catch (err) {
                console.error('Error loading global history overview:', err);
                if (historyList) historyList.innerHTML = '<p class="text-muted" style="text-align:center; padding:1.5rem; margin:0;">Error de conexión.</p>';
            }
        }

        // Auto-load ticket if folio is in URL query parameters
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const folioParam = urlParams.get('folio');
            if (folioParam) {
                searchInput.value = folioParam;
                loadTicketDetails(folioParam);
            } else {
                loadDashboardOverview();
            }
        });

        // Close suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchSuggestions.contains(e.target)) {
                searchSuggestions.classList.remove('active');
            }
        });
    </script>
</body>
</html>
