<?php
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

function columnExistsInTable(PDO $conn, $table, $column) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

$sql_statements = [
    "CREATE TABLE IF NOT EXISTS conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id INT NOT NULL,
        tenant_id INT NOT NULL,
        landlord_id INT NOT NULL,
        status ENUM('pending', 'accepted', 'declined', 'blocked') DEFAULT 'pending',
        last_message TEXT,
        last_message_time DATETIME,
        last_message_sender_id INT NULL,
        tenant_unread_count INT DEFAULT 0,
        landlord_unread_count INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
        FOREIGN KEY (tenant_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (landlord_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (last_message_sender_id) REFERENCES users(id) ON DELETE SET NULL,
        UNIQUE KEY unique_conversation (room_id, tenant_id, landlord_id),
        INDEX idx_tenant (tenant_id, status),
        INDEX idx_landlord (landlord_id, status),
        INDEX idx_updated (updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    "CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        read_at DATETIME NULL,
        is_delivered BOOLEAN DEFAULT FALSE,
        delivered_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_conversation (conversation_id, created_at),
        INDEX idx_sender (sender_id),
        INDEX idx_receiver (receiver_id, is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type ENUM('message', 'contact_request', 'contract', 'review', 'verification', 'system') NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        link VARCHAR(255) NULL,
        is_read BOOLEAN DEFAULT FALSE,
        read_at DATETIME NULL,
        data JSON NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user (user_id, is_read),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    "CREATE TABLE IF NOT EXISTS contact_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id INT NOT NULL,
        tenant_id INT NOT NULL,
        landlord_id INT NOT NULL,
        message TEXT NULL,
        status ENUM('pending', 'accepted', 'declined') DEFAULT 'pending',
        viewed_by_landlord BOOLEAN DEFAULT FALSE,
        viewed_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
        FOREIGN KEY (tenant_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (landlord_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_request (room_id, tenant_id),
        INDEX idx_landlord (landlord_id, status),
        INDEX idx_tenant (tenant_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    "CREATE TABLE IF NOT EXISTS typing_status (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        user_id INT NOT NULL,
        is_typing BOOLEAN DEFAULT FALSE,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_conversation_user (conversation_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
];

foreach ($sql_statements as $sql) {
    try {
        $conn->exec($sql);
        echo 'Table created successfully<br>';
    } catch (PDOException $e) {
        echo 'Error: ' . $e->getMessage() . '<br>';
    }
}

$profile_columns = [
    'response_rate' => "ALTER TABLE profiles ADD COLUMN response_rate DECIMAL(5,2) DEFAULT 0 AFTER trust_score",
    'response_time' => "ALTER TABLE profiles ADD COLUMN response_time INT DEFAULT 0 AFTER response_rate",
    'total_responses' => "ALTER TABLE profiles ADD COLUMN total_responses INT DEFAULT 0 AFTER response_time",
    'avg_response_time' => "ALTER TABLE profiles ADD COLUMN avg_response_time INT DEFAULT 0 AFTER total_responses",
];

foreach ($profile_columns as $column => $sql) {
    try {
        if (!columnExistsInTable($conn, 'profiles', $column)) {
            $conn->exec($sql);
        }
    } catch (PDOException $e) {
        echo 'Error updating profiles: ' . $e->getMessage() . '<br>';
    }
}

echo 'Profile columns updated<br>';
echo "<br>Phase 5 database updates complete! <a href='../setup.php'>Back to Setup</a>";
?>
