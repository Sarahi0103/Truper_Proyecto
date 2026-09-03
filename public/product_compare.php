<?php
/**
 * Comparador de Productos
 * Permite comparar características de múltiples productos
 */

require_once '../config/config.php';

$productIds = $_GET['products'] ?? [];
if (!is_array($productIds) || count($productIds) < 2 || count($productIds) > 4) {
    header('Location: tienda.php');
    exit;
}

// Obtener productos a comparar
$placeholders = implode(',', array_fill(0, count($productIds), '?'));
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.id IN ({$placeholders}) AND p.is_online_visible = true
");
$stmt->execute($productIds);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($products) < 2) {
    header('Location: tienda.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comparar Productos - Ferretería FOX</title>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <link rel="stylesheet" href="css/styles.css?v=4.1">
    <link rel="stylesheet" href="css/theme.css?v=4.1">
    <style>
        body {
            background: #08080a;
            color: #fff;
            margin: 0;
            padding: 0;
        }
        
        .compare-container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        
        .compare-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .compare-title {
            font-size: 2rem;
            font-weight: 800;
            margin: 0;
            background: linear-gradient(90deg, #fff, #ff7f00);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .compare-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .compare-table th {
            background: #111;
            border: 1px solid #222;
            padding: 1.5rem;
            text-align: center;
            vertical-align: top;
            width: 25%;
        }
        
        .compare-table td {
            background: #0a0a0a;
            border: 1px solid #222;
            padding: 1rem;
            text-align: center;
            vertical-align: middle;
        }
        
        .compare-table tr:nth-child(even) td {
            background: #0d0d0d;
        }
        
        .feature-label {
            background: #111;
            border: 1px solid #222;
            padding: 1rem;
            font-weight: 700;
            color: #888;
            text-align: left;
            width: 20%;
        }
        
        .product-image {
            width: 150px;
            height: 150px;
            object-fit: contain;
            margin-bottom: 1rem;
        }
        
        .product-name {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #fff;
        }
        
        .product-sku {
            font-family: monospace;
            color: #888;
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
        }
        
        .product-price {
            font-size: 1.5rem;
            font-weight: 800;
            color: #ff7f00;
            margin-bottom: 1rem;
        }
        
        .feature-value {
            color: #fff;
            font-size: 0.95rem;
        }
        
        .feature-value.highlight {
            color: #22c55e;
            font-weight: 700;
        }
        
        .feature-value.lowlight {
            color: #ef4444;
        }
        
        .add-to-cart-btn {
            background: #ff7f00;
            color: #fff;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 1rem;
        }
        
        .add-to-cart-btn:hover {
            background: #ff9900;
        }
        
        .remove-btn {
            background: #ef4444;
            color: #fff;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            margin-top: 0.5rem;
        }
        
        .back-btn {
            background: #222;
            color: #fff;
            border: 1px solid #333;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .back-btn:hover {
            background: #333;
        }
        
        @media (max-width: 900px) {
            .compare-table {
                display: block;
                overflow-x: auto;
            }
            
            .compare-table th, .compare-table td {
                min-width: 200px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <a href="tienda.php" class="logo"><img src="img/logo_fox.png" alt="Ferretería FOX" style="height: 42px;"></a>
            <nav class="nav-menu">
                <a href="tienda.php">Tienda en Línea</a>
                <a href="product_compare.php" class="active">Comparador</a>
                <a href="cart.php">Carrito</a>
            </nav>
        </div>
    </header>

    <div class="compare-container">
        <div class="compare-header">
            <h1 class="compare-title">⚖️ Comparador de Productos</h1>
            <a href="tienda.php" class="back-btn">← Volver a Tienda</a>
        </div>

        <table class="compare-table">
            <thead>
                <tr>
                    <th class="feature-label">Característica</th>
                    <?php foreach ($products as $product): ?>
                    <th>
                        <img src="<?php echo htmlspecialchars($product['image_url'] ?? '/img/no-image.png'); ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>" 
                             class="product-image">
                        <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                        <div class="product-sku">SKU: <?php echo htmlspecialchars($product['sku']); ?></div>
                        <div class="product-price">$<?php echo number_format($product['price'], 2); ?></div>
                        <button onclick="addToCart(<?php echo $product['id']; ?>)" class="add-to-cart-btn">
                            Agregar al Carrito
                        </button>
                        <button onclick="removeFromCompare(<?php echo $product['id']; ?>)" class="remove-btn">
                            Eliminar
                        </button>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="feature-label">Precio</td>
                    <?php 
                    $prices = array_column($products, 'price');
                    $minPrice = min($prices);
                    $maxPrice = max($prices);
                    foreach ($products as $product): 
                        $isMin = $product['price'] == $minPrice;
                        $isMax = $product['price'] == $maxPrice;
                    ?>
                    <td>
                        <span class="feature-value <?php echo $isMin ? 'highlight' : ($isMax ? 'lowlight' : ''); ?>">
                            $<?php echo number_format($product['price'], 2); ?>
                        </span>
                        <?php if ($isMin): ?><span style="color: #22c55e; font-size: 0.75rem;"> (Mejor precio)</span><?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td class="feature-label">Categoría</td>
                    <?php foreach ($products as $product): ?>
                    <td class="feature-value"><?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td class="feature-label">Stock Disponible</td>
                    <?php 
                    $stocks = array_column($products, 'stock_online');
                    $maxStock = max($stocks);
                    foreach ($products as $product): 
                        $stock = $product['stock_online'] ?? $product['stock_quantity'] ?? 0;
                        $isMax = $stock == $maxStock && $stock > 0;
                    ?>
                    <td>
                        <span class="feature-value <?php echo $isMax ? 'highlight' : ($stock == 0 ? 'lowlight' : ''); ?>">
                            <?php echo $stock > 0 ? $stock : 'Agotado'; ?>
                        </span>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td class="feature-label">Descripción</td>
                    <?php foreach ($products as $product): ?>
                    <td class="feature-value" style="text-align: left; font-size: 0.85rem;">
                        <?php echo htmlspecialchars(substr($product['description'] ?? 'Sin descripción', 0, 150)); ?>...
                    </td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td class="feature-label">Garantía</td>
                    <?php foreach ($products as $product): ?>
                    <td class="feature-value"><?php echo htmlspecialchars($product['warranty'] ?? 'N/A'); ?></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td class="feature-label">Marca</td>
                    <?php foreach ($products as $product): ?>
                    <td class="feature-value"><?php echo htmlspecialchars($product['brand'] ?? 'N/A'); ?></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td class="feature-label">Modelo</td>
                    <?php foreach ($products as $product): ?>
                    <td class="feature-value"><?php echo htmlspecialchars($product['model'] ?? 'N/A'); ?></td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>
    </div>

    <script src="js/catalog.js?v=2.6"></script>
    <script>
        function addToCart(productId) {
            // Implementar lógica de agregar al carrito
            alert('Producto agregado al carrito');
        }
        
        function removeFromCompare(productId) {
            const productIds = <?php echo json_encode($productIds); ?>;
            const newIds = productIds.filter(id => id !== productId);
            
            if (newIds.length < 2) {
                alert('Debes tener al menos 2 productos para comparar');
                return;
            }
            
            window.location.href = 'product_compare.php?products=' + newIds.join(',');
        }
    </script>
</body>
</html>
