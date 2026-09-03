-- Migración: Sistema de Reviews/Ratings de Productos
-- Calificaciones y reseñas de productos por clientes
-- Fecha: 2026-08-18

-- Tabla de reviews de productos
CREATE TABLE IF NOT EXISTS product_reviews (
    id SERIAL PRIMARY KEY,
    product_id INTEGER REFERENCES products(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    order_id INTEGER REFERENCES sales_tickets(id) ON DELETE SET NULL,
    rating INTEGER NOT NULL CHECK (rating >= 1 AND rating <= 5),
    title VARCHAR(200),
    review TEXT,
    pros TEXT[],
    cons TEXT[],
    would_recommend BOOLEAN DEFAULT true,
    verified_purchase BOOLEAN DEFAULT false,
    is_approved BOOLEAN DEFAULT false,
    helpful_count INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='product_reviews' AND column_name='order_id') THEN
        ALTER TABLE product_reviews ADD COLUMN order_id INTEGER REFERENCES sales_tickets(id) ON DELETE SET NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='product_reviews' AND column_name='pros') THEN
        ALTER TABLE product_reviews ADD COLUMN pros TEXT[];
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='product_reviews' AND column_name='cons') THEN
        ALTER TABLE product_reviews ADD COLUMN cons TEXT[];
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='product_reviews' AND column_name='would_recommend') THEN
        ALTER TABLE product_reviews ADD COLUMN would_recommend BOOLEAN DEFAULT true;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='product_reviews' AND column_name='verified_purchase') THEN
        ALTER TABLE product_reviews ADD COLUMN verified_purchase BOOLEAN DEFAULT false;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='product_reviews' AND column_name='is_approved') THEN
        ALTER TABLE product_reviews ADD COLUMN is_approved BOOLEAN DEFAULT false;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='product_reviews' AND column_name='helpful_count') THEN
        ALTER TABLE product_reviews ADD COLUMN helpful_count INTEGER DEFAULT 0;
    END IF;
END $$;

-- Índices para rendimiento
CREATE INDEX IF NOT EXISTS idx_product_reviews_product ON product_reviews(product_id);
CREATE INDEX IF NOT EXISTS idx_product_reviews_user ON product_reviews(user_id);
CREATE INDEX IF NOT EXISTS idx_product_reviews_order ON product_reviews(order_id);
CREATE INDEX IF NOT EXISTS idx_product_reviews_rating ON product_reviews(rating);

-- Tabla de votos útiles para reviews
CREATE TABLE IF NOT EXISTS review_helpful_votes (
    id SERIAL PRIMARY KEY,
    review_id INTEGER REFERENCES product_reviews(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(review_id, user_id)
);

-- Índices para votos útiles
CREATE INDEX IF NOT EXISTS idx_review_helpful_votes_review ON review_helpful_votes(review_id);
CREATE INDEX IF NOT EXISTS idx_review_helpful_votes_user ON review_helpful_votes(user_id);

-- Función para calcular rating promedio de un producto
CREATE OR REPLACE FUNCTION get_product_rating(p_product_id INTEGER)
RETURNS JSON AS $$
DECLARE
    v_avg_rating DECIMAL;
    v_total_reviews INTEGER;
    v_rating_distribution JSON;
BEGIN
    SELECT 
        COALESCE(AVG(rating), 0),
        COUNT(*) 
    INTO v_avg_rating, v_total_reviews
    FROM product_reviews
    WHERE product_id = p_product_id AND is_approved = true;
    
    SELECT json_object_agg(rating, count) INTO v_rating_distribution
    FROM (
        SELECT rating, COUNT(*) as count
        FROM product_reviews
        WHERE product_id = p_product_id AND is_approved = true
        GROUP BY rating
    ) rating_counts;
    
    RETURN json_build_object(
        'average_rating', ROUND(v_avg_rating::numeric, 2),
        'total_reviews', v_total_reviews,
        'rating_distribution', COALESCE(v_rating_distribution, '{}'::json)
    );
END;
$$ LANGUAGE plpgsql;

-- Función para marcar review como compra verificada
CREATE OR REPLACE FUNCTION mark_review_as_verified(p_review_id INTEGER, p_order_id INTEGER)
RETURNS VOID AS $$
BEGIN
    UPDATE product_reviews
    SET verified_purchase = true
    WHERE id = p_review_id AND order_id = p_order_id;
END;
$$ LANGUAGE plpgsql;

-- Trigger para updated_at en product_reviews
CREATE OR REPLACE FUNCTION update_product_reviews_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_product_reviews_updated_at ON product_reviews;
CREATE TRIGGER trg_product_reviews_updated_at
BEFORE UPDATE ON product_reviews
FOR EACH ROW
EXECUTE FUNCTION update_product_reviews_updated_at();

-- Vista de reviews aprobados con datos de usuario
CREATE OR REPLACE VIEW approved_product_reviews AS
SELECT 
    pr.id,
    pr.product_id,
    pr.user_id,
    u.first_name,
    u.last_name,
    pr.rating,
    pr.title,
    pr.review,
    pr.pros,
    pr.cons,
    pr.would_recommend,
    pr.verified_purchase,
    pr.helpful_count,
    pr.created_at,
    CASE 
        WHEN pr.created_at > NOW() - INTERVAL '30 days' THEN true
        ELSE false
    END as is_recent
FROM product_reviews pr
LEFT JOIN users u ON pr.user_id = u.id
WHERE pr.is_approved = true
ORDER BY pr.created_at DESC;

-- Comentarios
COMMENT ON TABLE product_reviews IS 'Reviews y ratings de productos por clientes';
COMMENT ON TABLE review_helpful_votes IS 'Votos útiles para reviews';
