<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$message = '';
$type = '';

if (isset($_GET['token'])) {
    $token = sanitize($_GET['token']);
    $result = verifyEmail($token);
    if ($result['success']) {
        if (isLoggedIn()) {
            redirect(SITE_URL . '/pages/dashboard.php');
        }
        redirect(SITE_URL . '/auth/login.php?verified=1');
    } else {
        $message = $result['message'];
        $type = 'error';
    }
} else {
    $message = 'No verification token provided';
    $type = 'error';
}

$page_title = 'Email Verification';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="auth-container auth-page-shell">
    <div class="auth-card auth-card-narrow auth-card-centered" data-animate="fade-up">
        <div class="auth-header auth-hero-copy">
            <h1>Email verification</h1>
            <p>We use verification to keep the platform more trustworthy for both tenants and landlords.</p>
        </div>

        <div class="alert alert-<?php echo $type; ?> auth-centered-alert">
            <i class="fas fa-<?php echo $type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>

        <?php if ($type === 'error'): ?>
            <div class="auth-side-note">
                <span class="auth-section-label"><i class="fas fa-life-ring"></i> Link Problem</span>
                <p class="muted-text">The verification link may have expired or already been used. You can request a fresh one below.</p>
            </div>
            <form method="POST" action="resend-verification.php" class="auth-form compact-form">
                <input type="email" name="email" placeholder="Enter your email" required>
                <button type="submit" class="btn-primary btn-block">Resend Verification</button>
            </form>
        <?php else: ?>
            <div class="auth-note-row" style="justify-content: center;">
                <a href="login.php" class="btn-primary">Go to Login</a>
            </div>
        <?php endif; ?>

        <div class="auth-note-row" style="justify-content: center;">
            <a href="<?php echo SITE_URL; ?>/index.php" class="btn-outline">Back to Home</a>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
