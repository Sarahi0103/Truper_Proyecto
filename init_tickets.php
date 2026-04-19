<?php
/**
 * Script de inicialización del sistema de tickets
 * Crear tablas necesarias para el sistema de gestión de tickets
 */

require_once 'config/config.php';

try {
    echo "🔧 Inicializando sistema de tickets...\n\n";
    
    // 1. Tabla de tickets principales
    $sql1 = "CREATE TABLE IF NOT EXISTS sales_tickets (
        id SERIAL PRIMARY KEY,
        folio VARCHAR(20) UNIQUE NOT NULL,
        order_id INT REFERENCES orders(id) ON DELETE SET NULL,
        user_id INT REFERENCES users(id) ON DELETE SET NULL,
        ticket_type VARCHAR(50) DEFAULT 'sale' CHECK (ticket_type IN ('sale', 'return', 'adjustment', 'credit')),
        description TEXT,
        subtotal_amount DECIMAL(10,2) DEFAULT 0,
        tax_amount DECIMAL(10,2) DEFAULT 0,
        discount_amount DECIMAL(10,2) DEFAULT 0,
        total_amount DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50) CHECK (payment_method IN ('cash', 'card', 'transfer', 'credit', 'check')),
        payment_status VARCHAR(50) DEFAULT 'completed' CHECK (payment_status IN ('pending', 'completed', 'failed', 'refunded')),
        issued_by INT REFERENCES users(id) ON DELETE SET NULL,
        issued_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        notes TEXT,
        archived_at TIMESTAMP NULL,
        deleted_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql1);
    echo "✅ Tabla sales_tickets creada\n";
    
    // 2. Tabla de items del ticket
    $sql2 = "CREATE TABLE IF NOT EXISTS ticket_items (
        id SERIAL PRIMARY KEY,
        ticket_id INT NOT NULL REFERENCES sales_tickets(id) ON DELETE CASCADE,
        product_id INT REFERENCES products(id) ON DELETE SET NULL,
        product_name VARCHAR(255),
        quantity INT NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        total DECIMAL(10,2) NOT NULL,
        discount DECIMAL(10,2) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql2);
    echo "✅ Tabla ticket_items creada\n";
    
    // 3. Tabla de auditoría de tickets
    $sql3 = "CREATE TABLE IF NOT EXISTS ticket_audit_log (
        id SERIAL PRIMARY KEY,
        ticket_id INT NOT NULL REFERENCES sales_tickets(id) ON DELETE CASCADE,
        action VARCHAR(50),
        description TEXT,
        admin_id INT REFERENCES users(id) ON DELETE SET NULL,
        old_value JSON,
        new_value JSON,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql3);
    echo "✅ Tabla ticket_audit_log creada\n";
    
    // 4. Tabla contador de folios
    $sql4 = "CREATE TABLE IF NOT EXISTS ticket_folio_counter (
        year_month VARCHAR(7) PRIMARY KEY,
        counter INT DEFAULT 0
    )";
    
    $pdo->exec($sql4);
    echo "✅ Tabla ticket_folio_counter creada\n";
    
    // 5. Crear índices para mejorar performance
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_tickets_folio ON sales_tickets(folio)",
        "CREATE INDEX IF NOT EXISTS idx_tickets_user ON sales_tickets(user_id)",
        "CREATE INDEX IF NOT EXISTS idx_tickets_date ON sales_tickets(issued_date)",
        "CREATE INDEX IF NOT EXISTS idx_tickets_status ON sales_tickets(payment_status)",
        "CREATE INDEX IF NOT EXISTS idx_ticket_items_ticket ON ticket_items(ticket_id)",
        "CREATE INDEX IF NOT EXISTS idx_audit_ticket ON ticket_audit_log(ticket_id)"
    ];
    
    foreach ($indexes as $idx) {
        try {
            $pdo->exec($idx);
        } catch (Exception $e) {
            // Index podría ya existir
        }
    }
    echo "✅ Índices creados/verificados\n";
    
    echo "\n✅ Sistema de tickets inicializado correctamente\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
