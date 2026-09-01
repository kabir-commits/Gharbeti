<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    redirect(SITE_URL . '/auth/login.php');
}
if (getCurrentUserRole() !== 'landlord' && !isAdmin()) {
    $_SESSION['error'] = 'Only landlords can create listings';
    redirect(SITE_URL . '/pages/dashboard.php');
}

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
$amenities_by_category = getAmenitiesByCategory();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $error = 'Invalid security token';
    } else {
        $data = [
            'title' => $_POST['title'] ?? '',
            'description' => $_POST['description'] ?? '',
            'price' => $_POST['price'] ?? '',
            'location' => $_POST['location'] ?? '',
            'address' => $_POST['address'] ?? '',
            'floor_area' => $_POST['floor_area'] ?: null,
            'bedroom_count' => $_POST['bedroom_count'] ?: 1,
            'bathroom_count' => $_POST['bathroom_count'] ?: 1,
            'kitchen_type' => $_POST['kitchen_type'] ?? 'private',
            'floor_number' => $_POST['floor_number'] ?: null,
            'total_floors' => $_POST['total_floors'] ?: null,
            'available_from' => $_POST['available_from'] ?? date('Y-m-d'),
            'minimum_stay' => $_POST['minimum_stay'] ?: 1,
            'deposit_months' => $_POST['deposit_months'] ?: 1,
            'utilities_included' => isset($_POST['utilities_included']),
            'electricity_charge' => $_POST['electricity_charge'] ?? '',
            'water_charge' => $_POST['water_charge'] ?? '',
            'internet_charge' => $_POST['internet_charge'] ?? '',
            'amenities' => $_POST['amenities'] ?? []
        ];
        $result = createRoom(getCurrentUserId(), $data);
        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
            $_SESSION['room_id'] = $result['room_id'];
            if (!empty($_FILES['images']['name'][0])) {
                $_SESSION['redirect_to'] = 'edit-listing.php?id=' . $result['room_id'];
                redirect('upload-room-images.php?room_id=' . $result['room_id']);
            }
            redirect('edit-listing.php?id=' . $result['room_id']);
        }
        $error = $result['message'];
    }
}

