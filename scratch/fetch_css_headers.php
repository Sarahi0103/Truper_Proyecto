<?php
$url = "https://truper-web-eg3h.onrender.com/css/analytics.css?v=" . time();
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
$response = curl_exec($ch);
echo "--- HEADERS ---\n" . $response;
echo "\n--- CONTENT SIZE ---\n";
$content = file_get_contents($url);
echo strlen($content) . " bytes\n";
echo "First 200 bytes:\n" . substr($content, 0, 200) . "\n";
echo "Last 200 bytes:\n" . substr($content, -200) . "\n";
