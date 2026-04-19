import argparse
import os
import shlex
import sys
import json
import time
import socket
import urllib.request
import urllib.parse
import urllib.error
import subprocess
import atexit
import signal
import threading
import queue

# ======================== CONFIG ========================
BASE_DIR = os.path.dirname(__file__)


def load_env_file(env_path):
    env_values = {}

    if not os.path.exists(env_path):
        return env_values

    try:
        with open(env_path, 'r', encoding='utf-8') as env_file:
            for raw_line in env_file:
                line = raw_line.strip()
                if not line or line.startswith('#') or '=' not in line:
                    continue

                key, value = line.split('=', 1)
                env_values[key.strip()] = value.strip().strip('"').strip("'")
    except OSError as exc:
        print(f'[WARN] Unable to read .env file: {exc}')

    return env_values


ENV = load_env_file(os.path.join(BASE_DIR, '.env'))


def get_config(*keys, default=''):
    for key in keys:
        value = os.getenv(key)
        if value:
            return value.strip()

        value = ENV.get(key)
        if value:
            return value.strip()

    return default


API_BASE = get_config(
    'SENTINEL_API_BASE',
    'API_BASE',
    default='https://sentinel.busistree.com/backend/api/index.php',
).replace(' ', '')

if API_BASE.endswith('/'):
    API_BASE = API_BASE.rstrip('/') + '/backend/api/index.php'
elif API_BASE.lower().endswith('.php'):
    API_BASE = API_BASE
elif API_BASE.lower().endswith('/backend/api'):
    API_BASE = API_BASE + '/index.php'
elif API_BASE.lower().endswith('/backend/api/index.php'):
    API_BASE = API_BASE
else:
    API_BASE = API_BASE.rstrip('/') + '/backend/api/index.php'

API_KEY = get_config('SENTINEL_API_KEY', 'API_KEY', default='your-secure-api-key-change-this')
ADMIN_KEY = get_config('SENTINEL_ADMIN_KEY', 'ADMIN_KEY', default='your-admin-key-change-this')
BLOCK_REDIRECT_IP = get_config('SENTINEL_BLOCK_REDIRECT_IP', 'BLOCK_REDIRECT_IP', default='127.0.0.2')

HOSTS_PATH = r"C:\Windows\System32\drivers\etc\hosts" if os.name == 'nt' else '/etc/hosts'
SENTINEL_START = '# === SENTINEL START ==='
SENTINEL_END = '# === SENTINEL END ==='
DEVICE_FILE = os.path.join(BASE_DIR, '.sentinel_device_id')
DEBUG_LOG_FILE = os.path.join(BASE_DIR, 'agent_debug.log')

AGENT_SERVICE_NAME = 'sentinel-agent'
WINDOWS_TASK_NAME = 'SentinelAgent'

cached_domains = []
original_hosts_content = ''
last_alert_time = {}
DEFAULT_BLOCK_MESSAGE = 'Warning: Access to this website is restricted by Sentinel security policy.'
EVENT_DEBOUNCE_SECONDS = 3
DOMAIN_REFRESH_SECONDS = 5
HEARTBEAT_SECONDS = 30

# ======================== API HELPERS ========================

def api_request(action, method='GET', data=None, params=None):
    query = {'action': action}
    if params:
        query.update(params)

    url = API_BASE + '?' + urllib.parse.urlencode(query)
    body = None

    headers = {
        'Content-Type': 'application/json',
        'apikey': API_KEY,
        'Authorization': f'Bearer {API_KEY}',
    }

    if data is not None:
        body = json.dumps(data).encode('utf-8')

    req = urllib.request.Request(url, data=body, headers=headers, method=method)

    try:
        with urllib.request.urlopen(req, timeout=10) as resp:
            text = resp.read().decode('utf-8')
            return json.loads(text)
    except urllib.error.HTTPError as exc:
        try:
            error_body = exc.read().decode('utf-8', errors='replace')
        except Exception:
            error_body = ''
        print(f'[ERROR] API request failed: HTTP {exc.code} for {action}')
        if error_body:
            print(f'[ERROR] Response body: {error_body}')
        print(f'[ERROR] URL: {url}')
        return None
    except Exception as exc:
        print(f'[ERROR] API request failed: {exc}')
        return None


