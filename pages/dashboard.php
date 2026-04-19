<?php
/**
 * Dashboard Page - System Overview
 */

$onlineWindowSeconds = 90;
$allDevices = fetch_all($pdo, 'SELECT * FROM devices ORDER BY last_heartbeat DESC');
$totalDevices = count($allDevices);
$onlineThreshold = time() - $onlineWindowSeconds;
$onlineDevices = 0;

foreach ($allDevices as $device) {
    $heartbeatTs = ($device['last_heartbeat'] ?? null) ? strtotime($device['last_heartbeat']) : false;
    if ($heartbeatTs && $heartbeatTs >= $onlineThreshold) {
        $onlineDevices++;
    }
}

$totalAlerts = fetch_count($pdo, 'SELECT COUNT(*) AS total FROM alerts');
$restrictedDomains = fetch_count($pdo, 'SELECT COUNT(*) AS total FROM domains WHERE status IN ("live", "restricted")');
$latestDevices = array_slice($allDevices, 0, 10);
$latestAlerts = fetch_all($pdo, 'SELECT a.*, d.device_name FROM alerts a JOIN devices d ON a.device_id = d.id ORDER BY a.timestamp DESC LIMIT 10');

?>

<div class="page-header">
    <h2><i class="bi bi-speedometer2"></i> Dashboard</h2>
    <span class="badge bg-info">Last updated: <?= date('H:i:s') ?></span>
</div>

<!-- Statistics Cards -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-label">Total Devices</div>
        <div class="stat-value" id="stat-total-devices"><?= $totalDevices ?></div>
        <small class="text-muted"><span id="stat-online-devices"><?= $onlineDevices ?></span> online</small>
    </div>

    <div class="stat-card alert">
        <div class="stat-label">Active Alerts</div>
        <div class="stat-value"><?= $totalAlerts ?></div>
        <small class="text-muted">Threat events captured</small>
    </div>

    <div class="stat-card success">
        <div class="stat-label">Active Restrictions</div>
        <div class="stat-value"><?= $restrictedDomains ?></div>
        <small class="text-muted">Blocked policies</small>
    </div>

    <div class="stat-card">
        <div class="stat-label">System Status</div>
        <div class="stat-value"><span class="badge bg-success">Operational</span></div>
        <small class="text-muted">All systems green</small>
    </div>
</div>

<!-- Recent Activity -->
<div class="row">
    <div class="col-md-6">
        <div class="table-wrapper">
            <div style="padding: 20px; border-bottom: 1px solid #e0e0e0;">
                <h5 class="mb-0"><i class="bi bi-laptop"></i> Recent Devices</h5>
            </div>
            <?php if (empty($latestDevices)): ?>
                <div class="empty-state">
                    <p>No devices registered yet</p>
                </div>
            <?php else: ?>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Device Name</th>
                            <th>IP Address</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($latestDevices, 0, 5) as $device): ?>
                            <?php $online = ($device['last_heartbeat'] ?? null) && strtotime($device['last_heartbeat']) >= $onlineThreshold; ?>
                            <tr>
                                <td><?= htmlspecialchars($device['device_name'] ?: 'Unknown') ?></td>
                                <td><code><?= htmlspecialchars($device['ip_address'] ?? 'N/A') ?></code></td>
                                <td>
                                    <span class="badge-status <?= $online ? 'badge-online' : 'badge-offline' ?>">
                                        <?= $online ? 'Online' : 'Offline' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-md-6">
        <div class="table-wrapper">
            <div style="padding: 20px; border-bottom: 1px solid #e0e0e0;">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Recent Alerts</h5>
            </div>
            <?php if (empty($latestAlerts)): ?>
                <div class="empty-state">
                    <p>No alerts yet</p>
                </div>
            <?php else: ?>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Device</th>
                            <th>Domain</th>
                            <th>Severity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($latestAlerts, 0, 5) as $alert): ?>
                            <tr>
                                <td><?= htmlspecialchars($alert['device_name']) ?></td>
                                <td><code style="font-size: 0.85rem;"><?= htmlspecialchars($alert['domain']) ?></code></td>
                                <td>
                                    <?php
                                    $severityClass = match($alert['severity']) {
                                        'critical' => 'danger',
                                        'high' => 'warning',
                                        'medium' => 'info',
                                        default => 'secondary'
                                    };
                                    ?>
                                    <span class="badge bg-<?= $severityClass ?>"><?= htmlspecialchars($alert['severity']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
