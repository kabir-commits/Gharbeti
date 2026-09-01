<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
if (!isLoggedIn()) { redirect(SITE_URL . '/auth/login.php'); }
$room_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$room = getRoomDetails($room_id);
if (!$room || ((int) $room['landlord_id'] !== (int) getCurrentUserId() && !isAdmin())) {
    $_SESSION['error'] = 'Room not found or you do not have permission';
    redirect(SITE_URL . '/pages/dashboard.php');
}
$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
$amenities_by_category = getAmenitiesByCategory();
$room_amenities = array_column($room['amenities'] ?? [], 'id');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $error = 'Invalid security token';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'update_details') {
            $data = ['title'=>$_POST['title'] ?? '','description'=>$_POST['description'] ?? '','price'=>$_POST['price'] ?? '','location'=>$_POST['location'] ?? '','address'=>$_POST['address'] ?? '','floor_area'=>$_POST['floor_area'] ?: null,'bedroom_count'=>$_POST['bedroom_count'] ?: 1,'bathroom_count'=>$_POST['bathroom_count'] ?: 1,'kitchen_type'=>$_POST['kitchen_type'] ?? 'private','floor_number'=>$_POST['floor_number'] ?: null,'total_floors'=>$_POST['total_floors'] ?: null,'available_from'=>$_POST['available_from'] ?? date('Y-m-d'),'minimum_stay'=>$_POST['minimum_stay'] ?: 1,'deposit_months'=>$_POST['deposit_months'] ?: 1,'utilities_included'=>isset($_POST['utilities_included']),'electricity_charge'=>$_POST['electricity_charge'] ?? '','water_charge'=>$_POST['water_charge'] ?? '','internet_charge'=>$_POST['internet_charge'] ?? '','amenities'=>$_POST['amenities'] ?? []];
            $result = updateRoom($room_id, getCurrentUserId(), $data);
            $_SESSION[$result['success'] ? 'success' : 'error'] = $result['message'];
            redirect('edit-listing.php?id=' . $room_id);
        } elseif ($action === 'delete_image') {
            $result = deleteRoomImage((int) $_POST['image_id'], getCurrentUserId());
            $_SESSION[$result['success'] ? 'success' : 'error'] = $result['message'];
            redirect('edit-listing.php?id=' . $room_id);
        } elseif ($action === 'set_primary') {
            $result = setPrimaryImage($room_id, (int) $_POST['image_id'], getCurrentUserId());
            $_SESSION[$result['success'] ? 'success' : 'error'] = $result['message'];
            redirect('edit-listing.php?id=' . $room_id);
        } elseif ($action === 'delete_listing') {
            $result = deleteRoom($room_id, getCurrentUserId());
            if ($result['success']) {
                $_SESSION['success'] = 'Listing deleted successfully';
                redirect('dashboard.php');
            }
            $error = $result['message'];
        }
    }
}
$page_title = 'Edit Listing';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell listing-builder-page">
    <div class="container listing-builder-container">
        <div class="dashboard-card dashboard-hero-card" data-animate="fade-up">
            <p class="dashboard-kicker">Listing Editor</p>
            <h1>Edit Listing</h1>
            <p><?php echo htmlspecialchars($room['title']); ?> | <?php echo ucfirst($room['status']); ?><?php echo !empty($room['is_verified']) ? ' | Verified' : ''; ?></p>
        </div>

        <?php if ($error): ?><div class="alert alert-error" data-animate="fade-up"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success" data-animate="fade-up"><?php echo $success; ?></div><?php endif; ?>

        <div class="profile-card listing-builder-form" data-animate="fade-up">
            <div class="section-heading compact contract-section-gap">
                <span class="section-kicker">Media</span>
                <h2>Photos</h2>
                <p class="muted-text">Photos currently attached to this listing.</p>
            </div>
            <div class="listing-editor-topbar">
                <span class="muted-text"><?php echo count($room['images'] ?? []); ?>/10 images</span>
                <a href="upload-room-images.php?room_id=<?php echo $room_id; ?>" class="btn-primary">Add More</a>
            </div>
            <?php if (empty($room['images'])): ?>
                <div class="admin-empty-state"><p class="muted-text">No photos yet.</p></div>
            <?php else: ?>
                <div class="listing-editor-image-grid">
                    <?php foreach ($room['images'] as $image): ?>
                        <div class="listing-editor-image-card">
                            <img src="<?php echo getRoomImageUrl($image['image_url']); ?>" class="listing-editor-image" alt="Listing image">
                            <div class="listing-editor-image-overlay">
                                <?php if (!$image['is_primary']): ?>
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="action" value="set_primary">
                                        <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                        <button type="submit" class="btn-outline btn-small" style="background:white;">Set Primary</button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" onsubmit="return confirm('Delete this image?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="delete_image">
                                    <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                    <button type="submit" class="btn-small listing-delete-btn">Delete</button>
                                </form>
                            </div>
                            <?php if ($image['is_primary']): ?><span class="listing-image-primary-pill">Primary</span><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <form method="POST" class="profile-card listing-builder-form" data-animate="fade-up">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="update_details">

            <div class="section-heading compact">
                <span class="section-kicker">Basics</span>
                <h2>Edit Details</h2>
            </div>
            <div class="profile-form-grid">
                <div class="form-group span-2"><label for="title">Title *</label><input type="text" id="title" name="title" required value="<?php echo htmlspecialchars($room['title']); ?>"></div>
                <div class="form-group span-2"><label for="description">Description *</label><textarea id="description" name="description" rows="6" required><?php echo htmlspecialchars($room['description']); ?></textarea></div>
                <div class="form-group"><label for="price">Price (Rs.) *</label><input type="number" id="price" name="price" required value="<?php echo $room['price']; ?>"></div>
                <div class="form-group"><label for="location">Location *</label><select id="location" name="location" required><?php foreach(['Kathmandu','Lalitpur','Bhaktapur','Pokhara','Chitwan','Biratnagar','Butwal','Dharan','Nepalgunj','Janakpur'] as $loc): ?><option value="<?php echo $loc; ?>" <?php echo $room['location'] === $loc ? 'selected' : ''; ?>><?php echo $loc; ?></option><?php endforeach; ?></select></div>
                <div class="form-group span-2"><label for="address">Address</label><input type="text" id="address" name="address" value="<?php echo htmlspecialchars($room['address'] ?? ''); ?>"></div>
                <div class="form-group"><label for="floor_area">Floor Area</label><input type="number" id="floor_area" name="floor_area" value="<?php echo htmlspecialchars((string) ($room['floor_area'] ?? '')); ?>"></div>
                <div class="form-group"><label for="bedroom_count">Bedrooms</label><input type="number" id="bedroom_count" name="bedroom_count" min="1" value="<?php echo $room['bedroom_count']; ?>"></div>
                <div class="form-group"><label for="bathroom_count">Bathrooms</label><input type="number" id="bathroom_count" name="bathroom_count" min="1" value="<?php echo $room['bathroom_count']; ?>"></div>
                <div class="form-group"><label for="kitchen_type">Kitchen Type</label><select id="kitchen_type" name="kitchen_type"><option value="private" <?php echo ($room['kitchen_type'] ?? '') === 'private' ? 'selected' : ''; ?>>Private Kitchen</option><option value="shared" <?php echo ($room['kitchen_type'] ?? '') === 'shared' ? 'selected' : ''; ?>>Shared Kitchen</option><option value="none" <?php echo ($room['kitchen_type'] ?? '') === 'none' ? 'selected' : ''; ?>>No Kitchen</option></select></div>
                <div class="form-group"><label for="floor_number">Floor Number</label><input type="number" id="floor_number" name="floor_number" value="<?php echo htmlspecialchars((string) ($room['floor_number'] ?? '')); ?>"></div>
                <div class="form-group"><label for="total_floors">Total Floors</label><input type="number" id="total_floors" name="total_floors" value="<?php echo htmlspecialchars((string) ($room['total_floors'] ?? '')); ?>"></div>
                <div class="form-group"><label for="available_from">Available From</label><input type="date" id="available_from" name="available_from" value="<?php echo htmlspecialchars((string) ($room['available_from'] ?? date('Y-m-d'))); ?>"></div>
                <div class="form-group"><label for="minimum_stay">Minimum Stay</label><input type="number" id="minimum_stay" name="minimum_stay" min="1" value="<?php echo $room['minimum_stay'] ?? 1; ?>"></div>
                <div class="form-group"><label for="deposit_months">Deposit</label><input type="number" id="deposit_months" name="deposit_months" min="0" value="<?php echo $room['deposit_months'] ?? 1; ?>"></div>
            </div>

            <div class="section-heading compact contract-section-gap"><span class="section-kicker">Charges</span><h3>Utilities</h3></div>
            <div class="form-group"><label class="checkbox-label compact-checkbox"><input type="checkbox" name="utilities_included" id="utilities_included" <?php echo !empty($room['utilities_included']) ? 'checked' : ''; ?>><span>All utilities included in rent</span></label></div>
            <div class="profile-form-grid" id="utilityFields">
                <div class="form-group"><label for="electricity_charge">Electricity Charge</label><input type="text" id="electricity_charge" name="electricity_charge" value="<?php echo htmlspecialchars($room['electricity_charge'] ?? ''); ?>"></div>
                <div class="form-group"><label for="water_charge">Water Charge</label><input type="text" id="water_charge" name="water_charge" value="<?php echo htmlspecialchars($room['water_charge'] ?? ''); ?>"></div>
                <div class="form-group"><label for="internet_charge">Internet Charge</label><input type="text" id="internet_charge" name="internet_charge" value="<?php echo htmlspecialchars($room['internet_charge'] ?? ''); ?>"></div>
            </div>

            <div class="section-heading compact contract-section-gap"><span class="section-kicker">Comfort</span><h3>Amenities</h3></div>
            <?php foreach ($amenities_by_category as $category => $amenities): ?>
                <div class="listing-amenity-group">
                    <div class="form-section-title listing-amenity-group-title"><?php echo ucfirst($category); ?></div>
                    <div class="listing-builder-amenity-grid">
                        <?php foreach ($amenities as $amenity): ?>
                            <label class="amenity-checkbox listing-builder-amenity-item">
                                <input type="checkbox" name="amenities[]" value="<?php echo $amenity['id']; ?>" <?php echo in_array($amenity['id'], $room_amenities) ? 'checked' : ''; ?>>
                                <?php if ($amenity['icon']): ?><i class="fas fa-<?php echo $amenity['icon']; ?>"></i><?php endif; ?>
                                <span><?php echo $amenity['name']; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="listing-editor-actions">
                <button type="button" class="btn-small listing-delete-btn-large" onclick="confirmDelete()">Delete Listing</button>
                <div class="tag-row">
                    <a href="dashboard.php" class="btn-outline">Cancel</a>
                    <button type="submit" class="btn-primary">Save Changes</button>
                </div>
            </div>
        </form>

        <form method="POST" id="deleteForm" style="display:none;">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="delete_listing">
        </form>
    </div>
</section>
<script>
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
function confirmDelete() {
    if (confirm('Are you sure you want to delete this listing? This action cannot be undone.')) {
        document.getElementById('deleteForm').submit();
    }
}
window.addEventListener('load', function () {
    const checkbox = document.getElementById('utilities_included');
    if (checkbox && checkbox.checked) checkbox.dispatchEvent(new Event('change'));
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
