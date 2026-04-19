<?php
function nav_item(string $href, string $label, string $activePage, string $page): void {
    $class = $page === $activePage ? 'nav-item active' : 'nav-item';
    echo "<a class=\"$class\" href=\"$href\">$label</a>";
}

function render_header(string $activePage, string $title, string $subtitle): void {
    $currentUser = function_exists('get_authenticated_user') ? get_authenticated_user() : null;
    $latestAlert = null;
    $unreadAlerts = 0;

    if (isset($GLOBALS['pdo'])) {
        try {
            $latestAlert = fetch_one($GLOBALS['pdo'], 'SELECT timestamp FROM alerts ORDER BY timestamp DESC LIMIT 1');
            $unreadAlerts = fetch_count($GLOBALS['pdo'], 'SELECT COUNT(*) AS total FROM alerts WHERE is_read = 0');
        } catch (Exception $e) {
            $latestAlert = null;
            $unreadAlerts = 0;
        }
    }

    $latestTimestamp = $latestAlert['timestamp'] ?? '';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title><?php echo htmlspecialchars($title); ?> | Sentinel</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="assets/style.css">
        <!-- Bootstrap CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Chart.js -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    </head>
    <body data-page="<?php echo htmlspecialchars($activePage); ?>" data-alert-feed="alert_feed.php" data-last-alert="<?php echo htmlspecialchars($latestTimestamp); ?>">
    <div class="page-shell">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-marker">SN</div>
                <div>
                    <h1>SENTINEL</h1>
                    <p>Security Console</p>
                </div>
            </div>
            <nav class="nav-list">
                <?php nav_item('index.php', 'Dashboard', $activePage, 'dashboard'); ?>
                <?php nav_item('devices.php', 'Devices', $activePage, 'devices'); ?>
                <?php nav_item('alerts.php', 'Alerts', $activePage, 'alerts'); ?>
                <?php nav_item('domains.php', 'Domains', $activePage, 'domains'); ?>
                <?php nav_item('commands.php', 'Commands', $activePage, 'commands'); ?>
            </nav>
            <div class="sidebar-footer">
                <?php if ($currentUser): ?>
                    <div class="user-card">
                        <strong><?php echo htmlspecialchars($currentUser['display_name'] ?? $currentUser['email']); ?></strong>
                        <span><?php echo htmlspecialchars($currentUser['email']); ?></span>
                    </div>
                    <a class="logout-link" href="logout.php">Log out</a>
                <?php else: ?>
                    <p>Live monitoring active</p>
                <?php endif; ?>
            </div>
        </aside>
        <main class="main-panel">
            <div class="header-row" id="<?php echo htmlspecialchars($activePage); ?>">
                <div>
                    <h1 class="hero-title"><?php echo htmlspecialchars($title); ?></h1>
                    <p class="hero-subtitle"><?php echo htmlspecialchars($subtitle); ?></p>
                </div>
                <div class="header-actions">
                    <a class="notification-chip" href="alerts.php" aria-label="Open alerts">
                        <span>Alerts</span>
                        <strong id="notification-count"><?php echo intval($unreadAlerts); ?></strong>
                    </a>
                    <div class="status-chip">Last refresh: <span id="last-refresh"><?php echo date('H:i:s'); ?></span></div>
                </div>
            </div>
            <div id="toast-stack" class="toast-stack" aria-live="polite" aria-atomic="true"></div>
    <?php
}
