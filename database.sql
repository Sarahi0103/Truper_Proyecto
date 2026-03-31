-- Crear Base de Datos Truper Platform
-- PostgreSQL

-- Crear extensiones necesarias
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";

-- Tabla de Usuarios
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'client', -- admin, client, employee
    phone VARCHAR(20),
    address TEXT,
    birthdate DATE,
    loyalty_points INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT true,
    is_verified BOOLEAN DEFAULT false,
    last_login TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de Clientes (Información adicional)
CREATE TABLE IF NOT EXISTS clients (
    id SERIAL PRIMARY KEY,
    user_id INTEGER UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    company_name VARCHAR(255),
    tax_id VARCHAR(50),
    is_wholesale BOOLEAN DEFAULT false,
    credit_limit DECIMAL(12, 2) DEFAULT 0,
    credit_available DECIMAL(12, 2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de Productos
CREATE TABLE IF NOT EXISTS products (
    id SERIAL PRIMARY KEY,
    sku VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    technical_specs TEXT,
    image_url TEXT,
    variants_json TEXT,
    category VARCHAR(100),
    unit_price DECIMAL(10, 2) NOT NULL,
    barcode VARCHAR(100) UNIQUE,
    stock_quantity INTEGER DEFAULT 0,
    reorder_level INTEGER DEFAULT 10,
    supplier_id INTEGER,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de Órdenes/Pedidos
CREATE TABLE IF NOT EXISTS orders (
    id SERIAL PRIMARY KEY,
    client_id INTEGER NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    total_amount DECIMAL(12, 2) NOT NULL,
    payment_status VARCHAR(20) DEFAULT 'pending', -- pending, partial, paid
    payment_amount DECIMAL(12, 2) DEFAULT 0,
    balance DECIMAL(12, 2) NOT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delivery_date DATE,
    notes TEXT,
    is_wholesale BOOLEAN DEFAULT false,
    status VARCHAR(20) DEFAULT 'pending', -- pending, confirmed, processing, shipped, delivered, cancelled
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de Detalles de Órdenes
CREATE TABLE IF NOT EXISTS order_items (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    product_id INTEGER NOT NULL REFERENCES products(id),
    quantity INTEGER NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(12, 2) NOT NULL,
    discount_percentage DECIMAL(5, 2) DEFAULT 0,
    discount_amount DECIMAL(12, 2) DEFAULT 0,
    line_total DECIMAL(12, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de Pagos
CREATE TABLE IF NOT EXISTS payments (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    amount DECIMAL(12, 2) NOT NULL,
    payment_method VARCHAR(50), -- cash, card, transfer, check
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reference_number VARCHAR(100),
    notes TEXT,
    processed_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de Tareas para Empleados
CREATE TABLE IF NOT EXISTS tasks (
    id SERIAL PRIMARY KEY,
    task_number VARCHAR(50) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    assigned_to INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    assigned_by INTEGER NOT NULL REFERENCES users(id),
    priority VARCHAR(20) DEFAULT 'medium', -- low, medium, high, urgent
    status VARCHAR(20) DEFAULT 'pending', -- pending, in_progress, completed, cancelled
    due_date DATE NOT NULL,
    completion_date TIMESTAMP,
    estimated_hours DECIMAL(5, 2),
    actual_hours DECIMAL(5, 2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de Estadísticas de Compras
CREATE TABLE IF NOT EXISTS purchase_statistics (
    id SERIAL PRIMARY KEY,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    month INTEGER NOT NULL,
    year INTEGER NOT NULL,
    total_quantity INTEGER NOT NULL,
    total_amount DECIMAL(12, 2) NOT NULL,
    season VARCHAR(50), -- invierno, primavera, verano, otoño
    weather_condition VARCHAR(100),
    special_event VARCHAR(255),
    prediction_score DECIMAL(5, 2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de Mayoristas
CREATE TABLE IF NOT EXISTS wholesalers (
    id SERIAL PRIMARY KEY,
    client_id INTEGER NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
    business_type VARCHAR(100),
    min_order_quantity INTEGER DEFAULT 50,
    discount_percentage DECIMAL(5, 2) DEFAULT 15,
    payment_terms VARCHAR(100),
    is_approved BOOLEAN DEFAULT false,
    requested_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_date TIMESTAMP,
    approved_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de Promociones e Información de Cumpleaños
CREATE TABLE IF NOT EXISTS promotions (
    id SERIAL PRIMARY KEY,
    client_id INTEGER NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
    promotion_type VARCHAR(50), -- birthday_bonus, points_redemption, seasonal
    discount_amount DECIMAL(12, 2),
    discount_percentage DECIMAL(5, 2),
    expiry_date DATE,
    is_used BOOLEAN DEFAULT false,
    used_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de Historial de Códigos de Barras
CREATE TABLE IF NOT EXISTS barcode_registry (
    id SERIAL PRIMARY KEY,
    barcode VARCHAR(100) UNIQUE NOT NULL,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    registered_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified BOOLEAN DEFAULT true,
    notes TEXT
);

-- Tabla de Registros de Auditoría/Acciones
CREATE TABLE IF NOT EXISTS action_logs (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de Predicciones del Sistema (Machine Learning)
CREATE TABLE IF NOT EXISTS ai_predictions (
    id SERIAL PRIMARY KEY,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    prediction_date DATE,
    predicted_demand INTEGER,
    confidence_score DECIMAL(5, 2),
    actual_demand INTEGER,
    accuracy DECIMAL(5, 2),
    season VARCHAR(50),
    factors TEXT, -- JSON con factores considerados
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de cajón de dinero (POS)
CREATE TABLE IF NOT EXISTS cash_drawer_sessions (
    id SERIAL PRIMARY KEY,
    opened_by INTEGER NOT NULL REFERENCES users(id),
    opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    opening_amount DECIMAL(12, 2) NOT NULL DEFAULT 0,
    closed_by INTEGER REFERENCES users(id),
    closed_at TIMESTAMP,
    closing_amount DECIMAL(12, 2),
    expected_amount DECIMAL(12, 2),
    difference_amount DECIMAL(12, 2),
    status VARCHAR(20) NOT NULL DEFAULT 'open', -- open, closed
    notes TEXT
);

CREATE TABLE IF NOT EXISTS cash_drawer_movements (
    id SERIAL PRIMARY KEY,
    session_id INTEGER NOT NULL REFERENCES cash_drawer_sessions(id) ON DELETE CASCADE,
    movement_type VARCHAR(20) NOT NULL, -- in, out, sale
    amount DECIMAL(12, 2) NOT NULL,
    description TEXT,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de calendario logistico de proveedores
CREATE TABLE IF NOT EXISTS supplier_calendar (
    id SERIAL PRIMARY KEY,
    supplier_name VARCHAR(180) NOT NULL,
    visit_datetime TIMESTAMP NOT NULL,
    notes TEXT,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de ordenes a proveedores
CREATE TABLE IF NOT EXISTS supplier_orders (
    id SERIAL PRIMARY KEY,
    folio VARCHAR(50) UNIQUE NOT NULL,
    supplier_name VARCHAR(180) NOT NULL,
    expected_date DATE NOT NULL,
    items_json TEXT NOT NULL,
    total_estimated DECIMAL(12, 2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'pending',
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla historica de transacciones
CREATE TABLE IF NOT EXISTS transaction_history (
    id SERIAL PRIMARY KEY,
    transaction_type VARCHAR(40) NOT NULL,
    reference_folio VARCHAR(80) NOT NULL,
    data_json TEXT,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices para optimización
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_clients_user_id ON clients(user_id);
CREATE INDEX idx_products_sku ON products(sku);
CREATE INDEX idx_products_barcode ON products(barcode);
CREATE INDEX idx_products_category ON products(category);
CREATE INDEX idx_orders_client_id ON orders(client_id);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_payment_status ON orders(payment_status);
CREATE INDEX idx_order_items_order_id ON order_items(order_id);
CREATE INDEX idx_order_items_product_id ON order_items(product_id);
CREATE INDEX idx_payments_order_id ON payments(order_id);
CREATE INDEX idx_tasks_assigned_to ON tasks(assigned_to);
CREATE INDEX idx_tasks_status ON tasks(status);
CREATE INDEX idx_purchase_stats_product ON purchase_statistics(product_id);
CREATE INDEX idx_purchase_stats_date ON purchase_statistics(month, year);
CREATE INDEX idx_action_logs_user_id ON action_logs(user_id);
CREATE INDEX idx_action_logs_timestamp ON action_logs(timestamp);
CREATE INDEX idx_supplier_calendar_visit ON supplier_calendar(visit_datetime);
CREATE INDEX idx_supplier_orders_created ON supplier_orders(created_at);
CREATE INDEX idx_transaction_history_created ON transaction_history(created_at);

-- Crear usuario administrador por defecto
INSERT INTO users (email, password_hash, first_name, last_name, role, is_active, is_verified)
VALUES (
    'admin@truper.com',
    '$2y$12$GQvLh9xH4Hs6ZL2J5V8N8uY7K6P2M3L5N9O1Q9R7S5T3U1V9W7X5Y3', -- Contraseña: Admin123!
    'Administrador',
    'Truper',
    'admin',
    true,
    true
) ON CONFLICT DO NOTHING;

-- Productos de ejemplo para catálogo público
INSERT INTO products (sku, name, description, technical_specs, variants_json, image_url, category, unit_price, barcode, stock_quantity, reorder_level, is_active)
VALUES
('TRUP-001', 'Taladro Percutor 1/2" 750W', 'Taladro de alto rendimiento para concreto y metal.', 'Potencia 750W | Velocidad variable | Mandril 1/2"', '["Modelo Compacto", "Modelo Industrial"]', 'images/products/default-product.svg', 'Herramientas Eléctricas', 1899.00, '750624060001', 35, 10, true),
('TRUP-002', 'Juego de Llaves Combinadas 12 pzas', 'Juego profesional de llaves de acero cromo vanadio.', '12 piezas | Acero Cr-V | Acabado anticorrosivo', '["6-17 mm", "8-19 mm"]', 'images/products/default-product.svg', 'Herramientas Manuales', 799.00, '750624060002', 50, 12, true),
('TRUP-003', 'Esmeriladora Angular 4-1/2" 900W', 'Corte y desbaste con control y seguridad.', '900W | Disco 4-1/2" | Guarda ajustable', '["Con maletin", "Sin maletin"]', 'images/products/default-product.svg', 'Herramientas Eléctricas', 1299.00, '750624060003', 28, 8, true),
('TRUP-004', 'Caja de Herramientas 19" Reforzada', 'Caja resistente con compartimentos organizadores.', 'Polimero reforzado | 19 pulgadas | Cierres metalicos', '["Roja", "Negra"]', 'images/products/default-product.svg', 'Almacenamiento', 499.00, '750624060004', 42, 10, true),
('TRUP-005', 'Martillo Uña 16 oz Mango Fibra', 'Martillo balanceado para uso diario en obra.', '16 oz | Mango de fibra | Cabeza templada', '["16 oz", "20 oz"]', 'images/products/default-product.svg', 'Herramientas Manuales', 249.00, '750624060005', 95, 20, true),
('TRUP-006', 'Cinta Métrica 8m Uso Rudo', 'Cinta con recubrimiento anti-impacto y freno rápido.', '8 metros | Carcasa ABS | Gancho magnetico', '["5 m", "8 m"]', 'images/products/default-product.svg', 'Medición', 179.00, '750624060006', 120, 25, true),
('TRUP-007', 'Pistola para Pintar HVLP', 'Acabado uniforme para madera y metal.', 'Boquilla 1.4 mm | Deposito 600 ml | Bajo consumo', '["Boquilla 1.4", "Boquilla 1.8"]', 'images/products/default-product.svg', 'Pintura', 999.00, '750624060007', 22, 8, true),
('TRUP-008', 'Compresor de Aire 24L 2HP', 'Compresor portátil para taller y construcción.', 'Tanque 24L | Motor 2HP | 120 PSI', '["24L", "50L"]', 'images/products/default-product.svg', 'Equipo Industrial', 3599.00, '750624060008', 15, 5, true),
('TRUP-009', 'Guantes de Trabajo Anticorte', 'Protección de manos para manejo de materiales.', 'Nivel de corte C | Palma antiderrapante', '["Talla M", "Talla L", "Talla XL"]', 'images/products/default-product.svg', 'Seguridad', 129.00, '750624060009', 160, 40, true),
('TRUP-010', 'Carretilla 5 ft3 Reforzada', 'Carretilla de alta capacidad para obra pesada.', 'Capacidad 5 ft3 | Bastidor de acero', '["Llanta solida", "Llanta neumatica"]', 'images/products/default-product.svg', 'Construcción', 1499.00, '750624060010', 18, 6, true)
ON CONFLICT (sku) DO UPDATE SET
description = EXCLUDED.description,
technical_specs = EXCLUDED.technical_specs,
variants_json = EXCLUDED.variants_json,
image_url = EXCLUDED.image_url,
category = EXCLUDED.category,
unit_price = EXCLUDED.unit_price,
stock_quantity = EXCLUDED.stock_quantity,
reorder_level = EXCLUDED.reorder_level,
is_active = EXCLUDED.is_active;

-- Crear tabla de configuración del sistema
CREATE TABLE IF NOT EXISTS system_config (
    id SERIAL PRIMARY KEY,
    config_key VARCHAR(100) UNIQUE NOT NULL,
    config_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insertar configuraciones por defecto
INSERT INTO system_config (config_key, config_value) VALUES
('company_name', 'Truper'),
('support_email', 'soporte@truper.com'),
('phone', '+56 2 1234 5678'),
('address', 'Dirección de Truper'),
('currency', 'CLP'),
('timezone', 'America/Santiago')
ON CONFLICT DO NOTHING;
