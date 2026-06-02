<?php
$files = [
    'public/wholesale.php',
    'public/tickets.php',
    'public/tasks.php',
    'public/profile.php',
    'public/product_detail.php',
    'public/order_confirmation.php',
    'public/orders.php',
    'public/cashier.php',
    'public/checkout.php',
    'public/cart.php',
    'public/analytics.php',
    'public/admin_supply.php',
    'public/account.php'
];

$replacements = [];

// wholesale.php
$replacements['public/wholesale.php'] = <<<'EOD'
        <nav class="nav-menu">
            <a href="index.php">Catálogo</a>
            <a href="marketplace_ce.php">Marketplace CE</a>
            <div class="nav-dropdown">
                <button class="nav-dropdown-btn">Mi Cuenta <span class="arrow">▼</span></button>
                <div class="nav-dropdown-content">
                    <a href="dashboard.php">Dashboard</a>
                    <a href="orders.php">Pedidos</a>
                    <a href="wholesale.php" class="active">Mayoreo</a>
                    <a href="profile.php">Perfil</a>
                </div>
            </div>
            <?php if ($isAdmin): ?>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Administración <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content">
                        <a href="cashier.php">Caja</a>
                        <a href="admin_supply.php?nocache=true">Abastecimiento</a>
                        <a href="tickets.php">Tickets</a>
                        <a href="tasks.php">Tareas</a>
                        <a href="analytics.php">Estadísticas</a>
                    </div>
                </div>
            <?php endif; ?>
        </nav>
EOD;

// tickets.php
$replacements['public/tickets.php'] = <<<'EOD'
            <nav class="nav-menu">
                <a href="index.php">Catálogo</a>
                <a href="marketplace_ce.php">Marketplace CE</a>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Mi Cuenta <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content">
                        <a href="dashboard.php">Dashboard</a>
                        <a href="orders.php">Pedidos</a>
                        <a href="wholesale.php">Mayoreo</a>
                        <a href="profile.php">Perfil</a>
                    </div>
                </div>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Administración <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content">
                        <a href="cashier.php">Caja</a>
                        <a href="admin_supply.php?nocache=true">Abastecimiento</a>
                        <a href="tickets.php" class="active">Tickets</a>
                        <a href="tasks.php">Tareas</a>
                        <a href="analytics.php">Estadísticas</a>
                    </div>
                </div>
            </nav>
EOD;

// tasks.php
$replacements['public/tasks.php'] = <<<'EOD'
            <nav class="nav-menu">
                <a href="index.php">Catálogo</a>
                <a href="marketplace_ce.php">Marketplace CE</a>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Mi Cuenta <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content">
                        <a href="dashboard.php">Dashboard</a>
                        <a href="orders.php">Pedidos</a>
                        <a href="wholesale.php">Mayoreo</a>
                        <a href="profile.php">Perfil</a>
                    </div>
                </div>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Administración <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content">
                        <?php if ($is_admin): ?><a href="cashier.php">Caja</a><?php endif; ?>
                        <?php if ($is_admin): ?><a href="admin_supply.php?nocache=true">Abastecimiento</a><?php endif; ?>
                        <?php if ($is_admin): ?><a href="tickets.php">Tickets</a><?php endif; ?>
                        <a href="tasks.php" class="active">Tareas</a>
                        <a href="analytics.php">Estadísticas</a>
                    </div>
                </div>
            </nav>
EOD;

// profile.php
$replacements['public/profile.php'] = <<<'EOD'
            <nav class="nav-menu">
                <a href="index.php">Catálogo</a>
                <a href="marketplace_ce.php">Marketplace CE</a>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Mi Cuenta <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content">
                        <a href="dashboard.php">Dashboard</a>
                        <a href="orders.php">Pedidos</a>
                        <a href="wholesale.php">Mayoreo</a>
                        <a href="profile.php" class="active">Perfil</a>
                    </div>
                </div>
                <?php if ($is_admin): ?>
                    <div class="nav-dropdown">
                        <button class="nav-dropdown-btn">Administración <span class="arrow">▼</span></button>
                        <div class="nav-dropdown-content">
                            <a href="cashier.php">Caja</a>
                            <a href="admin_supply.php?nocache=true">Abastecimiento</a>
                            <a href="tickets.php">Tickets</a>
                            <a href="tasks.php">Tareas</a>
                            <a href="analytics.php">Estadísticas</a>
                        </div>
                    </div>
                <?php endif; ?>
            </nav>
EOD;

// product_detail.php
$replacements['public/product_detail.php'] = <<<'EOD'
        <nav class="nav-menu">
            <a href="index.php">Catálogo</a>
            <a href="marketplace_ce.php">Marketplace CE</a>
            <?php if ($isLogged): ?>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Mi Cuenta <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content">
                        <a href="dashboard.php">Dashboard</a>
                        <a href="orders.php">Pedidos</a>
                        <a href="wholesale.php">Mayoreo</a>
                        <a href="profile.php">Perfil</a>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Administración <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content">
                        <a href="cashier.php">Caja</a>
                        <a href="admin_supply.php?nocache=true">Abastecimiento</a>
                        <a href="tickets.php">Tickets</a>
                        <a href="tasks.php">Tareas</a>
                        <a href="analytics.php">Estadísticas</a>
                    </div>
                </div>
            <?php endif; ?>
        </nav>
