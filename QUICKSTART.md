# Sentinel Net V2.0 - Quick Start Guide

## 📋 Summary of Changes

This is the major V2.0 upgrade of Sentinel Net with 9 new professional features.

---

## ⚡ Quick Setup (5 Minutes)

### 1. Upload Files to Hostinger
```bash
# Use FileZilla or Hostinger File Manager
# Upload all files EXCEPT: .env, vendor/, .git/

# Key directories to upload:
- backend/database/migrations_v2.sql (NEW)
- backend/api/endpoints/* (NEW files)
- pages/ (NEW directory)
- assets/app.js (NEW)
- dashboard.php (NEW/REPLACE)
- composer.json (NEW)
```

### 2. Create .env File on Server
```
Via Hostinger File Manager:
1. Create new file: /sentinel/.env
2. Paste this content (update values):

DB_HOST=mysql.hostinger.com
DB_USER=sentinel_user
DB_PASS=[YOUR_PASSWORD]
DB_NAME=sentinel_prod
SENTINEL_API_BASE=https://yourdomain.com/sentinel/backend/api/index.php
SENTINEL_API_KEY=your-secure-key
SENTINEL_ADMIN_KEY=your-admin-key
```

### 3. Run Database Migrations
```
Via phpMyAdmin:
1. Select sentinel database
2. Click "Import" tab
3. Upload: backend/database/migrations_v2.sql
4. Click "Go"

Or via SSH:
mysql -u sentinel_user -p sentinel_prod < backend/database/migrations_v2.sql
```

### 4. Install Composer (Dependencies)
```bash
Via SSH:
cd public_html/sentinel/
composer install
```

### 5. Test
```
Visit: https://yourdomain.com/sentinel/dashboard.php
Login with your admin credentials
```

---

## 📁 What's New

| Feature | File(s) | Status |
|---------|---------|--------|
| Modern Dashboard | `dashboard.php`, `pages/*`, `assets/app.js` | ✅ Complete |
| Device Management | `update-device.php`, `delete-device.php` | ✅ Complete |
| Alerts Export | `export-alerts-csv.php` | ✅ Complete |
| Domain Management | `toggle-domain.php`, `update-domain.php`, `delete-domain.php` | ✅ Complete |
| Device Permissions | `assign-domain-device.php`, `fetch-permissions.php` | ✅ Complete |
| Settings/SMTP | `get-settings.php`, `save-settings.php` | ✅ Complete |
| Email Alerts | `send_email_alert()` in `db.php` | ✅ Complete |
| Audit Logging | `audit_logs` table | ✅ Complete |
| Database Schema | `migrations_v2.sql` | ✅ Complete |

---

## 🎯 New Features at a Glance

### 1. Beautiful Sidebar Dashboard
- Clean, modern UI with Bootstrap 5
- Navigation menu: Dashboard, Devices, Alerts, Domains, Settings
- Real-time statistics
- Responsive mobile design

### 2. Device Management
- Edit device names and types
- Toggle devices on/off
- Delete devices with confirmation

### 3. Alerts Export
- Select multiple alerts
- Download as CSV
- Import into Excel/Sheets

### 4. Domain Controls
- Pause/Resume domains without deleting
- Edit domain notes and categories
- Manage per-device access overrides

### 5. Device-Specific Permissions
- Allow specific devices to bypass restrictions
- Example: YouTube blocked globally, but allowed for Device-A
- Modal interface for easy management

### 6. Settings Panel
- Configure SMTP for email alerts
- Set sender email and credentials
- Choose alert severity levels
- Configure organization name

### 7. Automatic Email Alerts
- Send emails when threats detected
- Configurable severity levels
- HTML formatted messages
- Uses PHPMailer (SMTP) or PHP mail()

### 8. Admin Audit Trail
- All actions logged with timestamps
- Track changes to devices, domains, settings
- IP address recorded for each action

---

## 📖 Documentation Files

**Read These In Order:**

