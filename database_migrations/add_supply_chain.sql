-- Tabla de proveedores
CREATE TABLE IF NOT EXISTS suppliers (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    contact_name VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(50),
    address TEXT,
    rfc VARCHAR(13),
    tax_regime VARCHAR(50),
    payment_terms VARCHAR(100),
    lead_time_days INTEGER DEFAULT 7,
    is_active BOOLEAN DEFAULT TRUE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices para proveedores
CREATE INDEX IF NOT EXISTS idx_suppliers_name ON suppliers(name);
CREATE INDEX IF NOT EXISTS idx_suppliers_active ON suppliers(is_active);

-- Tabla de pedidos de compra
CREATE TABLE IF NOT EXISTS purchase_orders (
    id SERIAL PRIMARY KEY,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    supplier_id INTEGER REFERENCES suppliers(id) ON DELETE SET NULL,
    status VARCHAR(20) DEFAULT 'pending', -- pending, sent, received, cancelled
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expected_delivery_date TIMESTAMP,
    received_date TIMESTAMP NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    notes TEXT,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices para pedidos de compra
CREATE INDEX IF NOT EXISTS idx_purchase_orders_supplier ON purchase_orders(supplier_id);
CREATE INDEX IF NOT EXISTS idx_purchase_orders_status ON purchase_orders(status);
CREATE INDEX IF NOT EXISTS idx_purchase_orders_date ON purchase_orders(order_date DESC);

-- Tabla de items de pedido de compra
CREATE TABLE IF NOT EXISTS purchase_order_items (
    id SERIAL PRIMARY KEY,
    purchase_order_id INTEGER REFERENCES purchase_orders(id) ON DELETE CASCADE,
    product_id INTEGER REFERENCES products(id) ON DELETE SET NULL,
    quantity_ordered INTEGER NOT NULL,
    quantity_received INTEGER DEFAULT 0,
    unit_cost DECIMAL(10,2) NOT NULL,
    total_cost DECIMAL(10,2) NOT NULL,
    notes TEXT
);

-- Índices para items de pedido
CREATE INDEX IF NOT EXISTS idx_purchase_order_items_order ON purchase_order_items(purchase_order_id);
CREATE INDEX IF NOT EXISTS idx_purchase_order_items_product ON purchase_order_items(product_id);

-- Tabla de predicción de demanda
CREATE TABLE IF NOT EXISTS demand_forecasts (
    id SERIAL PRIMARY KEY,
    product_id INTEGER REFERENCES products(id) ON DELETE CASCADE,
    forecast_date DATE NOT NULL,
    predicted_demand INTEGER NOT NULL,
    actual_demand INTEGER,
    accuracy DECIMAL(5,2),
    forecast_method VARCHAR(50), -- moving_average, linear_regression, seasonal
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(product_id, forecast_date)
);

-- Índices para predicciones
CREATE INDEX IF NOT EXISTS idx_demand_forecasts_product ON demand_forecasts(product_id);
CREATE INDEX IF NOT EXISTS idx_demand_forecasts_date ON demand_forecasts(forecast_date);

-- Función para crear pedido de compra
CREATE OR REPLACE FUNCTION create_purchase_order(
    p_supplier_id INTEGER,
    p_expected_delivery_date TIMESTAMP,
    p_notes TEXT,
    p_created_by INTEGER
)
RETURNS INTEGER AS $$
DECLARE
    v_order_number VARCHAR(50);
    v_order_id INTEGER;
BEGIN
    -- Generar número de orden
    v_order_number := 'OC-' || TO_CHAR(CURRENT_DATE, 'YYYY') || '-' || LPAD(nextval('purchase_order_seq')::TEXT, 6, '0');
    
    INSERT INTO purchase_orders (order_number, supplier_id, expected_delivery_date, notes, created_by)
    VALUES (v_order_number, p_supplier_id, p_expected_delivery_date, p_notes, p_created_by)
    RETURNING id INTO v_order_id;
    
    RETURN v_order_id;
END;
$$ LANGUAGE plpgsql;

-- Secuencia para pedidos de compra
CREATE SEQUENCE IF NOT EXISTS purchase_order_seq START 1;

-- Función para agregar item a pedido de compra
CREATE OR REPLACE FUNCTION add_purchase_order_item(
    p_purchase_order_id INTEGER,
    p_product_id INTEGER,
    p_quantity INTEGER,
    p_unit_cost DECIMAL
)
RETURNS INTEGER AS $$
DECLARE
    v_total_cost DECIMAL;
    v_item_id INTEGER;
BEGIN
    v_total_cost := p_quantity * p_unit_cost;
    
    INSERT INTO purchase_order_items (purchase_order_id, product_id, quantity_ordered, unit_cost, total_cost)
    VALUES (p_purchase_order_id, p_product_id, p_quantity, p_unit_cost, v_total_cost)
    RETURNING id INTO v_item_id;
    
    -- Actualizar total del pedido
    UPDATE purchase_orders
    SET subtotal = subtotal + v_total_cost,
        total_amount = subtotal + tax_amount,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = p_purchase_order_id;
    
    RETURN v_item_id;
END;
$$ LANGUAGE plpgsql;

-- Función para recibir pedido de compra
CREATE OR REPLACE FUNCTION receive_purchase_order(p_purchase_order_id INTEGER)
RETURNS BOOLEAN AS $$
DECLARE
    v_order RECORD;
BEGIN
    -- Obtener datos del pedido
    SELECT * INTO v_order
    FROM purchase_orders
    WHERE id = p_purchase_order_id;
    
    IF NOT FOUND THEN
        RETURN FALSE;
    END IF;
    
    -- Actualizar stock de productos
    UPDATE products p
    SET 
        stock_quantity = stock_quantity + poi.quantity_received,
        stock_online = stock_online + poi.quantity_received,
        updated_at = CURRENT_TIMESTAMP
    FROM purchase_order_items poi
    WHERE poi.purchase_order_id = p_purchase_order_id
    AND p.id = poi.product_id;
    
    -- Actualizar estado del pedido
    UPDATE purchase_orders
    SET status = 'received',
        received_date = CURRENT_TIMESTAMP,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = p_purchase_order_id;
    
    RETURN TRUE;
END;
$$ LANGUAGE plpgsql;

-- Función para obtener sugerencias de reabastecimiento
CREATE OR REPLACE FUNCTION get_restock_suggestions()
RETURNS TABLE (
    product_id INTEGER,
    product_name VARCHAR(255),
    current_stock INTEGER,
    low_stock_threshold INTEGER,
    suggested_quantity INTEGER,
    avg_monthly_demand DECIMAL,
    urgency VARCHAR(20)
) AS $$
BEGIN
    RETURN QUERY
    SELECT 
        p.id,
        p.name,
        COALESCE(p.stock_online, p.stock_quantity) as current_stock,
        COALESCE(p.low_stock_threshold_online, p.low_stock_threshold, 5) as low_stock_threshold,
        GREATEST(
            COALESCE(p.low_stock_threshold_online, p.low_stock_threshold, 5) * 2 - COALESCE(p.stock_online, p.stock_quantity),
            0
        ) as suggested_quantity,
        0 as avg_monthly_demand, -- Se calcularía con datos históricos
        CASE 
            WHEN COALESCE(p.stock_online, p.stock_quantity) = 0 THEN 'critical'
            WHEN COALESCE(p.stock_online, p.stock_quantity) <= COALESCE(p.low_stock_threshold_online, p.low_stock_threshold, 5) THEN 'high'
            ELSE 'normal'
        END as urgency
    FROM products p
    WHERE p.is_online_visible = true
    AND COALESCE(p.stock_online, p.stock_quantity) <= COALESCE(p.low_stock_threshold_online, p.low_stock_threshold, 5) * 1.5
    ORDER BY urgency DESC, current_stock ASC;
END;
$$ LANGUAGE plpgsql;
