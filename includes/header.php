<?php
if (function_exists('isMaintenanceMode') && isMaintenanceMode() && !isAdmin()) {
    http_response_code(503);
    require_once __DIR__ . '/../maintenance.php';
    exit;
}
if (function_exists('trackVisit')) {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    if ($requestUri !== '' && !preg_match('/\.(css|js|png|jpe?g|gif|svg|webp|ico|map)$/i', $requestUri)) {
        trackVisit($requestUri);
    }
}
require_once __DIR__ . '/components.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Home'; ?> - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/svg+xml" href="<?php echo SITE_URL; ?>/assets/images/favicon.svg">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/main.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/responsive.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/skeleton.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/utilities.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/print.css" media="print">
</head>
<body>
    <?php
    $siteLogoUrl = getSiteLogoUrl();
    if (isLoggedIn()) {
        $_SESSION['unread_messages'] = tableExists('conversations') ? getUnreadMessageCount(getCurrentUserId()) : 0;
        $_SESSION['unread_notifications'] = tableExists('notifications') ? getUnreadNotificationCount(getCurrentUserId()) : 0;
    }
    ?>
    <nav class="navbar">
        <div class="nav-container">
            <a href="<?php echo SITE_URL; ?>/index.php" class="logo" aria-label="Go to Gharbeti homepage">
                <?php if ($siteLogoUrl): ?>
                    <img src="<?php echo $siteLogoUrl; ?>" alt="Gharbeti logo" class="site-logo">
                <?php else: ?>
                    <span class="logo-mark"><i class="fas fa-home"></i></span>
                <?php endif; ?>
                <span class="brand-text">
                    <span class="brand-name">Gharbeti</span>
                    <span class="brand-tagline">Live where you belong</span>
                </span>
            </a>
            <div class="nav-menu" id="navMenu">
                <ul class="nav-links">
                    <li><a href="<?php echo SITE_URL; ?>/pages/rooms.php">Browse Rooms</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/pages/how-it-works.php">How It Works</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/pages/about.php">About</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/pages/faq.php">FAQ</a></li>
                    <?php if (isLoggedIn()): ?>
                        <li><a href="<?php echo SITE_URL; ?>/pages/dashboard.php">Dashboard</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/pages/messages.php">Messages<?php if (!empty($_SESSION['unread_messages'])): ?><span class="header-badge unread-badge" data-badge="messages"><?php echo (int) $_SESSION['unread_messages']; ?></span><?php endif; ?></a></li>
                        <li><a href="<?php echo SITE_URL; ?>/pages/notifications.php"><i class="fas fa-bell"></i><?php if (!empty($_SESSION['unread_notifications'])): ?><span class="header-badge unread-badge" data-badge="notifications"><?php echo (int) $_SESSION['unread_notifications']; ?></span><?php endif; ?></a></li>
                        <?php if (isAdmin()): ?>
                            <li><a href="<?php echo SITE_URL; ?>/pages/admin/dashboard.php">Admin</a></li>
                        <?php endif; ?>
                        <li class="nav-dropdown">
                            <a href="#" class="dropdown-toggle">
                                <img src="<?php echo getUserAvatarUrl($_SESSION['user_avatar'] ?? null); ?>" alt="Profile" class="nav-avatar">
                                <?php echo $_SESSION['user_name'] ?? 'Account'; ?> <i class="fas fa-chevron-down"></i>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a href="<?php echo SITE_URL; ?>/pages/profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/pages/favorites.php"><i class="fas fa-heart"></i> Saved Rooms</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/pages/contracts.php"><i class="fas fa-file-contract"></i> My Contracts</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/pages/settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/pages/help.php"><i class="fas fa-question-circle"></i> Help Center</a></li>
                                <li><hr></li>
                                <li><a href="<?php echo SITE_URL; ?>/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li><a href="<?php echo SITE_URL; ?>/auth/login.php" class="btn-outline">Login</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/auth/register.php" class="btn-primary">Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="hamburger" id="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </nav>
    <main>
