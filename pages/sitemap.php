<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Sitemap';
require_once __DIR__ . '/../includes/header.php';
$sections = [
    'Platform' => [['Home', SITE_URL . '/index.php'], ['Browse Rooms', SITE_URL . '/pages/rooms.php'], ['How It Works', SITE_URL . '/pages/how-it-works.php'], ['About', SITE_URL . '/pages/about.php'], ['Contact', SITE_URL . '/pages/contact.php']],
    'Resources' => [['FAQ', SITE_URL . '/pages/faq.php'], ['Blog', SITE_URL . '/pages/blog.php'], ['Rental Tips', SITE_URL . '/pages/rental-tips.php'], ['Verification Guide', SITE_URL . '/pages/verification-guide.php'], ['Renter Rights', SITE_URL . '/pages/renter-rights.php']],
    'Legal' => [['Privacy Policy', SITE_URL . '/pages/privacy.php'], ['Terms of Service', SITE_URL . '/pages/terms.php'], ['Cookie Policy', SITE_URL . '/pages/cookie-policy.php'], ['Disclaimer', SITE_URL . '/pages/disclaimer.php']],
    'Account' => [['Login', SITE_URL . '/auth/login.php'], ['Register', SITE_URL . '/auth/register.php'], ['Dashboard', SITE_URL . '/pages/dashboard.php'], ['Profile', SITE_URL . '/pages/profile.php'], ['Messages', SITE_URL . '/pages/messages.php'], ['Settings', SITE_URL . '/pages/settings.php']],
];
?>
<section class="page-shell"><div class="container page-content page-content-wide"><div class="page-hero"><span class="page-eyebrow">Sitemap</span><h1>A quick way to explore the full site.</h1><p>Use this page as a directory for the main product flows, resources, and legal pages.</p></div><div class="card-grid card-grid-3"><?php foreach ($sections as $title => $links): ?><section class="page-panel"><h2><?php echo htmlspecialchars($title); ?></h2><ul class="link-list"><?php foreach ($links as $link): ?><li><a href="<?php echo htmlspecialchars($link[1]); ?>"><?php echo htmlspecialchars($link[0]); ?></a></li><?php endforeach; ?></ul></section><?php endforeach; ?></div></div></section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
