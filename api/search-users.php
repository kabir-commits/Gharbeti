<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$query = $_GET['q'] ?? '';
$role = $_GET['role'] ?? '';

echo json_encode([
    'success' => true,
    'users' => searchUsers($query, $role),
]);
?>
