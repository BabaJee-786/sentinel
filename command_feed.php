<?php
require_once __DIR__ . '/backend/db.php';
require_once __DIR__ . '/backend/session_auth.php';

require_login_json();
header('Content-Type: application/json');

$commands = fetch_all(
    $pdo,
    'SELECT c.*, d.device_name FROM commands c LEFT JOIN devices d ON c.device_id = d.id ORDER BY c.created_at DESC LIMIT 20'
);

$payload = [
    'commands' => $commands,
    'pending' => fetch_count($pdo, "SELECT COUNT(*) AS total FROM commands WHERE status = 'pending'"),
    'sent' => fetch_count($pdo, "SELECT COUNT(*) AS total FROM commands WHERE status = 'sent'"),
    'delivered' => fetch_count($pdo, "SELECT COUNT(*) AS total FROM commands WHERE status = 'delivered'"),
    'server_time' => date('Y-m-d H:i:s'),
];

echo json_encode($payload);
