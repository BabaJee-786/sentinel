<?php
require_once __DIR__ . '/backend/db.php';
require_once __DIR__ . '/backend/session_auth.php';

require_login();

// Current page
$page = $_GET['page'] ?? 'dashboard';
$validPages = ['dashboard', 'devices', 'alerts', 'domains', 'settings'];
if (!in_array($page, $validPages)) {
    $page = 'dashboard';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sentinel Net - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="assets/style.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0d6efd;
            --danger-color: #dc3545;
            --success-color: #198754;
            --warning-color: #ffc107;
            --dark: #1a1a1a;
            --light: #f8f9fa;
        }

        body {
            background-color: var(--light);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: linear-gradient(135deg, var(--dark) 0%, #2a2a2a 100%);
            color: white;
            padding: 20px 0;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-brand {
            padding: 0 20px 30px;
            border-bottom: 1px solid rgba(255,255,255, 0.1);
            margin-bottom: 20px;
        }

        .sidebar-brand h4 {
            margin: 0;
            font-weight: 700;
            font-size: 1.3rem;
        }

        .sidebar-brand small {
            color: rgba(255,255,255, 0.6);
        }

        .nav-item {
            margin-bottom: 5px;
        }

        .nav-link {
            color: rgba(255,255,255, 0.7);
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .nav-link:hover {
            color: white;
            background-color: rgba(255,255,255, 0.1);
            border-left-color: var(--primary-color);
        }

        .nav-link.active {
            color: white;
            background-color: var(--primary-color);
            border-left-color: var(--warning-color);
        }

        .nav-icon {
            font-size: 1.2rem;
            width: 24px;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
        }

        .top-navbar {
            background: white;
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary-color);
        }

        .stat-card.alert {
            border-left-color: var(--danger-color);
        }

        .stat-card.success {
            border-left-color: var(--success-color);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin: 10px 0;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table-wrapper {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .table {
            margin: 0;
        }

        .table thead {
            background-color: #f8f9fa;
            border-bottom: 2px solid #e0e0e0;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
        }

        .badge-status {
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .badge-online {
            background-color: #d1f2eb;
            color: #0f5132;
        }

        .badge-offline {
            background-color: #f8d7da;
            color: #842029;
        }

        .badge-live {
            background-color: #d1f2eb;
            color: #0f5132;
        }

        .badge-paused {
            background-color: #cfe2ff;
            color: #084298;
        }

        .modal-header {
            border-bottom: 1px solid #e0e0e0;
            background-color: #f8f9fa;
        }

        .toggle-switch {
            display: inline-block;
            position: relative;
            width: 50px;
            height: 24px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.4s;
            border-radius: 24px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background-color: var(--success-color);
        }

        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            margin-bottom: 8px;
        }

        .checkbox-label input[type="checkbox"] {
            cursor: pointer;
            width: 18px;
            height: 18px;
        }

        .page-section {
            display: none;
        }

        .page-section.active {
            display: block;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }

            .main-content {
                margin-left: 0;
                padding: 10px;
            }

            .nav-link {
                padding: 10px 15px;
                font-size: 0.9rem;
            }

            .top-navbar {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-header h2 {
            margin: 0;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <h4><i class="bi bi-shield-check"></i> Sentinel Net</h4>
            <small>Security Lab Monitor</small>
        </div>
        
        <div class="nav-item">
            <a href="?page=dashboard" class="nav-link <?= $page === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2 nav-icon"></i>
                <span>Dashboard</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="?page=devices" class="nav-link <?= $page === 'devices' ? 'active' : '' ?>">
                <i class="bi bi-laptop nav-icon"></i>
                <span>Devices</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="?page=alerts" class="nav-link <?= $page === 'alerts' ? 'active' : '' ?>">
                <i class="bi bi-exclamation-triangle nav-icon"></i>
                <span>Alerts</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="?page=domains" class="nav-link <?= $page === 'domains' ? 'active' : '' ?>">
                <i class="bi bi-globe nav-icon"></i>
                <span>Domains</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="?page=settings" class="nav-link <?= $page === 'settings' ? 'active' : '' ?>">
                <i class="bi bi-gear nav-icon"></i>
                <span>Settings</span>
            </a>
        </div>

        <hr style="border-color: rgba(255,255,255, 0.1);">

        <div class="nav-item">
            <a href="logout.php" class="nav-link">
                <i class="bi bi-box-arrow-left nav-icon"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-navbar">
            <h5><i class="bi bi-info-circle"></i> Sentinel Net Admin Dashboard</h5>
            <div>
                <span class="badge bg-primary"><?= htmlspecialchars(get_authenticated_user()['display_name'] ?? 'Admin') ?></span>
            </div>
        </div>

        <!-- Dashboard Page -->
        <div class="page-section <?= $page === 'dashboard' ? 'active' : '' ?>">
            <?php include 'pages/dashboard.php'; ?>
        </div>

        <!-- Devices Page -->
        <div class="page-section <?= $page === 'devices' ? 'active' : '' ?>">
            <?php include 'pages/devices.php'; ?>
        </div>

        <!-- Alerts Page -->
        <div class="page-section <?= $page === 'alerts' ? 'active' : '' ?>">
            <?php include 'pages/alerts.php'; ?>
        </div>

        <!-- Domains Page -->
        <div class="page-section <?= $page === 'domains' ? 'active' : '' ?>">
            <?php include 'pages/domains.php'; ?>
        </div>

        <!-- Settings Page -->
        <div class="page-section <?= $page === 'settings' ? 'active' : '' ?>">
            <?php include 'pages/settings.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="assets/app.js"></script>
</body>
</html>
