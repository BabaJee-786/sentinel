<?php
/**
 * Sentinel API - Manage Device Domain Permissions Endpoint
 * Assigns or removes domain access permissions for specific devices
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
if (!isset($data['domain_id']) || !isset($data['devices']) || !is_array($data['devices'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing domain_id or devices array']);
    exit;
}

$domain_id = $data['domain_id'];
$devices = $data['devices']; // array of device_ids with is_allowed flag

try {
    // Verify domain exists
    $domain = fetch_one($pdo, 'SELECT * FROM domains WHERE id = :id', [':id' => $domain_id]);
    if (!$domain) {
        http_response_code(404);
        echo json_encode(['error' => 'Domain not found']);
        exit;
    }

    // Process each device permission
    foreach ($devices as $deviceData) {
        if (!isset($deviceData['device_id']) || !isset($deviceData['is_allowed'])) {
            continue;
        }

        $device_id = $deviceData['device_id'];
        $is_allowed = (bool)$deviceData['is_allowed'];

        // Verify device exists
        $device = fetch_one($pdo, 'SELECT id FROM devices WHERE id = :id', [':id' => $device_id]);
        if (!$device) {
            continue;
        }

        // Check if permission already exists
        $existing = fetch_one($pdo, 
            'SELECT id FROM device_domain_permissions WHERE device_id = :device_id AND domain_id = :domain_id',
            [':device_id' => $device_id, ':domain_id' => $domain_id]
        );

        if ($existing) {
            // Update existing permission
            update($pdo, 'device_domain_permissions', 
                [':is_allowed' => ($is_allowed ? 1 : 0)],
                [':id' => $existing['id']]
            );
        } else {
            // Create new permission
            insert($pdo, 'device_domain_permissions', [
                ':id' => gen_uuid(),
                ':device_id' => $device_id,
                ':domain_id' => $domain_id,
                ':is_allowed' => ($is_allowed ? 1 : 0)
            ]);
        }

        // Log this action
        log_admin_action(
            $pdo,
            'assign_domain_device',
            'device_domain_permissions',
            gen_uuid(),
            null,
            json_encode(['device_id' => $device_id, 'domain_id' => $domain_id, 'is_allowed' => $is_allowed])
        );
    }

    echo json_encode([
        'success' => true,
        'message' => 'Device permissions updated successfully',
        'domain_id' => $domain_id
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Update failed: ' . $e->getMessage()]);
    exit;
}
