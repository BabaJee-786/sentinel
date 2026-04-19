<?php
/**
 * Sentinel API - Main Entry Point
 * Pure core PHP, no frameworks or complex abstractions
 */

// Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, apikey');
header('Content-Type: application/json');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include core files
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../routes.php';

// Get API key from environment
$api_key = $_ENV['API_KEY'] ?? 'your-secure-api-key-here';
$admin_key = $_ENV['ADMIN_KEY'] ?? 'your-admin-key-here';

// Verify API key (skip for health check)
$action = $_GET['action'] ?? null;

if ($action !== 'health') {
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $api_header = $_SERVER['HTTP_APIKEY'] ?? '';
    $token = str_replace('Bearer ', '', $auth_header);
    if (!$token && $api_header) {
        $token = $api_header;
    }
    
    if (!$token || ($token !== $api_key && $token !== $admin_key)) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

// Parse request body
$input = file_get_contents('php://input');
$data = $input ? json_decode($input, true) : [];

// Route handling
try {
    $result = null;
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($action) {
        case 'register_device':
            if ($method !== 'POST') {
                $result = ['error' => 'Method not allowed', 'code' => 405];
            } else {
                $result = route_register_device($pdo, $data);
            }
            break;

        case 'heartbeat':
            if ($method !== 'POST') {
                $result = ['error' => 'Method not allowed', 'code' => 405];
            } else {
                $result = route_heartbeat($pdo, $data);
            }
            break;

        case 'report_alert':
            if ($method !== 'POST') {
                $result = ['error' => 'Method not allowed', 'code' => 405];
            } else {
                $result = route_report_alert($pdo, $data);
            }
            break;

        case 'log_access':
            if ($method !== 'POST') {
                $result = ['error' => 'Method not allowed', 'code' => 405];
            } else {
                $result = route_log_access($pdo, $data);
            }
            break;

        case 'get_domains':
            if ($method !== 'GET') {
                $result = ['error' => 'Method not allowed', 'code' => 405];
            } else {
                $result = route_get_domains($pdo, $_GET);
            }
            break;

        case 'update_domains':
            if ($method !== 'POST' && $method !== 'PUT') {
                $result = ['error' => 'Method not allowed', 'code' => 405];
            } elseif ($token !== $admin_key) {
                $result = ['error' => 'Admin authorization required', 'code' => 403];
            } else {
                $result = route_update_domains($pdo, $data);
            }
            break;

        case 'get_alerts':
            if ($method !== 'GET') {
                $result = ['error' => 'Method not allowed', 'code' => 405];
            } else {
                $result = route_get_alerts($pdo, $_GET);
            }
            break;

        case 'get_commands':
            if ($method !== 'GET') {
                $result = ['error' => 'Method not allowed', 'code' => 405];
            } else {
                $result = route_get_commands($pdo, $_GET);
            }
            break;

        case 'update_command_status':
            if ($method !== 'POST' && $method !== 'PUT') {
                $result = ['error' => 'Method not allowed', 'code' => 405];
            } else {
                $result = route_update_command_status($pdo, $data);
            }
            break;

        case 'get_logs':
            if ($method !== 'GET') {
                $result = ['error' => 'Method not allowed', 'code' => 405];
            } else {
                $result = route_get_logs($pdo, $_GET);
            }
            break;

        case 'get_stats':
            if ($method !== 'GET') {
                $result = ['error' => 'Method not allowed', 'code' => 405];
            } else {
                $result = route_get_stats($pdo);
            }
            break;

        case 'health':
            $result = route_health();
            break;

        default:
            $result = ['error' => 'Unknown action: ' . $action, 'code' => 400];
    }

    // Send response
    $code = $result['code'] ?? 200;
    unset($result['code']);

    http_response_code($code);
    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
