<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    setUserOffline(getCurrentUserId());
}

logoutUser();
redirect(SITE_URL . '/index.php');
?>
