<?php require "config/database.php"; $stmt = $pdo->query("SELECT * FROM marketplace_ce_products LIMIT 1"); print_r($stmt->fetchAll()); ?>
