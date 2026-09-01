<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    die('Access denied');
}

if (defined('IS_PRODUCTION') && IS_PRODUCTION) {
    http_response_code(403);
    die('Disabled in production');
}

function createUserIfNotExists($email, $password, $role, $full_name, $phone) {
    global $conn;

    $check = $conn->prepare('SELECT id FROM users WHERE email = ?');
    $check->execute([$email]);
    $existing = $check->fetch();
    if ($existing) {
        echo 'User ' . htmlspecialchars($email) . ' already exists<br>';
        return (int) $existing['id'];
    }

    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT INTO users (email, password, role, email_verified, created_at) VALUES (?, ?, ?, 1, NOW())");
    $stmt->execute([$email, $hashed, $role]);
    $user_id = (int) $conn->lastInsertId();

    $stmt = $conn->prepare("INSERT INTO profiles (user_id, full_name, phone, joined_date, trust_score, avatar) VALUES (?, ?, ?, CURDATE(), 70, 'default-avatar.png')");
    $stmt->execute([$user_id, $full_name, $phone]);

    echo 'Created user: ' . htmlspecialchars($email) . '<br>';
    return $user_id;
}

function createSampleRoom($landlord_id, $title, $description, $price, $location, $is_verified = true) {
    global $conn;

    $check = $conn->prepare('SELECT id FROM rooms WHERE landlord_id = ? AND title = ? LIMIT 1');
    $check->execute([(int) $landlord_id, $title]);
    $existing = $check->fetch();
    if ($existing) {
        echo 'Room already exists: ' . htmlspecialchars($title) . '<br>';
        return (int) $existing['id'];
    }

    $stmt = $conn->prepare("INSERT INTO rooms (landlord_id, title, description, price, location, status, is_verified, created_at) VALUES (?, ?, ?, ?, ?, 'active', ?, NOW())");
    $stmt->execute([(int) $landlord_id, $title, $description, $price, $location, $is_verified ? 1 : 0]);
    $room_id = (int) $conn->lastInsertId();

    echo 'Created room: ' . htmlspecialchars($title) . '<br>';
    return $room_id;
}

function createSampleReview($contract_id, $reviewer_id, $reviewee_id, $rating, $text) {
    $result = createReview([
        'contract_id' => (int) $contract_id,
        'reviewer_id' => (int) $reviewer_id,
        'reviewee_id' => (int) $reviewee_id,
        'rating_accuracy' => (int) $rating,
        'rating_communication' => (int) $rating,
        'rating_cleanliness' => (int) $rating,
        'rating_value' => (int) $rating,
        'review_text' => $text,
        'is_recommended' => true,
    ]);

    if ($result['success']) {
        echo 'Created review<br>';
    } else {
        echo 'Review skipped: ' . htmlspecialchars($result['message']) . '<br>';
    }
}

echo 'Starting data population...<br><br>';

echo 'Creating users...<br>';
$landlord1 = createUserIfNotExists('ramesh@example.com', 'password123', 'landlord', 'Ramesh Shrestha', '9812345678');
$landlord2 = createUserIfNotExists('sita@example.com', 'password123', 'landlord', 'Sita Sharma', '9823456789');
$landlord3 = createUserIfNotExists('hari@example.com', 'password123', 'landlord', 'Hari Gurung', '9834567890');
$tenant1 = createUserIfNotExists('binod@example.com', 'password123', 'tenant', 'Binod KC', '9845678901');
$tenant2 = createUserIfNotExists('sunita@example.com', 'password123', 'tenant', 'Sunita Rai', '9856789012');
$tenant3 = createUserIfNotExists('prakash@example.com', 'password123', 'tenant', 'Prakash Adhikari', '9867890123');

echo '<br>Creating rooms...<br>';
$room1 = createSampleRoom($landlord1, 'Modern 1BHK in Pulchowk', 'Spacious 1 bedroom apartment with attached bathroom, kitchen, and balcony. Located close to schools and hospitals.', 15000, 'Lalitpur', true);
$room2 = createSampleRoom($landlord1, 'Cozy Studio Near TU', 'Fully furnished studio room perfect for students. Walking distance to Tribhuvan University.', 8000, 'Kirtipur', true);
$room3 = createSampleRoom($landlord2, 'Family Apartment in Baneshwor', '2 bedroom apartment with hall, kitchen, and 2 bathrooms. Available for families.', 25000, 'Kathmandu', true);
$room4 = createSampleRoom($landlord2, 'Student PG Accommodation', 'Single rooms with shared kitchen and bathroom. Ideal for students.', 6000, 'Pulchowk', true);
$room5 = createSampleRoom($landlord3, 'Luxury 3BHK in Lakeside', 'Premium apartment with lake view, modern amenities, and parking.', 35000, 'Pokhara', true);
$room6 = createSampleRoom($landlord3, 'Budget Room Near Bus Park', 'Simple room with basic amenities, close to transportation.', 5000, 'Pokhara', false);

echo '<br>Creating contracts and reviews...<br>';
$contracts = [
    [
        'room_id' => $room1,
        'tenant_id' => $tenant1,
        'landlord_id' => $landlord1,
        'monthly_rent' => 15000,
        'advance_amount' => 15000,
        'deposit_amount' => 30000,
        'contract_start_date' => date('Y-m-d', strtotime('-3 months')),
        'contract_end_date' => date('Y-m-d', strtotime('+9 months')),
        'review_text' => 'Great landlord! Very responsive and helpful. The room was exactly as described. Would definitely recommend.',
        'rating' => 5,
    ],
    [
        'room_id' => $room3,
        'tenant_id' => $tenant2,
        'landlord_id' => $landlord2,
        'monthly_rent' => 25000,
        'advance_amount' => 25000,
        'deposit_amount' => 50000,
        'contract_start_date' => date('Y-m-d', strtotime('-2 months')),
        'contract_end_date' => date('Y-m-d', strtotime('+10 months')),
        'review_text' => 'Good experience overall. The apartment is spacious and well-maintained. Communication could be slightly better.',
        'rating' => 4,
    ],
    [
        'room_id' => $room5,
        'tenant_id' => $tenant3,
        'landlord_id' => $landlord3,
        'monthly_rent' => 35000,
        'advance_amount' => 35000,
        'deposit_amount' => 70000,
        'contract_start_date' => date('Y-m-d', strtotime('-1 month')),
        'contract_end_date' => date('Y-m-d', strtotime('+11 months')),
        'review_text' => 'Absolutely love the place! The lake view is amazing and the landlord is very cooperative. Best decision ever.',
        'rating' => 5,
    ],
];

foreach ($contracts as $contract_data) {
    $result = createContract($contract_data);
    if (!$result['success']) {
        echo 'Contract skipped: ' . htmlspecialchars($result['message']) . '<br>';
        continue;
    }

    signContract((int) $result['contract_id'], (int) $contract_data['tenant_id'], 'Signed by tenant');
    signContract((int) $result['contract_id'], (int) $contract_data['landlord_id'], 'Signed by landlord');
    createSampleReview((int) $result['contract_id'], (int) $contract_data['tenant_id'], (int) $contract_data['landlord_id'], (int) $contract_data['rating'], $contract_data['review_text']);
}

echo '<br>Sample data population complete!<br>';
echo 'You can now log in with:<br>';
echo '- Landlord: ramesh@example.com / password123<br>';
echo '- Tenant: binod@example.com / password123<br>';
echo '- Admin: admin@gharbeti.com / Admin@123<br>';
?>
