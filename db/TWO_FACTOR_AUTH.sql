-- Sistema de Autenticación de Dos Factores (2FA)
-- Tabla para configuración de 2FA por usuario

CREATE TABLE IF NOT EXISTS two_factor_auth (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    secret_key VARCHAR(255) NOT NULL UNIQUE,
    backup_codes TEXT,
    enabled BOOLEAN DEFAULT FALSE,
    verified BOOLEAN DEFAULT FALSE,
    last_used TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_two_factor_auth_user ON two_factor_auth(user_id);
CREATE INDEX IF NOT EXISTS idx_two_factor_auth_enabled ON two_factor_auth(enabled);

-- Tabla para códigos de verificación 2FA (para login)
CREATE TABLE IF NOT EXISTS two_factor_codes (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    code VARCHAR(10) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_two_factor_codes_user ON two_factor_codes(user_id);
CREATE INDEX IF NOT EXISTS idx_two_factor_codes_expires ON two_factor_codes(expires_at);

-- Función para generar código 2FA
CREATE OR REPLACE FUNCTION generate_2fa_code(user_id INTEGER)
RETURNS VARCHAR AS $$
DECLARE
    code VARCHAR;
    expires_at TIMESTAMP;
BEGIN
    -- Generar código de 6 dígitos
    code := LPAD(FLOOR(RANDOM() * 1000000)::VARCHAR, 6, '0');
    expires_at := NOW() + INTERVAL '5 minutes';
    
    -- Insertar código en la tabla
    INSERT INTO two_factor_codes (user_id, code, expires_at, ip_address, user_agent)
    VALUES (user_id, code, expires_at, inet_client_addr(), NULL);
    
    RETURN code;
END;
$$ LANGUAGE plpgsql;

-- Función para verificar código 2FA
CREATE OR REPLACE FUNCTION verify_2fa_code(user_id INTEGER, code VARCHAR)
RETURNS BOOLEAN AS $$
DECLARE
    code_record RECORD;
BEGIN
    -- Buscar código válido y no usado
    SELECT * INTO code_record
    FROM two_factor_codes
    WHERE user_id = verify_2fa_code.user_id
    AND code = verify_2fa_code.code
    AND used_at IS NULL
    AND expires_at > NOW()
    ORDER BY created_at DESC
    LIMIT 1;
    
    IF NOT FOUND THEN
        RETURN false;
    END IF;
    
    -- Marcar código como usado
    UPDATE two_factor_codes
    SET used_at = NOW()
    WHERE id = code_record.id;
    
    -- Actualizar último uso en configuración 2FA
    UPDATE two_factor_auth
    SET last_used = NOW()
    WHERE user_id = verify_2fa_code.user_id;
    
    RETURN true;
END;
$$ LANGUAGE plpgsql;

-- Función para habilitar 2FA para usuario
CREATE OR REPLACE FUNCTION enable_2fa(user_id INTEGER, secret_key VARCHAR, backup_codes TEXT DEFAULT NULL)
RETURNS BOOLEAN AS $$
BEGIN
    INSERT INTO two_factor_auth (user_id, secret_key, backup_codes, enabled, verified)
    VALUES (user_id, secret_key, backup_codes, true, true)
    ON CONFLICT (user_id)
    DO UPDATE SET
        secret_key = EXCLUDED.secret_key,
        backup_codes = EXCLUDED.backup_codes,
        enabled = true,
        verified = true,
        updated_at = NOW();
    
    RETURN true;
EXCEPTION
    WHEN OTHERS THEN
        RETURN false;
END;
$$ LANGUAGE plpgsql;

-- Función para deshabilitar 2FA para usuario
CREATE OR REPLACE FUNCTION disable_2fa(user_id INTEGER)
RETURNS BOOLEAN AS $$
BEGIN
    UPDATE two_factor_auth
    SET enabled = false,
        updated_at = NOW()
    WHERE user_id = disable_2fa.user_id;
    
    RETURN true;
EXCEPTION
    WHEN OTHERS THEN
        RETURN false;
END;
$$ LANGUAGE plpgsql;

-- Función para verificar si usuario tiene 2FA habilitado
CREATE OR REPLACE FUNCTION has_2fa_enabled(user_id INTEGER)
RETURNS BOOLEAN AS $$
BEGIN
    RETURN EXISTS (
        SELECT 1 FROM two_factor_auth
        WHERE user_id = has_2fa_enabled.user_id
        AND enabled = true
    );
END;
$$ LANGUAGE plpgsql;

-- Función para limpiar códigos 2FA expirados
CREATE OR REPLACE FUNCTION cleanup_expired_2fa_codes()
RETURNS INTEGER AS $$
DECLARE
    deleted_count INTEGER;
BEGIN
    DELETE FROM two_factor_codes
    WHERE expires_at < NOW();
    
    GET DIAGNOSTICS deleted_count = ROW_COUNT;
    RETURN deleted_count;
END;
$$ LANGUAGE plpgsql;

-- Comentarios
COMMENT ON TABLE two_factor_auth IS 'Configuración de autenticación de dos factores por usuario';
COMMENT ON TABLE two_factor_codes IS 'Códigos de verificación 2FA temporales';
COMMENT ON FUNCTION generate_2fa_code(INTEGER) IS 'Genera un código de verificación 2FA';
COMMENT ON FUNCTION verify_2fa_code(INTEGER, VARCHAR) IS 'Verifica si un código 2FA es válido';
COMMENT ON FUNCTION enable_2fa(INTEGER, VARCHAR, TEXT) IS 'Habilita 2FA para un usuario';
COMMENT ON FUNCTION disable_2fa(INTEGER) IS 'Deshabilita 2FA para un usuario';
COMMENT ON FUNCTION has_2fa_enabled(INTEGER) IS 'Verifica si un usuario tiene 2FA habilitado';
COMMENT ON FUNCTION cleanup_expired_2fa_codes() IS 'Limpia códigos 2FA expirados';
