-- Migración: Índices de rendimiento para Truper Platform
-- Fecha: 20 de Junio, 2026
-- Descripción: Agrega índices para mejorar el rendimiento de consultas frecuentes

-- Índices para tabla products
CREATE INDEX IF NOT EXISTS idx_products_sku ON products(sku);
CREATE INDEX IF NOT EXISTS idx_products_name ON products(name);
CREATE INDEX IF NOT EXISTS idx_products_category ON products(category);
CREATE INDEX IF NOT EXISTS idx_products_active ON products(COALESCE(active, is_active));
CREATE INDEX IF NOT EXISTS idx_products_barcode ON products(barcode);

-- Índices para tabla users
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_code ON users(user_code);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);

-- Índices para tabla orders
CREATE INDEX IF NOT EXISTS idx_orders_client_id ON orders(client_id);
CREATE INDEX IF NOT EXISTS idx_orders_created_at ON orders(created_at);
CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status);
CREATE INDEX IF NOT EXISTS idx_orders_order_number ON orders(order_number);

-- Índices para tabla order_items
CREATE INDEX IF NOT EXISTS idx_order_items_order_id ON order_items(order_id);
CREATE INDEX IF NOT EXISTS idx_order_items_product_id ON order_items(product_id);

-- Índices para tabla payments
CREATE INDEX IF NOT EXISTS idx_payments_order_id ON payments(order_id);
CREATE INDEX IF NOT EXISTS idx_payments_created_at ON payments(created_at);

-- Índices compuestos para consultas frecuentes
CREATE INDEX IF NOT EXISTS idx_products_category_active ON products(category, COALESCE(active, is_active));
CREATE INDEX IF NOT EXISTS idx_orders_client_created ON orders(client_id, created_at DESC);

-- Nota: Estos índices mejorarán significativamente el rendimiento de las consultas más frecuentes
-- sin afectar negativamente las operaciones de escritura (INSERT/UPDATE/DELETE)
