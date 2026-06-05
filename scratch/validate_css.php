<?php
$css = file_get_contents('c:/Users/ksgom/proyecto_Truper/public/css/analytics.css');

echo "--- CSS VALIDATOR ---\n";
// Check comments
$offset = 0;
$comments = 0;
$errors = 0;
while (($pos = strpos($css, '/*', $offset)) !== false) {
    $end = strpos($css, '*/', $pos);
    if ($end === false) {
        echo "Error: Unclosed comment starting at position $pos.\n";
        $errors++;
        break;
    }
    $comments++;
    $offset = $end + 2;
}
echo "Checked $comments comment blocks. Errors: $errors\n";

// Check curly braces
$len = strlen($css);
$braces = 0;
$line = 1;
$bracketStack = [];
for ($i = 0; $i < $len; $i++) {
    $char = $css[$i];
    if ($char === "\n") {
        $line++;
    }
    if ($char === '{') {
        $bracketStack[] = $line;
    } elseif ($char === '}') {
        if (empty($bracketStack)) {
            echo "Error: Unmatched closing brace '}' at line $line.\n";
            $errors++;
        } else {
            array_pop($bracketStack);
        }
    }
}

if (!empty($bracketStack)) {
    echo "Error: Unclosed opening braces '{' at lines: " . implode(', ', $bracketStack) . "\n";
    $errors++;
} else {
    echo "Checked all curly braces. All brackets are balanced!\n";
}

echo "Total syntax validation errors: $errors\n";
