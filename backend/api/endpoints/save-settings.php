<?php
/**
 * Sentinel API - Save Settings Endpoint
 * Updates configuration settings (SMTP, email alerts, etc.)
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

if (!isset($data['settings']) || !is_array($data['settings'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing settings object']);
    exit;
}

$settings = $data['settings'];
$allowedSettings = ['smtp_host', 'smtp_port', 'smtp_email', 'smtp_password', 'email_enabled', 'email_alert_severity', 'organization_name'];

try {
    foreach ($settings as $key => $value) {
        // Only allow predefined settings
        if (!in_array($key, $allowedSettings)) {
            continue;
        }

        // Validate some fields
        if ($key === 'smtp_port' && !is_numeric($value)) {
            http_response_code(400);
            echo json_encode(['error' => 'SMTP port must be numeric']);
            exit;
        }

        if ($key === 'email_enabled') {
            $value = $value ? '1' : '0';
        }

        // Check if setting exists
        $existing = fetch_one($pdo, 'SELECT id FROM settings WHERE setting_key = :key', [':key' => $key]);

        if ($existing) {
            // Update existing setting
            update($pdo, 'settings', [':setting_value' => $value], [':setting_key' => $key]);
        } else {
            // Create new setting
            insert($pdo, 'settings', [
                ':id' => gen_uuid(),
                ':setting_key' => $key,
                ':setting_value' => $value
            ]);
        }

        // Log this action
        log_admin_action(
            $pdo,
            'update_setting',
            'settings',
            $key,
            null,
            json_encode(['key' => $key, 'value' => ($key === 'smtp_password' ? '***HIDDEN***' : $value)])
        );
    }

    echo json_encode([
        'success' => true,
        'message' => 'Settings saved successfully'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Save failed: ' . $e->getMessage()]);
    exit;
}
