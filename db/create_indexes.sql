-- Índices de optimización para base de datos Truper
-- Ejecutar: psql -U truper_admin -d truper_platform -f create_indexes.sql

-- Índices para tabla products
CREATE INDEX IF NOT EXISTS idx_products_sku ON products(sku);
CREATE INDEX IF NOT EXISTS idx_products_category ON products(category);
CREATE INDEX IF NOT EXISTS idx_products_is_active ON products(is_active);
CREATE INDEX IF NOT EXISTS idx_products_unit_price ON products(unit_price);
CREATE INDEX IF NOT EXISTS idx_products_stock_quantity ON products(stock_quantity);
CREATE INDEX IF NOT EXISTS idx_products_name ON products(name);

-- Índices para tabla marketplace_ce_products
CREATE INDEX IF NOT EXISTS idx_marketplace_ce_sku ON marketplace_ce_products(sku);
CREATE INDEX IF NOT EXISTS idx_marketplace_ce_category ON marketplace_ce_products(category);
CREATE INDEX IF NOT EXISTS idx_marketplace_ce_is_active ON marketplace_ce_products(is_active);
CREATE INDEX IF NOT EXISTS idx_marketplace_ce_unit_price ON marketplace_ce_products(unit_price);
CREATE INDEX IF NOT EXISTS idx_marketplace_ce_name ON marketplace_ce_products(name);

-- Índices para tabla users
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_users_user_code ON users(user_code);
CREATE INDEX IF NOT EXISTS idx_users_is_active ON users(is_active);

-- Índices para tabla orders
CREATE INDEX IF NOT EXISTS idx_orders_client_id ON orders(client_id);
CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status);
CREATE INDEX IF NOT EXISTS idx_orders_payment_status ON orders(payment_status);
CREATE INDEX IF NOT EXISTS idx_orders_order_number ON orders(order_number);
CREATE INDEX IF NOT EXISTS idx_orders_created_at ON orders(created_at);

-- Índices para tabla order_items
CREATE INDEX IF NOT EXISTS idx_order_items_order_id ON order_items(order_id);
CREATE INDEX IF NOT EXISTS idx_order_items_product_id ON order_items(product_id);

-- Índices para tabla tasks
CREATE INDEX IF NOT EXISTS idx_tasks_assigned_to ON tasks(assigned_to);
CREATE INDEX IF NOT EXISTS idx_tasks_status ON tasks(status);
CREATE INDEX IF NOT EXISTS idx_tasks_due_date ON tasks(due_date);

-- Índices para tabla action_logs
CREATE INDEX IF NOT EXISTS idx_action_logs_user_id ON action_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_action_logs_timestamp ON action_logs(timestamp);
CREATE INDEX IF NOT EXISTS idx_action_logs_action ON action_logs(action);

-- Índices para tabla security_logs
CREATE INDEX IF NOT EXISTS idx_security_logs_ip_address ON security_logs(ip_address);
CREATE INDEX IF NOT EXISTS idx_security_logs_event_type ON security_logs(event_type);
CREATE INDEX IF NOT EXISTS idx_security_logs_created_at ON security_logs(created_at);

-- Índices compuestos para consultas frecuentes
CREATE INDEX IF NOT EXISTS idx_products_category_active ON products(category, is_active);
CREATE INDEX IF NOT EXISTS idx_marketplace_ce_category_active ON marketplace_ce_products(category, is_active);
CREATE INDEX IF NOT EXISTS idx_orders_status_created ON orders(status, created_at);

-- Comentario
COMMENT ON INDEX idx_products_sku IS 'Índice para búsquedas rápidas por SKU';
COMMENT ON INDEX idx_marketplace_ce_sku IS 'Índice para búsquedas rápidas por SKU en Marketplace CE';
COMMENT ON INDEX idx_users_email IS 'Índice para login por email';
