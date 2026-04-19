<?php
require_once __DIR__ . '/backend/db.php';
require_once __DIR__ . '/backend/session_auth.php';
require_once __DIR__ . '/partials/header.php';
require_once __DIR__ . '/partials/footer.php';

require_login();

$flash = '';
$flash_type = 'success';

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

ensure_commands_table($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'send_command') {
        $deviceId = $_POST['device_id'] ?? '';
        $message = trim($_POST['message'] ?? '');

        if ($deviceId === '' || $message === '') {
            $flash_type = 'error';
            $flash = 'Select a device and enter a warning message.';
        } else {
            $device = fetch_one($pdo, 'SELECT id FROM devices WHERE id = :id', [':id' => $deviceId]);
            if (!$device) {
                $flash_type = 'error';
                $flash = 'Selected device was not found.';
            } else {
                try {
                    insert($pdo, 'commands', [
                        'id' => gen_uuid(),
                        'device_id' => $deviceId,
                        'message' => $message,
                        'status' => 'pending',
                    ]);
                    $flash = 'Warning sent to selected device.';
                } catch (Exception $e) {
                    $flash_type = 'error';
                    $flash = 'Unable to send command: ' . $e->getMessage();
                }
            }
        }
    }
}

$devices = fetch_all($pdo, 'SELECT * FROM devices ORDER BY last_heartbeat DESC');
$commands = fetch_all($pdo, 'SELECT c.*, d.device_name FROM commands c LEFT JOIN devices d ON c.device_id = d.id ORDER BY created_at DESC LIMIT 20');
$pending = fetch_count($pdo, "SELECT COUNT(*) AS total FROM commands WHERE status = 'pending'");
$sent = fetch_count($pdo, "SELECT COUNT(*) AS total FROM commands WHERE status = 'sent'");
$delivered = fetch_count($pdo, "SELECT COUNT(*) AS total FROM commands WHERE status = 'delivered'");

render_header('commands', 'Command Center', 'Send warning messages to connected devices.');
?>

<?php if ($flash !== ''): ?>
    <div class="message-box <?php echo $flash_type === 'error' ? 'message-error' : 'message-success'; ?>">
        <?php echo htmlspecialchars($flash); ?>
    </div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <h2>CONNECTED DEVICES</h2>
        <div class="value"><?php echo count($devices); ?></div>
        <div class="desc">Available targets</div>
    </div>
    <div class="stat-card alert">
        <h2>PENDING</h2>
        <div class="value" id="commands-pending"><?php echo $pending; ?></div>
        <div class="desc">Waiting delivery</div>
    </div>
    <div class="stat-card">
        <h2>SENT</h2>
        <div class="value" id="commands-sent"><?php echo $sent; ?></div>
        <div class="desc">Dispatched commands</div>
    </div>
    <div class="stat-card">
        <h2>DELIVERED</h2>
        <div class="value" id="commands-delivered"><?php echo $delivered; ?></div>
        <div class="desc">Confirmed on agent</div>
    </div>
</div>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3>Warning Dispatch</h3>
            <p class="panel-meta">Select a device and push a warning message to it.</p>
        </div>
        <span class="status-pill"><?php echo count($commands); ?> recent</span>
    </div>

    <form method="post" class="form-card">
        <input type="hidden" name="action" value="send_command">
        <div class="form-row">
            <label>
                Select device
                <select name="device_id" required>
                    <option value="">Choose device</option>
                    <?php foreach ($devices as $device): ?>
                        <option value="<?php echo htmlspecialchars($device['id']); ?>"><?php echo htmlspecialchars($device['device_name'] ?: $device['id']); ?> - <?php echo htmlspecialchars($device['ip_address'] ?? 'N/A'); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Warning message
                <textarea name="message" required placeholder="Enter the warning text for the agent to forward...">Warning: Access to this website is restricted by Sentinel security policy. Please close the site and contact the administrator if you need access.</textarea>
            </label>
            <button type="submit" class="button">Send Warning</button>
        </div>
    </form>

    <?php if (count($commands) === 0): ?>
        <div class="activity-card" data-empty-commands>No commands have been dispatched yet.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table" id="commands-table">
                <thead>
                    <tr>
                        <th>Device</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($commands as $command): ?>
                        <tr data-command-id="<?php echo htmlspecialchars($command['id']); ?>">
                            <td><?php echo htmlspecialchars($command['device_name'] ?? $command['device_id']); ?></td>
                            <td><?php echo htmlspecialchars($command['message']); ?></td>
                            <td><span class="status-pill <?php echo $command['status'] === 'delivered' ? 'pill-high' : ($command['status'] === 'sent' ? 'pill-medium' : 'pill-low'); ?>"><?php echo htmlspecialchars(ucfirst($command['status'])); ?></span></td>
                            <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($command['created_at']))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php render_footer();
