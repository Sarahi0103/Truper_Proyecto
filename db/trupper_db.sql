-- ============================================
-- BASE DE DATOS Truper - SCRIPT LEGADO (PostgreSQL)
-- ============================================

CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    birthday DATE,
    role VARCHAR(20) NOT NULL DEFAULT 'client' CHECK (role IN ('admin', 'employee', 'client')),
    points INT DEFAULT 0,
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    sku VARCHAR(100) UNIQUE NOT NULL,
    barcode VARCHAR(100),
    description TEXT,
    category VARCHAR(100),
    unit VARCHAR(50),
    cost_price DECIMAL(10, 2),
    sell_price DECIMAL(10, 2) NOT NULL,
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS orders (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    total DECIMAL(10, 2) NOT NULL,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'processing', 'completed', 'cancelled')),
    payment_status VARCHAR(20) DEFAULT 'pending' CHECK (payment_status IN ('pending', 'partial', 'paid')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS order_items (
    id SERIAL PRIMARY KEY,
    order_id INT NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    product_id INT NOT NULL REFERENCES products(id) ON DELETE RESTRICT,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS payment_tracking (
    id SERIAL PRIMARY KEY,
    order_id INT NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    amount_paid DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50),
    payment_date TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS payments (
    id SERIAL PRIMARY KEY,
    order_id INT NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50),
    reference_number VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tasks (
    id SERIAL PRIMARY KEY,
    assigned_to INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    assigned_by INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    priority VARCHAR(20) DEFAULT 'medium' CHECK (priority IN ('low', 'medium', 'high')),
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'in_progress', 'completed')),
    due_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS wholesale_requests (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    company_name VARCHAR(255) NOT NULL,
    contact_email VARCHAR(255),
    contact_phone VARCHAR(20),
    business_type VARCHAR(100),
    description TEXT,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS wholesale_quotes (
    id SERIAL PRIMARY KEY,
    request_id INT NOT NULL REFERENCES wholesale_requests(id) ON DELETE CASCADE,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    total_amount DECIMAL(10, 2),
    discount_percent DECIMAL(5, 2) DEFAULT 0,
    final_amount DECIMAL(10, 2),
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'accepted', 'rejected', 'converted')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS wholesale_quote_items (
    id SERIAL PRIMARY KEY,
    quote_id INT NOT NULL REFERENCES wholesale_quotes(id) ON DELETE CASCADE,
    product_id INT NOT NULL REFERENCES products(id) ON DELETE RESTRICT,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2),
    subtotal DECIMAL(10, 2)
);

CREATE TABLE IF NOT EXISTS barcode_scans (
    id SERIAL PRIMARY KEY,
    product_id INT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    barcode VARCHAR(100),
    scanned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_sku ON products(sku);
CREATE INDEX IF NOT EXISTS idx_barcode ON products(barcode);
CREATE INDEX IF NOT EXISTS idx_category ON products(category);
-- Some deployments use `client_id` instead of `user_id` on orders
CREATE INDEX IF NOT EXISTS idx_user_id ON orders(client_id);
CREATE INDEX IF NOT EXISTS idx_status ON orders(status);
CREATE INDEX IF NOT EXISTS idx_created_at ON orders(created_at);
CREATE INDEX IF NOT EXISTS idx_order_id ON order_items(order_id);
CREATE INDEX IF NOT EXISTS idx_product_id ON order_items(product_id);
CREATE INDEX IF NOT EXISTS idx_payment_order_id ON payments(order_id);
CREATE INDEX IF NOT EXISTS idx_assigned_to ON tasks(assigned_to);
CREATE INDEX IF NOT EXISTS idx_task_status ON tasks(status);
CREATE INDEX IF NOT EXISTS idx_wholesale_status ON wholesale_requests(status);
CREATE INDEX IF NOT EXISTS idx_quote_status ON wholesale_quotes(status);
CREATE INDEX IF NOT EXISTS idx_barcode_scan_barcode ON barcode_scans(barcode);
CREATE INDEX IF NOT EXISTS idx_barcode_scan_scanned_at ON barcode_scans(scanned_at);

-- Use current schema column names: password_hash, first_name, last_name, birthdate/points
INSERT INTO users (email, password_hash, first_name, last_name, phone, role) VALUES 
('admin@truper.com', '$2y$12$ViZrw8LXZv8Hc.uQj3uKGuC/YqLcPJeYLDhQkK8M7H7iKz7.m1Nrm', 'Administrador', 'Truper', '+1-234-567-8900', 'admin')
ON CONFLICT (email) DO NOTHING;

INSERT INTO users (email, password_hash, first_name, last_name, phone, birthdate, role, points) VALUES 
('cliente@truper.com', '$2y$12$ViZrw8LXZv8Hc.uQj3uKGuC/YqLcPJeYLDhQkK8M7H7iKz7.m1Nrm', 'Cliente', 'Demo', '+1-987-654-3210', '1990-05-15', 'client', 100)
ON CONFLICT (email) DO NOTHING;

-- Include `unit_price` to satisfy schemas that require it (set same as sell_price)
INSERT INTO products (name, sku, description, category, unit, cost_price, unit_price, sell_price) VALUES 
('Martillo de Peña', 'HAM001', 'Martillo profesional de peña de 500g', 'Herramientas', 'pieza', 15.00, 35.00, 35.00),
('Destornillador Phillips', 'SCR001', 'Set de destornilladores Phillips de precisión', 'Herramientas', 'set', 8.00, 22.00, 22.00),
('Llave Inglesa Ajustable', 'WRN001', 'Llave inglesa de 10 pulgadas', 'Herramientas', 'pieza', 12.00, 28.00, 28.00),
('Taladro Eléctrico 20V', 'DRL001', 'Taladro con batería y accesorios', 'Herramientas', 'pieza', 75.00, 180.00, 180.00),
('Cinta Métrica 25m', 'TAP001', 'Cinta métrica de acero de 25 metros', 'Herramientas', 'pieza', 5.00, 15.00, 15.00)
ON CONFLICT (sku) DO NOTHING;



