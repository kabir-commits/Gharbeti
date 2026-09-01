<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['success' => false, 'message' => 'Not logged in']); exit; }
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) { echo json_encode(['success' => false, 'message' => 'Invalid token']); exit; }
if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) { echo json_encode(['success' => false, 'message' => 'No file uploaded']); exit; }
echo json_encode(uploadAvatar(getCurrentUserId(), $_FILES['avatar']));
?>
