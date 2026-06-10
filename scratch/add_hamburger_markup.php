<?php
$dir = dirname(__DIR__) . '/public';
$files = glob($dir . '/*.php');

$markup = '            <button class="hamburger-btn" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            ';

foreach ($files as $file) {
    if (!is_file($file)) continue;
    $content = file_get_contents($file);
    
    // Check if it has nav-menu but lacks hamburger-btn
    if (strpos($content, 'class="nav-menu"') !== false && strpos($content, 'hamburger-btn') === false) {
        // Safe check for `<nav class="nav-menu">`
        if (strpos($content, '<nav class="nav-menu">') !== false) {
            $content = str_replace('<nav class="nav-menu">', $markup . '<nav class="nav-menu">', $content);
            file_put_contents($file, $content);
            echo "Added hamburger button markup to: " . basename($file) . "\n";
        } else {
            // Regex fallback just in case there are variations
            $pattern = '/<nav\s+class="nav-menu">/i';
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $markup . '<nav class="nav-menu">', $content);
                file_put_contents($file, $content);
                echo "Added hamburger button markup to (regex): " . basename($file) . "\n";
            } else {
                echo "Warning: Could not find nav-menu pattern in: " . basename($file) . "\n";
            }
        }
    }
}
echo "Done!\n";
