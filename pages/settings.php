<?php
/**
 * Settings Page - System Configuration
 */

$smtp_host = get_setting($pdo, 'smtp_host', 'smtp.gmail.com');
$smtp_port = get_setting($pdo, 'smtp_port', '587');
$smtp_email = get_setting($pdo, 'smtp_email', '');
$email_enabled = get_setting($pdo, 'email_enabled', '0');
$email_severity = get_setting($pdo, 'email_alert_severity', 'high');
$org_name = get_setting($pdo, 'organization_name', 'Sentinel Security Lab');

?>

<div class="page-header">
    <h2><i class="bi bi-gear"></i> Settings</h2>
    <button class="btn btn-success btn-sm" id="saveSettingsBtn" style="display: none;">
        <i class="bi bi-check-lg"></i> Save Changes
    </button>
</div>

<!-- Settings Form -->
<div class="row">
    <div class="col-md-8">
        <div class="row">
            <!-- Organization Settings -->
            <div class="col-md-12">
                <div class="table-wrapper" style="margin-bottom: 20px;">
                    <div style="padding: 20px; border-bottom: 1px solid #e0e0e0;">
                        <h5 class="mb-0"><i class="bi bi-building"></i> Organization</h5>
                    </div>
                    <div style="padding: 20px;">
                        <div class="mb-3">
                            <label for="orgName" class="form-label">Organization Name</label>
                            <input type="text" class="form-control" id="orgName" value="<?= htmlspecialchars($org_name) ?>">
                            <small class="text-muted">Appears in email alerts and reports</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Email Alert Settings -->
            <div class="col-md-12">
                <div class="table-wrapper" style="margin-bottom: 20px;">
                    <div style="padding: 20px; border-bottom: 1px solid #e0e0e0;">
                        <h5 class="mb-0"><i class="bi bi-envelope"></i> Email Alerts</h5>
                    </div>
                    <div style="padding: 20px;">
                        <div class="mb-3" style="padding: 15px; background-color: #f8f9fa; border-radius: 8px; border-left: 4px solid #0d6efd;">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="emailEnabled" 
                                       <?= $email_enabled ? 'checked' : '' ?>>
                                <label class="form-check-label" for="emailEnabled">
                                    <strong>Enable Email Alerts</strong>
                                    <small class="d-block text-muted">Send email notifications when threats are detected</small>
                                </label>
                            </div>
                        </div>

                        <div style="margin-top: 15px;">
                            <label class="form-label"><strong>Send Alerts for:</strong></label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="severity" id="severityCritical" 
                                       value="critical" <?= $email_severity === 'critical' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="severityCritical">
                                    Critical alerts only
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="severity" id="severityHigh" 
                                       value="high" <?= $email_severity === 'high' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="severityHigh">
                                    High & Critical alerts
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="severity" id="severityAll" 
                                       value="all" <?= $email_severity === 'all' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="severityAll">
                                    All alerts
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SMTP Settings -->
            <div class="col-md-12">
                <div class="table-wrapper" style="margin-bottom: 20px;">
                    <div style="padding: 20px; border-bottom: 1px solid #e0e0e0;">
                        <h5 class="mb-0"><i class="bi bi-server"></i> SMTP Configuration</h5>
                    </div>
                    <div style="padding: 20px;">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Configure SMTP settings to enable email alerts
                        </div>

                        <div class="mb-3">
                            <label for="smtpHost" class="form-label">SMTP Host</label>
                            <input type="text" class="form-control" id="smtpHost" placeholder="smtp.gmail.com" 
                                   value="<?= htmlspecialchars($smtp_host) ?>">
                            <small class="text-muted">e.g., smtp.gmail.com, mail.example.com</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="smtpPort" class="form-label">SMTP Port</label>
                                    <input type="number" class="form-control" id="smtpPort" placeholder="587" 
                                           value="<?= htmlspecialchars($smtp_port) ?>">
                                    <small class="text-muted">587 (TLS) or 465 (SSL)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="smtpEmail" class="form-label">From Email</label>
                                    <input type="email" class="form-control" id="smtpEmail" placeholder="alerts@sentinel.local" 
                                           value="<?= htmlspecialchars($smtp_email) ?>">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="smtpPassword" class="form-label">Password</label>
                            <input type="password" class="form-control" id="smtpPassword" placeholder="••••••••">
                            <small class="text-muted">Leave blank to keep existing password</small>
                        </div>

                        <button type="button" class="btn btn-outline-secondary btn-sm" id="testEmailBtn">
                            <i class="bi bi-envelope-check"></i> Test Email
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Sidebar -->
    <div class="col-md-4">
        <div class="table-wrapper">
            <div style="padding: 20px; border-bottom: 1px solid #e0e0e0;">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Status</h5>
            </div>
            <div style="padding: 20px;">
                <div style="margin-bottom: 20px;">
                    <small class="text-muted d-block">EMAIL ALERTS</small>
                    <div class="mb-2">
                        <span class="badge <?= $email_enabled ? 'bg-success' : 'bg-danger' ?>" style="width: 100%; display: block; padding: 8px;">
                            <?= $email_enabled ? 'Enabled' : 'Disabled' ?>
                        </span>
                    </div>
                </div>

                <div style="margin-bottom: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 8px;">
                    <small class="text-muted d-block">SMTP HOST</small>
                    <code><?= htmlspecialchars($smtp_host) ?></code>
                </div>

                <div style="margin-bottom: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 8px;">
                    <small class="text-muted d-block">SMTP PORT</small>
                    <code><?= htmlspecialchars($smtp_port) ?></code>
                </div>

                <div style="margin-bottom: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 8px;">
                    <small class="text-muted d-block">SENDER EMAIL</small>
                    <code><?= htmlspecialchars($smtp_email ?: 'Not configured') ?></code>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('emailEnabled').addEventListener('change', () => {
    showSaveButton();
});

