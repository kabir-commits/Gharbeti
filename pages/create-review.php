<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    redirect(SITE_URL . '/auth/login.php');
}

$user_id = getCurrentUserId();
$contract_id = isset($_GET['contract_id']) ? (int) $_GET['contract_id'] : 0;
$contract = getContractDetails($contract_id);
if (!$contract) {
    redirect(SITE_URL . '/pages/contracts.php');
}
if ($user_id != $contract['tenant_id'] && $user_id != $contract['landlord_id']) {
    redirect(SITE_URL . '/pages/contracts.php');
}
if (!in_array($contract['status'], ['active', 'terminated', 'expired'], true)) {
    $_SESSION['error'] = 'You can only review a signed contract';
    redirect(SITE_URL . '/pages/contract-detail.php?id=' . $contract_id);
}
if (hasUserReviewed($contract_id, $user_id)) {
    $_SESSION['success'] = 'You have already reviewed this contract.';
    redirect(SITE_URL . '/pages/contract-detail.php?id=' . $contract_id);
}

$reviewee_id = ((int) $user_id === (int) $contract['tenant_id']) ? (int) $contract['landlord_id'] : (int) $contract['tenant_id'];
$reviewee_name = ((int) $user_id === (int) $contract['tenant_id']) ? $contract['landlord_name'] : $contract['tenant_name'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $error = 'Invalid security token';
    } else {
        $result = createReview([
            'contract_id' => $contract_id,
            'reviewer_id' => $user_id,
            'reviewee_id' => $reviewee_id,
            'rating_accuracy' => $_POST['rating_accuracy'] ?? '',
            'rating_communication' => $_POST['rating_communication'] ?? '',
            'rating_cleanliness' => $_POST['rating_cleanliness'] ?? '',
            'rating_value' => $_POST['rating_value'] ?? '',
            'review_text' => $_POST['review_text'] ?? '',
            'is_anonymous' => isset($_POST['is_anonymous']),
            'is_recommended' => isset($_POST['is_recommended']),
        ]);
        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
            redirect(SITE_URL . '/pages/contract-detail.php?id=' . $contract_id);
        }
        $error = $result['message'];
    }
}

$page_title = 'Write a Review';
require_once __DIR__ . '/../includes/header.php';
$csrf_token = generateCSRFToken();
?>
<section class="dashboard-shell">
    <div class="dashboard-card" style="max-width: 760px;">
        <div class="section-heading compact" style="margin-bottom:1.5rem;"><div><span class="section-kicker">Reviews</span><h1>Write a Review</h1><p class="muted-text">Share your experience with <?php echo htmlspecialchars($reviewee_name); ?> after renting <?php echo htmlspecialchars($contract['room_title']); ?>.</p></div></div>
        <?php if ($error): ?><div class="alert alert-error" style="margin-bottom:1rem;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="POST" id="reviewForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <div class="profile-card" style="padding:1.25rem;margin-bottom:1.25rem;">
                <h3 style="margin-bottom:1rem;">Rate Your Experience</h3>
                <div style="display:grid;gap:1.2rem;">
                    <?php $categories = ['rating_accuracy' => 'Accuracy', 'rating_communication' => 'Communication', 'rating_cleanliness' => 'Cleanliness', 'rating_value' => 'Value for Money']; ?>
                    <?php foreach ($categories as $field => $label): ?>
                        <div>
                            <label style="display:block;margin-bottom:0.5rem;"><?php echo $label; ?></label>
                            <div class="star-rating" data-rating="<?php echo $field; ?>">
                                <?php for ($i = 1; $i <= 5; $i++): ?><i class="far fa-star" data-value="<?php echo $i; ?>" onclick="setRating('<?php echo $field; ?>', <?php echo $i; ?>)"></i><?php endfor; ?>
                            </div>
                            <input type="hidden" name="<?php echo $field; ?>" id="<?php echo $field; ?>" required>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="profile-card" style="padding:1.25rem;margin-bottom:1.25rem;">
                <label for="review_text">Written Review *</label>
                <textarea id="review_text" name="review_text" rows="6" required style="width:100%;padding:1rem;border:2px solid var(--border);border-radius:12px;margin-top:0.5rem;" placeholder="Tell others about your experience..."></textarea>
                <div style="display:flex;gap:1.5rem;flex-wrap:wrap;margin-top:1rem;">
                    <label style="display:flex;align-items:center;gap:0.5rem;"><input type="checkbox" name="is_anonymous"> <span>Post anonymously</span></label>
                    <label style="display:flex;align-items:center;gap:0.5rem;"><input type="checkbox" name="is_recommended" checked> <span>I would recommend this person</span></label>
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:0.8rem;flex-wrap:wrap;">
                <a href="<?php echo SITE_URL; ?>/pages/contract-detail.php?id=<?php echo (int) $contract_id; ?>" class="btn-outline">Cancel</a>
                <button type="submit" class="btn-primary">Submit Review</button>
            </div>
        </form>
    </div>
</section>
<style>
.star-rating{display:flex;gap:0.45rem;font-size:1.5rem;}
.star-rating i{cursor:pointer;color:var(--text-light);transition:var(--transition);}
.star-rating i:hover,.star-rating i.active{color:var(--primary);transform:scale(1.1);}
</style>
<script>
function setRating(fieldName, value) {
    document.getElementById(fieldName).value = value;
    document.querySelectorAll(`[data-rating="${fieldName}"] i`).forEach((star, index) => {
        if (index < value) {
            star.classList.remove('far');
            star.classList.add('fas', 'active');
        } else {
            star.classList.remove('fas', 'active');
            star.classList.add('far');
        }
    });
}

document.getElementById('reviewForm').addEventListener('submit', function (event) {
    const required = ['rating_accuracy', 'rating_communication', 'rating_cleanliness', 'rating_value'];
    const missing = required.filter((field) => !document.getElementById(field).value);
    if (missing.length > 0) {
        event.preventDefault();
        alert('Please rate all categories before submitting.');
    }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
