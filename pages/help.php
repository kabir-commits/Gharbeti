<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Help Center';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="page-shell">
    <div class="container page-content page-content-wide">
        <div class="page-hero" data-animate="fade-up">
            <span class="page-eyebrow">Help Center</span>
            <h1>Support should feel like part of the product, not a dead-end utility page.</h1>
            <p>Start with the path that matches your issue and move straight into the most useful resource.</p>
        </div>

        <div class="help-grid card-grid card-grid-3" data-animate="fade-up">
            <article class="page-panel mini-card help-resource-card">
                <span class="help-badge"><i class="fas fa-question-circle"></i> FAQ</span>
                <h3>Fast answers</h3>
                <p>Start with the most common questions about rooms, contracts, verification, and account issues.</p>
                <div class="help-link-row">
                    <a href="<?php echo SITE_URL; ?>/pages/faq.php" class="btn-primary">Open FAQ</a>
                </div>
            </article>
            <article class="page-panel mini-card help-resource-card">
                <span class="help-badge"><i class="fas fa-user-check"></i> Verification</span>
                <h3>Trust and identity help</h3>
                <p>Understand document requirements, approval timelines, and how trust score signals work.</p>
                <div class="help-link-row">
                    <a href="<?php echo SITE_URL; ?>/pages/verification-guide.php" class="btn-primary">Read Guide</a>
                </div>
            </article>
            <article class="page-panel mini-card help-resource-card">
                <span class="help-badge"><i class="fas fa-envelope"></i> Support</span>
                <h3>Talk to the team</h3>
                <p>Reach out directly when you need help with bugs, access issues, or a rental workflow problem.</p>
                <div class="help-link-row">
                    <a href="<?php echo SITE_URL; ?>/pages/contact.php" class="btn-primary">Contact Us</a>
                </div>
            </article>
        </div>

        <div class="card-grid card-grid-2" data-animate="fade-up">
            <section class="page-panel help-resource-card">
                <span class="page-eyebrow">Popular Paths</span>
                <h2>Where most users go next</h2>
                <div class="help-resource-list">
                    <a href="<?php echo SITE_URL; ?>/pages/rental-tips.php" class="text-link">Rental tips for tenants and landlords</a>
                    <a href="<?php echo SITE_URL; ?>/pages/contracts.php" class="text-link">Review contracts and signing status</a>
                    <a href="<?php echo SITE_URL; ?>/pages/notifications.php" class="text-link">Check unread activity and system alerts</a>
                    <a href="<?php echo SITE_URL; ?>/pages/profile.php" class="text-link">Update your profile and verification info</a>
                </div>
            </section>
            <section class="page-panel page-panel-soft help-resource-card">
                <span class="page-eyebrow">Need Human Help?</span>
                <h2>Use the direct support route</h2>
                <p>If the issue affects your room listing, contract, or account access, contacting support directly is the fastest path.</p>
                <div class="help-link-row">
                    <a href="<?php echo SITE_URL; ?>/pages/contact.php" class="btn-primary">Contact Support</a>
                    <a href="<?php echo SITE_URL; ?>/pages/sitemap.php" class="btn-outline">Browse Sitemap</a>
                </div>
            </section>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
