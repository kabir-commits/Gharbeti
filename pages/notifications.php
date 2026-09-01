<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    redirect(SITE_URL . '/auth/login.php');
}

$user_id = getCurrentUserId();
$csrf_token = generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $_SESSION['error'] = 'Invalid security token';
        redirect(SITE_URL . '/pages/notifications.php');
    }

    $notification_id = isset($_POST['notification_id']) ? (int) $_POST['notification_id'] : 0;
    if (isset($_POST['mark_all_read'])) {
        markAllNotificationsRead($user_id);
    } elseif (isset($_POST['mark_read'])) {
        markNotificationRead($notification_id, $user_id);
    } elseif (isset($_POST['delete'])) {
        deleteNotification($notification_id, $user_id);
    }
    redirect(SITE_URL . '/pages/notifications.php');
}

$notifications = getUserNotifications($user_id, 50);
$_SESSION['unread_notifications'] = getUnreadNotificationCount($user_id);

$page_title = 'Notifications';
require_once __DIR__ . '/../includes/header.php';

$type_styles = [
    'message' => ['bg' => '#dff0ff', 'color' => '#0a5d9b', 'icon' => 'envelope'],
    'contact_request' => ['bg' => '#fff3d4', 'color' => '#8a6200', 'icon' => 'handshake'],
    'contract' => ['bg' => '#dff6e6', 'color' => '#1f7a42', 'icon' => 'file-signature'],
    'verification' => ['bg' => '#dff4f7', 'color' => '#0b6470', 'icon' => 'id-card'],
    'review' => ['bg' => '#f3e8ff', 'color' => '#7d3cb5', 'icon' => 'star'],
    'system' => ['bg' => '#eceff3', 'color' => '#44505c', 'icon' => 'bell'],
];

$unread_count = 0;
foreach ($notifications as $notification) {
    if (empty($notification['is_read'])) {
        $unread_count++;
    }
}
?>
<section class="dashboard-shell">
    <div class="dashboard-card dashboard-hero-card" data-animate="fade-up" style="max-width: 1080px;">
        <div class="section-heading compact">
            <div>
                <span class="section-kicker">Notifications</span>
                <h1>Every update in one polished, readable stream.</h1>
                <p class="muted-text">Stay on top of messages, contact requests, contracts, and system activity without the UI dropping back to a plain utility screen.</p>
            </div>
            <?php if (!empty($notifications)): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <button type="submit" name="mark_all_read" class="btn-outline"><i class="fas fa-check-double"></i> Mark All as Read</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="notification-summary-grid" data-animate="fade-up" style="max-width: 1080px; margin: 0 auto 1rem;">
        <div class="profile-card contract-summary-card">
            <span class="profile-mini-label">Total Alerts</span>
            <strong><?php echo count($notifications); ?></strong>
            <p class="muted-text">Everything currently visible in your inbox.</p>
        </div>
        <div class="profile-card contract-summary-card">
            <span class="profile-mini-label">Unread</span>
            <strong><?php echo $unread_count; ?></strong>
            <p class="muted-text">Items that still need your attention.</p>
        </div>
        <div class="profile-card contract-summary-card">
            <span class="profile-mini-label">Read</span>
            <strong><?php echo max(count($notifications) - $unread_count, 0); ?></strong>
            <p class="muted-text">Alerts already reviewed or completed.</p>
        </div>
    </div>

    <div class="dashboard-card" data-animate="fade-up" style="max-width: 1080px;">
        <?php if (empty($notifications)): ?>
            <div class="admin-empty-state">
                <i class="fas fa-bell" style="font-size: 3rem; color: var(--primary);"></i>
                <h3>No notifications yet</h3>
                <p class="muted-text">You're all caught up.</p>
            </div>
        <?php else: ?>
            <div class="notification-list">
                <?php foreach ($notifications as $notification): ?>
                    <?php $style = $type_styles[$notification['type']] ?? $type_styles['system']; ?>
                    <article class="profile-card notification-item-card <?php echo empty($notification['is_read']) ? 'unread' : ''; ?>" data-animate="fade-up">
                        <div class="notification-item-top">
                            <div class="notification-icon-wrap" style="background: <?php echo $style['bg']; ?>; color: <?php echo $style['color']; ?>;">
                                <i class="fas fa-<?php echo $style['icon']; ?>"></i>
                            </div>
                            <div class="notification-copy">
                                <div class="notification-headline">
                                    <div>
                                        <h3><?php echo htmlspecialchars($notification['title']); ?></h3>
                                        <p class="muted-text" style="margin-top: 0.45rem;"><?php echo htmlspecialchars($notification['message']); ?></p>
                                    </div>
                                    <span class="muted-text" style="font-size: 0.86rem;"><?php echo htmlspecialchars(timeAgo($notification['created_at'])); ?></span>
                                </div>
                                <div class="notification-actions-row">
                                    <?php if (!empty($notification['link'])): ?>
                                        <?php $link = strpos($notification['link'], 'http') === 0 ? $notification['link'] : SITE_URL . '/' . ltrim($notification['link'], '/'); ?>
                                        <a href="<?php echo $link; ?>" class="btn-primary btn-small">Open</a>
                                    <?php endif; ?>
                                    <?php if (empty($notification['is_read'])): ?>
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="notification_id" value="<?php echo (int) $notification['id']; ?>">
                                            <button type="submit" name="mark_read" class="btn-outline btn-small">Mark as Read</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" onsubmit="return confirm('Delete this notification?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="notification_id" value="<?php echo (int) $notification['id']; ?>">
                                        <button type="submit" name="delete" class="btn-outline btn-small notification-delete-btn">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
