<?php
$dir = dirname(__DIR__) . '/public/css';
$files = glob($dir . '/*.css');

foreach ($files as $file) {
    $content = file_get_contents($file);
    echo "=== File: " . basename($file) . " ===\n";
    
    // Find lines matching 'header' and print them with context
    $lines = explode("\n", $content);
    foreach ($lines as $i => $line) {
        if (preg_match('/header\b/i', $line)) {
            // Print line number and line content
            echo "Line " . ($i + 1) . ": " . trim($line) . "\n";
            // Print next 5 lines
            for ($j = 1; $j <= 8; $j++) {
                if (isset($lines[$i + $j])) {
                    echo "  +" . $j . ": " . trim($lines[$i + $j]) . "\n";
                }
            }
            echo "---------------------------------\n";
        }
    }
}
