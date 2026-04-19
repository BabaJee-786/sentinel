# 🧪 Testing & Verification Checklist

## Pre-Deployment Testing (Local/Staging)

### Environment Setup
- [ ] .env file created with test database credentials
- [ ] Database exists and is accessible
- [ ] PHP 7.4+ installed
- [ ] PDO MySQL extension available
- [ ] Composer installed and working

### Database Verification
- [ ] Both migration files ran without errors
- [ ] All 4 new tables created
  - [ ] `device_domain_permissions`
  - [ ] `settings`
  - [ ] `email_logs`
  - [ ] `audit_logs`
- [ ] Modified tables updated
  - [ ] `devices.status` column added
  - [ ] `domains.status` extended to include 'live' and 'paused'
- [ ] Indexes created correctly
- [ ] Default settings populated

### Composer Verification
- [ ] `vendor/` directory created
- [ ] `phpmailer/` installed
- [ ] `autoload.php` present
- [ ] No errors during install

### File Structure
- [ ] All 9 API endpoint files exist
- [ ] All 5 page files in `pages/` directory
- [ ] `dashboard.php` main file exists
- [ ] `assets/app.js` exists
- [ ] `backend/db.php` updated

---

## Dashboard Functionality Testing

### Access & Navigation
- [ ] Login page loads at `/` or `/login.php`
- [ ] Admin can login with credentials
- [ ] Redirects to dashboard on successful login
- [ ] Sidebar navigation displays all 5 pages
- [ ] Page switching works (Dashboard, Devices, Alerts, Domains, Settings)
- [ ] Logout button works

### Dashboard Page
- [ ] Statistics cards display (devices, alerts, domains, status)
- [ ] Recent devices table shows data
- [ ] Recent alerts table shows data
- [ ] Online/offline status indicators accurate
- [ ] Last updated timestamp shows
- [ ] Severity badge colors correct

### Devices Page
- [ ] Device list loads
- [ ] Device count matches database
- [ ] Edit button opens modal
- [ ] Delete button shows confirmation
- [ ] Status toggle works (Active/Disabled)
- [ ] Online status indicator works
- [ ] Add device modal opens
- [ ] Device name and type can be edited
- [ ] Changes saved to database
- [ ] Deleted devices removed from list

### Alerts Page
- [ ] Alerts list loads
- [ ] Checkboxes appear for each alert
- [ ] "Select All" checkbox works
- [ ] Count shows selected alerts
- [ ] "Export Selected" button enabled when items selected
- [ ] CSV file downloads when export clicked
- [ ] CSV format correct (headers, data)
- [ ] "Select All" button toggles selection

### Domains Page
- [ ] Domain list loads
- [ ] Each domain shows:
  - [ ] Domain name
  - [ ] Status badge (Live/Paused)
  - [ ] Action buttons
- [ ] Toggle button switches status (Live ↔ Paused)
- [ ] Pause feedback shows in UI
- [ ] Edit modal opens and saves changes
- [ ] Delete shows confirmation and removes
- [ ] "Assign to Devices" modal opens
- [ ] Permissions load and save correctly
- [ ] Add domain form works
- [ ] Domain added to list

### Settings Page
- [ ] All input fields display
- [ ] Sections organized properly:
  - [ ] Organization
  - [ ] Email Alerts
  - [ ] SMTP Configuration
  - [ ] Status Dashboard
- [ ] "Save Changes" button appears only on edit
- [ ] SMTP settings can be changed
- [ ] Settings save to database
- [ ] Email enable/disable toggle works
- [ ] Severity radio buttons work
- [ ] Status section updates

---

## API Endpoint Testing

### Device Endpoints
```bash
# Test update-device.php
curl -X POST http://localhost/sentinel/backend/api/endpoints/update-device.php \
  -H "Content-Type: application/json" \
  -d '{"device_id":"test-id","device_name":"Updated"}'
# Expected: {success: true, message: "Device updated"}

# Test delete-device.php
curl -X POST http://localhost/sentinel/backend/api/endpoints/delete-device.php \
  -H "Content-Type: application/json" \
  -d '{"device_id":"test-id"}'
# Expected: {success: true}
```

