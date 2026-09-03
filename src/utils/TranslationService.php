<?php
/**
 * Translation Service
 * Sistema de multi-idioma para la plataforma
 */

class TranslationService {
    private $pdo;
    private $currentLanguage;
    private $translations = [];
    
    // Idiomas soportados
    const SUPPORTED_LANGUAGES = ['es', 'en'];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->currentLanguage = $this->detectLanguage();
        $this->loadTranslations();
    }
    
    /**
     * Detectar idioma del usuario
     * 
     * @return string Código de idioma
     */
    private function detectLanguage() {
        // Prioridad: sesión > cookie > navegador > default
        if (isset($_SESSION['language']) && in_array($_SESSION['language'], self::SUPPORTED_LANGUAGES)) {
            return $_SESSION['language'];
        }
        
        if (isset($_COOKIE['language']) && in_array($_COOKIE['language'], self::SUPPORTED_LANGUAGES)) {
            return $_COOKIE['language'];
        }
        
        $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'es', 0, 2);
        if (in_array($browserLang, self::SUPPORTED_LANGUAGES)) {
            return $browserLang;
        }
        
        return 'es'; // Default español
    }
    
    /**
     * Cargar traducciones del idioma actual
     */
    private function loadTranslations() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT translation_key, translation_value
                FROM translations
                WHERE language_code = ?
                AND is_active = true
            ");
            $stmt->execute([$this->currentLanguage]);
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->translations[$row['translation_key']] = $row['translation_value'];
            }
        } catch (Exception $e) {
            // Si la tabla no existe, usar traducciones por defecto
            $this->loadDefaultTranslations();
        }
    }
    
    /**
     * Cargar traducciones por defecto (fallback)
     */
    private function loadDefaultTranslations() {
        $this->translations = [
            // General
            'home' => $this->currentLanguage === 'es' ? 'Inicio' : 'Home',
            'products' => $this->currentLanguage === 'es' ? 'Productos' : 'Products',
            'cart' => $this->currentLanguage === 'es' ? 'Carrito' : 'Cart',
            'checkout' => $this->currentLanguage === 'es' ? 'Finalizar Compra' : 'Checkout',
            'login' => $this->currentLanguage === 'es' ? 'Iniciar Sesión' : 'Login',
            'register' => $this->currentLanguage === 'es' ? 'Registrarse' : 'Register',
            'logout' => $this->currentLanguage === 'es' ? 'Cerrar Sesión' : 'Logout',
            
            // Productos
            'add_to_cart' => $this->currentLanguage === 'es' ? 'Agregar al Carrito' : 'Add to Cart',
            'price' => $this->currentLanguage === 'es' ? 'Precio' : 'Price',
            'stock' => $this->currentLanguage === 'es' ? 'Stock' : 'Stock',
            'out_of_stock' => $this->currentLanguage === 'es' ? 'Agotado' : 'Out of Stock',
            'description' => $this->currentLanguage === 'es' ? 'Descripción' : 'Description',
            
            // Pedidos
            'order' => $this->currentLanguage === 'es' ? 'Pedido' : 'Order',
            'orders' => $this->currentLanguage === 'es' ? 'Pedidos' : 'Orders',
            'order_history' => $this->currentLanguage === 'es' ? 'Historial de Pedidos' : 'Order History',
            'order_status' => $this->currentLanguage === 'es' ? 'Estado del Pedido' : 'Order Status',
            'tracking' => $this->currentLanguage === 'es' ? 'Seguimiento' : 'Tracking',
            
            // Estados
            'pending' => $this->currentLanguage === 'es' ? 'Pendiente' : 'Pending',
            'confirmed' => $this->currentLanguage === 'es' ? 'Confirmado' : 'Confirmed',
            'processing' => $this->currentLanguage === 'es' ? 'En Proceso' : 'Processing',
            'shipped' => $this->currentLanguage === 'es' ? 'Enviado' : 'Shipped',
            'delivered' => $this->currentLanguage === 'es' ? 'Entregado' : 'Delivered',
            'cancelled' => $this->currentLanguage === 'es' ? 'Cancelado' : 'Cancelled',
            
            // Búsqueda
            'search' => $this->currentLanguage === 'es' ? 'Buscar' : 'Search',
            'search_results' => $this->currentLanguage === 'es' ? 'Resultados de Búsqueda' : 'Search Results',
            'no_results' => $this->currentLanguage === 'es' ? 'No se encontraron resultados' : 'No results found',
            
            // Errores
            'error' => $this->currentLanguage === 'es' ? 'Error' : 'Error',
            'try_again' => $this->currentLanguage === 'es' ? 'Intentar de nuevo' : 'Try again',
            'something_went_wrong' => $this->currentLanguage === 'es' ? 'Algo salió mal' : 'Something went wrong',
        ];
    }
    
    /**
     * Obtener traducción de una clave
     * 
     * @param string $key Clave de traducción
     * @param array $params Parámetros para reemplazar
     * @return string Texto traducido
     */
    public function translate($key, $params = []) {
        $translation = $this->translations[$key] ?? $key;
        
        // Reemplazar parámetros
        foreach ($params as $param => $value) {
            $translation = str_replace(':' . $param, $value, $translation);
        }
        
        return $translation;
    }
    
    /**
     * Obtener idioma actual
     * 
     * @return string Código de idioma
     */
    public function getCurrentLanguage() {
        return $this->currentLanguage;
    }
    
    /**
     * Cambiar idioma
     * 
     * @param string $language Código de idioma
     * @return bool True si se cambió exitosamente
     */
    public function setLanguage($language) {
        if (!in_array($language, self::SUPPORTED_LANGUAGES)) {
            return false;
        }
        
        $_SESSION['language'] = $language;
        setcookie('language', $language, time() + (86400 * 30), '/'); // 30 días
        
        $this->currentLanguage = $language;
        $this->loadTranslations();
        
        return true;
    }
    
    /**
     * Obtener todos los idiomas soportados
     * 
     * @return array Lista de idiomas
     */
    public function getSupportedLanguages() {
        return [
            'es' => ['name' => 'Español', 'flag' => '🇲🇽'],
            'en' => ['name' => 'English', 'flag' => '🇺🇸']
        ];
    }
    
    /**
     * Función helper para traducción en vistas
     * 
     * @param string $key Clave de traducción
     * @param array $params Parámetros
     * @return string Texto traducido
     */
    public function t($key, $params = []) {
        return $this->translate($key, $params);
    }
}

// Función global para traducción
if (!function_exists('__')) {
    function __($key, $params = []) {
        global $translationService;
        if ($translationService) {
            return $translationService->translate($key, $params);
        }
        return $key;
    }
}
