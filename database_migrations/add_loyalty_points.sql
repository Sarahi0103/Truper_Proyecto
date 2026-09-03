-- Tabla de puntos de lealtad
CREATE TABLE IF NOT EXISTS loyalty_points (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    points INTEGER NOT NULL DEFAULT 0,
    tier VARCHAR(20) DEFAULT 'bronze', -- bronze, silver, gold, platinum
    total_earned INTEGER DEFAULT 0,
    total_redeemed INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id)
);

-- Índices para mejor rendimiento
CREATE INDEX IF NOT EXISTS idx_loyalty_points_user ON loyalty_points(user_id);
CREATE INDEX IF NOT EXISTS idx_loyalty_points_tier ON loyalty_points(tier);

-- Tabla de transacciones de puntos
CREATE TABLE IF NOT EXISTS point_transactions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    points INTEGER NOT NULL, -- Positivo para ganados, negativo para redimidos
    transaction_type VARCHAR(20) NOT NULL, -- earned, redeemed, expired, adjusted
    reference_id INTEGER, -- ID de orden, cupón, etc.
    reference_type VARCHAR(50), -- order, coupon, manual, etc.
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices para transacciones
CREATE INDEX IF NOT EXISTS idx_point_transactions_user ON point_transactions(user_id);
CREATE INDEX IF NOT EXISTS idx_point_transactions_type ON point_transactions(transaction_type);
CREATE INDEX IF NOT EXISTS idx_point_transactions_created ON point_transactions(created_at DESC);

-- Tabla de recompensas por nivel
CREATE TABLE IF NOT EXISTS loyalty_rewards (
    id SERIAL PRIMARY KEY,
    tier VARCHAR(20) NOT NULL,
    points_required INTEGER NOT NULL,
    reward_type VARCHAR(50) NOT NULL, -- discount, free_shipping, product
    reward_value DECIMAL(10,2),
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices para recompensas
CREATE INDEX IF NOT EXISTS idx_loyalty_rewards_tier ON loyalty_rewards(tier);
CREATE INDEX IF NOT EXISTS idx_loyalty_rewards_active ON loyalty_rewards(is_active);

-- Función para agregar puntos a un usuario
CREATE OR REPLACE FUNCTION add_loyalty_points(p_user_id INTEGER, p_points INTEGER, p_transaction_type VARCHAR, p_reference_id INTEGER DEFAULT NULL, p_reference_type VARCHAR DEFAULT NULL, p_description TEXT DEFAULT NULL)
RETURNS INTEGER AS $$
DECLARE
    v_current_points INTEGER;
    v_new_tier VARCHAR(20);
BEGIN
    -- Insertar o actualizar puntos del usuario
    INSERT INTO loyalty_points (user_id, points, total_earned)
    VALUES (p_user_id, p_points, p_points)
    ON CONFLICT (user_id) DO UPDATE SET
        points = loyalty_points.points + p_points,
        total_earned = loyalty_points.total_earned + p_points,
        updated_at = CURRENT_TIMESTAMP;
    
    -- Obtener puntos actualizados
    SELECT points INTO v_current_points
    FROM loyalty_points
    WHERE user_id = p_user_id;
    
    -- Determinar nuevo nivel
    IF v_current_points >= 10000 THEN
        v_new_tier := 'platinum';
    ELSIF v_current_points >= 5000 THEN
        v_new_tier := 'gold';
    ELSIF v_current_points >= 2000 THEN
        v_new_tier := 'silver';
    ELSE
        v_new_tier := 'bronze';
    END IF;
    
    -- Actualizar nivel
    UPDATE loyalty_points
    SET tier = v_new_tier,
        updated_at = CURRENT_TIMESTAMP
    WHERE user_id = p_user_id;
    
    -- Registrar transacción
    INSERT INTO point_transactions (user_id, points, transaction_type, reference_id, reference_type, description)
    VALUES (p_user_id, p_points, p_transaction_type, p_reference_id, p_reference_type, p_description);
    
    RETURN v_current_points;
END;
$$ LANGUAGE plpgsql;

-- Función para redimir puntos
CREATE OR REPLACE FUNCTION redeem_loyalty_points(p_user_id INTEGER, p_points INTEGER, p_reference_id INTEGER DEFAULT NULL, p_reference_type VARCHAR DEFAULT NULL, p_description TEXT DEFAULT NULL)
RETURNS BOOLEAN AS $$
DECLARE
    v_current_points INTEGER;
BEGIN
    -- Obtener puntos actuales
    SELECT points INTO v_current_points
    FROM loyalty_points
    WHERE user_id = p_user_id;
    
    IF v_current_points IS NULL OR v_current_points < p_points THEN
        RETURN FALSE;
    END IF;
    
    -- Actualizar puntos
    UPDATE loyalty_points
    SET points = points - p_points,
        total_redeemed = total_redeemed + p_points,
        updated_at = CURRENT_TIMESTAMP
    WHERE user_id = p_user_id;
    
    -- Registrar transacción
    INSERT INTO point_transactions (user_id, points, transaction_type, reference_id, reference_type, description)
    VALUES (p_user_id, -p_points, 'redeemed', p_reference_id, p_reference_type, p_description);
    
    RETURN TRUE;
END;
$$ LANGUAGE plpgsql;

-- Función para obtener balance de puntos de un usuario
CREATE OR REPLACE FUNCTION get_user_points_balance(p_user_id INTEGER)
RETURNS JSON AS $$
DECLARE
    v_points INTEGER;
    v_tier VARCHAR(20);
    v_total_earned INTEGER;
    v_total_redeemed INTEGER;
BEGIN
    SELECT points, tier, total_earned, total_redeemed
    INTO v_points, v_tier, v_total_earned, v_total_redeemed
    FROM loyalty_points
    WHERE user_id = p_user_id;
    
    RETURN json_build_object(
        'points', COALESCE(v_points, 0),
        'tier', COALESCE(v_tier, 'bronze'),
        'total_earned', COALESCE(v_total_earned, 0),
        'total_redeemed', COALESCE(v_total_redeemed, 0)
    );
END;
$$ LANGUAGE plpgsql;

-- Función para calcular puntos de una orden (1 punto por cada $100)
CREATE OR REPLACE FUNCTION calculate_order_points(p_order_amount DECIMAL)
RETURNS INTEGER AS $$
BEGIN
    RETURN FLOOR(p_order_amount / 100);
END;
$$ LANGUAGE plpgsql;
