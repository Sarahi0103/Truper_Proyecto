<!-- Truper - Mis Puntos -->
<?php
require_once __DIR__ . '/../backend/config/security.php';
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/models/User.php';

Security::requireAuth();

$user_model = new User();
$user = $user_model->getById($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Puntos - Truper</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/dashboard.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">Truper</div>
            <ul class="nav-menu">
                <li><a href="/views/dashboard.php">Dashboard</a></li>
                <li><a href="/backend/controllers/logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <h1>Mis Puntos</h1>
        
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <h3>Puntos Disponibles</h3>
                <p class="card-value"><?php echo $user['points']; ?></p>
                <p class="card-label">Puedes usarlos en tu prÃ³xima compra</p>
            </div>

            <div class="dashboard-card">
                <h3>Equivalencia</h3>
                <p class="card-value">$<?php echo $user['points'] * 0.1; ?></p>
                <p class="card-label">En descuentos</p>
            </div>

            <div class="dashboard-card">
                <h3>PrÃ³ximo Bono</h3>
                <p class="card-label">Â¡Tu cumpleaÃ±os es especial! RecibirÃ¡s un bono bonus.</p>
                <p class="card-label">Fecha: <?php echo date('d/m/Y', strtotime($user['birthday'])); ?></p>
            </div>
        </div>

        <section style="background: white; padding: 2rem; border-radius: 8px; margin-top: 2rem;">
            <h2>CÃ³mo funcionan los Puntos</h2>
            <ul style="line-height: 2; padding-left: 2rem;">
                <li>âœ“ Ganas 1 punto por cada $10 de compra</li>
                <li>âœ“ Acumula puntos sin lÃ­mite</li>
                <li>âœ“ Usa tus puntos como descuento en compras futuras</li>
                <li>âœ“ Recibe bonus especial en tu cumpleaÃ±os</li>
                <li>âœ“ Los puntos no caducan</li>
            </ul>
        </section>
    </div>
</body>
</html>


