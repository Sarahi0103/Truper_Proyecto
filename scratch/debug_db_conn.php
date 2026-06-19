<?php
require_once __DIR__ . '/../config/database.php';

echo "Resolved Host: " . DB_HOST . "\n";
echo "Resolved Port: " . DB_PORT . "\n";
echo "Resolved DB Name: " . DB_NAME . "\n";
echo "Resolved User: " . DB_USER . "\n";
echo "Resolved Pass (first 3 chars): " . substr(DB_PASS, 0, 3) . "...\n";
echo "DATABASE_URL env: " . var_export(getenv('DATABASE_URL'), true) . "\n";
echo "INTERNAL_DATABASE_URL env: " . var_export(getenv('INTERNAL_DATABASE_URL'), true) . "\n";
