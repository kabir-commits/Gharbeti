<?php
require_once 'config/app.php';
require_once 'includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    require_once __DIR__ . '/pages/403.php';
    exit;
}

$csrf_token = generateCSRFToken();
$is_production = defined('IS_PRODUCTION') ? IS_PRODUCTION : file_exists(__DIR__ . '/.production');
$health = function_exists('getSystemHealth') ? getSystemHealth() : [];
$admin_message = $_SESSION['admin_message'] ?? '';
$admin_message_type = $_SESSION['admin_message_type'] ?? 'info';
unset($_SESSION['admin_message'], $_SESSION['admin_message_type']);

$page_title = 'System Setup';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gharbeti - System Setup</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<section class="setup-page-shell">
    <div class="container">
        <div class="dashboard-card dashboard-hero-card" data-animate="fade-up">
            <div class="section-heading compact">
                <div>
                    <span class="section-kicker">System Setup</span>
                    <h1>Administrator-only setup and production hardening tools.</h1>
                    <p class="muted-text">Use this screen for schema updates, maintenance actions, and final launch cleanup. Access is restricted and intended for trusted administrators only.</p>
                </div>
            </div>
        </div>

        <?php if ($admin_message): ?>
            <div class="dashboard-card" data-animate="fade-up" style="max-width:1120px;padding:1rem;">
                <div class="alert alert-<?php echo $admin_message_type === 'success' ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($admin_message); ?></div>
            </div>
        <?php endif; ?>

        <?php if ($is_production): ?>
            <div class="page-panel page-panel-soft setup-card" data-animate="fade-up">
                <span class="page-eyebrow">Production Mode</span>
                <h2>Live-mode safeguards are enabled</h2>
                <p>The `.production` flag is active, so error display is suppressed and this page should be used with extra caution.</p>
            </div>
        <?php endif; ?>

        <div class="setup-grid" data-animate="fade-up">
            <article class="page-panel setup-card mini-card">
                <span class="page-eyebrow">Schema</span>
                <h2>Database Updates</h2>
                <p>Run only the schema installers you actually need for your current environment.</p>
                <div class="setup-actions">
                    <a href="database/schema.php" class="btn-primary">Core Schema</a>
                    <a href="database/phase3_schema.php" class="btn-outline">Phase 3</a>
                    <a href="database/phase4_schema.php" class="btn-outline">Phase 4</a>
                    <a href="database/phase5_schema.php" class="btn-outline">Phase 5</a>
                    <a href="database/phase6_schema.php" class="btn-outline">Phase 6</a>
                    <a href="database/phase7_schema.php" class="btn-outline">Phase 7</a>
                </div>
            </article>

            <article class="page-panel setup-card mini-card">
                <span class="page-eyebrow">Maintenance</span>
                <h2>Operational Tools</h2>
                <p>Clear cache, run a health check, or regenerate default placeholder assets when needed.</p>
                <div class="setup-actions">
                    <form method="POST" action="admin/actions.php">
                        <input type="hidden" name="action" value="clear_cache">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <button type="submit" class="btn-primary">Clear System Cache</button>
                    </form>
                    <form method="POST" action="admin/actions.php">
                        <input type="hidden" name="action" value="check_health">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <button type="submit" class="btn-outline">Run Health Check</button>
                    </form>
                    <a href="create-default-images.php" class="btn-outline">Create Default Images</a>
                </div>
            </article>

            <article class="page-panel setup-card mini-card">
                <span class="page-eyebrow">Cleanup</span>
                <h2>Production Cleanup</h2>
                <p>Use these only when preparing to remove staging data or prune noisy logs.</p>
                <div class="setup-actions">
                    <form method="POST" action="admin/actions.php" onsubmit="return confirm('Clear older system and admin logs while keeping the latest records?');">
                        <input type="hidden" name="action" value="clear_all_logs">
                        <input type="hidden" name="confirmed" value="yes">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <button type="submit" class="btn-outline">Clear Old Logs</button>
                    </form>
                    <form method="POST" action="admin/actions.php" onsubmit="return confirm('Clean up demo and test data? This cannot be undone.');">
                        <input type="hidden" name="action" value="cleanup_test_data">
                        <input type="hidden" name="confirmed" value="yes">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <button type="submit" class="btn-outline">Cleanup Test Data</button>
                    </form>
                    <a href="admin/cleanup-production.php?confirm=yes" class="btn-primary" onclick="return confirm('Run the full production cleanup script?');">Full Production Cleanup</a>
                </div>
            </article>
        </div>

        <div class="card-grid card-grid-2" data-animate="fade-up">
            <section class="page-panel setup-card">
                <span class="page-eyebrow">Environment</span>
                <h2>System Information</h2>
                <ul>
                    <li><strong>PHP Version:</strong> <?php echo htmlspecialchars(PHP_VERSION); ?></li>
                    <li><strong>Server Software:</strong> <?php echo htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'); ?></li>
                    <li><strong>Your IP:</strong> <?php echo htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'Unknown'); ?></li>
                    <li><strong>Production Mode:</strong> <?php echo $is_production ? 'Enabled' : 'Disabled'; ?></li>
                    <li><strong>Database:</strong> <?php echo !empty($health['database']) ? 'Connected' : 'Unavailable'; ?></li>
                </ul>
            </section>

            <section class="page-panel page-panel-soft setup-card">
                <span class="page-eyebrow">Launch Checklist</span>
                <h2>Before going live</h2>
                <p>Use the production checklist to walk through the last security and launch steps.</p>
                <div class="setup-actions">
                    <a href="PRODUCTION-CHECKLIST.txt" class="btn-primary">Open Checklist</a>
                    <a href="index.php" class="btn-outline">Back to Homepage</a>
                </div>
            </section>
        </div>
    </div>
</section>
<script src="<?php echo SITE_URL; ?>/assets/js/animations.js"></script>
</body>
</html>
