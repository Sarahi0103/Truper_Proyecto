<?php
/**
 * Test: AppLogger structured JSON logging
 */

require_once __DIR__ . '/../src/utils/AppLogger.php';

class LoggerTest {
    public static function run(): array {
        $results = ['name' => 'Structured Logging (AppLogger) Test', 'passed' => false, 'message' => ''];
        $testLogFile = __DIR__ . '/../logs/test_run.log';
        if (file_exists($testLogFile)) {
            @unlink($testLogFile);
        }

        try {
            // Set temporary env values
            putenv('LOG_PATH=logs/test_run.log');
            putenv('LOG_LEVEL=warning'); // filter out info/debug, keep warning/error
            
            // Force reset in AppLogger via reflection since it keeps a private static $logFile
            $ref = new ReflectionClass('AppLogger');
            $prop = $ref->getProperty('logFile');
            $prop->setAccessible(true);
            $prop->setValue(null, null); // reset so it re-reads environment

            // Write logs of different levels
            AppLogger::debug("Este log de debug debería ser ignorado.");
            AppLogger::info("Este log de info debería ser ignorado.");
            AppLogger::warning("Este es un log de warning.", ['meta' => 'test-warning']);
            AppLogger::error("Este es un log de error.", ['meta' => 'test-error']);

            if (!file_exists($testLogFile)) {
                $results['message'] = "El archivo de log temporal no se creó: $testLogFile";
                return $results;
            }

            $lines = file($testLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (count($lines) !== 2) {
                $results['message'] = "Se esperaban exactamente 2 líneas de log (warning y error). Encontradas: " . count($lines);
                return $results;
            }

            // Check warning entry
            $warningEntry = json_decode($lines[0], true);
            if (!$warningEntry || $warningEntry['level'] !== 'WARNING' || $warningEntry['message'] !== 'Este es un log de warning.' || ($warningEntry['context']['meta'] ?? '') !== 'test-warning') {
                $results['message'] = "La entrada WARNING no tiene el formato JSON estructurado correcto: " . $lines[0];
                return $results;
            }

            // Check error entry
            $errorEntry = json_decode($lines[1], true);
            if (!$errorEntry || $errorEntry['level'] !== 'ERROR' || $errorEntry['message'] !== 'Este es un log de error.' || ($errorEntry['context']['meta'] ?? '') !== 'test-error') {
                $results['message'] = "La entrada ERROR no tiene el formato JSON estructurado correcto: " . $lines[1];
                return $results;
            }

            $results['passed'] = true;
            $results['message'] = "Logs estructurados en formato JSON validados correctamente. Nivel de filtrado y estructura JSON correctos.";
        } catch (Exception $e) {
            $results['message'] = "Excepción capturada: " . $e->getMessage();
        } finally {
            // Clean up
            if (file_exists($testLogFile)) {
                @unlink($testLogFile);
            }
            // Restore default values
            putenv('LOG_PATH=logs/app.log');
            putenv('LOG_LEVEL=info');
            
            $ref = new ReflectionClass('AppLogger');
            $prop = $ref->getProperty('logFile');
            $prop->setAccessible(true);
            $prop->setValue(null, null);
        }
        return $results;
    }
}
