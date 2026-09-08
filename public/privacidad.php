<?php
require_once '../config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/truper_logo2.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Política de Privacidad - Ferretería FOX</title>
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
        .badge-date { display: inline-block; background: rgba(34,197,94,0.15); color: #22c55e; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; margin-bottom: 1.5rem; }
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
            <span class="badge-date">Cumplimiento LFPDPPP • México</span>
            <h1>🔒 Aviso y Política de Privacidad</h1>
            <p>En cumplimiento con la <strong>Ley Federal de Protección de Datos Personales en Posesión de los Particulares (LFPDPPP)</strong> y su Reglamento, <strong>Ferretería FOX S.A. de C.V.</strong>, con domicilio comercial en Guadalajara, Jalisco, México, pone a su disposición el presente Aviso de Privacidad Integral.</p>

            <h2>1. Datos Personales Recabados</h2>
            <p>Para la debida prestación de nuestros servicios de comercio electrónico, entrega logística y facturación fiscal, recabamos los siguientes datos personales:</p>
            <ul>
                <li><strong>Identificación y Contacto:</strong> Nombre completo, correo electrónico, número telefónico fijo o celular.</li>
                <li><strong>Logística y Entrega:</strong> Calle, número exterior, número interior, colonia, municipio/alcaldía, estado, código postal y coordenadas geográficas de entrega seleccionadas en el mapa.</li>
                <li><strong>Información Fiscal (opcional):</strong> Registro Federal de Contribuyentes (RFC), Nombre o Razón Social, Régimen Fiscal y Código Postal Fiscal.</li>
                <li><strong>Datos de Pago:</strong> No almacenamos en nuestros servidores la numeración completa de su tarjeta bancaria ni código de seguridad CVV. Dicha información es procesada directamente a través de pasarelas bancarias certificadas bajo protocolos de cifrado TLS/SSL y tokenización PCI-DSS.</li>
            </ul>

            <h2>2. Finalidades del Tratamiento de Datos</h2>
            <p>Sus datos personales serán utilizados para las siguientes finalidades primarias y necesarias:</p>
            <ul>
                <li>Procesamiento, confirmación, empaque y envío de sus compras en línea.</li>
                <li>Emisión de comprobantes fiscales digitales (CFDI 4.0 ante el SAT) y notas de remisión.</li>
                <li>Notificaciones en tiempo real sobre el estatus de su pedido vía correo electrónico y mensajería oficial.</li>
                <li>Atención de garantías, devoluciones (RMA) y soporte técnico posventa.</li>
            </ul>

            <h2>3. Transferencia y Protección de Datos</h2>
            <p>Ferretería FOX <strong>no vende, renta ni transfiere sus datos personales a terceros</strong> para fines publicitarios ajenos. Únicamente se transfieren los datos estrictamente indispensables a:</p>
            <ul>
                <li>Empresas de logística y paquetería para realizar la entrega física de sus productos.</li>
                <li>Proveedores Autorizados de Certificación (PAC) del SAT para la emisión de timbrado fiscal.</li>
                <li>Instituciones bancarias para el procesamiento seguro de pagos con tarjeta de crédito o débito.</li>
            </ul>

            <h2>4. Ejercicio de Derechos ARCO</h2>
            <p>Usted tiene derecho a conocer qué datos personales tenemos de usted, para qué los utilizamos y las condiciones del uso que les damos (<strong>Acceso</strong>). Asimismo, es su derecho solicitar la corrección de su información personal en caso de que esté desactualizada, sea inexacta o incompleta (<strong>Rectificación</strong>); que la eliminemos de nuestros registros o bases de datos (<strong>Cancelación</strong>); así como oponerse al uso de sus datos personales para fines específicos (<strong>Oposición</strong>).</p>
            <p>Para ejercer cualquiera de sus Derechos ARCO, puede enviar una solicitud a nuestro departamento de privacidad al correo: <strong>privacidad@ferreteriafox.com</strong> o desde su panel de cliente en nuestro portal.</p>

            <h2>5. Modificaciones al Aviso de Privacidad</h2>
            <p>El presente aviso de privacidad puede sufrir modificaciones, cambios o actualizaciones derivadas de nuevos requerimientos legales o de nuestras prácticas de privacidad. Cualquier cambio sustancial será publicado en esta misma sección.</p>

            <div style="margin-top: 2.5rem; text-align: center;">
                <a href="checkout.php?mode=online" class="btn btn-primary" style="background: linear-gradient(135deg, #ff7f00, #ff5500); color: #fff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: 700; display: inline-block;">Entendido y Regresar al Checkout</a>
            </div>
        </div>
    </main>
</body>
</html>
