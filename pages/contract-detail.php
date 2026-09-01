<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    redirect(SITE_URL . '/auth/login.php');
}

$user_id = getCurrentUserId();
$contract_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$contract = getContractDetails($contract_id);
if (!$contract) {
    redirect(SITE_URL . '/pages/contracts.php');
}
if ($user_id != $contract['tenant_id'] && $user_id != $contract['landlord_id'] && !isAdmin()) {
    redirect(SITE_URL . '/pages/contracts.php');
}

$is_tenant = ((int) $user_id === (int) $contract['tenant_id']);
$is_landlord = ((int) $user_id === (int) $contract['landlord_id']);
$other_party = $is_tenant ? $contract['landlord_name'] : $contract['tenant_name'];
$error = '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $error = 'Invalid security token';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'sign') {
            $signature_data = 'Signed by ' . ($is_tenant ? $contract['tenant_name'] : $contract['landlord_name']) . ' on ' . date('Y-m-d H:i:s');
            $result = signContract($contract_id, $user_id, $signature_data);
            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
                redirect(SITE_URL . '/pages/contract-detail.php?id=' . $contract_id);
            }
            $error = $result['message'];
        } elseif ($action === 'generate_pdf') {
            $result = generateContractPDF($contract_id);
            if ($result['success']) {
                redirect($result['pdf_url']);
            }
            $error = $result['message'];
        } elseif ($action === 'terminate') {
            $result = terminateContract($contract_id, $user_id, trim($_POST['termination_reason'] ?? ''));
            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
                redirect(SITE_URL . '/pages/contract-detail.php?id=' . $contract_id);
            }
            $error = $result['message'];
        }
        $contract = getContractDetails($contract_id);
    }
}

