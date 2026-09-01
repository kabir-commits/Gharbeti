<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    redirect(SITE_URL . '/auth/login.php?redirect=' . urlencode(SITE_URL . '/pages/settings.php'));
}

$page_title = 'Settings';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="page-shell">
    <div class="container page-content page-content-wide">
        <div class="page-hero page-hero-left" data-animate="fade-up">
            <span class="page-eyebrow">Settings</span>
            <h1>Manage your account, privacy touchpoints, and everyday shortcuts.</h1>
            <p>This keeps the utility side of the platform aligned with the same polished feel as discovery, contracts, and messaging.</p>
        </div>

        <div class="settings-grid" data-animate="fade-up">
            <div class="page-panel mini-card">
                <span class="settings-badge"><i class="fas fa-user"></i> Profile</span>
                <h3>Personal Details</h3>
                <p>Update your public identity, contact info, bio, and social links.</p>
                <div class="settings-link-row">
                    <a href="<?php echo SITE_URL; ?>/pages/profile.php" class="btn-primary">Open Profile</a>
                </div>
            </div>
            <div class="page-panel mini-card">
                <span class="settings-badge"><i class="fas fa-bell"></i> Alerts</span>
                <h3>Notification Control</h3>
                <p>Review unread alerts, contract updates, and message activity in one stream.</p>
                <div class="settings-link-row">
                    <a href="<?php echo SITE_URL; ?>/pages/notifications.php" class="btn-primary">View Notifications</a>
                </div>
            </div>
            <div class="page-panel mini-card">
                <span class="settings-badge"><i class="fas fa-heart"></i> Saved</span>
                <h3>Saved Rooms</h3>
                <p>Return to rooms you shortlisted so you can compare them later.</p>
                <div class="settings-link-row">
                    <a href="<?php echo SITE_URL; ?>/pages/favorites.php" class="btn-primary">Open Favorites</a>
                </div>
            </div>
        </div>

        <div class="card-grid card-grid-2" data-animate="fade-up">
            <section class="page-panel settings-shortcut-card">
                <span class="page-eyebrow">Quick Actions</span>
                <h2>Common account tasks</h2>
                <div class="settings-actions-list">
                    <a href="<?php echo SITE_URL; ?>/pages/contracts.php" class="text-link">Review your contract history</a>
                    <a href="<?php echo SITE_URL; ?>/pages/messages.php" class="text-link">Open recent conversations</a>
                    <a href="<?php echo SITE_URL; ?>/pages/help.php" class="text-link">Get help with verification or support</a>
                    <a href="<?php echo SITE_URL; ?>/auth/logout.php" class="text-link">Log out securely</a>
                </div>
            </section>
            <section class="page-panel page-panel-soft settings-shortcut-card">
                <span class="page-eyebrow">Need More Control?</span>
                <h2>Some settings still live in other flows</h2>
                <p>Parts of the full settings experience are still handled inside your profile, notifications, and support tools while we continue unifying the rest of the product.</p>
                <div class="settings-link-row">
                    <a href="<?php echo SITE_URL; ?>/pages/contact.php" class="btn-outline">Contact Support</a>
                    <a href="<?php echo SITE_URL; ?>/pages/help.php" class="btn-primary">Open Help Center</a>
                </div>
            </section>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
