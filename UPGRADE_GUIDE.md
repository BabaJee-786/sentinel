# Sentinel Net V2.0 - Upgrade Guide

## New Features Overview

This document outlines all the new professional features added to Sentinel Net in version 2.0.

---

## 🎯 Feature Checklist

### 1. ✅ Modern Admin Dashboard
- **New File:** `dashboard.php` (replaces old index.php)
- **Components:** Sidebar navigation, responsive layout, Bootstrap 5
- **Pages:** Dashboard, Devices, Alerts, Domains, Settings
- **Navigation:** Single-page dashboard with modular pages

**Location:** `/pages/` directory contains all page modules

### 2. ✅ Enhanced Device Management
- **Features:**
  - Edit device name and type
  - Toggle device status (Active/Disabled)
  - Delete devices with confirmation
  - Real-time online/offline status
  
- **API Endpoints:**
  - `backend/api/endpoints/update-device.php` - Update device
  - `backend/api/endpoints/delete-device.php` - Delete device
  
- **Database:** Added `status` column to `devices` table

**UI:** Devices page with action buttons for each device

### 3. ✅ Alerts CSV Export
- **Features:**
  - Select multiple alerts via checkboxes
  - Download selected alerts as CSV
  - Exports: Device Name, IP Address, Domain, Timestamp, Severity, Message
  - UTF-8 compatible (works in Excel)

- **API Endpoint:** `backend/api/endpoints/export-alerts-csv.php`

**UI:** Alerts page with select all checkbox and export button

### 4. ✅ Advanced Domain Management
- **Features:**
  - Toggle domain status between Live (blocking) and Paused (disabled)
  - Edit domain category and reason
  - Delete domains
  - Per-domain device assignment

- **API Endpoints:**
  - `backend/api/endpoints/toggle-domain.php` - Pause/Resume blocking
  - `backend/api/endpoints/update-domain.php` - Edit domain details
  - `backend/api/endpoints/delete-domain.php` - Delete domain

- **Database:** Modified `status` column to support 'live' and 'paused' values

**UI:** Domains page with toggle buttons for each status

### 5. ✅ Device-Specific Domain Permissions
- **Concept:** Allow certain devices to bypass specific blocked domains
  - Example: YouTube is globally restricted, but allowed for Device 1
  
- **Features:**
  - Per-domain device assignment modal
  - Checkboxes for each device
  - Save individual device permissions

- **API Endpoints:**
  - `backend/api/endpoints/assign-domain-device.php` - Set device permissions
  - `backend/api/endpoints/fetch-permissions.php` - Get current permissions

- **Database Tables:**
  - `device_domain_permissions` - NEW table for storing permissions

**UI:** Domains page > "Assign to Devices" button opens modal with checkboxes

### 6. ✅ Settings & SMTP Configuration
- **Features:**
  - Configure SMTP server details
  - Enable/disable email alerts
  - Set alert severity level (Critical/High/All)
  - Organization name configuration
  - Test email functionality

- **API Endpoints:**
  - `backend/api/endpoints/get-settings.php` - Fetch settings
  - `backend/api/endpoints/save-settings.php` - Save settings

- **Database Table:**
  - `settings` - NEW table for system configuration
  - Pre-populated with common SMTP providers

**UI:** Settings page with organized sections

### 7. ✅ Email Alert Integration
- **Features:**
  - Automatic email notifications when threats detected
  - Uses PHPMailer library (SMTP support)
  - Fallback to PHP mail() function
  - Severity-based filtering
  - HTML formatted emails

- **Helper Functions:** (in `backend/db.php`)
  - `send_email_alert()` - Send email for alert
  - `send_email_phpmailer()` - SMTP via PHPMailer
  - `log_email_alert()` - Log all sent emails

- **Database Table:**
  - `email_logs` - NEW, tracks sent/failed emails

**Configuration:** SMTP settings in Settings page

### 8. ✅ Audit Logging
- **Features:**
  - Log all admin actions
  - Track what changed (old/new values)
  - Record timestamp and IP address
  - Track: create, update, delete operations

- **Helper Function:** (in `backend/db.php`)
  - `log_admin_action()` - Log any admin action

- **Database Table:**
  - `audit_logs` - NEW table for admin trail

**Visibility:** All actions automatically logged (no UI needed yet)

### 9. ✅ Security & Best Practices
- **Implementation:**
  - PDO prepared statements (all SQL queries)
  - Input validation (server-side)
  - Output escaping (htmlspecialchars)
  - Admin session protection (require_admin_session)
  - CORS/CSRF considerations
  - Strong password hashing (bcrypt in users table)

