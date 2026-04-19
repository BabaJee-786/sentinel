<?php
/**
 * AGENT.PY UPGRADE CODE
 * 
 * Add the following functions to agent.py after the "write_debug_log" function
 * These functions implement device-specific domain permission checking
 * 
 * Location: agent.py, after line ~150 (after write_debug_log function)
 */
?>

# ======================== DEVICE PERMISSION CHECKING ========================

def fetch_device_permissions(device_id, domain_ids):
    """
    Fetch device-specific domain permissions from the server
    
    Args:
        device_id: The device ID
        domain_ids: List of domain IDs to check permissions for
    
    Returns:
        Dictionary mapping domain_id to permission (True = allowed, False = blocked, None = use default)
    """
    if not domain_ids:
        return {}
    
    result = api_request(
        'fetch_device_permissions',
        method='POST',
        data={
            'device_id': device_id,
            'domain_ids': domain_ids
        }
    )
    
    if result and result.get('success'):
        return result.get('permissions', {})
    
    return {}


def check_domain_permission(device_id, domain, domain_id, permissions_cache=None):
    """
    Check if a device is allowed to access a specific domain
    
    Permission hierarchy:
    1. If device has specific permission override, use it
    2. If domain is paused globally, allow access
    3. Otherwise, apply default blocking
    
    Args:
        device_id: The device ID
        domain: The domain name (string)
        domain_id: The domain ID (string)
        permissions_cache: Optional cached permissions dict
    
    Returns:
        - True: Device is allowed to access (override active)
        - False: Device should be blocked
        - None: Check global domain status
    """
    # Check device-specific permission cache
    if permissions_cache and domain_id in permissions_cache:
        perm = permissions_cache[domain_id]
        if perm.get('is_allowed'):
            write_debug_log(f'[ACCESS] Device {device_id} has permission override for {domain}')
            return True
    
    return None


def should_block_domain(domain_dict, device_id, permissions_cache=None):
    """
    Determine if a domain should be blocked for a specific device
    
    Factors:
    - Device-specific permission (highest priority)
    - Domain status (live/paused)
    - Default blocking rules
    
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
        return False  # Device is allowed access
    
    # Check global domain status
    if status == 'paused':
        return False  # Domain is temporarily disabled globally
    
    if status == 'live' or status == 'restricted':
        return True  # Block this domain
    
    return False  # Default: allow


def build_restricted_domain_map_with_device(device_id, domains, permissions_cache=None):
    """
    Build a map of restricted domains for a specific device
    Takes device permissions into account
    
    Args:
        device_id: The device ID
        domains: List of all domains from API
        permissions_cache: Cached device permissions
    
    Returns:
        Dictionary mapping normalized domains to original domain objects
    """
    domain_map = {}
    
    for domain_obj in domains:
        # Check if device should block this domain
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
    Call this when updating domains or on regular intervals
    
    Args:
        device_id: The device ID
        domains: List of domain dictionaries
    
    Returns:
        Dictionary of permissions
    """
    domain_ids = [d.get('id') for d in domains if d.get('id')]
    permissions = fetch_device_permissions(device_id, domain_ids)
    return {d.get('id'): d for d in permissions}


# ======================== USAGE IN MAIN LOOP ========================

# In the main() function, modify the domain fetching and blocking logic:

# OLD CODE (line ~880):
# domains = fetch_domains(device_id)
# update_hosts(domains)
# block_listener.update_domains(domains)

# NEW CODE:
# domains = fetch_domains(device_id)
# permissions_cache = sync_device_permissions(device_id, domains)
# 
# # Build domain map with device-specific permissions
# restricted_domains = build_restricted_domain_map_with_device(device_id, domains, permissions_cache)
# 
# # Update hosts file with device-aware restrictions
# update_hosts_with_permissions(domains, restricted_domains)
# block_listener.update_domains(domains)  # Still pass all domains for status tracking


def update_hosts_with_permissions(all_domains, device_restricted_domains):
    """
    Update hosts file based on device-specific restricted domains
    
    Args:
        all_domains: All domains from API
        device_restricted_domains: Restricted domains for this device
    """
    global original_hosts_content
    
    content = original_hosts_content.strip() + '\n\n' + SENTINEL_START + '\n'
    
    # Add device-restricted domains to hosts
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
        write_debug_log(f'[INFO] Hosts file updated for {len([d for d in device_restricted_domains.keys() if not d.startswith("www.")])} device-restricted domains.')
    except Exception as exc:
        write_debug_log(f'[WARN] Unable to update hosts file: {exc}')


# ======================== INSTALLATION INSTRUCTIONS ========================

# 1. Copy the functions above into agent.py after line ~150
# 2. Update the main() function to use sync_device_permissions():
#    - After fetch_domains call, add: permissions_cache = sync_device_permissions(device_id, domains)
#    - Replace update_hosts() call with device-aware logic
# 3. Update domain refresh logic in the main loop to also refresh permissions

# ======================== TESTING THE FEATURE ========================

# Test Commands:
# 1. In the dashboard, set a domain as "Live" (blocking active)
# 2. Go to Domains > Select domain > "Assign to Devices"
# 3. Uncheck a device to bypass that domain for that device
# 4. The agent will fetch permissions on next refresh (~5 seconds)
# 5. Device will no longer block that specific domain while others continue to be blocked