EOD;

// order_confirmation.php
$replacements['public/order_confirmation.php'] = <<<'EOD'
            <nav class="nav-menu">
                <a href="index.php">Catálogo</a>
                <a href="marketplace_ce.php">Marketplace CE</a>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Mi Cuenta <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content">
                        <a href="dashboard.php">Dashboard</a>
                        <a href="orders.php" class="active">Pedidos</a>
                        <a href="wholesale.php">Mayoreo</a>
                        <a href="profile.php">Perfil</a>
                    </div>
                </div>
                <?php if ($is_admin): ?>
                    <div class="nav-dropdown">
                        <button class="nav-dropdown-btn">Administración <span class="arrow">▼</span></button>
                        <div class="nav-dropdown-content">
                            <a href="cashier.php">Caja</a>
                            <a href="admin_supply.php?nocache=true">Abastecimiento</a>
                            <a href="tickets.php">Tickets</a>
                            <a href="tasks.php">Tareas</a>
                            <a href="analytics.php">Estadísticas</a>
                        </div>
                    </div>
                <?php endif; ?>
            </nav>
EOD;

// orders.php
$replacements['public/orders.php'] = <<<'EOD'
            <nav class="nav-menu">
                <a href="index.php">Catálogo</a>
                <a href="marketplace_ce.php">Marketplace CE</a>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Mi Cuenta <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content">
                        <a href="dashboard.php">Dashboard</a>
                        <a href="orders.php" class="active">Pedidos</a>
                        <a href="wholesale.php">Mayoreo</a>
                        <a href="profile.php">Perfil</a>
                    </div>
                </div>
                <?php if ($is_admin): ?>
                    <div class="nav-dropdown">
                        <button class="nav-dropdown-btn">Administración <span class="arrow">▼</span></button>
                        <div class="nav-dropdown-content">
                            <a href="cashier.php">Caja</a>
                            <a href="admin_supply.php?nocache=true">Abastecimiento</a>
                            <a href="tickets.php">Tickets</a>
                            <a href="tasks.php">Tareas</a>
                            <a href="analytics.php">Estadísticas</a>
                        </div>
                    </div>
                <?php endif; ?>
            </nav>
EOD;

// cashier.php
$replacements['public/cashier.php'] = <<<'EOD'
        <nav class="nav-menu">
            <a href="index.php">Catálogo</a>
            <a href="marketplace_ce.php">Marketplace CE</a>
            <div class="nav-dropdown">
                <button class="nav-dropdown-btn">Mi Cuenta <span class="arrow">▼</span></button>
                <div class="nav-dropdown-content">
                    <a href="dashboard.php">Dashboard</a>
                    <a href="orders.php">Pedidos</a>
                    <a href="wholesale.php">Mayoreo</a>
                    <a href="profile.php">Perfil</a>
                </div>
            </div>
            <div class="nav-dropdown">
                <button class="nav-dropdown-btn">Administración <span class="arrow">▼</span></button>
                <div class="nav-dropdown-content">
                    <a href="cashier.php" class="active">Caja</a>
                    <a href="admin_supply.php?nocache=true">Abastecimiento</a>
                    <?php if (($_SESSION['role'] ?? '') === 'admin'): ?><a href="tickets.php">Tickets</a><?php endif; ?>
                    <a href="tasks.php">Tareas</a>
                    <a href="analytics.php">Estadísticas</a>
                </div>
            </div>
        </nav>
EOD;

// checkout.php
$replacements['public/checkout.php'] = <<<'EOD'
            <nav class="nav-menu">
                <a href="index.php">Catálogo</a>
                <a href="marketplace_ce.php">Marketplace CE</a>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Mi Cuenta <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content">
                        <a href="dashboard.php">Dashboard</a>
                        <a href="orders.php">Pedidos</a>
                        <a href="wholesale.php">Mayoreo</a>
                        <a href="profile.php">Perfil</a>
                    </div>
                </div>
                <?php if ($is_admin): ?>
                    <div class="nav-dropdown">
                        <button class="nav-dropdown-btn">Administración <span class="arrow">▼</span></button>
                        <div class="nav-dropdown-content">
                            <a href="cashier.php">Caja</a>
                            <a href="admin_supply.php?nocache=true">Abastecimiento</a>
                            <a href="tickets.php">Tickets</a>
                            <a href="tasks.php">Tareas</a>
                            <a href="analytics.php">Estadísticas</a>
                        </div>
                    </div>
                <?php endif; ?>
            </nav>
EOD;

