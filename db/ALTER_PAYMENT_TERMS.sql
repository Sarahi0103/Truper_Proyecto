-- ============================================
-- EXTENSIÓN: TÉRMINOS DE PAGO Y CONTROL SEMANAL
-- ============================================

-- Agregar columnas a tabla orders para plazos de pago
ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_terms VARCHAR(20) NOT NULL DEFAULT 'immediate';
ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_due_date DATE;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS balance DECIMAL(10, 2) DEFAULT 0;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS balance_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'chk_orders_payment_terms'
    ) THEN
        ALTER TABLE orders
            ADD CONSTRAINT chk_orders_payment_terms
            CHECK (payment_terms IN ('immediate', '15_days', '30_days'));
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'chk_orders_payment_status'
    ) THEN
        ALTER TABLE orders
            ADD CONSTRAINT chk_orders_payment_status
            CHECK (payment_status IN ('pending', 'partial', 'paid'));
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_payment_due_date ON orders(payment_due_date);
CREATE INDEX IF NOT EXISTS idx_payment_status ON orders(payment_status);

-- Tabla para resumen semanal de consumo
CREATE TABLE IF NOT EXISTS weekly_consumption_summary (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    week_start DATE NOT NULL,
    week_end DATE NOT NULL,
    total_consumed DECIMAL(10, 2) DEFAULT 0,
    total_owed DECIMAL(10, 2) DEFAULT 0,
    payment_status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (payment_status IN ('pending', 'partial', 'paid')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_weekly_consumption_user_id ON weekly_consumption_summary(user_id);
CREATE INDEX IF NOT EXISTS idx_weekly_consumption_week_start ON weekly_consumption_summary(week_start);
CREATE INDEX IF NOT EXISTS idx_weekly_consumption_payment_status ON weekly_consumption_summary(payment_status);

-- Tabla para crédito de cliente (saldo de cuenta)
CREATE TABLE IF NOT EXISTS client_credit_balance (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    credit_limit DECIMAL(10, 2) DEFAULT 0,
    credit_available DECIMAL(10, 2) DEFAULT 0,
    credit_used DECIMAL(10, 2) DEFAULT 0,
    total_owed DECIMAL(10, 2) DEFAULT 0,
    last_payment_date DATE,
    days_overdue INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_client_credit_balance_days_overdue ON client_credit_balance(days_overdue);

-- Tabla para historial de pagos de crédito
CREATE TABLE IF NOT EXISTS credit_payments (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    order_id INT REFERENCES orders(id) ON DELETE SET NULL,
    payment_amount DECIMAL(10, 2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method VARCHAR(50),
    reference_number VARCHAR(100),
    notes TEXT,
    recorded_by INT REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CHECK (payment_amount >= 0)
);

CREATE INDEX IF NOT EXISTS idx_credit_payments_user_id ON credit_payments(user_id);
CREATE INDEX IF NOT EXISTS idx_credit_payments_order_id ON credit_payments(order_id);
CREATE INDEX IF NOT EXISTS idx_credit_payments_payment_date ON credit_payments(payment_date);

-- Tabla para cotizaciones por WhatsApp
CREATE TABLE IF NOT EXISTS whatsapp_quotes (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    quote_data JSONB NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    items_count INT NOT NULL,
    whatsapp_phone VARCHAR(20),
    status VARCHAR(30) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'sent', 'answered', 'converted_to_order')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_whatsapp_quotes_user_id ON whatsapp_quotes(user_id);
CREATE INDEX IF NOT EXISTS idx_whatsapp_quotes_status ON whatsapp_quotes(status);
