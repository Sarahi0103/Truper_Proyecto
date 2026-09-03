-- Tabla de bancos mexicanos configurados
CREATE TABLE IF NOT EXISTS mexican_banks (
    id SERIAL PRIMARY KEY,
    bank_name VARCHAR(100) NOT NULL,
    bank_code VARCHAR(10) NOT NULL UNIQUE, -- Código de banco SAT
    clabe VARCHAR(18) NOT NULL,
    account_number VARCHAR(20),
    account_holder VARCHAR(255) NOT NULL,
    rfc VARCHAR(13),
    is_active BOOLEAN DEFAULT TRUE,
    display_order INTEGER DEFAULT 0,
    supports_spei BOOLEAN DEFAULT TRUE,
    supports_card BOOLEAN DEFAULT FALSE,
    supports_transfer BOOLEAN DEFAULT TRUE,
    logo_url TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices para bancos
CREATE INDEX IF NOT EXISTS idx_mexican_banks_active ON mexican_banks(is_active);
CREATE INDEX IF NOT EXISTS idx_mexican_banks_code ON mexican_banks(bank_code);

-- Insertar bancos mexicanos comunes
INSERT INTO mexican_banks (bank_name, bank_code, clabe, account_holder, rfc, display_order, supports_spei, supports_card, supports_transfer, logo_url) VALUES
('BBVA Bancomer', '002', '012345678901234567', 'Ferretería FOX S.A. de C.V.', 'FOX010101ABC', 1, true, true, true, 'https://www.bbva.com.mx/logo.png'),
('Banorte', '072', '012345678901234568', 'Ferretería FOX S.A. de C.V.', 'FOX010101ABC', 2, true, true, true, 'https://www.banorte.com/logo.png'),
('Santander', '014', '012345678901234569', 'Ferretería FOX S.A. de C.V.', 'FOX010101ABC', 3, true, true, true, 'https://www.santander.com.mx/logo.png'),
('Banamex (Citibanamex)', '021', '012345678901234570', 'Ferretería FOX S.A. de C.V.', 'FOX010101ABC', 4, true, true, true, 'https://www.banamex.com/logo.png'),
('HSBC México', '021', '012345678901234571', 'Ferretería FOX S.A. de C.V.', 'FOX010101ABC', 5, true, true, true, 'https://www.hsbc.com.mx/logo.png'),
('Scotiabank', '044', '012345678901234572', 'Ferretería FOX S.A. de C.V.', 'FOX010101ABC', 6, true, true, true, 'https://www.scotiabank.com.mx/logo.png'),
('Inbursa', '036', '012345678901234573', 'Ferretería FOX S.A. de C.V.', 'FOX010101ABC', 7, true, false, true, 'https://www.inbursa.com/logo.png'),
('Banco Azteca', '030', '012345678901234574', 'Ferretería FOX S.A. de C.V.', 'FOX010101ABC', 8, true, false, true, 'https://www.bancoazteca.com/logo.png'),
('Banco del Bajío', '059', '012345678901234575', 'Ferretería FOX S.A. de C.V.', 'FOX010101ABC', 9, true, true, true, 'https://www.bancodebajio.com/logo.png'),
('Afirme', '060', '012345678901234576', 'Ferretería FOX S.A. de C.V.', 'FOX010101ABC', 10, true, true, true, 'https://www.afirme.com/logo.png')
ON CONFLICT (bank_code) DO NOTHING;

-- Tabla de métodos de pago configurados
CREATE TABLE IF NOT EXISTS payment_methods_config (
    id SERIAL PRIMARY KEY,
    method_code VARCHAR(50) NOT NULL UNIQUE, -- card, spei, cash, transfer, mercadopago, stripe
    method_name VARCHAR(100) NOT NULL,
    is_enabled BOOLEAN DEFAULT TRUE,
    display_order INTEGER DEFAULT 0,
    requires_bank_selection BOOLEAN DEFAULT FALSE,
    supported_banks INTEGER[], -- IDs de bancos que soportan este método
    min_amount DECIMAL(10,2) DEFAULT 0,
    max_amount DECIMAL(10,2),
    fee_percentage DECIMAL(5,2) DEFAULT 0,
    fee_fixed DECIMAL(10,2) DEFAULT 0,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices para métodos de pago
CREATE INDEX IF NOT EXISTS idx_payment_methods_enabled ON payment_methods_config(is_enabled);

-- Insertar métodos de pago predeterminados
INSERT INTO payment_methods_config (method_code, method_name, is_enabled, display_order, requires_bank_selection, min_amount, fee_percentage, description) VALUES
('card', 'Tarjeta de Crédito/Débito', true, 1, false, 0, 3.5, 'Pago con tarjeta (Stripe/Mercado Pago)'),
('spei', 'Transferencia SPEI', true, 2, true, 100, 0, 'Transferencia bancaria instantánea'),
('cash', 'Pago contra entrega', true, 3, false, 0, 0, 'Pague al recibir su pedido'),
('transfer', 'Transferencia bancaria', true, 4, true, 500, 0, 'Transferencia interbancaria tradicional'),
('mercadopago', 'Mercado Pago', true, 5, false, 0, 2.9, 'Pago a través de Mercado Pago'),
('stripe', 'Stripe', true, 6, false, 0, 2.9, 'Pago a través de Stripe')
ON CONFLICT (method_code) DO NOTHING;

-- Tabla de configuración fiscal SAT
CREATE TABLE IF NOT EXISTS sat_fiscal_config (
    id SERIAL PRIMARY KEY,
    company_rfc VARCHAR(13) NOT NULL,
    company_tax_name VARCHAR(255) NOT NULL,
    company_tax_regime VARCHAR(3) NOT NULL, -- c_RegimenFiscal
    company_zip_code VARCHAR(5) NOT NULL,
    company_email VARCHAR(255),
    company_phone VARCHAR(20),
    company_address TEXT,
    facturapi_api_key VARCHAR(255),
    pac_provider VARCHAR(50) DEFAULT 'facturapi', -- facturapi, finkok, etc.
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices para configuración fiscal
CREATE INDEX IF NOT EXISTS idx_sat_fiscal_config_active ON sat_fiscal_config(is_active);

-- Función para obtener bancos activos
CREATE OR REPLACE FUNCTION get_active_mexican_banks()
RETURNS TABLE (
    id INTEGER,
    bank_name VARCHAR(100),
    bank_code VARCHAR(10),
    clabe VARCHAR(18),
    account_holder VARCHAR(255),
    supports_spei BOOLEAN,
    supports_card BOOLEAN,
    supports_transfer BOOLEAN,
    logo_url TEXT
) AS $$
BEGIN
    RETURN QUERY
    SELECT id, bank_name, bank_code, clabe, account_holder, 
           supports_spei, supports_card, supports_transfer, logo_url
    FROM mexican_banks
    WHERE is_active = true
    ORDER BY display_order ASC, bank_name ASC;
END;
$$ LANGUAGE plpgsql;

-- Función para obtener métodos de pago activos
CREATE OR REPLACE FUNCTION get_active_payment_methods()
RETURNS TABLE (
    method_code VARCHAR(50),
    method_name VARCHAR(100),
    requires_bank_selection BOOLEAN,
    min_amount DECIMAL,
    fee_percentage DECIMAL,
    description TEXT
) AS $$
BEGIN
    RETURN QUERY
    SELECT method_code, method_name, requires_bank_selection, 
           min_amount, fee_percentage, description
    FROM payment_methods_config
    WHERE is_enabled = true
    ORDER BY display_order ASC;
END;
$$ LANGUAGE plpgsql;
