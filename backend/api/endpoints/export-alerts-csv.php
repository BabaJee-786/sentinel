<?php
/**
 * Sentinel API - Export Alerts as CSV Endpoint
 * Generates and downloads selected alerts in CSV format
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../session_auth.php';

require_admin_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Get alert IDs to export
$alertIds = $data['alert_ids'] ?? [];

if (empty($alertIds)) {
    http_response_code(400);
    echo json_encode(['error' => 'No alerts selected']);
    exit;
}

try {
    // Sanitize and prepare the IDs for the query
    $placeholders = array_fill(0, count($alertIds), '?');
    $sql = "
        SELECT 
            d.device_name,
            d.ip_address,
            a.domain,
            a.timestamp,
            a.severity,
            a.message
        FROM alerts a
        JOIN devices d ON a.device_id = d.id
        WHERE a.id IN (" . implode(',', $placeholders) . ")
        ORDER BY a.timestamp DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($alertIds);
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($alerts)) {
        http_response_code(404);
        echo json_encode(['error' => 'No alerts found']);
        exit;
    }

    // Generate CSV
    $filename = 'alerts_export_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Create temporary file in memory
    $output = fopen('php://output', 'w');
    
    // Write BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write header row
    fputcsv($output, ['Device Name', 'IP Address', 'Domain', 'Timestamp', 'Severity', 'Message']);
    
    // Write data rows
    foreach ($alerts as $alert) {
        fputcsv($output, [
            $alert['device_name'] ?? 'Unknown',
            $alert['ip_address'] ?? 'Unknown',
            $alert['domain'] ?? '',
            $alert['timestamp'] ?? '',
            $alert['severity'] ?? 'medium',
            $alert['message'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Export failed: ' . $e->getMessage()]);
    exit;
}
