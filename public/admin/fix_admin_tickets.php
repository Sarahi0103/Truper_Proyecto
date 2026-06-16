<?php
/**
 * Script temporal de corrección de datos históricos
 * Corrige tickets creados por admin que tienen nombre de cliente incorrecto
 * 
 * IMPORTANTE: Eliminar este archivo después de ejecutarlo
 */

require_once '../../config/config.php';

// Solo admins pueden ejecutar esto
require_admin();

header('Content-Type: text/html; charset=utf-8');

$action = $_POST['action'] ?? 'preview';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Fix: Tickets de Admin</title>
    <style>
        body { font-family: monospace; background: #111; color: #eee; padding: 30px; }
        h1 { color: #f90; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th { background: #333; padding: 10px; text-align: left; }
        td { padding: 8px 10px; border-bottom: 1px solid #333; }
        .badge-admin { background: #f90; color: #000; padding: 2px 8px; border-radius: 4px; font-size: 12px; }
        .badge-client { background: #0f9; color: #000; padding: 2px 8px; border-radius: 4px; font-size: 12px; }
        .btn { padding: 12px 28px; border: none; border-radius: 6px; cursor: pointer; font-size: 15px; margin: 5px; }
        .btn-danger { background: #e33; color: #fff; }
        .btn-safe { background: #363; color: #fff; }
        .alert { padding: 15px; border-radius: 6px; margin: 15px 0; }
        .alert-warn { background: #553300; border: 1px solid #f90; }
        .alert-ok { background: #003300; border: 1px solid #0f9; }
        .alert-err { background: #330000; border: 1px solid #e33; }
        pre { background: #222; padding: 15px; border-radius: 6px; overflow-x: auto; }
    </style>
</head>
<body>
<h1>🔧 Corrección: Tickets creados por Admin</h1>

<?php if ($action === 'preview'): ?>
<div class="alert alert-warn">
    ⚠️ <strong>PASO 1 — Vista previa</strong>: Tickets que serán actualizados (customer_name → "Admin")
</div>

<?php
try {
    // Verificar cuántos tickets fueron creados por admin pero tienen otro nombre
    $stmt = $pdo->prepare("
        SELECT 
            st.id,
            st.folio,
            st.customer_name AS nombre_actual,
            st.issued_date,
            u.role,
            u.first_name || ' ' || COALESCE(u.last_name, '') AS issued_by_name
        FROM sales_tickets st
        JOIN users u ON st.issued_by = u.id
        WHERE u.role = 'admin'
          AND (st.customer_name IS NULL OR st.customer_name != 'Admin')
          AND st.deleted_at IS NULL
        ORDER BY st.issued_date DESC
    ");
    $stmt->execute();
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo '<p><strong>Tickets encontrados para corregir: ' . count($tickets) . '</strong></p>';
    
    if (count($tickets) > 0):
?>
<table>
    <tr>
        <th>Folio</th>
        <th>Nombre Actual</th>
        <th>Creado por (Admin)</th>
        <th>Fecha</th>
        <th>Será cambiado a</th>
    </tr>
    <?php foreach ($tickets as $t): ?>
    <tr>
        <td><?= htmlspecialchars($t['folio']) ?></td>
        <td><?= htmlspecialchars($t['nombre_actual'] ?? 'NULL') ?></td>
        <td><span class="badge-admin"><?= htmlspecialchars($t['issued_by_name']) ?></span></td>
        <td><?= htmlspecialchars(substr($t['issued_date'], 0, 10)) ?></td>
        <td><span class="badge-admin">Admin</span></td>
    </tr>
    <?php endforeach; ?>
</table>

<form method="POST">
    <input type="hidden" name="action" value="execute">
    <button type="submit" class="btn btn-danger" onclick="return confirm('¿Confirmas que quieres actualizar <?= count($tickets) ?> tickets?')">
        ✅ Ejecutar corrección (<?= count($tickets) ?> tickets)
    </button>
    <a href="?" class="btn btn-safe">🔄 Recargar vista previa</a>
</form>
<?php else: ?>
<div class="alert alert-ok">✅ No hay tickets que corregir. Todo está en orden.</div>
<?php endif; ?>

<?php
} catch (Exception $e) {
    echo '<div class="alert alert-err">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>

<?php elseif ($action === 'execute'): ?>
<div class="alert alert-warn">⚙️ <strong>Ejecutando corrección...</strong></div>

<?php
try {
    $pdo->beginTransaction();
    
    // Primero, contar cuántos se van a actualizar
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM sales_tickets st
        JOIN users u ON st.issued_by = u.id
        WHERE u.role = 'admin'
          AND (st.customer_name IS NULL OR st.customer_name != 'Admin')
          AND st.deleted_at IS NULL
    ");
    $countStmt->execute();
    $total = $countStmt->fetchColumn();
    
    // Obtener folios afectados para el log
    $listStmt = $pdo->prepare("
        SELECT st.folio, st.customer_name
        FROM sales_tickets st
        JOIN users u ON st.issued_by = u.id
        WHERE u.role = 'admin'
          AND (st.customer_name IS NULL OR st.customer_name != 'Admin')
          AND st.deleted_at IS NULL
    ");
    $listStmt->execute();
    $affected = $listStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ejecutar el UPDATE
    $updateStmt = $pdo->prepare("
        UPDATE sales_tickets 
        SET customer_name = 'Admin',
            updated_at = NOW()
        WHERE issued_by IN (
            SELECT id FROM users WHERE role = 'admin'
        )
        AND (customer_name IS NULL OR customer_name != 'Admin')
        AND deleted_at IS NULL
    ");
    $updateStmt->execute();
    $rowsUpdated = $updateStmt->rowCount();
    
    $pdo->commit();
    
    echo '<div class="alert alert-ok">✅ <strong>Corrección completada: ' . $rowsUpdated . ' tickets actualizados</strong></div>';
    echo '<h3>Tickets corregidos:</h3>';
    echo '<pre>';
    foreach ($affected as $t) {
        echo htmlspecialchars($t['folio']) . '  →  "' . htmlspecialchars($t['customer_name'] ?? 'NULL') . '"  →  "Admin"' . "\n";
    }
    echo '</pre>';
    echo '<div class="alert alert-warn">⚠️ <strong>¡IMPORTANTE!</strong> Elimina este archivo una vez que hayas verificado los cambios:<br><code>public/admin/fix_admin_tickets.php</code></div>';
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo '<div class="alert alert-err">❌ Error durante la corrección: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>

<a href="?" class="btn btn-safe">🔍 Ver estado actual</a>

<?php endif; ?>

<hr style="margin-top: 40px; border-color: #444;">
<p style="color:#666; font-size:12px;">Script temporal — eliminar después de usar: <code>public/admin/fix_admin_tickets.php</code></p>
</body>
</html>
