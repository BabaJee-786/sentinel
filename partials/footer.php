<?php
function render_footer(): void {
    ?>
        </main>
    </div>
    <script>
    (function () {
        const body = document.body;
        const feedUrl = body?.dataset?.alertFeed;
        if (!feedUrl) {
            return;
        }

        const pageName = body.dataset.page || '';
        let lastAlertTimestamp = body.dataset.lastAlert || '';
        const toastStack = document.getElementById('toast-stack');
        const counter = document.getElementById('notification-count');
        const refreshEl = document.getElementById('last-refresh');
        const dashboardTableBody = document.querySelector('#dashboard-alerts tbody');
        const alertsTableBody = document.querySelector('#alerts-table tbody');
        const commandsTableBody = document.querySelector('#commands-table tbody');
        const commandsEmptyState = document.querySelector('[data-empty-commands]');
        const commandsPending = document.getElementById('commands-pending');
        const commandsSent = document.getElementById('commands-sent');
        const commandsDelivered = document.getElementById('commands-delivered');
        const devicesTableBody = document.querySelector('#devices-table tbody');
        const dashboardDevicesBody = document.querySelector('#dashboard-devices tbody');
        const devicesTotal = document.getElementById('devices-total');
        const devicesOnline = document.getElementById('devices-online');
        const devicesRecords = document.getElementById('devices-records');
        const dashboardDevicesTotal = document.getElementById('dashboard-devices-total');
        const dashboardDevicesOnline = document.getElementById('dashboard-devices-online');
        const dashboardDeviceRecords = document.getElementById('dashboard-device-records');
        const emptyStates = document.querySelectorAll('[data-empty-alerts]');
        const seenAlertIds = new Set();

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function severityClass(severity) {
            const allowed = ['low', 'medium', 'high', 'critical'];
            return allowed.includes(severity) ? severity : 'medium';
        }

        function formatDate(value) {
            if (!value) {
                return 'N/A';
            }

            const date = new Date(value.replace(' ', 'T'));
            if (Number.isNaN(date.getTime())) {
                return value;
            }

            return date.toLocaleString();
        }

        function updateUnreadCount(count) {
            if (counter) {
                counter.textContent = String(count ?? 0);
            }
        }

        function updateRefreshTime(value) {
            if (refreshEl && value) {
                refreshEl.textContent = value;
            }
        }

        function commandStatusClass(status) {
            if (status === 'delivered') {
                return 'pill-high';
            }
            if (status === 'sent') {
                return 'pill-medium';
            }
            return 'pill-low';
        }

        function removeEmptyState() {
            emptyStates.forEach((node) => node.remove());
        }

        document.body.addEventListener('click', async (event) => {
            const button = event.target.closest('.warn-button');
            if (!button) {
                return;
            }

            event.preventDefault();
            const deviceId = button.dataset.deviceId;
            const message = button.dataset.message || 'Warning: Access to this website is restricted by Sentinel security policy. Please close the site and contact the administrator if you need access.';
            if (!deviceId) {
                return;
            }

            button.disabled = true;
            try {
                const formData = new FormData();
                formData.set('device_id', deviceId);
                formData.set('message', message);

                const response = await fetch('warn_device.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                const result = await response.json();
                if (!response.ok || result.error) {
                    throw new Error(result.error || 'Unable to send warning');
                }

                const toast = document.createElement('div');
                toast.className = 'toast toast-low';
                toast.innerHTML = '<strong>Warning queued</strong><div>' + escapeHtml(result.message || 'The device has been alerted.') + '</div>';
                if (toastStack) {
                    toastStack.prepend(toast);
                }
                window.setTimeout(() => {
                    toast.classList.add('toast-hide');
                    window.setTimeout(() => toast.remove(), 350);
                }, 4000);
                button.textContent = 'Queued';
            } catch (error) {
                button.textContent = 'Retry';
                console.warn(error);
            } finally {
                button.disabled = false;
            }
        });

        function prependDashboardRow(alert) {
            if (!dashboardTableBody || seenAlertIds.has('dashboard-' + alert.id)) {
                return;
            }

            removeEmptyState();
            const row = document.createElement('tr');
            row.dataset.alertId = alert.id;
            row.innerHTML =
                '<td>' + escapeHtml(alert.domain) + '</td>' +
                '<td><span class="status-pill pill-' + severityClass(alert.severity) + '">' + escapeHtml((alert.severity || 'medium').toUpperCase()) + '</span></td>' +
                '<td>' + escapeHtml(formatDate(alert.timestamp)) + '</td>';
            dashboardTableBody.prepend(row);
            seenAlertIds.add('dashboard-' + alert.id);

            while (dashboardTableBody.children.length > 5) {
                dashboardTableBody.removeChild(dashboardTableBody.lastElementChild);
            }
        }

        function prependAlertsRow(alert) {
            if (!alertsTableBody || seenAlertIds.has('alerts-' + alert.id)) {
                return;
            }

            removeEmptyState();
            const row = document.createElement('tr');
            row.dataset.alertId = alert.id;
            row.innerHTML =
                '<td>' + escapeHtml(alert.domain) + '</td>' +
                '<td>' + escapeHtml(alert.device_name || alert.device_id || 'Unknown device') + '</td>' +
                '<td><span class="status-pill pill-' + severityClass(alert.severity) + '">' + escapeHtml((alert.severity || 'medium').toUpperCase()) + '</span></td>' +
                '<td>' + escapeHtml(alert.message || '') + '</td>' +
                '<td>' + escapeHtml(formatDate(alert.timestamp)) + '</td>' +
                '<td><button type="button" class="button button-small warn-button" data-device-id="' + escapeHtml(alert.device_id || '') + '" data-message="Warning: Access to this website is restricted by Sentinel security policy. Please close the site and contact the administrator if you need access.">Warn</button></td>';
            alertsTableBody.prepend(row);
            seenAlertIds.add('alerts-' + alert.id);

            while (alertsTableBody.children.length > 40) {
                alertsTableBody.removeChild(alertsTableBody.lastElementChild);
            }
        }

        function showToast(alert) {
            if (!toastStack) {
                return;
            }

            const toast = document.createElement('div');
            toast.className = 'toast toast-' + severityClass(alert.severity);
            toast.innerHTML =
                '<strong>Restricted domain detected</strong>' +
                '<div>' + escapeHtml(alert.domain) + '</div>' +
                '<p>' + escapeHtml(alert.message || 'Blocked browsing attempt detected.') + '</p>';
            toastStack.prepend(toast);

            window.setTimeout(() => {
                toast.classList.add('toast-hide');
                window.setTimeout(() => toast.remove(), 350);
            }, 6000);
        }

        function renderCommands(payload) {
            if (!commandsTableBody) {
                return;
            }

            if (commandsPending) {
                commandsPending.textContent = String(payload.pending ?? 0);
            }
            if (commandsSent) {
                commandsSent.textContent = String(payload.sent ?? 0);
            }
            if (commandsDelivered) {
                commandsDelivered.textContent = String(payload.delivered ?? 0);
            }

            const commands = Array.isArray(payload.commands) ? payload.commands : [];
            if (!commands.length) {
                if (commandsEmptyState) {
                    commandsEmptyState.hidden = false;
                }
                commandsTableBody.innerHTML = '';
                return;
            }

            if (commandsEmptyState) {
                commandsEmptyState.hidden = true;
            }

            commandsTableBody.innerHTML = commands.map((command) => {
                const created = formatDate(command.created_at);
                const status = String(command.status || 'pending').toLowerCase();
                return (
                    '<tr data-command-id="' + escapeHtml(command.id) + '">' +
                        '<td>' + escapeHtml(command.device_name || command.device_id) + '</td>' +
                        '<td>' + escapeHtml(command.message || '') + '</td>' +
                        '<td><span class="status-pill ' + commandStatusClass(status) + '">' + escapeHtml(status.charAt(0).toUpperCase() + status.slice(1)) + '</span></td>' +
                        '<td>' + escapeHtml(created) + '</td>' +
                    '</tr>'
                );
            }).join('');
        }

        function renderDeviceRows(targetBody, devices) {
            if (!targetBody) {
                return;
            }

            targetBody.innerHTML = devices.map((device) => {
                const lastHeartbeat = device.last_heartbeat ? formatDate(device.last_heartbeat) : 'N/A';
                const isOnline = Boolean(device.is_online);
                const statusClass = isOnline ? 'pill-high' : 'pill-low';
                const statusText = isOnline ? 'Online' : 'Offline';

                if (targetBody === devicesTableBody) {
                    return (
                        '<tr>' +
                            '<td>' + escapeHtml(device.device_name || device.id) + '</td>' +
                            '<td>' + escapeHtml(device.ip_address || 'Unknown') + '</td>' +
                            '<td>' + escapeHtml(device.device_type || 'Unknown') + '</td>' +
                            '<td>' + escapeHtml(lastHeartbeat) + '</td>' +
                            '<td><span class="status-pill ' + statusClass + '">' + statusText + '</span></td>' +
                        '</tr>'
                    );
                }

                return (
                    '<tr>' +
                        '<td>' + escapeHtml(device.device_name || device.id) + '</td>' +
                        '<td>' + escapeHtml(device.ip_address || 'N/A') + '</td>' +
                        '<td>' + escapeHtml(lastHeartbeat) + '</td>' +
                        '<td><span class="status-pill ' + statusClass + '">' + statusText + '</span></td>' +
                    '</tr>'
                );
            }).join('');
        }

        function renderDevices(payload) {
            if (devicesTotal) {
                devicesTotal.textContent = String(payload.total ?? 0);
            }
            if (devicesOnline) {
                devicesOnline.textContent = String(payload.online ?? 0);
            }
            if (devicesRecords) {
                devicesRecords.textContent = String((payload.devices || []).length);
            }
            if (dashboardDevicesTotal) {
                dashboardDevicesTotal.textContent = String(payload.total ?? 0);
            }
            if (dashboardDevicesOnline) {
                dashboardDevicesOnline.textContent = String(payload.online ?? 0);
            }
            if (dashboardDeviceRecords) {
                dashboardDeviceRecords.textContent = String((payload.recent_devices || []).length);
            }

            if (devicesTableBody) {
                renderDeviceRows(devicesTableBody, Array.isArray(payload.devices) ? payload.devices : []);
            }
            if (dashboardDevicesBody) {
                renderDeviceRows(dashboardDevicesBody, Array.isArray(payload.recent_devices) ? payload.recent_devices : []);
            }
        }

        document.querySelectorAll('[data-alert-id]').forEach((row) => {
            const id = row.getAttribute('data-alert-id');
            if (!id) {
                return;
            }
            if (row.closest('#dashboard-alerts')) {
                seenAlertIds.add('dashboard-' + id);
            }
            if (row.closest('#alerts-table')) {
                seenAlertIds.add('alerts-' + id);
            }
        });

        async function pollAlerts() {
            try {
                const url = new URL(feedUrl, window.location.href);
                if (lastAlertTimestamp) {
                    url.searchParams.set('since', lastAlertTimestamp);
                }

                const response = await fetch(url.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    cache: 'no-store'
                });

                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                updateUnreadCount(payload.unread_count);
                updateRefreshTime(payload.server_time ? payload.server_time.slice(11) : '');

                const alerts = Array.isArray(payload.alerts) ? payload.alerts : [];
                if (alerts.length) {
                    alerts.forEach((alert) => {
                        if (pageName !== 'alerts') {
                            showToast(alert);
                        }
                        prependDashboardRow(alert);
                        prependAlertsRow(alert);
                    });
                }

                if (payload.latest_timestamp) {
                    lastAlertTimestamp = payload.latest_timestamp;
                    body.dataset.lastAlert = payload.latest_timestamp;
                }
            } catch (error) {
                console.warn('Unable to fetch live alerts', error);
            }
        }

        async function pollCommands() {
            if (pageName !== 'commands') {
                return;
            }

            try {
                const response = await fetch('command_feed.php', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    cache: 'no-store'
                });

                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                renderCommands(payload);
                updateRefreshTime(payload.server_time ? payload.server_time.slice(11) : '');
            } catch (error) {
                console.warn('Unable to fetch live commands', error);
            }
        }

        async function pollDevices() {
            if (pageName !== 'devices' && pageName !== 'dashboard') {
                return;
            }

            try {
                const response = await fetch('device_feed.php', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    cache: 'no-store'
                });

                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                renderDevices(payload);
                updateRefreshTime(payload.server_time ? payload.server_time.slice(11) : '');
            } catch (error) {
                console.warn('Unable to fetch live devices', error);
            }
        }

        pollAlerts();
        pollCommands();
        pollDevices();
        window.setInterval(pollAlerts, 5000);
        window.setInterval(pollCommands, 4000);
        window.setInterval(pollDevices, 5000);
    }());
    </script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}