def write_debug_log(message):
    timestamp = time.strftime('%Y-%m-%d %H:%M:%S')
    line = f'[{timestamp}] {message}'
    print(line)
    try:
        with open(DEBUG_LOG_FILE, 'a', encoding='utf-8') as log_file:
            log_file.write(line + '\n')
    except OSError:
        pass

# ======================== DEVICE PERMISSION CHECKING ========================

def fetch_device_permissions(device_id, domain_ids):
    """
    Fetch device-specific domain permissions from the server
    
    Args:
        device_id: The device ID
        domain_ids: List of domain IDs to check permissions for
    
    Returns:
        Dictionary mapping domain_id to permission dict
    """
    if not domain_ids:
        return {}
    
    result = api_request(
        'fetch-permissions',
        method='POST',
        data={
            'device_id': device_id,
            'domain_ids': domain_ids
        }
    )
    
    if result and result.get('success'):
        permissions = result.get('permissions', [])
        return {p.get('domain_id'): p for p in permissions if p.get('domain_id')}
    
    return {}


def check_domain_permission(device_id, domain, domain_id, permissions_cache=None):
    """
    Check if a device has specific permission override for a domain
    
    Args:
        device_id: The device ID
        domain: The domain name (string)
        domain_id: The domain ID (string)
        permissions_cache: Optional cached permissions dict
    
    Returns:
        True if device has allow override, None otherwise
    """
    if permissions_cache and domain_id in permissions_cache:
        perm = permissions_cache[domain_id]
        if perm.get('is_allowed'):
            write_debug_log(f'[PERMISSION] Device {device_id} has override for {domain}')
            return True
    
    return None


def should_block_domain(domain_dict, device_id, permissions_cache=None):
    """
    Determine if a domain should be blocked for this device
    
    Factors:
    - Device-specific permission (highest priority)
    - Domain status (live/paused/restricted)
    
    Args:
        domain_dict: Dictionary with 'id', 'domain', 'status' keys
        device_id: The device ID
        permissions_cache: Cached device permissions
    
    Returns:
        Boolean: True if should block, False if should allow
    """
    domain = domain_dict.get('domain', '')
    domain_id = domain_dict.get('id', '')
    status = domain_dict.get('status', 'restricted')
    
    # Check device-specific permission first
    permission = check_domain_permission(device_id, domain, domain_id, permissions_cache)
    if permission is True:
        return False  # Device has allow override
    
    # Check global domain status
    if status == 'paused':
        return False  # Domain is temporarily disabled
    
    if status == 'restricted' or status == 'live':
        return True  # Should block
    
    return False  # Default: allow


def build_restricted_domain_map_with_device(device_id, domains, permissions_cache=None):
    """
    Build a map of restricted domains for a specific device
    
    Args:
        device_id: The device ID
        domains: List of all domains from API
        permissions_cache: Cached device permissions
    
    Returns:
        Dictionary mapping normalized domains to original domain objects
    """
    domain_map = {}
    
    for domain_obj in domains:
        if not should_block_domain(domain_obj, device_id, permissions_cache):
            continue
        
        domain_name = domain_obj.get('domain', '').strip().lower()
        if not domain_name:
            continue
        
        normalized = normalize_domain(domain_name)
        if normalized:
            domain_map[normalized] = domain_name
            domain_map['www.' + normalized] = domain_name
    
    return domain_map


def sync_device_permissions(device_id, domains):
    """
    Synchronize device permissions from server
    
    Args:
        device_id: The device ID
        domains: List of domain dictionaries
    
    Returns:
        Dictionary of permissions indexed by domain_id
    """
    domain_ids = [d.get('id') for d in domains if d.get('id')]
    return fetch_device_permissions(device_id, domain_ids)


def update_hosts_with_permissions(all_domains, device_restricted_domains):
    """
    Update hosts file based on device-specific restricted domains
    
    Args:
        all_domains: All domains from API
        device_restricted_domains: Restricted domains for this device
    """
    global original_hosts_content
    
    content = original_hosts_content.strip() + '\n\n' + SENTINEL_START + '\n'
    
    for domain in device_restricted_domains.keys():
        if not domain.startswith('www.'):  # Skip www duplicates
            content += f'{BLOCK_REDIRECT_IP} {domain}\n'
            content += f'{BLOCK_REDIRECT_IP} www.{domain}\n'
    
    content += SENTINEL_END + '\n'
    
    try:
        with open(HOSTS_PATH, 'w', encoding='utf-8') as f:
            f.write(content)
        if os.name == 'nt':
            subprocess.run(['ipconfig', '/flushdns'], capture_output=True)
        write_debug_log('[INFO] Hosts file updated with device-specific restrictions.')
    except Exception as exc:
        write_debug_log(f'[WARN] Unable to update hosts file: {exc}')

