<?php
/**
 * Log Access Endpoint
 * Agent logs domain access attempts
 */

function handle_log_access($pdo, $method) {
    if ($method !== 'POST') {
        send_error('Method not allowed', 405);
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['device_id']) || !isset($data['domain'])) {
        send_error('Missing required fields: device_id, domain', 400);
    }

    $log_id = bin2hex(random_bytes(18));
    $device_id = $data['device_id'];
    $domain = $data['domain'];
    $access_type = $data['access_type'] ?? 'dns_query';
    $status = $data['status'] ?? 'detected';
    $ip_address = $data['ip_address'] ?? $_SERVER['REMOTE_ADDR'];
    $process_name = $data['process_name'] ?? null;
    $details = json_encode($data['details'] ?? []);

    try {
        // Get user_id from device
        $stmt = $pdo->prepare("SELECT user_id FROM devices WHERE id = :device_id");
        $stmt->execute([':device_id' => $device_id]);
        $device = $stmt->fetch();

        if (!$device) {
            send_error('Device not found', 404);
        }

        $user_id = $device['user_id'];

        // Insert log
        $stmt = $pdo->prepare("
            INSERT INTO logs (id, device_id, user_id, domain, access_type, status, ip_address, process_name, details)
            VALUES (:id, :device_id, :user_id, :domain, :access_type, :status, :ip_address, :process_name, :details)
        ");

        $stmt->execute([
            ':id' => $log_id,
            ':device_id' => $device_id,
            ':user_id' => $user_id,
            ':domain' => $domain,
            ':access_type' => $access_type,
            ':status' => $status,
            ':ip_address' => $ip_address,
            ':process_name' => $process_name,
            ':details' => $details
        ]);

        send_success([
            'log_id' => $log_id,
            'message' => 'Access logged successfully'
        ], 201);

    } catch (PDOException $e) {
        throw $e;
    }
}
?>
