<?php
require_once '../config/config.php';
require_admin();

$user_name = htmlspecialchars($_SESSION['name'] ?? 'Administrador', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Abastecimiento - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css?v=2.2">
    <link rel="stylesheet" href="css/theme.css?v=2.5">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/responsive-complete.css?v=2.2">
    <style>
        /* Estilos para badges de estatus de visitas */
        .visit-status-badge {
            font-size: 0.72rem;
            font-weight: bold;
            padding: 0.15rem 0.45rem;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
        }
        .visit-status-badge.badge-upcoming {
            background: rgba(255, 102, 0, 0.15);
            color: var(--color-naranja, #ff6600);
            border: 1px solid rgba(255, 102, 0, 0.25);
        }
        .visit-status-badge.badge-past {
            background: rgba(255, 255, 255, 0.05);
            color: #888888;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .visit-item.visit-past {
            opacity: 0.65;
            transition: opacity 0.2s, transform 0.2s, border-color 0.2s;
        }
        .visit-item.visit-past:hover {
            opacity: 1;
        }
        .visit-item.visit-past::before {
            background: #555555 !important;
        }

        /* Estilos para el selector de exclusión de productos */
        .exclude-chips-container {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            min-height: 42px;
            padding: 0.5rem 0.75rem;
            background: #121212;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 8px;
            align-items: center;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .exclude-chips-container:focus-within {
            border-color: var(--color-naranja, #ff6600);
            box-shadow: 0 0 0 2px rgba(255, 102, 0, 0.2);
        }
        .exclude-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 102, 0, 0.08);
            border: 1px solid rgba(255, 102, 0, 0.25);
            border-radius: 6px;
            padding: 0.3rem 0.6rem;
            font-size: 0.85rem;
            color: #ff6600;
            font-weight: 500;
            transition: all 0.2s ease;
            animation: chipFadeIn 0.2s ease-out;
        }
        .exclude-chip:hover {
            background: rgba(255, 102, 0, 0.15);
            border-color: rgba(255, 102, 0, 0.4);
            transform: translateY(-1px);
        }
        .exclude-chip-sku {
            font-weight: bold;
            background: rgba(255, 102, 0, 0.2);
            color: #ff8833;
            padding: 0.1rem 0.35rem;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        .exclude-chip-name {
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #eee;
        }
        .exclude-chip-remove {
            background: none;
            border: none;
            color: #ff6600;
            cursor: pointer;
            font-size: 1.1rem;
            padding: 0;
            margin-left: 0.15rem;
            line-height: 1;
            display: inline-flex;
            align-items: center;
            font-weight: bold;
            transition: color 0.2s, transform 0.2s;
        }
        .exclude-chip-remove:hover {
            color: #ff3300;
            transform: scale(1.2);
        }
        @keyframes chipFadeIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
        
        .exclude-search-input {
            width: 100%;
            padding: 0.75rem 1rem;
            background: #1a1a1a;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 8px;
            color: #fff;
            font-size: 0.95rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .exclude-search-input:focus {
            outline: none;
            border-color: var(--color-naranja, #ff6600);
            box-shadow: 0 0 0 2px rgba(255, 102, 0, 0.25);
            background: #1f1f1f;
        }
        
        .exclude-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            background: #1a1a1a;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            max-height: 280px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.6);
            padding: 0.35rem;
            animation: dropdownFadeIn 0.15s ease-out;
        }
        @keyframes dropdownFadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .exclude-dropdown-item {
            padding: 0.6rem 0.8rem;
            cursor: pointer;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            transition: all 0.15s ease;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
        }
        .exclude-dropdown-item:last-child {
            border-bottom: none;
        }
        .exclude-dropdown-item:hover {
            background: rgba(255, 102, 0, 0.12);
        }
        .exclude-item-details {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
            min-width: 0;
        }
        .exclude-item-name {
            color: #fff;
            font-weight: 500;
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .exclude-item-meta {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.4);
        }
        .exclude-item-action-indicator {
            font-size: 0.8rem;
            color: var(--color-naranja, #ff6600);
            font-weight: bold;
            opacity: 0;
            transform: translateX(5px);
            transition: all 0.2s ease;
        }
        .exclude-dropdown-item:hover .exclude-item-action-indicator {
            opacity: 1;
            transform: translateX(0);
        }

        /* ===== CARGA MASIVA CSV ===== */
        .csv-upload-panel {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 1.25rem;
            margin-top: 0.5rem;
            margin-bottom: 1rem;
        }

        /* Grilla de campos requeridos */
        .csv-fields-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .csv-field-badge {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
            min-width: 90px;
            text-align: center;
            transition: all 0.2s;
        }
        .csv-field-badge.required {
            border-color: rgba(255,102,0,0.35);
            background: rgba(255,102,0,0.06);
        }
        .csv-field-badge.required::after {
            content: "Requerido";
            font-size: 9px;
            color: #ff6600;
            font-weight: 600;
            letter-spacing: 0.3px;
            margin-top: 2px;
        }
        .csv-field-icon { font-size: 1.15rem; line-height: 1; }
        .csv-field-name {
            font-family: 'Courier New', monospace;
            font-size: 0.78rem;
            font-weight: 700;
            color: #eee;
        }
        .csv-field-badge small {
            font-size: 0.68rem;
            color: rgba(255,255,255,0.4);
            line-height: 1.2;
        }

        /* Zona de drag & drop */
        .csv-drop-zone {
            border: 2px dashed rgba(255,255,255,0.15);
            border-radius: 10px;
            padding: 1.8rem 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background: rgba(255,255,255,0.02);
            user-select: none;
        }
        .csv-drop-zone:hover, .csv-drop-zone.drag-over {
            border-color: #ff6600;
            background: rgba(255,102,0,0.06);
            transform: scale(1.01);
        }
        .csv-drop-zone.file-selected {
            border-color: #22c55e;
            background: rgba(34,197,94,0.06);
        }
        .csv-drop-icon { font-size: 2.2rem; margin-bottom: 0.4rem; display: block; }
        .csv-drop-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: #ddd;
            margin: 0 0 0.25rem;
        }
        .csv-drop-sub {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.4);
            margin: 0;
            word-break: break-all;
        }
        .csv-drop-zone.file-selected .csv-drop-sub {
            color: #22c55e;
            font-weight: 600;
        }

        /* Fila de acciones */
        .csv-actions-row {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        .btn-ghost {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.15);
            background: transparent;
            color: rgba(255,255,255,0.6);
            font-size: 0.85rem;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-ghost:hover {
            border-color: rgba(255,255,255,0.35);
            color: #fff;
            background: rgba(255,255,255,0.05);
        }

        /* Barra de progreso */
        .csv-progress-bar-bg {
            background: rgba(255,255,255,0.08);
            border-radius: 99px;
            height: 8px;
            overflow: hidden;
            margin-bottom: 0.4rem;
        }
        .csv-progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #ff6600, #ff9500);
            border-radius: 99px;
            transition: width 0.4s ease;
        }
        .csv-progress-label {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.55);
            text-align: center;
        }
        .csv-progress-label.success { color: #22c55e; font-weight: 600; }
        .csv-progress-label.warning { color: #f59e0b; font-weight: 600; }
        .csv-progress-label.error { color: #ef4444; font-weight: 600; }
    </style>
</head>
<body>
<header>
    <div class="header-content">
        <a href="dashboard.php" class="logo"><img src="images/truper-logo.svg" alt="Truper"></a>
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
    </div>
    <div class="user-menu">
        <div class="user-info"><div class="user-name"><?php echo $user_name; ?></div><div class="user-role">Admin</div></div>
        <button class="btn-logout" onclick="window.location.href='api/auth.php?action=logout'">Cerrar Sesion</button>
    </div>
</header>

<main>
    <div class="container-fluid admin-supply-shell">
        <div class="page-hero">
            <div class="module-badge module-admin"><span class="module-glyph">AD</span> Módulo administrativo</div>
            <h1>Panel de Abastecimiento</h1>
            <p class="text-muted">Control de existencias, calendario de proveedores, ordenes de compra y historico.</p>
        </div>

        <div class="grid grid-2 mt-3 admin-overview-grid">
            <div class="card admin-overview-card">
                <div class="card-body">
                    <h3>Acceso rápido a clientes</h3>
                    <p class="text-muted">Registrar y consultar el acceso del cliente sin usar contraseña.</p>
                    <button class="btn btn-primary" onclick="goToClientsTab()">Ir al registro de cliente</button>
                </div>
            </div>
            <div class="card admin-overview-card">
                <div class="card-body">
                    <h3>Inicio de sesión del cliente</h3>
                    <p class="text-muted">El cliente entra con código único y fecha de nacimiento obligatoria.</p>
                    <a class="btn btn-secondary" href="/login.php?force=1">Abrir inicio de sesión</a>
                </div>
            </div>
        </div>

        <div class="tabs mt-3 admin-tabs">
            <button class="tab-button active" data-tab="stockTab">Stock</button>
            <button class="tab-button" data-tab="calendarTab">Calendario</button>
            <button class="tab-button" data-tab="supplierOrderTab">Orden Proveedor</button>
            <button class="tab-button" data-tab="updatesTab">Portada</button>
            <button class="tab-button" data-tab="clientsTab">Clientes</button>
            <button class="tab-button" data-tab="historyTab">Historico</button>
            <button class="tab-button" data-tab="pricesTab">Precios</button>
            <button class="tab-button" data-tab="marketplaceTab">Marketplace CE</button>
            <button class="tab-button" data-tab="categoriesTab">Categorías</button>
        </div>

        <section id="stockTab" class="tab-content active admin-tab-panel">
            <div class="card mb-3 admin-editor-card admin-editor-card-stock"><div class="card-body">
                <div class="section-kicker section-kicker-stock">Stock interno</div>
                <h3>Agregar Producto</h3>
                <p class="text-muted">Registra nuevos productos y opcionalmente sube su imagen.</p>

                <input type="hidden" id="newProductEditId" value="">
                <input type="hidden" id="newProductSeedMode" value="0">

                <div class="grid grid-2">
                    <div class="form-group"><label>Código del producto (5 o 6 números)</label><input id="newProductSku" type="text" maxlength="6" inputmode="numeric" pattern="\d{5,6}" placeholder="Ej. 23032"><small id="newProductSkuStatus" class="text-muted">Se valida en la base de datos y debe ser único.</small></div>
                    <div class="form-group"><label>Nombre</label><input id="newProductName" type="text" maxlength="255"></div>
                </div>

                <div class="form-group">
                    <label>Categorías (selección múltiple)</label>
                    <div class="category-panel">
                        <div class="category-panel-title">Categorías de catálogo</div>
                        <select id="newProductCategory" multiple size="6">
                            <option value="Material eléctrico">Material eléctrico</option>
                            <option value="Fontanería">Fontanería</option>
                            <option value="Cerrajería">Cerrajería</option>
                            <option value="Herrería">Herrería</option>
                        </select>
                        <small class="text-muted">Usa Ctrl/Cmd para seleccionar múltiples categorías. Gestión en la pestaña Categorías.</small>
                    </div>
                </div>

                <div class="grid grid-3">
                    <div class="form-group"><label>Precio</label><input id="newProductPrice" type="number" min="0" step="0.01" value="0"></div>
                    <div class="form-group"><label>Stock inicial</label><input id="newProductStock" type="number" min="0" step="1" value="50"></div>
                    <div class="form-group"><label>Visibilidad en tienda</label><select id="newProductVisible"><option value="1">✅ Visible en tienda</option><option value="0">🔒 Oculto</option></select></div>
                </div>

                <div class="grid grid-3">
                    <div class="form-group"><label>Nivel reorden</label><input id="newProductReorder" type="number" min="0" step="1" value="10"></div>
                </div>

                <div class="grid grid-2 mt-2">
                    <div class="form-group">
                        <label>Subir nuevas imágenes</label>
                        <input id="newProductImages" type="file" accept="image/*" multiple style="margin-bottom: 0.5rem;">
                        <button class="btn btn-secondary btn-small" type="button" onclick="uploadProductImages()">Cargar e integrar a galería</button>
                        <small class="text-muted">Al seleccionar archivos se subirán automáticamente.</small>
                    </div>
                    <div class="form-group">
                        <label>Imagen de referencia existente (Evita duplicidad)</label>
                        <select id="newProductImageRef" onchange="updateStockPreview()">
                            <option value="images/products/default-product.svg">Por defecto (Logo Truper)</option>
                        </select>
                        <small class="text-muted">Usa una imagen compartida que ya esté en el servidor para optimizar espacio.</small>
                    </div>
                </div>

                <div class="form-group">
                    <label>Galería del producto (por código)</label>
                    <small class="text-muted">Sube varias imágenes para este SKU, define portada y elimina las que no necesites.</small>
                    <div id="productGalleryStatus" class="text-muted" style="margin-top:6px;">Escribe un código de 5 o 6 números para cargar su galería.</div>
                    <div id="productGalleryList" style="display:grid; grid-template-columns:repeat(auto-fill,minmax(120px,1fr)); gap:0.6rem; margin-top:0.6rem;"></div>
                </div>

                <div class="form-group"><label>Descripción</label><textarea id="newProductDescription" rows="3"></textarea></div>

                <div class="admin-preview-wrap">
                    <p class="admin-list-caption">Vista previa (estilo portada):</p>
                    <div id="stockPreviewHost" class="catalog-grid-min"></div>
                </div>

                <div class="d-flex align-center" style="gap: 0.75rem; flex-wrap: wrap;">
                    <button class="btn btn-primary" id="newProductSaveButton" onclick="createProductByAdmin()">Guardar producto</button>
                    <button class="btn btn-secondary" type="button" onclick="resetProductForm()">Cancelar</button>
                </div>
                <div id="productCreateResult" class="mt-3"></div>
            </div></div>

            <div class="card"><div class="card-body">
                <h3>Control de Existencias</h3>
                <div class="admin-search-row">
                    <input id="stockSearch" type="text" placeholder="Buscar por código, nombre o categoría...">
                </div>
                <div class="admin-section-subtitle mt-3">📤 Carga Masiva (CSV)</div>
                <div class="csv-upload-panel" id="stockCsvPanel">
                    <!-- Columnas requeridas -->
                    <div class="csv-fields-grid">
                        <div class="csv-field-badge required"><span class="csv-field-icon">🔑</span><span class="csv-field-name">sku</span><small>Código 5-6 dígitos</small></div>
                        <div class="csv-field-badge required"><span class="csv-field-icon">📝</span><span class="csv-field-name">name</span><small>Nombre del producto</small></div>
                        <div class="csv-field-badge required"><span class="csv-field-icon">🏷️</span><span class="csv-field-name">category</span><small>Categoría</small></div>
                        <div class="csv-field-badge"><span class="csv-field-icon">📄</span><span class="csv-field-name">description</span><small>Descripción</small></div>
                        <div class="csv-field-badge required"><span class="csv-field-icon">💲</span><span class="csv-field-name">unit_price</span><small>Precio unitario</small></div>
                        <div class="csv-field-badge required"><span class="csv-field-icon">📦</span><span class="csv-field-name">stock_quantity</span><small>Cantidad en stock</small></div>
                        <div class="csv-field-badge"><span class="csv-field-icon">🔄</span><span class="csv-field-name">reorder_level</span><small>Nivel reorden (def: 10)</small></div>
                    </div>
                    <!-- Zona drag & drop -->
                    <div class="csv-drop-zone" id="stockCsvDropZone" onclick="document.getElementById('csvFileInput').click()" ondragover="handleCsvDragOver(event,'stockCsvDropZone')" ondragleave="handleCsvDragLeave(event,'stockCsvDropZone')" ondrop="handleCsvDrop(event,'csvFileInput','stockCsvDropZone')">
                        <input type="file" id="csvFileInput" accept=".csv,.xls,.xlsx" style="display:none" onchange="onCsvFileSelected(this,'stockCsvDropZone','csvSelectedName')">
                        <div class="csv-drop-icon">📁</div>
                        <p class="csv-drop-title">Arrastra tu archivo CSV aquí</p>
                        <p class="csv-drop-sub" id="csvSelectedName">o haz clic para seleccionar (.csv)</p>
                    </div>
                    <!-- Acciones -->
                    <div class="csv-actions-row">
                        <button class="btn btn-primary" onclick="processCsvUpload()">⬆️ Cargar productos</button>
                        <a class="btn btn-ghost" href="/exports/plantilla_productos.csv" download="plantilla_productos.csv">⬇️ Descargar plantilla</a>
                    </div>
                    <!-- Barra de progreso -->
                    <div id="csvProgressWrap" style="display:none; margin-top:0.75rem;">
                        <div class="csv-progress-bar-bg"><div class="csv-progress-bar-fill" id="csvProgressFill" style="width:0%"></div></div>
                        <div id="csvProgress" class="csv-progress-label">Preparando...</div>
                    </div>
                </div>
                <div class="admin-section-subtitle">Gestión rápida (selección múltiple)</div>
                <div class="admin-quick-panel">
                    <div class="admin-quick-grid">
                        <div>
                            <select id="stockBulkSelect" class="admin-quick-select" multiple size="7"></select>
                            <small class="text-muted">Selecciona uno o varios productos para editar u ocultar.</small>
                        </div>
                        <div class="admin-quick-actions">
                            <button class="btn btn-secondary" type="button" onclick="editStockSelectedItem()">Editar seleccionado</button>
                            <button class="btn btn-danger" type="button" onclick="deleteStockSelectedItems()">Eliminar seleccionados</button>
                        </div>
                    </div>
                    <div id="stockQuickResult" class="text-muted" style="font-size:12px; margin-top:8px;"></div>
                </div>
                <div id="stockPagination" class="mt-3"></div>
                <div id="stockListCaption" class="admin-list-caption">Cargando productos...</div>
                <div id="stockRows"><p class="text-muted">Cargando...</p></div>
            </div></div>
        </section>

        <section id="updatesTab" class="tab-content admin-tab-panel">
            <div class="card mb-3 admin-editor-card"><div class="card-body">
                <h3>Noticias y promociones de portada</h3>
                <p class="text-muted">Administra el carrusel automático que se muestra en la página principal.</p>

                <input type="hidden" id="updateEditId" value="">

                <div class="grid grid-3">
                    <div class="form-group">
                        <label>Tipo</label>
                        <select id="updateType">
                            <option value="noticia">Noticia</option>
                            <option value="promocion">Promoción</option>
                            <option value="evento">Evento</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Orden</label>
                        <input id="updateOrder" type="number" min="0" step="1" value="0">
                    </div>
                    <div class="form-group">
                        <label>Visible en portada</label>
                        <select id="updateActive">
                            <option value="1">Sí</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                </div>

                <div class="form-group"><label>Título</label><input id="updateTitle" type="text" maxlength="220"></div>
                <div class="form-group"><label>Contenido</label><textarea id="updateBody" rows="4" maxlength="1200"></textarea></div>
                <div class="form-group"><label>Imagen (opcional)</label><input id="updateImage" type="file" accept="image/jpeg,image/png,image/webp,image/gif"></div>
                <div id="updateImagePreview" style="display: none; margin-top: 1rem;">
                    <p class="text-muted">Imagen previa:</p>
                    <img id="updateImagePreviewImg" src="" alt="Vista previa" style="max-width: 300px; border-radius: 8px; margin-top: 0.5rem;">
                </div>

                <div class="d-flex align-center" style="gap: 0.75rem; flex-wrap: wrap;">
                    <button class="btn btn-primary" type="button" onclick="saveHomepageUpdate()" id="updateSaveButton">Guardar publicación</button>
                    <button class="btn btn-secondary" type="button" onclick="resetUpdateForm()">Cancelar</button>
                </div>

                <div id="updateResult" class="mt-3"></div>
            </div></div>

            <div class="card"><div class="card-body">
                <h3>Publicaciones registradas</h3>
                <div id="updatesList" class="text-muted">Cargando publicaciones...</div>
            </div></div>
        </section>

        <section id="calendarTab" class="tab-content admin-tab-panel">
            <div class="grid grid-2">
                <div class="card"><div class="card-body">
                    <h3>Registrar visita de proveedor</h3>
                    <div class="form-group">
                        <label for="supplierName">Proveedor <span style="color: var(--theme-accent);">*</span></label>
                        <input id="supplierName" type="text" placeholder="Ej. Proveedor Truper Centro" required minlength="2" maxlength="100" oninput="clearValidationError('supplierName')">
                        <span class="validation-error" id="errSupplierName" style="color: #ef4444; font-size: 0.78rem; display: none; margin-top: 0.35rem; font-weight: 500;"></span>
                    </div>
                    <div class="form-group">
                        <label for="visitDate">Fecha y hora <span style="color: var(--theme-accent);">*</span></label>
                        <input id="visitDate" type="datetime-local" required oninput="clearValidationError('visitDate')">
                        <span class="validation-error" id="errVisitDate" style="color: #ef4444; font-size: 0.78rem; display: none; margin-top: 0.35rem; font-weight: 500;"></span>
                    </div>
                    <div class="form-group">
                        <label for="visitNotes">Notas (opcional)</label>
                        <textarea id="visitNotes" placeholder="Detalles u objetivos de la visita (máx. 500 caracteres)..." maxlength="500" oninput="clearValidationError('visitNotes')"></textarea>
                        <span class="validation-error" id="errVisitNotes" style="color: #ef4444; font-size: 0.78rem; display: none; margin-top: 0.35rem; font-weight: 500;"></span>
                    </div>
                    <button class="btn btn-primary" onclick="createVisit()">Guardar visita</button>
                </div></div>
                <div class="card"><div class="card-body">
                    <h3>Calendario mensual</h3>
                    <div class="d-flex justify-between align-center" style="margin-bottom: 1rem;">
                        <button class="btn btn-small btn-ghost" onclick="changeCalendarMonth(-1)">Mes anterior</button>
                        <strong id="calendarMonthLabel">Mes</strong>
                        <button class="btn btn-small btn-ghost" onclick="changeCalendarMonth(1)">Mes siguiente</button>
                    </div>
                    <div id="calendarGrid" class="mt-2"></div>
                </div></div>
            </div>

            <!-- Visitas agendadas a lo ancho abajo de las dos columnas -->
            <div class="card mt-3"><div class="card-body">
                <div id="calendarList" class="text-muted mt-2">Cargando...</div>
            </div></div>
        </section>

        <section id="supplierOrderTab" class="tab-content admin-tab-panel">
            <div class="card mb-3"><div class="card-body">
                <h3>Asignar producto a proveedor</h3>
                <p class="text-muted">Un mismo producto puede estar ligado a varios proveedores.</p>
                <div class="grid grid-4">
                    <div class="form-group">
                        <label>Producto</label>
                        <select id="spProduct"></select>
                    </div>
                    <div class="form-group"><label>Proveedor</label><input id="spSupplier" type="text" placeholder="Proveedor A"></div>
                    <div class="form-group"><label>SKU proveedor (opcional)</label><input id="spSupplierSku" type="text"></div>
                    <div class="form-group"><label>Costo unitario</label><input id="spUnitCost" type="number" min="0" step="0.01" value="0"></div>
                </div>
                <button class="btn btn-primary" onclick="createSupplierProductLink()">Guardar asignación</button>
                <div id="supplierProductResult" class="mt-2"></div>
                <div id="supplierProductList" class="mt-2 text-muted">Cargando asignaciones...</div>
            </div></div>

            <div class="card"><div class="card-body">
                <h3>Orden de Proveedor (ticket logistica)</h3>
                <div class="grid grid-2">
                    <div class="form-group"><label>Proveedor</label><input id="poSupplier" type="text"></div>
                    <div class="form-group"><label>Fecha recepcion</label><input id="poDate" type="date"></div>
                </div>
                <div class="grid grid-4">
                    <div class="form-group"><label>Producto proveedor</label><select id="poMappedProduct"></select></div>
                    <div class="form-group"><label>Cantidad</label><input id="poQty" type="number" min="1" value="1"></div>
                    <div class="form-group"><label>Costo estimado</label><input id="poCost" type="number" min="0" step="0.01" value="0"></div>
                    <div class="form-group"><label>&nbsp;</label><button class="btn btn-secondary" onclick="addMappedProductToOrder()">Agregar item</button></div>
                </div>
                <div id="poItems" class="mt-2"></div>
                <button class="btn btn-primary mt-2" onclick="createSupplierOrder()">Generar orden y ticket</button>

                <h4 class="mt-4">Ordenes registradas</h4>
                <table>
                    <thead><tr><th>Folio</th><th>Proveedor</th><th>Recepcion</th><th>Total</th><th>Ticket</th></tr></thead>
                    <tbody id="supplierRows"><tr><td colspan="5">Cargando...</td></tr></tbody>
                </table>
            </div></div>
        </section>

        <section id="clientsTab" class="tab-content admin-tab-panel">
            <div class="card"><div class="card-body">
                <h3>Registrar Cliente (Admin)</h3>
                <p class="text-muted">Crea clientes y genera su código único para identificación rápida.</p>

                <input type="hidden" id="clientEditId" value="">

                <div class="grid grid-2">
                    <div class="form-group"><label>Nombre</label><input id="clientFirstName" type="text"></div>
                    <div class="form-group"><label>Apellido</label><input id="clientLastName" type="text"></div>
                </div>
                <div class="grid grid-2">
                    <div class="form-group"><label>Teléfono (para login)</label><input id="clientPhone" type="text" placeholder="+52 33..."></div>
                    <div class="form-group"><label>Email (opcional)</label><input id="clientEmail" type="email" placeholder="cliente@email.com"></div>
                </div>
                <div class="grid grid-2">
                    <div class="form-group"><label>Empresa (opcional)</label><input id="clientCompany" type="text"></div>
                    <div class="form-group"><label>Fecha de nacimiento</label><input id="clientBirthdate" type="date" required></div>
                </div>

                <p class="text-muted">El cliente iniciará sesión con su código único y su fecha de nacimiento.</p>

                <div class="d-flex align-center" style="gap: 0.75rem; flex-wrap: wrap;">
                    <button class="btn btn-primary" onclick="saveClientByAdmin()" id="clientSaveButton">Registrar cliente</button>
                    <button class="btn btn-secondary" type="button" onclick="resetClientForm()">Cancelar</button>
                </div>

                <div id="clientCreateResult" class="mt-3"></div>
            </div></div>

            <div class="card mt-3"><div class="card-body">
                <h3>Clientes registrados</h3>
                <div id="clientListResult" class="text-muted">Cargando clientes...</div>
            </div></div>
        </section>

        <section id="historyTab" class="tab-content admin-tab-panel">
            <div class="card"><div class="card-body">
                <h3>Historico de Transacciones</h3>
                <table>
                    <thead><tr><th>Tipo</th><th>Folio/Ref</th><th>Fecha</th><th>Datos</th></tr></thead>
                    <tbody id="historyRows"><tr><td colspan="4">Cargando...</td></tr></tbody>
                </table>
            </div></div>
        </section>

        <section id="pricesTab" class="tab-content admin-tab-panel">
            <div class="card mb-3"><div class="card-body">
                <h3>Ajuste de Precios Masivo</h3>
                <p class="text-muted">Aplica un cambio de precio a múltiples productos. Usa % para porcentaje o $ para monto fijo.</p>
                
                <div class="grid grid-3 mt-2">
                    <div class="form-group">
                        <label>Tipo de ajuste</label>
                        <select id="priceAdjustType">
                            <option value="percentage">Porcentaje (%)</option>
                            <option value="fixed">Monto fijo ($)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Valor (ej: 10 o -5)</label>
                        <input id="priceAdjustValue" type="number" placeholder="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button class="btn btn-primary" onclick="applyPriceAdjustment()">Calcular preview</button>
                    </div>
                </div>

                <div class="form-group mt-2" style="position: relative;">
                    <label>Excluir productos</label>
                    <input id="priceExcludeSkus" type="hidden" value="">
                    
                    <!-- Selected product chips container -->
                    <div id="excludeProductChips" class="exclude-chips-container">
                        <span class="text-muted" style="font-size: 0.9rem; color: rgba(255, 255, 255, 0.4); padding-left: 0.25rem;">Ningún producto excluido</span>
                    </div>

                    <!-- Product search input -->
                    <div style="position: relative;">
                        <input id="excludeProductSearch" type="text" class="exclude-search-input" placeholder="🔍 Buscar producto por nombre o SKU para excluir..." oninput="filterExcludeProducts(this.value)" onfocus="showExcludeProductDropdown()">
                        
                        <!-- Floating dropdown list of matching products -->
                        <div id="excludeProductDropdown" class="exclude-dropdown">
                            <!-- Products will be dynamically populated here -->
                            <div class="text-muted text-center" style="padding: 0.5rem; color: rgba(255, 255, 255, 0.4);">Cargando productos...</div>
                        </div>
                    </div>
                </div>

                <div id="pricePreview" class="mt-3 p-2" style="background: var(--ui-surface-soft); border-radius: 8px; display: none;">
                    <h4>Preview del cambio:</h4>
                    <div id="pricePreviewContent"></div>
                    <button class="btn btn-primary mt-2" onclick="confirmPriceAdjustment()">Aplicar cambios</button>
                    <button class="btn btn-secondary mt-2" onclick="cancelPriceAdjustment()">Cancelar</button>
                </div>
                <div id="priceResult" class="mt-2"></div>
            </div></div>
        </section>

        <section id="marketplaceTab" class="tab-content admin-tab-panel">
            <div class="card mb-3 admin-editor-card admin-editor-card-marketplace"><div class="card-body">
                <div class="section-kicker section-kicker-marketplace">Marketplace CE</div>
                <h3>Marketplace CE - Gestión de artículos</h3>
                <p class="text-muted">Administra artículos de segunda mano: producto, condición, precio, stock, imagen y visibilidad.</p>

                <input type="hidden" id="marketplaceEditId" value="">

                <div class="grid grid-3">
                    <div class="form-group"><label>SKU CE (5 o 6 números)</label><input id="marketplaceSku" type="text" maxlength="6" inputmode="numeric" pattern="\d{5,6}" placeholder="Ej. 24061"><small id="marketplaceSkuStatus" class="text-muted">Debe ser único y de 5 o 6 números.</small></div>
                    <div class="form-group"><label>Nombre</label><input id="marketplaceName" type="text" maxlength="220"></div>
                    <div class="form-group">
                        <label>Condición</label>
                        <select id="marketplaceCondition">
                            <option value="Seminuevo">Seminuevo</option>
                            <option value="Usado">Usado</option>
                            <option value="Reacondicionado">Reacondicionado</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Categorías CE (selección múltiple)</label>
                    <div class="category-panel">
                        <div class="category-panel-title">Categorías para Marketplace</div>
                        <select id="marketplaceCategory" multiple size="6"></select>
                        <small class="text-muted">Las categorías se comparten con Stock. Gestión en la pestaña Categorías.</small>
                    </div>
                </div>

                <div class="grid grid-3">
                    <div class="form-group"><label>Precio</label><input id="marketplacePrice" type="number" min="0" step="0.01" value="0"></div>
                    <div class="form-group"><label>Stock</label><input id="marketplaceStock" type="number" min="0" step="1" value="1"></div>
                    <div class="form-group"><label>Visibilidad CE</label><select id="marketplaceActive"><option value="1">✅ Visible en Marketplace</option><option value="0">🔒 Oculto (revisar antes de publicar)</option></select></div>
                </div>

                <div class="form-group"><label>Descripción</label><textarea id="marketplaceDescription" rows="4" maxlength="1800"></textarea></div>

                <div class="grid grid-2 mt-2">
                    <div class="form-group">
                        <label>Subir nuevas imágenes</label>
                        <input id="marketplaceImages" type="file" accept="image/*" multiple style="margin-bottom: 0.5rem;">
                        <button class="btn btn-secondary btn-small" type="button" onclick="uploadMarketplaceImages()">Cargar e integrar a galería</button>
                        <small class="text-muted">Al seleccionar archivos se subirán automáticamente.</small>
                    </div>
                    <div class="form-group">
                        <label>Imagen de referencia existente (Evita duplicidad)</label>
                        <select id="marketplaceImageRef" onchange="updateMarketplacePreview()">
                            <option value="images/products/default-product.svg">Por defecto (Logo Truper)</option>
                        </select>
                        <small class="text-muted">Usa una imagen compartida que ya esté en el servidor para optimizar espacio.</small>
                    </div>
                </div>

                <div class="form-group">
                    <label>Galería del artículo CE (por código)</label>
                    <small class="text-muted">Sube varias imágenes para este SKU CE, acomódalas por orden, define portada y elimina las que no necesites.</small>
                    <div id="marketplaceGalleryStatus" class="text-muted" style="margin-top:6px;">Escribe un código de 5 números para cargar su galería CE.</div>
                    <div id="marketplaceGalleryList" style="display:grid; grid-template-columns:repeat(auto-fill,minmax(120px,1fr)); gap:0.6rem; margin-top:0.6rem;"></div>
                </div>

                <div class="admin-preview-wrap">
                    <p class="admin-list-caption">Vista previa (estilo portada):</p>
                    <div id="marketplacePreviewHost" class="catalog-grid-min"></div>
                </div>

                <div class="d-flex align-center" style="gap: 0.75rem; flex-wrap: wrap; margin-top: 0.75rem;">
                    <button class="btn btn-primary" type="button" id="marketplaceSaveButton" onclick="saveMarketplaceCeByAdmin()">Guardar artículo CE</button>
                    <button class="btn btn-secondary" type="button" onclick="resetMarketplaceForm()">Cancelar</button>
                </div>

                <div id="marketplaceResult" class="mt-2"></div>
            </div></div>

            <div class="card"><div class="card-body">
                <h3>Artículos CE registrados</h3>
                <div class="admin-search-row">
                    <input id="marketplaceSearch" type="text" placeholder="Buscar por SKU, nombre o condición...">
                </div>
                <div class="admin-section-subtitle">Gestión rápida (selección múltiple)</div>
                <div class="admin-quick-panel">
                    <div class="admin-quick-grid">
                        <div>
                            <select id="marketplaceBulkSelect" class="admin-quick-select" multiple size="7"></select>
                            <small class="text-muted">Selecciona uno o varios artículos CE para editar o eliminar.</small>
                        </div>
                        <div class="admin-quick-actions">
                            <button class="btn btn-secondary" type="button" onclick="editMarketplaceSelectedItem()">Editar seleccionado</button>
                            <button class="btn btn-danger" type="button" onclick="deleteMarketplaceSelectedItems()">Eliminar seleccionados</button>
                        </div>
                    </div>
                    <div id="marketplaceQuickResult" class="text-muted" style="font-size:12px; margin-top:8px;"></div>
                </div>
                
                <div class="admin-quick-panel mt-3">
                    <div class="admin-section-subtitle">📤 Carga Masiva CE (CSV)</div>
                    <div class="csv-upload-panel" id="mktCsvPanel">
                        <!-- Columnas requeridas Marketplace -->
                        <div class="csv-fields-grid">
                            <div class="csv-field-badge required"><span class="csv-field-icon">🔑</span><span class="csv-field-name">sku</span><small>Código 5-6 dígitos</small></div>
                            <div class="csv-field-badge required"><span class="csv-field-icon">📝</span><span class="csv-field-name">name</span><small>Nombre artículo</small></div>
                            <div class="csv-field-badge"><span class="csv-field-icon">🏷️</span><span class="csv-field-name">category</span><small>Categoría</small></div>
                            <div class="csv-field-badge"><span class="csv-field-icon">📄</span><span class="csv-field-name">description</span><small>Descripción</small></div>
                            <div class="csv-field-badge required"><span class="csv-field-icon">🔍</span><span class="csv-field-name">condition_label</span><small>Seminuevo/Usado/Reacond.</small></div>
                            <div class="csv-field-badge required"><span class="csv-field-icon">💲</span><span class="csv-field-name">unit_price</span><small>Precio</small></div>
                            <div class="csv-field-badge"><span class="csv-field-icon">📦</span><span class="csv-field-name">stock_quantity</span><small>Cantidad (def: 1)</small></div>
                        </div>
                        <!-- Zona drag & drop -->
                        <div class="csv-drop-zone" id="mktCsvDropZone" onclick="document.getElementById('marketplaceCsvFileInput').click()" ondragover="handleCsvDragOver(event,'mktCsvDropZone')" ondragleave="handleCsvDragLeave(event,'mktCsvDropZone')" ondrop="handleCsvDrop(event,'marketplaceCsvFileInput','mktCsvDropZone')">
                            <input type="file" id="marketplaceCsvFileInput" accept=".csv,.xls,.xlsx" style="display:none" onchange="onCsvFileSelected(this,'mktCsvDropZone','mktCsvSelectedName')">
                            <div class="csv-drop-icon">📁</div>
                            <p class="csv-drop-title">Arrastra tu archivo CSV aquí</p>
                            <p class="csv-drop-sub" id="mktCsvSelectedName">o haz clic para seleccionar (.csv)</p>
                        </div>
                        <!-- Acciones -->
                        <div class="csv-actions-row">
                            <button class="btn btn-primary" onclick="processMarketplaceCsvUpload()">⬆️ Cargar artículos CE</button>
                            <a class="btn btn-ghost" href="/exports/plantilla_marketplace_ce.csv" download="plantilla_marketplace_ce.csv">⬇️ Descargar plantilla CE</a>
                        </div>
                        <!-- Barra de progreso -->
                        <div id="mktCsvProgressWrap" style="display:none; margin-top:0.75rem;">
                            <div class="csv-progress-bar-bg"><div class="csv-progress-bar-fill" id="mktCsvProgressFill" style="width:0%"></div></div>
                            <div id="marketplaceCsvProgress" class="csv-progress-label">Preparando...</div>
                        </div>
                    </div>
                </div>
                <div id="marketplacePagination" class="mt-3"></div>
                <div id="marketplaceListCaption" class="admin-list-caption">Cargando artículos CE...</div>
                <div id="marketplaceList" class="text-muted">Cargando artículos CE...</div>
        </section>

        <section id="categoriesTab" class="tab-content admin-tab-panel">
            <div class="grid grid-2">
                <div class="card">
                    <div class="card-body">
                        <h3>Administrar Categorías</h3>
                        <p class="text-muted">Crea o edita las categorías generales del catálogo.</p>
                        <input type="hidden" id="categoryEditId" value="">
                        <div class="form-group">
                            <label>Nombre de la Categoría</label>
                            <input id="categoryName" type="text" placeholder="Ej. Material eléctrico" maxlength="120">
                        </div>
                        <div class="grid grid-2">
                            <div class="form-group">
                                <label>Orden de clasificación</label>
                                <input id="categoryOrder" type="number" min="0" max="999" value="0">
                            </div>
                            <div class="form-group">
                                <label>Estado</label>
                                <select id="categoryActive">
                                    <option value="1">Activo</option>
                                    <option value="0">Inactivo</option>
                                </select>
                            </div>
                        </div>
                        <div class="d-flex align-center" style="gap: 0.75rem; flex-wrap: wrap; margin-top: 1.5rem;">
                            <button class="btn btn-primary" id="categorySaveButton" onclick="saveCategoryByAdmin()">Guardar categoría</button>
                            <button class="btn btn-secondary" type="button" onclick="resetCategoryForm()">Cancelar</button>
                        </div>
                        <div id="categoryResult" class="mt-2"></div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <h3>Categorías Registradas</h3>
                        <div id="categoriesList" class="text-muted">Cargando categorías...</div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</main>

<script src="js/main.js"></script>
<script>
// CSRF token for API requests
window.csrfToken = '<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>';
let supplierOrderItems = [];
let stockItemsCache = [];
let marketplaceItemsCache = [];
let stockGalleryCache = [];
let marketplaceGalleryCache = [];
let marketplaceCurrentPage = 1;
let marketplacePagination = null;
const marketplacePerPage = 50;
const galleryStateCache = {
    stock: { sku: '', images: [], cover: '' },
    marketplace: { sku: '', images: [], cover: '' }
};

function escapeHtml(v) {
    return String(v || '').replace(/[&<>"']/g, function(m) {
        return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m];
    });
}

function displayProductCode(rawSku) {
    return String(rawSku || '').replace(/^XLS-/i, '');
}

function normalizeNumericSku(rawValue) {
    return String(rawValue || '').replace(/\D+/g, '').slice(0, 6);
}

function isValidNumericSku(sku) {
    return /^\d{5,6}$/.test(String(sku || '').trim());
}

function formatAdminMoney(value) {
    const amount = Number(value || 0);
    return `$${amount.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

const skuCheckVersion = { product: 0, marketplace: 0 };

function getSafeImageSrc(src) {
    if (!src || typeof src !== 'string') return 'images/products/default-product.svg';
    const s = src.trim();
    if (s === '') return 'images/products/default-product.svg';
    // Accept data URIs (base64), http(s) URLs, and relative paths
    if (s.startsWith('data:') || s.startsWith('http://') || s.startsWith('https://') || s.startsWith('/') || s.startsWith('images/')) {
        return s;
    }
    return 'images/products/default-product.svg';
}

// Helper: upsert a product into the stock cache and re-render quickly
function upsertStockCache(product) {
    if (!product) return;
    const normalizedSku = normalizeNumericSku(product.sku || '');
    let idx = -1;

    if (Number(product.id) > 0) {
        idx = stockItemsCache.findIndex((p) => Number(p.id) === Number(product.id));
    }

    if (idx < 0 && /^\d{5,6}$/.test(normalizedSku)) {
        idx = stockItemsCache.findIndex((p) => normalizeNumericSku(p.sku || '') === normalizedSku);
    }

    if (idx >= 0) {
        stockItemsCache[idx] = Object.assign({}, stockItemsCache[idx], product, {
            id: Number(product.id) > 0 ? Number(product.id) : Number(stockItemsCache[idx].id || 0)
        });
    } else {
        // insert at the top for immediate visibility
        stockItemsCache.unshift(product);
    }
    try {
        renderStockList();
        // don't always re-fetch pagination server-side; keep user on current page
        renderStockPagination({ current_page: stockCurrentPage, total_pages: Math.max(1, Math.ceil(stockItemsCache.length / (stockPerPage || 25))), total_items: stockItemsCache.length });
    } catch (e) {
        console.warn('Failed to render stock after upsert:', e);
    }
}

// Helper: upsert a CE item into the marketplace cache and re-render quickly
function upsertMarketplaceCache(item) {
    if (!item) return;
    const normalizedSku = normalizeNumericSku(item.sku || '');
    let idx = -1;

    if (Number(item.id) > 0) {
        idx = marketplaceItemsCache.findIndex((p) => Number(p.id) === Number(item.id));
    }

    if (idx < 0 && /^\d{5,6}$/.test(normalizedSku)) {
        idx = marketplaceItemsCache.findIndex((p) => normalizeNumericSku(p.sku || '') === normalizedSku);
    }

    if (idx >= 0) {
        marketplaceItemsCache[idx] = Object.assign({}, marketplaceItemsCache[idx], item, {
            id: Number(item.id) > 0 ? Number(item.id) : Number(marketplaceItemsCache[idx].id || 0)
        });
    } else {
        marketplaceItemsCache.unshift(item);
    }
    try {
        renderMarketplaceList();
        renderMarketplacePagination();
    } catch (e) {
        console.warn('Failed to render marketplace after upsert:', e);
    }
}

function renderAdminProductCard(item, mode = 'stock', withActions = true) {
    const id = Number(item.id || 0);
    const sku = displayProductCode(item.sku || '');
    const name = String(item.name || 'Sin nombre');
    const category = String(item.category || (mode === 'marketplace' ? 'Marketplace CE' : 'General'));
    const description = String(item.description || 'Descripción pendiente');
    const imageUrl = String(item.image_url || 'images/products/default-product.svg');
    const unitPrice = Number(item.unit_price || 0);
    const stock = Math.max(0, Number(item.stock_quantity || 0));
    const reorder = Math.max(0, Number(item.reorder_level || 10));
    const condition = mode === 'marketplace' ? String(item.condition_label || 'Seminuevo') : 'Modelo Estandar';
    const stockText = stock <= (mode === 'marketplace' ? 2 : reorder) ? 'Stock bajo: ' : 'Stock: ';
    const stockClass = stock <= (mode === 'marketplace' ? 2 : reorder) ? 'stock-low' : 'stock-ok';
    const inactive = Number(item.is_active) === 0 || item.is_active === false || item.is_active === 'f' || item.is_active === 'false' || item.is_active === 'False' || item.is_active === 'FALSE';
    const seedOnly = Boolean(item.seed_only || item.__seed_only);
    const stateLabel = inactive ? 'Oculto' : 'Visible';
    const stateBadgeClass = inactive ? 'badge-danger' : 'badge-success';
        const actions = mode === 'marketplace' ? `
            <button class="btn btn-small btn-secondary" type="button" onclick="fillMarketplaceFormById(${id})">Editar</button>
            <button class="btn btn-small btn-ghost" type="button" onclick="toggleMarketplaceVisibility(${id}, ${inactive ? 1 : 0})">${inactive ? 'Mostrar' : 'Ocultar'}</button>
            <button class="btn btn-small btn-danger" type="button" onclick="deleteMarketplaceCeByAdmin(${id})">Eliminar</button>
        ` : (seedOnly ? `
            <button class="btn btn-small btn-secondary" type="button" onclick="prepareSeedProductForEditing(${id})">Editar</button>
            <span class="text-muted" style="font-size:12px;">Guárdalo para convertirlo en editable</span>
        ` : `
            <button class="btn btn-small btn-secondary" type="button" onclick="fillProductFormById(${id})">Editar</button>
            <button class="btn btn-small btn-ghost" type="button" onclick="toggleStockVisibility(${id}, ${inactive ? 1 : 0})">${inactive ? 'Mostrar' : 'Ocultar'}</button>
            <button class="btn btn-small btn-danger" type="button" onclick="deleteProductByAdmin(${id})">Eliminar</button>
        `);

    return `
        <article class="product-card-min ${inactive ? 'product-card-inactive' : ''}">
            <div class="product-media">
                <img class="product-gallery-image active" src="${getSafeImageSrc(imageUrl)}" alt="${escapeHtml(name)}" loading="lazy">
            </div>
            <div class="product-content">
                <div class="catalog-tag">${escapeHtml(category)}</div>
                <div class="product-code-label"><strong>Código:</strong> <strong>${escapeHtml(sku)}</strong></div>
                <h3 class="product-title">${escapeHtml(name)}</h3>
                <p class="product-spec">${escapeHtml(description)}</p>
                <div><span class="variant-pill">${escapeHtml(condition)}</span></div>
                <span class="stock-badge ${stockClass}">${stockText}${stock}</span>
                <div style="margin-top:4px;"><span class="badge ${stateBadgeClass}">Estado: ${stateLabel}</span></div>
                <div class="catalog-price">${formatAdminMoney(unitPrice)}</div>
                ${withActions ? `<div class="product-actions">${actions}</div>` : '<div class="text-muted" style="font-size:12px;margin-top:8px;">Vista previa del diseño en portada.</div>'}
                ${inactive ? '<div class="text-muted" style="font-size:12px;margin-top:6px;">Producto oculto/desactivado.</div>' : ''}
            </div>
        </article>
    `;
}

function extractGalleryImagesFromItem(item) {
    if (!item || typeof item !== 'object') return [];

    const parsed = [];
    const rawVariants = item.variants_json;
    if (Array.isArray(rawVariants)) {
        rawVariants.forEach((img) => {
            const value = String(img || '').trim();
            if (value && !value.includes('default-product.svg')) parsed.push(value);
        });
    } else if (typeof rawVariants === 'string' && rawVariants.trim() !== '') {
        try {
            const decoded = JSON.parse(rawVariants);
            if (Array.isArray(decoded)) {
                decoded.forEach((img) => {
                    const value = String(img || '').trim();
                    if (value && !value.includes('default-product.svg')) parsed.push(value);
                });
            }
        } catch (error) {
            // Ignore invalid legacy JSON and fallback to image_url.
        }
    }

    const unique = [];
    parsed.forEach((img) => {
        if (!unique.includes(img)) unique.push(img);
    });
    return unique;
}

function updateStockPreview() {
    const host = document.getElementById('stockPreviewHost');
    if (!host) return;
    const sku = normalizeNumericSku(document.getElementById('newProductSku')?.value || '');
    const selectedCategoryOptions = Array.from(document.getElementById('newProductCategory')?.selectedOptions || []);
    const selectedCategories = selectedCategoryOptions.map((option) => option.value).filter(Boolean);
    const rawImg = document.getElementById('newProductImageRef')?.value || 'images/products/default-product.svg';
    const coverImg = (rawImg && !rawImg.startsWith('/') && !rawImg.startsWith('blob:') && !rawImg.startsWith('http')) ? '/' + rawImg : rawImg;
    // Try gallery cache cover first
    const galleryState = getGalleryState('stock');
    const galleryCover = (galleryState && galleryState.cover) ? galleryState.cover : '';
    const finalImg = galleryCover && !galleryCover.includes('default-product.svg') ? galleryCover : coverImg;
    const normFinalImg = (finalImg && !finalImg.startsWith('/') && !finalImg.startsWith('blob:') && !finalImg.startsWith('http')) ? '/' + finalImg : finalImg;
    const item = {
        id: 0,
        sku: sku || '00000',
        name: document.getElementById('newProductName')?.value || 'Nombre del producto',
        category: selectedCategories.join(', ') || 'General',
        description: document.getElementById('newProductDescription')?.value || 'Descripción pendiente',
        unit_price: document.getElementById('newProductPrice')?.value || 0,
        stock_quantity: document.getElementById('newProductStock')?.value || 0,
        reorder_level: document.getElementById('newProductReorder')?.value || 10,
        image_url: normFinalImg,
        is_active: Number(document.getElementById('newProductVisible')?.value || 1)
    };
    host.innerHTML = renderAdminProductCard(item, 'stock', false);
}

function updateMarketplacePreview() {
    const host = document.getElementById('marketplacePreviewHost');
    if (!host) return;
    const sku = normalizeNumericSku(document.getElementById('marketplaceSku')?.value || '');
    const selectedCategoryOptions = Array.from(document.getElementById('marketplaceCategory')?.selectedOptions || []);
    const selectedCategories = selectedCategoryOptions.map((option) => option.value).filter(Boolean);
    const rawImg = document.getElementById('marketplaceImageRef')?.value || 'images/products/default-product.svg';
    const coverImg = (rawImg && !rawImg.startsWith('/') && !rawImg.startsWith('blob:') && !rawImg.startsWith('http')) ? '/' + rawImg : rawImg;
    const galleryState = getGalleryState('marketplace');
    const galleryCover = (galleryState && galleryState.cover) ? galleryState.cover : '';
    const finalImg = galleryCover && !galleryCover.includes('default-product.svg') ? galleryCover : coverImg;
    const normFinalImg = (finalImg && !finalImg.startsWith('/') && !finalImg.startsWith('blob:') && !finalImg.startsWith('http')) ? '/' + finalImg : finalImg;
    const item = {
        id: Number(document.getElementById('marketplaceEditId')?.value || 0),
        sku: sku || '00000',
        name: document.getElementById('marketplaceName')?.value || 'Artículo CE',
        category: selectedCategories.join(', ') || 'Marketplace CE',
        description: document.getElementById('marketplaceDescription')?.value || 'Descripción pendiente',
        condition_label: document.getElementById('marketplaceCondition')?.value || 'Seminuevo',
        unit_price: document.getElementById('marketplacePrice')?.value || 0,
        stock_quantity: document.getElementById('marketplaceStock')?.value || 1,
        image_url: normFinalImg,
        is_active: Number(document.getElementById('marketplaceActive')?.value || 1)
    };
    host.innerHTML = renderAdminProductCard(item, 'marketplace', false);
}

function normalizeCategoryValue(value) {
    return String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
}

function findCategoryOption(selectEl, categoryName) {
    const normalizedName = normalizeCategoryValue(categoryName);
    if (!normalizedName) return null;

    return Array.from(selectEl?.options || []).find((option) =>
        normalizeCategoryValue(option.value) === normalizedName
    ) || null;
}

function ensureCategoryOptions(selectEl, categories, selected = true) {
    if (!selectEl || !Array.isArray(categories)) return;

    categories.forEach((categoryName) => {
        const normalizedName = normalizeCategoryValue(categoryName);
        if (!normalizedName) return;

        let option = findCategoryOption(selectEl, categoryName);
        if (!option) {
            option = document.createElement('option');
            option.value = String(categoryName).trim();
            option.textContent = String(categoryName).trim();
            selectEl.appendChild(option);
        }

        if (selected) {
            option.selected = true;
        }
    });
}

function setCategorySelections(selectEl, categories) {
    if (!selectEl) return;

    const normalizedCategories = new Set(
        Array.isArray(categories)
            ? categories.map((category) => normalizeCategoryValue(category)).filter(Boolean)
            : []
    );

    Array.from(selectEl.options || []).forEach((option) => {
        option.selected = normalizedCategories.has(normalizeCategoryValue(option.value));
    });
}

function setSkuStatus(statusId, message, tone = 'muted') {
    const el = document.getElementById(statusId);
    if (!el) return;
    el.textContent = message;
    if (tone === 'success') {
        el.style.color = '#15803d';
    } else if (tone === 'error') {
        el.style.color = '#b91c1c';
    } else if (tone === 'warning') {
        el.style.color = '#b45309';
    } else {
        el.style.color = 'var(--ui-text-muted)';
    }
}

async function validateSkuAvailability(kind, options = {}) {
    const isMarketplace = kind === 'marketplace';
    const skuInput = document.getElementById(isMarketplace ? 'marketplaceSku' : 'newProductSku');
    const statusId = isMarketplace ? 'marketplaceSkuStatus' : 'newProductSkuStatus';
    const sku = normalizeNumericSku(skuInput?.value || '');
    if (skuInput) skuInput.value = sku;

    if (!sku) {
        setSkuStatus(statusId, 'Código listo para guardar.', 'muted');
        return false;
    }

    if (!/^\d{5,6}$/.test(sku)) {
        setSkuStatus(statusId, 'El código debe tener 5 o 6 números.', 'warning');
        return false;
    }

    const currentId = isMarketplace
        ? Number(document.getElementById('marketplaceEditId').value || 0)
        : Number(document.getElementById('newProductEditId').value || 0);

    // Evitar consultar API si el SKU ingresado coincide exactamente con el original del registro que editamos
    if (currentId > 0) {
        const cache = isMarketplace ? marketplaceItemsCache : stockItemsCache;
        const item = cache.find((row) => Number(row.id) === currentId);
        if (item && normalizeNumericSku(item.sku || '') === sku) {
            setSkuStatus(statusId, isMarketplace ? 'Editando artículo CE existente.' : 'Editando producto existente.', 'muted');
            return true;
        }
    }

    setSkuStatus(statusId, 'Verificando en la base de datos...', 'muted');
    const version = ++skuCheckVersion[kind];
    const allowSeedSku = !isMarketplace && Number(document.getElementById('newProductSeedMode')?.value || 0) === 1;
    const endpoint = isMarketplace
        ? `/admin_supply.php?action=marketplace-sku-check&sku=${encodeURIComponent(sku)}&id=${encodeURIComponent(currentId)}`
        : `/admin_supply.php?action=product-sku-check&sku=${encodeURIComponent(sku)}&id=${encodeURIComponent(currentId)}&allow_seed=${allowSeedSku ? '1' : '0'}`;

    const check = await apiCall(endpoint, 'GET', null, { silent: true });
    if (version !== skuCheckVersion[kind]) {
        return false;
    }

    if (!check || !check.success) {
        setSkuStatus(statusId, 'No fue posible verificar el código en la base de datos. Intenta de nuevo.', 'warning');
        return false;
    }

    if (check.available === false) {
        // If server indicates the collision is only with the seed catalog, offer to mark SKU as deleted
        if (check.seed_only) {
            const btnId = `${statusId}-mark-deleted`;
            const msg = (check.message || 'Ese código ya existe en el catálogo base') + ` `;
            const el = document.getElementById(statusId);
            if (el) {
                el.innerHTML = '';
                const span = document.createElement('span');
                span.style.color = '#b91c1c';
                span.textContent = msg;
                el.appendChild(span);

                const btn = document.createElement('button');
                btn.id = btnId;
                btn.type = 'button';
                btn.className = 'btn btn-small btn-danger';
                btn.style.marginLeft = '8px';
                btn.textContent = 'Marcar como eliminado';
                btn.onclick = async function () {
                    btn.disabled = true;
                    const res = await apiCall('/admin_supply.php?action=mark-sku-deleted', 'POST', { sku: sku });
                    if (res && res.success) {
                        setSkuStatus(statusId, 'Código marcado como eliminado. Ahora está disponible para creación.', 'success');
                        // re-run check to refresh state
                        await validateSkuAvailability(kind, options);
                    } else {
                        setSkuStatus(statusId, (res && res.message) ? res.message : 'No fue posible marcar como eliminado', 'error');
                    }
                };
                el.appendChild(btn);
            }
            return false;
        }

        setSkuStatus(statusId, check.message || 'Código ya registrado.', 'error');
        return false;
    }

    setSkuStatus(statusId, 'Código disponible.', 'success');
    return true;
}

function displayProductLabel(rawSku, name) {
    const code = displayProductCode(rawSku);
    const cleanName = String(name || '').trim();
    if (code && cleanName) {
        return `${code} | ${cleanName}`;
    }
    return code || cleanName || 'Sin producto';
}

function displayClientCode(rawCode) {
    return String(rawCode || '').replace(/\D+/g, '');
}

function normalizeUpdateTypeLabel(type) {
    if (type === 'promocion') return 'Promoción';
    if (type === 'evento') return 'Evento';
    return 'Noticia';
}

function isTruthyFlag(value) {
    if (value === true || value === 1) return true;
    const raw = String(value ?? '').trim().toLowerCase();
    return ['1', 'true', 't', 'yes', 'y', 'on'].includes(raw);
}

function activateAdminSupplyTab(tabName, scrollTargetId = '') {
    const tabButton = document.querySelector(`[data-tab="${tabName}"]`);
    if (tabButton) {
        tabButton.click();
    }

    const target = scrollTargetId ? document.getElementById(scrollTargetId) : null;
    if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        return;
    }

    if (tabButton) {
        tabButton.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

// ===== PRODUCT VISIBILITY FUNCTIONS =====
let allProductsVisibility = [];

async function loadProductsVisibility() {
    try {
        const res = await fetch('/api/products.php?action=list-all&visibility=1');
        const data = await res.json();
        if (!data.success || !Array.isArray(data.items)) {
            console.error('Failed to load products');
            return;
        }
        allProductsVisibility = data.items || [];
        renderVisibilityList(allProductsVisibility);
    } catch (e) {
        console.error('Error loading visibility:', e);
    }
}

function renderVisibilityList(products) {
    const container = document.getElementById('visibilityList');
    if (!products || products.length === 0) {
        container.innerHTML = '<p class="text-muted">No hay productos.</p>';
        return;
    }

    container.innerHTML = `
        <table style="width: 100%;">
            <thead>
                <tr style="border-bottom: 1px solid var(--ui-border);">
                    <th style="padding: 0.75rem; text-align: left;">Código</th>
                    <th style="padding: 0.75rem; text-align: left;">Producto</th>
                    <th style="padding: 0.75rem; text-align: center;">Visible</th>
                    <th style="padding: 0.75rem; text-align: center;">Acción</th>
                </tr>
            </thead>
            <tbody>
                ${products.map(p => `
                    <tr style="border-bottom: 1px solid var(--ui-border-soft);" data-product-id="${p.id}">
                        <td style="padding: 0.75rem;">${escapeHtml(displayProductCode(p.sku))}</td>
                        <td style="padding: 0.75rem;">${escapeHtml(p.name)}</td>
                        <td style="padding: 0.75rem; text-align: center;">
                            <span class="badge ${p.is_active ? 'badge-success' : 'badge-danger'}">
                                ${p.is_active ? 'Visible' : 'Oculto'}
                            </span>
                        </td>
                        <td style="padding: 0.75rem; text-align: center;">
                            <button class="btn btn-small ${p.is_active ? 'btn-danger' : 'btn-success'}" onclick="toggleProductVisibility(${p.id}, !${p.is_active ? 1 : 0})">
                                ${p.is_active ? 'Ocultar' : 'Activar'}
                            </button>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

function filterVisibilityProducts() {
    const query = (document.getElementById('visibilitySearch').value || '').toLowerCase();
    const filtered = allProductsVisibility.filter(p => {
        const code = displayProductCode(p.sku).toLowerCase();
        const name = (p.name || '').toLowerCase();
        return code.includes(query) || name.includes(query);
    });
    renderVisibilityList(filtered);
}

async function toggleProductVisibility(productId, newState) {
    try {
        const res = await fetch('/api/products.php?action=toggle-visibility', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: productId, is_active: newState })
        });
        const data = await res.json();
        if (data.success) {
            const idx = allProductsVisibility.findIndex(p => p.id === productId);
            if (idx >= 0) {
                allProductsVisibility[idx].is_active = newState;
                filterVisibilityProducts();
            }
            window.showAlert?.apply(null, [data.message || 'Actualizado', 'success']);
            activateAdminSupplyTab('visibilityTab', 'visibilityList');
        } else {
            window.showAlert?.apply(null, ['Error: ' + (data.message || 'desconocido'), 'error']);
        }
    } catch (e) {
        console.error('Error toggling visibility:', e);
        window.showAlert?.apply(null, ['Error de conexión', 'error']);
    }
}

// ===== PRICE ADJUSTMENT FUNCTIONS =====
let pricePreviewData = null;

async function applyPriceAdjustment() {
    const type = document.getElementById('priceAdjustType').value;
    const value = parseFloat(document.getElementById('priceAdjustValue').value || 0);
    const excludeSkus = (document.getElementById('priceExcludeSkus').value || '').split(',').map(s => s.trim().toUpperCase()).filter(s => s);

    if (value === 0 || isNaN(value)) {
        document.getElementById('pricePreview').style.display = 'none';
        return;
    }

    try {
        const res = await fetch('/api/products.php?action=preview-price-adjustment', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type, value, exclude_skus: excludeSkus })
        });
        const data = await res.json();
        if (!data.success || !Array.isArray(data.preview)) {
            console.error('Failed to preview prices');
            return;
        }

        pricePreviewData = { type, value, exclude_skus: excludeSkus, affected: data.count || 0 };
        const preview = data.preview.slice(0, 5);
        
        const content = document.getElementById('pricePreviewContent');
        content.innerHTML = `
            <p><strong>Cambios a aplicar: ${pricePreviewData.affected} productos</strong></p>
            <table style="width: 100%; font-size: 0.9rem; margin-top: 1rem;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--ui-border);">
                        <th style="padding: 0.5rem; text-align: left;">Producto</th>
                        <th style="padding: 0.5rem; text-align: right;">Precio actual</th>
                        <th style="padding: 0.5rem; text-align: right;">Nuevo precio</th>
                    </tr>
                </thead>
                <tbody>
                    ${preview.map(item => `
                        <tr style="border-bottom: 1px solid var(--ui-border-soft);">
                            <td style="padding: 0.5rem;">${escapeHtml(item.name)}</td>
                            <td style="padding: 0.5rem; text-align: right;">${formatAdminMoney(item.current_price)}</td>
                            <td style="padding: 0.5rem; text-align: right; color: var(--color-naranja); font-weight: 600;">${formatAdminMoney(item.new_price)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
            ${data.count > 5 ? `<p class="text-muted" style="font-size: 0.85rem; margin-top: 1rem;">+ ${data.count - 5} productos más...</p>` : ''}
        `;
        document.getElementById('pricePreview').style.display = 'block';
    } catch (e) {
        console.error('Error generating price preview:', e);
    }
}

function cancelPriceAdjustment() {
    document.getElementById('pricePreview').style.display = 'none';
    document.getElementById('priceAdjustValue').value = '';
    pricePreviewData = null;
    excludedProductsMap.clear();
    updateExcludedChips();
}

async function confirmPriceAdjustment() {
    if (!pricePreviewData) return;

    try {
        const res = await fetch('/api/products.php?action=apply-price-adjustment', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(pricePreviewData)
        });
        const data = await res.json();
        const resultBox = document.getElementById('priceResult');
        if (data.success) {
            resultBox.innerHTML = `<div class="alert alert-success">${escapeHtml(data.message || 'Precios actualizados exitosamente')}</div>`;
            setTimeout(() => {
                document.getElementById('pricePreview').style.display = 'none';
                cancelPriceAdjustment();
            }, 2000);
        } else {
            resultBox.innerHTML = `<div class="alert alert-error">${escapeHtml(data.message || 'Error al actualizar precios')}</div>`;
        }
    } catch (e) {
        console.error('Error applying price adjustment:', e);
        document.getElementById('priceResult').innerHTML = '<div class="alert alert-error">Error de conexión</div>';
    }
}

// Bulk Price Adjustment - Product Exclusion Search and Selection Logic
let excludedProductsMap = new Map();
let activeSearchTimeout = null;

function showExcludeProductDropdown() {
    const dropdown = document.getElementById('excludeProductDropdown');
    if (dropdown) {
        dropdown.style.display = 'block';
        const val = document.getElementById('excludeProductSearch').value;
        filterExcludeProducts(val);
    }
}

// Close dropdown on click outside or escape key
document.addEventListener('click', function(e) {
    const container = document.getElementById('excludeProductSearch')?.closest('.form-group');
    if (container && !container.contains(e.target)) {
        const dropdown = document.getElementById('excludeProductDropdown');
        if (dropdown) dropdown.style.display = 'none';
    }
});

const excludeSearchEl = document.getElementById('excludeProductSearch');
if (excludeSearchEl) {
    excludeSearchEl.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const dropdown = document.getElementById('excludeProductDropdown');
            if (dropdown) dropdown.style.display = 'none';
            this.blur();
        }
    });
}

// Event delegation for selecting products to exclude from the dropdown list
const excludeDropdownEl = document.getElementById('excludeProductDropdown');
if (excludeDropdownEl) {
    excludeDropdownEl.addEventListener('click', function(e) {
        const item = e.target.closest('.exclude-dropdown-item');
        if (item) {
            const sku = item.getAttribute('data-sku');
            const name = item.getAttribute('data-name');
            if (sku) {
                addExcludedSku(sku, name || sku);
            }
        }
    });
}

// Event delegation for removing excluded products from the chips container
const excludeChipsEl = document.getElementById('excludeProductChips');
if (excludeChipsEl) {
    excludeChipsEl.addEventListener('click', function(e) {
        const btn = e.target.closest('.exclude-chip-remove');
        if (btn) {
            const chip = btn.closest('.exclude-chip');
            if (chip) {
                const sku = chip.getAttribute('data-sku');
                if (sku) {
                    removeExcludedSku(sku);
                }
            }
        }
    });
}

async function filterExcludeProducts(query) {
    const dropdown = document.getElementById('excludeProductDropdown');
    if (!dropdown) return;

    const cleanQuery = query.trim().toLowerCase();
    
    // If query is empty, we show default active products list (up to 200 items)
    if (cleanQuery.length < 2) {
        try {
            const res = await apiCall('/products.php?action=list&all=1');
            if (res && res.success && Array.isArray(res.products)) {
                renderExcludeDropdownItems(res.products);
            } else {
                dropdown.innerHTML = '<div class="text-muted text-center" style="padding: 0.5rem; color: rgba(255, 255, 255, 0.4);">Escribe al menos 2 letras para buscar...</div>';
            }
        } catch (e) {
            dropdown.innerHTML = '<div class="text-muted text-center" style="padding: 0.5rem; color: rgba(255, 255, 255, 0.4);">Escribe al menos 2 letras para buscar...</div>';
        }
        return;
    }

    dropdown.innerHTML = '<div class="text-muted text-center" style="padding: 0.5rem; color: rgba(255, 255, 255, 0.4);">⏳ Buscando...</div>';

    if (activeSearchTimeout) clearTimeout(activeSearchTimeout);
    activeSearchTimeout = setTimeout(async () => {
        try {
            const res = await apiCall(`/products.php?action=search&all=1&q=${encodeURIComponent(cleanQuery)}`);
            if (res && res.success && Array.isArray(res.products)) {
                renderExcludeDropdownItems(res.products);
            } else {
                dropdown.innerHTML = '<div class="text-muted text-center" style="padding: 0.5rem; color: rgba(255, 255, 255, 0.4);">No se encontraron productos.</div>';
            }
        } catch (e) {
            console.error('Error searching products for exclusion:', e);
            dropdown.innerHTML = '<div class="text-muted text-center" style="padding: 0.5rem; color: rgba(255, 255, 255, 0.4);">Error al buscar.</div>';
        }
    }, 300);
}

function renderExcludeDropdownItems(products) {
    const dropdown = document.getElementById('excludeProductDropdown');
    if (!dropdown) return;

    if (products.length === 0) {
        dropdown.innerHTML = '<div class="text-muted text-center" style="padding: 0.5rem; color: rgba(255, 255, 255, 0.4);">No se encontraron productos.</div>';
        return;
    }

    // Filter out products that are already excluded
    const availableProducts = products.filter(p => !excludedProductsMap.has(p.sku));

    if (availableProducts.length === 0) {
        dropdown.innerHTML = '<div class="text-muted text-center" style="padding: 0.5rem; color: rgba(255, 255, 255, 0.4);">Todos los productos ya están excluidos.</div>';
        return;
    }

    dropdown.innerHTML = availableProducts.map(p => `
        <div class="exclude-dropdown-item" data-sku="${escapeHtml(p.sku)}" data-name="${escapeHtml(p.name)}">
            <div class="exclude-item-details">
                <span class="exclude-item-name" title="${escapeHtml(p.name)}">${escapeHtml(p.name)}</span>
                <span class="exclude-item-meta">SKU: ${escapeHtml(p.sku)} | Precio: ${formatAdminMoney(p.unit_price || p.sell_price || 0)}</span>
            </div>
            <span class="exclude-item-action-indicator">Excluir +</span>
        </div>
    `).join('');
}

function addExcludedSku(sku, name) {
    if (!sku) return;
    excludedProductsMap.set(sku, name);
    updateExcludedChips();
    
    const searchInput = document.getElementById('excludeProductSearch');
    if (searchInput) {
        searchInput.value = '';
        searchInput.focus();
    }
    
    filterExcludeProducts('');
}

function removeExcludedSku(sku) {
    excludedProductsMap.delete(sku);
    updateExcludedChips();
    
    const dropdown = document.getElementById('excludeProductDropdown');
    if (dropdown && dropdown.style.display === 'block') {
        const val = document.getElementById('excludeProductSearch').value;
        filterExcludeProducts(val);
    }
}

function updateExcludedChips() {
    const container = document.getElementById('excludeProductChips');
    const hiddenInput = document.getElementById('priceExcludeSkus');
    if (!container) return;

    const skusArray = Array.from(excludedProductsMap.keys());
    
    if (hiddenInput) {
        hiddenInput.value = skusArray.join(', ');
        // Trigger change event to update the price preview recalculation automatically
        const event = new Event('change');
        hiddenInput.dispatchEvent(event);
    }

    if (skusArray.length === 0) {
        container.innerHTML = '<span class="text-muted" style="font-size: 0.9rem; color: rgba(255, 255, 255, 0.4); padding-left: 0.25rem;">Ningún producto excluido</span>';
        return;
    }

    container.innerHTML = Array.from(excludedProductsMap.entries()).map(([sku, name]) => `
        <div class="exclude-chip" data-sku="${escapeHtml(sku)}">
            <span class="exclude-chip-sku">${escapeHtml(sku)}</span>
            <span class="exclude-chip-name" title="${escapeHtml(name)}">${escapeHtml(name)}</span>
            <button type="button" class="exclude-chip-remove" title="Eliminar exclusión">&times;</button>
        </div>
    `).join('');
}

function resetUpdateForm() {
    document.getElementById('updateEditId').value = '';
    document.getElementById('updateType').value = 'noticia';
    document.getElementById('updateOrder').value = '0';
    document.getElementById('updateActive').value = '1';
    document.getElementById('updateTitle').value = '';
    document.getElementById('updateBody').value = '';
    document.getElementById('updateImage').value = '';

    const preview = document.getElementById('updateImagePreview');
    if (preview) {
        preview.style.display = 'none';
    }

    const button = document.getElementById('updateSaveButton');
    if (button) {
        button.textContent = 'Guardar publicación';
    }

    const box = document.getElementById('updateResult');
    if (box) {
        box.innerHTML = '';
    }
}

function fillUpdateForm(update) {
    if (!update) return;
    document.getElementById('updateEditId').value = update.id || '';
    document.getElementById('updateType').value = update.update_type || 'noticia';
    document.getElementById('updateOrder').value = String(update.sort_order || 0);
    document.getElementById('updateActive').value = Number(update.is_active) ? '1' : '0';
    document.getElementById('updateTitle').value = update.title || '';
    document.getElementById('updateBody').value = update.body || '';
    document.getElementById('updateImage').value = '';

    const preview = document.getElementById('updateImagePreview');
    const previewImg = document.getElementById('updateImagePreviewImg');
    if (update.image_url && preview && previewImg) {
        previewImg.src = update.image_url;
        preview.style.display = 'block';
    } else if (preview) {
        preview.style.display = 'none';
    }

    const button = document.getElementById('updateSaveButton');
    if (button) {
        button.textContent = 'Actualizar publicación';
    }
}

async function loadHomepageUpdatesAdmin() {
    const box = document.getElementById('updatesList');
    const res = await apiCall('/admin_supply.php?action=updates-list', 'GET', null, { silent: true });

    if (!res || !res.success || !Array.isArray(res.items)) {
        if (box) box.innerHTML = '<p class="text-muted">No fue posible cargar publicaciones.</p>';
        return;
    }

    if (res.items.length === 0) {
        if (box) box.innerHTML = '<p class="text-muted">No hay publicaciones registradas.</p>';
        return;
    }

    box.innerHTML = `
        <table>
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Título</th>
                    <th>Orden</th>
                    <th>Visible</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                ${res.items.map((item) => `
                    <tr>
                        <td>${escapeHtml(normalizeUpdateTypeLabel(item.update_type))}</td>
                        <td>
                            <strong>${escapeHtml(item.title || '')}</strong>
                            <div class="text-muted" style="font-size: 12px; max-width: 480px;">${escapeHtml(item.body || '')}</div>
                        </td>
                        <td>${Number(item.sort_order || 0)}</td>
                        <td>${isTruthyFlag(item.is_active)
                            ? '<span class="badge badge-success" style="color:#dcfce7;background:rgba(22,101,52,.55);border:1px solid rgba(74,222,128,.75);font-weight:700;">Sí</span>'
                            : '<span class="badge badge-danger" style="color:#fee2e2;background:rgba(127,29,29,.58);border:1px solid rgba(248,113,113,.75);font-weight:700;">No</span>'
                        }</td>
                        <td>
                            <button class="btn btn-small btn-secondary" type="button" data-action="edit-update">Editar</button>
                            <button class="btn btn-small btn-danger" type="button" onclick="deleteHomepageUpdate(${Number(item.id)})">Eliminar</button>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;

    const rows = box.querySelectorAll('tbody tr');
    rows.forEach((row, idx) => {
        const editBtn = row.querySelector('[data-action="edit-update"]');
        const item = res.items[idx];
        if (editBtn && item) {
            editBtn.onclick = function () {
                fillUpdateForm(item);
            };
        }
    });
}

async function saveHomepageUpdate() {
    // Validation
    const title = document.getElementById('updateTitle')?.value?.trim() || '';
    const body = document.getElementById('updateBody')?.value?.trim() || '';
    const box = document.getElementById('updateResult');

    if (!title) {
        if (box) box.innerHTML = '<div class="alert alert-error">El título es requerido.</div>';
        showAlert('Título requerido', 'warning');
        return;
    }
    if (title.length > 220) {
        if (box) box.innerHTML = '<div class="alert alert-error">El título no puede exceder 220 caracteres.</div>';
        showAlert('Título muy largo', 'warning');
        return;
    }
    if (!body) {
        if (box) box.innerHTML = '<div class="alert alert-error">El contenido es requerido.</div>';
        showAlert('Contenido requerido', 'warning');
        return;
    }
    if (body.length > 1200) {
        if (box) box.innerHTML = '<div class="alert alert-error">El contenido no puede exceder 1200 caracteres.</div>';
        showAlert('Contenido muy largo', 'warning');
        return;
    }

    const imageInput = document.getElementById('updateImage');
    if (imageInput && imageInput.files.length > 0) {
        const file = imageInput.files[0];
        const maxSize = 5 * 1024 * 1024; // 5MB
        if (file.size > maxSize) {
            if (box) box.innerHTML = '<div class="alert alert-error">La imagen no puede exceder 5MB.</div>';
            showAlert('Imagen muy grande', 'warning');
            return;
        }
        // Validate image type
        if (!['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(file.type)) {
            if (box) box.innerHTML = '<div class="alert alert-error">Formato de imagen no soportado. Usa JPG, PNG, WebP o GIF.</div>';
            showAlert('Formato de imagen inválido', 'warning');
            return;
        }
    }

    // Use FormData to support file uploads
    const formData = new FormData();
    formData.append('id', Number(document.getElementById('updateEditId').value || 0));
    formData.append('update_type', document.getElementById('updateType').value);
    formData.append('sort_order', Number(document.getElementById('updateOrder').value || 0));
    formData.append('is_active', document.getElementById('updateActive').value === '1' ? '1' : '0');
    formData.append('title', title);
    formData.append('body', body);
    formData.append('csrf_token', window.csrfToken || '');
    
    if (imageInput && imageInput.files.length > 0) {
        formData.append('image', imageInput.files[0]);
    }

    try {
        const response = await fetch('/api/admin_supply.php?action=updates-save', {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json'
            }
        });
        const res = await response.json();
        
        if (!res || !res.success) {
            if (box) box.innerHTML = `<div class="alert alert-error">${escapeHtml((res && res.message) ? res.message : 'No fue posible guardar')}</div>`;
            return;
        }
        
        if (box) box.innerHTML = `<div class="alert alert-success">${escapeHtml(res.message || 'Publicación guardada')}</div>`;
        resetUpdateForm();
        loadHomepageUpdatesAdmin();
    } catch (error) {
        if (box) box.innerHTML = `<div class="alert alert-error">Error de conexión: ${escapeHtml(error.message)}</div>`;
    }
}

async function deleteHomepageUpdate(id) {
    if (!id) return;
    if (!confirm('¿Deseas eliminar esta publicación de portada?')) return;

    const box = document.getElementById('updateResult');
    const res = await apiCall('/admin_supply.php?action=updates-delete', 'POST', { id: id });
    if (!res || !res.success) {
        if (box) box.innerHTML = `<div class="alert alert-error">${escapeHtml((res && res.message) ? res.message : 'No fue posible eliminar')}</div>`;
        return;
    }

    if (box) box.innerHTML = `<div class="alert alert-success">${escapeHtml(res.message || 'Publicación eliminada')}</div>`;
    loadHomepageUpdatesAdmin();
}

function resetClientForm() {
    document.getElementById('clientEditId').value = '';
    document.getElementById('clientFirstName').value = '';
    document.getElementById('clientLastName').value = '';
    document.getElementById('clientPhone').value = '';
    document.getElementById('clientEmail').value = '';
    document.getElementById('clientCompany').value = '';
    document.getElementById('clientBirthdate').value = '';

    const button = document.getElementById('clientSaveButton');
    if (button) {
        button.textContent = 'Registrar cliente';
    }

    const box = document.getElementById('clientCreateResult');
    if (box) {
        box.innerHTML = '';
    }
}

function fillClientForm(client) {
    if (!client) return;

    document.getElementById('clientEditId').value = client.id || '';
    document.getElementById('clientFirstName').value = client.first_name || '';
    document.getElementById('clientLastName').value = client.last_name || '';
    document.getElementById('clientPhone').value = client.phone || '';
    document.getElementById('clientEmail').value = client.email || '';
    document.getElementById('clientCompany').value = client.company_name || '';
    document.getElementById('clientBirthdate').value = (client.birthdate || '').slice(0, 10);

    const button = document.getElementById('clientSaveButton');
    if (button) {
        button.textContent = 'Actualizar cliente';
    }
}

function fillClientFormFromButton(buttonEl) {
    if (!buttonEl) return;

    const encoded = buttonEl.getAttribute('data-client') || '';
    if (!encoded) return;

    try {
        const client = JSON.parse(decodeURIComponent(encoded));
        fillClientForm(client);
    } catch (error) {
        showAlert('No fue posible cargar los datos del cliente', 'error');
    }
}

function renderClientList(clients) {
    const box = document.getElementById('clientListResult');
    if (!box) return;

    if (!Array.isArray(clients) || clients.length === 0) {
        box.innerHTML = '<p class="text-muted">No hay clientes registrados.</p>';
        return;
    }

    box.innerHTML = `
        <table>
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Código único</th>
                    <th>Teléfono</th>
                    <th>Email</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                ${clients.map((client) => {
                    const fullName = `${client.first_name || ''} ${client.last_name || ''}`.trim();
                    const code = displayClientCode(client.user_code || '');
                    const statusLabel = Number(client.is_active) ? 'Activo' : 'Inactivo';
                    return `
                        <tr>
                            <td>${escapeHtml(fullName || 'Sin nombre')}</td>
                            <td>${escapeHtml(code || 'Sin código')}</td>
                            <td>${escapeHtml(client.phone || '')}</td>
                            <td>${escapeHtml(client.email || '')}</td>
                            <td>${Number(client.is_active) ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-danger">Inactivo</span>'}</td>
                            <td>
                                <button class="btn btn-small btn-secondary" type="button" data-client="${escapeHtml(encodeURIComponent(JSON.stringify(client)))}" onclick="fillClientFormFromButton(this)">Editar</button>
                                <button class="btn btn-small btn-danger" type="button" onclick="deleteClientByAdmin(${Number(client.id)})">Eliminar</button>
                            </td>
                        </tr>
                    `;
                }).join('')}
            </tbody>
        </table>
    `;
}

async function loadClients() {
    const box = document.getElementById('clientListResult');
    const response = await apiCall('/admin_clients.php?action=list', 'GET', null, { silent: true });

    if (!response || !response.success || !Array.isArray(response.clients)) {
        if (box) {
            box.innerHTML = '<p class="text-muted">No fue posible cargar clientes.</p>';
        }
        return;
    }

    renderClientList(response.clients);
}

let stockCurrentPage = 1;
const stockPerPage = 50;

async function loadStock(page = 1, customPerPage = null) {
    const box = document.getElementById('stockRows');
    const caption = document.getElementById('stockListCaption');
    if (box) box.innerHTML = '<div style="padding:2rem; text-align:center;"><span class="spinner"></span><p class="text-muted">Cargando catálogo...</p></div>';
    
    stockCurrentPage = page;
    // Use custom per_page if provided (for faster loading after save), otherwise use default
    const perPageToUse = customPerPage !== null ? customPerPage : stockPerPage;
    const res = await apiCall(`/admin_supply.php?action=stock&page=${page}&per_page=${perPageToUse}&_=${Date.now()}`, 'GET', null, { silent: true });
    const body = document.getElementById('stockRows');
    
    if (!res || !res.success || !Array.isArray(res.items)) {
        if (body) body.innerHTML = '<tr><td colspan="10" class="text-center text-muted">No fue posible cargar productos.</td></tr>';
        return;
    }

    stockItemsCache = res.items;
    renderStockList();
    renderStockPagination(res.pagination);
    updateStockPreview();
}

function renderStockPagination(pagination) {
    const container = document.getElementById('stockPagination');
    if (!container || !pagination) return;

    const { current_page, total_pages, total_items } = pagination;
    
    container.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:1rem; padding:1rem; background:var(--ui-surface-soft); border-radius:12px;">
            <div class="text-muted">Total: <strong>${total_items}</strong> productos</div>
            <div style="display:flex; gap:0.5rem; align-items:center;">
                <button class="btn btn-small" ${current_page <= 1 ? 'disabled' : ''} onclick="loadStock(${current_page - 1})">Anterior</button>
                <span class="text-muted">Página <strong>${current_page}</strong> de ${total_pages}</span>
                <button class="btn btn-small btn-primary" ${current_page >= total_pages ? 'disabled' : ''} onclick="loadStock(${current_page + 1})">Ver más</button>
            </div>
        </div>
    `;
}

async function deleteCategoryQuickFromSelect(selectId, resultId) {
    const select = document.getElementById(selectId);
    const quickBox = document.getElementById(resultId);
    const selected = Array.from(select?.selectedOptions || []);
    if (selected.length === 0) {
        if (quickBox) quickBox.innerHTML = '<span style="color:#f59e0b;">Selecciona una categoría para eliminar.</span>';
        showAlert('Selecciona una categoría para eliminar', 'warning');
        return;
    }

    const names = selected.map((opt) => String(opt.value || '').trim()).filter(Boolean);
    const confirmLabel = names.length === 1
        ? `¿Eliminar categoría "${names[0]}"?`
        : `¿Eliminar ${names.length} categorías seleccionadas?`;
    if (!confirm(confirmLabel)) return;

    let removed = 0;
    let firstError = '';

    for (const option of selected) {
        const categoryName = String(option.value || '').trim();
        const categoryId = Number(option.dataset?.id || 0);
        const res = await apiCall('/admin_supply.php?action=categories-delete', 'POST', {
            id: categoryId,
            name: categoryName
        });

        if (res && res.success) {
            removed += 1;
            option.remove();
        } else if (!firstError) {
            firstError = (res && res.message) ? res.message : `No fue posible eliminar "${categoryName}"`;
        }
    }

    if (removed > 0) {
        if (quickBox) quickBox.innerHTML = `<span style="color:#22c55e;">${removed === 1 ? 'Categoría eliminada.' : `${removed} categorías eliminadas.`}</span>`;
        showAlert(removed === 1 ? 'Categoría eliminada' : `${removed} categorías eliminadas`, 'success');
    }
    if (firstError) {
        if (quickBox) quickBox.innerHTML = `<span style="color:#f87171;">${escapeHtml(firstError)}</span>`;
        showAlert(firstError, 'error');
    }

    await refreshCategoriesUi();
    
    // Force reload of product lists to show updated categories
    if (removed > 0) {
        console.log('Reloading product lists after category deletion...');
        await Promise.all([
            loadStock(stockCurrentPage || 1, 25),
            loadMarketplaceCeAdmin(marketplaceCurrentPage || 1, 25)
        ]);
    }
}

async function deleteCategoryQuick() {
    return deleteCategoryQuickFromSelect('newProductCategory', 'quickCategoryResult');
}

async function deleteMarketplaceCategoryQuick() {
    return deleteCategoryQuickFromSelect('marketplaceCategory', 'marketplaceQuickCategoryResult');
}

async function addCategoryFromQuickForm(selectId, inputId, resultId, context = 'both') {
    const input = document.getElementById(inputId);
    const quickBox = document.getElementById(resultId);
    const raw = (input?.value || '').trim().replace(/\s+/g, ' ');
    if (!raw) {
        if (quickBox) quickBox.innerHTML = '<span style="color:#f59e0b;">Escribe el nombre de la nueva categoría.</span>';
        showAlert('Escribe el nombre de la nueva categoría', 'warning');
        return;
    }

    const categorySelect = document.getElementById(selectId);
    const alreadyExists = Array.from(categorySelect?.options || []).some((opt) =>
        normalizeCategoryValue(opt.value) === normalizeCategoryValue(raw)
    );
    if (alreadyExists) {
        if (quickBox) quickBox.innerHTML = '<span style="color:#ef4444;">❌ Esta categoría ya está agregada.</span>';
        showAlert('Esta categoría ya está agregada', 'warning');
        return;
    }

    if (quickBox) quickBox.innerHTML = '<span style="color:#cbd5e1;">⏳ Guardando categoría...</span>';

    const payload = {
        id: 0,
        name: raw,
        sort_order: 0,
        is_active: true,
        context: context
    };

    const res = await apiCall('/admin_supply.php?action=categories-save', 'POST', payload);
    if (!res || !res.success) {
        if (quickBox) quickBox.innerHTML = `<span style="color:#f87171;">❌ ${escapeHtml((res && res.message) ? res.message : 'No fue posible guardar la categoría.')}</span>`;
        showAlert((res && res.message) ? res.message : 'No fue posible guardar la categoría', 'error');
        return;
    }

    // Reflect creation immediately in the same select control.
    if (categorySelect) {
        const existingOption = findCategoryOption(categorySelect, raw);
        if (existingOption) {
            existingOption.selected = true;
            existingOption.dataset.id = String(Number((res.item && res.item.id) ? res.item.id : existingOption.dataset.id || 0));
        } else {
            const option = document.createElement('option');
            option.value = raw;
            option.textContent = raw;
            option.dataset.id = String(Number((res.item && res.item.id) ? res.item.id : 0));
            option.selected = true;
            categorySelect.appendChild(option);
        }
    }

    if (input) input.value = '';
    if (quickBox) quickBox.innerHTML = '<span style="color:#22c55e;">✓ Categoría guardada correctamente.</span>';
    showAlert(res.message || 'Categoría guardada', 'success');
    
    // Immediately refresh all category displays and reload product lists
    await refreshCategoriesUi();
    
    // Force reload of product lists to show updated categories
    console.log('Reloading product lists after category creation...');
    await Promise.all([
        loadStock(stockCurrentPage || 1, 25),
        loadMarketplaceCeAdmin(marketplaceCurrentPage || 1, 25)
    ]);

    if (categorySelect) {
        const target = findCategoryOption(categorySelect, raw);
        if (target) {
            target.selected = true;
            target.scrollIntoView({ block: 'nearest' });
        }
    }
}

async function addCategoryFromStockForm() {
    return addCategoryFromQuickForm('newProductCategory', 'newCategoryQuickName', 'quickCategoryResult', 'both');
}

async function addCategoryFromMarketplaceForm() {
    return addCategoryFromQuickForm('marketplaceCategory', 'marketplaceCategoryQuickName', 'marketplaceQuickCategoryResult', 'both');
}

function updateStockQuickSelection(items = stockItemsCache) {
    const select = document.getElementById('stockBulkSelect');
    if (!select) return;

    const previouslySelected = new Set(Array.from(select.selectedOptions || []).map((option) => Number(option.value || 0)));
    select.innerHTML = '';

    if (!Array.isArray(items) || items.length === 0) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'Sin productos para mostrar';
        option.disabled = true;
        select.appendChild(option);
        return;
    }

    items.forEach((item) => {
        const id = Number(item.id || 0);
        if (id <= 0) return;

        const code = displayProductCode(item.sku || '');
        const name = String(item.name || 'Sin nombre').trim();
        const stock = Number(item.stock_quantity || 0);
        const inactive = Number(item.is_active) === 0;

        const option = document.createElement('option');
        option.value = String(id);
        option.textContent = `${code} | ${name} | Stock: ${stock}${inactive ? ' (Oculto)' : ''}`;
        option.selected = previouslySelected.has(id);
        select.appendChild(option);
    });
}

function renderStockList() {
    const body = document.getElementById('stockRows');
    const caption = document.getElementById('stockListCaption');
    const query = (document.getElementById('stockSearch')?.value || '').toLowerCase().trim();

    if (!body) return;

    const filtered = stockItemsCache.filter((item) => {
        const code = displayProductCode(item.sku || '').toLowerCase();
        const name = String(item.name || '').toLowerCase();
        const cat = String(item.category || '').toLowerCase();
        return `${code} ${name} ${cat}`.includes(query);
    });

    if (caption) {
        caption.textContent = `Mostrando ${filtered.length} de ${stockItemsCache.length} productos`;
    }

    updateStockQuickSelection(filtered);

    if (filtered.length === 0) {
        body.innerHTML = '<p class="text-muted">No hay productos que coincidan con la búsqueda.</p>';
        return;
    }

    body.innerHTML = `<div class="catalog-grid-min">${filtered.map((item) => renderAdminProductCard(item, 'stock')).join('')}</div>`;
}

function getStockBulkSelectedIds() {
    const select = document.getElementById('stockBulkSelect');
    return Array.from(select?.selectedOptions || [])
        .map((option) => Number(option.value || 0))
        .filter((id) => id > 0);
}

function editStockSelectedItem() {
    const quickBox = document.getElementById('stockQuickResult');
    const selectedIds = getStockBulkSelectedIds();

    if (selectedIds.length === 0) {
        if (quickBox) quickBox.innerHTML = '<span style="color:#f59e0b;">Selecciona un producto para editar.</span>';
        return;
    }

    if (selectedIds.length > 1) {
        if (quickBox) quickBox.innerHTML = '<span style="color:#f59e0b;">Selecciona solo un producto para editar.</span>';
        return;
    }

    const target = stockItemsCache.find((item) => Number(item.id || 0) === selectedIds[0]);
    if (!target) {
        if (quickBox) quickBox.innerHTML = '<span style="color:#f87171;">No se encontró el producto seleccionado.</span>';
        return;
    }

    void fillProductFormById(target.id);
    if (quickBox) quickBox.innerHTML = '<span style="color:#22c55e;">Producto cargado en el formulario.</span>';
    document.getElementById('newProductSku')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

async function deleteStockSelectedItems() {
    const quickBox = document.getElementById('stockQuickResult');
    const selectedIds = getStockBulkSelectedIds();

    if (selectedIds.length === 0) {
        if (quickBox) quickBox.innerHTML = '<span style="color:#f59e0b;">Selecciona al menos un producto para eliminar.</span>';
        return;
    }

    if (!confirm(`¿Eliminar ${selectedIds.length} producto(s) seleccionado(s)? Se quitarán del catálogo.`)) {
        return;
    }

    if (quickBox) quickBox.innerHTML = '<span style="color:#cbd5e1;">Eliminando productos...</span>';

    let successCount = 0;
    let firstError = '';
    for (const id of selectedIds) {
        const res = await apiCall('/admin_supply.php?action=product-delete', 'POST', { id: id });
        if (res && res.success) {
            successCount += 1;
        } else if (!firstError) {
            firstError = (res && res.message) ? res.message : `No se pudo eliminar el producto ${id}`;
        }
    }

    if (successCount > 0) {
        if (quickBox) quickBox.innerHTML = `<span style="color:#22c55e;">${successCount} producto(s) eliminado(s).</span>`;
        showAlert(`${successCount} producto(s) eliminado(s)`, 'success');
    }
    if (firstError) {
        if (quickBox) quickBox.innerHTML += ` <span style="color:#f87171;">${escapeHtml(firstError)}</span>`;
        showAlert(firstError, 'error');
    }

    // Optimistic update: remove deleted products from cache and re-render
    try {
        const idsSet = new Set(selectedIds.map((i) => Number(i)));
        stockItemsCache = (stockItemsCache || []).filter((p) => !idsSet.has(Number(p.id)));
        renderStockList();
        renderStockPagination({ current_page: stockCurrentPage, total_pages: Math.max(1, Math.ceil((stockItemsCache || []).length / (stockPerPage || 50))), total_items: (stockItemsCache || []).length });
    } catch (e) {
        console.warn('Optimistic bulk removal failed:', e);
    }

    // Background sync: refresh stock and marketplace quickly
    await loadStock(stockCurrentPage);
    void loadMarketplaceCeAdmin(marketplaceCurrentPage || 1, 10);
}

function resetProductForm() {
    setGalleryState('stock', '', [], '');
    
    // Reset file input if exists
    const fileInput = document.getElementById('newProductImages');
    if (fileInput) fileInput.value = '';

    document.getElementById('newProductEditId').value = '';
    document.getElementById('newProductSeedMode').value = '0';
    document.getElementById('newProductSku').value = '';
    document.getElementById('newProductName').value = '';
    document.getElementById('newProductPrice').value = '0';
    document.getElementById('newProductStock').value = '50';
    document.getElementById('newProductReorder').value = '10';
    document.getElementById('newProductDescription').value = '';
    document.getElementById('newProductImageRef').value = 'images/products/default-product.svg';
    document.getElementById('newProductVisible').value = '0';

    Array.from(document.getElementById('newProductCategory').options || []).forEach((opt) => {
        opt.selected = false;
    });

    const saveBtn = document.getElementById('newProductSaveButton');
    if (saveBtn) saveBtn.textContent = 'Guardar producto';
    const box = document.getElementById('productCreateResult');
    if (box) box.innerHTML = '';
    setSkuStatus('newProductSkuStatus', 'Código listo para guardar.', 'muted');
    updateStockPreview();
    
    // Clear gallery display since form is being reset
    const galleryHost = document.getElementById('productGalleryList');
    const galleryStatus = document.getElementById('productGalleryStatus');
    if (galleryHost) galleryHost.innerHTML = '';
    if (galleryStatus) galleryStatus.textContent = 'Escribe un código de 5 o 6 números para cargar su galería.';
}

async function fillProductFormById(id) {
    const item = stockItemsCache.find((row) => Number(row.id) === Number(id));
    if (!item) return;

    window.scrollTo({ top: 0, behavior: 'smooth' });

    const isSeedOnly = Boolean(item.seed_only || item.__seed_only);
    document.getElementById('newProductEditId').value = isSeedOnly ? '' : (item.id || '');
    document.getElementById('newProductSeedMode').value = isSeedOnly ? '1' : '0';
    document.getElementById('newProductSku').value = displayProductCode(item.sku || '');
    document.getElementById('newProductName').value = item.name || '';
    document.getElementById('newProductPrice').value = String(item.unit_price || 0);
    document.getElementById('newProductStock').value = String(item.stock_quantity || 0);
    document.getElementById('newProductReorder').value = String(item.reorder_level || 10);
    document.getElementById('newProductDescription').value = item.description || '';
    const imageRefSelect = document.getElementById('newProductImageRef');
    const itemImage = item.image_url || 'images/products/default-product.svg';
    if (imageRefSelect) imageRefSelect.value = itemImage;

    const skuForGallery = normalizeNumericSku(item.sku || '');
    const fastImages = extractGalleryImagesFromItem(item);
    if (fastImages.length === 0 && itemImage && !itemImage.includes('default-product.svg')) {
        fastImages.push(itemImage);
    }
    if (/^\d{5,6}$/.test(skuForGallery) && fastImages.length > 0) {
        setGalleryState('stock', skuForGallery, fastImages, fastImages[0] || '');
        renderProductGallery(fastImages, skuForGallery, 'stock');
        const galleryStatus = document.getElementById('productGalleryStatus');
        if (galleryStatus) galleryStatus.textContent = `Galería para ${skuForGallery}: ${fastImages.length} imagen(es)`;
        syncGalleryModeUi('stock', fastImages[0] || '');
    }

    document.getElementById('newProductVisible').value = Number(item.is_active) ? '1' : '0';

    await loadProductCategories(false);

    const categories = String(item.category || '')
        .split(',')
        .map((x) => x.trim())
        .filter(Boolean);
    const categorySelect = document.getElementById('newProductCategory');
    ensureCategoryOptions(categorySelect, categories, true);
    setCategorySelections(categorySelect, categories);

    const saveBtn = document.getElementById('newProductSaveButton');
    const box = document.getElementById('productCreateResult');
    if (saveBtn) saveBtn.textContent = 'Actualizar producto';
    if (isSeedOnly) {
        if (saveBtn) saveBtn.textContent = 'Guardar en stock';
        setSkuStatus('newProductSkuStatus', 'Producto base: al guardar se crea editable en stock.', 'warning');
        if (box) box.innerHTML = '<div class="alert alert-info">Editando producto base. Al guardar se crea en stock y quedará oculto hasta mostrarlo.</div>';
    } else {
        setSkuStatus('newProductSkuStatus', 'Editando producto existente.', 'muted');
        if (box) {
            const visibility = Number(item.is_active) ? 'Visible' : 'Oculto';
            box.innerHTML = `<div class="alert alert-info">Editando producto: estado actual <strong>${visibility}</strong>.</div>`;
        }
    }
    primeGalleryFromCurrentForm('stock');
    updateStockPreview();
    loadProductGalleryForCurrentSku();
}

function prepareSeedProductForEditing(id) {
    void fillProductFormById(id);
    const formTop = document.getElementById('newProductSku');
    if (formTop) {
        formTop.focus();
        formTop.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

async function deleteProductByAdmin(id) {
    if (!id) return;
    if (!confirm('¿Deseas eliminar este producto? Se quitará del catálogo principal.')) return;

    const box = document.getElementById('productCreateResult');
    const res = await apiCall('/admin_supply.php?action=product-delete', 'POST', { id: id });
    if (!res || !res.success) {
        if (box) box.innerHTML = `<div class="alert alert-error">${escapeHtml((res && res.message) ? res.message : 'No fue posible eliminar producto')}</div>`;
        return;
    }

    if (box) box.innerHTML = `<div class="alert alert-success">${escapeHtml(res.message || 'Producto eliminado')}</div>`;
    if (Number(document.getElementById('newProductEditId').value || 0) === Number(id)) {
        resetProductForm();
    }
    // Optimistic update: remove product from local cache and re-render immediately
    try {
        stockItemsCache = (stockItemsCache || []).filter((p) => Number(p.id) !== Number(id));
        renderStockList();
        // update pagination based on current cache
        renderStockPagination({ current_page: stockCurrentPage, total_pages: Math.max(1, Math.ceil((stockItemsCache || []).length / (stockPerPage || 50))), total_items: (stockItemsCache || []).length });
    } catch (e) {
        console.warn('Optimistic product removal failed:', e);
    }

    // Background sync to keep server state in sync (non-blocking). Use small per_page for quick refresh.
    void loadStock(stockCurrentPage);
    void loadSupplierProducts();
    void loadMarketplaceCeAdmin(marketplaceCurrentPage || 1, 10);
}

function syncStockVisibilityState(id, nextVisible) {
    const normalized = Number(nextVisible) ? 1 : 0;
    const target = stockItemsCache.find((row) => Number(row.id) === Number(id));
    if (target) {
        target.is_active = normalized;
    }

    const currentEditId = Number(document.getElementById('newProductEditId')?.value || 0);
    if (currentEditId === Number(id)) {
        const visibleField = document.getElementById('newProductVisible');
        if (visibleField) {
            visibleField.value = String(normalized);
        }
        updateStockPreview();
    }

    const visibilityIdx = allProductsVisibility.findIndex((p) => Number(p.id) === Number(id));
    if (visibilityIdx >= 0) {
        allProductsVisibility[visibilityIdx].is_active = normalized;
    }
}

async function toggleStockVisibility(id, nextVisible) {
    if (!id) return;

    const box = document.getElementById('productCreateResult');
    const res = await apiCall('/admin_supply.php?action=product-visibility', 'POST', {
        id: Number(id),
        is_visible: Number(nextVisible) ? 1 : 0
    });

    if (!res || !res.success) {
        if (box) {
            box.innerHTML = `<div class="alert alert-error">${escapeHtml((res && res.message) ? res.message : 'No fue posible actualizar visibilidad')}</div>`;
        }
        return;
    }

    if (box) {
        box.innerHTML = `<div class="alert alert-success">${escapeHtml(res.message || 'Visibilidad actualizada')}</div>`;
    }

    syncStockVisibilityState(id, nextVisible);
    renderStockList();

    showAlert(res.message || 'Visibilidad actualizada', 'success');
    // Background sync to refresh current page
    void loadStock(stockCurrentPage);
}

async function toggleMarketplaceVisibility(id, nextVisible) {
    if (!id) return;

    const box = document.getElementById('marketplaceResult');
    const res = await apiCall('/admin_supply.php?action=marketplace-visibility', 'POST', {
        id: Number(id),
        is_visible: Number(nextVisible) ? 1 : 0
    });

    if (!res || !res.success) {
        if (box) {
            box.innerHTML = `<div class="alert alert-error">${escapeHtml((res && res.message) ? res.message : 'No fue posible actualizar visibilidad')}</div>`;
        }
        return;
    }

    if (box) {
        box.innerHTML = `<div class="alert alert-success">${escapeHtml(res.message || 'Visibilidad actualizada')}</div>`;
    }

    syncMarketplaceVisibilityState(id, nextVisible);
    showAlert(res.message || 'Visibilidad actualizada', 'success');
    // Background sync to refresh current marketplace page
    void loadMarketplaceCeAdmin(marketplaceCurrentPage);
    activateAdminSupplyTab('marketplaceTab', 'marketplaceList');
}

function syncMarketplaceVisibilityState(id, nextVisible) {
    const normalized = Number(nextVisible) ? 1 : 0;
    const target = marketplaceItemsCache.find((row) => Number(row.id) === Number(id));
    if (target) {
        target.is_active = normalized;
    }

    const currentEditId = Number(document.getElementById('marketplaceEditId')?.value || 0);
    if (currentEditId === Number(id)) {
        const activeField = document.getElementById('marketplaceActive');
        if (activeField) {
            activeField.value = String(normalized);
        }
        updateMarketplacePreview();
    }
}

async function createVisit() {
    const supplierName = document.getElementById('supplierName')?.value?.trim() || '';
    const visitDate = document.getElementById('visitDate')?.value || '';
    const notes = document.getElementById('visitNotes')?.value?.trim() || '';

    // Form validations
    let isValid = true;
    
    // Validate supplierName
    const errSupplierName = document.getElementById('errSupplierName');
    const inputSupplierName = document.getElementById('supplierName');
    if (!supplierName) {
        if (errSupplierName) {
            errSupplierName.textContent = 'El nombre del proveedor es obligatorio';
            errSupplierName.style.display = 'block';
        }
        if (inputSupplierName) {
            inputSupplierName.style.borderColor = '#ef4444';
        }
        isValid = false;
    } else if (supplierName.length < 2) {
        if (errSupplierName) {
            errSupplierName.textContent = 'El nombre debe tener al menos 2 caracteres';
            errSupplierName.style.display = 'block';
        }
        if (inputSupplierName) {
            inputSupplierName.style.borderColor = '#ef4444';
        }
        isValid = false;
    } else {
        if (errSupplierName) errSupplierName.style.display = 'none';
        if (inputSupplierName) inputSupplierName.style.borderColor = '';
    }

    // Validate visitDate
    const errVisitDate = document.getElementById('errVisitDate');
    const inputVisitDate = document.getElementById('visitDate');
    if (!visitDate) {
        if (errVisitDate) {
            errVisitDate.textContent = 'La fecha y hora son obligatorias';
            errVisitDate.style.display = 'block';
        }
        if (inputVisitDate) {
            inputVisitDate.style.borderColor = '#ef4444';
        }
        isValid = false;
    } else {
        if (errVisitDate) errVisitDate.style.display = 'none';
        if (inputVisitDate) inputVisitDate.style.borderColor = '';
    }

    // Validate visitNotes (max length 500)
    const errVisitNotes = document.getElementById('errVisitNotes');
    const inputVisitNotes = document.getElementById('visitNotes');
    if (notes.length > 500) {
        if (errVisitNotes) {
            errVisitNotes.textContent = 'Las notas no pueden superar los 500 caracteres';
            errVisitNotes.style.display = 'block';
        }
        if (inputVisitNotes) {
            inputVisitNotes.style.borderColor = '#ef4444';
        }
        isValid = false;
    } else {
        if (errVisitNotes) errVisitNotes.style.display = 'none';
        if (inputVisitNotes) inputVisitNotes.style.borderColor = '';
    }

    if (!isValid) {
        showAlert('Por favor, corrige los errores en el formulario', 'warning');
        return;
    }

    const payload = {
        supplier_name: supplierName,
        visit_datetime: visitDate,
        notes: notes
    };
    const res = await apiCall('/admin_supply.php?action=calendar-create', 'POST', payload);
    if (res && res.success) {
        showAlert(res.message || 'Visita registrada correctamente', 'success');
        document.getElementById('supplierName').value = '';
        document.getElementById('visitDate').value = '';
        document.getElementById('visitNotes').value = '';
        
        // Reset borders
        if (inputSupplierName) inputSupplierName.style.borderColor = '';
        if (inputVisitDate) inputVisitDate.style.borderColor = '';
        if (inputVisitNotes) inputVisitNotes.style.borderColor = '';
        
        // Reset selected filter
        selectedCalendarDay = null;
    } else if (res) {
        showAlert(res.message || 'No fue posible guardar la visita', 'error');
    }
    loadCalendar();
}

function clearValidationError(id) {
    const errorEl = document.getElementById('err' + id.charAt(0).toUpperCase() + id.slice(1));
    const inputEl = document.getElementById(id);
    if (errorEl) errorEl.style.display = 'none';
    if (inputEl) inputEl.style.borderColor = '';
}

let calendarVisits = [];
let calendarMonthCursor = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
let selectedCalendarDay = null; // Filter visits by day

function formatDateTimeLocal(dateValue) {
    const d = new Date(dateValue);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleString('es-MX', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function selectCalendarDay(day) {
    if (selectedCalendarDay === day) {
        selectedCalendarDay = null;
    } else {
        selectedCalendarDay = day;
    }
    renderCalendarMonth();
}

function renderCalendarMonth() {
    const label = document.getElementById('calendarMonthLabel');
    const grid = document.getElementById('calendarGrid');
    const list = document.getElementById('calendarList');
    if (!label || !grid || !list) return;

    const year = calendarMonthCursor.getFullYear();
    const month = calendarMonthCursor.getMonth();
    label.textContent = calendarMonthCursor.toLocaleDateString('es-MX', { month: 'long', year: 'numeric' });

    const firstDay = new Date(year, month, 1);
    const startWeekDay = (firstDay.getDay() + 6) % 7;
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    const visitMap = {};
    calendarVisits.forEach((visit) => {
        const d = new Date(visit.visit_datetime);
        if (Number.isNaN(d.getTime())) return;
        if (d.getFullYear() !== year || d.getMonth() !== month) return;
        const key = d.getDate();
        if (!visitMap[key]) visitMap[key] = [];
        visitMap[key].push(visit);
    });

    const weekNames = ['L', 'M', 'M', 'J', 'V', 'S', 'D'];
    let html = '<div class="calendar-weekdays">' + weekNames.map(w => `<div class="calendar-weekday">${w}</div>`).join('') + '</div>';
    html += '<div class="calendar-days">';

    for (let i = 0; i < startWeekDay; i += 1) {
        html += '<div class="calendar-day calendar-day-empty"></div>';
    }

    for (let day = 1; day <= daysInMonth; day += 1) {
        const count = (visitMap[day] || []).length;
        const hasVisitsClass = count > 0 ? 'calendar-day-has-visits' : '';
        const activeClass = selectedCalendarDay === day ? 'calendar-day-selected' : '';
        html += `<div class="calendar-day ${hasVisitsClass} ${activeClass}" onclick="selectCalendarDay(${day})">`;
        html += `<div class="calendar-day-number">${day}</div>`;
        if (count > 0) {
            html += `<div class="calendar-day-visits">${count} visita${count > 1 ? 's' : ''}</div>`;
        }
        html += '</div>';
    }

    html += '</div>';
    grid.innerHTML = html;

    const monthVisits = calendarVisits.filter((visit) => {
        const d = new Date(visit.visit_datetime);
        return !Number.isNaN(d.getTime()) && d.getFullYear() === year && d.getMonth() === month;
    }).sort((a, b) => new Date(a.visit_datetime) - new Date(b.visit_datetime));

    let listTitleHtml = '';
    const formattedMonthName = calendarMonthCursor.toLocaleDateString('es-MX', { month: 'long', year: 'numeric' });
    if (selectedCalendarDay !== null) {
        listTitleHtml = `
            <div class="d-flex justify-between align-center mb-3" style="border-bottom: 1px solid var(--theme-border); padding-bottom: 0.5rem; flex-wrap: wrap; gap: 0.5rem;">
                <span style="font-weight: 700; font-size: 1.05rem; color: var(--theme-accent); display: flex; align-items: center; gap: 0.5rem;">
                    📅 Visitas del día ${selectedCalendarDay} de ${formattedMonthName}
                </span>
                <button class="btn btn-small btn-secondary" onclick="selectCalendarDay(null)">Ver todas las del mes</button>
            </div>
        `;
    } else {
        listTitleHtml = `
            <div class="mb-3" style="border-bottom: 1px solid var(--theme-border); padding-bottom: 0.5rem;">
                <span style="font-weight: 700; font-size: 1.05rem; color: var(--theme-text);">
                    📅 Todas las visitas de ${formattedMonthName}
                </span>
            </div>
        `;
    }

    let filteredVisits = monthVisits;
    if (selectedCalendarDay !== null) {
        filteredVisits = monthVisits.filter((visit) => {
            const d = new Date(visit.visit_datetime);
            return !Number.isNaN(d.getTime()) && d.getDate() === selectedCalendarDay;
        });
    }

    if (filteredVisits.length === 0) {
        list.innerHTML = listTitleHtml + '<p class="text-muted" style="padding: 1.5rem; text-align: center; background: var(--theme-surface-strong); border-radius: 8px; border: 1px solid var(--theme-border);">No hay visitas agendadas para la selección.</p>';
        return;
    }

    const now = new Date();
    const upcomingVisits = [];
    const pastVisits = [];

    filteredVisits.forEach((visit) => {
        const d = new Date(visit.visit_datetime);
        if (Number.isNaN(d.getTime())) return;
        if (d >= now) {
            upcomingVisits.push(visit);
        } else {
            pastVisits.push(visit);
        }
    });

    // Sort past visits by date descending (latest past first)
    pastVisits.sort((a, b) => new Date(b.visit_datetime) - new Date(a.visit_datetime));

    const renderVisitCard = (i, isUpcoming) => `
        <div class="visit-item ${isUpcoming ? 'visit-upcoming' : 'visit-past'}">
            <div class="visit-header">
                <span class="visit-supplier">${escapeHtml(i.supplier_name)}</span>
                <div class="d-flex align-center" style="gap: 0.5rem; flex-wrap: wrap;">
                    <span class="visit-status-badge ${isUpcoming ? 'badge-upcoming' : 'badge-past'}">
                        ${isUpcoming ? '⏰ Próxima' : '✅ Pasada'}
                    </span>
                    <span class="visit-time" style="font-size: 0.78rem;">${escapeHtml(formatDateTimeLocal(i.visit_datetime))}</span>
                </div>
            </div>
            ${i.notes ? `<div class="visit-notes">${escapeHtml(i.notes)}</div>` : ''}
        </div>
    `;

    let splitHtml = `
        <div class="visits-split-container" style="display: flex; flex-direction: column; gap: 1.5rem;">
            <!-- Próximas Visitas -->
            <div class="visits-section-upcoming">
                <h4 style="color: var(--color-naranja, #ff6600); border-bottom: 2px solid rgba(255, 102, 0, 0.15); padding-bottom: 0.4rem; display: flex; align-items: center; gap: 0.5rem; font-size: 0.95rem; margin-top: 0; margin-bottom: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">
                    🚀 Próximas Visitas (${upcomingVisits.length})
                </h4>
                ${upcomingVisits.length === 0 
                    ? '<p class="text-muted" style="font-size:0.85rem; padding: 1rem; text-align: center; background: rgba(255,255,255,0.01); border: 1px dashed rgba(255,255,255,0.06); border-radius: 8px;">No hay visitas próximas agendadas.</p>'
                    : `<div class="visit-list-container" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1rem;">
                        ${upcomingVisits.map(i => renderVisitCard(i, true)).join('')}
                       </div>`
                }
            </div>

            <!-- Visitas Pasadas -->
            <div class="visits-section-past">
                <h4 style="color: #888888; border-bottom: 2px solid rgba(255, 255, 255, 0.08); padding-bottom: 0.4rem; display: flex; align-items: center; gap: 0.5rem; font-size: 0.95rem; margin-top: 0; margin-bottom: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">
                    ✓ Historial / Visitas Pasadas (${pastVisits.length})
                </h4>
                ${pastVisits.length === 0 
                    ? '<p class="text-muted" style="font-size:0.85rem; padding: 1rem; text-align: center; background: rgba(255,255,255,0.01); border: 1px dashed rgba(255,255,255,0.06); border-radius: 8px;">No hay registro de visitas pasadas.</p>'
                    : `<div class="visit-list-container" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1rem;">
                        ${pastVisits.map(i => renderVisitCard(i, false)).join('')}
                       </div>`
                }
            </div>
        </div>
    `;

    list.innerHTML = listTitleHtml + splitHtml;
}

function changeCalendarMonth(offset) {
    calendarMonthCursor = new Date(calendarMonthCursor.getFullYear(), calendarMonthCursor.getMonth() + offset, 1);
    selectedCalendarDay = null;
    renderCalendarMonth();
}

async function loadCalendar() {
    const res = await apiCall('/admin_supply.php?action=calendar-list', 'GET', null, { silent: true });
    if (!res || !res.success || !Array.isArray(res.items)) {
        calendarVisits = [];
        renderCalendarMonth();
        return;
    }
    calendarVisits = res.items;
    renderCalendarMonth();
}

function addMappedProductToOrder() {
    const select = document.getElementById('poMappedProduct');
    const quantityInput = document.getElementById('poQty');
    const costInput = document.getElementById('poCost');
    const quantity = Number(quantityInput?.value || 0);
    const estimated_cost = Number(costInput?.value || 0);

    if (!select || !select.value || quantity <= 0) {
        showAlert('Selecciona producto proveedor y cantidad mayor a 0', 'warning');
        return;
    }
    if (quantity > 999999) {
        showAlert('Cantidad muy grande (máximo 999999)', 'warning');
        return;
    }
    if (estimated_cost < 0) {
        showAlert('El costo estimado no puede ser negativo', 'warning');
        return;
    }

    const opt = select.options[select.selectedIndex];
    const supplier_product_id = Number(opt.value || 0);
    const product_name = opt.getAttribute('data-product-name') || '';
    const sku = opt.getAttribute('data-sku') || '';

    supplierOrderItems.push({ supplier_product_id, product_name, sku, quantity, estimated_cost });
    renderPoItems();
    quantityInput.value = '1';
    costInput.value = '0';
    select.selectedIndex = 0;
}

function renderPoItems() {
    const box = document.getElementById('poItems');
    if (supplierOrderItems.length === 0) {
        box.innerHTML = '<p class="text-muted">No hay items</p>';
        return;
    }
    box.innerHTML = '<ul>' + supplierOrderItems.map((i, idx) => `<li>${escapeHtml(displayProductLabel(i.sku, i.product_name))} | ${i.quantity} | $${Number(i.estimated_cost || 0).toFixed(2)} <button class="btn btn-small btn-danger" onclick="removePoItem(${idx})">Quitar</button></li>`).join('') + '</ul>';
}

function removePoItem(index) {
    supplierOrderItems = supplierOrderItems.filter((_, i) => i !== index);
    renderPoItems();
}

async function loadSupplierProducts() {
    const res = await apiCall('/admin_supply.php?action=supplier-products-list', 'GET', null, { silent: true });
    const listBox = document.getElementById('supplierProductList');
    const productSelect = document.getElementById('spProduct');

    if (productSelect) {
        productSelect.innerHTML = '<option value="">Cargando productos...</option>';

        const sources = [
            { endpoint: '/admin_supply.php?action=stock', key: 'items' },
            { endpoint: '/products.php?action=list-all', key: 'items' },
            { endpoint: '/products.php?action=list', key: 'products' }
        ];

        let products = [];
        for (const source of sources) {
            const sourceRes = await apiCall(source.endpoint, 'GET', null, { silent: true });
            const items = sourceRes && sourceRes.success && Array.isArray(sourceRes[source.key]) ? sourceRes[source.key] : [];
            if (items.length > 0) {
                products = items;
                break;
            }
        }

        if (products.length > 0) {
            productSelect.innerHTML = '<option value="">Selecciona producto...</option>' + products
                .map((p) => `<option value="${Number(p.id)}">${escapeHtml(displayProductLabel(p.sku, p.name))}</option>`)
                .join('');
        } else {
            productSelect.innerHTML = '<option value="">No hay productos disponibles</option>';
        }
    }

    if (!res || !res.success || !Array.isArray(res.items)) {
        if (listBox) listBox.innerHTML = '<p class="text-muted">Sin asignaciones.</p>';
        return;
    }

    if (listBox) {
        if (res.items.length === 0) {
            listBox.innerHTML = '<p class="text-muted">Sin asignaciones.</p>';
        } else {
            listBox.innerHTML = '<ul>' + res.items.map((i) => `<li>${escapeHtml(i.supplier_name)} -> ${escapeHtml(displayProductLabel(i.sku, i.product_name))} (${escapeHtml(i.supplier_sku || 'sin SKU')}) $${Number(i.unit_cost || 0).toFixed(2)}</li>`).join('') + '</ul>';
        }
    }
}

async function createSupplierProductLink() {
    const productId = Number(document.getElementById('spProduct')?.value || 0);
    const supplierName = document.getElementById('spSupplier')?.value?.trim() || '';
    const unitCost = Number(document.getElementById('spUnitCost')?.value || 0);
    const resultBox = document.getElementById('supplierProductResult');

    // Validation
    if (productId <= 0) {
        if (resultBox) resultBox.innerHTML = '<div class="alert alert-error">Selecciona un producto.</div>';
        showAlert('Producto requerido', 'warning');
        return;
    }
    if (!supplierName) {
        if (resultBox) resultBox.innerHTML = '<div class="alert alert-error">El nombre del proveedor es requerido.</div>';
        showAlert('Proveedor requerido', 'warning');
        return;
    }
    if (supplierName.length > 200) {
        if (resultBox) resultBox.innerHTML = '<div class="alert alert-error">El nombre del proveedor es muy largo.</div>';
        showAlert('Nombre de proveedor muy largo', 'warning');
        return;
    }
    if (unitCost < 0) {
        if (resultBox) resultBox.innerHTML = '<div class="alert alert-error">El costo unitario no puede ser negativo.</div>';
        showAlert('Costo inválido', 'warning');
        return;
    }

    const payload = {
        product_id: productId,
        supplier_name: supplierName,
        supplier_sku: document.getElementById('spSupplierSku')?.value?.trim() || '',
        unit_cost: unitCost
    };

    const res = await apiCall('/admin_supply.php?action=supplier-product-create', 'POST', payload);
    if (!res || !res.success) {
        if (resultBox) resultBox.innerHTML = `<div class="alert alert-error">${escapeHtml((res && res.message) ? res.message : 'No fue posible guardar')}</div>`;
        return;
    }

    if (resultBox) resultBox.innerHTML = `<div class="alert alert-success">${escapeHtml(res.message || 'Asignacion guardada')}</div>`;
    document.getElementById('spSupplier').value = '';
    document.getElementById('spSupplierSku').value = '';
    document.getElementById('spUnitCost').value = '0';
    document.getElementById('spProduct').value = '';
    await loadSupplierProducts();
    await loadMappedProductsBySupplier();
}

async function loadMappedProductsBySupplier() {
    const supplier = document.getElementById('poSupplier').value.trim();
    const select = document.getElementById('poMappedProduct');
    if (!select) return;

    if (!supplier) {
        select.innerHTML = '<option value="">Captura proveedor...</option>';
        return;
    }

    const res = await apiCall(`/admin_supply.php?action=supplier-products-by-supplier&supplier_name=${encodeURIComponent(supplier)}`, 'GET', null, { silent: true });
    if (!res || !res.success || !Array.isArray(res.items) || res.items.length === 0) {
        select.innerHTML = '<option value="">Sin productos para proveedor</option>';
        return;
    }

    select.innerHTML = '<option value="">Selecciona producto...</option>' + res.items.map((i) => {
        const label = `${displayProductLabel(i.sku, i.product_name)} | ${i.supplier_sku || 'sin SKU prov.'}`;
        return `<option value="${Number(i.id)}" data-product-name="${escapeHtml(i.product_name)}" data-sku="${escapeHtml(i.sku)}">${escapeHtml(label)}</option>`;
    }).join('');
}

async function createSupplierOrder() {
    const supplierName = document.getElementById('poSupplier')?.value?.trim() || '';
    const expectedDate = document.getElementById('poDate')?.value || '';

    // Validation
    if (!supplierName) {
        showAlert('El nombre del proveedor es requerido', 'warning');
        return;
    }
    if (supplierName.length > 200) {
        showAlert('Nombre de proveedor muy largo', 'warning');
        return;
    }
    if (!expectedDate) {
        showAlert('La fecha de recepción es requerida', 'warning');
        return;
    }
    if (supplierOrderItems.length === 0) {
        showAlert('Agrega al menos un producto a la orden', 'warning');
        return;
    }

    const payload = {
        supplier_name: supplierName,
        expected_date: expectedDate,
        items: supplierOrderItems
    };
    const res = await apiCall('/admin_supply.php?action=supplier-order-create', 'POST', payload);
    if (!res || !res.success) {
        if (res) showAlert(res.message || 'No fue posible guardar la orden', 'error');
        return;
    }
    showAlert(res.message || 'Orden guardada correctamente', 'success');
    supplierOrderItems = [];
    renderPoItems();
    document.getElementById('poSupplier').value = '';
    document.getElementById('poDate').value = '';
    if (res.ticket_url) window.open(res.ticket_url, '_blank');
    loadSupplierOrders();
    loadHistory();
}

async function loadSupplierOrders() {
    const res = await apiCall('/admin_supply.php?action=supplier-order-list', 'GET', null, { silent: true });
    const body = document.getElementById('supplierRows');
    if (!res || !res.success || !Array.isArray(res.items) || res.items.length === 0) {
        body.innerHTML = '<tr><td colspan="5">Sin ordenes</td></tr>';
        return;
    }
    body.innerHTML = res.items.map(i => `<tr>
        <td>${escapeHtml(i.folio)}</td>
        <td>${escapeHtml(i.supplier_name)}</td>
        <td>${escapeHtml(i.expected_date)}</td>
        <td>$${Number(i.total_estimated || 0).toFixed(2)}</td>
        <td><a class="btn btn-small btn-primary" href="/ticket_supplier.php?id=${i.id}" target="_blank">Imprimir</a></td>
    </tr>`).join('');
}

async function loadHistory() {
    const res = await apiCall('/admin_supply.php?action=history', 'GET', null, { silent: true });
    const body = document.getElementById('historyRows');
    if (!res || !res.success || !Array.isArray(res.items) || res.items.length === 0) {
        body.innerHTML = '<tr><td colspan="4">Sin registros</td></tr>';
        return;
    }
    body.innerHTML = res.items.map(i => `<tr>
        <td>${escapeHtml(i.transaction_type)}</td>
        <td>${escapeHtml(i.reference_folio)}</td>
        <td>${escapeHtml(i.created_at)}</td>
        <td><small>${escapeHtml(i.data_json || '')}</small></td>
    </tr>`).join('');
}

async function saveClientByAdmin() {
    const clientId = document.getElementById('clientEditId').value || '';
    const firstName = document.getElementById('clientFirstName').value?.trim() || '';
    const lastName = document.getElementById('clientLastName').value?.trim() || '';
    const phone = document.getElementById('clientPhone').value?.trim() || '';
    const email = document.getElementById('clientEmail').value?.trim() || '';
    const company = document.getElementById('clientCompany').value?.trim() || '';
    const birthdate = document.getElementById('clientBirthdate').value || null;
    const box = document.getElementById('clientCreateResult');

    // Validation
    if (!firstName) {
        if (box) box.innerHTML = '<div class="alert alert-error">El nombre es requerido.</div>';
        showAlert('Nombre requerido', 'warning');
        return;
    }
    if (!lastName) {
        if (box) box.innerHTML = '<div class="alert alert-error">El apellido es requerido.</div>';
        showAlert('Apellido requerido', 'warning');
        return;
    }
    if (!phone) {
        if (box) box.innerHTML = '<div class="alert alert-error">El teléfono es requerido.</div>';
        showAlert('Teléfono requerido', 'warning');
        return;
    }
    if (!/^\d{10,15}$/.test(phone.replace(/[\s\-\(\)]/g, ''))) {
        if (box) box.innerHTML = '<div class="alert alert-error">El teléfono debe tener entre 10 y 15 dígitos.</div>';
        showAlert('Formato de teléfono inválido', 'warning');
        return;
    }
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        if (box) box.innerHTML = '<div class="alert alert-error">El email tiene un formato inválido.</div>';
        showAlert('Email inválido', 'warning');
        return;
    }
    if (birthdate && new Date(birthdate) > new Date()) {
        if (box) box.innerHTML = '<div class="alert alert-error">La fecha de nacimiento no puede ser en el futuro.</div>';
        showAlert('Fecha de nacimiento inválida', 'warning');
        return;
    }

    const payload = {
        id: clientId,
        first_name: firstName,
        last_name: lastName,
        phone: phone,
        email: email,
        company_name: company,
        birthdate: birthdate || null
    };

    const endpoint = clientId
        ? '/admin_supply.php?action=client-update'
        : '/admin_clients.php?action=create';
    const res = await apiCall(endpoint, 'POST', payload);

    if (!res || !res.success) {
        if (box) {
            const fallbackMessage = clientId ? 'No fue posible actualizar al cliente' : 'No fue posible registrar al cliente';
            box.innerHTML = `<div class="alert alert-error">${escapeHtml((res && res.message) ? res.message : fallbackMessage)}</div>`;
        }
        return;
    }

    if (box) {
        const code = displayClientCode(res.client.user_code || '');
        box.innerHTML = `
            <div class="alert alert-success">
                Cliente ${clientId ? 'actualizado' : 'registrado'} correctamente.<br>
                <strong>Código único:</strong> ${escapeHtml(code || 'N/A')}<br>
                <strong>Login con teléfono:</strong> ${escapeHtml(res.client.phone || '')}
            </div>
        `;
    }

    showAlert(clientId ? 'Cliente actualizado correctamente' : 'Cliente registrado correctamente', 'success');
    resetClientForm();
    loadClients();
}

async function deleteClientByAdmin(clientId) {
    if (!clientId) return;

    if (!confirm('¿Deseas eliminar este cliente? Esta acción no se puede deshacer.')) {
        return;
    }

    const res = await apiCall('/admin_clients.php?action=delete', 'POST', { id: clientId });
    const box = document.getElementById('clientCreateResult');

    if (!res || !res.success) {
        if (box) {
            box.innerHTML = `<div class="alert alert-error">${escapeHtml((res && res.message) ? res.message : 'No fue posible eliminar el cliente')}</div>`;
        }
        return;
    }

    if (box) {
        box.innerHTML = `<div class="alert alert-success">${escapeHtml(res.message || 'Cliente eliminado correctamente')}</div>`;
    }

    resetClientForm();
    loadClients();
}

async function loadProductImageReferences() {
    const stockSelect = document.getElementById('newProductImageRef');
    const marketplaceSelect = document.getElementById('marketplaceImageRef');
    if (!stockSelect && !marketplaceSelect) return;

    // Cargar imágenes únicas desde el servidor/base de datos
    const res = await apiCall('/admin_supply.php?action=product-images', 'GET', null, { silent: true });
    if (res && res.success && Array.isArray(res.images)) {
        const populateSelect = (selectEl, defaultVal) => {
            if (!selectEl) return;
            const currentSelected = selectEl.value || defaultVal;
            selectEl.innerHTML = '<option value="images/products/default-product.svg">Por defecto (Logo Truper)</option>';
            res.images.forEach(img => {
                if (img === 'images/products/default-product.svg') return;
                const opt = document.createElement('option');
                opt.value = img;
                opt.textContent = img.replace(/^images\/products\//, '');
                selectEl.appendChild(opt);
            });
            selectEl.value = currentSelected;
        };

        populateSelect(stockSelect, 'images/products/default-product.svg');
        populateSelect(marketplaceSelect, 'images/products/default-product.svg');
    }

    const stockState = getGalleryState('stock');
    const marketplaceState = getGalleryState('marketplace');

    if (stockSelect && stockState && stockState.cover) {
        let exists = false;
        for (let i = 0; i < stockSelect.options.length; i++) {
            if (stockSelect.options[i].value === stockState.cover) exists = true;
        }
        if (!exists) {
            const opt = document.createElement('option');
            opt.value = stockState.cover;
            opt.textContent = stockState.cover.replace(/^images\/products\//, '');
            stockSelect.appendChild(opt);
        }
        stockSelect.value = stockState.cover;
    }

    if (marketplaceSelect && marketplaceState && marketplaceState.cover) {
        let exists = false;
        for (let i = 0; i < marketplaceSelect.options.length; i++) {
            if (marketplaceSelect.options[i].value === marketplaceState.cover) exists = true;
        }
        if (!exists) {
            const opt = document.createElement('option');
            opt.value = marketplaceState.cover;
            opt.textContent = marketplaceState.cover.replace(/^images\/products\//, '');
            marketplaceSelect.appendChild(opt);
        }
        marketplaceSelect.value = marketplaceState.cover;
    }

    updateStockPreview();
    updateMarketplacePreview();
}

async function loadProductCategories(onlyActive = true) {
    const categorySelect = document.getElementById('newProductCategory');
    const marketplaceCategorySelect = document.getElementById('marketplaceCategory');
    const categoriesListBox = document.getElementById('categoriesList');
    const action = `/admin_supply.php?action=categories-list${onlyActive ? '&active=1' : ''}`;
    const res = await apiCall(action, 'GET', null, { silent: false });

    if (!res || !res.success || !Array.isArray(res.items)) {
        // If API fails and we're asking for all categories, at least preserve existing select options
        if (onlyActive === false && (categorySelect || marketplaceCategorySelect)) {
            // Fallback: try to use existing options or continue with what's there
            console.warn('Failed to load categories from API, using existing options');
        }
        if (categoriesListBox && !onlyActive) {
            categoriesListBox.innerHTML = '<p class="text-muted">No fue posible cargar categorías.</p>';
        }
        return;
    }

    const fillSelect = function (selectEl) {
        if (!selectEl) return;
        const selectedValues = new Set(
            Array.from(selectEl.selectedOptions || []).map((option) => normalizeCategoryValue(option.value))
        );
        const seenCategories = new Set();
        selectEl.innerHTML = '';
        res.items.forEach((cat) => {
            const isCatActive = cat.is_active === true || cat.is_active === 't' || cat.is_active === '1' || Number(cat.is_active) === 1;
            if (!isCatActive) {
                return;
            }

            const categoryName = String(cat.name || '').trim();
            const categoryNameNormalized = normalizeCategoryValue(categoryName);

            if (seenCategories.has(categoryNameNormalized)) {
                return;
            }
            seenCategories.add(categoryNameNormalized);

            const option = document.createElement('option');
            option.value = categoryName;
            option.textContent = categoryName;
            option.dataset.id = String(Number(cat.id || 0));
            option.selected = selectedValues.has(categoryNameNormalized);
            selectEl.appendChild(option);
        });
    };

    fillSelect(categorySelect);
    fillSelect(marketplaceCategorySelect);
    console.log('Categories loaded:', res.items.length, 'items');

    if (!onlyActive && categoriesListBox) {
        if (res.items.length === 0) {
            categoriesListBox.innerHTML = '<p class="text-muted">No hay categorías registradas.</p>';
            return;
        }

        categoriesListBox.innerHTML = `
            <table>
                <thead><tr><th>Nombre</th><th>Orden</th><th>Activa</th><th>Acciones</th></tr></thead>
                <tbody>
                    ${res.items.map((cat) => `
                        <tr>
                            <td>${escapeHtml(cat.name || '')}</td>
                            <td>${Number(cat.sort_order || 0)}</td>
                            <td>${(cat.is_active === true || cat.is_active === 't' || cat.is_active === '1' || Number(cat.is_active) === 1) ? '<span class="badge badge-success">Sí</span>' : '<span class="badge badge-danger">No</span>'}</td>
                            <td>
                                <button class="btn btn-small btn-secondary" type="button" data-action="edit-category">Editar</button>
                                <button class="btn btn-small btn-danger" type="button" onclick="deleteCategoryByAdminId(${Number(cat.id || 0)})">Eliminar</button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;

        // Rebind edit buttons safely without inline payload risks.
        const rows = categoriesListBox.querySelectorAll('tbody tr');
        rows.forEach((row, idx) => {
            const editBtn = row.querySelector('[data-action="edit-category"]');
            const cat = res.items[idx];
            if (editBtn && cat) {
                editBtn.onclick = function () {
                    fillCategoryForm(cat);
                };
            }
        });
    }
}

async function refreshCategoriesUi() {
    await loadProductCategories(false);
    updateStockPreview();
    updateMarketplacePreview();
}

function resetCategoryForm() {
    const editId = document.getElementById('categoryEditId');
    const name = document.getElementById('categoryName');
    const order = document.getElementById('categoryOrder');
    const active = document.getElementById('categoryActive');
    const saveBtn = document.getElementById('categorySaveButton');
    const box = document.getElementById('categoryResult');

    if (editId) editId.value = '';
    if (name) name.value = '';
    if (order) order.value = '0';
    if (active) active.value = '1';
    if (saveBtn) saveBtn.textContent = 'Guardar categoría';
    if (box) box.innerHTML = '';
}

function fillCategoryForm(category) {
    if (!category) return;
    const saveBtn = document.getElementById('categorySaveButton');
    document.getElementById('categoryEditId').value = category.id || '';
    document.getElementById('categoryName').value = category.name || '';
    document.getElementById('categoryOrder').value = String(category.sort_order || 0);
    document.getElementById('categoryActive').value = (category.is_active === true || category.is_active === 't' || category.is_active === '1' || Number(category.is_active) === 1) ? '1' : '0';
    if (saveBtn) saveBtn.textContent = 'Actualizar categoría';
}

async function saveCategoryByAdmin() {
    const categoryId = Number(document.getElementById('categoryEditId')?.value || 0);
    const categoryName = document.getElementById('categoryName')?.value?.trim() || '';
    const categoryOrder = Number(document.getElementById('categoryOrder')?.value || 0);
    const categoryActive = document.getElementById('categoryActive')?.value === '1';
    const box = document.getElementById('categoryResult');

    // Validation
    if (!categoryName) {
        if (box) box.innerHTML = '<div class="alert alert-error">El nombre de la categoría es requerido.</div>';
        showAlert('Nombre de categoría requerido', 'warning');
        return;
    }
    if (categoryName.length > 120) {
        if (box) box.innerHTML = '<div class="alert alert-error">El nombre de la categoría es muy largo (máximo 120 caracteres).</div>';
        showAlert('Nombre muy largo', 'warning');
        return;
    }
    if (categoryOrder < 0 || categoryOrder > 999) {
        if (box) box.innerHTML = '<div class="alert alert-error">El orden debe estar entre 0 y 999.</div>';
        showAlert('Orden inválido', 'warning');
        return;
    }

    const payload = {
        id: categoryId,
        name: categoryName,
        sort_order: categoryOrder,
        is_active: categoryActive
    };
    const res = await apiCall('/admin_supply.php?action=categories-save', 'POST', payload);
    if (!res || !res.success) {
        if (box) box.innerHTML = `<div class="alert alert-error">${escapeHtml((res && res.message) ? res.message : 'No fue posible guardar categoría')}</div>`;
        return;
    }
    if (box) box.innerHTML = `<div class="alert alert-success">${escapeHtml(res.message || 'Categoría guardada')}</div>`;
    resetCategoryForm();
    await refreshCategoriesUi();
}

async function deleteCategoryByAdminId(id) {
    if (!id) return;
    if (!confirm('¿Deseas eliminar esta categoría?')) return;
    const box = document.getElementById('categoryResult');
    const res = await apiCall('/admin_supply.php?action=categories-delete', 'POST', { id: id });
    if (!res || !res.success) {
        if (box) box.innerHTML = `<div class="alert alert-error">${escapeHtml((res && res.message) ? res.message : 'No fue posible eliminar categoría')}</div>`;
        return;
    }
    if (box) box.innerHTML = `<div class="alert alert-success">${escapeHtml(res.message || 'Categoría eliminada')}</div>`;
    await refreshCategoriesUi();
}

function getCurrentStockSkuForGallery() {
    return normalizeNumericSku(document.getElementById('newProductSku')?.value || '');
}

function getCurrentMarketplaceSkuForGallery() {
    return normalizeNumericSku(document.getElementById('marketplaceSku')?.value || '');
}

function showGalleryResult(mode, message, tone = 'success') {
    const targetId = mode === 'marketplace' ? 'marketplaceResult' : 'productCreateResult';
    const box = document.getElementById(targetId);
    if (!box) return;
    box.innerHTML = `<div class="alert alert-${tone}">${escapeHtml(message)}</div>`;
}

function getGalleryState(mode = 'stock') {
    return galleryStateCache[mode] || galleryStateCache.stock;
}

function setGalleryState(mode, sku, images, cover = '') {
    const key = mode === 'marketplace' ? 'marketplace' : 'stock';
    galleryStateCache[key] = {
        sku: String(sku || ''),
        images: Array.isArray(images) ? images.slice() : [],
        cover: String(cover || '')
    };
}

function syncGalleryModeUi(mode, cover = '') {
    if (mode === 'marketplace') {
        const target = document.getElementById('marketplaceImageRef');
        if (target && cover) {
            if (target.tagName.toLowerCase() === 'select') {
                let exists = false;
                for (let i = 0; i < target.options.length; i++) {
                    if (target.options[i].value === cover) exists = true;
                }
                if (!exists) {
                    const opt = document.createElement('option');
                    opt.value = cover;
                    opt.text = cover;
                    target.appendChild(opt);
                }
            }
            target.value = cover;
        }
        updateMarketplacePreview();
        return;
    }

    const target = document.getElementById('newProductImageRef');
    if (target && cover) {
        if (target.tagName.toLowerCase() === 'select') {
            let exists = false;
            for (let i = 0; i < target.options.length; i++) {
                if (target.options[i].value === cover) exists = true;
            }
            if (!exists) {
                const opt = document.createElement('option');
                opt.value = cover;
                opt.text = cover;
                target.appendChild(opt);
            }
        }
        target.value = cover;
    }
    updateStockPreview();
}

function getGalleryImagesForMode(mode, sku) {
    const state = getGalleryState(mode);
    if (state && state.sku === String(sku || '') && Array.isArray(state.images) && state.images.length > 0) {
        return state.images.slice();
    }
    return [];
}

function primeGalleryFromCurrentForm(mode) {
    const isMarketplace = mode === 'marketplace';
    const sku = normalizeNumericSku(document.getElementById(isMarketplace ? 'marketplaceSku' : 'newProductSku')?.value || '');
    if (!/^\d{5,6}$/.test(sku)) {
        return;
    }

    const imageRef = String(document.getElementById(isMarketplace ? 'marketplaceImageRef' : 'newProductImageRef')?.value || '').trim();
    const cachedImages = getGalleryImagesForMode(mode, sku);
    const images = cachedImages.length > 0
        ? cachedImages
        : (imageRef && !imageRef.includes('default-product.svg') ? [imageRef] : []);

    if (images.length === 0) {
        return;
    }

    const cover = images[0] || imageRef || '';
    setGalleryState(mode, sku, images, cover);
    renderProductGallery(images, sku, mode);
    syncGalleryModeUi(mode, cover);
}

async function reorderGalleryImages(sku, orderedImages, mode = 'stock') {
    const previousState = getGalleryState(mode);
    const previousImages = previousState && previousState.sku === String(sku || '') && Array.isArray(previousState.images)
        ? previousState.images.slice()
        : [];
    const nextImages = Array.isArray(orderedImages) ? orderedImages.slice() : [];
    const nextCover = nextImages[0] || '';

    setGalleryState(mode, sku, nextImages, nextCover);
    renderProductGallery(nextImages, sku, mode);
    syncGalleryModeUi(mode, nextCover);

    const res = await apiCall('/admin_supply.php?action=product-gallery-reorder', 'POST', {
        sku: sku,
        images: nextImages
    });

    if (!res || !res.success) {
        setGalleryState(mode, sku, previousImages, previousImages[0] || '');
        renderProductGallery(previousImages, sku, mode);
        syncGalleryModeUi(mode, previousImages[0] || '');
        showGalleryResult(mode, (res && res.message) ? res.message : 'No se pudo reordenar la galería', 'error');
        return false;
    }

    const renderedImages = Array.isArray(res.images) && res.images.length > 0 ? res.images : nextImages;
    const cover = res.cover || renderedImages[0] || '';
    setGalleryState(mode, sku, renderedImages, cover);
    renderProductGallery(renderedImages, sku, mode);
    syncGalleryModeUi(mode, cover);
    showGalleryResult(mode, res.message || 'Orden de imágenes actualizado', 'success');
    
    // Update main product caches and grids so the new cover is visible
    try {
        if (mode === 'stock') {
            const prod = stockItemsCache.find(p => String(p.sku || '') === String(sku));
            if (prod) {
                prod.image_url = cover || prod.image_url;
                upsertStockCache(prod);
            }
            void loadStock(stockCurrentPage);
        } else {
            const item = marketplaceItemsCache.find(p => String(p.sku || '') === String(sku));
            if (item) {
                item.image_url = cover || item.image_url;
                upsertMarketplaceCache(item);
            }
            void loadMarketplaceCeAdmin(marketplaceCurrentPage);
        }
    } catch (e) {
        console.warn('Cache update after reorder failed:', e);
    }

    void loadProductImageReferences();
    return true;
}

async function moveGalleryImage(sku, imagePath, direction, mode = 'stock') {
    let images = getGalleryImagesForMode(mode, sku);
    if (images.length === 0) {
        const listAction = `/admin_supply.php?action=product-gallery-list&sku=${encodeURIComponent(sku)}`;
        const listing = await apiCall(listAction, 'GET', null, { silent: true });
        images = Array.isArray(listing?.images) ? listing.images.slice() : [];
        setGalleryState(mode, sku, images, listing?.cover || images[0] || '');
    }
    const index = images.indexOf(imagePath);
    if (index < 0) {
        showGalleryResult(mode, 'No se encontró la imagen a mover', 'error');
        return;
    }

    const target = direction === 'up' ? index - 1 : index + 1;
    if (target < 0 || target >= images.length) {
        return;
    }

    const temp = images[index];
    images[index] = images[target];
    images[target] = temp;
    await reorderGalleryImages(sku, images, mode);
}

async function moveGalleryImageToPosition(sku, imagePath, targetPosition, mode = 'stock') {
    let images = getGalleryImagesForMode(mode, sku);
    if (images.length === 0) {
        const listAction = `/admin_supply.php?action=product-gallery-list&sku=${encodeURIComponent(sku)}`;
        const listing = await apiCall(listAction, 'GET', null, { silent: true });
        images = Array.isArray(listing?.images) ? listing.images.slice() : [];
        setGalleryState(mode, sku, images, listing?.cover || images[0] || '');
    }
    const index = images.indexOf(imagePath);
    if (index < 0) {
        showGalleryResult(mode, 'No se encontró la imagen a mover', 'error');
        return;
    }

    const positionNumber = Number(targetPosition || 0);
    if (!Number.isInteger(positionNumber) || positionNumber < 1 || positionNumber > images.length) {
        showGalleryResult(mode, 'Posición inválida', 'error');
        return;
    }

    const targetIndex = positionNumber - 1;
    if (targetIndex === index) {
        return;
    }

    const [moved] = images.splice(index, 1);
    images.splice(targetIndex, 0, moved);
    await reorderGalleryImages(sku, images, mode);
}

function renderProductGallery(images, sku, mode = 'stock') {
    // Store in global cache to avoid passing large base64 strings in onclick attrs
    if (mode === 'marketplace') {
        marketplaceGalleryCache = images.slice();
    } else {
        stockGalleryCache = images.slice();
    }

    const host = document.getElementById(mode === 'marketplace' ? 'marketplaceGalleryList' : 'productGalleryList');
    const status = document.getElementById(mode === 'marketplace' ? 'marketplaceGalleryStatus' : 'productGalleryStatus');
    if (!host || !status) return;

    if (!Array.isArray(images) || images.length === 0) {
        host.innerHTML = '<div style="padding:1rem;text-align:center;color:var(--ui-text-muted,#aaa);font-size:13px;">Sin im\u00e1genes cargadas. Sube una imagen arriba.</div>';
        status.textContent = `Sin im\u00e1genes para ${sku}.`;
        return;
    }

    status.textContent = `Galer\u00eda para ${sku}: ${images.length} imagen(es). Usa Drag & Drop para reordenar.`;

    // Helper: convierte ruta relativa a URL absoluta desde ra\u00edz del sitio
    const toSrc = (img) => {
        if (!img) return '';
        // blob URLs or absolute URLs \u2014 usar tal cual
        if (img.startsWith('blob:') || img.startsWith('http') || img.startsWith('//')) return img;
        // Asegurar que empiece con /
        const clean = img.startsWith('/') ? img : '/' + img;
        return clean + '?t=' + Date.now();
    };

    host.innerHTML = images.map((img, idx) => {
        const src = toSrc(img);
        const isCover = idx === 0;
        return `
        <div class="gallery-item" data-index="${idx}" data-sku="${sku}" data-mode="${mode}" draggable="true"
             style="border:2px solid ${isCover ? 'var(--theme-accent, #f80)' : 'var(--ui-border, #444)'}; border-radius:10px; padding:0.4rem; background:var(--ui-surface-soft, #1e1e2e); position:relative; cursor:grab; transition:all 0.2s; user-select:none;">
            ${isCover ? '<div style="position:absolute;top:4px;left:4px;background:var(--theme-accent,#f80);color:#fff;font-size:9px;font-weight:700;padding:2px 7px;border-radius:6px;z-index:2;letter-spacing:.5px;">\u2605 PORTADA</div>' : ''}
            <div style="width:100%;height:130px;overflow:hidden;border-radius:7px;background:#111;display:flex;align-items:center;justify-content:center;">
                <img src="${escapeHtml(src)}"
                     alt="Imagen ${idx + 1}"
                     style="width:100%;height:100%;object-fit:cover;border-radius:7px;pointer-events:none;display:block;"
                     loading="lazy"
                     onerror="this.style.display=\'none\';this.parentElement.innerHTML=\'<div style=\\'color:#888;font-size:22px;text-align:center;padding:30px;\\\'>\uD83D\uDDBC\uFE0F</div>\';">
            </div>
            <div style="display:flex;align-items:center;gap:0.3rem;margin-top:0.4rem;flex-wrap:wrap;justify-content:center;">
                <label style="font-size:10px;color:var(--ui-text-muted,#aaa);">Pos:</label>
                <select id="galleryPos-${mode}-${idx}" style="max-width:55px;font-size:11px;padding:1px 2px;" onchange="galleryMoveByIndex('${escapeHtml(sku)}', ${idx}, this.value, '${mode}')">
                    ${images.map((_, pos) => `<option value="${pos + 1}" ${pos === idx ? 'selected' : ''}>${pos + 1}</option>`).join('')}
                </select>
                ${!isCover ? `<button class="btn btn-small btn-secondary" type="button" style="font-size:10px;padding:2px 5px;" title="Usar como portada" onclick="galleryCoverByIndex('${escapeHtml(sku)}', ${idx}, '${mode}')">\u2605</button>` : ''}
                <button class="btn btn-small btn-danger" type="button" style="font-size:10px;padding:2px 6px;" title="Eliminar imagen" onclick="galleryDeleteByIndex('${escapeHtml(sku)}', ${idx}, '${mode}')">&#x2715;</button>
            </div>
        </div>`;
    }).join('');

    // Add drag-drop listeners after rendering
    setupGalleryDragDrop(mode, sku);
}

// Setup drag-and-drop for gallery items
function setupGalleryDragDrop(mode, sku) {
    const host = document.getElementById(mode === 'marketplace' ? 'marketplaceGalleryList' : 'productGalleryList');
    if (!host) return;

    const items = host.querySelectorAll('[data-index][draggable="true"]');
    let draggedItem = null;

    items.forEach(item => {
        item.addEventListener('dragstart', (e) => {
            draggedItem = item;
            item.style.opacity = '0.5';
            e.dataTransfer.effectAllowed = 'move';
        });

        item.addEventListener('dragend', (e) => {
            item.style.opacity = '1';
            draggedItem = null;
            // Clear all drop indicators
            items.forEach(i => i.style.borderStyle = 'solid');
        });

        item.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            if (draggedItem && draggedItem !== item) {
                item.style.borderStyle = 'dashed';
                item.style.opacity = '0.7';
            }
        });

        item.addEventListener('dragleave', (e) => {
            if (e.target === item) {
                item.style.borderStyle = 'solid';
                item.style.opacity = '1';
            }
        });

        item.addEventListener('drop', async (e) => {
            e.preventDefault();
            if (draggedItem && draggedItem !== item) {
                const fromIdx = parseInt(draggedItem.dataset.index);
                const toIdx = parseInt(item.dataset.index);
                const cache = mode === 'marketplace' ? marketplaceGalleryCache : stockGalleryCache;
                
                if (cache && cache.length > 0) {
                    const images = cache.slice();
                    const [moved] = images.splice(fromIdx, 1);
                    images.splice(toIdx, 0, moved);
                    await reorderGalleryImages(sku, images, mode);
                }
            }
            item.style.borderStyle = 'solid';
            item.style.opacity = '1';
        });
    });
}

// --- Index-based gallery helpers (avoid passing base64 in HTML onclick attrs) ---
function galleryGetByIndex(index, mode) {
    const cache = mode === 'marketplace' ? marketplaceGalleryCache : stockGalleryCache;
    return (cache && cache[index] !== undefined) ? cache[index] : null;
}

async function galleryDeleteByIndex(sku, index, mode) {
    const img = galleryGetByIndex(index, mode);
    if (!img) { showGalleryResult(mode, 'Imagen no encontrada en caché', 'error'); return; }
    if (mode === 'marketplace') return deleteMarketplaceGalleryImage(sku, img);
    return deleteProductGalleryImage(sku, img);
}

async function galleryCoverByIndex(sku, index, mode) {
    const img = galleryGetByIndex(index, mode);
    if (!img) { showGalleryResult(mode, 'Imagen no encontrada en caché', 'error'); return; }
    if (mode === 'marketplace') return setMarketplaceGalleryCover(sku, img);
    return setProductGalleryCover(sku, img);
}

async function galleryMoveByIndex(sku, currentIndex, targetPosition, mode) {
    const cache = mode === 'marketplace' ? marketplaceGalleryCache : stockGalleryCache;
    if (!cache || cache.length === 0) { showGalleryResult(mode, 'Galería no cargada', 'error'); return; }
    const images = cache.slice();
    const targetIndex = Number(targetPosition) - 1;
    if (targetIndex === currentIndex || targetIndex < 0 || targetIndex >= images.length) return;
    const [moved] = images.splice(currentIndex, 1);
    images.splice(targetIndex, 0, moved);
    await reorderGalleryImages(sku, images, mode);
}


async function loadProductGalleryForCurrentSku() {
    const sku = getCurrentStockSkuForGallery();
    const host = document.getElementById('productGalleryList');
    const status = document.getElementById('productGalleryStatus');
    if (!host || !status) return;

    if (!/^\d{5,6}$/.test(sku)) {
        host.innerHTML = '';
        status.textContent = 'Escribe un código de 5 o 6 números para cargar su galería.';
        return;
    }

    const res = await apiCall(`/admin_supply.php?action=product-gallery-list&sku=${encodeURIComponent(sku)}`, 'GET', null, { silent: true });
    if (!res || !res.success) {
        status.textContent = (res && res.message) ? res.message : 'No se pudo cargar la galería del producto.';
        host.innerHTML = '';
        return;
    }

    const imagesToRender = Array.isArray(res.images) && res.images.length > 0 
        ? res.images 
        : (res.cover ? [res.cover] : []);

    setGalleryState('stock', sku, imagesToRender, res.cover || imagesToRender[0] || '');

    if (imagesToRender.length === 0) {
        const seed = document.getElementById('newProductImageRef')?.value || '';
        if (seed && !seed.includes('default-product.svg')) {
            setGalleryState('stock', sku, [seed], seed);
            renderProductGallery([seed], sku, 'stock');
            status.textContent = `Galería para ${sku}: 1 imagen(es)`;

            const bootstrap = await apiCall('/admin_supply.php?action=product-gallery-bootstrap', 'POST', {
                sku: sku,
                image: seed
            });
            if (bootstrap && bootstrap.success) {
                const bootstrapImages = Array.isArray(bootstrap.images) && bootstrap.images.length > 0 ? bootstrap.images : [seed];
                setGalleryState('stock', sku, bootstrapImages, bootstrap.cover || bootstrapImages[0] || seed);
                renderProductGallery(bootstrapImages, sku, 'stock');
                if (bootstrap.cover) {
                    const select = document.getElementById('newProductImageRef');
                    if (select) select.value = bootstrap.cover;
                }
                status.textContent = `Galería para ${sku}: ${(bootstrap.images || [seed]).length} imagen(es)`;
                updateStockPreview();
                return;
            }
            updateStockPreview();
            return;
        }
    }

    renderProductGallery(imagesToRender, sku, 'stock');
    syncGalleryModeUi('stock', res.cover || imagesToRender[0] || '');
}

async function loadMarketplaceGalleryForCurrentSku() {
    const sku = getCurrentMarketplaceSkuForGallery();
    const host = document.getElementById('marketplaceGalleryList');
    const status = document.getElementById('marketplaceGalleryStatus');
    if (!host || !status) return;

    if (!/^\d{5,6}$/.test(sku)) {
        host.innerHTML = '';
        status.textContent = 'Escribe un código de 5 o 6 números para cargar su galería CE.';
        return;
    }

    const res = await apiCall(`/admin_supply.php?action=product-gallery-list&sku=${encodeURIComponent(sku)}`, 'GET', null, { silent: true });
    if (!res || !res.success) {
        status.textContent = (res && res.message) ? res.message : 'No se pudo cargar la galería del artículo CE.';
        host.innerHTML = '';
        return;
    }

    const imagesToRender = Array.isArray(res.images) && res.images.length > 0 
        ? res.images 
        : (res.cover ? [res.cover] : []);

    setGalleryState('marketplace', sku, imagesToRender, res.cover || imagesToRender[0] || '');

    if (imagesToRender.length === 0) {
        const seed = document.getElementById('marketplaceImageRef')?.value || '';
        if (seed && !seed.includes('default-product.svg')) {
            setGalleryState('marketplace', sku, [seed], seed);
            renderProductGallery([seed], sku, 'marketplace');
            status.textContent = `Galería para ${sku}: 1 imagen(es)`;

            const bootstrap = await apiCall('/admin_supply.php?action=product-gallery-bootstrap', 'POST', {
                sku: sku,
                image: seed
            });
            if (bootstrap && bootstrap.success) {
                const bootstrapImages = Array.isArray(bootstrap.images) && bootstrap.images.length > 0 ? bootstrap.images : [seed];
                setGalleryState('marketplace', sku, bootstrapImages, bootstrap.cover || bootstrapImages[0] || seed);
                renderProductGallery(bootstrapImages, sku, 'marketplace');
                if (bootstrap.cover) {
                    const select = document.getElementById('marketplaceImageRef');
                    if (select) select.value = bootstrap.cover;
                }
                status.textContent = `Galería para ${sku}: ${(bootstrap.images || [seed]).length} imagen(es)`;
                updateMarketplacePreview();
                return;
            }
            updateMarketplacePreview();
            return;
        }
    }

    renderProductGallery(imagesToRender, sku, 'marketplace');
    syncGalleryModeUi('marketplace', res.cover || imagesToRender[0] || '');
}

async function setProductGalleryCover(sku, imagePath) {
    const res = await apiCall('/admin_supply.php?action=product-gallery-cover', 'POST', {
        sku: sku,
        image: imagePath
    });



    if (!res || !res.success) {
        showGalleryResult('stock', (res && res.message) ? res.message : 'No se pudo cambiar la portada', 'error');
        return;
    }

    showGalleryResult('stock', res.message || 'Portada actualizada', 'success');

    // Optimistic update: move selected image to first position in cache and re-render
    try {
        const cache = stockGalleryCache || [];
        const idx = cache.findIndex((i) => i === imagePath);
        if (idx > 0) {
            cache.splice(idx, 1);
            cache.unshift(imagePath);
            setGalleryState('stock', sku, cache.slice(), imagePath);
            renderProductGallery(cache, sku, 'stock');
            syncGalleryModeUi('stock', imagePath);
        }
        // reflect cover into product preview and caches
        const prod = stockItemsCache.find(p => String(p.sku || '') === String(sku));
        if (prod) {
            prod.image_url = imagePath;
            upsertStockCache(prod);
        }
    } catch (e) {
        console.warn('Optimistic cover update failed:', e);
    }

    void loadStock(stockCurrentPage);
}

async function deleteProductGalleryImage(sku, imagePath) {
    if (!confirm('¿Eliminar esta imagen de la galería?')) return;

    // 1. Optimistic update INMEDIATO — antes de llamar a la API
    const previousImages = Array.isArray(stockGalleryCache) ? stockGalleryCache.slice() : [];
    const previousCover = previousImages[0] || '';
    const optimisticImages = previousImages.filter((i) => i !== imagePath);

    setGalleryState('stock', sku, optimisticImages.slice(), optimisticImages[0] || '');
    renderProductGallery(optimisticImages, sku, 'stock');
    syncGalleryModeUi('stock', optimisticImages[0] || '');

    // 2. Llamada a la API
    const res = await apiCall('/admin_supply.php?action=product-gallery-delete', 'POST', {
        sku: sku,
        image: imagePath
    });

    if (!res || !res.success) {
        // Revertir al estado anterior si falla
        setGalleryState('stock', sku, previousImages.slice(), previousCover);
        renderProductGallery(previousImages, sku, 'stock');
        syncGalleryModeUi('stock', previousCover);
        showGalleryResult('stock', (res && res.message) ? res.message : 'No se pudo eliminar la imagen', 'error');
        return;
    }

    showGalleryResult('stock', res.message || 'Imagen eliminada', 'success');

    // 3. Usar las imágenes que devuelve el servidor como fuente de verdad definitiva
    //    (NO hacer re-fetch — evita que la imagen reaparezca)
    const serverImages = Array.isArray(res.images) && res.images.length >= 0
        ? res.images
        : optimisticImages;
    const serverCover = res.cover || serverImages[0] || '';

    setGalleryState('stock', sku, serverImages.slice(), serverCover);
    renderProductGallery(serverImages, sku, 'stock');
    syncGalleryModeUi('stock', serverCover);

    // Actualizar cache de producto si existe
    try {
        const prod = stockItemsCache.find(p => String(p.sku || '') === String(sku));
        if (prod) {
            prod.image_url = serverCover || prod.image_url;
            upsertStockCache(prod);
        }
    } catch (e) {
        console.warn('Stock cache update after delete failed:', e);
    }
}


function moveProductGalleryImage(sku, imagePath, direction) {
    return moveGalleryImage(sku, imagePath, direction, 'stock');
}

function moveMarketplaceGalleryImage(sku, imagePath, direction) {
    return moveGalleryImage(sku, imagePath, direction, 'marketplace');
}

function moveProductGalleryImageTo(sku, imagePath, targetPosition) {
    return moveGalleryImageToPosition(sku, imagePath, targetPosition, 'stock');
}

function moveMarketplaceGalleryImageTo(sku, imagePath, targetPosition) {
    return moveGalleryImageToPosition(sku, imagePath, targetPosition, 'marketplace');
}

async function setMarketplaceGalleryCover(sku, imagePath) {
    const res = await apiCall('/admin_supply.php?action=product-gallery-cover', 'POST', {
        sku: sku,
        image: imagePath
    });

    if (!res || !res.success) {
        showGalleryResult('marketplace', (res && res.message) ? res.message : 'No se pudo cambiar la portada CE', 'error');
        return;
    }

    showGalleryResult('marketplace', res.message || 'Portada CE actualizada', 'success');

    // Optimistic update for marketplace cache
    try {
        const cache = marketplaceGalleryCache || [];
        const idx = cache.findIndex((i) => i === imagePath);
        if (idx > 0) {
            cache.splice(idx, 1);
            cache.unshift(imagePath);
            setGalleryState('marketplace', sku, cache.slice(), imagePath);
            renderProductGallery(cache, sku, 'marketplace');
            syncGalleryModeUi('marketplace', imagePath);
        }
        const item = marketplaceItemsCache.find(p => String(p.sku || '') === String(sku));
        if (item) {
            item.image_url = imagePath;
            upsertMarketplaceCache(item);
        }
    } catch (e) {
        console.warn('Optimistic marketplace cover failed:', e);
    }

    void loadMarketplaceCeAdmin(marketplaceCurrentPage);
}

async function deleteMarketplaceGalleryImage(sku, imagePath) {
    if (!confirm('¿Eliminar esta imagen de la galería CE?')) return;

    // 1. Optimistic update INMEDIATO — antes de llamar a la API
    const previousImages = Array.isArray(marketplaceGalleryCache) ? marketplaceGalleryCache.slice() : [];
    const previousCover = previousImages[0] || '';
    const optimisticImages = previousImages.filter((image) => image !== imagePath);

    setGalleryState('marketplace', sku, optimisticImages.slice(), optimisticImages[0] || '');
    renderProductGallery(optimisticImages, sku, 'marketplace');
    syncGalleryModeUi('marketplace', optimisticImages[0] || '');

    // 2. Llamada a la API
    const res = await apiCall('/admin_supply.php?action=product-gallery-delete', 'POST', {
        sku: sku,
        image: imagePath
    });

    if (!res || !res.success) {
        // Revertir al estado anterior si falla
        setGalleryState('marketplace', sku, previousImages.slice(), previousCover);
        renderProductGallery(previousImages, sku, 'marketplace');
        syncGalleryModeUi('marketplace', previousCover);
        showGalleryResult('marketplace', (res && res.message) ? res.message : 'No se pudo eliminar la imagen CE', 'error');
        return;
    }

    showGalleryResult('marketplace', res.message || 'Imagen CE eliminada', 'success');

    // 3. Usar las imágenes que devuelve el servidor como fuente de verdad definitiva
    //    (NO hacer re-fetch — evita que la imagen reaparezca)
    const serverImages = Array.isArray(res.images) && res.images.length >= 0
        ? res.images
        : optimisticImages;
    const serverCover = res.cover || serverImages[0] || '';

    setGalleryState('marketplace', sku, serverImages.slice(), serverCover);
    renderProductGallery(serverImages, sku, 'marketplace');
    syncGalleryModeUi('marketplace', serverCover);

    // Actualizar cache de marketplace si existe
    try {
        const item = marketplaceItemsCache.find(p => String(p.sku || '') === String(sku));
        if (item) {
            item.image_url = serverCover || item.image_url;
            upsertMarketplaceCache(item);
        }
    } catch (e) {
        console.warn('Marketplace cache update after delete failed:', e);
    }
}

// Image compression helper (client-side optimization)
async function compressImage(file, maxWidth = 1920, maxHeight = 1440, quality = 0.85) {
    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = new Image();
            img.onload = function() {
                const canvas = document.createElement('canvas');
                let { width, height } = img;
                
                // Calculate new dimensions
                const ratio = Math.min(maxWidth / width, maxHeight / height);
                if (ratio < 1) {
                    width = Math.floor(width * ratio);
                    height = Math.floor(height * ratio);
                }
                
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);
                
                canvas.toBlob((blob) => {
                    resolve(new File([blob], file.name, { type: file.type || 'image/jpeg' }));
                }, file.type || 'image/jpeg', quality);
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
}

async function uploadProductImages() {
    const input = document.getElementById('newProductImages');
    const resultBox = document.getElementById('productCreateResult');
    const sku = getCurrentStockSkuForGallery();

    if (!/^\d{5,6}$/.test(sku)) {
        if (resultBox) {
            resultBox.innerHTML = '<div class="alert alert-error">Primero captura un código de producto válido (5 o 6 números)</div>';
        }
        return false;
    }

    if (!input || !input.files || input.files.length === 0) {
        // No hay imágenes seleccionadas - permitir continuar
        return true;
    }

    // Show progress indicator
    if (resultBox) {
        resultBox.innerHTML = '<div class="alert alert-info" style="display:flex; align-items:center; gap:10px;"><span>⏳ Procesando imágenes...</span><div style="flex:1; height:4px; background:#ddd; border-radius:2px;"><div style="width:0%; height:100%; background:#0066cc; border-radius:2px; transition:width 0.3s;" id="uploadProgress"></div></div></div>';
    }

    try {
        // Compress all images client-side in parallel (faster)
        const files = Array.from(input.files);
        const uploadProgress = document.getElementById('uploadProgress');
        const compressedFiles = await Promise.all(
            files.map(async (file, idx) => {
                try {
                    const compressed = await compressImage(file);
                    const progress = Math.floor((idx + 1) / files.length * 80); // 0-80% for compression
                    if (uploadProgress) uploadProgress.style.width = progress + '%';
                    return compressed;
                } catch (e) {
                    console.warn('Compression failed for', file.name, '- using original');
                    return file;
                }
            })
        );

        // Build form data with compressed images
        const formData = new FormData();
        formData.append('sku', sku);
        compressedFiles.forEach((file) => {
            formData.append('images[]', file);
        });

        // Show upload in progress
        if (uploadProgress) uploadProgress.style.width = '80%';

        const response = await fetch('/api/admin_supply.php?action=product-gallery-upload', {
            method: 'POST',
            body: formData,
            credentials: 'include',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (response.status === 401) {
            if (resultBox) {
                resultBox.innerHTML = '<div class="alert alert-error">Tu sesión de administrador expiró. Vuelve a iniciar sesión.</div>';
            }
            window.location.href = '/admin_login.php?error=expired&return_to=' + encodeURIComponent('/admin_supply.php');
            return false;
        }

        if (uploadProgress) uploadProgress.style.width = '95%';

        const data = await response.json();
        if (!data || !data.success) {
            if (resultBox) {
                resultBox.innerHTML = `<div class="alert alert-error">${escapeHtml((data && data.message) ? data.message : 'No fue posible cargar las imágenes')}</div>`;
            }
            return false;
        }

        if (resultBox) {
            resultBox.innerHTML = `<div class="alert alert-success">✅ ${escapeHtml(data.message || 'Imágenes cargadas correctamente')} (${compressedFiles.length} imagen${compressedFiles.length !== 1 ? 'es' : ''})</div>`;
        }

        if (uploadProgress) uploadProgress.style.width = '100%';

        input.value = '';

        const currentSku = getCurrentStockSkuForGallery();
        const renderedImages = Array.isArray(data.images) && data.images.length > 0
            ? data.images
            : (data.cover ? [data.cover] : []);
        if (/^\d{5,6}$/.test(currentSku) && renderedImages.length > 0) {
            // Optimistic update: set cache and render immediately
            setGalleryState('stock', currentSku, renderedImages, data.cover || renderedImages[0] || '');
            renderProductGallery(renderedImages, currentSku, 'stock');
            syncGalleryModeUi('stock', data.cover || renderedImages[0] || '');

            // update select and preview
            const select = document.getElementById('newProductImageRef');
            if (select && data.cover) select.value = data.cover;
            updateStockPreview();

            // update caches if product exists in cache
            try {
                const prod = stockItemsCache.find(p => String(p.sku || '') === String(currentSku));
                if (prod) {
                    prod.image_url = data.cover || renderedImages[0] || prod.image_url;
                    upsertStockCache(prod);
                }
                const mp = marketplaceItemsCache.find(p => String(p.sku || '') === String(currentSku));
                if (mp) {
                    mp.image_url = data.cover || renderedImages[0] || mp.image_url;
                    upsertMarketplaceCache(mp);
                }
            } catch (e) {
                console.warn('Optimistic upload cache update failed:', e);
            }
        }

        // Background sync for consistency
        void loadProductGalleryForCurrentSku();
        return true;
    } catch (error) {
        console.error('Error al subir imágenes:', error);
        if (resultBox) {
            let errorMsg = 'Error al cargar imágenes';
            if (error.message) {
                errorMsg += ': ' + error.message;
            }
            resultBox.innerHTML = '<div class="alert alert-error">' + escapeHtml(errorMsg) + '</div>';
        }
        return false;
    }
}

async function uploadMarketplaceImages() {
    const input = document.getElementById('marketplaceImages');
    const sku = getCurrentMarketplaceSkuForGallery();

    if (!/^\d{5,6}$/.test(sku)) {
        showGalleryResult('marketplace', 'Primero captura un código SKU CE válido (5 o 6 números)', 'error');
        return false;
    }

    if (!input || !input.files || input.files.length === 0) {
        // No hay imágenes seleccionadas - permitir continuar
        return true;
    }

    // Show progress indicator
    const resultBox = document.getElementById('marketplaceResult');
    if (resultBox) {
        resultBox.innerHTML = '<div class="alert alert-info" style="display:flex; align-items:center; gap:10px;"><span>⏳ Procesando imágenes...</span><div style="flex:1; min-width:200px; height:4px; background:#ddd; border-radius:2px;"><div style="width:0%; height:100%; background:#0066cc; border-radius:2px; transition:width 0.3s;" id="marketplaceUploadProgress"></div></div></div>';
    }

    try {
        const previousImages = Array.isArray(marketplaceGalleryCache) ? marketplaceGalleryCache.slice() : [];

        // Compress all images client-side in parallel (faster)
        const files = Array.from(input.files);
        const previewUrls = files.map((file) => URL.createObjectURL(file));
        const uploadProgress = document.getElementById('marketplaceUploadProgress');

        if (previewUrls.length > 0) {
            setGalleryState('marketplace', sku, previewUrls.slice(), previewUrls[0] || '');
            renderProductGallery(previewUrls, sku, 'marketplace');
            syncGalleryModeUi('marketplace', previewUrls[0] || '');
        }

        const compressedFiles = await Promise.all(
            files.map(async (file, idx) => {
                try {
                    const compressed = await compressImage(file);
                    const progress = Math.floor((idx + 1) / files.length * 80); // 0-80% for compression
                    if (uploadProgress) uploadProgress.style.width = progress + '%';
                    return compressed;
                } catch (e) {
                    console.warn('Compression failed for', file.name, '- using original');
                    return file;
                }
            })
        );

        // Build form data with compressed images
        const formData = new FormData();
        formData.append('sku', sku);
        compressedFiles.forEach((file) => {
            formData.append('images[]', file);
        });

        // Show upload in progress
        if (uploadProgress) uploadProgress.style.width = '80%';

        const response = await fetch('/api/admin_supply.php?action=product-gallery-upload', {
            method: 'POST',
            body: formData,
            credentials: 'include',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (response.status === 401) {
            previewUrls.forEach((url) => URL.revokeObjectURL(url));
            showGalleryResult('marketplace', 'Tu sesión de administrador expiró. Vuelve a iniciar sesión.', 'error');
            window.location.href = '/admin_login.php?error=expired&return_to=' + encodeURIComponent('/admin_supply.php');
            return false;
        }

        if (uploadProgress) uploadProgress.style.width = '95%';

        const data = await response.json();
        if (!data || !data.success) {
            previewUrls.forEach((url) => URL.revokeObjectURL(url));
            if (previousImages.length > 0) {
                const previousCover = previousImages[0] || '';
                setGalleryState('marketplace', sku, previousImages.slice(), previousCover);
                renderProductGallery(previousImages, sku, 'marketplace');
                syncGalleryModeUi('marketplace', previousCover);
            }
            showGalleryResult('marketplace', (data && data.message) ? data.message : 'No fue posible cargar las imágenes CE', 'error');
            return false;
        }

        showGalleryResult('marketplace', `✅ ${data.message || 'Imágenes CE cargadas correctamente'} (${compressedFiles.length} imagen${compressedFiles.length !== 1 ? 'es' : ''})`, 'success');
        if (uploadProgress) uploadProgress.style.width = '100%';
        input.value = '';

        const currentSku = getCurrentMarketplaceSkuForGallery();
        const renderedImages = Array.isArray(data.images) && data.images.length > 0
            ? data.images
            : (data.cover ? [data.cover] : []);
        if (/^\d{5,6}$/.test(currentSku) && renderedImages.length > 0) {
            // Optimistic: update cache and render immediately
            previewUrls.forEach((url) => URL.revokeObjectURL(url));
            setGalleryState('marketplace', currentSku, renderedImages, data.cover || renderedImages[0] || '');
            renderProductGallery(renderedImages, currentSku, 'marketplace');
            syncGalleryModeUi('marketplace', data.cover || renderedImages[0] || '');

            // update select and preview
            const select = document.getElementById('marketplaceImageRef');
            if (select && data.cover) select.value = data.cover;
            updateMarketplacePreview();

            // reflect in caches if possible
            try {
                const item = marketplaceItemsCache.find(p => String(p.sku || '') === String(currentSku));
                if (item) {
                    item.image_url = data.cover || renderedImages[0] || item.image_url;
                    upsertMarketplaceCache(item);
                }
                const prod = stockItemsCache.find(p => String(p.sku || '') === String(currentSku));
                if (prod) {
                    prod.image_url = data.cover || renderedImages[0] || prod.image_url;
                    upsertStockCache(prod);
                }
            } catch (e) {
                console.warn('Optimistic marketplace upload cache update failed:', e);
            }
        }

        // background sync for consistency
        void loadMarketplaceGalleryForCurrentSku();
        void loadMarketplaceCeAdmin(marketplaceCurrentPage);
        void loadStock(stockCurrentPage);

        return true;
    } catch (error) {
        console.error('Error al subir imágenes CE:', error);
        showGalleryResult('marketplace', 'Error al cargar imágenes: ' + (error.message || 'desconocido'), 'error');
        return false;
    }
}

async function createProductByAdmin() {
    const stockPageBeforeSave = Math.max(1, Number(stockCurrentPage || 1));
    const editId = Number(document.getElementById('newProductEditId')?.value || 0);
    const seedMode = Number(document.getElementById('newProductSeedMode')?.value || 0) === 1;
    const skuInput = document.getElementById('newProductSku');
    const normalizedSku = normalizeNumericSku(skuInput?.value || '');
    const productName = document.getElementById('newProductName')?.value?.trim() || '';
    const price = Number(document.getElementById('newProductPrice')?.value || 0);
    const stock = Number(document.getElementById('newProductStock')?.value || 0);
    const reorder = Number(document.getElementById('newProductReorder')?.value || 0);
    const box = document.getElementById('productCreateResult');

    if (skuInput) {
        skuInput.value = normalizedSku;
    }

    // Validation
    if (!/^\d{5,6}$/.test(normalizedSku)) {
        if (box) {
            box.innerHTML = '<div class="alert alert-error">El código del producto debe tener 5 o 6 números.</div>';
        }
        setSkuStatus('newProductSkuStatus', 'El código debe tener 5 o 6 números.', 'warning');
        showAlert('SKU inválido', 'warning');
        return;
    }

    if (!productName) {
        if (box) box.innerHTML = '<div class="alert alert-error">El nombre del producto es requerido.</div>';
        showAlert('Nombre requerido', 'warning');
        return;
    }
    if (productName.length > 255) {
        if (box) box.innerHTML = '<div class="alert alert-error">El nombre del producto es muy largo (máximo 255 caracteres).</div>';
        showAlert('Nombre muy largo', 'warning');
        return;
    }

    if (price < 0) {
        if (box) box.innerHTML = '<div class="alert alert-error">El precio no puede ser negativo.</div>';
        showAlert('Precio inválido', 'warning');
        return;
    }
    if (price > 999999.99) {
        if (box) box.innerHTML = '<div class="alert alert-error">El precio es demasiado alto.</div>';
        showAlert('Precio muy alto', 'warning');
        return;
    }

    if (stock < 0) {
        if (box) box.innerHTML = '<div class="alert alert-error">El stock no puede ser negativo.</div>';
        showAlert('Stock inválido', 'warning');
        return;
    }
    if (stock > 999999) {
        if (box) box.innerHTML = '<div class="alert alert-error">El stock es demasiado alto.</div>';
        showAlert('Stock muy alto', 'warning');
        return;
    }

    if (reorder < 0) {
        if (box) box.innerHTML = '<div class="alert alert-error">El nivel de reorden no puede ser negativo.</div>';
        showAlert('Reorden inválido', 'warning');
        return;
    }

    const selectedCategoryOptions = Array.from(document.getElementById('newProductCategory').selectedOptions || []);
    const selectedCategories = selectedCategoryOptions.map((option) => option.value).filter(Boolean);

    if (selectedCategories.length === 0) {
        if (box) {
            box.innerHTML = '<div class="alert alert-error">Selecciona al menos una categoría para el producto.</div>';
        }
        showAlert('Selecciona una categoría', 'warning');
        return;
    }

    // If the user selected files but hasn't waited, upload them first so the product save can reference the new image
    const newProductImagesEl = document.getElementById('newProductImages');
    if (newProductImagesEl && newProductImagesEl.files && newProductImagesEl.files.length > 0) {
        if (box) box.innerHTML = '<div class="alert alert-info">Subiendo imágenes, espera por favor...</div>';
        const uploadSuccess = await uploadProductImages();
        if (!uploadSuccess) {
            return; // Stop if image upload failed
        }
    }

    const payload = {
        id: editId,
        sku: normalizedSku,
        name: productName,
        category: selectedCategories.join(', '),
        description: document.getElementById('newProductDescription')?.value?.trim() || '',
        price: price,
        stock_quantity: stock,
        reorder_level: reorder,
        image_url: document.getElementById('newProductImageRef').value || 'images/products/default-product.svg',
        is_visible: Number(document.getElementById('newProductVisible')?.value || 1) === 1 ? 1 : 0,
        allow_seed_sku: seedMode ? 1 : 0
    };

    let res = await apiCall('/admin_supply.php?action=product-save', 'POST', payload);
    if ((!res || !res.success) && editId <= 0) {
        // Fallback to stable create endpoint if unified save fails in legacy environments.
        res = await apiCall('/admin_supply.php?action=product-create', 'POST', payload);
    }
    if (!res || !res.success) {
        if (box) {
            const detail = res && res.debug && res.debug.detail ? ` (${res.debug.detail})` : '';
            box.innerHTML = `<div class="alert alert-error">${escapeHtml(((res && res.message) ? res.message : 'No fue posible registrar el producto') + detail)}</div>`;
        }
        return;
    }

    if (box) {
        box.innerHTML = `<div class="alert alert-success">${escapeHtml(res.message || 'Producto guardado')}: <strong>${escapeHtml(displayProductCode((res.product && res.product.sku) ? res.product.sku : normalizedSku))}</strong></div>`;
    }

    showAlert(res.message || 'Producto guardado correctamente', 'success');
    resetProductForm();

    // Optimistic update using the submitted payload so edit flow reflects latest values immediately.
    const existingStockBySku = (stockItemsCache || []).find((p) => normalizeNumericSku(p.sku || '') === normalizedSku);
    const existingMarketplaceBySku = (marketplaceItemsCache || []).find((p) => normalizeNumericSku(p.sku || '') === normalizedSku);
    const optimisticProduct = {
        id: Number((res.product && res.product.id) || editId || (existingStockBySku && existingStockBySku.id) || 0),
        sku: (res.product && res.product.sku) ? res.product.sku : normalizedSku,
        name: (res.product && res.product.name) ? res.product.name : productName,
        category: payload.category,
        description: payload.description,
        unit_price: price,
        stock_quantity: stock,
        reorder_level: reorder,
        image_url: payload.image_url,
        is_active: payload.is_visible,
        variants_json: getGalleryState('stock')?.images || []
    };
    upsertStockCache(optimisticProduct);
    upsertMarketplaceCache(Object.assign({}, optimisticProduct, {
        id: Number((existingMarketplaceBySku && existingMarketplaceBySku.id) || 0),
        condition_label: (existingMarketplaceBySku && existingMarketplaceBySku.condition_label) || 'Seminuevo'
    }));

    // Refresh in background to keep server state authoritative without blocking UI.
    void loadStock(stockPageBeforeSave);
    void loadSupplierProducts();
    void loadMarketplaceCeAdmin(marketplaceCurrentPage || 1, 10);
    activateAdminSupplyTab('stockTab', 'productCreateResult');
}

function resetMarketplaceForm() {
    setGalleryState('marketplace', '', [], '');
    document.getElementById('marketplaceEditId').value = '';
    document.getElementById('marketplaceSku').value = '';
    document.getElementById('marketplaceName').value = '';
    document.getElementById('marketplaceCondition').value = 'Seminuevo';
    document.getElementById('marketplacePrice').value = '0';
    document.getElementById('marketplaceStock').value = '1';
    document.getElementById('marketplaceActive').value = '0';
    document.getElementById('marketplaceDescription').value = '';
    Array.from(document.getElementById('marketplaceCategory')?.options || []).forEach((opt) => {
        opt.selected = false;
    });
    const marketplaceImageRef = document.getElementById('marketplaceImageRef');
    if (marketplaceImageRef) {
        marketplaceImageRef.value = 'images/products/default-product.svg';
    }
    const marketplaceImages = document.getElementById('marketplaceImages');
    if (marketplaceImages) {
        marketplaceImages.value = '';
    }
    const saveBtn = document.getElementById('marketplaceSaveButton');
    if (saveBtn) saveBtn.textContent = 'Guardar artículo CE';
    const box = document.getElementById('marketplaceResult');
    if (box) box.innerHTML = '';
    setSkuStatus('marketplaceSkuStatus', 'Debe ser único y de 5 o 6 números.', 'muted');
    updateMarketplacePreview();
    loadMarketplaceGalleryForCurrentSku();
}

async function fillMarketplaceForm(item) {
    if (!item) return;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    document.getElementById('marketplaceEditId').value = item.id || '';
    document.getElementById('marketplaceSku').value = item.sku || '';
    document.getElementById('marketplaceName').value = item.name || '';
    document.getElementById('marketplaceCondition').value = item.condition_label || 'Seminuevo';
    document.getElementById('marketplacePrice').value = String(item.unit_price || 0);
    document.getElementById('marketplaceStock').value = String(item.stock_quantity || 0);
    document.getElementById('marketplaceActive').value = Number(item.is_active) ? '1' : '0';
    document.getElementById('marketplaceDescription').value = item.description || '';
    const marketplaceImages = document.getElementById('marketplaceImages');
    if (marketplaceImages) {
        marketplaceImages.value = '';
    }

    const categories = String(item.category || '')
        .split(',')
        .map((x) => x.trim())
        .filter(Boolean);

    await loadProductCategories(false);

    const marketplaceCategorySelect = document.getElementById('marketplaceCategory');
    ensureCategoryOptions(marketplaceCategorySelect, categories, true);
    setCategorySelections(marketplaceCategorySelect, categories);

    const marketplaceImageRef = document.getElementById('marketplaceImageRef');
    if (marketplaceImageRef && item.image_url) marketplaceImageRef.value = item.image_url;

    const skuForGallery = normalizeNumericSku(item.sku || '');
    const fastImages = extractGalleryImagesFromItem(item);
    if (fastImages.length === 0 && item.image_url && !String(item.image_url).includes('default-product.svg')) {
        fastImages.push(String(item.image_url));
    }
    if (/^\d{5,6}$/.test(skuForGallery) && fastImages.length > 0) {
        setGalleryState('marketplace', skuForGallery, fastImages, fastImages[0] || '');
        renderProductGallery(fastImages, skuForGallery, 'marketplace');
        const galleryStatus = document.getElementById('marketplaceGalleryStatus');
        if (galleryStatus) galleryStatus.textContent = `Galería para ${skuForGallery}: ${fastImages.length} imagen(es)`;
        syncGalleryModeUi('marketplace', fastImages[0] || '');
    }

    const saveBtn = document.getElementById('marketplaceSaveButton');
    const box = document.getElementById('marketplaceResult');
    if (saveBtn) saveBtn.textContent = 'Actualizar artículo CE';
    validateSkuAvailability('marketplace');
    if (box) {
        const visibility = Number(item.is_active) ? 'Visible' : 'Oculto';
        box.innerHTML = `<div class="alert alert-info">Editando artículo CE: estado actual <strong>${visibility}</strong>.</div>`;
    }
    primeGalleryFromCurrentForm('marketplace');
    updateMarketplacePreview();
    loadMarketplaceGalleryForCurrentSku();
}

function fillMarketplaceFormById(id) {
    const item = marketplaceItemsCache.find((row) => Number(row.id) === Number(id));
    if (!item) return;
    void fillMarketplaceForm(item);
}

async function loadMarketplaceCeAdmin(page = 1, customPerPage = null) {
    const box = document.getElementById('marketplaceList');
    const caption = document.getElementById('marketplaceListCaption');
    const paginationBox = document.getElementById('marketplacePagination');
    const quickBox = document.getElementById('marketplaceQuickResult');
    marketplaceCurrentPage = page;
    // Use custom per_page if provided (for faster loading after save), otherwise use default
    const perPageToUse = customPerPage !== null ? customPerPage : marketplacePerPage;
    const res = await apiCall(`/admin_supply.php?action=marketplace-list&page=${page}&per_page=${perPageToUse}&_=${Date.now()}`, 'GET', null, { silent: true });

    if (!res || !res.success || !Array.isArray(res.items)) {
        if (box) box.innerHTML = '<p class="text-muted">No fue posible cargar artículos CE.</p>';
        if (caption) caption.textContent = 'No fue posible cargar artículos CE.';
        if (paginationBox) paginationBox.innerHTML = '';
        if (quickBox) quickBox.innerHTML = '<span style="color:#f87171;">No fue posible cargar la gestión rápida.</span>';
        updateMarketplaceQuickSelection([]);
        return;
    }

    marketplaceItemsCache = Array.isArray(res.items) ? res.items : [];
    marketplacePagination = res.pagination || null;
    renderMarketplaceList();
    renderMarketplacePagination();
    updateMarketplacePreview();
}

function updateMarketplaceQuickSelection(items = marketplaceItemsCache) {
    const select = document.getElementById('marketplaceBulkSelect');
    if (!select) return;

    const previouslySelected = new Set(Array.from(select.selectedOptions || []).map((option) => Number(option.value || 0)));
    select.innerHTML = '';

    if (!Array.isArray(items) || items.length === 0) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'Sin artículos para mostrar';
        option.disabled = true;
        select.appendChild(option);
        return;
    }

    items.forEach((item) => {
        const id = Number(item.id || 0);
        if (id <= 0) return;

        const code = displayProductCode(item.sku || '');
        const name = String(item.name || 'Sin nombre').trim();
        const condition = String(item.condition_label || 'Seminuevo').trim();
        const inactive = Number(item.is_active) === 0;

        const option = document.createElement('option');
        option.value = String(id);
        option.textContent = `${code} | ${name} | ${condition}${inactive ? ' (Oculto)' : ''}`;
        option.selected = previouslySelected.has(id);
        select.appendChild(option);
    });
}

function getMarketplaceBulkSelectedIds() {
    const select = document.getElementById('marketplaceBulkSelect');
    return Array.from(select?.selectedOptions || [])
        .map((option) => Number(option.value || 0))
        .filter((id) => id > 0);
}

function editMarketplaceSelectedItem() {
    const quickBox = document.getElementById('marketplaceQuickResult');
    const selectedIds = getMarketplaceBulkSelectedIds();

    if (selectedIds.length === 0) {
        if (quickBox) quickBox.innerHTML = '<span style="color:#f59e0b;">Selecciona un artículo para editar.</span>';
        return;
    }

    if (selectedIds.length > 1) {
        if (quickBox) quickBox.innerHTML = '<span style="color:#f59e0b;">Selecciona solo un artículo para editar.</span>';
        return;
    }

    const target = marketplaceItemsCache.find((item) => Number(item.id || 0) === selectedIds[0]);
    if (!target) {
        if (quickBox) quickBox.innerHTML = '<span style="color:#f87171;">No se encontró el artículo seleccionado.</span>';
        return;
    }

    void fillMarketplaceForm(target);
    if (quickBox) quickBox.innerHTML = '<span style="color:#22c55e;">Artículo cargado en el formulario.</span>';
    document.getElementById('marketplaceSku')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

async function deleteMarketplaceSelectedItems() {
    const quickBox = document.getElementById('marketplaceQuickResult');
    const selectedIds = getMarketplaceBulkSelectedIds();

    if (selectedIds.length === 0) {
        if (quickBox) quickBox.innerHTML = '<span style="color:#f59e0b;">Selecciona al menos un artículo para eliminar.</span>';
        return;
    }

    if (!confirm(`¿Eliminar ${selectedIds.length} artículo(s) seleccionado(s)? Se borrarán definitivamente del Marketplace CE.`)) {
        return;
    }

    if (quickBox) quickBox.innerHTML = '<span style="color:#cbd5e1;">Eliminando artículos...</span>';

    let successCount = 0;
    let firstError = '';
    for (const id of selectedIds) {
        const res = await apiCall('/admin_supply.php?action=marketplace-delete', 'POST', { id: id });
        if (res && res.success) {
            successCount += 1;
        } else if (!firstError) {
            firstError = (res && res.message) ? res.message : `No se pudo eliminar el artículo ${id}`;
        }
    }

    if (successCount > 0) {
        if (quickBox) quickBox.innerHTML = `<span style="color:#22c55e;">${successCount} artículo(s) eliminado(s).</span>`;
        showAlert(`${successCount} artículo(s) CE eliminado(s) definitivamente`, 'success');
    }
    if (firstError) {
        if (quickBox) quickBox.innerHTML += ` <span style="color:#f87171;">${escapeHtml(firstError)}</span>`;
        showAlert(firstError, 'error');
    }

    // Optimistic UI update already performed; background sync to current page
    await loadMarketplaceCeAdmin(marketplaceCurrentPage);
}

function renderMarketplaceList() {
    const box = document.getElementById('marketplaceList');
    const caption = document.getElementById('marketplaceListCaption');
    if (!box) return;

    const query = (document.getElementById('marketplaceSearch')?.value || '').toLowerCase().trim();
    const filtered = marketplaceItemsCache.filter((item) => {
        const code = displayProductCode(item.sku || '').toLowerCase();
        const name = String(item.name || '').toLowerCase();
        const cond = String(item.condition_label || '').toLowerCase();
        const category = String(item.category || '').toLowerCase();
        return `${code} ${name} ${cond} ${category}`.includes(query);
    });

    if (caption) {
        caption.textContent = `Mostrando ${filtered.length} de ${marketplaceItemsCache.length} artículos CE`;
    }

    updateMarketplaceQuickSelection(filtered);

    if (filtered.length === 0) {
        if (box) box.innerHTML = '<p class="text-muted">No hay artículos CE registrados.</p>';
        const paginationBox = document.getElementById('marketplacePagination');
        if (paginationBox) paginationBox.innerHTML = '';
        return;
    }

    box.innerHTML = `<div class="catalog-grid-min">${filtered.map((item) => renderAdminProductCard(item, 'marketplace')).join('')}</div>`;
}

function renderMarketplacePagination() {
    const container = document.getElementById('marketplacePagination');
    if (!container) return;

    if (!marketplacePagination) {
        container.innerHTML = '';
        return;
    }

    const current_page = Number(marketplacePagination.current_page || marketplaceCurrentPage || 1);
    const total_pages = Number(marketplacePagination.total_pages || 1);
    const total_items = Number(marketplacePagination.total_items || marketplaceItemsCache.length || 0);

    container.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:1rem; padding:1rem; background:var(--ui-surface-soft); border-radius:12px; gap:0.75rem; flex-wrap:wrap;">
            <div class="text-muted">Total: <strong>${total_items}</strong> artículos CE</div>
            <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
                <button class="btn btn-small" ${current_page <= 1 ? 'disabled' : ''} onclick="loadMarketplaceCeAdmin(${current_page - 1})">Anterior</button>
                <span class="text-muted">Página <strong>${current_page}</strong> de ${total_pages}</span>
                <button class="btn btn-small btn-primary" ${current_page >= total_pages ? 'disabled' : ''} onclick="loadMarketplaceCeAdmin(${current_page + 1})">Ver más</button>
            </div>
        </div>
    `;
}

async function saveMarketplaceCeByAdmin() {
    const skuInput = document.getElementById('marketplaceSku');
    const normalizedSku = normalizeNumericSku(skuInput?.value || '');
    const marketplaceName = document.getElementById('marketplaceName')?.value?.trim() || '';
    const price = Number(document.getElementById('marketplacePrice')?.value || 0);
    const stock = Number(document.getElementById('marketplaceStock')?.value || 0);
    const box = document.getElementById('marketplaceResult');

    if (skuInput) {
        skuInput.value = normalizedSku;
    }

    // Validation
    if (!/^\d{5,6}$/.test(normalizedSku)) {
        if (box) box.innerHTML = '<div class="alert alert-error">El código SKU CE debe tener exactamente 5 números.</div>';
        setSkuStatus('marketplaceSkuStatus', 'El código debe tener 5 o 6 números.', 'warning');
        showAlert('SKU inválido', 'warning');
        return;
    }

    if (!marketplaceName) {
        if (box) box.innerHTML = '<div class="alert alert-error">El nombre del artículo CE es requerido.</div>';
        showAlert('Nombre requerido', 'warning');
        return;
    }
    if (marketplaceName.length > 255) {
        if (box) box.innerHTML = '<div class="alert alert-error">El nombre es muy largo (máximo 255 caracteres).</div>';
        showAlert('Nombre muy largo', 'warning');
        return;
    }

    if (price < 0) {
        if (box) box.innerHTML = '<div class="alert alert-error">El precio no puede ser negativo.</div>';
        showAlert('Precio inválido', 'warning');
        return;
    }
    if (price > 999999.99) {
        if (box) box.innerHTML = '<div class="alert alert-error">El precio es demasiado alto.</div>';
        showAlert('Precio muy alto', 'warning');
        return;
    }

    if (stock < 0) {
        if (box) box.innerHTML = '<div class="alert alert-error">El stock no puede ser negativo.</div>';
        showAlert('Stock inválido', 'warning');
        return;
    }
    if (stock > 999999) {
        if (box) box.innerHTML = '<div class="alert alert-error">El stock es demasiado alto.</div>';
        showAlert('Stock muy alto', 'warning');
        return;
    }

    const skuOk = await validateSkuAvailability('marketplace', { strict: true });
    if (!skuOk) {
        if (box) box.innerHTML = '<div class="alert alert-error">No fue posible guardar: código inválido o duplicado.</div>';
        return;
    }

    const currentId = Number(document.getElementById('marketplaceEditId').value || 0);
    const selectedCategoryOptions = Array.from(document.getElementById('marketplaceCategory')?.selectedOptions || []);
    const selectedCategories = selectedCategoryOptions.map((option) => option.value).filter(Boolean);

    if (selectedCategories.length === 0) {
        if (box) box.innerHTML = '<div class="alert alert-error">Selecciona al menos una categoría.</div>';
        showAlert('Selecciona una categoría', 'warning');
        return;
    }

    // If the user selected files but hasn't waited, upload them first so the product save can reference the new images
    const marketplaceImagesEl = document.getElementById('marketplaceImages');
    if (marketplaceImagesEl && marketplaceImagesEl.files && marketplaceImagesEl.files.length > 0) {
        if (box) box.innerHTML = '<div class="alert alert-info">Subiendo imágenes CE, espera por favor...</div>';
        const uploadSuccess = await uploadMarketplaceImages();
        if (!uploadSuccess) {
            return; // Stop if image upload failed
        }
    }

    const payload = {
        id: currentId,
        sku: normalizedSku,
        name: marketplaceName,
        condition_label: document.getElementById('marketplaceCondition')?.value || 'Seminuevo',
        category: selectedCategories.join(', '),
        unit_price: price,
        stock_quantity: stock,
        is_active: document.getElementById('marketplaceActive')?.value === '1' ? 1 : 0,
        description: document.getElementById('marketplaceDescription')?.value?.trim() || '',
        image_url: document.getElementById('marketplaceImageRef')?.value || 'images/products/default-product.svg'
    };

    const res = await apiCall('/admin_supply.php?action=marketplace-save', 'POST', payload);
    if (!res || !res.success) {
        const detail = res && res.debug && res.debug.detail ? ` (${res.debug.detail})` : '';
        if (box) box.innerHTML = `<div class="alert alert-error">${escapeHtml(((res && res.message) ? res.message : 'No fue posible guardar artículo CE') + detail)}</div>`;
        return;
    }

    if (box) box.innerHTML = `<div class="alert alert-success">${escapeHtml(res.message || 'Artículo CE guardado')}</div>`;
    showAlert(res.message || 'Artículo CE guardado correctamente', 'success');
    resetMarketplaceForm();

    const existingMarketplaceBySku = (marketplaceItemsCache || []).find((p) => normalizeNumericSku(p.sku || '') === normalizedSku);
    const existingStockBySku = (stockItemsCache || []).find((p) => normalizeNumericSku(p.sku || '') === normalizedSku);
    const optimisticMarketplace = Object.assign({}, existingMarketplaceBySku || {}, {
        id: Number((res.item && res.item.id) || currentId || (existingMarketplaceBySku && existingMarketplaceBySku.id) || 0),
        sku: normalizedSku,
        name: marketplaceName,
        condition_label: payload.condition_label,
        category: payload.category,
        unit_price: payload.unit_price,
        stock_quantity: payload.stock_quantity,
        is_active: payload.is_active,
        description: payload.description,
        image_url: payload.image_url,
        variants_json: getGalleryState('marketplace')?.images || []
    }, res.item || {});

    upsertMarketplaceCache(optimisticMarketplace);
    upsertStockCache(Object.assign({}, optimisticMarketplace, {
        id: Number((existingStockBySku && existingStockBySku.id) || 0),
        reorder_level: Number((existingStockBySku && existingStockBySku.reorder_level) || 10)
    }));

    // Keep UI fast with optimistic update, then sync in background.
    void Promise.all([
        loadMarketplaceCeAdmin(marketplaceCurrentPage || 1, 25),
        loadStock(stockCurrentPage || 1, 25)
    ]);

    activateAdminSupplyTab('marketplaceTab', 'marketplaceResult');
}

async function deleteMarketplaceCeByAdmin(id) {
    if (!id) return;
    if (!confirm('¿Deseas eliminar este artículo CE? Se ocultará del Marketplace CE.')) return;

    const box = document.getElementById('marketplaceResult');
    const res = await apiCall('/admin_supply.php?action=marketplace-delete', 'POST', { id: id });
    if (!res || !res.success) {
        if (box) box.innerHTML = `<div class="alert alert-error">${escapeHtml((res && res.message) ? res.message : 'No fue posible eliminar artículo CE')}</div>`;
        return;
    }

    if (box) box.innerHTML = `<div class="alert alert-success">${escapeHtml(res.message || 'Artículo CE eliminado definitivamente del Marketplace CE')}</div>`;
    // Optimistic removal from marketplace cache
    try {
        marketplaceItemsCache = (marketplaceItemsCache || []).filter((p) => Number(p.id) !== Number(id));
        renderMarketplaceList();
        renderMarketplacePagination();
    } catch (e) {
        console.warn('Optimistic marketplace removal failed:', e);
    }

    // Background sync
    void loadMarketplaceCeAdmin(marketplaceCurrentPage);
}

// ===== HELPERS DRAG & DROP CSV =====
function handleCsvDragOver(e, zoneId) {
    e.preventDefault();
    document.getElementById(zoneId).classList.add('drag-over');
}
function handleCsvDragLeave(e, zoneId) {
    document.getElementById(zoneId).classList.remove('drag-over');
}
function handleCsvDrop(e, inputId, zoneId) {
    e.preventDefault();
    const zone = document.getElementById(zoneId);
    zone.classList.remove('drag-over');
    const files = e.dataTransfer.files;
    if (files && files.length > 0) {
        const input = document.getElementById(inputId);
        // Transfer files to the hidden input
        const dt = new DataTransfer();
        dt.items.add(files[0]);
        input.files = dt.files;
        // Determine label element
        const labelId = inputId === 'csvFileInput' ? 'csvSelectedName' : 'mktCsvSelectedName';
        onCsvFileSelected(input, zoneId, labelId);
    }
}
function onCsvFileSelected(input, zoneId, labelId) {
    const zone = document.getElementById(zoneId);
    const label = document.getElementById(labelId);
    if (input.files && input.files.length > 0) {
        const name = input.files[0].name;
        zone.classList.add('file-selected');
        zone.classList.remove('drag-over');
        if (label) label.textContent = '✅ ' + name;
    } else {
        zone.classList.remove('file-selected');
        if (label) label.textContent = 'o haz clic para seleccionar (.csv)';
    }
}

// Parser CSV robusto (soporta campos con comas dentro de comillas)
function parseCsvText(text) {
    const rows = [];
    const lines = text.split(/\r?\n/);
    for (const line of lines) {
        if (line.trim() === '') continue;
        const cols = [];
        let inQuotes = false, current = '';
        for (let ci = 0; ci < line.length; ci++) {
            const ch = line[ci];
            if (ch === '"') {
                if (inQuotes && line[ci + 1] === '"') { current += '"'; ci++; }
                else inQuotes = !inQuotes;
            } else if (ch === ',' && !inQuotes) {
                cols.push(current.trim()); current = '';
            } else {
                current += ch;
            }
        }
        cols.push(current.trim());
        rows.push(cols);
    }
    return rows;
}

// ===== CARGA MASIVA STOCK =====
async function processCsvUpload() {
    const fileInput = document.getElementById('csvFileInput');
    const progressBox = document.getElementById('csvProgress');
    const progressWrap = document.getElementById('csvProgressWrap');
    const progressFill = document.getElementById('csvProgressFill');

    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        showAlert('Selecciona un archivo CSV primero', 'warning');
        return;
    }

    const file = fileInput.files[0];
    const reader = new FileReader();

    reader.onload = async function(e) {
        const text = e.target.result;
        const allRows = parseCsvText(text);
        if (allRows.length < 2) {
            progressBox.textContent = 'El archivo está vacío o no tiene encabezados.';
            progressBox.className = 'csv-progress-label error';
            if (progressWrap) progressWrap.style.display = 'block';
            return;
        }

        const headers = allRows[0].map(h => h.toLowerCase().replace(/^\ufeff/, '')); // strip BOM
        const data = [];
        for (let i = 1; i < allRows.length; i++) {
            const cols = allRows[i];
            if (cols.every(c => c === '')) continue;
            const rowData = {};
            headers.forEach((h, idx) => { rowData[h] = cols[idx] || ''; });
            data.push(rowData);
        }

        if (data.length === 0) {
            progressBox.textContent = 'No se encontraron productos en el archivo.';
            progressBox.className = 'csv-progress-label error';
            if (progressWrap) progressWrap.style.display = 'block';
            return;
        }

        const batchSize = 250;
        let successCount = 0, errorCount = 0;
        if (progressWrap) progressWrap.style.display = 'block';
        progressBox.className = 'csv-progress-label';
        progressBox.textContent = `Procesando ${data.length} productos en lotes de ${batchSize}...`;

        for (let i = 0; i < data.length; i += batchSize) {
            const batch = data.slice(i, i + batchSize);
            try {
                const res = await apiCall('/admin_supply.php?action=product-batch-save', 'POST', { products: batch });
                if (res && res.success) {
                    successCount += res.processed || batch.length;
                } else {
                    errorCount += batch.length;
                }
            } catch (err) {
                errorCount += batch.length;
            }
            const done = Math.min(i + batchSize, data.length);
            const pct = Math.round((done / data.length) * 100);
            if (progressFill) progressFill.style.width = pct + '%';
            progressBox.textContent = `Progreso: ${done} / ${data.length} (${pct}%)`;
        }

        if (progressFill) progressFill.style.width = '100%';
        if (successCount === 0) {
            progressBox.className = 'csv-progress-label error';
            progressBox.textContent = `❌ Carga fallida — 0 exitosos, ${errorCount} fallidos`;
        } else if (errorCount > 0) {
            progressBox.className = 'csv-progress-label warning';
            progressBox.textContent = `⚠️ Carga parcial — ${successCount} exitosos, ${errorCount} fallidos`;
        } else {
            progressBox.className = 'csv-progress-label success';
            progressBox.textContent = `✅ Carga exitosa — ${successCount} exitosos, 0 fallidos`;
        }

        // Reset drop zone
        const zone = document.getElementById('stockCsvDropZone');
        const label = document.getElementById('csvSelectedName');
        if (zone) zone.classList.remove('file-selected');
        if (label) label.textContent = 'o haz clic para seleccionar (.csv)';
        fileInput.value = '';

        // Reset a página 1 y refresca la lista completa
        stockItemsCache = [];
        stockCurrentPage = 1;
        await loadStock(1);
        // Scroll suave a la lista de productos
        const listEl = document.getElementById('stockRows');
        if (listEl) listEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };
    reader.onerror = function() {
        if (progressWrap) progressWrap.style.display = 'block';
        progressBox.textContent = 'Error al leer el archivo.';
        progressBox.className = 'csv-progress-label error';
    };
    reader.readAsText(file, 'UTF-8');
}

// ===== CARGA MASIVA MARKETPLACE CE =====
async function processMarketplaceCsvUpload() {
    const fileInput = document.getElementById('marketplaceCsvFileInput');
    const progressBox = document.getElementById('marketplaceCsvProgress');
    const progressWrap = document.getElementById('mktCsvProgressWrap');
    const progressFill = document.getElementById('mktCsvProgressFill');

    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        showAlert('Selecciona un archivo CSV para Marketplace CE', 'warning');
        return;
    }

    const file = fileInput.files[0];
    const reader = new FileReader();

    reader.onload = async function(e) {
        const text = e.target.result;
        const allRows = parseCsvText(text);
        if (allRows.length < 2) {
            progressBox.textContent = 'El archivo está vacío o no tiene encabezados.';
            progressBox.className = 'csv-progress-label error';
            if (progressWrap) progressWrap.style.display = 'block';
            return;
        }

        const headers = allRows[0].map(h => h.toLowerCase().replace(/^\ufeff/, ''));
        const data = [];
        for (let i = 1; i < allRows.length; i++) {
            const cols = allRows[i];
            if (cols.every(c => c === '')) continue;
            const rowData = {};
            headers.forEach((h, idx) => { rowData[h] = cols[idx] || ''; });
            data.push(rowData);
        }

        if (data.length === 0) {
            progressBox.textContent = 'No se encontraron artículos en el archivo.';
            progressBox.className = 'csv-progress-label error';
            if (progressWrap) progressWrap.style.display = 'block';
            return;
        }

        const batchSize = 250;
        let successCount = 0, errorCount = 0;
        if (progressWrap) progressWrap.style.display = 'block';
        progressBox.className = 'csv-progress-label';
        progressBox.textContent = `Procesando ${data.length} artículos CE en lotes de ${batchSize}...`;

        for (let i = 0; i < data.length; i += batchSize) {
            const batch = data.slice(i, i + batchSize);
            try {
                const res = await apiCall('/admin_supply.php?action=marketplace-batch-save', 'POST', { products: batch });
                if (res && res.success) {
                    successCount += res.processed || batch.length;
                } else {
                    errorCount += batch.length;
                }
            } catch (err) {
                errorCount += batch.length;
            }
            const done = Math.min(i + batchSize, data.length);
            const pct = Math.round((done / data.length) * 100);
            if (progressFill) progressFill.style.width = pct + '%';
            progressBox.textContent = `Progreso: ${done} / ${data.length} (${pct}%)`;
        }

        if (progressFill) progressFill.style.width = '100%';
        if (successCount === 0) {
            progressBox.className = 'csv-progress-label error';
            progressBox.textContent = `❌ Carga CE fallida — 0 exitosos, ${errorCount} fallidos`;
        } else if (errorCount > 0) {
            progressBox.className = 'csv-progress-label warning';
            progressBox.textContent = `⚠️ Carga CE parcial — ${successCount} exitosos, ${errorCount} fallidos`;
        } else {
            progressBox.className = 'csv-progress-label success';
            progressBox.textContent = `✅ Carga CE exitosa — ${successCount} exitosos, 0 fallidos`;
        }

        // Reset drop zone
        const zone = document.getElementById('mktCsvDropZone');
        const label = document.getElementById('mktCsvSelectedName');
        if (zone) zone.classList.remove('file-selected');
        if (label) label.textContent = 'o haz clic para seleccionar (.csv)';
        fileInput.value = '';

        // Reset a p\u00e1gina 1 y refresca la lista completa
        marketplaceItemsCache = [];
        marketplaceCurrentPage = 1;
        await loadMarketplaceCeAdmin(1);
        // Scroll suave a la lista de art\u00edculos
        const mktListEl = document.getElementById('marketplaceList');
        if (mktListEl) mktListEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };
    reader.onerror = function() {
        if (progressWrap) progressWrap.style.display = 'block';
        progressBox.textContent = 'Error al leer el archivo.';
        progressBox.className = 'csv-progress-label error';
    };
    reader.readAsText(file, 'UTF-8');
}

// (uploadMarketplaceImages is defined above — duplicate removed)


function goToClientsTab() {
    const tabButton = document.querySelector('[data-tab="clientsTab"]');
    if (tabButton) {
        tabButton.click();
    }
    const section = document.getElementById('clientsTab');
    if (section) {
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

document.addEventListener('DOMContentLoaded', function () {
    setupTabs();
    renderPoItems();
    // Carga inmediata: solo la pestaña activa (Stock)
    loadStock(stockCurrentPage);
    loadProductImageReferences();
    refreshCategoriesUi();

    // Carga bajo demanda al abrir cada pestaña para acelerar la carga inicial
    var updatesBtn = document.querySelector('[data-tab="updatesTab"]');
    if (updatesBtn) {
        var _updLoaded = false;
        updatesBtn.addEventListener('click', function () {
            if (!_updLoaded) {
                _updLoaded = true;
                loadHomepageUpdatesAdmin();
            }
        });
    }

    var clientsBtn = document.querySelector('[data-tab="clientsTab"]');
    if (clientsBtn) {
        var _cliLoaded = false;
        clientsBtn.addEventListener('click', function () {
            if (!_cliLoaded) {
                _cliLoaded = true;
                loadClients();
            }
        });
    }

    var marketplaceBtn = document.querySelector('[data-tab="marketplaceTab"]');
    if (marketplaceBtn) {
        var _mktLoaded = false;
        marketplaceBtn.addEventListener('click', function () {
            if (!_mktLoaded) {
                _mktLoaded = true;
                loadMarketplaceCeAdmin(marketplaceCurrentPage);
            }
        });
    }

    var calendarBtn = document.querySelector('[data-tab="calendarTab"]');
    if (calendarBtn) { var _calLoaded = false; calendarBtn.addEventListener('click', function () { if (!_calLoaded) { _calLoaded = true; loadCalendar(); } }); }

    var supplierBtn = document.querySelector('[data-tab="supplierOrderTab"]');
    if (supplierBtn) { var _supLoaded = false; supplierBtn.addEventListener('click', function () { if (!_supLoaded) { _supLoaded = true; loadSupplierProducts(); loadMappedProductsBySupplier(); loadSupplierOrders(); } }); }

    var historyBtn = document.querySelector('[data-tab="historyTab"]');
    if (historyBtn) { var _hisLoaded = false; historyBtn.addEventListener('click', function () { if (!_hisLoaded) { _hisLoaded = true; loadHistory(); } }); }

    var categoriesBtn = document.querySelector('[data-tab="categoriesTab"]');
    if (categoriesBtn) {
        var _catLoaded = false;
        categoriesBtn.addEventListener('click', function () {
            if (!_catLoaded) {
                _catLoaded = true;
                refreshCategoriesUi();
            }
        });
    }

    const supplierInput = document.getElementById('poSupplier');
    if (supplierInput) {
        supplierInput.addEventListener('input', loadMappedProductsBySupplier);
        supplierInput.addEventListener('change', loadMappedProductsBySupplier);
        supplierInput.addEventListener('blur', loadMappedProductsBySupplier);
    }

    const productSkuInput = document.getElementById('newProductSku');
    if (productSkuInput) {
        let productSkuDebounce = null;
        productSkuInput.addEventListener('input', function () {
            productSkuInput.value = normalizeNumericSku(productSkuInput.value);
            updateStockPreview();
            setSkuStatus('newProductSkuStatus', 'Código listo para guardar.', 'muted');
            if (productSkuDebounce) window.clearTimeout(productSkuDebounce);
            productSkuDebounce = window.setTimeout(() => {
                validateSkuAvailability('product');
            }, 250);
        });
        productSkuInput.addEventListener('blur', async function () {
            if (productSkuDebounce) window.clearTimeout(productSkuDebounce);
            await validateSkuAvailability('product');
            await loadProductGalleryForCurrentSku();
        });
    }

    const marketplaceSkuInput = document.getElementById('marketplaceSku');
    if (marketplaceSkuInput) {
        let marketplaceSkuDebounce = null;
        marketplaceSkuInput.addEventListener('input', function () {
            marketplaceSkuInput.value = normalizeNumericSku(marketplaceSkuInput.value);
            updateMarketplacePreview();
            setSkuStatus('marketplaceSkuStatus', 'Debe ser único y de 5 o 6 números.', 'muted');
            if (marketplaceSkuDebounce) window.clearTimeout(marketplaceSkuDebounce);
            marketplaceSkuDebounce = window.setTimeout(() => {
                validateSkuAvailability('marketplace');
            }, 250);
        });
        marketplaceSkuInput.addEventListener('blur', async function () {
            if (marketplaceSkuDebounce) window.clearTimeout(marketplaceSkuDebounce);
            await validateSkuAvailability('marketplace');
            await loadMarketplaceGalleryForCurrentSku();
        });
    }

    setSkuStatus('newProductSkuStatus', 'Código listo para guardar.', 'muted');
    setSkuStatus('marketplaceSkuStatus', 'Debe ser único y de 5 o 6 números.', 'muted');

    const stockSearch = document.getElementById('stockSearch');
    if (stockSearch) {
        stockSearch.addEventListener('input', debounce(renderStockList, 300));
    }

    const marketplaceSearch = document.getElementById('marketplaceSearch');
    if (marketplaceSearch) {
        marketplaceSearch.addEventListener('input', renderMarketplaceList);
    }

    const marketplaceBulkSelect = document.getElementById('marketplaceBulkSelect');
    if (marketplaceBulkSelect) {
        marketplaceBulkSelect.addEventListener('dblclick', editMarketplaceSelectedItem);
    }

    const stockBulkSelect = document.getElementById('stockBulkSelect');
    if (stockBulkSelect) {
        stockBulkSelect.addEventListener('dblclick', editStockSelectedItem);
    }

    ['newProductName', 'newProductPrice', 'newProductStock', 'newProductReorder', 'newProductDescription', 'newProductImageRef', 'newProductVisible'].forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', updateStockPreview);
        if (el && el.tagName === 'SELECT') el.addEventListener('change', updateStockPreview);
    });

    const newProductCategory = document.getElementById('newProductCategory');
    if (newProductCategory) {
        newProductCategory.addEventListener('change', updateStockPreview);
    }

    const newProductImages = document.getElementById('newProductImages');
    if (newProductImages) {
        newProductImages.addEventListener('change', function () {
            if (this.files && this.files.length > 0) {
                uploadProductImages();
            }
        });
    }

    const newCategoryQuickName = document.getElementById('newCategoryQuickName');
    if (newCategoryQuickName) {
        newCategoryQuickName.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                addCategoryFromStockForm();
            }
        });
    }

    const marketplaceCategoryQuickName = document.getElementById('marketplaceCategoryQuickName');
    if (marketplaceCategoryQuickName) {
        marketplaceCategoryQuickName.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                addCategoryFromMarketplaceForm();
            }
        });
    }

    ['marketplaceName', 'marketplaceCondition', 'marketplacePrice', 'marketplaceStock', 'marketplaceDescription', 'marketplaceActive', 'marketplaceImageRef'].forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', updateMarketplacePreview);
        if (el && el.tagName === 'SELECT') el.addEventListener('change', updateMarketplacePreview);
    });

    const marketplaceCategory = document.getElementById('marketplaceCategory');
    if (marketplaceCategory) {
        marketplaceCategory.addEventListener('change', updateMarketplacePreview);
    }

    const marketplaceImages = document.getElementById('marketplaceImages');
    if (marketplaceImages) {
        marketplaceImages.addEventListener('change', function () {
            if (this.files && this.files.length > 0) {
                uploadMarketplaceImages();
            }
        });
    }

    updateStockPreview();
    updateMarketplacePreview();
    loadProductGalleryForCurrentSku();
    loadMarketplaceGalleryForCurrentSku();

    // Add file input preview handler for homepage update images (use object URLs for faster previews)
    const imageInput = document.getElementById('updateImage');
    if (imageInput) {
        imageInput.addEventListener('change', function (e) {
            const preview = document.getElementById('updateImagePreview');
            const previewImg = document.getElementById('updateImagePreviewImg');

            if (e.target.files && e.target.files[0] && preview && previewImg) {
                const file = e.target.files[0];
                // Use createObjectURL to avoid base64 encoding and speed up preview
                const url = URL.createObjectURL(file);
                previewImg.src = url;
                preview.style.display = 'block';
                // Revoke object URL after image loads to free memory
                previewImg.onload = function () { URL.revokeObjectURL(url); previewImg.onload = null; };
            }
        });
    }

    // PRICES TAB
    ['priceAdjustType', 'priceAdjustValue', 'priceExcludeSkus'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', applyPriceAdjustment);
    });
});

</script>
    <script src="js/mobile-optimize.js"></script>
</body>
</html>
