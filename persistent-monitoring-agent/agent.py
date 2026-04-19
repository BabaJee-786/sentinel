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
import signal
import threading
import queue
import shutil

# ======================== CONFIG ========================
IS_FROZEN = getattr(sys, 'frozen', False)
RUNTIME_PATH = sys.executable if IS_FROZEN else __file__
BASE_DIR = os.path.dirname(os.path.abspath(RUNTIME_PATH))


def get_env_paths():
    env_paths = [os.path.join(BASE_DIR, '.env')]
    parent_dir = os.path.dirname(BASE_DIR)
    if parent_dir and parent_dir != BASE_DIR:
        env_paths.append(os.path.join(parent_dir, '.env'))
    return env_paths


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


ENV = {}
for env_path in get_env_paths():
    ENV.update(load_env_file(env_path))


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
SENTINEL_START = '# === SENTINEL PERSISTENT AGENT START ==='
SENTINEL_END = '# === SENTINEL PERSISTENT AGENT END ==='
DEVICE_FILE = os.path.join(BASE_DIR, '.sentinel_persistent_agent_device_id')
DEBUG_LOG_FILE = os.path.join(BASE_DIR, 'persistent_agent_debug.log')
LOCK_FILE = os.path.join(BASE_DIR, 'persistent_agent.lock')

AGENT_VERSION = '2.0'
AGENT_SERVICE_NAME = 'sentinel-persistent-agent'
WINDOWS_TASK_NAME = 'SentinelPersistentAgent'
WINDOWS_INSTALL_DIR = os.path.join(
    os.environ.get('ProgramData', r'C:\ProgramData'),
    'SentinelPersistentAgent',
)
WINDOWS_INSTALL_EXE = os.path.join(WINDOWS_INSTALL_DIR, 'SentinelPersistentAgent.exe')
WINDOWS_INSTALL_SCRIPT = os.path.join(WINDOWS_INSTALL_DIR, 'agent.py')

cached_domains = []
last_alert_time = {}
DEFAULT_BLOCK_MESSAGE = 'Warning: Access to this website is restricted by Sentinel security policy.'
EVENT_DEBOUNCE_SECONDS = 3
DOMAIN_REFRESH_SECONDS = 5
HEARTBEAT_SECONDS = 30
COMMAND_POLL_SECONDS = 5
SERVICE_RETRY_SECONDS = 15

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


def find_existing_env_file():
    for env_path in get_env_paths():
        if os.path.exists(env_path):
            return env_path
    return None


def acquire_single_instance_lock():
    try:
        lock_handle = open(LOCK_FILE, 'a+', encoding='utf-8')
        lock_handle.seek(0)
        if lock_handle.tell() == 0:
            lock_handle.write('0')
            lock_handle.flush()
        lock_handle.seek(0)

        if os.name == 'nt':
            import msvcrt

            msvcrt.locking(lock_handle.fileno(), msvcrt.LK_NBLCK, 1)
        else:
            import fcntl

            fcntl.flock(lock_handle.fileno(), fcntl.LOCK_EX | fcntl.LOCK_NB)

        lock_handle.seek(0)
        lock_handle.truncate()
        lock_handle.write(str(os.getpid()))
        lock_handle.flush()
        return lock_handle
    except OSError:
        return None

# ======================== HOSTS FILE MANAGEMENT ========================

def flush_dns():
    if os.name == 'nt':
        subprocess.run(['ipconfig', '/flushdns'], capture_output=True, check=False)


def strip_managed_hosts_block(content):
    cleaned_lines = []
    inside_managed_block = False

    for raw_line in content.splitlines():
        line = raw_line.strip()
        if line == SENTINEL_START:
            inside_managed_block = True
            continue
        if line == SENTINEL_END:
            inside_managed_block = False
            continue
        if not inside_managed_block:
            cleaned_lines.append(raw_line)

    return '\n'.join(cleaned_lines).strip()


def read_hosts_content():
    try:
        with open(HOSTS_PATH, 'r', encoding='utf-8', errors='ignore') as hosts_file:
            return hosts_file.read()
    except OSError as exc:
        write_debug_log(f'[WARN] Unable to read hosts file: {exc}')
        return ''


def update_hosts(domains):
    active = []
    for domain in domains:
        if domain.get('status') != 'restricted':
            continue
        normalized = normalize_domain(domain.get('domain'))
        if normalized:
            active.append(normalized)

    base_content = strip_managed_hosts_block(read_hosts_content())
    lines = [SENTINEL_START]
    for domain in sorted(set(active)):
        lines.append(f'{BLOCK_REDIRECT_IP} {domain}')
        lines.append(f'{BLOCK_REDIRECT_IP} www.{domain}')
    lines.append(SENTINEL_END)

    try:
        with open(HOSTS_PATH, 'w', encoding='utf-8') as hosts_file:
            if active:
                if base_content:
                    hosts_file.write(base_content + '\n\n')
                hosts_file.write('\n'.join(lines) + '\n')
            else:
                hosts_file.write(base_content.strip() + '\n')
        flush_dns()
        write_debug_log('[INFO] Hosts file updated for blocked domains.')
    except OSError as exc:
        write_debug_log(f'[WARN] Unable to update hosts file: {exc}')


