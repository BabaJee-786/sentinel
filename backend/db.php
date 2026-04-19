<?php
/**
 * Sentinel API - Core PHP Database Layer
 * Pure PDO-based database functions, no abstraction
 */

// Load environment variables
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Database configuration from environment
$db_host = $_ENV['DB_HOST'] ?? 'localhost';
$db_user = $_ENV['DB_USER'] ?? 'sentinel_user';
$db_pass = $_ENV['DB_PASS'] ?? 'sentinel_password';
$db_name = $_ENV['DB_NAME'] ?? 'sentinel_db';
$db_port = $_ENV['DB_PORT'] ?? 3306;

// Connect to database
try {
    $pdo = new PDO(
        "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
}

// ======================== Database Helper Functions ========================

function gen_uuid() {
    return bin2hex(random_bytes(9));
}

function run_query($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        throw new Exception("Database error: " . $e->getMessage());
    }
}

function fetch_one($pdo, $sql, $params = []) {
    return run_query($pdo, $sql, $params)->fetch();
}

function fetch_all($pdo, $sql, $params = []) {
    return run_query($pdo, $sql, $params)->fetchAll();
}

function fetch_count($pdo, $sql, $params = []) {
    $row = fetch_one($pdo, $sql, $params);
    return intval($row['total'] ?? 0);
}

function normalize_params($data) {
    $normalized = [];
    foreach ($data as $key => $value) {
        $clean = ltrim($key, ':');
        $normalized[$clean] = $value;
    }
    return $normalized;
}

function insert($pdo, $table, $data) {
    $cleanKeys = array_map(fn($k) => ltrim($k, ':'), array_keys($data));
    $cols = implode(',', $cleanKeys);
    $vals = ':' . implode(', :', $cleanKeys);
    $sql = "INSERT INTO $table ($cols) VALUES ($vals)";
    return run_query($pdo, $sql, normalize_params($data));
}

function update($pdo, $table, $data, $where = []) {
    $cleanDataKeys = array_map(fn($k) => ltrim($k, ':'), array_keys($data));
    $set = implode(',', array_map(fn($k) => "$k=:$k", $cleanDataKeys));
    $sql = "UPDATE $table SET $set";
    
    if ($where) {
        $cleanWhereKeys = array_map(fn($k) => ltrim($k, ':'), array_keys($where));
        $where_clause = implode(' AND ', array_map(fn($k) => "$k=:$k", $cleanWhereKeys));
        $sql .= " WHERE $where_clause";
        $params = array_merge(normalize_params($data), array_combine($cleanWhereKeys, array_values($where)));
    } else {
        $params = normalize_params($data);
    }
    
    return run_query($pdo, $sql, $params);
}

function delete($pdo, $table, $where) {
    $cleanWhereKeys = array_map(fn($k) => ltrim($k, ':'), array_keys($where));
    $where_clause = implode(' AND ', array_map(fn($k) => "$k=:$k", $cleanWhereKeys));
    $sql = "DELETE FROM $table WHERE $where_clause";
    return run_query($pdo, $sql, array_combine($cleanWhereKeys, array_values($where)));
}

// ======================== Audit & Logging Functions ========================

/**
 * Log admin action to audit trail
 */
function log_admin_action($pdo, $action, $entity_type, $entity_id, $old_value = null, $new_value = null) {
    try {
        $user_id = get_authenticated_user()['id'] ?? 'system';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        insert($pdo, 'audit_logs', [
            ':id' => gen_uuid(),
            ':user_id' => $user_id,
            ':action' => $action,
            ':entity_type' => $entity_type,
            ':entity_id' => $entity_id,
            ':old_value' => $old_value,
            ':new_value' => $new_value,
            ':ip_address' => $ip_address
        ]);
    } catch (Exception $e) {
        error_log('Audit log failed: ' . $e->getMessage());
    }
}

/**
 * Get a setting value from settings table
 */