# ======================== HOSTS FILE MANAGEMENT ========================

def backup_hosts():
    global original_hosts_content
    try:
        with open(HOSTS_PATH, 'r', encoding='utf-8', errors='ignore') as f:
            lines = f.readlines()
            original_hosts_content = ''.join([line for line in lines if SENTINEL_START not in line and SENTINEL_END not in line])
    except Exception as exc:
        print(f'[WARN] Unable to read hosts file: {exc}')


def restore_hosts():
    try:
        with open(HOSTS_PATH, 'w', encoding='utf-8') as f:
            f.write(original_hosts_content.strip() + '\n')
        if os.name == 'nt':
            subprocess.run(['ipconfig', '/flushdns'], capture_output=True)
    except Exception as exc:
        print(f'[WARN] Unable to restore hosts file: {exc}')


def update_hosts(domains):
    active = [d['domain'].strip().lower() for d in domains if d.get('status') == 'restricted']
    content = original_hosts_content.strip() + '\n\n' + SENTINEL_START + '\n'
    for domain in active:
        content += f'{BLOCK_REDIRECT_IP} {domain}\n{BLOCK_REDIRECT_IP} www.{domain}\n'
    content += SENTINEL_END + '\n'

    try:
        with open(HOSTS_PATH, 'w', encoding='utf-8') as f:
            f.write(content)
        if os.name == 'nt':
            subprocess.run(['ipconfig', '/flushdns'], capture_output=True)
        write_debug_log('[INFO] Hosts file updated for blocked domains.')
    except Exception as exc:
        write_debug_log(f'[WARN] Unable to update hosts file: {exc}')

# ======================== DEVICE STORAGE ========================

def save_device_id(device_id):
    try:
        with open(DEVICE_FILE, 'w', encoding='utf-8') as f:
            f.write(device_id)
    except Exception:
        pass


def load_device_id():
    try:
        if os.path.exists(DEVICE_FILE):
            with open(DEVICE_FILE, 'r', encoding='utf-8') as f:
                return f.read().strip()
    except Exception:
        pass
    return None

# ======================== AGENT ACTIONS ========================

def get_local_ip():
    try:
        hostname = socket.gethostname()
        return socket.gethostbyname(hostname)
    except Exception:
        return '127.0.0.1'


def register_device():
    device_id = load_device_id()
    if device_id:
        return device_id

    payload = {
        'device_name': socket.gethostname(),
        'device_type': 'Windows' if os.name == 'nt' else 'Linux',
        'os_version': sys.platform,
        'ip_address': get_local_ip(),
        'mac_address': None,
    }
    response = api_request('register_device', method='POST', data=payload)
    if response and response.get('device_id'):
        device_id = response['device_id']
        save_device_id(device_id)
        write_debug_log(f'[INFO] Registered device: {device_id}')
        return device_id

    write_debug_log('[ERROR] Device registration failed.')
    return None


def fetch_domains(device_id):
    response = api_request('get_domains', method='GET', params={'device_id': device_id})
    if isinstance(response, dict):
        return response.get('domains', [])
    return []


def normalize_domain(domain):
    if not domain:
        return ''

    value = str(domain).strip().lower().rstrip('.')
    if value.startswith('http://'):
        value = value[7:]
    elif value.startswith('https://'):
        value = value[8:]

    return value.split('/')[0]


def build_restricted_domain_map(domains):
    domain_map = {}
    for domain in domains:
        if domain.get('status') != 'restricted':
            continue

        normalized = normalize_domain(domain.get('domain'))
        if not normalized:
            continue

        domain_map[normalized] = normalized
        domain_map[f'www.{normalized}'] = normalized

    return domain_map


