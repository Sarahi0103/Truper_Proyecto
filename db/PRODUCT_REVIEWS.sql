-- Sistema de Reviews de Productos
-- Tabla para reviews y calificaciones de productos

CREATE TABLE IF NOT EXISTS product_reviews (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    product_id INTEGER NOT NULL,
    product_type VARCHAR(20) DEFAULT 'catalog', -- catalog, marketplace
    rating INTEGER NOT NULL CHECK (rating >= 1 AND rating <= 5),
    title VARCHAR(255),
    review TEXT,
    pros TEXT,
    cons TEXT,
    verified_purchase BOOLEAN DEFAULT FALSE,
    helpful_count INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, product_id, product_type)
);

CREATE INDEX IF NOT EXISTS idx_product_reviews_product ON product_reviews(product_id, product_type);
CREATE INDEX IF NOT EXISTS idx_product_reviews_user ON product_reviews(user_id);
CREATE INDEX IF NOT EXISTS idx_product_reviews_rating ON product_reviews(rating);

-- Función para agregar review de producto
CREATE OR REPLACE FUNCTION add_product_review(user_id INTEGER, product_id INTEGER, product_type VARCHAR, rating INTEGER, title VARCHAR, review TEXT, pros TEXT, cons TEXT, verified_purchase BOOLEAN DEFAULT FALSE)
RETURNS INTEGER AS $$
DECLARE
    review_id INTEGER;
BEGIN
    INSERT INTO product_reviews (user_id, product_id, product_type, rating, title, review, pros, cons, verified_purchase)
    VALUES (user_id, product_id, product_type, rating, title, review, pros, cons, verified_purchase)
    ON CONFLICT (user_id, product_id, product_type) 
    DO UPDATE SET
        rating = EXCLUDED.rating,
        title = EXCLUDED.title,
        review = EXCLUDED.review,
        pros = EXCLUDED.pros,
        cons = EXCLUDED.cons,
        updated_at = NOW()
    RETURNING id INTO review_id;
    
    RETURN review_id;
END;
$$ LANGUAGE plpgsql;

-- Función para obtener reviews de producto
CREATE OR REPLACE FUNCTION get_product_reviews(product_id INTEGER, product_type VARCHAR DEFAULT 'catalog')
RETURNS TABLE(
    id INTEGER,
    user_id INTEGER,
    rating INTEGER,
    title VARCHAR,
    review TEXT,
    pros TEXT,
    cons TEXT,
    verified_purchase BOOLEAN,
    helpful_count INTEGER,
    created_at TIMESTAMP,
    user_name VARCHAR,
    user_first_name VARCHAR,
    user_last_name VARCHAR
) AS $$
BEGIN
    RETURN QUERY
    SELECT 
        pr.id,
        pr.user_id,
        pr.rating,
        pr.title,
        pr.review,
        pr.pros,
        pr.cons,
        pr.verified_purchase,
        pr.helpful_count,
        pr.created_at,
        u.name AS user_name,
        u.first_name AS user_first_name,
        u.last_name AS user_last_name
    FROM product_reviews pr
    LEFT JOIN users u ON pr.user_id = u.id
    WHERE pr.product_id = get_product_reviews.product_id 
    AND pr.product_type = get_product_reviews.product_type
    ORDER BY pr.helpful_count DESC, pr.created_at DESC;
END;
$$ LANGUAGE plpgsql;

-- Función para obtener promedio de calificaciones de producto
CREATE OR REPLACE FUNCTION get_product_rating_stats(product_id INTEGER, product_type VARCHAR DEFAULT 'catalog')
RETURNS TABLE(
    average_rating DECIMAL,
    total_reviews INTEGER,
    rating_1 INTEGER,
    rating_2 INTEGER,
    rating_3 INTEGER,
    rating_4 INTEGER,
    rating_5 INTEGER
) AS $$
BEGIN
    RETURN QUERY
    SELECT 
        ROUND(AVG(pr.rating)::DECIMAL, 2) AS average_rating,
        COUNT(*) AS total_reviews,
        SUM(CASE WHEN pr.rating = 1 THEN 1 ELSE 0 END) AS rating_1,
        SUM(CASE WHEN pr.rating = 2 THEN 1 ELSE 0 END) AS rating_2,
        SUM(CASE WHEN pr.rating = 3 THEN 1 ELSE 0 END) AS rating_3,
        SUM(CASE WHEN pr.rating = 4 THEN 1 ELSE 0 END) AS rating_4,
        SUM(CASE WHEN pr.rating = 5 THEN 1 ELSE 0 END) AS rating_5
    FROM product_reviews pr
    WHERE pr.product_id = get_product_rating_stats.product_id 
    AND pr.product_type = get_product_rating_stats.product_type;
END;
$$ LANGUAGE plpgsql;

-- Función para marcar review como útil
CREATE OR REPLACE FUNCTION mark_review_helpful(review_id INTEGER)
RETURNS BOOLEAN AS $$
BEGIN
    UPDATE product_reviews 
    SET helpful_count = helpful_count + 1
    WHERE id = mark_review_helpful.review_id;
    RETURN true;
EXCEPTION
    WHEN OTHERS THEN
        RETURN false;
END;
$$ LANGUAGE plpgsql;

-- Función para eliminar review
CREATE OR REPLACE FUNCTION delete_product_review(review_id INTEGER, user_id INTEGER)
RETURNS BOOLEAN AS $$
BEGIN
    DELETE FROM product_reviews 
    WHERE id = delete_product_review.review_id 
    AND user_id = delete_product_review.user_id;
    RETURN true;
EXCEPTION
    WHEN OTHERS THEN
        RETURN false;
END;
$$ LANGUAGE plpgsql;

-- Comentarios
COMMENT ON TABLE product_reviews IS 'Reviews y calificaciones de productos';
COMMENT ON FUNCTION add_product_review(INTEGER, INTEGER, VARCHAR, INTEGER, VARCHAR, TEXT, TEXT, TEXT, BOOLEAN) IS 'Agrega o actualiza review de producto';
COMMENT ON FUNCTION get_product_reviews(INTEGER, VARCHAR) IS 'Obtiene reviews de un producto';
COMMENT ON FUNCTION get_product_rating_stats(INTEGER, VARCHAR) IS 'Obtiene estadísticas de calificaciones de producto';
COMMENT ON FUNCTION mark_review_helpful(INTEGER) IS 'Marca review como útil';
COMMENT ON FUNCTION delete_product_review(INTEGER, INTEGER) IS 'Elimina review de producto';
