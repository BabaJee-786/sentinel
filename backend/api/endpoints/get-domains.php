<?php
/**
 * Get Domains Endpoint
 */

function handle_get_domains($pdo, $method) {
    if ($method !== 'GET') {
        send_error('Method not allowed', 405);
    }

    $device_id = $_GET['device_id'] ?? null;
    $user_id = $_GET['user_id'] ?? null;
    $status = $_GET['status'] ?? null;

    if (!$device_id && !$user_id) {
        send_error('Missing required parameter: device_id or user_id', 400);
    }

    try {
        $query = "SELECT * FROM domains WHERE 1=1";
        $params = [];

        if ($user_id) {
            $query .= " AND user_id = :user_id";
            $params[':user_id'] = $user_id;
        }

        if ($device_id) {
            // Get user_id from device if not provided
            $stmt = $pdo->prepare("SELECT user_id FROM devices WHERE id = :device_id");
            $stmt->execute([':device_id' => $device_id]);
            $device = $stmt->fetch();

            if ($device && $device['user_id']) {
                $query .= " AND user_id = :user_id";
                $params[':user_id'] = $device['user_id'];
            }
        }

        if ($status) {
            $query .= " AND status = :status";
            $params[':status'] = $status;
        }

        $query .= " ORDER BY created_at DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $domains = $stmt->fetchAll();

        send_success([
            'domains' => $domains,
            'count' => count($domains)
        ]);

    } catch (PDOException $e) {
        throw $e;
    }
}
?>
