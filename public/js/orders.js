/**
 * Script para gestión de pedidos
 */

let currentCart = [];
let currentTotal = 0;

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
    
    const orderData = {
        items: currentCart,
        is_wholesale: isWholesale
    };
    
    const response = await apiCall('/orders/create', 'POST', orderData);
    
    if (response && response.success) {
        showAlert(response.message, 'success');
        currentCart = [];
        updateCartUI();
        
        setTimeout(() => {
            window.location.href = '/truper_platform/public/orders.php';
        }, 1500);
    }
}

/**
 * Registrar pago
 */
async function recordPayment(orderId) {
    const amount = parseFloat(document.getElementById('paymentAmount')?.value || 0);
    const method = document.getElementById('paymentMethod')?.value || 'cash';
    
    if (amount <= 0) {
        showAlert('Ingresa un monto válido', 'warning');
        return;
    }
    
    const paymentData = {
        order_id: orderId,
        amount: amount,
        payment_method: method,
        reference: document.getElementById('paymentReference')?.value || null
    };
    
    const response = await apiCall('/orders/payment', 'POST', paymentData);
    
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
