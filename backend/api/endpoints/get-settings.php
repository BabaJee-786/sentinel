<?php
/**
 * Sentinel API - Get Settings Endpoint
 * Retrieves configuration settings (SMTP, email alerts, etc.)
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

try {
    // Fetch all settings
    $settings = fetch_all($pdo, 'SELECT setting_key, setting_value FROM settings ORDER BY setting_key ASC');

    $settingsArray = [];
    foreach ($settings as $setting) {
        $key = $setting['setting_key'];
        $value = $setting['setting_value'];
        
        // Try to parse JSON values
        if (in_array($key, ['email_enabled'])) {
            $settingsArray[$key] = (bool)$value;
        } else {
            $settingsArray[$key] = $value;
        }
    }

    echo json_encode([
        'success' => true,
        'settings' => $settingsArray
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Fetch failed: ' . $e->getMessage()]);
    exit;
}