---

## 📁 New File Structure

```
sentinel/
├── dashboard.php                    # Main dashboard (NEW)
├── pages/                           # Page modules (NEW)
│   ├── dashboard.php
│   ├── devices.php
│   ├── alerts.php
│   ├── domains.php
│   └── settings.php
├── backend/
│   ├── database/
│   │   └── migrations_v2.sql        # New tables/columns (NEW)
│   ├── api/
│   │   └── endpoints/
│   │       ├── update-device.php    (NEW)
│   │       ├── delete-device.php    (NEW)
│   │       ├── toggle-domain.php    (NEW)
│   │       ├── update-domain.php    (NEW)
│   │       ├── delete-domain.php    (NEW)
│   │       ├── assign-domain-device.php (NEW)
│   │       ├── fetch-permissions.php (NEW)
│   │       ├── get-settings.php     (NEW)
│   │       ├── save-settings.php    (NEW)
│   │       └── export-alerts-csv.php (NEW)
│   └── db.php                       # Updated with helpers (MODIFIED)
├── assets/
│   └── app.js                       # Dashboard JS utilities (NEW)
├── composer.json                    # Dependencies (NEW)
├── AGENT_UPGRADE_INSTRUCTIONS.md    # Agent device permissions (NEW)
└── HOSTINGER_DEPLOYMENT.md          # Deployment guide (NEW)
```

---

## 🔧 Installation Steps

### 1. Database Migration

Run both migration files to set up new tables and columns:

```bash
# Via MySQL/phpMyAdmin:
mysql -u sentinel_user -p sentinel_prod < backend/database/migrations.sql
mysql -u sentinel_user -p sentinel_prod < backend/database/migrations_v2.sql

# Or import via phpMyAdmin GUI
```

**Tables Created:**
- `device_domain_permissions` - Device-specific domain access
- `settings` - System configuration
- `email_logs` - Email delivery tracking
- `audit_logs` - Admin action log

### 2. Install Composer Dependencies

```bash
cd sentinel/
composer install
```

This installs PHPMailer for SMTP email functionality.

### 3. Update .env File

Add any new configuration needed (if upgrading):

```env
# Database (usually unchanged)
DB_HOST=localhost
DB_USER=sentinel_user
DB_PASS=your_password
DB_NAME=sentinel_db

# SMTP (new feature)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_EMAIL=your@email.com
SMTP_PASSWORD=app_password
```

### 4. Update Dashboard Access

**Old:** `index.php` (simple device list)
**New:** `dashboard.php` (complete admin panel)

Update bookmarks and redirect old index.php:

```php
<?php
// Old index.php - redirect to new dashboard
header('Location: dashboard.php');
exit;
?>
```

---

## 🔐 Agent Integration (Optional)

The agent.py can be enhanced to support device-specific permissions:

**See:** `AGENT_UPGRADE_INSTRUCTIONS.md` for step-by-step guide

**Key changes:**
- New function `check_device_domain_permission()` checks device-specific overrides
- New function `sync_device_permissions()` fetches permissions from dashboard
- Respects device permissions before applying global blocking

---

## 📊 Usage Scenarios

### Scenario 1: Global Domain Block with Device Exception
1. Login to dashboard
2. Go to **Domains**
3. Add domain (e.g., "youtube.com") as "Live"
4. Click **Assign to Devices**
5. Uncheck "Device-A" (others remain blocked)
6. Save - now Device-A can access YouTube, others cannot

### Scenario 2: Export Alerts for Report
1. Go to **Alerts**
2. Click **Select All**
3. Click **Export Selected**
4. Opens CSV file in Excel/Sheets
5. Can filter, sort, or print for presentation

### Scenario 3: Temporarily Pause a Domain
1. Go to **Domains**
2. Find domain (e.g., "facebook.com")
3. Click **Pause** button
4. Changes status from "Live" to "Paused"
5. All devices can access it until resumed

### Scenario 4: Email Alerts Setup
1. Go to **Settings**
2. Enter SMTP details (Gmail example):
   - Host: smtp.gmail.com
   - Port: 587
   - Email: your-email@gmail.com
   - Password: [App-specific password from Google]
3. Check "Enable Email Alerts"
4. Select "High & Critical" for severity
5. Click "Test Email"
6. From now on, alerts will be emailed

---

## 🧪 Testing Checklist