def domains_signature(domains):
    normalized = []
    for domain in domains:
        normalized.append({
            'domain': normalize_domain(domain.get('domain')),
            'status': domain.get('status') or '',
            'category': domain.get('category') or '',
            'reason': domain.get('reason') or '',
        })

    normalized.sort(key=lambda item: (item['domain'], item['status'], item['category'], item['reason']))
    return json.dumps(normalized, sort_keys=True)


def parse_tls_sni(payload):
    try:
        if len(payload) < 5 or payload[0] != 22:
            return None

        offset = 5
        if payload[offset] != 1:
            return None

        offset += 4
        offset += 2
        offset += 32

        session_id_length = payload[offset]
        offset += 1 + session_id_length

        cipher_suites_length = int.from_bytes(payload[offset:offset + 2], 'big')
        offset += 2 + cipher_suites_length

        compression_methods_length = payload[offset]
        offset += 1 + compression_methods_length

        extensions_length = int.from_bytes(payload[offset:offset + 2], 'big')
        offset += 2
        end = offset + extensions_length

        while offset + 4 <= end and offset + 4 <= len(payload):
            ext_type = int.from_bytes(payload[offset:offset + 2], 'big')
            ext_length = int.from_bytes(payload[offset + 2:offset + 4], 'big')
            ext_data_start = offset + 4
            ext_data_end = ext_data_start + ext_length

            if ext_type == 0 and ext_data_end <= len(payload):
                list_length = int.from_bytes(payload[ext_data_start:ext_data_start + 2], 'big')
                cursor = ext_data_start + 2
                limit = min(cursor + list_length, ext_data_end)

                while cursor + 3 <= limit:
                    name_type = payload[cursor]
                    name_length = int.from_bytes(payload[cursor + 1:cursor + 3], 'big')
                    cursor += 3
                    if name_type == 0 and cursor + name_length <= limit:
                        return normalize_domain(payload[cursor:cursor + name_length].decode('utf-8', errors='ignore'))
                    cursor += name_length

            offset = ext_data_end
    except Exception:
        return None

    return None


class BlockEventListener:
    def __init__(self):
        self.domain_map = {}
        self.lock = threading.Lock()
        self.stop_event = threading.Event()
        self.event_queue = queue.Queue()
        self.threads = []

    def update_domains(self, domains):
        with self.lock:
            self.domain_map = build_restricted_domain_map(domains)

    def start(self):
        self.threads = [
            threading.Thread(target=self._tcp_listener, args=(80, self._handle_http_connection, 'HTTP'), daemon=True),
            threading.Thread(target=self._tcp_listener, args=(443, self._handle_https_connection, 'HTTPS'), daemon=True),
        ]

        for thread in self.threads:
            thread.start()

    def stop(self):
        self.stop_event.set()

    def poll_events(self):
        events = []
        while True:
            try:
                events.append(self.event_queue.get_nowait())
            except queue.Empty:
                break
        return events

    def _canonical_domain(self, hostname):
        normalized = normalize_domain(hostname)
        if not normalized:
            return None

        with self.lock:
            if normalized in self.domain_map:
                return self.domain_map[normalized]

            for candidate, original in self.domain_map.items():
                if normalized.endswith('.' + candidate):
                    return original

        return None

    def _queue_domain(self, hostname, source):
        canonical = self._canonical_domain(hostname)
        if not canonical:
            return

        self.event_queue.put({
            'domain': canonical,
            'source': source,
            'timestamp': time.time(),
        })

    def _tcp_listener(self, port, handler, label):
        server_socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        server_socket.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)

        try:
            server_socket.bind((BLOCK_REDIRECT_IP, port))
            server_socket.listen(50)
            server_socket.settimeout(1.0)
            write_debug_log(f'[INFO] {label} block listener started on {BLOCK_REDIRECT_IP}:{port}')
        except OSError as exc:
            write_debug_log(f'[WARN] Unable to start {label} block listener on port {port}: {exc}')
            try:
                server_socket.close()
            except OSError:
                pass
            return

        while not self.stop_event.is_set():
            try:
                client_socket, _ = server_socket.accept()
            except socket.timeout:
                continue
            except OSError:
                break

            threading.Thread(target=handler, args=(client_socket,), daemon=True).start()

        try:
            server_socket.close()
        except OSError:
            pass

    def _handle_http_connection(self, client_socket):
        try:
            client_socket.settimeout(2.0)
            payload = client_socket.recv(4096)
            request_text = payload.decode('utf-8', errors='ignore')
            host = ''

            for line in request_text.splitlines():
                if line.lower().startswith('host:'):
                    host = normalize_domain(line.split(':', 1)[1].strip())
                    break

            if host:
                self._queue_domain(host, 'http-host-header')

            response = (
                'HTTP/1.1 403 Forbidden\r\n'
                'Content-Type: text/html; charset=utf-8\r\n'
                'Connection: close\r\n\r\n'
                '<html><body><h1>Blocked by Sentinel</h1><p>This website is restricted.</p></body></html>'
            ).encode('utf-8')
            client_socket.sendall(response)
        except Exception:
            pass
        finally:
            try:
                client_socket.close()
            except OSError:
                pass

    def _handle_https_connection(self, client_socket):
        try:
            client_socket.settimeout(2.0)
            payload = client_socket.recv(4096)
            hostname = parse_tls_sni(payload)
            if hostname:
                self._queue_domain(hostname, 'https-sni')
        except Exception:
            pass
        finally:
            try:
                client_socket.close()
            except OSError:
                pass


