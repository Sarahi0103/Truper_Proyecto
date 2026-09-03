-- Migración: Sistema de Cupones y Descuentos
-- Permite crear códigos promocionales con reglas de uso
-- Fecha: 2026-08-18

-- Tabla de cupones
CREATE TABLE IF NOT EXISTS coupons (
    id SERIAL PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    discount_type VARCHAR(20) NOT NULL CHECK (discount_type IN ('percentage', 'fixed')),
    discount_value DECIMAL(10, 2) NOT NULL,
    min_purchase_amount DECIMAL(10, 2) DEFAULT 0,
    max_discount_amount DECIMAL(10, 2),
    usage_limit INTEGER DEFAULT NULL,
    usage_count INTEGER DEFAULT 0,
    valid_from TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    valid_until TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT true,
    applicable_to_segments TEXT[],
    applicable_to_categories TEXT[],
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='coupons' AND column_name='min_purchase_amount') THEN
        ALTER TABLE coupons ADD COLUMN min_purchase_amount DECIMAL(10, 2) DEFAULT 0;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='coupons' AND column_name='max_discount_amount') THEN
        ALTER TABLE coupons ADD COLUMN max_discount_amount DECIMAL(10, 2);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='coupons' AND column_name='applicable_to_segments') THEN
        ALTER TABLE coupons ADD COLUMN applicable_to_segments TEXT[];
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='coupons' AND column_name='applicable_to_categories') THEN
        ALTER TABLE coupons ADD COLUMN applicable_to_categories TEXT[];
    END IF;
END $$;

-- Índices para rendimiento
CREATE INDEX IF NOT EXISTS idx_coupons_code ON coupons(code);
CREATE INDEX IF NOT EXISTS idx_coupons_active ON coupons(is_active, valid_from, valid_until);
CREATE INDEX IF NOT EXISTS idx_coupons_dates ON coupons(valid_from, valid_until);

