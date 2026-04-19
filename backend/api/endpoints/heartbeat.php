<?php
/**
 * Heartbeat Endpoint
 * Agent sends heartbeat to indicate it's alive
 */

function handle_heartbeat($pdo, $method) {
    if ($method !== 'POST') {
        send_error('Method not allowed', 405);
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['device_id'])) {
        send_error('Missing required fields: device_id', 400);
    }

    $device_id = $data['device_id'];
    $agent_version = $data['agent_version'] ?? 'unknown';
    $status_info = json_encode($data['status_info'] ?? []);

    try {
        // Update device last heartbeat
        $stmt = $pdo->prepare("
            UPDATE devices
            SET last_heartbeat = NOW()
            WHERE id = :device_id
        ");
        $stmt->execute([':device_id' => $device_id]);

        // Log heartbeat
        $heartbeat_id = bin2hex(random_bytes(18));
        $stmt = $pdo->prepare("
            INSERT INTO heartbeats (id, device_id, agent_version, status_info)
            VALUES (:id, :device_id, :agent_version, :status_info)
        ");
        $stmt->execute([
            ':id' => $heartbeat_id,
            ':device_id' => $device_id,
            ':agent_version' => $agent_version,
            ':status_info' => $status_info
        ]);

        send_success([
            'status' => 'ok',
            'timestamp' => time()
        ]);

    } catch (PDOException $e) {
        throw $e;
    }
}
?>