1. **UPGRADE_GUIDE.md** - Features, usage, testing
2. **HOSTINGER_DEPLOYMENT.md** - Full deployment instructions
3. **AGENT_UPGRADE_INSTRUCTIONS.md** - Update agent for new features
4. **This File** - Quick reference

---

## 🔧 File Locations

```
sentinel/
├── dashboard.php              ← Main entry point (NEW)
│
├── pages/                     ← Page modules (NEW directory)
│   ├── dashboard.php
│   ├── devices.php
│   ├── alerts.php
│   ├── domains.php
│   └── settings.php
│
├── backend/
│   ├── db.php                 ← Updated with helper functions
│   │
│   ├── database/
│   │   └── migrations_v2.sql  ← New tables (NEW)
│   │
│   ├── api/
│   │   └── endpoints/
│   │       ├── update-device.php          (NEW)
│   │       ├── delete-device.php          (NEW)
│   │       ├── toggle-domain.php          (NEW)
│   │       ├── update-domain.php          (NEW)
│   │       ├── delete-domain.php          (NEW)
│   │       ├── assign-domain-device.php   (NEW)
│   │       ├── fetch-permissions.php      (NEW)
│   │       ├── get-settings.php           (NEW)
│   │       ├── save-settings.php          (NEW)
│   │       └── export-alerts-csv.php      (NEW)
│
├── assets/
│   └── app.js                 ← Dashboard utilities (NEW)
│
├── composer.json              ← Dependencies (NEW)
├── .env                       ← Configuration (CREATE on server)
├── UPGRADE_GUIDE.md           ← Feature guide (NEW)
├── HOSTINGER_DEPLOYMENT.md    ← Deployment help (NEW)
└── AGENT_UPGRADE_INSTRUCTIONS.md ← Agent changes (NEW)
```

---

## ✅ Pre-Deployment Checklist

- [ ] Backup current database
- [ ] Backup current code
- [ ] Have Hostinger FTP/SFTP credentials ready
- [ ] Have MySQL credentials
- [ ] Have phpMyAdmin access
- [ ] Downloaded latest version
- [ ] Read HOSTINGER_DEPLOYMENT.md

---

## 🚀 Deployment Checklist

**On Hostinger Server:**

- [ ] Upload all files to /public_html/sentinel/
- [ ] Create .env file with correct credentials
- [ ] Run migrations_v2.sql via phpMyAdmin
- [ ] Run `composer install` via SSH
- [ ] Set file permissions (755 for dirs, 644 for files)
- [ ] Test login: https://yourdomain.com/sentinel/dashboard.php
- [ ] Test each dashboard page loads
- [ ] Test CSV export
- [ ] Optional: Configure SMTP for email alerts

---

## 🆘 Troubleshooting

### Pages not loading?
```
Check:
- pages/ directory exists with all 5 files
- Browser console for JavaScript errors
- .env file has correct DB credentials
```

### 500 error?
```
Check:
- PHP error logs in Hostinger hPanel
- .env file is readable by PHP (chmod 600)
- Database exists and is accessible
- All migrations ran successfully
```

### API endpoints returning errors?
```
Check:
- Database tables exist (check phpmyadmin)
- Admin session is valid (logged in?)
- API endpoint file exists in /backend/api/endpoints/
```

### Email not sending?
```
Check:
- SMTP settings correct in Settings page
- PHPMailer installed: vendor/phpmailer/phpmailer/
- Gmail: Use app-specific password, not account password
- Check email_logs table for details
```

---

## 📊 Database Tables Created

```sql
-- New tables from migrations_v2.sql:

1. device_domain_permissions
   - Stores device-specific domain access
   - Links devices to domains with allow/deny flag

2. settings
   - Key-value configuration storage
   - Pre-populated with SMTP defaults
   - Stores system settings

3. email_logs
   - Tracks all sent/failed emails
   - Includes subject, recipient, status
   - For audit trail

4. audit_logs
   - Admin action history
   - Records changes with before/after values
   - Timestamp and IP address tracking

-- Modified tables:

- devices
  Added column: status ENUM('active', 'disabled')

- domains
  Modified: status now includes 'live' and 'paused'
```

