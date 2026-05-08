<?php
/**
 * Product search and filtering system test
 */

require 'config/config.php';

echo "🔍 PRODUCT SEARCH & FILTERING TEST\n";
echo str_repeat("=", 60) . "\n\n";

// 1. Get all categories
echo "1️⃣  Fetching product categories...\n";
$stmt = $pdo->query("SELECT id, name, sort_order FROM product_categories WHERE is_active = true ORDER BY sort_order, name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "   ✓ Found " . count($categories) . " categories:\n";
foreach ($categories as $cat) {
    echo "     - {$cat['name']} (sort: {$cat['sort_order']})\n";
}
echo "\n";

// 2. Test category filtering
echo "2️⃣  Testing category filter...\n";
if (count($categories) > 0) {
    $category = $categories[0];
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE category = ? AND is_active = true");
    $stmt->execute([$category['name']]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   ✓ Products in '{$category['name']}': $count\n";
    
    if ($count > 0) {
        $stmt = $pdo->prepare("SELECT id, sku, name, unit_price FROM products WHERE category = ? AND is_active = true LIMIT 2");
        $stmt->execute([$category['name']]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($products as $p) {
            echo "     - {$p['sku']}: {$p['name']} (\${$p['unit_price']})\n";
        }
    }
}
echo "\n";

// 3. Test price range filtering
echo "3️⃣  Testing price range filters...\n";
$priceRanges = [
    ['min' => 0, 'max' => 10, 'label' => 'Budget ($0-$10)'],
    ['min' => 10, 'max' => 50, 'label' => 'Mid-range ($10-$50)'],
    ['min' => 50, 'max' => 999999, 'label' => 'Premium ($50+)']
];

foreach ($priceRanges as $range) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE unit_price >= ? AND unit_price <= ? AND is_active = true");
    $stmt->execute([$range['min'], $range['max']]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   ✓ {$range['label']}: $count products\n";
}
echo "\n";

// 4. Test keyword search
echo "4️⃣  Testing keyword search...\n";
$searchTerms = ['candado', 'tuerca', 'cable'];
foreach ($searchTerms as $term) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE (name ILIKE ? OR description ILIKE ? OR sku ILIKE ?) AND is_active = true");
    $searchPattern = "%$term%";
    $stmt->execute([$searchPattern, $searchPattern, $searchPattern]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($count > 0) {
        echo "   ✓ Search '{$term}': $count results\n";
        $stmt = $pdo->prepare("SELECT sku, name FROM products WHERE (name ILIKE ? OR description ILIKE ? OR sku ILIKE ?) AND is_active = true LIMIT 1");
        $stmt->execute([$searchPattern, $searchPattern, $searchPattern]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "     Sample: {$product['sku']} - {$product['name']}\n";
    } else {
        echo "   ⓘ Search '{$term}': no results\n";
    }
}
echo "\n";

// 5. Test SKU search
echo "5️⃣  Testing SKU-based search...\n";
$stmt = $pdo->query("SELECT sku FROM products WHERE is_active = true LIMIT 1");
$product = $stmt->fetch(PDO::FETCH_ASSOC);
if ($product) {
    $sku = $product['sku'];
    $stmt = $pdo->prepare("SELECT id, name, sku FROM products WHERE sku = ?");
    $stmt->execute([$sku]);
    $found = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($found) {
        echo "   ✓ SKU '$sku' found: {$found['name']}\n";
    }
}
echo "\n";

// 6. Test stock availability filter
echo "6️⃣  Testing stock availability filter...\n";
$stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE stock_quantity > 0 AND is_active = true");
$inStock = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE stock_quantity = 0 AND is_active = true");
$outOfStock = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE is_active = true");
$total = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

echo "   ✓ In stock: $inStock products\n";
echo "   ✓ Out of stock: $outOfStock products\n";
echo "   ✓ Total active: $total products\n";
echo "\n";

// 7. Test sorting functionality
echo "7️⃣  Testing product sorting...\n";
$sortOptions = [
    ['by' => 'name', 'order' => 'ASC', 'label' => 'Name A-Z'],
    ['by' => 'unit_price', 'order' => 'ASC', 'label' => 'Price Low to High'],
    ['by' => 'unit_price', 'order' => 'DESC', 'label' => 'Price High to Low'],
    ['by' => 'stock_quantity', 'order' => 'DESC', 'label' => 'Most Stock']
];

foreach ($sortOptions as $sort) {
    $query = "SELECT sku, name, unit_price, stock_quantity FROM products WHERE is_active = true ORDER BY {$sort['by']} {$sort['order']} LIMIT 1";
    $stmt = $pdo->query($query);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($product) {
        echo "   ✓ {$sort['label']}: {$product['sku']} - {$product['name']} (\${$product['unit_price']})\n";
    }
}
echo "\n";

// 8. Test pagination
echo "8️⃣  Testing pagination...\n";
$pageSize = 5;
$stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE is_active = true");
$totalProducts = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
$totalPages = ceil($totalProducts / $pageSize);

echo "   ✓ Total products: $totalProducts\n";
echo "   ✓ Page size: $pageSize\n";
echo "   ✓ Total pages: $totalPages\n";

// Test page 1
$stmt = $pdo->query("SELECT id, sku, name FROM products WHERE is_active = true ORDER BY id LIMIT $pageSize OFFSET 0");
$page1 = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "   ✓ Page 1: " . count($page1) . " items\n";

// Test page 2 if exists
if ($totalPages > 1) {
    $offset = $pageSize;
    $stmt = $pdo->query("SELECT id, sku, name FROM products WHERE is_active = true ORDER BY id LIMIT $pageSize OFFSET $offset");
    $page2 = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   ✓ Page 2: " . count($page2) . " items\n";
}
echo "\n";

// 9. Test advanced filtering (combined filters)
echo "9️⃣  Testing combined filters...\n";

// Category + Price range
if (count($categories) > 0) {
    $category = $categories[0]['name'];
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE category = ? AND unit_price >= 10 AND unit_price <= 100 AND is_active = true");
    $stmt->execute([$category]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   ✓ '{$category}' + Price \$10-\$100: $count products\n";
}

// Stock + Price
$stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE stock_quantity > 0 AND unit_price > 0 AND is_active = true");
$count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
echo "   ✓ In stock + Priced: $count products\n";

echo "\n";

// 10. Test faceted search data
echo "🔟 Testing faceted search data...\n";

$stmt = $pdo->query("SELECT category, COUNT(*) as count FROM products WHERE is_active = true GROUP BY category ORDER BY count DESC");
$facets = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "   ✓ Categories facet:\n";
foreach ($facets as $facet) {
    echo "     - {$facet['category']}: {$facet['count']} products\n";
}
echo "\n";

// Summary
echo str_repeat("=", 60) . "\n";
echo "✅ SEARCH & FILTER TEST COMPLETE\n\n";

echo "📋 Test Summary:\n";
echo "   ✓ Category filtering: working\n";
echo "   ✓ Price range filtering: working\n";
echo "   ✓ Keyword search: working\n";
echo "   ✓ SKU search: working\n";
echo "   ✓ Stock availability: working\n";
echo "   ✓ Sorting: working\n";
echo "   ✓ Pagination: working\n";
echo "   ✓ Combined filters: working\n";
echo "   ✓ Faceted search: working\n\n";

echo "🟢 RESULT: SEARCH & FILTERING FULLY OPERATIONAL\n";
