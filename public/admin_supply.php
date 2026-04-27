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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abastecimiento - Truper Platform</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/theme.css">
    <style>
        .admin-supply-shell {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .admin-supply-shell .page-hero {
            background: linear-gradient(145deg, var(--ui-surface), var(--ui-surface-soft));
            border: 1px solid var(--ui-border);
            border-radius: 14px;
            padding: 1.05rem 1.1rem;
            box-shadow: var(--ui-shadow);
            margin-bottom: 0;
        }

        .admin-overview-grid {
            margin-top: 0;
            gap: 1rem;
        }

        .admin-overview-card {
            border-radius: 12px;
            border: 1px solid var(--ui-border);
            background: var(--ui-surface);
            box-shadow: 0 10px 24px rgba(17, 24, 39, 0.08);
        }

        .admin-overview-card .card-body {
            padding: 1.1rem;
        }

        .admin-overview-card h3 {
            margin-bottom: 0.3rem;
            font-size: 1.15rem;
        }

        .admin-tabs {
            margin-top: 0;
            border: 1px solid var(--ui-border);
            border-radius: 12px;
            padding: 0.35rem;
            background: var(--ui-surface);
            position: static;
            top: auto;
            z-index: 25;
            box-shadow: 0 6px 14px rgba(17, 24, 39, 0.06);
            gap: 0.35rem;
        }

        .admin-tabs .tab-button {
            border-radius: 10px;
            border-bottom: 0;
            padding: 0.72rem 0.95rem;
            font-weight: 600;
        }

        .admin-tabs .tab-button.active {
            background: var(--theme-accent);
            color: #fff;
        }

        .admin-tab-panel {
            margin-top: 0;
        }

        .admin-tab-panel .card {
            border-radius: 12px;
        }

        .grid-4 { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
        .calendar-weekdays,
        .calendar-days { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
        .calendar-weekday { text-align: center; font-weight: 700; color: var(--ui-text-muted); font-size: 12px; }
        .calendar-day { border: 1px solid var(--ui-border); border-radius: 10px; min-height: 58px; padding: 6px; background: var(--ui-surface); }
        .calendar-day-empty { background: var(--ui-surface-soft); border-style: dashed; }
        .calendar-day-number { font-weight: 700; font-size: 13px; color: var(--ui-text); }
        .calendar-day-visits { margin-top: 4px; font-size: 11px; color: var(--color-naranja); }
        .calendar-day-has-visits { border-color: var(--color-naranja); background: var(--theme-accent-soft); }
        .admin-search-row { display: flex; gap: 0.75rem; align-items: center; margin: 0.5rem 0 1rem; }
        .admin-search-row input { max-width: 480px; }
        .admin-preview-wrap { margin-top: 1rem; }
        .admin-preview-wrap .catalog-grid-min { grid-template-columns: minmax(240px, 330px); }
        .admin-list-caption { color: var(--ui-text-muted); font-size: 0.9rem; margin-bottom: 0.6rem; }
        .admin-editor-card {
            border: 1px solid var(--ui-border);
            background: var(--ui-surface);
            box-shadow: none;
        }
        .admin-editor-card-stock {
            border-left: 4px solid #f59e0b;
            background: linear-gradient(180deg, rgba(245, 158, 11, 0.04) 0%, rgba(0, 0, 0, 0) 48%);
        }
        .admin-editor-card-marketplace {
            border-left: 4px solid #38bdf8;
            background: linear-gradient(180deg, rgba(56, 189, 248, 0.05) 0%, rgba(0, 0, 0, 0) 48%);
        }
        .section-kicker {
            display: inline-flex;
            align-items: center;
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            border-radius: 999px;
            border: 1px solid var(--ui-border);
            padding: 0.2rem 0.55rem;
            margin-bottom: 0.45rem;
        }
        .section-kicker-stock {
            color: #f59e0b;
            border-color: rgba(245, 158, 11, 0.38);
            background: rgba(245, 158, 11, 0.12);
        }
        .section-kicker-marketplace {
            color: #38bdf8;
            border-color: rgba(56, 189, 248, 0.42);
            background: rgba(56, 189, 248, 0.12);
        }
        .admin-section-subtitle {
            margin-top: 1rem;
            margin-bottom: 0.35rem;
            font-size: 0.84rem;
            color: var(--ui-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.03em;
            font-weight: 700;
        }
        .category-quick-tools {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
            flex-wrap: wrap;
        }
        .category-panel {
            border: 1px solid var(--ui-border);
            border-radius: 10px;
            padding: 0.65rem;
            background: var(--ui-surface-soft);
        }
        .category-panel-title {
            font-size: 0.85rem;
            color: var(--ui-text-muted);
            margin-bottom: 0.45rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .category-quick-input {
            max-width: 220px;
            min-width: 170px;
        }
        .category-help-box {
            margin-top: 0.5rem;
            padding: 0.5rem;
            font-size: 12px;
        }
        .category-action-btn {
            min-width: 92px;
        }
        .category-action-btn-remove {
            border-color: #d97706;
            color: #ffffff;
            background: #f59e0b;
        }
        .category-action-btn-remove:hover {
            border-color: #b45309;
            background: #ea8a00;
        }
        .admin-quick-panel {
            border: 1px solid var(--ui-border);
            border-radius: 10px;
            padding: 0.75rem;
            background: var(--ui-surface-soft);
            margin-bottom: 0.8rem;
        }
        .admin-quick-grid {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 0.85rem;
            align-items: end;
        }
        .admin-quick-actions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            min-width: 210px;
        }
        .admin-quick-select {
            width: 100%;
            min-height: 160px;
            border-radius: 8px;
            border: 1px solid var(--ui-border);
            background: var(--ui-surface);
        }
        @media (max-width: 900px) {
            .admin-quick-grid {
                grid-template-columns: 1fr;
            }
            .admin-quick-actions {
                min-width: 0;
                flex-direction: row;
                flex-wrap: wrap;
            }
        }

        @media (max-width: 768px) {
            .admin-tabs {
                overflow-x: auto;
                flex-wrap: nowrap;
                padding-bottom: 0.4rem;
            }

            .admin-tabs .tab-button {
                white-space: nowrap;
            }
        }
    </style>
</head>
<body>
<header>
    <div class="header-content">
        <a href="dashboard.php" class="logo"><img src="images/truper-logo.svg" alt="Truper"></a>
        <nav class="nav-menu">
            <a href="#stockTab" onclick="activateAdminSupplyTab('stockTab'); return false;">Productos</a>
            <a href="orders.php">Pedidos</a>
            <a href="cashier.php">Caja</a>
            <a href="admin_supply.php" class="active">Abastecimiento</a>
            <a href="analytics.php">Estadisticas</a>
            <a href="profile.php">Perfil</a>
        </nav>
    </div>
    <div class="user-menu">
        <div class="user-info"><div class="user-name"><?php echo $user_name; ?></div><div class="user-role">Admin</div></div>
        <a href="index.php" class="btn btn-small btn-ghost">Ver portada</a>
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
        </div>

        <section id="stockTab" class="tab-content active admin-tab-panel">
            <div class="card mb-3 admin-editor-card admin-editor-card-stock"><div class="card-body">
                <div class="section-kicker section-kicker-stock">Stock interno</div>
                <h3>Agregar Producto</h3>
                <p class="text-muted">Registra nuevos productos y opcionalmente sube su imagen.</p>

                <input type="hidden" id="newProductEditId" value="">
                <input type="hidden" id="newProductSeedMode" value="0">

                <div class="grid grid-2">
                    <div class="form-group"><label>Código del producto (5 números)</label><input id="newProductSku" type="text" maxlength="5" inputmode="numeric" pattern="\d{5}" placeholder="Ej. 23032"><small id="newProductSkuStatus" class="text-muted">Debe ser único y de 5 números.</small></div>
                    <div class="form-group"><label>Nombre</label><input id="newProductName" type="text" maxlength="255"></div>
                </div>

                <div class="form-group">
                    <label>Categorías (selección múltiple)</label>
                    <div class="category-panel">
                        <div class="category-panel-title">Administrar categorías</div>
                        <select id="newProductCategory" multiple size="6">
                            <option value="Material eléctrico">Material eléctrico</option>
                            <option value="Fontanería">Fontanería</option>
                            <option value="Cerrajería">Cerrajería</option>
                            <option value="Herrería">Herrería</option>
                        </select>
                        <small class="text-muted">Usa Ctrl/Cmd para seleccionar múltiples categorías.</small>
                        <div class="category-quick-tools">
                            <input id="newCategoryQuickName" class="category-quick-input" type="text" placeholder="Nueva categoría" maxlength="120">
                            <button class="btn btn-small btn-secondary category-action-btn" type="button" onclick="addCategoryFromStockForm()" title="Agregar categoría">Agregar</button>
                            <button class="btn btn-small btn-secondary category-action-btn category-action-btn-remove" type="button" onclick="deleteCategoryQuick()" title="Eliminar categoría seleccionada">Eliminar</button>
                        </div>
                    </div>
                    <div id="quickCategoryResult" class="text-muted" style="font-size:12px; margin-top:6px;"></div>
                </div>

                <div class="grid grid-3">
                    <div class="form-group"><label>Precio</label><input id="newProductPrice" type="number" min="0" step="0.01" value="0"></div>
                    <div class="form-group"><label>Stock inicial</label><input id="newProductStock" type="number" min="0" step="1" value="50"></div>
                    <input id="newProductVisible" type="hidden" value="0">
                </div>

                <div class="grid grid-3">
                    <div class="form-group"><label>Nivel reorden</label><input id="newProductReorder" type="number" min="0" step="1" value="10"></div>
                </div>

                <div class="grid grid-2">
                    <div class="form-group">
                        <label>Imagen de referencia</label>
                        <select id="newProductImageRef">
                            <option value="images/products/default-product.svg">Imagen por defecto</option>
                        </select>
                        <small class="text-muted">Selecciona una imagen ya existente del sitio.</small>
                    </div>
                </div>

                <div class="grid grid-2 mt-2">
                    <div class="form-group">
                        <label>Subir imagen o varias imágenes</label>
                        <input id="newProductImages" type="file" accept="image/*" multiple>
                        <small class="text-muted">Puedes subir una o varias imágenes. Se guardarán en el catálogo de archivos.</small>
                    </div>
                    <div class="form-group d-flex align-center" style="gap: 0.75rem; flex-wrap: wrap;">
                        <button class="btn btn-secondary" type="button" onclick="uploadProductImages()">Cargar imágenes</button>
                        <button class="btn btn-ghost" type="button" onclick="loadProductImageReferences()">Actualizar opciones</button>
                    </div>
                </div>

                <div class="form-group">
                    <label>Galería del producto (por código)</label>
                    <small class="text-muted">Sube varias imágenes para este SKU, define portada y elimina las que no necesites.</small>
                    <div id="productGalleryStatus" class="text-muted" style="margin-top:6px;">Escribe un código de 5 números para cargar su galería.</div>
                    <div id="productGalleryList" style="display:grid; grid-template-columns:repeat(auto-fill,minmax(120px,1fr)); gap:0.6rem; margin-top:0.6rem;"></div>
                </div>

                <div class="form-group"><label>Descripción</label><textarea id="newProductDescription" rows="3"></textarea></div>

                <div class="admin-preview-wrap">
                    <p class="admin-list-caption">Vista previa (estilo portada):</p>
                    <div id="stockPreviewHost" class="catalog-grid-min"></div>
                </div>

                <div class="d-flex align-center" style="gap: 0.75rem; flex-wrap: wrap;">
                    <button class="btn btn-primary" id="newProductSaveButton" onclick="createProductByAdmin()">Guardar producto</button>
                    <button class="btn btn-secondary" type="button" onclick="resetProductForm()">Limpiar formulario</button>
                </div>
                <div id="productCreateResult" class="mt-3"></div>
            </div></div>

            <div class="card"><div class="card-body">
                <h3>Control de Existencias</h3>
                <div class="admin-search-row">
                    <input id="stockSearch" type="text" placeholder="Buscar por código, nombre o categoría...">
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
                    <button class="btn btn-secondary" type="button" onclick="resetUpdateForm()">Limpiar formulario</button>
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
                    <div class="form-group"><label>Proveedor</label><input id="supplierName" type="text"></div>
                    <div class="form-group"><label>Fecha y hora</label><input id="visitDate" type="datetime-local"></div>
                    <div class="form-group"><label>Notas</label><textarea id="visitNotes"></textarea></div>
                    <button class="btn btn-primary" onclick="createVisit()">Guardar visita</button>
                </div></div>
                <div class="card"><div class="card-body">
                    <h3>Calendario mensual</h3>
                    <div class="d-flex justify-between align-center">
                        <button class="btn btn-small btn-ghost" onclick="changeCalendarMonth(-1)">Mes anterior</button>
                        <strong id="calendarMonthLabel">Mes</strong>
                        <button class="btn btn-small btn-ghost" onclick="changeCalendarMonth(1)">Mes siguiente</button>
                    </div>
                    <div id="calendarGrid" class="mt-2"></div>
                    <div id="calendarList" class="text-muted mt-2">Cargando...</div>
                </div></div>
            </div>
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
                    <button class="btn btn-secondary" type="button" onclick="resetClientForm()">Limpiar formulario</button>
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

                <div class="form-group mt-2">
                    <label>Excluir productos (separados por coma)</label>
                    <input id="priceExcludeSkus" type="text" placeholder="TRUP-001, TRUP-002...">
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
                    <div class="form-group"><label>SKU CE (5 números)</label><input id="marketplaceSku" type="text" maxlength="5" inputmode="numeric" pattern="\d{5}" placeholder="Ej. 24061"><small id="marketplaceSkuStatus" class="text-muted">Debe ser único y de 5 números.</small></div>
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
                        <small class="text-muted">Las categorías se comparten con Stock.</small>
                        <div class="category-quick-tools">
                            <input id="marketplaceCategoryQuickName" class="category-quick-input" type="text" placeholder="Nueva categoría" maxlength="120">
                            <button class="btn btn-small btn-secondary category-action-btn" type="button" onclick="addCategoryFromMarketplaceForm()">Agregar</button>
                            <button class="btn btn-small btn-secondary category-action-btn category-action-btn-remove" type="button" onclick="deleteMarketplaceCategoryQuick()">Eliminar</button>
                        </div>
                    </div>
                    <div id="marketplaceQuickCategoryResult" class="text-muted" style="font-size:12px; margin-top:6px;"></div>
                </div>

                <div class="grid grid-3">
                    <div class="form-group"><label>Precio</label><input id="marketplacePrice" type="number" min="0" step="0.01" value="0"></div>
                    <div class="form-group"><label>Stock</label><input id="marketplaceStock" type="number" min="0" step="1" value="1"></div>
                    <input id="marketplaceActive" type="hidden" value="0">
                </div>

                <div class="form-group"><label>Descripción</label><textarea id="marketplaceDescription" rows="4" maxlength="1800"></textarea></div>

                <div class="grid grid-2">
                    <div class="form-group">
                        <label>Imagen de referencia</label>
                        <select id="marketplaceImageRef">
                            <option value="images/products/default-product.svg">Imagen por defecto</option>
                        </select>
                        <small class="text-muted">Selecciona una imagen ya existente del sitio.</small>
                    </div>
                </div>

                <div class="grid grid-2 mt-2">
                    <div class="form-group">
                        <label>Subir imagen o varias imágenes</label>
                        <input id="marketplaceImages" type="file" accept="image/*" multiple>
                        <small class="text-muted">Puedes subir una o varias imágenes para este SKU CE.</small>
                    </div>
                    <div class="form-group d-flex align-center" style="gap: 0.75rem; flex-wrap: wrap;">
                        <button class="btn btn-secondary" type="button" onclick="uploadMarketplaceImages()">Cargar imágenes</button>
                        <button class="btn btn-ghost" type="button" onclick="loadProductImageReferences()">Actualizar opciones</button>
                    </div>
                </div>

                <div class="form-group">
                    <label>Galería del artículo CE (por código)</label>
                    <small class="text-muted">Sube varias imágenes para este SKU CE, acomódalas por orden, define portada y elimina las que no necesites.</small>
                    <div id="marketplaceGalleryStatus" class="text-muted" style="margin-top:6px;">Escribe un código de 5 números para cargar su galería CE.</div>
                    <div id="marketplaceGalleryList" style="display:grid; grid-template-columns:repeat(auto-fill,minmax(120px,1fr)); gap:0.6rem; margin-top:0.6rem;"></div>
                </div>

                <div class="d-flex align-center" style="gap: 0.75rem; flex-wrap: wrap;">
                    <button class="btn btn-primary" type="button" id="marketplaceSaveButton" onclick="saveMarketplaceCeByAdmin()">Guardar artículo CE</button>
                    <button class="btn btn-secondary" type="button" onclick="resetMarketplaceForm()">Limpiar formulario</button>
                </div>

                <div class="admin-preview-wrap">
                    <p class="admin-list-caption">Vista previa (estilo portada):</p>
                    <div id="marketplacePreviewHost" class="catalog-grid-min"></div>
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
                <div id="marketplaceListCaption" class="admin-list-caption">Cargando artículos CE...</div>
                <div id="marketplaceList" class="text-muted">Cargando artículos CE...</div>
            </div></div>
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

function escapeHtml(v) {
    return String(v || '').replace(/[&<>"']/g, function(m) {
        return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m];
    });
}

function displayProductCode(rawSku) {
    return String(rawSku || '').replace(/^XLS-/i, '');
}

function normalizeNumericSku(rawValue) {
    return String(rawValue || '').replace(/\D+/g, '').slice(0, 5);
}

const skuCheckVersion = { product: 0, marketplace: 0 };

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
    const condition = mode === 'marketplace' ? String(item.condition_label || 'Seminuevo') : 'Modelo estándar';
    const stockText = stock <= (mode === 'marketplace' ? 2 : reorder) ? 'Stock bajo: ' : 'Stock: ';
    const stockClass = stock <= (mode === 'marketplace' ? 2 : reorder) ? 'stock-low' : 'stock-ok';
    const inactive = Number(item.is_active) === 0 || item.is_active === false || item.is_active === 'f' || item.is_active === 'false' || item.is_active === 'False' || item.is_active === 'FALSE';
    const seedOnly = Boolean(item.seed_only || item.__seed_only);
    const stateLabel = inactive ? 'Oculto' : 'Visible';
    const stateBadgeClass = inactive ? 'badge-danger' : 'badge-success';
    const actions = mode === 'marketplace'
        ? `
            <button class="btn btn-small btn-secondary" type="button" onclick="fillMarketplaceFormById(${id})">Editar</button>
            <button class="btn btn-small btn-ghost" type="button" onclick="toggleMarketplaceVisibility(${id}, ${inactive ? 1 : 0})">${inactive ? 'Mostrar' : 'Ocultar'}</button>
            <button class="btn btn-small btn-danger" type="button" onclick="deleteMarketplaceCeByAdmin(${id})">Eliminar</button>
        `
        : (seedOnly
            ? `
                <button class="btn btn-small btn-secondary" type="button" onclick="prepareSeedProductForEditing(${id})">Editar</button>
                <span class="text-muted" style="font-size:12px;">Guárdalo para convertirlo en editable</span>
            `
            : `
                <button class="btn btn-small btn-secondary" type="button" onclick="fillProductFormById(${id})">Editar</button>
                <button class="btn btn-small btn-ghost" type="button" onclick="toggleStockVisibility(${id}, ${inactive ? 1 : 0})">${inactive ? 'Mostrar' : 'Ocultar'}</button>
                <button class="btn btn-small btn-danger" type="button" onclick="deleteProductByAdmin(${id})">Eliminar</button>
            `);

    return `
        <article class="product-card-min ${inactive ? 'product-card-inactive' : ''}">
            <div class="product-media">
                <img class="product-gallery-image active" src="${escapeHtml(imageUrl)}" alt="${escapeHtml(name)}" loading="lazy">
            </div>
            <div class="product-content">
                <div class="catalog-tag">${escapeHtml(category)}</div>
                <div class="product-code-label"><strong>Código:</strong> <strong>${escapeHtml(sku)}</strong></div>
                <h3 class="product-title">${escapeHtml(name)}</h3>
                <p class="product-spec">${escapeHtml(description)}</p>
                <div><span class="variant-pill">${escapeHtml(condition)}</span></div>
                <span class="stock-badge ${stockClass}">${stockText}${stock}</span>
                <div style="margin-top:4px;"><span class="badge ${stateBadgeClass}">Estado: ${stateLabel}</span></div>
                <div class="catalog-price">$${Math.round(unitPrice).toLocaleString('es-MX')}</div>
                ${withActions ? `<div class="product-actions">${actions}</div>` : '<div class="text-muted" style="font-size:12px;margin-top:8px;">Vista previa del diseño en portada.</div>'}
                ${inactive ? '<div class="text-muted" style="font-size:12px;margin-top:6px;">Producto oculto/desactivado.</div>' : ''}
            </div>
        </article>
    `;
}

function updateStockPreview() {
    const host = document.getElementById('stockPreviewHost');
    if (!host) return;
    const sku = normalizeNumericSku(document.getElementById('newProductSku')?.value || '');
    const selectedCategoryOptions = Array.from(document.getElementById('newProductCategory')?.selectedOptions || []);
    const selectedCategories = selectedCategoryOptions.map((option) => option.value).filter(Boolean);
    const item = {
        id: 0,
        sku: sku || '00000',
        name: document.getElementById('newProductName')?.value || 'Nombre del producto',
        category: selectedCategories.join(', ') || 'General',
        description: document.getElementById('newProductDescription')?.value || 'Descripción pendiente',
        unit_price: document.getElementById('newProductPrice')?.value || 0,
        stock_quantity: document.getElementById('newProductStock')?.value || 0,
        reorder_level: document.getElementById('newProductReorder')?.value || 10,
        image_url: document.getElementById('newProductImageRef')?.value || 'images/products/default-product.svg',
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
    const item = {
        id: Number(document.getElementById('marketplaceEditId')?.value || 0),
        sku: sku || '00000',
        name: document.getElementById('marketplaceName')?.value || 'Artículo CE',
        category: selectedCategories.join(', ') || 'Marketplace CE',
        description: document.getElementById('marketplaceDescription')?.value || 'Descripción pendiente',
        condition_label: document.getElementById('marketplaceCondition')?.value || 'Seminuevo',
        unit_price: document.getElementById('marketplacePrice')?.value || 0,
        stock_quantity: document.getElementById('marketplaceStock')?.value || 1,
        image_url: document.getElementById('marketplaceImageRef')?.value || 'images/products/default-product.svg',
        is_active: Number(document.getElementById('marketplaceActive')?.value || 1)
    };
    host.innerHTML = renderAdminProductCard(item, 'marketplace', false);
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

async function validateSkuAvailability(kind) {
    const isMarketplace = kind === 'marketplace';
    const skuInput = document.getElementById(isMarketplace ? 'marketplaceSku' : 'newProductSku');
    const statusId = isMarketplace ? 'marketplaceSkuStatus' : 'newProductSkuStatus';
    const sku = normalizeNumericSku(skuInput?.value || '');
    if (skuInput) skuInput.value = sku;

    if (!sku) {
        setSkuStatus(statusId, 'Debe ser único y de 5 números.', 'muted');
        return false;
    }

    if (!/^\d{5}$/.test(sku)) {
        setSkuStatus(statusId, 'El código debe tener exactamente 5 números.', 'warning');
        return false;
    }

    setSkuStatus(statusId, 'Verificando disponibilidad...', 'muted');
    const version = ++skuCheckVersion[kind];
    const currentId = isMarketplace
        ? Number(document.getElementById('marketplaceEditId').value || 0)
        : Number(document.getElementById('newProductEditId').value || 0);
    const allowSeedSku = !isMarketplace && Number(document.getElementById('newProductSeedMode')?.value || 0) === 1;
    const endpoint = isMarketplace
        ? `/admin_supply.php?action=marketplace-sku-check&sku=${encodeURIComponent(sku)}&id=${encodeURIComponent(currentId)}`
        : `/admin_supply.php?action=product-sku-check&sku=${encodeURIComponent(sku)}&id=${encodeURIComponent(currentId)}&allow_seed=${allowSeedSku ? '1' : '0'}`;

    const check = await apiCall(endpoint, 'GET', null, { silent: true });
    if (version !== skuCheckVersion[kind]) {
        return false;
    }

    if (!check || !check.success) {
        // Fallback local: use loaded caches so the admin can continue even if SKU endpoint is temporarily unavailable.
        const normalizeRowSku = (row) => normalizeNumericSku(displayProductCode(row?.sku || ''));

        const existsInStock = stockItemsCache.some((row) => {
            const isSeedRow = Boolean(row?.seed_only || row?.__seed_only);
            if (isSeedRow && !isMarketplace) {
                return false;
            }
            if (!isMarketplace && Number(row?.id || 0) === currentId) {
                return false;
            }
            return normalizeRowSku(row) === sku;
        });

        const existsInMarketplace = marketplaceItemsCache.some((row) => {
            if (isMarketplace && Number(row?.id || 0) === currentId) {
                return false;
            }
            return normalizeRowSku(row) === sku;
        });

        if (existsInStock || existsInMarketplace) {
            setSkuStatus(statusId, existsInStock ? 'Ya existe un producto con ese código.' : 'Ya existe un artículo CE con ese código.', 'error');
            return false;
        }

        setSkuStatus(statusId, 'Validación local aplicada (servidor no disponible).', 'warning');
        return true;
    }

    if (check.available === false) {
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
                            <td style="padding: 0.5rem; text-align: right;">$${item.current_price.toFixed(0)}</td>
                            <td style="padding: 0.5rem; text-align: right; color: var(--color-naranja); font-weight: 600;">$${item.new_price.toFixed(0)}</td>
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
                        <td>${Number(item.is_active) ? '<span class="badge badge-success">Sí</span>' : '<span class="badge badge-danger">No</span>'}</td>
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
    formData.append('is_active', document.getElementById('updateActive').value === '1');
    formData.append('title', title);
    formData.append('body', body);
    
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

async function loadStock() {
    const res = await apiCall('/admin_supply.php?action=stock', 'GET', null, { silent: true });
    const body = document.getElementById('stockRows');
    const caption = document.getElementById('stockListCaption');
    if (!res || !res.success || !Array.isArray(res.items) || res.items.length === 0) {
        const fallback = await apiCall('/products.php?action=list', 'GET', null, { silent: true });
        if (fallback && fallback.success && Array.isArray(fallback.products) && fallback.products.length > 0) {
            stockItemsCache = fallback.products.map((p) => ({
                id: Number(p.id || 0),
                sku: p.sku || '',
                name: p.name || '',
                description: p.description || '',
                category: p.category || 'General',
                stock_quantity: Number(p.stock_quantity || 50),
                reorder_level: Number(p.reorder_level || 10),
                unit_price: Number(p.unit_price || 0),
                image_url: p.image_url || 'images/products/default-product.svg',
                is_active: 1,
                __fallback: true
            }));
            renderStockList();
            return;
        }

        if (body) body.innerHTML = '<p class="text-muted">Sin datos</p>';
        if (caption) caption.textContent = 'No fue posible cargar productos.';
        return;
    }

    stockItemsCache = Array.isArray(res.items) ? res.items : [];
    renderStockList();
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
}

async function deleteCategoryQuick() {
    return deleteCategoryQuickFromSelect('newProductCategory', 'quickCategoryResult');
}

async function deleteMarketplaceCategoryQuick() {
    return deleteCategoryQuickFromSelect('marketplaceCategory', 'marketplaceQuickCategoryResult');
}

async function addCategoryFromQuickForm(selectId, inputId, resultId) {
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
        String(opt.value || '').trim().toLowerCase() === raw.toLowerCase()
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
        is_active: true
    };

    const res = await apiCall('/admin_supply.php?action=categories-save', 'POST', payload);
    if (!res || !res.success) {
        if (quickBox) quickBox.innerHTML = `<span style="color:#f87171;">❌ ${escapeHtml((res && res.message) ? res.message : 'No fue posible guardar la categoría.')}</span>`;
        showAlert((res && res.message) ? res.message : 'No fue posible guardar la categoría', 'error');
        return;
    }

    // Reflect creation immediately in the same select control.
    if (categorySelect) {
        const option = document.createElement('option');
        option.value = raw;
        option.textContent = raw;
        option.dataset.id = String(Number((res.item && res.item.id) ? res.item.id : 0));
        option.selected = true;
        categorySelect.appendChild(option);
    }

    if (input) input.value = '';
    if (quickBox) quickBox.innerHTML = '<span style="color:#22c55e;">✓ Categoría guardada correctamente.</span>';
    showAlert(res.message || 'Categoría guardada', 'success');
    await refreshCategoriesUi();

    if (categorySelect) {
        const target = Array.from(categorySelect.options || []).find((opt) =>
            String(opt.value || '').trim().toLowerCase() === raw.toLowerCase()
        );
        if (target) {
            target.selected = true;
            target.scrollIntoView({ block: 'nearest' });
        }
    }
}

async function addCategoryFromStockForm() {
    return addCategoryFromQuickForm('newProductCategory', 'newCategoryQuickName', 'quickCategoryResult');
}

async function addCategoryFromMarketplaceForm() {
    return addCategoryFromQuickForm('marketplaceCategory', 'marketplaceCategoryQuickName', 'marketplaceQuickCategoryResult');
}

function renderStockList() {
    const body = document.getElementById('stockRows');
    const caption = document.getElementById('stockListCaption');
    const search = (document.getElementById('stockSearch')?.value || '').toLowerCase().trim();

    if (!body) return;

    const filtered = stockItemsCache.filter((item) => {
        const code = displayProductCode(item.sku || '').toLowerCase();
        const name = String(item.name || '').toLowerCase();
        const cat = String(item.category || '').toLowerCase();
        return `${code} ${name} ${cat}`.includes(search);
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

    fillProductFormById(target.id);
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

    await loadStock();
}

function resetProductForm() {
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
    setSkuStatus('newProductSkuStatus', 'Debe ser único y de 5 números.', 'muted');
    updateStockPreview();
    loadProductGalleryForCurrentSku();
}

function fillProductFormById(id) {
    const item = stockItemsCache.find((row) => Number(row.id) === Number(id));
    if (!item) return;

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
    if (imageRefSelect) {
        const exists = Array.from(imageRefSelect.options || []).some((opt) => opt.value === itemImage);
        if (!exists) {
            const option = document.createElement('option');
            option.value = itemImage;
            option.textContent = itemImage;
            imageRefSelect.appendChild(option);
        }
        imageRefSelect.value = itemImage;
    }
    document.getElementById('newProductVisible').value = Number(item.is_active) ? '1' : '0';

    const categories = String(item.category || '')
        .split(',')
        .map((x) => x.trim())
        .filter(Boolean);
    Array.from(document.getElementById('newProductCategory').options || []).forEach((opt) => {
        opt.selected = categories.includes(opt.value);
    });

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
    updateStockPreview();
    loadProductGalleryForCurrentSku();
}

function prepareSeedProductForEditing(id) {
    fillProductFormById(id);
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
    loadStock();
    loadSupplierProducts();
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
    await loadStock();
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
    await loadMarketplaceCeAdmin();
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

    // Validation
    if (!supplierName) {
        showAlert('El nombre del proveedor es requerido', 'warning');
        return;
    }
    if (!visitDate) {
        showAlert('La fecha y hora son requeridas', 'warning');
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
    } else if (res) {
        showAlert(res.message || 'No fue posible guardar la visita', 'error');
    }
    loadCalendar();
}

let calendarVisits = [];
let calendarMonthCursor = new Date(new Date().getFullYear(), new Date().getMonth(), 1);

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
        html += `<div class="calendar-day ${count > 0 ? 'calendar-day-has-visits' : ''}">`;
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

    if (monthVisits.length === 0) {
        list.innerHTML = '<p class="text-muted">Sin visitas para este mes.</p>';
        return;
    }

    list.innerHTML = monthVisits.map(i => `<div class="task-item"><strong>${escapeHtml(i.supplier_name)}</strong><div>${escapeHtml(formatDateTimeLocal(i.visit_datetime))}</div><div class="text-muted">${escapeHtml(i.notes || '')}</div></div>`).join('');
}

function changeCalendarMonth(offset) {
    calendarMonthCursor = new Date(calendarMonthCursor.getFullYear(), calendarMonthCursor.getMonth() + offset, 1);
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

    const res = await apiCall('/admin_supply.php?action=product-images', 'GET', null, { silent: true });
    if (!res || !res.success || !Array.isArray(res.images)) {
        return;
    }

    const applyImagesToSelect = function (selectEl) {
        if (!selectEl) return;
        const current = selectEl.value || 'images/products/default-product.svg';
        selectEl.innerHTML = '';
        res.images.forEach((img) => {
            const option = document.createElement('option');
            option.value = img;
            option.textContent = img;
            selectEl.appendChild(option);
        });

        if (Array.from(selectEl.options).some((o) => o.value === current)) {
            selectEl.value = current;
        } else if (Array.from(selectEl.options).some((o) => o.value === 'images/products/default-product.svg')) {
            selectEl.value = 'images/products/default-product.svg';
        }
    };

    applyImagesToSelect(stockSelect);
    applyImagesToSelect(marketplaceSelect);
    updateStockPreview();
    updateMarketplacePreview();
}

async function loadProductCategories(onlyActive = true) {
    const categorySelect = document.getElementById('newProductCategory');
    const marketplaceCategorySelect = document.getElementById('marketplaceCategory');
    const categoriesListBox = document.getElementById('categoriesList');
    const action = `/admin_supply.php?action=categories-list${onlyActive ? '&active=1' : ''}`;
    const res = await apiCall(action, 'GET', null, { silent: true });

    if (!res || !res.success || !Array.isArray(res.items)) {
        if (categoriesListBox && !onlyActive) {
            categoriesListBox.innerHTML = '<p class="text-muted">No fue posible cargar categorías.</p>';
        }
        return;
    }

    if (onlyActive) {
        const fillSelect = function (selectEl) {
            if (!selectEl) return;
            const selectedValues = new Set(
                Array.from(selectEl.selectedOptions || []).map((option) => String(option.value || '').trim().toLowerCase())
            );
            const normalizeForDedup = (value) => {
                return String(value || '')
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .toLowerCase()
                    .trim();
            };
            const seenCategories = new Set();
            selectEl.innerHTML = '';
            res.items.forEach((cat) => {
                const categoryName = String(cat.name || '').trim();
                const categoryNameNormalized = normalizeForDedup(categoryName);
                
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
    }

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
                            <td>${Number(cat.is_active) ? '<span class="badge badge-success">Sí</span>' : '<span class="badge badge-danger">No</span>'}</td>
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
    await loadProductCategories(true);
    await loadProductCategories(false);
    updateStockPreview();
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
    document.getElementById('categoryActive').value = Number(category.is_active) ? '1' : '0';
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

async function reorderGalleryImages(sku, orderedImages, mode = 'stock') {
    const res = await apiCall('/admin_supply.php?action=product-gallery-reorder', 'POST', {
        sku: sku,
        images: orderedImages
    });

    if (!res || !res.success) {
        showGalleryResult(mode, (res && res.message) ? res.message : 'No se pudo reordenar la galería', 'error');
        return false;
    }

    showGalleryResult(mode, res.message || 'Orden de imágenes actualizado', 'success');
    await loadProductImageReferences();
    if (mode === 'marketplace') {
        await loadMarketplaceGalleryForCurrentSku();
    } else {
        await loadProductGalleryForCurrentSku();
    }
    await loadStock();
    await loadMarketplaceCeAdmin();
    return true;
}

async function moveGalleryImage(sku, imagePath, direction, mode = 'stock') {
    const listAction = `/admin_supply.php?action=product-gallery-list&sku=${encodeURIComponent(sku)}`;
    const listing = await apiCall(listAction, 'GET', null, { silent: true });
    const images = Array.isArray(listing?.images) ? listing.images.slice() : [];
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
    const listAction = `/admin_supply.php?action=product-gallery-list&sku=${encodeURIComponent(sku)}`;
    const listing = await apiCall(listAction, 'GET', null, { silent: true });
    const images = Array.isArray(listing?.images) ? listing.images.slice() : [];
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
    const host = document.getElementById(mode === 'marketplace' ? 'marketplaceGalleryList' : 'productGalleryList');
    const status = document.getElementById(mode === 'marketplace' ? 'marketplaceGalleryStatus' : 'productGalleryStatus');
    if (!host || !status) return;

    if (!Array.isArray(images) || images.length === 0) {
        host.innerHTML = '';
        status.textContent = `No hay imágenes cargadas para el código ${sku}.`;
        return;
    }

    const setCoverFn = mode === 'marketplace' ? 'setMarketplaceGalleryCover' : 'setProductGalleryCover';
    const deleteFn = mode === 'marketplace' ? 'deleteMarketplaceGalleryImage' : 'deleteProductGalleryImage';
    const moveToFn = mode === 'marketplace' ? 'moveMarketplaceGalleryImageTo' : 'moveProductGalleryImageTo';

    status.textContent = `Galería para ${sku}: ${images.length} imagen(es)`;
    host.innerHTML = images.map((img, idx) => `
        <div style="border:1px solid var(--ui-border); border-radius:10px; padding:0.5rem; background:var(--ui-surface-soft);">
            <img src="${escapeHtml(img)}" alt="Imagen ${idx + 1}" style="width:100%; height:90px; object-fit:cover; border-radius:8px;">
            <div style="display:flex; align-items:center; gap:0.35rem; margin-top:0.45rem;">
                <label style="font-size:12px; color:var(--ui-text-muted);">Posición</label>
                <select id="galleryPos-${mode}-${idx}" style="max-width:90px;" onchange="${moveToFn}('${escapeHtml(sku)}','${escapeHtml(img)}', this.value)">
                    ${images.map((_, pos) => `<option value="${pos + 1}" ${pos === idx ? 'selected' : ''}>${pos + 1}</option>`).join('')}
                </select>
            </div>
            <div style="display:flex; gap:0.35rem; margin-top:0.45rem; flex-wrap:wrap;">
                <button class="btn btn-small btn-secondary" type="button" onclick="${setCoverFn}('${escapeHtml(sku)}','${escapeHtml(img)}')">Portada</button>
                <button class="btn btn-small btn-danger" type="button" onclick="${deleteFn}('${escapeHtml(sku)}','${escapeHtml(img)}')">Eliminar</button>
            </div>
            ${idx === 0 ? '<div class="text-muted" style="font-size:11px; margin-top:4px;">Portada actual</div>' : ''}
        </div>
    `).join('');
}

async function loadProductGalleryForCurrentSku() {
    const sku = getCurrentStockSkuForGallery();
    const host = document.getElementById('productGalleryList');
    const status = document.getElementById('productGalleryStatus');
    if (!host || !status) return;

    if (!/^\d{5}$/.test(sku)) {
        host.innerHTML = '';
        status.textContent = 'Escribe un código de 5 números para cargar su galería.';
        return;
    }

    const res = await apiCall(`/admin_supply.php?action=product-gallery-list&sku=${encodeURIComponent(sku)}`, 'GET', null, { silent: true });
    if (!res || !res.success) {
        status.textContent = (res && res.message) ? res.message : 'No se pudo cargar la galería del producto.';
        host.innerHTML = '';
        return;
    }

    renderProductGallery(Array.isArray(res.images) ? res.images : [], sku, 'stock');
    if (res.cover) {
        const select = document.getElementById('newProductImageRef');
        if (select) {
            const exists = Array.from(select.options || []).some((opt) => opt.value === res.cover);
            if (!exists) {
                const option = document.createElement('option');
                option.value = res.cover;
                option.textContent = res.cover;
                select.appendChild(option);
            }
            select.value = res.cover;
        }
    }
    updateStockPreview();
}

async function loadMarketplaceGalleryForCurrentSku() {
    const sku = getCurrentMarketplaceSkuForGallery();
    const host = document.getElementById('marketplaceGalleryList');
    const status = document.getElementById('marketplaceGalleryStatus');
    if (!host || !status) return;

    if (!/^\d{5}$/.test(sku)) {
        host.innerHTML = '';
        status.textContent = 'Escribe un código de 5 números para cargar su galería CE.';
        return;
    }

    const res = await apiCall(`/admin_supply.php?action=product-gallery-list&sku=${encodeURIComponent(sku)}`, 'GET', null, { silent: true });
    if (!res || !res.success) {
        status.textContent = (res && res.message) ? res.message : 'No se pudo cargar la galería del artículo CE.';
        host.innerHTML = '';
        return;
    }

    renderProductGallery(Array.isArray(res.images) ? res.images : [], sku, 'marketplace');
    if (res.cover) {
        const select = document.getElementById('marketplaceImageRef');
        if (select) {
            const exists = Array.from(select.options || []).some((opt) => opt.value === res.cover);
            if (!exists) {
                const option = document.createElement('option');
                option.value = res.cover;
                option.textContent = res.cover;
                select.appendChild(option);
            }
            select.value = res.cover;
        }
    }
    updateMarketplacePreview();
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
    await loadProductImageReferences();
    await loadProductGalleryForCurrentSku();
    await loadStock();
    await loadMarketplaceCeAdmin();
}

async function deleteProductGalleryImage(sku, imagePath) {
    if (!confirm('¿Eliminar esta imagen de la galería?')) return;
    const res = await apiCall('/admin_supply.php?action=product-gallery-delete', 'POST', {
        sku: sku,
        image: imagePath
    });

    if (!res || !res.success) {
        showGalleryResult('stock', (res && res.message) ? res.message : 'No se pudo eliminar la imagen', 'error');
        return;
    }

    showGalleryResult('stock', res.message || 'Imagen eliminada', 'success');
    await loadProductImageReferences();
    await loadProductGalleryForCurrentSku();
    await loadStock();
    await loadMarketplaceCeAdmin();
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
    await loadProductImageReferences();
    await loadMarketplaceGalleryForCurrentSku();
    await loadMarketplaceCeAdmin();
    await loadStock();
}

async function deleteMarketplaceGalleryImage(sku, imagePath) {
    if (!confirm('¿Eliminar esta imagen de la galería CE?')) return;

    const res = await apiCall('/admin_supply.php?action=product-gallery-delete', 'POST', {
        sku: sku,
        image: imagePath
    });

    if (!res || !res.success) {
        showGalleryResult('marketplace', (res && res.message) ? res.message : 'No se pudo eliminar la imagen CE', 'error');
        return;
    }

    showGalleryResult('marketplace', res.message || 'Imagen CE eliminada', 'success');
    await loadProductImageReferences();
    await loadMarketplaceGalleryForCurrentSku();
    await loadMarketplaceCeAdmin();
    await loadStock();
}

async function uploadProductImages() {
    const input = document.getElementById('newProductImages');
    const resultBox = document.getElementById('productCreateResult');
    const sku = getCurrentStockSkuForGallery();

    if (!/^\d{5}$/.test(sku)) {
        if (resultBox) {
            resultBox.innerHTML = '<div class="alert alert-error">Primero captura un código de producto válido (5 números)</div>';
        }
        return;
    }

    if (!input || !input.files || input.files.length === 0) {
        if (resultBox) {
            resultBox.innerHTML = '<div class="alert alert-error">Selecciona una o varias imágenes para cargar</div>';
        }
        return;
    }

    const formData = new FormData();
    formData.append('sku', sku);
    Array.from(input.files).forEach((file) => {
        formData.append('images[]', file);
    });

    try {
        const response = await fetch('/api/admin_supply.php?action=product-gallery-upload', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const data = await response.json();
        if (!data || !data.success) {
            if (resultBox) {
                resultBox.innerHTML = `<div class="alert alert-error">${escapeHtml((data && data.message) ? data.message : 'No fue posible cargar las imágenes')}</div>`;
            }
            return;
        }

        if (resultBox) {
            resultBox.innerHTML = `<div class="alert alert-success">${escapeHtml(data.message || 'Imágenes cargadas correctamente')}</div>`;
        }

        input.value = '';
        await loadProductImageReferences();
        await loadProductGalleryForCurrentSku();
        await loadMarketplaceCeAdmin();

        const select = document.getElementById('newProductImageRef');
        if (select && data.cover) {
            const exists = Array.from(select.options || []).some((o) => o.value === data.cover);
            if (!exists) {
                const option = document.createElement('option');
                option.value = data.cover;
                option.textContent = data.cover;
                select.appendChild(option);
            }
            select.value = data.cover;
        }
    } catch (error) {
        if (resultBox) {
            resultBox.innerHTML = '<div class="alert alert-error">Error al cargar imágenes</div>';
        }
    }
}

async function uploadMarketplaceImages() {
    const input = document.getElementById('marketplaceImages');
    const sku = getCurrentMarketplaceSkuForGallery();

    if (!/^\d{5}$/.test(sku)) {
        showGalleryResult('marketplace', 'Primero captura un código SKU CE válido (5 números)', 'error');
        return;
    }

    if (!input || !input.files || input.files.length === 0) {
        showGalleryResult('marketplace', 'Selecciona una o varias imágenes para cargar', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('sku', sku);
    Array.from(input.files).forEach((file) => {
        formData.append('images[]', file);
    });

    try {
        const response = await fetch('/api/admin_supply.php?action=marketplace-image-upload', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const data = await response.json();
        if (!data || !data.success) {
            showGalleryResult('marketplace', (data && data.message) ? data.message : 'No fue posible cargar las imágenes CE', 'error');
            return;
        }

        showGalleryResult('marketplace', data.message || 'Imágenes CE cargadas correctamente', 'success');
        input.value = '';
        await loadProductImageReferences();
        await loadMarketplaceGalleryForCurrentSku();
        await loadMarketplaceCeAdmin();
        await loadStock();

        const select = document.getElementById('marketplaceImageRef');
        if (select && data.cover) {
            const exists = Array.from(select.options || []).some((o) => o.value === data.cover);
            if (!exists) {
                const option = document.createElement('option');
                option.value = data.cover;
                option.textContent = data.cover;
                select.appendChild(option);
            }
            select.value = data.cover;
        }
        updateMarketplacePreview();
    } catch (error) {
        showGalleryResult('marketplace', 'Error al cargar imágenes CE', 'error');
    }
}

async function createProductByAdmin() {
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
    if (!/^\d{5}$/.test(normalizedSku)) {
        if (box) {
            box.innerHTML = '<div class="alert alert-error">El código del producto debe tener exactamente 5 números.</div>';
        }
        setSkuStatus('newProductSkuStatus', 'El código debe tener exactamente 5 números.', 'warning');
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

    const skuOk = await validateSkuAvailability('product');
    if (!skuOk) {
        if (box) {
            box.innerHTML = '<div class="alert alert-error">No fue posible guardar: código inválido o duplicado.</div>';
        }
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
    loadStock();
    loadSupplierProducts();
    loadProductGalleryForCurrentSku();
    activateAdminSupplyTab('stockTab', 'productCreateResult');
}

function resetMarketplaceForm() {
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
    setSkuStatus('marketplaceSkuStatus', 'Debe ser único y de 5 números.', 'muted');
    updateMarketplacePreview();
    loadMarketplaceGalleryForCurrentSku();
}

function fillMarketplaceForm(item) {
    if (!item) return;
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
    Array.from(document.getElementById('marketplaceCategory')?.options || []).forEach((opt) => {
        opt.selected = categories.includes(opt.value);
    });

    const marketplaceImageRef = document.getElementById('marketplaceImageRef');
    if (marketplaceImageRef && item.image_url) {
        const exists = Array.from(marketplaceImageRef.options || []).some((opt) => opt.value === item.image_url);
        if (!exists) {
            const option = document.createElement('option');
            option.value = item.image_url;
            option.textContent = item.image_url;
            marketplaceImageRef.appendChild(option);
        }
        marketplaceImageRef.value = item.image_url;
    }

    const saveBtn = document.getElementById('marketplaceSaveButton');
    const box = document.getElementById('marketplaceResult');
    if (saveBtn) saveBtn.textContent = 'Actualizar artículo CE';
    validateSkuAvailability('marketplace');
    if (box) {
        const visibility = Number(item.is_active) ? 'Visible' : 'Oculto';
        box.innerHTML = `<div class="alert alert-info">Editando artículo CE: estado actual <strong>${visibility}</strong>.</div>`;
    }
    updateMarketplacePreview();
    loadMarketplaceGalleryForCurrentSku();
}

function fillMarketplaceFormById(id) {
    const item = marketplaceItemsCache.find((row) => Number(row.id) === Number(id));
    if (!item) return;
    fillMarketplaceForm(item);
}

async function loadMarketplaceCeAdmin() {
    const box = document.getElementById('marketplaceList');
    const caption = document.getElementById('marketplaceListCaption');
    const quickBox = document.getElementById('marketplaceQuickResult');
    const res = await apiCall('/admin_supply.php?action=marketplace-list', 'GET', null, { silent: true });

    if (!res || !res.success || !Array.isArray(res.items)) {
        if (box) box.innerHTML = '<p class="text-muted">No fue posible cargar artículos CE.</p>';
        if (caption) caption.textContent = 'No fue posible cargar artículos CE.';
        if (quickBox) quickBox.innerHTML = '<span style="color:#f87171;">No fue posible cargar la gestión rápida.</span>';
        updateMarketplaceQuickSelection([]);
        return;
    }

    marketplaceItemsCache = Array.isArray(res.items) ? res.items : [];
    renderMarketplaceList();
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

    fillMarketplaceForm(target);
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

    if (!confirm(`¿Eliminar ${selectedIds.length} artículo(s) seleccionado(s)? Se ocultarán del Marketplace CE.`)) {
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
        showAlert(`${successCount} artículo(s) CE eliminado(s)`, 'success');
    }
    if (firstError) {
        if (quickBox) quickBox.innerHTML += ` <span style="color:#f87171;">${escapeHtml(firstError)}</span>`;
        showAlert(firstError, 'error');
    }

    await loadMarketplaceCeAdmin();
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
        return;
    }

    box.innerHTML = `<div class="catalog-grid-min">${filtered.map((item) => renderAdminProductCard(item, 'marketplace')).join('')}</div>`;
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
    if (!/^\d{5}$/.test(normalizedSku)) {
        if (box) box.innerHTML = '<div class="alert alert-error">El código SKU CE debe tener exactamente 5 números.</div>';
        setSkuStatus('marketplaceSkuStatus', 'El código debe tener exactamente 5 números.', 'warning');
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

    const skuOk = await validateSkuAvailability('marketplace');
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
    loadMarketplaceCeAdmin();
    loadStock();
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

    if (box) box.innerHTML = `<div class="alert alert-success">${escapeHtml(res.message || 'Artículo CE eliminado del Marketplace CE')}</div>`;
    loadMarketplaceCeAdmin();
}

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
    loadStock();
    loadCalendar();
    loadSupplierProducts();
    loadMappedProductsBySupplier();
    loadSupplierOrders();
    loadHistory();
    loadProductImageReferences();
    refreshCategoriesUi();
    loadMarketplaceCeAdmin();
    loadClients();
    loadHomepageUpdatesAdmin();

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
            if (productSkuDebounce) window.clearTimeout(productSkuDebounce);
            productSkuDebounce = window.setTimeout(() => {
                validateSkuAvailability('product');
                loadProductGalleryForCurrentSku();
            }, 220);
        });
        productSkuInput.addEventListener('blur', async function () {
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
            if (marketplaceSkuDebounce) window.clearTimeout(marketplaceSkuDebounce);
            marketplaceSkuDebounce = window.setTimeout(() => {
                validateSkuAvailability('marketplace');
                loadMarketplaceGalleryForCurrentSku();
            }, 220);
        });
        marketplaceSkuInput.addEventListener('blur', async function () {
            await validateSkuAvailability('marketplace');
            await loadMarketplaceGalleryForCurrentSku();
        });
    }

    setSkuStatus('newProductSkuStatus', 'Debe ser único y de 5 números.', 'muted');
    setSkuStatus('marketplaceSkuStatus', 'Debe ser único y de 5 números.', 'muted');

    const stockSearch = document.getElementById('stockSearch');
    if (stockSearch) {
        stockSearch.addEventListener('input', renderStockList);
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

    updateStockPreview();
    updateMarketplacePreview();
    loadProductGalleryForCurrentSku();
    loadMarketplaceGalleryForCurrentSku();

    // Add file input preview handler for homepage update images
    const imageInput = document.getElementById('updateImage');
    if (imageInput) {
        imageInput.addEventListener('change', function (e) {
            const preview = document.getElementById('updateImagePreview');
            const previewImg = document.getElementById('updateImagePreviewImg');
            
            if (e.target.files && e.target.files[0] && preview && previewImg) {
                const reader = new FileReader();
                reader.onload = function (event) {
                    previewImg.src = event.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(e.target.files[0]);
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
</body>
</html>
