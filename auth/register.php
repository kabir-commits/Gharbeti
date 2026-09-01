<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    redirect(SITE_URL . '/index.php');
}

$error = '';
$success = '';
$role = $_GET['role'] ?? 'tenant';
$verification_link = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $error = 'Invalid security token';
    } else {
        $email = sanitize($_POST['email']);
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $full_name = sanitize($_POST['full_name']);
        $phone = sanitize($_POST['phone'] ?? '');
        $role = sanitize($_POST['role'] ?? 'tenant');
        $terms = isset($_POST['terms']);

        if (empty($email) || empty($password) || empty($full_name)) {
            $error = 'All fields are required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match';
        } elseif (!$terms) {
            $error = 'You must agree to the Terms of Service';
        } else {
            $result = registerUser($email, $password, $role, $full_name, $phone);
            if ($result['success']) {
                $success = $result['message'];
                $_SESSION['registered_email'] = $email;
                if (!defined('IS_PRODUCTION') || !IS_PRODUCTION) {
                    $verification_link = $_SESSION['verification_link'] ?? '';
                }
            } else {
                $error = $result['message'];
            }
        }
    }
}

$csrf_token = generateCSRFToken();
$page_title = 'Register';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="auth-container auth-page-shell">
    <div class="auth-card" data-animate="fade-up">
        <div class="auth-header auth-hero-copy">
            <h1>Create your account</h1>
            <p>Join Gharbeti to discover verified rooms, message directly, and build a trusted rental identity.</p>
        </div>

        <div class="auth-feature-grid" data-animate="fade-up">
            <div class="auth-feature-card">
                <i class="fas fa-user-check"></i>
                <h3>Trust-first Profiles</h3>
                <p>Verification, reviews, and contracts all strengthen your reputation over time.</p>
            </div>
            <div class="auth-feature-card">
                <i class="fas fa-file-signature"></i>
                <h3>Digital Workflow</h3>
                <p>From discovery to signed agreements, your account stays connected across the whole journey.</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success auth-success-block">
                <i class="fas fa-check-circle"></i>
                <div>
                    <div><?php echo $success; ?></div>
                    <?php if (!empty($verification_link)): ?>
                        <div class="demo-link-box">
                            <strong>Development verification link:</strong><br>
                            <a href="<?php echo htmlspecialchars($verification_link); ?>"><?php echo htmlspecialchars($verification_link); ?></a>
                        </div>
                    <?php endif; ?>
                    <div class="auth-action-row">
                        <a href="login.php" class="btn-primary">Proceed to Login</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="auth-tabs">
                <a href="?role=tenant" class="tab <?php echo $role === 'tenant' ? 'active' : ''; ?>"><i class="fas fa-user"></i> I'm a Tenant</a>
                <a href="?role=landlord" class="tab <?php echo $role === 'landlord' ? 'active' : ''; ?>"><i class="fas fa-home"></i> I'm a Landlord</a>
            </div>

            <form method="POST" action="" class="auth-form" id="registerForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="role" value="<?php echo $role; ?>">

                <div class="form-group">
                    <label for="full_name"><i class="fas fa-user"></i> Full Name</label>
                    <input type="text" id="full_name" name="full_name" placeholder="Enter your full name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" id="email" name="email" placeholder="your@email.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="phone"><i class="fas fa-phone"></i> Phone Number (Optional)</label>
                    <input type="tel" id="phone" name="phone" placeholder="98XXXXXXXX" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <input type="password" id="password" name="password" placeholder="Min. 6 characters" required>
                    <div class="password-strength" id="passwordStrength"><div class="strength-bar"></div></div>
                    <ul class="password-requirements" id="passwordRequirements">
                        <li class="req-length">At least 6 characters</li>
                        <li class="req-number">Contains a number</li>
                        <li class="req-letter">Contains a letter</li>
                    </ul>
                </div>
                <div class="form-group">
                    <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter password" required>
                    <div class="password-match" id="passwordMatch"></div>
                </div>

                <?php if ($role === 'landlord'): ?>
                    <div class="auth-side-note">
                        <span class="auth-section-label"><i class="fas fa-home"></i> Landlord note</span>
                        <p class="muted-text">After registering, you will be able to verify your property and strengthen listing trust before publishing rooms.</p>
                    </div>
                <?php endif; ?>

                <div class="form-group terms-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="terms" required>
                        <span>I agree to the <a href="<?php echo SITE_URL; ?>/pages/terms.php" target="_blank">Terms of Service</a> and <a href="<?php echo SITE_URL; ?>/pages/privacy.php" target="_blank">Privacy Policy</a></span>
                    </label>
                </div>

                <button type="submit" class="btn-primary btn-block"><i class="fas fa-user-plus"></i> Create Account</button>
            </form>

            <div class="auth-footer">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        <?php endif; ?>
    </div>
</div>
<script>
const passwordInput = document.getElementById('password');
const strengthBar = document.querySelector('.strength-bar');
const requirements = {
    length: document.querySelector('.req-length'),
    number: document.querySelector('.req-number'),
    letter: document.querySelector('.req-letter')
};
const confirmInput = document.getElementById('confirm_password');
const matchDiv = document.getElementById('passwordMatch');

if (passwordInput && strengthBar) {
    passwordInput.addEventListener('input', function () {
        const password = this.value;
        let strength = 0;
        if (password.length >= 6) {
            strength += 25;
            requirements.length.classList.add('valid');
        } else {
            requirements.length.classList.remove('valid');
        }
        if (/\d/.test(password)) {
            strength += 25;
            requirements.number.classList.add('valid');
        } else {
            requirements.number.classList.remove('valid');
        }
        if (/[a-zA-Z]/.test(password)) {
            strength += 25;
            requirements.letter.classList.add('valid');
        } else {
            requirements.letter.classList.remove('valid');
        }
        if (/[!@#$%^&*]/.test(password)) {
            strength += 25;
        }
        if (strength <= 25) {
            strengthBar.className = 'strength-bar weak';
        } else if (strength <= 50) {
            strengthBar.className = 'strength-bar medium';
        } else if (strength <= 75) {
            strengthBar.className = 'strength-bar strong';
        } else {
            strengthBar.className = 'strength-bar very-strong';
        }
    });
}

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
}

const registerForm = document.getElementById('registerForm');
if (registerForm && passwordInput && confirmInput) {
    registerForm.addEventListener('submit', function (e) {
        if (passwordInput.value !== confirmInput.value) {
            e.preventDefault();
            alert('Passwords do not match!');
        }
    });
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
