<?php
/**
 * Sentinel API - Core PHP Routes
 * All endpoints in a single file with pure PHP logic
 */

function route_register_device($pdo, $data) {
    if (!isset($data['device_name'])) {
        return ['error' => 'Missing device_name', 'code' => 400];
    }

    $device_id = gen_uuid();
    
    try {
        insert($pdo, 'devices', [
            ':id' => $device_id,
            ':user_id' => $data['user_id'] ?? null,
            ':device_name' => $data['device_name'],
            ':device_type' => $data['device_type'] ?? 'Windows',
            ':os_version' => $data['os_version'] ?? 'Unknown',
            ':ip_address' => $data['ip_address'] ?? $_SERVER['REMOTE_ADDR'],
            ':mac_address' => $data['mac_address'] ?? null,
        ]);

        return [
            'device_id' => $device_id,
            'message' => 'Device registered successfully',
            'code' => 201
        ];
    } catch (Exception $e) {
        return ['error' => $e->getMessage(), 'code' => 500];
    }
}

function route_heartbeat($pdo, $data) {
    if (!isset($data['device_id'])) {
        return ['error' => 'Missing device_id', 'code' => 400];
    }

    try {
        // Update device heartbeat
        update($pdo, 'devices', [':last_heartbeat' => date('Y-m-d H:i:s')], [':id' => $data['device_id']]);

        // Log heartbeat
        insert($pdo, 'heartbeats', [
            ':id' => gen_uuid(),
            ':device_id' => $data['device_id'],
            ':agent_version' => $data['agent_version'] ?? 'unknown',
            ':status_info' => json_encode($data['status_info'] ?? []),
        ]);

        return ['status' => 'ok', 'timestamp' => time(), 'code' => 200];
    } catch (Exception $e) {
        return ['error' => $e->getMessage(), 'code' => 500];
    }
}

function route_report_alert($pdo, $data) {
    if (!isset($data['device_id']) || !isset($data['domain'])) {
        return ['error' => 'Missing device_id or domain', 'code' => 400];
    }

    try {
        // Get user_id from device
        $device = fetch_one($pdo, 'SELECT user_id FROM devices WHERE id = :id', [':id' => $data['device_id']]);
        
        if (!$device) {
            return ['error' => 'Device not found', 'code' => 404];
        }

        if (!empty($data['device_name'])) {
            update($pdo, 'devices', [':device_name' => $data['device_name']], [':id' => $data['device_id']]);
        }

        $alert_id = gen_uuid();
        
        insert($pdo, 'alerts', [
            ':id' => $alert_id,
            ':device_id' => $data['device_id'],
            ':user_id' => $device['user_id'],
            ':log_id' => $data['log_id'] ?? null,
            ':domain' => $data['domain'],
            ':severity' => $data['severity'] ?? 'medium',
            ':message' => $data['message'] ?? 'Alert from agent',
            ':action_taken' => $data['action_taken'] ?? null,
        ]);

        return ['alert_id' => $alert_id, 'message' => 'Alert reported', 'code' => 201];
    } catch (Exception $e) {
        return ['error' => $e->getMessage(), 'code' => 500];
    }
}

function route_log_access($pdo, $data) {
    if (!isset($data['device_id']) || !isset($data['domain'])) {
        return ['error' => 'Missing device_id or domain', 'code' => 400];
    }

    try {
        // Get user_id from device
        $device = fetch_one($pdo, 'SELECT user_id FROM devices WHERE id = :id', [':id' => $data['device_id']]);
        
        if (!$device) {
            return ['error' => 'Device not found', 'code' => 404];
        }

        $log_id = gen_uuid();
        
        insert($pdo, 'logs', [
            ':id' => $log_id,
            ':device_id' => $data['device_id'],
            ':user_id' => $device['user_id'],
            ':domain' => $data['domain'],
            ':access_type' => $data['access_type'] ?? 'dns_query',
            ':status' => $data['status'] ?? 'detected',
            ':ip_address' => $data['ip_address'] ?? $_SERVER['REMOTE_ADDR'],
            ':process_name' => $data['process_name'] ?? null,
            ':details' => json_encode($data['details'] ?? []),
        ]);

        return ['log_id' => $log_id, 'message' => 'Access logged', 'code' => 201];
    } catch (Exception $e) {
        return ['error' => $e->getMessage(), 'code' => 500];
    }
}

