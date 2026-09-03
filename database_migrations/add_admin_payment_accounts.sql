-- Tabla de cuentas de pago del administrador (Stripe, Mercado Pago, etc.)
CREATE TABLE IF NOT EXISTS admin_payment_accounts (
    id SERIAL PRIMARY KEY,
    account_type VARCHAR(50) NOT NULL, -- stripe, mercadopago, bank_account
    account_name VARCHAR(100) NOT NULL, -- Nombre descriptivo para el admin
    provider_account_id VARCHAR(255), -- ID de cuenta en Stripe/Mercado Pago
    bank_name VARCHAR(100),
    last_4 VARCHAR(4), -- Últimos 4 dígitos de tarjeta/cuenta
    clabe VARCHAR(18), -- Para cuentas bancarias mexicanas
    account_holder VARCHAR(255),
    rfc VARCHAR(13),
    is_primary BOOLEAN DEFAULT FALSE, -- Cuenta principal para recibir pagos
    is_active BOOLEAN DEFAULT TRUE,
    payment_gateway VARCHAR(50), -- stripe, mercadopago
    metadata JSONB, -- Datos adicionales del proveedor
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices
CREATE INDEX IF NOT EXISTS idx_admin_payment_accounts_type ON admin_payment_accounts(account_type);
CREATE INDEX IF NOT EXISTS idx_admin_payment_accounts_active ON admin_payment_accounts(is_active);
CREATE INDEX IF NOT EXISTS idx_admin_payment_accounts_primary ON admin_payment_accounts(is_primary);

-- Función para obtener cuentas activas
CREATE OR REPLACE FUNCTION get_active_payment_accounts()
RETURNS TABLE (
    id INTEGER,
    account_type VARCHAR(50),
    account_name VARCHAR(100),
    payment_gateway VARCHAR(50),
    bank_name VARCHAR(100),
    last_4 VARCHAR(4),
    is_primary BOOLEAN
) AS $$
BEGIN
    RETURN QUERY
    SELECT id, account_type, account_name, payment_gateway, bank_name, last_4, is_primary
    FROM admin_payment_accounts
    WHERE is_active = true
    ORDER BY is_primary DESC, account_name ASC;
END;
$$ LANGUAGE plpgsql;

-- Función para obtener cuenta principal
CREATE OR REPLACE FUNCTION get_primary_payment_account()
RETURNS TABLE (
    id INTEGER,
    account_type VARCHAR(50),
    account_name VARCHAR(100),
    payment_gateway VARCHAR(50),
    provider_account_id VARCHAR(255)
) AS $$
BEGIN
    RETURN QUERY
    SELECT id, account_type, account_name, payment_gateway, provider_account_id
    FROM admin_payment_accounts
    WHERE is_primary = true AND is_active = true
    LIMIT 1;
END;
$$ LANGUAGE plpgsql;

-- Función para establecer cuenta como principal
CREATE OR REPLACE FUNCTION set_primary_payment_account(account_id INTEGER)
RETURNS BOOLEAN AS $$
BEGIN
    -- Desmarcar todas las demás
    UPDATE admin_payment_accounts SET is_primary = false WHERE id != account_id;
    -- Marcar esta como principal
    UPDATE admin_payment_accounts SET is_primary = true, updated_at = NOW() WHERE id = account_id;
    RETURN true;
END;
$$ LANGUAGE plpgsql;