def heartbeat(device_id):
    payload = {
        'device_id': device_id,
        'agent_version': '1.0',
        'status_info': {
            'platform': sys.platform,
        },
    }
    response = api_request('heartbeat', method='POST', data=payload)
    if response and response.get('status') == 'ok':
        write_debug_log('[INFO] Heartbeat sent')
        return True
    return False


def report_alert(device_id, domain, severity='critical', message='Live violation detected', log_id=None):
    payload = {
        'device_id': device_id,
        'device_name': socket.gethostname(),
        'domain': domain,
        'severity': severity,
        'message': message,
        'log_id': log_id,
    }
    response = api_request('report_alert', method='POST', data=payload)
    if response and response.get('alert_id'):
        return response.get('alert_id')
    return None


def log_access(device_id, domain, access_type='dns_query', status='detected', details=None):
    payload = {
        'device_id': device_id,
        'domain': domain,
        'access_type': access_type,
        'status': status,
        'details': details or {},
    }
    response = api_request('log_access', method='POST', data=payload)
    if response and response.get('log_id'):
        return response.get('log_id')
    return None


def fetch_pending_commands(device_id):
    response = api_request('get_commands', method='GET', params={'device_id': device_id})
    if isinstance(response, dict):
        return response.get('commands', [])
    return []


def update_command_status(command_id, status):
    response = api_request('update_command_status', method='POST', data={'id': command_id, 'status': status})
    return isinstance(response, dict) and response.get('message') is not None


def show_popup(message, title='Sentinel Warning'):
    if not message:
        return

    if os.name == 'nt':
        try:
            import ctypes
            flags = 0x1000 | 0x30  # MB_SYSTEMMODAL | MB_ICONWARNING
            ctypes.windll.user32.MessageBoxW(0, message, title, flags)
            return
        except Exception as exc:
            print(f'[WARN] Unable to display popup: {exc}')

    print(f'[POPUP] {title}: {message}')


def show_block_warning(domain):
    message = (
        f'{DEFAULT_BLOCK_MESSAGE}\n\n'
        f'Blocked domain: {domain}\n'
        'Please close the site and contact the administrator if you need access.'
    )
    show_popup(message, title='Restricted Website Blocked')


def get_python_executable():
    return sys.executable or 'python'


def get_agent_command():
    script_path = os.path.abspath(__file__)
    return f'"{get_python_executable()}" "{script_path}" run'


def run_subprocess(cmd):
    try:
        result = subprocess.run(cmd, capture_output=True, text=True, check=False)
        return result.returncode, result.stdout.strip(), result.stderr.strip()
    except Exception as exc:
        return 1, '', str(exc)


def is_admin():
    if os.name == 'nt':
        try:
            import ctypes
            return ctypes.windll.shell32.IsUserAnAdmin() != 0
        except Exception:
            return False

    return hasattr(os, 'geteuid') and os.geteuid() == 0


def install_windows_task():
    task_cmd = get_agent_command()
    args = [
        'schtasks', '/Create', '/TN', WINDOWS_TASK_NAME,
        '/TR', task_cmd,
        '/SC', 'ONSTART', '/RL', 'HIGHEST', '/F'
    ]
    code, out, err = run_subprocess(args)
    if code == 0:
        print(f'Installed scheduled task "{WINDOWS_TASK_NAME}".')
        return True

    print(f'Failed to install scheduled task: {err or out}')
    return False


