<?php
$content = file_get_contents('public/api/admin_supply.php');
$lines = explode("\n", $content);

$found = false;
foreach ($lines as $index => $line) {
    if (strpos($line, "case 'marketplace-delete'") !== false) {
        $found = $index + 1;
        break;
    }
}

if ($found) {
    echo "Found 'marketplace-delete' at line $found. Printing lines " . ($found - 5) . " to " . ($found + 25) . ":\n\n";
    for ($i = $found - 5; $i <= $found + 25; $i++) {
        if (isset($lines[$i - 1])) {
            echo $i . ": " . $lines[$i - 1] . "\n";
        }
    }
} else {
    echo "Could not find 'marketplace-delete' case statement.\n";
}
?>
