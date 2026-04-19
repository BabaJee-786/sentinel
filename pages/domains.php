<?php
/**
 * Domains Page - Domain Management
 */

$domains = fetch_all($pdo, 'SELECT * FROM domains ORDER BY domain ASC');
$totalDomains = count($domains);
$liveDomains = count(array_filter($domains, fn($d) => $d['status'] === 'live' || $d['status'] === 'restricted'));
$pausedDomains = count(array_filter($domains, fn($d) => $d['status'] === 'paused'));

?>

<div class="page-header">
    <h2><i class="bi bi-globe"></i> Domain Management</h2>
    <button class="btn btn-primary btn-sm btn-action" data-bs-toggle="modal" data-bs-target="#addDomainModal">
        <i class="bi bi-plus-lg"></i> Add Domain
    </button>
</div>

<!-- Statistics -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-label">Total Domains</div>
        <div class="stat-value"><?= $totalDomains ?></div>
        <small class="text-muted">Managed restrictions</small>
    </div>

    <div class="stat-card alert">
        <div class="stat-label">Live (Blocking)</div>
        <div class="stat-value"><?= $liveDomains ?></div>
        <small class="text-muted">Actively blocked</small>
    </div>

    <div class="stat-card success">
        <div class="stat-label">Paused</div>
        <div class="stat-value"><?= $pausedDomains ?></div>
        <small class="text-muted">Temporarily disabled</small>
    </div>
</div>

<!-- Domains Table -->
<div class="table-wrapper">
    <div style="padding: 20px; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center;">
        <h5 class="mb-0"><i class="bi bi-list-ul"></i> All Domains</h5>
        <span class="badge bg-secondary"><?= $totalDomains ?> domains</span>
    </div>

    <?php if (empty($domains)): ?>
        <div class="empty-state">
            <i class="bi bi-globe"></i>
            <p><strong>No domains configured yet</strong></p>
            <p>Add restricted domains to start blocking access</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th><i class="bi bi-globe"></i> Domain</th>
                        <th><i class="bi bi-tag"></i> Category</th>
                        <th><i class="bi bi-info-circle"></i> Reason</th>
                        <th><i class="bi bi-toggle2-on"></i> Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($domains as $domain): ?>
                        <?php $isLive = $domain['status'] === 'live' || $domain['status'] === 'restricted'; ?>
                        <tr>
                            <td><code><?= htmlspecialchars($domain['domain']) ?></code></td>
                            <td><small><?= htmlspecialchars($domain['category'] ?? 'N/A') ?></small></td>
                            <td><small><?= htmlspecialchars(substr($domain['reason'] ?? '', 0, 40)) ?></small></td>
                            <td>
                                <span class="badge-status <?= $isLive ? 'badge-live' : 'badge-paused' ?>">
                                    <?= $isLive ? 'Live' : 'Paused' ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-outline-warning" onclick="toggleDomainStatus('<?= htmlspecialchars($domain['id'], ENT_QUOTES) ?>', '<?= htmlspecialchars($domain['domain'], ENT_QUOTES) ?>', '<?= $isLive ? 'paused' : 'live' ?>')">
                                        <i class="bi <?= $isLive ? 'bi-pause' : 'bi-play' ?>"></i> <?= $isLive ? 'Pause' : 'Resume' ?>
                                    </button>
                                    <button class="btn btn-sm btn-outline-info" onclick="editDomain('<?= htmlspecialchars($domain['id'], ENT_QUOTES) ?>', '<?= htmlspecialchars($domain['domain'], ENT_QUOTES) ?>', '<?= htmlspecialchars($domain['category'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($domain['reason'] ?? '', ENT_QUOTES) ?>')">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="manageDomainDevices('<?= htmlspecialchars($domain['id'], ENT_QUOTES) ?>', '<?= htmlspecialchars($domain['domain'], ENT_QUOTES) ?>')">
                                        <i class="bi bi-people"></i> Assign
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteDomain('<?= htmlspecialchars($domain['id'], ENT_QUOTES) ?>', '<?= htmlspecialchars($domain['domain'], ENT_QUOTES) ?>')">
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

<!-- Add Domain Modal -->
<div class="modal fade" id="addDomainModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-lg"></i> Add Domain</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addDomainForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="domainName" class="form-label">Domain</label>
                        <input type="text" class="form-control" id="domainName" name="domain" placeholder="example.com" required>
                    </div>
                    <div class="mb-3">
                        <label for="domainCategory" class="form-label">Category</label>
                        <input type="text" class="form-control" id="domainCategory" name="category" placeholder="Social Media, Streaming, etc.">
                    </div>
                    <div class="mb-3">
                        <label for="domainReason" class="form-label">Reason</label>
                        <textarea class="form-control" id="domainReason" name="reason" rows="3" placeholder="Why is this domain blocked?"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Domain</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Domain Modal -->
