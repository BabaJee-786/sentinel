<?php
/**
 * Report Alert Endpoint
 * Agent reports detected domain access
 */

function handle_report_alert($pdo, $method) {
    if ($method !== 'POST') {
        send_error('Method not allowed', 405);
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['device_id']) || !isset($data['domain'])) {
        send_error('Missing required fields: device_id, domain', 400);
    }

    $alert_id = bin2hex(random_bytes(18));
    $device_id = $data['device_id'];
    $domain = $data['domain'];
    $severity = $data['severity'] ?? 'medium';
    $message = $data['message'] ?? "Restricted domain accessed: $domain";
    $log_id = $data['log_id'] ?? null;
    $action_taken = $data['action_taken'] ?? null;

    try {
        // Get user_id from device
        $stmt = $pdo->prepare("SELECT user_id FROM devices WHERE id = :device_id");
        $stmt->execute([':device_id' => $device_id]);
        $device = $stmt->fetch();

        if (!$device) {
            send_error('Device not found', 404);
        }

        $user_id = $device['user_id'];

        if (!empty($data['device_name'])) {
            $stmt = $pdo->prepare("UPDATE devices SET device_name = :device_name WHERE id = :device_id");
            $stmt->execute([':device_name' => $data['device_name'], ':device_id' => $device_id]);
        }

            ':log_id' => $log_id,
            ':domain' => $domain,
            ':severity' => $severity,
            ':message' => $message,
            ':action_taken' => $action_taken
        ]);

        send_success([
            'alert_id' => $alert_id,
            'message' => 'Alert reported successfully'
        ], 201);

    } catch (PDOException $e) {
        throw $e;
    }
}
?>