- [ ] update-device returns success on valid input
- [ ] update-device validates input
- [ ] delete-device removes device
- [ ] Both endpoints check admin session

### Domain Endpoints
- [ ] toggle-domain.php changes status
- [ ] update-domain.php updates details
- [ ] delete-domain.php removes domain
- [ ] All return proper responses

### Permission Endpoints
- [ ] fetch-permissions.php returns all devices
- [ ] assign-domain-device.php sets permissions
- [ ] Permissions saved to database

### Settings Endpoints
- [ ] get-settings.php returns all settings
- [ ] save-settings.php updates settings
- [ ] SMTP credentials saved securely

### CSV Export Endpoint
- [ ] export-alerts-csv.php accepts alert IDs
- [ ] Returns CSV format
- [ ] Column headers correct
- [ ] Data rows populate correctly
- [ ] File download works in browser

---

## Security Testing

### Input Validation
- [ ] SQL injection attempts blocked
- [ ] XSS attempts escaped
- [ ] Invalid data types rejected
- [ ] Required fields enforced
- [ ] Email format validated
- [ ] Port number validated (numeric)
- [ ] UUID format validated

### Authentication/Authorization
- [ ] Unauthenticated users cannot access admin pages
- [ ] Non-admin users cannot access API endpoints
- [ ] Session timeout works (if configured)
- [ ] CSRF tokens included (if implemented)
- [ ] Admin actions logged to audit table

### Database Security
- [ ] All queries use prepared statements
- [ ] No SQL errors exposed to user
- [ ] Sensitive data (passwords) not logged
- [ ] Database errors logged internally only

---

## Database Operations Testing

### CRUD Operations
- [ ] Create: New device/domain/setting added
- [ ] Read: Data retrieved correctly
- [ ] Update: Changes persist
- [ ] Delete: Records removed with cascades

### Audit Logging
- [ ] Device update logged
- [ ] Device delete logged
- [ ] Domain update logged
- [ ] Domain delete logged
- [ ] Settings update logged
- [ ] Audit records show action, user, timestamp, IP

### Permissions
- [ ] Permissions saved correctly
- [ ] Multiple devices can be assigned
- [ ] is_allowed flag toggles properly
- [ ] Join queries work correctly

### Settings
- [ ] Default settings populated
- [ ] New settings can be created
- [ ] Existing settings can be updated
- [ ] get_setting() helper returns values
- [ ] Settings persist between sessions

---

## Email Integration Testing

### SMTP Configuration
- [ ] Settings saved correctly
- [ ] get-settings.php retrieves SMTP config
- [ ] save-settings.php updates SMTP
- [ ] Password field doesn't display back to browser

### Email Sending
- [ ] PHPMailer library loads if present
- [ ] send_email_alert() can be called
- [ ] Email queues without errors
- [ ] Email logs created in database
- [ ] Failed emails logged with error message

### Email Logs
- [ ] email_logs table records all attempts
- [ ] Status field shows sent/failed/bounced
- [ ] Recipient email stored
- [ ] Subject stored
- [ ] Timestamp recorded

---

## JavaScript/Client-Side Testing

### Dashboard Dynamics
- [ ] SweetAlert confirmations work
- [ ] API calls return and process responses
- [ ] Form submissions work
- [ ] Modal opens and closes
- [ ] Select all checkbox works
- [ ] CSV download triggered correctly
- [ ] No console errors

### UI Responsiveness
- [ ] Desktop layout (1920px) looks good
- [ ] Tablet layout (768px) responsive
- [ ] Mobile layout (320px) hamburger menu
- [ ] Tables scroll on mobile
- [ ] Buttons clickable on mobile

### Form Validation
- [ ] Required fields enforced
- [ ] Invalid inputs shown
- [ ] Success messages display
- [ ] Error messages clear

