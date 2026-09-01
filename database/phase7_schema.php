<?php
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$sql_statements = [
    "CREATE TABLE IF NOT EXISTS admin_actions_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        action_type VARCHAR(50) NOT NULL,
        target_type VARCHAR(50) NOT NULL,
        target_id INT NULL,
        description TEXT NULL,
        ip_address VARCHAR(45) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_admin (admin_id),
        INDEX idx_target (target_type, target_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    "CREATE TABLE IF NOT EXISTS site_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT NULL,
        setting_type ENUM('text', 'number', 'boolean', 'json', 'image') DEFAULT 'text',
        description TEXT NULL,
        updated_by INT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_key (setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    "CREATE TABLE IF NOT EXISTS analytics_visits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        visitor_id VARCHAR(100) NULL,
        user_id INT NULL,
        page_url VARCHAR(255) NOT NULL,
        referrer_url VARCHAR(255) NULL,
        ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL,
        device_type ENUM('desktop', 'tablet', 'mobile', 'unknown') DEFAULT 'unknown',
        browser VARCHAR(50) NULL,
        os VARCHAR(50) NULL,
        country VARCHAR(100) NULL,
        city VARCHAR(100) NULL,
        session_duration INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_visitor (visitor_id),
        INDEX idx_page (page_url),
        INDEX idx_date (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    "CREATE TABLE IF NOT EXISTS analytics_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        event_type VARCHAR(50) NOT NULL,
        event_category VARCHAR(50) NULL,
        event_label VARCHAR(255) NULL,
        event_value INT NULL,
        page_url VARCHAR(255) NULL,
        ip_address VARCHAR(45) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_user (user_id),
        INDEX idx_event (event_type, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    "CREATE TABLE IF NOT EXISTS moderation_queue (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_type ENUM('room', 'review', 'user', 'verification') NOT NULL,
        item_id INT NOT NULL,
        action_required ENUM('approve', 'reject', 'review') NOT NULL,
        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
        status ENUM('pending', 'approved', 'rejected', 'skipped') DEFAULT 'pending',
        reported_count INT DEFAULT 0,
        report_reasons TEXT NULL,
        admin_notes TEXT NULL,
        assigned_to INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        completed_at DATETIME NULL,
        completed_by INT NULL,
        FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_status (status),
        INDEX idx_priority (priority),
        INDEX idx_item (item_type, item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    "CREATE TABLE IF NOT EXISTS system_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        log_level ENUM('info', 'warning', 'error', 'critical') DEFAULT 'info',
        log_type VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        details JSON NULL,
        ip_address VARCHAR(45) NULL,
        user_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_level (log_level),
        INDEX idx_type (log_type),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    "CREATE TABLE IF NOT EXISTS backups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        backup_name VARCHAR(255) NOT NULL,
        backup_type ENUM('full', 'database', 'files') NOT NULL,
        file_size BIGINT NULL,
        file_path VARCHAR(255) NULL,
        status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    "CREATE TABLE IF NOT EXISTS email_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        recipient_email VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        body TEXT NULL,
        status ENUM('sent', 'failed', 'queued') DEFAULT 'queued',
        error_message TEXT NULL,
        sent_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_recipient (recipient_email),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    "CREATE TABLE IF NOT EXISTS failed_jobs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_type VARCHAR(100) NOT NULL,
        payload JSON NULL,
        error_message TEXT NULL,
        failed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        retry_count INT DEFAULT 0,
        INDEX idx_type (job_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
];

foreach ($sql_statements as $sql) {
    try {
        $conn->exec($sql);
        echo 'Table created successfully<br>';
    } catch (PDOException $e) {
        echo 'Error: ' . htmlspecialchars($e->getMessage()) . '<br>';
    }
}

$settings = [
    ['site_name', 'Gharbeti', 'text', 'Site name displayed throughout the platform'],
    ['site_description', "Nepal''s Trust-First Room Discovery Platform", 'text', 'Site description for SEO'],
    ['contact_email', 'admin@gharbeti.com', 'text', 'Primary contact email'],
    ['support_email', 'support@gharbeti.com', 'text', 'Support email address'],
    ['contact_phone', '+977-1-4234567', 'text', 'Contact phone number'],
    ['address', 'Putalisadak, Kathmandu, Nepal', 'text', 'Office address'],
    ['enable_registration', '1', 'boolean', 'Allow new user registrations'],
    ['require_email_verification', '1', 'boolean', 'Require email verification for new accounts'],
    ['default_user_role', 'tenant', 'text', 'Default role for new users'],
    ['max_room_images', '10', 'number', 'Maximum number of images per room listing'],
    ['max_file_size', '10', 'number', 'Maximum file size in MB for uploads'],
    ['listing_approval', 'manual', 'text', 'Room listing approval method: auto, manual'],
    ['maintenance_mode', '0', 'boolean', 'Put site in maintenance mode'],
    ['maintenance_message', 'Site under maintenance. Please check back later.', 'text', 'Message to show in maintenance mode'],
    ['enable_analytics', '1', 'boolean', 'Enable analytics tracking'],
    ['from_email', 'noreply@gharbeti.com', 'text', 'From email address for system emails'],
    ['from_name', 'Gharbeti', 'text', 'From name for system emails'],
    ['currency_symbol', '??', 'text', 'Currency symbol'],
    ['currency_code', 'NPR', 'text', 'Currency code'],
    ['items_per_page', '12', 'number', 'Number of items per page in listings'],
    ['admin_items_per_page', '20', 'number', 'Number of items per page in admin panel'],
    ['footer_text', '© 2024 Gharbeti. All rights reserved.', 'text', 'Footer copyright text'],
    ['meta_author', 'Gharbeti Team', 'text', 'Meta author tag'],
    ['meta_robots', 'index, follow', 'text', 'Meta robots tag']
];

$insert_setting = $conn->prepare('INSERT IGNORE INTO site_settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, ?, ?)');
foreach ($settings as $setting) {
    try {
        $insert_setting->execute($setting);
        echo 'Added setting: ' . htmlspecialchars($setting[0]) . '<br>';
    } catch (PDOException $e) {
    }
}

echo "<br>Phase 7 database updates complete! <a href='../setup.php'>Back to Setup</a>";
?>
