<?php
require_once __DIR__ . '/backend/db.php';
require_once __DIR__ . '/backend/session_auth.php';
require_once __DIR__ . '/partials/header.php';
require_once __DIR__ . '/partials/footer.php';

require_login();

$onlineWindowSeconds = 90;
$devices = fetch_all($pdo, 'SELECT * FROM devices ORDER BY last_heartbeat DESC');
$totalDevices = count($devices);
$onlineThreshold = time() - $onlineWindowSeconds;
$onlineDevices = 0;
foreach ($devices as $device) {
    $heartbeatTs = ($device['last_heartbeat'] ?? null) ? strtotime($device['last_heartbeat']) : false;
    if ($heartbeatTs && $heartbeatTs >= $onlineThreshold) {
        $onlineDevices++;
    }
}
$totalAlerts = fetch_count($pdo, 'SELECT COUNT(*) AS total FROM alerts');

render_header('devices', 'Devices', 'Live agent inventory and heartbeat status.');
?>

<div class="stats-grid">
    <div class="stat-card">
        <h2>TOTAL DEVICES</h2>
        <div class="value" id="devices-total"><?php echo $totalDevices; ?></div>
        <div class="desc"><span id="devices-online"><?php echo $onlineDevices; ?></span> online</div>
    </div>
    <div class="stat-card alert">
        <h2>ACTIVE ALERTS</h2>
        <div class="value"><?php echo $totalAlerts; ?></div>
        <div class="desc">Latest threat activity</div>
    </div>
    <div class="stat-card">
        <h2>LAST CHECK</h2>
        <div class="value"><?php echo htmlspecialchars(date('H:i:s')); ?></div>
        <div class="desc">Updated automatically</div>
    </div>
</div>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3>Connected Devices</h3>
            <p class="panel-meta">Detailed endpoint list with heartbeat and network details.</p>
        </div>
        <span class="status-pill"><span id="devices-records"><?php echo count($devices); ?></span> records</span>
    </div>

    <?php if (count($devices) === 0): ?>
        <div class="activity-card">No devices are registered yet.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table" id="devices-table">
                <thead>
                    <tr>
                        <th>Device</th>
                        <th>IP Address</th>
                        <th>Type</th>
                        <th>Last Heartbeat</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($devices as $device): ?>
                        <?php $online = ($device['last_heartbeat'] ?? null) && strtotime($device['last_heartbeat']) >= $onlineThreshold; ?>
                        <tr>
                            <td><?php echo htmlspecialchars($device['device_name'] ?: $device['id']); ?></td>
                            <td><?php echo htmlspecialchars($device['ip_address'] ?: 'Unknown'); ?></td>
                            <td><?php echo htmlspecialchars($device['device_type'] ?: 'Unknown'); ?></td>
                            <td><?php echo htmlspecialchars($device['last_heartbeat'] ?? 'N/A'); ?></td>
                            <td><span class="status-pill <?php echo $online ? 'pill-high' : 'pill-low'; ?>"><?php echo $online ? 'Online' : 'Offline'; ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php render_footer();