$page_title = 'List Your Room';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell listing-builder-page">
    <div class="container listing-builder-container">
        <div class="dashboard-card dashboard-hero-card" data-animate="fade-up">
            <p class="dashboard-kicker">Landlord Portal</p>
            <h1>List Your Room</h1>
            <p>Create a polished listing with amenities, terms, and photos. New listings start in review mode so the platform stays trustworthy.</p>
        </div>

        <?php if ($error): ?><div class="alert alert-error" data-animate="fade-up"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success" data-animate="fade-up"><?php echo $success; ?></div><?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="listingForm" class="profile-card listing-builder-form" data-animate="fade-up">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

            <div class="section-heading compact">
                <span class="section-kicker">Basics</span>
                <h2>Basic Information</h2>
            </div>
            <div class="profile-form-grid">
                <div class="form-group span-2">
                    <label for="title">Listing Title *</label>
                    <input type="text" id="title" name="title" required placeholder="e.g., Modern 1BHK in Lalitpur" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                </div>
                <div class="form-group span-2">
                    <label for="description">Description *</label>
                    <textarea id="description" name="description" rows="6" required placeholder="Describe the room, neighborhood, accessibility, nearby amenities, and what makes this place special."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="price">Monthly Rent (Rs.) *</label>
                    <input type="number" id="price" name="price" min="1000" step="100" required value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="location">Location *</label>
                    <select id="location" name="location" required>
                        <option value="">Select Location</option>
                        <?php foreach (['Kathmandu','Lalitpur','Bhaktapur','Pokhara','Chitwan','Biratnagar','Butwal','Dharan','Nepalgunj','Janakpur'] as $loc): ?>
                            <option value="<?php echo $loc; ?>" <?php echo (($_POST['location'] ?? '') === $loc) ? 'selected' : ''; ?>><?php echo $loc; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group span-2">
                    <label for="address">Full Address</label>
                    <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>" placeholder="Street, tole, nearby landmark">
                </div>
            </div>

            <div class="section-heading compact listing-section-gap">
                <span class="section-kicker">Room Details</span>
                <h2>Space & Layout</h2>
            </div>
            <div class="profile-form-grid">
                <div class="form-group"><label for="floor_area">Floor Area (sq ft)</label><input type="number" id="floor_area" name="floor_area" value="<?php echo htmlspecialchars($_POST['floor_area'] ?? ''); ?>"></div>
                <div class="form-group"><label for="bedroom_count">Bedrooms</label><input type="number" id="bedroom_count" name="bedroom_count" min="1" value="<?php echo htmlspecialchars($_POST['bedroom_count'] ?? '1'); ?>"></div>
                <div class="form-group"><label for="bathroom_count">Bathrooms</label><input type="number" id="bathroom_count" name="bathroom_count" min="1" value="<?php echo htmlspecialchars($_POST['bathroom_count'] ?? '1'); ?>"></div>
                <div class="form-group"><label for="kitchen_type">Kitchen Type</label><select id="kitchen_type" name="kitchen_type"><option value="private" <?php echo (($_POST['kitchen_type'] ?? 'private') === 'private') ? 'selected' : ''; ?>>Private Kitchen</option><option value="shared" <?php echo (($_POST['kitchen_type'] ?? '') === 'shared') ? 'selected' : ''; ?>>Shared Kitchen</option><option value="none" <?php echo (($_POST['kitchen_type'] ?? '') === 'none') ? 'selected' : ''; ?>>No Kitchen</option></select></div>
                <div class="form-group"><label for="floor_number">Floor Number</label><input type="number" id="floor_number" name="floor_number" value="<?php echo htmlspecialchars($_POST['floor_number'] ?? ''); ?>"></div>
                <div class="form-group"><label for="total_floors">Total Floors</label><input type="number" id="total_floors" name="total_floors" value="<?php echo htmlspecialchars($_POST['total_floors'] ?? ''); ?>"></div>
            </div>

            <div class="section-heading compact listing-section-gap">
                <span class="section-kicker">Terms</span>
                <h2>Availability & Deposit</h2>
            </div>
            <div class="profile-form-grid">
                <div class="form-group"><label for="available_from">Available From</label><input type="date" id="available_from" name="available_from" value="<?php echo htmlspecialchars($_POST['available_from'] ?? date('Y-m-d')); ?>"></div>
                <div class="form-group"><label for="minimum_stay">Minimum Stay (months)</label><input type="number" id="minimum_stay" name="minimum_stay" min="1" value="<?php echo htmlspecialchars($_POST['minimum_stay'] ?? '1'); ?>"></div>
                <div class="form-group"><label for="deposit_months">Deposit (months)</label><input type="number" id="deposit_months" name="deposit_months" min="0" max="12" value="<?php echo htmlspecialchars($_POST['deposit_months'] ?? '1'); ?>"></div>
            </div>

            <div class="section-heading compact listing-section-gap">
                <span class="section-kicker">Charges</span>
                <h2>Utilities & Bills</h2>
            </div>
            <div class="form-group">
                <label class="checkbox-label compact-checkbox"><input type="checkbox" name="utilities_included" id="utilities_included" <?php echo isset($_POST['utilities_included']) ? 'checked' : ''; ?>><span>All utilities included in rent</span></label>
            </div>
            <div class="profile-form-grid" id="utilityFields">
                <div class="form-group"><label for="electricity_charge">Electricity Charge</label><input type="text" id="electricity_charge" name="electricity_charge" value="<?php echo htmlspecialchars($_POST['electricity_charge'] ?? ''); ?>" placeholder="Rs. 500 or As per meter"></div>
                <div class="form-group"><label for="water_charge">Water Charge</label><input type="text" id="water_charge" name="water_charge" value="<?php echo htmlspecialchars($_POST['water_charge'] ?? ''); ?>"></div>
                <div class="form-group"><label for="internet_charge">Internet Charge</label><input type="text" id="internet_charge" name="internet_charge" value="<?php echo htmlspecialchars($_POST['internet_charge'] ?? ''); ?>"></div>
            </div>

            <div class="section-heading compact listing-section-gap">
                <span class="section-kicker">Comfort</span>
                <h2>Amenities</h2>
                <p class="muted-text">Select all amenities that apply to your room.</p>
            </div>
            <?php foreach ($amenities_by_category as $category => $amenities): ?>
                <div class="listing-amenity-group">
                    <div class="form-section-title listing-amenity-group-title"><?php echo ucfirst($category); ?></div>
                    <div class="listing-builder-amenity-grid">
                        <?php foreach ($amenities as $amenity): ?>
                            <label class="amenity-checkbox listing-builder-amenity-item">
                                <input type="checkbox" name="amenities[]" value="<?php echo $amenity['id']; ?>" <?php echo (isset($_POST['amenities']) && in_array($amenity['id'], $_POST['amenities'])) ? 'checked' : ''; ?>>
                                <?php if ($amenity['icon']): ?><i class="fas fa-<?php echo $amenity['icon']; ?>"></i><?php endif; ?>
                                <span><?php echo $amenity['name']; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="section-heading compact listing-section-gap">
                <span class="section-kicker">Media</span>
                <h2>Photos</h2>
                <p class="muted-text">You can upload up to 10 images. The first image becomes the primary cover.</p>
            </div>
            <div class="form-group">
                <label for="imageUpload">Upload Photos</label>
                <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" id="imageUpload" class="listing-upload-input">
            </div>
            <div id="imagePreview" class="listing-image-preview-grid"></div>

            <div class="dashboard-actions listing-builder-actions">
                <a href="dashboard.php" class="btn-outline">Cancel</a>
                <button type="submit" class="btn-primary"><i class="fas fa-plus-circle"></i> Create Listing</button>
            </div>
        </form>
    </div>
