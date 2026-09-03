-- Migración: Sistema de Tracking de Envíos
-- Integración con paqueterías (FedEx, DHL, etc.)
-- Fecha: 2026-08-18

-- Tabla de tracking de envíos
CREATE TABLE IF NOT EXISTS shipping_tracking (
    id SERIAL PRIMARY KEY,
    order_id INTEGER REFERENCES sales_tickets(id) ON DELETE CASCADE,
    carrier VARCHAR(50) NOT NULL, -- 'fedex', 'dhl', 'estafeta', 'redpack', etc.
    tracking_number VARCHAR(100) NOT NULL UNIQUE,
    shipping_date TIMESTAMP,
    estimated_delivery TIMESTAMP,
    actual_delivery TIMESTAMP,
    shipping_address JSONB,
    tracking_status VARCHAR(50) DEFAULT 'pending', -- 'pending', 'in_transit', 'out_for_delivery', 'delivered', 'exception'
    tracking_events JSONB DEFAULT '[]', -- Array de eventos de tracking
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices para rendimiento
CREATE INDEX IF NOT EXISTS idx_shipping_tracking_order ON shipping_tracking(order_id);
CREATE INDEX IF NOT EXISTS idx_shipping_tracking_carrier ON shipping_tracking(carrier);
CREATE INDEX IF NOT EXISTS idx_shipping_tracking_number ON shipping_tracking(tracking_number);

-- Tabla de configuración de paqueterías
CREATE TABLE IF NOT EXISTS shipping_carriers (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL, -- 'fedex', 'dhl', etc.
    api_key VARCHAR(255),
    api_secret VARCHAR(255),
    account_number VARCHAR(100),
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Función para agregar evento de tracking
CREATE OR REPLACE FUNCTION add_tracking_event(
    p_tracking_id INTEGER,
    p_status VARCHAR,
    p_description TEXT,
    p_location VARCHAR,
    p_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
RETURNS VOID AS $$
BEGIN
    UPDATE shipping_tracking
    SET tracking_events = tracking_events || jsonb_build_object(
        'status', p_status,
        'description', p_description,
        'location', p_location,
        'timestamp', p_timestamp
    ),
    tracking_status = p_status,
    updated_at = CURRENT_TIMESTAMP
    WHERE id = p_tracking_id;
END;
$$ LANGUAGE plpgsql;

-- Trigger para updated_at
CREATE OR REPLACE FUNCTION update_shipping_tracking_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_shipping_tracking_updated_at ON shipping_tracking;
CREATE TRIGGER trg_shipping_tracking_updated_at
BEFORE UPDATE ON shipping_tracking
FOR EACH ROW
EXECUTE FUNCTION update_shipping_tracking_updated_at();

-- Comentarios
COMMENT ON TABLE shipping_tracking IS 'Tracking de envíos con integración de paqueterías';
COMMENT ON TABLE shipping_carriers IS 'Configuración de APIs de paqueterías';
