<?php
http_response_code(403);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Access Denied';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="error-page-shell">
    <div class="dashboard-card dashboard-hero-card error-display-card" data-animate="fade-up">
        <div class="error-code-badge">403</div>
        <span class="section-kicker">Access Restricted</span>
        <h1 style="margin-bottom: 0.75rem;">This area is locked to protect the right people and data.</h1>
        <p class="muted-text" style="max-width: 620px; margin: 0 auto;">Your account does not currently have permission to view this page. If you think this is a mistake, head back to a safe area and try again with the correct role.</p>
        <div class="error-actions">
            <a href="<?php echo SITE_URL; ?>/index.php" class="btn-primary">Back to Home</a>
            <a href="<?php echo SITE_URL; ?>/pages/rooms.php" class="btn-outline">Browse Rooms</a>
        </div>
        <div class="error-help-grid">
            <div class="profile-mini-card" data-animate="fade-up">
                <span class="profile-mini-label">Try This</span>
                <strong>Return to Dashboard</strong>
                <p class="muted-text">If you are signed in, your dashboard will show the pages available to your role.</p>
            </div>
            <div class="profile-mini-card" data-animate="fade-up">
                <span class="profile-mini-label">Need Another Path</span>
                <strong>Browse Public Pages</strong>
                <p class="muted-text">You can still explore verified rooms, guides, and public resources.</p>
            </div>
            <div class="profile-mini-card" data-animate="fade-up">
                <span class="profile-mini-label">Still Stuck?</span>
                <strong>Contact Support</strong>
                <p class="muted-text">If access should be available to you, reach out and we can help verify the issue.</p>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
