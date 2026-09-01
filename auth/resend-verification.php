<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$message = '';
$type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['email'])) {
    $email = sanitize($_POST['email'] ?? $_GET['email'] ?? '');
    if (empty($email)) {
        $message = 'Please provide an email address';
        $type = 'error';
    } else {
        $result = resendVerification($email);
        if ($result['success']) {
            $message = $result['message'];
            $type = 'success';
        } else {
            $message = $result['message'];
            $type = 'error';
        }
    }
}

$page_title = 'Resend Verification';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="auth-container auth-page-shell">
    <div class="auth-card auth-card-narrow" data-animate="fade-up">
        <div class="auth-header auth-hero-copy">
            <h1>Resend verification</h1>
            <p>Need a fresh email verification link? Enter your address and we will send a new one.</p>
        </div>

        <div class="auth-side-note">
            <span class="auth-section-label"><i class="fas fa-envelope-open-text"></i> Verification Help</span>
            <p class="muted-text">This is useful if your previous link expired or the original email never arrived.</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $type; ?>">
                <i class="fas fa-<?php echo $type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="auth-form">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="your@email.com" value="<?php echo htmlspecialchars($_GET['email'] ?? ''); ?>" required>
            </div>
            <button type="submit" class="btn-primary btn-block">Send Verification Email</button>
        </form>

        <div class="auth-note-row">
            <a href="login.php" class="btn-outline">Back to Login</a>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
