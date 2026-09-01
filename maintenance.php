<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';
$maintenance_message = function_exists('getSiteSetting') ? getSiteSetting('maintenance_message', 'Site under maintenance. Please check back later.') : 'Site under maintenance. Please check back later.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Mode - Gharbeti</title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
</head>
<body>
<section class="error-page-shell">
    <div class="dashboard-card dashboard-hero-card error-display-card" data-animate="fade-up">
        <div class="error-code-badge"><i class="fas fa-screwdriver-wrench"></i></div>
        <span class="section-kicker">Maintenance Mode</span>
        <h1 style="margin-bottom:0.75rem;">We are making improvements behind the scenes.</h1>
        <p class="muted-text" style="max-width:620px;margin:0 auto;"><?php echo htmlspecialchars($maintenance_message); ?></p>
        <div class="error-actions">
            <a href="javascript:location.reload()" class="btn-primary">Refresh</a>
        </div>
    </div>
</section>
<script src="<?php echo SITE_URL; ?>/assets/js/animations.js"></script>
</body>
</html>
