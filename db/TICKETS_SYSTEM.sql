-- ============================================
-- SISTEMA DE TICKETS DE VENTAS (PostgreSQL)
-- Esquema compatible con `backend/models/SalesTicket.php`
-- ============================================

CREATE TABLE IF NOT EXISTS sales_tickets (
    id SERIAL PRIMARY KEY,
    folio VARCHAR(50) UNIQUE NOT NULL,
    order_id INT REFERENCES orders(id) ON DELETE SET NULL,
    user_id INT REFERENCES users(id) ON DELETE SET NULL,
    ticket_type VARCHAR(50) NOT NULL DEFAULT 'sale' CHECK (ticket_type IN ('sale', 'return', 'adjustment', 'credit')),
    description TEXT,
    subtotal_amount DECIMAL(12, 2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(12, 2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(12, 2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(12, 2) NOT NULL DEFAULT 0,
    payment_method VARCHAR(50),
    payment_status VARCHAR(50) NOT NULL DEFAULT 'completed' CHECK (payment_status IN ('pending', 'completed', 'failed', 'refunded')),
    issued_by INT REFERENCES users(id) ON DELETE SET NULL,
    issued_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified_by INT REFERENCES users(id) ON DELETE SET NULL,
    verified_date TIMESTAMP NULL,
    archived_at TIMESTAMP NULL,
    archived_date TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    notes TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'archived', 'cancelled')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ticket_items (
    id SERIAL PRIMARY KEY,
    ticket_id INT NOT NULL REFERENCES sales_tickets(id) ON DELETE CASCADE,
    product_id INT REFERENCES products(id) ON DELETE SET NULL,
    product_name VARCHAR(255),
    quantity INT NOT NULL,
    unit_price DECIMAL(12, 2) NOT NULL,
    total DECIMAL(12, 2) NOT NULL,
    discount DECIMAL(12, 2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ticket_audit_log (
    id SERIAL PRIMARY KEY,
    ticket_id INT NOT NULL REFERENCES sales_tickets(id) ON DELETE CASCADE,
    action VARCHAR(50) NOT NULL,
    description TEXT,
    admin_id INT REFERENCES users(id) ON DELETE SET NULL,
    old_value JSONB,
    new_value JSONB,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ticket_folio_counter (
    year_month VARCHAR(7) PRIMARY KEY,
    counter INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sales_tickets_archived (
    id SERIAL PRIMARY KEY,
    original_ticket_id INT,
    folio VARCHAR(50) UNIQUE NOT NULL,
    ticket_data JSONB NOT NULL,
    archived_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    archive_reason VARCHAR(255),
    total_amount DECIMAL(12, 2),
    ticket_type VARCHAR(50)
);

CREATE TABLE IF NOT EXISTS sales_monthly_statistics (
    id SERIAL PRIMARY KEY,
    year_month VARCHAR(7) NOT NULL UNIQUE,
    total_sales DECIMAL(12, 2) DEFAULT 0,
    total_returns DECIMAL(12, 2) DEFAULT 0,
    total_adjustments DECIMAL(12, 2) DEFAULT 0,
    ticket_count INT DEFAULT 0,
    return_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_tickets_folio ON sales_tickets(folio);
CREATE INDEX IF NOT EXISTS idx_tickets_user ON sales_tickets(user_id);
CREATE INDEX IF NOT EXISTS idx_tickets_order ON sales_tickets(order_id);
CREATE INDEX IF NOT EXISTS idx_tickets_status ON sales_tickets(status);
CREATE INDEX IF NOT EXISTS idx_tickets_date ON sales_tickets(issued_date);
CREATE INDEX IF NOT EXISTS idx_ticket_items_ticket ON ticket_items(ticket_id);
CREATE INDEX IF NOT EXISTS idx_ticket_items_product ON ticket_items(product_id);
CREATE INDEX IF NOT EXISTS idx_audit_ticket ON ticket_audit_log(ticket_id);
CREATE INDEX IF NOT EXISTS idx_audit_date ON ticket_audit_log(created_at);
CREATE INDEX IF NOT EXISTS idx_ticket_folio_counter_year_month ON ticket_folio_counter(year_month);
CREATE INDEX IF NOT EXISTS idx_monthly_statistics_year_month ON sales_monthly_statistics(year_month);

CREATE OR REPLACE VIEW v_current_tickets AS
SELECT 
    st.id,
    st.folio,
    st.ticket_type,
    st.total_amount,
    st.payment_status,
    st.issued_date,
    u.email AS customer_email,
    u.first_name || CASE WHEN u.last_name IS NOT NULL AND u.last_name <> '' THEN ' ' || u.last_name ELSE '' END AS customer_name,
    COUNT(sti.id) AS item_count
FROM sales_tickets st
LEFT JOIN users u ON st.user_id = u.id
LEFT JOIN ticket_items sti ON st.id = sti.ticket_id
WHERE st.status = 'active'
GROUP BY st.id, u.email, u.first_name, u.last_name
ORDER BY st.issued_date DESC;

CREATE OR REPLACE VIEW v_ticket_summary AS
SELECT 
    TO_CHAR(issued_date, 'YYYY-MM') AS month,
    ticket_type,
    COUNT(*) AS count,
    SUM(total_amount) AS total_amount,
    AVG(total_amount) AS avg_amount
FROM sales_tickets
WHERE status = 'active'
GROUP BY TO_CHAR(issued_date, 'YYYY-MM'), ticket_type;
