-- Sistema de Favoritos de Productos
-- Tabla para guardar productos favoritos de usuarios

CREATE TABLE IF NOT EXISTS product_favorites (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    product_id INTEGER NOT NULL,
    product_type VARCHAR(20) DEFAULT 'catalog', -- catalog, marketplace
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, product_id, product_type)
);

CREATE INDEX IF NOT EXISTS idx_product_favorites_user ON product_favorites(user_id);
CREATE INDEX IF NOT EXISTS idx_product_favorites_product ON product_favorites(product_id, product_type);

-- Función para agregar producto a favoritos
CREATE OR REPLACE FUNCTION add_to_favorites(user_id INTEGER, product_id INTEGER, product_type VARCHAR DEFAULT 'catalog')
RETURNS BOOLEAN AS $$
BEGIN
    INSERT INTO product_favorites (user_id, product_id, product_type)
    VALUES (user_id, product_id, product_type)
    ON CONFLICT (user_id, product_id, product_type) DO NOTHING;
    RETURN true;
EXCEPTION
    WHEN OTHERS THEN
        RETURN false;
END;
$$ LANGUAGE plpgsql;

-- Función para eliminar producto de favoritos
CREATE OR REPLACE FUNCTION remove_from_favorites(user_id INTEGER, product_id INTEGER, product_type VARCHAR DEFAULT 'catalog')
RETURNS BOOLEAN AS $$
BEGIN
    DELETE FROM product_favorites 
    WHERE user_id = remove_from_favorites.user_id 
    AND product_id = remove_from_favorites.product_id 
    AND product_type = remove_from_favorites.product_type;
    RETURN true;
EXCEPTION
    WHEN OTHERS THEN
        RETURN false;
END;
$$ LANGUAGE plpgsql;

-- Función para verificar si producto está en favoritos
CREATE OR REPLACE FUNCTION is_favorite(user_id INTEGER, product_id INTEGER, product_type VARCHAR DEFAULT 'catalog')
RETURNS BOOLEAN AS $$
BEGIN
    RETURN EXISTS (
        SELECT 1 FROM product_favorites 
        WHERE user_id = is_favorite.user_id 
        AND product_id = is_favorite.product_id 
        AND product_type = is_favorite.product_type
    );
END;
$$ LANGUAGE plpgsql;

-- Función para obtener productos favoritos de usuario
CREATE OR REPLACE FUNCTION get_user_favorites(user_id INTEGER)
RETURNS TABLE(
    id INTEGER,
    product_id INTEGER,
    product_type VARCHAR,
    created_at TIMESTAMP
) AS $$
BEGIN
    RETURN QUERY
    SELECT 
        pf.id,
        pf.product_id,
        pf.product_type,
        pf.created_at
    FROM product_favorites pf
    WHERE pf.user_id = get_user_favorites.user_id
    ORDER BY pf.created_at DESC;
END;
$$ LANGUAGE plpgsql;

-- Función para contar favoritos de usuario
CREATE OR REPLACE FUNCTION count_user_favorites(user_id INTEGER)
RETURNS INTEGER AS $$
DECLARE
    favorite_count INTEGER;
BEGIN
    SELECT COUNT(*) INTO favorite_count 
    FROM product_favorites 
    WHERE user_id = count_user_favorites.user_id;
    RETURN favorite_count;
END;
$$ LANGUAGE plpgsql;

-- Comentarios
COMMENT ON TABLE product_favorites IS 'Productos favoritos de usuarios';
COMMENT ON FUNCTION add_to_favorites(INTEGER, INTEGER, VARCHAR) IS 'Agrega un producto a favoritos';
COMMENT ON FUNCTION remove_from_favorites(INTEGER, INTEGER, VARCHAR) IS 'Elimina un producto de favoritos';
COMMENT ON FUNCTION is_favorite(INTEGER, INTEGER, VARCHAR) IS 'Verifica si un producto está en favoritos';
COMMENT ON FUNCTION get_user_favorites(INTEGER) IS 'Obtiene todos los productos favoritos de un usuario';
COMMENT ON FUNCTION count_user_favorites(INTEGER) IS 'Cuenta los productos favoritos de un usuario';