- [ ] Login works with credentials
- [ ] Dashboard loads all 5 pages
- [ ] Can add/edit/delete devices
- [ ] Can select and export alerts as CSV
- [ ] Can toggle domain status
- [ ] Can assign/unassign devices to domains
- [ ] Settings page loads and saves
- [ ] SMTP test email works (if configured)
- [ ] Sidebar navigation works
- [ ] Responsive on mobile (hamburger menu)

---

## 📚 API Reference

All endpoints require admin session authentication.

### Device Management

**Update Device**
```
POST /backend/api/endpoints/update-device.php
{
  "device_id": "uuid",
  "device_name": "New Name",
  "device_type": "Windows",
  "status": "active"  // or "disabled"
}
```

**Delete Device**
```
POST /backend/api/endpoints/delete-device.php
{
  "device_id": "uuid"
}
```

### Domain Management

**Toggle Domain Status**
```
POST /backend/api/endpoints/toggle-domain.php
{
  "domain_id": "uuid",
  "status": "live"  // or "paused"
}
```

**Assign Devices to Domain**
```
POST /backend/api/endpoints/assign-domain-device.php
{
  "domain_id": "uuid",
  "devices": [
    {"device_id": "uuid1", "is_allowed": true},
    {"device_id": "uuid2", "is_allowed": false}
  ]
}
```

**Fetch Permissions**
```
GET /backend/api/endpoints/fetch-permissions.php?domain_id=uuid
```

### Settings Management

**Get Settings**
```
GET /backend/api/endpoints/get-settings.php
```

**Save Settings**
```
POST /backend/api/endpoints/save-settings.php
{
  "settings": {
    "smtp_host": "smtp.gmail.com",
    "smtp_port": 587,
    "smtp_email": "alerts@example.com",
    "smtp_password": "app_password",
    "email_enabled": true,
    "email_alert_severity": "high",
    "organization_name": "My Lab"
  }
}
```

### Alerts Export

**Export Alerts as CSV**
```
POST /backend/api/endpoints/export-alerts-csv.php
{
  "alert_ids": ["uuid1", "uuid2", "uuid3"]
}
```

---

## 🐛 Troubleshooting

### Dashboard pages not loading
- Ensure `pages/` directory exists with all 5 PHP files
- Check browser console for JavaScript errors
- Verify AJAX requests in Network tab

### Email not sending
- Verify SMTP settings in Settings page
- Check `email_logs` table for failed attempts
- Ensure PHPMailer vendor files installed: `vendor/phpmailer/phpmailer/`
- For Gmail: Use app-specific password, not account password

### Database errors
- Run migrations again if tables are missing
- Check `settings` table has default entries
- Verify `device_domain_permissions` table exists

### Permissions/Audit logs not working
- Ensure audit function not suppressing errors
- Check database user has full privileges
- Verify `audit_logs` table exists

---

## 📝 Database Schema Summary

### New Columns
- `devices.status` - ENUM('active', 'disabled') - Device enable/disable
- `domains.status` - EXTENDED: ENUM('restricted','allowed','monitored','live','paused')

### New Tables

**device_domain_permissions**
- Stores per-device domain access overrides
- Links device_id, domain_id, is_allowed flag
- Indexed for fast lookups

**settings**
- Key-value configuration storage
- Pre-populated with SMTP defaults
- Stores organization info, email config

**email_logs**
- Tracks all sent/failed emails
- References alerts and devices
- For audit and troubleshooting

**audit_logs**
- Complete admin action history
- Records old/new values for changes
- Timestamp and IP tracking

---

## 🚀 Performance Tips

1. **Regular Database Maintenance**
   ```sql
   -- Archive old alerts
   DELETE FROM alerts WHERE timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY);
   
   -- Optimize tables
   OPTIMIZE TABLE alerts, logs, devices;
   ```

2. **Index Optimization**
   - Indexes already added in migrations_v2.sql
   - Check `EXPLAIN` query plans if slow

3. **Caching**
   - PHP OPcache significantly improves performance
   - Ask Hostinger to enable if not already

4. **API Response Limiting**
   - Default query limits: 500 alerts, 10 devices
   - Modify in page PHP files as needed

---

## 🔄 Version History

- **v2.0** - Complete dashboard redesign, device permissions, email alerts, settings
- **v1.0** - Initial release, basic device tracking and domain blocking

---

## 📞 Support

For issues or questions:
1. Check HOSTINGER_DEPLOYMENT.md for deployment help
2. Check AGENT_UPGRADE_INSTRUCTIONS.md for agent changes
3. Review database schema in migrations_v2.sql
4. Check error logs in Hostinger hPanel

---

**Installation Complete!** 🎉

Your Sentinel Net V2.0 dashboard is now ready with all professional features.