def uninstall_windows_task():
    args = ['schtasks', '/Delete', '/TN', WINDOWS_TASK_NAME, '/F']
    code, out, err = run_subprocess(args)
    if code == 0:
        print(f'Removed scheduled task "{WINDOWS_TASK_NAME}".')
        return True

    print(f'Failed to remove scheduled task: {err or out}')
    return False


def install_systemd_service():
    if not is_admin():
        print('Root privileges are required to install the systemd service.')
        return False

    unit_path = f'/etc/systemd/system/{AGENT_SERVICE_NAME}.service'
    python_path = shlex.quote(get_python_executable())
    script_path = shlex.quote(os.path.abspath(__file__))
    unit_contents = f'''[Unit]
Description=Sentinel agent service
After=network.target

[Service]
Type=simple
ExecStart={python_path} {script_path} run
Restart=always
RestartSec=5
WorkingDirectory={shlex.quote(os.path.abspath(BASE_DIR))}
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
'''

    try:
        with open(unit_path, 'w', encoding='utf-8') as unit_file:
            unit_file.write(unit_contents)

        subprocess.run(['systemctl', 'daemon-reload'], check=False)
        subprocess.run(['systemctl', 'enable', AGENT_SERVICE_NAME], check=False)
        subprocess.run(['systemctl', 'start', AGENT_SERVICE_NAME], check=False)
        print(f'Installed systemd service "{AGENT_SERVICE_NAME}.service".')
        return True
    except Exception as exc:
        print(f'Failed to install systemd service: {exc}')
        return False


def uninstall_systemd_service():
    if not is_admin():
        print('Root privileges are required to uninstall the systemd service.')
        return False

    unit_path = f'/etc/systemd/system/{AGENT_SERVICE_NAME}.service'
    subprocess.run(['systemctl', 'stop', AGENT_SERVICE_NAME], check=False)
    subprocess.run(['systemctl', 'disable', AGENT_SERVICE_NAME], check=False)

    try:
        if os.path.exists(unit_path):
            os.remove(unit_path)
        subprocess.run(['systemctl', 'daemon-reload'], check=False)
        print(f'Removed systemd service "{AGENT_SERVICE_NAME}.service".')
        return True
    except Exception as exc:
        print(f'Failed to remove systemd service: {exc}')
        return False


def install_agent():
    if os.name == 'nt':
        return install_windows_task()
    return install_systemd_service()


def uninstall_agent():
    if os.name == 'nt':
        return uninstall_windows_task()
    return uninstall_systemd_service()


def status_agent():
    if os.name == 'nt':
        args = ['schtasks', '/Query', '/TN', WINDOWS_TASK_NAME]
        code, _, _ = run_subprocess(args)
        return code == 0

    args = ['systemctl', 'is-active', '--quiet', AGENT_SERVICE_NAME]
    code, _, _ = run_subprocess(args)
    return code == 0


def execute_command(command):
    target = command.get('device_name') or command.get('device_id')
    command_id = command.get('id')
    message = (command.get('message') or '').strip()
    write_debug_log(f"[COMMAND] Executing command {command_id} for {target}: {message}")

    if not update_command_status(command_id, 'sent'):
        write_debug_log(f"[WARN] Unable to update status for command {command_id} to sent")
        return

    write_debug_log(f"[COMMAND] {command_id} marked as sent.")
    if message:
        show_popup(message, title='Sentinel Warning')

    # Simulate command delivery confirmation after execution
    time.sleep(1)
    if update_command_status(command_id, 'delivered'):
        write_debug_log(f"[COMMAND] {command_id} marked as delivered.")
    else:
        write_debug_log(f"[WARN] Unable to update status for command {command_id} to delivered")

# ======================== LOCAL BLOCK LISTENER ========================

# ======================== MAIN LOOP ========================

