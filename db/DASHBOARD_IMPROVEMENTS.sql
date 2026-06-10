-- Mejoras para el Dashboard
-- Agregar índices para optimizar consultas del dashboard

-- Índices para tabla orders
CREATE INDEX IF NOT EXISTS idx_orders_client_created ON orders(client_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_orders_status_created ON orders(status, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_orders_date_range ON orders(DATE(created_at));

-- Índices para tabla order_items
CREATE INDEX IF NOT EXISTS idx_order_items_product ON order_items(product_id);
CREATE INDEX IF NOT EXISTS idx_order_items_order ON order_items(order_id);

-- Índices para tabla products
CREATE INDEX IF NOT EXISTS idx_products_category ON products(category);
CREATE INDEX IF NOT EXISTS idx_products_stock ON products(stock_quantity);
CREATE INDEX IF NOT EXISTS idx_products_active ON products(is_active);

-- Índices para tabla users
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_users_active ON users(is_active);

-- Índices para tabla clients
CREATE INDEX IF NOT EXISTS idx_clients_user ON clients(user_id);

-- Tabla para configuración de widgets personalizados del dashboard
CREATE TABLE IF NOT EXISTS dashboard_widget_config (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    widget_type VARCHAR(50) NOT NULL,
    widget_order INTEGER NOT NULL DEFAULT 0,
    is_visible BOOLEAN DEFAULT true,
    config JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, widget_type)
);

CREATE INDEX IF NOT EXISTS idx_dashboard_widget_config_user ON dashboard_widget_config(user_id);
CREATE INDEX IF NOT EXISTS idx_dashboard_widget_config_order ON dashboard_widget_config(user_id, widget_order);

-- Comentarios
COMMENT ON TABLE dashboard_widget_config IS 'Configuración personalizada de widgets del dashboard por usuario';
COMMENT ON COLUMN dashboard_widget_config.widget_type IS 'Tipo de widget (recent_orders, sales_chart, top_products, etc.)';
COMMENT ON COLUMN dashboard_widget_config.widget_order IS 'Orden de visualización del widget';
COMMENT ON COLUMN dashboard_widget_config.config IS 'Configuración adicional del widget en formato JSON';
