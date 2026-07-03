<?php
/**
 * TRUPER PLATFORM - Master Test Runner
 */

require_once __DIR__ . '/DatabaseTest.php';
require_once __DIR__ . '/LoggerTest.php';
require_once __DIR__ . '/ImageCompressionTest.php';
require_once __DIR__ . '/ZipUploadTest.php';

echo "============================================================\n";
echo "🧪 EJECUTANDO SUITE DE PRUEBAS DE OPTIMIZACIÓN - TRUPER PLATFORM\n";
echo "============================================================\n\n";

$tests = [
    'DatabaseTest',
    'LoggerTest',
    'ImageCompressionTest',
    'ZipUploadTest'
];

$allPassed = true;
$passedCount = 0;
$totalCount = count($tests);

foreach ($tests as $testClass) {
    echo "• Ejecutando: $testClass...\n";
    $result = $testClass::run();
    
    if ($result['passed']) {
        echo "  🟢 [APROBADA] - " . $result['name'] . "\n";
        echo "    Detalle: " . $result['message'] . "\n\n";
        $passedCount++;
    } else {
        echo "  🔴 [FALLIDA]  - " . $result['name'] . "\n";
        echo "    Error:   " . $result['message'] . "\n\n";
        $allPassed = false;
    }
}

echo "============================================================\n";
echo "📊 RESUMEN DE RESULTADOS\n";
echo "============================================================\n";
echo "Total de pruebas: $totalCount\n";
echo "Aprobadas:        $passedCount\n";
echo "Fallidas:         " . ($totalCount - $passedCount) . "\n";
echo "============================================================\n";

if ($allPassed) {
    echo "🟢 SUITE APROBADA: ¡Todas las pruebas pasaron con éxito!\n";
    exit(0);
} else {
    echo "🔴 SUITE FALLIDA: Algunas pruebas fallaron. Revisa el registro de errores.\n";
    exit(1);
}
