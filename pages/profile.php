<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    redirect(SITE_URL . '/auth/login.php');
}

$user_id = isset($_GET['id']) ? (int) $_GET['id'] : getCurrentUserId();
$is_own_profile = ($user_id === (int) getCurrentUserId());
$user = getCompleteProfile($user_id);

if (!$user) {
    redirect(SITE_URL . '/pages/dashboard.php');
}

if (!$is_own_profile) {
    recordProfileView($user_id, getCurrentUserId());
}

updateOnlineStatus(getCurrentUserId());

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_own_profile) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $error = 'Invalid security token';
    } elseif (($_POST['action'] ?? '') === 'update_profile') {
        $data = [
            'full_name' => $_POST['full_name'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'address' => $_POST['address'] ?? '',
            'bio' => $_POST['bio'] ?? '',
            'date_of_birth' => $_POST['date_of_birth'] ?? '',
            'gender' => $_POST['gender'] ?? '',
            'occupation' => $_POST['occupation'] ?? '',
            'company' => $_POST['company'] ?? '',
            'education' => $_POST['education'] ?? '',
            'languages' => $_POST['languages'] ?? '',
            'emergency_contact_name' => $_POST['emergency_contact_name'] ?? '',
            'emergency_contact_phone' => $_POST['emergency_contact_phone'] ?? '',
            'facebook_url' => $_POST['facebook_url'] ?? '',
            'twitter_url' => $_POST['twitter_url'] ?? '',
            'linkedin_url' => $_POST['linkedin_url'] ?? '',
            'instagram_url' => $_POST['instagram_url'] ?? ''
        ];

        $result = updateProfile($user_id, $data);
        if ($result['success']) {
            $message = $result['message'];
            $user = getCompleteProfile($user_id);
        } else {
            $error = $result['message'];
        }
    }
}

$trust_score = (int) ($user['trust_score'] ?? 30);
$trust_badge = getTrustScoreBadge($trust_score);
$online_status = getOnlineStatus($user_id);
$verification = getVerificationStatus($user_id);
$profile_views = getProfileViews($user_id);
$profile_completion = round(calculateProfileCompleteness($user_id) * 10);
$trust_history = $is_own_profile ? getTrustScoreHistory($user_id, 5) : [];
$pending_documents = $verification['pending_documents'] ?? [];
$has_pending_document = false;

foreach ($pending_documents as $document) {
    if (($document['status'] ?? '') === 'pending') {
        $has_pending_document = true;
        break;
    }
}

$social_links = [
    'Facebook' => $user['facebook_url'] ?? '',
    'Twitter' => $user['twitter_url'] ?? '',
    'LinkedIn' => $user['linkedin_url'] ?? '',
    'Instagram' => $user['instagram_url'] ?? ''
];

