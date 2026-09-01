<?php
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

function phase6ColumnExists(PDO $conn, $table, $column) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

$sql_statements = [
    "CREATE TABLE IF NOT EXISTS contracts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id INT NOT NULL,
        tenant_id INT NOT NULL,
        landlord_id INT NOT NULL,
        conversation_id INT NULL,
        contract_number VARCHAR(50) UNIQUE NOT NULL,
        monthly_rent DECIMAL(10,2) NOT NULL,
        advance_amount DECIMAL(10,2) NOT NULL,
        deposit_amount DECIMAL(10,2) NOT NULL,
        contract_start_date DATE NOT NULL,
        contract_end_date DATE NULL,
        is_indefinite BOOLEAN DEFAULT FALSE,
        notice_period INT DEFAULT 30,
        payment_day INT DEFAULT 1,
        utilities_included BOOLEAN DEFAULT FALSE,
        electricity_charge TEXT NULL,
        water_charge TEXT NULL,
        internet_charge TEXT NULL,
        maintenance_charge TEXT NULL,
        parking_charge TEXT NULL,
        guest_policy TEXT NULL,
        pet_policy TEXT NULL,
        smoking_policy TEXT NULL,
        noise_policy TEXT NULL,
        additional_rules TEXT NULL,
        tenant_signature TEXT NULL,
        landlord_signature TEXT NULL,
        tenant_signed_at DATETIME NULL,
        landlord_signed_at DATETIME NULL,
        tenant_ip VARCHAR(45) NULL,
        landlord_ip VARCHAR(45) NULL,
        tenant_agreed BOOLEAN DEFAULT FALSE,
        landlord_agreed BOOLEAN DEFAULT FALSE,
        status ENUM('draft', 'pending_tenant', 'pending_landlord', 'active', 'expired', 'terminated') DEFAULT 'draft',
        contract_pdf VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        signed_at DATETIME NULL,
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
        FOREIGN KEY (tenant_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (landlord_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE SET NULL,
        INDEX idx_room (room_id),
        INDEX idx_tenant (tenant_id),
        INDEX idx_landlord (landlord_id),
        INDEX idx_status (status),
        INDEX idx_contract_number (contract_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    "CREATE TABLE IF NOT EXISTS contract_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        landlord_id INT NOT NULL,
        template_name VARCHAR(100) NOT NULL,
        is_default BOOLEAN DEFAULT FALSE,
        terms TEXT NULL,
        utilities_clause TEXT NULL,
        maintenance_clause TEXT NULL,
        termination_clause TEXT NULL,
        additional_clauses TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (landlord_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_landlord (landlord_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    "CREATE TABLE IF NOT EXISTS contract_negotiations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        contract_id INT NOT NULL,
        user_id INT NOT NULL,
        field_name VARCHAR(50) NOT NULL,
        old_value TEXT NULL,
        new_value TEXT NULL,
        message TEXT NULL,
        status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_contract (contract_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    "CREATE TABLE IF NOT EXISTS reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        contract_id INT UNIQUE NOT NULL,
        reviewer_id INT NOT NULL,
        reviewee_id INT NOT NULL,
        rating_accuracy INT NOT NULL,
        rating_communication INT NOT NULL,
        rating_cleanliness INT NOT NULL,
        rating_value INT NOT NULL,
        rating_overall DECIMAL(3,2) NOT NULL DEFAULT 0,
        review_text TEXT NOT NULL,
        landlord_response TEXT NULL,
        is_anonymous BOOLEAN DEFAULT FALSE,
        is_recommended BOOLEAN DEFAULT TRUE,
        is_verified BOOLEAN DEFAULT FALSE,
        is_helpful_count INT DEFAULT 0,
        reported_count INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        responded_at DATETIME NULL,
        FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
        FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (reviewee_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_reviewer (reviewer_id),
        INDEX idx_reviewee (reviewee_id),
        INDEX idx_contract (contract_id),
        INDEX idx_rating (rating_overall)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    "CREATE TABLE IF NOT EXISTS review_helpful (
        id INT AUTO_INCREMENT PRIMARY KEY,
        review_id INT NOT NULL,
        user_id INT NOT NULL,
        is_helpful BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_vote (review_id, user_id),
        INDEX idx_review (review_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    "CREATE TABLE IF NOT EXISTS review_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        review_id INT NOT NULL,
        reporter_id INT NOT NULL,
        reason VARCHAR(100) NOT NULL,
        description TEXT NULL,
        status ENUM('pending', 'reviewed', 'dismissed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        resolved_at DATETIME NULL,
        resolved_by INT NULL,
        FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE,
        FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_review (review_id),
        INDEX idx_status (status)
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
    'total_contracts' => "ALTER TABLE profiles ADD COLUMN total_contracts INT DEFAULT 0 AFTER avg_response_time",
    'completed_contracts' => "ALTER TABLE profiles ADD COLUMN completed_contracts INT DEFAULT 0 AFTER total_contracts",
    'cancelled_contracts' => "ALTER TABLE profiles ADD COLUMN cancelled_contracts INT DEFAULT 0 AFTER completed_contracts",
    'avg_rating' => "ALTER TABLE profiles ADD COLUMN avg_rating DECIMAL(3,2) DEFAULT 0 AFTER cancelled_contracts",
    'total_reviews' => "ALTER TABLE profiles ADD COLUMN total_reviews INT DEFAULT 0 AFTER avg_rating",
];
foreach ($profile_columns as $column => $sql) {
    try {
        if (!phase6ColumnExists($conn, 'profiles', $column)) {
            $conn->exec($sql);
        }
    } catch (PDOException $e) {
        echo 'Error updating profiles: ' . $e->getMessage() . '<br>';
    }
}
echo 'Profile columns updated<br>';

try {
    $owner = $conn->query("SELECT id FROM users WHERE role IN ('landlord', 'admin') ORDER BY FIELD(role, 'landlord', 'admin'), id ASC LIMIT 1")->fetch();
    if ($owner) {
        $check = $conn->prepare("SELECT id FROM contract_templates WHERE landlord_id = ? AND template_name = ? LIMIT 1");
        $check->execute([(int) $owner['id'], 'Standard Rental Agreement']);
        if (!$check->fetch()) {
            $insert = $conn->prepare("INSERT INTO contract_templates (landlord_id, template_name, is_default, terms, utilities_clause, maintenance_clause, termination_clause)
                VALUES (?, 'Standard Rental Agreement', 1, ?, ?, ?, ?)");
            $insert->execute([
                (int) $owner['id'],
                'This Rental Agreement is made between the Landlord and the Tenant for the property listed in the contract. Both parties agree to the terms stated below.',
                'Utilities such as electricity, water, and internet shall be paid by the Tenant as per actual consumption unless stated otherwise.',
                'The Landlord is responsible for major structural repairs. The Tenant is responsible for routine care and any damage caused during the tenancy.',
                'Either party may terminate this agreement by providing the agreed notice period in writing.',
            ]);
            echo 'Default contract template inserted<br>';
        } else {
            echo 'Default contract template already exists<br>';
        }
    }
} catch (PDOException $e) {
    echo 'Template insert error: ' . $e->getMessage() . '<br>';
}

echo "<br>Phase 6 database updates complete! <a href='../setup.php'>Back to Setup</a>";
?>
