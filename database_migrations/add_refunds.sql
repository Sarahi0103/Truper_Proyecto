-- Tabla de reembolsos
CREATE TABLE IF NOT EXISTS refunds (
    id SERIAL PRIMARY KEY,
    order_id INTEGER REFERENCES orders(id) ON DELETE CASCADE,
    payment_id INTEGER REFERENCES payments(id) ON DELETE SET NULL,
    refund_amount DECIMAL(10,2) NOT NULL,
    refund_reason TEXT NOT NULL,
    refund_status VARCHAR(20) DEFAULT 'pending', -- pending, processing, completed, failed
    refund_method VARCHAR(50), -- stripe, mercadopago, cash, transfer
    refund_transaction_id VARCHAR(255),
    processed_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    processed_at TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices para mejor rendimiento
CREATE INDEX IF NOT EXISTS idx_refunds_order ON refunds(order_id);
CREATE INDEX IF NOT EXISTS idx_refunds_payment ON refunds(payment_id);
CREATE INDEX IF NOT EXISTS idx_refunds_status ON refunds(refund_status);
CREATE INDEX IF NOT EXISTS idx_refunds_created ON refunds(created_at DESC);

-- Tabla de notas de crédito (para reembolsos parciales)
CREATE TABLE IF NOT EXISTS credit_notes (
    id SERIAL PRIMARY KEY,
    order_id INTEGER REFERENCES orders(id) ON DELETE CASCADE,
    client_id INTEGER REFERENCES clients(id) ON DELETE CASCADE,
    credit_note_number VARCHAR(50) UNIQUE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    remaining_balance DECIMAL(10,2) NOT NULL,
    reason TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'active', -- active, used, expired
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices para notas de crédito
CREATE INDEX IF NOT EXISTS idx_credit_notes_order ON credit_notes(order_id);
CREATE INDEX IF NOT EXISTS idx_credit_notes_client ON credit_notes(client_id);
CREATE INDEX IF NOT EXISTS idx_credit_notes_status ON credit_notes(status);
CREATE INDEX IF NOT EXISTS idx_credit_notes_number ON credit_notes(credit_note_number);

-- Función para crear reembolso
CREATE OR REPLACE FUNCTION create_refund(
    order_id INTEGER,
    payment_id INTEGER,
    refund_amount DECIMAL,
    refund_reason TEXT,
    refund_method VARCHAR(50)
)
RETURNS INTEGER AS $$
DECLARE
    refund_id INTEGER;
BEGIN
    INSERT INTO refunds (order_id, payment_id, refund_amount, refund_reason, refund_method)
    VALUES (order_id, payment_id, refund_amount, refund_reason, refund_method)
    RETURNING id INTO refund_id;
    
    RETURN refund_id;
END;
$$ LANGUAGE plpgsql;

-- Función para procesar reembolso
CREATE OR REPLACE FUNCTION process_refund(
    refund_id INTEGER,
    processed_by INTEGER,
    refund_transaction_id VARCHAR(255) DEFAULT NULL
)
RETURNS BOOLEAN AS $$
BEGIN
    UPDATE refunds
    SET refund_status = 'completed',
        refund_transaction_id = COALESCE(refund_transaction_id, refund_transaction_id),
        processed_by = processed_by,
        processed_at = CURRENT_TIMESTAMP,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = refund_id;
    
    RETURN FOUND;
END;
$$ LANGUAGE plpgsql;

-- Función para crear nota de crédito
CREATE OR REPLACE FUNCTION create_credit_note(
    order_id INTEGER,
    client_id INTEGER,
    amount DECIMAL,
    reason TEXT,
    expires_days INTEGER DEFAULT 365
)
RETURNS INTEGER AS $$
DECLARE
    credit_note_id INTEGER;
    credit_note_number VARCHAR(50);
BEGIN
    -- Generar número de nota de crédito
    credit_note_number := 'NC-' || TO_CHAR(CURRENT_DATE, 'YYYY') || '-' || LPAD(nextval('credit_note_seq')::TEXT, 6, '0');
    
    INSERT INTO credit_notes (order_id, client_id, credit_note_number, amount, remaining_balance, reason, expires_at)
    VALUES (order_id, client_id, credit_note_number, amount, amount, reason, CURRENT_TIMESTAMP + (expires_days || ' days')::INTERVAL)
    RETURNING id INTO credit_note_id;
    
    RETURN credit_note_id;
END;
$$ LANGUAGE plpgsql;

-- Secuencia para notas de crédito
CREATE SEQUENCE IF NOT EXISTS credit_note_seq START 1;

-- Función para usar nota de crédito
CREATE OR REPLACE FUNCTION use_credit_note(
    credit_note_id INTEGER,
    amount_to_use DECIMAL,
    order_id INTEGER
)
RETURNS DECIMAL AS $$
DECLARE
    remaining_balance DECIMAL;
BEGIN
    -- Obtener balance actual
    SELECT remaining_balance INTO remaining_balance
    FROM credit_notes
    WHERE id = credit_note_id AND status = 'active';
    
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Nota de crédito no encontrada o no activa';
    END IF;
    
    IF remaining_balance < amount_to_use THEN
        RAISE EXCEPTION 'Saldo insuficiente en nota de crédito';
    END IF;
    
    -- Actualizar balance
    UPDATE credit_notes
    SET remaining_balance = remaining_balance - amount_to_use,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = credit_note_id;
    
    -- Si el balance es 0, marcar como usada
    IF (remaining_balance - amount_to_use) = 0 THEN
        UPDATE credit_notes
        SET status = 'used',
            updated_at = CURRENT_TIMESTAMP
        WHERE id = credit_note_id;
    END IF;
    
    RETURN remaining_balance - amount_to_use;
END;
$$ LANGUAGE plpgsql;

-- Función para obtener reembolsos pendientes
CREATE OR REPLACE FUNCTION get_pending_refunds()
RETURNS TABLE (
    id INTEGER,
    order_id INTEGER,
    refund_amount DECIMAL,
    refund_reason TEXT,
    refund_method VARCHAR(50),
    created_at TIMESTAMP
) AS $$
BEGIN
    RETURN QUERY
    SELECT r.id, r.order_id, r.refund_amount, r.refund_reason, r.refund_method, r.created_at
    FROM refunds r
    WHERE r.refund_status = 'pending'
    ORDER BY r.created_at ASC;
END;
$$ LANGUAGE plpgsql;