// cart.php
$replacements['public/cart.php'] = <<<'EOD'
            <nav class="nav-menu">
                <a href="index.php">Catálogo</a>
                <a href="marketplace_ce.php">Marketplace CE</a>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Mi Cuenta <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content">
                        <a href="dashboard.php">Dashboard</a>
                        <a href="orders.php">Pedidos</a>
                        <a href="wholesale.php">Mayoreo</a>
                        <a href="profile.php">Perfil</a>
                    </div>
                </div>
                <?php if ($is_admin): ?>
                    <div class="nav-dropdown">
                        <button class="nav-dropdown-btn">Administración <span class="arrow">▼</span></button>
                        <div class="nav-dropdown-content">
                            <a href="cashier.php">Caja</a>
                            <a href="admin_supply.php?nocache=true">Abastecimiento</a>
                            <a href="tickets.php">Tickets</a>
                            <a href="tasks.php">Tareas</a>
                            <a href="analytics.php">Estadísticas</a>
                        </div>
                    </div>
                <?php endif; ?>
            </nav>
EOD;

// analytics.php
$replacements['public/analytics.php'] = <<<'EOD'
            <nav class="nav-menu">
                <a href="index.php">Catálogo</a>
                <a href="marketplace_ce.php">Marketplace CE</a>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Mi Cuenta <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content">
                        <a href="dashboard.php">Dashboard</a>
                        <a href="orders.php">Pedidos</a>
                        <a href="wholesale.php">Mayoreo</a>
                        <a href="profile.php">Perfil</a>
                    </div>
                </div>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Administración <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content">
                        <?php if ($is_admin): ?><a href="cashier.php">Caja</a><?php endif; ?>
                        <?php if ($is_admin): ?><a href="admin_supply.php?nocache=true">Abastecimiento</a><?php endif; ?>
                        <?php if ($is_admin): ?><a href="tickets.php">Tickets</a><?php endif; ?>
                        <a href="tasks.php">Tareas</a>
                        <a href="analytics.php" class="active">Estadísticas</a>
                    </div>
                </div>
            </nav>
EOD;

// admin_supply.php
$replacements['public/admin_supply.php'] = <<<'EOD'
        <nav class="nav-menu">
            <a href="index.php">Catálogo</a>
            <a href="marketplace_ce.php">Marketplace CE</a>
            <div class="nav-dropdown">
                <button class="nav-dropdown-btn">Mi Cuenta <span class="arrow">▼</span></button>
                <div class="nav-dropdown-content">
                    <a href="dashboard.php">Dashboard</a>
                    <a href="orders.php">Pedidos</a>
                    <a href="wholesale.php">Mayoreo</a>
                    <a href="profile.php">Perfil</a>
                </div>
            </div>
            <div class="nav-dropdown">
                <button class="nav-dropdown-btn">Administración <span class="arrow">▼</span></button>
                <div class="nav-dropdown-content">
                    <a href="cashier.php">Caja</a>
                    <a href="admin_supply.php?nocache=true" class="active">Abastecimiento</a>
                    <?php if (($_SESSION['role'] ?? '') === 'admin'): ?><a href="tickets.php">Tickets</a><?php endif; ?>
                    <a href="tasks.php">Tareas</a>
                    <a href="analytics.php">Estadísticas</a>
                </div>
            </div>
        </nav>
EOD;

// account.php
$replacements['public/account.php'] = <<<'EOD'
        <nav class="nav-menu">
            <a href="index.php">Catálogo</a>
            <a href="marketplace_ce.php">Marketplace CE</a>
            <div class="nav-dropdown">
                <button class="nav-dropdown-btn">Mi Cuenta <span class="arrow">▼</span></button>
                <div class="nav-dropdown-content">
                    <a href="dashboard.php">Dashboard</a>
                    <a href="orders.php">Pedidos</a>
                    <a href="wholesale.php">Mayoreo</a>
                    <a href="account.php" class="active">Mi Cuenta</a>
                    <a href="profile.php">Perfil</a>
                </div>
            </div>
            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">Administración <span class="arrow">▼</span></button>
                    <div class="nav-dropdown-content">
                        <a href="cashier.php">Caja</a>
                        <a href="admin_supply.php?nocache=true">Abastecimiento</a>
                        <a href="tickets.php">Tickets</a>
                        <a href="tasks.php">Tareas</a>
                        <a href="analytics.php">Estadísticas</a>
                    </div>
                </div>
            <?php endif; ?>
        </nav>
EOD;

foreach ($files as $filePath) {
    $realPath = dirname(__DIR__) . '/' . $filePath;
    if (!file_exists($realPath)) {
        echo "File does not exist: $realPath\n";
        continue;
    }
    
    $content = file_get_contents($realPath);
    $rep = $replacements[$filePath];
    
    // Perform regex replace of the entire <nav class="nav-menu">...</nav> block
    $count = 0;
    $updatedContent = preg_replace(
        '/<nav class="nav-menu">.*?<\/nav>/s',
        $rep,
        $content,
        -1,
        $count
    );
    
    if ($count > 0) {
        file_put_contents($realPath, $updatedContent);
        echo "Successfully updated: $filePath\n";
    } else {
        echo "Regex match failed in: $filePath\n";
    }
}
