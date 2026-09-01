<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$message = '';
$type = '';
$show_form = false;
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $message = 'No reset token provided';
    $type = 'error';
} else {
    $stmt = $conn->prepare('SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()');
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if ($user) {
        $show_form = true;
    } else {
        $message = 'Invalid or expired reset token';
        $type = 'error';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $show_form) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $token = $_POST['token'] ?? '';

    if (empty($password) || empty($confirm_password)) {
        $message = 'Please enter a new password';
        $type = 'error';
    } elseif (strlen($password) < 6) {
        $message = 'Password must be at least 6 characters';
        $type = 'error';
    } elseif ($password !== $confirm_password) {
        $message = 'Passwords do not match';
        $type = 'error';
    } else {
        $result = resetPassword($token, $password);
        if ($result['success']) {
            $message = $result['message'];
            $type = 'success';
            $show_form = false;
        } else {
            $message = $result['message'];
            $type = 'error';
            $show_form = false;
        }
    }
}

$page_title = 'Reset Password';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="auth-container auth-page-shell">
    <div class="auth-card auth-card-narrow" data-animate="fade-up">
        <div class="auth-header auth-hero-copy">
            <h1>Reset password</h1>
            <p><?php echo $show_form ? 'Choose a new password and confirm it below.' : 'We will help you safely restart the reset flow.'; ?></p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $type; ?>">
                <i class="fas fa-<?php echo $type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($show_form): ?>
            <div class="auth-side-note">
                <span class="auth-section-label"><i class="fas fa-lock"></i> Password Reset</span>
                <p class="muted-text">Use a password you have not used elsewhere and make sure it is easy for you to remember but hard to guess.</p>
            </div>
            <form method="POST" action="" class="auth-form" id="resetForm">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" placeholder="Min. 6 characters" required autofocus>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter new password" required>
                    <div class="password-match" id="passwordMatch"></div>
                </div>
                <button type="submit" class="btn-primary btn-block">Reset Password</button>
            </form>
            <div class="auth-note-row">
                <a href="login.php" class="btn-outline">Back to Login</a>
            </div>
        <?php else: ?>
            <div class="auth-note-row">
                <a href="forgot-password.php" class="btn-outline">Request New Reset Link</a>
                <a href="login.php" class="btn-primary">Back to Login</a>
            </div>
        <?php endif; ?>
    </div>
</div>
<script>
const passwordInput = document.getElementById('password');
const confirmInput = document.getElementById('confirm_password');
const matchDiv = document.getElementById('passwordMatch');

function checkMatch() {
    if (confirmInput && confirmInput.value) {
        if (confirmInput.value === passwordInput.value) {
            matchDiv.className = 'password-match match';
            matchDiv.innerHTML = '<i class="fas fa-check"></i> Passwords match';
            confirmInput.classList.remove('error');
        } else {
            matchDiv.className = 'password-match error';
            matchDiv.innerHTML = '<i class="fas fa-times"></i> Passwords do not match';
            confirmInput.classList.add('error');
        }
    } else if (matchDiv) {
        matchDiv.innerHTML = '';
    }
}

if (passwordInput && confirmInput) {
    passwordInput.addEventListener('input', checkMatch);
    confirmInput.addEventListener('input', checkMatch);
    document.getElementById('resetForm')?.addEventListener('submit', function (e) {
        if (passwordInput.value !== confirmInput.value) {
            e.preventDefault();
            alert('Passwords do not match!');
        }
    });
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
