<?php
/**
 * Alerts Page - Alert Management and Export
 */

$alerts = fetch_all($pdo, 'SELECT a.*, d.device_name, d.ip_address FROM alerts a JOIN devices d ON a.device_id = d.id ORDER BY a.timestamp DESC LIMIT 500');
$totalAlerts = count($alerts);
$severityCount = [
    'critical' => count(array_filter($alerts, fn($a) => $a['severity'] === 'critical')),
    'high' => count(array_filter($alerts, fn($a) => $a['severity'] === 'high')),
    'medium' => count(array_filter($alerts, fn($a) => $a['severity'] === 'medium')),
    'low' => count(array_filter($alerts, fn($a) => $a['severity'] === 'low')),
];

?>

<div class="page-header">
    <h2><i class="bi bi-exclamation-triangle"></i> Alerts</h2>
    <div>
        <button class="btn btn-outline-danger btn-sm" id="exportCsvBtn" disabled style="margin-right: 10px;">
            <i class="bi bi-download"></i> Export Selected
        </button>
        <button class="btn btn-outline-secondary btn-sm" id="selectAllBtn">
            <i class="bi bi-check2-all"></i> Select All
        </button>
    </div>
</div>

<!-- Statistics -->
<div class="stat-grid">
    <div class="stat-card alert">
        <div class="stat-label">Critical</div>
        <div class="stat-value" style="color: #dc3545;"><?= $severityCount['critical'] ?></div>
        <small class="text-muted">Immediate action needed</small>
    </div>

    <div class="stat-card alert">
        <div class="stat-label">High</div>
        <div class="stat-value" style="color: #fd7e14;"><?= $severityCount['high'] ?></div>
        <small class="text-muted">Urgent attention</small>
    </div>

    <div class="stat-card">
        <div class="stat-label">Medium</div>
        <div class="stat-value"><?= $severityCount['medium'] ?></div>
        <small class="text-muted">Review recommended</small>
    </div>

    <div class="stat-card">
        <div class="stat-label">Total Alerts</div>
        <div class="stat-value"><?= $totalAlerts ?></div>
        <small class="text-muted">All threat events</small>
    </div>
</div>

<!-- Alerts Table -->
<div class="table-wrapper">
    <div style="padding: 20px; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center;">
        <h5 class="mb-0"><i class="bi bi-list-ul"></i> Alert History</h5>
        <span id="selectedCount" class="badge bg-info" style="display: none;">0 selected</span>
    </div>

    <?php if (empty($alerts)): ?>
        <div class="empty-state">
            <i class="bi bi-check-circle"></i>
            <p><strong>No alerts yet</strong></p>
            <p>Alerts will appear here when threats are detected</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover" id="alertsTable">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" id="selectAllCheckbox" class="form-check-input">
                        </th>
                        <th><i class="bi bi-laptop"></i> Device</th>
                        <th><i class="bi bi-globe"></i> Domain</th>
                        <th><i class="bi bi-clock"></i> Timestamp</th>
                        <th><i class="bi bi-exclamation-circle"></i> Severity</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alerts as $alert): ?>
                        <?php
                        $severityBadgeClass = match($alert['severity']) {
                            'critical' => 'danger',
                            'high' => 'warning',
                            'medium' => 'info',
                            default => 'secondary'
                        };
                        ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="form-check-input alert-checkbox" value="<?= htmlspecialchars($alert['id'], ENT_QUOTES) ?>" data-alert-id="<?= htmlspecialchars($alert['id'], ENT_QUOTES) ?>">
                            </td>
                            <td><strong><?= htmlspecialchars($alert['device_name']) ?></strong></td>
                            <td><code><?= htmlspecialchars($alert['domain']) ?></code></td>
                            <td><small><?= $alert['timestamp'] ?></small></td>
                            <td><span class="badge bg-<?= $severityBadgeClass ?>"><?= htmlspecialchars($alert['severity']) ?></span></td>
                            <td><small><?= htmlspecialchars(substr($alert['message'] ?? '', 0, 50)) ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
const alertCheckboxes = document.querySelectorAll('.alert-checkbox');
const selectAllCheckbox = document.getElementById('selectAllCheckbox');
const exportCsvBtn = document.getElementById('exportCsvBtn');
const selectedCountSpan = document.getElementById('selectedCount');
const selectAllBtn = document.getElementById('selectAllBtn');

function updateExportButton() {
    const selectedCount = document.querySelectorAll('.alert-checkbox:checked').length;
    exportCsvBtn.disabled = selectedCount === 0;
    if (selectedCount > 0) {
        selectedCountSpan.style.display = 'inline-block';
        selectedCountSpan.textContent = `${selectedCount} selected`;
    } else {
        selectedCountSpan.style.display = 'none';
    }
}

alertCheckboxes.forEach(checkbox => {
    checkbox.addEventListener('change', () => {
        updateExportButton();
        const allChecked = Array.from(alertCheckboxes).every(cb => cb.checked);
        const anyChecked = Array.from(alertCheckboxes).some(cb => cb.checked);
        selectAllCheckbox.checked = allChecked;
        selectAllCheckbox.indeterminate = anyChecked && !allChecked;
    });
});

selectAllCheckbox.addEventListener('change', () => {
    const isChecked = selectAllCheckbox.checked;
    alertCheckboxes.forEach(checkbox => {
        checkbox.checked = isChecked;
    });
    updateExportButton();
});

selectAllBtn.addEventListener('click', () => {
    selectAllCheckbox.checked = !selectAllCheckbox.checked;
    selectAllCheckbox.dispatchEvent(new Event('change'));
});

exportCsvBtn.addEventListener('click', () => {
    const selectedAlerts = Array.from(document.querySelectorAll('.alert-checkbox:checked')).map(cb => cb.value);
    if (selectedAlerts.length === 0) {
        Swal.fire('No Selection', 'Please select alerts to export', 'warning');
        return;
    }

    const data = { alert_ids: selectedAlerts };
    
    // POST request for CSV export
    fetch('/backend/api/endpoints/export-alerts-csv.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => {
        if (response.ok) {
            return response.blob();
        }
        throw new Error('Export failed');
    })
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `alerts_${new Date().toISOString().slice(0,10)}.csv`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        a.remove();
        Swal.fire('Success!', 'Alerts exported to CSV', 'success');
    })
    .catch(error => {
        Swal.fire('Error', 'Failed to export alerts: ' + error.message, 'error');
    });
});
</script>