def remove_managed_hosts_entries():
    try:
        cleaned_content = strip_managed_hosts_block(read_hosts_content())
        with open(HOSTS_PATH, 'w', encoding='utf-8') as hosts_file:
            hosts_file.write(cleaned_content.strip() + '\n')
        flush_dns()
    except OSError as exc:
        write_debug_log(f'[WARN] Unable to clean up managed hosts entries: {exc}')

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
        'agent_version': AGENT_VERSION,
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


def is_system_account():
    return os.name == 'nt' and os.environ.get('USERNAME', '').strip().upper() == 'SYSTEM'


def show_popup(message, title='Sentinel Warning'):
    if not message:
        return

    if os.name == 'nt':
        if is_system_account():
            msg_text = f'{title}: {message}'.replace('\r', ' ').replace('\n', ' ')
            code, _, err = run_subprocess(['msg', '*', msg_text])
            if code == 0:
                return
            write_debug_log(f'[WARN] Unable to deliver session message via msg.exe: {err}')

        try:
            import ctypes
            flags = 0x1000 | 0x30
            ctypes.windll.user32.MessageBoxW(0, message, title, flags)
            return
        except Exception as exc:
            write_debug_log(f'[WARN] Unable to display popup: {exc}')

    write_debug_log(f'[POPUP] {title}: {message}')


def show_block_warning(domain):
    message = (
        f'{DEFAULT_BLOCK_MESSAGE}\n\n'
        f'Blocked domain: {domain}\n'
        'Please close the site and contact the administrator if you need access.'
    )
    show_popup(message, title='Restricted Website Blocked')


def get_python_executable():
    return sys.executable or 'python'


def get_runtime_target():
    return os.path.abspath(sys.executable if IS_FROZEN else __file__)


def get_agent_command(target_path=None):
    target_path = os.path.abspath(target_path or get_runtime_target())
    if target_path.lower().endswith('.exe'):
        return f'"{target_path}" run'
    return f'"{get_python_executable()}" "{target_path}" run'


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


def start_hidden_background_process(target_path):
    target_path = os.path.abspath(target_path)
    if target_path.lower().endswith('.exe'):
        command = [target_path, 'run']
    else:
        command = [get_python_executable(), target_path, 'run']

    creation_flags = 0
    startupinfo = None
    if os.name == 'nt':
        creation_flags = 0x00000008 | 0x00000200 | 0x08000000
        startupinfo = subprocess.STARTUPINFO()
        startupinfo.dwFlags |= subprocess.STARTF_USESHOWWINDOW
        startupinfo.wShowWindow = 0

    try:
        subprocess.Popen(
            command,
            cwd=os.path.dirname(target_path),
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
            stdin=subprocess.DEVNULL,
            close_fds=True,
            creationflags=creation_flags,
            startupinfo=startupinfo,
        )
        return True
    except OSError as exc:
        write_debug_log(f'[WARN] Failed to start hidden background process: {exc}')
        return False


def install_windows_payload():
    env_path = find_existing_env_file()
    if not env_path:
        print('Missing .env file. Create it next to the installer or executable before installing.')
        return None

    try:
        os.makedirs(WINDOWS_INSTALL_DIR, exist_ok=True)
        if IS_FROZEN:
            shutil.copy2(get_runtime_target(), WINDOWS_INSTALL_EXE)
            installed_target = WINDOWS_INSTALL_EXE
        else:
            shutil.copy2(os.path.abspath(__file__), WINDOWS_INSTALL_SCRIPT)
            installed_target = WINDOWS_INSTALL_SCRIPT

        shutil.copy2(env_path, os.path.join(WINDOWS_INSTALL_DIR, '.env'))
        env_example = os.path.join(os.path.dirname(env_path), '.env.example')
        if os.path.exists(env_example):
            shutil.copy2(env_example, os.path.join(WINDOWS_INSTALL_DIR, '.env.example'))

        if os.name == 'nt':
            subprocess.run(['attrib', '+h', '+s', WINDOWS_INSTALL_DIR], capture_output=True, check=False)

        return installed_target
    except OSError as exc:
        print(f'Failed to copy installation payload: {exc}')
        return None


def install_windows_task():
    if not is_admin():
        print('Administrator privileges are required to install the Windows agent.')
        return False

    installed_target = install_windows_payload()
    if not installed_target:
        return False

    task_cmd = get_agent_command(installed_target)
    args = [
        'schtasks', '/Create', '/TN', WINDOWS_TASK_NAME,
        '/TR', task_cmd,
        '/SC', 'ONSTART',
        '/RU', 'SYSTEM',
        '/RL', 'HIGHEST',
        '/F'
    ]
    code, out, err = run_subprocess(args)
    if code != 0:
        print(f'Failed to install scheduled task: {err or out}')
        return False

    run_subprocess(['schtasks', '/Run', '/TN', WINDOWS_TASK_NAME])
    start_hidden_background_process(installed_target)
    print(f'Installed scheduled task "{WINDOWS_TASK_NAME}".')
    print('The agent starts immediately in the background and will persist across reboots.')
    return True


