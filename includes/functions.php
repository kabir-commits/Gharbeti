<?php
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim((string) $data)));
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? 0;
}

function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? 'guest';
}

function getSiteLogoUrl() {
    $candidates = ['logo.svg', 'logo.png', 'logo.webp', 'logo.jpg', 'logo.jpeg'];

    foreach ($candidates as $file) {
        $absolutePath = __DIR__ . '/../assets/images/' . $file;
        if (file_exists($absolutePath)) {
            return SITE_URL . '/assets/images/' . $file;
        }
    }

    return null;
}

function getUserAvatarUrl($avatar = null) {
    $avatar = $avatar ?: ($_SESSION['user_avatar'] ?? 'default-avatar.svg');

    if (filter_var($avatar, FILTER_VALIDATE_URL)) {
        return $avatar;
    }

    $uploadCandidate = __DIR__ . '/../assets/uploads/avatars/' . $avatar;
    if ($avatar && file_exists($uploadCandidate)) {
        return SITE_URL . '/assets/uploads/avatars/' . $avatar;
    }

    $imageCandidate = __DIR__ . '/../assets/images/' . $avatar;
    if ($avatar && file_exists($imageCandidate)) {
        return SITE_URL . '/assets/images/' . $avatar;
    }

    return SITE_URL . '/assets/images/default-avatar.svg';
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string) $token)) {
        die('Invalid CSRF token');
    }
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 2592000) return floor($diff / 86400) . ' days ago';
    return date('M d, Y', $time);
}

function getVerificationBadge($level) {
    $badges = [
        1 => '<span class="badge badge-email"><i class="fas fa-envelope"></i> Email Verified</span>',
        2 => '<span class="badge badge-phone"><i class="fas fa-phone"></i> Phone Verified</span>',
        3 => '<span class="badge badge-id"><i class="fas fa-id-card"></i> ID Verified</span>',
        4 => '<span class="badge badge-gold"><i class="fas fa-check-circle"></i> Fully Verified</span>'
    ];
    return $badges[$level] ?? '';
}

function getTrustScoreColor($score) {
    if ($score >= 80) return 'trust-high';
    if ($score >= 50) return 'trust-medium';
    return 'trust-low';
}

/**
 * AUTHENTICATION FUNCTIONS
 */
function registerUser($email, $password, $role, $full_name, $phone = '') {
    global $conn;

    $check = $conn->prepare('SELECT id FROM users WHERE email = ?');
    $check->execute([$email]);
    if ($check->fetch()) {
        return ['success' => false, 'message' => 'Email already registered'];
    }

    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    $verification_token = bin2hex(random_bytes(32));
    $verification_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare(
            'INSERT INTO users (email, password, role, email_verification_token, email_verification_expires, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$email, $hashed_password, $role, $verification_token, $verification_expires]);

        $user_id = $conn->lastInsertId();

        $stmt = $conn->prepare(
            'INSERT INTO profiles (user_id, full_name, phone, joined_date, trust_score, avatar)
             VALUES (?, ?, ?, CURDATE(), 30, ?)'
        );
        $stmt->execute([$user_id, $full_name, $phone, 'default-avatar.svg']);

        logActivity($user_id, 'REGISTER', "User registered as $role");

        $conn->commit();

        sendVerificationEmail($email, $verification_token);

        return [
            'success' => true,
            'message' => 'Registration successful! Please check your email to verify your account.',
            'user_id' => $user_id
        ];
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
    }
}

function loginUser($email, $password, $remember = false) {
    global $conn;

    $stmt = $conn->prepare(
        'SELECT u.*, p.full_name, p.avatar, p.trust_score
         FROM users u
         LEFT JOIN profiles p ON u.id = p.user_id
         WHERE u.email = ? AND u.is_active = 1'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'message' => 'Email not found or account inactive'];
    }

    if (!password_verify($password, $user['password'])) {
        logActivity($user['id'], 'LOGIN_FAILED', 'Invalid password');
        return ['success' => false, 'message' => 'Invalid password'];
    }

    if (!(bool) $user['email_verified']) {
        return ['success' => false, 'message' => 'Please verify your email first', 'needs_verification' => true];
    }

    $update = $conn->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
    $update->execute([$user['id']]);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_avatar'] = $user['avatar'] ?: 'default-avatar.svg';
    $_SESSION['trust_score'] = $user['trust_score'] ?? 30;
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();

    if ($remember) {
        createRememberMeToken($user['id']);
    }

    logActivity($user['id'], 'LOGIN', 'User logged in');

    return ['success' => true, 'message' => 'Login successful', 'role' => $user['role']];
}

function createRememberMeToken($user_id) {
    global $conn;

    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

    $delete = $conn->prepare('DELETE FROM user_sessions WHERE user_id = ?');
    $delete->execute([$user_id]);

    $insert = $conn->prepare('INSERT INTO user_sessions (user_id, session_token, expires_at) VALUES (?, ?, ?)');
    $insert->execute([$user_id, $token, $expires]);

    setcookie('remember_token', $token, time() + (86400 * 30), '/', '', false, true);
    setcookie('remember_user', (string) $user_id, time() + (86400 * 30), '/', '', false, true);
}

function checkRememberMe() {
    global $conn;

    if (isset($_COOKIE['remember_token'], $_COOKIE['remember_user']) && !isLoggedIn()) {
        $token = $_COOKIE['remember_token'];
        $user_id = $_COOKIE['remember_user'];

        $stmt = $conn->prepare(
            'SELECT s.*, u.email, u.role, p.full_name, p.avatar, p.trust_score
             FROM user_sessions s
             JOIN users u ON s.user_id = u.id
             LEFT JOIN profiles p ON u.id = p.user_id
             WHERE s.user_id = ? AND s.session_token = ? AND s.expires_at > NOW()'
        );
        $stmt->execute([$user_id, $token]);
        $session = $stmt->fetch();

        if ($session) {
            $_SESSION['user_id'] = $session['user_id'];
            $_SESSION['user_email'] = $session['email'];
            $_SESSION['user_role'] = $session['role'];
            $_SESSION['user_name'] = $session['full_name'];
            $_SESSION['user_avatar'] = $session['avatar'] ?: 'default-avatar.svg';
            $_SESSION['trust_score'] = $session['trust_score'] ?? 30;
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();

            logActivity($session['user_id'], 'AUTO_LOGIN', 'Logged in via remember me');
            return true;
        }
    }

    return false;
}

function logoutUser() {
    global $conn;

    if (isLoggedIn()) {
        logActivity(getCurrentUserId(), 'LOGOUT', 'User logged out');

        $delete = $conn->prepare('DELETE FROM user_sessions WHERE user_id = ?');
        $delete->execute([getCurrentUserId()]);

        setcookie('remember_token', '', time() - 3600, '/');
        setcookie('remember_user', '', time() - 3600, '/');
    }

    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function verifyEmail($token) {
    global $conn;

    $stmt = $conn->prepare(
        'SELECT id FROM users
         WHERE email_verification_token = ?
         AND email_verification_expires > NOW()
         AND email_verified = 0'
    );
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $update = $conn->prepare(
            'UPDATE users
             SET email_verified = 1,
                 email_verification_token = NULL,
                 email_verification_expires = NULL
             WHERE id = ?'
        );
        $update->execute([$user['id']]);

        logActivity($user['id'], 'VERIFY_EMAIL', 'Email verified');

        return ['success' => true, 'message' => 'Email verified successfully'];
    }

    return ['success' => false, 'message' => 'Invalid or expired verification link'];
}

function resendVerification($email) {
    global $conn;

    $stmt = $conn->prepare('SELECT id, email_verified FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'message' => 'Email not found'];
    }

    if ((bool) $user['email_verified']) {
        return ['success' => false, 'message' => 'Email already verified'];
    }

    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

    $update = $conn->prepare(
        'UPDATE users
         SET email_verification_token = ?,
             email_verification_expires = ?
         WHERE id = ?'
    );
    $update->execute([$token, $expires, $user['id']]);

    sendVerificationEmail($email, $token);

    return ['success' => true, 'message' => 'Verification email sent'];
}

function requestPasswordReset($email) {
    global $conn;

    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $update = $conn->prepare(
            'UPDATE users
             SET reset_token = ?, reset_expires = ?
             WHERE id = ?'
        );
        $update->execute([$token, $expires, $user['id']]);

        sendPasswordResetEmail($email, $token);
    }

    return ['success' => true, 'message' => 'If your email exists, you will receive a reset link'];
}

function resetPassword($token, $new_password) {
    global $conn;

    $stmt = $conn->prepare(
        'SELECT id FROM users
         WHERE reset_token = ? AND reset_expires > NOW()'
    );
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $hashed = password_hash($new_password, PASSWORD_BCRYPT);

        $update = $conn->prepare(
            'UPDATE users
             SET password = ?,
                 reset_token = NULL,
                 reset_expires = NULL
             WHERE id = ?'
        );
        $update->execute([$hashed, $user['id']]);

        logActivity($user['id'], 'PASSWORD_RESET', 'Password reset successfully');

        return ['success' => true, 'message' => 'Password reset successfully'];
    }

    return ['success' => false, 'message' => 'Invalid or expired reset token'];
}

function changePassword($user_id, $current_password, $new_password) {
    global $conn;

    $stmt = $conn->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($current_password, $user['password'])) {
        return ['success' => false, 'message' => 'Current password is incorrect'];
    }

    $hashed = password_hash($new_password, PASSWORD_BCRYPT);

    $update = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
    $update->execute([$hashed, $user_id]);

    logActivity($user_id, 'PASSWORD_CHANGE', 'Password changed');

    return ['success' => true, 'message' => 'Password changed successfully'];
}

function sendVerificationEmail($email, $token) {
    $verification_link = SITE_URL . '/auth/verify-email.php?token=' . $token;
    error_log("Verification email for $email: $verification_link");
    $_SESSION['verification_link'] = $verification_link;
}

function sendPasswordResetEmail($email, $token) {
    $reset_link = SITE_URL . '/auth/reset-password.php?token=' . $token;
    error_log("Password reset for $email: $reset_link");
    $_SESSION['reset_link'] = $reset_link;
}

function logActivity($user_id, $action, $details = '') {
    global $conn;

    if (!$conn) {
        return;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $stmt = $conn->prepare(
        'INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$user_id, $action, $details, $ip, $user_agent]);
}

function getUserById($user_id) {
    global $conn;

    $stmt = $conn->prepare(
        'SELECT u.*, p.*
         FROM users u
         LEFT JOIN profiles p ON u.id = p.user_id
         WHERE u.id = ?'
    );
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

function updateLastActivity() {
    if (isLoggedIn()) {
        $_SESSION['last_activity'] = time();
    }
}

function checkSessionTimeout() {
    $timeout = 1800;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        logoutUser();
        redirect(SITE_URL . '/auth/login.php?timeout=1');
    }
    updateLastActivity();
}


/**
 * PROFILE FUNCTIONS
 */
function getCompleteProfile($user_id) {
    global $conn;

    $stmt = $conn->prepare(
        'SELECT u.*, p.*
         FROM users u
         LEFT JOIN profiles p ON u.id = p.user_id
         WHERE u.id = ?'
    );
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

function updateProfile($user_id, $data) {
    global $conn;

    $allowed = ['full_name', 'phone', 'address', 'bio', 'date_of_birth', 'gender', 'occupation', 'company', 'education', 'languages', 'emergency_contact_name', 'emergency_contact_phone', 'facebook_url', 'twitter_url', 'linkedin_url', 'instagram_url'];
    $updates = [];
    $params = [];

    foreach ($data as $key => $value) {
        if (in_array($key, $allowed, true)) {
            $updates[] = "$key = ?";
            $params[] = sanitize($value);
        }
    }

    if (empty($updates)) {
        return ['success' => false, 'message' => 'No valid fields to update'];
    }

    $params[] = $user_id;
    $sql = 'UPDATE profiles SET ' . implode(', ', $updates) . ' WHERE user_id = ?';

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        logActivity($user_id, 'PROFILE_UPDATE', 'Profile updated');

        if (isset($data['full_name']) && $data['full_name'] !== '') {
            $_SESSION['user_name'] = $data['full_name'];
        }

        updateTrustScore($user_id, 'profile_updated');

        return ['success' => true, 'message' => 'Profile updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
    }
}

function uploadAvatar($user_id, $file) {
    global $conn;

    $target_dir = UPLOAD_PATH . 'avatars/';
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($file_extension, $allowed, true)) {
        return ['success' => false, 'message' => 'Only JPG, PNG, GIF, WEBP files are allowed'];
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'File size must be less than 5MB'];
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return ['success' => false, 'message' => 'Invalid image file'];
    }

    [$width, $height] = $imageInfo;
    $new_filename = 'avatar_' . $user_id . '_' . time() . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;
    $max_size = 500;

    $canResize = extension_loaded('gd')
        && function_exists('imagecreatefromstring')
        && function_exists('imagecreatetruecolor');

    if (($width > $max_size || $height > $max_size) && $canResize) {
        $ratio = $width / $height;
        if ($width > $height) {
            $new_width = $max_size;
            $new_height = (int) round($max_size / $ratio);
        } else {
            $new_height = $max_size;
            $new_width = (int) round($max_size * $ratio);
        }

        $src = @imagecreatefromstring(file_get_contents($file['tmp_name']));
        if (!$src) {
            return ['success' => false, 'message' => 'Unable to process image'];
        }

        $dst = imagecreatetruecolor($new_width, $new_height);
        if ($file_extension === 'png' || $file_extension === 'webp' || $file_extension === 'gif') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
            imagefilledrectangle($dst, 0, 0, $new_width, $new_height, $transparent);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

        switch ($file_extension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($dst, $target_file, 90);
                break;
            case 'png':
                imagepng($dst, $target_file, 9);
                break;
            case 'gif':
                imagegif($dst, $target_file);
                break;
            case 'webp':
                imagewebp($dst, $target_file, 90);
                break;
        }

        imagedestroy($src);
        imagedestroy($dst);
    } else {
        if (!move_uploaded_file($file['tmp_name'], $target_file)) {
            return ['success' => false, 'message' => 'Failed to move uploaded file'];
        }
    }

    $stmt = $conn->prepare('SELECT avatar FROM profiles WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $old = $stmt->fetch();

    $stmt = $conn->prepare('UPDATE profiles SET avatar = ? WHERE user_id = ?');
    $stmt->execute([$new_filename, $user_id]);

    if ($old && !empty($old['avatar']) && $old['avatar'] !== 'default-avatar.svg' && file_exists($target_dir . $old['avatar'])) {
        unlink($target_dir . $old['avatar']);
    }

    $_SESSION['user_avatar'] = $new_filename;

    logActivity($user_id, 'AVATAR_UPLOAD', 'Profile picture updated');
    updateTrustScore($user_id, 'avatar_uploaded');

    return ['success' => true, 'message' => 'Avatar uploaded successfully', 'filename' => $new_filename];
}

function getProfileViews($user_id) {
    global $conn;

    if (!tableExists('user_views')) {
        return 0;
    }

    $stmt = $conn->prepare('SELECT COUNT(*) as views FROM user_views WHERE viewed_user_id = ?');
    $stmt->execute([$user_id]);
    return (int) ($stmt->fetch()['views'] ?? 0);
}

function recordProfileView($viewed_user_id, $viewer_id = null) {
    global $conn;

    if (!tableExists('user_views')) {
        return;
    }

    if ($viewer_id && (int) $viewer_id === (int) $viewed_user_id) {
        return;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    $stmt = $conn->prepare('INSERT INTO user_views (viewer_id, viewed_user_id, ip_address) VALUES (?, ?, ?)');
    $stmt->execute([$viewer_id, $viewed_user_id, $ip]);

    $stmt = $conn->prepare('UPDATE profiles SET profile_views = profile_views + 1 WHERE user_id = ?');
    $stmt->execute([$viewed_user_id]);
}

function updateOnlineStatus($user_id) {
    global $conn;

    if (!columnExists('profiles', 'last_active') || !columnExists('profiles', 'is_online')) {
        return;
    }

    $stmt = $conn->prepare('UPDATE profiles SET last_active = NOW(), is_online = TRUE WHERE user_id = ?');
    $stmt->execute([$user_id]);
}

function setUserOffline($user_id) {
    global $conn;

    if (!columnExists('profiles', 'last_active') || !columnExists('profiles', 'is_online')) {
        return;
    }

    $stmt = $conn->prepare('UPDATE profiles SET is_online = FALSE, last_active = NOW() WHERE user_id = ?');
    $stmt->execute([$user_id]);
}

function getOnlineStatus($user_id) {
    global $conn;

    if (!columnExists('profiles', 'last_active') || !columnExists('profiles', 'is_online')) {
        return ['status' => 'offline', 'last_seen' => null];
    }

    $stmt = $conn->prepare('SELECT is_online, last_active FROM profiles WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['status' => 'offline', 'last_seen' => null];
    }

    if ($user['is_online'] && $user['last_active'] && time() - strtotime($user['last_active']) > 900) {
        $update = $conn->prepare('UPDATE profiles SET is_online = FALSE WHERE user_id = ?');
        $update->execute([$user_id]);
        $user['is_online'] = false;
    }

    return ['status' => !empty($user['is_online']) ? 'online' : 'offline', 'last_seen' => $user['last_active']];
}

/**
 * VERIFICATION FUNCTIONS
 */
function sendPhoneVerification($user_id, $phone) {
    global $conn;

    if (!tableExists('phone_verification')) {
        return ['success' => false, 'message' => 'Phone verification is not set up yet'];
    }

    $check = $conn->prepare('SELECT user_id FROM profiles WHERE phone = ? AND phone_verified = 1 AND user_id != ?');
    $check->execute([$phone, $user_id]);
    if ($check->fetch()) {
        return ['success' => false, 'message' => 'Phone number already verified by another user'];
    }

    $code = sprintf('%06d', mt_rand(1, 999999));
    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $delete = $conn->prepare('DELETE FROM phone_verification WHERE user_id = ?');
    $delete->execute([$user_id]);

    $insert = $conn->prepare('INSERT INTO phone_verification (user_id, phone, code, expires_at) VALUES (?, ?, ?, ?)');
    $insert->execute([$user_id, $phone, $code, $expires]);

    $_SESSION['phone_code'] = $code;
    $_SESSION['phone'] = $phone;

    $update = $conn->prepare('UPDATE profiles SET phone = ? WHERE user_id = ?');
    $update->execute([$phone, $user_id]);

    logActivity($user_id, 'PHONE_VERIFICATION_SENT', "Verification code sent to $phone");

    return ['success' => true, 'message' => 'Verification code sent', 'code' => $code];
}

function verifyPhoneCode($user_id, $code) {
    global $conn;

    if (!tableExists('phone_verification')) {
        return ['success' => false, 'message' => 'Phone verification is not set up yet'];
    }

    $stmt = $conn->prepare('SELECT * FROM phone_verification WHERE user_id = ? AND code = ? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1');
    $stmt->execute([$user_id, $code]);
    $verification = $stmt->fetch();

    if ($verification) {
        $update = $conn->prepare('UPDATE profiles SET phone_verified = 1, phone = ? WHERE user_id = ?');
        $update->execute([$verification['phone'], $user_id]);

        $update = $conn->prepare('UPDATE phone_verification SET verified_at = NOW() WHERE id = ?');
        $update->execute([$verification['id']]);

        updateTrustScore($user_id, 'phone_verified');
        logActivity($user_id, 'PHONE_VERIFIED', 'Phone number verified');

        return ['success' => true, 'message' => 'Phone verified successfully'];
    }

    return ['success' => false, 'message' => 'Invalid or expired code'];
}

function submitIDVerification($user_id, $document_type, $document_number, $file) {
    global $conn;

    if (!tableExists('verification_documents')) {
        return ['success' => false, 'message' => 'Document verification is not set up yet'];
    }

    $target_dir = UPLOAD_PATH . 'documents/';
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];

    if (!in_array($file_extension, $allowed, true)) {
        return ['success' => false, 'message' => 'Only JPG, PNG, PDF files are allowed'];
    }

    if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
        return ['success' => false, 'message' => 'File size must be less than 10MB'];
    }

    $check = $conn->prepare("SELECT id FROM verification_documents WHERE user_id = ? AND status = 'pending'");
    $check->execute([$user_id]);
    if ($check->fetch()) {
        return ['success' => false, 'message' => 'You already have a pending verification request'];
    }

    $new_filename = 'doc_' . $user_id . '_' . time() . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;

    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        $insert = $conn->prepare('INSERT INTO verification_documents (user_id, document_type, document_number, document_file) VALUES (?, ?, ?, ?)');
        $insert->execute([$user_id, $document_type, $document_number, $new_filename]);

        logActivity($user_id, 'ID_VERIFICATION_SUBMITTED', "$document_type submitted");
        return ['success' => true, 'message' => 'Verification documents submitted successfully'];
    }

    return ['success' => false, 'message' => 'Failed to upload file'];
}