$page_title = 'Contract Details';
require_once __DIR__ . '/../includes/header.php';
$csrf_token = generateCSRFToken();
$status_class = $contract['status'] === 'active' ? 'verified' : (in_array($contract['status'], ['draft', 'pending_tenant', 'pending_landlord'], true) ? 'pending' : ($contract['status'] === 'terminated' ? 'rejected' : 'processing'));
?>
<section class="dashboard-shell contract-detail-page">
    <div class="dashboard-card dashboard-hero-card" data-animate="fade-up" style="max-width: 1180px;">
        <div class="section-heading compact">
            <div>
                <span class="section-kicker">Contract Details</span>
                <h1><?php echo htmlspecialchars($contract['room_title']); ?></h1>
                <p class="muted-text">Agreement between <?php echo htmlspecialchars($contract['landlord_name']); ?> and <?php echo htmlspecialchars($contract['tenant_name']); ?>.</p>
            </div>
            <span class="status-chip <?php echo $status_class; ?>"><?php echo ucwords(str_replace('_', ' ', $contract['status'])); ?></span>
        </div>
        <div class="contract-hero-meta">
            <span><i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($contract['contract_number']); ?></span>
            <span><i class="fas fa-money-bill-wave"></i> Rs. <?php echo number_format((float) $contract['monthly_rent']); ?>/month</span>
            <span><i class="fas fa-calendar-day"></i> <?php echo date('M d, Y', strtotime($contract['contract_start_date'])); ?></span>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="dashboard-card" data-animate="fade-up" style="max-width: 1180px; padding: 1rem;">
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="dashboard-card" data-animate="fade-up" style="max-width: 1180px; padding: 1rem;">
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        </div>
    <?php endif; ?>

    <div class="contract-detail-layout" style="max-width: 1180px; margin: 0 auto;">
        <div>
            <div class="profile-card contract-document-card" data-animate="fade-up">
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.25rem;">
                    <div>
                        <span class="section-kicker">Agreement</span>
                        <h2 style="margin-bottom: 0.35rem;">Rental Agreement</h2>
                        <p class="muted-text">Created on <?php echo date('F d, Y', strtotime($contract['created_at'])); ?></p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="generate_pdf">
                        <button type="submit" class="btn-outline"><i class="fas fa-file-pdf"></i> Download</button>
                    </form>
                </div>

                <div class="contract-document-body">
                    <p>This agreement records the rental terms for the listed room and confirms the responsibilities of both parties. Review each clause carefully before taking action.</p>

                    <h2>Parties</h2>
                    <div class="contract-parties-grid">
                        <div class="profile-mini-card">
                            <span class="profile-mini-label">Landlord</span>
                            <strong><?php echo htmlspecialchars($contract['landlord_name']); ?></strong>
                            <p class="muted-text"><?php echo htmlspecialchars($contract['landlord_phone']); ?><br><?php echo htmlspecialchars($contract['landlord_email']); ?></p>
                        </div>
                        <div class="profile-mini-card">
                            <span class="profile-mini-label">Tenant</span>
                            <strong><?php echo htmlspecialchars($contract['tenant_name']); ?></strong>
                            <p class="muted-text"><?php echo htmlspecialchars($contract['tenant_phone']); ?><br><?php echo htmlspecialchars($contract['tenant_email']); ?></p>
                        </div>
                    </div>

                    <h2>Property</h2>
                    <p>
                        <strong>Room:</strong> <?php echo htmlspecialchars($contract['room_title']); ?><br>
                        <strong>Location:</strong> <?php echo htmlspecialchars($contract['location']); ?><br>
                        <?php if (!empty($contract['address'])): ?>
                            <strong>Address:</strong> <?php echo htmlspecialchars($contract['address']); ?><br>
                        <?php endif; ?>
                    </p>

                    <h2>Term And Rent</h2>
                    <div class="contract-info-grid">
                        <div class="profile-meta-item"><i class="fas fa-calendar"></i><span>Start Date: <?php echo date('F d, Y', strtotime($contract['contract_start_date'])); ?></span></div>
                        <div class="profile-meta-item"><i class="fas fa-calendar-alt"></i><span>End Date: <?php echo !empty($contract['is_indefinite']) ? 'Indefinite' : (!empty($contract['contract_end_date']) ? date('F d, Y', strtotime($contract['contract_end_date'])) : 'Not specified'); ?></span></div>
                        <div class="profile-meta-item"><i class="fas fa-money-bill-wave"></i><span>Monthly Rent: Rs. <?php echo number_format((float) $contract['monthly_rent']); ?></span></div>
                        <div class="profile-meta-item"><i class="fas fa-wallet"></i><span>Advance: Rs. <?php echo number_format((float) $contract['advance_amount']); ?></span></div>
                        <div class="profile-meta-item"><i class="fas fa-shield-alt"></i><span>Deposit: Rs. <?php echo number_format((float) $contract['deposit_amount']); ?></span></div>
                        <div class="profile-meta-item"><i class="fas fa-clock"></i><span>Payment Day: <?php echo ordinal((int) $contract['payment_day']); ?> of each month</span></div>
                    </div>

                    <h2>Utilities</h2>
                    <?php if ($contract['utilities_included']): ?>
                        <p>All utilities are included in the monthly rent.</p>
                    <?php else: ?>
                        <div class="contract-info-grid">
                            <?php if (!empty($contract['electricity_charge'])): ?><div class="profile-meta-item"><i class="fas fa-bolt"></i><span>Electricity: <?php echo htmlspecialchars($contract['electricity_charge']); ?></span></div><?php endif; ?>
                            <?php if (!empty($contract['water_charge'])): ?><div class="profile-meta-item"><i class="fas fa-water"></i><span>Water: <?php echo htmlspecialchars($contract['water_charge']); ?></span></div><?php endif; ?>
                            <?php if (!empty($contract['internet_charge'])): ?><div class="profile-meta-item"><i class="fas fa-wifi"></i><span>Internet: <?php echo htmlspecialchars($contract['internet_charge']); ?></span></div><?php endif; ?>
                            <?php if (!empty($contract['maintenance_charge'])): ?><div class="profile-meta-item"><i class="fas fa-tools"></i><span>Maintenance: <?php echo htmlspecialchars($contract['maintenance_charge']); ?></span></div><?php endif; ?>
                            <?php if (!empty($contract['parking_charge'])): ?><div class="profile-meta-item"><i class="fas fa-car"></i><span>Parking: <?php echo htmlspecialchars($contract['parking_charge']); ?></span></div><?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <h2>House Rules</h2>
                    <?php if (!empty($contract['guest_policy'])): ?><p><strong>Guest Policy:</strong><br><?php echo nl2br(htmlspecialchars($contract['guest_policy'])); ?></p><?php endif; ?>
                    <?php if (!empty($contract['pet_policy'])): ?><p><strong>Pet Policy:</strong><br><?php echo nl2br(htmlspecialchars($contract['pet_policy'])); ?></p><?php endif; ?>
                    <?php if (!empty($contract['smoking_policy'])): ?><p><strong>Smoking Policy:</strong><br><?php echo nl2br(htmlspecialchars($contract['smoking_policy'])); ?></p><?php endif; ?>
                    <?php if (!empty($contract['noise_policy'])): ?><p><strong>Noise Policy:</strong><br><?php echo nl2br(htmlspecialchars($contract['noise_policy'])); ?></p><?php endif; ?>
                    <?php if (!empty($contract['additional_rules'])): ?><p><strong>Additional Rules:</strong><br><?php echo nl2br(htmlspecialchars($contract['additional_rules'])); ?></p><?php endif; ?>

                    <h2>Signatures</h2>
                    <div class="contract-signature-grid">
                        <div class="profile-mini-card">
                            <span class="profile-mini-label">Landlord Signature</span>
                            <strong><?php echo htmlspecialchars($contract['landlord_name']); ?></strong>
                            <?php if ($contract['landlord_signed_at']): ?>
                                <p class="listing-ok-text"><i class="fas fa-check-circle"></i> Signed on <?php echo date('F d, Y h:i A', strtotime($contract['landlord_signed_at'])); ?><br><small>IP: <?php echo htmlspecialchars($contract['landlord_ip']); ?></small></p>
                            <?php else: ?>
                                <p class="muted-text">Not signed yet</p>
                                <div class="contract-sign-placeholder">Signature</div>
                            <?php endif; ?>
                        </div>
                        <div class="profile-mini-card">
                            <span class="profile-mini-label">Tenant Signature</span>
                            <strong><?php echo htmlspecialchars($contract['tenant_name']); ?></strong>
                            <?php if ($contract['tenant_signed_at']): ?>
                                <p class="listing-ok-text"><i class="fas fa-check-circle"></i> Signed on <?php echo date('F d, Y h:i A', strtotime($contract['tenant_signed_at'])); ?><br><small>IP: <?php echo htmlspecialchars($contract['tenant_ip']); ?></small></p>
                            <?php else: ?>
                                <p class="muted-text">Not signed yet</p>
                                <div class="contract-sign-placeholder">Signature</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <aside class="profile-card contract-side-card sticky-sidebar-card" data-animate="fade-up">
                <span class="section-kicker">Status</span>
                <h3 style="margin-bottom: 0.5rem;">What happens next?</h3>
                <p class="muted-text">This panel keeps the contract actionable, not just readable.</p>
                <div class="contract-action-stack" style="margin-top: 1rem;">
                    <div class="profile-mini-card">
                        <span class="profile-mini-label">Current State</span>
                        <strong><?php echo ucwords(str_replace('_', ' ', $contract['status'])); ?></strong>
                    </div>
                    <div class="profile-mini-card">
                        <span class="profile-mini-label">Other Party</span>
                        <strong><?php echo htmlspecialchars($other_party); ?></strong>
                    </div>
                    <div class="profile-mini-card">
                        <span class="profile-mini-label">Review Eligibility</span>
                        <strong><?php echo hasUserReviewed($contract_id, $user_id) ? 'Submitted' : 'Available'; ?></strong>
                    </div>
                </div>
            </aside>

            <aside class="profile-card contract-side-card" data-animate="fade-up">
                <h3 style="margin-bottom: 0.85rem;">Actions</h3>

                <?php if (in_array($contract['status'], ['draft', 'pending_tenant', 'pending_landlord'], true)): ?>
                    <?php if (($is_tenant && empty($contract['tenant_signed_at'])) || ($is_landlord && empty($contract['landlord_signed_at']))): ?>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to sign this contract?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="sign">
                            <label style="display: flex; align-items: flex-start; gap: 0.65rem; margin-bottom: 1rem;">
                                <input type="checkbox" required style="margin-top: 0.2rem;">
                                <span>I have reviewed the terms and I agree to sign this agreement electronically.</span>
                            </label>
                            <button type="submit" class="btn-primary" style="width: 100%;"><i class="fas fa-signature"></i> Sign Contract</button>
                        </form>
                    <?php else: ?>
                        <p class="muted-text">Your signature is already recorded. Waiting for the other party to complete the agreement.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="muted-text">No signature action is needed right now.</p>
                <?php endif; ?>

                <?php if ($contract['status'] === 'active' && ($is_landlord || $is_tenant)): ?>
                    <div class="contract-terminate-box">
                        <button type="button" class="btn-outline" style="width: 100%;" onclick="document.getElementById('terminateForm').style.display='block';">Terminate Contract</button>
                        <div id="terminateForm" style="display: none; margin-top: 1rem;">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="terminate">
                                <label for="termination_reason">Reason for termination</label>
                                <textarea id="termination_reason" name="termination_reason" rows="4" required style="width: 100%; padding: 0.9rem; border: 2px solid var(--border); border-radius: 14px; margin: 0.5rem 0 1rem;"></textarea>
                                <button type="submit" class="btn-primary" style="width: 100%;" onclick="return confirm('Are you sure you want to terminate this contract?');">Confirm Termination</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (in_array($contract['status'], ['active', 'terminated', 'expired'], true) && !hasUserReviewed($contract_id, $user_id)): ?>
                    <div class="contract-sign-state">
                        <h4 style="margin-bottom: 0.5rem;">Leave a review</h4>
                        <p class="muted-text" style="margin-bottom: 1rem;">Share your experience with <?php echo htmlspecialchars($other_party); ?>.</p>
                        <a href="<?php echo SITE_URL; ?>/pages/create-review.php?contract_id=<?php echo (int) $contract_id; ?>" class="btn-primary" style="width: 100%; text-align: center;">Write Review</a>
                    </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