$page_title = $is_own_profile ? 'My Profile' : (($user['full_name'] ?? 'User') . "'s Profile");
require_once __DIR__ . '/../includes/header.php';
?>
<section class="profile-shell premium-section">
    <div class="container">
        <div class="profile-hero-card">
            <div class="profile-hero-glow"></div>
            <div class="profile-top">
                <div class="profile-avatar-block">
                    <img src="<?php echo getUserAvatarUrl($user['avatar'] ?? null); ?>" alt="Avatar" class="profile-avatar-img">
                    <?php if ($is_own_profile): ?>
                        <label for="avatar-upload" class="avatar-upload-btn" aria-label="Upload avatar">
                            <i class="fas fa-camera"></i>
                        </label>
                        <input type="file" id="avatar-upload" accept="image/*" hidden>
                    <?php endif; ?>
                </div>

                <div class="profile-main">
                    <div class="profile-kicker-row">
                        <span class="profile-status <?php echo $online_status['status']; ?>">
                            <i class="fas fa-<?php echo $online_status['status'] === 'online' ? 'circle' : 'clock'; ?>"></i>
                            <?php echo ucfirst($online_status['status']); ?>
                            <?php if ($online_status['status'] === 'offline' && $online_status['last_seen']): ?>
                                <span class="profile-status-detail"><?php echo timeAgo($online_status['last_seen']); ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="profile-role-pill">
                            <i class="fas fa-<?php echo ($user['role'] ?? 'tenant') === 'landlord' ? 'home' : 'user'; ?>"></i>
                            <?php echo ucfirst($user['role'] ?? 'tenant'); ?>
                        </span>
                    </div>

                    <div class="profile-headline">
                        <div>
                            <h1><?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?></h1>
                            <p class="profile-subtitle">Trusted identity, verified activity, and a complete rental profile in one place.</p>
                        </div>
                        <div class="profile-badges"><?php echo getVerificationBadges($user_id); ?></div>
                    </div>

                    <div class="profile-trust-strip">
                        <div class="trust-row">
                            <div class="trust-ring" style="background: conic-gradient(<?php echo $trust_badge['color']; ?> <?php echo $trust_score; ?>%, rgba(15, 79, 76, 0.12) 0);">
                                <div class="trust-ring-inner"><?php echo $trust_score; ?></div>
                            </div>
                            <div>
                                <strong class="trust-level" style="color: <?php echo $trust_badge['color']; ?>;">
                                    <i class="fas <?php echo $trust_badge['icon']; ?>"></i>
                                    <?php echo $trust_badge['level']; ?>
                                </strong>
                                <p class="muted-text">Trust score based on verification and profile strength</p>
                            </div>
                        </div>

                        <?php if (!$is_own_profile): ?>
                            <button class="btn-primary" onclick="startConversation(<?php echo $user_id; ?>)">
                                <i class="fas fa-comment"></i>
                                Send Message
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="profile-meta-grid">
                        <?php if (!empty($user['phone'])): ?>
                            <div class="profile-meta-item"><i class="fas fa-phone"></i><span><?php echo htmlspecialchars($user['phone']); ?></span></div>
                        <?php endif; ?>
                        <div class="profile-meta-item"><i class="fas fa-envelope"></i><span><?php echo htmlspecialchars($user['email']); ?></span></div>
                        <?php if (!empty($user['address'])): ?>
                            <div class="profile-meta-item"><i class="fas fa-map-marker-alt"></i><span><?php echo htmlspecialchars($user['address']); ?></span></div>
                        <?php endif; ?>
                        <div class="profile-meta-item"><i class="fas fa-calendar"></i><span>Joined <?php echo date('M Y', strtotime($user['joined_date'] ?? $user['created_at'])); ?></span></div>
                        <div class="profile-meta-item"><i class="fas fa-eye"></i><span><?php echo $profile_views; ?> profile views</span></div>
                        <div class="profile-meta-item"><i class="fas fa-layer-group"></i><span><?php echo $profile_completion; ?>% profile complete</span></div>
                    </div>
                </div>
            </div>

            <?php if (!empty($user['bio'])): ?>
                <div class="profile-bio">
                    <h3>About</h3>
                    <p><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="profile-overview-grid">
            <div class="profile-mini-card">
                <span class="profile-mini-label">Profile Strength</span>
                <strong><?php echo $profile_completion; ?>%</strong>
                <p>Complete more fields to improve trust and visibility.</p>
            </div>
            <div class="profile-mini-card">
                <span class="profile-mini-label">Verification Level</span>
                <strong><?php echo (!empty($user['email_verified']) ? 1 : 0) + (!empty($verification['phone_verified']) ? 1 : 0) + (!empty($verification['id_verified']) ? 1 : 0); ?>/3</strong>
                <p>Email, phone, and identity checks all build confidence.</p>
            </div>
            <div class="profile-mini-card">
                <span class="profile-mini-label">Audience Reach</span>
                <strong><?php echo $profile_views; ?></strong>
                <p>Total profile visits recorded across the platform.</p>
            </div>
        </div>

        <?php if ($is_own_profile): ?>
            <div class="profile-grid-own">
                <div class="profile-card profile-sidebar-card">
                    <div class="section-heading">
                        <span class="section-kicker">Verification</span>
                        <h2>Account Status</h2>
                    </div>
                    <div class="verification-stack">
                        <div class="verification-card premium-verify-card">
                            <div class="verification-copy">
                                <div class="verification-icon <?php echo !empty($user['email_verified']) ? 'verified' : 'pending'; ?>"><i class="fas fa-<?php echo !empty($user['email_verified']) ? 'check-circle' : 'envelope'; ?>"></i></div>
                                <div>
                                    <h3>Email</h3>
                                    <p><?php echo !empty($user['email_verified']) ? 'Your email is verified and trusted.' : 'Verify your email to unlock a stronger profile.'; ?></p>
                                </div>
                            </div>
                            <?php if (!empty($user['email_verified'])): ?>
                                <span class="status-chip verified">Verified</span>
                            <?php else: ?>
                                <a href="<?php echo SITE_URL; ?>/auth/resend-verification.php?email=<?php echo urlencode($user['email']); ?>" class="btn-outline btn-small">Resend</a>
                            <?php endif; ?>
                        </div>

                        <div class="verification-card premium-verify-card">
                            <div class="verification-copy">
                                <div class="verification-icon <?php echo !empty($verification['phone_verified']) ? 'verified' : 'pending'; ?>"><i class="fas fa-<?php echo !empty($verification['phone_verified']) ? 'check-circle' : 'phone'; ?>"></i></div>
                                <div>
                                    <h3>Phone</h3>
                                    <p><?php echo !empty($verification['phone_verified']) ? 'Your phone number has been confirmed.' : 'Add another layer of trust with phone verification.'; ?></p>
                                </div>
                            </div>
                            <?php if (!empty($verification['phone_verified'])): ?>
                                <span class="status-chip verified">Verified</span>
                            <?php else: ?>
                                <button class="btn-outline btn-small" onclick="showPhoneVerification()">Verify Now</button>
                            <?php endif; ?>
                        </div>

                        <div class="verification-card premium-verify-card">
                            <div class="verification-copy">
                                <div class="verification-icon <?php echo !empty($verification['id_verified']) ? 'verified' : 'pending'; ?>"><i class="fas fa-<?php echo !empty($verification['id_verified']) ? 'check-circle' : 'id-card'; ?>"></i></div>
                                <div>
                                    <h3>Identity</h3>
                                    <p>
                                        <?php if (!empty($verification['id_verified'])): ?>
                                            Your identity document has been approved.
                                        <?php elseif ($has_pending_document): ?>
                                            Your documents are under review by the admin team.
                                        <?php else: ?>
                                            Submit an official document to earn higher trust.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            <?php if (!empty($verification['id_verified'])): ?>
                                <span class="status-chip verified">Verified</span>
                            <?php elseif ($has_pending_document): ?>
                                <span class="status-chip pending">Pending Review</span>
                            <?php else: ?>
                                <button class="btn-outline btn-small" onclick="showIDVerification()">Upload ID</button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="profile-history-block">
                        <div class="section-heading compact">
                            <span class="section-kicker">Trust</span>
                            <h3>Recent Score Changes</h3>
                        </div>
                        <?php if (empty($trust_history)): ?>
                            <p class="muted-text">Your trust score history will appear here after verification updates.</p>
                        <?php else: ?>
                            <div class="timeline-list">
                                <?php foreach ($trust_history as $entry): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-dot"></div>
                                        <div>
                                            <strong><?php echo (int) $entry['old_score']; ?> to <?php echo (int) $entry['new_score']; ?></strong>
                                            <p><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $entry['reason']))); ?></p>
                                            <span><?php echo timeAgo($entry['changed_at']); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="profile-card profile-editor-card">
                    <div class="section-heading">
                        <span class="section-kicker">Profile</span>
                        <h2>Edit Details</h2>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="" id="profileForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="profile-form-grid">
                            <div class="span-2 form-section-title">Basic Information</div>
                            <div class="form-group">
                                <label>Full Name *</label>
                                <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                            <div class="form-group span-2">
                                <label>Address</label>
                                <input type="text" name="address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                            </div>
                            <div class="form-group span-2">
                                <label>Bio</label>
                                <textarea name="bio" rows="5" placeholder="Share a short introduction about yourself"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            </div>

                            <div class="span-2 form-section-title">Personal Details</div>
                            <div class="form-group">
                                <label>Date of Birth</label>
                                <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Gender</label>
                                <select name="gender">
                                    <option value="">Select Gender</option>
                                    <option value="male" <?php echo ($user['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo ($user['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo ($user['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Occupation</label>
                                <input type="text" name="occupation" value="<?php echo htmlspecialchars($user['occupation'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Company</label>
                                <input type="text" name="company" value="<?php echo htmlspecialchars($user['company'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Education</label>
                                <input type="text" name="education" value="<?php echo htmlspecialchars($user['education'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Languages</label>
                                <input type="text" name="languages" value="<?php echo htmlspecialchars($user['languages'] ?? ''); ?>" placeholder="Nepali, English">
                            </div>

                            <div class="span-2 form-section-title">Emergency Contact</div>
                            <div class="form-group">
                                <label>Contact Name</label>
                                <input type="text" name="emergency_contact_name" value="<?php echo htmlspecialchars($user['emergency_contact_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Contact Phone</label>
                                <input type="tel" name="emergency_contact_phone" value="<?php echo htmlspecialchars($user['emergency_contact_phone'] ?? ''); ?>">
                            </div>

                            <div class="span-2 form-section-title">Social Media</div>
                            <div class="form-group">
                                <label>Facebook</label>
                                <input type="url" name="facebook_url" value="<?php echo htmlspecialchars($user['facebook_url'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Twitter</label>
                                <input type="url" name="twitter_url" value="<?php echo htmlspecialchars($user['twitter_url'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>LinkedIn</label>
                                <input type="url" name="linkedin_url" value="<?php echo htmlspecialchars($user['linkedin_url'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Instagram</label>
                                <input type="url" name="instagram_url" value="<?php echo htmlspecialchars($user['instagram_url'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="profile-actions">
                            <button type="submit" class="btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="profile-grid-viewer">
                <div class="profile-card">
                    <div class="section-heading compact">
                        <span class="section-kicker">Identity</span>
                        <h2>Public Profile Snapshot</h2>
                    </div>
                    <div class="public-profile-list">
                        <?php if (!empty($user['occupation'])): ?><div><strong>Occupation</strong><span><?php echo htmlspecialchars($user['occupation']); ?></span></div><?php endif; ?>
                        <?php if (!empty($user['company'])): ?><div><strong>Company</strong><span><?php echo htmlspecialchars($user['company']); ?></span></div><?php endif; ?>
                        <?php if (!empty($user['education'])): ?><div><strong>Education</strong><span><?php echo htmlspecialchars($user['education']); ?></span></div><?php endif; ?>
                        <?php if (!empty($user['languages'])): ?><div><strong>Languages</strong><span><?php echo htmlspecialchars($user['languages']); ?></span></div><?php endif; ?>
                        <?php if (!empty($user['emergency_contact_name'])): ?><div><strong>Emergency Contact</strong><span><?php echo htmlspecialchars($user['emergency_contact_name']); ?></span></div><?php endif; ?>
                    </div>
                </div>

                <div class="profile-card">
                    <div class="section-heading compact">
                        <span class="section-kicker">Social</span>
                        <h2>Connected Profiles</h2>
                    </div>
                    <div class="social-link-list">
                        <?php $has_social = false; ?>
                        <?php foreach ($social_links as $label => $url): ?>
                            <?php if (!empty($url)): ?>
                                <?php $has_social = true; ?>
                                <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener noreferrer" class="social-link-item"><?php echo htmlspecialchars($label); ?></a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if (!$has_social): ?>
                            <p class="muted-text">No public social links added yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<div id="phoneModal" class="profile-modal">
    <div class="profile-modal-card">
        <h3>Verify Phone Number</h3>
        <div id="phoneStep1">
            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" id="verifyPhone" placeholder="98XXXXXXXX" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
            </div>
            <button class="btn-primary" onclick="sendPhoneCode()">Send Code</button>
        </div>
        <div id="phoneStep2" style="display:none;">
            <div class="form-group">
                <label>Enter 6-digit Code</label>
                <input type="text" id="verifyCode" maxlength="6" placeholder="123456">
            </div>
            <button class="btn-primary" onclick="verifyPhoneCodeRequest()">Verify</button>
            <p class="muted-text modal-helper">Didn't receive code? <a href="#" onclick="sendPhoneCode(); return false;">Resend</a></p>
        </div>
        <button class="btn-outline modal-close-btn" onclick="closePhoneModal()">Close</button>
    </div>
</div>

<div id="idModal" class="profile-modal">
    <div class="profile-modal-card large">
        <h3>Upload ID Document</h3>
        <form id="idVerificationForm" enctype="multipart/form-data">
            <div class="form-group">
                <label>Document Type</label>
                <select id="docType" required>
                    <option value="">Select Document</option>
                    <option value="citizenship">Citizenship</option>
                    <option value="passport">Passport</option>
                    <option value="license">Driver's License</option>
                    <option value="voter_id">Voter ID</option>
                </select>
            </div>
            <div class="form-group">
                <label>Document Number</label>
                <input type="text" id="docNumber" placeholder="Enter document number" required>
            </div>
            <div class="form-group">
                <label>Upload Document</label>
                <input type="file" id="docFile" accept=".jpg,.jpeg,.png,.pdf" required>
                <p class="muted-text">Accepted: JPG, PNG, PDF (Max 10MB)</p>
            </div>
            <div id="uploadPreview" class="upload-preview" style="display:none;">
                <img id="previewImage" class="preview-img" alt="Preview">
            </div>
            <button type="submit" class="btn-primary">Submit for Verification</button>
        </form>
        <button class="btn-outline modal-close-btn" onclick="closeIDModal()">Close</button>
    </div>
</div>

<script>
document.getElementById('avatar-upload')?.addEventListener('change', function (e) {
    const file = e.target.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('avatar', file);
    formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');

    fetch('upload-avatar.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message);
            }
        });
});

function showPhoneVerification() {
    document.getElementById('phoneModal').style.display = 'flex';
}

function closePhoneModal() {
    document.getElementById('phoneModal').style.display = 'none';
    document.getElementById('phoneStep1').style.display = 'block';
    document.getElementById('phoneStep2').style.display = 'none';
}

function sendPhoneCode() {
    const phone = document.getElementById('verifyPhone').value;
    if (!phone || phone.length < 10) {
        alert('Please enter a valid phone number');
        return;
    }

    fetch('send-phone-code.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({phone: phone, csrf_token: '<?php echo generateCSRFToken(); ?>'})
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('phoneStep1').style.display = 'none';
                document.getElementById('phoneStep2').style.display = 'block';
                alert('Code sent: ' + data.code);
            } else {
                alert(data.message);
            }
        });
}

