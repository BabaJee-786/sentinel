<?php
/**
 * Sentinel API - Delete Device Endpoint
 * Permanently removes a device and associated data
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session_auth.php';

header('Content-Type: application/json');
require_admin_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['device_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing device_id']);
    exit;
}

$device_id = $data['device_id'];

try {
    // Verify device exists
    $device = fetch_one($pdo, 'SELECT * FROM devices WHERE id = :id', [':id' => $device_id]);
    if (!$device) {
        http_response_code(404);
        echo json_encode(['error' => 'Device not found']);
        exit;
    }

    // Log this action in audit trail
    log_admin_action(
        $pdo,
        'delete_device',
        'device',
        $device_id,
        json_encode($device),
        null // old value stored in old_value, new_value is null for deletions
    );

    // Delete device (cascade will handle related records)
    run_query($pdo, 'DELETE FROM devices WHERE id = :id', [':id' => $device_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Device deleted successfully',
        'device_id' => $device_id
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Delete failed: ' . $e->getMessage()]);
    exit;
}
