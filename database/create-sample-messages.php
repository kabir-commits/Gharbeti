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

$stmt = $conn->query("SELECT id FROM users WHERE role = 'tenant' ORDER BY id ASC LIMIT 1");
$tenant = $stmt->fetch();

$stmt = $conn->query("SELECT id FROM users WHERE role = 'landlord' ORDER BY id ASC LIMIT 1");
$landlord = $stmt->fetch();

$room = null;
if ($landlord) {
    $stmt = $conn->query('SELECT id FROM rooms WHERE landlord_id = ' . (int) $landlord['id'] . ' ORDER BY id ASC LIMIT 1');
    $room = $stmt->fetch();
}

if ($tenant && $landlord && $room) {
    $conversation_id = getOrCreateConversation((int) $room['id'], (int) $tenant['id'], (int) $landlord['id']);
    acceptContactRequest($conversation_id, (int) $landlord['id']);
    sendMessage($conversation_id, (int) $tenant['id'], "Hi, I'm interested in this room. Is it still available?");
    sleep(1);
    sendMessage($conversation_id, (int) $landlord['id'], 'Yes, it is still available. Would you like to schedule a visit?');
    sleep(1);
    sendMessage($conversation_id, (int) $tenant['id'], 'That would be great. Is this weekend okay?');
    sleep(1);
    sendMessage($conversation_id, (int) $landlord['id'], 'Sure, Saturday at 11 AM works for me.');
    echo 'Sample conversation created!';
} else {
    echo 'Need at least one tenant, one landlord, and one room.';
}
?>
