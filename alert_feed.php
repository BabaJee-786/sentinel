<?php
require_once __DIR__ . '/backend/db.php';
require_once __DIR__ . '/backend/session_auth.php';

require_login_json();
header('Content-Type: application/json');

$since = trim($_GET['since'] ?? '');
$limit = intval($_GET['limit'] ?? 10);
if ($limit < 1) {
    $limit = 1;
}
if ($limit > 25) {
    $limit = 25;
}

$params = [];
$where = '';
if ($since !== '') {
    $where = 'WHERE a.timestamp > :since';
    $params[':since'] = $since;
}

$alerts = fetch_all(
    $pdo,
    "SELECT a.*, d.device_name
     FROM alerts a
     LEFT JOIN devices d ON d.id = a.device_id
     $where
     ORDER BY a.timestamp DESC
     LIMIT $limit",
    $params
);

$latest = fetch_one(
    $pdo,
    'SELECT timestamp FROM alerts ORDER BY timestamp DESC LIMIT 1'
);

$unread = fetch_count($pdo, 'SELECT COUNT(*) AS total FROM alerts WHERE is_read = 0');

$payload = [
    'alerts' => array_reverse($alerts),
    'unread_count' => $unread,
    'latest_timestamp' => $latest['timestamp'] ?? null,
    'server_time' => date('Y-m-d H:i:s'),
];

echo json_encode($payload);
