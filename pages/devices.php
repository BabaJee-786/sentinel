<?php
/**
 * Devices Page - Device Management
 */

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

?>

<div class="page-header">
    <h2><i class="bi bi-laptop"></i> Device Management</h2>
    <a href="#" class="btn btn-primary btn-sm btn-action" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
        <i class="bi bi-plus-lg"></i> Add Device
    </a>
</div>

<!-- Statistics -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-label">Total Devices</div>
        <div class="stat-value"><?= $totalDevices ?></div>
        <small class="text-muted"><span><?= $onlineDevices ?></span> online</small>
    </div>

    <div class="stat-card success">
        <div class="stat-label">Active Devices</div>
        <div class="stat-value"><?= count(array_filter($devices, fn($d) => $d['status'] === 'active')) ?></div>
        <small class="text-muted">Monitoring enabled</small>
    </div>

    <div class="stat-card alert">
        <div class="stat-label">Disabled Devices</div>
        <div class="stat-value"><?= count(array_filter($devices, fn($d) => $d['status'] === 'disabled')) ?></div>
        <small class="text-muted">Not monitoring</small>
    </div>
</div>

<!-- Devices Table -->
<div class="table-wrapper">
    <div style="padding: 20px; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center;">
        <h5 class="mb-0"><i class="bi bi-list-ul"></i> All Devices</h5>
        <span class="badge bg-secondary"><?= $totalDevices ?> devices</span>
    </div>

    <?php if (empty($devices)): ?>
        <div class="empty-state">
            <i class="bi bi-laptop"></i>
            <p><strong>No devices registered yet</strong></p>
            <p>Devices will appear here when agents connect to the system</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th><i class="bi bi-laptop"></i> Device Name</th>
                        <th><i class="bi bi-globe"></i> IP Address</th>
                        <th><i class="bi bi-window"></i> Type</th>
                        <th><i class="bi bi-clock"></i> Last Heartbeat</th>
                        <th><i class="bi bi-activity"></i> Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($devices as $device): ?>
                        <?php
                        $online = ($device['last_heartbeat'] ?? null) && strtotime($device['last_heartbeat']) >= $onlineThreshold;
                        $deviceStatus = $device['status'] ?? 'active';
                        $deviceStatusBadge = $deviceStatus === 'active' ? 'bg-success' : 'bg-danger';
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($device['device_name'] ?: 'Unknown Device') ?></strong></td>
                            <td><code><?= htmlspecialchars($device['ip_address'] ?? 'N/A') ?></code></td>
                            <td><?= htmlspecialchars($device['device_type'] ?? 'Unknown') ?></td>
                            <td><small><?= $device['last_heartbeat'] ?? 'Never' ?></small></td>
                            <td>
                                <div class="d-flex gap-2">
                                    <span class="badge-status <?= $online ? 'badge-online' : 'badge-offline' ?>">
                                        <?= $online ? 'Online' : 'Offline' ?>
                                    </span>
                                    <span class="badge <?= $deviceStatusBadge ?>" style="font-size: 0.75rem;">
                                        <?= ucfirst($deviceStatus) ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-outline-primary" onclick="editDevice('<?= htmlspecialchars($device['id'], ENT_QUOTES) ?>', '<?= htmlspecialchars($device['device_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($device['device_type'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($device['status'], ENT_QUOTES) ?>')">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteDevice('<?= htmlspecialchars($device['id'], ENT_QUOTES) ?>', '<?= htmlspecialchars($device['device_name'], ENT_QUOTES) ?>')">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Add Device Modal -->
<div class="modal fade" id="addDeviceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-lg"></i> Add Device</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addDeviceForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="deviceName" class="form-label">Device Name</label>
                        <input type="text" class="form-control" id="deviceName" name="device_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="deviceType" class="form-label">Device Type</label>
                        <select class="form-select" id="deviceType" name="device_type">
                            <option value="Windows">Windows</option>
                            <option value="Linux">Linux</option>
                            <option value="macOS">macOS</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Device</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Device Modal -->
<div class="modal fade" id="editDeviceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Device</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editDeviceForm">
                <input type="hidden" id="editDeviceId" name="device_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editDeviceName" class="form-label">Device Name</label>
                        <input type="text" class="form-control" id="editDeviceName" name="device_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="editDeviceType" class="form-label">Device Type</label>
                        <select class="form-select" id="editDeviceType" name="device_type">
                            <option value="Windows">Windows</option>
                            <option value="Linux">Linux</option>
                            <option value="macOS">macOS</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editDeviceStatus" class="form-label">Device Status</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="editDeviceStatus" name="status" value="active">
                            <label class="form-check-label" for="editDeviceStatus">
                                <span id="statusLabel">Active</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editDevice(id, name, type, status) {
    document.getElementById('editDeviceId').value = id;
    document.getElementById('editDeviceName').value = name;
    document.getElementById('editDeviceType').value = type;
    const statusCheckbox = document.getElementById('editDeviceStatus');
    statusCheckbox.checked = status === 'active';
    updateStatusLabel();
    new bootstrap.Modal(document.getElementById('editDeviceModal')).show();
}

function updateStatusLabel() {
    const checkbox = document.getElementById('editDeviceStatus');
    document.getElementById('statusLabel').textContent = checkbox.checked ? 'Active' : 'Disabled';
}

function deleteDevice(id, name) {
    Swal.fire({
        title: 'Delete Device?',
        text: `Are you sure you want to delete "${name}"? This action cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            apiCall('/backend/api/endpoints/delete-device.php', {device_id: id}, (response) => {
                Swal.fire('Deleted!', 'Device has been deleted.', 'success');
                setTimeout(() => location.reload(), 1000);
            });
        }
    });
}

document.getElementById('editDeviceStatus').addEventListener('change', updateStatusLabel);

document.getElementById('editDeviceForm').addEventListener('submit', (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = {
        device_id: formData.get('device_id'),
        device_name: formData.get('device_name'),
        device_type: formData.get('device_type'),
        status: formData.get('status') ? 'active' : 'disabled'
    };

    apiCall('/backend/api/endpoints/update-device.php', data, (response) => {
        Swal.fire('Updated!', 'Device updated successfully.', 'success');
        setTimeout(() => location.reload(), 1000);
    });
});
</script>
