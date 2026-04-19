<?php
require_once __DIR__ . '/backend/db.php';
require_once __DIR__ . '/backend/session_auth.php';
require_once __DIR__ . '/partials/header.php';
require_once __DIR__ . '/partials/footer.php';

require_login();

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
$restrictedDomains = fetch_count($pdo, 'SELECT COUNT(*) AS total FROM domains WHERE status = :status', [':status' => 'restricted']);
$latestDevices = array_slice($allDevices, 0, 5);
$latestAlerts = fetch_all($pdo, 'SELECT * FROM alerts ORDER BY timestamp DESC LIMIT 5');
$pendingCommands = 0;
try {
    $pendingCommands = fetch_count($pdo, "SELECT COUNT(*) AS total FROM commands WHERE status = 'pending'");
} catch (Exception $e) {
    $pendingCommands = 0;
}

render_header('dashboard', 'System Overview', 'Real-time monitoring with alerts, device status, domains, and command dispatch.');
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="card stat-card">
                <div class="card-body">
                    <h5 class="card-title">TOTAL DEVICES</h5>
                    <div class="value" id="dashboard-devices-total"><?php echo $totalDevices; ?></div>
                    <div class="desc"><span id="dashboard-devices-online"><?php echo $onlineDevices; ?></span> online</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card stat-card alert">
                <div class="card-body">
                    <h5 class="card-title">ACTIVE ALERTS</h5>
                    <div class="value"><?php echo $totalAlerts; ?></div>
                    <div class="desc">Threat events captured</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card stat-card">
                <div class="card-body">
                    <h5 class="card-title">RESTRICTED DOMAINS</h5>
                    <div class="value"><?php echo $restrictedDomains; ?></div>
                    <div class="desc">Blocked policies</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card stat-card">
                <div class="card-body">
                    <h5 class="card-title">PENDING COMMANDS</h5>
                    <div class="value"><?php echo $pendingCommands; ?></div>
                    <div class="desc">Awaiting dispatch</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Alerts Over Time</h5>
                    <canvas id="alertsChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Device Status</h5>
                    <canvas id="devicesChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card panel">
                <div class="card-header panel-header">
                    <h5>Recent Devices</h5>
                    <span class="badge bg-secondary"><span id="dashboard-device-records"><?php echo count($latestDevices); ?></span> recent</span>
                </div>
                <div class="card-body">
                    <?php if (count($latestDevices) === 0): ?>
                        <p>No devices have checked in yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped" id="dashboard-devices">
                                <thead>
                                    <tr>
                                        <th>Device</th>
                                        <th>IP</th>
                                        <th>Heartbeat</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($latestDevices as $device): ?>
                                        <?php $isOnline = ($device['last_heartbeat'] ?? null) && strtotime($device['last_heartbeat']) >= $onlineThreshold; ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($device['device_name'] ?: $device['id']); ?></td>
                                            <td><?php echo htmlspecialchars($device['ip_address'] ?: 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($device['last_heartbeat'] ?? 'N/A'); ?></td>
                                            <td><span class="badge <?php echo $isOnline ? 'bg-success' : 'bg-danger'; ?>"><?php echo $isOnline ? 'Online' : 'Offline'; ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card panel">
                <div class="card-header panel-header">
                    <h5>Recent Alerts</h5>
                    <span class="badge bg-warning"><?php echo count($latestAlerts); ?> recent</span>
                </div>
                <div class="card-body">
                    <?php if (count($latestAlerts) === 0): ?>
                        <p>No alerts yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped" id="dashboard-alerts">
                                <thead>
                                    <tr>
                                        <th>Domain</th>
                                        <th>Severity</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($latestAlerts as $alert): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($alert['domain']); ?></td>
                                            <td><span class="badge bg-<?php echo $alert['severity'] === 'high' ? 'danger' : ($alert['severity'] === 'medium' ? 'warning' : 'info'); ?>"><?php echo htmlspecialchars(ucfirst($alert['severity'])); ?></span></td>
                                            <td><?php echo htmlspecialchars($alert['timestamp']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Fetch stats from API
async function fetchStats() {
    try {
        const response = await fetch('backend/api/index.php?action=get_stats', {
            headers: {
                'Authorization': 'Bearer your-secure-api-key-here' // Replace with actual key
            }
        });
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error fetching stats:', error);
        return null;
    }
}

// Initialize charts with API data
async function initCharts() {
    const stats = await fetchStats();
    if (!stats) return;

    // Alerts Over Time
    const alertsCtx = document.getElementById('alertsChart').getContext('2d');
    const alertsData = stats.alerts_over_time || [];
    const labels = alertsData.map(item => item.date);
    const counts = alertsData.map(item => item.count);

    new Chart(alertsCtx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Alerts',
                data: counts,
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Device Status Pie Chart
    const devicesCtx = document.getElementById('devicesChart').getContext('2d');
    new Chart(devicesCtx, {
        type: 'pie',
        data: {
            labels: ['Online', 'Offline'],
            datasets: [{
                data: [stats.online_devices, stats.total_devices - stats.online_devices],
                backgroundColor: ['#28a745', '#dc3545']
            }]
        },
        options: {
            responsive: true
        }
    });
}

initCharts();
</script>

<?php
render_footer();
