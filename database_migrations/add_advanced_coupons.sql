-- Agregar columnas faltantes a la tabla coupons existente
DO $$
BEGIN
    -- Agregar columnas si no existen
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='coupons' AND column_name='user_usage_limit') THEN
        ALTER TABLE coupons ADD COLUMN user_usage_limit INTEGER DEFAULT 1;
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='coupons' AND column_name='applicable_products') THEN
        ALTER TABLE coupons ADD COLUMN applicable_products INTEGER[];
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='coupons' AND column_name='created_by') THEN
        ALTER TABLE coupons ADD COLUMN created_by INTEGER REFERENCES users(id) ON DELETE SET NULL;
    END IF;
END $$;

-- Índices para mejor rendimiento
CREATE INDEX IF NOT EXISTS idx_coupons_code ON coupons(code);
CREATE INDEX IF NOT EXISTS idx_coupons_active ON coupons(is_active);
CREATE INDEX IF NOT EXISTS idx_coupons_validity ON coupons(valid_from, valid_until);

-- Tabla de uso de cupones por usuario
CREATE TABLE IF NOT EXISTS coupon_usage (
    id SERIAL PRIMARY KEY,
    coupon_id INTEGER REFERENCES coupons(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    order_id INTEGER REFERENCES orders(id) ON DELETE SET NULL,
    discount_amount DECIMAL(10,2) NOT NULL,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(coupon_id, user_id, order_id)
);

-- Índices para uso de cupones
CREATE INDEX IF NOT EXISTS idx_coupon_usage_coupon ON coupon_usage(coupon_id);
CREATE INDEX IF NOT EXISTS idx_coupon_usage_user ON coupon_usage(user_id);
CREATE INDEX IF NOT EXISTS idx_coupon_usage_order ON coupon_usage(order_id);

-- Función para validar cupón
CREATE OR REPLACE FUNCTION validate_coupon(
    p_code VARCHAR(50),
    p_user_id INTEGER,
    p_order_amount DECIMAL,
    p_product_ids INTEGER[] DEFAULT NULL
)
RETURNS JSON AS $$
DECLARE
    v_coupon RECORD;
    v_user_uses INTEGER;
    v_is_valid BOOLEAN := TRUE;
    v_error_message TEXT := '';
    v_discount_amount DECIMAL := 0;
BEGIN
    -- Obtener cupón
    SELECT * INTO v_coupon
    FROM coupons
    WHERE code = p_code AND is_active = TRUE;
    
    IF NOT FOUND THEN
        v_is_valid := FALSE;
        v_error_message := 'Cupón no encontrado o inactivo';
        RETURN json_build_object(
            'valid', v_is_valid,
            'error', v_error_message,
            'discount_amount', v_discount_amount
        );
    END IF;
    
    -- Verificar fecha de validez
    IF NOW() < v_coupon.valid_from OR NOW() > v_coupon.valid_until THEN
        v_is_valid := FALSE;
        v_error_message := 'Cupón expirado o aún no válido';
        RETURN json_build_object(
            'valid', v_is_valid,
            'error', v_error_message,
            'discount_amount', v_discount_amount
        );
    END IF;
    
    -- Verificar monto mínimo
    IF p_order_amount < v_coupon.minimum_order_amount THEN
        v_is_valid := FALSE;
        v_error_message := 'Monto mínimo de orden no alcanzado';
        RETURN json_build_object(
            'valid', v_is_valid,
            'error', v_error_message,
            'discount_amount', v_discount_amount
        );
    END IF;
    
    -- Verificar límite de uso total
    IF v_coupon.usage_limit IS NOT NULL AND v_coupon.usage_count >= v_coupon.usage_limit THEN
        v_is_valid := FALSE;
        v_error_message := 'Cupón agotado';
        RETURN json_build_object(
            'valid', v_is_valid,
            'error', v_error_message,
            'discount_amount', v_discount_amount
        );
    END IF;
    
    -- Verificar límite de uso por usuario
    SELECT COUNT(*) INTO v_user_uses
    FROM coupon_usage
    WHERE coupon_id = v_coupon.id AND user_id = p_user_id;
    
    IF v_user_uses >= v_coupon.user_usage_limit THEN
        v_is_valid := FALSE;
        v_error_message := 'Has alcanzado el límite de uso de este cupón';
        RETURN json_build_object(
            'valid', v_is_valid,
            'error', v_error_message,
            'discount_amount', v_discount_amount
        );
    END IF;
    
    -- Verificar productos aplicables
    IF v_coupon.applicable_products IS NOT NULL AND p_product_ids IS NOT NULL THEN
        IF NOT EXISTS (
            SELECT 1 FROM unnest(p_product_ids) AS pid
            WHERE pid = ANY(v_coupon.applicable_products)
        ) THEN
            v_is_valid := FALSE;
            v_error_message := 'Cupón no aplicable a estos productos';
            RETURN json_build_object(
                'valid', v_is_valid,
                'error', v_error_message,
                'discount_amount', v_discount_amount
            );
        END IF;
    END IF;
    
    -- Calcular descuento
    IF v_coupon.discount_type = 'percentage' THEN
        v_discount_amount := p_order_amount * (v_coupon.discount_value / 100);
        
        -- Aplicar límite máximo de descuento
        IF v_coupon.maximum_discount_amount IS NOT NULL THEN
            v_discount_amount := LEAST(v_discount_amount, v_coupon.maximum_discount_amount);
        END IF;
    ELSIF v_coupon.discount_type = 'fixed_amount' THEN
        v_discount_amount := v_coupon.discount_value;
    ELSIF v_coupon.discount_type = 'free_shipping' THEN
        v_discount_amount := 0; -- El descuento se aplica en envío
    END IF;
    
    RETURN json_build_object(
        'valid', v_is_valid,
        'coupon_id', v_coupon.id,
        'discount_type', v_coupon.discount_type,
        'discount_amount', v_discount_amount,
        'description', v_coupon.description
    );
END;
$$ LANGUAGE plpgsql;

-- Función para registrar uso de cupón
CREATE OR REPLACE FUNCTION register_coupon_usage(
    p_coupon_id INTEGER,
    p_user_id INTEGER,
    p_order_id INTEGER,
    p_discount_amount DECIMAL
)
RETURNS BOOLEAN AS $$
BEGIN
    -- Registrar uso
    INSERT INTO coupon_usage (coupon_id, user_id, order_id, discount_amount)
    VALUES (p_coupon_id, p_user_id, p_order_id, p_discount_amount);
    
    -- Incrementar contador de uso del cupón
    UPDATE coupons
    SET usage_count = usage_count + 1,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = p_coupon_id;
    
    RETURN TRUE;
END;
$$ LANGUAGE plpgsql;

-- Función para obtener cupones activos
CREATE OR REPLACE FUNCTION get_active_coupons()
RETURNS TABLE (
    id INTEGER,
    code VARCHAR(50),
    description TEXT,
    discount_type VARCHAR(20),
    discount_value DECIMAL,
    valid_from TIMESTAMP,
    valid_until TIMESTAMP,
    usage_count INTEGER,
    usage_limit INTEGER
) AS $$
BEGIN
    RETURN QUERY
    SELECT id, code, description, discount_type, discount_value, 
           valid_from, valid_until, usage_count, usage_limit
    FROM coupons
    WHERE is_active = TRUE
    AND valid_from <= NOW()
    AND valid_until >= NOW()
    ORDER BY created_at DESC;
END;
$$ LANGUAGE plpgsql;
