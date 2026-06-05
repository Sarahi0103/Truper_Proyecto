/**
 * Script para gestión de pedidos
 */

let currentCart = [];
let loadedProducts = [];
let currentTotal = 0;
const COMPANY_WHATSAPP = String(window.TRUPER_COMPANY_WHATSAPP || '3317915887');
const ORDERS_ROLE = String(window.TRUPER_ORDERS_ROLE || 'client').toLowerCase();
const ORDERS_IS_ADMIN = ORDERS_ROLE === 'admin';
const ORDER_STATUS_OPTIONS = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];

function displayProductCode(rawSku) {
    return String(rawSku || '').replace(/^\s*XLS-/i, '').trim();
}

function resetOrderForm() {
    const isWholesale = document.getElementById('isWholesale');
    const specialEvent = document.getElementById('specialEvent');
    const orderNotes = document.getElementById('orderNotes');

    if (isWholesale) {
        isWholesale.checked = false;
    }
    if (specialEvent) {
        specialEvent.value = '';
    }
    if (orderNotes) {
        orderNotes.value = '';
    }
}

function activateOrdersTab(tabName) {
    const tabButton = document.querySelector(`.tab-button[data-tab="${tabName}"]`);
    if (tabButton) {
        tabButton.click();
    }
}

function normalizeOrderStatus(status) {
    if (status === 'completed') {
        return 'delivered';
    }

    return status || 'pending';
}

/**
 * Agregar producto al carrito
 */
function addToCart(productId, productName, price, quantity) {
    if (quantity <= 0) {
        showAlert('Cantidad debe ser mayor a 0', 'warning');
        return;
    }
    
    const existingItem = currentCart.find(item => item.productId == productId);
    
    if (existingItem) {
        existingItem.quantity = parseInt(quantity);
    } else {
        currentCart.push({
            productId: productId,
            name: productName,
            price: parseFloat(price),
            quantity: parseInt(quantity)
        });
    }
    
    updateCartUI();
    showAlert(`${productName} agregado al pedido`, 'success');
}

/**
 * Remover producto del carrito
 */
function removeFromCart(productId) {
    currentCart = currentCart.filter(item => item.productId != productId);
    updateCartUI();
}

/**
 * Limpiar carrito
 */
function clearCart() {
    if (confirm('¿Deseas limpiar todo el pedido?')) {
        currentCart = [];
        updateCartUI();
        showAlert('Pedido limpiado', 'info');
    }
}

/**
 * Actualizar interfaz del carrito
 */
function updateCartUI() {
    const cartContainer = document.getElementById('cartItems');
    const totalContainer = document.getElementById('cartTotal');
    const subtotalContainer = document.getElementById('cartSubtotal');
    const discountContainer = document.getElementById('cartDiscount');
    
    if (!cartContainer) return;
    
    let html = '';
    let total = 0;
    let subtotalAmount = 0;
    let discountAmount = 0;
    
    currentCart.forEach(item => {
        const subtotal = item.price * item.quantity;
        let discount = 0;
        
        if (item.quantity >= 100) {
            discount = subtotal * 0.15; // 15%
        } else if (item.quantity >= 50) {
            discount = subtotal * 0.10; // 10%
        } else if (item.quantity >= 20) {
            discount = subtotal * 0.05; // 5%
        }
        
        const lineTotal = subtotal - discount;
        subtotalAmount += subtotal;
        discountAmount += discount;
        total += lineTotal;
        
        html += `
            <tr>
                <td>${item.name}</td>
                <td>
                    <input type="number" min="1" value="${item.quantity}" 
                           onchange="updateCartItem(${item.productId}, this.value)">
                </td>
                <td>${formatCurrency(item.price)}</td>
                <td>${formatCurrency(subtotal)}</td>
                <td>${discount > 0 ? formatCurrency(discount) : 'N/A'}</td>
                <td>${formatCurrency(lineTotal)}</td>
                <td>
                    <button class="btn btn-danger btn-small" onclick="removeFromCart(${item.productId})">Eliminar</button>
                </td>
            </tr>
        `;
    });
    
    currentTotal = total;
    
    if (html === '') {
        html = '<tr><td colspan="7" class="text-center">Tu carrito está vacío</td></tr>';
    }
    
    cartContainer.innerHTML = html;
    
    if (totalContainer) {
        totalContainer.textContent = formatCurrency(total);
    }
    if (subtotalContainer) {
        subtotalContainer.textContent = formatCurrency(subtotalAmount);
    }
    if (discountContainer) {
        discountContainer.textContent = formatCurrency(discountAmount);
    }
}