</section>
<script>
document.getElementById('imageUpload')?.addEventListener('change', function (e) {
    const preview = document.getElementById('imagePreview');
    preview.innerHTML = '';
    const files = Array.from(e.target.files);
    if (files.length > 10) {
        alert('Maximum 10 images allowed');
        e.target.value = '';
        return;
    }
    files.forEach((file, index) => {
        if (!file.type.startsWith('image/')) return;
        const reader = new FileReader();
        reader.onload = function (evt) {
            const div = document.createElement('div');
            div.className = 'listing-image-preview-item';
            div.innerHTML = `<img src="${evt.target.result}" class="listing-image-preview-thumb">${index === 0 ? '<span class="listing-image-primary-pill">Primary</span>' : ''}`;
            preview.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
});

document.getElementById('utilities_included')?.addEventListener('change', function () {
    const fields = document.getElementById('utilityFields');
    const inputs = fields.querySelectorAll('input');
    if (this.checked) {
        fields.style.opacity = '0.5';
        fields.style.pointerEvents = 'none';
        inputs.forEach((input) => input.disabled = true);
    } else {
        fields.style.opacity = '1';
        fields.style.pointerEvents = 'auto';
        inputs.forEach((input) => input.disabled = false);
    }
});

document.getElementById('listingForm')?.addEventListener('submit', function (e) {
    const price = Number(document.getElementById('price').value || 0);
    const title = document.getElementById('title').value.trim();
    const description = document.getElementById('description').value.trim();
    if (price < 1000) {
        e.preventDefault();
        alert('Price must be at least Rs. 1,000');
        return;
    }
    if (title.length < 10) {
        e.preventDefault();
        alert('Title must be at least 10 characters long');
        return;
    }
    if (description.length < 50) {
        e.preventDefault();
        alert('Please provide a more detailed description (minimum 50 characters)');
    }
});

window.addEventListener('load', function () {
    const checkbox = document.getElementById('utilities_included');
    if (checkbox && checkbox.checked) {
        checkbox.dispatchEvent(new Event('change'));
    }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
