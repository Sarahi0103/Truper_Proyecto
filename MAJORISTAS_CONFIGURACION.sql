/*================================
  CONFIGURACIÓN PARA MAYOZRISTAS
  ACTUALIZAR EN PANEL DE ADMINISTRADOR
  ================================*/

-- Tabla para solicitudes de mayoreo
CREATE TABLE if NOT EXISTS wholesaler_requests (
    id SERIAL PRIMARY KEY,
    client_id INTEGER NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
    business_type VARCHAR(100),
    estimated_monthly_volume INTEGER,
    business_references TEXT,
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'pending', -- pending, approved, rejected
    reviewed_by INTEGER REFERENCES users(id),
    review_date TIMESTAMP,
    notes TEXT
);

-- Tabla de Historiales de Compra Mayoreo
CREATE TABLE IF NOT EXISTS wholesale_transactions (
    id SERIAL PRIMARY KEY,
    wholesale_id INTEGER NOT NULL REFERENCES wholesalers(id) ON DELETE CASCADE,
    order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    total_volume INTEGER,
    discount_applied DECIMAL(5, 2),
    amount_saved DECIMAL(12, 2),
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Configurar descuentos escalonados para mayoristas
-- Cantidad: 50-99 = 10%, 100-199 = 15%, 200-499 = 20%, 500+ = 25%

-- Insertar tabla de descuentos
CREATE TABLE IF NOT EXISTS discount_tiers (
    id SERIAL PRIMARY KEY,
    min_quantity INTEGER NOT NULL,
    max_quantity INTEGER,
    discount_percentage DECIMAL(5, 2) NOT NULL,
    applicable_to VARCHAR(20) DEFAULT 'wholesale', -- wholesale, retail, both
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO discount_tiers (min_quantity, max_quantity, discount_percentage, applicable_to) VALUES
(20, 49, 5, 'retail'),
(50, 99, 10, 'both'),
(100, 199, 15, 'both'),
(200, 499, 20, 'wholesale'),
(500, NULL, 25, 'wholesale');
