/**
 * Main JavaScript - Truper
 */

// Shopping Cart
class ShoppingCart {
    constructor() {
        this.items = JSON.parse(localStorage.getItem('cart')) || [];
    }

    addItem(productId, name, price, quantity = 1) {
        const existingItem = this.items.find(item => item.productId === productId);
        
        if (existingItem) {
            existingItem.quantity += quantity;
        } else {
            this.items.push({
                productId,
                name,
                price,
                quantity
            });
        }
        
        this.save();
        this.showNotification(`"${name}" agregado al carrito`);
    }

    removeItem(productId) {
        this.items = this.items.filter(item => item.productId !== productId);
        this.save();
    }

    getTotal() {
        return this.items.reduce((total, item) => total + (item.price * item.quantity), 0);
    }

    getItemCount() {
        return this.items.reduce((count, item) => count + item.quantity, 0);
    }

    save() {
        localStorage.setItem('cart', JSON.stringify(this.items));
    }

    showNotification(message) {
        const notification = document.createElement('div');
        notification.className = 'notification';
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => notification.remove(), 3000);
    }
}

// Instancia global del carrito
const cart = new ShoppingCart();

// Agregar item al carrito
function addToCart(productId, name, price) {
    cart.addItem(productId, name, price);
}

// Buscar productos
function searchProducts(query) {
    const products = document.querySelectorAll('.product-card');
    const lowerQuery = query.toLowerCase();
    
    products.forEach(product => {
        const name = product.querySelector('.product-info h3').textContent.toLowerCase();
        const sku = product.querySelector('.product-sku').textContent.toLowerCase();
        
        if (name.includes(lowerQuery) || sku.includes(lowerQuery)) {
            product.style.display = '';
        } else {
            product.style.display = 'none';
        }
    });
}

// Event Listeners
document.addEventListener('DOMContentLoaded', () => {
    // Search input
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            searchProducts(e.target.value);
        });
    }
    
    // Category filter
    const categoryFilter = document.getElementById('category-filter');
    if (categoryFilter) {
        categoryFilter.addEventListener('change', (e) => {
            if (e.target.value) {
                window.location.href = `/views/products.php?category=${e.target.value}`;
            } else {
                window.location.href = '/views/products.php';
            }
        });
    }
});

// Formateo de dinero
function formatMoney(amount) {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
    }).format(amount);
}

// Formateo de fecha
function formatDate(date) {
    return new Date(date).toLocaleDateString('es-MX', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

// Dropdown menu
document.addEventListener('DOMContentLoaded', () => {
    const dropdownItems = document.querySelectorAll('.nav-item-dropdown');
    
    dropdownItems.forEach(item => {
        const link = item.querySelector('.nav-link');
        const menu = item.querySelector('.dropdown-menu');
        
        if (link && menu) {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
            });
            
            document.addEventListener('click', (e) => {
                if (!item.contains(e.target)) {
                    menu.style.display = 'none';
                }
            });
        }
    });
});

// Validar formularios
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;
    
    const inputs = form.querySelectorAll('input[required], textarea[required], select[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.style.borderColor = '#dc3545';
            isValid = false;
        } else {
            input.style.borderColor = '#DDDDDD';
        }
    });
    
    return isValid;
}

// AJAX para envío de datos
function submitFormAjax(formSelector, endpoint) {
    const form = document.querySelector(formSelector);
    if (!form) return;
    
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        
        if (!validateForm(form.id)) {
            alert('Por favor completa todos los campos requeridos');
            return;
        }
        
        const formData = new FormData(form);
        
        fetch(endpoint, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Operación realizada exitosamente');
                form.reset();
                if (data.redirect) {
                    window.location.href = data.redirect;
                }
            } else {
                alert(data.message || 'Error en la operación');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error en la solicitud');
        });
    });
}

// Crear estilos para notificaciones
const style = document.createElement('style');
style.textContent = `
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background-color: #28a745;
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 4px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        animation: slideIn 0.3s ease;
        z-index: 10000;
    }
    
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
`;
document.head.appendChild(style);


