-- Sistema de Cupones de Descuento
-- Tabla para gestión de cupones promocionales

CREATE TABLE IF NOT EXISTS coupons (
    id SERIAL PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    discount_type VARCHAR(20) NOT NULL CHECK (discount_type IN ('percentage', 'fixed')),
    discount_value DECIMAL(10,2) NOT NULL,
    min_purchase DECIMAL(10,2) DEFAULT 0,
    max_discount DECIMAL(10,2),
    usage_limit INTEGER,
    used_count INTEGER DEFAULT 0,
    valid_from TIMESTAMP NOT NULL,
    valid_until TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_coupons_code ON coupons(code);
CREATE INDEX IF NOT EXISTS idx_coupons_active ON coupons(is_active, valid_from, valid_until);

-- Tabla para seguimiento de uso de cupones por usuario
CREATE TABLE IF NOT EXISTS coupon_usage (
    id SERIAL PRIMARY KEY,
    coupon_id INTEGER REFERENCES coupons(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    order_id INTEGER,
    discount_amount DECIMAL(10,2) NOT NULL,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(coupon_id, user_id, order_id)
);

CREATE INDEX IF NOT EXISTS idx_coupon_usage_user ON coupon_usage(user_id);
CREATE INDEX IF NOT EXISTS idx_coupon_usage_coupon ON coupon_usage(coupon_id);

-- Función para validar cupón
CREATE OR REPLACE FUNCTION validate_coupon(coupon_code VARCHAR, user_id INTEGER, cart_total DECIMAL)
RETURNS TABLE(
    is_valid BOOLEAN,
    discount_type VARCHAR,
    discount_value DECIMAL,
    discount_amount DECIMAL,
    message VARCHAR
) AS $$
DECLARE
    coupon RECORD;
    user_uses INTEGER;
    discount_amount DECIMAL;
BEGIN
    -- Buscar cupón activo
    SELECT * INTO coupon
    FROM coupons
    WHERE code = UPPER(validate_coupon.coupon_code)
    AND is_active = TRUE
    AND valid_from <= NOW()
    AND valid_until >= NOW();
    
    IF NOT FOUND THEN
        RETURN QUERY SELECT false, NULL, NULL, 0::DECIMAL, 'Cupón no encontrado o expirado'::VARCHAR;
        RETURN;
    END IF;
    
    -- Verificar límite de uso total
    IF coupon.usage_limit IS NOT NULL AND coupon.used_count >= coupon.usage_limit THEN
        RETURN QUERY SELECT false, NULL, NULL, 0::DECIMAL, 'Cupón ha alcanzado su límite de uso'::VARCHAR;
        RETURN;
    END IF;
    
    -- Verificar compra mínima
    IF cart_total < coupon.min_purchase THEN
        RETURN QUERY SELECT false, NULL, NULL, 0::DECIMAL, 'Compra mínima requerida: ' || coupon.min_purchase::VARCHAR::VARCHAR;
        RETURN;
    END IF;
    
    -- Verificar uso por usuario (si se proporciona user_id)
    IF user_id IS NOT NULL THEN
        SELECT COUNT(*) INTO user_uses
        FROM coupon_usage
        WHERE coupon_id = coupon.id
        AND user_id = validate_coupon.user_id;
        
        -- Limitar a 1 uso por usuario por defecto
        IF user_uses >= 1 THEN
            RETURN QUERY SELECT false, NULL, NULL, 0::DECIMAL, 'Ya has usado este cupón'::VARCHAR;
            RETURN;
        END IF;
    END IF;
    
    -- Calcular descuento
    IF coupon.discount_type = 'percentage' THEN
        discount_amount := cart_total * (coupon.discount_value / 100);
    ELSE
        discount_amount := coupon.discount_value;
    END IF;
    
    -- Aplicar límite máximo de descuento
    IF coupon.max_discount IS NOT NULL AND discount_amount > coupon.max_discount THEN
        discount_amount := coupon.max_discount;
    END IF;
    
    -- El descuento no puede exceder el total del carrito
    IF discount_amount > cart_total THEN
        discount_amount := cart_total;
    END IF;
    
    RETURN QUERY SELECT true, coupon.discount_type, coupon.discount_value, discount_amount, 'Cupón válido'::VARCHAR;
END;
$$ LANGUAGE plpgsql;

-- Función para registrar uso de cupón
CREATE OR REPLACE FUNCTION apply_coupon(coupon_code VARCHAR, user_id INTEGER, order_id INTEGER, discount_amount DECIMAL)
RETURNS BOOLEAN AS $$
DECLARE
    coupon_id INTEGER;
BEGIN
    -- Obtener ID del cupón
    SELECT id INTO coupon_id
    FROM coupons
    WHERE code = UPPER(apply_coupon.coupon_code);
    
    IF NOT FOUND THEN
        RETURN false;
    END IF;
    
    -- Registrar uso
    INSERT INTO coupon_usage (coupon_id, user_id, order_id, discount_amount)
    VALUES (coupon_id, user_id, order_id, discount_amount);
    
    -- Incrementar contador de uso
    UPDATE coupons
    SET used_count = used_count + 1,
        updated_at = NOW()
    WHERE id = coupon_id;
    
    RETURN true;
EXCEPTION
    WHEN OTHERS THEN
        RETURN false;
END;
$$ LANGUAGE plpgsql;

-- Función para crear cupón
CREATE OR REPLACE FUNCTION create_coupon(
    coupon_code VARCHAR,
    description TEXT,
    discount_type VARCHAR,
    discount_value DECIMAL,
    min_purchase DECIMAL DEFAULT 0,
    max_discount DECIMAL DEFAULT NULL,
    usage_limit INTEGER DEFAULT NULL,
    valid_days INTEGER DEFAULT 30
)
RETURNS INTEGER AS $$
DECLARE
    coupon_id INTEGER;
BEGIN
    INSERT INTO coupons (
        code, description, discount_type, discount_value,
        min_purchase, max_discount, usage_limit,
        valid_from, valid_until
    )
    VALUES (
        UPPER(coupon_code), description, discount_type, discount_value,
        min_purchase, max_discount, usage_limit,
        NOW(), NOW() + (valid_days || ' days')::INTERVAL
    )
    RETURNING id INTO coupon_id;
    
    RETURN coupon_id;
END;
$$ LANGUAGE plpgsql;

-- Comentarios
COMMENT ON TABLE coupons IS 'Cupones de descuento promocionales';
COMMENT ON TABLE coupon_usage IS 'Seguimiento de uso de cupones por usuario';
COMMENT ON FUNCTION validate_coupon(VARCHAR, INTEGER, DECIMAL) IS 'Valida si un cupón es aplicable';
COMMENT ON FUNCTION apply_coupon(VARCHAR, INTEGER, INTEGER, DECIMAL) IS 'Registra el uso de un cupón';
COMMENT ON FUNCTION create_coupon(VARCHAR, TEXT, VARCHAR, DECIMAL, DECIMAL, DECIMAL, INTEGER, INTEGER) IS 'Crea un nuevo cupón de descuento';
