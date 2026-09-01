<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['success' => false, 'message' => 'Not logged in']); exit; }
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) { echo json_encode(['success' => false, 'message' => 'Invalid token']); exit; }
$doc_type = sanitize($_POST['doc_type'] ?? '');
$doc_number = sanitize($_POST['doc_number'] ?? '');
if ($doc_type === '' || $doc_number === '') { echo json_encode(['success' => false, 'message' => 'All fields required']); exit; }
if (!isset($_FILES['doc_file']) || $_FILES['doc_file']['error'] !== UPLOAD_ERR_OK) { echo json_encode(['success' => false, 'message' => 'Document file required']); exit; }
echo json_encode(submitIDVerification(getCurrentUserId(), $doc_type, $doc_number, $_FILES['doc_file']));
?>
