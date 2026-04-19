<?php
/**
 * Get Alerts Endpoint
 */

function handle_get_alerts($pdo, $method) {
    if ($method !== 'GET') {
        send_error('Method not allowed', 405);
    }

    $device_id = $_GET['device_id'] ?? null;
    $user_id = $_GET['user_id'] ?? null;
    $limit = intval($_GET['limit'] ?? 100);
    $offset = intval($_GET['offset'] ?? 0);

    if (!$device_id && !$user_id) {
        send_error('Missing required parameter: device_id or user_id', 400);
    }

    try {
        $query = "SELECT * FROM alerts WHERE 1=1";
        $count_query = "SELECT COUNT(*) as total FROM alerts WHERE 1=1";
        $params = [];

        if ($user_id) {
            $query .= " AND user_id = :user_id";
            $count_query .= " AND user_id = :user_id";
            $params[':user_id'] = $user_id;
        }

        if ($device_id) {
            $query .= " AND device_id = :device_id";
            $count_query .= " AND device_id = :device_id";
            $params[':device_id'] = $device_id;
        }

        // Get total count
        $stmt = $pdo->prepare($count_query);
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];

        // Get paginated results
        $query .= " ORDER BY timestamp DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        $alerts = $stmt->fetchAll();

        send_success([
            'alerts' => $alerts,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ]);

    } catch (PDOException $e) {
        throw $e;
    }
}
?>
