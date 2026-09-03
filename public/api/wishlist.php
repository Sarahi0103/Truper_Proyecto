<?php
/**
 * API de Wishlist/Favoritos
 * Gestión de productos favoritos de usuarios
 */

require_once '../../config/config.php';
require_once '../../src/Services/WishlistService.php';
require_once '../../src/utils/AppLogger.php';

header('Content-Type: application/json');

// Verificar sesión
require_login();

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$userId = (int)$_SESSION['user_id'];

try {
    $wishlistService = new WishlistService($pdo);
    
    switch ($action) {
        case 'add':
            // Agregar producto a wishlist
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $productId = $input['product_id'] ?? 0;
            
            if (!$productId) {
                echo json_encode(['success' => false, 'message' => 'ID de producto requerido']);
                exit;
            }
            
            $result = $wishlistService->addToWishlist($userId, $productId);
            echo json_encode($result);
            break;
            
        case 'remove':
            // Eliminar producto de wishlist
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $productId = $input['product_id'] ?? 0;
            
            if (!$productId) {
                echo json_encode(['success' => false, 'message' => 'ID de producto requerido']);
                exit;
            }
            
            $result = $wishlistService->removeFromWishlist($userId, $productId);
            echo json_encode($result);
            break;
            
        case 'list':
            // Obtener wishlist del usuario
            if ($method !== 'GET') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $result = $wishlistService->getUserWishlist($userId);
            echo json_encode($result);
            break;
            
        case 'check':
            // Verificar si producto está en wishlist
            if ($method !== 'GET') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $productId = $_GET['product_id'] ?? 0;
            
            if (!$productId) {
                echo json_encode(['success' => false, 'message' => 'ID de producto requerido']);
                exit;
            }
            
            $isInWishlist = $wishlistService->isInWishlist($userId, $productId);
            echo json_encode(['success' => true, 'in_wishlist' => $isInWishlist]);
            break;
            
        case 'clear':
            // Limpiar wishlist
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $result = $wishlistService->clearWishlist($userId);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            break;
    }
} catch (Exception $e) {
    AppLogger::error("Wishlist API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
