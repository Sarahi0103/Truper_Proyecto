
<?php
$file = 'database.sql';
if (file_exists($file)) {
    $contents = file_get_contents($file);
    $lines = explode("\n", $contents);
    foreach ($lines as $i => $line) {
        if (stripos($line, 'categories') !== false) {
            echo ($i + 1) . ": " . trim($line) . "\n";
        }
    }
} else {
    echo "database.sql does not exist\n";
}
