<?php
http_response_code(404);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Page Not Found';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="error-page-shell">
    <div class="dashboard-card dashboard-hero-card error-display-card" data-animate="fade-up">
        <div class="error-code-badge">404</div>
        <span class="section-kicker">Lost The Trail</span>
        <h1 style="margin-bottom: 0.75rem;">We could not find the page you were looking for.</h1>
        <p class="muted-text" style="max-width: 620px; margin: 0 auto;">The link may be outdated, the page may have moved, or the address may have a small typo. The good news is the rest of the platform is still right where it should be.</p>
        <div class="error-actions">
            <a href="<?php echo SITE_URL; ?>/index.php" class="btn-primary">Back to Home</a>
            <a href="<?php echo SITE_URL; ?>/pages/rooms.php" class="btn-outline">Browse Rooms</a>
        </div>
        <div class="error-help-grid">
            <div class="profile-mini-card" data-animate="fade-up">
                <span class="profile-mini-label">Try This</span>
                <strong>Check The URL</strong>
                <p class="muted-text">A small typo in the address can lead here, especially after copying links manually.</p>
            </div>
            <div class="profile-mini-card" data-animate="fade-up">
                <span class="profile-mini-label">Need Another Path</span>
                <strong>Start From Home</strong>
                <p class="muted-text">Use the homepage, rooms page, or dashboard to find the same destination again.</p>
            </div>
            <div class="profile-mini-card" data-animate="fade-up">
                <span class="profile-mini-label">Still Stuck?</span>
                <strong>Use The Sitemap</strong>
                <p class="muted-text">The sitemap and footer links can help you jump directly to real working pages.</p>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
