<?php
require_once '../config/config.php';
require_login();
require_admin();
$user_name = htmlspecialchars($_SESSION['name'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escáner de Inventario - Ferretería FOX</title>
    <link rel="stylesheet" href="css/styles.css?v=4.1">
    <style>
        body { background: #08080a; color: #fff; margin: 0; padding: 0; }
        .scanner-container { max-width: 1400px; margin: 0 auto; padding: 2rem 1.5rem; }
        .scanner-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .scanner-title { font-size: 2rem; font-weight: 800; margin: 0; background: linear-gradient(90deg, #fff, #ff7f00); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .scanner-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; }
        .card { background: #111; border: 1px solid #222; border-radius: 12px; padding: 1.5rem; }
        .card-header { font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem; color: #ff7f00; }
        .scan-input { width: 100%; padding: 1rem; font-size: 1.1rem; background: #1a1a1a; border: 2px solid #333; border-radius: 8px; color: #fff; }
        .stock-mode-selector { display: flex; gap: 1rem; margin: 1rem 0; }
        .stock-mode-btn { flex: 1; padding: 0.75rem; border: 2px solid #333; background: #1a1a1a; color: #fff; border-radius: 8px; cursor: pointer; }
        .stock-mode-btn.active { border-color: #ff7f00; background: rgba(255,127,0,0.1); }
        .product-info { background: #1a1a1a; border-radius: 8px; padding: 1.5rem; margin: 1rem 0; display: none; }
        .product-info.show { display: block; }
        .stock-display { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin: 1rem 0; }
        .stock-item { background: #222; padding: 1rem; border-radius: 8px; text-align: center; }
        .stock-value { font-size: 1.5rem; font-weight: 700; color: #ff7f00; }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .btn-primary { background: #ff7f00; color: #fff; }
        .btn-secondary { background: #333; color: #fff; }
        .history-item { background: #1a1a1a; border-radius: 8px; padding: 1rem; margin-bottom: 0.75rem; border-left: 3px solid #ff7f00; }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <a href="index.php" class="logo"><img src="img/logo_fox.png" alt="Ferretería FOX" style="height: 42px;"></a>
            <nav class="nav-menu">
                <a href="admin_online_orders.php">Pedidos Online</a>
                <a href="inventory_scanner.php" class="active">Escáner Inventario</a>
                <a href="profile.php">Perfil</a>
            </nav>
            <div class="user-menu">
                <span><?php echo $user_name; ?></span>
                <button onclick="logout()" class="btn-logout">Cerrar Sesión</button>
            </div>
        </div>
    </header>

    <div class="scanner-container">
        <div class="scanner-header">
            <h1 class="scanner-title">📷 Escáner de Inventario</h1>
            <a href="admin_online_orders.php" class="btn btn-secondary">Volver a Pedidos</a>
        </div>

        <div class="scanner-grid">
            <div class="card">
                <div class="card-header">Escaneo de Productos</div>
                <input type="text" id="scanInput" class="scan-input" placeholder="Escanear código de barras o SKU..." autofocus>
                
                <div class="stock-mode-selector">
                    <button class="stock-mode-btn active" data-mode="online" onclick="setStockMode('online')">Stock Online</button>
                    <button class="stock-mode-btn" data-mode="local" onclick="setStockMode('local')">Stock Local</button>
                </div>
                
                <div id="productInfo" class="product-info">
                    <div class="product-name" id="productName">-</div>
                    <div class="product-sku" id="productSku">-</div>
                    
                    <div class="stock-display">
                        <div class="stock-item">
                            <div>Stock Online</div>
                            <div class="stock-value" id="stockOnline">0</div>
                        </div>
                        <div class="stock-item">
                            <div>Stock Local</div>
                            <div class="stock-value" id="stockLocal">0</div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 1rem;">
                        <label>Cantidad a agregar:</label>
                        <input type="number" id="stockQuantity" value="1" min="1" style="width: 100%; padding: 0.5rem; background: #1a1a1a; border: 1px solid #333; color: #fff;">
                    </div>
                    
                    <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                        <button class="btn btn-primary" onclick="updateStock()" style="flex: 1;">Actualizar Stock</button>
                        <button class="btn btn-secondary" onclick="clearProduct()" style="flex: 1;">Limpiar</button>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">Historial de Escaneos</div>
                <div id="scanHistory">
                    <div style="text-align: center; padding: 2rem; color: #888;">No hay escaneos hoy</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentProduct = null;
        let stockMode = 'online';

        function setStockMode(mode) {
            stockMode = mode;
            document.querySelectorAll('.stock-mode-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.mode === mode);
            });
            document.getElementById('stockInputLabel').textContent = `Cantidad a agregar (Stock ${mode === 'online' ? 'Online' : 'Local'}):`;
        }

        document.getElementById('scanInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const code = this.value.trim();
                if (code) searchProduct(code);
            }
        });

        async function searchProduct(code) {
            try {
                const response = await fetch(`api/inventory.php?action=search&code=${encodeURIComponent(code)}`);
                const data = await response.json();
                
                if (data.success && data.product) {
                    currentProduct = data.product;
                    document.getElementById('productName').textContent = data.product.name;
                    document.getElementById('productSku').textContent = 'SKU: ' + data.product.sku;
                    document.getElementById('stockOnline').textContent = data.product.stock_online || 0;
                    document.getElementById('stockLocal').textContent = data.product.stock_local || 0;
                    document.getElementById('productInfo').classList.add('show');
                    document.getElementById('scanInput').value = '';
                } else {
                    alert('Producto no encontrado');
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        async function updateStock() {
            if (!currentProduct) return;
            
            const quantity = parseInt(document.getElementById('stockQuantity').value);
            const column = stockMode === 'online' ? 'stock_online' : 'stock_local';
            
            try {
                const response = await fetch('api/inventory.php?action=update', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        product_id: currentProduct.id,
                        column: column,
                        quantity: quantity
                    })
                });
                const data = await response.json();
                
                if (data.success) {
                    addToHistory(currentProduct, column, quantity);
                    searchProduct(currentProduct.sku);
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        function clearProduct() {
            currentProduct = null;
            document.getElementById('productInfo').classList.remove('show');
            document.getElementById('scanInput').focus();
        }

        function addToHistory(product, column, quantity) {
            const history = document.getElementById('scanHistory');
            const item = document.createElement('div');
            item.className = 'history-item';
            item.innerHTML = `
                <div style="font-weight: 600; color: #fff;">${product.name}</div>
                <div style="font-size: 0.9rem; color: #888;">${column}: +${quantity} - ${new Date().toLocaleTimeString()}</div>
            `;
            history.insertBefore(item, history.firstChild);
        }

        function logout() {
            window.location.href = 'api/auth.php?action=logout';
        }
    </script>
</body>
</html>
