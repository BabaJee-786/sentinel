# Sentinel

Sentinel is a lightweight PHP monitoring and blocking dashboard with a Python-based endpoint agent. It includes:

- PHP backend API with MySQL storage
- Web dashboard for devices, alerts, domains, and commands
- Python agent that registers devices, polls policies, updates the hosts file, and reports blocked access
- Installer helper for agent startup management

## Project structure

```
.
├── README.md              # This guide
├── .env                   # Runtime configuration (create from .env.example)
├── .env.example           # Configuration template
├── agent.py               # Endpoint agent script
├── installer.py           # Installer/uninstaller wrapper for the agent
├── index.php              # Dashboard home page
├── devices.php            # Device list
├── alerts.php             # Alert list
├── domains.php            # Domain management page
├── commands.php           # Command dispatch page
├── backend/               # PHP backend API and helper code
└── assets/                # CSS and frontend assets
```

## Requirements

- PHP 7.4+ with PDO MySQL support
- MySQL or MariaDB
- Python 3.8+ for the agent
- Web server capable of serving PHP files

## Setup

### 1. Create the configuration file

From the project root:

```bash
cp .env.example .env
```

Edit `.env` with your database credentials and API keys.

### 2. Create the database schema

From the project root:

```bash
cd backend
mysql -h localhost -u root -p sentinel_db < database/migrations.sql
```

If you prefer a dedicated MySQL user:

```bash
mysql -h localhost -u root -p -e "CREATE DATABASE sentinel_db; GRANT ALL ON sentinel_db.* TO 'sentinel_user'@'localhost' IDENTIFIED BY 'sentinel_password';"
mysql -h localhost -u sentinel_user -p sentinel_db < database/migrations.sql
```

### 3. Start the backend server locally

From the project root:

```bash
php -S localhost:8000
```

Then open the dashboard in your browser at:

```
http://localhost:8000/index.php
```

If you host this on a real server, upload all files to the document root and ensure `backend/api/index.php` is reachable.

## How the app works

- The PHP dashboard pages use the backend database to show devices, alerts, domains, and commands.
- The Python agent registers itself with the backend API and receives a `device_id`.
- The agent polls restricted domains from the backend and updates the local `hosts` file.
- When a blocked domain is accessed, the agent logs the access and sends an alert to the backend.
- Commands created in the dashboard are pulled by the agent and displayed locally.

## Agent installation

The agent can be installed to run automatically on system startup.

### On Windows (run as Administrator):

1. Ensure Python 3.8+ is installed and in PATH.
2. Copy the configuration template:

```bash
copy .env.example .env
```

3. Edit `.env` with your Sentinel server URL and API keys.
4. Run the installer:

```bash
install_agent.bat
```

Or manually:

```bash
python agent.py install
```

This creates a scheduled task that runs the agent on system boot. The installer also starts the agent immediately after installation.

### On Linux (run as root):

```bash
python agent.py install
```

This installs a systemd service.

### Uninstall:

```bash
python agent.py uninstall
```

Or use `uninstall_agent.bat` on Windows.

### Configure agent settings

Make sure `.env` contains the correct backend API endpoint and keys:

```env
API_BASE=http://localhost/sentinel/backend/api/index.php
SENTINEL_API_KEY=your-secret-api-key-change-this
SENTINEL_ADMIN_KEY=your-admin-key-change-this
BLOCK_REDIRECT_IP=127.0.0.2
```

### Run the agent manually

```bash
python agent.py run
```

### Check installation status

```bash
python agent.py status
```

### Remove the installed agent

```bash
python agent.py uninstall
```

## Dashboard pages

- `index.php` — main dashboard overview
- `devices.php` — device inventory and heartbeat status
- `alerts.php` — recent alerts and detection events
- `domains.php` — policy management for blocked/allowed domains
- `commands.php` — send commands/messages to agents

## Dashboard login

The dashboard now uses a session-based login backed by the existing `users` table.

- Login page: `http://localhost:8000/login.php`
- Default seeded user email: `admin@sentinel.local`
- Default seeded user password: `sentinel123`

The app inserts this user into `users` automatically if that email does not already exist. Change the password hash in the database after the first login if you plan to deploy this anywhere outside local testing.

## API overview

The backend API is implemented in `backend/api/index.php` and supports actions such as:

- `register_device`
- `heartbeat`
- `report_alert`
- `log_access`
- `get_domains`
- `update_domains`
- `get_alerts`
- `get_commands`
- `update_command_status`
- `get_logs`
- `health`

Most endpoints require `Authorization: Bearer {API_KEY}`.

## Notes for new users

- Keep the `.env` file secure and do not commit it to source control.
- The root `.env` file is shared by the backend and the agent.
- If you host on a shared PHP host, ensure the `backend/api/index.php` path matches the installed URL.
- If the agent cannot access the API, verify `API_BASE`, `SENTINEL_API_KEY`, and network reachability.

## Troubleshooting

- `Unauthorized` errors: confirm `API_KEY`/`ADMIN_KEY` match between `.env` and requests.
- Database connection errors: verify `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`, and `DB_PORT`.
- Agent startup failures on Windows: run PowerShell as Administrator.
- Agent startup failures on Linux: install with root privileges or use `sudo`.

## Additional resources

See `backend/README.md` for backend-specific details and API examples.