-- Tabla de uso de cupones por pedido
CREATE TABLE IF NOT EXISTS coupon_usage (
    id SERIAL PRIMARY KEY,
    coupon_id INTEGER REFERENCES coupons(id) ON DELETE CASCADE,
    order_id INTEGER REFERENCES sales_tickets(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    discount_amount DECIMAL(10, 2) NOT NULL,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices para tracking de uso
CREATE INDEX IF NOT EXISTS idx_coupon_usage_coupon ON coupon_usage(coupon_id);
CREATE INDEX IF NOT EXISTS idx_coupon_usage_order ON coupon_usage(order_id);
CREATE INDEX IF NOT EXISTS idx_coupon_usage_user ON coupon_usage(user_id);

-- Función para validar cupón
CREATE OR REPLACE FUNCTION validate_coupon(
    p_code VARCHAR,
    p_user_id INTEGER,
    p_cart_total DECIMAL,
    p_user_segment VARCHAR DEFAULT NULL
)
RETURNS JSON AS $$
DECLARE
    coupon RECORD;
    user_uses INTEGER;
    is_valid BOOLEAN := false;
    discount_amount DECIMAL(10, 2);
    error_message TEXT;
BEGIN
    -- Buscar cupón activo y válido
    SELECT * INTO coupon
    FROM coupons
    WHERE code = p_code
    AND is_active = true
    AND valid_from <= CURRENT_TIMESTAMP
    AND valid_until >= CURRENT_TIMESTAMP;
    
    IF NOT FOUND THEN
        error_message := 'Cupón no encontrado o expirado';
        RETURN json_build_object('valid', false, 'error', error_message);
    END IF;
    
    -- Verificar límite de uso global
    IF coupon.usage_limit IS NOT NULL AND coupon.usage_count >= coupon.usage_limit THEN
        error_message := 'Cupón ha alcanzado su límite de uso';
        RETURN json_build_object('valid', false, 'error', error_message);
    END IF;
    
    -- Verificar uso por usuario
    IF p_user_id IS NOT NULL THEN
        SELECT COUNT(*) INTO user_uses
        FROM coupon_usage
        WHERE coupon_id = coupon.id AND user_id = p_user_id;
        
        -- Limitar a 1 uso por usuario por defecto (configurable)
        IF user_uses >= 1 THEN
            error_message := 'Ya has usado este cupón anteriormente';
            RETURN json_build_object('valid', false, 'error', error_message);
        END IF;
    END IF;
    
    -- Verificar monto mínimo de compra
    IF p_cart_total < coupon.min_purchase_amount THEN
        error_message := 'Monto mínimo de compra no alcanzado: $' || coupon.min_purchase_amount;
        RETURN json_build_object('valid', false, 'error', error_message);
    END IF;
    
    -- Verificar segmento de cliente
    IF coupon.applicable_to_segments IS NOT NULL AND p_user_segment IS NOT NULL THEN
        IF NOT (p_user_segment = ANY(coupon.applicable_to_segments)) THEN
            error_message := 'Cupón no aplicable a tu segmento de cliente';
            RETURN json_build_object('valid', false, 'error', error_message);
        END IF;
    END IF;
    
    -- Calcular descuento
    IF coupon.discount_type = 'percentage' THEN
        discount_amount := p_cart_total * (coupon.discount_value / 100);
        
        -- Aplicar límite máximo de descuento si existe
        IF coupon.max_discount_amount IS NOT NULL AND discount_amount > coupon.max_discount_amount THEN
            discount_amount := coupon.max_discount_amount;
        END IF;
    ELSE -- fixed
        discount_amount := coupon.discount_value;
        
        -- No puede exceder el total del carrito
        IF discount_amount > p_cart_total THEN
            discount_amount := p_cart_total;
        END IF;
    END IF;
    
    is_valid := true;
    
    RETURN json_build_object(
        'valid', is_valid,
        'coupon_id', coupon.id,
        'discount_amount', discount_amount,
        'discount_type', coupon.discount_type,
        'discount_value', coupon.discount_value,
        'description', coupon.description
    );
END;
$$ LANGUAGE plpgsql;

-- Función para registrar uso de cupón
CREATE OR REPLACE FUNCTION apply_coupon(
    p_coupon_id INTEGER,
    p_order_id INTEGER,
    p_user_id INTEGER,
    p_discount_amount DECIMAL
)
RETURNS JSON AS $$
BEGIN
    -- Registrar uso del cupón
    INSERT INTO coupon_usage (coupon_id, order_id, user_id, discount_amount)
    VALUES (p_coupon_id, p_order_id, p_user_id, p_discount_amount);
    
    -- Incrementar contador de uso del cupón
    UPDATE coupons
    SET usage_count = usage_count + 1,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = p_coupon_id;
    
    RETURN json_build_object('success', true);
END;
$$ LANGUAGE plpgsql;

-- Trigger para updated_at en coupons
CREATE OR REPLACE FUNCTION update_coupons_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_coupons_updated_at ON coupons;
CREATE TRIGGER trg_coupons_updated_at
BEFORE UPDATE ON coupons
FOR EACH ROW
EXECUTE FUNCTION update_coupons_updated_at();

-- Insertar cupones de ejemplo
INSERT INTO coupons (code, description, discount_type, discount_value, min_purchase_amount, valid_from, valid_until) VALUES
('BIENVENIDO10', 'Descuento para nuevos clientes', 'percentage', 10.00, 500.00, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP + INTERVAL '30 days'),
('ENVIOGRATIS', 'Envío gratis en compras mayores a $1000', 'fixed', 15.00, 1000.00, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP + INTERVAL '60 days'),
('TRUPER20', 'Descuento especial Truper', 'percentage', 20.00, 2000.00, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP + INTERVAL '15 days')
ON CONFLICT (code) DO NOTHING;

-- Comentario sobre la tabla
COMMENT ON TABLE coupons IS 'Sistema de cupones promocionales con reglas de uso y segmentación';
COMMENT ON TABLE coupon_usage IS 'Registro de uso de cupones por pedido y usuario';