function verifyPhoneCodeRequest() {
    const code = document.getElementById('verifyCode').value;
    if (!code || code.length !== 6) {
        alert('Please enter 6-digit code');
        return;
    }

    fetch('verify-phone.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({code: code, csrf_token: '<?php echo generateCSRFToken(); ?>'})
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message);
            }
        });
}

function showIDVerification() {
    document.getElementById('idModal').style.display = 'flex';
}

function closeIDModal() {
    document.getElementById('idModal').style.display = 'none';
    document.getElementById('idVerificationForm').reset();
    document.getElementById('uploadPreview').style.display = 'none';
}

document.getElementById('docFile')?.addEventListener('change', function (e) {
    const file = e.target.files[0];
    if (file && file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = function (evt) {
            document.getElementById('previewImage').src = evt.target.result;
            document.getElementById('uploadPreview').style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
});

document.getElementById('idVerificationForm')?.addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData();
    formData.append('doc_type', document.getElementById('docType').value);
    formData.append('doc_number', document.getElementById('docNumber').value);
    formData.append('doc_file', document.getElementById('docFile').files[0]);
    formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');

    fetch('submit-id-verification.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Documents submitted successfully! They will be reviewed by admin.');
                closeIDModal();
                setTimeout(() => location.reload(), 800);
            } else {
                alert(data.message);
            }
        });
});

function startConversation(userId) {
    window.location.href = '<?php echo SITE_URL; ?>/pages/messages.php?user=' + userId;
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
