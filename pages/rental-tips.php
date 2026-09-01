<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Rental Tips';
require_once __DIR__ . '/../includes/header.php';
$sections = [
    'For Tenants' => ['Start your search early enough to compare options instead of accepting the first available room under pressure.', 'Visit the property in person and verify what is actually included in the rent.', 'Ask direct questions about water, internet, electricity, guests, notice period, and maintenance expectations.', 'Take move-in photos so room condition is documented from day one.'],
    'For Landlords' => ['Use honest photos and detailed descriptions that match the actual room condition.', 'State extra charges clearly so tenants are not surprised later.', 'Respond quickly and keep conversations professional to build stronger trust.', 'Use written contracts instead of relying on verbal promises.'],
    'Safety Tips' => ['Avoid sending money before identity and property details are properly checked.', 'Keep important communication on-platform whenever possible.', 'Report suspicious users, fraudulent listings, or abusive behavior promptly.'],
];
?>
<section class="page-shell"><div class="container page-content page-content-wide"><div class="page-hero"><span class="page-eyebrow">Rental Tips</span><h1>Practical advice for better rental decisions.</h1><p>Simple habits can prevent the biggest misunderstandings before and after move-in.</p></div><?php foreach ($sections as $title => $tips): ?><section class="page-panel"><h2><?php echo htmlspecialchars($title); ?></h2><div class="card-grid card-grid-2"><?php foreach ($tips as $tip): ?><article class="mini-card mini-card-left"><i class="fas fa-check-circle"></i><p><?php echo htmlspecialchars($tip); ?></p></article><?php endforeach; ?></div></section><?php endforeach; ?></div></section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
