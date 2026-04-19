-- Sentinel Database Schema
-- MySQL/MariaDB migration from Supabase

-- Create devices table
CREATE TABLE IF NOT EXISTS devices (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36),
    device_name VARCHAR(255) NOT NULL,
    device_type VARCHAR(50),
    os_version VARCHAR(100),
    ip_address VARCHAR(45),
    mac_address VARCHAR(17),
    last_heartbeat TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_last_heartbeat (last_heartbeat)
);

-- Create domains table (blacklist/whitelist)
CREATE TABLE IF NOT EXISTS domains (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36),
    domain VARCHAR(255) NOT NULL,
    status ENUM('restricted', 'allowed', 'monitored') DEFAULT 'restricted',
    category VARCHAR(100),
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_domain_user (domain, user_id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
);

-- Create logs table
CREATE TABLE IF NOT EXISTS logs (
    id VARCHAR(36) PRIMARY KEY,
    device_id VARCHAR(36) NOT NULL,
    user_id VARCHAR(36),
    domain VARCHAR(255) NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    access_type ENUM('dns_query', 'https_request', 'http_request', 'ping') DEFAULT 'dns_query',
    status ENUM('allowed', 'blocked', 'detected') DEFAULT 'detected',
    ip_address VARCHAR(45),
    process_name VARCHAR(255),
    details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_device_id (device_id),
    INDEX idx_user_id (user_id),
    INDEX idx_timestamp (timestamp),
    INDEX idx_domain (domain),
    CONSTRAINT fk_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

-- Create alerts table
CREATE TABLE IF NOT EXISTS alerts (
    id VARCHAR(36) PRIMARY KEY,
    device_id VARCHAR(36) NOT NULL,
    user_id VARCHAR(36),
    log_id VARCHAR(36),
    domain VARCHAR(255) NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    action_taken VARCHAR(255),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_device_id (device_id),
    INDEX idx_user_id (user_id),
    INDEX idx_severity (severity),
    INDEX idx_is_read (is_read),
    INDEX idx_timestamp (timestamp),
    CONSTRAINT fk_log FOREIGN KEY (log_id) REFERENCES logs(id) ON DELETE SET NULL
);

-- Create commands table for outgoing device warnings
CREATE TABLE IF NOT EXISTS commands (
    id VARCHAR(36) PRIMARY KEY,
    device_id VARCHAR(36) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending','sent','delivered') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_device_id (device_id),
    CONSTRAINT fk_command_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

-- Create users table (optional, for frontend auth)
CREATE TABLE IF NOT EXISTS users (
    id VARCHAR(36) PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255),
    display_name VARCHAR(255),
    is_admin BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email)
);

INSERT INTO users (id, email, password_hash, display_name, is_admin)
SELECT '00000000-0000-0000-0000-000000000001', 'admin@sentinel.local', '$2y$10$9dZREbwdSLQzTCQKNkI4UexG1d8tKRRWA8GlY7UjCtuwkpYVdYws2', 'Sentinel Admin', TRUE
WHERE NOT EXISTS (
    SELECT 1 FROM users WHERE email = 'admin@sentinel.local'
);

-- Create heartbeat logs for monitoring agent health
CREATE TABLE IF NOT EXISTS heartbeats (
    id VARCHAR(36) PRIMARY KEY,
    device_id VARCHAR(36) NOT NULL,
    agent_version VARCHAR(20),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status_info JSON,
    INDEX idx_device_id (device_id),
    INDEX idx_timestamp (timestamp),
    CONSTRAINT fk_device_heartbeat FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);
