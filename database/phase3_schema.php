<?php
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$alter_profiles = [
    "ALTER TABLE profiles ADD COLUMN IF NOT EXISTS date_of_birth DATE NULL AFTER phone",
    "ALTER TABLE profiles ADD COLUMN IF NOT EXISTS gender ENUM('male', 'female', 'other') NULL AFTER date_of_birth",
    "ALTER TABLE profiles ADD COLUMN IF NOT EXISTS occupation VARCHAR(100) NULL AFTER gender",
    "ALTER TABLE profiles ADD COLUMN IF NOT EXISTS company VARCHAR(100) NULL AFTER occupation",
    "ALTER TABLE profiles ADD COLUMN IF NOT EXISTS education VARCHAR(100) NULL AFTER company",
    "ALTER TABLE profiles ADD COLUMN IF NOT EXISTS languages TEXT NULL AFTER education",
    "ALTER TABLE profiles ADD COLUMN IF NOT EXISTS emergency_contact_name VARCHAR(100) NULL AFTER languages",
    "ALTER TABLE profiles ADD COLUMN IF NOT EXISTS emergency_contact_phone VARCHAR(20) NULL AFTER emergency_contact_name",
    "ALTER TABLE profiles ADD COLUMN IF NOT EXISTS facebook_url VARCHAR(255) NULL AFTER emergency_contact_phone",
    "ALTER TABLE profiles ADD COLUMN IF NOT EXISTS twitter_url VARCHAR(255) NULL AFTER facebook_url",
    "ALTER TABLE profiles ADD COLUMN IF NOT EXISTS linkedin_url VARCHAR(255) NULL AFTER twitter_url",
    "ALTER TABLE profiles ADD COLUMN IF NOT EXISTS instagram_url VARCHAR(255) NULL AFTER linkedin_url",
    "ALTER TABLE profiles ADD COLUMN IF NOT EXISTS last_active DATETIME NULL AFTER trust_score",
    "ALTER TABLE profiles ADD COLUMN IF NOT EXISTS is_online BOOLEAN DEFAULT FALSE AFTER last_active"
];

foreach ($alter_profiles as $sql) {
    try {
        $conn->exec($sql);
        echo "Profile table updated successfully<br>";
    } catch (PDOException $e) {
        echo 'Error: ' . $e->getMessage() . '<br>';
    }
}

$tables = [
    "CREATE TABLE IF NOT EXISTS verification_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        document_type ENUM('citizenship', 'passport', 'license', 'voter_id', 'property_document') NOT NULL,
        document_number VARCHAR(50),
        document_file VARCHAR(255) NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        admin_notes TEXT,
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        reviewed_at DATETIME,
        reviewed_by INT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_status (user_id, status),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    "CREATE TABLE IF NOT EXISTS phone_verification (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        phone VARCHAR(20) NOT NULL,
        code VARCHAR(6) NOT NULL,
        attempts INT DEFAULT 0,
        expires_at DATETIME NOT NULL,
        verified_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_code (user_id, code),
        INDEX idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    "CREATE TABLE IF NOT EXISTS trust_score_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        old_score INT NOT NULL,
        new_score INT NOT NULL,
        reason VARCHAR(255) NOT NULL,
        changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        changed_by INT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    "CREATE TABLE IF NOT EXISTS user_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        viewer_id INT NULL,
        viewed_user_id INT NOT NULL,
        viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        ip_address VARCHAR(45),
        FOREIGN KEY (viewer_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (viewed_user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_viewed (viewed_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
];

foreach ($tables as $sql) {
    try {
        $conn->exec($sql);
        echo "Table created successfully<br>";
    } catch (PDOException $e) {
        echo 'Error: ' . $e->getMessage() . '<br>';
    }
}

echo "<br>Phase 3 database updates complete! <a href='../setup.php'>Back to Setup</a>";
?>
