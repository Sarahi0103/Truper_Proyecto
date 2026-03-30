/**
 * Products JavaScript - Truper
 */

// Cargar productos dinÃ¡micamente
function loadProducts(filters = {}) {
    const formData = new FormData();
    Object.keys(filters).forEach(key => {
        formData.append(key, filters[key]);
    });
    
    fetch('/backend/controllers/product_controller.php?action=search', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderProducts(data.products);
        }
    });
}

// Renderizar productos
function renderProducts(products) {
    const grid = document.getElementById('products-grid');
    if (!grid) return;
    
    grid.innerHTML = '';
    
    products.forEach(product => {
        const card = document.createElement('div');
        card.className = 'product-card';
        card.innerHTML = `
            <div class="product-image">
                <img src="/assets/img/placeholder.png" alt="${product.name}">
            </div>
            <div class="product-info">
                <h3>${product.name}</h3>
                <p class="product-sku">SKU: ${product.sku}</p>
                <p class="product-description">${product.description.substring(0, 100)}...</p>
                <div class="product-footer">
                    <span class="product-price">$${parseFloat(product.sell_price).toFixed(2)}</span>
                    <button class="btn-small btn-add-cart" onclick="addToCart(${product.id}, '${product.name}', ${product.sell_price})">Agregar</button>
                </div>
            </div>
        `;
        grid.appendChild(card);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    // Los productos se cargan desde PHP inicialmente
});


