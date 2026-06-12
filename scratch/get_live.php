<?php
$html = file_get_contents('https://truper-web-eg3h.onrender.com/login.php');
echo substr($html, 0, 1500);
