<?php
/**
 * Sentinel API - Toggle Domain Status Endpoint
 * Switches domain between 'live' (active blocking) and 'paused' (disabled)
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

if (!isset($data['domain_id']) || !isset($data['status'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing domain_id or status']);
    exit;
}

$domain_id = $data['domain_id'];
$newStatus = $data['status'];

// Validate status values
if (!in_array($newStatus, ['live', 'paused'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid status. Must be "live" or "paused"']);
    exit;
}

try {
    // Verify domain exists
    $domain = fetch_one($pdo, 'SELECT * FROM domains WHERE id = :id', [':id' => $domain_id]);
    if (!$domain) {
        http_response_code(404);
        echo json_encode(['error' => 'Domain not found']);
        exit;
    }

    // Log this action
    log_admin_action(
        $pdo,
        'toggle_domain_status',
        'domain',
        $domain_id,
        json_encode(['status' => $domain['status']]),
        json_encode(['status' => $newStatus])
    );

    // Update status
    update($pdo, 'domains', [':status' => $newStatus], [':id' => $domain_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Domain status updated',
        'domain_id' => $domain_id,
        'new_status' => $newStatus
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Update failed: ' . $e->getMessage()]);
    exit;
}
