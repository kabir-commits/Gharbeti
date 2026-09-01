<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    redirect(SITE_URL . '/auth/login.php');
}

$user_id = getCurrentUserId();
$room_id = isset($_GET['room_id']) ? (int) $_GET['room_id'] : 0;
$tenant_id = isset($_GET['tenant_id']) ? (int) $_GET['tenant_id'] : 0;
$conversation_id = isset($_GET['conversation_id']) ? (int) $_GET['conversation_id'] : 0;

if (!$room_id) {
    redirect(SITE_URL . '/pages/dashboard.php');
}

$room = getRoomDetails($room_id);
if (!$room || ((int) $room['landlord_id'] !== $user_id && !isAdmin())) {
    redirect(SITE_URL . '/pages/dashboard.php');
}

$tenant = $tenant_id ? getUserById($tenant_id) : null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $error = 'Invalid security token';
    } else {
        $data = [
            'room_id' => $room_id,
            'tenant_id' => (int) ($_POST['tenant_id'] ?? 0),
            'landlord_id' => $user_id,
            'conversation_id' => $conversation_id ?: null,
            'monthly_rent' => $_POST['monthly_rent'] ?? '',
            'advance_amount' => $_POST['advance_amount'] ?? '',
            'deposit_amount' => $_POST['deposit_amount'] ?? '',
            'contract_start_date' => $_POST['contract_start_date'] ?? '',
            'contract_end_date' => $_POST['contract_end_date'] ?? null,
            'is_indefinite' => isset($_POST['is_indefinite']),
            'notice_period' => $_POST['notice_period'] ?? 30,
            'payment_day' => $_POST['payment_day'] ?? 1,
            'utilities_included' => isset($_POST['utilities_included']),
            'electricity_charge' => $_POST['electricity_charge'] ?? null,
            'water_charge' => $_POST['water_charge'] ?? null,
            'internet_charge' => $_POST['internet_charge'] ?? null,
            'maintenance_charge' => $_POST['maintenance_charge'] ?? null,
            'parking_charge' => $_POST['parking_charge'] ?? null,
            'guest_policy' => $_POST['guest_policy'] ?? null,
            'pet_policy' => $_POST['pet_policy'] ?? null,
            'smoking_policy' => $_POST['smoking_policy'] ?? null,
            'noise_policy' => $_POST['noise_policy'] ?? null,
            'additional_rules' => $_POST['additional_rules'] ?? null,
        ];
        $result = createContract($data);
        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
            redirect(SITE_URL . '/pages/contract-detail.php?id=' . $result['contract_id']);
        }
        $error = $result['message'];
    }
}