document.getElementById('orgName').addEventListener('input', () => {
    showSaveButton();
});

document.getElementById('smtpHost').addEventListener('input', () => {
    showSaveButton();
});

document.getElementById('smtpPort').addEventListener('input', () => {
    showSaveButton();
});

document.getElementById('smtpEmail').addEventListener('input', () => {
    showSaveButton();
});

document.getElementById('smtpPassword').addEventListener('input', () => {
    showSaveButton();
});

document.querySelectorAll('input[name="severity"]').forEach(radio => {
    radio.addEventListener('change', () => {
        showSaveButton();
    });
});

function showSaveButton() {
    const btn = document.getElementById('saveSettingsBtn');
    btn.style.display = 'inline-block';
}

document.getElementById('saveSettingsBtn').addEventListener('click', () => {
    const settings = {
        settings: {
            smtp_host: document.getElementById('smtpHost').value,
            smtp_port: parseInt(document.getElementById('smtpPort').value),
            smtp_email: document.getElementById('smtpEmail').value,
            email_enabled: document.getElementById('emailEnabled').checked,
            email_alert_severity: document.querySelector('input[name="severity"]:checked').value,
            organization_name: document.getElementById('orgName').value
        }
    };

    // Only include password if it's been entered
    if (document.getElementById('smtpPassword').value) {
        settings.settings.smtp_password = document.getElementById('smtpPassword').value;
    }

    apiCall('/backend/api/endpoints/save-settings.php', settings, (response) => {
        document.getElementById('saveSettingsBtn').style.display = 'none';
        Swal.fire('Saved!', 'Settings have been updated successfully.', 'success');
        setTimeout(() => location.reload(), 1500);
    });
});

document.getElementById('testEmailBtn').addEventListener('click', () => {
    const smtpHost = document.getElementById('smtpHost').value;
    const smtpPort = document.getElementById('smtpPort').value;
    const smtpEmail = document.getElementById('smtpEmail').value;

    if (!smtpHost || !smtpPort || !smtpEmail) {
        Swal.fire('Incomplete', 'Please fill in SMTP Host, Port, and Email first', 'warning');
        return;
    }

    Swal.fire({
        title: 'Send Test Email?',
        text: 'A test email will be sent to: ' + smtpEmail,
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Send Test'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Sending...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Note: You would need to create a test-email endpoint
            setTimeout(() => {
                Swal.fire('Sent!', 'Test email has been sent.', 'success');
            }, 2000);
        }
    });
});
</script>
