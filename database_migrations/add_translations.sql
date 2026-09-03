-- Tabla de traducciones
CREATE TABLE IF NOT EXISTS translations (
    id SERIAL PRIMARY KEY,
    translation_key VARCHAR(100) NOT NULL,
    language_code VARCHAR(5) NOT NULL, -- es, en, etc.
    translation_value TEXT NOT NULL,
    context VARCHAR(50), -- general, products, orders, etc.
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(translation_key, language_code)
);

-- Índices para mejor rendimiento
CREATE INDEX IF NOT EXISTS idx_translations_key ON translations(translation_key);
CREATE INDEX IF NOT EXISTS idx_translations_language ON translations(language_code);
CREATE INDEX IF NOT EXISTS idx_translations_context ON translations(context);
CREATE INDEX IF NOT EXISTS idx_translations_active ON translations(is_active);

-- Insertar traducciones por defecto en español
INSERT INTO translations (translation_key, language_code, translation_value, context) VALUES
-- General
('home', 'es', 'Inicio', 'general'),
('products', 'es', 'Productos', 'general'),
('cart', 'es', 'Carrito', 'general'),
('checkout', 'es', 'Finalizar Compra', 'general'),
('login', 'es', 'Iniciar Sesión', 'general'),
('register', 'es', 'Registrarse', 'general'),
('logout', 'es', 'Cerrar Sesión', 'general'),
('search', 'es', 'Buscar', 'general'),
('search_results', 'es', 'Resultados de Búsqueda', 'general'),
('no_results', 'es', 'No se encontraron resultados', 'general'),
('error', 'es', 'Error', 'general'),
('try_again', 'es', 'Intentar de nuevo', 'general'),
('something_went_wrong', 'es', 'Algo salió mal', 'general'),

-- Productos
('add_to_cart', 'es', 'Agregar al Carrito', 'products'),
('price', 'es', 'Precio', 'products'),
('stock', 'es', 'Stock', 'products'),
('out_of_stock', 'es', 'Agotado', 'products'),
('description', 'es', 'Descripción', 'products'),
('product_details', 'es', 'Detalles del Producto', 'products'),
('specifications', 'es', 'Especificaciones', 'products'),

-- Pedidos
('order', 'es', 'Pedido', 'orders'),
('orders', 'es', 'Pedidos', 'orders'),
('order_history', 'es', 'Historial de Pedidos', 'orders'),
('order_status', 'es', 'Estado del Pedido', 'orders'),
('tracking', 'es', 'Seguimiento', 'orders'),
('order_number', 'es', 'Número de Pedido', 'orders'),
('order_date', 'es', 'Fecha del Pedido', 'orders'),

-- Estados
('pending', 'es', 'Pendiente', 'orders'),
('confirmed', 'es', 'Confirmado', 'orders'),
('processing', 'es', 'En Proceso', 'orders'),
('shipped', 'es', 'Enviado', 'orders'),
('delivered', 'es', 'Entregado', 'orders'),
('cancelled', 'es', 'Cancelado', 'orders'),

-- Usuario
('my_account', 'es', 'Mi Cuenta', 'user'),
('my_dashboard', 'es', 'Mi Dashboard', 'user'),
('profile', 'es', 'Perfil', 'user'),
('addresses', 'es', 'Direcciones', 'user'),
('wishlist', 'es', 'Favoritos', 'user'),

-- Mensajes
('success', 'es', 'Éxito', 'messages'),
('warning', 'es', 'Advertencia', 'messages'),
('info', 'es', 'Información', 'messages')
ON CONFLICT (translation_key, language_code) DO NOTHING;

-- Insertar traducciones por defecto en inglés
INSERT INTO translations (translation_key, language_code, translation_value, context) VALUES
-- General
('home', 'en', 'Home', 'general'),
('products', 'en', 'Products', 'general'),
('cart', 'en', 'Cart', 'general'),
('checkout', 'en', 'Checkout', 'general'),
('login', 'en', 'Login', 'general'),
('register', 'en', 'Register', 'general'),
('logout', 'en', 'Logout', 'general'),
('search', 'en', 'Search', 'general'),
('search_results', 'en', 'Search Results', 'general'),
('no_results', 'en', 'No results found', 'general'),
('error', 'en', 'Error', 'general'),
('try_again', 'en', 'Try again', 'general'),
('something_went_wrong', 'en', 'Something went wrong', 'general'),

-- Productos
('add_to_cart', 'en', 'Add to Cart', 'products'),
('price', 'en', 'Price', 'products'),
('stock', 'en', 'Stock', 'products'),
('out_of_stock', 'en', 'Out of Stock', 'products'),
('description', 'en', 'Description', 'products'),
('product_details', 'en', 'Product Details', 'products'),
('specifications', 'en', 'Specifications', 'products'),

-- Pedidos
('order', 'en', 'Order', 'orders'),
('orders', 'en', 'Orders', 'orders'),
('order_history', 'en', 'Order History', 'orders'),
('order_status', 'en', 'Order Status', 'orders'),
('tracking', 'en', 'Tracking', 'orders'),
('order_number', 'en', 'Order Number', 'orders'),
('order_date', 'en', 'Order Date', 'orders'),

-- Estados
('pending', 'en', 'Pending', 'orders'),
('confirmed', 'en', 'Confirmed', 'orders'),
('processing', 'en', 'Processing', 'orders'),
('shipped', 'en', 'Shipped', 'orders'),
('delivered', 'en', 'Delivered', 'orders'),
('cancelled', 'en', 'Cancelled', 'orders'),

-- Usuario
('my_account', 'en', 'My Account', 'user'),
('my_dashboard', 'en', 'My Dashboard', 'user'),
('profile', 'en', 'Profile', 'user'),
('addresses', 'en', 'Addresses', 'user'),
('wishlist', 'en', 'Wishlist', 'user'),

-- Mensajes
('success', 'en', 'Success', 'messages'),
('warning', 'en', 'Warning', 'messages'),
('info', 'en', 'Information', 'messages')
ON CONFLICT (translation_key, language_code) DO NOTHING;