/**
 * Actualizar cantidad de un item
 */
function updateCartItem(productId, quantity) {
    const item = currentCart.find(i => i.productId == productId);
    if (item) {
        item.quantity = parseInt(quantity);
        if (item.quantity <= 0) {
            removeFromCart(productId);
        } else {
            updateCartUI();
        }
    }
}



/**
 * Buscar productos
 */
function searchProducts() {
    const searchTerm = document.getElementById('productSearch')?.value.toLowerCase() || '';
    const rows = document.querySelectorAll('#productsList tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
}

/**
 * Aplicar filtros combinados de estado y búsqueda de órdenes
 */
function applyOrderFilters() {
    const filterValue = document.getElementById('orderFilter')?.value || '';
    const searchTerm = document.getElementById('orderSearch')?.value.toLowerCase() || '';
    const rows = document.querySelectorAll('#ordersList tr');

    rows.forEach(row => {
        const matchesStatus = !filterValue || row.getAttribute('data-status') === filterValue;
        const matchesSearch = !searchTerm || row.textContent.toLowerCase().includes(searchTerm);

        if (matchesStatus && matchesSearch) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function filterOrders() {
    applyOrderFilters();
}

function searchOrders() {
    applyOrderFilters();
}



function getOrderStatusClass(status) {
    const normalized = normalizeOrderStatus(status);
    if (!ORDER_STATUS_OPTIONS.includes(normalized)) {
        return 'status-pending';
    }
    return `status-${normalized}`;
}

function syncOrderStatusSelectVisual(selectElement, statusValue) {
    if (!selectElement) return;

    ORDER_STATUS_OPTIONS.forEach((value) => {
        selectElement.classList.remove(`status-${value}`);
    });

    selectElement.classList.add(getOrderStatusClass(statusValue));
}

function renderOrderStatusCell(status, orderId) {
    const normalizedStatus = normalizeOrderStatus(status);
    const statusClass = getOrderStatusClass(normalizedStatus);

    if (!ORDERS_IS_ADMIN) {
        return `<span class="order-status-readonly ${statusClass}">${getStatusLabel(normalizedStatus)}</span>`;
    }

    const options = ORDER_STATUS_OPTIONS.map((value) => {
        const selected = value === normalizedStatus ? 'selected' : '';
        return `<option value="${value}" ${selected}>${getStatusLabel(value)}</option>`;
    }).join('');

    return `
        <select class="order-status-select ${statusClass}" onchange="syncOrderStatusSelectVisual(this, this.value); updateOrderStatus(${Number(orderId || 0)}, this.value)">
            ${options}
        </select>
    `;
}

async function updateOrderStatus(orderId, newStatus) {
    if (!ORDERS_IS_ADMIN) {
        return;
    }

    if (!orderId || !ORDER_STATUS_OPTIONS.includes(newStatus)) {
        showAlert('Estado de pedido inválido', 'warning');
        return;
    }

    const response = await apiCall('/orders.php?action=update-status', 'PUT', {
        order_id: Number(orderId),
        status: newStatus
    });

    if (response && response.success) {
        showAlert(response.message || 'Estado actualizado', 'success');
        await loadOrders();
        return;
    }

    showAlert((response && response.message) ? response.message : 'No se pudo actualizar el estado del pedido', 'error');
}

function normalizeCategoryText(value) {
    return String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
}

function categoryMatches(productCategory, selectedFilter) {
    if (!selectedFilter) {
        return true;
    }

    return normalizeCategoryText(productCategory) === normalizeCategoryText(selectedFilter);
}

function updateCategoryFilter(products) {
    const select = document.getElementById('productCategoryFilter');
    if (!select) {
        return;
    }

    const previousValue = select.value || '';
    const categories = [...new Set(
        (products || [])
            .map(product => String(product.category || '').trim())
            .filter(Boolean)
    )].sort((a, b) => a.localeCompare(b, 'es', { sensitivity: 'base' }));

    const options = ['<option value="">Todas las categorías</option>'];
    categories.forEach(category => {
        const escapedValue = category
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
        options.push(`<option value="${escapedValue}">${escapedValue}</option>`);
    });

    select.innerHTML = options.join('');

    if (previousValue && categories.some(category => normalizeCategoryText(category) === normalizeCategoryText(previousValue))) {
        select.value = categories.find(category => normalizeCategoryText(category) === normalizeCategoryText(previousValue)) || '';
    }
}

function removePayButtonsFromOrders() {
    const ordersList = document.getElementById('ordersList');
    if (!ordersList) {
        return;
    }

    const actionButtons = ordersList.querySelectorAll('a, button');
    actionButtons.forEach(element => {
        const text = String(element.textContent || '').trim().toLowerCase();
        if (text === 'pagar' || text.includes('pagar')) {
            element.remove();
        }
    });

    ordersList.querySelectorAll('td:last-child').forEach(cell => {
        if (!cell.textContent.trim()) {
            cell.textContent = '-';
        }
    });
}



function addToCartFromList(productId) {
    const product = loadedProducts.find(p => p.id == productId);
    if (!product) return;
    const qtyInput = document.getElementById(`qty_${productId}`);
    const quantity = qtyInput ? parseInt(qtyInput.value) : 1;
    addToCart(product.id, product.name, product.unit_price, quantity);
}

async function loadProducts() {
    const response = await apiCall('/products.php?action=list');
    const productsList = document.getElementById('productsList');
    const categoryFilter = document.getElementById('productCategoryFilter');
    const selectedCategory = categoryFilter?.value || '';
    if (!productsList) return;

    if (!response || !response.success || !Array.isArray(response.products)) {
        productsList.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No fue posible cargar productos</td></tr>';
        return;
    }

    loadedProducts = response.products;

    updateCategoryFilter(response.products);

    const filteredProducts = response.products.filter(product => categoryMatches(product.category, selectedCategory));

    if (filteredProducts.length === 0) {
        productsList.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No hay productos registrados</td></tr>';
        return;
    }

    productsList.innerHTML = filteredProducts.map(product => `
        <tr>
            <td>${product.name}</td>
            <td>${displayProductCode(product.sku)}</td>
            <td>${formatCurrency(product.unit_price)}</td>
            <td><input id="qty_${product.id}" type="number" min="1" value="1" style="width: 80px;"></td>
            <td>
                <button class="btn btn-primary btn-small"
                    onclick="addToCartFromList(${product.id})">
                    Agregar
                </button>
            </td>
        </tr>
    `).join('');
}

function getStatusLabel(status) {
    const labels = {
        pending: 'Pendiente',
        confirmed: 'Confirmado',
        processing: 'En Proceso',
        shipped: 'Enviado',
        delivered: 'Completado',
        completed: 'Completado',
        cancelled: 'Cancelado'
    };
    return labels[status] || status || 'N/A';
}

async function createOrder(buttonElement = null) {
    if (currentCart.length === 0) {
        showAlert('Agrega productos al pedido primero', 'warning');
        return;
    }

    const submitButton = buttonElement || document.querySelector('.orders-page .btn-group .btn-primary');
    const originalButtonText = submitButton ? submitButton.textContent : '';

    if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = 'Enviando...';
    }

    const isWholesale = document.getElementById('isWholesale')?.checked || false;
    const specialEvent = document.getElementById('specialEvent')?.value || null;
    const notes = document.getElementById('orderNotes')?.value || null;

    const quoteItems = currentCart.map(item => ({
        product_id: item.productId,
        name: item.name,
        price: item.price,
        quantity: item.quantity
    }));

    const orderData = {
        items: quoteItems,
        total: currentTotal,
        whatsapp_phone: COMPANY_WHATSAPP,
        is_wholesale: isWholesale,
        special_event: specialEvent,
        notes: notes
    };

    // Pre-open blank tab for WhatsApp to bypass popup blockers
    let whatsappWindow = null;
    try {
        whatsappWindow = window.open('', '_blank');
    } catch (err) {
        console.error('Failed to pre-open WhatsApp window:', err);
    }

    try {
        const response = await apiCall('/client_account.php?action=whatsapp-quote', 'POST', orderData);

        if (response && response.success) {
            showAlert(response.message || 'Pedido registrado. Abriendo WhatsApp y descargando ticket...', 'success');

            // Redirect the pre-opened tab to the WhatsApp URL
            if (response.whatsapp_url && whatsappWindow) {
                whatsappWindow.location.href = response.whatsapp_url;
            } else if (whatsappWindow) {
                whatsappWindow.close();
            }

            // Redirect current tab to ticket with auto_pdf parameter to download it automatically
            if (response.ticket_url) {
                const downloadUrl = response.ticket_url + (response.ticket_url.includes('?') ? '&' : '?') + 'auto_pdf=1';
                setTimeout(() => {
                    window.location.href = downloadUrl;
                }, 1000);
            }

            currentCart = [];
            updateCartUI();
            resetOrderForm();
            return;
        }

        if (whatsappWindow) {
            whatsappWindow.close();
        }
        showAlert((response && response.message) ? response.message : 'No se pudo crear la cotizacion', 'error');
    } catch (error) {
        if (whatsappWindow) {
            whatsappWindow.close();
        }
        showAlert('Error al procesar la cotización', 'error');
    } finally {
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = originalButtonText;
        }
    }
}

async function saveOrderOnly(buttonElement = null) {
    if (currentCart.length === 0) {
        showAlert('Agrega productos al pedido primero', 'warning');
        return;
    }

    const submitButton = buttonElement || document.querySelector('.orders-page .btn-group .btn-save-order');
    const originalButtonText = submitButton ? submitButton.textContent : '';

    if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = 'Guardando...';
    }

    const isWholesale = document.getElementById('isWholesale')?.checked || false;
    const specialEvent = document.getElementById('specialEvent')?.value || null;
    const notes = document.getElementById('orderNotes')?.value || null;

    const quoteItems = currentCart.map(item => ({
        product_id: item.productId,
        name: item.name,
        price: item.price,
        quantity: item.quantity
    }));

    const orderData = {
        items: quoteItems,
        total: currentTotal,
        whatsapp_phone: COMPANY_WHATSAPP,
        is_wholesale: isWholesale,
        special_event: specialEvent,
        notes: notes
    };

    try {
        const response = await apiCall('/client_account.php?action=whatsapp-quote', 'POST', orderData);

        if (response && response.success) {
            showAlert(response.message || 'Pedido guardado y ticket generado con éxito', 'success');

            // Iniciar la descarga del PDF de forma no intrusiva usando un iframe temporal
            if (response.ticket_url) {
                const downloadUrl = response.ticket_url + (response.ticket_url.includes('?') ? '&' : '?') + 'auto_pdf=1';
                const iframe = document.createElement('iframe');
                iframe.style.display = 'none';
                iframe.src = downloadUrl;
                document.body.appendChild(iframe);
                setTimeout(() => {
                    if (iframe.parentNode) {
                        document.body.removeChild(iframe);
                    }
                }, 5000);
            }

            // Limpiar el carrito y restablecer el formulario
            currentCart = [];
            updateCartUI();
            resetOrderForm();

            // Cargar y actualizar la lista de pedidos
            await loadOrders();

            // Cambiar a la pestaña "Mis Pedidos"
            activateOrdersTab('myOrders');
            return;
        }

        showAlert((response && response.message) ? response.message : 'No se pudo guardar el pedido', 'error');
    } catch (error) {
        console.error('Error al guardar el pedido:', error);
        showAlert('Error al procesar y guardar el pedido', 'error');
    } finally {
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = originalButtonText;
        }
    }
}

