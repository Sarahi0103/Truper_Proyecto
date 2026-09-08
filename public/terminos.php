<?php
require_once '../config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Términos y Condiciones - Ferretería FOX</title>
    <link rel="stylesheet" href="css/styles.css?v=4.1">
    <link rel="stylesheet" href="css/theme.css?v=4.1">
    <link rel="stylesheet" href="css/responsive-complete.css?v=5.0">
    <style>
        body { font-family: var(--theme-font, 'Outfit', 'Inter', sans-serif); color: #e2e8f0; background: #0b0b0e; margin: 0; padding: 0; line-height: 1.6; }
        .legal-header { background: #121217; border-bottom: 1px solid rgba(255,127,0,0.25); padding: 1.25rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        .legal-container { max-width: 900px; margin: 2.5rem auto; padding: 0 1.5rem; }
        .legal-card { background: #15151b; border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 2.5rem; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        h1 { color: #ffffff; font-size: 2rem; margin-top: 0; display: flex; align-items: center; gap: 10px; }
        h2 { color: #ff7f00; font-size: 1.25rem; margin-top: 2rem; border-bottom: 1px solid rgba(255,127,0,0.2); padding-bottom: 6px; }
        p, li { color: #cbd5e1; font-size: 0.95rem; }
        ul { padding-left: 1.5rem; }
        .badge-date { display: inline-block; background: rgba(255,127,0,0.15); color: #ff9f43; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; margin-bottom: 1.5rem; }
        .back-link { color: #ff7f00; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <header class="legal-header">
        <a href="index.php" style="text-decoration:none; display:flex; align-items:center; gap:10px;">
            <img src="/truper_logo2.png" alt="Ferretería FOX" style="height:36px;">
            <span style="color:#fff; font-weight:800; font-size:1.2rem;">Ferretería FOX</span>
        </a>
        <a href="checkout.php?mode=online" class="back-link">← Volver al Checkout</a>
    </header>

    <main class="legal-container">
        <div class="legal-card">
            <span class="badge-date">Última actualización: Septiembre 2026 • México</span>
            <h1>📜 Términos y Condiciones de Uso y Venta</h1>
            <p>Bienvenido a <strong>Ferretería FOX</strong>. Al realizar una compra, crear una cuenta o navegar en nuestro portal web, aceptas plenamente los presentes Términos y Condiciones, los cuales rigen todas las operaciones comerciales, pagos y envíos en la República Mexicana.</p>

            <h2>1. Capacidad y Objeto de la Plataforma</h2>
            <p>El portal web de Ferretería FOX comercializa herramientas, maquinaria, material eléctrico, plomería, ferretería general y suministros industriales. Las ventas están dirigidas a personas físicas con capacidad legal y personas morales legalmente constituidas en los Estados Unidos Mexicanos.</p>

            <h2>2. Precios y Moneda</h2>
            <p>Todos los precios exhibidos en nuestro catálogo se encuentran expresados en <strong>Pesos Mexicanos (MXN)</strong> e incluyen el Impuesto al Valor Agregado (IVA) correspondiente al 16%, salvo que expresamente se indique lo contrario. Nos reservamos el derecho de modificar precios sin previo aviso antes de la confirmación formal del pedido.</p>

            <h2>3. Métodos de Pago y Financiamiento</h2>
            <ul>
                <li><strong>Tarjetas de Débito:</strong> Aceptadas Visa, Mastercard y Carnet. El cobro se procesa en una sola exhibición por el importe total exacto de la orden. No aplican promociones de financiamiento diferido.</li>
                <li><strong>Tarjetas de Crédito:</strong> Aceptadas Visa, Mastercard y American Express. El titular podrá optar por pago en una sola exhibición o diferir su compra a <strong>Meses Sin Intereses (3, 6 o 12 MSI)</strong> en montos y promociones participantes según el total del pedido.</li>
                <li><strong>Seguridad en Transacciones:</strong> No almacenamos números completos de tarjetas bancarias ni códigos CVV. Todos los pagos son tokenizados bajo estándares internacionales de seguridad bancaria PCI-DSS y autenticación 3D Secure 2.0.</li>
            </ul>

            <h2>4. Facturación Electrónica (CFDI 4.0 - SAT)</h2>
            <p>Conforme a las disposiciones del Servicio de Administración Tributaria (SAT), el cliente puede solicitar su Comprobante Fiscal Digital por Internet (CFDI) al momento de realizar su compra en el checkout o dentro del mes calendario de su pago:</p>
            <ul>
                <li>Es indispensable proporcionar RFC exacto, Razón Social, Régimen Fiscal y Código Postal del domicilio fiscal.</li>
                <li>En caso de no solicitar factura fiscal con RFC, se emitirá automáticamente la Nota de Venta correspondiente a la Factura Global del periodo (Público en General).</li>
            </ul>

            <h2>5. Envíos y Tiempos de Entrega</h2>
            <p>Los pedidos son preparados y despachados desde nuestros centros de distribución. Los tiempos estimados de entrega son:</p>
            <ul>
                <li><strong>Envío Estándar:</strong> De 3 a 7 días hábiles a nivel nacional.</li>
                <li><strong>Envío Express:</strong> De 1 a 3 días hábiles en zonas metropolitanas de cobertura directa.</li>
                <li><strong>Retiro en Tienda:</strong> Disponible para recolección en sucursal asignada en un lapso de 24 horas tras confirmación de existencias.</li>
            </ul>

            <h2>6. Garantías y Política de Devoluciones (RMA)</h2>
            <p>Todos nuestros productos cuentan con garantía oficial de fábrica y soporte de Ferretería FOX. En caso de defecto de fabricación o inconformidad:</p>
            <ul>
                <li>El cliente dispone de un plazo de 30 días naturales posteriores a la recepción para tramitar una solicitud de devolución o cambio a través del módulo de Soporte / RMA.</li>
                <li>El producto debe conservarse en su empaque original con todos sus accesorios, manuales y sin señales de maltrato por uso indebido.</li>
            </ul>

            <h2>7. Jurisdicción y Ley Aplicable</h2>
            <p>Para la resolución de cualquier controversia emanada de las operaciones comerciales reguladas por este contrato, las partes se someten a las leyes aplicables de la República Mexicana y a la competencia de los tribunales de la ciudad de Guadalajara, Jalisco, renunciando a cualquier otro fuero que pudiera corresponderles por razón de sus domicilios presentes o futuros.</p>

            <div style="margin-top: 2.5rem; text-align: center;">
                <a href="checkout.php?mode=online" class="btn btn-primary" style="background: linear-gradient(135deg, #ff7f00, #ff5500); color: #fff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: 700; display: inline-block;">Entendido y Regresar al Checkout</a>
            </div>
        </div>
    </main>
</body>
</html>
