<?php
require_once '../config/config.php';
require_login();

$user_name = htmlspecialchars($_SESSION['name'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
$user_role = htmlspecialchars($_SESSION['role'] ?? 'client', ENT_QUOTES, 'UTF-8');
$is_admin = (($_SESSION['role'] ?? '') === 'admin');
$column_count = $is_admin ? 7 : 6;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Mayoreo - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/theme.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/responsive-complete.css">
    <!-- jsPDF Library for PDF Generation -->
    <script src="js/jspdf.umd.min.js"></script>
    <style>
    /* Wholesale custom styles */
    .product-row-item {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(255, 255, 255, 0.06);
        border-radius: 10px;
        padding: 1rem;
        position: relative;
    }
    
    .wholesale-details {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(255, 255, 255, 0.06);
        border-radius: 8px;
        padding: 0.4rem 0.6rem;
        cursor: pointer;
        font-size: 0.85rem;
        width: 100%;
        max-width: 250px;
        transition: background-color 0.2s ease;
    }
    .wholesale-details:hover {
        background: rgba(255, 255, 255, 0.04);
    }
    .wholesale-details[open] {
        background: rgba(255, 255, 255, 0.05);
    }
    .wholesale-details summary {
        font-weight: 600;
        color: #ff6600;
        outline: none;
    }
    .wholesale-details ul {
        margin-top: 0.5rem;
        padding-left: 1.25rem;
        list-style-type: disc;
        color: #ccc;
        font-size: 0.8rem;
        text-align: left;
    }
    .wholesale-details li {
        margin-bottom: 0.4rem;
        line-height: 1.3;
    }
    
    .actions-cell {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        align-items: center;
    }
    .btn-action-sm {
        padding: 0.3rem 0.6rem;
        font-size: 0.75rem;
        font-weight: 600;
        border-radius: 6px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        text-decoration: none;
        transition: opacity 0.2s ease;
        border: none;
    }
    .btn-action-sm:hover {
        opacity: 0.9;
    }
    .btn-pdf {
        background: #dc2626 !important;
        color: #fff !important;
    }
    .btn-whatsapp {
        background: #25d366 !important;
        color: #fff !important;
    }
    
    .badge {
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        display: inline-block;
    }
    .badge-pending {
        background: rgba(245, 158, 11, 0.15);
        color: #f59e0b;
    }
    .badge-paid {
        background: rgba(16, 185, 129, 0.15);
        color: #10b981;
    }
    
    /* Badge count for notifications */
    .badge-count {
        background: #ff6600;
        color: #fff;
        border-radius: 50%;
        padding: 0.15rem 0.4rem;
        font-size: 0.75rem;
        font-weight: 700;
        margin-left: 0.5rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 18px;
        height: 18px;
        line-height: 1;
    }
    </style>
</head>
<body>
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
                    <a href="wholesale.php" class="active">Mayoreo</a>
                    <a href="profile.php">Perfil</a>
                </div>
            </div>
            <?php if ($isAdmin): ?>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Administración <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content">
                        <a href="cashier.php">Caja</a>
                        <a href="admin_supply.php?nocache=true">Abastecimiento</a>
                        <a href="tickets.php">Tickets</a>
                        <a href="tasks.php">Tareas</a>
                        <a href="analytics.php">Estadísticas</a>
                    </div>
                </div>
            <?php endif; ?>
        </nav>
    </div>
    <div class="user-menu">
        <div class="user-info">
            <div class="user-name"><?php echo $user_name; ?></div>
            <div class="user-role"><?php echo ucfirst($user_role); ?></div>
        </div>
        <button class="btn-logout" onclick="window.location.href='api/auth.php?action=logout'">Cerrar Sesion</button>
    </div>
</header>

<main>
    <div class="container admin-supply-shell">
        <div class="page-hero">
            <div class="module-badge module-admin"><span class="module-glyph">MY</span> Módulo de Mayoreo</div>
            <h1>Solicitud de Mayoreo</h1>
            <p class="text-muted">Solicita condiciones comerciales de volumen para tu negocio y cotiza productos específicos.</p>
        </div>

        <!-- TABS -->
        <div class="tabs" style="margin-bottom: 1.5rem;">
            <button class="tab-button active" data-tab="newWholesaleTab">Crear Solicitud</button>
            <button class="tab-button" data-tab="wholesaleRequestsTab">
                <?php echo $is_admin ? 'Solicitudes Recibidas' : 'Mis Solicitudes'; ?>
                <span id="wholesaleBadge" class="badge-count" style="display: none;">0</span>
            </button>
        </div>

        <!-- TAB CONTENT: CREAR SOLICITUD -->
        <div id="newWholesaleTab" class="tab-content active">
            <form id="wholesaleForm" class="mt-3" onsubmit="submitWholesale(event)">
            <div class="grid grid-2">
                <div class="form-group">
                    <label>Tipo de negocio</label>
                    <input type="text" id="businessType" placeholder="Ej. Ferretería, Distribuidora..." required>
                </div>
                <div class="form-group">
                    <label>Pedido mínimo estimado (unidades)</label>
                    <input type="number" id="minOrder" min="1" value="50" required>
                </div>
            </div>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label>Descuento solicitado (%)</label>
                    <input type="number" id="discountPct" min="1" max="40" value="15" required>
                </div>
                <div class="form-group">
                    <label>Términos de pago</label>
                    <input type="text" id="paymentTerms" value="Contado" required>
                </div>
            </div>

            <!-- Products list selection section -->
            <div class="card mt-3" style="background: rgba(255,255,255,0.01); border: 1px solid rgba(255,255,255,0.08);">
                <div class="card-body">
                    <h3>Productos a Cotizar</h3>
                    <p class="text-muted">Indique los artículos y volúmenes requeridos para aplicar el descuento solicitado.</p>
                    
                    <div id="productsListContainer" class="mt-2">
                        <!-- Dynamic rows will be inserted here -->
                    </div>
                    
                    <button type="button" class="btn btn-secondary mt-2" onclick="addProductRow()">
                        ➕ Agregar Artículo
                    </button>
                </div>
            </div>

            <button class="btn btn-primary mt-3" type="submit" style="width: 100%; max-width: 300px;">
                Enviar solicitud de mayoreo
            </button>
            </form>
        </div>

        <!-- TAB CONTENT: SOLICITUDES RECIBIDAS -->
        <div id="wholesaleRequestsTab" class="tab-content">
            <div class="card mt-4">
                <div class="card-header"><?php echo $is_admin ? 'Solicitudes de Mayoreo Recibidas' : 'Mis Solicitudes de Mayoreo'; ?></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table>
                            <thead>
                            <tr>
                                <?php if ($is_admin): ?><th>Cliente</th><?php endif; ?>
                                <th>Negocio</th>
                                <th>Descuento</th>
                                <th>Términos</th>
                                <th>Productos Detalle</th>
                                <th>Estatus</th>
                                <th>Cotización y Acciones</th>
                            </tr>
                            </thead>
                            <tbody id="wholesaleRows">
                                <tr><td colspan="<?php echo $column_count; ?>">Cargando solicitudes...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
window.companyWhatsapp = '<?php echo htmlspecialchars(whatsapp_phone_digits(), ENT_QUOTES, "UTF-8"); ?>';
function activateWholesaleTab(tabName) {
  const tabButton = document.querySelector(`.tab-button[data-tab="${tabName}"]`);
  if (tabButton) {
    tabButton.click();
  }
}
</script>
<script src="js/main.js"></script>
<script>
let allProducts = [];
let loadedWholesaleRequests = [];

async function loadProductsForForm() {
  const res = await apiCall('/wholesale.php?action=products');
  if (res && res.success) {
    const catalog = Array.isArray(res.catalog_products) ? res.catalog_products : [];
    const marketplace = Array.isArray(res.marketplace_products) ? res.marketplace_products : [];
    
    allProducts = [
      ...catalog.map(p => ({ ...p, type: 'catalog', display_name: `[Catálogo] ${p.name} (${p.sku}) - $${Number(p.unit_price).toFixed(2)}` })),
      ...marketplace.map(p => ({ ...p, type: 'marketplace', display_name: `[Marketplace] ${p.name} (${p.sku}) - $${Number(p.unit_price).toFixed(2)}` }))
    ];
  }
  
  // Add first row by default after loading
  const container = document.getElementById('productsListContainer');
  if (container && container.children.length === 0) {
    addProductRow();
  }
}

function addProductRow() {
  const container = document.getElementById('productsListContainer');
  if (!container) return;
  
  const div = document.createElement('div');
  div.className = 'product-row-item';
  div.style.display = 'grid';
  div.style.gridTemplateColumns = '3fr 1fr auto';
  div.style.gap = '0.75rem';
  div.style.alignItems = 'end';
  div.style.marginBottom = '0.75rem';
  
  const optionsHtml = allProducts.map(p => 
    `<option value="${p.id}">
      ${p.display_name}
    </option>`
  ).join('');
  
  div.innerHTML = `
    <div class="form-group" style="margin-bottom: 0;">
      <label>Buscar y Seleccionar Producto</label>
      <input type="text" class="form-control prod-search-input" placeholder="🔍 Filtrar por nombre o SKU..." oninput="filterRowProducts(this)" style="margin-bottom: 0.5rem; font-size: 0.9rem; padding: 0.5rem 0.75rem;">
      <select class="form-control prod-select" required style="width: 100%;">
        <option value="">-- Seleccione un artículo --</option>
        ${optionsHtml}
      </select>
    </div>
    <div class="form-group" style="margin-bottom: 0;">
      <label>Cantidad</label>
      <input type="number" class="form-control prod-qty" min="1" value="50" required style="width: 100%;">
    </div>
    <button type="button" class="btn btn-danger" onclick="this.parentElement.remove()" style="padding: 0.65rem; height: 42px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; line-height: 1;">
      🗑️
    </button>
  `;
  container.appendChild(div);
}

function filterRowProducts(input) {
  const query = input.value.toLowerCase().trim();
  const select = input.closest('.form-group')?.querySelector('.prod-select');
  if (!select) return;
  
  const currentValue = select.value;
  
  select.innerHTML = '<option value="">-- Seleccione un artículo --</option>';
  
  const filtered = allProducts.filter(p => 
    String(p.name || '').toLowerCase().includes(query) || 
    String(p.sku || '').toLowerCase().includes(query)
  );
  
  select.innerHTML += filtered.map(p => 
    `<option value="${p.id}" ${p.id == currentValue ? 'selected' : ''}>
      ${p.display_name}
    </option>`
  ).join('');
  
  if (currentValue && !filtered.some(p => p.id == currentValue)) {
    const originalProd = allProducts.find(p => p.id == currentValue);
    if (originalProd) {
      select.innerHTML += `<option value="${originalProd.id}" selected>
        ${originalProd.display_name}
      </option>`;
    }
  }
}

async function submitWholesale(e) {
  e.preventDefault();
  
  const products = [];
  const rows = document.querySelectorAll('.product-row-item');
  rows.forEach(row => {
    const select = row.querySelector('.prod-select');
    const qtyInput = row.querySelector('.prod-qty');
    if (select && select.value) {
      const pId = parseInt(select.value, 10);
      const product = allProducts.find(p => p.id == pId);
      if (product) {
        products.push({
          product_id: pId,
          product_type: product.type,
          quantity: parseInt(qtyInput.value, 10)
        });
      }
    }
  });
  
  if (products.length === 0) {
    showAlert('Por favor, añada al menos un producto a su solicitud', 'error');
    return;
  }
  
  const payload = {
    business_type: document.getElementById('businessType').value,
    min_order_quantity: document.getElementById('minOrder').value,
    discount_percentage: document.getElementById('discountPct').value,
    payment_terms: document.getElementById('paymentTerms').value,
    products: products
  };
  
  const res = await apiCall('/wholesale.php?action=request', 'POST', payload);
  if (res && res.success) {
    handleSuccessResponse(res, {
      scrollTarget: '#wholesaleForm',
      successMessage: res.message || 'Solicitud enviada correctamente',
      onSuccess: async () => {
        document.getElementById('wholesaleForm').reset();
        const container = document.getElementById('productsListContainer');
        if (container) container.innerHTML = '';
        addProductRow();
        await loadWholesale();
        activateWholesaleTab('wholesaleRequestsTab');
      }
    });
  } else if (res) {
    showAlert(res.message, 'error');
  }
}

async function approveWholesale(id) {
  const res = await apiCall('/wholesale.php?action=approve', 'POST', { id });
  if (res && res.success) {
    handleSuccessResponse(res, {
      scrollTarget: '#wholesaleRows',
      successMessage: res.message || 'Solicitud aprobada',
      onSuccess: () => loadWholesale()
    });
  } else if (res) {
    showAlert(res.message, 'error');
  }
}

async function loadWholesale() {
  const res = await apiCall('/wholesale.php?action=list');
  const tb = document.getElementById('wholesaleRows');
  const colCount = <?php echo $column_count; ?>;
  
  // Update badge count
  const badge = document.getElementById('wholesaleBadge');
  if (badge) {
    const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
    const items = (res && res.success && Array.isArray(res.items)) ? res.items : [];
    // If admin, show pending requests. If client, show all requests.
    const displayCount = isAdmin ? items.filter(i => !i.is_approved).length : items.length;
    if (displayCount > 0) {
      badge.textContent = displayCount;
      badge.style.display = 'inline-flex';
    } else {
      badge.style.display = 'none';
    }
  }

  if (!res || !res.success || !Array.isArray(res.items) || res.items.length === 0) {
    tb.innerHTML = `<tr><td colspan="${colCount}">Sin registros</td></tr>`;
    loadedWholesaleRequests = [];
    return;
  }

  loadedWholesaleRequests = res.items;
  const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
  
  tb.innerHTML = res.items.map(i => {
    const prods = Array.isArray(i.products) ? i.products : [];
    let productsHtml = '';
    if (prods.length === 0) {
      productsHtml = '<span class="text-muted">Sin especificar</span>';
    } else {
      productsHtml = `
        <details class="wholesale-details">
          <summary>Ver prod. (${prods.length})</summary>
          <ul>
            ${prods.map(p => `
              <li>
                <strong>${p.quantity}x</strong> ${p.product_name} <br>
                <small class="text-muted">SKU: ${p.product_sku} | Lista: $${Number(p.product_price).toFixed(2)}</small>
              </li>
            `).join('')}
          </ul>
        </details>
      `;
    }
    
    const discount = Number(i.discount_percentage || 0);
    const statusText = i.is_approved ? 'Aprobado' : 'Pendiente';
    const badgeClass = i.is_approved ? 'badge-paid' : 'badge-pending';
    
    return `
      <tr>
        ${isAdmin ? `<td><strong>${i.first_name || ''} ${i.last_name || ''}</strong></td>` : ''}
        <td>${i.business_type || ''}</td>
        <td>${discount.toFixed(0)}%</td>
        <td>${i.payment_terms || ''}</td>
        <td>${productsHtml}</td>
        <td><span class="badge ${badgeClass}">${statusText}</span></td>
        <td>
          <div class="actions-cell">
            <button class="btn-action-sm btn-pdf" onclick="downloadWholesalePdf(${i.id})">📥 PDF</button>
            <button class="btn-action-sm btn-whatsapp" onclick="shareWholesaleWhatsApp(${i.id})">💬 WhatsApp</button>
            ${isAdmin && !i.is_approved ? `<button class="btn-action-sm" style="background:#ff6600; color:#fff;" onclick="approveWholesale(${i.id})">Aprobar</button>` : ''}
          </div>
        </td>
      </tr>
    `;
  }).join('');
}

function downloadWholesalePdf(id) {
  const req = loadedWholesaleRequests.find(r => r.id === id);
  if (!req) {
    showAlert('No se encontró la solicitud seleccionada', 'error');
    return;
  }
  
  if (!window.jspdf || !window.jspdf.jsPDF) {
    showAlert('Error: La librería de generación de PDF no está cargada', 'error');
    return;
  }
  
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'letter' });
  
  const primaryColor = [255, 102, 0]; 
  const darkColor = [30, 30, 30]; 
  const lightBg = [245, 245, 245];
  
  doc.setFillColor(...primaryColor);
  doc.rect(0, 0, 215.9, 35, 'F');
  
  doc.setTextColor(255, 255, 255);
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(22);
  doc.text('TRUPER PLATFORM', 15, 18);
  
  doc.setFontSize(11);
  doc.setFont('helvetica', 'normal');
  doc.text('COTIZACIÓN DE MAYOREO PENDIENTE DE APROBACIÓN', 15, 26);
  
  doc.setFontSize(10);
  doc.text(`Folio: MAY-${req.id}-${new Date(req.requested_date).getFullYear()}`, 145, 15);
  doc.text(`Fecha: ${new Date(req.requested_date).toLocaleDateString('es-MX')}`, 145, 21);
  doc.text(`Estado: ${req.is_approved ? 'APROBADO' : 'PENDIENTE'}`, 145, 27);
  
  let y = 50;
  
  doc.setTextColor(...darkColor);
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(13);
  doc.text('INFORMACIÓN DE LA SOLICITUD', 15, y);
  
  doc.setDrawColor(220, 220, 220);
  doc.line(15, y + 2, 200, y + 2);
  y += 10;
  
  doc.setFont('helvetica', 'normal');
  doc.setFontSize(10);
  
  const clientName = req.first_name ? `${req.first_name} ${req.last_name || ''}` : 'Cliente Registrado';
  
  doc.text(`Cliente: ${clientName}`, 15, y);
  doc.text(`Tipo de Negocio: ${req.business_type || 'No especificado'}`, 110, y);
  y += 6;
  doc.text(`Descuento Solicitado: ${Number(req.discount_percentage).toFixed(0)}%`, 15, y);
  doc.text(`Términos de Pago: ${req.payment_terms || 'Contado'}`, 110, y);
  y += 12;
  
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(13);
  doc.text('DESGLOSE DE ARTÍCULOS COTIZADOS', 15, y);
  doc.line(15, y + 2, 200, y + 2);
  y += 10;
  
  doc.setFillColor(...lightBg);
  doc.rect(15, y, 185, 8, 'F');
  
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(9);
  doc.setTextColor(50, 50, 50);
  doc.text('SKU', 18, y + 5.5);
  doc.text('Descripción del Producto', 45, y + 5.5);
  doc.text('Cant.', 115, y + 5.5);
  doc.text('P. Lista', 130, y + 5.5);
  doc.text('P. Mayorista', 155, y + 5.5);
  doc.text('Subtotal', 180, y + 5.5);
  
  y += 8;
  doc.setFont('helvetica', 'normal');
  doc.setTextColor(...darkColor);
  
  const products = Array.isArray(req.products) ? req.products : [];
  let totalOriginal = 0;
  let totalWholesale = 0;
  
  products.forEach((p, idx) => {
    if (y > 250) {
      doc.addPage();
      y = 20;
      doc.setFillColor(...lightBg);
      doc.rect(15, y, 185, 8, 'F');
      doc.setFont('helvetica', 'bold');
      doc.text('SKU', 18, y + 5.5);
      doc.text('Descripción del Producto', 45, y + 5.5);
      doc.text('Cant.', 115, y + 5.5);
      doc.text('P. Lista', 130, y + 5.5);
      doc.text('P. Mayorista', 155, y + 5.5);
      doc.text('Subtotal', 180, y + 5.5);
      y += 8;
      doc.setFont('helvetica', 'normal');
    }
    
    const qty = Number(p.quantity || 0);
    const origPrice = Number(p.product_price || 0);
    const discMultiplier = (100 - Number(req.discount_percentage || 0)) / 100;
    const wholesalePrice = origPrice * discMultiplier;
    const subtotal = wholesalePrice * qty;
    
    totalOriginal += (origPrice * qty);
    totalWholesale += subtotal;
    
    if (idx % 2 === 1) {
      doc.setFillColor(250, 250, 250);
      doc.rect(15, y, 185, 7, 'F');
    }
    
    doc.text(p.product_sku || '-', 18, y + 5);
    
    let name = p.product_name || '';
    if (name.length > 36) name = name.substring(0, 34) + '...';
    doc.text(name, 45, y + 5);
    
    doc.text(qty.toString(), 115, y + 5);
    doc.text(`$${origPrice.toFixed(2)}`, 130, y + 5);
    doc.text(`$${wholesalePrice.toFixed(2)}`, 155, y + 5);
    doc.text(`$${subtotal.toFixed(2)}`, 180, y + 5);
    
    y += 7;
  });
  
  y += 5;
  
  doc.setDrawColor(200, 200, 200);
  doc.line(120, y, 200, y);
  y += 5;
  
  doc.setFont('helvetica', 'normal');
  doc.setFontSize(10);
  doc.text('Total Precio Lista:', 125, y);
  doc.text(`$${totalOriginal.toFixed(2)}`, 175, y);
  
  y += 5;
  doc.text(`Descuento Aplicado (${Number(req.discount_percentage).toFixed(0)}%):`, 125, y);
  doc.text(`-$${(totalOriginal - totalWholesale).toFixed(2)}`, 175, y);
  
  y += 6;
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(12);
  doc.setTextColor(...primaryColor);
  doc.text('TOTAL COTIZADO:', 125, y);
  doc.text(`$${totalWholesale.toFixed(2)}`, 175, y);
  
  y += 15;
  
  doc.setTextColor(100, 100, 100);
  doc.setFont('helvetica', 'normal');
  doc.setFontSize(8.5);
  doc.text('* Esta cotización es de carácter informativo y está sujeta a aprobación por el administrador de Truper Platform.', 15, y);
  y += 4;
  doc.text('* Los términos comerciales y de entrega se formalizarán una vez que se notifique la aprobación del pedido.', 15, y);
  
  doc.save(`cotizacion-mayoreo-folio-${req.id}.pdf`);
}

