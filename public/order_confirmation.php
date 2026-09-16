<?php
/**
 * Nueva Interfaz de Confirmación y Procesamiento de Pedido en Tiempo Real
 * Truper Platform - Nivel Estatal / Nacional
 */
require_once '../config/config.php';
require_once '../src/utils/SatCatalogs.php';
require_once '../src/Services/ShippingTrackingService.php';

$folio = sanitize($_GET['folio'] ?? '');
if (empty($folio)) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT st.id, st.folio, st.customer_name, st.total_amount, st.issued_date, st.order_status, st.invoice_required, st.shipping_address_json, st.tax_regime_selected, st.cfdi_use FROM sales_tickets st WHERE st.folio = ? LIMIT 1");
$stmt->execute([$folio]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die('Pedido no encontrado.');
}

$addressData = json_decode($order['shipping_address_json'] ?? '[]', true) ?: [];
$addressStr = $addressData['address'] ?? 'Dirección de Entrega Registrada';
$cityStr = $addressData['city'] ?? 'México';
$cpStr = $addressData['postalCode'] ?? '';

// Obtener tracking si existe
$tracking = null;
$trackingService = new ShippingTrackingService($pdo);
$trackingData = $trackingService->getOrderTracking($order['id']);
if ($trackingData) {
    $tracking = [
        'carrier' => $trackingData['carrier'],
        'tracking_number' => $trackingData['tracking_number'],
        'estimated_delivery' => $trackingData['estimated_delivery'],
        'tracking_status' => $trackingData['tracking_status']
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Pedido Confirmado - Folio <?php echo htmlspecialchars($order['folio']); ?> - Ferretería FOX</title>
    <link rel="stylesheet" href="css/styles.css?v=4.1">
    <link rel="stylesheet" href="css/theme.css?v=4.1">
    <link rel="stylesheet" href="css/responsive-complete.css?v=5.0">
    <style>
        body { font-family: var(--theme-font, 'Outfit', 'Inter', sans-serif); color: #fff; background: #0b0b0e; margin: 0; padding: 0; }
        .conf-container { max-width: 920px; margin: 3rem auto; padding: 0 1.25rem; }
        .hero-card { background: linear-gradient(135deg, rgba(34,197,94,0.1), rgba(18,18,24,0.95)); border: 1px solid rgba(34,197,94,0.3); border-radius: 16px; padding: 2.5rem; text-align: center; margin-bottom: 2rem; box-shadow: 0 15px 40px rgba(0,0,0,0.4); }
        .check-icon { width: 68px; height: 68px; background: #22c55e; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; box-shadow: 0 0 30px rgba(34,197,94,0.4); }
        .hero-title { font-size: 2.1rem; font-weight: 800; color: #fff; margin: 0 0 0.5rem; letter-spacing: -0.02em; }
        .hero-subtitle { color: #aaaab8; font-size: 0.98rem; margin: 0; }
        .folio-pill { display: inline-block; background: #14141a; border: 1px solid #2e2e3a; padding: 8px 20px; border-radius: 30px; font-family: monospace; font-size: 1.2rem; font-weight: 700; color: var(--theme-accent, #ff7f00); margin-top: 1.25rem; letter-spacing: 0.05em; }
        
        .timeline-section { background: #121217; border: 1px solid #22222a; border-radius: 16px; padding: 1.75rem; margin-bottom: 2rem; }
        .timeline-title { font-size: 1.1rem; font-weight: 700; color: #fff; margin-bottom: 1.5rem; border-bottom: 1px solid #22222a; padding-bottom: 0.75rem; text-transform: uppercase; letter-spacing: 0.04em; }
        .stepper { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; text-align: center; }
        .step-icon { width: 42px; height: 42px; border-radius: 50%; background: #181820; border: 2px solid #2e2e3a; color: #888; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.6rem; font-weight: 700; }
        .step.active .step-icon { background: var(--theme-accent, #ff7f00); border-color: var(--theme-accent, #ff7f00); color: #fff; box-shadow: 0 0 15px rgba(255,127,0,0.4); }
        .step.done .step-icon { background: #22c55e; border-color: #22c55e; color: #fff; }
        .step-label { font-size: 0.82rem; font-weight: 600; color: #888899; }
        .step.active .step-label { color: #fff; }

        .details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
        .detail-card { background: #121217; border: 1px solid #22222a; border-radius: 12px; padding: 1.35rem; }
        .detail-title { font-size: 0.95rem; font-weight: 700; color: var(--theme-accent, #ff7f00); margin-bottom: 0.85rem; border-bottom: 1px solid #22222a; padding-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.04em; }

        .btn-action { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; font-weight: 700; padding: 12px 24px; border-radius: 8px; text-decoration: none; transition: all 0.2s ease; }
        .btn-primary-action { background: var(--theme-accent, #ff7f00); color: #fff; }
        .btn-secondary-action { background: #1e1e26; color: #fff; border: 1px solid #333342; }

        @media (max-width: 600px) { .details-grid { grid-template-columns: 1fr; } .stepper { grid-template-columns: 1fr 1fr; gap: 1.5rem; } }
    </style>
</head>
<body>
    <div class="conf-container">
        <!-- Hero Card -->
        <div class="hero-card">
            <div class="check-icon">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
            </div>
            <h1 class="hero-title">Pedido Confirmado y Registrado</h1>
            <p class="hero-subtitle">Hemos recibido tu compra y la validación de pago en tiempo real. Tu mercancía está siendo procesada en almacén.</p>
            <div class="folio-pill">Folio Único: <?php echo htmlspecialchars($order['folio']); ?></div>
            
            <!-- Botones Destacados de Descarga de Ticket -->
            <div style="margin-top: 1.5rem; display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">
                <a href="/ticket_client.php?folio=<?php echo urlencode($order['folio']); ?>&auto_pdf=1" target="_blank" id="btnHeroDownloadTicket" style="background: linear-gradient(135deg, #ff7f00, #e05c00); color: #fff; padding: 12px 26px; border-radius: 30px; font-weight: 800; font-size: 1rem; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 6px 22px rgba(255, 127, 0, 0.45); transition: transform 0.2s;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    🎟️ Descargar Ticket de Compra (PDF)
                </a>
                <a href="/ticket_client.php?folio=<?php echo urlencode($order['folio']); ?>" target="_blank" style="background: #181820; border: 1px solid rgba(255,255,255,0.2); color: #fff; padding: 12px 20px; border-radius: 30px; font-weight: 700; font-size: 0.95rem; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                    🖨️ Ver / Imprimir Ticket
                </a>
            </div>
            <div id="autoDownloadNotice" style="margin-top: 0.9rem; font-size: 0.85rem; color: #4ade80; display: inline-flex; align-items: center; gap: 6px;">
                <span>⚡ Tu ticket se está descargando automáticamente...</span>
            </div>
        </div>

        <!-- Timeline Stepper -->
        <div class="timeline-section">
            <div class="timeline-title">Estado del Envío y Procesamiento</div>
            <div class="stepper">
                <div class="step done">
                    <div class="step-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    </div>
                    <div class="step-label">Pago Validado</div>
                </div>
                <div class="step active">
                    <div class="step-icon">2</div>
                    <div class="step-label">En Preparación</div>
                </div>
                <div class="step">
                    <div class="step-icon">3</div>
                    <div class="step-label">Empacado Ciego</div>
                </div>
                <div class="step">
                    <div class="step-icon">4</div>
                    <div class="step-label">En Ruta de Entrega</div>
                </div>
            </div>
        </div>

        <!-- Details Grid -->
        <div class="details-grid">
            <div class="detail-card">
                <div class="detail-title">Datos de Entrega</div>
                <div style="font-size:0.92rem; line-height:1.6; color:#ccc;">
                    <strong>Destinatario:</strong> <?php echo htmlspecialchars($order['customer_name']); ?><br>
                    <strong>Dirección:</strong> <?php echo htmlspecialchars($addressStr); ?><br>
                    <strong>Ubicación:</strong> <?php echo htmlspecialchars($cityStr . ($cpStr ? " (C.P. {$cpStr})" : '')); ?><br>
                    <small style="color:#4ade80; display:block; margin-top:0.6rem; font-weight:600;">🔒 Garantía de Empaque Ciego: Tu paquete no mostrará contenidos ni costos en su exterior.</small>
                </div>
            </div>

            <div class="detail-card">
                <div class="detail-title">Resumen de Comprobante</div>
                <div style="font-size:0.92rem; line-height:1.6; color:#ccc;">
                    <strong>Monto Total:</strong> $<?php echo number_format((float)$order['total_amount'], 2); ?> MXN<br>
                    <strong>Tipo de Emisión:</strong> <?php echo $order['invoice_required'] ? '<span style="color:#4ade80; font-weight:700;">Factura Fiscal CFDI 4.0</span>' : 'Nota de Venta General'; ?><br>
                    <strong>Fecha de Emisión:</strong> <?php echo substr((string)$order['issued_date'], 0, 16); ?><br>
                    
                    <div style="margin-top:1rem; display:flex; gap:0.5rem; flex-wrap:wrap;">
                        <a href="/ticket_client.php?folio=<?php echo urlencode($order['folio']); ?>&auto_pdf=1" target="_blank" style="padding:7px 14px; background:linear-gradient(135deg, #ff7f00, #ff5500); color:#fff; border-radius:6px; font-weight:800; font-size:0.84rem; text-decoration:none; display:inline-flex; align-items:center; gap:5px; box-shadow: 0 3px 10px rgba(255,127,0,0.3);">🎟️ Ticket PDF</a>
                        <a href="/ticket_client.php?folio=<?php echo urlencode($order['folio']); ?>" target="_blank" style="padding:7px 12px; background:#1e1e28; border:1px solid #333345; color:#fff; border-radius:6px; font-weight:600; font-size:0.84rem; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">🖨️ Ver Ticket</a>
                        <?php if ($order['invoice_required']): ?>
                        <a href="/api/invoice.php?action=download_pdf&folio=<?php echo urlencode($order['folio']); ?>" target="_blank" style="padding:7px 12px; background:#2563eb; color:#fff; border-radius:6px; font-weight:700; font-size:0.84rem; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">📕 Factura PDF</a>
                        <a href="/api/invoice.php?action=download_xml&folio=<?php echo urlencode($order['folio']); ?>" target="_blank" style="padding:7px 12px; background:#222; border:1px solid #444; color:#fff; border-radius:6px; font-weight:600; font-size:0.84rem; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">📄 XML SAT</a>
                        <button onclick="promptSendInvoiceEmail('<?php echo htmlspecialchars($order['folio']); ?>')" style="padding:7px 12px; background:#334155; color:#fff; border:none; border-radius:6px; font-weight:600; font-size:0.84rem; cursor:pointer; display:inline-flex; align-items:center; gap:4px;">✉️ Enviar a Correo</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tracking Info (si existe) -->
        <?php if ($tracking): ?>
        <div class="timeline-section" style="background: linear-gradient(135deg, rgba(59,130,246,0.1), rgba(18,18,24,0.95)); border-color: rgba(59,130,246,0.3);">
            <div class="timeline-title" style="color:#3b82f6;">📦 Información de Envío</div>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem;">
                <div>
                    <div style="font-size:0.8rem; color:#888899; margin-bottom:0.3rem;">Paquetería</div>
                    <div style="font-size:1rem; font-weight:700; color:#fff;"><?php echo htmlspecialchars($tracking['carrier']); ?></div>
                </div>
                <div>
                    <div style="font-size:0.8rem; color:#888899; margin-bottom:0.3rem;">Número de Guía</div>
                    <div style="font-size:1rem; font-weight:700; color:#ff7f00; font-family:monospace;"><?php echo htmlspecialchars($tracking['tracking_number']); ?></div>
                </div>
                <?php if ($tracking['estimated_delivery']): ?>
                <div>
                    <div style="font-size:0.8rem; color:#888899; margin-bottom:0.3rem;">Entrega Estimada</div>
                    <div style="font-size:1rem; font-weight:700; color:#fff;"><?php echo date('d/m/Y', strtotime($tracking['estimated_delivery'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div style="display:flex; gap:1rem; justify-content:center; flex-wrap:wrap;">
            <a href="order_tracking.php" class="btn-action btn-primary-action">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                Consultar Seguimiento de Pedidos
            </a>
            <a href="index.php" class="btn-action btn-secondary-action">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                Seguir Comprando
            </a>
        </div>
    </div>

    <script src="js/modals.js"></script>
    <script>
    function promptSendInvoiceEmail(folio) {
        showPrompt("Enviar Factura Fiscal", "Ingresa el correo electrónico para recibir los archivos PDF y XML de tu factura:", "", (email) => {
            if (!email || !email.trim()) return;

            fetch('/api/invoice.php?action=send_email&folio=' + encodeURIComponent(folio), {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'email=' + encodeURIComponent(email.trim())
            })
            .then(r => r.json())
            .then(data => {
                showAlert(data.message || 'Factura enviada exitosamente', 'success');
            })
            .catch(err => {
                showAlert('Error al enviar la factura: ' + err.message, 'error');
            });
        });
    }

    // Auto-descarga suave del ticket en PDF al cargar la confirmación
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            const frame = document.createElement('iframe');
            frame.style.display = 'none';
            frame.src = '/ticket_client.php?folio=<?php echo urlencode($order['folio']); ?>&auto_pdf=1';
            document.body.appendChild(frame);
        }, 600);

        setTimeout(() => {
            const notice = document.getElementById('autoDownloadNotice');
            if (notice) {
                notice.innerHTML = '<span style="color:#94a3b8;">✅ ¿Necesitas otra copia? Usa el botón naranja de arriba.</span>';
            }
        }, 4500);
    });
    </script>
</body>
</html>