function route_get_domains($pdo, $params) {
    $device_id = $params['device_id'] ?? null;
    $user_id = $params['user_id'] ?? null;
    $status = $params['status'] ?? null;

    if (!$device_id && !$user_id) {
        return ['error' => 'Missing device_id or user_id', 'code' => 400];
    }

    try {
        $sql = 'SELECT * FROM domains WHERE 1=1';
        $query_params = [];

        if ($user_id) {
            $sql .= ' AND user_id = :user_id';
            $query_params[':user_id'] = $user_id;
        }

        if ($device_id) {
            $device = fetch_one($pdo, 'SELECT user_id FROM devices WHERE id = :id', [':id' => $device_id]);
            if ($device && $device['user_id']) {
                $sql .= ' AND user_id = :user_id';
                $query_params[':user_id'] = $device['user_id'];
            }
        }

        if ($status) {
            $sql .= ' AND status = :status';
            $query_params[':status'] = $status;
        }

        $sql .= ' ORDER BY created_at DESC';

        $domains = fetch_all($pdo, $sql, $query_params);

        return ['domains' => $domains, 'count' => count($domains), 'code' => 200];
    } catch (Exception $e) {
        return ['error' => $e->getMessage(), 'code' => 500];
    }
}

function route_update_domains($pdo, $data) {
    if (!isset($data['user_id']) || !isset($data['domains'])) {
        return ['error' => 'Missing user_id or domains array', 'code' => 400];
    }

    if (!is_array($data['domains'])) {
        return ['error' => 'domains must be an array', 'code' => 400];
    }

    try {
        $inserted = 0;
        $updated = 0;

        foreach ($data['domains'] as $domain_data) {
            if (!isset($domain_data['domain'])) continue;

            $domain_id = gen_uuid();
            $domain_name = $domain_data['domain'];
            $status = $domain_data['status'] ?? 'restricted';
            $category = $domain_data['category'] ?? null;
            $reason = $domain_data['reason'] ?? null;

            // Check if domain exists
            $exists = fetch_one($pdo, 
                'SELECT id FROM domains WHERE domain = :domain AND user_id = :user_id',
                [':domain' => $domain_name, ':user_id' => $data['user_id']]
            );

            if ($exists) {
                update($pdo, 'domains', [
                    ':status' => $status,
                    ':category' => $category,
                    ':reason' => $reason,
                ], [':domain' => $domain_name, ':user_id' => $data['user_id']]);
                $updated++;
            } else {
                insert($pdo, 'domains', [
                    ':id' => $domain_id,
                    ':user_id' => $data['user_id'],
                    ':domain' => $domain_name,
                    ':status' => $status,
                    ':category' => $category,
                    ':reason' => $reason,
                ]);
                $inserted++;
            }
        }

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'message' => 'Domains updated',
            'code' => 200
        ];
    } catch (Exception $e) {
        return ['error' => $e->getMessage(), 'code' => 500];
    }
}

