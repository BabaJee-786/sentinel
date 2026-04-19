<?php
/**
 * Sentinel API - Update Domain Endpoint
 * Allows modification of domain properties
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

if (!isset($data['domain_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing domain_id']);
    exit;
}

$domain_id = $data['domain_id'];

try {
    // Verify domain exists
    $domain = fetch_one($pdo, 'SELECT * FROM domains WHERE id = :id', [':id' => $domain_id]);
    if (!$domain) {
        http_response_code(404);
        echo json_encode(['error' => 'Domain not found']);
        exit;
    }

    $updateData = [];
    $allowedFields = ['domain', 'status', 'category', 'reason'];

    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            // Validate domain field
            if ($field === 'domain' && empty(trim($data[$field]))) {
                http_response_code(400);
                echo json_encode(['error' => 'Domain cannot be empty']);
                exit;
            }
            // Validate status
            if ($field === 'status' && !in_array($data[$field], ['restricted', 'allowed', 'monitored', 'live', 'paused'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid status value']);
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

    // Log this action
    log_admin_action(
        $pdo,
        'update_domain',
        'domain',
        $domain_id,
        json_encode($domain),
        json_encode(array_merge($domain, $updateData))
    );

    // Perform update
    update($pdo, 'domains', $updateData, [':id' => $domain_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Domain updated successfully',
        'domain_id' => $domain_id
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Update failed: ' . $e->getMessage()]);
    exit;
}
