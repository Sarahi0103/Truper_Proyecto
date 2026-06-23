<?php
require_once __DIR__ . '/../config/config.php';

echo "Running migrations for homepage_updates table...\n";

try {
    $pdo->exec("ALTER TABLE homepage_updates ADD COLUMN IF NOT EXISTS additional_images TEXT DEFAULT '[]'");
    echo "✓ Column 'additional_images' added (or already exists).\n";
} catch (Exception $e) {
    echo "✗ Error adding 'additional_images': " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE homepage_updates ADD COLUMN IF NOT EXISTS registration_url TEXT DEFAULT ''");
    echo "✓ Column 'registration_url' added (or already exists).\n";
} catch (Exception $e) {
    echo "✗ Error adding 'registration_url': " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE homepage_updates ADD COLUMN IF NOT EXISTS design_template VARCHAR(50) DEFAULT 'classic'");
    echo "✓ Column 'design_template' added (or already exists).\n";
} catch (Exception $e) {
    echo "✗ Error adding 'design_template': " . $e->getMessage() . "\n";
}

echo "Migration finished.\n";