function shareWholesaleWhatsApp(id) {
  const req = loadedWholesaleRequests.find(r => r.id === id);
  if (!req) {
    showAlert('No se encontró la solicitud seleccionada', 'error');
    return;
  }
  
  const discount = Number(req.discount_percentage || 0);
  const discMultiplier = (100 - discount) / 100;
  
  let msg = `*SOLICITUD DE MAYOREO #[${req.id}]*\n`;
  msg += `*Cliente:* ${req.first_name || ''} ${req.last_name || ''}\n`;
  msg += `*Negocio:* ${req.business_type || ''}\n`;
  msg += `*Plazo solicitado:* ${req.payment_terms || ''}\n`;
  msg += `*Descuento solicitado:* ${discount.toFixed(0)}%\n`;
  msg += `*Estatus:* ${req.is_approved ? 'Aprobado' : 'Pendiente'}\n\n`;
  msg += `*DETALLE DE ARTÍCULOS:*\n`;
  
  const products = Array.isArray(req.products) ? req.products : [];
  let totalOriginal = 0;
  let totalWholesale = 0;
  
  products.forEach(p => {
    const qty = Number(p.quantity || 0);
    const origPrice = Number(p.product_price || 0);
    const wholesalePrice = origPrice * discMultiplier;
    const subtotal = wholesalePrice * qty;
    
    totalOriginal += (origPrice * qty);
    totalWholesale += subtotal;
    
    msg += `• ${qty}x ${p.product_name} (${p.product_sku})\n`;
    msg += `  Lista: $${origPrice.toFixed(2)} | Mayorista: $${wholesalePrice.toFixed(2)} | Subt: $${subtotal.toFixed(2)}\n`;
  });
  
  msg += `\n---------------------------\n`;
  msg += `*Total Lista:* $${totalOriginal.toFixed(2)}\n`;
  msg += `*Total Mayorista:* $${totalWholesale.toFixed(2)}\n`;
  msg += `*Ahorro Estimado:* $${(totalOriginal - totalWholesale).toFixed(2)}\n\n`;
  msg += `Quedo atento(a) a la aprobación y comentarios de esta solicitud.`;
  
  const phone = window.companyWhatsapp || '';
  const url = `https://api.whatsapp.com/send?phone=${encodeURIComponent(phone)}&text=${encodeURIComponent(msg)}`;
  
  showAlert('Abriendo WhatsApp...', 'success');
  setTimeout(() => {
    window.open(url, '_blank');
  }, 500);
}

document.addEventListener('DOMContentLoaded', () => {
  loadProductsForForm();
  loadWholesale();
});
</script>
<script src="js/mobile-optimize.js"></script>
</body>
</html>