---

## Integration Testing

### Device → Dashboard
- [ ] Registered device appears in dashboard
- [ ] Last heartbeat updates
- [ ] Online status reflects correctly
- [ ] Can edit device from dashboard
- [ ] Can delete device from dashboard

### Agent → Dashboard
- [ ] Agent can heartbeat
- [ ] Agent receives domain list
- [ ] Agent can report alerts
- [ ] Alerts appear in dashboard

### Alert → Export
- [ ] Alerts record in database
- [ ] Can select and export alerts
- [ ] CSV contains all alert information

### Domain → Permission
- [ ] Create domain in dashboard
- [ ] Assign devices to domain
- [ ] Permissions save
- [ ] Permissions retrievable

### Settings → Email
- [ ] Configure SMTP in settings
- [ ] Trigger alert
- [ ] Email sent (if enabled)
- [ ] Email logged

---

## Performance Testing

### Response Times
- [ ] Dashboard loads < 2 seconds
- [ ] API endpoints respond < 500ms
- [ ] CSV export completes < 5 seconds
- [ ] Database queries optimized

### Load Testing (Optional)
- [ ] Handle 10 concurrent users
- [ ] Handle 1000 alerts in export
- [ ] Handle large audit logs

### Database Performance
- [ ] Indexes improve query speed
- [ ] No n+1 query problems
- [ ] Indexes added on appropriate columns

---

## Hostinger-Specific Testing

### File Upload/FTP
- [ ] All files upload successfully
- [ ] No corruption in binary files
- [ ] `.env` created correctly on server
- [ ] Permissions set correctly

### MySQL Access
- [ ] Can connect via phpMyAdmin
- [ ] Can run migrations
- [ ] Can view tables
- [ ] Can execute queries

### PHP/Web Server
- [ ] PHP version >= 7.4
- [ ] PDO MySQL extension enabled
- [ ] Composer works
- [ ] SSH access works (if available)

### SSL/HTTPS
- [ ] Dashboard accessible via HTTPS
- [ ] No mixed content warnings
- [ ] SSL certificate valid
- [ ] Browser shows secure lock icon

---

## Production Readiness

### Code Quality
- [ ] No console errors/warnings
- [ ] No PHP warnings/notices
- [ ] No database errors
- [ ] Code properly formatted
- [ ] Functions documented

### Documentation
- [ ] README.md clear and accurate
- [ ] API documentation provided
- [ ] Deployment guide followed
- [ ] Troubleshooting guide helpful

### Security
- [ ] HTTPS enforced
- [ ] .env properly protected
- [ ] Credentials not in logs
- [ ] Audit trail enabled
- [ ] Admin access controlled

### Backup/Recovery
- [ ] Database backup procedure tested
- [ ] File backup procedure tested
- [ ] Recovery tested from backup
- [ ] Backup schedule established

### Monitoring
- [ ] Error logging configured
- [ ] Audit logs monitored
- [ ] Performance monitored
- [ ] Alerts configured (if applicable)

---

## Deployment Checklist (Before Going Live)

### Pre-Production
- [ ] All tests pass
- [ ] Database migrated
- [ ] Composer dependencies installed
- [ ] Permissions set correctly
- [ ] .env configured with real values
- [ ] SMTP tested (if using email)
- [ ] Backup completed
- [ ] Rollback procedure documented

### Post-Production
- [ ] Verify all pages load
- [ ] Test critical functions
- [ ] Monitor error logs
- [ ] Check database size
- [ ] Verify backups run
- [ ] Document any issues
- [ ] Communicate status to users

---

## Sign-Off

- [ ] All tests completed
- [ ] All tests passed
- [ ] Documentation reviewed
- [ ] Ready for production deployment
- [ ] Deployment date: ___________
- [ ] Deployed by: ___________
- [ ] Verified by: ___________

**System Status:** ___________  
**Notes:** ___________

---

**Total Checklist Items:** 150+  
**Estimated Testing Time:** 3-4 hours  

---

