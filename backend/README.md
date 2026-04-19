# Sentinel PHP Backend - Core PHP Version

Streamlined core PHP implementation with no framework dependencies.

## File Structure

```
backend/
├── db.php              # Database connection & helper functions
├── routes.php          # All API routes and handlers
├── api/index.php       # Main entry point
├── database/
│   └── migrations.sql  # Database schema

root .env and .env.example are used by both backend and agent:
├── .env                # Configuration (create from .env.example)
└── .env.example        # Configuration template
```

## Quick Setup

### 1. Create .env File

```bash
cd ..
cp .env.example .env
# Edit the .env file with your MySQL and API credentials
```

### 2. Create Database

**One command (use root password):**
```bash
mysql -h localhost -u root -p sentinel_db < database/migrations.sql
```

**Or with dedicated user:**
```bash
mysql -h localhost -u root -p -e "CREATE DATABASE sentinel_db; GRANT ALL ON sentinel_db.* TO 'sentinel_user'@'localhost' IDENTIFIED BY 'sentinel_password';"

mysql -h localhost -u sentinel_user -p sentinel_db < database/migrations.sql
```

### 3. Start Server

```bash
php -S localhost:8000
```

### 4. Test

```bash
curl http://localhost:8000/api/index.php?action=health
```

## Agent Install / Uninstall

The root `agent.py` script now supports install and uninstall commands for automatic startup.

- Install the agent at user login or system startup:

```bash
python agent.py install
```

- Remove the installed agent startup task/service:

```bash
python agent.py uninstall
```

- Run the agent manually:

```bash
python agent.py run
```

- Check installation status:

```bash
python agent.py status
```

## API Endpoints

All endpoints require `Authorization: Bearer {API_KEY}` header.

### agent_api Endpoints

#### Register Device
```
POST /api/index.php?action=register_device

{
  "device_name": "string",
  "ip_address": "string",
  "os_version": "string",
  "mac_address": "string" (optional)
}

Response:
{
  "device_id": "uuid",
  "message": "Device registered successfully"
}
```

#### Heartbeat
```
POST /api/index.php?action=heartbeat

{
  "device_id": "uuid",
  "agent_version": "string",
  "status_info": {} (optional JSON)
}

Response:
{
  "status": "ok",
  "timestamp": timestamp
}
```

#### Report Alert
```
POST /api/index.php?action=report_alert

{
  "device_id": "uuid",
  "domain": "string",
  "severity": "low|medium|high|critical",
  "message": "string",
  "log_id": "uuid" (optional),
  "action_taken": "string" (optional)
}

Response:
{
  "alert_id": "uuid",
  "message": "Alert reported successfully"
}
```

#### Log Access
```
POST /api/index.php?action=log_access

{
  "device_id": "uuid",
  "domain": "string",
  "access_type": "dns_query|https_request|http_request|ping",
  "status": "allowed|blocked|detected",
  "ip_address": "string",
  "process_name": "string" (optional),
  "details": {} (optional JSON)
}

Response:
{
  "log_id": "uuid",
  "message": "Access logged successfully"
}
```

#### Get Domains
```
GET /api/index.php?action=get_domains&device_id=uuid&status=restricted

Response:
{
  "domains": [
    {
      "id": "uuid",
      "domain": "example.com",
      "status": "restricted|allowed|monitored",
      "category": "string",
      "reason": "string"
    }
  ],
  "count": number
}
```

#### Update Domains (Admin only)
```
PUT /api/index.php?action=update_domains

Headers: Authorization: Bearer {ADMIN_KEY}

{
  "user_id": "uuid",
  "domains": [
    {
      "domain": "example.com",
      "status": "restricted|allowed|monitored",
      "category": "string",
      "reason": "string"
    }
  ]
}

Response:
{
  "inserted": number,
  "updated": number,
  "message": "Domains updated successfully"
}
```

#### Get Alerts
```
GET /api/index.php?action=get_alerts&user_id=uuid&limit=100&offset=0

Response:
{
  "alerts": [
    {
      "id": "uuid",
      "device_id": "uuid",
      "domain": "example.com",
      "severity": "low|medium|high|critical",
      "message": "string",
      "is_read": boolean,
      "timestamp": datetime
    }
  ],
  "total": number,
  "limit": number,
  "offset": number
}
```

#### Get Logs
```
GET /api/index.php?action=get_logs&user_id=uuid&domain=example.com&limit=100&offset=0

Response:
{
  "logs": [
    {
      "id": "uuid",
      "device_id": "uuid",
      "domain": "example.com",
      "access_type": "dns_query|https_request|http_request|ping",
      "status": "allowed|blocked|detected",
      "ip_address": "string",
      "process_name": "string",
      "details": {},
      "timestamp": datetime
    }
  ],
  "total": number,
  "limit": number,
  "offset": number
}
```

#### Health Check
```
GET /api/index.php?action=health

Response:
{
  "status": "ok",
  "timestamp": timestamp
}
```

## Database Schema

### Tables

- **devices** - Connected Windows devices
- **domains** - Restricted/allowed domain list
- **logs** - Access attempt logs
- **alerts** - Security alerts
- **users** - User accounts (optional)
- **heartbeats** - Agent health monitoring

See `database/migrations.sql` for full schema.

## Security

- All endpoints require API key authentication
- CORS enabled for frontend (customize in `index.php:5`)
- Use HTTPS in production
- Change default API keys immediately
- Use environment variables for sensitive data
- Implement user authentication in frontend

## Frontend Integration

Update your frontend `.env`:

```env
VITE_API_URL=http://localhost:8000/backend/api/index.php
VITE_API_KEY=your-secure-api-key
```

### React Hook Example

```typescript
async function fetchDomains(deviceId: string) {
  const response = await fetch(
    `${import.meta.env.VITE_API_URL}?action=get_domains&device_id=${deviceId}`,
    {
      headers: {
        'Authorization': `Bearer ${import.meta.env.VITE_API_KEY}`
      }
    }
  );
  return response.json();
}
```

## Troubleshooting

### "Cannot connect to database"
- Check DB credentials in `.env`
- Ensure MySQL is running
- Verify database exists: `mysql -u root -p -e "SELECT * FROM sentinel_db.devices;"`

### "API returns 401 Unauthorized"
- Check Authorization header in request
- Verify API_KEY in .env matches request header
- Ensure Bearer token is used: `Bearer {API_KEY}`

### Database schema not imported
```bash
mysql -u sentinel_user -p sentinel_db < backend/database/migrations.sql
```

### PHP shows blank page
- Enable error display: Add to `config/database.php`:
  ```php
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
  ```
- Check PHP error logs

## Production Deployment

1. Use environment variables for all credentials
2. Run on HTTPS only
3. Implement proper user authentication
4. Add request rate limiting
5. Setup database backups
6. Monitor logs for suspicious activity
7. Use Apache/Nginx with proper PHP-FPM configuration
8. Keep PHP and MySQL updated

## Development

Enable debug mode by setting environment variable:
```bash
export DEBUG=1
```

Then check logs for detailed error messages.
