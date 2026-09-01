<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    redirect(SITE_URL . '/auth/login.php');
}

$user_id = getCurrentUserId();
$review_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$review = getReviewDetails($review_id);
if (!$review) {
    redirect(SITE_URL . '/pages/dashboard.php');
}

$is_reviewer = ((int) $user_id === (int) $review['reviewer_id']);
$is_reviewee = ((int) $user_id === (int) $review['reviewee_id']);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $error = 'Invalid security token';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'helpful') {
            $result = markReviewHelpful($review_id, $user_id);
            if ($result['success']) {
                redirect(SITE_URL . '/pages/review-detail.php?id=' . $review_id);
            }
            $error = $result['message'] ?? 'Could not update vote';
        } elseif ($action === 'report') {
            $result = reportReview($review_id, $user_id, $_POST['report_reason'] ?? '', $_POST['report_description'] ?? '');
            if ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
        } elseif ($action === 'respond' && $is_reviewee) {
            $result = respondToReview($review_id, $user_id, $_POST['response'] ?? '');
            if ($result['success']) {
                redirect(SITE_URL . '/pages/review-detail.php?id=' . $review_id);
            }
            $error = $result['message'];
        }
        $review = getReviewDetails($review_id);
    }
}

$page_title = 'Review Details';
require_once __DIR__ . '/../includes/header.php';
$csrf_token = generateCSRFToken();
?>
<section class="dashboard-shell">
    <div class="dashboard-card" style="max-width: 860px;">
        <?php if ($error): ?><div class="alert alert-error" style="margin-bottom:1rem;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success" style="margin-bottom:1rem;"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <div class="profile-card" style="padding:1.5rem;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;">
                <div style="display:flex;align-items:center;gap:1rem;">
                    <img src="<?php echo getUserAvatarUrl($review['reviewer_avatar'] ?? null); ?>" alt="Reviewer" style="width:60px;height:60px;border-radius:50%;object-fit:cover;">
                    <div>
                        <h2 style="margin-bottom:0.25rem;"><?php echo !empty($review['is_anonymous']) ? 'Anonymous' : htmlspecialchars($review['reviewer_name']); ?></h2>
                        <span class="muted-text">Trust Score: <?php echo (int) ($review['reviewer_trust'] ?? 0); ?></span>
                    </div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:1.4rem;color:var(--primary);margin-bottom:0.25rem;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php if ($i <= round((float) $review['rating_overall'])): ?><i class="fas fa-star"></i><?php else: ?><i class="far fa-star"></i><?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    <span class="muted-text"><?php echo date('F d, Y', strtotime($review['created_at'])); ?></span>
                </div>
            </div>

            <div class="profile-mini-card" style="padding:1rem;margin-bottom:1.5rem;"><p>Review for contract #<?php echo htmlspecialchars($review['contract_number']); ?> - <?php echo htmlspecialchars($review['room_title']); ?></p></div>
            <div style="display:grid;gap:0.8rem;margin-bottom:1.5rem;">
                <?php $breakdown = ['Accuracy' => 'rating_accuracy', 'Communication' => 'rating_communication', 'Cleanliness' => 'rating_cleanliness', 'Value for Money' => 'rating_value']; ?>
                <?php foreach ($breakdown as $label => $field): ?>
                    <div style="display:flex;align-items:center;gap:1rem;"><span style="width:140px;"><?php echo $label; ?></span><div style="flex:1;height:8px;background:var(--border);border-radius:999px;"><div style="width:<?php echo (((int) $review[$field]) / 5) * 100; ?>%;height:100%;background:var(--primary);border-radius:999px;"></div></div><span><?php echo (int) $review[$field]; ?>/5</span></div>
                <?php endforeach; ?>
            </div>
            <div style="background:rgba(15,79,76,0.05);padding:1.25rem;border-radius:14px;line-height:1.8;margin-bottom:1.25rem;"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></div>
            <?php if (!empty($review['is_recommended'])): ?><p class="ok-text" style="margin-bottom:1.25rem;"><i class="fas fa-thumbs-up"></i> Reviewer recommends this person.</p><?php endif; ?>
            <?php if (!empty($review['landlord_response'])): ?>
                <div style="padding-left:1rem;border-left:3px solid var(--primary);margin-bottom:1.25rem;"><h4 style="margin-bottom:0.5rem;">Response from <?php echo htmlspecialchars($review['reviewee_name']); ?></h4><p class="muted-text"><?php echo nl2br(htmlspecialchars($review['landlord_response'])); ?></p></div>
            <?php endif; ?>
            <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;align-items:center;padding-top:1rem;border-top:1px solid var(--border);">
                <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
                    <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>"><input type="hidden" name="action" value="helpful"><button type="submit" class="btn-outline">Helpful (<?php echo (int) $review['is_helpful_count']; ?>)</button></form>
                    <?php if (!$is_reviewer): ?><button type="button" class="btn-outline" onclick="document.getElementById('reportForm').style.display='block';">Report</button><?php endif; ?>
                </div>
                <?php if ($is_reviewee && empty($review['landlord_response'])): ?><button type="button" class="btn-primary" onclick="document.getElementById('responseForm').style.display='block';">Respond</button><?php endif; ?>
            </div>
            <div id="reportForm" style="display:none;margin-top:1.25rem;"><form method="POST"><input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>"><input type="hidden" name="action" value="report"><label for="report_reason">Reason</label><select id="report_reason" name="report_reason" required style="width:100%;padding:0.85rem;border:2px solid var(--border);border-radius:12px;margin:0.5rem 0 1rem;"><option value="">Select reason</option><option value="inappropriate">Inappropriate content</option><option value="fake">Fake review</option><option value="harassment">Harassment</option><option value="conflict">Conflict of interest</option><option value="other">Other</option></select><label for="report_description">Additional details</label><textarea id="report_description" name="report_description" rows="3" style="width:100%;padding:0.85rem;border:2px solid var(--border);border-radius:12px;margin:0.5rem 0 1rem;"></textarea><button type="submit" class="btn-primary">Submit Report</button></form></div>
            <div id="responseForm" style="display:none;margin-top:1.25rem;"><form method="POST"><input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>"><input type="hidden" name="action" value="respond"><label for="response">Your Response</label><textarea id="response" name="response" rows="4" required style="width:100%;padding:0.85rem;border:2px solid var(--border);border-radius:12px;margin:0.5rem 0 1rem;"></textarea><button type="submit" class="btn-primary">Post Response</button></form></div>
        </div>
        <div style="text-align:center;margin-top:1rem;"><a href="<?php echo SITE_URL; ?>/pages/contract-detail.php?id=<?php echo (int) $review['contract_id']; ?>" class="btn-outline btn-small">Back to Contract</a></div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
