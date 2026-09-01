<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['success' => false, 'message' => 'Not logged in']); exit; }
$data = json_decode(file_get_contents('php://input'), true) ?: [];
if (!isset($data['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $data['csrf_token'])) { echo json_encode(['success' => false, 'message' => 'Invalid token']); exit; }
$phone = sanitize($data['phone'] ?? '');
if ($phone === '') { echo json_encode(['success' => false, 'message' => 'Phone number required']); exit; }
echo json_encode(sendPhoneVerification(getCurrentUserId(), $phone));
?>
