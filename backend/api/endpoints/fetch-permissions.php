<?php
/**
 * Sentinel API - Fetch Device Domain Permissions Endpoint
 * Retrieves all permissions for a specific domain
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session_auth.php';

header('Content-Type: application/json');
require_admin_session();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$domain_id = $_GET['domain_id'] ?? null;

if (!$domain_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing domain_id parameter']);
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

    // Get all devices with their permission status for this domain
    $sql = "
        SELECT 
            d.id,
            d.device_name,
            d.ip_address,
            d.status,
            COALESCE(ddp.is_allowed, 0) as is_allowed,
            COALESCE(ddp.id, null) as permission_id
        FROM devices d
        LEFT JOIN device_domain_permissions ddp 
            ON d.id = ddp.device_id AND ddp.domain_id = :domain_id
        ORDER BY d.device_name ASC
    ";

    $permissions = fetch_all($pdo, $sql, [':domain_id' => $domain_id]);

    echo json_encode([
        'success' => true,
        'domain_id' => $domain_id,
        'domain_name' => $domain['domain'],
        'permissions' => $permissions
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Fetch failed: ' . $e->getMessage()]);
    exit;
}
