<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Success Stories';
require_once __DIR__ . '/../includes/header.php';
$stories = [
    ['title' => 'A student found a room without broker pressure', 'summary' => 'After comparing verified listings and messaging directly, a tenant secured a room near campus with clearer expectations around utilities and notice.'],
    ['title' => 'A landlord reduced no-show inquiries', 'summary' => 'A fuller profile, clearer room photos, and direct messaging helped improve the quality of tenant conversations.'],
    ['title' => 'A signed contract prevented confusion later', 'summary' => 'Digital agreement details made move-in expectations easier to reference when questions came up after the tenancy started.'],
];
?>
<section class="page-shell"><div class="container page-content page-content-wide"><div class="page-hero"><span class="page-eyebrow">Success Stories</span><h1>Examples of what a smoother rental experience can look like.</h1><p>These stories reflect the kind of outcomes the platform is designed to support.</p></div><div class="card-grid card-grid-3"><?php foreach ($stories as $story): ?><article class="page-panel mini-card"><i class="fas fa-star"></i><h2><?php echo htmlspecialchars($story['title']); ?></h2><p><?php echo htmlspecialchars($story['summary']); ?></p></article><?php endforeach; ?></div></div></section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
