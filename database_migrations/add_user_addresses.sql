-- Migración: Sistema de Direcciones Múltiples por Cliente
-- Permite a los clientes guardar varias direcciones de entrega
-- Fecha: 2026-08-18

-- Tabla de direcciones de usuario
CREATE TABLE IF NOT EXISTS user_addresses (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255),
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    postal_code VARCHAR(10) NOT NULL,
    country VARCHAR(100) DEFAULT 'México',
    address_label VARCHAR(50), -- Ej: "Casa", "Oficina", "Obra"
    is_default BOOLEAN DEFAULT false,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices para rendimiento
CREATE INDEX IF NOT EXISTS idx_user_addresses_user ON user_addresses(user_id);
CREATE INDEX IF NOT EXISTS idx_user_addresses_default ON user_addresses(user_id, is_default);

-- Función para establecer dirección como predeterminada
CREATE OR REPLACE FUNCTION set_default_address(p_user_id INTEGER, p_address_id INTEGER)
RETURNS VOID AS $$
BEGIN
    -- Quitar default de otras direcciones del usuario
    UPDATE user_addresses
    SET is_default = false
    WHERE user_id = p_user_id AND id != p_address_id;
    
    -- Establecer nueva dirección como default
    UPDATE user_addresses
    SET is_default = true, updated_at = CURRENT_TIMESTAMP
    WHERE id = p_address_id AND user_id = p_user_id;
END;
$$ LANGUAGE plpgsql;

-- Trigger para updated_at
CREATE OR REPLACE FUNCTION update_user_addresses_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_user_addresses_updated_at ON user_addresses;
CREATE TRIGGER trg_user_addresses_updated_at
BEFORE UPDATE ON user_addresses
FOR EACH ROW
EXECUTE FUNCTION update_user_addresses_updated_at();

-- Comentario sobre la tabla
COMMENT ON TABLE user_addresses IS 'Direcciones de entrega múltiples por cliente con opción de predeterminada';
