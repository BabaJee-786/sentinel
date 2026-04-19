-- ============================================================
-- Sentinel Database Upgrades - Version 2
-- Adds device management, domain status, and permissions features
-- ============================================================

-- ============================================================
-- 1. UPGRADE: Add status and is_active columns to devices table
-- ============================================================
ALTER TABLE devices ADD COLUMN IF NOT EXISTS status ENUM('active', 'disabled') DEFAULT 'active' COMMENT 'Device status: active or disabled';

-- ============================================================
-- 2. UPGRADE: Add status column to domains table
-- ============================================================
-- Note: This modifies the existing status ENUM to add 'live' and 'paused'
ALTER TABLE domains MODIFY COLUMN status ENUM('restricted', 'allowed', 'monitored', 'live', 'paused') DEFAULT 'live' COMMENT 'Domain blocking status';

-- ============================================================
-- 3. NEW TABLE: Device-Domain Permissions
-- Allows specific devices to bypass restricted domains
-- ============================================================
CREATE TABLE IF NOT EXISTS device_domain_permissions (
    id VARCHAR(36) PRIMARY KEY COMMENT 'Unique permission ID',
    device_id VARCHAR(36) NOT NULL COMMENT 'Device reference',
    domain_id VARCHAR(36) NOT NULL COMMENT 'Domain reference',
    is_allowed BOOLEAN DEFAULT 1 COMMENT '1 = allowed to access, 0 = blocked',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When permission was created',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'When permission was last updated',
    UNIQUE KEY unique_device_domain (device_id, domain_id),
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE,
    INDEX idx_device_id (device_id),
    INDEX idx_domain_id (domain_id),
    INDEX idx_is_allowed (is_allowed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Per-device domain access permissions';

-- ============================================================
-- 4. NEW TABLE: Settings for SMTP Email Configuration
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
    id VARCHAR(36) PRIMARY KEY COMMENT 'Setting ID',
    user_id VARCHAR(36) COMMENT 'User reference (for multi-tenant support)',
    setting_key VARCHAR(100) NOT NULL UNIQUE COMMENT 'Setting key name',
    setting_value LONGTEXT COMMENT 'Setting value (may be JSON)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System settings and configuration';

-- Pre-populate default settings
INSERT INTO settings (id, setting_key, setting_value) VALUES
('00000000-0000-0000-0000-000000000101', 'smtp_host', 'smtp.gmail.com'),
('00000000-0000-0000-0000-000000000102', 'smtp_port', '587'),
('00000000-0000-0000-0000-000000000103', 'smtp_email', 'alerts@sentinel.local'),
('00000000-0000-0000-0000-000000000104', 'smtp_password', ''),
('00000000-0000-0000-0000-000000000105', 'email_enabled', '0'),
('00000000-0000-0000-0000-000000000106', 'email_alert_severity', 'high'),
('00000000-0000-0000-0000-000000000107', 'organization_name', 'Sentinel Security Lab')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

-- ============================================================
-- 5. NEW TABLE: Email Logs (track sent emails)
-- ============================================================
CREATE TABLE IF NOT EXISTS email_logs (
    id VARCHAR(36) PRIMARY KEY COMMENT 'Email log ID',
    alert_id VARCHAR(36) COMMENT 'Related alert reference',
    device_id VARCHAR(36) COMMENT 'Device reference',
    recipient_email VARCHAR(255) COMMENT 'Email sent to',
    subject VARCHAR(255) COMMENT 'Email subject',
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When email was sent',
    status ENUM('sent', 'failed', 'bounced') DEFAULT 'sent' COMMENT 'Email delivery status',
    error_message TEXT COMMENT 'Error details if failed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (alert_id) REFERENCES alerts(id) ON DELETE SET NULL,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL,
    INDEX idx_alert_id (alert_id),
    INDEX idx_device_id (device_id),
    INDEX idx_sent_at (sent_at),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Email delivery logs for audit trail';

-- ============================================================
-- 6. NEW TABLE: Admin Audit Log
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id VARCHAR(36) PRIMARY KEY COMMENT 'Audit log ID',
    user_id VARCHAR(36) COMMENT 'Admin user reference',
    action VARCHAR(100) NOT NULL COMMENT 'Action performed',
    entity_type VARCHAR(50) COMMENT 'Type of entity affected (device, domain, etc.)',
    entity_id VARCHAR(36) COMMENT 'ID of entity affected',
    old_value LONGTEXT COMMENT 'Previous value (JSON)',
    new_value LONGTEXT COMMENT 'New value (JSON)',
    ip_address VARCHAR(45) COMMENT 'Admin IP address',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_entity_type (entity_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Admin activity audit trail';

-- ============================================================
-- 7. Add indexes for better query performance
-- ============================================================
ALTER TABLE devices ADD INDEX IF NOT EXISTS idx_status (status);
ALTER TABLE devices ADD INDEX IF NOT EXISTS idx_device_name (device_name);
ALTER TABLE domains ADD INDEX IF NOT EXISTS idx_status (status);
ALTER TABLE alerts ADD INDEX IF NOT EXISTS idx_created_at (created_at);

-- ============================================================
-- 8. Verification
-- ============================================================
-- These queries verify the migrations work correctly
-- SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='devices' AND COLUMN_NAME='status';
-- SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='domains' AND COLUMN_NAME='status';
-- SELECT * FROM information_schema.TABLES WHERE TABLE_NAME='device_domain_permissions';
-- SELECT * FROM information_schema.TABLES WHERE TABLE_NAME='settings';
-- SELECT COUNT(*) FROM settings;
