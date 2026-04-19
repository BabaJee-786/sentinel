<?php
/**
 * Database Configuration
 * MySQL/MariaDB connection setup
 */

// Database credentials
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'sentinel_user');
define('DB_PASS', getenv('DB_PASS') ?: 'sentinel_password');
define('DB_NAME', getenv('DB_NAME') ?: 'sentinel_db');
define('DB_PORT', getenv('DB_PORT') ?: 3306);

// API Keys
define('API_KEY', getenv('API_KEY') ?: 'your-secret-api-key');
define('ADMIN_KEY', getenv('ADMIN_KEY') ?: 'your-admin-key');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        )
    );
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
}

return $pdo;
?>
