-- ============================================
-- SISTEMA DE TICKETS DE VENTAS
-- Historial de transacciones con folio único
-- ============================================

-- Tabla principal de tickets
CREATE TABLE IF NOT EXISTS sales_tickets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- Folio único (YYYYMM-XXXXX)
    folio VARCHAR(50) UNIQUE NOT NULL,
    
    -- Referencia a orden o venta
    order_id INT,
    user_id INT NOT NULL,
    
    -- Detalles de la transacción
    ticket_type ENUM('sale', 'return', 'adjustment', 'credit') DEFAULT 'sale',
    description TEXT,
    
    -- Montos
    subtotal DECIMAL(12, 2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(12, 2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(12, 2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(12, 2) NOT NULL DEFAULT 0,
    
    -- Método de pago
    payment_method VARCHAR(50),
    payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'completed',
    
    -- Detalles de auditoría
    issued_by INT NOT NULL,
    verified_by INT,
    notes TEXT,
    
    -- Fechas
    issued_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified_date TIMESTAMP NULL,
    archived_date TIMESTAMP NULL,
    
    -- Estado
    status ENUM('active', 'archived', 'cancelled') DEFAULT 'active',
    
    -- Índices
    INDEX idx_folio (folio),
    INDEX idx_user (user_id),
    INDEX idx_order (order_id),
    INDEX idx_status (status),
    INDEX idx_issued_date (issued_date),
    INDEX idx_payment_status (payment_status),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Tabla de items del ticket
CREATE TABLE IF NOT EXISTS sales_ticket_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    sales_ticket_id INT NOT NULL,
    product_id INT,
    
    -- Descripción del item
    sku VARCHAR(50),
    product_name VARCHAR(255),
    quantity INT NOT NULL,
    unit_price DECIMAL(12, 2) NOT NULL,
    line_total DECIMAL(12, 2) NOT NULL,
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_ticket (sales_ticket_id),
    INDEX idx_product (product_id),
    
    FOREIGN KEY (sales_ticket_id) REFERENCES sales_tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- Tabla de auditoría de tickets (cambios/acciones)
CREATE TABLE IF NOT EXISTS sales_ticket_audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    sales_ticket_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    action_by INT NOT NULL,
    
    -- Qué cambió
    old_values JSON,
    new_values JSON,
    reason TEXT,
    
    -- Timestamps
    action_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_ticket (sales_ticket_id),
    INDEX idx_action_date (action_date),
    
    FOREIGN KEY (sales_ticket_id) REFERENCES sales_tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (action_by) REFERENCES users(id) ON DELETE RESTRICT
);

-- Tabla de contador de folios por mes
CREATE TABLE IF NOT EXISTS ticket_folio_counter (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    year_month VARCHAR(7) UNIQUE NOT NULL,  -- YYYY-MM
    counter INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_year_month (year_month)
);

-- Tabla de archivo de tickets eliminados (para estadísticas históricas)
CREATE TABLE IF NOT EXISTS sales_tickets_archived (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    original_ticket_id INT,
    folio VARCHAR(50) UNIQUE NOT NULL,
    
    -- Datos completos guardados como JSON
    ticket_data JSON NOT NULL,
    
    -- Cuándo se archivó
    archived_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    archive_reason VARCHAR(255),
    
    -- Estadísticas agregadas
    total_amount DECIMAL(12, 2),
    ticket_type VARCHAR(50),
    
    INDEX idx_folio (folio),
    INDEX idx_archived_date (archived_date),
    INDEX idx_year_month (DATE_FORMAT(archived_date, '%Y-%m'))
);

-- Tabla de estadísticas mensuales de ventas
CREATE TABLE IF NOT EXISTS sales_monthly_statistics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    year_month VARCHAR(7) NOT NULL UNIQUE,  -- YYYY-MM
    
    -- Totales
    total_sales DECIMAL(12, 2) DEFAULT 0,
    total_returns DECIMAL(12, 2) DEFAULT 0,
    total_adjustments DECIMAL(12, 2) DEFAULT 0,
    
    -- Conteos
    ticket_count INT DEFAULT 0,
    return_count INT DEFAULT 0,
    
    -- Datos
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_year_month (year_month)
);

-- Vistas útiles
CREATE OR REPLACE VIEW v_current_tickets AS
SELECT 
    st.id,
    st.folio,
    st.ticket_type,
    st.total_amount,
    st.payment_status,
    st.issued_date,
    u.email as customer_email,
    u.name as customer_name,
    COUNT(sti.id) as item_count
FROM sales_tickets st
LEFT JOIN users u ON st.user_id = u.id
LEFT JOIN sales_ticket_items sti ON st.id = sti.sales_ticket_id
WHERE st.status = 'active'
GROUP BY st.id
ORDER BY st.issued_date DESC;

CREATE OR REPLACE VIEW v_ticket_summary AS
SELECT 
    DATE_FORMAT(issued_date, '%Y-%m') as month,
    ticket_type,
    COUNT(*) as count,
    SUM(total_amount) as total_amount,
    AVG(total_amount) as avg_amount
FROM sales_tickets
WHERE status = 'active'
GROUP BY DATE_FORMAT(issued_date, '%Y-%m'), ticket_type;
