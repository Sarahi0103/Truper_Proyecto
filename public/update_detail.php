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
    <title><?php echo htmlspecialchars($update['title'], ENT_QUOTES, 'UTF-8'); ?> - Truper</title>
    <link rel="stylesheet" href="<?php echo asset_url('css/styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('css/theme.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('css/responsive-complete.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('css/dark-mode-auto.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('css/catalog-min.css'); ?>">
    <style>
        /* Base Styling for Informative Page */
        .detail-wrapper {
            max-width: 1100px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        
        .back-nav {
            margin-bottom: 1.5rem;
        }

        .btn-back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--theme-text-muted, #888);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
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

        /* Badge Styling */
        .update-badge {
            display: inline-block;
            padding: 4px 12px;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            border-radius: 999px;
            letter-spacing: 0.05em;
            margin-bottom: 1rem;
        }
        .badge-news {
            color: #dbeafe;
            background: rgba(30, 64, 175, 0.4);
            border: 1px solid rgba(59, 130, 246, 0.5);
        }
        .badge-promo {
            color: #fef08a;
            background: rgba(133, 77, 14, 0.4);
            border: 1px solid rgba(234, 179, 8, 0.5);
        }
        .badge-event {
            color: #e9d5ff;
            background: rgba(107, 33, 168, 0.4);
            border: 1px solid rgba(168, 85, 247, 0.5);
        }

        .update-meta {
            font-size: 0.85rem;
            color: var(--theme-text-muted, #888);
            margin-bottom: 1.5rem;
        }

        .premium-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent 0%, rgba(255, 127, 0, 0.4) 20%, rgba(255, 127, 0, 0.4) 80%, transparent 100%);
            margin: 2rem 0;
            border: none;
            opacity: 0.85;
        }

        /* Generic Gallery Grid */
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
            margin-top: 2rem;
        }
        
        .gallery-item {
            position: relative;
            aspect-ratio: 4/3;
            overflow: hidden;
            border-radius: 12px;
            border: 2px solid rgba(255, 127, 0, 0.3);
            cursor: zoom-in;
            background: #0b0b0d;
            transition: transform 0.3s cubic-bezier(0.25, 0.8, 0.25, 1), border-color 0.3s, box-shadow 0.3s;
            box-shadow: 0 0 25px rgba(255, 127, 0, 0.12), 0 8px 24px rgba(0, 0, 0, 0.45);
        }
        
        .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .gallery-item:hover {
            transform: translateY(-4px) scale(1.02);
            border-color: rgba(255, 127, 0, 0.7);
            box-shadow: 0 0 35px rgba(255, 127, 0, 0.35), 0 15px 30px rgba(0,0,0,0.55);
        }
        
        .gallery-item:hover img {
            transform: scale(1.05);
        }

        /* CTA Section for registration link */
        .registration-cta-box {
            margin-top: 3rem;
            padding: 2.25rem 2rem;
            background: linear-gradient(135deg, rgba(255, 102, 0, 0.08) 0%, rgba(255, 102, 0, 0.02) 50%, rgba(12, 12, 15, 0.65) 100%);
            border: 1px solid rgba(255, 102, 0, 0.28);
            border-radius: 18px;
            text-align: center;
            box-shadow: 0 15px 35px rgba(0,0,0,0.35), inset 0 1px 1px rgba(255,255,255,0.06);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            transition: border-color 0.3s, box-shadow 0.3s, transform 0.3s;
        }
        .registration-cta-box:hover {
            border-color: rgba(255, 102, 0, 0.45);
            box-shadow: 0 20px 45px rgba(0,0,0,0.45), 0 0 20px rgba(255, 102, 0, 0.08);
            transform: translateY(-2px);
        }
        .registration-cta-box h4 {
            margin: 0 0 0.65rem;
            color: var(--theme-accent, #ff7f00);
            font-size: 1.3rem;
            font-weight: 800;
            letter-spacing: -0.01em;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-shadow: 0 2px 10px rgba(255, 102, 0, 0.15);
        }
        .registration-cta-box p {
            margin: 0 0 1.5rem;
            font-size: 0.92rem;
            line-height: 1.5;
            color: rgba(255, 255, 255, 0.7);
        }
        .registration-cta-box .btn-cta {
            padding: 0.85rem 2rem;
            font-size: 0.92rem;
            font-weight: 800;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: linear-gradient(135deg, #ff6600, #ff8c00);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.1);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            box-shadow: 0 6px 20px rgba(255,102,0,0.25);
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            cursor: pointer;
            text-decoration: none;
            max-width: 280px;
            width: 100%;
            box-sizing: border-box;
            margin: 0 auto;
        }
        .registration-cta-box .btn-cta:hover {
            background: linear-gradient(135deg, #ff8c00, #ffaa00);
            transform: translateY(-2px) scale(1.01);
            box-shadow: 0 8px 25px rgba(255, 102, 0, 0.4), 0 0 0 4px rgba(255, 102, 0, 0.15);
            color: #ffffff;
        }
        .registration-cta-box .btn-cta:active {
            transform: translateY(1px);
        }


        /* ============================================================
           TEMPLATE 1: CLASSIC (🏛️ Clásica)
        ============================================================ */
        .tpl-classic {
            max-width: 800px;
            margin: 0 auto;
        }
        .tpl-classic .cover-wrap {
            width: 100%;
            margin-bottom: 2rem;
            border-radius: 16px;
            overflow: hidden;
            border: 2px solid rgba(255, 127, 0, 0.35);
            box-shadow: 0 0 30px rgba(255, 127, 0, 0.18), 0 15px 35px rgba(0,0,0,0.5);
            background: #0c0c0f;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .tpl-classic .cover-wrap:hover {
            border-color: rgba(255, 127, 0, 0.65);
            box-shadow: 0 0 40px rgba(255, 127, 0, 0.35), 0 20px 45px rgba(0,0,0,0.6);
        }
        .tpl-classic .cover-wrap img {
            width: 100%;
            height: auto;
            display: block;
            max-height: 520px;
            object-fit: contain;
            background: #0c0c0f;
        }
        .tpl-classic h1 {
            font-size: 2.25rem;
            margin-bottom: 0.75rem;
            line-height: 1.25;
        }
        .tpl-classic .body-content {
            font-size: 1.05rem;
            line-height: 1.7;
            color: var(--theme-text, #eee);
            margin-bottom: 2rem;
            white-space: pre-line;
        }

        /* ============================================================
           TEMPLATE 2: SPLIT (🌗 Lateral)
        ============================================================ */
        .tpl-split {
            display: flex;
            flex-direction: column;
            gap: 2.5rem;
        }
        @media (min-width: 768px) {
            .tpl-split {
                flex-direction: row;
                align-items: flex-start;
            }
            .tpl-split .split-sidebar {
                flex: 0 0 380px;
                position: sticky;
                top: 2rem;
            }
            .tpl-split .split-main {
                flex: 1;
            }
        }
        .tpl-split .split-sidebar img.sidebar-cover {
            width: 100%;
            height: auto;
            max-height: 420px;
            object-fit: contain;
            background: #0c0c0f;
            border-radius: 16px;
            border: 2px solid rgba(255, 127, 0, 0.35);
            box-shadow: 0 0 30px rgba(255, 127, 0, 0.18), 0 15px 35px rgba(0,0,0,0.35);
            display: block;
            margin-bottom: 1.5rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .tpl-split .split-sidebar img.sidebar-cover:hover {
            border-color: rgba(255, 127, 0, 0.65);
            box-shadow: 0 0 40px rgba(255, 127, 0, 0.35), 0 20px 45px rgba(0,0,0,0.45);
        }
        .tpl-split h1 {
            font-size: 2.2rem;
            margin-top: 0;
            margin-bottom: 0.75rem;
            line-height: 1.2;
        }
        .tpl-split .body-content {
            font-size: 1.05rem;
            line-height: 1.75;
            white-space: pre-line;
            margin-bottom: 2rem;
        }

        /* ============================================================
           TEMPLATE 3: GALLERY FOCUS (🖼️ Galería Destacada)
        ============================================================ */
        .tpl-gallery h1 {
            font-size: 2.4rem;
            text-align: center;
            margin-bottom: 0.5rem;
        }
        .tpl-gallery .update-meta {
            text-align: center;
        }
        .tpl-gallery .gallery-hero {
            display: grid;
            grid-template-columns: 2fr 1fr;
            grid-template-rows: repeat(2, 200px);
            gap: 15px;
            margin-bottom: 2.5rem;
        }
        @media (max-width: 600px) {
            .tpl-gallery .gallery-hero {
                grid-template-columns: 1fr;
                grid-template-rows: repeat(3, 200px);
            }
        }
        .tpl-gallery .gallery-hero-item {
            border-radius: 14px;
            overflow: hidden;
            border: 2px solid rgba(255, 127, 0, 0.3);
            cursor: zoom-in;
            background: var(--theme-surface-hover, #1a1a1a);
            box-shadow: 0 0 25px rgba(255, 127, 0, 0.12), 0 8px 24px rgba(0,0,0,0.45);
            transition: transform 0.3s ease, border-color 0.3s, box-shadow 0.3s;
        }
        .tpl-gallery .gallery-hero-item:hover {
            transform: translateY(-2px);
            border-color: rgba(255, 127, 0, 0.6);
            box-shadow: 0 0 35px rgba(255, 127, 0, 0.28), 0 12px 30px rgba(0,0,0,0.55);
        }
        .tpl-gallery .gallery-hero-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .tpl-gallery .hero-main {
            grid-row: span 2;
        }
        .tpl-gallery .body-content {
            font-size: 1.08rem;
            line-height: 1.75;
            max-width: 800px;
            margin: 0 auto 2.5rem;
            white-space: pre-line;
        }

        /* ============================================================
           TEMPLATE 4: MINIMAL PREMIUM (✨ Minimalista Card)
        ============================================================ */
        .tpl-minimal {
            max-width: 780px;
            margin: 0 auto;
        }
        .tpl-minimal .glass-card {
            background: rgba(22, 22, 26, 0.72);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border: 2px solid rgba(255, 127, 0, 0.3);
            border-radius: 24px;
            padding: 2.5rem;
            box-shadow: 0 0 30px rgba(255, 127, 0, 0.15), 0 20px 50px rgba(0,0,0,0.4);
            margin-bottom: 2rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .tpl-minimal .glass-card:hover {
            border-color: rgba(255, 127, 0, 0.55);
            box-shadow: 0 0 45px rgba(255, 127, 0, 0.28), 0 25px 55px rgba(0,0,0,0.5);
        }
        :root[data-theme="light"] .tpl-minimal .glass-card {
            background: rgba(0, 0, 0, 0.02);
            border-color: rgba(0, 0, 0, 0.08);
            box-shadow: 0 20px 50px rgba(0,0,0,0.06);
        }
        .tpl-minimal h1 {
            font-size: 2.15rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin-bottom: 1rem;
        }
        .tpl-minimal .minimal-cover {
            width: 100%;
            height: auto;
            max-height: 420px;
            object-fit: contain;
            background: #0c0c0f;
            border-radius: 20px;
            margin-bottom: 2rem;
            border: 2px solid rgba(255, 127, 0, 0.35);
            box-shadow: 0 0 30px rgba(255, 127, 0, 0.18), 0 15px 35px rgba(0,0,0,0.5);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .tpl-minimal .minimal-cover:hover {
            border-color: rgba(255, 127, 0, 0.65);
            box-shadow: 0 0 40px rgba(255, 127, 0, 0.35), 0 20px 45px rgba(0,0,0,0.6);
        }
        .tpl-minimal .body-content {
            font-size: 1.05rem;
            line-height: 1.8;
            letter-spacing: 0.01em;
            white-space: pre-line;
            margin-bottom: 2rem;
            color: var(--theme-text-primary, #ffffff);
        }
        :root[data-theme="light"] .tpl-minimal .body-content {
            color: #111;
        }

        /* ============================================================
           TEMPLATE 5: MAGAZINE / EDITORIAL (📰 Revista)
        ============================================================ */
        .tpl-magazine {
            max-width: 900px;
            margin: 0 auto;
        }
        .tpl-magazine .magazine-header {
            position: relative;
            border-radius: 18px;
            overflow: hidden;
            height: 380px;
            margin-bottom: 2.5rem;
            display: flex;
            align-items: flex-end;
            border: 2px solid rgba(255, 127, 0, 0.35);
            box-shadow: 0 0 30px rgba(255, 127, 0, 0.18), 0 15px 35px rgba(0,0,0,0.5);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .tpl-magazine .magazine-header:hover {
            border-color: rgba(255, 127, 0, 0.65);
            box-shadow: 0 0 40px rgba(255, 127, 0, 0.35), 0 20px 45px rgba(0,0,0,0.6);
        }
        .tpl-magazine .magazine-header .header-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 1;
        }
        .tpl-magazine .magazine-header .header-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to top, rgba(0,0,0,0.92) 0%, rgba(0,0,0,0.4) 60%, rgba(0,0,0,0.1) 100%);
            z-index: 2;
        }
        .tpl-magazine .magazine-header .header-text {
            position: relative;
            z-index: 3;
            padding: 2.5rem;
            width: 100%;
        }
        .tpl-magazine .magazine-header h1 {
            font-size: 2.5rem;
            color: #fff;
            margin: 0 0 0.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
            font-family: 'Outfit', 'Georgia', serif;
            font-weight: 700;
        }
        .tpl-magazine .magazine-header .update-meta {
            color: #ccc;
            margin-bottom: 0;
        }
        .tpl-magazine .body-content {
            font-size: 1.1rem;
            line-height: 1.8;
            font-family: 'Inter', Georgia, serif;
            white-space: pre-line;
            margin-bottom: 2rem;
        }
        .tpl-magazine .body-content::first-letter {
            font-size: 3.5rem;
            font-weight: bold;
            float: left;
            margin-right: 10px;
            line-height: 0.85;
            color: var(--theme-accent, #ff7f00);
            font-family: 'Outfit', sans-serif;
            margin-top: 5px;
        }
    </style>
</head>
<body class="catalog-minimal" data-client-code="PUBLICO" data-client-number="0">
    <!-- HEADER -->
    <header>
        <div class="header-content">
            <a href="index.php" class="logo"><img src="img/logo_truper.1.1.png" alt="Truper" style="height: 40px; width: auto; object-fit: contain;"></a>
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
            <div class="tpl-split">
                <div class="split-sidebar">
                    <?php if (!empty($update['image_url'])): ?>
                        <img class="sidebar-cover promo-image" src="<?php echo htmlspecialchars($update['image_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($update['title'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php endif; ?>
                    
                    <?php if (!empty($update['registration_url'])): ?>
                        <div class="registration-cta-box" style="margin-top: 0;">
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
                        <p class="lead-desc" style="font-size: 1.15rem; line-height: 1.65; color: var(--theme-accent, #ff7f00); margin-bottom: 1.25rem; font-style: italic; font-weight: 500; border-left: 3px solid var(--theme-accent, #ff7f00); padding-left: 1rem;"><?php echo htmlspecialchars($update['brief_description'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <hr class="premium-divider">
                    <?php endif; ?>
                    <div class="body-content"><?php echo htmlspecialchars($update['body'], ENT_QUOTES, 'UTF-8'); ?></div>

                    <?php if (!empty($galleryImages)): ?>
                        <hr class="premium-divider">
                        <h4 style="margin: 0 0 1rem; font-size: 1.1rem; font-weight: 700; color: var(--theme-accent, #ff7f00); display: flex; align-items: center; gap: 0.5rem;">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            Imágenes adicionales
                        </h4>
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
            <div class="tpl-gallery">
                <span class="update-badge <?php echo get_update_badge_class($update['update_type']); ?>" style="display:table; margin: 0 auto 1rem;"><?php echo get_update_label($update['update_type']); ?></span>
                <h1><?php echo htmlspecialchars($update['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <?php if ($formattedDate): ?>
                    <div class="update-meta">Publicado el <?php echo $formattedDate; ?></div>
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
                    <div class="gallery-hero">
                        <?php foreach ($heroImages as $index => $img): ?>
                            <div class="gallery-hero-item <?php echo $index === 0 ? 'hero-main' : ''; ?> promo-image-gallery">
                                <img class="promo-image" src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="Galería principal">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <hr class="premium-divider" style="max-width:800px; margin-left:auto; margin-right:auto;">

                <?php if (!empty($update['brief_description'])): ?>
                    <p class="lead-desc" style="font-size: 1.15rem; line-height: 1.65; color: var(--theme-accent, #ff7f00); margin-bottom: 1.25rem; margin-top: 0; font-style: italic; font-weight: 500; border-left: 3px solid var(--theme-accent, #ff7f00); padding-left: 1rem; max-width: 800px; margin-left: auto; margin-right: auto;"><?php echo htmlspecialchars($update['brief_description'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <hr class="premium-divider" style="max-width:800px; margin-left:auto; margin-right:auto;">
                <?php endif; ?>
                <div class="body-content"><?php echo htmlspecialchars($update['body'], ENT_QUOTES, 'UTF-8'); ?></div>

                <?php if (!empty($remainingGallery)): ?>
                    <hr class="premium-divider" style="max-width:800px; margin-left:auto; margin-right:auto;">
                    <h4 style="font-size: 1.1rem; font-weight: 700; color: var(--theme-accent, #ff7f00); text-align: center; max-width:800px; margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        Más fotos de la galería
                    </h4>
                    <div class="gallery-grid" style="max-width: 800px; margin-left: auto; margin-right: auto;">
                        <?php foreach ($remainingGallery as $img): ?>
                            <div class="gallery-item promo-image-gallery">
                                <img class="promo-image" src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="Galería secundaria">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($update['registration_url'])): ?>
                    <hr class="premium-divider" style="max-width:800px; margin-left:auto; margin-right:auto;">
                    <div class="registration-cta-box" style="max-width: 800px; margin: 2rem auto 0;">
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
            <div class="tpl-minimal">
                <div class="glass-card">
                    <span class="update-badge <?php echo get_update_badge_class($update['update_type']); ?>"><?php echo get_update_label($update['update_type']); ?></span>
                    <h1><?php echo htmlspecialchars($update['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                    <?php if ($formattedDate): ?>
                        <div class="update-meta">Publicado el <?php echo $formattedDate; ?></div>
                    <?php endif; ?>

                    <?php if (!empty($update['image_url'])): ?>
                        <img class="minimal-cover promo-image" src="<?php echo htmlspecialchars($update['image_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($update['title'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php endif; ?>

                    <hr class="premium-divider">

                    <?php if (!empty($update['brief_description'])): ?>
                        <p class="lead-desc" style="font-size: 1.15rem; line-height: 1.65; color: var(--theme-accent, #ff7f00); margin-bottom: 1.25rem; margin-top: 0; font-style: italic; font-weight: 500; border-left: 3px solid var(--theme-accent, #ff7f00); padding-left: 1rem;"><?php echo htmlspecialchars($update['brief_description'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <hr class="premium-divider">
                    <?php endif; ?>
                    <div class="body-content"><?php echo htmlspecialchars($update['body'], ENT_QUOTES, 'UTF-8'); ?></div>

                    <?php if (!empty($galleryImages)): ?>
                        <hr class="premium-divider">
                        <h4 style="margin: 0 0 1rem; font-size: 1.1rem; font-weight: 700; color: var(--theme-accent, #ff7f00); display: flex; align-items: center; gap: 0.5rem;">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            Galería Complementaria
                        </h4>
                        <div class="gallery-grid" style="grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));">
                            <?php foreach ($galleryImages as $img): ?>
                                <div class="gallery-item promo-image-gallery" style="aspect-ratio: 1/1; border-radius: 12px;">
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
                            <a href="<?php echo htmlspecialchars($update['registration_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-cta" style="border-radius: 8px;">
                                Enlace de Registro
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($tpl === 'magazine'): ?>
            <!-- TEMPLATE 5: MAGAZINE LAYOUT -->
            <div class="tpl-magazine">
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

                <hr class="premium-divider">

                <?php if (!empty($update['brief_description'])): ?>
                    <p class="lead-desc" style="font-size: 1.15rem; line-height: 1.65; color: var(--theme-accent, #ff7f00); margin-bottom: 1.25rem; margin-top: 0; font-style: italic; font-weight: 500; border-left: 3px solid var(--theme-accent, #ff7f00); padding-left: 1rem;"><?php echo htmlspecialchars($update['brief_description'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <hr class="premium-divider">
                <?php endif; ?>
                <div class="body-content"><?php echo htmlspecialchars($update['body'], ENT_QUOTES, 'UTF-8'); ?></div>

                <?php if (!empty($galleryImages)): ?>
                    <hr class="premium-divider">
                    <h4 style="font-family:'Outfit', sans-serif; font-size:1.35rem; font-weight: 800; color: var(--theme-accent, #ff7f00); margin: 0 0 1rem; display: flex; align-items: center; gap: 0.5rem;">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        Reportaje Fotográfico
                    </h4>
                    <div class="gallery-grid" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap:20px;">
                        <?php foreach ($galleryImages as $img): ?>
                            <div class="gallery-item promo-image-gallery" style="aspect-ratio: 16/10;">
                                <img class="promo-image" src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="Galería revista">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($update['registration_url'])): ?>
                    <hr class="premium-divider">
                    <div class="registration-cta-box" style="margin-top: 2rem; border-width: 2px;">
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

        <?php else: ?>
            <!-- TEMPLATE 1: CLASSIC LAYOUT (DEFAULT) -->
            <div class="tpl-classic">
                <span class="update-badge <?php echo get_update_badge_class($update['update_type']); ?>"><?php echo get_update_label($update['update_type']); ?></span>
                <h1><?php echo htmlspecialchars($update['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <?php if ($formattedDate): ?>
                    <div class="update-meta">Publicado el <?php echo $formattedDate; ?></div>
                <?php endif; ?>

                <?php if (!empty($update['image_url'])): ?>
                    <div class="cover-wrap">
                        <img class="promo-image" src="<?php echo htmlspecialchars($update['image_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($update['title'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                <?php endif; ?>

                <hr class="premium-divider">

                <?php if (!empty($update['brief_description'])): ?>
                    <p class="lead-desc" style="font-size: 1.15rem; line-height: 1.65; color: var(--theme-accent, #ff7f00); margin-bottom: 1.25rem; margin-top: 0; font-style: italic; font-weight: 500; border-left: 3px solid var(--theme-accent, #ff7f00); padding-left: 1rem;"><?php echo htmlspecialchars($update['brief_description'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <hr class="premium-divider">
                <?php endif; ?>
                <div class="body-content"><?php echo htmlspecialchars($update['body'], ENT_QUOTES, 'UTF-8'); ?></div>

                <?php if (!empty($galleryImages)): ?>
                    <hr class="premium-divider">
                    <h4 style="margin: 0 0 1rem; font-size: 1.1rem; font-weight: 700; color: var(--theme-accent, #ff7f00); display: flex; align-items: center; gap: 0.5rem;">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        Galería de fotos
                    </h4>
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
                    <div class="registration-cta-box" style="margin-top: 2rem;">
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
        <div class="footer-bottom">&copy; 2026 Truper Platform</div>
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
