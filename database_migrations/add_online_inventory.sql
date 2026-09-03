-- Migración: Gestión de Inventario Online vs Local
-- Separación de stock visible en tienda en línea vs tienda física
-- Fecha: 2026-08-18

-- Agregar columnas a la tabla products para separar inventario
ALTER TABLE products ADD COLUMN IF NOT EXISTS stock_online INTEGER DEFAULT 0;
ALTER TABLE products ADD COLUMN IF NOT EXISTS stock_local INTEGER DEFAULT 0;
ALTER TABLE products ADD COLUMN IF NOT EXISTS low_stock_threshold_online INTEGER DEFAULT 5;
ALTER TABLE products ADD COLUMN IF NOT EXISTS low_stock_threshold_local INTEGER DEFAULT 10;

-- Actualizar stock_online inicialmente con el stock_quantity existente
UPDATE products SET stock_online = stock_quantity WHERE stock_online = 0 AND stock_quantity IS NOT NULL;
UPDATE products SET stock_local = stock_quantity WHERE stock_local = 0 AND stock_quantity IS NOT NULL;

-- Función para verificar disponibilidad online
CREATE OR REPLACE FUNCTION check_online_availability(p_product_id INTEGER, p_quantity INTEGER)
RETURNS JSON AS $$
DECLARE
    v_stock_online INTEGER;
    v_available BOOLEAN;
    v_message TEXT;
BEGIN
    SELECT COALESCE(stock_online, 0) INTO v_stock_online
    FROM products
    WHERE id = p_product_id;
    
    IF v_stock_online >= p_quantity THEN
        v_available := true;
        v_message := 'Stock disponible';
    ELSE
        v_available := false;
        v_message := 'Stock insuficiente. Disponible: ' || v_stock_online;
    END IF;
    
    RETURN json_build_object(
        'available', v_available,
        'stock_online', v_stock_online,
        'message', v_message
    );
END;
$$ LANGUAGE plpgsql;

-- Función para deducir stock online
CREATE OR REPLACE FUNCTION deduct_online_stock(p_product_id INTEGER, p_quantity INTEGER)
RETURNS JSON AS $$
DECLARE
    v_stock_online INTEGER;
    v_success BOOLEAN;
    v_message TEXT;
BEGIN
    SELECT COALESCE(stock_online, 0) INTO v_stock_online
    FROM products
    WHERE id = p_product_id
    FOR UPDATE;
    
    IF v_stock_online >= p_quantity THEN
        UPDATE products
        SET stock_online = stock_online - p_quantity,
            updated_at = NOW()
        WHERE id = p_product_id;
        
        v_success := true;
        v_message := 'Stock deducido correctamente';
    ELSE
        v_success := false;
        v_message := 'Stock insuficiente';
    END IF;
    
    RETURN json_build_object(
        'success', v_success,
        'message', v_message,
        'remaining_stock', v_stock_online
    );
END;
$$ LANGUAGE plpgsql;

-- Función para transferir stock de local a online
CREATE OR REPLACE FUNCTION transfer_stock_to_online(p_product_id INTEGER, p_quantity INTEGER)
RETURNS JSON AS $$
DECLARE
    v_stock_local INTEGER;
    v_success BOOLEAN;
    v_message TEXT;
BEGIN
    SELECT COALESCE(stock_local, 0) INTO v_stock_local
    FROM products
    WHERE id = p_product_id
    FOR UPDATE;
    
    IF v_stock_local >= p_quantity THEN
        UPDATE products
        SET stock_local = stock_local - p_quantity,
            stock_online = COALESCE(stock_online, 0) + p_quantity,
            updated_at = NOW()
        WHERE id = p_product_id;
        
        v_success := true;
        v_message := 'Stock transferido correctamente';
    ELSE
        v_success := false;
        v_message := 'Stock local insuficiente';
    END IF;
    
    RETURN json_build_object(
        'success', v_success,
        'message', v_message,
        'remaining_local', v_stock_local
    );
END;
$$ LANGUAGE plpgsql;

-- Función para transferir stock de online a local
CREATE OR REPLACE FUNCTION transfer_stock_to_local(p_product_id INTEGER, p_quantity INTEGER)
RETURNS JSON AS $$
DECLARE
    v_stock_online INTEGER;
    v_success BOOLEAN;
    v_message TEXT;
BEGIN
    SELECT COALESCE(stock_online, 0) INTO v_stock_online
    FROM products
    WHERE id = p_product_id
    FOR UPDATE;
    
    IF v_stock_online >= p_quantity THEN
        UPDATE products
        SET stock_online = stock_online - p_quantity,
            stock_local = COALESCE(stock_local, 0) + p_quantity,
            updated_at = NOW()
        WHERE id = p_product_id;
        
        v_success := true;
        v_message := 'Stock transferido correctamente';
    ELSE
        v_success := false;
        v_message := 'Stock online insuficiente';
    END IF;
    
    RETURN json_build_object(
        'success', v_success,
        'message', v_message,
        'remaining_online', v_stock_online
    );
END;
$$ LANGUAGE plpgsql;

-- Vista de productos con bajo stock online
CREATE OR REPLACE VIEW low_stock_online AS
SELECT id, name, sku, stock_online, low_stock_threshold_online
FROM products
WHERE stock_online <= low_stock_threshold_online
AND is_active = true
ORDER BY stock_online ASC;

-- Comentarios
COMMENT ON COLUMN products.stock_online IS 'Stock disponible para venta en línea';
COMMENT ON COLUMN products.stock_local IS 'Stock disponible en tienda física';
COMMENT ON COLUMN products.low_stock_threshold_online IS 'Umbral para alertas de bajo stock online';
COMMENT ON COLUMN products.low_stock_threshold_local IS 'Umbral para alertas de bajo stock local';
