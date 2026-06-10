-- Mejoras para el sistema de Abastecimiento
-- Agregar tablas para batch operations, alertas de stock e importación CSV

-- Agregar campos a tabla products
ALTER TABLE products ADD COLUMN IF NOT EXISTS last_restocked_at TIMESTAMP;
ALTER TABLE products ADD COLUMN IF NOT EXISTS reorder_level INTEGER DEFAULT 10;
ALTER TABLE products ADD COLUMN IF NOT EXISTS supplier_id INTEGER REFERENCES suppliers(id) ON DELETE SET NULL;
ALTER TABLE products ADD COLUMN IF NOT EXISTS batch_import_id INTEGER; -- Referencia al lote de importación

-- Índices para productos
CREATE INDEX IF NOT EXISTS idx_products_reorder ON products(reorder_level, stock_quantity);
CREATE INDEX IF NOT EXISTS idx_products_supplier ON products(supplier_id);
CREATE INDEX IF NOT EXISTS idx_products_batch ON products(batch_import_id);

-- Tabla de alertas de stock
CREATE TABLE IF NOT EXISTS stock_alerts (
    id SERIAL PRIMARY KEY,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    alert_type VARCHAR(20) NOT NULL, -- low_stock, out_of_stock, overstock
    current_stock INTEGER NOT NULL,
    reorder_level INTEGER NOT NULL,
    is_resolved BOOLEAN DEFAULT false,
    resolved_at TIMESTAMP,
    resolved_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_stock_alerts_product ON stock_alerts(product_id);
CREATE INDEX IF NOT EXISTS idx_stock_alerts_resolved ON stock_alerts(is_resolved);
CREATE INDEX IF NOT EXISTS idx_stock_alerts_created ON stock_alerts(created_at DESC);

-- Tabla de importaciones en lote
CREATE TABLE IF NOT EXISTS batch_imports (
    id SERIAL PRIMARY KEY,
    imported_by INTEGER NOT NULL REFERENCES users(id),
    import_type VARCHAR(20) NOT NULL DEFAULT 'products', -- products, suppliers, prices
    file_name VARCHAR(255) NOT NULL,
    file_size INTEGER,
    total_rows INTEGER DEFAULT 0,
    successful_rows INTEGER DEFAULT 0,
    failed_rows INTEGER DEFAULT 0,
    status VARCHAR(20) DEFAULT 'pending', -- pending, processing, completed, failed
    error_message TEXT,
    started_at TIMESTAMP,
    completed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_batch_imports_user ON batch_imports(imported_by);
CREATE INDEX IF NOT EXISTS idx_batch_imports_status ON batch_imports(status);
CREATE INDEX IF NOT EXISTS idx_batch_imports_created ON batch_imports(created_at DESC);

-- Tabla de errores de importación
CREATE TABLE IF NOT EXISTS batch_import_errors (
    id SERIAL PRIMARY KEY,
    batch_import_id INTEGER NOT NULL REFERENCES batch_imports(id) ON DELETE CASCADE,
    row_number INTEGER NOT NULL,
    error_message TEXT NOT NULL,
    error_data JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_batch_import_errors_batch ON batch_import_errors(batch_import_id);

-- Tabla de operaciones en lote
CREATE TABLE IF NOT EXISTS batch_operations (
    id SERIAL PRIMARY KEY,
    operation_type VARCHAR(20) NOT NULL, -- update_stock, update_price, delete
    target_type VARCHAR(20) NOT NULL, -- products, suppliers
    target_ids INTEGER[] NOT NULL,
    operation_data JSONB,
    created_by INTEGER NOT NULL REFERENCES users(id),
    status VARCHAR(20) DEFAULT 'pending', -- pending, processing, completed, failed
    total_targets INTEGER DEFAULT 0,
    successful_targets INTEGER DEFAULT 0,
    failed_targets INTEGER DEFAULT 0,
    started_at TIMESTAMP,
    completed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_batch_operations_user ON batch_operations(created_by);
CREATE INDEX IF NOT EXISTS idx_batch_operations_status ON batch_operations(status);

-- Función para crear alerta de stock bajo
CREATE OR REPLACE FUNCTION create_stock_alert(product_id INTEGER, alert_type VARCHAR)
RETURNS INTEGER AS $$
DECLARE
    alert_id INTEGER;
    product_stock INTEGER;
    product_reorder INTEGER;
BEGIN
    SELECT stock_quantity, reorder_level INTO product_stock, product_reorder
    FROM products
    WHERE id = product_id;

    INSERT INTO stock_alerts (product_id, alert_type, current_stock, reorder_level)
    VALUES (product_id, alert_type, product_stock, product_reorder)
    RETURNING id INTO alert_id;

    RETURN alert_id;
END;
$$ LANGUAGE plpgsql;

-- Función para verificar y crear alertas de stock
CREATE OR REPLACE FUNCTION check_and_create_stock_alerts()
RETURNS INTEGER AS $$
DECLARE
    alert_count INTEGER;
BEGIN
    alert_count := 0;

    -- Alertas de stock bajo
    INSERT INTO stock_alerts (product_id, alert_type, current_stock, reorder_level)
    SELECT id, 'low_stock', stock_quantity, reorder_level
    FROM products
    WHERE stock_quantity <= reorder_level
        AND stock_quantity > 0
        AND id NOT IN (
            SELECT product_id FROM stock_alerts
            WHERE alert_type = 'low_stock' AND is_resolved = false
        );

    GET DIAGNOSTICS alert_count = ROW_COUNT;

    -- Alertas de stock agotado
    INSERT INTO stock_alerts (product_id, alert_type, current_stock, reorder_level)
    SELECT id, 'out_of_stock', stock_quantity, reorder_level
    FROM products
    WHERE stock_quantity = 0
        AND id NOT IN (
            SELECT product_id FROM stock_alerts
            WHERE alert_type = 'out_of_stock' AND is_resolved = false
        );

    alert_count := alert_count + ROW_COUNT;

    RETURN alert_count;
END;
$$ LANGUAGE plpgsql;

-- Comentarios
COMMENT ON TABLE stock_alerts IS 'Alertas de stock bajo o agotado';
COMMENT ON TABLE batch_imports IS 'Importaciones en lote de productos y datos';
COMMENT ON TABLE batch_import_errors IS 'Errores detallados de importaciones en lote';
COMMENT ON TABLE batch_operations IS 'Operaciones en lote sobre productos';
COMMENT ON FUNCTION create_stock_alert(INTEGER, VARCHAR) IS 'Crea una alerta de stock para un producto';
COMMENT ON FUNCTION check_and_create_stock_alerts() IS 'Verifica todos los productos y crea alertas de stock necesarias';
