<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = getCurrentUserId();
$conversation_id = isset($_GET['conversation_id']) ? (int) $_GET['conversation_id'] : 0;
$last_id = isset($_GET['last_id']) ? (int) $_GET['last_id'] : 0;
$wait_for_updates = isset($_GET['wait']) && $_GET['wait'] === '1';
$timeout_seconds = isset($_GET['timeout']) ? max(1, min(15, (int) $_GET['timeout'])) : 12;
$previous_status_hash = (string) ($_GET['status_hash'] ?? '');
$previous_typing_hash = (string) ($_GET['typing_hash'] ?? '');

if (!$conversation_id) {
    echo json_encode(['success' => false, 'message' => 'Conversation ID required']);
    exit;
}

$stmt = $conn->prepare('SELECT * FROM conversations WHERE id = ? AND (tenant_id = ? OR landlord_id = ?)');
$stmt->execute([$conversation_id, $user_id, $user_id]);
$conversation = $stmt->fetch();
if (!$conversation) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$should_mark_read = isset($_GET['mark_read']) && $_GET['mark_read'] === '1';
$started_at = microtime(true);
$messages = [];
$status_updates = [];
$typing = [];
$status_hash = '';
$typing_hash = '';

while (true) {
    $stmt = $conn->prepare('SELECT m.*, u.email, p.full_name, p.avatar FROM messages m JOIN users u ON m.sender_id = u.id LEFT JOIN profiles p ON u.id = p.user_id WHERE m.conversation_id = ? AND m.id > ? ORDER BY m.created_at ASC, m.id ASC');
    $stmt->execute([$conversation_id, $last_id]);
    $messages = $stmt->fetchAll();

    $markDelivered = $conn->prepare('UPDATE messages SET is_delivered = 1, delivered_at = NOW() WHERE conversation_id = ? AND receiver_id = ? AND is_delivered = 0');
    $markDelivered->execute([$conversation_id, $user_id]);

    if ($should_mark_read) {
        $markRead = $conn->prepare('UPDATE messages SET is_read = 1, read_at = NOW() WHERE conversation_id = ? AND receiver_id = ? AND is_read = 0');
        $markRead->execute([$conversation_id, $user_id]);

        if ((int) $user_id === (int) $conversation['tenant_id']) {
            $reset = $conn->prepare('UPDATE conversations SET tenant_unread_count = 0 WHERE id = ?');
        } else {
            $reset = $conn->prepare('UPDATE conversations SET landlord_unread_count = 0 WHERE id = ?');
        }
        $reset->execute([$conversation_id]);
    }

    $status_stmt = $conn->prepare('SELECT id, is_delivered, is_read FROM messages WHERE conversation_id = ? AND sender_id = ? ORDER BY id ASC');
    $status_stmt->execute([$conversation_id, $user_id]);
    $status_updates = $status_stmt->fetchAll();
    $typing = getTypingStatus($conversation_id, $user_id);

    $status_hash = md5(json_encode($status_updates));
    $typing_hash = md5(json_encode($typing));

    $has_updates = !empty($messages)
        || $previous_status_hash === ''
        || $previous_typing_hash === ''
        || $status_hash !== $previous_status_hash
        || $typing_hash !== $previous_typing_hash;

    if (!$wait_for_updates || $has_updates || (microtime(true) - $started_at) >= $timeout_seconds) {
        break;
    }

    usleep(250000);
}

echo json_encode([
    'success' => true,
    'messages' => $messages,
    'typing' => $typing,
    'status_updates' => $status_updates,
    'status_hash' => $status_hash,
    'typing_hash' => $typing_hash,
]);
?>
