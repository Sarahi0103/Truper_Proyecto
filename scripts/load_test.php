<?php
/**
 * TRUPER PLATFORM - Basic Load Test Script
 * Performs simple load testing on the application
 * 
 * Usage: php scripts/load_test.php [url] [concurrent_users] [requests_per_user]
 * Example: php scripts/load_test.php http://localhost:8000 10 50
 */

if ($argc < 2) {
    echo "Usage: php load_test.php [url] [concurrent_users] [requests_per_user]\n";
    echo "Example: php load_test.php http://localhost:8000 10 50\n";
    exit(1);
}

$url = $argv[1];
$concurrentUsers = isset($argv[2]) ? (int)$argv[2] : 5;
$requestsPerUser = isset($argv[3]) ? (int)$argv[3] : 10;

echo "========================================\n";
echo "LOAD TEST - TRUPER PLATFORM\n";
echo "========================================\n";
echo "URL: $url\n";
echo "Concurrent Users: $concurrentUsers\n";
echo "Requests per User: $requestsPerUser\n";
echo "Total Requests: " . ($concurrentUsers * $requestsPerUser) . "\n";
echo "========================================\n\n";

$results = [];
$startTime = microtime(true);

// Function to simulate a user
function simulateUser($url, $requests, $userId) {
    $userResults = [
        'user_id' => $userId,
        'requests' => $requests,
        'successful' => 0,
        'failed' => 0,
        'total_time' => 0,
        'response_times' => []
    ];
    
    for ($i = 0; $i < $requests; $i++) {
        $requestStart = microtime(true);
        
        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, "LoadTest-User-$userId");
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $requestEnd = microtime(true);
            $responseTime = ($requestEnd - $requestStart) * 1000; // ms
            
            $userResults['response_times'][] = $responseTime;
            $userResults['total_time'] += $responseTime;
            
            if ($httpCode >= 200 && $httpCode < 400) {
                $userResults['successful']++;
            } else {
                $userResults['failed']++;
            }
        } catch (Exception $e) {
            $userResults['failed']++;
        }
        
        // Small delay between requests
        usleep(rand(100000, 500000)); // 100-500ms
    }
    
    return $userResults;
}

// Run concurrent users
$processes = [];
$pipes = [];

for ($i = 0; $i < $concurrentUsers; $i++) {
    $descriptorspec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];
    
    $process = proc_open(
        'php -r \'require "' . __FILE__ . '"; echo json_encode(simulateUser("' . $url . '", ' . $requestsPerUser . ', ' . ($i + 1) . '));\'',
        $descriptorspec,
        $pipes
    );
    
    if (is_resource($process)) {
        $processes[] = $process;
    }
}

// Collect results
foreach ($processes as $process) {
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    
    if ($output) {
        $result = json_decode($output, true);
        if ($result) {
            $results[] = $result;
        }
    }
}

$endTime = microtime(true);
$totalTime = ($endTime - $startTime);

// Calculate statistics
$totalRequests = $concurrentUsers * $requestsPerUser;
$totalSuccessful = array_sum(array_column($results, 'successful'));
$totalFailed = array_sum(array_column($results, 'failed'));
$allResponseTimes = [];
foreach ($results as $result) {
    $allResponseTimes = array_merge($allResponseTimes, $result['response_times']);
}

sort($allResponseTimes);
$avgResponseTime = count($allResponseTimes) > 0 ? array_sum($allResponseTimes) / count($allResponseTimes) : 0;
$minResponseTime = count($allResponseTimes) > 0 ? $allResponseTimes[0] : 0;
$maxResponseTime = count($allResponseTimes) > 0 ? $allResponseTimes[count($allResponseTimes) - 1] : 0;
$medianResponseTime = count($allResponseTimes) > 0 ? $allResponseTimes[floor(count($allResponseTimes) / 2)] : 0;

$requestsPerSecond = $totalRequests / $totalTime;

echo "========================================\n";
echo "LOAD TEST RESULTS\n";
echo "========================================\n";
echo "Total Time: " . round($totalTime, 2) . " seconds\n";
echo "Total Requests: $totalRequests\n";
echo "Successful: $totalSuccessful (" . round(($totalSuccessful / $totalRequests) * 100, 2) . "%)\n";
echo "Failed: $totalFailed (" . round(($totalFailed / $totalRequests) * 100, 2) . "%)\n";
echo "Requests/Second: " . round($requestsPerSecond, 2) . "\n";
echo "========================================\n";
echo "Response Times (ms):\n";
echo "  Average: " . round($avgResponseTime, 2) . "\n";
echo "  Median: " . round($medianResponseTime, 2) . "\n";
echo "  Min: " . round($minResponseTime, 2) . "\n";
echo "  Max: " . round($maxResponseTime, 2) . "\n";
echo "========================================\n";

// Performance assessment
if ($totalFailed / $totalRequests > 0.05) {
    echo "⚠️  WARNING: High failure rate (>5%)\n";
} elseif ($avgResponseTime > 1000) {
    echo "⚠️  WARNING: High average response time (>1000ms)\n";
} elseif ($requestsPerSecond < 10) {
    echo "⚠️  WARNING: Low throughput (<10 req/s)\n";
} else {
    echo "✅ Load test passed successfully\n";
}

echo "========================================\n";
