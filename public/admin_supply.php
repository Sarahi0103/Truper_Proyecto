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
    <title>Abastecimiento - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
<header>
    <div class="header-content">
        <a href="dashboard.php" class="logo"><img src="images/truper-logo.svg" alt="Truper"></a>
        <nav class="nav-menu">
            <a href="index.php">Productos</a>
            <a href="orders.php">Pedidos</a>
            <a href="cashier.php">Caja</a>
            <a href="admin_supply.php" class="active">Abastecimiento</a>
            <a href="analytics.php">Estadisticas</a>
            <a href="profile.php">Perfil</a>
        </nav>
    </div>
    <div class="user-menu">
        <div class="user-info"><div class="user-name"><?php echo $user_name; ?></div><div class="user-role">Admin</div></div>
        <a href="index.php" class="btn btn-small btn-ghost">Ver portada</a>
        <button class="btn-logout" onclick="window.location.href='api/auth.php?action=logout'">Cerrar Sesion</button>
    </div>
</header>

<main>
    <div class="container-fluid">
        <h1>Panel de Abastecimiento</h1>
        <p class="text-muted">Control de existencias, calendario de proveedores, ordenes de compra y historico.</p>

        <div class="grid grid-2 mt-3">
            <div class="card">
                <div class="card-body">
                    <h3>Acceso rápido a clientes</h3>
                    <p class="text-muted">Registrar y consultar el acceso del cliente sin usar contraseña.</p>
                    <button class="btn btn-primary" onclick="goToClientsTab()">Ir al registro de cliente</button>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <h3>Inicio de sesión del cliente</h3>
                    <p class="text-muted">El cliente entra con código único y fecha de nacimiento obligatoria.</p>
                    <a class="btn btn-secondary" href="login.php">Abrir inicio de sesión</a>
                </div>
            </div>
        </div>

        <div class="tabs mt-3">
            <button class="tab-button active" data-tab="stockTab">Stock</button>
            <button class="tab-button" data-tab="calendarTab">Calendario</button>
            <button class="tab-button" data-tab="supplierOrderTab">Orden Proveedor</button>
            <button class="tab-button" data-tab="clientsTab">Clientes</button>
            <button class="tab-button" data-tab="historyTab">Historico</button>
        </div>

        <section id="stockTab" class="tab-content active">
            <div class="card"><div class="card-body">
                <h3>Control de Existencias</h3>
                <table>
                    <thead><tr><th>SKU</th><th>Producto</th><th>Categoria</th><th>Stock</th><th>Nivel Reorden</th><th>Estatus</th></tr></thead>
                    <tbody id="stockRows"><tr><td colspan="6">Cargando...</td></tr></tbody>
                </table>
            </div></div>
        </section>

        <section id="calendarTab" class="tab-content">
            <div class="grid grid-2">
                <div class="card"><div class="card-body">
                    <h3>Registrar visita de proveedor</h3>
                    <div class="form-group"><label>Proveedor</label><input id="supplierName" type="text"></div>
                    <div class="form-group"><label>Fecha y hora</label><input id="visitDate" type="datetime-local"></div>
                    <div class="form-group"><label>Notas</label><textarea id="visitNotes"></textarea></div>
                    <button class="btn btn-primary" onclick="createVisit()">Guardar visita</button>
                </div></div>
                <div class="card"><div class="card-body">
                    <h3>Agenda</h3>
                    <div id="calendarList" class="text-muted">Cargando...</div>
                </div></div>
            </div>
        </section>

        <section id="supplierOrderTab" class="tab-content">
            <div class="card"><div class="card-body">
                <h3>Orden de Proveedor (ticket logistica)</h3>
                <div class="grid grid-2">
                    <div class="form-group"><label>Proveedor</label><input id="poSupplier" type="text"></div>
                    <div class="form-group"><label>Fecha recepcion</label><input id="poDate" type="date"></div>
                </div>
                <div class="grid grid-3">
                    <div class="form-group"><label>SKU</label><input id="poSku" type="text"></div>
                    <div class="form-group"><label>Cantidad</label><input id="poQty" type="number" min="1" value="1"></div>
                    <div class="form-group"><label>Costo estimado</label><input id="poCost" type="number" min="0" step="0.01" value="0"></div>
                </div>
                <button class="btn btn-secondary" onclick="addPoItem()">Agregar item</button>
                <div id="poItems" class="mt-2"></div>
                <button class="btn btn-primary mt-2" onclick="createSupplierOrder()">Generar orden y ticket</button>

                <h4 class="mt-4">Ordenes registradas</h4>
                <table>
                    <thead><tr><th>Folio</th><th>Proveedor</th><th>Recepcion</th><th>Total</th><th>Ticket</th></tr></thead>
                    <tbody id="supplierRows"><tr><td colspan="5">Cargando...</td></tr></tbody>
                </table>
            </div></div>
        </section>

        <section id="clientsTab" class="tab-content">
            <div class="card"><div class="card-body">
                <h3>Registrar Cliente (Admin)</h3>
                <p class="text-muted">Crea clientes y genera su código único para identificación rápida.</p>

                <div class="grid grid-2">
                    <div class="form-group"><label>Nombre</label><input id="clientFirstName" type="text"></div>
                    <div class="form-group"><label>Apellido</label><input id="clientLastName" type="text"></div>
                </div>
                <div class="grid grid-2">
                    <div class="form-group"><label>Teléfono (para login)</label><input id="clientPhone" type="text" placeholder="+52 33..."></div>
                    <div class="form-group"><label>Email (opcional)</label><input id="clientEmail" type="email" placeholder="cliente@email.com"></div>
                </div>
                <div class="grid grid-2">
                    <div class="form-group"><label>Empresa (opcional)</label><input id="clientCompany" type="text"></div>
                    <div class="form-group"><label>Fecha de nacimiento</label><input id="clientBirthdate" type="date" required></div>
                </div>

                <p class="text-muted">El cliente iniciará sesión con su código único y su fecha de nacimiento.</p>

                <button class="btn btn-primary" onclick="createClientByAdmin()">Registrar cliente</button>

                <div id="clientCreateResult" class="mt-3"></div>
            </div></div>
        </section>

        <section id="historyTab" class="tab-content">
            <div class="card"><div class="card-body">
                <h3>Historico de Transacciones</h3>
                <table>
                    <thead><tr><th>Tipo</th><th>Folio/Ref</th><th>Fecha</th><th>Datos</th></tr></thead>
                    <tbody id="historyRows"><tr><td colspan="4">Cargando...</td></tr></tbody>
                </table>
            </div></div>
        </section>
    </div>
