<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    redirect(SITE_URL . '/index.php');
}

$error = '';
$success = '';
$email = $_GET['email'] ?? $_POST['email'] ?? '';

if (isset($_SESSION['registered_email'])) {
    $success = 'Registration successful! Please check your email to verify your account.';
    $email = $_SESSION['registered_email'];
    unset($_SESSION['registered_email']);
}
if (isset($_GET['timeout'])) {
    $error = 'Your session timed out. Please login again.';
}
if (isset($_GET['verified'])) {
    $success = 'Email verified successfully! You can now login.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $error = 'Invalid security token';
    } else {
        $email = sanitize($_POST['email']);
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password';
        } else {
            $result = loginUser($email, $password, $remember);
            if ($result['success']) {
                if ($result['role'] === 'admin') {
                    redirect(SITE_URL . '/pages/admin/dashboard.php');
                }
                redirect(SITE_URL . '/pages/dashboard.php');
            } else {
                $error = $result['message'];
                if (!empty($result['needs_verification'])) {
                    $_SESSION['verify_email'] = $email;
                }
            }
        }
    }
}

$csrf_token = generateCSRFToken();
$page_title = 'Login';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="auth-container auth-page-shell">
    <div class="auth-card" data-animate="fade-up">
        <div class="auth-header auth-hero-copy">
            <h1>Welcome back</h1>
            <p>Sign in to continue your room search, messages, contracts, and trust-building activity.</p>
        </div>

        <div class="auth-feature-grid" data-animate="fade-up">
            <div class="auth-feature-card">
                <i class="fas fa-shield-alt"></i>
                <h3>Trusted Access</h3>
                <p>All your verified identity, contract, and messaging activity lives behind one secure login.</p>
            </div>
            <div class="auth-feature-card">
                <i class="fas fa-comments"></i>
                <h3>Conversations</h3>
                <p>Jump straight back into landlord or tenant chats without losing context.</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error auth-success-block">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <div><?php echo $error; ?></div>
                    <?php if (isset($_SESSION['verify_email'])): ?>
                        <div class="auth-action-row">
                            <a href="resend-verification.php?email=<?php echo urlencode($_SESSION['verify_email']); ?>" class="btn-outline btn-small">Resend Verification Email</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                <input type="email" id="email" name="email" placeholder="your@email.com" value="<?php echo htmlspecialchars($email); ?>" required autofocus>
            </div>
            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>
            <div class="auth-inline-row">
                <label class="checkbox-label compact-checkbox"><input type="checkbox" name="remember" value="1"><span>Remember me</span></label>
                <a href="forgot-password.php" class="auth-link-inline">Forgot password?</a>
            </div>
            <button type="submit" class="btn-primary btn-block"><i class="fas fa-sign-in-alt"></i> Login</button>
        </form>

        <div class="auth-footer">
            <p>Don't have an account? <a href="register.php">Register here</a></p>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
