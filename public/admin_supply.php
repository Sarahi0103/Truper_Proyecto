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
    <link rel="stylesheet" href="css/theme.css">
    <style>
        .grid-4 { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
        .calendar-weekdays,
        .calendar-days { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
        .calendar-weekday { text-align: center; font-weight: 700; color: #6b7280; font-size: 12px; }
        .calendar-day { border: 1px solid #e5e7eb; border-radius: 10px; min-height: 58px; padding: 6px; background: #fff; }
        .calendar-day-empty { background: #f9fafb; border-style: dashed; }
        .calendar-day-number { font-weight: 700; font-size: 13px; color: #111827; }
        .calendar-day-visits { margin-top: 4px; font-size: 11px; color: #b45309; }
        .calendar-day-has-visits { border-color: #f59e0b; background: #fffbeb; }
    </style>
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
            <div class="card mb-3"><div class="card-body">
                <h3>Agregar Producto</h3>
                <p class="text-muted">Registra nuevos productos y opcionalmente sube su imagen.</p>

                <div class="grid grid-3">
                    <div class="form-group"><label>SKU</label><input id="newProductSku" type="text" maxlength="100"></div>
                    <div class="form-group"><label>Nombre</label><input id="newProductName" type="text" maxlength="255"></div>
                    <div class="form-group"><label>Categoría</label><input id="newProductCategory" type="text" maxlength="100" placeholder="Material eléctrico"></div>
                </div>

                <div class="grid grid-3">
                    <div class="form-group"><label>Precio</label><input id="newProductPrice" type="number" min="0" step="0.01" value="0"></div>
                    <div class="form-group"><label>Stock inicial</label><input id="newProductStock" type="number" min="0" step="1" value="50"></div>
                    <div class="form-group"><label>Nivel reorden</label><input id="newProductReorder" type="number" min="0" step="1" value="10"></div>
                </div>

                <div class="grid grid-2">
                    <div class="form-group"><label>Código de barras (opcional)</label><input id="newProductBarcode" type="text" maxlength="100"></div>
                    <div class="form-group">
                        <label>Imagen de referencia</label>
                        <select id="newProductImageRef">
                            <option value="images/products/default-product.svg">Imagen por defecto</option>
                        </select>
                        <small class="text-muted">Selecciona una imagen ya existente del sitio.</small>
                    </div>
                </div>

                <div class="form-group"><label>Descripción</label><textarea id="newProductDescription" rows="3"></textarea></div>

                <button class="btn btn-primary" onclick="createProductByAdmin()">Guardar producto</button>
                <div id="productCreateResult" class="mt-3"></div>
            </div></div>

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
                    <h3>Calendario mensual</h3>
                    <div class="d-flex justify-between align-center">
                        <button class="btn btn-small btn-ghost" onclick="changeCalendarMonth(-1)">Mes anterior</button>
                        <strong id="calendarMonthLabel">Mes</strong>
                        <button class="btn btn-small btn-ghost" onclick="changeCalendarMonth(1)">Mes siguiente</button>
                    </div>
                    <div id="calendarGrid" class="mt-2"></div>
                    <div id="calendarList" class="text-muted mt-2">Cargando...</div>
                </div></div>
            </div>
        </section>

        <section id="supplierOrderTab" class="tab-content">
            <div class="card mb-3"><div class="card-body">
                <h3>Asignar producto a proveedor</h3>
                <p class="text-muted">Un mismo producto puede estar ligado a varios proveedores.</p>
                <div class="grid grid-4">
                    <div class="form-group">
                        <label>Producto</label>
                        <select id="spProduct"></select>
                    </div>
                    <div class="form-group"><label>Proveedor</label><input id="spSupplier" type="text" placeholder="Proveedor A"></div>
                    <div class="form-group"><label>SKU proveedor (opcional)</label><input id="spSupplierSku" type="text"></div>
                    <div class="form-group"><label>Costo unitario</label><input id="spUnitCost" type="number" min="0" step="0.01" value="0"></div>
                </div>
                <button class="btn btn-primary" onclick="createSupplierProductLink()">Guardar asignación</button>
                <div id="supplierProductResult" class="mt-2"></div>
                <div id="supplierProductList" class="mt-2 text-muted">Cargando asignaciones...</div>
            </div></div>

            <div class="card"><div class="card-body">
                <h3>Orden de Proveedor (ticket logistica)</h3>
                <div class="grid grid-2">
                    <div class="form-group"><label>Proveedor</label><input id="poSupplier" type="text"></div>
                    <div class="form-group"><label>Fecha recepcion</label><input id="poDate" type="date"></div>
                </div>
                <div class="grid grid-4">
                    <div class="form-group"><label>Producto proveedor</label><select id="poMappedProduct"></select></div>
                    <div class="form-group"><label>Cantidad</label><input id="poQty" type="number" min="1" value="1"></div>
                    <div class="form-group"><label>Costo estimado</label><input id="poCost" type="number" min="0" step="0.01" value="0"></div>
                    <div class="form-group"><label>&nbsp;</label><button class="btn btn-secondary" onclick="addMappedProductToOrder()">Agregar item</button></div>
                </div>
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

let calendarVisits = [];
let calendarMonthCursor = new Date(new Date().getFullYear(), new Date().getMonth(), 1);

function formatDateTimeLocal(dateValue) {
    const d = new Date(dateValue);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleString('es-MX', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function renderCalendarMonth() {
    const label = document.getElementById('calendarMonthLabel');
    const grid = document.getElementById('calendarGrid');
    const list = document.getElementById('calendarList');
    if (!label || !grid || !list) return;

    const year = calendarMonthCursor.getFullYear();
    const month = calendarMonthCursor.getMonth();
    label.textContent = calendarMonthCursor.toLocaleDateString('es-MX', { month: 'long', year: 'numeric' });

    const firstDay = new Date(year, month, 1);
    const startWeekDay = (firstDay.getDay() + 6) % 7;
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    const visitMap = {};
    calendarVisits.forEach((visit) => {
        const d = new Date(visit.visit_datetime);
        if (Number.isNaN(d.getTime())) return;
        if (d.getFullYear() !== year || d.getMonth() !== month) return;
        const key = d.getDate();
        if (!visitMap[key]) visitMap[key] = [];
        visitMap[key].push(visit);
    });

    const weekNames = ['L', 'M', 'M', 'J', 'V', 'S', 'D'];
    let html = '<div class="calendar-weekdays">' + weekNames.map(w => `<div class="calendar-weekday">${w}</div>`).join('') + '</div>';
    html += '<div class="calendar-days">';

    for (let i = 0; i < startWeekDay; i += 1) {
        html += '<div class="calendar-day calendar-day-empty"></div>';
    }

    for (let day = 1; day <= daysInMonth; day += 1) {
        const count = (visitMap[day] || []).length;
        html += `<div class="calendar-day ${count > 0 ? 'calendar-day-has-visits' : ''}">`;
        html += `<div class="calendar-day-number">${day}</div>`;
        if (count > 0) {
            html += `<div class="calendar-day-visits">${count} visita${count > 1 ? 's' : ''}</div>`;
        }
        html += '</div>';
    }

    html += '</div>';
    grid.innerHTML = html;

    const monthVisits = calendarVisits.filter((visit) => {
        const d = new Date(visit.visit_datetime);
        return !Number.isNaN(d.getTime()) && d.getFullYear() === year && d.getMonth() === month;
    }).sort((a, b) => new Date(a.visit_datetime) - new Date(b.visit_datetime));

    if (monthVisits.length === 0) {
        list.innerHTML = '<p class="text-muted">Sin visitas para este mes.</p>';
        return;
    }

    list.innerHTML = monthVisits.map(i => `<div class="task-item"><strong>${escapeHtml(i.supplier_name)}</strong><div>${escapeHtml(formatDateTimeLocal(i.visit_datetime))}</div><div class="text-muted">${escapeHtml(i.notes || '')}</div></div>`).join('');
}

function changeCalendarMonth(offset) {
    calendarMonthCursor = new Date(calendarMonthCursor.getFullYear(), calendarMonthCursor.getMonth() + offset, 1);
    renderCalendarMonth();
}

async function loadCalendar() {
    const res = await apiCall('/admin_supply.php?action=calendar-list');
    if (!res || !res.success || !Array.isArray(res.items)) {
        calendarVisits = [];
        renderCalendarMonth();
        return;
    }
    calendarVisits = res.items;
    renderCalendarMonth();
}

function addMappedProductToOrder() {
    const select = document.getElementById('poMappedProduct');
    const quantity = Number(document.getElementById('poQty').value || 0);
    const estimated_cost = Number(document.getElementById('poCost').value || 0);

    if (!select || !select.value || quantity <= 0) {
        showAlert('Selecciona producto proveedor y cantidad', 'warning');
        return;
    }

    const opt = select.options[select.selectedIndex];
    const supplier_product_id = Number(opt.value || 0);
    const product_name = opt.getAttribute('data-product-name') || '';
    const sku = opt.getAttribute('data-sku') || '';

    supplierOrderItems.push({ supplier_product_id, product_name, sku, quantity, estimated_cost });
    renderPoItems();
}

function renderPoItems() {
    const box = document.getElementById('poItems');
    if (supplierOrderItems.length === 0) {
        box.innerHTML = '<p class="text-muted">No hay items</p>';
        return;
    }
    box.innerHTML = '<ul>' + supplierOrderItems.map((i, idx) => `<li>${escapeHtml(i.product_name || i.sku)} | ${i.quantity} | $${Number(i.estimated_cost || 0).toFixed(2)} <button class="btn btn-small btn-danger" onclick="removePoItem(${idx})">Quitar</button></li>`).join('') + '</ul>';
}

function removePoItem(index) {
    supplierOrderItems = supplierOrderItems.filter((_, i) => i !== index);
    renderPoItems();
}

async function loadSupplierProducts() {
    const res = await apiCall('/admin_supply.php?action=supplier-products-list');
    const listBox = document.getElementById('supplierProductList');
    const productSelect = document.getElementById('spProduct');

    if (productSelect) {
        const stockRes = await apiCall('/admin_supply.php?action=stock');
        if (stockRes && stockRes.success && Array.isArray(stockRes.items)) {
            productSelect.innerHTML = '<option value="">Selecciona producto...</option>' + stockRes.items
                .map((p) => `<option value="${Number(p.id)}">${escapeHtml(p.sku)} | ${escapeHtml(p.name)}</option>`)
                .join('');
        }
    }

    if (!res || !res.success || !Array.isArray(res.items)) {
        if (listBox) listBox.innerHTML = '<p class="text-muted">Sin asignaciones.</p>';
        return;
    }

    if (listBox) {
        if (res.items.length === 0) {
            listBox.innerHTML = '<p class="text-muted">Sin asignaciones.</p>';
        } else {
            listBox.innerHTML = '<ul>' + res.items.map((i) => `<li>${escapeHtml(i.supplier_name)} -> ${escapeHtml(i.sku)} ${escapeHtml(i.product_name || '')} (${escapeHtml(i.supplier_sku || 'sin SKU')}) $${Number(i.unit_cost || 0).toFixed(2)}</li>`).join('') + '</ul>';
        }
    }
}

async function createSupplierProductLink() {
    const payload = {
        product_id: Number(document.getElementById('spProduct').value || 0),
        supplier_name: document.getElementById('spSupplier').value,
        supplier_sku: document.getElementById('spSupplierSku').value,
        unit_cost: Number(document.getElementById('spUnitCost').value || 0)
    };

    const resultBox = document.getElementById('supplierProductResult');
    const res = await apiCall('/admin_supply.php?action=supplier-product-create', 'POST', payload);
    if (!res || !res.success) {
        if (resultBox) resultBox.innerHTML = `<div class="alert alert-error">${escapeHtml((res && res.message) ? res.message : 'No fue posible guardar')}</div>`;
        return;
    }

    if (resultBox) resultBox.innerHTML = `<div class="alert alert-success">${escapeHtml(res.message || 'Asignacion guardada')}</div>`;
    await loadSupplierProducts();
    await loadMappedProductsBySupplier();
}

async function loadMappedProductsBySupplier() {
    const supplier = document.getElementById('poSupplier').value.trim();
    const select = document.getElementById('poMappedProduct');
    if (!select) return;

    if (!supplier) {
        select.innerHTML = '<option value="">Captura proveedor...</option>';
        return;
    }

    const res = await apiCall(`/admin_supply.php?action=supplier-products-by-supplier&supplier_name=${encodeURIComponent(supplier)}`);
    if (!res || !res.success || !Array.isArray(res.items) || res.items.length === 0) {
        select.innerHTML = '<option value="">Sin productos para proveedor</option>';
        return;
    }

    select.innerHTML = '<option value="">Selecciona producto...</option>' + res.items.map((i) => {
        const label = `${i.sku} | ${i.product_name} | ${i.supplier_sku || 'sin SKU prov.'}`;
        return `<option value="${Number(i.id)}" data-product-name="${escapeHtml(i.product_name)}" data-sku="${escapeHtml(i.sku)}">${escapeHtml(label)}</option>`;
    }).join('');
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

async function loadProductImageReferences() {
    const select = document.getElementById('newProductImageRef');
    if (!select) return;

    const res = await apiCall('/admin_supply.php?action=product-images');
    if (!res || !res.success || !Array.isArray(res.images)) {
        return;
    }

    const current = select.value;
    select.innerHTML = '';
    res.images.forEach((img) => {
        const option = document.createElement('option');
        option.value = img;
        option.textContent = img;
        select.appendChild(option);
    });

    if (Array.from(select.options).some((o) => o.value === current)) {
        select.value = current;
    }
}

async function createProductByAdmin() {
    const payload = {
        sku: document.getElementById('newProductSku').value || '',
        name: document.getElementById('newProductName').value || '',
        category: document.getElementById('newProductCategory').value || '',
        description: document.getElementById('newProductDescription').value || '',
        price: document.getElementById('newProductPrice').value || '0',
        stock_quantity: document.getElementById('newProductStock').value || '50',
        reorder_level: document.getElementById('newProductReorder').value || '10',
        barcode: document.getElementById('newProductBarcode').value || '',
        image_url: document.getElementById('newProductImageRef').value || 'images/products/default-product.svg'
    };

    const box = document.getElementById('productCreateResult');

    const res = await apiCall('/admin_supply.php?action=product-create', 'POST', payload);
    if (!res || !res.success) {
        if (box) {
            box.innerHTML = `<div class="alert alert-error">${escapeHtml((res && res.message) ? res.message : 'No fue posible registrar el producto')}</div>`;
        }
        return;
    }

    if (box) {
        box.innerHTML = `<div class="alert alert-success">Producto registrado correctamente: <strong>${escapeHtml(res.product.sku || '')}</strong></div>`;
    }

    showAlert('Producto registrado correctamente', 'success');
    loadStock();
    loadSupplierProducts();
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
    loadSupplierProducts();
    loadMappedProductsBySupplier();
    loadSupplierOrders();
    loadHistory();
    loadProductImageReferences();

    const supplierInput = document.getElementById('poSupplier');
    if (supplierInput) {
        supplierInput.addEventListener('change', loadMappedProductsBySupplier);
        supplierInput.addEventListener('blur', loadMappedProductsBySupplier);
    }
});
</script>
</body>
</html>
