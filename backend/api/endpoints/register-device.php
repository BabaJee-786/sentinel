<?php
/**
 * Register Device Endpoint
 */

function handle_register_device($pdo, $method) {
    if ($method !== 'POST') {
        send_error('Method not allowed', 405);
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['device_name'])) {
        send_error('Missing required fields: device_name', 400);
    }

    $device_id = bin2hex(random_bytes(18));
    $device_name = $data['device_name'];
    $device_type = $data['device_type'] ?? 'Windows';
    $os_version = $data['os_version'] ?? 'Unknown';
    $ip_address = $data['ip_address'] ?? $_SERVER['REMOTE_ADDR'];
    $mac_address = $data['mac_address'] ?? null;
    $user_id = $data['user_id'] ?? null;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO devices (id, user_id, device_name, device_type, os_version, ip_address, mac_address)
            VALUES (:id, :user_id, :device_name, :device_type, :os_version, :ip_address, :mac_address)
        ");

        $stmt->execute([
            ':id' => $device_id,
            ':user_id' => $user_id,
            ':device_name' => $device_name,
            ':device_type' => $device_type,
            ':os_version' => $os_version,
            ':ip_address' => $ip_address,
            ':mac_address' => $mac_address
        ]);

        send_success([
            'device_id' => $device_id,
            'message' => 'Device registered successfully'
        ], 201);

    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            send_error('Device already registered', 409);
        }
        throw $e;
    }
}
?>
