<?php
/**
 * Get Dashboard Stats Endpoint
 */

function handle_get_stats($pdo, $method) {
    if ($method !== 'GET') {
        send_error('Method not allowed', 405);
    }

    try {
        // Alerts over time (last 7 days)
        $alerts_query = "
            SELECT DATE(timestamp) as date, COUNT(*) as count
            FROM alerts
            WHERE timestamp >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(timestamp)
            ORDER BY date
        ";
        $alerts_stmt = $pdo->prepare($alerts_query);
        $alerts_stmt->execute();
        $alerts_data = $alerts_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Device counts
        $devices_query = "SELECT COUNT(*) as total FROM devices";
        $devices_stmt = $pdo->prepare($devices_query);
        $devices_stmt->execute();
        $total_devices = $devices_stmt->fetch()['total'];

        $online_threshold = date('Y-m-d H:i:s', time() - 90);
        $online_query = "SELECT COUNT(*) as online FROM devices WHERE last_heartbeat >= :threshold";
        $online_stmt = $pdo->prepare($online_query);
        $online_stmt->bindValue(':threshold', $online_threshold);
        $online_stmt->execute();
        $online_devices = $online_stmt->fetch()['online'];

        // Other stats
        $alerts_total = fetch_count($pdo, 'SELECT COUNT(*) AS total FROM alerts');
        $restricted_domains = fetch_count($pdo, 'SELECT COUNT(*) AS total FROM domains WHERE status = :status', [':status' => 'restricted']);
        $pending_commands = fetch_count($pdo, "SELECT COUNT(*) AS total FROM commands WHERE status = 'pending'");

        send_success([
            'alerts_over_time' => $alerts_data,
            'total_devices' => $total_devices,
            'online_devices' => $online_devices,
            'total_alerts' => $alerts_total,
            'restricted_domains' => $restricted_domains,
            'pending_commands' => $pending_commands
        ]);
    } catch (Exception $e) {
        send_error('Database error: ' . $e->getMessage(), 500);
    }
}