function get_setting($pdo, $key, $default = null) {
    try {
        $setting = fetch_one($pdo, 'SELECT setting_value FROM settings WHERE setting_key = :key', [':key' => $key]);
        return $setting ? $setting['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Check device domain permission (device-specific override)
 */
function check_device_domain_permission($pdo, $device_id, $domain_id) {
    try {
        $permission = fetch_one($pdo,
            'SELECT is_allowed FROM device_domain_permissions WHERE device_id = :device_id AND domain_id = :domain_id',
            [':device_id' => $device_id, ':domain_id' => $domain_id]
        );
        
        // If permission exists, return it; if not, return null (use default blocking)
        return $permission ? (bool)$permission['is_allowed'] : null;
    } catch (Exception $e) {
        return null;
    }
}

// ======================== Email Functions ========================

/**
 * Send email alert using SMTP or mail() function
 * Requires PHPMailer or built-in mail()
 */
function send_email_alert($pdo, $alert_data) {
    try {
        // Check if email alerts are enabled
        $email_enabled = get_setting($pdo, 'email_enabled', '0');
        if (!$email_enabled || $email_enabled === '0') {
            return false;
        }

        // Get SMTP settings
        $smtp_host = get_setting($pdo, 'smtp_host', 'smtp.gmail.com');
        $smtp_port = get_setting($pdo, 'smtp_port', '587');
        $smtp_email = get_setting($pdo, 'smtp_email', '');
        $smtp_password = get_setting($pdo, 'smtp_password', '');
        $org_name = get_setting($pdo, 'organization_name', 'Sentinel');

        if (empty($smtp_email)) {
            error_log('Email alert failed: SMTP email not configured');
            return false;
        }

        // Extract alert data
        $device_name = $alert_data['device_name'] ?? 'Unknown Device';
        $ip_address = $alert_data['ip_address'] ?? 'Unknown';
        $domain = $alert_data['domain'] ?? 'Unknown';
        $timestamp = $alert_data['timestamp'] ?? date('Y-m-d H:i:s');
        $severity = $alert_data['severity'] ?? 'medium';
        $message = $alert_data['message'] ?? 'Security Alert';
        $alert_id = $alert_data['alert_id'] ?? '';

        // Construct email body
        $subject = "[$org_name] Alert: $domain blocked on $device_name";
        $body = "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <h2>Sentinel Security Alert</h2>
                <p><strong>Organization:</strong> $org_name</p>
                <p><strong>Device:</strong> $device_name</p>
                <p><strong>IP Address:</strong> $ip_address</p>
                <p><strong>Blocked Domain:</strong> <code>$domain</code></p>
                <p><strong>Severity:</strong> <span style='color: red;'>$severity</span></p>
                <p><strong>Timestamp:</strong> $timestamp</p>
                <p><strong>Details:</strong> $message</p>
                <hr>
                <p><small>Alert ID: $alert_id</small></p>
                <p><small>This is an automated alert from Sentinel Net monitoring system.</small></p>
            </body>
            </html>
        ";

        // Try to use PHPMailer if available
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            return send_email_phpmailer($smtp_host, $smtp_port, $smtp_email, $smtp_password, $subject, $body);
        } else {
            // Fall back to PHP mail()
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
            $headers .= "From: " . $smtp_email . "\r\n";
            
            $to = $smtp_email; // Send to configured email
            return mail($to, $subject, $body, $headers);
        }
    } catch (Exception $e) {
        error_log('Email alert error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Send email using PHPMailer
 */
function send_email_phpmailer($host, $port, $username, $password, $subject, $body) {
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        // SMTP configuration
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->SMTPSecure = ($port == 587) ? 'tls' : 'ssl';
        $mail->Port = $port;
        
        // Email details
        $mail->setFrom($username);
        $mail->addAddress($username); // Send to the configured email
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);
        
        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log('PHPMailer error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Log email sending attempt
 */
function log_email_alert($pdo, $alert_id, $device_id, $recipient, $subject, $status, $error_msg = null) {
    try {
        insert($pdo, 'email_logs', [
            ':id' => gen_uuid(),
            ':alert_id' => $alert_id,
            ':device_id' => $device_id,
            ':recipient_email' => $recipient,
            ':subject' => $subject,
            ':status' => $status,
            ':error_message' => $error_msg
        ]);
    } catch (Exception $e) {
        error_log('Email log failed: ' . $e->getMessage());
    }
}
?>

