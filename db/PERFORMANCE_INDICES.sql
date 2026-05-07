-- ============================================================
-- ÍNDICES PARA OPTIMIZAR PERFORMANCE DEL SISTEMA TRUPER
-- ============================================================

-- 1. Índices en tabla products (acelera búsqueda de SKU)
CREATE INDEX IF NOT EXISTS idx_products_sku ON products(sku);
CREATE INDEX IF NOT EXISTS idx_products_name ON products(name);
CREATE INDEX IF NOT EXISTS idx_products_barcode ON products(barcode);
CREATE INDEX IF NOT EXISTS idx_products_visible ON products(visible);

-- 2. Índices en tabla marketplace_ce_products
CREATE INDEX IF NOT EXISTS idx_marketplace_sku ON marketplace_ce_products(sku);
CREATE INDEX IF NOT EXISTS idx_marketplace_name ON marketplace_ce_products(name);
CREATE INDEX IF NOT EXISTS idx_marketplace_visible ON marketplace_ce_products(visible);

-- 3. Índices en tabla product_categories
CREATE INDEX IF NOT EXISTS idx_categories_name ON product_categories(name);
CREATE INDEX IF NOT EXISTS idx_categories_active ON product_categories(is_active);

-- 4. Índices compuestos para búsquedas frecuentes
CREATE INDEX IF NOT EXISTS idx_products_sku_visible ON products(sku, visible);
CREATE INDEX IF NOT EXISTS idx_marketplace_sku_visible ON marketplace_ce_products(sku, visible);

-- 5. Verificar índices creados
SELECT 
    schemaname,
    tablename,
    indexname
FROM pg_indexes
WHERE schemaname = 'public'
ORDER BY tablename, indexname;
