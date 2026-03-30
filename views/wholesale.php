<?php
require_once __DIR__ . '/../backend/config/security.php';
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/models/WholesaleSale.php';

Security::requireAuth();

$wholesale = new WholesaleSale();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de Mayoreo - Truper</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/forms.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">Truper</div>
            <ul class="nav-menu">
                <li><a href="/index.php">Inicio</a></li>
                <li><a href="/backend/controllers/logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="form-container">
            <h1>Solicitud de Compra Mayoreo</h1>
            <p class="subtitle">Completa el formulario y nuestro equipo se contactarÃ¡ contigo</p>

            <form action="/backend/controllers/wholesale_controller.php" method="POST" class="form">
                <div class="form-group">
                    <label for="company_name">Nombre de la Empresa *</label>
                    <input type="text" id="company_name" name="company_name" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="contact_email">Email de Contacto *</label>
                        <input type="email" id="contact_email" name="contact_email" required>
                    </div>
                    <div class="form-group">
                        <label for="contact_phone">TelÃ©fono *</label>
                        <input type="tel" id="contact_phone" name="contact_phone" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="business_type">Tipo de Negocio *</label>
                    <select id="business_type" name="business_type" required>
                        <option value="">Seleccionar...</option>
                        <option value="FerreterÃ­a">FerreterÃ­a</option>
                        <option value="Tienda">Tienda de Herramientas</option>
                        <option value="ConstrucciÃ³n">Empresa de ConstrucciÃ³n</option>
                        <option value="Industrial">Distribuidor Industrial</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description">DescripciÃ³n de tu Negocio *</label>
                    <textarea id="description" name="description" rows="5" required placeholder="CuÃ©ntanos sobre tu negocio y quÃ© productos te interesan..."></textarea>
                </div>

                <button type="submit" name="action" value="create_request" class="btn-primary">Enviar Solicitud</button>
            </form>
        </div>
    </div>

    <script src="/assets/js/main.js"></script>
</body>
</html>


