-- Sistema de Persistencia de Carrito
-- Tabla para guardar carritos en base de datos

CREATE TABLE IF NOT EXISTS shopping_carts (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    session_id VARCHAR(255),
    product_id INTEGER NOT NULL,
    product_type VARCHAR(20) DEFAULT 'catalog', -- catalog, marketplace
    quantity INTEGER NOT NULL DEFAULT 1,
    price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_shopping_carts_user ON shopping_carts(user_id);
CREATE INDEX IF NOT EXISTS idx_shopping_carts_session ON shopping_carts(session_id);
CREATE INDEX IF NOT EXISTS idx_shopping_carts_product ON shopping_carts(product_id, product_type);

-- Función para obtener o crear carrito
CREATE OR REPLACE FUNCTION get_or_create_cart(user_id INTEGER, session_id VARCHAR DEFAULT NULL)
RETURNS VOID AS $$
BEGIN
    -- Esta función es un placeholder para lógica adicional si es necesaria
    -- La tabla shopping_carts maneja múltiples items por usuario/sesión
END;
$$ LANGUAGE plpgsql;

-- Función para agregar item al carrito
CREATE OR REPLACE FUNCTION add_to_cart(user_id INTEGER, session_id VARCHAR, product_id INTEGER, product_type VARCHAR, quantity INTEGER, price DECIMAL)
RETURNS INTEGER AS $$
DECLARE
    cart_id INTEGER;
BEGIN
    -- Verificar si el item ya existe en el carrito
    IF user_id IS NOT NULL THEN
        SELECT id INTO cart_id FROM shopping_carts 
        WHERE user_id = add_to_cart.user_id 
        AND product_id = add_to_cart.product_id 
        AND product_type = add_to_cart.product_type
        LIMIT 1;
    ELSE
        SELECT id INTO cart_id FROM shopping_carts 
        WHERE session_id = add_to_cart.session_id 
        AND product_id = add_to_cart.product_id 
        AND product_type = add_to_cart.product_type
        LIMIT 1;
    END IF;
    
    IF cart_id IS NOT NULL THEN
        -- Actualizar cantidad
        UPDATE shopping_carts 
        SET quantity = quantity + add_to_cart.quantity,
            updated_at = NOW()
        WHERE id = cart_id;
        RETURN cart_id;
    ELSE
        -- Insertar nuevo item
        INSERT INTO shopping_carts (user_id, session_id, product_id, product_type, quantity, price)
        VALUES (user_id, session_id, product_id, product_type, quantity, price)
        RETURNING id INTO cart_id;
        RETURN cart_id;
    END IF;
END;
$$ LANGUAGE plpgsql;

-- Función para actualizar cantidad de item
CREATE OR REPLACE FUNCTION update_cart_item(cart_id INTEGER, new_quantity INTEGER)
RETURNS BOOLEAN AS $$
BEGIN
    IF new_quantity <= 0 THEN
        DELETE FROM shopping_carts WHERE id = cart_id;
        RETURN true;
    ELSE
        UPDATE shopping_carts 
        SET quantity = new_quantity, updated_at = NOW()
        WHERE id = cart_id;
        RETURN true;
    END IF;
    RETURN false;
END;
$$ LANGUAGE plpgsql;

-- Función para eliminar item del carrito
CREATE OR REPLACE FUNCTION remove_cart_item(cart_id INTEGER)
RETURNS BOOLEAN AS $$
BEGIN
    DELETE FROM shopping_carts WHERE id = cart_id;
    RETURN true;
EXCEPTION
    WHEN OTHERS THEN
        RETURN false;
END;
$$ LANGUAGE plpgsql;

-- Función para obtener items del carrito
CREATE OR REPLACE FUNCTION get_cart_items(user_id INTEGER, session_id VARCHAR DEFAULT NULL)
RETURNS TABLE(
    id INTEGER,
    product_id INTEGER,
    product_type VARCHAR,
    quantity INTEGER,
    price DECIMAL,
    total DECIMAL,
    created_at TIMESTAMP
) AS $$
BEGIN
    RETURN QUERY
    SELECT 
        sc.id,
        sc.product_id,
        sc.product_type,
        sc.quantity,
        sc.price,
        (sc.quantity * sc.price) AS total,
        sc.created_at
    FROM shopping_carts sc
    WHERE (user_id IS NOT NULL AND sc.user_id = get_cart_items.user_id)
       OR (session_id IS NOT NULL AND sc.session_id = get_cart_items.session_id)
    ORDER BY sc.created_at DESC;
END;
$$ LANGUAGE plpgsql;

-- Función para limpiar carrito
CREATE OR REPLACE FUNCTION clear_cart(user_id INTEGER, session_id VARCHAR DEFAULT NULL)
RETURNS INTEGER AS $$
DECLARE
    deleted_count INTEGER;
BEGIN
    IF user_id IS NOT NULL THEN
        DELETE FROM shopping_carts WHERE user_id = clear_cart.user_id;
    ELSE
        DELETE FROM shopping_carts WHERE session_id = clear_cart.session_id;
    END IF;
    
    GET DIAGNOSTICS deleted_count = ROW_COUNT;
    RETURN deleted_count;
END;
$$ LANGUAGE plpgsql;

-- Función para limpiar carritos antiguos (más de 30 días)
CREATE OR REPLACE FUNCTION cleanup_old_carts()
RETURNS INTEGER AS $$
DECLARE
    deleted_count INTEGER;
BEGIN
    DELETE FROM shopping_carts 
    WHERE created_at < NOW() - INTERVAL '30 days';
    
    GET DIAGNOSTICS deleted_count = ROW_COUNT;
    RETURN deleted_count;
END;
$$ LANGUAGE plpgsql;

-- Comentarios
COMMENT ON TABLE shopping_carts IS 'Items del carrito de compras persistentes';
COMMENT ON FUNCTION add_to_cart(INTEGER, VARCHAR, INTEGER, VARCHAR, INTEGER, DECIMAL) IS 'Agrega o actualiza un item en el carrito';
COMMENT ON FUNCTION update_cart_item(INTEGER, INTEGER) IS 'Actualiza la cantidad de un item del carrito';
COMMENT ON FUNCTION remove_cart_item(INTEGER) IS 'Elimina un item del carrito';
COMMENT ON FUNCTION get_cart_items(INTEGER, VARCHAR) IS 'Obtiene todos los items del carrito de un usuario o sesión';
COMMENT ON FUNCTION clear_cart(INTEGER, VARCHAR) IS 'Limpia todos los items del carrito';
COMMENT ON FUNCTION cleanup_old_carts() IS 'Limpia carritos antiguos (más de 30 días)';
