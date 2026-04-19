<?php
/**
 * Sentinel API - Delete Domain Endpoint
 * Permanently removes a domain and its permissions
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

    // Log this action
    log_admin_action(
        $pdo,
        'delete_domain',
        'domain',
        $domain_id,
        json_encode($domain),
        null
    );

    // Delete domain (cascade will handle related records)
    run_query($pdo, 'DELETE FROM domains WHERE id = :id', [':id' => $domain_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Domain deleted successfully',
        'domain_id' => $domain_id
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Delete failed: ' . $e->getMessage()]);
    exit;
}
