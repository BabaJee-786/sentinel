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
?>
