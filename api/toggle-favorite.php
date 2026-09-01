<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['success'=>false,'message'=>'Not logged in']); exit; }
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $data['csrf_token'])) { echo json_encode(['success'=>false,'message'=>'Invalid token']); exit; }
$room_id = isset($data['room_id']) ? (int) $data['room_id'] : 0;
if (!$room_id) { echo json_encode(['success'=>false,'message'=>'Room ID required']); exit; }
echo json_encode(toggleFavorite(getCurrentUserId(), $room_id));
?>
