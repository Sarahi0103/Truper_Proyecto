/**
 * Script para gestión de pedidos
 */

let currentCart = [];
let loadedProducts = [];
let currentTotal = 0;
const COMPANY_WHATSAPP = String(window.TRUPER_COMPANY_WHATSAPP || '3312482297');
const ORDERS_ROLE = String(window.TRUPER_ORDERS_ROLE || 'client').toLowerCase();
const ORDERS_IS_ADMIN = ORDERS_ROLE === 'admin' || ORDERS_ROLE === 'employee';
const ORDER_STATUS_OPTIONS = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];

// Historial de búsqueda y autocompletado
let searchHistory = JSON.parse(localStorage.getItem('orderSearchHistory') || '[]');
let autocompleteTimeout = null;

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
        const itemPrice = parseFloat(price);
        currentCart.push({
            productId: productId,
            name: productName,
            price: itemPrice,
            wholesalePrice: itemPrice * 0.70,
            isCustomPrice: false,
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
    confirmAction(
        'Limpiar Pedido',
        '¿Deseas limpiar todo el pedido? Todos los artículos seleccionados se removerán.',
        '🗑️',
        function() {
            currentCart = [];
            updateCartUI();
            showAlert('Pedido limpiado', 'info');
        }
    );
}

/**
 * Actualizar interfaz del carrito
 */
function updateCartUI() {
    const cartContainer = document.getElementById('cartItems');
    const totalContainer = document.getElementById('cartTotal');
    const subtotalContainer = document.getElementById('cartSubtotal');
    const discountContainer = document.getElementById('cartDiscount');
    const theadContainer = document.querySelector('.cart-scroll table thead');
    
    if (!cartContainer) return;

    if (theadContainer) {
        if (ORDERS_IS_ADMIN) {
            theadContainer.innerHTML = `
                <tr>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>P. Minoreo</th>
                    <th>P. Mayoreo</th>
                    <th>Subtotal</th>
                    <th>Descuento</th>
                    <th>Total</th>
                    <th></th>
                </tr>
            `;
        } else {
            theadContainer.innerHTML = `
                <tr>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Precio Unit.</th>
                    <th>Subtotal</th>
                    <th>Descuento</th>
                    <th>Total</th>
                    <th></th>
                </tr>
            `;
        }
    }
    
    let html = '';
    let total = 0;
    let subtotalAmount = 0;
    let discountAmount = 0;
    const isWholesaleChecked = document.getElementById('isWholesale')?.checked || false;
    
    currentCart.forEach(item => {
        if (item.wholesalePrice === undefined) {
            item.wholesalePrice = item.price * 0.70;
        }

        const activePrice = isWholesaleChecked ? item.wholesalePrice : item.price;
        const subtotal = activePrice * item.quantity;
        let discount = 0;
        
        const isCustomPrice = item.isCustomPrice || false;
        if (!isCustomPrice) {
            if (item.quantity >= 100) {
                discount = subtotal * 0.15; // 15%
            } else if (item.quantity >= 50) {
                discount = subtotal * 0.10; // 10%
            } else if (item.quantity >= 20) {
                discount = subtotal * 0.05; // 5%
            }
        }
        
        const lineTotal = subtotal - discount;
        subtotalAmount += subtotal;
        discountAmount += discount;
        total += lineTotal;
        
        if (ORDERS_IS_ADMIN) {
            html += `
                <tr>
                    <td>${item.name}</td>
                    <td>
                        <input type="number" min="1" value="${item.quantity}" 
                               onchange="updateCartItem(${item.productId}, this.value)" style="width: 70px; background:#121212; border:1px solid rgba(255,255,255,0.12); color:#fff; border-radius:4px; padding:2px 4px;">
                    </td>
                    <td>
                        <input type="number" step="0.01" min="0" value="${parseFloat(item.price).toFixed(2)}" 
                               onchange="updateCartItemPrice(${item.productId}, this.value, 'retail')" style="width: 105px; background:#121212; border:1px solid rgba(255,255,255,0.12); color:#fff; border-radius:4px; padding:2px 4px;">
                    </td>
                    <td>
                        <input type="number" step="0.01" min="0" value="${parseFloat(item.wholesalePrice).toFixed(2)}" 
                               onchange="updateCartItemPrice(${item.productId}, this.value, 'wholesale')" style="width: 105px; background:#121212; border:1px solid rgba(255,255,255,0.12); color:#fff; border-radius:4px; padding:2px 4px;">
                    </td>
                    <td>${formatCurrency(subtotal)}</td>
                    <td>${discount > 0 ? formatCurrency(discount) : 'N/A'}</td>
                    <td>${formatCurrency(lineTotal)}</td>
                    <td>
                        <button class="btn btn-danger btn-small" onclick="removeFromCart(${item.productId})">Eliminar</button>
                    </td>
                </tr>
            `;
        } else {
            html += `
                <tr>
                    <td>${item.name}</td>
                    <td>
                        <input type="number" min="1" value="${item.quantity}" 
                               onchange="updateCartItem(${item.productId}, this.value)" style="width: 70px; background:#121212; border:1px solid rgba(255,255,255,0.12); color:#fff; border-radius:4px; padding:2px 4px;">
                    </td>
                    <td>${formatCurrency(activePrice)}</td>
                    <td>${formatCurrency(subtotal)}</td>
                    <td>${discount > 0 ? formatCurrency(discount) : 'N/A'}</td>
                    <td>${formatCurrency(lineTotal)}</td>
                    <td>
                        <button class="btn btn-danger btn-small" onclick="removeFromCart(${item.productId})">Eliminar</button>
                    </td>
                </tr>
            `;
        }
    });
    
    currentTotal = total;
    
    if (html === '') {
        const colspan = ORDERS_IS_ADMIN ? 8 : 7;
        html = `<tr><td colspan="${colspan}" class="text-center">Tu carrito está vacío</td></tr>`;
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
 * Actualizar precio de minoría o mayoreo de un item (solo administrador o personal)
 */
function updateCartItemPrice(productId, newPrice, priceType) {
    const item = currentCart.find(i => i.productId == productId);
    if (item) {
        const priceVal = parseFloat(newPrice);
        if (isNaN(priceVal) || priceVal < 0) {
            showAlert('Precio inválido', 'warning');
            return;
        }

        if (priceType === 'retail') {
            item.price = priceVal;
        } else if (priceType === 'wholesale') {
            item.wholesalePrice = priceVal;
        }

        item.isCustomPrice = true;
        updateCartUI();
    }
}



/**
 * Buscar productos con debounce y autocompletado
 */
function searchProducts() {
    const searchTerm = document.getElementById('productSearch')?.value.toLowerCase() || '';
    const rows = document.querySelectorAll('#productsList tr');

    // Guardar en historial de búsqueda
    if (searchTerm.length >= 2) {
        saveSearchHistory(searchTerm);
    }

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });

    // Mostrar sugerencias de autocompletado
    showAutocompleteSuggestions(searchTerm);
}

/**
 * Guardar historial de búsqueda
 */
function saveSearchHistory(term) {
    // Eliminar duplicados y mantener solo los últimos 10
    searchHistory = searchHistory.filter(t => t.toLowerCase() !== term.toLowerCase());
    searchHistory.unshift(term);
    searchHistory = searchHistory.slice(0, 10);
    localStorage.setItem('orderSearchHistory', JSON.stringify(searchHistory));
}

/**
 * Mostrar sugerencias de autocompletado
 */
function showAutocompleteSuggestions(searchTerm) {
    const searchInput = document.getElementById('productSearch');
    if (!searchInput || searchTerm.length < 2) return;

    // Eliminar sugerencias anteriores
    const existingSuggestions = document.querySelector('.autocomplete-suggestions');
    if (existingSuggestions) existingSuggestions.remove();

    // Buscar coincidencias en productos cargados
    const matches = loadedProducts.filter(p =>
        p.name.toLowerCase().includes(searchTerm) ||
        p.sku.toLowerCase().includes(searchTerm)
    ).slice(0, 5);

    if (matches.length === 0) return;

    // Crear contenedor de sugerencias
    const suggestionsDiv = document.createElement('div');
    suggestionsDiv.className = 'autocomplete-suggestions';
    suggestionsDiv.style.cssText = `
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: #1e1e1e;
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 8px;
        margin-top: 4px;
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    `;

    matches.forEach(product => {
        const suggestion = document.createElement('div');
        suggestion.style.cssText = `
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            transition: background 0.2s;
        `;
        suggestion.textContent = `${product.name} (${displayProductCode(product.sku)})`;
        suggestion.onmouseover = () => suggestion.style.background = 'rgba(255, 102, 0, 0.1)';
        suggestion.onmouseout = () => suggestion.style.background = 'transparent';
        suggestion.onclick = () => {
            searchInput.value = product.name;
            suggestionsDiv.remove();
            searchProducts();
        };
        suggestionsDiv.appendChild(suggestion);
    });

    searchInput.parentElement.style.position = 'relative';
    searchInput.parentElement.appendChild(suggestionsDiv);

    // Cerrar al hacer clic fuera
    document.addEventListener('click', function closeSuggestions(e) {
        if (!suggestionsDiv.contains(e.target) && e.target !== searchInput) {
            suggestionsDiv.remove();
            document.removeEventListener('click', closeSuggestions);
        }
    });
}

/**
 * Mostrar historial de búsqueda
 */
function showSearchHistory() {
    const searchInput = document.getElementById('productSearch');
    if (!searchInput) return;

    // Eliminar historial anterior
    const existingHistory = document.querySelector('.search-history-dropdown');
    if (existingHistory) existingHistory.remove();

    if (searchHistory.length === 0) return;

    const historyDiv = document.createElement('div');
    historyDiv.className = 'search-history-dropdown';
    historyDiv.style.cssText = `
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: #1e1e1e;
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 8px;
        margin-top: 4px;
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    `;

    const header = document.createElement('div');
    header.style.cssText = 'padding: 8px 15px; font-size: 12px; color: #888; border-bottom: 1px solid rgba(255, 255, 255, 0.06);';
    header.textContent = 'Búsquedas recientes';
    historyDiv.appendChild(header);

    searchHistory.forEach(term => {
        const item = document.createElement('div');
        item.style.cssText = `
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
        `;
        item.innerHTML = `
            <span>${term}</span>
            <span style="font-size: 12px; color: #888;">🕐</span>
        `;
        item.onmouseover = () => item.style.background = 'rgba(255, 102, 0, 0.1)';
        item.onmouseout = () => item.style.background = 'transparent';
        item.onclick = () => {
            searchInput.value = term;
            historyDiv.remove();
            searchProducts();
        };
        historyDiv.appendChild(item);
    });

    searchInput.parentElement.style.position = 'relative';
    searchInput.parentElement.appendChild(historyDiv);

    // Cerrar al hacer clic fuera
    document.addEventListener('click', function closeHistory(e) {
        if (!historyDiv.contains(e.target) && e.target !== searchInput) {
            historyDiv.remove();
            document.removeEventListener('click', closeHistory);
        }
    });
}

/**
 * Vista rápida de producto en modal
 */
function showQuickView(productId) {
    const product = loadedProducts.find(p => p.id == productId);
    if (!product) return;

    // Eliminar modal existente
    const existingModal = document.querySelector('.quick-view-modal');
    if (existingModal) existingModal.remove();

    const modal = document.createElement('div');
    modal.className = 'quick-view-modal';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 2000;
    `;

    modal.innerHTML = `
        <div style="
            background: #1e1e1e;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 12px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        ">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 style="margin: 0; color: #fff; font-size: 1.5rem;">${product.name}</h2>
                <button onclick="this.closest('.quick-view-modal').remove()" style="
                    background: none;
                    border: none;
                    color: #fff;
                    font-size: 1.5rem;
                    cursor: pointer;
                    padding: 0.5rem;
                ">✕</button>
            </div>
            <div style="margin-bottom: 1rem;">
                <strong style="color: #ff6600;">SKU:</strong> ${displayProductCode(product.sku)}
            </div>
            <div style="margin-bottom: 1rem;">
                <strong style="color: #ff6600;">Categoría:</strong> ${product.category || 'N/A'}
            </div>
            <div style="margin-bottom: 1rem;">
                <strong style="color: #ff6600;">Precio:</strong> ${formatCurrency(product.unit_price)}
            </div>
            <div style="margin-bottom: 1rem;">
                <strong style="color: #ff6600;">Stock:</strong> ${product.stock_quantity || 0} unidades
            </div>
            ${product.description ? `<div style="margin-bottom: 1rem;"><strong style="color: #ff6600;">Descripción:</strong> ${product.description}</div>` : ''}
            <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
                <button onclick="addToCartFromList(${product.id}); this.closest('.quick-view-modal').remove();" style="
                    flex: 1;
                    background: #28a745;
                    color: #fff;
                    border: none;
                    padding: 0.75rem;
                    border-radius: 8px;
                    cursor: pointer;
                    font-weight: 600;
                ">Agregar al Pedido</button>
                <button onclick="this.closest('.quick-view-modal').remove();" style="
                    flex: 1;
                    background: #6c757d;
                    color: #fff;
                    border: none;
                    padding: 0.75rem;
                    border-radius: 8px;
                    cursor: pointer;
                ">Cerrar</button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    // Cerrar al hacer clic fuera del contenido
    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.remove();
    });
}

