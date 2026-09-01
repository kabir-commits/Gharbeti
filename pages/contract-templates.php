<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Contract Templates';
require_once __DIR__ . '/../includes/header.php';
$templates = [
    ['name' => 'Standard Room Rental', 'summary' => 'A balanced default template covering rent, notice, deposits, utilities, and house rules.', 'icon' => 'file-contract'],
    ['name' => 'Short-Term Stay', 'summary' => 'A lighter agreement structure for shorter rental periods and temporary occupancy.', 'icon' => 'calendar-alt'],
    ['name' => 'Shared Housing Agreement', 'summary' => 'Useful when expectations around common spaces and shared responsibilities need to be explicit.', 'icon' => 'users'],
    ['name' => 'Furnished Room Agreement', 'summary' => 'Adds room for inventory, furniture condition, and replacement responsibility.', 'icon' => 'couch'],
];
?>
<section class="page-shell"><div class="container page-content page-content-wide"><div class="page-hero"><span class="page-eyebrow">Contract Templates</span><h1>Explore common rental agreement formats.</h1><p>These examples help users understand how different rental situations are usually structured before creating a real contract.</p></div><div class="card-grid card-grid-3"><?php foreach ($templates as $template): ?><article class="page-panel mini-card"><i class="fas fa-<?php echo htmlspecialchars($template['icon']); ?>"></i><h2><?php echo htmlspecialchars($template['name']); ?></h2><p><?php echo htmlspecialchars($template['summary']); ?></p><a href="<?php echo SITE_URL; ?>/pages/create-contract.php" class="text-link">Create a contract</a></article><?php endforeach; ?></div></div></section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