function getVerificationStatus($user_id) {
    global $conn;

    $stmt = $conn->prepare('SELECT phone_verified, id_verified FROM profiles WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();

    $documents = [];
    if (tableExists('verification_documents')) {
        $stmt = $conn->prepare('SELECT * FROM verification_documents WHERE user_id = ? ORDER BY submitted_at DESC');
        $stmt->execute([$user_id]);
        $documents = $stmt->fetchAll();
    }

    return [
        'phone_verified' => !empty($profile['phone_verified']),
        'id_verified' => !empty($profile['id_verified']),
        'pending_documents' => $documents,
    ];
}

function approveVerification($doc_id, $admin_id) {
    global $conn;

    $stmt = $conn->prepare('SELECT user_id FROM verification_documents WHERE id = ?');
    $stmt->execute([$doc_id]);
    $doc = $stmt->fetch();

    if (!$doc) {
        return ['success' => false, 'message' => 'Document not found'];
    }

    $update = $conn->prepare("UPDATE verification_documents SET status = 'approved', reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
    $update->execute([$admin_id, $doc_id]);

    $update = $conn->prepare('UPDATE profiles SET id_verified = 1 WHERE user_id = ?');
    $update->execute([$doc['user_id']]);

    updateTrustScore($doc['user_id'], 'id_verified');
    logActivity($doc['user_id'], 'ID_VERIFIED', 'ID verification approved by admin');
    logAdminAction($admin_id, 'approve_verification', 'verification', $doc_id, 'Approved verification for user #' . $doc['user_id']);

    return ['success' => true, 'message' => 'Verification approved'];
}

function rejectVerification($doc_id, $admin_id, $reason) {
    global $conn;

    $stmt = $conn->prepare("UPDATE verification_documents SET status = 'rejected', admin_notes = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
    $stmt->execute([$reason, $admin_id, $doc_id]);
    logAdminAction($admin_id, 'reject_verification', 'verification', $doc_id, 'Rejected verification: ' . $reason);

    return ['success' => true, 'message' => 'Verification rejected'];
}

/**
 * TRUST SCORE FUNCTIONS
 */
function calculateProfileCompleteness($user_id) {
    global $conn;

    $stmt = $conn->prepare('SELECT * FROM profiles WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();

    if (!$profile) {
        return 0;
    }

    $fields = ['full_name', 'phone', 'address', 'bio', 'date_of_birth', 'occupation', 'education', 'emergency_contact_name'];
    $filled = 0;
    foreach ($fields as $field) {
        if (!empty($profile[$field])) {
            $filled++;
        }
    }

    if (!empty($profile['avatar']) && $profile['avatar'] !== 'default-avatar.svg') {
        $filled++;
    }

    return (int) min(10, round(($filled / (count($fields) + 1)) * 10));
}

function calculateTrustScore($user_id) {
    global $conn;

    $stmt = $conn->prepare('SELECT u.email_verified, p.phone_verified, p.id_verified, p.profile_views FROM users u LEFT JOIN profiles p ON u.id = p.user_id WHERE u.id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        return 30;
    }

    $score = 30;
    if (!empty($user['email_verified'])) { $score += 10; }
    if (!empty($user['phone_verified'])) { $score += 15; }
    if (!empty($user['id_verified'])) { $score += 20; }
    $score += calculateProfileCompleteness($user_id);
    $score += min(5, (int) (($user['profile_views'] ?? 0) / 10));

    return max(0, min(100, $score));
}

function updateTrustScore($user_id, $reason) {
    global $conn;

    $stmt = $conn->prepare('SELECT trust_score FROM profiles WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $current = $stmt->fetch();
    $old_score = (int) ($current['trust_score'] ?? 30);
    $new_score = calculateTrustScore($user_id);

    if ($new_score !== $old_score) {
        $update = $conn->prepare('UPDATE profiles SET trust_score = ? WHERE user_id = ?');
        $update->execute([$new_score, $user_id]);

        if (tableExists('trust_score_history')) {
            $history = $conn->prepare('INSERT INTO trust_score_history (user_id, old_score, new_score, reason) VALUES (?, ?, ?, ?)');
            $history->execute([$user_id, $old_score, $new_score, $reason]);
        }

        if ((int) $user_id === (int) getCurrentUserId()) {
            $_SESSION['trust_score'] = $new_score;
        }
    }

    return $new_score;
}

function getTrustScoreHistory($user_id, $limit = 10) {
    global $conn;

    if (!tableExists('trust_score_history')) {
        return [];
    }

    $limit = max(1, (int) $limit);
    $stmt = $conn->prepare("SELECT * FROM trust_score_history WHERE user_id = ? ORDER BY changed_at DESC LIMIT $limit");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function getTrustScoreBadge($score) {
    if ($score >= 80) {
        return ['level' => 'High Trust', 'color' => '#1f9d73', 'icon' => 'fa-shield-alt'];
    }
    if ($score >= 50) {
        return ['level' => 'Medium Trust', 'color' => '#d79a2b', 'icon' => 'fa-shield'];
    }
    return ['level' => 'Low Trust', 'color' => '#d85f5f', 'icon' => 'fa-exclamation-triangle'];
}

function getVerificationBadges($user_id) {
    global $conn;

    $stmt = $conn->prepare('SELECT u.email_verified, p.phone_verified, p.id_verified FROM users u LEFT JOIN profiles p ON u.id = p.user_id WHERE u.id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    $badges = [];
    if (!empty($user['email_verified'])) {
        $badges[] = '<span class="badge badge-email" title="Email Verified"><i class="fas fa-envelope"></i></span>';
    }
    if (!empty($user['phone_verified'])) {
        $badges[] = '<span class="badge badge-phone" title="Phone Verified"><i class="fas fa-phone"></i></span>';
    }
    if (!empty($user['id_verified'])) {
        $badges[] = '<span class="badge badge-id" title="ID Verified"><i class="fas fa-id-card"></i></span>';
    }

    return implode(' ', $badges);
}

function tableExists($table) {
    global $conn;

    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $stmt = $conn->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->execute([$table]);
    $cache[$table] = (bool) $stmt->fetchColumn();
    return $cache[$table];
}

function columnExists($table, $column) {
    global $conn;

    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $stmt = $conn->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $stmt->execute([$table, $column]);
    $cache[$key] = (bool) $stmt->fetchColumn();
    return $cache[$key];
}


/**
 * ROOM LISTING FUNCTIONS
 */
function getRoomImageUrl($image = null) {
    if ($image && filter_var($image, FILTER_VALIDATE_URL)) {
        return $image;
    }

    if ($image) {
        $uploadCandidate = __DIR__ . '/../assets/uploads/rooms/' . $image;
        if (file_exists($uploadCandidate)) {
            return SITE_URL . '/assets/uploads/rooms/' . $image;
        }
    }

    $fallbacks = [
        ['file' => __DIR__ . '/../assets/images/default-room.jpg', 'url' => SITE_URL . '/assets/images/default-room.jpg'],
        ['file' => __DIR__ . '/../assets/uploads/rooms/default-room.jpg', 'url' => SITE_URL . '/assets/uploads/rooms/default-room.jpg'],
        ['file' => __DIR__ . '/../assets/images/default-room.svg', 'url' => SITE_URL . '/assets/images/default-room.svg'],
    ];

    foreach ($fallbacks as $fallback) {
        if (file_exists($fallback['file'])) {
            return $fallback['url'];
        }
    }

    return 'https://via.placeholder.com/800x500?text=No+Image';
}

function getAvatarUrl($avatar_name = null) {
    if ($avatar_name && filter_var($avatar_name, FILTER_VALIDATE_URL)) {
        return $avatar_name;
    }

    if (!empty($avatar_name) && $avatar_name !== 'default-avatar.png' && $avatar_name !== 'default-avatar.svg') {
        $uploadCandidate = __DIR__ . '/../assets/uploads/avatars/' . $avatar_name;
        if (file_exists($uploadCandidate)) {
            return SITE_URL . '/assets/uploads/avatars/' . $avatar_name;
        }
    }

    $fallbacks = [
        ['file' => __DIR__ . '/../assets/images/default-avatar.png', 'url' => SITE_URL . '/assets/images/default-avatar.png'],
        ['file' => __DIR__ . '/../assets/uploads/avatars/default-avatar.png', 'url' => SITE_URL . '/assets/uploads/avatars/default-avatar.png'],
        ['file' => __DIR__ . '/../assets/images/default-avatar.svg', 'url' => SITE_URL . '/assets/images/default-avatar.svg'],
    ];

    foreach ($fallbacks as $fallback) {
        if (file_exists($fallback['file'])) {
            return $fallback['url'];
        }
    }

    return SITE_URL . '/assets/images/default-avatar.svg';
}

/**
 * REAL DATA & STATISTICS FUNCTIONS
 */
function getPlatformStats() {
    global $conn;

    $stats = [
        'verified_rooms' => 0,
        'landlords' => 0,
        'tenants' => 0,
        'contracts' => 0,
        'avg_rating' => 0,
        'connections' => 0,
        'total_listings' => 0,
        'new_users_month' => 0,
        'new_listings_month' => 0,
    ];

    try {
        if (tableExists('rooms')) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM rooms WHERE status = 'active' AND is_verified = 1");
            $stmt->execute();
            $stats['verified_rooms'] = (int) ($stmt->fetch()['count'] ?? 0);

            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM rooms WHERE status = 'active'");
            $stmt->execute();
            $stats['total_listings'] = (int) ($stmt->fetch()['count'] ?? 0);

            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM rooms WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $stmt->execute();
            $stats['new_listings_month'] = (int) ($stmt->fetch()['count'] ?? 0);
        }

        if (tableExists('users')) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'landlord'");
            $stmt->execute();
            $stats['landlords'] = (int) ($stmt->fetch()['count'] ?? 0);

            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'tenant'");
            $stmt->execute();
            $stats['tenants'] = (int) ($stmt->fetch()['count'] ?? 0);

            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $stmt->execute();
            $stats['new_users_month'] = (int) ($stmt->fetch()['count'] ?? 0);
        }

        if (tableExists('contracts')) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM contracts WHERE status = 'active'");
            $stmt->execute();
            $stats['contracts'] = (int) ($stmt->fetch()['count'] ?? 0);
        }

        if (tableExists('reviews')) {
            $stmt = $conn->prepare("SELECT AVG(rating_overall) as avg FROM reviews");
            $stmt->execute();
            $stats['avg_rating'] = round((float) ($stmt->fetch()['avg'] ?? 0), 1);
        }

        if (tableExists('messages')) {
            $stmt = $conn->prepare("SELECT COUNT(DISTINCT conversation_id) as count FROM messages");
            $stmt->execute();
            $stats['connections'] = (int) ($stmt->fetch()['count'] ?? 0);
        }
    } catch (Exception $e) {
        error_log('Error getting platform stats: ' . $e->getMessage());
    }

    return $stats;
}

function getRealFeaturedRooms($limit = 4) {
    global $conn;

    if (!tableExists('rooms')) {
        return [];
    }

    try {
        $stmt = $conn->prepare("
            SELECT r.*,
                   p.full_name as landlord_name,
                   p.avatar as landlord_avatar,
                   p.trust_score,
                   (SELECT image_url FROM room_images WHERE room_id = r.id AND is_primary = 1 LIMIT 1) as primary_image
            FROM rooms r
            LEFT JOIN profiles p ON r.landlord_id = p.user_id
            WHERE r.status = 'active' AND r.is_verified = 1
            ORDER BY r.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([(int) $limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('Error getting featured rooms: ' . $e->getMessage());
        return [];
    }
}

function getRealTestimonials($limit = 3) {
    global $conn;

    if (!tableExists('reviews')) {
        return [];
    }

    try {
        $stmt = $conn->prepare("
            SELECT r.*,
                   rev.full_name as reviewer_name,
                   rev.avatar as reviewer_avatar,
                   revi.full_name as reviewee_name,
                   c.room_title
            FROM reviews r
            JOIN profiles rev ON r.reviewer_id = rev.user_id
            JOIN profiles revi ON r.reviewee_id = revi.user_id
            JOIN (
                SELECT c.id as contract_id, rm.title as room_title
                FROM contracts c
                JOIN rooms rm ON c.room_id = rm.id
            ) c ON r.contract_id = c.contract_id
            WHERE r.is_verified = 1
            ORDER BY r.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([(int) $limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('Error getting testimonials: ' . $e->getMessage());
        return [];
    }
}

function getRecentActivity($limit = 5) {
    global $conn;

    $activity = [
        'recent_rooms' => [],
        'recent_reviews' => [],
        'recent_contracts' => [],
    ];

    try {
        if (tableExists('rooms')) {
            $stmt = $conn->prepare("
                SELECT 'room' as type, r.id, r.title, r.created_at, p.full_name as user_name
                FROM rooms r
                JOIN profiles p ON r.landlord_id = p.user_id
                WHERE r.status = 'active'
                ORDER BY r.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([(int) $limit]);
            $activity['recent_rooms'] = $stmt->fetchAll();
        }

        if (tableExists('reviews')) {
            $stmt = $conn->prepare("
                SELECT 'review' as type, r.id, r.review_text as title, r.created_at, rev.full_name as user_name
                FROM reviews r
                JOIN profiles rev ON r.reviewer_id = rev.user_id
                ORDER BY r.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([(int) $limit]);
            $activity['recent_reviews'] = $stmt->fetchAll();
        }

        if (tableExists('contracts')) {
            $stmt = $conn->prepare("
                SELECT 'contract' as type, c.id, c.contract_number as title, c.created_at,
                       tp.full_name as tenant_name, lp.full_name as landlord_name
                FROM contracts c
                JOIN profiles tp ON c.tenant_id = tp.user_id
                JOIN profiles lp ON c.landlord_id = lp.user_id
                WHERE c.status = 'active'
                ORDER BY c.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([(int) $limit]);
            $activity['recent_contracts'] = $stmt->fetchAll();
        }
    } catch (Exception $e) {
        error_log('Error getting recent activity: ' . $e->getMessage());
    }

    return $activity;
}

function getLocationStats() {
    global $conn;

    if (!tableExists('rooms')) {
        return [];
    }

    try {
        $stmt = $conn->prepare("
            SELECT location, COUNT(*) as count
            FROM rooms
            WHERE status = 'active'
            GROUP BY location
            ORDER BY count DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('Error getting location stats: ' . $e->getMessage());
        return [];
    }
}

function getPriceRangeStats() {
    global $conn;

    if (!tableExists('rooms')) {
        return ['min_price' => 0, 'max_price' => 0, 'avg_price' => 0];
    }

    try {
        $stmt = $conn->prepare("
            SELECT MIN(price) as min_price, MAX(price) as max_price, AVG(price) as avg_price
            FROM rooms
            WHERE status = 'active'
        ");
        $stmt->execute();
        return $stmt->fetch() ?: ['min_price' => 0, 'max_price' => 0, 'avg_price' => 0];
    } catch (Exception $e) {
        error_log('Error getting price stats: ' . $e->getMessage());
        return ['min_price' => 0, 'max_price' => 0, 'avg_price' => 0];
    }
}

function formatNumber($num) {
    $num = (float) $num;
    if ($num >= 1000000) {
        return round($num / 1000000, 1) . 'M';
    }
    if ($num >= 1000) {
        return round($num / 1000, 1) . 'K';
    }
    if ((int) $num == $num) {
        return (string) (int) $num;
    }
    return number_format($num, 1);
}

function createRoom($landlord_id, $data) {
    global $conn;

    if (!tableExists('rooms')) {
        return ['success' => false, 'message' => 'Room listings are not set up yet'];
    }

    $required = ['title', 'description', 'price', 'location'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'message' => ucfirst($field) . ' is required'];
        }
    }

    try {
        $conn->beginTransaction();

        $sql = "INSERT INTO rooms (
            landlord_id, title, description, price, location, address,
            floor_area, bedroom_count, bathroom_count, kitchen_type,
            floor_number, total_floors, available_from, minimum_stay,
            deposit_months, utilities_included, electricity_charge,
            water_charge, internet_charge, status
        ) VALUES (
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, 'pending'
        )";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $landlord_id,
            sanitize($data['title']),
            trim((string) $data['description']),
            $data['price'],
            sanitize($data['location']),
            !empty($data['address']) ? sanitize($data['address']) : null,
            $data['floor_area'] ?: null,
            $data['bedroom_count'] ?: 1,
            $data['bathroom_count'] ?: 1,
            $data['kitchen_type'] ?: 'private',
            $data['floor_number'] ?: null,
            $data['total_floors'] ?: null,
            $data['available_from'] ?: date('Y-m-d'),
            $data['minimum_stay'] ?: 1,
            $data['deposit_months'] ?: 1,
            !empty($data['utilities_included']) ? 1 : 0,
            !empty($data['electricity_charge']) ? sanitize($data['electricity_charge']) : null,
            !empty($data['water_charge']) ? sanitize($data['water_charge']) : null,
            !empty($data['internet_charge']) ? sanitize($data['internet_charge']) : null,
        ]);

        $room_id = (int) $conn->lastInsertId();

        if (!empty($data['amenities']) && is_array($data['amenities'])) {
            addRoomAmenities($room_id, $data['amenities']);
        }

        logActivity($landlord_id, 'ROOM_CREATED', "Created room listing #$room_id");
        $conn->commit();

        return ['success' => true, 'message' => 'Room listing created successfully', 'room_id' => $room_id];
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        return ['success' => false, 'message' => 'Failed to create listing: ' . $e->getMessage()];
    }
}

function updateRoom($room_id, $landlord_id, $data) {
    global $conn;

    $check = $conn->prepare('SELECT id FROM rooms WHERE id = ? AND landlord_id = ?');
    $check->execute([$room_id, $landlord_id]);
    if (!$check->fetch()) {
        return ['success' => false, 'message' => 'Room not found or you do not have permission'];
    }

    $allowed = [
        'title', 'description', 'price', 'location', 'address',
        'floor_area', 'bedroom_count', 'bathroom_count', 'kitchen_type',
        'floor_number', 'total_floors', 'available_from', 'minimum_stay',
        'deposit_months', 'utilities_included', 'electricity_charge',
        'water_charge', 'internet_charge', 'status'
    ];

    $updates = [];
    $params = [];

    foreach ($data as $key => $value) {
        if (in_array($key, $allowed, true)) {
            $updates[] = "$key = ?";
            if (in_array($key, ['title', 'location', 'address', 'electricity_charge', 'water_charge', 'internet_charge'], true)) {
                $params[] = $value === null || $value === '' ? null : sanitize($value);
            } elseif ($key === 'description') {
                $params[] = trim((string) $value);
            } elseif ($key === 'utilities_included') {
                $params[] = !empty($value) ? 1 : 0;
            } else {
                $params[] = $value === '' ? null : $value;
            }
        }
    }

    if (empty($updates)) {
        return ['success' => false, 'message' => 'No valid fields to update'];
    }

    $params[] = $room_id;
    $sql = 'UPDATE rooms SET ' . implode(', ', $updates) . ' WHERE id = ?';

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        if (isset($data['amenities']) && is_array($data['amenities'])) {
            $delete = $conn->prepare('DELETE FROM room_amenities WHERE room_id = ?');
            $delete->execute([$room_id]);
            addRoomAmenities($room_id, $data['amenities']);
        }

        logActivity($landlord_id, 'ROOM_UPDATED', "Updated room listing #$room_id");
        return ['success' => true, 'message' => 'Room updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
    }
}

function addRoomAmenities($room_id, $amenity_ids) {
    global $conn;

    if (!tableExists('room_amenities')) {
        return;
    }

    $stmt = $conn->prepare('INSERT INTO room_amenities (room_id, amenity_id) VALUES (?, ?)');
    foreach ($amenity_ids as $amenity_id) {
        try {
            $stmt->execute([$room_id, (int) $amenity_id]);
        } catch (Exception $e) {
        }
    }
}

function uploadRoomImages($room_id, $landlord_id, $files) {
    global $conn;

    $check = $conn->prepare('SELECT id FROM rooms WHERE id = ? AND landlord_id = ?');
    $check->execute([$room_id, $landlord_id]);
    if (!$check->fetch()) {
        return ['success' => false, 'message' => 'Room not found or you do not have permission'];
    }

    $target_dir = UPLOAD_PATH . 'rooms/';
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $uploaded = [];
    $errors = [];

    $count_stmt = $conn->prepare('SELECT COUNT(*) as count FROM room_images WHERE room_id = ?');
    $count_stmt->execute([$room_id]);
    $current_count = (int) ($count_stmt->fetch()['count'] ?? 0);

    foreach (($files['tmp_name'] ?? []) as $key => $tmp_name) {
        if (($files['error'][$key] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }

        if ($current_count + count($uploaded) >= 10) {
            $errors[] = 'Maximum 10 images allowed';
            break;
        }

        $file = [
            'name' => $files['name'][$key],
            'tmp_name' => $tmp_name,
            'size' => $files['size'][$key],
        ];

        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($file_extension, $allowed, true)) {
            $errors[] = "File {$file['name']} is not allowed. Only JPG, PNG, WEBP";
            continue;
        }

        if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
            $errors[] = "File {$file['name']} is too large. Max 10MB";
            continue;
        }

        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            $errors[] = "File {$file['name']} is not a valid image";
            continue;
        }

        [$width, $height] = $imageInfo;
        $new_filename = 'room_' . $room_id . '_' . time() . '_' . $key . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;
        $max_size = 1200;
        $canResize = extension_loaded('gd')
            && function_exists('imagecreatefromstring')
            && function_exists('imagecreatetruecolor');

        if (($width > $max_size || $height > $max_size) && $canResize) {
            $ratio = $width / $height;
            if ($width > $height) {
                $new_width = $max_size;
                $new_height = (int) round($max_size / $ratio);
            } else {
                $new_height = $max_size;
                $new_width = (int) round($max_size * $ratio);
            }

            $src = @imagecreatefromstring(file_get_contents($file['tmp_name']));
            if (!$src) {
                $errors[] = "File {$file['name']} could not be processed";
                continue;
            }

            $dst = imagecreatetruecolor($new_width, $new_height);
            if ($file_extension === 'png' || $file_extension === 'webp') {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
                imagefilledrectangle($dst, 0, 0, $new_width, $new_height, $transparent);
            }

            imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

            switch ($file_extension) {
                case 'jpg':
                case 'jpeg':
                    imagejpeg($dst, $target_file, 85);
                    break;
                case 'png':
                    imagepng($dst, $target_file, 8);
                    break;
                case 'webp':
                    imagewebp($dst, $target_file, 85);
                    break;
            }

            imagedestroy($src);
            imagedestroy($dst);
        } else {
            if (!move_uploaded_file($file['tmp_name'], $target_file)) {
                $errors[] = "File {$file['name']} could not be uploaded";
                continue;
            }
        }

        $check_primary = $conn->prepare('SELECT COUNT(*) as count FROM room_images WHERE room_id = ?');
        $check_primary->execute([$room_id]);
        $is_primary = ((int) ($check_primary->fetch()['count'] ?? 0) === 0) ? 1 : 0;

        $insert = $conn->prepare('INSERT INTO room_images (room_id, image_url, is_primary, sort_order) VALUES (?, ?, ?, ?)');
        $insert->execute([$room_id, $new_filename, $is_primary, $current_count + count($uploaded)]);

        $uploaded[] = $new_filename;
    }

    logActivity($landlord_id, 'ROOM_IMAGES_UPLOADED', 'Uploaded ' . count($uploaded) . " images for room #$room_id");

    return [
        'success' => empty($errors) || !empty($uploaded),
        'uploaded' => $uploaded,
        'errors' => $errors,
        'message' => 'Uploaded ' . count($uploaded) . ' images successfully'
    ];
}

function deleteRoomImage($image_id, $landlord_id) {
    global $conn;

    $check = $conn->prepare('SELECT ri.*, r.landlord_id FROM room_images ri JOIN rooms r ON ri.room_id = r.id WHERE ri.id = ?');
    $check->execute([$image_id]);
    $image = $check->fetch();

    if (!$image || (int) $image['landlord_id'] !== (int) $landlord_id) {
        return ['success' => false, 'message' => 'Image not found or permission denied'];
    }

    if (!empty($image['is_primary'])) {
        $new_primary = $conn->prepare('SELECT id FROM room_images WHERE room_id = ? AND id != ? ORDER BY sort_order ASC LIMIT 1');
        $new_primary->execute([$image['room_id'], $image_id]);
        $new = $new_primary->fetch();
        if ($new) {
            $update = $conn->prepare('UPDATE room_images SET is_primary = 1 WHERE id = ?');
            $update->execute([$new['id']]);
        }
    }

    $file_path = UPLOAD_PATH . 'rooms/' . $image['image_url'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }

    $delete = $conn->prepare('DELETE FROM room_images WHERE id = ?');
    $delete->execute([$image_id]);

    return ['success' => true, 'message' => 'Image deleted successfully'];
}

function setPrimaryImage($room_id, $image_id, $landlord_id) {
    global $conn;

    $check = $conn->prepare('SELECT id FROM rooms WHERE id = ? AND landlord_id = ?');
    $check->execute([$room_id, $landlord_id]);
    if (!$check->fetch()) {
        return ['success' => false, 'message' => 'Permission denied'];
    }

    $reset = $conn->prepare('UPDATE room_images SET is_primary = 0 WHERE room_id = ?');
    $reset->execute([$room_id]);

    $set = $conn->prepare('UPDATE room_images SET is_primary = 1 WHERE id = ? AND room_id = ?');
    $set->execute([$image_id, $room_id]);

    return ['success' => true, 'message' => 'Primary image updated'];
}

function getRoomDetails($room_id) {
    global $conn;

    if (!tableExists('rooms')) {
        return null;
    }

    $stmt = $conn->prepare('SELECT r.*, u.email, p.full_name, p.phone, p.avatar, p.trust_score, p.phone_verified, p.id_verified, p.response_rate, p.response_time, p.joined_date FROM rooms r JOIN users u ON r.landlord_id = u.id LEFT JOIN profiles p ON u.id = p.user_id WHERE r.id = ?');
    $stmt->execute([(int) $room_id]);
    $room = $stmt->fetch();

    if ($room) {
        $img_stmt = $conn->prepare('SELECT * FROM room_images WHERE room_id = ? ORDER BY is_primary DESC, sort_order ASC');
        $img_stmt->execute([$room_id]);
        $room['images'] = $img_stmt->fetchAll();

        $amenity_stmt = $conn->prepare('SELECT a.* FROM amenities a JOIN room_amenities ra ON a.id = ra.amenity_id WHERE ra.room_id = ? ORDER BY a.category, a.name');
        $amenity_stmt->execute([$room_id]);
        $room['amenities'] = $amenity_stmt->fetchAll();

        $fav_stmt = $conn->prepare('SELECT COUNT(*) as count FROM favorites WHERE room_id = ?');
        $fav_stmt->execute([$room_id]);
        $room['favorite_count'] = (int) ($fav_stmt->fetch()['count'] ?? 0);
    }

    return $room;
}

function searchRooms($filters = [], $page = 1, $per_page = 12) {
    global $conn;

    if (!tableExists('rooms')) {
        return ['rooms' => [], 'total' => 0, 'page' => 1, 'per_page' => $per_page, 'total_pages' => 0];
    }

    $where = ["r.status = 'active'"];
    $params = [];

    if (!empty($filters['location'])) {
        $where[] = 'r.location LIKE ?';
        $params[] = '%' . $filters['location'] . '%';
    }
    if (!empty($filters['min_price'])) {
        $where[] = 'r.price >= ?';
        $params[] = (int) $filters['min_price'];
    }
    if (!empty($filters['max_price'])) {
        $where[] = 'r.price <= ?';
        $params[] = (int) $filters['max_price'];
    }
    if (!empty($filters['bedrooms'])) {
        $where[] = 'r.bedroom_count >= ?';
        $params[] = (int) $filters['bedrooms'];
    }
    if (!empty($filters['verified_only'])) {
        $where[] = 'r.is_verified = 1';
    }
    if (!empty($filters['amenities']) && is_array($filters['amenities'])) {
        $amenities = array_values(array_filter(array_map('intval', $filters['amenities'])));
        if (!empty($amenities)) {
            $placeholders = implode(',', array_fill(0, count($amenities), '?'));
            $where[] = "r.id IN (SELECT room_id FROM room_amenities WHERE amenity_id IN ($placeholders) GROUP BY room_id HAVING COUNT(DISTINCT amenity_id) = ?)";
            $params = array_merge($params, $amenities);
            $params[] = count($amenities);
        }
    }
    if (!empty($filters['search'])) {
        $where[] = '(r.title LIKE ? OR r.description LIKE ? OR r.location LIKE ?)';
        $searchTerm = '%' . $filters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $where_clause = implode(' AND ', $where);
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM rooms r WHERE $where_clause");
    $count_stmt->execute($params);
    $total = (int) ($count_stmt->fetch()['total'] ?? 0);

    $offset = max(0, ($page - 1) * $per_page);
    $order = 'r.created_at DESC';
    if (!empty($filters['sort'])) {
        switch ($filters['sort']) {
            case 'price_asc':
                $order = 'r.price ASC';
                break;
            case 'price_desc':
                $order = 'r.price DESC';
                break;
            case 'popular':
                $order = 'r.view_count DESC';
                break;
            case 'trust':
                $order = 'p.trust_score DESC, r.created_at DESC';
                break;
            default:
                $order = 'r.created_at DESC';
        }
    }

    $sql = "SELECT r.*, p.full_name, p.trust_score, p.avatar,
            (SELECT image_url FROM room_images WHERE room_id = r.id AND is_primary = 1 LIMIT 1) as primary_image,
            (SELECT COUNT(*) FROM favorites WHERE room_id = r.id) as favorite_count
            FROM rooms r
            LEFT JOIN profiles p ON r.landlord_id = p.user_id
            WHERE $where_clause
            ORDER BY $order
            LIMIT ? OFFSET ?";

    $queryParams = $params;
    $queryParams[] = (int) $per_page;
    $queryParams[] = (int) $offset;

    $stmt = $conn->prepare($sql);
    $stmt->execute($queryParams);
    $rooms = $stmt->fetchAll();

    return [
        'rooms' => $rooms,
        'total' => $total,
        'page' => (int) $page,
        'per_page' => (int) $per_page,
        'total_pages' => $total > 0 ? (int) ceil($total / $per_page) : 1,
    ];
}

function getLandlordRooms($landlord_id, $status = null) {
    global $conn;

    if (!tableExists('rooms')) {
        return [];
    }

    $sql = 'SELECT r.*, (SELECT COUNT(*) FROM room_images WHERE room_id = r.id) as image_count, (SELECT image_url FROM room_images WHERE room_id = r.id AND is_primary = 1 LIMIT 1) as primary_image FROM rooms r WHERE r.landlord_id = ?';
    $params = [$landlord_id];
    if ($status) {
        $sql .= ' AND r.status = ?';
        $params[] = $status;
    }
    $sql .= ' ORDER BY r.created_at DESC';

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function toggleFavorite($user_id, $room_id) {
    global $conn;

    $check = $conn->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND room_id = ?');
    $check->execute([$user_id, $room_id]);

    if ($check->fetch()) {
        $delete = $conn->prepare('DELETE FROM favorites WHERE user_id = ? AND room_id = ?');
        $delete->execute([$user_id, $room_id]);

        $update = $conn->prepare('UPDATE rooms SET favorite_count = GREATEST(favorite_count - 1, 0) WHERE id = ?');
        $update->execute([$room_id]);

        return ['success' => true, 'action' => 'removed', 'message' => 'Removed from favorites'];
    }

    $insert = $conn->prepare('INSERT INTO favorites (user_id, room_id) VALUES (?, ?)');
    $insert->execute([$user_id, $room_id]);

    $update = $conn->prepare('UPDATE rooms SET favorite_count = favorite_count + 1 WHERE id = ?');
    $update->execute([$room_id]);

    logActivity($user_id, 'FAVORITE_ADDED', "Added room #$room_id to favorites");
    return ['success' => true, 'action' => 'added', 'message' => 'Added to favorites'];
}

function isFavorited($user_id, $room_id) {
    global $conn;

    if (!$user_id || !tableExists('favorites')) {
        return false;
    }

    $stmt = $conn->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND room_id = ?');
    $stmt->execute([$user_id, $room_id]);
    return (bool) $stmt->fetch();
}

function getFavoriteRooms($user_id) {
    global $conn;

    if (!tableExists('favorites')) {
        return [];
    }

    $stmt = $conn->prepare('SELECT r.*, f.created_at as favorited_at,
        (SELECT image_url FROM room_images WHERE room_id = r.id AND is_primary = 1 LIMIT 1) as primary_image,
        p.full_name, p.trust_score, p.avatar
        FROM favorites f
        JOIN rooms r ON f.room_id = r.id
        LEFT JOIN profiles p ON r.landlord_id = p.user_id
        WHERE f.user_id = ?
        ORDER BY f.created_at DESC');
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function recordRoomView($room_id, $viewer_id = null) {
    global $conn;

    if (!tableExists('room_views')) {
        return;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $conn->prepare('INSERT INTO room_views (room_id, viewer_id, ip_address) VALUES (?, ?, ?)');
    $stmt->execute([$room_id, $viewer_id, $ip]);

    $update = $conn->prepare('UPDATE rooms SET view_count = view_count + 1 WHERE id = ?');
    $update->execute([$room_id]);
}

function getAllAmenities() {
    global $conn;

    if (!tableExists('amenities')) {
        return [];
    }

    $stmt = $conn->prepare('SELECT * FROM amenities ORDER BY category, name');
    $stmt->execute();
    return $stmt->fetchAll();
}

function getAmenitiesByCategory() {
    $amenities = getAllAmenities();
    $grouped = [];
    foreach ($amenities as $amenity) {
        $grouped[$amenity['category']][] = $amenity;
    }
    return $grouped;
}

function deleteRoom($room_id, $landlord_id) {
    global $conn;

    $check = $conn->prepare('SELECT id FROM rooms WHERE id = ? AND landlord_id = ?');
    $check->execute([$room_id, $landlord_id]);
    if (!$check->fetch()) {
        return ['success' => false, 'message' => 'Room not found or permission denied'];
    }

    try {
        $img_stmt = $conn->prepare('SELECT image_url FROM room_images WHERE room_id = ?');
        $img_stmt->execute([$room_id]);
        $images = $img_stmt->fetchAll();

        $delete = $conn->prepare('DELETE FROM rooms WHERE id = ?');
        $delete->execute([$room_id]);

        foreach ($images as $image) {
            $file_path = UPLOAD_PATH . 'rooms/' . $image['image_url'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }

        logActivity($landlord_id, 'ROOM_DELETED', "Deleted room listing #$room_id");
        return ['success' => true, 'message' => 'Room deleted successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()];
    }
}

function updateRoomStatus($room_id, $status, $admin_id) {
    global $conn;

    $allowed = ['active', 'inactive', 'booked', 'pending', 'rejected'];
    if (!in_array($status, $allowed, true)) {
        return ['success' => false, 'message' => 'Invalid status'];
    }

    $stmt = $conn->prepare('UPDATE rooms SET status = ?, is_verified = ? WHERE id = ?');
    $stmt->execute([$status, $status === 'active' ? 1 : 0, $room_id]);

    logActivity($admin_id, 'ROOM_STATUS_UPDATED', "Updated room #$room_id status to $status");
    logAdminAction($admin_id, 'update_room_status', 'room', $room_id, 'Updated room status to ' . $status);
    return ['success' => true, 'message' => 'Room status updated'];
}

function getSimilarRooms($room_id, $location, $price, $limit = 4) {
    global $conn;

    if (!tableExists('rooms')) {
        return [];
    }

    $stmt = $conn->prepare('SELECT r.*,
        (SELECT image_url FROM room_images WHERE room_id = r.id AND is_primary = 1 LIMIT 1) as primary_image,
        p.full_name, p.trust_score
        FROM rooms r
        LEFT JOIN profiles p ON r.landlord_id = p.user_id
        WHERE r.status = ? AND r.id != ? AND (r.location = ? OR ABS(r.price - ?) < 5000)
        ORDER BY CASE WHEN r.location = ? THEN 1 ELSE 2 END, ABS(r.price - ?) ASC
        LIMIT ' . (int) $limit);
    $stmt->execute(['active', $room_id, $location, $price, $location, $price]);
    return $stmt->fetchAll();
}



/**
 * MESSAGING & NOTIFICATION FUNCTIONS
 */
function getOrCreateConversation($room_id, $tenant_id, $landlord_id) {
    global $conn;

    if (!tableExists('conversations')) {
        return 0;
    }

    $stmt = $conn->prepare('SELECT id FROM conversations WHERE room_id = ? AND tenant_id = ? AND landlord_id = ?');
    $stmt->execute([$room_id, $tenant_id, $landlord_id]);
    $conversation = $stmt->fetch();
    if ($conversation) {
        return (int) $conversation['id'];
    }

    $stmt = $conn->prepare('INSERT INTO conversations (room_id, tenant_id, landlord_id, status) VALUES (?, ?, ?, ?)');
    $stmt->execute([$room_id, $tenant_id, $landlord_id, 'pending']);
    $conversation_id = (int) $conn->lastInsertId();

    if (tableExists('contact_requests')) {
        $request = $conn->prepare('INSERT INTO contact_requests (room_id, tenant_id, landlord_id) VALUES (?, ?, ?)');
        $request->execute([$room_id, $tenant_id, $landlord_id]);
    }

    $room = getRoomDetails($room_id);
    $tenant = getUserById($tenant_id);
    createNotification(
        $landlord_id,
        'contact_request',
        'New Contact Request',
        (($tenant['full_name'] ?? 'A tenant') . ' is interested in your room: ' . ($room['title'] ?? 'Room listing')),
        '/pages/messages.php?conversation=' . $conversation_id
    );

    return $conversation_id;
}

function sendMessage($conversation_id, $sender_id, $message) {
    global $conn;

    if (!tableExists('messages') || !tableExists('conversations')) {
        return ['success' => false, 'message' => 'Messaging is not available yet'];
    }

    $message = trim((string) $message);
    if ($message === '') {
        return ['success' => false, 'message' => 'Message cannot be empty'];
    }

    $stmt = $conn->prepare('SELECT c.*, r.title as room_title FROM conversations c JOIN rooms r ON c.room_id = r.id WHERE c.id = ?');
    $stmt->execute([$conversation_id]);
    $conversation = $stmt->fetch();
    if (!$conversation) {
        return ['success' => false, 'message' => 'Conversation not found'];
    }

    if ((int) $sender_id !== (int) $conversation['tenant_id'] && (int) $sender_id !== (int) $conversation['landlord_id']) {
        return ['success' => false, 'message' => 'Access denied'];
    }

    if (!canSendMessage($sender_id, $conversation_id)) {
        return ['success' => false, 'message' => 'You cannot send messages in this conversation'];
    }

    $receiver_id = (int) $sender_id === (int) $conversation['tenant_id'] ? (int) $conversation['landlord_id'] : (int) $conversation['tenant_id'];

    $stmt = $conn->prepare('INSERT INTO messages (conversation_id, sender_id, receiver_id, message, is_delivered, delivered_at, is_read, read_at) VALUES (?, ?, ?, ?, 0, NULL, 0, NULL)');
    $stmt->execute([$conversation_id, $sender_id, $receiver_id, $message]);
    $message_id = (int) $conn->lastInsertId();

    $stmt = $conn->prepare('UPDATE conversations SET last_message = ?, last_message_time = NOW(), last_message_sender_id = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$message, $sender_id, $conversation_id]);

    if ((int) $sender_id === (int) $conversation['tenant_id']) {
        $countStmt = $conn->prepare('UPDATE conversations SET landlord_unread_count = landlord_unread_count + 1 WHERE id = ?');
    } else {
        $countStmt = $conn->prepare('UPDATE conversations SET tenant_unread_count = tenant_unread_count + 1 WHERE id = ?');
        updateResponseStats((int) $sender_id);
    }
    $countStmt->execute([$conversation_id]);

    $sender = getUserById($sender_id);
    createNotification(
        $receiver_id,
        'message',
        'New Message',
        (($sender['full_name'] ?? 'Someone') . ' sent you a message about ' . ($conversation['room_title'] ?? 'a room')),
        '/pages/messages.php?conversation=' . $conversation_id,
        ['conversation_id' => $conversation_id, 'message_id' => $message_id]
    );

    return [
        'success' => true,
        'message' => 'Message sent',
        'message_id' => $message_id,
        'created_at' => date('Y-m-d H:i:s'),
        'is_delivered' => 0,
        'is_read' => 0,
    ];
}

function getUserConversations($user_id) {
    global $conn;

    if (!tableExists('conversations')) {
        return [];
    }

    $stmt = $conn->prepare(
        'SELECT c.*, r.title as room_title, r.price, r.location, r.is_verified as room_verified,
            (SELECT image_url FROM room_images WHERE room_id = r.id AND is_primary = 1 LIMIT 1) as room_image,
            CASE WHEN c.tenant_id = ? THEN c.landlord_id ELSE c.tenant_id END as other_user_id,
            CASE WHEN c.tenant_id = ? THEN lp.full_name ELSE tp.full_name END as other_user_name,
            CASE WHEN c.tenant_id = ? THEN lp.avatar ELSE tp.avatar END as other_user_avatar,
            CASE WHEN c.tenant_id = ? THEN lp.trust_score ELSE tp.trust_score END as other_user_trust,
            CASE WHEN c.tenant_id = ? THEN c.tenant_unread_count ELSE c.landlord_unread_count END as unread_count
         FROM conversations c
         JOIN rooms r ON c.room_id = r.id
         LEFT JOIN profiles tp ON c.tenant_id = tp.user_id
         LEFT JOIN profiles lp ON c.landlord_id = lp.user_id
         WHERE c.tenant_id = ? OR c.landlord_id = ?
         ORDER BY COALESCE(c.last_message_time, c.updated_at, c.created_at) DESC'
    );
    $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
    return $stmt->fetchAll();
}

function getConversationMessages($conversation_id, $user_id) {
    global $conn;

    if (!tableExists('conversations') || !tableExists('messages')) {
        return ['success' => false, 'message' => 'Messaging is not available yet'];
    }

    $stmt = $conn->prepare('SELECT c.*, r.title as room_title, r.price, r.location FROM conversations c JOIN rooms r ON c.room_id = r.id WHERE c.id = ? AND (c.tenant_id = ? OR c.landlord_id = ?)');
    $stmt->execute([$conversation_id, $user_id, $user_id]);
    $conversation = $stmt->fetch();
    if (!$conversation) {
        return ['success' => false, 'message' => 'Access denied'];
    }

    $markRead = $conn->prepare('UPDATE messages SET is_read = 1, read_at = NOW() WHERE conversation_id = ? AND receiver_id = ? AND is_read = 0');
    $markRead->execute([$conversation_id, $user_id]);

    if ((int) $user_id === (int) $conversation['tenant_id']) {
        $reset = $conn->prepare('UPDATE conversations SET tenant_unread_count = 0 WHERE id = ?');
    } else {
        $reset = $conn->prepare('UPDATE conversations SET landlord_unread_count = 0 WHERE id = ?');
    }
    $reset->execute([$conversation_id]);

    $stmt = $conn->prepare('SELECT m.*, u.email, p.full_name, p.avatar FROM messages m JOIN users u ON m.sender_id = u.id LEFT JOIN profiles p ON u.id = p.user_id WHERE m.conversation_id = ? ORDER BY m.created_at ASC, m.id ASC');
    $stmt->execute([$conversation_id]);

    return [
        'success' => true,
        'conversation' => $conversation,
        'messages' => $stmt->fetchAll(),
    ];
}

function getUnreadMessageCount($user_id) {
    $conversations = getUserConversations($user_id);
    $total = 0;
    foreach ($conversations as $conversation) {
        $total += (int) ($conversation['unread_count'] ?? 0);
    }
    return $total;
}

function acceptContactRequest($conversation_id, $landlord_id) {
    global $conn;

    if (!tableExists('conversations')) {
        return ['success' => false, 'message' => 'Messaging is not available yet'];
    }

    $stmt = $conn->prepare('UPDATE conversations SET status = ? WHERE id = ? AND landlord_id = ?');
    $stmt->execute(['accepted', $conversation_id, $landlord_id]);
    if ($stmt->rowCount() < 1) {
        return ['success' => false, 'message' => 'Conversation not found'];
    }

    if (tableExists('contact_requests')) {
        $stmt = $conn->prepare('UPDATE contact_requests cr JOIN conversations c ON cr.room_id = c.room_id AND cr.tenant_id = c.tenant_id AND cr.landlord_id = c.landlord_id SET cr.status = ?, cr.viewed_by_landlord = 1, cr.viewed_at = NOW(), cr.updated_at = NOW() WHERE c.id = ?');
        $stmt->execute(['accepted', $conversation_id]);
    }

    $stmt = $conn->prepare('SELECT c.*, r.title FROM conversations c JOIN rooms r ON c.room_id = r.id WHERE c.id = ?');
    $stmt->execute([$conversation_id]);
    $conv = $stmt->fetch();
    if ($conv) {
        createNotification(
            (int) $conv['tenant_id'],
            'contact_request',
            'Request Accepted',
            'Your contact request for ' . $conv['title'] . ' has been accepted. You can now chat.',
            '/pages/messages.php?conversation=' . $conversation_id
        );
    }

    updateResponseStats($landlord_id);
    return ['success' => true, 'message' => 'Contact request accepted'];
}

function declineContactRequest($conversation_id, $landlord_id) {
    global $conn;

    if (!tableExists('conversations')) {
        return ['success' => false, 'message' => 'Messaging is not available yet'];
    }

    $stmt = $conn->prepare('UPDATE conversations SET status = ? WHERE id = ? AND landlord_id = ?');
    $stmt->execute(['declined', $conversation_id, $landlord_id]);
    if ($stmt->rowCount() < 1) {
        return ['success' => false, 'message' => 'Conversation not found'];
    }

    if (tableExists('contact_requests')) {
        $stmt = $conn->prepare('UPDATE contact_requests cr JOIN conversations c ON cr.room_id = c.room_id AND cr.tenant_id = c.tenant_id AND cr.landlord_id = c.landlord_id SET cr.status = ?, cr.viewed_by_landlord = 1, cr.viewed_at = NOW(), cr.updated_at = NOW() WHERE c.id = ?');
        $stmt->execute(['declined', $conversation_id]);
    }

    $stmt = $conn->prepare('SELECT c.*, r.title FROM conversations c JOIN rooms r ON c.room_id = r.id WHERE c.id = ?');
    $stmt->execute([$conversation_id]);
    $conv = $stmt->fetch();
    if ($conv) {
        createNotification(
            (int) $conv['tenant_id'],
            'contact_request',
            'Request Declined',
            'Your contact request for ' . $conv['title'] . ' was declined.',
            '/pages/messages.php?conversation=' . $conversation_id
        );
    }

    updateResponseStats($landlord_id);
    return ['success' => true, 'message' => 'Contact request declined'];
}

function canSendMessage($user_id, $conversation_id) {
    global $conn;

    if (!tableExists('conversations')) {
        return false;
    }

    $stmt = $conn->prepare('SELECT status, tenant_id, landlord_id FROM conversations WHERE id = ?');
    $stmt->execute([$conversation_id]);
    $conv = $stmt->fetch();
    if (!$conv) {
        return false;
    }

    if ((int) $user_id === (int) $conv['landlord_id']) {
        return $conv['status'] === 'accepted';
    }

    if ((int) $user_id === (int) $conv['tenant_id']) {
        return $conv['status'] === 'accepted';
    }

    return false;
}

function isConversationParticipant($conversation_id, $user_id) {
    global $conn;

    if (!tableExists('conversations')) {
        return false;
    }

    $stmt = $conn->prepare('SELECT id FROM conversations WHERE id = ? AND (tenant_id = ? OR landlord_id = ?)');
    $stmt->execute([$conversation_id, $user_id, $user_id]);
    return (bool) $stmt->fetch();
}

function updateTypingStatus($conversation_id, $user_id, $is_typing) {
    global $conn;

    if (!tableExists('typing_status') || !isConversationParticipant($conversation_id, $user_id) || !canSendMessage($user_id, $conversation_id)) {
        return false;
    }

    $stmt = $conn->prepare('INSERT INTO typing_status (conversation_id, user_id, is_typing) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE is_typing = VALUES(is_typing), updated_at = CURRENT_TIMESTAMP');
    $stmt->execute([$conversation_id, $user_id, $is_typing ? 1 : 0]);
    return true;
}

function getTypingStatus($conversation_id, $user_id) {
    global $conn;

    if (!tableExists('typing_status')) {
        return [];
    }

    $stmt = $conn->prepare('SELECT user_id, is_typing, updated_at FROM typing_status WHERE conversation_id = ? AND user_id != ? AND is_typing = 1 AND updated_at > DATE_SUB(NOW(), INTERVAL 2 SECOND)');
    $stmt->execute([$conversation_id, $user_id]);
    return $stmt->fetchAll();
}

function createNotification($user_id, $type, $title, $message, $link = null, $data = null) {
    global $conn;

    if (!tableExists('notifications')) {
        return 0;
    }

    $stmt = $conn->prepare('INSERT INTO notifications (user_id, type, title, message, link, data) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$user_id, $type, $title, $message, $link, $data ? json_encode($data) : null]);

    if (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] === (int) $user_id) {
        $_SESSION['unread_notifications'] = getUnreadNotificationCount($user_id);
    }

    return (int) $conn->lastInsertId();
}

function getUserNotifications($user_id, $limit = 20, $offset = 0) {
    global $conn;

    if (!tableExists('notifications')) {
        return [];
    }

    $stmt = $conn->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?');
    $stmt->bindValue(1, (int) $user_id, 1);
    $stmt->bindValue(2, (int) $limit, 1);
    $stmt->bindValue(3, (int) $offset, 1);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getUnreadNotificationCount($user_id) {
    global $conn;

    if (!tableExists('notifications')) {
        return 0;
    }

    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$user_id]);
    return (int) ($stmt->fetch()['count'] ?? 0);
}

function markNotificationRead($notification_id, $user_id) {
    global $conn;

    if (!tableExists('notifications')) {
        return;
    }

    $stmt = $conn->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?');
    $stmt->execute([$notification_id, $user_id]);
    $_SESSION['unread_notifications'] = getUnreadNotificationCount($user_id);
}

function markAllNotificationsRead($user_id) {
    global $conn;

    if (!tableExists('notifications')) {
        return;
    }

    $stmt = $conn->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$user_id]);
    $_SESSION['unread_notifications'] = 0;
}

function deleteNotification($notification_id, $user_id) {
    global $conn;

    if (!tableExists('notifications')) {
        return;
    }

    $stmt = $conn->prepare('DELETE FROM notifications WHERE id = ? AND user_id = ?');
    $stmt->execute([$notification_id, $user_id]);
    $_SESSION['unread_notifications'] = getUnreadNotificationCount($user_id);
}

function createContactRequest($room_id, $tenant_id, $message = '') {
    global $conn;

    if (!tableExists('conversations') || !tableExists('contact_requests')) {
        return ['success' => false, 'message' => 'Messaging is not available yet'];
    }

    $room = getRoomDetails($room_id);
    if (!$room || ($room['status'] ?? '') !== 'active') {
        return ['success' => false, 'message' => 'Room not found'];
    }

    if ((int) $room['landlord_id'] === (int) $tenant_id) {
        return ['success' => false, 'message' => 'You cannot contact yourself about your own listing'];
    }

    $stmt = $conn->prepare('SELECT * FROM contact_requests WHERE room_id = ? AND tenant_id = ?');
    $stmt->execute([$room_id, $tenant_id]);
    $existing_request = $stmt->fetch();

    $stmt = $conn->prepare('SELECT id FROM conversations WHERE room_id = ? AND tenant_id = ? AND landlord_id = ?');
    $stmt->execute([$room_id, $tenant_id, $room['landlord_id']]);
    $existing_conversation = $stmt->fetch();
    if ($existing_conversation) {
        return [
            'success' => true,
            'message' => $existing_request ? 'You already requested this room' : 'Conversation already exists',
            'redirect' => SITE_URL . '/pages/messages.php?conversation=' . $existing_conversation['id'],
            'conversation_id' => (int) $existing_conversation['id'],
        ];
    }

    $conversation_id = getOrCreateConversation($room_id, $tenant_id, (int) $room['landlord_id']);
    if (!$conversation_id) {
        return ['success' => false, 'message' => 'Could not create conversation'];
    }

    $default_message = "Hello, I'm interested in your room: {$room['title']}. Is it still available?";
    $send_result = sendMessage($conversation_id, $tenant_id, $message !== '' ? $message : $default_message);
    if (!$send_result['success']) {
        return $send_result;
    }

    return [
        'success' => true,
        'message' => 'Contact request sent successfully',
        'conversation_id' => $conversation_id,
        'redirect' => SITE_URL . '/pages/messages.php?conversation=' . $conversation_id,
    ];
}

function getPendingRequests($landlord_id) {
    global $conn;

    if (!tableExists('contact_requests')) {
        return [];
    }

    $stmt = $conn->prepare('SELECT cr.*, r.title as room_title, r.price,
        p.full_name as tenant_name, p.avatar as tenant_avatar, p.trust_score as tenant_trust,
        (SELECT image_url FROM room_images WHERE room_id = r.id AND is_primary = 1 LIMIT 1) as room_image
        FROM contact_requests cr
        JOIN rooms r ON cr.room_id = r.id
        LEFT JOIN profiles p ON cr.tenant_id = p.user_id
        WHERE cr.landlord_id = ? AND cr.status = ?
        ORDER BY cr.created_at DESC');
    $stmt->execute([$landlord_id, 'pending']);
    return $stmt->fetchAll();
}

function updateResponseStats($landlord_id) {
    global $conn;

    if (!tableExists('contact_requests') || !tableExists('messages') || !tableExists('conversations') || !tableExists('profiles')) {
        return;
    }

    $accepted_stmt = $conn->prepare('SELECT COUNT(*) as count FROM contact_requests WHERE landlord_id = ? AND status = ?');
    $accepted_stmt->execute([$landlord_id, 'accepted']);
    $accepted_count = (int) ($accepted_stmt->fetch()['count'] ?? 0);

    $total_stmt = $conn->prepare('SELECT COUNT(*) as count FROM contact_requests WHERE landlord_id = ?');
    $total_stmt->execute([$landlord_id]);
    $total_requests = (int) ($total_stmt->fetch()['count'] ?? 0);

    $avg_stmt = $conn->prepare('SELECT AVG(TIMESTAMPDIFF(MINUTE, cr.created_at, first_reply.created_at)) as avg_response_time
        FROM contact_requests cr
        JOIN conversations c ON c.room_id = cr.room_id AND c.tenant_id = cr.tenant_id AND c.landlord_id = cr.landlord_id
        JOIN messages first_reply ON first_reply.id = (
            SELECT m.id FROM messages m
            WHERE m.conversation_id = c.id AND m.sender_id = cr.landlord_id
            ORDER BY m.created_at ASC, m.id ASC LIMIT 1
        )
        WHERE cr.landlord_id = ? AND cr.status = ?');
    $avg_stmt->execute([$landlord_id, 'accepted']);
    $avg_response_time = (int) round((float) ($avg_stmt->fetch()['avg_response_time'] ?? 0));

    $response_rate = $total_requests > 0 ? round(($accepted_count * 100) / $total_requests, 2) : 0;

    $update = $conn->prepare('UPDATE profiles SET response_rate = ?, response_time = ?, total_responses = ?, avg_response_time = ? WHERE user_id = ?');
    $update->execute([$response_rate, $avg_response_time, $accepted_count, $avg_response_time, $landlord_id]);
}



/**
 * CONTRACTS & REVIEWS
 */
function generateContractNumber() {
    global $conn;

    if (!tableExists('contracts')) {
        return 'GHB-' . date('Ym') . '-' . strtoupper(substr(uniqid(), -6));
    }

    do {
        $number = 'GHB-' . date('Ym') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $stmt = $conn->prepare('SELECT id FROM contracts WHERE contract_number = ?');
        $stmt->execute([$number]);
    } while ($stmt->fetch());

    return $number;
}

function ordinal($number) {
    $number = (int) $number;
    if (($number % 100) >= 11 && ($number % 100) <= 13) {
        return $number . 'th';
    }

    $suffixes = ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'];
    return $number . $suffixes[$number % 10];
}

function updateContractProfileStats($user_id) {
    global $conn;

    if (!tableExists('contracts') || !tableExists('profiles')) {
        return;
    }

    $stmt = $conn->prepare("SELECT
        COUNT(*) as total_contracts,
        SUM(CASE WHEN status IN ('active', 'expired', 'terminated') THEN 1 ELSE 0 END) as completed_contracts,
        SUM(CASE WHEN status = 'terminated' THEN 1 ELSE 0 END) as cancelled_contracts
        FROM contracts
        WHERE tenant_id = ? OR landlord_id = ?");
    $stmt->execute([$user_id, $user_id]);
    $stats = $stmt->fetch();

    $update = $conn->prepare('UPDATE profiles SET total_contracts = ?, completed_contracts = ?, cancelled_contracts = ? WHERE user_id = ?');
    $update->execute([
        (int) ($stats['total_contracts'] ?? 0),
        (int) ($stats['completed_contracts'] ?? 0),
        (int) ($stats['cancelled_contracts'] ?? 0),
        $user_id,
    ]);
}

function createContract($data) {
    global $conn;

    if (!tableExists('contracts')) {
        return ['success' => false, 'message' => 'Contracts are not available yet'];
    }

    $required = ['room_id', 'tenant_id', 'landlord_id', 'monthly_rent', 'advance_amount', 'deposit_amount', 'contract_start_date'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            return ['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required'];
        }
    }

    $room = getRoomDetails((int) $data['room_id']);
    if (!$room) {
        return ['success' => false, 'message' => 'Room not found'];
    }
    if ((int) $room['landlord_id'] !== (int) $data['landlord_id']) {
        return ['success' => false, 'message' => 'You do not own this room'];
    }
    if ((int) $data['tenant_id'] === (int) $data['landlord_id']) {
        return ['success' => false, 'message' => 'Landlord and tenant cannot be the same user'];
    }

    try {
        $conn->beginTransaction();

        $contract_number = generateContractNumber();
        $stmt = $conn->prepare("INSERT INTO contracts (
            room_id, tenant_id, landlord_id, conversation_id, contract_number,
            monthly_rent, advance_amount, deposit_amount,
            contract_start_date, contract_end_date, is_indefinite,
            notice_period, payment_day,
            utilities_included, electricity_charge, water_charge,
            internet_charge, maintenance_charge, parking_charge,
            guest_policy, pet_policy, smoking_policy, noise_policy, additional_rules,
            status
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?,
            ?, ?,
            ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?, ?,
            'draft'
        )");

        $stmt->execute([
            (int) $data['room_id'],
            (int) $data['tenant_id'],
            (int) $data['landlord_id'],
            !empty($data['conversation_id']) ? (int) $data['conversation_id'] : null,
            $contract_number,
            (float) $data['monthly_rent'],
            (float) $data['advance_amount'],
            (float) $data['deposit_amount'],
            $data['contract_start_date'],
            !empty($data['is_indefinite']) ? null : ($data['contract_end_date'] ?? null),
            !empty($data['is_indefinite']) ? 1 : 0,
            (int) ($data['notice_period'] ?? 30),
            (int) ($data['payment_day'] ?? 1),
            !empty($data['utilities_included']) ? 1 : 0,
            $data['electricity_charge'] ?? null,
            $data['water_charge'] ?? null,
            $data['internet_charge'] ?? null,
            $data['maintenance_charge'] ?? null,
            $data['parking_charge'] ?? null,
            $data['guest_policy'] ?? null,
            $data['pet_policy'] ?? null,
            $data['smoking_policy'] ?? null,
            $data['noise_policy'] ?? null,
            $data['additional_rules'] ?? null,
        ]);

        $contract_id = (int) $conn->lastInsertId();
        updateContractProfileStats((int) $data['tenant_id']);
        updateContractProfileStats((int) $data['landlord_id']);

        $tenant = getUserById((int) $data['tenant_id']);
        $landlord = getUserById((int) $data['landlord_id']);

        createNotification(
            (int) $data['landlord_id'],
            'contract',
            'New Contract Ready',
            'A new contract for ' . $room['title'] . ' has been created with ' . ($tenant['full_name'] ?? 'the tenant') . '.',
            '/pages/contract-detail.php?id=' . $contract_id
        );
        createNotification(
            (int) $data['tenant_id'],
            'contract',
            'New Contract Ready',
            'A new contract for ' . $room['title'] . ' with ' . ($landlord['full_name'] ?? 'the landlord') . ' is ready for review.',
            '/pages/contract-detail.php?id=' . $contract_id
        );

        logActivity((int) $data['landlord_id'], 'CONTRACT_CREATED', 'Created contract #' . $contract_number . ' for room #' . $room['id']);
        $conn->commit();

        return [
            'success' => true,
            'message' => 'Contract created successfully',
            'contract_id' => $contract_id,
            'contract_number' => $contract_number,
        ];
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        return ['success' => false, 'message' => 'Failed to create contract: ' . $e->getMessage()];
    }
}

function getContractDetails($contract_id) {
    global $conn;

    if (!tableExists('contracts')) {
        return null;
    }

    $stmt = $conn->prepare("SELECT c.*, 
        r.title as room_title, r.location, r.address, r.price as original_price,
        (SELECT image_url FROM room_images WHERE room_id = r.id AND is_primary = 1 LIMIT 1) as room_image,
        tp.full_name as tenant_name, tp.phone as tenant_phone, tp.avatar as tenant_avatar, tp.trust_score as tenant_trust,
        t.email as tenant_email,
        lp.full_name as landlord_name, lp.phone as landlord_phone, lp.avatar as landlord_avatar, lp.trust_score as landlord_trust,
        l.email as landlord_email
        FROM contracts c
        JOIN rooms r ON c.room_id = r.id
        JOIN users t ON c.tenant_id = t.id
        LEFT JOIN profiles tp ON t.id = tp.user_id
        JOIN users l ON c.landlord_id = l.id
        LEFT JOIN profiles lp ON l.id = lp.user_id
        WHERE c.id = ?");
    $stmt->execute([$contract_id]);
    return $stmt->fetch() ?: null;
}

function getUserContracts($user_id, $role = null) {
    global $conn;

    if (!tableExists('contracts')) {
        return [];
    }

    if ($role === 'tenant') {
        $stmt = $conn->prepare("SELECT c.*, r.title as room_title, r.location,
            (SELECT image_url FROM room_images WHERE room_id = r.id AND is_primary = 1 LIMIT 1) as room_image,
            lp.full_name as other_party_name, lp.avatar as other_party_avatar
            FROM contracts c
            JOIN rooms r ON c.room_id = r.id
            LEFT JOIN profiles lp ON c.landlord_id = lp.user_id
            WHERE c.tenant_id = ?
            ORDER BY c.created_at DESC");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }

    if ($role === 'landlord') {
        $stmt = $conn->prepare("SELECT c.*, r.title as room_title, r.location,
            (SELECT image_url FROM room_images WHERE room_id = r.id AND is_primary = 1 LIMIT 1) as room_image,
            tp.full_name as other_party_name, tp.avatar as other_party_avatar
            FROM contracts c
            JOIN rooms r ON c.room_id = r.id
            LEFT JOIN profiles tp ON c.tenant_id = tp.user_id
            WHERE c.landlord_id = ?
            ORDER BY c.created_at DESC");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }

    $stmt = $conn->prepare("SELECT c.*, r.title as room_title, r.location,
        (SELECT image_url FROM room_images WHERE room_id = r.id AND is_primary = 1 LIMIT 1) as room_image,
        CASE WHEN c.tenant_id = ? THEN lp.full_name ELSE tp.full_name END as other_party_name,
        CASE WHEN c.tenant_id = ? THEN lp.avatar ELSE tp.avatar END as other_party_avatar
        FROM contracts c
        JOIN rooms r ON c.room_id = r.id
        LEFT JOIN profiles tp ON c.tenant_id = tp.user_id
        LEFT JOIN profiles lp ON c.landlord_id = lp.user_id
        WHERE c.tenant_id = ? OR c.landlord_id = ?
        ORDER BY c.created_at DESC");
    $stmt->execute([$user_id, $user_id, $user_id, $user_id]);
    return $stmt->fetchAll();
}

function signContract($contract_id, $user_id, $signature_data = null) {
    global $conn;

    $contract = getContractDetails($contract_id);
    if (!$contract) {
        return ['success' => false, 'message' => 'Contract not found'];
    }

    $is_tenant = (int) $user_id === (int) $contract['tenant_id'];
    $is_landlord = (int) $user_id === (int) $contract['landlord_id'];
    if (!$is_tenant && !$is_landlord) {
        return ['success' => false, 'message' => 'You are not authorized to sign this contract'];
    }

    if ($contract['status'] === 'terminated') {
        return ['success' => false, 'message' => 'This contract has been terminated'];
    }

    if (($is_tenant && !empty($contract['tenant_signed_at'])) || ($is_landlord && !empty($contract['landlord_signed_at']))) {
        return ['success' => false, 'message' => 'You have already signed this contract'];
    }

    try {
        $conn->beginTransaction();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $signature_data = $signature_data ?: ('Signed by user #' . $user_id . ' on ' . date('Y-m-d H:i:s'));

        if ($is_tenant) {
            $stmt = $conn->prepare("UPDATE contracts SET
                tenant_signature = ?, tenant_signed_at = NOW(), tenant_ip = ?, tenant_agreed = 1,
                status = CASE WHEN landlord_agreed = 1 THEN 'active' ELSE 'pending_landlord' END,
                signed_at = CASE WHEN landlord_agreed = 1 THEN NOW() ELSE signed_at END
                WHERE id = ?");
            $stmt->execute([$signature_data, $ip, $contract_id]);

            createNotification(
                (int) $contract['landlord_id'],
                'contract',
                'Contract Signed by Tenant',
                ($contract['tenant_name'] ?? 'The tenant') . ' has signed the contract for ' . $contract['room_title'] . '.',
                '/pages/contract-detail.php?id=' . $contract_id
            );
        } else {
            $stmt = $conn->prepare("UPDATE contracts SET
                landlord_signature = ?, landlord_signed_at = NOW(), landlord_ip = ?, landlord_agreed = 1,
                status = CASE WHEN tenant_agreed = 1 THEN 'active' ELSE 'pending_tenant' END,
                signed_at = CASE WHEN tenant_agreed = 1 THEN NOW() ELSE signed_at END
                WHERE id = ?");
            $stmt->execute([$signature_data, $ip, $contract_id]);

            createNotification(
                (int) $contract['tenant_id'],
                'contract',
                'Contract Signed by Landlord',
                ($contract['landlord_name'] ?? 'The landlord') . ' has signed the contract for ' . $contract['room_title'] . '.',
                '/pages/contract-detail.php?id=' . $contract_id
            );
        }

        $updated = getContractDetails($contract_id);
        $both_signed = !empty($updated['tenant_agreed']) && !empty($updated['landlord_agreed']);
        if ($both_signed) {
            $roomUpdate = $conn->prepare("UPDATE rooms SET status = 'booked' WHERE id = ?");
            $roomUpdate->execute([(int) $updated['room_id']]);
            updateContractProfileStats((int) $updated['tenant_id']);
            updateContractProfileStats((int) $updated['landlord_id']);
            logActivity((int) $updated['tenant_id'], 'CONTRACT_SIGNED', 'Signed contract #' . $updated['contract_number']);
            logActivity((int) $updated['landlord_id'], 'CONTRACT_SIGNED', 'Signed contract #' . $updated['contract_number']);
        }

        $conn->commit();
        return [
            'success' => true,
            'message' => 'Contract signed successfully',
            'status' => $updated['status'],
            'both_signed' => $both_signed,
        ];
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        return ['success' => false, 'message' => 'Failed to sign contract: ' . $e->getMessage()];
    }
}

function generateContractHTML($contract) {
    $escape = static function ($value) {
        return nl2br(htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8'));
    };

    $date = date('F d, Y');
    $start_date = date('F d, Y', strtotime($contract['contract_start_date']));
    $end_date = !empty($contract['is_indefinite']) ? 'Indefinite' : (!empty($contract['contract_end_date']) ? date('F d, Y', strtotime($contract['contract_end_date'])) : 'Not specified');

    return '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Rental Agreement - ' . htmlspecialchars($contract['contract_number']) . '</title>
<style>
body{font-family:Arial,sans-serif;line-height:1.6;max-width:820px;margin:0 auto;padding:2rem;color:#222;}
h1{color:#0f4f4c;text-align:center;margin-bottom:2rem;}
h2{border-bottom:2px solid #d8a73f;padding-bottom:0.35rem;margin-top:2rem;}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;}
.card{border:1px solid #d9e1e1;border-radius:12px;padding:1rem;}
.signature-line{border-top:1px solid #333;margin-top:2rem;padding-top:0.5rem;width:80%;}
.footer{margin-top:3rem;font-size:0.9rem;color:#666;text-align:center;}
</style>
</head>
<body>
<div style="text-align:right;color:#666;">Contract #: ' . htmlspecialchars($contract['contract_number']) . '</div>
<h1>RENTAL AGREEMENT</h1>
<p>This Rental Agreement is made on ' . $date . ' between the parties below for the rental of the listed property.</p>
<h2>Parties</h2>
<div class="grid">
<div class="card"><strong>Landlord</strong><br>' . $escape($contract['landlord_name']) . '<br>Phone: ' . $escape($contract['landlord_phone']) . '<br>Email: ' . $escape($contract['landlord_email']) . '</div>
<div class="card"><strong>Tenant</strong><br>' . $escape($contract['tenant_name']) . '<br>Phone: ' . $escape($contract['tenant_phone']) . '<br>Email: ' . $escape($contract['tenant_email']) . '</div>
</div>
<h2>Property</h2>
<p><strong>Room:</strong> ' . $escape($contract['room_title']) . '<br><strong>Location:</strong> ' . $escape($contract['location']) . '<br><strong>Address:</strong> ' . $escape($contract['address']) . '</p>
<h2>Term and Rent</h2>
<p><strong>Start Date:</strong> ' . $start_date . '<br><strong>End Date:</strong> ' . $end_date . '<br><strong>Monthly Rent:</strong> Rs. ' . number_format((float) $contract['monthly_rent'], 2) . '<br><strong>Advance Payment:</strong> Rs. ' . number_format((float) $contract['advance_amount'], 2) . '<br><strong>Security Deposit:</strong> Rs. ' . number_format((float) $contract['deposit_amount'], 2) . '<br><strong>Payment Day:</strong> ' . ordinal((int) $contract['payment_day']) . ' of each month<br><strong>Notice Period:</strong> ' . (int) $contract['notice_period'] . ' days</p>
<h2>Utilities</h2>
<p>' . (!empty($contract['utilities_included']) ? 'All utilities are included in the rent.' : 'Utilities are billed separately unless otherwise stated.') . '</p>
' . (!empty($contract['electricity_charge']) ? '<p><strong>Electricity:</strong> ' . $escape($contract['electricity_charge']) . '</p>' : '') . '
' . (!empty($contract['water_charge']) ? '<p><strong>Water:</strong> ' . $escape($contract['water_charge']) . '</p>' : '') . '
' . (!empty($contract['internet_charge']) ? '<p><strong>Internet:</strong> ' . $escape($contract['internet_charge']) . '</p>' : '') . '
' . (!empty($contract['maintenance_charge']) ? '<p><strong>Maintenance:</strong> ' . $escape($contract['maintenance_charge']) . '</p>' : '') . '
' . (!empty($contract['parking_charge']) ? '<p><strong>Parking:</strong> ' . $escape($contract['parking_charge']) . '</p>' : '') . '
<h2>House Rules</h2>
' . (!empty($contract['guest_policy']) ? '<p><strong>Guest Policy:</strong><br>' . $escape($contract['guest_policy']) . '</p>' : '') . '
' . (!empty($contract['pet_policy']) ? '<p><strong>Pet Policy:</strong><br>' . $escape($contract['pet_policy']) . '</p>' : '') . '
' . (!empty($contract['smoking_policy']) ? '<p><strong>Smoking Policy:</strong><br>' . $escape($contract['smoking_policy']) . '</p>' : '') . '
' . (!empty($contract['noise_policy']) ? '<p><strong>Noise Policy:</strong><br>' . $escape($contract['noise_policy']) . '</p>' : '') . '
' . (!empty($contract['additional_rules']) ? '<p><strong>Additional Rules:</strong><br>' . $escape($contract['additional_rules']) . '</p>' : '') . '
<h2>Signatures</h2>
<div class="grid">
<div><strong>Landlord</strong>' . (!empty($contract['landlord_signed_at']) ? '<p>Signed on ' . date('F d, Y h:i A', strtotime($contract['landlord_signed_at'])) . '<br>IP: ' . $escape($contract['landlord_ip']) . '</p>' : '<div class="signature-line">Signature</div>') . '<p>' . $escape($contract['landlord_name']) . '</p></div>
<div><strong>Tenant</strong>' . (!empty($contract['tenant_signed_at']) ? '<p>Signed on ' . date('F d, Y h:i A', strtotime($contract['tenant_signed_at'])) . '<br>IP: ' . $escape($contract['tenant_ip']) . '</p>' : '<div class="signature-line">Signature</div>') . '<p>' . $escape($contract['tenant_name']) . '</p></div>
</div>
<div class="footer">Generated electronically on Gharbeti.</div>
</body>
</html>';
}

function generateContractPDF($contract_id) {
    global $conn;

    $contract = getContractDetails($contract_id);
    if (!$contract) {
        return ['success' => false, 'message' => 'Contract not found'];
    }

    $directory = UPLOAD_PATH . 'contracts/';
    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $filename = 'contract_' . preg_replace('/[^A-Za-z0-9\-_]/', '_', $contract['contract_number']) . '.html';
    $filepath = $directory . $filename;
    file_put_contents($filepath, generateContractHTML($contract));

    $stmt = $conn->prepare('UPDATE contracts SET contract_pdf = ? WHERE id = ?');
    $stmt->execute([$filename, $contract_id]);

    return [
        'success' => true,
        'pdf_path' => $filename,
        'pdf_url' => SITE_URL . '/assets/uploads/contracts/' . $filename,
    ];
}

function terminateContract($contract_id, $user_id, $reason) {
    global $conn;

    $contract = getContractDetails($contract_id);
    if (!$contract) {
        return ['success' => false, 'message' => 'Contract not found'];
    }
    if ((int) $user_id !== (int) $contract['tenant_id'] && (int) $user_id !== (int) $contract['landlord_id']) {
        return ['success' => false, 'message' => 'Not authorized'];
    }

    $stmt = $conn->prepare("UPDATE contracts SET status = 'terminated', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$contract_id]);

    $roomUpdate = $conn->prepare("UPDATE rooms SET status = 'active' WHERE id = ?");
    $roomUpdate->execute([(int) $contract['room_id']]);

    $other_id = (int) $user_id === (int) $contract['tenant_id'] ? (int) $contract['landlord_id'] : (int) $contract['tenant_id'];
    createNotification(
        $other_id,
        'contract',
        'Contract Terminated',
        'The contract for ' . $contract['room_title'] . ' has been terminated. Reason: ' . $reason,
        '/pages/contract-detail.php?id=' . $contract_id
    );

    updateContractProfileStats((int) $contract['tenant_id']);
    updateContractProfileStats((int) $contract['landlord_id']);
    logActivity($user_id, 'CONTRACT_TERMINATED', 'Terminated contract #' . $contract['contract_number']);

    return ['success' => true, 'message' => 'Contract terminated'];
}

function hasUserReviewed($contract_id, $user_id) {
    global $conn;

    if (!tableExists('reviews')) {
        return false;
    }

    $stmt = $conn->prepare('SELECT id FROM reviews WHERE contract_id = ? AND reviewer_id = ?');
    $stmt->execute([$contract_id, $user_id]);
    return (bool) $stmt->fetch();
}

function createReview($data) {
    global $conn;

    if (!tableExists('reviews')) {
        return ['success' => false, 'message' => 'Reviews are not available yet'];
    }

    $required = ['contract_id', 'reviewer_id', 'reviewee_id', 'rating_accuracy', 'rating_communication', 'rating_cleanliness', 'rating_value', 'review_text'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            return ['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required'];
        }
    }

    if (hasUserReviewed((int) $data['contract_id'], (int) $data['reviewer_id'])) {
        return ['success' => false, 'message' => 'Review already exists for this contract'];
    }

    $contract = getContractDetails((int) $data['contract_id']);
    if (!$contract || !in_array($contract['status'], ['active', 'terminated', 'expired'], true)) {
        return ['success' => false, 'message' => 'You can only review a signed contract'];
    }

    $allowed_reviewer = (int) $data['reviewer_id'] === (int) $contract['tenant_id'] || (int) $data['reviewer_id'] === (int) $contract['landlord_id'];
    $allowed_reviewee = (int) $data['reviewee_id'] === (int) $contract['tenant_id'] || (int) $data['reviewee_id'] === (int) $contract['landlord_id'];
    if (!$allowed_reviewer || !$allowed_reviewee || (int) $data['reviewer_id'] === (int) $data['reviewee_id']) {
        return ['success' => false, 'message' => 'Invalid review parties'];
    }

    $ratings = [
        (int) $data['rating_accuracy'],
        (int) $data['rating_communication'],
        (int) $data['rating_cleanliness'],
        (int) $data['rating_value'],
    ];
    foreach ($ratings as $rating) {
        if ($rating < 1 || $rating > 5) {
            return ['success' => false, 'message' => 'Ratings must be between 1 and 5'];
        }
    }

    $rating_overall = round(array_sum($ratings) / count($ratings), 2);

    try {
        $stmt = $conn->prepare('INSERT INTO reviews (
            contract_id, reviewer_id, reviewee_id,
            rating_accuracy, rating_communication, rating_cleanliness, rating_value, rating_overall,
            review_text, is_anonymous, is_recommended, is_verified
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
        $stmt->execute([
            (int) $data['contract_id'],
            (int) $data['reviewer_id'],
            (int) $data['reviewee_id'],
            $ratings[0],
            $ratings[1],
            $ratings[2],
            $ratings[3],
            $rating_overall,
            $data['review_text'],
            !empty($data['is_anonymous']) ? 1 : 0,
            !empty($data['is_recommended']) ? 1 : 0,
        ]);

        $review_id = (int) $conn->lastInsertId();
        updateUserRatings((int) $data['reviewee_id']);

        $reviewer = getUserById((int) $data['reviewer_id']);
        createNotification(
            (int) $data['reviewee_id'],
            'review',
            'New Review Received',
            ($reviewer['full_name'] ?? 'Someone') . ' left you a review with ' . $rating_overall . ' stars.',
            '/pages/review-detail.php?id=' . $review_id
        );

        return ['success' => true, 'message' => 'Review submitted successfully', 'review_id' => $review_id];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to submit review: ' . $e->getMessage()];
    }
}

function updateUserRatings($user_id) {
    global $conn;

    if (!tableExists('reviews') || !tableExists('profiles')) {
        return;
    }

    $stmt = $conn->prepare('SELECT COUNT(*) as total_reviews, AVG(rating_overall) as avg_rating FROM reviews WHERE reviewee_id = ? AND is_verified = 1');
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();

    $update = $conn->prepare('UPDATE profiles SET total_reviews = ?, avg_rating = ? WHERE user_id = ?');
    $update->execute([
        (int) ($stats['total_reviews'] ?? 0),
        round((float) ($stats['avg_rating'] ?? 0), 2),
        $user_id,
    ]);
}

function getUserReviews($user_id, $as = 'reviewee') {
    global $conn;

    if (!tableExists('reviews')) {
        return [];
    }

    if ($as === 'reviewee') {
        $sql = "SELECT r.*, p.full_name as reviewer_name, p.avatar as reviewer_avatar, p.trust_score as reviewer_trust, rm.title as room_title
            FROM reviews r
            JOIN profiles p ON r.reviewer_id = p.user_id
            JOIN contracts c ON r.contract_id = c.id
            JOIN rooms rm ON c.room_id = rm.id
            WHERE r.reviewee_id = ?
            ORDER BY r.created_at DESC";
    } else {
        $sql = "SELECT r.*, p.full_name as reviewee_name, p.avatar as reviewee_avatar, rm.title as room_title
            FROM reviews r
            JOIN profiles p ON r.reviewee_id = p.user_id
            JOIN contracts c ON r.contract_id = c.id
            JOIN rooms rm ON c.room_id = rm.id
            WHERE r.reviewer_id = ?
            ORDER BY r.created_at DESC";
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function getReviewDetails($review_id) {
    global $conn;

    if (!tableExists('reviews')) {
        return null;
    }

    $stmt = $conn->prepare("SELECT r.*, 
        rev.full_name as reviewer_name, rev.avatar as reviewer_avatar, rev.trust_score as reviewer_trust,
        revi.full_name as reviewee_name, revi.avatar as reviewee_avatar, revi.trust_score as reviewee_trust,
        rm.title as room_title, c.contract_number
        FROM reviews r
        JOIN profiles rev ON r.reviewer_id = rev.user_id
        JOIN profiles revi ON r.reviewee_id = revi.user_id
        JOIN contracts c ON r.contract_id = c.id
        JOIN rooms rm ON c.room_id = rm.id
        WHERE r.id = ?");
    $stmt->execute([$review_id]);
    return $stmt->fetch() ?: null;
}

function markReviewHelpful($review_id, $user_id) {
    global $conn;

    if (!tableExists('review_helpful')) {
        return ['success' => false, 'message' => 'Review votes are not available yet'];
    }

    $check = $conn->prepare('SELECT id FROM review_helpful WHERE review_id = ? AND user_id = ?');
    $check->execute([$review_id, $user_id]);

    if ($check->fetch()) {
        $delete = $conn->prepare('DELETE FROM review_helpful WHERE review_id = ? AND user_id = ?');
        $delete->execute([$review_id, $user_id]);
        $update = $conn->prepare('UPDATE reviews SET is_helpful_count = GREATEST(is_helpful_count - 1, 0) WHERE id = ?');
        $update->execute([$review_id]);
        return ['success' => true, 'action' => 'removed'];
    }

    $insert = $conn->prepare('INSERT INTO review_helpful (review_id, user_id, is_helpful) VALUES (?, ?, 1)');
    $insert->execute([$review_id, $user_id]);
    $update = $conn->prepare('UPDATE reviews SET is_helpful_count = is_helpful_count + 1 WHERE id = ?');
    $update->execute([$review_id]);
    return ['success' => true, 'action' => 'added'];
}

function reportReview($review_id, $reporter_id, $reason, $description = '') {
    global $conn;

    if (!tableExists('review_reports')) {
        return ['success' => false, 'message' => 'Review reports are not available yet'];
    }

    $stmt = $conn->prepare('INSERT INTO review_reports (review_id, reporter_id, reason, description) VALUES (?, ?, ?, ?)');
    $stmt->execute([$review_id, $reporter_id, $reason, $description]);

    $update = $conn->prepare('UPDATE reviews SET reported_count = reported_count + 1 WHERE id = ?');
    $update->execute([$review_id]);

    $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM review_reports WHERE review_id = ? AND status = 'pending'");
    $countStmt->execute([$review_id]);
    $count = (int) ($countStmt->fetch()['count'] ?? 0);
    if ($count >= 3) {
        createNotification(1, 'system', 'Review Reported Multiple Times', 'Review #' . $review_id . ' has been reported ' . $count . ' times.', '/pages/review-detail.php?id=' . $review_id);
        addToModerationQueue('review', $review_id, 'review', 'high', 'Review reported ' . $count . ' times');
    }

    return ['success' => true, 'message' => 'Review reported successfully'];
}

function respondToReview($review_id, $landlord_id, $response) {
    global $conn;

    if (!tableExists('reviews')) {
        return ['success' => false, 'message' => 'Reviews are not available yet'];
    }

    $stmt = $conn->prepare('UPDATE reviews SET landlord_response = ?, responded_at = NOW() WHERE id = ? AND reviewee_id = ?');
    $stmt->execute([$response, $review_id, $landlord_id]);
    return ['success' => true, 'message' => 'Response added'];
}

function searchUsers($query, $role = '') {
    global $conn;

    $query = trim((string) $query);
    if (strlen($query) < 3) {
        return [];
    }

    $sql = 'SELECT u.id, u.email, p.full_name, p.avatar, p.trust_score FROM users u LEFT JOIN profiles p ON u.id = p.user_id WHERE (p.full_name LIKE ? OR u.email LIKE ?)';
    $params = ["%{$query}%", "%{$query}%"];
    if ($role !== '') {
        $sql .= ' AND u.role = ?';
        $params[] = $role;
    }
    $sql .= ' ORDER BY p.full_name ASC LIMIT 10';

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * ADMIN FUNCTIONS
 */
function logAdminAction($admin_id, $action_type, $target_type, $target_id = null, $description = '') {
    global $conn;

    if (!tableExists('admin_actions_log')) {
        return false;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $conn->prepare('INSERT INTO admin_actions_log (admin_id, action_type, target_type, target_id, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
    return $stmt->execute([(int) $admin_id, $action_type, $target_type, $target_id, $description, $ip]);
}

function getAdminStats() {
    global $conn;

    $stats = [
        'users' => ['total' => 0, 'new_7days' => 0, 'new_30days' => 0, 'tenant' => 0, 'landlord' => 0, 'admin' => 0],
        'rooms' => ['total' => 0, 'verified' => 0, 'active' => 0, 'inactive' => 0, 'booked' => 0, 'pending' => 0, 'rejected' => 0],
        'contracts' => ['total' => 0, 'draft' => 0, 'pending_tenant' => 0, 'pending_landlord' => 0, 'active' => 0, 'expired' => 0, 'terminated' => 0],
        'revenue' => ['total_monthly_rent' => 0],
        'verifications' => ['pending' => 0, 'approved_30days' => 0],
        'reports' => ['pending_reviews' => 0],
        'moderation' => ['pending' => 0],
        'messages' => ['last_7days' => 0],
        'reviews' => ['total' => 0, 'avg_rating' => 0],
    ];

    if (tableExists('users')) {
        $stats['users']['total'] = (int) $conn->query('SELECT COUNT(*) as count FROM users')->fetch()['count'];
        $stats['users']['new_7days'] = (int) $conn->query("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch()['count'];
        $stats['users']['new_30days'] = (int) $conn->query("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch()['count'];
        $result = $conn->query('SELECT role, COUNT(*) as count FROM users GROUP BY role');
        while ($row = $result->fetch()) {
            $stats['users'][$row['role']] = (int) $row['count'];
        }
    }

    if (tableExists('rooms')) {
        $stats['rooms']['total'] = (int) $conn->query('SELECT COUNT(*) as count FROM rooms')->fetch()['count'];
        $stats['rooms']['verified'] = (int) $conn->query('SELECT COUNT(*) as count FROM rooms WHERE is_verified = 1')->fetch()['count'];
        $result = $conn->query('SELECT status, COUNT(*) as count FROM rooms GROUP BY status');
        while ($row = $result->fetch()) {
            $stats['rooms'][$row['status']] = (int) $row['count'];
        }
    }

    if (tableExists('contracts')) {
        $stats['contracts']['total'] = (int) $conn->query('SELECT COUNT(*) as count FROM contracts')->fetch()['count'];
        $result = $conn->query('SELECT status, COUNT(*) as count FROM contracts GROUP BY status');
        while ($row = $result->fetch()) {
            $stats['contracts'][$row['status']] = (int) $row['count'];
        }
        $stats['revenue']['total_monthly_rent'] = (float) ($conn->query("SELECT SUM(monthly_rent) as total FROM contracts WHERE status = 'active'")->fetch()['total'] ?? 0);
    }

    if (tableExists('verification_documents')) {
        $stats['verifications']['pending'] = (int) $conn->query("SELECT COUNT(*) as count FROM verification_documents WHERE status = 'pending'")->fetch()['count'];
        $stats['verifications']['approved_30days'] = (int) $conn->query("SELECT COUNT(*) as count FROM verification_documents WHERE status = 'approved' AND reviewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch()['count'];
    }

    if (tableExists('review_reports')) {
        $stats['reports']['pending_reviews'] = (int) $conn->query("SELECT COUNT(*) as count FROM review_reports WHERE status = 'pending'")->fetch()['count'];
    }

    if (tableExists('moderation_queue')) {
        $stats['moderation']['pending'] = (int) $conn->query("SELECT COUNT(*) as count FROM moderation_queue WHERE status = 'pending'")->fetch()['count'];
    }

    if (tableExists('messages')) {
        $stats['messages']['last_7days'] = (int) $conn->query("SELECT COUNT(*) as count FROM messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch()['count'];
    }

    if (tableExists('reviews')) {
        $stats['reviews']['total'] = (int) $conn->query('SELECT COUNT(*) as count FROM reviews')->fetch()['count'];
        $stats['reviews']['avg_rating'] = round((float) ($conn->query('SELECT AVG(rating_overall) as avg FROM reviews')->fetch()['avg'] ?? 0), 1);
    }

    return $stats;
}

function getAdminUsers($filters = [], $page = 1, $per_page = 20) {
    global $conn;

    if (!tableExists('users')) {
        return ['users' => [], 'total' => 0, 'page' => 1, 'per_page' => $per_page, 'total_pages' => 0];
    }

    $page = max(1, (int) $page);
    $per_page = max(1, (int) $per_page);
    $where = ['1=1'];
    $params = [];

    if (!empty($filters['role'])) {
        $where[] = 'u.role = ?';
        $params[] = $filters['role'];
    }

    if (!empty($filters['status'])) {
        if ($filters['status'] === 'active' && columnExists('users', 'is_active')) {
            $where[] = 'u.is_active = 1';
        } elseif ($filters['status'] === 'inactive' && columnExists('users', 'is_active')) {
            $where[] = 'u.is_active = 0';
        } elseif ($filters['status'] === 'verified' && columnExists('users', 'email_verified')) {
            $where[] = 'u.email_verified = 1';
        } elseif ($filters['status'] === 'unverified' && columnExists('users', 'email_verified')) {
            $where[] = 'u.email_verified = 0';
        }
    }

    if (!empty($filters['search'])) {
        $where[] = '(u.email LIKE ? OR p.full_name LIKE ? OR p.phone LIKE ?)';
        $search = '%' . trim((string) $filters['search']) . '%';
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }

    if (!empty($filters['date_from'])) {
        $where[] = 'DATE(u.created_at) >= ?';
        $params[] = $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $where[] = 'DATE(u.created_at) <= ?';
        $params[] = $filters['date_to'];
    }

    if (isset($filters['trust_min']) && $filters['trust_min'] !== '' && columnExists('profiles', 'trust_score')) {
        $where[] = 'COALESCE(p.trust_score, 0) >= ?';
        $params[] = (int) $filters['trust_min'];
    }

    if (isset($filters['trust_max']) && $filters['trust_max'] !== '' && columnExists('profiles', 'trust_score')) {
        $where[] = 'COALESCE(p.trust_score, 0) <= ?';
        $params[] = (int) $filters['trust_max'];
    }

    $where_clause = implode(' AND ', $where);
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM users u LEFT JOIN profiles p ON u.id = p.user_id WHERE $where_clause");
    $count_stmt->execute($params);
    $total = (int) $count_stmt->fetch()['total'];
    $offset = ($page - 1) * $per_page;

    $sql = "SELECT u.*, p.full_name, p.phone, p.avatar, p.trust_score, p.phone_verified, p.id_verified,
               (SELECT COUNT(*) FROM rooms WHERE landlord_id = u.id) as room_count,
               (SELECT COUNT(*) FROM contracts WHERE tenant_id = u.id OR landlord_id = u.id) as contract_count
            FROM users u
            LEFT JOIN profiles p ON u.id = p.user_id
            WHERE $where_clause
            ORDER BY u.created_at DESC
            LIMIT ? OFFSET ?";

    $query_params = $params;
    $query_params[] = $per_page;
    $query_params[] = $offset;

    $stmt = $conn->prepare($sql);
    $stmt->execute($query_params);

    return [
        'users' => $stmt->fetchAll(),
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => $total > 0 ? (int) ceil($total / $per_page) : 0,
    ];
}

function getAdminRooms($filters = [], $page = 1, $per_page = 20) {
    global $conn;

    if (!tableExists('rooms')) {
        return ['rooms' => [], 'total' => 0, 'page' => 1, 'per_page' => $per_page, 'total_pages' => 0];
    }

    $page = max(1, (int) $page);
    $per_page = max(1, (int) $per_page);
    $where = ['1=1'];
    $params = [];

    if (!empty($filters['status'])) {
        $where[] = 'r.status = ?';
        $params[] = $filters['status'];
    }

    if (!empty($filters['verified'])) {
        $where[] = 'r.is_verified = ?';
        $params[] = $filters['verified'] === 'yes' ? 1 : 0;
    }

    if (!empty($filters['location'])) {
        $where[] = 'r.location = ?';
        $params[] = $filters['location'];
    }

    if (!empty($filters['landlord_id'])) {
        $where[] = 'r.landlord_id = ?';
        $params[] = (int) $filters['landlord_id'];
    }

    if (!empty($filters['search'])) {
        $where[] = '(r.title LIKE ? OR r.description LIKE ? OR r.location LIKE ?)';
        $search = '%' . trim((string) $filters['search']) . '%';
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }

    if (isset($filters['price_min']) && $filters['price_min'] !== '') {
        $where[] = 'r.price >= ?';
        $params[] = (float) $filters['price_min'];
    }

    if (isset($filters['price_max']) && $filters['price_max'] !== '') {
        $where[] = 'r.price <= ?';
        $params[] = (float) $filters['price_max'];
    }

    if (!empty($filters['date_from'])) {
        $where[] = 'DATE(r.created_at) >= ?';
        $params[] = $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $where[] = 'DATE(r.created_at) <= ?';
        $params[] = $filters['date_to'];
    }

    $where_clause = implode(' AND ', $where);
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM rooms r WHERE $where_clause");
    $count_stmt->execute($params);
    $total = (int) $count_stmt->fetch()['total'];
    $offset = ($page - 1) * $per_page;

    $sql = "SELECT r.*, u.email as landlord_email, p.full_name as landlord_name,
               (SELECT COUNT(*) FROM room_images WHERE room_id = r.id) as image_count,
               (SELECT image_url FROM room_images WHERE room_id = r.id AND is_primary = 1 LIMIT 1) as primary_image
            FROM rooms r
            JOIN users u ON r.landlord_id = u.id
            LEFT JOIN profiles p ON u.id = p.user_id
            WHERE $where_clause
            ORDER BY r.created_at DESC
            LIMIT ? OFFSET ?";

    $query_params = $params;
    $query_params[] = $per_page;
    $query_params[] = $offset;

    $stmt = $conn->prepare($sql);
    $stmt->execute($query_params);

    return [
        'rooms' => $stmt->fetchAll(),
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => $total > 0 ? (int) ceil($total / $per_page) : 0,
    ];
}

function getAdminContracts($filters = [], $page = 1, $per_page = 20) {
    global $conn;

    if (!tableExists('contracts')) {
        return ['contracts' => [], 'total' => 0, 'page' => 1, 'per_page' => $per_page, 'total_pages' => 0];
    }

    $page = max(1, (int) $page);
    $per_page = max(1, (int) $per_page);
    $where = ['1=1'];
    $params = [];

    if (!empty($filters['status'])) {
        $where[] = 'c.status = ?';
        $params[] = $filters['status'];
    }

    if (!empty($filters['tenant_id'])) {
        $where[] = 'c.tenant_id = ?';
        $params[] = (int) $filters['tenant_id'];
    }

    if (!empty($filters['landlord_id'])) {
        $where[] = 'c.landlord_id = ?';
        $params[] = (int) $filters['landlord_id'];
    }

    if (!empty($filters['search'])) {
        $where[] = '(c.contract_number LIKE ? OR r.title LIKE ?)';
        $search = '%' . trim((string) $filters['search']) . '%';
        $params[] = $search;
        $params[] = $search;
    }

    if (!empty($filters['date_from'])) {
        $where[] = 'DATE(c.created_at) >= ?';
        $params[] = $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $where[] = 'DATE(c.created_at) <= ?';
        $params[] = $filters['date_to'];
    }

    if (isset($filters['amount_min']) && $filters['amount_min'] !== '') {
        $where[] = 'c.monthly_rent >= ?';
        $params[] = (float) $filters['amount_min'];
    }

    if (isset($filters['amount_max']) && $filters['amount_max'] !== '') {
        $where[] = 'c.monthly_rent <= ?';
        $params[] = (float) $filters['amount_max'];
    }

    $where_clause = implode(' AND ', $where);
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM contracts c LEFT JOIN rooms r ON c.room_id = r.id WHERE $where_clause");
    $count_stmt->execute($params);
    $total = (int) $count_stmt->fetch()['total'];
    $offset = ($page - 1) * $per_page;

    $sql = "SELECT c.*, r.title as room_title,
               tp.full_name as tenant_name, tp.phone as tenant_phone, tp.avatar as tenant_avatar,
               lp.full_name as landlord_name, lp.phone as landlord_phone, lp.avatar as landlord_avatar
            FROM contracts c
            JOIN rooms r ON c.room_id = r.id
            LEFT JOIN profiles tp ON c.tenant_id = tp.user_id
            LEFT JOIN profiles lp ON c.landlord_id = lp.user_id
            WHERE $where_clause
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?";

    $query_params = $params;
    $query_params[] = $per_page;
    $query_params[] = $offset;

    $stmt = $conn->prepare($sql);
    $stmt->execute($query_params);

    return [
        'contracts' => $stmt->fetchAll(),
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => $total > 0 ? (int) ceil($total / $per_page) : 0,
    ];
}

function getPendingVerifications() {
    global $conn;

    if (!tableExists('verification_documents')) {
        return [];
    }

    $stmt = $conn->prepare("SELECT v.*, u.email, p.full_name, p.phone,
        (SELECT COUNT(*) FROM verification_documents WHERE user_id = v.user_id AND status = 'approved') as previous_approvals
        FROM verification_documents v
        JOIN users u ON v.user_id = u.id
        LEFT JOIN profiles p ON u.id = p.user_id
        WHERE v.status = 'pending'
        ORDER BY v.submitted_at ASC");
    $stmt->execute();
    return $stmt->fetchAll();
}

function getPendingReviews() {
    global $conn;

    if (!tableExists('reviews')) {
        return [];
    }

    $stmt = $conn->prepare("SELECT r.*, rev.full_name as reviewer_name, revi.full_name as reviewee_name, rm.title as room_title,
        (SELECT COUNT(*) FROM review_reports WHERE review_id = r.id AND status = 'pending') as report_count
        FROM reviews r
        JOIN profiles rev ON r.reviewer_id = rev.user_id
        JOIN profiles revi ON r.reviewee_id = revi.user_id
        JOIN contracts c ON r.contract_id = c.id
        JOIN rooms rm ON c.room_id = rm.id
        WHERE r.reported_count > 0 OR r.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY r.reported_count DESC, r.created_at DESC");
    $stmt->execute();
    return $stmt->fetchAll();
}

function getPendingRooms() {
    global $conn;

    if (!tableExists('rooms')) {
        return [];
    }

    $stmt = $conn->prepare("SELECT r.*, p.full_name as landlord_name, p.phone as landlord_phone,
        (SELECT COUNT(*) FROM room_images WHERE room_id = r.id) as image_count,
        (SELECT image_url FROM room_images WHERE room_id = r.id AND is_primary = 1 LIMIT 1) as primary_image
        FROM rooms r
        LEFT JOIN profiles p ON r.landlord_id = p.user_id
        WHERE r.status = 'pending'
        ORDER BY r.created_at ASC");
    $stmt->execute();
    return $stmt->fetchAll();
}

function getSystemLogs($level = null, $limit = 100) {
    global $conn;

    if (!tableExists('system_logs')) {
        return [];
    }

    $limit = max(1, (int) $limit);
    $sql = 'SELECT * FROM system_logs';
    $params = [];

    if ($level) {
        $sql .= ' WHERE log_level = ?';
        $params[] = $level;
    }

    $sql .= ' ORDER BY created_at DESC LIMIT ?';
    $params[] = $limit;

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function addSystemLog($level, $type, $message, $details = null) {
    global $conn;

    if (!tableExists('system_logs')) {
        return false;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_id = isLoggedIn() ? getCurrentUserId() : null;
    $stmt = $conn->prepare('INSERT INTO system_logs (log_level, log_type, message, details, ip_address, user_id) VALUES (?, ?, ?, ?, ?, ?)');
    return $stmt->execute([$level, $type, $message, $details ? json_encode($details) : null, $ip, $user_id]);
}

function addToModerationQueue($item_type, $item_id, $action_required, $priority = 'medium', $reason = '') {
    global $conn;

    if (!tableExists('moderation_queue')) {
        return null;
    }

    $check = $conn->prepare("SELECT id FROM moderation_queue WHERE item_type = ? AND item_id = ? AND status = 'pending'");
    $check->execute([$item_type, $item_id]);
    $existing = $check->fetchColumn();
    if ($existing) {
        return (int) $existing;
    }

    $stmt = $conn->prepare('INSERT INTO moderation_queue (item_type, item_id, action_required, priority, report_reasons) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$item_type, (int) $item_id, $action_required, $priority, $reason]);
    notifyAdmins('new_moderation', 'New ' . $item_type . ' requires moderation');
    return (int) $conn->lastInsertId();
}

function updateSiteSetting($key, $value, $admin_id) {
    global $conn;

    if (!tableExists('site_settings')) {
        return false;
    }

    $stmt = $conn->prepare('UPDATE site_settings SET setting_value = ?, updated_by = ?, updated_at = NOW() WHERE setting_key = ?');
    $ok = $stmt->execute([(string) $value, (int) $admin_id, $key]);
    if ($ok) {
        logAdminAction($admin_id, 'update_setting', 'setting', null, 'Updated setting: ' . $key);
        if (function_exists('apcu_delete')) {
            apcu_delete('setting_' . $key);
        }
    }
    return $ok;
}

function getSiteSetting($key, $default = null) {
    global $conn;

    static $settings_cache = [];
    if (array_key_exists($key, $settings_cache)) {
        return $settings_cache[$key];
    }

    if (!tableExists('site_settings')) {
        $settings_cache[$key] = $default;
        return $default;
    }

    if (function_exists('apcu_fetch')) {
        $cached = apcu_fetch('setting_' . $key);
        if ($cached !== false) {
            $settings_cache[$key] = $cached;
            return $cached;
        }
    }

    $stmt = $conn->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    if ($value === false) {
        $value = $default;
    }

    $settings_cache[$key] = $value;
    if (function_exists('apcu_store')) {
        apcu_store('setting_' . $key, $value, 3600);
    }
    return $value;
}

function getAllSettings() {
    global $conn;

    if (!tableExists('site_settings')) {
        return [];
    }

    $stmt = $conn->query('SELECT * FROM site_settings ORDER BY setting_key ASC');
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row;
    }
    return $settings;
}

function notifyAdmins($type, $message, $link = null) {
    global $conn;

    if (!tableExists('users')) {
        return;
    }

    $stmt = $conn->query("SELECT id FROM users WHERE role = 'admin'");
    while ($admin = $stmt->fetch()) {
        createNotification((int) $admin['id'], 'system', 'Admin Notification', $message, $link, ['type' => $type]);
    }
}

function createBackup($admin_id, $type = 'database') {
    global $conn;

    $backup_name = 'backup_' . date('Y-m-d_H-i-s') . '_' . $type;
    $backup_dir = __DIR__ . '/../backups/';
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }

    $created_files = [];
    $total_size = 0;

    try {
        if ($type === 'database' || $type === 'full') {
            $sql_filename = $backup_name . '.sql';
            $sql_filepath = $backup_dir . $sql_filename;
            $tables = [];
            $result = $conn->query('SHOW TABLES');
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }

            $output = "-- Gharbeti Database Backup\n";
            $output .= '-- Generated: ' . date('Y-m-d H:i:s') . "\n\n";
            $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

            foreach ($tables as $table) {
                $create = $conn->query('SHOW CREATE TABLE `' . $table . '`')->fetch();
                $output .= 'DROP TABLE IF EXISTS `' . $table . "`;\n";
                $output .= $create['Create Table'] . ";\n\n";

                $rows = $conn->query('SELECT * FROM `' . $table . '`');
                $records = $rows->fetchAll(PDO::FETCH_ASSOC);
                if (!$records) {
                    continue;
                }

                $columns = array_keys($records[0]);
                $output .= 'INSERT INTO `' . $table . '` (`' . implode('`, `', $columns) . "`) VALUES\n";
                $values = [];
                foreach ($records as $row) {
                    $escaped = [];
                    foreach ($row as $value) {
                        $escaped[] = $value === null ? 'NULL' : $conn->quote((string) $value);
                    }
                    $values[] = '(' . implode(', ', $escaped) . ')';
                }
                $output .= implode(",\n", $values) . ";\n\n";
            }

            $output .= "SET FOREIGN_KEY_CHECKS=1;\n";
            file_put_contents($sql_filepath, $output);
            $created_files[] = $sql_filename;
            $total_size += filesize($sql_filepath);
        }

        if (($type === 'files' || $type === 'full') && class_exists('ZipArchive')) {
            $zip_filename = $backup_name . '_uploads.zip';
            $zip_filepath = $backup_dir . $zip_filename;
            $uploads_dir = __DIR__ . '/../assets/uploads/';
            if (!is_dir($uploads_dir)) {
                mkdir($uploads_dir, 0777, true);
            }

            $zip = new ZipArchive();
            if ($zip->open($zip_filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new Exception('Failed to create zip file');
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($uploads_dir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $file) {
                if ($file->isDir()) {
                    continue;
                }
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($uploads_dir));
                $zip->addFile($filePath, ltrim(str_replace('\\', '/', $relativePath), '/'));
            }

            $zip->close();
            $created_files[] = $zip_filename;
            $total_size += filesize($zip_filepath);
        }

        if (tableExists('backups')) {
            $stmt = $conn->prepare('INSERT INTO backups (backup_name, backup_type, file_size, file_path, status, created_by, completed_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
            $stmt->execute([$backup_name, $type, $total_size, implode(', ', $created_files), 'completed', (int) $admin_id]);
        }

        logAdminAction($admin_id, 'create_backup', 'system', null, 'Created ' . $type . ' backup: ' . implode(', ', $created_files));
        return ['success' => true, 'message' => ucfirst($type) . ' backup created successfully', 'filename' => implode(', ', $created_files)];
    } catch (Exception $e) {
        addSystemLog('error', 'backup', 'Backup failed: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Backup failed: ' . $e->getMessage()];
    }
}

function restoreBackup($backup_id, $admin_id) {
    global $conn;

    if (!tableExists('backups')) {
        return ['success' => false, 'message' => 'Backups table is not available'];
    }

    $stmt = $conn->prepare('SELECT * FROM backups WHERE id = ?');
    $stmt->execute([(int) $backup_id]);
    $backup = $stmt->fetch();
    if (!$backup) {
        return ['success' => false, 'message' => 'Backup not found'];
    }

    $backup_dir = __DIR__ . '/../backups/';
    $paths = array_filter(array_map('trim', explode(',', (string) $backup['file_path'])));
    if (empty($paths)) {
        return ['success' => false, 'message' => 'Backup file path is missing'];
    }

    try {
        foreach ($paths as $file) {
            $filepath = $backup_dir . $file;
            if (!file_exists($filepath)) {
                continue;
            }

            $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
            if ($extension === 'sql') {
                $sql = file_get_contents($filepath);
                $conn->exec('SET FOREIGN_KEY_CHECKS = 0');
                $conn->exec($sql);
                $conn->exec('SET FOREIGN_KEY_CHECKS = 1');
            } elseif ($extension === 'zip' && class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if ($zip->open($filepath) === true) {
                    $uploads_dir = __DIR__ . '/../assets/uploads/';
                    if (!is_dir($uploads_dir)) {
                        mkdir($uploads_dir, 0777, true);
                    }
                    $zip->extractTo($uploads_dir);
                    $zip->close();
                }
            }
        }

        logAdminAction($admin_id, 'restore_backup', 'backup', $backup_id, 'Restored backup: ' . $backup['backup_name']);
        addSystemLog('info', 'backup', 'Backup restored: ' . $backup['backup_name']);
        return ['success' => true, 'message' => 'Backup restored successfully'];
    } catch (Exception $e) {
        addSystemLog('error', 'backup', 'Restore failed: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Restore failed: ' . $e->getMessage()];
    }
}

function isMaintenanceMode() {
    if (PHP_SAPI === 'cli') {
        return false;
    }

    if (isAdmin()) {
        return false;
    }

    if (!tableExists('site_settings')) {
        return false;
    }

    $maintenance = getSiteSetting('maintenance_mode', '0') === '1';
    if (!$maintenance) {
        return false;
    }

    $allowed_ips = trim((string) getSiteSetting('maintenance_allowed_ips', ''));
    if ($allowed_ips !== '') {
        $ips = array_map('trim', explode(',', $allowed_ips));
        if (in_array($_SERVER['REMOTE_ADDR'] ?? '', $ips, true)) {
            return false;
        }
    }

    return true;
}

function trackVisit($page_url) {
    global $conn;

    if (PHP_SAPI === 'cli' || !tableExists('analytics_visits') || getSiteSetting('enable_analytics', '1') !== '1') {
        return;
    }

    if (strpos($page_url, '/admin/') !== false || strpos($page_url, '/api/') !== false) {
        return;
    }

    $visitor_id = $_COOKIE['visitor_id'] ?? null;
    if (!$visitor_id) {
        $visitor_id = bin2hex(random_bytes(16));
        setcookie('visitor_id', $visitor_id, time() + (86400 * 365), '/');
    }

    $user_id = isLoggedIn() ? getCurrentUserId() : null;
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $device_type = 'unknown';
    $browser = 'unknown';
    $os = 'unknown';

    if ($user_agent) {
        if (preg_match('/tablet|ipad/i', $user_agent)) {
            $device_type = 'tablet';
        } elseif (preg_match('/mobile|android|iphone/i', $user_agent)) {
            $device_type = 'mobile';
        } elseif (preg_match('/windows|macintosh|linux/i', $user_agent)) {
            $device_type = 'desktop';
        }

        if (preg_match('/edg/i', $user_agent)) {
            $browser = 'edge';
        } elseif (preg_match('/chrome|chromium/i', $user_agent)) {
            $browser = 'chrome';
        } elseif (preg_match('/firefox/i', $user_agent)) {
            $browser = 'firefox';
        } elseif (preg_match('/safari/i', $user_agent) && !preg_match('/chrome|chromium/i', $user_agent)) {
            $browser = 'safari';
        } elseif (preg_match('/opera|opr/i', $user_agent)) {
            $browser = 'opera';
        }

        if (preg_match('/windows/i', $user_agent)) {
            $os = 'windows';
        } elseif (preg_match('/android/i', $user_agent)) {
            $os = 'android';
        } elseif (preg_match('/iphone|ipad|ipod|ios/i', $user_agent)) {
            $os = 'ios';
        } elseif (preg_match('/macintosh|mac os x/i', $user_agent)) {
            $os = 'macos';
        } elseif (preg_match('/linux/i', $user_agent)) {
            $os = 'linux';
        }
    }

    $stmt = $conn->prepare('INSERT INTO analytics_visits (visitor_id, user_id, page_url, referrer_url, ip_address, user_agent, device_type, browser, os, country, city) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$visitor_id, $user_id, $page_url, $referrer, $ip, $user_agent, $device_type, $browser, $os, '', '']);
}

function trackEvent($event_type, $event_category = null, $event_label = null, $event_value = null) {
    global $conn;

    if (PHP_SAPI === 'cli' || !tableExists('analytics_events') || getSiteSetting('enable_analytics', '1') !== '1') {
        return;
    }

    $user_id = isLoggedIn() ? getCurrentUserId() : null;
    $page_url = $_SERVER['REQUEST_URI'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    $stmt = $conn->prepare('INSERT INTO analytics_events (user_id, event_type, event_category, event_label, event_value, page_url, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$user_id, $event_type, $event_category, $event_label, $event_value, $page_url, $ip]);
}

function getAnalyticsData($period = '7days') {
    global $conn;

    if (!tableExists('analytics_visits')) {
        return [
            'visits_over_time' => [],
            'top_pages' => [],
            'top_referrers' => [],
            'devices' => [],
            'browsers' => [],
            'os' => [],
            'registrations' => [],
            'listings' => [],
            'contracts' => [],
            'summary' => ['unique_visitors' => 0, 'total_visits' => 0, 'avg_session_duration' => 0],
        ];
    }

    $range_map = [
        '24hours' => ['interval' => '24 HOUR', 'group' => '%Y-%m-%d %H:00', 'label' => '%H:00'],
        '7days' => ['interval' => '7 DAY', 'group' => '%Y-%m-%d', 'label' => '%a'],
        '30days' => ['interval' => '30 DAY', 'group' => '%Y-%m-%d', 'label' => '%b %d'],
        '12months' => ['interval' => '12 MONTH', 'group' => '%Y-%m', 'label' => '%b %Y'],
    ];

    $config = $range_map[$period] ?? $range_map['7days'];
    $interval = $config['interval'];
    $group = $config['group'];
    $label = $config['label'];

    $data = [];
    $data['visits_over_time'] = $conn->query("SELECT DATE_FORMAT(created_at, '{$group}') as time_period, DATE_FORMAT(created_at, '{$label}') as label, COUNT(DISTINCT visitor_id) as unique_visitors, COUNT(*) as total_visits FROM analytics_visits WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$interval}) GROUP BY time_period ORDER BY time_period ASC")->fetchAll();
    $data['top_pages'] = $conn->query("SELECT page_url, COUNT(*) as views, COUNT(DISTINCT visitor_id) as unique_views FROM analytics_visits WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$interval}) GROUP BY page_url ORDER BY views DESC LIMIT 10")->fetchAll();
    $data['top_referrers'] = $conn->query("SELECT referrer_url, COUNT(*) as count FROM analytics_visits WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$interval}) AND referrer_url != '' GROUP BY referrer_url ORDER BY count DESC LIMIT 10")->fetchAll();
    $data['devices'] = $conn->query("SELECT device_type, COUNT(*) as count FROM analytics_visits WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$interval}) GROUP BY device_type")->fetchAll();
    $data['browsers'] = $conn->query("SELECT browser, COUNT(*) as count FROM analytics_visits WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$interval}) GROUP BY browser")->fetchAll();
    $data['os'] = $conn->query("SELECT os, COUNT(*) as count FROM analytics_visits WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$interval}) GROUP BY os")->fetchAll();
    $data['registrations'] = tableExists('users') ? $conn->query("SELECT DATE_FORMAT(created_at, '{$group}') as time_period, COUNT(*) as registrations FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$interval}) GROUP BY time_period ORDER BY time_period ASC")->fetchAll() : [];
    $data['listings'] = tableExists('rooms') ? $conn->query("SELECT DATE_FORMAT(created_at, '{$group}') as time_period, COUNT(*) as listings FROM rooms WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$interval}) GROUP BY time_period ORDER BY time_period ASC")->fetchAll() : [];
    $data['contracts'] = tableExists('contracts') ? $conn->query("SELECT DATE_FORMAT(created_at, '{$group}') as time_period, COUNT(*) as contracts FROM contracts WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$interval}) GROUP BY time_period ORDER BY time_period ASC")->fetchAll() : [];
    $data['summary'] = $conn->query("SELECT COUNT(DISTINCT visitor_id) as unique_visitors, COUNT(*) as total_visits, AVG(session_duration) as avg_session_duration FROM analytics_visits WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$interval})")->fetch();
    return $data;
}

function sendEmail($to, $subject, $body, $from = null, $from_name = null) {
    global $conn;

    $from = $from ?: getSiteSetting('from_email', 'noreply@gharbeti.com');
    $from_name = $from_name ?: getSiteSetting('from_name', 'Gharbeti');
    $log_id = null;

    if (tableExists('email_logs')) {
        $stmt = $conn->prepare("INSERT INTO email_logs (recipient_email, subject, body, status) VALUES (?, ?, ?, 'queued')");
        $stmt->execute([$to, $subject, $body]);
        $log_id = (int) $conn->lastInsertId();
    }

    $headers = "From: {$from_name} <{$from}>\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $success = @mail($to, $subject, $body, $headers);

    if ($log_id) {
        if ($success) {
            $update = $conn->prepare("UPDATE email_logs SET status = 'sent', sent_at = NOW() WHERE id = ?");
            $update->execute([$log_id]);
        } else {
            $update = $conn->prepare("UPDATE email_logs SET status = 'failed', error_message = ? WHERE id = ?");
            $update->execute(['mail() returned false', $log_id]);
        }
    }

    return $success;
}

function getAdminLogs($limit = 100) {
    global $conn;

    if (!tableExists('admin_actions_log')) {
        return [];
    }

    $limit = max(1, (int) $limit);
    $stmt = $conn->prepare('SELECT l.*, u.email, p.full_name FROM admin_actions_log l JOIN users u ON l.admin_id = u.id LEFT JOIN profiles p ON u.id = p.user_id ORDER BY l.created_at DESC LIMIT ?');
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function clearCache() {
    if (function_exists('apcu_clear_cache')) {
        apcu_clear_cache();
    }

    $cache_dir = __DIR__ . '/../cache/';
    if (is_dir($cache_dir)) {
        foreach (glob($cache_dir . '*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    addSystemLog('info', 'cache', 'Cache cleared');
    return true;
}

function getSystemHealth() {
    global $conn;

    $health = [];
    $health['php_version'] = PHP_VERSION;
    $health['php_version_ok'] = version_compare(PHP_VERSION, '7.4.0', '>=');

    $required_extensions = ['pdo_mysql', 'json', 'session', 'gd', 'fileinfo', 'zip'];
    $health['extensions'] = [];
    foreach ($required_extensions as $ext) {
        $health['extensions'][$ext] = extension_loaded($ext);
    }

    try {
        $conn->query('SELECT 1')->fetch();
        $health['database'] = true;
    } catch (Exception $e) {
        $health['database'] = false;
        $health['database_error'] = $e->getMessage();
    }

    $upload_dir = UPLOAD_PATH;
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $health['disk_free'] = @disk_free_space($upload_dir) ?: 0;
    $health['disk_total'] = @disk_total_space($upload_dir) ?: 0;
    $health['disk_used_percent'] = $health['disk_total'] > 0 ? round((1 - ($health['disk_free'] / $health['disk_total'])) * 100, 2) : 0;

    $dirs_to_check = [
        UPLOAD_PATH,
        UPLOAD_PATH . 'avatars/',
        UPLOAD_PATH . 'rooms/',
        UPLOAD_PATH . 'documents/',
        UPLOAD_PATH . 'contracts/',
        __DIR__ . '/../backups/',
        __DIR__ . '/../cache/',
    ];

    $health['writable'] = [];
    foreach ($dirs_to_check as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $health['writable'][$dir] = is_writable($dir);
    }

    $health['memory_limit'] = ini_get('memory_limit');
    $health['memory_usage'] = memory_get_usage(true);
    $health['memory_peak'] = memory_get_peak_usage(true);
    $health['upload_max_filesize'] = ini_get('upload_max_filesize');
    $health['post_max_size'] = ini_get('post_max_size');
    $health['max_execution_time'] = ini_get('max_execution_time');

    $session_path = session_save_path() ?: sys_get_temp_dir();
    if ($session_path === '') {
        $session_path = sys_get_temp_dir();
    }
    $health['session_save_path'] = $session_path;
    $health['session_save_path_writable'] = is_dir($session_path) ? is_writable($session_path) : false;
    $health['is_production'] = defined('IS_PRODUCTION') ? IS_PRODUCTION : false;

    return $health;
}

function getBackups() {
    global $conn;

    if (!tableExists('backups')) {
        return [];
    }

    $stmt = $conn->query('SELECT b.*, u.email, p.full_name FROM backups b LEFT JOIN users u ON b.created_by = u.id LEFT JOIN profiles p ON u.id = p.user_id ORDER BY b.created_at DESC');
    return $stmt->fetchAll();
}

function cleanupBackups($days_to_keep = 30) {
    global $conn;

    if (!tableExists('backups')) {
        return 0;
    }

    $cutoff = date('Y-m-d H:i:s', strtotime('-' . max(1, (int) $days_to_keep) . ' days'));
    $stmt = $conn->prepare('SELECT * FROM backups WHERE created_at < ?');
    $stmt->execute([$cutoff]);
    $old_backups = $stmt->fetchAll();
    $backup_dir = __DIR__ . '/../backups/';
    $deleted = 0;

    foreach ($old_backups as $backup) {
        foreach (array_filter(array_map('trim', explode(',', (string) $backup['file_path']))) as $file) {
            $filepath = $backup_dir . $file;
            if (is_file($filepath)) {
                unlink($filepath);
            }
        }

        $delete = $conn->prepare('DELETE FROM backups WHERE id = ?');
        $delete->execute([$backup['id']]);
        $deleted++;
    }

    addSystemLog('info', 'cleanup', 'Deleted ' . $deleted . ' old backups');
    return $deleted;
}

if (isLoggedIn()) {
    checkSessionTimeout();
}

if (!isLoggedIn()) {
    checkRememberMe();
}
?>
