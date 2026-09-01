<?php
require_once __DIR__ . '/../config/database.php';
$database = new Database();
$conn = $database->getConnection();
$sql_statements = [
    "CREATE TABLE IF NOT EXISTS rooms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        landlord_id INT NOT NULL,
        title VARCHAR(200) NOT NULL,
        description TEXT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        location VARCHAR(255) NOT NULL,
        address TEXT,
        latitude DECIMAL(10,8),
        longitude DECIMAL(11,8),
        floor_area INT COMMENT 'in sq ft',
        bedroom_count INT DEFAULT 1,
        bathroom_count INT DEFAULT 1,
        kitchen_type ENUM('private', 'shared', 'none') DEFAULT 'private',
        floor_number INT,
        total_floors INT,
        available_from DATE,
        minimum_stay INT COMMENT 'in months',
        deposit_months INT DEFAULT 1,
        utilities_included BOOLEAN DEFAULT FALSE,
        electricity_charge VARCHAR(50),
        water_charge VARCHAR(50),
        internet_charge VARCHAR(50),
        is_verified BOOLEAN DEFAULT FALSE,
        is_featured BOOLEAN DEFAULT FALSE,
        status ENUM('active', 'inactive', 'booked', 'pending', 'rejected') DEFAULT 'pending',
        view_count INT DEFAULT 0,
        contact_count INT DEFAULT 0,
        favorite_count INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (landlord_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_location (location),
        INDEX idx_price (price),
        INDEX idx_status (status),
        INDEX idx_landlord (landlord_id),
        INDEX idx_verified (is_verified),
        FULLTEXT INDEX idx_search (title, description, location)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    "CREATE TABLE IF NOT EXISTS room_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id INT NOT NULL,
        image_url VARCHAR(255) NOT NULL,
        is_primary BOOLEAN DEFAULT FALSE,
        sort_order INT DEFAULT 0,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
        INDEX idx_room (room_id),
        INDEX idx_primary (is_primary)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    "CREATE TABLE IF NOT EXISTS amenities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        icon VARCHAR(50),
        category VARCHAR(50) DEFAULT 'basic',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    "CREATE TABLE IF NOT EXISTS room_amenities (
        room_id INT NOT NULL,
        amenity_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (room_id, amenity_id),
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
        FOREIGN KEY (amenity_id) REFERENCES amenities(id) ON DELETE CASCADE,
        INDEX idx_amenity (amenity_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    "CREATE TABLE IF NOT EXISTS favorites (
        user_id INT NOT NULL,
        room_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, room_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
        INDEX idx_user (user_id),
        INDEX idx_room (room_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    "CREATE TABLE IF NOT EXISTS room_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id INT NOT NULL,
        viewer_id INT,
        ip_address VARCHAR(45),
        viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
        FOREIGN KEY (viewer_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_room (room_id),
        INDEX idx_date (viewed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
];
foreach ($sql_statements as $sql) {
    try { $conn->exec($sql); echo 'Table created successfully<br>'; }
    catch (PDOException $e) { echo 'Error: ' . $e->getMessage() . '<br>'; }
}
$amenities_data = [
    ['WiFi', 'wifi', 'basic'], ['Water Supply', 'faucet', 'basic'], ['Electricity Backup', 'bolt', 'basic'], ['Parking', 'parking', 'basic'], ['Kitchen', 'kitchen-set', 'basic'],
    ['Furnished', 'couch', 'furniture'], ['Semi-Furnished', 'chair', 'furniture'], ['AC', 'wind', 'cooling'], ['Ceiling Fan', 'fan', 'cooling'], ['Heater', 'temperature-high', 'heating'],
    ['Geyser', 'water', 'bathroom'], ['Attached Bathroom', 'toilet', 'bathroom'], ['Common Bathroom', 'toilet', 'bathroom'], ['Balcony', 'sun', 'outdoor'], ['Garden', 'seedling', 'outdoor'],
    ['Terrace Access', 'building', 'outdoor'], ['Security Guard', 'shield', 'security'], ['CCTV', 'video', 'security'], ['Intercom', 'phone', 'security'], ['Lift', 'elevator', 'building'],
    ['Generator', 'bolt', 'utility'], ['Solar Power', 'solar-panel', 'utility'], ['Water Tank', 'water', 'utility'], ['Gas Pipeline', 'fire', 'utility'], ['Garbage Disposal', 'trash', 'maintenance'],
    ['Housekeeping', 'broom', 'maintenance'], ['Laundry', 'soap', 'service'], ['Pet Friendly', 'paw', 'policy'], ['Couple Friendly', 'heart', 'policy'], ['Bachelor Friendly', 'user', 'policy'],
    ['Non-Veg Allowed', 'drumstick', 'policy'], ['Visitors Allowed', 'users', 'policy']
];
$insert_amenity = $conn->prepare('INSERT IGNORE INTO amenities (name, icon, category) VALUES (?, ?, ?)');
foreach ($amenities_data as $amenity) {
    try { $insert_amenity->execute($amenity); echo 'Added amenity: ' . $amenity[0] . '<br>'; } catch (PDOException $e) {}
}
echo "<br>Phase 4 database updates complete! <a href='../setup.php'>Back to Setup</a>";
?>
