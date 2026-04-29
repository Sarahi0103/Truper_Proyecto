-- Script para convertir imágenes base64 a referencias de archivo
-- Ejecutar en la BD: psql -U truper_admin -d truper_platform -f fix_base64_images.sql

-- 1. Crear tabla de backup de imágenes base64
CREATE TABLE IF NOT EXISTS products_base64_backup AS
SELECT id, sku, image_url, variants_json 
FROM products 
WHERE image_url LIKE 'data:image%' OR variants_json LIKE '%data:image%';

-- 2. Actualizar productos que solo tienen imagen por defecto fallida
UPDATE products 
SET image_url = 'images/products/default-product.svg'
WHERE image_url LIKE 'data:image%' AND (image_url IS NULL OR TRIM(image_url) = 'data:image');

-- 3. Actualizar marketplace
CREATE TABLE IF NOT EXISTS marketplace_ce_products_base64_backup AS
SELECT id, sku, image_url, variants_json 
FROM marketplace_ce_products 
WHERE image_url LIKE 'data:image%' OR variants_json LIKE '%data:image%';

UPDATE marketplace_ce_products
SET image_url = 'images/products/default-product.svg'
WHERE image_url LIKE 'data:image%' AND (image_url IS NULL OR TRIM(image_url) = 'data:image');

-- 4. Verificar el resultado
SELECT 
    COUNT(*) as total_products,
    SUM(CASE WHEN image_url LIKE 'data:image%' THEN 1 ELSE 0 END) as still_base64,
    SUM(CASE WHEN image_url = 'images/products/default-product.svg' THEN 1 ELSE 0 END) as using_default
FROM products;

SELECT 
    COUNT(*) as total_marketplace,
    SUM(CASE WHEN image_url LIKE 'data:image%' THEN 1 ELSE 0 END) as still_base64,
    SUM(CASE WHEN image_url = 'images/products/default-product.svg' THEN 1 ELSE 0 END) as using_default
FROM marketplace_ce_products;
