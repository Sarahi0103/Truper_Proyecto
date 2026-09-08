<?php
/**
 * Geocoding & Reverse Geocoding API - Ferretería FOX
 * Proxy backend to avoid CORS, User-Agent restrictions and rate limit issues in client browsers.
 */

require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');

$action = $_GET['action'] ?? 'reverse';

function callHttpService(string $url, array $headers = []): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => array_merge([
            'User-Agent: FerreteriaFOX-Geocode/1.0 (contacto@ferreteriafox.com)',
            'Accept-Language: es-MX,es;q=0.9,en;q=0.5',
            'Accept: application/json'
        ], $headers)
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300 && !empty($response)) {
        return $response;
    }
    return null;
}

if ($action === 'reverse') {
    $lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
    $lng = isset($_GET['lng']) ? (float)$_GET['lng'] : (isset($_GET['lon']) ? (float)$_GET['lon'] : null);

    if ($lat === null || $lng === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Coordenadas lat y lng requeridas']);
        exit;
    }

    $road = '';
    $houseNumber = '';
    $neighbourhood = '';
    $city = '';
    $state = '';
    $postcode = '';
    $displayName = '';

    // 1. Intentar OpenStreetMap Nominatim con User-Agent autorizado desde servidor
    $osmUrl = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$lat}&lon={$lng}&zoom=18&addressdetails=1";
    $osmRaw = callHttpService($osmUrl);

    if ($osmRaw) {
        $osmData = json_decode($osmRaw, true);
        if (!empty($osmData['address'])) {
            $addr = $osmData['address'];
            $road = $addr['road'] ?? $addr['pedestrian'] ?? $addr['residential'] ?? $addr['highway'] ?? $addr['suburb'] ?? '';
            $houseNumber = $addr['house_number'] ?? '';
            $neighbourhood = $addr['neighbourhood'] ?? $addr['suburb'] ?? $addr['quarter'] ?? $addr['residential'] ?? '';
            $city = $addr['city'] ?? $addr['county'] ?? $addr['municipality'] ?? $addr['town'] ?? $addr['village'] ?? '';
            $state = $addr['state'] ?? '';
            $postcode = !empty($addr['postcode']) ? substr(trim($addr['postcode']), 0, 5) : '';
            $displayName = $osmData['display_name'] ?? '';
        }
    }

    // 2. Fallback a Photon (Komoot OSM)
    if (empty($road) || empty($city)) {
        $photonUrl = "https://photon.komoot.io/reverse?lat={$lat}&lon={$lng}";
        $photonRaw = callHttpService($photonUrl);
        if ($photonRaw) {
            $photonData = json_decode($photonRaw, true);
            if (!empty($photonData['features'][0]['properties'])) {
                $p = $photonData['features'][0]['properties'];
                if (empty($road)) $road = $p['street'] ?? $p['name'] ?? '';
                if (empty($houseNumber)) $houseNumber = $p['housenumber'] ?? '';
                if (empty($city)) $city = $p['city'] ?? $p['town'] ?? $p['district'] ?? '';
                if (empty($state)) $state = $p['state'] ?? '';
                if (empty($postcode) && !empty($p['postcode'])) $postcode = substr(trim($p['postcode']), 0, 5);
            }
        }
    }

    // 3. Fallback a BigDataCloud
    if (empty($road) || empty($city) || empty($state)) {
        $bdcUrl = "https://api.bigdatacloud.net/data/reverse-geocode-client?latitude={$lat}&longitude={$lng}&localityLanguage=es";
        $bdcRaw = callHttpService($bdcUrl);
        if ($bdcRaw) {
            $bdcData = json_decode($bdcRaw, true);
            if (!empty($bdcData)) {
                if (empty($city)) $city = $bdcData['city'] ?? $bdcData['locality'] ?? '';
                if (empty($state)) $state = $bdcData['principalSubdivision'] ?? '';
                if (empty($postcode) && !empty($bdcData['postcode'])) $postcode = substr(trim($bdcData['postcode']), 0, 5);
                if (empty($road)) $road = $bdcData['locality'] ?? '';
            }
        }
    }

    // Formatear dirección completa para el campo "Calle y Número"
    $formattedAddress = $road;
    if (!empty($houseNumber)) {
        $formattedAddress .= " #{$houseNumber}";
    }
    if (!empty($neighbourhood) && strcasecmp($neighbourhood, $road) !== 0) {
        $formattedAddress .= ($formattedAddress ? ", Col. " : "Col. ") . $neighbourhood;
    }

    // Si aún no se encontró calle pero sí colonia o ciudad
    if (empty($formattedAddress)) {
        if (!empty($neighbourhood)) {
            $formattedAddress = "Col. " . $neighbourhood;
        } elseif (!empty($city)) {
            $formattedAddress = $city;
        }
    }

    echo json_encode([
        'success' => true,
        'lat' => $lat,
        'lng' => $lng,
        'address' => $formattedAddress,
        'road' => $road,
        'house_number' => $houseNumber,
        'neighbourhood' => $neighbourhood,
        'city' => $city,
        'state' => $state,
        'postcode' => $postcode,
        'display_name' => $displayName ?: $formattedAddress
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'ip') {
    // Ubicación por IP (cuando GPS está denegado en el navegador)
    $clientIp = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    if (strpos($clientIp, ',') !== false) {
        $parts = explode(',', $clientIp);
        $clientIp = trim($parts[0]);
    }

    $isLocal = empty($clientIp) || in_array($clientIp, ['127.0.0.1', '::1']) || strpos($clientIp, '192.168.') === 0 || strpos($clientIp, '10.') === 0;

    $url = $isLocal ? "https://ipapi.co/json/" : "https://ipapi.co/{$clientIp}/json/";
    $ipRaw = callHttpService($url);

    if ($ipRaw) {
        $ipData = json_decode($ipRaw, true);
        if (!empty($ipData['latitude']) && !empty($ipData['longitude'])) {
            echo json_encode([
                'success' => true,
                'lat' => (float)$ipData['latitude'],
                'lng' => (float)$ipData['longitude'],
                'city' => $ipData['city'] ?? 'Guadalajara',
                'state' => $ipData['region'] ?? 'Jalisco',
                'postcode' => $ipData['postal'] ?? '44100',
                'country' => $ipData['country_name'] ?? 'México',
                'ip' => $ipData['ip'] ?? $clientIp
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // Fallback estándar en caso de fallo de servicio IP
    echo json_encode([
        'success' => true,
        'lat' => 20.6597,
        'lng' => -103.3496,
        'city' => 'Guadalajara',
        'state' => 'Jalisco',
        'postcode' => '44100',
        'country' => 'México',
        'fallback' => true
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'search') {
    $q = trim($_GET['q'] ?? '');
    if (empty($q)) {
        echo json_encode(['success' => false, 'results' => []]);
        exit;
    }

    $osmUrl = "https://nominatim.openstreetmap.org/search?format=json&q=" . urlencode($q . ", México") . "&limit=5&addressdetails=1";
    $osmRaw = callHttpService($osmUrl);
    $results = $osmRaw ? json_decode($osmRaw, true) : [];

    echo json_encode([
        'success' => true,
        'results' => is_array($results) ? $results : []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acción no reconocida']);
