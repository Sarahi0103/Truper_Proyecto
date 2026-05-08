<?php
require __DIR__ . "/../config/config.php";

$new = password_hash('Truper123', PASSWORD_DEFAULT);
$stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE email = ?');
$stmt->execute([$new, 'admin@truper.com']);
echo "updated\n";
