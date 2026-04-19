<?php
require_once __DIR__ . '/backend/db.php';
require_once __DIR__ . '/backend/session_auth.php';
require_once __DIR__ . '/partials/header.php';
require_once __DIR__ . '/partials/footer.php';

require_login();

$flash = '';
$flash_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_domain') {
        $domain = trim($_POST['domain'] ?? '');
        $status = $_POST['status'] ?? 'restricted';
        $category = trim($_POST['category'] ?? '');
        $reason = trim($_POST['reason'] ?? '');

        if ($domain === '') {
            $flash_type = 'error';
            $flash = 'Domain value is required.';
        } else {
            try {
                $existing = fetch_one($pdo, 'SELECT id FROM domains WHERE domain = :domain', [':domain' => $domain]);
                if ($existing) {
                    update($pdo, 'domains', [
                        'status' => $status,
                        'category' => $category ?: null,
                        'reason' => $reason ?: null,
                    ], ['id' => $existing['id']]);
                    $flash = 'Domain updated successfully.';
                } else {
                    insert($pdo, 'domains', [
                        'id' => gen_uuid(),
                        'user_id' => get_authenticated_user()['id'] ?? null,
                        'domain' => $domain,
                        'status' => $status,
                        'category' => $category ?: null,
                        'reason' => $reason ?: null,
                    ]);
                    $flash = 'Domain added successfully.';
                }
            } catch (Exception $e) {
                $flash_type = 'error';
                $flash = 'Unable to save domain: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'delete_domain') {
        $domainId = $_POST['domain_id'] ?? '';
        if ($domainId !== '') {
            try {
                run_query($pdo, 'DELETE FROM domains WHERE id = :id', [':id' => $domainId]);
                $flash = 'Domain removed successfully.';
            } catch (Exception $e) {
                $flash_type = 'error';
                $flash = 'Unable to delete domain: ' . $e->getMessage();
            }
        }
    }
}

$totalDomains = fetch_count($pdo, 'SELECT COUNT(*) AS total FROM domains');
$restrictedDomains = fetch_count($pdo, 'SELECT COUNT(*) AS total FROM domains WHERE status = :status', [':status' => 'restricted']);
$allowedDomains = fetch_count($pdo, 'SELECT COUNT(*) AS total FROM domains WHERE status = :status', [':status' => 'allowed']);
$domains = fetch_all($pdo, 'SELECT * FROM domains ORDER BY created_at DESC LIMIT 40');

render_header('domains', 'Domains', 'Add, remove and manage your domain policy list.');
?>

<?php if ($flash !== ''): ?>
    <div class="message-box <?php echo $flash_type === 'error' ? 'message-error' : 'message-success'; ?>">
        <?php echo htmlspecialchars($flash); ?>
    </div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <h2>TOTAL RULES</h2>
        <div class="value"><?php echo $totalDomains; ?></div>
        <div class="desc">Domain policy entries</div>
    </div>
    <div class="stat-card">
        <h2>RESTRICTED</h2>
        <div class="value"><?php echo $restrictedDomains; ?></div>
        <div class="desc">Blocked domains</div>
    </div>
    <div class="stat-card">
        <h2>ALLOWED</h2>
        <div class="value"><?php echo $allowedDomains; ?></div>
        <div class="desc">Safe domains</div>
    </div>
</div>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3>Domain Blocklist</h3>
            <p class="panel-meta">Manage restricted, allowed, and monitored domains for your network.
            </p>
        </div>
        <span class="status-pill"><?php echo count($domains); ?> records</span>
    </div>

    <form method="post" class="form-card">
        <input type="hidden" name="action" value="add_domain">
        <div class="form-row">
            <label>
                Domain name
                <input type="text" name="domain" placeholder="example.com" required>
            </label>
            <label>
                Status
                <select name="status">
                    <option value="restricted">Restricted</option>
                    <option value="allowed">Allowed</option>
                    <option value="monitored">Monitored</option>
                </select>
            </label>
            <label>
                Category
                <input type="text" name="category" placeholder="Malware / Phishing">
            </label>
            <label>
                Reason
                <textarea name="reason" placeholder="Optional reason"></textarea>
            </label>
            <button type="submit" class="button">Add / Update Domain</button>
        </div>
    </form>

    <?php if (count($domains) === 0): ?>
        <div class="activity-card">No domain entries yet.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>Status</th>
                        <th>Category</th>
                        <th>Reason</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($domains as $domain): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($domain['domain']); ?></td>
                            <td><span class="status-pill <?php echo $domain['status'] === 'restricted' ? 'pill-critical' : ($domain['status'] === 'allowed' ? 'pill-high' : 'pill-medium'); ?>"><?php echo htmlspecialchars(ucfirst($domain['status'])); ?></span></td>
                            <td><?php echo htmlspecialchars($domain['category'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($domain['reason'] ?? '-'); ?></td>
                            <td>
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="action" value="delete_domain">
                                    <input type="hidden" name="domain_id" value="<?php echo htmlspecialchars($domain['id']); ?>">
                                    <button type="submit" class="button button-danger">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php render_footer();
