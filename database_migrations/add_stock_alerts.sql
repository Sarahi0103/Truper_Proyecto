-- Tabla de alertas de stock bajo
CREATE TABLE IF NOT EXISTS stock_alerts (
    id SERIAL PRIMARY KEY,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    product_sku VARCHAR(50) NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    current_stock INTEGER NOT NULL,
    low_stock_threshold INTEGER NOT NULL,
    alert_type VARCHAR(20) DEFAULT 'low_stock', -- low_stock, out_of_stock, critical
    is_resolved BOOLEAN DEFAULT FALSE,
    resolved_at TIMESTAMP NULL,
    resolved_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices para mejor rendimiento
CREATE INDEX IF NOT EXISTS idx_stock_alerts_product ON stock_alerts(product_id);
CREATE INDEX IF NOT EXISTS idx_stock_alerts_resolved ON stock_alerts(is_resolved);
CREATE INDEX IF NOT EXISTS idx_stock_alerts_created ON stock_alerts(created_at DESC);

-- Función para crear alerta de stock bajo
CREATE OR REPLACE FUNCTION create_stock_alert(product_id INTEGER, current_stock INTEGER, threshold INTEGER)
RETURNS INTEGER AS $$
DECLARE
    alert_id INTEGER;
    product_rec RECORD;
BEGIN
    -- Obtener información del producto
    SELECT id, sku, name INTO product_rec
    FROM products
    WHERE id = product_id
    LIMIT 1;
    
    IF NOT FOUND THEN
        RETURN 0;
    END IF;
    
    -- Determinar tipo de alerta
    DECLARE alert_type VARCHAR(20);
    BEGIN
        IF current_stock = 0 THEN
            alert_type := 'out_of_stock';
        ELSEIF current_stock <= threshold * 0.5 THEN
            alert_type := 'critical';
        ELSE
            alert_type := 'low_stock';
        END IF;
    END;
    
    -- Verificar si ya existe una alerta no resuelta para este producto
    SELECT id INTO alert_id
    FROM stock_alerts
    WHERE product_id = product_id AND is_resolved = FALSE
    LIMIT 1;
    
    IF alert_id IS NOT NULL THEN
        -- Actualizar alerta existente
        UPDATE stock_alerts
        SET current_stock = current_stock,
            alert_type = alert_type,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = alert_id;
        
        RETURN alert_id;
    ELSE
        -- Crear nueva alerta
        INSERT INTO stock_alerts (product_id, product_sku, product_name, current_stock, low_stock_threshold, alert_type)
        VALUES (product_rec.id, product_rec.sku, product_rec.name, current_stock, threshold, alert_type)
        RETURNING id INTO alert_id;
        
        RETURN alert_id;
    END IF;
END;
$$ LANGUAGE plpgsql;

-- Función para resolver alerta de stock
CREATE OR REPLACE FUNCTION resolve_stock_alert(alert_id INTEGER, resolved_by INTEGER)
RETURNS BOOLEAN AS $$
BEGIN
    UPDATE stock_alerts
    SET is_resolved = TRUE,
        resolved_at = CURRENT_TIMESTAMP,
        resolved_by = resolved_by,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = alert_id;
    
    RETURN FOUND;
END;
$$ LANGUAGE plpgsql;

-- Función para obtener alertas activas
CREATE OR REPLACE FUNCTION get_active_stock_alerts()
RETURNS TABLE (
    id INTEGER,
    product_id INTEGER,
    product_sku VARCHAR(50),
    product_name VARCHAR(255),
    current_stock INTEGER,
    low_stock_threshold INTEGER,
    alert_type VARCHAR(20),
    created_at TIMESTAMP
) AS $$
BEGIN
    RETURN QUERY
    SELECT sa.id, sa.product_id, sa.product_sku, sa.product_name, 
           sa.current_stock, sa.low_stock_threshold, sa.alert_type, sa.created_at
    FROM stock_alerts sa
    WHERE sa.is_resolved = FALSE
    ORDER BY 
        CASE sa.alert_type
            WHEN 'out_of_stock' THEN 1
            WHEN 'critical' THEN 2
            WHEN 'low_stock' THEN 3
        END,
        sa.current_stock ASC,
        sa.created_at DESC;
END;
$$ LANGUAGE plpgsql;

-- Función para escanear productos y crear alertas automáticamente
CREATE OR REPLACE FUNCTION scan_and_create_stock_alerts()
RETURNS INTEGER AS $$
DECLARE
    alert_count INTEGER := 0;
    product_rec RECORD;
BEGIN
    -- Escanear productos con stock bajo
    FOR product_rec IN 
        SELECT id, sku, name, 
               COALESCE(stock_online, stock_quantity) as current_stock,
               COALESCE(low_stock_threshold_online, low_stock_threshold_local, 5) as threshold
        FROM products
        WHERE COALESCE(stock_online, stock_quantity) <= COALESCE(low_stock_threshold_online, low_stock_threshold_local, 5)
    LOOP
        PERFORM create_stock_alert(product_rec.id, product_rec.current_stock, product_rec.threshold);
        alert_count := alert_count + 1;
    END LOOP;
    
    RETURN alert_count;
END;
$$ LANGUAGE plpgsql;

-- Trigger para crear alerta automáticamente cuando el stock se actualiza
CREATE OR REPLACE FUNCTION check_stock_on_update()
RETURNS TRIGGER AS $$
BEGIN
    -- Solo para actualizaciones de stock
    IF TG_OP = 'UPDATE' THEN
        IF NEW.stock_online IS DISTINCT FROM OLD.stock_online OR 
           NEW.stock_quantity IS DISTINCT FROM OLD.stock_quantity THEN
            DECLARE
                current_stock INTEGER;
                threshold INTEGER;
            BEGIN
                current_stock := COALESCE(NEW.stock_online, NEW.stock_quantity);
                threshold := COALESCE(NEW.low_stock_threshold_online, NEW.low_stock_threshold_local, 5);
                
                IF current_stock <= threshold THEN
                    PERFORM create_stock_alert(NEW.id, current_stock, threshold);
                END IF;
            END;
        END IF;
    END IF;
    
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Crear trigger en tabla products
DROP TRIGGER IF EXISTS trg_check_stock_on_update ON products;
CREATE TRIGGER trg_check_stock_on_update
    AFTER UPDATE OF stock_online, stock_quantity, low_stock_threshold_online, low_stock_threshold_local
    ON products
    FOR EACH ROW
    EXECUTE FUNCTION check_stock_on_update();
