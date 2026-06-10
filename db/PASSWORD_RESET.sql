-- Sistema de Recuperación de Contraseña
-- Tabla para tokens de restablecimiento de contraseña

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT
);

CREATE INDEX IF NOT EXISTS idx_password_reset_tokens_user ON password_reset_tokens(user_id);
CREATE INDEX IF NOT EXISTS idx_password_reset_tokens_token ON password_reset_tokens(token);
CREATE INDEX IF NOT EXISTS idx_password_reset_tokens_expires ON password_reset_tokens(expires_at);

-- Función para limpiar tokens expirados
CREATE OR REPLACE FUNCTION cleanup_expired_password_tokens()
RETURNS INTEGER AS $$
DECLARE
    deleted_count INTEGER;
BEGIN
    DELETE FROM password_reset_tokens 
    WHERE expires_at < NOW() OR used_at IS NOT NULL;
    
    GET DIAGNOSTICS deleted_count = ROW_COUNT;
    RETURN deleted_count;
END;
$$ LANGUAGE plpgsql;

-- Función para generar token de restablecimiento
CREATE OR REPLACE FUNCTION generate_password_reset_token(user_id INTEGER, ip_address VARCHAR DEFAULT NULL, user_agent TEXT DEFAULT NULL)
RETURNS VARCHAR AS $$
DECLARE
    new_token VARCHAR;
    expires_at TIMESTAMP;
BEGIN
    -- Generar token único
    new_token := encode(gen_random_bytes(32), 'hex');
    expires_at := NOW() + INTERVAL '1 hour';
    
    -- Eliminar tokens anteriores del usuario
    DELETE FROM password_reset_tokens WHERE user_id = password_reset_token.user_id;
    
    -- Insertar nuevo token
    INSERT INTO password_reset_tokens (user_id, token, expires_at, ip_address, user_agent)
    VALUES (user_id, new_token, expires_at, ip_address, user_agent);
    
    RETURN new_token;
END;
$$ LANGUAGE plpgsql;

-- Función para validar token de restablecimiento
CREATE OR REPLACE FUNCTION validate_password_reset_token(token VARCHAR)
RETURNS TABLE(user_id INTEGER, is_valid BOOLEAN) AS $$
BEGIN
    RETURN QUERY
    SELECT 
        prt.user_id,
        CASE 
            WHEN prt.used_at IS NOT NULL THEN false
            WHEN prt.expires_at < NOW() THEN false
            ELSE true
        END AS is_valid
    FROM password_reset_tokens prt
    WHERE prt.token = validate_password_reset_token.token
    LIMIT 1;
END;
$$ LANGUAGE plpgsql;

-- Comentarios
COMMENT ON TABLE password_reset_tokens IS 'Tokens para restablecimiento de contraseña';
COMMENT ON FUNCTION cleanup_expired_password_tokens() IS 'Limpia tokens expirados de restablecimiento de contraseña';
COMMENT ON FUNCTION generate_password_reset_token(INTEGER, VARCHAR, TEXT) IS 'Genera un nuevo token de restablecimiento de contraseña';
COMMENT ON FUNCTION validate_password_reset_token(VARCHAR) IS 'Valida si un token de restablecimiento es válido';