/**
 * Imprimir orden directamente
 */
function printOrder(orderId) {
    const printUrl = `/ticket_client.php?id=${orderId}&print=1`;
    const printWindow = window.open(printUrl, '_blank');
    if (printWindow) {
        printWindow.onload = function() {
            printWindow.print();
        };
    }
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

async function deleteOrder(orderId, orderNumber) {
    if (!ORDERS_IS_ADMIN) return;

    confirmDelete(`el pedido ${orderNumber}`, async function() {
        const response = await apiCall('/orders.php?action=delete', 'DELETE', {
            order_id: Number(orderId)
        });

        if (response && response.success) {
            showAlert(response.message || 'Pedido eliminado correctamente', 'success');
            await loadOrders();
            return;
        }

        showAlert((response && response.message) ? response.message : 'No se pudo eliminar el pedido', 'error');
    });
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
    const response = await apiCall('/products_lazy.php?page=1&limit=50');
    const productsList = document.getElementById('productsList');
    const categoryFilter = document.getElementById('productCategoryFilter');
    const selectedCategory = categoryFilter?.value || '';
    if (!productsList) return;

    if (!response || !response.success || !Array.isArray(response.products)) {
        productsList.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No fue posible cargar productos</td></tr>';
        return;
    }

    loadedProducts = response.products;

    updateCategoryFilter(response.products);

    const filteredProducts = response.products.filter(product => categoryMatches(product.category, selectedCategory));

    if (filteredProducts.length === 0) {
        productsList.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No hay productos registrados</td></tr>';
        return;
    }

    productsList.innerHTML = filteredProducts.map(product => `
        <tr>
            <td>${product.name}</td>
            <td>${displayProductCode(product.sku)}</td>
            <td>${formatCurrency(product.unit_price)}</td>
            <td>${product.stock_quantity || 0}</td>
            <td><input id="qty_${product.id}" type="number" min="1" value="1" style="width: 80px;"></td>
            <td>
                <button class="btn btn-info btn-small" onclick="showQuickView(${product.id})" title="Vista rápida">👁</button>
                <button class="btn btn-primary btn-small" onclick="addToCartFromList(${product.id})">Agregar</button>
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
        price: isWholesale ? (item.wholesalePrice !== undefined ? item.wholesalePrice : item.price * 0.70) : item.price,
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
        price: isWholesale ? (item.wholesalePrice !== undefined ? item.wholesalePrice : item.price * 0.70) : item.price,
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
        const isDeleteAvailable = normalizedStatus === 'delivered' || normalizedStatus === 'cancelled';
        const deleteBtn = (ORDERS_IS_ADMIN && isDeleteAvailable)
            ? `<button class="btn btn-small btn-danger order-delete-btn" style="margin-left:6px;" onclick="deleteOrder(${Number(order.id)}, '${String(order.order_number || '').replace(/'/g, '')}')">
                🗑 Eliminar
               </button>`
            : '';

        return `
        <tr data-status="${normalizedStatus}" data-order-id="${order.id}">
            <td>${order.order_number}</td>
            <td>${formatDate(order.created_at)}</td>
            <td>${formatCurrency(order.total_amount)}</td>
            <td>WhatsApp</td>
            <td>${renderOrderStatusCell(normalizedStatus, order.id)}</td>
            <td>
                <a class="btn btn-small btn-primary" href="/ticket_client.php?id=${order.id}" target="_blank">Ticket</a>
                <button class="btn btn-small btn-info" onclick="printOrder(${order.id})" title="Imprimir">🖨</button>
                ${deleteBtn}
            </td>
        </tr>
    `;
    }).join('');

    removePayButtonsFromOrders();
    applyOrderFilters();
}

document.addEventListener('DOMContentLoaded', function() {
    loadOrders();
    loadProducts();

    const wholesaleCheckbox = document.getElementById('isWholesale');
    if (wholesaleCheckbox) {
        wholesaleCheckbox.addEventListener('change', function() {
            updateCartUI();
        });
    }

    // Agregar evento para mostrar historial de búsqueda al hacer focus
    const productSearch = document.getElementById('productSearch');
    if (productSearch) {
        productSearch.addEventListener('focus', function() {
            if (this.value === '' && searchHistory.length > 0) {
                showSearchHistory();
            }
        });
    }
});
