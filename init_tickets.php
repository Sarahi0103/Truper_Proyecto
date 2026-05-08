<?php
/**
 * Script de inicialización del sistema de tickets para PostgreSQL.
 */

require_once 'config/config.php';

try {
    echo "🔧 Inicializando sistema de tickets...\n\n";

    $tables = [
        "CREATE TABLE IF NOT EXISTS sales_tickets (
            id SERIAL PRIMARY KEY,
            folio VARCHAR(20) UNIQUE NOT NULL,
            order_id INT REFERENCES orders(id) ON DELETE SET NULL,
            user_id INT REFERENCES users(id) ON DELETE SET NULL,
            ticket_type VARCHAR(50) NOT NULL DEFAULT 'sale' CHECK (ticket_type IN ('sale', 'return', 'adjustment', 'credit')),
            description TEXT,
            subtotal_amount DECIMAL(10,2) DEFAULT 0,
            tax_amount DECIMAL(10,2) DEFAULT 0,
            discount_amount DECIMAL(10,2) DEFAULT 0,
            total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            payment_method VARCHAR(50),
            payment_status VARCHAR(50) NOT NULL DEFAULT 'completed' CHECK (payment_status IN ('pending', 'completed', 'failed', 'refunded')),
            issued_by INT REFERENCES users(id) ON DELETE SET NULL,
            issued_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            notes TEXT,
            archived_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS ticket_items (
            id SERIAL PRIMARY KEY,
            ticket_id INT NOT NULL REFERENCES sales_tickets(id) ON DELETE CASCADE,
            product_id INT REFERENCES products(id) ON DELETE SET NULL,
            product_name VARCHAR(255),
            quantity INT NOT NULL,
            unit_price DECIMAL(10,2) NOT NULL,
            total DECIMAL(10,2) NOT NULL,
            discount DECIMAL(10,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS ticket_audit_log (
            id SERIAL PRIMARY KEY,
            ticket_id INT NOT NULL REFERENCES sales_tickets(id) ON DELETE CASCADE,
            action VARCHAR(50),
            description TEXT,
            admin_id INT REFERENCES users(id) ON DELETE SET NULL,
            old_value JSONB,
            new_value JSONB,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS ticket_folio_counter (
            year_month VARCHAR(7) PRIMARY KEY,
            counter INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS ticket_whatsapp_sends (
            id SERIAL PRIMARY KEY,
            ticket_id INT NOT NULL REFERENCES sales_tickets(id) ON DELETE CASCADE,
            phone_number VARCHAR(20),
            whatsapp_message_id VARCHAR(100),
            sent_at TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS ticket_generations (
            id SERIAL PRIMARY KEY,
            ticket_id INT NOT NULL REFERENCES sales_tickets(id) ON DELETE CASCADE,
            type VARCHAR(50),
            generated_by INT REFERENCES users(id) ON DELETE SET NULL,
            generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS ticket_downloads (
            id SERIAL PRIMARY KEY,
            ticket_id INT NOT NULL REFERENCES sales_tickets(id) ON DELETE CASCADE,
            format VARCHAR(20),
            downloaded_by INT REFERENCES users(id) ON DELETE SET NULL,
            downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS sales_monthly_statistics (
            id SERIAL PRIMARY KEY,
            year_month VARCHAR(7) UNIQUE NOT NULL,
            total_sales DECIMAL(12,2) DEFAULT 0,
            total_returns DECIMAL(12,2) DEFAULT 0,
            total_adjustments DECIMAL(12,2) DEFAULT 0,
            ticket_count INT DEFAULT 0,
            return_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    ];

    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }

    $indexes = [
        'CREATE INDEX IF NOT EXISTS idx_tickets_folio ON sales_tickets(folio)',
        'CREATE INDEX IF NOT EXISTS idx_tickets_user ON sales_tickets(user_id)',
        'CREATE INDEX IF NOT EXISTS idx_tickets_date ON sales_tickets(issued_date)',
        'CREATE INDEX IF NOT EXISTS idx_tickets_status ON sales_tickets(payment_status)',
        'CREATE INDEX IF NOT EXISTS idx_ticket_items_ticket ON ticket_items(ticket_id)',
        'CREATE INDEX IF NOT EXISTS idx_audit_ticket ON ticket_audit_log(ticket_id)',
        'CREATE INDEX IF NOT EXISTS idx_whatsapp_ticket ON ticket_whatsapp_sends(ticket_id)',
        'CREATE INDEX IF NOT EXISTS idx_generations_ticket ON ticket_generations(ticket_id)',
        'CREATE INDEX IF NOT EXISTS idx_downloads_ticket ON ticket_downloads(ticket_id)',
        'CREATE INDEX IF NOT EXISTS idx_monthly_statistics_year_month ON sales_monthly_statistics(year_month)'
    ];

    foreach ($indexes as $indexSql) {
        $pdo->exec($indexSql);
    }

    echo "✅ Sistema de tickets inicializado correctamente\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
