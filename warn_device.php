<?php
require_once __DIR__ . '/backend/db.php';
require_once __DIR__ . '/backend/session_auth.php';

require_login_json();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$deviceId = trim($_POST['device_id'] ?? '');
$message = trim($_POST['message'] ?? 'Warning: Access to this website is restricted by Sentinel security policy. Please close the site and contact the administrator if you need access.');

if ($deviceId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing device_id']);
    exit;
}

$device = fetch_one($pdo, 'SELECT id FROM devices WHERE id = :id', [':id' => $deviceId]);
if (!$device) {
    http_response_code(404);
    echo json_encode(['error' => 'Device not found']);
    exit;
}

try {
    insert($pdo, 'commands', [
        ':id' => gen_uuid(),
        ':device_id' => $deviceId,
        ':message' => $message,
        ':status' => 'pending',
    ]);

    echo json_encode(['message' => 'Warning queued for device.', 'status' => 'ok']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
