<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/image_cache.php';

$id = (int)($_GET['id'] ?? 0);
$update = null;

if ($id > 0) {
    try {
        $imageSelect = db_column_exists('homepage_updates', 'image_url')
            ? "COALESCE(image_url, '') AS image_url"
            : "'' AS image_url";
            
        $stmt = $pdo->prepare("
            SELECT id, update_type, title, body, COALESCE(brief_description, '') AS brief_description, {$imageSelect}, 
                   COALESCE(additional_images, '[]') AS additional_images, 
                   COALESCE(registration_url, '') AS registration_url, 
                   COALESCE(design_template, 'classic') AS design_template,
                   created_at
            FROM homepage_updates 
            WHERE id = ? LIMIT 1
        ");
        $stmt->execute([$id]);
        $update = $stmt->fetch();
    } catch (Exception $e) {
        $update = null;
    }
}

if (!$update) {
    header('Location: index.php');
    exit;
}

$isLogged = is_logged_in();
$isAdmin = (($_SESSION['role'] ?? '') === 'admin' || ($_SESSION['role'] ?? '') === 'employee');

function get_update_label($type) {
    $value = strtolower(trim((string)$type));
    if ($value === 'promocion') {
        return 'Promoción';
    }
    if ($value === 'evento') {
        return 'Evento';
    }
    return 'Noticia';
}

function get_update_badge_class($type) {
    $value = strtolower(trim((string)$type));
    if ($value === 'promocion') {
        return 'badge-promo';
    }
    if ($value === 'evento') {
        return 'badge-event';
    }
    return 'badge-news';
}

$formattedDate = '';
if (!empty($update['created_at'])) {
    try {
        $dt = new DateTime($update['created_at']);
        $months = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        $formattedDate = $dt->format('d') . ' de ' . $months[(int)$dt->format('m')] . ' de ' . $dt->format('Y');
    } catch (Exception $e) {
        $formattedDate = '';
    }
}

$galleryImages = [];
try {
    $galleryImages = json_decode($update['additional_images'], true);
    if (!is_array($galleryImages)) {
        $galleryImages = [];
    }
} catch(Exception $e) {
    $galleryImages = [];
}

$whatsappHelpUrl = whatsapp_url('Hola, tengo una duda sobre la publicación: ' . ($update['title'] ?? ''));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($update['title'], ENT_QUOTES, 'UTF-8'); ?> - Ferretería FOX</title>
    <link rel="stylesheet" href="<?php echo asset_url('css/styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('css/theme.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('css/responsive-complete.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('css/dark-mode-auto.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('css/catalog-min.css'); ?>">
        <style>
        /* ============================================================
           BASE — Page Structure
           ============================================================ */
        .detail-wrapper {
            max-width: 860px;
            margin: 2.5rem auto;
            padding: 0 1.5rem;
        }

        .back-nav { margin-bottom: 2rem; }

        .btn-back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--theme-text-muted, #888);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.88rem;
            letter-spacing: 0.02em;
            transition: color 0.2s, transform 0.2s;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
        }
        .btn-back-link:hover {
            color: var(--theme-accent, #ff7f00);
            transform: translateX(-3px);
        }

        /* ============================================================
           PREMIUM GLASS CARD WITH ORANGE DIFFUSED CONTOUR
           ============================================================ */
        .premium-detail-card {
            background: rgba(18, 18, 22, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1.5px solid rgba(255, 127, 0, 0.28);
            border-radius: 20px;
            padding: 2.5rem 2.25rem;
            box-shadow: 0 0 28px rgba(255, 127, 0, 0.12), 0 16px 40px rgba(0, 0, 0, 0.45);
            transition: border-color 0.3s ease, box-shadow 0.3s ease, transform 0.3s ease;
        }
        .premium-detail-card:hover {
            border-color: rgba(255, 127, 0, 0.55);
            box-shadow: 0 0 40px rgba(255, 127, 0, 0.26), 0 20px 48px rgba(0, 0, 0, 0.55);
            transform: translateY(-2px);
        }
        :root[data-theme="light"] .premium-detail-card {
            background: rgba(255, 255, 255, 0.88);
            border-color: rgba(255, 127, 0, 0.22);
            box-shadow: 0 0 20px rgba(255, 127, 0, 0.08), 0 8px 30px rgba(0, 0, 0, 0.08);
        }
        :root[data-theme="light"] .premium-detail-card:hover {
            border-color: rgba(255, 127, 0, 0.45);
            box-shadow: 0 0 32px rgba(255, 127, 0, 0.18), 0 14px 36px rgba(0, 0, 0, 0.12);
        }

        /* ============================================================
           BADGES
           ============================================================ */
        .update-badge {
            display: inline-block;
            padding: 4px 12px;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            border-radius: 999px;
            letter-spacing: 0.08em;
            margin-bottom: 0.85rem;
        }
        .badge-news  { color: #dbeafe; background: rgba(30,64,175,0.4); border: 1px solid rgba(59,130,246,0.5); }
        .badge-promo { color: #fef08a; background: rgba(133,77,14,0.4); border: 1px solid rgba(234,179,8,0.5); }
        .badge-event { color: #e9d5ff; background: rgba(107,33,168,0.4); border: 1px solid rgba(168,85,247,0.5); }

        .update-meta {
            font-size: 0.82rem;
            color: var(--theme-text-muted, #777);
            margin-bottom: 0;
            letter-spacing: 0.02em;
        }

        /* ============================================================
           PREMIUM GRADIENT DIVIDER
           ============================================================ */
        .premium-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent 0%, rgba(255,127,0,0.4) 20%, rgba(255,127,0,0.4) 80%, transparent 100%);
            margin: 1.75rem 0;
            border: none;
            opacity: 0.85;
        }

        /* ============================================================
           BODY CONTENT
           ============================================================ */
        .body-content {
            font-size: 0.98rem;
            line-height: 1.8;
            color: var(--theme-text, rgba(255,255,255,0.88));
            white-space: pre-line;
            margin-bottom: 0;
        }
        :root[data-theme="light"] .body-content { color: #2d3748; }

        /* ============================================================
           GALLERY GRID
           ============================================================ */
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 12px;
            margin-top: 0;
            max-width: 600px;
        }
        .gallery-item {
            position: relative;
            aspect-ratio: 4/3;
            overflow: hidden;
            border-radius: 8px;
            border: 1.5px solid rgba(255,127,0,0.2);
            cursor: zoom-in;
            background: #0b0b0d;
            transition: transform 0.3s ease, border-color 0.3s, box-shadow 0.3s;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }
        .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.45s ease;
        }
        .gallery-item:hover {
            transform: translateY(-2px);
            border-color: rgba(255,127,0,0.55);
            box-shadow: 0 0 20px rgba(255,127,0,0.24), 0 8px 16px rgba(0,0,0,0.45);
        }
        .gallery-item:hover img { transform: scale(1.05); }

        .gallery-section-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--theme-accent, #ff7f00);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin: 0 0 1rem;
        }
        .gallery-section-title svg { flex-shrink: 0; opacity: 0.85; }

        /* ============================================================
           LEAD / BRIEF DESC
           ============================================================ */
        .lead-desc {
            font-size: 1.05rem !important;
            line-height: 1.65 !important;
            color: var(--theme-accent, #ff7f00) !important;
            margin: 0 0 0 !important;
            font-style: italic;
            font-weight: 500;
            border-left: 3px solid rgba(255,127,0,0.7);
            padding-left: 1rem;
            border-radius: 0 2px 2px 0;
        }

        /* ============================================================
           CTA BOX
           ============================================================ */
        .registration-cta-box {
            padding: 1.5rem;
            background: linear-gradient(135deg, rgba(255,102,0,0.06) 0%, rgba(12,12,15,0.75) 100%);
            border: 1px solid rgba(255,102,0,0.25);
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
            backdrop-filter: blur(8px);
            transition: border-color 0.3s, box-shadow 0.3s, transform 0.3s;
        }
        :root[data-theme="light"] .registration-cta-box {
            background: linear-gradient(135deg, rgba(255,102,0,0.04) 0%, rgba(247,250,252,0.95) 100%);
            border-color: rgba(255,102,0,0.2);
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .registration-cta-box:hover {
            border-color: rgba(255,102,0,0.45);
            box-shadow: 0 10px 28px rgba(0,0,0,0.25), 0 0 15px rgba(255,102,0,0.05);
            transform: translateY(-1px);
        }
        .registration-cta-box h4 {
            margin: 0 0 0.4rem;
            color: var(--theme-accent, #ff7f00);
            font-size: 1rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .registration-cta-box p {
            margin: 0 0 1.15rem;
            font-size: 0.85rem;
            line-height: 1.5;
            color: var(--theme-text-muted, rgba(255,255,255,0.6));
        }
        .registration-cta-box .btn-cta {
            padding: 0.65rem 1.5rem;
            font-size: 0.82rem;
            font-weight: 800;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: linear-gradient(135deg, #ff6600, #ff8c00);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.1);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            box-shadow: 0 4px 12px rgba(255,102,0,0.25);
            transition: all 0.2s ease;
            cursor: pointer;
            text-decoration: none;
            max-width: 250px;
            width: 100%;
            box-sizing: border-box;
            margin: 0 auto;
        }
        .registration-cta-box .btn-cta:hover {
            background: linear-gradient(135deg, #ff8c00, #ffaa00);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(255,102,0,0.35);
            color: #fff;
        }

        /* ============================================================
           TEMPLATE 1: CLASSIC 🏛️
           ============================================================ */
        .tpl-classic {
            max-width: 720px;
            margin: 0 auto;
        }
        .tpl-classic .page-header {
            margin-bottom: 1.25rem;
        }
        .tpl-classic h1 {
            font-size: 1.8rem;
            font-weight: 800;
            line-height: 1.25;
            letter-spacing: -0.02em;
            margin: 0.4rem 0 0.3rem;
            font-family: 'Outfit', sans-serif;
        }
        .tpl-classic .cover-wrap {
            max-width: 520px;
            margin: 0 auto 1.5rem;
            border-radius: 10px;
            overflow: hidden;
            border: 1.5px solid rgba(255,127,0,0.25);
            box-shadow: 0 4px 15px rgba(0,0,0,0.4);
            background: rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: border-color 0.3s ease;
        }
        .tpl-classic .cover-wrap:hover {
            border-color: rgba(255,127,0,0.5);
        }
        .tpl-classic .cover-wrap img {
            width: 100%;
            height: auto;
            max-height: 280px;
            object-fit: contain;
            display: block;
        }

        /* ============================================================
           TEMPLATE 2: SPLIT 🌗
           ============================================================ */
        .tpl-split {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        @media (min-width: 768px) {
            .tpl-split {
                flex-direction: row;
                align-items: flex-start;
            }
            .tpl-split .split-sidebar {
                flex: 0 0 250px;
                position: sticky;
                top: 2rem;
            }
            .tpl-split .split-main { flex: 1; }
        }
        .tpl-split h1 {
            font-size: 1.7rem;
            font-weight: 800;
            margin: 0.35rem 0 0.25rem;
            line-height: 1.25;
            letter-spacing: -0.02em;
            font-family: 'Outfit', sans-serif;
        }
        .tpl-split .split-sidebar img.sidebar-cover {
            width: 100%;
            height: auto;
            max-height: 240px;
            object-fit: contain;
            background: rgba(0,0,0,0.15);
            border-radius: 10px;
            border: 1.5px solid rgba(255,127,0,0.25);
            display: block;
            margin-bottom: 1rem;
            transition: border-color 0.3s ease;
        }
        .tpl-split .split-sidebar img.sidebar-cover:hover {
            border-color: rgba(255,127,0,0.5);
        }

        /* ============================================================
           TEMPLATE 3: GALLERY 🖼️
           ============================================================ */
        .tpl-gallery {
            max-width: 800px;
            margin: 0 auto;
        }
        .tpl-gallery .page-header {
            text-align: center;
            margin-bottom: 1.25rem;
        }
        .tpl-gallery h1 {
            font-size: 1.8rem;
            font-weight: 800;
            margin: 0.35rem 0 0.25rem;
            letter-spacing: -0.02em;
            font-family: 'Outfit', sans-serif;
        }
        .tpl-gallery .gallery-hero {
            display: grid;
            grid-template-columns: 2fr 1fr;
            grid-template-rows: repeat(2, 130px);
            gap: 10px;
            margin: 0 auto 1.5rem;
            max-width: 600px;
        }
        .tpl-gallery .gallery-hero.single-image {
            grid-template-columns: 1fr;
            grid-template-rows: auto;
            max-width: 480px;
        }
        .tpl-gallery .gallery-hero.single-image .gallery-hero-item {
            aspect-ratio: 16/10;
        }
        @media (max-width: 600px) {
            .tpl-gallery .gallery-hero {
                grid-template-columns: 1fr;
                grid-template-rows: auto;
            }
        }
        .tpl-gallery .gallery-hero-item {
            border-radius: 10px;
            overflow: hidden;
            border: 1.5px solid rgba(255,127,0,0.25);
            cursor: zoom-in;
            background: #1a1a1f;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            transition: border-color 0.3s ease;
        }
        .tpl-gallery .gallery-hero-item:hover {
            border-color: rgba(255,127,0,0.5);
        }
        .tpl-gallery .gallery-hero-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .tpl-gallery .hero-main { grid-row: span 2; }

        /* ============================================================
           TEMPLATE 4: MINIMAL ✨
           ============================================================ */
        .tpl-minimal {
            max-width: 700px;
            margin: 0 auto;
        }
        .tpl-minimal h1 {
            font-size: 1.7rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin: 0.35rem 0 0.25rem;
            line-height: 1.25;
            font-family: 'Outfit', sans-serif;
        }
        .tpl-minimal .minimal-cover {
            width: 100%;
            max-width: 480px;
            height: auto;
            max-height: 240px;
            object-fit: contain;
            background: rgba(0,0,0,0.15);
            border-radius: 10px;
            display: block;
            margin: 0 auto 1.5rem;
            border: 1.5px solid rgba(255,127,0,0.25);
            transition: border-color 0.3s ease;
        }
        .tpl-minimal .minimal-cover:hover {
            border-color: rgba(255,127,0,0.5);
        }

        /* ============================================================
           TEMPLATE 5: MAGAZINE 📰
           ============================================================ */
        .tpl-magazine {
            max-width: 800px;
            margin: 0 auto;
        }
        .tpl-magazine .magazine-header {
            position: relative;
            overflow: hidden;
            height: 240px;
            display: flex;
            align-items: flex-end;
        }
        .tpl-magazine .magazine-header .header-bg {
            position: absolute; top: 0; left: 0;
            width: 100%; height: 100%;
            object-fit: cover; z-index: 1;
        }
        .tpl-magazine .magazine-header .header-overlay {
            position: absolute; top: 0; left: 0;
            width: 100%; height: 100%;
            background: linear-gradient(to top, rgba(0,0,0,0.92) 0%, rgba(0,0,0,0.4) 60%, rgba(0,0,0,0.05) 100%);
            z-index: 2;
        }
        .tpl-magazine .magazine-header .header-text {
            position: relative; z-index: 3;
            padding: 1.75rem;
            width: 100%;
        }
        .tpl-magazine .magazine-header h1 {
            font-size: 1.85rem;
            color: #fff;
            margin: 0 0 0.3rem;
            text-shadow: 0 2px 6px rgba(0,0,0,0.6);
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.2;
        }
        .tpl-magazine .magazine-header .update-meta {
            color: rgba(255,255,255,0.75);
            margin-bottom: 0;
        }
        .tpl-magazine .body-content {
            font-family: 'Inter', sans-serif;
        }
        .tpl-magazine .body-content::first-letter {
            font-size: 3rem;
            font-weight: bold;
            float: left;
            margin-right: 8px;
            line-height: 0.88;
            color: var(--theme-accent, #ff7f00);
            font-family: 'Outfit', sans-serif;
            margin-top: 4px;
        }
    </style>
</head>
<body class="catalog-minimal" data-client-code="PUBLICO" data-client-number="0">
    <!-- HEADER -->
    <header>
        <div class="header-content">
            <a href="index.php" class="logo"><span class="logo-brand">Ferretería <span class="logo-bold">FOX</span></span></a>
            <button class="hamburger-btn" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <nav class="nav-menu">
                <a href="index.php">Productos</a>
                <a href="marketplace_ce.php">Marketplace CE</a>
                <a href="cart.php">Carrito</a>
                <?php if ($isLogged): ?>
                    <div class="nav-dropdown">
                        <button class="nav-dropdown-btn">Mi Cuenta <span class="arrow">▼</span></button>
                        <div class="nav-dropdown-content">
                            <a href="dashboard.php">Dashboard</a>
                            <a href="orders.php">Pedidos</a>
                            <a href="wholesale.php">Mayoreo</a>
                            <a href="account.php#historyTab">Historial</a>
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
                            <a href="gastos.php">Gastos</a>
                            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                                <a href="analytics.php">Estadísticas</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </nav>
            <div class="header-actions">
                <a href="<?php echo htmlspecialchars($whatsappHelpUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-small">Dudas por WhatsApp</a>
            </div>
        </div>
    </header>

    <main class="detail-wrapper">
        <!-- Back Navigation -->
        <div class="back-nav">
            <button onclick="history.back()" class="btn-back-link">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                Regresar al catálogo
            </button>
        </div>

        <?php 
        $tpl = strtolower(trim((string)$update['design_template']));
        if (!in_array($tpl, ['classic', 'split', 'gallery', 'minimal', 'magazine'])) {
            $tpl = 'classic';
        }
        ?>

        <!-- RENDER CHOSEN TEMPLATE -->
        <?php if ($tpl === 'split'): ?>
            <!-- TEMPLATE 2: SPLIT LAYOUT -->
            <div class="tpl-split premium-detail-card">
                <div class="split-sidebar">
                    <?php if (!empty($update['image_url'])): ?>
                        <img class="sidebar-cover promo-image" src="<?php echo htmlspecialchars($update['image_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($update['title'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php endif; ?>
                    
                    <?php if (!empty($update['registration_url'])): ?>
                        <div class="registration-cta-box">
                            <h4>📝 Registro</h4>
                            <p>Accede al registro o documentos de este evento/noticia.</p>
                            <a href="<?php echo htmlspecialchars($update['registration_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-cta">
                                Registrarse aquí
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="split-main">
                    <span class="update-badge <?php echo get_update_badge_class($update['update_type']); ?>"><?php echo get_update_label($update['update_type']); ?></span>
                    <h1><?php echo htmlspecialchars($update['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                    <?php if ($formattedDate): ?>
                        <div class="update-meta">Publicado el <?php echo $formattedDate; ?></div>
                    <?php endif; ?>

                    <hr class="premium-divider">

                    <?php if (!empty($update['brief_description'])): ?>
                        <p class="lead-desc"><?php echo htmlspecialchars($update['brief_description'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <hr class="premium-divider">
                    <?php endif; ?>
                    <div class="body-content"><?php echo htmlspecialchars($update['body'], ENT_QUOTES, 'UTF-8'); ?></div>

                    <?php if (!empty($galleryImages)): ?>
                        <hr class="premium-divider">
                        <div class="gallery-section-title">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            Imágenes adicionales
                        </div>
                        <div class="gallery-grid">
                            <?php foreach ($galleryImages as $img): ?>
                                <div class="gallery-item promo-image-gallery">
                                    <img class="promo-image" src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="Galería adicional">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($tpl === 'gallery'): ?>
            <!-- TEMPLATE 3: GALLERY FOCUS LAYOUT -->
            <div class="tpl-gallery premium-detail-card">
                <span class="update-badge <?php echo get_update_badge_class($update['update_type']); ?>" style="display:table; margin: 0 auto 1rem;"><?php echo get_update_label($update['update_type']); ?></span>
                <h1 style="text-align: center;"><?php echo htmlspecialchars($update['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <?php if ($formattedDate): ?>
                    <div class="update-meta" style="text-align: center; margin-bottom: 1.5rem;">Publicado el <?php echo $formattedDate; ?></div>
                <?php endif; ?>

                <?php 
                // Display cover and first two gallery images in top hero, others in normal grid
                $heroImages = [];
                if (!empty($update['image_url'])) {
                    $heroImages[] = $update['image_url'];
                }
                $remainingGallery = $galleryImages;
                while (count($heroImages) < 3 && !empty($remainingGallery)) {
                    $heroImages[] = array_shift($remainingGallery);
                }
                ?>
                
                <?php if (!empty($heroImages)): ?>
                    <div class="gallery-hero <?php echo count($heroImages) === 1 ? 'single-image' : ''; ?>">
                        <?php foreach ($heroImages as $index => $img): ?>
                            <div class="gallery-hero-item <?php echo ($index === 0 && count($heroImages) > 1) ? 'hero-main' : ''; ?> promo-image-gallery">
                                <img class="promo-image" src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="Galería principal">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <hr class="premium-divider">

                <?php if (!empty($update['brief_description'])): ?>
                    <p class="lead-desc" style="max-width: 600px; margin-left: auto; margin-right: auto;"><?php echo htmlspecialchars($update['brief_description'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <hr class="premium-divider">
                <?php endif; ?>
                <div class="body-content" style="max-width: 600px; margin-left: auto; margin-right: auto;"><?php echo htmlspecialchars($update['body'], ENT_QUOTES, 'UTF-8'); ?></div>

                <?php if (!empty($remainingGallery)): ?>
                    <hr class="premium-divider">
                    <div class="gallery-section-title" style="justify-content: center;">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        Más fotos de la galería
                    </div>
                    <div class="gallery-grid" style="margin-left: auto; margin-right: auto;">
                        <?php foreach ($remainingGallery as $img): ?>
                            <div class="gallery-item promo-image-gallery">
                                <img class="promo-image" src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="Galería secundaria">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($update['registration_url'])): ?>
                    <hr class="premium-divider">
                    <div class="registration-cta-box" style="max-width: 600px; margin: 2rem auto 0;">
                        <h4>📝 Registro / Documentación</h4>
                        <p>Completa el formulario de registro o consulta la información complementaria.</p>
                        <a href="<?php echo htmlspecialchars($update['registration_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-cta">
                            <span>Completar Registro</span>
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14L21 3"/>
                            </svg>
                        </a>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($tpl === 'minimal'): ?>
            <!-- TEMPLATE 4: MINIMAL PREMIUM LAYOUT -->
            <div class="tpl-minimal premium-detail-card">
                <span class="update-badge <?php echo get_update_badge_class($update['update_type']); ?>"><?php echo get_update_label($update['update_type']); ?></span>
                <h1><?php echo htmlspecialchars($update['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <?php if ($formattedDate): ?>
                    <div class="update-meta" style="margin-bottom: 1.5rem;">Publicado el <?php echo $formattedDate; ?></div>
                <?php endif; ?>

                <?php if (!empty($update['image_url'])): ?>
                    <img class="minimal-cover promo-image" src="<?php echo htmlspecialchars($update['image_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($update['title'], ENT_QUOTES, 'UTF-8'); ?>">
                <?php endif; ?>

                <hr class="premium-divider">

                <?php if (!empty($update['brief_description'])): ?>
                    <p class="lead-desc"><?php echo htmlspecialchars($update['brief_description'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <hr class="premium-divider">
                <?php endif; ?>
                <div class="body-content"><?php echo htmlspecialchars($update['body'], ENT_QUOTES, 'UTF-8'); ?></div>

                <?php if (!empty($galleryImages)): ?>
                    <hr class="premium-divider">
                    <div class="gallery-section-title">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        Galería Complementaria
                    </div>
                    <div class="gallery-grid">
                        <?php foreach ($galleryImages as $img): ?>
                            <div class="gallery-item promo-image-gallery">
                                <img class="promo-image" src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="Galería minimalista">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($update['registration_url'])): ?>
                    <hr class="premium-divider">
                    <div class="registration-cta-box" style="margin-top: 2rem;">
                        <h4>Enlace de Registro / Soporte</h4>
                        <p>Haz clic abajo para completar tu registro o descargar la documentación.</p>
                        <a href="<?php echo htmlspecialchars($update['registration_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-cta">
                            Enlace de Registro
                        </a>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($tpl === 'magazine'): ?>
            <!-- TEMPLATE 5: MAGAZINE LAYOUT -->
            <div class="tpl-magazine premium-detail-card" style="padding: 0; overflow: hidden;">
                <div class="magazine-header">
                    <?php if (!empty($update['image_url'])): ?>
                        <img class="header-bg promo-image" src="<?php echo htmlspecialchars($update['image_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($update['title'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php else: ?>
                        <div class="header-bg" style="background:#222;"></div>
                    <?php endif; ?>
                    <div class="header-overlay"></div>
                    <div class="header-text">
                        <span class="update-badge <?php echo get_update_badge_class($update['update_type']); ?>"><?php echo get_update_label($update['update_type']); ?></span>
                        <h1><?php echo htmlspecialchars($update['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                        <?php if ($formattedDate): ?>
                            <div class="update-meta">Publicado el <?php echo $formattedDate; ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="padding: 2.25rem 2rem;">
                    <?php if (!empty($update['brief_description'])): ?>
                        <p class="lead-desc"><?php echo htmlspecialchars($update['brief_description'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <hr class="premium-divider">
                    <?php endif; ?>
                    <div class="body-content"><?php echo htmlspecialchars($update['body'], ENT_QUOTES, 'UTF-8'); ?></div>

                    <?php if (!empty($galleryImages)): ?>
                        <hr class="premium-divider">
                        <div class="gallery-section-title">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            Reportaje Fotográfico
                        </div>
                        <div class="gallery-grid">
                            <?php foreach ($galleryImages as $img): ?>
                                <div class="gallery-item promo-image-gallery" style="aspect-ratio: 16/10;">
                                    <img class="promo-image" src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="Galería revista">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($update['registration_url'])): ?>
                        <hr class="premium-divider">
                        <div class="registration-cta-box">
                            <h4>📌 Enlace Relacionado / Registro</h4>
                            <p>¿Interesado en participar? Accede al formulario a través del botón oficial.</p>
                            <a href="<?php echo htmlspecialchars($update['registration_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-cta">
                                <span>Ingresar al Formulario</span>
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14L21 3"/>
                                </svg>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>
            <!-- TEMPLATE 1: CLASSIC LAYOUT (DEFAULT) -->
            <div class="tpl-classic premium-detail-card">
                <div class="page-header">
                    <span class="update-badge <?php echo get_update_badge_class($update['update_type']); ?>"><?php echo get_update_label($update['update_type']); ?></span>
                    <h1><?php echo htmlspecialchars($update['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                    <?php if ($formattedDate): ?>
                        <div class="update-meta">Publicado el <?php echo $formattedDate; ?></div>
                    <?php endif; ?>
                </div>

                <hr class="premium-divider">

                <?php if (!empty($update['image_url'])): ?>
                    <div class="cover-wrap">
                        <img class="promo-image" src="<?php echo htmlspecialchars($update['image_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($update['title'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <hr class="premium-divider">
                <?php endif; ?>

                <?php if (!empty($update['brief_description'])): ?>
                    <p class="lead-desc"><?php echo htmlspecialchars($update['brief_description'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <hr class="premium-divider">
                <?php endif; ?>
                <div class="body-content"><?php echo htmlspecialchars($update['body'], ENT_QUOTES, 'UTF-8'); ?></div>

                <?php if (!empty($galleryImages)): ?>
                    <hr class="premium-divider">
                    <div class="gallery-section-title">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        Galería de fotos
                    </div>
                    <div class="gallery-grid">
                        <?php foreach ($galleryImages as $img): ?>
                            <div class="gallery-item promo-image-gallery">
                                <img class="promo-image" src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="Galería adicional">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($update['registration_url'])): ?>
                    <hr class="premium-divider">
                    <div class="registration-cta-box">
                        <h4>📝 Registro e Inscripción</h4>
                        <p>Puedes completar tu registro o acceder a la documentación de soporte a través del siguiente enlace.</p>
                        <a href="<?php echo htmlspecialchars($update['registration_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-cta">
                            Regístrate Aquí
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- FOOTER -->
    <footer>
        <div class="footer-bottom">&copy; 2026 Ferretería FOX</div>
    </footer>

    <script src="<?php echo asset_url('js/main.js'); ?>"></script>
    <script src="<?php echo asset_url('js/modals.js'); ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Lightbox for Gallery Zoom
            function openLightbox(src, alt) {
                let lightbox = document.getElementById('promoLightbox');
                if (!lightbox) {
                    lightbox = document.createElement('div');
                    lightbox.id = 'promoLightbox';
                    lightbox.style.cssText = `
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100vw;
                        height: 100vh;
                        background: rgba(0, 0, 0, 0.85);
                        backdrop-filter: blur(8px);
                        -webkit-backdrop-filter: blur(8px);
                        z-index: 9999;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        opacity: 0;
                        transition: opacity 0.3s ease;
                        cursor: zoom-out;
                    `;
                    lightbox.innerHTML = `
                        <button type="button" style="
                            position: absolute;
                            top: 1.5rem;
                            right: 1.5rem;
                            background: rgba(255, 255, 255, 0.1);
                            border: 1px solid rgba(255, 255, 255, 0.2);
                            color: #fff;
                            border-radius: 50%;
                            width: 44px;
                            height: 44px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            font-size: 1.5rem;
                            cursor: pointer;
                            transition: all 0.2s;
                            font-weight: bold;
                        " onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.transform='scale(1.05)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.transform='none'">✕</button>
                        <img id="promoLightboxImg" src="" alt="" style="
                            max-width: 90%;
                            max-height: 90%;
                            object-fit: contain;
                            border-radius: 8px;
                            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
                            transform: scale(0.95);
                            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
                        ">
                    `;
                    document.body.appendChild(lightbox);
                    
                    const close = () => {
                        lightbox.style.opacity = '0';
                        lightbox.querySelector('img').style.transform = 'scale(0.95)';
                        setTimeout(() => {
                            lightbox.style.display = 'none';
                        }, 300);
                    };
                    
                    lightbox.addEventListener('click', close);
                    lightbox.querySelector('button').addEventListener('click', (e) => {
                        e.stopPropagation();
                        close();
                    });
                }
                
                const img = document.getElementById('promoLightboxImg');
                img.src = src;
                img.alt = alt || '';
                
                lightbox.style.display = 'flex';
                lightbox.offsetHeight;
                lightbox.style.opacity = '1';
                img.style.transform = 'scale(1)';
            }

            // Event delegation for promo images zoom
            document.addEventListener('click', function(e) {
                const img = e.target.closest('.promo-image');
                if (img) {
                    e.preventDefault();
                    e.stopPropagation();
                    openLightbox(img.src, img.alt);
                }
            });

            // Mobile menu toggle
            const hamburgerBtn = document.querySelector('.hamburger-btn');
            const navMenu = document.querySelector('.nav-menu');

            if (hamburgerBtn && navMenu) {
                hamburgerBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    navMenu.classList.toggle('active');
                    hamburgerBtn.classList.toggle('active');
                });

                // Dropdowns in mobile menu
                const dropdownBtns = document.querySelectorAll('.nav-dropdown-btn');
                dropdownBtns.forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        const dropdown = this.closest('.nav-dropdown');
                        dropdown.classList.toggle('active');
                    });
                });
            }
        });
    </script>
</body>
</html>
