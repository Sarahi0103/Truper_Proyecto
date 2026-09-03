-- Tabla de notificaciones
CREATE TABLE IF NOT EXISTS notifications (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL, -- order_status, stock_alert, promotion, system
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    data JSONB, -- Datos adicionales (order_id, product_id, etc.)
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices para mejor rendimiento
CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_notifications_read ON notifications(is_read);
CREATE INDEX IF NOT EXISTS idx_notifications_type ON notifications(type);
CREATE INDEX IF NOT EXISTS idx_notifications_created ON notifications(created_at DESC);

-- Tabla de suscripciones push (para web push notifications)
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    endpoint VARCHAR(500) NOT NULL,
    p256dh_key TEXT NOT NULL,
    auth_key TEXT NOT NULL,
    user_agent VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, endpoint)
);

-- Índices para push subscriptions
CREATE INDEX IF NOT EXISTS idx_push_subscriptions_user ON push_subscriptions(user_id);
CREATE INDEX IF NOT EXISTS idx_push_subscriptions_active ON push_subscriptions(is_active);

-- Función para crear notificación
CREATE OR REPLACE FUNCTION create_notification(
    user_id INTEGER,
    notification_type VARCHAR(50),
    notification_title VARCHAR(255),
    notification_message TEXT,
    notification_data JSONB DEFAULT NULL
)
RETURNS INTEGER AS $$
DECLARE
    notification_id INTEGER;
BEGIN
    INSERT INTO notifications (user_id, type, title, message, data)
    VALUES (user_id, notification_type, notification_title, notification_message, notification_data)
    RETURNING id INTO notification_id;
    
    RETURN notification_id;
END;
$$ LANGUAGE plpgsql;

-- Función para marcar notificación como leída
CREATE OR REPLACE FUNCTION mark_notification_read(notification_id INTEGER, user_id INTEGER)
RETURNS BOOLEAN AS $$
BEGIN
    UPDATE notifications
    SET is_read = TRUE,
        read_at = CURRENT_TIMESTAMP
    WHERE id = notification_id AND user_id = user_id;
    
    RETURN FOUND;
END;
$$ LANGUAGE plpgsql;

-- Función para marcar todas las notificaciones de un usuario como leídas
CREATE OR REPLACE FUNCTION mark_all_notifications_read(user_id INTEGER)
RETURNS INTEGER AS $$
DECLARE
    marked_count INTEGER;
BEGIN
    UPDATE notifications
    SET is_read = TRUE,
        read_at = CURRENT_TIMESTAMP
    WHERE user_id = user_id AND is_read = FALSE;
    
    GET DIAGNOSTICS marked_count = ROW_COUNT;
    
    RETURN marked_count;
END;
$$ LANGUAGE plpgsql;

-- Función para obtener notificaciones no leídas de un usuario
CREATE OR REPLACE FUNCTION get_unread_notifications(user_id INTEGER, limit_count INTEGER DEFAULT 20)
RETURNS TABLE (
    id INTEGER,
    type VARCHAR(50),
    title VARCHAR(255),
    message TEXT,
    data JSONB,
    created_at TIMESTAMP
) AS $$
BEGIN
    RETURN QUERY
    SELECT n.id, n.type, n.title, n.message, n.data, n.created_at
    FROM notifications n
    WHERE n.user_id = user_id AND n.is_read = FALSE
    ORDER BY n.created_at DESC
    LIMIT limit_count;
END;
$$ LANGUAGE plpgsql;

-- Función para obtener notificaciones de un usuario con paginación
CREATE OR REPLACE FUNCTION get_user_notifications(
    user_id INTEGER,
    offset_count INTEGER DEFAULT 0,
    limit_count INTEGER DEFAULT 50
)
RETURNS TABLE (
    id INTEGER,
    type VARCHAR(50),
    title VARCHAR(255),
    message TEXT,
    data JSONB,
    is_read BOOLEAN,
    read_at TIMESTAMP,
    created_at TIMESTAMP
) AS $$
BEGIN
    RETURN QUERY
    SELECT n.id, n.type, n.title, n.message, n.data, n.is_read, n.read_at, n.created_at
    FROM notifications n
    WHERE n.user_id = user_id
    ORDER BY n.created_at DESC
    LIMIT limit_count OFFSET offset_count;
END;
$$ LANGUAGE plpgsql;

-- Función para obtener conteo de notificaciones no leídas
CREATE OR REPLACE FUNCTION get_unread_count(user_id INTEGER)
RETURNS INTEGER AS $$
DECLARE
    unread_count INTEGER;
BEGIN
    SELECT COUNT(*) INTO unread_count
    FROM notifications
    WHERE user_id = user_id AND is_read = FALSE;
    
    RETURN unread_count;
END;
$$ LANGUAGE plpgsql;

-- Función para crear notificación masiva (para múltiples usuarios)
CREATE OR REPLACE FUNCTION create_bulk_notifications(
    user_ids INTEGER[],
    notification_type VARCHAR(50),
    notification_title VARCHAR(255),
    notification_message TEXT,
    notification_data JSONB DEFAULT NULL
)
RETURNS INTEGER AS $$
DECLARE
    created_count INTEGER;
    user_id INTEGER;
BEGIN
    created_count := 0;
    
    FOREACH user_id IN ARRAY user_ids
    LOOP
        INSERT INTO notifications (user_id, type, title, message, data)
        VALUES (user_id, notification_type, notification_title, notification_message, notification_data);
        
        created_count := created_count + 1;
    END LOOP;
    
    RETURN created_count;
END;
$$ LANGUAGE plpgsql;

-- Función para limpiar notificaciones antiguas (más de 90 días)
CREATE OR REPLACE FUNCTION cleanup_old_notifications()
RETURNS INTEGER AS $$
DECLARE
    deleted_count INTEGER;
BEGIN
    DELETE FROM notifications
    WHERE created_at < CURRENT_TIMESTAMP - INTERVAL '90 days' AND is_read = TRUE;
    
    GET DIAGNOSTICS deleted_count = ROW_COUNT;
    
    RETURN deleted_count;
END;
$$ LANGUAGE plpgsql;
