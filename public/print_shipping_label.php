<?php
/**
 * Módulo de Impresión de Etiquetas Ciegas de Empaque
 * Genera etiquetas con código de barras y folio de pedido
 * Sin información sensible visible (privacidad)
 */

require_once '../config/config.php';
require_login();

$orderFolio = $_GET['folio'] ?? '';
$orderId = null;

if (empty($orderFolio)) {
    die('Error: Folio de pedido requerido');
}

try {
    // Obtener información del pedido
    $stmt = $pdo->prepare("SELECT id, order_number, client_id, status FROM sales_tickets WHERE order_number = ? OR id = ?");
    $stmt->execute([$orderFolio, $orderFolio]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        die('Error: Pedido no encontrado');
    }
    
    $orderId = $order['id'];
    $orderNumber = $order['order_number'];
    
    // Verificar permisos (solo admin/employee pueden imprimir)
    $userRole = $_SESSION['role'] ?? '';
    if (!in_array($userRole, ['admin', 'employee'])) {
        die('Error: No tienes permisos para imprimir etiquetas');
    }
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Etiqueta de Empaque - <?php echo htmlspecialchars($orderNumber); ?></title>
    <style>
        @page {
            size: 4in 6in;
            margin: 0;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: 'Arial', sans-serif;
            font-size: 12px;
        }
        
        .label-container {
            width: 4in;
            height: 6in;
            padding: 0.25in;
            box-sizing: border-box;
            border: 2px solid #000;
            display: flex;
            flex-direction: column;
            background: white;
        }
        
        .label-header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 0.2in;
            margin-bottom: 0.2in;
        }
        
        .label-header h1 {
            margin: 0;
            font-size: 18px;
            font-weight: bold;
            color: #ff7f00;
        }
        
        .label-header p {
            margin: 5px 0 0 0;
            font-size: 10px;
        }
        
        .barcode-section {
            text-align: center;
            margin: 0.3in 0;
            padding: 0.2in;
            background: #f5f5f5;
            border: 1px solid #ddd;
        }
        
        .barcode {
            font-family: 'Libre Barcode 39', 'Code 39', monospace;
            font-size: 48px;
            letter-spacing: 2px;
            font-weight: bold;
        }
        
        .folio-number {
            font-size: 14px;
            font-weight: bold;
            margin-top: 0.1in;
            letter-spacing: 3px;
        }
        
        .info-section {
            margin: 0.2in 0;
            font-size: 11px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 0.1in 0;
            border-bottom: 1px dashed #ccc;
            padding-bottom: 0.05in;
        }
        
        .info-label {
            font-weight: bold;
            color: #666;
        }
        
        .info-value {
            font-weight: bold;
        }
        
        .warning-section {
            margin-top: auto;
            padding: 0.15in;
            background: #fff3cd;
            border: 1px solid #ffc107;
            text-align: center;
            font-size: 10px;
            font-weight: bold;
            color: #856404;
        }
        
        .footer {
            text-align: center;
            font-size: 9px;
            color: #999;
            margin-top: 0.1in;
        }
        
        @media print {
            body {
                margin: 0;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="padding: 20px; text-align: center; background: #f5f5f5;">
        <h2>Etiqueta de Empaque</h2>
        <p>Folio: <?php echo htmlspecialchars($orderNumber); ?></p>
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; cursor: pointer; margin: 10px;">🖨️ Imprimir Etiqueta</button>
        <button onclick="window.close()" style="padding: 10px 20px; font-size: 16px; cursor: pointer; margin: 10px;">❌ Cerrar</button>
    </div>
    
    <div class="label-container">
        <div class="label-header">
            <h1>FERRETERÍA FOX</h1>
            <p>Etiqueta de Empaque - Confidencial</p>
        </div>
        
        <div class="barcode-section">
            <div class="barcode">*<?php echo str_replace('-', '', $orderNumber); ?>*</div>
            <div class="folio-number">FOLIO: <?php echo htmlspecialchars($orderNumber); ?></div>
        </div>
        
        <div class="info-section">
            <div class="info-row">
                <span class="info-label">FECHA:</span>
                <span class="info-value"><?php echo date('d/m/Y'); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">HORA:</span>
                <span class="info-value"><?php echo date('H:i'); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">TIPO:</span>
                <span class="info-value">ENTREGA DOMICILIO</span>
            </div>
            <div class="info-row">
                <span class="info-label">PRIORIDAD:</span>
                <span class="info-value">NORMAL</span>
            </div>
        </div>
        
        <div class="warning-section">
            ⚠️ ESTE PAQUETE CONTIENE INFORMACIÓN CONFIDENCIAL
            <br>
            NO ABRIR SIN AUTORIZACIÓN DEL DESTINATARIO
        </div>
        
        <div class="footer">
            Código: <?php echo strtoupper(bin2hex(random_bytes(4))); ?> | Escaneado: <?php echo date('Y-m-d H:i:s'); ?>
        </div>
    </div>
    
    <script>
    // Auto-print cuando carga la página (opcional)
    // window.onload = function() {
    //     window.print();
    // };
    </script>
</body>
</html>
