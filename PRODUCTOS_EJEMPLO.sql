INSERT INTO products (sku, name, description, category, unit_price, barcode) VALUES
('HERR-001', 'Martillo de Acero', 'Martillo de acero de 500g', 'Herramientas', 15000, '7707055400110'),
('HERR-002', 'Llave Inglesa', 'Llave inglesa de 10 pulgadas', 'Herramientas', 8500, '7707055400127'),
('HERR-003', 'Set de Destornilladores', 'Set de 6 destornilladores', 'Herramientas', 12000, '7707055400134'),
('MADERA-001', 'Tabla de Madera 2x4', 'Tabla de madera 2x4 pulg', 'Materiales', 5000, '7707055400141'),
('MADERA-002', 'Clavos 2 Pulgadas', 'Caja de clavos galvanizados', 'Materiales', 3500, '7707055400158'),
('PINTURAS-001', 'Pintura Blanca 1L', 'Pintura blanca de interior', 'Pinturas', 8000, '7707055400165'),
('PINTURAS-002', 'Pintura Naranja 1L', 'Pintura naranja corporativa', 'Pinturas', 8500, '7707055400172'),
('SEGURIDAD-001', 'Casco de Seguridad', 'Casco amarillo ANSI', 'Seguridad', 12000, '7707055400189'),
('SEGURIDAD-002', 'Guantes de Trabajo', 'Guantes de algodón reforzados', 'Seguridad', 4500, '7707055400196'),
('EQUIPOS-001', 'Taladro Eléctrico', 'Taladro 500W profesional', 'Equipos', 45000, '7707055400202');

-- Confirmar inserciones
SELECT COUNT(*) as total_productos FROM products;
