<?php
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$sql_statements = [
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('tenant', 'landlord', 'admin') DEFAULT 'tenant',
        is_active BOOLEAN DEFAULT TRUE,
        email_verified BOOLEAN DEFAULT FALSE,
        email_verification_token VARCHAR(100),
        email_verification_expires DATETIME,
        reset_token VARCHAR(100),
        reset_expires DATETIME,
        last_login DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_email (email),
        INDEX idx_token (email_verification_token),
        INDEX idx_reset (reset_token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    "CREATE TABLE IF NOT EXISTS profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNIQUE NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        phone VARCHAR(20),
        phone_verified BOOLEAN DEFAULT FALSE,
        address TEXT,
        bio TEXT,
        avatar VARCHAR(255) DEFAULT 'default-avatar.svg',
        id_document VARCHAR(255),
        id_verified BOOLEAN DEFAULT FALSE,
        trust_score INT DEFAULT 30,
        response_rate DECIMAL(5,2) DEFAULT 0,
        response_time INT DEFAULT 0,
        profile_views INT DEFAULT 0,
        joined_date DATE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    "CREATE TABLE IF NOT EXISTS user_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        session_token VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_token (session_token),
        INDEX idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    "CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        action VARCHAR(100) NOT NULL,
        details TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_user_action (user_id, action),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
];

foreach ($sql_statements as $sql) {
    try {
        $conn->exec($sql);
        echo 'Table created successfully<br>';
    } catch (PDOException $e) {
        echo 'Error creating table: ' . $e->getMessage() . '<br>';
    }
}

$check_admin = $conn->prepare('SELECT id FROM users WHERE email = ?');
$check_admin->execute(['admin@gharbeti.com']);
if (!$check_admin->fetch()) {
    $password = password_hash('Admin@123', PASSWORD_BCRYPT);
    $token = bin2hex(random_bytes(32));
    $create_admin = $conn->prepare("INSERT INTO users (email, password, role, email_verified, email_verification_token, created_at) VALUES (?, ?, 'admin', TRUE, ?, NOW())");
    $create_admin->execute(['admin@gharbeti.com', $password, $token]);
    $admin_id = $conn->lastInsertId();
    $create_profile = $conn->prepare("INSERT INTO profiles (user_id, full_name, phone, joined_date, trust_score, avatar) VALUES (?, 'Admin User', '9800000000', CURDATE(), 100, 'default-avatar.svg')");
    $create_profile->execute([$admin_id]);
    echo 'Admin user created successfully<br>';
}

echo "<br>Database setup complete! <a href='../index.php'>Go to Homepage</a>";
?>
