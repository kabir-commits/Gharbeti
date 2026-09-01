<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if site is in production mode
if (!defined('IS_PRODUCTION')) {
    define('IS_PRODUCTION', file_exists(__DIR__ . '/../.production'));
}

if (IS_PRODUCTION) {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
} else {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Site configuration
define('SITE_NAME', 'Gharbeti');
define('SITE_URL', 'http://localhost/gharbeti');
define('UPLOAD_PATH', __DIR__ . '/../assets/uploads/');
define('UPLOAD_URL', SITE_URL . '/assets/uploads/');

// Timezone
date_default_timezone_set('Asia/Kathmandu');

// Include database
require_once __DIR__ . '/database.php';

// Create shared connection for app helpers
$database = new Database();
$conn = $database->getConnection();
?>
