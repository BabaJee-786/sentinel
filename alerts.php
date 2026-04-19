<?php
require_once __DIR__ . '/backend/db.php';
require_once __DIR__ . '/backend/session_auth.php';
require_once __DIR__ . '/partials/header.php';
require_once __DIR__ . '/partials/footer.php';

require_login();

try {
    run_query($pdo, 'UPDATE alerts SET is_read = 1 WHERE is_read = 0');
} catch (Exception $e) {
    // Keep page rendering even if alert acknowledgement fails.
}

$totalAlerts = fetch_count($pdo, 'SELECT COUNT(*) AS total FROM alerts');
$criticalAlerts = fetch_count($pdo, "SELECT COUNT(*) AS total FROM alerts WHERE severity = 'critical'");
$unreadAlerts = fetch_count($pdo, 'SELECT COUNT(*) AS total FROM alerts WHERE is_read = 0');
$alerts = fetch_all($pdo, 'SELECT a.*, d.device_name FROM alerts a LEFT JOIN devices d ON a.device_id = d.id ORDER BY a.timestamp DESC LIMIT 40');

render_header('alerts', 'Alerts', 'Threat events and detections from your deployed agents.');
?>

<div class="stats-grid">
    <div class="stat-card alert">
        <h2>TOTAL ALERTS</h2>
        <div class="value"><?php echo $totalAlerts; ?></div>
        <div class="desc">In the system</div>
    </div>
    <div class="stat-card">
        <h2>CRITICAL</h2>
        <div class="value"><?php echo $criticalAlerts; ?></div>
        <div class="desc">High-severity incidents</div>
    </div>
    <div class="stat-card">
        <h2>UNREAD</h2>
        <div class="value"><?php echo $unreadAlerts; ?></div>
        <div class="desc">Pending review</div>
    </div>
</div>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3>Alert Timeline</h3>
            <p class="panel-meta">Latest alerts with severity, domain, and message details.</p>
        </div>
        <span class="status-pill"><?php echo count($alerts); ?> latest</span>
    </div>

    <?php if (count($alerts) === 0): ?>
        <div class="activity-card" data-empty-alerts>No alert data is available.
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table" id="alerts-table">
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>Device</th>
                        <th>Severity</th>
                        <th>Message</th>
                        <th>Timestamp</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alerts as $alert): ?>
                        <tr data-alert-id="<?php echo htmlspecialchars($alert['id']); ?>">
                            <td><?php echo htmlspecialchars($alert['domain']); ?></td>
                            <td><?php echo htmlspecialchars($alert['device_name'] ?? $alert['device_id'] ?? 'Unknown device'); ?></td>
                            <td><span class="status-pill pill-<?php echo htmlspecialchars($alert['severity'] ?? 'medium'); ?>"><?php echo strtoupper(htmlspecialchars($alert['severity'] ?? 'medium')); ?></span></td>
                            <td><?php echo htmlspecialchars($alert['message']); ?></td>
                            <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($alert['timestamp']))); ?></td>
                            <td>
                                <button type="button" class="button button-small warn-button" data-device-id="<?php echo htmlspecialchars($alert['device_id']); ?>" data-message="Warning: Access to this website is restricted by Sentinel security policy. Please close the site and contact the administrator if you need access.">Warn</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php render_footer();