$page_title = 'Create Contract';
require_once __DIR__ . '/../includes/header.php';
$csrf_token = generateCSRFToken();
?>
<section class="dashboard-shell contract-builder-page">
    <div class="dashboard-card dashboard-hero-card" data-animate="fade-up" style="max-width: 1180px;">
        <div class="section-heading compact">
            <div>
                <span class="section-kicker">Contracts</span>
                <h1>Create a polished agreement for <?php echo htmlspecialchars($room['title']); ?></h1>
                <p class="muted-text">Use the same clean, trusted experience your tenants already feel on the homepage.</p>
            </div>
        </div>
        <div class="contract-hero-meta">
            <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($room['location']); ?></span>
            <span><i class="fas fa-money-bill-wave"></i> Listed at Rs. <?php echo number_format((float) $room['price']); ?>/month</span>
            <span><i class="fas fa-shield-alt"></i> Verified workflow</span>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="dashboard-card" data-animate="fade-up" style="max-width: 1180px; padding: 1rem;">
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        </div>
    <?php endif; ?>

    <form method="POST" id="contractForm" style="max-width: 1180px; margin: 0 auto;">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="tenant_id" value="<?php echo $tenant_id ?: ''; ?>">

        <div class="contract-detail-layout">
            <div>
                <?php if (!$tenant_id): ?>
                    <section class="profile-card contract-form-card" data-animate="fade-up">
                        <span class="section-kicker">Tenant</span>
                        <h2 style="margin-bottom: 0.35rem;">Pick the tenant for this agreement</h2>
                        <p class="muted-text" style="margin-bottom: 1rem;">Search by name or email to attach the contract to the right conversation.</p>
                        <input type="text" id="tenant_search" placeholder="Search tenant by name or email" style="width: 100%; padding: 0.95rem 1rem; border: 2px solid var(--border); border-radius: 16px;">
                        <div id="tenant_results" class="contract-search-results"></div>
                    </section>
                <?php else: ?>
                    <section class="profile-card contract-form-card" data-animate="fade-up">
                        <span class="section-kicker">Tenant</span>
                        <h2 style="margin-bottom: 0.75rem;">Selected tenant</h2>
                        <div class="listing-host-row" style="margin-bottom: 0;">
                            <img src="<?php echo getUserAvatarUrl($tenant['avatar'] ?? null); ?>" alt="<?php echo htmlspecialchars($tenant['full_name'] ?? 'Tenant'); ?>" class="listing-host-avatar">
                            <div>
                                <strong><?php echo htmlspecialchars($tenant['full_name'] ?? 'Tenant'); ?></strong><br>
                                <span class="muted-text"><?php echo htmlspecialchars($tenant['email'] ?? ''); ?><?php if (!empty($tenant['phone'])): ?> | <?php echo htmlspecialchars($tenant['phone']); ?><?php endif; ?></span>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>

                <section class="profile-card contract-form-card" data-animate="fade-up">
                    <span class="section-kicker">Rental Terms</span>
                    <h2 style="margin-bottom: 1rem;">Core pricing details</h2>
                    <div class="contract-form-grid">
                        <div>
                            <label for="monthly_rent">Monthly Rent (Rs.) *</label>
                            <input type="number" id="monthly_rent" name="monthly_rent" required value="<?php echo htmlspecialchars($_POST['monthly_rent'] ?? $room['price']); ?>" style="width: 100%; padding: 0.9rem; border: 2px solid var(--border); border-radius: 14px;">
                        </div>
                        <div>
                            <label for="advance_amount">Advance Amount (Rs.) *</label>
                            <input type="number" id="advance_amount" name="advance_amount" required value="<?php echo htmlspecialchars($_POST['advance_amount'] ?? $room['price']); ?>" style="width: 100%; padding: 0.9rem; border: 2px solid var(--border); border-radius: 14px;">
                        </div>
                        <div>
                            <label for="deposit_amount">Security Deposit (Rs.) *</label>
                            <input type="number" id="deposit_amount" name="deposit_amount" required value="<?php echo htmlspecialchars($_POST['deposit_amount'] ?? ((float) $room['price'] * (int) ($room['deposit_months'] ?? 1))); ?>" style="width: 100%; padding: 0.9rem; border: 2px solid var(--border); border-radius: 14px;">
                        </div>
                        <div>
                            <label for="payment_day">Payment Day</label>
                            <select id="payment_day" name="payment_day" style="width: 100%; padding: 0.9rem; border: 2px solid var(--border); border-radius: 14px;">
                                <?php for ($i = 1; $i <= 28; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ((int) ($_POST['payment_day'] ?? 1) === $i) ? 'selected' : ''; ?>><?php echo ordinal($i); ?> of each month</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="profile-card contract-form-card" data-animate="fade-up">
                    <span class="section-kicker">Duration</span>
                    <h2 style="margin-bottom: 1rem;">Set the agreement timeline</h2>
                    <label style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 1rem;">
                        <input type="checkbox" name="is_indefinite" id="is_indefinite" <?php echo isset($_POST['is_indefinite']) ? 'checked' : ''; ?>>
                        <span>Indefinite contract</span>
                    </label>
                    <div class="contract-form-grid three">
                        <div>
                            <label for="contract_start_date">Start Date *</label>
                            <input type="date" id="contract_start_date" name="contract_start_date" required value="<?php echo htmlspecialchars($_POST['contract_start_date'] ?? date('Y-m-d')); ?>" style="width: 100%; padding: 0.9rem; border: 2px solid var(--border); border-radius: 14px;">
                        </div>
                        <div id="endDateField">
                            <label for="contract_end_date">End Date</label>
                            <input type="date" id="contract_end_date" name="contract_end_date" value="<?php echo htmlspecialchars($_POST['contract_end_date'] ?? date('Y-m-d', strtotime('+1 year'))); ?>" style="width: 100%; padding: 0.9rem; border: 2px solid var(--border); border-radius: 14px;">
                        </div>
                        <div>
                            <label for="notice_period">Notice Period (days)</label>
                            <input type="number" id="notice_period" name="notice_period" min="1" value="<?php echo htmlspecialchars($_POST['notice_period'] ?? 30); ?>" style="width: 100%; padding: 0.9rem; border: 2px solid var(--border); border-radius: 14px;">
                        </div>
                    </div>
                </section>

                <section class="profile-card contract-form-card" data-animate="fade-up">
                    <span class="section-kicker">Utilities</span>
                    <h2 style="margin-bottom: 1rem;">Monthly charges and add-ons</h2>
                    <label style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 1rem;">
                        <input type="checkbox" name="utilities_included" id="utilities_included" <?php echo !empty($_POST['utilities_included']) || !empty($room['utilities_included']) ? 'checked' : ''; ?>>
                        <span>All utilities included in rent</span>
                    </label>
                    <div id="utilityFields" class="contract-form-grid">
                        <div><label for="electricity_charge">Electricity Charge</label><input type="text" id="electricity_charge" name="electricity_charge" value="<?php echo htmlspecialchars($_POST['electricity_charge'] ?? ($room['electricity_charge'] ?? '')); ?>" style="width: 100%; padding: 0.9rem; border: 2px solid var(--border); border-radius: 14px;"></div>
                        <div><label for="water_charge">Water Charge</label><input type="text" id="water_charge" name="water_charge" value="<?php echo htmlspecialchars($_POST['water_charge'] ?? ($room['water_charge'] ?? '')); ?>" style="width: 100%; padding: 0.9rem; border: 2px solid var(--border); border-radius: 14px;"></div>
                        <div><label for="internet_charge">Internet Charge</label><input type="text" id="internet_charge" name="internet_charge" value="<?php echo htmlspecialchars($_POST['internet_charge'] ?? ($room['internet_charge'] ?? '')); ?>" style="width: 100%; padding: 0.9rem; border: 2px solid var(--border); border-radius: 14px;"></div>
                        <div><label for="maintenance_charge">Maintenance Charge</label><input type="text" id="maintenance_charge" name="maintenance_charge" value="<?php echo htmlspecialchars($_POST['maintenance_charge'] ?? ''); ?>" style="width: 100%; padding: 0.9rem; border: 2px solid var(--border); border-radius: 14px;"></div>
                        <div><label for="parking_charge">Parking Charge</label><input type="text" id="parking_charge" name="parking_charge" value="<?php echo htmlspecialchars($_POST['parking_charge'] ?? ''); ?>" style="width: 100%; padding: 0.9rem; border: 2px solid var(--border); border-radius: 14px;"></div>
                    </div>
                </section>

                <section class="profile-card contract-form-card" data-animate="fade-up">
                    <span class="section-kicker">House Rules</span>
                    <h2 style="margin-bottom: 1rem;">Write clear expectations</h2>
                    <div style="display: grid; gap: 1rem;">
                        <div><label for="guest_policy">Guest Policy</label><textarea id="guest_policy" name="guest_policy" rows="3" style="width: 100%; padding: 0.95rem; border: 2px solid var(--border); border-radius: 14px;"><?php echo htmlspecialchars($_POST['guest_policy'] ?? 'Guests allowed with prior notice. Overnight stays limited to 3 days.'); ?></textarea></div>
                        <div><label for="pet_policy">Pet Policy</label><textarea id="pet_policy" name="pet_policy" rows="3" style="width: 100%; padding: 0.95rem; border: 2px solid var(--border); border-radius: 14px;"><?php echo htmlspecialchars($_POST['pet_policy'] ?? 'No pets allowed without prior permission.'); ?></textarea></div>
                        <div><label for="smoking_policy">Smoking Policy</label><textarea id="smoking_policy" name="smoking_policy" rows="3" style="width: 100%; padding: 0.95rem; border: 2px solid var(--border); border-radius: 14px;"><?php echo htmlspecialchars($_POST['smoking_policy'] ?? 'No smoking inside the room. Smoking only in designated areas.'); ?></textarea></div>
                        <div><label for="noise_policy">Noise Policy</label><textarea id="noise_policy" name="noise_policy" rows="3" style="width: 100%; padding: 0.95rem; border: 2px solid var(--border); border-radius: 14px;"><?php echo htmlspecialchars($_POST['noise_policy'] ?? 'Please maintain silence after 10 PM.'); ?></textarea></div>
                        <div><label for="additional_rules">Additional Rules</label><textarea id="additional_rules" name="additional_rules" rows="4" style="width: 100%; padding: 0.95rem; border: 2px solid var(--border); border-radius: 14px;"><?php echo htmlspecialchars($_POST['additional_rules'] ?? ''); ?></textarea></div>
                    </div>
                </section>
            </div>

            <div>
                <aside class="profile-card contract-side-card sticky-sidebar-card" data-animate="fade-up">
                    <span class="section-kicker">Preview</span>
                    <h3 style="margin-bottom: 0.8rem;">Agreement snapshot</h3>
                    <div class="contract-action-stack">
                        <div class="profile-mini-card">
                            <span class="profile-mini-label">Room</span>
                            <strong><?php echo htmlspecialchars($room['title']); ?></strong>
                            <p class="muted-text"><?php echo htmlspecialchars($room['location']); ?></p>
                        </div>
                        <div class="profile-mini-card">
                            <span class="profile-mini-label">Listed Rent</span>
                            <strong>Rs. <?php echo number_format((float) $room['price']); ?></strong>
                        </div>
                        <div class="profile-mini-card">
                            <span class="profile-mini-label">Suggested Deposit</span>
                            <strong>Rs. <?php echo number_format((float) $room['price'] * (int) ($room['deposit_months'] ?? 1)); ?></strong>
                        </div>
                    </div>
                    <div class="contract-sign-state">
                        <p class="muted-text" style="margin-bottom: 1rem;">Once created, both landlord and tenant will be able to review and sign digitally.</p>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <a href="<?php echo SITE_URL; ?>/pages/room-detail.php?id=<?php echo (int) $room_id; ?>" class="btn-outline" style="text-align: center;">Cancel</a>
                            <button type="submit" class="btn-primary"><i class="fas fa-file-signature"></i> Create Contract</button>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </form>
</section>
<script>
const indefiniteCheckbox = document.getElementById('is_indefinite');
const endDateField = document.getElementById('endDateField');
const endDateInput = document.getElementById('contract_end_date');
const utilityToggle = document.getElementById('utilities_included');
const utilityFields = document.getElementById('utilityFields');

function syncContractFields() {
    if (indefiniteCheckbox?.checked) {
        endDateField.style.opacity = '0.5';
        endDateInput.disabled = true;
        endDateInput.value = '';
    } else if (endDateInput) {
        endDateField.style.opacity = '1';
        endDateInput.disabled = false;
    }

    if (utilityToggle?.checked) {
        utilityFields.style.opacity = '0.5';
        utilityFields.style.pointerEvents = 'none';
        utilityFields.querySelectorAll('input').forEach((input) => { input.disabled = true; });
    } else {
        utilityFields.style.opacity = '1';
        utilityFields.style.pointerEvents = 'auto';
        utilityFields.querySelectorAll('input').forEach((input) => { input.disabled = false; });
    }
}

indefiniteCheckbox?.addEventListener('change', syncContractFields);
utilityToggle?.addEventListener('change', syncContractFields);
syncContractFields();

const tenantSearch = document.getElementById('tenant_search');
if (tenantSearch) {
    let timeout = null;
    tenantSearch.addEventListener('input', function () {
        clearTimeout(timeout);
        const query = this.value.trim();
        const results = document.getElementById('tenant_results');
        if (query.length < 3) {
            results.style.display = 'none';
            results.innerHTML = '';
            return;
        }

        timeout = setTimeout(() => {
            fetch('<?php echo SITE_URL; ?>/api/search-users.php?q=' + encodeURIComponent(query) + '&role=tenant')
                .then((response) => response.json())
                .then((data) => {
                    results.innerHTML = '';
                    if (!data.users || data.users.length === 0) {
                        results.innerHTML = '<div style="padding:0.85rem;color:var(--text-light);">No tenants found.</div>';
                        results.style.display = 'block';
                        return;
                    }

                    data.users.forEach((user) => {
                        const item = document.createElement('button');
                        item.type = 'button';
                        item.className = 'contract-search-item';
                        const avatar = user.avatar ? ('<?php echo SITE_URL; ?>/assets/uploads/avatars/' + user.avatar) : '<?php echo SITE_URL; ?>/assets/images/default-avatar.png';
                        item.innerHTML = `<img src="${avatar}" style="width:36px;height:36px;border-radius:50%;object-fit:cover;"><div><strong>${user.full_name || user.email}</strong><br><span style="color:var(--text-light);font-size:0.9rem;">${user.email}</span></div>`;
                        item.addEventListener('click', () => {
                            document.querySelector('input[name="tenant_id"]').value = user.id;
                            tenantSearch.value = `${user.full_name || user.email} (${user.email})`;
                            results.style.display = 'none';
                        });
                        results.appendChild(item);
                    });
                    results.style.display = 'block';
                });
        }, 350);
    });
}

document.getElementById('contractForm').addEventListener('submit', function (event) {
    const tenantId = document.querySelector('input[name="tenant_id"]').value;
    if (!tenantId) {
        event.preventDefault();
        alert('Please select a tenant.');
    }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
