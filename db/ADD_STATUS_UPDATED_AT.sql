-- Agregar campo status_updated_at a la tabla orders para tracking de cambios de estado
-- Migración para mejoras del sistema de pedidos

-- Agregar campo status_updated_at
ALTER TABLE orders ADD COLUMN IF NOT EXISTS status_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Crear índice para consultas de pedidos por cliente y fecha
CREATE INDEX IF NOT EXISTS idx_orders_client_created ON orders(client_id, created_at);

-- Crear índice para tracking de cambios de estado
CREATE INDEX IF NOT EXISTS idx_orders_status_updated ON orders(status_updated_at);

-- Comentario sobre la nueva columna
COMMENT ON COLUMN orders.status_updated_at IS 'Timestamp del último cambio de estado del pedido';
