<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    redirect(SITE_URL . '/auth/login.php');
}

$user_id = getCurrentUserId();
$contracts = getUserContracts($user_id, getCurrentUserRole());
$page_title = 'My Contracts';
require_once __DIR__ . '/../includes/header.php';

$status_counts = [
    'total' => count($contracts),
    'active' => 0,
    'pending' => 0,
    'closed' => 0,
];

foreach ($contracts as $contract) {
    $status = $contract['status'] ?? '';
    if ($status === 'active') {
        $status_counts['active']++;
    } elseif (in_array($status, ['draft', 'pending_tenant', 'pending_landlord'], true)) {
        $status_counts['pending']++;
    } else {
        $status_counts['closed']++;
    }
}
?>
<section class="dashboard-shell contracts-page">
    <div class="dashboard-card dashboard-hero-card" data-animate="fade-up" style="max-width: 1180px;">
        <div class="section-heading compact">
            <div>
                <span class="section-kicker">Contracts</span>
                <h1>Rental agreements that feel as solid as the rest of the platform.</h1>
                <p class="muted-text">Track every draft, active agreement, and completed tenancy from one place.</p>
            </div>
        </div>
        <div class="contract-hero-meta">
            <span><i class="fas fa-file-signature"></i> <?php echo (int) $status_counts['total']; ?> total contracts</span>
            <span><i class="fas fa-check-circle"></i> <?php echo (int) $status_counts['active']; ?> active now</span>
            <span><i class="fas fa-clock"></i> <?php echo (int) $status_counts['pending']; ?> awaiting action</span>
        </div>
    </div>

    <div class="contracts-grid" data-animate="fade-up">
        <div class="profile-card contract-summary-card">
            <span class="profile-mini-label">Total Agreements</span>
            <strong><?php echo (int) $status_counts['total']; ?></strong>
            <p class="muted-text">All contracts linked to your account.</p>
        </div>
        <div class="profile-card contract-summary-card">
            <span class="profile-mini-label">Active Contracts</span>
            <strong><?php echo (int) $status_counts['active']; ?></strong>
            <p class="muted-text">Currently live rental agreements.</p>
        </div>
        <div class="profile-card contract-summary-card">
            <span class="profile-mini-label">Waiting For Signature</span>
            <strong><?php echo (int) $status_counts['pending']; ?></strong>
            <p class="muted-text">Drafts and pending approvals.</p>
        </div>
        <div class="profile-card contract-summary-card">
            <span class="profile-mini-label">Completed Or Closed</span>
            <strong><?php echo (int) $status_counts['closed']; ?></strong>
            <p class="muted-text">Expired or terminated contracts.</p>
        </div>
    </div>

    <div class="dashboard-card" data-animate="fade-up" style="max-width: 1180px;">
        <div class="section-heading compact" style="margin-bottom: 1.25rem;">
            <div>
                <span class="section-kicker">Your Timeline</span>
                <h2>All contract activity</h2>
            </div>
        </div>

        <?php if (empty($contracts)): ?>
            <div class="admin-empty-state">
                <i class="fas fa-file-contract" style="font-size: 3rem; color: var(--text-lighter); margin-bottom: 1rem;"></i>
                <h3>No contracts yet</h3>
                <p class="muted-text">Once a landlord and tenant agree on terms, the contract will appear here with signing progress and next steps.</p>
                <a href="<?php echo SITE_URL; ?>/pages/rooms.php" class="btn-primary">Browse Rooms</a>
            </div>
        <?php else: ?>
            <div class="contract-list">
                <?php foreach ($contracts as $contract): ?>
                    <?php
                    $status_class = $contract['status'] === 'active' ? 'verified' : (in_array($contract['status'], ['draft', 'pending_tenant', 'pending_landlord'], true) ? 'pending' : ($contract['status'] === 'terminated' ? 'rejected' : 'processing'));
                    ?>
                    <a href="<?php echo SITE_URL; ?>/pages/contract-detail.php?id=<?php echo (int) $contract['id']; ?>" class="contract-card-link">
                        <article class="profile-card contract-card" data-animate="fade-up">
                            <img src="<?php echo getRoomImageUrl($contract['room_image'] ?? null); ?>" alt="<?php echo htmlspecialchars($contract['room_title']); ?>" class="contract-card-image">
                            <div class="contract-card-main">
                                <div class="contract-card-top">
                                    <div>
                                        <h3 style="margin-bottom: 0.35rem;"><?php echo htmlspecialchars($contract['room_title']); ?></h3>
                                        <p class="muted-text"><?php echo htmlspecialchars($contract['location']); ?></p>
                                    </div>
                                    <span class="status-chip <?php echo $status_class; ?>"><?php echo ucwords(str_replace('_', ' ', $contract['status'])); ?></span>
                                </div>
                                <div class="contract-card-meta">
                                    <div>
                                        <span class="profile-mini-label">Contract #</span>
                                        <strong><?php echo htmlspecialchars($contract['contract_number']); ?></strong>
                                    </div>
                                    <div>
                                        <span class="profile-mini-label">Monthly Rent</span>
                                        <strong>Rs. <?php echo number_format((float) $contract['monthly_rent']); ?></strong>
                                    </div>
                                    <div>
                                        <span class="profile-mini-label">Other Party</span>
                                        <strong><?php echo htmlspecialchars($contract['other_party_name'] ?? 'Other party'); ?></strong>
                                    </div>
                                </div>
                                <div class="contract-card-foot">
                                    <span class="muted-text"><?php echo !empty($contract['tenant_signed_at']) ? 'Tenant signed' : 'Tenant pending'; ?></span>
                                    <span class="muted-text"><?php echo !empty($contract['landlord_signed_at']) ? 'Landlord signed' : 'Landlord pending'; ?></span>
                                    <?php if (!empty($contract['contract_start_date'])): ?>
                                        <span class="muted-text">Starts <?php echo date('M d, Y', strtotime($contract['contract_start_date'])); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <i class="fas fa-chevron-right" style="color: var(--text-light);"></i>
                        </article>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
