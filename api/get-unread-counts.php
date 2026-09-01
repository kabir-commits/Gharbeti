<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = getCurrentUserId();
$unread_messages = getUnreadMessageCount($user_id);
$unread_notifications = getUnreadNotificationCount($user_id);

$_SESSION['unread_messages'] = $unread_messages;
$_SESSION['unread_notifications'] = $unread_notifications;

echo json_encode([
    'success' => true,
    'unread_messages' => $unread_messages,
    'unread_notifications' => $unread_notifications,
    'total' => $unread_messages + $unread_notifications,
]);
?>
