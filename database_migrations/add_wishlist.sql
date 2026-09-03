-- Tabla de wishlist/favoritos
CREATE TABLE IF NOT EXISTS wishlist (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    product_id INTEGER REFERENCES products(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, product_id)
);

-- Índices para mejor rendimiento
CREATE INDEX IF NOT EXISTS idx_wishlist_user ON wishlist(user_id);
CREATE INDEX IF NOT EXISTS idx_wishlist_product ON wishlist(product_id);
CREATE INDEX IF NOT EXISTS idx_wishlist_created ON wishlist(created_at DESC);

-- Función para agregar producto a wishlist
CREATE OR REPLACE FUNCTION add_to_wishlist(p_user_id INTEGER, p_product_id INTEGER)
RETURNS INTEGER AS $$
DECLARE
    wishlist_id INTEGER;
BEGIN
    INSERT INTO wishlist (user_id, product_id)
    VALUES (p_user_id, p_product_id)
    ON CONFLICT (user_id, product_id) DO NOTHING
    RETURNING id INTO wishlist_id;
    
    RETURN COALESCE(wishlist_id, 0);
END;
$$ LANGUAGE plpgsql;

-- Función para eliminar producto de wishlist
CREATE OR REPLACE FUNCTION remove_from_wishlist(p_user_id INTEGER, p_product_id INTEGER)
RETURNS BOOLEAN AS $$
BEGIN
    DELETE FROM wishlist
    WHERE user_id = p_user_id AND product_id = p_product_id;
    
    RETURN FOUND;
END;
$$ LANGUAGE plpgsql;

-- Función para verificar si producto está en wishlist
CREATE OR REPLACE FUNCTION is_in_wishlist(p_user_id INTEGER, p_product_id INTEGER)
RETURNS BOOLEAN AS $$
BEGIN
    RETURN EXISTS (
        SELECT 1 FROM wishlist
        WHERE user_id = p_user_id AND product_id = p_product_id
    );
END;
$$ LANGUAGE plpgsql;

-- Función para obtener wishlist de un usuario
CREATE OR REPLACE FUNCTION get_user_wishlist(p_user_id INTEGER)
RETURNS TABLE (
    product_id INTEGER,
    product_name VARCHAR(255),
    product_sku VARCHAR(50),
    product_price DECIMAL,
    product_image TEXT,
    product_stock INTEGER,
    added_at TIMESTAMP
) AS $$
BEGIN
    RETURN QUERY
    SELECT 
        p.id,
        p.name,
        p.sku,
        p.price,
        p.image_url,
        COALESCE(p.stock_online, p.stock_quantity) as stock,
        w.created_at
    FROM wishlist w
    JOIN products p ON w.product_id = p.id
    WHERE w.user_id = p_user_id
    ORDER BY w.created_at DESC;
END;
$$ LANGUAGE plpgsql;
