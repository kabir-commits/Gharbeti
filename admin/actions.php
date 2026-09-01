<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    die('Access denied');
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    die('Invalid security token');
}

$action = $_POST['action'] ?? '';
$message = 'Unknown action';
$type = 'error';

switch ($action) {
    case 'clear_cache':
        clearCache();
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        logAdminAction(getCurrentUserId(), 'clear_cache', 'system', null, 'Cache cleared manually');
        $message = 'Cache cleared successfully';
        $type = 'success';
        break;

    case 'check_health':
        $health = getSystemHealth();
        addSystemLog('info', 'health_check', 'Health check performed', $health);
        logAdminAction(getCurrentUserId(), 'check_health', 'system', null, 'Manual health check executed');
        $message = 'Health check completed. Review logs for full details.';
        $type = 'success';
        break;

    case 'clear_all_logs':
        global $conn;
        if (!isset($_POST['confirmed']) || $_POST['confirmed'] !== 'yes') {
            $message = 'Please confirm the log cleanup action.';
            break;
        }
        if (tableExists('system_logs')) {
            $conn->exec("DELETE FROM system_logs WHERE id <= (SELECT max_id FROM (SELECT GREATEST(MAX(id) - 1000, 0) AS max_id FROM system_logs) AS t)");
        }
        if (tableExists('admin_actions_log')) {
            $conn->exec("DELETE FROM admin_actions_log WHERE id <= (SELECT max_id FROM (SELECT GREATEST(MAX(id) - 1000, 0) AS max_id FROM admin_actions_log) AS t)");
        }
        logAdminAction(getCurrentUserId(), 'clear_logs', 'system', null, 'Old logs cleared');
        $message = 'Old logs cleared successfully. The latest 1000 entries were kept where possible.';
        $type = 'success';
        break;

    case 'cleanup_test_data':
        global $conn;
        if (!isset($_POST['confirmed']) || $_POST['confirmed'] !== 'yes') {
            $message = 'Please confirm the test data cleanup action.';
            break;
        }
        $summary = [];
        $stmt = $conn->prepare("DELETE FROM users WHERE email LIKE '%@test.com' OR email LIKE '%@example.com' OR email LIKE 'test%@%'");
        $stmt->execute();
        $summary[] = $stmt->rowCount() . ' test users';
        $stmt = $conn->prepare("DELETE FROM rooms WHERE title LIKE '%Test%' OR title LIKE '%Sample%' OR description LIKE '%test%' OR description LIKE '%sample%'");
        $stmt->execute();
        $summary[] = $stmt->rowCount() . ' test rooms';
        if (tableExists('contracts')) {
            $stmt = $conn->prepare("DELETE FROM contracts WHERE contract_number LIKE 'TEST%'");
            $stmt->execute();
            $summary[] = $stmt->rowCount() . ' test contracts';
        }
        if (tableExists('reviews')) {
            $stmt = $conn->prepare("DELETE FROM reviews WHERE review_text LIKE '%test%' OR review_text LIKE '%sample%'");
            $stmt->execute();
            $summary[] = $stmt->rowCount() . ' test reviews';
        }
        if (tableExists('messages')) {
            $stmt = $conn->prepare("DELETE FROM messages WHERE message LIKE '%test%' OR message LIKE '%sample%'");
            $stmt->execute();
            $summary[] = $stmt->rowCount() . ' test messages';
        }
        logAdminAction(getCurrentUserId(), 'cleanup_test_data', 'system', null, 'Cleanup performed: ' . implode(', ', $summary));
        $message = 'Cleanup complete: ' . implode(', ', $summary);
        $type = 'success';
        break;
}

$_SESSION['admin_message'] = $message;
$_SESSION['admin_message_type'] = $type;
header('Location: ' . SITE_URL . '/setup.php');
exit;
