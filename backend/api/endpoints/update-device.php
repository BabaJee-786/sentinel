<?php
/**
 * Sentinel API - Update Device Endpoint
 * Allows modification of device properties
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

// Validate required fields
if (!isset($data['device_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing device_id']);
    exit;
}

$device_id = $data['device_id'];

// Verify device exists
try {
    $device = fetch_one($pdo, 'SELECT id FROM devices WHERE id = :id', [':id' => $device_id]);
    if (!$device) {
        http_response_code(404);
        echo json_encode(['error' => 'Device not found']);
        exit;
    }

    // Build update query with only provided fields
    $updateData = [];
    $allowedFields = ['device_name', 'device_type', 'status'];

    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            // Validate status field
            if ($field === 'status' && !in_array($data[$field], ['active', 'disabled'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid status value. Must be "active" or "disabled"']);
                exit;
            }
            $updateData[':' . $field] = $data[$field];
        }
    }

    if (empty($updateData)) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update']);
        exit;
    }

    // Log this action in audit trail
    log_admin_action(
        $pdo,
        'update_device',
        'device',
        $device_id,
        json_encode($device),
        json_encode(array_merge($device, $updateData))
    );

    // Perform update
    update($pdo, 'devices', $updateData, [':id' => $device_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Device updated successfully',
        'device_id' => $device_id
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Update failed: ' . $e->getMessage()]);
    exit;
}
