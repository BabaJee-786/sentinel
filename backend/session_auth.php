<?php
/**
 * Session-based dashboard authentication.
 */

const SENTINEL_DEFAULT_USER_ID = '00000000-0000-0000-0000-000000000001';
const SENTINEL_DEFAULT_USER_EMAIL = 'admin@sentinel.local';
const SENTINEL_DEFAULT_USER_NAME = 'Sentinel Admin';
const SENTINEL_DEFAULT_USER_PASSWORD = 'sentinel123';
const SENTINEL_DEFAULT_USER_PASSWORD_HASH = '$2y$10$9dZREbwdSLQzTCQKNkI4UexG1d8tKRRWA8GlY7UjCtuwkpYVdYws2';

function ensure_session_started(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function ensure_default_dashboard_user(PDO $pdo): void {
    static $seeded = false;

    if ($seeded) {
        return;
    }

    try {
        run_query(
            $pdo,
            'INSERT INTO users (id, email, password_hash, display_name, is_admin)
             SELECT :id, :email, :password_hash, :display_name, :is_admin
             WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = :email_check)',
            [
                ':id' => SENTINEL_DEFAULT_USER_ID,
                ':email' => SENTINEL_DEFAULT_USER_EMAIL,
                ':password_hash' => SENTINEL_DEFAULT_USER_PASSWORD_HASH,
                ':display_name' => SENTINEL_DEFAULT_USER_NAME,
                ':is_admin' => 1,
                ':email_check' => SENTINEL_DEFAULT_USER_EMAIL,
            ]
        );
    } catch (Exception $e) {
        // Leave the dashboard usable even if the users table has not been created yet.
    }

    $seeded = true;
}

function get_authenticated_user(): ?array {
    ensure_session_started();
    return $_SESSION['sentinel_user'] ?? null;
}

function attempt_login(PDO $pdo, string $email, string $password): bool {
    ensure_session_started();
    ensure_default_dashboard_user($pdo);

    $user = fetch_one(
        $pdo,
        'SELECT id, email, password_hash, display_name, is_admin FROM users WHERE email = :email LIMIT 1',
        [':email' => $email]
    );

    if (!$user || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['sentinel_user'] = [
        'id' => $user['id'],
        'email' => $user['email'],
        'display_name' => $user['display_name'] ?: $user['email'],
        'is_admin' => (bool) ($user['is_admin'] ?? false),
    ];

    return true;
}

function require_login(): void {
    ensure_session_started();

    if (isset($_SESSION['sentinel_user'])) {
        return;
    }

    header('Location: login.php');
    exit;
}

function logout_user(): void {
    ensure_session_started();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

function require_login_json(): void {
    ensure_session_started();

    if (isset($_SESSION['sentinel_user'])) {
        return;
    }

    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Authentication required']);
    exit;
}
?>
