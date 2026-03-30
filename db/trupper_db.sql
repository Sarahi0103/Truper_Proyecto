-- ============================================
-- BASE DE DATOS TRUPPER - SCRIPT DE INICIALIZACIÓN
-- ============================================

CREATE DATABASE IF NOT EXISTS trupper_db;
USE trupper_db;

-- ============================================
-- TABLA: USUARIOS
-- ============================================

CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    birthday DATE,
    role ENUM('admin', 'employee', 'client') DEFAULT 'client',
    points INT DEFAULT 0,
    active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
);

-- ============================================
-- TABLA: PRODUCTOS
-- ============================================

CREATE TABLE IF NOT EXISTS products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    sku VARCHAR(100) UNIQUE NOT NULL,
    barcode VARCHAR(100),
    description TEXT,
    category VARCHAR(100),
    unit VARCHAR(50),
    cost_price DECIMAL(10, 2),
    sell_price DECIMAL(10, 2) NOT NULL,
    active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sku (sku),
    INDEX idx_barcode (barcode),
    INDEX idx_category (category)
);

-- ============================================
-- TABLA: ÓRDENES
-- ============================================

CREATE TABLE IF NOT EXISTS orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    total DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
    payment_status ENUM('pending', 'partial', 'paid') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- ============================================
-- TABLA: ITEMS DE ÓRDENES
-- ============================================

CREATE TABLE IF NOT EXISTS order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (product_id) REFERENCES products(id),
    INDEX idx_order_id (order_id),
    INDEX idx_product_id (product_id)
);

-- ============================================
-- TABLA: PAGOS
-- ============================================

CREATE TABLE IF NOT EXISTS payment_tracking (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    amount_paid DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50),
    payment_date DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    INDEX idx_order_id (order_id),
    INDEX idx_payment_date (payment_date)
);

-- ============================================
-- TABLA: COMPROBANTES/TICKETS
-- ============================================

CREATE TABLE IF NOT EXISTS payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50),
    reference VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    INDEX idx_order_id (order_id)
);

-- ============================================
-- TABLA: TAREAS
-- ============================================

CREATE TABLE IF NOT EXISTS tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    assigned_to INT NOT NULL,
    assigned_by INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    due_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    FOREIGN KEY (assigned_by) REFERENCES users(id),
    INDEX idx_assigned_to (assigned_to),
    INDEX idx_status (status)
);

-- ============================================
-- TABLA: SOLICITUDES DE MAYOREO
-- ============================================

CREATE TABLE IF NOT EXISTS wholesale_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    contact_email VARCHAR(255),
    contact_phone VARCHAR(20),
    business_type VARCHAR(100),
    description TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_status (status)
);

-- ============================================
-- TABLA: COTIZACIONES DE MAYOREO
-- ============================================

CREATE TABLE IF NOT EXISTS wholesale_quotes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_id INT NOT NULL,
    user_id INT NOT NULL,
    total_amount DECIMAL(10, 2),
    discount_percent DECIMAL(5, 2) DEFAULT 0,
    final_amount DECIMAL(10, 2),
    status ENUM('pending', 'accepted', 'rejected', 'converted') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES wholesale_requests(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_status (status)
);

-- ============================================
-- TABLA: ITEMS DE COTIZACIONES
-- ============================================

CREATE TABLE IF NOT EXISTS wholesale_quote_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quote_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2),
    subtotal DECIMAL(10, 2),
    FOREIGN KEY (quote_id) REFERENCES wholesale_quotes(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- ============================================
-- TABLA: ESCANEOS DE CÓDIGOS DE BARRAS
-- ============================================

CREATE TABLE IF NOT EXISTS barcode_scans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    barcode VARCHAR(100),
    scanned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    INDEX idx_barcode (barcode),
    INDEX idx_scanned_at (scanned_at)
);

-- ============================================
-- DATOS DE EJEMPLO
-- ============================================

-- Usuario Admin
INSERT INTO users (email, password, name, phone, role) VALUES 
('admin@trupper.com', '$2y$12$ViZrw8LXZv8Hc.uQj3uKGuC/YqLcPJeYLDhQkK8M7H7iKz7.m1Nrm', 'Administrador', '+1-234-567-8900', 'admin');

-- Usuario Cliente
INSERT INTO users (email, password, name, phone, birthday, role, points) VALUES 
('cliente@trupper.com', '$2y$12$ViZrw8LXZv8Hc.uQj3uKGuC/YqLcPJeYLDhQkK8M7H7iKz7.m1Nrm', 'Cliente Demo', '+1-987-654-3210', '1990-05-15', 'client', 100);

-- Productos de ejemplo
INSERT INTO products (name, sku, description, category, unit, cost_price, sell_price) VALUES 
('Martillo de Peña', 'HAM001', 'Martillo profesional de peña de 500g', 'Herramientas', 'pieza', 15.00, 35.00),
('Destornillador Phillips', 'SCR001', 'Set de destornilladores Phillips de precisión', 'Herramientas', 'set', 8.00, 22.00),
('Llave Inglesa Ajustable', 'WRN001', 'Llave inglesa de 10 pulgadas', 'Herramientas', 'pieza', 12.00, 28.00),
('Taladro Eléctrico 20V', 'DRL001', 'Taladro con batería y accesorios', 'Herramientas', 'pieza', 75.00, 180.00),
('Cinta Métrica 25m', 'TAP001', 'Cinta métrica de acero de 25 metros', 'Herramientas', 'pieza', 5.00, 15.00);

-- Permiso para crear tablas
GRANT ALL PRIVILEGES ON trupper_db.* TO 'trupper_user'@'localhost' IDENTIFIED BY 'trupper_password';
FLUSH PRIVILEGES;