async function loadOrders() {
    const response = await apiCall('/orders.php?action=list');
    const ordersList = document.getElementById('ordersList');
    if (!ordersList) return;

    if (!response || !response.success || !Array.isArray(response.orders)) {
        ordersList.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No fue posible cargar ordenes</td></tr>';
        return;
    }

    if (response.orders.length === 0) {
        ordersList.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Aun no tienes pedidos</td></tr>';
        return;
    }

    ordersList.innerHTML = response.orders.map(order => {
        const normalizedStatus = normalizeOrderStatus(order.status);
        const deleteBtn = (ORDERS_IS_ADMIN && normalizedStatus === 'delivered')
            ? `<button class="btn btn-small btn-danger" onclick="deleteOrder(${order.id})">Eliminar</button>`
            : '';

        return `
        <tr data-status="${normalizedStatus}">
            <td>${order.order_number}</td>
            <td>${formatDate(order.created_at)}</td>
            <td>${formatCurrency(order.total_amount)}</td>
            <td>WhatsApp</td>
            <td>${renderOrderStatusCell(normalizedStatus, order.id)}</td>
            <td>
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <a class="btn btn-small btn-primary" href="/ticket_client.php?id=${order.id}" target="_blank">Ticket</a>
                    ${deleteBtn}
                </div>
            </td>
        </tr>
    `;
    }).join('');

    removePayButtonsFromOrders();
    applyOrderFilters();
}

async function deleteOrder(orderId) {
    if (!orderId) return;
    if (!confirm('¿Deseas eliminar este pedido definitivamente? Esta acción no se puede deshacer.')) {
        return;
    }

    const response = await apiCall('/orders.php?action=delete', 'POST', { order_id: Number(orderId) });
    if (response && response.success) {
        showAlert(response.message || 'Pedido eliminado exitosamente', 'success');
        await loadOrders();
        return;
    }

    showAlert((response && response.message) ? response.message : 'No se pudo eliminar el pedido', 'error');
}

document.addEventListener('DOMContentLoaded', function() {
    loadOrders();
    loadProducts();
});