function ensure_commands_table($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS commands (
        id VARCHAR(36) PRIMARY KEY,
        device_id VARCHAR(36) NOT NULL,
        message TEXT NOT NULL,
        status ENUM('pending','sent','delivered') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_device_id (device_id),
        CONSTRAINT fk_command_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

function route_get_alerts($pdo, $params) {
    $device_id = $params['device_id'] ?? null;
    $user_id = $params['user_id'] ?? null;
    $limit = intval($params['limit'] ?? 100);
    $offset = intval($params['offset'] ?? 0);

    if (!$device_id && !$user_id) {
        return ['error' => 'Missing device_id or user_id', 'code' => 400];
    }

    try {
        $sql = 'SELECT * FROM alerts WHERE 1=1';
        $count_sql = 'SELECT COUNT(*) as total FROM alerts WHERE 1=1';
        $query_params = [];

        if ($user_id) {
            $sql .= ' AND user_id = :user_id';
            $count_sql .= ' AND user_id = :user_id';
            $query_params[':user_id'] = $user_id;
        }

        if ($device_id) {
            $sql .= ' AND device_id = :device_id';
            $count_sql .= ' AND device_id = :device_id';
            $query_params[':device_id'] = $device_id;
        }

        // Get total
        $total_result = fetch_one($pdo, $count_sql, $query_params);
        $total = $total_result['total'] ?? 0;

        // Get paginated results
        $sql .= ' ORDER BY timestamp DESC LIMIT :limit OFFSET :offset';
        $query_params[':limit'] = $limit;
        $query_params[':offset'] = $offset;

        $alerts = fetch_all($pdo, $sql, $query_params);

        return [
            'alerts' => $alerts,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'code' => 200
        ];
    } catch (Exception $e) {
        return ['error' => $e->getMessage(), 'code' => 500];
    }
}

function route_get_commands($pdo, $params) {
    ensure_commands_table($pdo);
    $device_id = $params['device_id'] ?? null;
    $user_id = $params['user_id'] ?? null;

    if (!$device_id && !$user_id) {
        return ['error' => 'Missing device_id or user_id', 'code' => 400];
    }

    try {
        $sql = 'SELECT c.*, d.device_name FROM commands c LEFT JOIN devices d ON c.device_id = d.id WHERE 1=1';
        $query_params = [];

        if ($user_id) {
            $sql .= ' AND c.device_id IN (SELECT id FROM devices WHERE user_id = :user_id)';
            $query_params[':user_id'] = $user_id;
        }

        if ($device_id) {
            $sql .= ' AND c.device_id = :device_id';
            $query_params[':device_id'] = $device_id;
        }

        if (isset($params['status'])) {
            $sql .= ' AND c.status = :status';
            $query_params[':status'] = $params['status'];
        } else {
            $sql .= ' AND c.status = :status';
            $query_params[':status'] = 'pending';
        }

        $sql .= ' ORDER BY c.created_at DESC';
        $commands = fetch_all($pdo, $sql, $query_params);

        return ['commands' => $commands, 'count' => count($commands), 'code' => 200];
    } catch (Exception $e) {
        return ['error' => $e->getMessage(), 'code' => 500];
    }
}

function route_update_command_status($pdo, $data) {
    ensure_commands_table($pdo);
    if (!isset($data['id']) || !isset($data['status'])) {
        return ['error' => 'Missing id or status', 'code' => 400];
    }

    try {
        update($pdo, 'commands', [':status' => $data['status']], [':id' => $data['id']]);
        return ['message' => 'Command status updated', 'code' => 200];
    } catch (Exception $e) {
        return ['error' => $e->getMessage(), 'code' => 500];
    }
}

function route_get_logs($pdo, $params) {
    $device_id = $params['device_id'] ?? null;
    $user_id = $params['user_id'] ?? null;
    $domain = $params['domain'] ?? null;
    $limit = intval($params['limit'] ?? 100);
    $offset = intval($params['offset'] ?? 0);

    if (!$device_id && !$user_id) {
        return ['error' => 'Missing device_id or user_id', 'code' => 400];
    }

    try {
        $sql = 'SELECT * FROM logs WHERE 1=1';
        $count_sql = 'SELECT COUNT(*) as total FROM logs WHERE 1=1';
        $query_params = [];

        if ($user_id) {
            $sql .= ' AND user_id = :user_id';
            $count_sql .= ' AND user_id = :user_id';
            $query_params[':user_id'] = $user_id;
        }

        if ($device_id) {
            $sql .= ' AND device_id = :device_id';
            $count_sql .= ' AND device_id = :device_id';
            $query_params[':device_id'] = $device_id;
        }

        if ($domain) {
            $sql .= ' AND domain LIKE :domain';
            $count_sql .= ' AND domain LIKE :domain';
            $query_params[':domain'] = '%' . $domain . '%';
        }

        // Get total
        $total_result = fetch_one($pdo, $count_sql, $query_params);
        $total = $total_result['total'] ?? 0;

        // Get paginated results
        $sql .= ' ORDER BY timestamp DESC LIMIT :limit OFFSET :offset';
        $query_params[':limit'] = $limit;
        $query_params[':offset'] = $offset;

        $logs = fetch_all($pdo, $sql, $query_params);

        return [
            'logs' => $logs,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'code' => 200
        ];
    } catch (Exception $e) {
        return ['error' => $e->getMessage(), 'code' => 500];
    }
}

function route_get_stats($pdo) {
    try {
        // Alerts over time (last 7 days)
        $alerts_query = "
            SELECT DATE(timestamp) as date, COUNT(*) as count
            FROM alerts
            WHERE timestamp >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(timestamp)
            ORDER BY date
        ";
        $alerts_data = fetch_all($pdo, $alerts_query);

        // Device counts
        $total_devices = fetch_count($pdo, 'SELECT COUNT(*) AS total FROM devices');
        $online_threshold = date('Y-m-d H:i:s', time() - 90);
        $online_devices = fetch_count($pdo, 'SELECT COUNT(*) AS total FROM devices WHERE last_heartbeat >= :threshold', [':threshold' => $online_threshold]);

        // Other stats
        $total_alerts = fetch_count($pdo, 'SELECT COUNT(*) AS total FROM alerts');
        $restricted_domains = fetch_count($pdo, 'SELECT COUNT(*) AS total FROM domains WHERE status = :status', [':status' => 'restricted']);
        $pending_commands = fetch_count($pdo, "SELECT COUNT(*) AS total FROM commands WHERE status = 'pending'");

        return [
            'alerts_over_time' => $alerts_data,
            'total_devices' => $total_devices,
            'online_devices' => $online_devices,
            'total_alerts' => $total_alerts,
            'restricted_domains' => $restricted_domains,
            'pending_commands' => $pending_commands,
            'code' => 200
        ];
    } catch (Exception $e) {
        return ['error' => $e->getMessage(), 'code' => 500];
    }
}

function route_health() {
    return ['status' => 'ok', 'timestamp' => time(), 'code' => 200];
}
?>
