<?php
require_once __DIR__ . '/../backend/config/security.php';
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/models/Product.php';

$product_model = new Product();
$products = $product_model->getAll();
$category_filter = $_GET['category'] ?? null;

if (!function_exists('public_catalog_normalize_image_path')) {
    function public_catalog_normalize_image_path(string $path): string {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        $path = preg_replace('/\?.*$/', '', $path) ?? $path;
        $path = ltrim($path, '/');
        if (strpos($path, 'images/') === 0) {
            return $path;
        }

        return $path;
    }
}

if (!function_exists('public_catalog_resolve_images_by_product_code')) {
    function public_catalog_resolve_images_by_product_code(string $code, array $productRow = []): array {
        $code = trim($code);
        $images = [];

        $mergeImage = function (string $value) use (&$images) {
            $value = public_catalog_normalize_image_path($value);
            if ($value === '' || strpos($value, 'default-product.svg') !== false) {
                return;
            }
            if (!in_array($value, $images, true)) {
                $images[] = $value;
            }
        };

        $imageUrl = public_catalog_normalize_image_path((string)($productRow['image_url'] ?? ''));
        if ($imageUrl !== '' && strpos($imageUrl, 'default-product.svg') === false) {
            $mergeImage($imageUrl);
        }

        if (!empty($productRow['variants_json'])) {
            $decoded = json_decode((string)$productRow['variants_json'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    $mergeImage((string)$item);
                }
            }
        }

        if (!empty($images)) {
            return $images;
        }

        $galleryRoot = __DIR__ . '/../images/products/gallery/' . $code;
        if (is_dir($galleryRoot)) {
            $matches = glob($galleryRoot . '/*.{jpg,jpeg,png,webp,gif,JPG,JPEG,PNG,WEBP,GIF}', GLOB_BRACE);
            if (is_array($matches) && !empty($matches)) {
                usort($matches, function ($a, $b) {
                    return strcmp((string)$a, (string)$b);
                });

                foreach ($matches as $match) {
                    $mergeImage('images/products/gallery/' . $code . '/' . basename($match));
                }
            }
        }

        if (empty($images)) {
            $images[] = 'images/products/default-product.svg';
        }

        return $images;
    }
}

if ($category_filter) {
    $products = $product_model->getByCategory($category_filter);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo de Productos - Truper</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/products.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">Truper</div>
            <ul class="nav-menu">
                <li><a href="/index.php">Inicio</a></li>
                <li><a href="/views/products.php">Catálogo</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="/views/dashboard.php">Dashboard</a></li>
                    <li><a href="/backend/controllers/logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="/views/login.php">Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <div class="products-container">
        <aside class="products-sidebar">
            <h3>Filtrar</h3>
            <div class="filter-group">
                <label>Búsqueda</label>
                <input type="text" id="search-input" placeholder="Buscar producto..." class="search-input">
            </div>
            
            <div class="filter-group">
                <label>Categoría</label>
                <select id="category-filter" class="form-control">
                    <option value="">Todas</option>
                    <option value="Herramientas">Herramientas</option>
                    <option value="Hardware">Hardware</option>
                    <option value="Electrónica">Electrónica</option>
                    <option value="Industrial">Industrial</option>
                </select>
            </div>
        </aside>

        <main class="products-main">
            <div class="products-header">
                <h1>Catálogo de Productos</h1>
                <p>Amplia selección de herramientas y productos de calidad</p>
            </div>

            <div class="products-grid" id="products-grid">
                <?php foreach ($products as $product): ?>
                <?php $productImages = public_catalog_resolve_images_by_product_code((string)($product['sku'] ?? ''), $product); ?>
                <div class="product-card">
                    <div class="product-image">
                        <img src="/<?php echo htmlspecialchars($productImages[0] ?? 'images/products/default-product.svg'); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                    </div>
                    <div class="product-info">
                        <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p class="product-sku">SKU: <?php echo htmlspecialchars($product['sku']); ?></p>
                        <p class="product-description"><?php echo substr($product['description'], 0, 100); ?>...</p>
                        <div class="product-footer">
                            <span class="product-price">$<?php echo number_format($product['sell_price'], 2); ?></span>
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <button class="btn-small btn-add-cart" onclick="addToCart(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>', <?php echo $product['sell_price']; ?>)">Agregar</button>
                            <?php else: ?>
                                <a href="/views/login.php" class="btn-small">Login</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>

    <footer class="footer">
        <p>&copy; 2024 Truper. Todos los derechos reservados.</p>
    </footer>

    <script src="/assets/js/products.js"></script>
</body>
</html>


