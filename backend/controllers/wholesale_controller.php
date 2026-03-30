<?php
/**
 * Wholesale Controller - Truper
 */

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/WholesaleSale.php';

Security::requireAuth();

$action = $_POST['action'] ?? null;

if ($action === 'create_request') {
    Security::requirePost();
    if (!Security::verifyRequestCSRFToken()) {
        header("Location: /views/wholesale.php?error=" . urlencode("Sesión inválida, recarga la página"));
        exit();
    }

    $company_name = Security::sanitize($_POST['company_name'] ?? '');
    $contact_email = Security::sanitize($_POST['contact_email'] ?? '');
    $contact_phone = Security::sanitize($_POST['contact_phone'] ?? '');
    $business_type = Security::sanitize($_POST['business_type'] ?? '');
    $description = Security::sanitize($_POST['description'] ?? '');
    
    $wholesale = new WholesaleSale();
    $result = $wholesale->createRequest(
        $_SESSION['user_id'],
        $company_name,
        $contact_email,
        $contact_phone,
        $business_type,
        $description
    );
    
    if ($result['success']) {
        header("Location: /views/wholesale.php?success=Solicitud enviada correctamente");
        exit();
    } else {
        header("Location: /views/wholesale.php?error=Error al enviar la solicitud");
        exit();
    }
}

else {
    header("Location: /index.php");
}
?>


