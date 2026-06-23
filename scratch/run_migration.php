<?php
require_once __DIR__ . '/../config/config.php';
try {
    $GLOBALS['pdo']->exec("ALTER TABLE homepage_updates ADD COLUMN IF NOT EXISTS brief_description TEXT DEFAULT ''");
    echo "Migration successful!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
