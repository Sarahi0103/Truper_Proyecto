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
    net_price DECIMAL(10, 2),
    discount_percentage DECIMAL(5, 2) DEFAULT 0,
    barcode VARCHAR(100) UNIQUE,
    stock_quantity INTEGER DEFAULT 0,
    reorder_level INTEGER DEFAULT 10,
    supplier_id INTEGER,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de Categorías de Productos
CREATE TABLE IF NOT EXISTS product_categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT true,
    context VARCHAR(20) NOT NULL DEFAULT 'stock',
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

-- DB-02: Admin user seed removed. Use the setup wizard or run:
--   INSERT INTO users (email, password_hash, first_name, last_name, role, is_active, is_verified)
--   VALUES ('admin@truper.com', '<bcrypt_hash_of_your_password>', 'Admin', 'Truper', 'admin', true, true);
-- Generate a bcrypt hash with: php -r "echo password_hash('YourSecurePassword', PASSWORD_BCRYPT);"

-- Crear tabla de configuración del sistema
CREATE TABLE IF NOT EXISTS system_config (
    id SERIAL PRIMARY KEY,
    config_key VARCHAR(100) UNIQUE NOT NULL,
    config_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- DB-07: Configuración correcta para México
INSERT INTO system_config (config_key, config_value) VALUES
('company_name', 'Truper'),
('support_email', 'soporte@truper.com'),
('phone', '+52 33 1248 2297'),
('address', 'Dirección de Truper'),
('currency', 'MXN'),
('timezone', 'America/Mexico_City')
ON CONFLICT DO NOTHING;

-- ============================================================
-- Tablas complementarias (creadas dinámicamente por el sistema)
-- ============================================================

-- Actualizaciones en el banner/héroe de la página principal
CREATE TABLE IF NOT EXISTS homepage_updates (
    id SERIAL PRIMARY KEY,
    title VARCHAR(220),
    body TEXT,
    link_url TEXT,
    link_label VARCHAR(100),
    image_url TEXT,
    position INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT true,
    additional_images TEXT DEFAULT '[]',
    registration_url TEXT DEFAULT '',
    design_template VARCHAR(50) DEFAULT 'classic',
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Artículos de segunda mano (Marketplace CE)
CREATE TABLE IF NOT EXISTS marketplace_ce_products (
    id SERIAL PRIMARY KEY,
    sku VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(220) NOT NULL,
    description TEXT NOT NULL,
    condition_label VARCHAR(80) NOT NULL DEFAULT 'Modelo Estandar',
    category VARCHAR(120),
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    net_price DECIMAL(12,2),
    discount_percentage DECIMAL(5, 2) DEFAULT 0,
    stock_quantity INTEGER NOT NULL DEFAULT 1,
    image_url TEXT,
    is_active BOOLEAN NOT NULL DEFAULT true,
    created_by INTEGER REFERENCES users(id),
    updated_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Metas mensuales de caja
CREATE TABLE IF NOT EXISTS cash_monthly_goals (
    id SERIAL PRIMARY KEY,
    month_key VARCHAR(7) NOT NULL UNIQUE,   -- YYYY-MM
    target_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Notas de control de pago (cliente / proveedor, plazos)
CREATE TABLE IF NOT EXISTS cash_control_notes (
    id SERIAL PRIMARY KEY,
    note_folio VARCHAR(60) NOT NULL UNIQUE,
    note_type VARCHAR(20) NOT NULL DEFAULT 'customer', -- customer, supplier
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_term VARCHAR(20) NOT NULL DEFAULT 'contado', -- contado, 15dias, 30dias
    due_date DATE,
    status VARCHAR(20) NOT NULL DEFAULT 'pending', -- pending, partial, paid, overdue
    reference_ticket VARCHAR(80),
    description TEXT,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Pagos/abonos a notas de control
CREATE TABLE IF NOT EXISTS cash_note_payments (
    id SERIAL PRIMARY KEY,
    note_id INTEGER NOT NULL REFERENCES cash_control_notes(id) ON DELETE CASCADE,
    amount DECIMAL(12,2) NOT NULL,
    payment_method VARCHAR(40) NOT NULL DEFAULT 'cash',
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices adicionales
CREATE INDEX IF NOT EXISTS idx_marketplace_ce_active ON marketplace_ce_products(is_active);
CREATE INDEX IF NOT EXISTS idx_cash_goals_month ON cash_monthly_goals(month_key);
CREATE INDEX IF NOT EXISTS idx_cash_notes_status ON cash_control_notes(status);
CREATE INDEX IF NOT EXISTS idx_cash_notes_due ON cash_control_notes(due_date);
CREATE INDEX IF NOT EXISTS idx_cash_note_payments_note ON cash_note_payments(note_id);

-- DB-06: Missing indexes for high-traffic queries
CREATE INDEX IF NOT EXISTS idx_homepage_updates_active ON homepage_updates(is_active);
CREATE INDEX IF NOT EXISTS idx_marketplace_ce_sku ON marketplace_ce_products(sku);
CREATE INDEX IF NOT EXISTS idx_products_sku ON products(sku);
CREATE INDEX IF NOT EXISTS idx_products_active ON products(is_active);
CREATE INDEX IF NOT EXISTS idx_products_category ON products(category);
CREATE INDEX IF NOT EXISTS idx_product_categories_active ON product_categories(is_active);
CREATE INDEX IF NOT EXISTS idx_order_items_order ON order_items(order_id);
CREATE INDEX IF NOT EXISTS idx_order_items_product ON order_items(product_id);
CREATE INDEX IF NOT EXISTS idx_payments_order ON payments(order_id);
CREATE INDEX IF NOT EXISTS idx_clients_user ON clients(user_id);

-- DB-09: Auto-update trigger for updated_at columns
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Apply trigger to main tables
DO $$ 
DECLARE
    tbl TEXT;
BEGIN
    FOR tbl IN SELECT unnest(ARRAY[
        'users', 'products', 'orders', 'clients', 'system_config',
        'homepage_updates', 'marketplace_ce_products', 'cash_monthly_goals',
        'cash_control_notes', 'product_categories'
    ])
    LOOP
        EXECUTE format(
            'DROP TRIGGER IF EXISTS trg_update_%I_updated_at ON %I; '
            'CREATE TRIGGER trg_update_%I_updated_at BEFORE UPDATE ON %I '
            'FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();',
            tbl, tbl, tbl, tbl
        );
    END LOOP;
END $$;

-- DB-10: Migrations tracking table
CREATE TABLE IF NOT EXISTS migrations (
    id SERIAL PRIMARY KEY,
    migration_name VARCHAR(255) NOT NULL UNIQUE,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    description TEXT
);

-- Rate limiting table (BE-07)
CREATE TABLE IF NOT EXISTS rate_limit_entries (
    id SERIAL PRIMARY KEY,
    rate_key VARCHAR(255) NOT NULL UNIQUE,
    attempts INTEGER NOT NULL DEFAULT 0,
    window_start TIMESTAMP NOT NULL DEFAULT NOW()
);

-- ============================================================
-- Esquemas Avanzados: Multi-Almacén, Kardex, Precios y Backorders
-- ============================================================

-- Multi-Almacén
CREATE TABLE IF NOT EXISTS warehouses (
    id SERIAL PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(150) NOT NULL,
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    postal_code VARCHAR(20),
    phone VARCHAR(30),
    is_main BOOLEAN DEFAULT false,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO warehouses (code, name, address, is_main, is_active)
VALUES ('ALM-CENTRAL', 'Almacén Central / Tienda Principal', 'Matriz Truper', true, true)
ON CONFLICT (code) DO NOTHING;

CREATE TABLE IF NOT EXISTS warehouse_stock (
    id SERIAL PRIMARY KEY,
    warehouse_id INTEGER NOT NULL REFERENCES warehouses(id) ON DELETE CASCADE,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    stock_quantity INTEGER NOT NULL DEFAULT 0,
    min_stock INTEGER DEFAULT 5,
    max_stock INTEGER DEFAULT 1000,
    aisle VARCHAR(50),
    shelf VARCHAR(50),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(warehouse_id, product_id)
);

CREATE TABLE IF NOT EXISTS stock_transfers (
    id SERIAL PRIMARY KEY,
    transfer_number VARCHAR(50) UNIQUE NOT NULL,
    from_warehouse_id INTEGER NOT NULL REFERENCES warehouses(id),
    to_warehouse_id INTEGER NOT NULL REFERENCES warehouses(id),
    status VARCHAR(30) DEFAULT 'pending',
    requested_by INTEGER REFERENCES users(id),
    received_by INTEGER REFERENCES users(id),
    notes TEXT,
    transfer_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    received_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS stock_transfer_items (
    id SERIAL PRIMARY KEY,
    transfer_id INTEGER NOT NULL REFERENCES stock_transfers(id) ON DELETE CASCADE,
    product_id INTEGER NOT NULL REFERENCES products(id),
    quantity INTEGER NOT NULL,
    received_quantity INTEGER DEFAULT 0,
    notes TEXT
);

-- Kardex de Inventario
CREATE TABLE IF NOT EXISTS inventory_kardex (
    id SERIAL PRIMARY KEY,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    warehouse_id INTEGER REFERENCES warehouses(id),
    movement_type VARCHAR(40) NOT NULL,
    quantity INTEGER NOT NULL,
    unit_cost DECIMAL(12, 2) DEFAULT 0,
    unit_price DECIMAL(12, 2) DEFAULT 0,
    balance_quantity INTEGER NOT NULL,
    reference_folio VARCHAR(80),
    notes TEXT,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Historial de Precios
CREATE TABLE IF NOT EXISTS product_price_history (
    id SERIAL PRIMARY KEY,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    old_unit_price DECIMAL(12, 2),
    new_unit_price DECIMAL(12, 2) NOT NULL,
    old_net_price DECIMAL(12, 2),
    new_net_price DECIMAL(12, 2),
    old_supplier_cost DECIMAL(12, 2),
    new_supplier_cost DECIMAL(12, 2),
    reason TEXT,
    changed_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Backorders y Pre-órdenes
CREATE TABLE IF NOT EXISTS order_backorders (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    product_id INTEGER NOT NULL REFERENCES products(id),
    requested_qty INTEGER NOT NULL,
    fulfilled_qty INTEGER NOT NULL DEFAULT 0,
    status VARCHAR(30) DEFAULT 'waiting_stock',
    estimated_arrival DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices de rendimiento
CREATE INDEX IF NOT EXISTS idx_warehouse_stock_lookup ON warehouse_stock(warehouse_id, product_id);
CREATE INDEX IF NOT EXISTS idx_kardex_product ON inventory_kardex(product_id);
CREATE INDEX IF NOT EXISTS idx_kardex_created ON inventory_kardex(created_at);
CREATE INDEX IF NOT EXISTS idx_kardex_movement ON inventory_kardex(movement_type);
CREATE INDEX IF NOT EXISTS idx_backorders_order ON order_backorders(order_id);
CREATE INDEX IF NOT EXISTS idx_backorders_product ON order_backorders(product_id);
CREATE INDEX IF NOT EXISTS idx_price_history_product ON product_price_history(product_id);

