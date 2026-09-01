<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$message = '';
$type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    if (empty($email)) {
        $message = 'Please enter your email address';
        $type = 'error';
    } else {
        requestPasswordReset($email);
        $message = 'If your email exists in our system, you will receive a password reset link.';
        $type = 'success';
    }
}

$page_title = 'Forgot Password';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="auth-container auth-page-shell">
    <div class="auth-card auth-card-narrow" data-animate="fade-up">
        <div class="auth-header auth-hero-copy">
            <h1>Forgot your password?</h1>
            <p>Enter your email and we will guide you back into your account.</p>
        </div>

        <div class="auth-side-note">
            <span class="auth-section-label"><i class="fas fa-key"></i> Reset Flow</span>
            <p class="muted-text">For security, we do not reveal whether an email exists. If the address is valid, you will receive a reset link.</p>
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
                <input type="email" id="email" name="email" placeholder="your@email.com" required autofocus>
            </div>
            <button type="submit" class="btn-primary btn-block">Send Reset Link</button>
        </form>

        <div class="auth-note-row">
            <a href="login.php" class="btn-outline">Back to Login</a>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
