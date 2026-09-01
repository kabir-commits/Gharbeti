<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/auth/login.php');
}

$stats = getAdminStats();
$health = getSystemHealth();
$logs = getAdminLogs(8);

$page_title = 'Admin Dashboard';
require_once __DIR__ . '/../../includes/header.php';
?>
<section class="dashboard-shell admin-shell">
    <div class="container">
        <div class="dashboard-card dashboard-hero-card admin-dashboard-card" data-animate="fade-up">
            <div class="dashboard-header">
                <div>
                    <p class="dashboard-kicker">Admin Panel</p>
                    <h1>Operations Dashboard</h1>
                    <p>Monitor platform growth, moderation workload, and system health from one place.</p>
                </div>
                <div class="admin-badge"><i class="fas fa-shield-alt"></i> Admin Access</div>
            </div>
            <div class="dashboard-actions" style="margin-top:1rem;">
                <a href="<?php echo SITE_URL; ?>/pages/admin/rooms.php" class="btn-primary admin-primary-invert">Moderate Listings</a>
                <a href="<?php echo SITE_URL; ?>/pages/admin/verifications.php" class="btn-outline admin-outline-light">Review Verifications</a>
                <a href="<?php echo SITE_URL; ?>/index.php" class="btn-outline admin-outline-light">Go to Home</a>
            </div>
        </div>

        <div class="profile-overview-grid" style="margin-bottom:1.5rem;">
            <div class="profile-mini-card" data-animate="fade-up"><span class="profile-mini-label">Users</span><strong><?php echo number_format((int) $stats['users']['total']); ?></strong><p><?php echo (int) $stats['users']['new_7days']; ?> joined in the last 7 days.</p></div>
            <div class="profile-mini-card" data-animate="fade-up"><span class="profile-mini-label">Rooms</span><strong><?php echo number_format((int) $stats['rooms']['total']); ?></strong><p><?php echo (int) $stats['rooms']['pending']; ?> pending moderation.</p></div>
            <div class="profile-mini-card" data-animate="fade-up"><span class="profile-mini-label">Contracts</span><strong><?php echo number_format((int) $stats['contracts']['total']); ?></strong><p><?php echo (int) $stats['contracts']['active']; ?> currently active.</p></div>
            <div class="profile-mini-card" data-animate="fade-up"><span class="profile-mini-label">Reviews</span><strong><?php echo number_format((int) $stats['reviews']['total']); ?></strong><p>Average rating <?php echo number_format((float) $stats['reviews']['avg_rating'], 1); ?>/5.</p></div>
        </div>

        <div class="profile-overview-grid" style="margin-bottom:1.5rem;">
            <div class="profile-mini-card" data-animate="fade-up"><span class="profile-mini-label">Verification Queue</span><strong><?php echo number_format((int) $stats['verifications']['pending']); ?></strong><p>ID submissions waiting for review.</p></div>
            <div class="profile-mini-card" data-animate="fade-up"><span class="profile-mini-label">Reported Reviews</span><strong><?php echo number_format((int) $stats['reports']['pending_reviews']); ?></strong><p>Pending review reports need attention.</p></div>
            <div class="profile-mini-card" data-animate="fade-up"><span class="profile-mini-label">Moderation Queue</span><strong><?php echo number_format((int) $stats['moderation']['pending']); ?></strong><p>Items queued by the new moderation tools.</p></div>
            <div class="profile-mini-card" data-animate="fade-up"><span class="profile-mini-label">Weekly Messages</span><strong><?php echo number_format((int) $stats['messages']['last_7days']); ?></strong><p>Messages sent across the last 7 days.</p></div>
        </div>

        <div class="profile-overview-grid" style="margin-bottom:1.5rem;grid-template-columns:1.3fr .9fr;">
            <div class="dashboard-card" data-animate="fade-up">
                <div class="section-heading compact" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
                    <div>
                        <span class="section-kicker">Health</span>
                        <h2>System Health</h2>
                    </div>
                    <span class="status-chip <?php echo !empty($health['database']) && !empty($health['php_version_ok']) ? 'verified' : 'pending'; ?>"><?php echo !empty($health['database']) && !empty($health['php_version_ok']) ? 'Healthy' : 'Needs Attention'; ?></span>
                </div>
                <div class="profile-meta-grid">
                    <div class="profile-meta-item"><i class="fas fa-code"></i><span>PHP <?php echo htmlspecialchars($health['php_version']); ?></span></div>
                    <div class="profile-meta-item"><i class="fas fa-database"></i><span>Database <?php echo !empty($health['database']) ? 'Connected' : 'Unavailable'; ?></span></div>
                    <div class="profile-meta-item"><i class="fas fa-memory"></i><span>Memory Limit: <?php echo htmlspecialchars((string) $health['memory_limit']); ?></span></div>
                    <div class="profile-meta-item"><i class="fas fa-upload"></i><span>Upload Max: <?php echo htmlspecialchars((string) $health['upload_max_filesize']); ?></span></div>
                    <div class="profile-meta-item"><i class="fas fa-hdd"></i><span>Disk Used: <?php echo number_format((float) ($health['disk_used_percent'] ?? 0), 2); ?>%</span></div>
                    <div class="profile-meta-item"><i class="fas fa-clock"></i><span>Execution Time: <?php echo htmlspecialchars((string) $health['max_execution_time']); ?>s</span></div>
                </div>
                <div class="admin-health-grid">
                    <?php foreach (($health['extensions'] ?? []) as $extension => $loaded): ?>
                        <div class="admin-health-item">
                            <span><?php echo htmlspecialchars($extension); ?></span>
                            <span class="status-chip <?php echo $loaded ? 'verified' : 'rejected'; ?>"><?php echo $loaded ? 'Loaded' : 'Missing'; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="dashboard-card" data-animate="fade-up">
                <div class="section-heading compact">
                    <span class="section-kicker">Access</span>
                    <h2>Quick Actions</h2>
                </div>
                <div style="display:grid;gap:0.85rem;">
                    <a href="<?php echo SITE_URL; ?>/pages/admin/rooms.php" class="btn-primary" style="text-align:center;">Open Listing Queue</a>
                    <a href="<?php echo SITE_URL; ?>/pages/admin/verifications.php" class="btn-outline" style="text-align:center;">Open Verification Queue</a>
                    <a href="<?php echo SITE_URL; ?>/pages/contracts.php" class="btn-outline" style="text-align:center;">Browse Contracts</a>
                    <a href="<?php echo SITE_URL; ?>/pages/rooms.php?verified_only=1" class="btn-outline" style="text-align:center;">View Verified Listings</a>
                    <a href="<?php echo SITE_URL; ?>/setup.php" class="btn-outline" style="text-align:center;">Open Setup Tools</a>
                </div>
                <div class="admin-metric-highlight">
                    <strong>Monthly Rent in Active Contracts</strong>
                    <div class="metric-value">Rs. <?php echo number_format((float) $stats['revenue']['total_monthly_rent']); ?></div>
                </div>
            </div>
        </div>

        <div class="dashboard-card" data-animate="fade-up">
            <div class="section-heading compact">
                <span class="section-kicker">Audit</span>
                <h2>Recent Admin Actions</h2>
            </div>
            <div class="admin-history-table-wrap">
                <table class="admin-history-table">
                    <thead>
                        <tr>
                            <th>Admin</th>
                            <th>Action</th>
                            <th>Target</th>
                            <th>Description</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="5" class="muted-text text-center">No admin actions logged yet. Run the Phase 7 schema and use the admin tools to populate this timeline.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['full_name'] ?? $log['email']); ?></td>
                                    <td><?php echo htmlspecialchars($log['action_type']); ?></td>
                                    <td><?php echo htmlspecialchars($log['target_type']); ?><?php if (!empty($log['target_id'])): ?> #<?php echo (int) $log['target_id']; ?><?php endif; ?></td>
                                    <td><?php echo htmlspecialchars($log['description'] ?? ''); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
