-- Migración: Sistema de Reservas de Stock Temporal
-- Evita overselling cuando múltiples clientes compran el mismo producto simultáneamente
-- Fecha: 2026-08-18

-- Tabla de reservas de stock temporales
CREATE TABLE IF NOT EXISTS stock_reservations (
    id SERIAL PRIMARY KEY,
    product_id INTEGER NOT NULL,
    quantity INTEGER NOT NULL,
    user_id INTEGER,
    session_id VARCHAR(255),
    reserved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    order_id INTEGER REFERENCES sales_tickets(id) ON DELETE SET NULL,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'confirmed', 'expired', 'cancelled')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices para rendimiento
CREATE INDEX IF NOT EXISTS idx_stock_reservations_product ON stock_reservations(product_id);
CREATE INDEX IF NOT EXISTS idx_stock_reservations_expires ON stock_reservations(expires_at);
CREATE INDEX IF NOT EXISTS idx_stock_reservations_status ON stock_reservations(status);
CREATE INDEX IF NOT EXISTS idx_stock_reservations_session ON stock_reservations(session_id);
CREATE INDEX IF NOT EXISTS idx_stock_reservations_user ON stock_reservations(user_id);

-- Función para calcular stock disponible (considerando reservas activas)
CREATE OR REPLACE FUNCTION get_available_stock(product_id INTEGER)
RETURNS INTEGER AS $$
DECLARE
    total_stock INTEGER;
    reserved_quantity INTEGER;
BEGIN
    -- Obtener stock total del producto
    SELECT COALESCE(stock_quantity, 0) INTO total_stock
    FROM products
    WHERE id = product_id;
    
    -- Obtener cantidad reservada (solo reservas pendientes no expiradas)
    SELECT COALESCE(SUM(quantity), 0) INTO reserved_quantity
    FROM stock_reservations
    WHERE product_id = product_id
    AND status = 'pending'
    AND expires_at > CURRENT_TIMESTAMP;
    
    RETURN GREATEST(0, total_stock - reserved_quantity);
END;
$$ LANGUAGE plpgsql;

-- Función para crear reserva de stock
CREATE OR REPLACE FUNCTION create_stock_reservation(
    p_product_id INTEGER,
    p_quantity INTEGER,
    p_user_id INTEGER,
    p_session_id VARCHAR,
    p_expires_minutes INTEGER DEFAULT 15
)
RETURNS JSON AS $$
DECLARE
    available_stock INTEGER;
    reservation_id INTEGER;
    expires_at TIMESTAMP;
BEGIN
    -- Verificar stock disponible
    available_stock := get_available_stock(p_product_id);
    
    IF available_stock < p_quantity THEN
        RETURN json_build_object(
            'success', false,
            'message', 'Stock insuficiente',
            'available', available_stock,
            'requested', p_quantity
        );
    END IF;
    
    -- Calcular fecha de expiración
    expires_at := CURRENT_TIMESTAMP + (p_expires_minutes || ' minutes')::INTERVAL;
    
    -- Crear reserva
    INSERT INTO stock_reservations (product_id, quantity, user_id, session_id, expires_at)
    VALUES (p_product_id, p_quantity, p_user_id, p_session_id, expires_at)
    RETURNING id INTO reservation_id;
    
    RETURN json_build_object(
        'success', true,
        'reservation_id', reservation_id,
        'expires_at', expires_at
    );
END;
$$ LANGUAGE plpgsql;

-- Función para confirmar reserva (cuando se completa el pago)
CREATE OR REPLACE FUNCTION confirm_stock_reservation(
    p_reservation_id INTEGER,
    p_order_id INTEGER
)
RETURNS JSON AS $$
DECLARE
    reservation RECORD;
BEGIN
    -- Obtener reserva
    SELECT * INTO reservation
    FROM stock_reservations
    WHERE id = p_reservation_id AND status = 'pending';
    
    IF NOT FOUND THEN
        RETURN json_build_object('success', false, 'message', 'Reserva no encontrada o ya procesada');
    END IF;
    
    -- Actualizar reserva
    UPDATE stock_reservations
    SET status = 'confirmed',
        order_id = p_order_id,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = p_reservation_id;
    
    -- Descontar stock del producto
    UPDATE products
    SET stock_quantity = GREATEST(0, stock_quantity - reservation.quantity)
    WHERE id = reservation.product_id;
    
    RETURN json_build_object('success', true, 'message', 'Reserva confirmada');
END;
$$ LANGUAGE plpgsql;

-- Función para cancelar reserva
CREATE OR REPLACE FUNCTION cancel_stock_reservation(p_reservation_id INTEGER)
RETURNS JSON AS $$
BEGIN
    UPDATE stock_reservations
    SET status = 'cancelled',
        updated_at = CURRENT_TIMESTAMP
    WHERE id = p_reservation_id AND status = 'pending';
    
    IF NOT FOUND THEN
        RETURN json_build_object('success', false, 'message', 'Reserva no encontrada o ya procesada');
    END IF;
    
    RETURN json_build_object('success', true, 'message', 'Reserva cancelada');
END;
$$ LANGUAGE plpgsql;

-- Función para limpiar reservas expiradas (ejecutar por cron job)
CREATE OR REPLACE FUNCTION cleanup_expired_reservations()
RETURNS INTEGER AS $$
DECLARE
    cleaned_count INTEGER;
BEGIN
    UPDATE stock_reservations
    SET status = 'expired',
        updated_at = CURRENT_TIMESTAMP
    WHERE status = 'pending'
    AND expires_at < CURRENT_TIMESTAMP;
    
    GET DIAGNOSTICS cleaned_count = ROW_COUNT;
    
    RETURN cleaned_count;
END;
$$ LANGUAGE plpgsql;

-- Trigger para actualizar updated_at automáticamente
CREATE OR REPLACE FUNCTION update_stock_reservations_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_stock_reservations_updated_at ON stock_reservations;
CREATE TRIGGER trg_stock_reservations_updated_at
BEFORE UPDATE ON stock_reservations
FOR EACH ROW
EXECUTE FUNCTION update_stock_reservations_updated_at();

-- Comentario sobre la tabla
COMMENT ON TABLE stock_reservations IS 'Sistema de reservas de stock temporal para evitar overselling. Las reservas expiran después de 15 minutos por defecto.';
