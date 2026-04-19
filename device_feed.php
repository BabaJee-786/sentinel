<?php
require_once __DIR__ . '/backend/db.php';
require_once __DIR__ . '/backend/session_auth.php';

require_login_json();
header('Content-Type: application/json');

$onlineWindowSeconds = 90;
$devices = fetch_all($pdo, 'SELECT * FROM devices ORDER BY last_heartbeat DESC');
$threshold = time() - $onlineWindowSeconds;
$onlineCount = 0;

$annotatedDevices = [];
foreach ($devices as $device) {
    $heartbeat = $device['last_heartbeat'] ?? null;
    $heartbeatTs = $heartbeat ? strtotime($heartbeat) : false;
    $isOnline = $heartbeatTs && $heartbeatTs >= $threshold;
    if ($isOnline) {
        $onlineCount++;
    }

    $device['is_online'] = $isOnline;
    $annotatedDevices[] = $device;
}

$recentDevices = array_slice($annotatedDevices, 0, 5);

echo json_encode([
    'total' => count($annotatedDevices),
    'online' => $onlineCount,
    'online_window_seconds' => $onlineWindowSeconds,
    'devices' => $annotatedDevices,
    'recent_devices' => $recentDevices,
    'server_time' => date('Y-m-d H:i:s'),
]);
