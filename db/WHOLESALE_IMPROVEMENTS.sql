-- Mejoras para el sistema de Mayoreo
-- Agregar tablas para sistema de descuentos y límites de crédito

-- Tabla de precios mayoreo escalonados
CREATE TABLE IF NOT EXISTS wholesale_pricing (
    id SERIAL PRIMARY KEY,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    min_quantity INTEGER NOT NULL, -- Cantidad mínima para aplicar este precio
    max_quantity INTEGER, -- Cantidad máxima (null para sin límite)
    discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0, -- Porcentaje de descuento
    price DECIMAL(12,2), -- Precio fijo (opcional, si es null usa porcentaje)
    is_active BOOLEAN DEFAULT true,
    valid_from DATE DEFAULT CURRENT_DATE,
    valid_until DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_wholesale_pricing_product ON wholesale_pricing(product_id);
CREATE INDEX IF NOT EXISTS idx_wholesale_pricing_qty ON wholesale_pricing(product_id, min_quantity, max_quantity);
CREATE INDEX IF NOT EXISTS idx_wholesale_pricing_active ON wholesale_pricing(is_active, valid_from, valid_until);

-- Agregar campo de límite de crédito a clientes
ALTER TABLE clients ADD COLUMN IF NOT EXISTS credit_limit DECIMAL(12,2) DEFAULT 0;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS current_balance DECIMAL(12,2) DEFAULT 0;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS credit_status VARCHAR(20) DEFAULT 'good'; -- good, warning, blocked
ALTER TABLE clients ADD COLUMN IF NOT EXISTS rfc VARCHAR(13); -- RFC mexicano
ALTER TABLE clients ADD COLUMN IF NOT EXISTS tax_id VARCHAR(50); -- Identificación fiscal general

-- Índices para límites de crédito
CREATE INDEX IF NOT EXISTS idx_clients_credit_limit ON clients(credit_limit);
CREATE INDEX IF NOT EXISTS idx_clients_credit_status ON clients(credit_status);

-- Tabla de historial de compras mayoreo
CREATE TABLE IF NOT EXISTS wholesale_purchase_history (
    id SERIAL PRIMARY KEY,
    client_id INTEGER NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
    order_id INTEGER REFERENCES orders(id) ON DELETE SET NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    discount_applied DECIMAL(5,2) DEFAULT 0,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    final_amount DECIMAL(12,2) NOT NULL,
    payment_method VARCHAR(50),
    purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_wholesale_history_client ON wholesale_purchase_history(client_id);
CREATE INDEX IF NOT EXISTS idx_wholesale_history_date ON wholesale_purchase_history(purchase_date DESC);

DROP FUNCTION IF EXISTS validate_rfc(TEXT);
DROP FUNCTION IF EXISTS calculate_wholesale_discount(INTEGER, INTEGER);
DROP FUNCTION IF EXISTS check_credit_limit(INTEGER, DECIMAL(12,2));

-- Función para validar RFC mexicano
CREATE OR REPLACE FUNCTION validate_rfc(p_rfc TEXT)
RETURNS BOOLEAN AS $$
BEGIN
    -- RFC debe tener 13 caracteres para personas morales o 12 para físicas
    -- Formato: 4 letras + 6 dígitos + 3 caracteres (homoclave)
    IF p_rfc IS NULL OR LENGTH(p_rfc) NOT IN (12, 13) THEN
        RETURN FALSE;
    END IF;

    -- Verificar que los primeros 4 caracteres sean letras
    IF p_rfc ~ '^[A-Z]{4}' THEN
        RETURN TRUE;
    END IF;

    RETURN FALSE;
END;
$$ LANGUAGE plpgsql;

-- Función para calcular descuento mayoreo
CREATE OR REPLACE FUNCTION calculate_wholesale_discount(p_product_id INTEGER, p_quantity INTEGER)
RETURNS TABLE(
    discount_percent DECIMAL(5,2),
    discount_price DECIMAL(12,2),
    pricing_tier VARCHAR(50)
) AS $$
BEGIN
    RETURN QUERY
    SELECT
        COALESCE(wp.discount_percent, 0) AS discount_percent,
        wp.price AS discount_price,
        CASE
            WHEN wp.min_quantity >= 100 THEN 'wholesale_100_plus'::VARCHAR(50)
            WHEN wp.min_quantity >= 50 THEN 'wholesale_50_99'::VARCHAR(50)
            WHEN wp.min_quantity >= 10 THEN 'wholesale_10_49'::VARCHAR(50)
            ELSE 'retail'::VARCHAR(50)
        END AS pricing_tier
    FROM wholesale_pricing wp
    WHERE wp.product_id = p_product_id
        AND wp.is_active = true
        AND wp.valid_from <= CURRENT_DATE
        AND (wp.valid_until IS NULL OR wp.valid_until >= CURRENT_DATE)
        AND p_quantity >= wp.min_quantity
        AND (wp.max_quantity IS NULL OR p_quantity <= wp.max_quantity)
    ORDER BY wp.min_quantity DESC
    LIMIT 1;
END;
$$ LANGUAGE plpgsql;

-- Función para verificar límite de crédito
CREATE OR REPLACE FUNCTION check_credit_limit(p_client_id INTEGER, p_amount DECIMAL(12,2))
RETURNS TABLE(
    can_purchase BOOLEAN,
    remaining_credit DECIMAL(12,2),
    credit_status VARCHAR(20)
) AS $$
BEGIN
    RETURN QUERY
    SELECT
        (c.credit_limit - c.current_balance) >= p_amount AS can_purchase,
        (c.credit_limit - c.current_balance) AS remaining_credit,
        c.credit_status
    FROM clients c
    WHERE c.id = p_client_id;
END;
$$ LANGUAGE plpgsql;

-- Comentarios
COMMENT ON TABLE wholesale_pricing IS 'Precios mayoreo escalonados por producto';
COMMENT ON COLUMN wholesale_pricing.min_quantity IS 'Cantidad mínima para aplicar este precio';
COMMENT ON COLUMN wholesale_pricing.discount_percent IS 'Porcentaje de descuento (0-100)';
COMMENT ON COLUMN wholesale_pricing.price IS 'Precio fijo (opcional, si es null usa porcentaje sobre precio base)';
COMMENT ON FUNCTION validate_rfc(TEXT) IS 'Valida formato de RFC mexicano';
COMMENT ON FUNCTION calculate_wholesale_discount(INTEGER, INTEGER) IS 'Calcula descuento mayoreo basado en cantidad';
COMMENT ON FUNCTION check_credit_limit(INTEGER, DECIMAL) IS 'Verifica si el cliente tiene crédito disponible';
