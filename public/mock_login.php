<?php
require_once '../config/config.php';
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['name'] = 'Administrador Ferretería FOX';
echo "Logged in as Admin locally! Go to <a href='/tickets.php'>tickets.php</a> or <a href='/guest_tickets.php'>guest_tickets.php</a>";
