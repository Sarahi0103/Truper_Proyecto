/**
 * Script para gestión de pedidos
 */

let currentCart = [];
let currentTotal = 0;
let selectedOrderId = null;

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
    
    if (!cartContainer) return;
    
    let html = '';
    let total = 0;
    
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
 * Crear nuevo pedido
 */
async function createOrder() {
    if (currentCart.length === 0) {
        showAlert('Agrega productos al pedido primero', 'warning');
        return;
    }
    
    const isWholesale = document.getElementById('isWholesale')?.checked || false;
    const weatherCondition = document.getElementById('weatherCondition')?.value || null;
    const specialEvent = document.getElementById('specialEvent')?.value || null;
    const notes = document.getElementById('orderNotes')?.value || null;
    
    const orderData = {
        items: currentCart,
        is_wholesale: isWholesale,
        weather_condition: weatherCondition,
        special_event: specialEvent,
        notes: notes
    };
    
    const response = await apiCall('/orders.php?action=create', 'POST', orderData);
    
    if (response && response.success) {
        showAlert(response.message, 'success');
        currentCart = [];
        updateCartUI();
        
        setTimeout(() => {
            window.location.href = '/orders.php';
        }, 1500);
    }
}

/**
 * Registrar pago
 */
async function recordPayment(orderId) {
    const effectiveOrderId = orderId || selectedOrderId;
    const amount = parseFloat(document.getElementById('paymentAmount')?.value || 0);
    const method = document.getElementById('paymentMethod')?.value || 'cash';
    
    if (amount <= 0) {
        showAlert('Ingresa un monto válido', 'warning');
        return;
    }
    
    const paymentData = {
        order_id: effectiveOrderId,
        amount: amount,
        payment_method: method,
        reference: document.getElementById('paymentReference')?.value || null
    };
    
    const response = await apiCall('/orders.php?action=payment', 'POST', paymentData);
    
    if (response && response.success) {
        showAlert(response.message, 'success');
        document.getElementById('paymentForm').reset();
        closeModal('paymentModal');
        
        // Recargar información de la orden
        setTimeout(() => {
            location.reload();
        }, 1000);
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
 * Filtrar órdenes por estado
 */
function filterOrders() {
    const filterValue = document.getElementById('orderFilter')?.value || '';
    const rows = document.querySelectorAll('#ordersList tr');
    
    rows.forEach(row => {
        if (!filterValue || row.getAttribute('data-status') === filterValue) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function openPaymentModal(orderId) {
    selectedOrderId = orderId;
    openModal('paymentModal');
}

function searchOrders() {
    const searchTerm = document.getElementById('orderSearch')?.value.toLowerCase() || '';
    const rows = document.querySelectorAll('#ordersList tr');

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
}

function getStatusLabel(status) {
    const labels = {
        pending: 'Pendiente',
        confirmed: 'Confirmado',
        processing: 'En Proceso',
        shipped: 'Enviado',
        delivered: 'Entregado',
        cancelled: 'Cancelado'
    };
    return labels[status] || status || 'N/A';
}

async function loadOrders() {
    const response = await apiCall('/orders.php?action=list');
    const ordersList = document.getElementById('ordersList');
    if (!ordersList) return;

    if (!response || !response.success || !Array.isArray(response.orders)) {
        ordersList.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No fue posible cargar órdenes</td></tr>';
        return;
    }

    if (response.orders.length === 0) {
        ordersList.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Aún no tienes pedidos</td></tr>';
        return;
    }

    ordersList.innerHTML = response.orders.map(order => `
        <tr data-status="${order.status}">
            <td>${order.order_number}</td>
            <td>${formatDate(order.created_at)}</td>
            <td>${formatCurrency(order.total_amount)}</td>
            <td>${getStatusLabel(order.payment_status)}</td>
            <td>${getStatusLabel(order.status)}</td>
            <td>
                <button class="btn btn-small btn-secondary" onclick="openPaymentModal(${order.id})">Pagar</button>
            </td>
        </tr>
    `).join('');
}

async function loadProducts() {
    const response = await apiCall('/products.php?action=list');
    const productsList = document.getElementById('productsList');
    if (!productsList) return;

    if (!response || !response.success || !Array.isArray(response.products)) {
        productsList.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No fue posible cargar productos</td></tr>';
        return;
    }

    if (response.products.length === 0) {
        productsList.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No hay productos registrados</td></tr>';
        return;
    }

    productsList.innerHTML = response.products.map(product => `
        <tr>
            <td>${product.name}</td>
            <td>${product.sku}</td>
            <td>${formatCurrency(product.unit_price)}</td>
            <td><input id="qty_${product.id}" type="number" min="1" value="1" style="width: 80px;"></td>
            <td>
                <button class="btn btn-primary btn-small"
                    onclick="addToCart(${product.id}, '${String(product.name).replace(/'/g, "\\'")}', ${product.unit_price}, document.getElementById('qty_${product.id}').value)">
                    Agregar
                </button>
            </td>
        </tr>
    `).join('');
}

document.addEventListener('DOMContentLoaded', function() {
    loadOrders();
    loadProducts();
});
