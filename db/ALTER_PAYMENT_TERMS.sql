-- ============================================
-- EXTENSIÓN: TÉRMINOS DE PAGO Y CONTROL SEMANAL
-- ============================================

-- Agregar columnas a tabla orders para plazos de pago
ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_terms ENUM('immediate', '15_days', '30_days') DEFAULT 'immediate';
ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_due_date DATE;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS balance DECIMAL(10, 2) DEFAULT 0;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS balance_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE orders ADD INDEX IF NOT EXISTS idx_payment_due_date (payment_due_date);
ALTER TABLE orders ADD INDEX IF NOT EXISTS idx_payment_status (payment_status);

-- Tabla para resumen semanal de consumo
CREATE TABLE IF NOT EXISTS weekly_consumption_summary (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    week_start DATE NOT NULL,
    week_end DATE NOT NULL,
    total_consumed DECIMAL(10, 2) DEFAULT 0,
    total_owed DECIMAL(10, 2) DEFAULT 0,
    payment_status ENUM('pending', 'partial', 'paid') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_id (user_id),
    INDEX idx_week_start (week_start),
    INDEX idx_payment_status (payment_status)
);

-- Tabla para crédito de cliente (saldo de cuenta)
CREATE TABLE IF NOT EXISTS client_credit_balance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    credit_limit DECIMAL(10, 2) DEFAULT 0,
    credit_available DECIMAL(10, 2) DEFAULT 0,
    credit_used DECIMAL(10, 2) DEFAULT 0,
    total_owed DECIMAL(10, 2) DEFAULT 0,
    last_payment_date DATE,
    days_overdue INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_id (user_id),
    INDEX idx_days_overdue (days_overdue)
);

-- Tabla para historial de pagos de crédito
CREATE TABLE IF NOT EXISTS credit_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    order_id INT,
    payment_amount DECIMAL(10, 2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method VARCHAR(50),
    reference_number VARCHAR(100),
    notes TEXT,
    recorded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (recorded_by) REFERENCES users(id),
    INDEX idx_user_id (user_id),
    INDEX idx_order_id (order_id),
    INDEX idx_payment_date (payment_date)
);

-- Tabla para cotizaciones por WhatsApp
CREATE TABLE IF NOT EXISTS whatsapp_quotes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    quote_data JSON NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    items_count INT NOT NULL,
    whatsapp_phone VARCHAR(20),
    status ENUM('pending', 'sent', 'answered', 'converted_to_order') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
);