def main():
    if os.name == 'nt':
        try:
            import ctypes
            if not ctypes.windll.shell32.IsUserAnAdmin():
                write_debug_log('Run this agent as Administrator.')
                return
        except Exception:
            write_debug_log('Unable to verify admin status; proceed with caution.')

    atexit.register(restore_hosts)
    signal.signal(signal.SIGINT, lambda s, f: sys.exit(0))

    backup_hosts()
    device_id = register_device()
    if not device_id:
        return

    block_listener = BlockEventListener()
    block_listener.start()
    atexit.register(block_listener.stop)

    domains = fetch_domains(device_id)
    permissions_cache = sync_device_permissions(device_id, domains)
    
    # Build domain map with device-specific permissions
    restricted_domains = build_restricted_domain_map_with_device(device_id, domains, permissions_cache)
    
    # Update hosts file with device-aware restrictions
    update_hosts_with_permissions(domains, restricted_domains)
    block_listener.update_domains(domains)
    current_domains_signature = domains_signature(domains)
    heartbeat(device_id)
    write_debug_log(f'[INFO] Monitoring {len(restricted_domains) // 2} restricted domains for blocked browsing attempts.')
    write_debug_log(f'[INFO] Debug log file: {DEBUG_LOG_FILE}')

    last_domain_refresh = time.time()
    last_heartbeat = time.time()
    last_command_poll = 0

    while True:
        now = time.time()
        for blocked_event in block_listener.poll_events():
            domain = blocked_event['domain']
            source = blocked_event['source']
            event_time = blocked_event['timestamp']
            if event_time - last_alert_time.get(domain, 0) < EVENT_DEBOUNCE_SECONDS:
                continue

            write_debug_log(f'[DETECT] Restricted domain attempt detected for {domain} via {source}')
            log_id = log_access(
                device_id,
                domain,
                access_type='dns_query',
                status='blocked',
                details={
                    'source': source,
                    'resolved_ip': BLOCK_REDIRECT_IP,
                    'event_timestamp': int(event_time),
                },
            )
            alert_id = report_alert(
                device_id,
                domain,
                severity='critical',
                message=f'Restricted domain attempted and redirected to {BLOCK_REDIRECT_IP}: {domain}',
                log_id=log_id,
            )
            last_alert_time[domain] = event_time
            if log_id:
                write_debug_log(f'[LOG] Access logged for {domain} with log_id={log_id}')
            if alert_id:
                write_debug_log(f'[ALERT] Reported domain {domain} with alert_id={alert_id}')
            else:
                write_debug_log(f'[WARN] Alert API did not return an alert_id for {domain}')
            show_block_warning(domain)

        if now - last_command_poll > 5:
            pending = fetch_pending_commands(device_id)
            if pending:
                write_debug_log(f'[COMMAND] Retrieved {len(pending)} pending command(s)')
            for cmd in pending:
                execute_command(cmd)
            last_command_poll = now

        if now - last_domain_refresh > DOMAIN_REFRESH_SECONDS:
            latest_domains = fetch_domains(device_id)
            latest_signature = domains_signature(latest_domains)
            if latest_signature != current_domains_signature:
                domains = latest_domains
                current_domains_signature = latest_signature
                # Refresh device permissions and update with device-aware restrictions
                permissions_cache = sync_device_permissions(device_id, domains)
                restricted_domains = build_restricted_domain_map_with_device(device_id, domains, permissions_cache)
                update_hosts_with_permissions(domains, restricted_domains)
                block_listener.update_domains(domains)
                write_debug_log(f'[INFO] Domain rules updated in real time. Active restricted domains: {len(restricted_domains) // 2}')
            last_domain_refresh = now

        if now - last_heartbeat > HEARTBEAT_SECONDS:
            heartbeat(device_id)
            last_heartbeat = now

        time.sleep(0.1)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Sentinel agent control')
    parser.add_argument('command', nargs='?', default='run', choices=['run', 'install', 'uninstall', 'status'],
                        help='Action to perform: run the agent, install at startup, uninstall, or show status')
    args = parser.parse_args()

    if args.command == 'run':
        main()
    elif args.command == 'install':
        if install_agent():
            print('Sentinel agent install complete.')
        else:
            sys.exit(1)
    elif args.command == 'uninstall':
        if uninstall_agent():
            print('Sentinel agent uninstall complete.')
        else:
            sys.exit(1)
    elif args.command == 'status':
        installed = status_agent()
        print(f'Sentinel agent installed: {installed}')
        sys.exit(0 if installed else 1)
