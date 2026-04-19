<?php
/**
 * Authentication Middleware
 */

function verify_api_key($headers = null) {
    if ($headers === null) {
        $headers = getallheaders();
    }

    if (!isset($headers['Authorization']) && !isset($headers['authorization'])) {
        return false;
    }

    $auth_header = $headers['Authorization'] ?? $headers['authorization'];
    $token = str_replace('Bearer ', '', $auth_header);

    return $token === API_KEY || $token === ADMIN_KEY;
}

function verify_admin_key($headers = null) {
    if ($headers === null) {
        $headers = getallheaders();
    }

    if (!isset($headers['Authorization']) && !isset($headers['authorization'])) {
        return false;
    }

    $auth_header = $headers['Authorization'] ?? $headers['authorization'];
    $token = str_replace('Bearer ', '', $auth_header);

    return $token === ADMIN_KEY;
}

function send_unauthorized() {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized: Invalid API key']);
    exit;
}

function send_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

function send_success($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}
?>