---

## 🔐 Security Notes

✅ **Already Implemented:**
- PDO prepared statements (SQL injection protection)
- Input validation on all APIs
- Output escaping (htmlspecialchars)
- Admin session protection
- Password hashing (bcrypt)

**Additional Steps You Should Take:**
1. Change default admin password
2. Rotate SMTP password monthly
3. Keep PHP updated
4. Regular database backups
5. Monitor audit logs for suspicious activity

---

## 📞 Getting Help

### Documentation
- See UPGRADE_GUIDE.md for feature documentation
- See HOSTINGER_DEPLOYMENT.md for step-by-step deployment
- See AGENT_UPGRADE_INSTRUCTIONS.md for agent changes

### Common Issues
- Check Hostinger error logs: hPanel → Tools → Files → Error Logs
- PhpMyAdmin database verification
- SSH terminal for composer and database commands

### API Testing
```javascript
// Test in browser console:
fetch('/sentinel/backend/api/endpoints/get-settings.php')
.then(r => r.json())
.then(d => console.log(d));
```

---

## 🎓 Learning Curve

**Time estimates:**
- Setup (upload files): 10 minutes
- Database migration: 5 minutes
- Testing: 15 minutes
- SMTP configuration (optional): 10 minutes
- **Total: ~40 minutes for full setup**

**For agent upgrade (optional):**
- Review AGENT_UPGRADE_INSTRUCTIONS.md: 15 minutes
- Implement in agent.py: 20 minutes
- Test device permissions: 10 minutes

---

## 📝 Next Steps After Setup

1. **Test Dashboard:**
   - Navigate to each page
   - Verify all stats display correctly
   - Test button functionality

2. **Add Test Data:**
   - Register a test device (via agent or manually)
   - Add a test domain
   - Create test alert

3. **Configure Email (Optional):**
   - Go to Settings page
   - Enter SMTP credentials
   - Enable email alerts
   - Send test email

4. **Update Agent (Optional):**
   - Read AGENT_UPGRADE_INSTRUCTIONS.md
   - Add device permission functions to agent.py
   - Update domain checking logic
   - Redeploy agent to endpoints

5. **Monitor & Maintain:**
   - Check Dashboard regularly
   - Review Audit logs monthly
   - Backup database weekly

---

## 💡 Pro Tips

1. **Bulk Actions:** Use "Select All" on alerts page for batch operations
2. **Device Bypass:** Pause a domain temporarily instead of deleting
3. **Permissions:** Grant device access before distributing
4. **Email Testing:** Send test email before enabling production
5. **Backups:** Export settings monthly for disaster recovery

---

## 📊 Feature Completeness Matrix

| Feature | Frontend | Backend | Database | Testing |
|---------|----------|---------|----------|---------|
| Dashboard | ✅ | ✅ | ✅ | ✅ |
| Devices | ✅ | ✅ | ✅ | ✅ |
| Alerts | ✅ | ✅ | ✅ | ✅ |
| Domains | ✅ | ✅ | ✅ | ✅ |
| Permissions | ✅ | ✅ | ✅ | ✅ |
| Settings | ✅ | ✅ | ✅ | ✅ |
| Email | ✅ | ✅ | ✅ | ✅ |
| Audit | ⬜ | ✅ | ✅ | ✅ |

**Legend:**
- ✅ Complete & Tested
- ⬜ Backend only (no UI needed)

---

## 🎉 Congratulations!

You now have a **production-grade cybersecurity monitoring dashboard** with:
- ✅ Professional UI/UX
- ✅ Advanced device management
- ✅ Flexible domain policies
- ✅ Per-device overrides
- ✅ Email notifications
- ✅ Complete audit trail
- ✅ Enterprise-grade security

**Enjoy your upgraded Sentinel Net!**

---

**Version:** 2.0  
**Release Date:** 2026-04-19  
**Status:** Production Ready  