<div class="modal fade" id="editDomainModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Domain</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editDomainForm">
                <input type="hidden" id="editDomainId" name="domain_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editDomainName" class="form-label">Domain</label>
                        <input type="text" class="form-control" id="editDomainName" name="domain" required disabled>
                    </div>
                    <div class="mb-3">
                        <label for="editDomainCategory" class="form-label">Category</label>
                        <input type="text" class="form-control" id="editDomainCategory" name="category">
                    </div>
                    <div class="mb-3">
                        <label for="editDomainReason" class="form-label">Reason</label>
                        <textarea class="form-control" id="editDomainReason" name="reason" rows="3"></textarea>
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

<!-- Manage Domain Devices Modal -->
<div class="modal fade" id="manageDomainDevicesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-people"></i> <span id="devicesDomainName"></span> - Device Access</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="manageDomainDevicesForm">
                <input type="hidden" id="manageDomainDeviceId" name="domain_id">
                <div class="modal-body" id="devicesList">
                    <p class="text-muted">Loading devices...</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Permissions</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editDomain(id, domain, category, reason) {
    document.getElementById('editDomainId').value = id;
    document.getElementById('editDomainName').value = domain;
    document.getElementById('editDomainCategory').value = category;
    document.getElementById('editDomainReason').value = reason;
    new bootstrap.Modal(document.getElementById('editDomainModal')).show();
}

function toggleDomainStatus(id, domain, newStatus) {
    const action = newStatus === 'live' ? 'Resume' : 'Pause';
    Swal.fire({
        title: `${action} Domain?`,
        text: `${action} blocking for "${domain}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, ' + action.toLowerCase() + ' it!'
    }).then((result) => {
        if (result.isConfirmed) {
            apiCall('/backend/api/endpoints/toggle-domain.php', {domain_id: id, status: newStatus}, (response) => {
                Swal.fire('Updated!', `Domain ${action.toLowerCase()}ed successfully.`, 'success');
                setTimeout(() => location.reload(), 1000);
            });
        }
    });
}

function deleteDomain(id, domain) {
    Swal.fire({
        title: 'Delete Domain?',
        text: `Delete "${domain}"? This cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            apiCall('/backend/api/endpoints/delete-domain.php', {domain_id: id}, (response) => {
                Swal.fire('Deleted!', 'Domain has been deleted.', 'success');
                setTimeout(() => location.reload(), 1000);
            });
        }
    });
}

function manageDomainDevices(id, domain) {
    document.getElementById('manageDomainDeviceId').value = id;
    document.getElementById('devicesDomainName').textContent = domain;
    document.getElementById('devicesList').innerHTML = '<p class="text-muted">Loading...</p>';

    fetch(`/backend/api/endpoints/fetch-permissions.php?domain_id=${encodeURIComponent(id)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const permissions = data.permissions;
                let html = '';
                permissions.forEach(perm => {
                    html += `
                        <div class="checkbox-label">
                            <input type="checkbox" class="form-check-input device-permission" 
                                   value="${perm.id}" 
                                   data-device-id="${perm.id}"
                                   ${perm.is_allowed ? 'checked' : ''}>
                            <span>${perm.device_name} (${perm.ip_address})</span>
                        </div>
                    `;
                });
                document.getElementById('devicesList').innerHTML = html || '<p class="text-muted">No devices available</p>';
            }
        });

    new bootstrap.Modal(document.getElementById('manageDomainDevicesModal')).show();
}

document.getElementById('addDomainForm').addEventListener('submit', (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = {
        domain: formData.get('domain'),
        category: formData.get('category'),
        reason: formData.get('reason'),
        status: 'live'
    };

    apiCall('/backend/api/endpoints/update-domain.php', data, (response) => {
        Swal.fire('Added!', 'Domain added successfully.', 'success');
        setTimeout(() => location.reload(), 1000);
    });
});

document.getElementById('editDomainForm').addEventListener('submit', (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = {
        domain_id: formData.get('domain_id'),
        category: formData.get('category'),
        reason: formData.get('reason')
    };

    apiCall('/backend/api/endpoints/update-domain.php', data, (response) => {
        Swal.fire('Updated!', 'Domain updated successfully.', 'success');
        setTimeout(() => location.reload(), 1000);
    });
});

document.getElementById('manageDomainDevicesForm').addEventListener('submit', (e) => {
    e.preventDefault();
    const domainId = document.getElementById('manageDomainDeviceId').value;
    const devices = [];

    document.querySelectorAll('.device-permission').forEach(checkbox => {
        devices.push({
            device_id: checkbox.dataset.deviceId,
            is_allowed: checkbox.checked
        });
    });

    const data = { domain_id: domainId, devices };
    apiCall('/backend/api/endpoints/assign-domain-device.php', data, (response) => {
        Swal.fire('Saved!', 'Device permissions updated.', 'success');
        setTimeout(() => location.reload(), 1000);
    });
});
</script>