def uninstall_windows_task():
    if not is_admin():
        print('Administrator privileges are required to uninstall the Windows agent.')
        return False

    run_subprocess(['schtasks', '/End', '/TN', WINDOWS_TASK_NAME])
    args = ['schtasks', '/Delete', '/TN', WINDOWS_TASK_NAME, '/F']
    code, out, err = run_subprocess(args)
    if code != 0 and 'cannot find the file specified' not in (err or out).lower():
        print(f'Failed to remove scheduled task: {err or out}')
        return False

    remove_managed_hosts_entries()
    try:
        if os.path.isdir(WINDOWS_INSTALL_DIR):
            shutil.rmtree(WINDOWS_INSTALL_DIR, ignore_errors=True)
    except OSError:
        pass
    print(f'Removed scheduled task "{WINDOWS_TASK_NAME}".')
    return True


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

def ensure_runtime_privileges():
    if os.name != 'nt':
        return True

    if is_admin():
        return True

    write_debug_log('[ERROR] The Windows monitoring agent must run as Administrator or SYSTEM.')
    return False


def run_agent_loop():
    if not ensure_runtime_privileges():
        raise RuntimeError('Insufficient privileges to run the agent.')

    device_id = register_device()
    block_listener = BlockEventListener()
    block_listener.start()

    try:
        domains = fetch_domains(device_id)
        update_hosts(domains)
        block_listener.update_domains(domains)
        current_domains_signature = domains_signature(domains)
        heartbeat(device_id)
        write_debug_log(
            f'[INFO] Monitoring {len(build_restricted_domain_map(domains)) // 2} restricted domains for blocked browsing attempts.'
        )
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

            if now - last_command_poll >= COMMAND_POLL_SECONDS:
                pending = fetch_pending_commands(device_id)
                if pending:
                    write_debug_log(f'[COMMAND] Retrieved {len(pending)} pending command(s)')
                for cmd in pending:
                    execute_command(cmd)
                last_command_poll = now

            if now - last_domain_refresh >= DOMAIN_REFRESH_SECONDS:
                latest_domains = fetch_domains(device_id)
                latest_signature = domains_signature(latest_domains)
                if latest_signature != current_domains_signature:
                    domains = latest_domains
                    current_domains_signature = latest_signature
                    update_hosts(domains)
                    block_listener.update_domains(domains)
                    write_debug_log(
                        f'[INFO] Domain rules updated in real time. Active restricted domains: '
                        f'{len(build_restricted_domain_map(domains)) // 2}'
                    )
                last_domain_refresh = now

            if now - last_heartbeat >= HEARTBEAT_SECONDS:
                heartbeat(device_id)
                last_heartbeat = now

            time.sleep(0.1)
    finally:
        block_listener.stop()


def run_agent_forever():
    lock_handle = acquire_single_instance_lock()
    if lock_handle is None:
        write_debug_log('[INFO] Another agent instance is already running. Exiting duplicate process.')
        return 0

    if hasattr(signal, 'SIGINT'):
        signal.signal(signal.SIGINT, lambda s, f: sys.exit(0))

    while True:
        try:
            run_agent_loop()
            write_debug_log('[WARN] Agent loop exited unexpectedly. Restarting.')
        except KeyboardInterrupt:
            return 0
        except SystemExit:
            raise
        except Exception as exc:
            write_debug_log(f'[ERROR] Agent crashed: {exc}')

        time.sleep(SERVICE_RETRY_SECONDS)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Sentinel persistent agent control')
    parser.add_argument('command', nargs='?', default='run', choices=['run', 'run-once', 'install', 'uninstall', 'status'],
                        help='Action to perform: run the agent, install at startup, uninstall, or show status')
    args = parser.parse_args()

    if args.command == 'run':
        sys.exit(run_agent_forever())
    elif args.command == 'run-once':
        lock_handle = acquire_single_instance_lock()
        if lock_handle is None:
            write_debug_log('[INFO] Another agent instance is already running. Exiting duplicate process.')
            sys.exit(0)
        run_agent_loop()
    elif args.command == 'install':
        if install_agent():
            print('Sentinel persistent agent install complete.')
        else:
            sys.exit(1)
    elif args.command == 'uninstall':
        if uninstall_agent():
            print('Sentinel persistent agent uninstall complete.')
        else:
            sys.exit(1)
    elif args.command == 'status':
        installed = status_agent()
        print(f'Sentinel persistent agent installed: {installed}')
        sys.exit(0 if installed else 1)
