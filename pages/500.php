<?php
http_response_code(500);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Server Error';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="error-page-shell">
    <div class="dashboard-card dashboard-hero-card error-display-card" data-animate="fade-up">
        <div class="error-code-badge">500</div>
        <span class="section-kicker">Something Broke</span>
        <h1 style="margin-bottom: 0.75rem;">Something went wrong on our side.</h1>
        <p class="muted-text" style="max-width: 620px; margin: 0 auto;">This is a temporary server issue, not something you caused. Give it a moment, then retry the action or return to a stable page while things recover.</p>
        <div class="error-actions">
            <a href="<?php echo SITE_URL; ?>/index.php" class="btn-primary">Back to Home</a>
            <a href="<?php echo SITE_URL; ?>/pages/rooms.php" class="btn-outline">Browse Rooms</a>
        </div>
        <div class="error-help-grid">
            <div class="profile-mini-card" data-animate="fade-up">
                <span class="profile-mini-label">Try This</span>
                <strong>Refresh The Page</strong>
                <p class="muted-text">Temporary glitches often clear on the next request.</p>
            </div>
            <div class="profile-mini-card" data-animate="fade-up">
                <span class="profile-mini-label">Need Another Path</span>
                <strong>Open A Stable Page</strong>
                <p class="muted-text">The homepage or rooms page can help you continue while this specific page recovers.</p>
            </div>
            <div class="profile-mini-card" data-animate="fade-up">
                <span class="profile-mini-label">Still Stuck?</span>
                <strong>Report The Issue</strong>
                <p class="muted-text">If the problem keeps happening, let support know what page triggered it.</p>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
