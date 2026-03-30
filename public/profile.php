<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <!-- HEADER -->
    <header>
        <div class="header-content">
            <a href="dashboard.php" class="logo">🏪 Truper</a>
            <nav class="nav-menu">
                <a href="dashboard.php">Dashboard</a>
                <a href="orders.php">Pedidos</a>
                <a href="profile.php" class="active">Perfil</a>
            </nav>
        </div>
        <div class="user-menu">
            <div class="user-info">
                <div class="user-name">Usuario</div>
            </div>
            <button class="btn-logout" onclick="logout()">Cerrar Sesión</button>
        </div>
    </header>

    <main>
        <div class="container" style="max-width: 600px;">
            <h1>Mi Perfil</h1>

            <div class="tabs">
                <button class="tab-button active" data-tab="profileInfo">Información Personal</button>
                <button class="tab-button" data-tab="loyaltyInfo">Puntos de Lealtad</button>
                <button class="tab-button" data-tab="passwordChange">Cambiar Contraseña</button>
            </div>

            <!-- INFORMACIÓN PERSONAL -->
            <div id="profileInfo" class="tab-content active">
                <div class="card">
                    <div class="card-header">Información de Perfil</div>
                    <div class="card-body">
                        <form id="profileForm" action="api/profile.php" method="POST">
                            <div class="form-group">
                                <label>Nombre</label>
                                <input type="text" name="first_name" value="Juan" required>
                            </div>

                            <div class="form-group">
                                <label>Apellido</label>
                                <input type="text" name="last_name" value="Pérez" required>
                            </div>

                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" value="juan@example.com" disabled>
                                <small class="text-muted">No se puede cambiar el email</small>
                            </div>

                            <div class="form-group">
                                <label>Teléfono</label>
                                <input type="tel" name="phone" value="+56 9 1234 5678">
                            </div>

                            <div class="form-group">
                                <label>Dirección</label>
                                <textarea name="address">Calle Principal 123, Santiago</textarea>
                            </div>

                            <div class="form-group">
                                <label>Empresa</label>
                                <input type="text" name="company_name" value="Mi Empresa">
                            </div>

                            <div class="form-group">
                                <label>Fecha de Nacimiento</label>
                                <input type="date" name="birthdate" value="1990-05-15">
                            </div>

                            <button type="submit" class="btn btn-primary btn-block">Actualizar Perfil</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- PUNTOS DE LEALTAD -->
            <div id="loyaltyInfo" class="tab-content">
                <div class="card">
                    <div class="card-header">Programa de Lealtad</div>
                    <div class="card-body">
                        <div style="text-align: center; padding: 2rem;">
                            <div style="font-size: 2rem; color: #FF7F00; margin-bottom: 0.5rem;">⭐</div>
                            <div style="font-size: 2.5rem; font-weight: bold; color: #FF7F00;">2,450</div>
                            <div style="color: #666; margin-bottom: 2rem;">Puntos Disponibles</div>

                            <div style="background-color: #f5f5f5; padding: 1.5rem; border-radius: 8px; text-align: left;">
                                <h4 style="margin-bottom: 1rem;">Cómo canjear tus puntos:</h4>
                                <ul style="line-height: 2;">
                                    <li>💰 100 puntos = 5% descuento</li>
                                    <li>💰 250 puntos = 10% descuento</li>
                                    <li>💰 500 puntos = 15% descuento</li>
                                    <li>💰 1000+ puntos = 20% descuento</li>
                                </ul>
                            </div>

                            <div style="margin-top: 2rem;">
                                <h4>Próximo Cumpleaños</h4>
                                <p>15 de mayo - ¡Recibirás un bono especial! 🎂</p>
                            </div>

                            <button class="btn btn-primary btn-block mt-3">Usar Descuento</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CAMBIAR CONTRASEÑA -->
            <div id="passwordChange" class="tab-content">
                <div class="card">
                    <div class="card-header">Cambiar Contraseña</div>
                    <div class="card-body">
                        <form id="passwordForm" action="api/profile.php?action=change-password" method="POST">
                            <div class="form-group">
                                <label>Contraseña Actual</label>
                                <input type="password" name="current_password" required>
                            </div>

                            <div class="form-group">
                                <label>Nueva Contraseña</label>
                                <input type="password" name="new_password" required minlength="8">
                                <small class="text-muted">Mínimo 8 caracteres</small>
                            </div>

                            <div class="form-group">
                                <label>Confirmar Nueva Contraseña</label>
                                <input type="password" name="confirm_password" required>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block">Cambiar Contraseña</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- FOOTER -->
    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h4>Truper</h4>
                <p>Plataforma de Gestión Empresarial</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2024 Truper Platform. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script src="js/main.js"></script>
    <script>
        function logout() {
            if (confirm('¿Deseas cerrar sesión?')) {
                window.location.href = 'api/auth.php?action=logout';
            }
        }
    </script>
</body>
</html>
