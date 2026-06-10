<?php
$dir = dirname(__DIR__) . '/public';
$adminDir = dirname(__DIR__) . '/admin';

$files = glob($dir . '/*.php');
$adminFiles = glob($adminDir . '/*.php');
$allFiles = array_merge($files, $adminFiles);

$replacements = [
    '/css\/responsive-complete\.css(\?v=[0-9.]+)?/' => 'css/responsive-complete.css?v=4.0',
    '/css\/styles\.css(\?v=[0-9.]+)?/' => 'css/styles.css?v=4.0',
    '/css\/theme\.css(\?v=[0-9.]+)?/' => 'css/theme.css?v=4.0',
    '/\/assets\/css\/style\.css(\?v=[0-9.]+)?/' => '/assets/css/style.css?v=4.0',
    '/\/assets\/css\/dashboard\.css(\?v=[0-9.]+)?/' => '/assets/css/dashboard.css?v=4.0',
    '/\/assets\/css\/responsive\.css(\?v=[0-9.]+)?/' => '/assets/css/responsive.css?v=4.0',
];

foreach ($allFiles as $file) {
    if (!is_file($file)) continue;
    $content = file_get_contents($file);
    $original = $content;
    foreach ($replacements as $pattern => $replacement) {
        $content = preg_replace($pattern, $replacement, $content);
    }
    if ($content !== $original) {
        file_put_contents($file, $content);
        echo "Updated CSS versions in: " . basename($file) . "\n";
    }
}
echo "Done!\n";