</main>

<script src="js/main.js"></script>
<script>
let supplierOrderItems = [];

function escapeHtml(v) {
    return String(v || '').replace(/[&<>"']/g, function(m) {
        return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m];
    });
}

async function loadStock() {
    const res = await apiCall('/admin_supply.php?action=stock');
    const body = document.getElementById('stockRows');
    if (!res || !res.success || !Array.isArray(res.items)) {
        body.innerHTML = '<tr><td colspan="6">Sin datos</td></tr>';
        return;
    }
    body.innerHTML = res.items.map(i => {
        const low = Number(i.stock_quantity) <= Number(i.reorder_level);
        return `<tr>
            <td>${escapeHtml(i.sku)}</td>
            <td>${escapeHtml(i.name)}</td>
            <td>${escapeHtml(i.category || '')}</td>
            <td>${escapeHtml(i.stock_quantity)}</td>
            <td>${escapeHtml(i.reorder_level)}</td>
            <td>${low ? '<span class="badge badge-danger">Reabastecer</span>' : '<span class="badge badge-success">OK</span>'}</td>
        </tr>`;
    }).join('');
}

async function createVisit() {
    const payload = {
        supplier_name: document.getElementById('supplierName').value,
        visit_datetime: document.getElementById('visitDate').value,
        notes: document.getElementById('visitNotes').value
    };
    const res = await apiCall('/admin_supply.php?action=calendar-create', 'POST', payload);
    if (res && res.success) showAlert(res.message, 'success'); else if (res) showAlert(res.message, 'error');
    loadCalendar();
}

async function loadCalendar() {
    const res = await apiCall('/admin_supply.php?action=calendar-list');
    const box = document.getElementById('calendarList');
    if (!res || !res.success || !Array.isArray(res.items) || res.items.length === 0) {
        box.innerHTML = '<p class="text-muted">Sin visitas registradas.</p>';
        return;
    }
    box.innerHTML = res.items.map(i => `<div class="task-item"><strong>${escapeHtml(i.supplier_name)}</strong><div>${escapeHtml(i.visit_datetime)}</div><div class="text-muted">${escapeHtml(i.notes || '')}</div></div>`).join('');
}

function addPoItem() {
    const sku = document.getElementById('poSku').value.trim();
    const quantity = Number(document.getElementById('poQty').value || 0);
    const estimated_cost = Number(document.getElementById('poCost').value || 0);
    if (!sku || quantity <= 0) {
        showAlert('SKU y cantidad son obligatorios', 'warning');
        return;
    }
    supplierOrderItems.push({ sku, quantity, estimated_cost });
    document.getElementById('poSku').value = '';
    renderPoItems();
}

