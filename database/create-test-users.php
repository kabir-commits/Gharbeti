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

$test_users = [
    ['email' => 'tenant@test.com', 'password' => 'tenant123', 'role' => 'tenant', 'full_name' => 'Test Tenant', 'phone' => '9812345678'],
    ['email' => 'landlord@test.com', 'password' => 'landlord123', 'role' => 'landlord', 'full_name' => 'Test Landlord', 'phone' => '9823456789']
];

foreach ($test_users as $user) {
    $check = $conn->prepare('SELECT id FROM users WHERE email = ?');
    $check->execute([$user['email']]);
    if (!$check->fetch()) {
        $hashed = password_hash($user['password'], PASSWORD_BCRYPT);
        $stmt = $conn->prepare('INSERT INTO users (email, password, role, email_verified, created_at) VALUES (?, ?, ?, 1, NOW())');
        $stmt->execute([$user['email'], $hashed, $user['role']]);
        $user_id = $conn->lastInsertId();
        $stmt = $conn->prepare('INSERT INTO profiles (user_id, full_name, phone, joined_date, trust_score, avatar) VALUES (?, ?, ?, CURDATE(), 70, ?)');
        $stmt->execute([$user_id, $user['full_name'], $user['phone'], 'default-avatar.svg']);
        echo 'Created test user: ' . $user['email'] . '<br>';
    } else {
        echo 'User already exists: ' . $user['email'] . '<br>';
    }
}

echo "<br>Test users created successfully!";
echo "<br><br><a href='../auth/login.php'>Go to Login</a>";
?>