function renderPoItems() {
    const box = document.getElementById('poItems');
    if (supplierOrderItems.length === 0) {
        box.innerHTML = '<p class="text-muted">No hay items</p>';
        return;
    }
    box.innerHTML = '<ul>' + supplierOrderItems.map((i, idx) => `<li>${escapeHtml(i.sku)} | ${i.quantity} | $${i.estimated_cost.toFixed(2)} <button class="btn btn-small btn-danger" onclick="removePoItem(${idx})">Quitar</button></li>`).join('') + '</ul>';
}

function removePoItem(index) {
    supplierOrderItems = supplierOrderItems.filter((_, i) => i !== index);
    renderPoItems();
}

async function createSupplierOrder() {
    const payload = {
        supplier_name: document.getElementById('poSupplier').value,
        expected_date: document.getElementById('poDate').value,
        items: supplierOrderItems
    };
    const res = await apiCall('/admin_supply.php?action=supplier-order-create', 'POST', payload);
    if (!res || !res.success) {
        if (res) showAlert(res.message, 'error');
        return;
    }
    showAlert(res.message, 'success');
    supplierOrderItems = [];
    renderPoItems();
    if (res.ticket_url) window.open(res.ticket_url, '_blank');
    loadSupplierOrders();
    loadHistory();
}

async function loadSupplierOrders() {
    const res = await apiCall('/admin_supply.php?action=supplier-order-list');
    const body = document.getElementById('supplierRows');
    if (!res || !res.success || !Array.isArray(res.items) || res.items.length === 0) {
        body.innerHTML = '<tr><td colspan="5">Sin ordenes</td></tr>';
        return;
    }
    body.innerHTML = res.items.map(i => `<tr>
        <td>${escapeHtml(i.folio)}</td>
        <td>${escapeHtml(i.supplier_name)}</td>
        <td>${escapeHtml(i.expected_date)}</td>
        <td>$${Number(i.total_estimated || 0).toFixed(2)}</td>
        <td><a class="btn btn-small btn-primary" href="/ticket_supplier.php?id=${i.id}" target="_blank">Imprimir</a></td>
    </tr>`).join('');
}

async function loadHistory() {
    const res = await apiCall('/admin_supply.php?action=history');
    const body = document.getElementById('historyRows');
    if (!res || !res.success || !Array.isArray(res.items) || res.items.length === 0) {
        body.innerHTML = '<tr><td colspan="4">Sin registros</td></tr>';
        return;
    }
    body.innerHTML = res.items.map(i => `<tr>
        <td>${escapeHtml(i.transaction_type)}</td>
        <td>${escapeHtml(i.reference_folio)}</td>
        <td>${escapeHtml(i.created_at)}</td>
        <td><small>${escapeHtml(i.data_json || '')}</small></td>
    </tr>`).join('');
}

async function createClientByAdmin() {
    const payload = {
        first_name: document.getElementById('clientFirstName').value,
        last_name: document.getElementById('clientLastName').value,
        phone: document.getElementById('clientPhone').value,
        email: document.getElementById('clientEmail').value,
        company_name: document.getElementById('clientCompany').value,
        birthdate: document.getElementById('clientBirthdate').value || null
    };

    const res = await apiCall('/admin_clients.php?action=create', 'POST', payload);
    const box = document.getElementById('clientCreateResult');

    if (!res || !res.success) {
        if (box) {
            box.innerHTML = `<div class="alert alert-error">${escapeHtml((res && res.message) ? res.message : 'No fue posible registrar al cliente')}</div>`;
        }
        return;
    }

    if (box) {
        box.innerHTML = `
            <div class="alert alert-success">
                Cliente registrado correctamente.<br>
                <strong>Código único:</strong> ${escapeHtml(res.client.user_code || 'N/A')}<br>
                <strong>Login con teléfono:</strong> ${escapeHtml(res.client.phone || '')}
            </div>
        `;
    }

    showAlert('Cliente registrado correctamente', 'success');
}

function goToClientsTab() {
    const tabButton = document.querySelector('[data-tab="clientsTab"]');
    if (tabButton) {
        tabButton.click();
    }
    const section = document.getElementById('clientsTab');
    if (section) {
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

document.addEventListener('DOMContentLoaded', function () {
    setupTabs();
    renderPoItems();
    loadStock();
    loadCalendar();
    loadSupplierOrders();
    loadHistory();
});
</script>
</body>
</html>
