<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$room_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$room = getRoomDetails($room_id);

if (!$room || $room['status'] !== 'active') {
    header('HTTP/1.0 404 Not Found');
    $page_title = 'Room Not Found';
    require_once __DIR__ . '/../includes/header.php';
    echo '<section class="dashboard-shell"><div class="dashboard-card admin-empty-state"><h1>Room Not Found</h1><p class="muted-text">The room you\'re looking for does not exist or is not available.</p><a href="rooms.php" class="btn-primary">Browse Rooms</a></div></section>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

recordRoomView($room_id, isLoggedIn() ? getCurrentUserId() : null);
$similar_rooms = getSimilarRooms($room_id, $room['location'], $room['price']);
$page_title = $room['title'];
require_once __DIR__ . '/../includes/header.php';

$images = $room['images'] ?? [];
$primary_image = null;
foreach ($images as $image) {
    if (!empty($image['is_primary'])) {
        $primary_image = $image;
        break;
    }
}
if (!$primary_image && !empty($images)) {
    $primary_image = $images[0];
}
?>
<section class="dashboard-shell listing-page">
    <div class="container listing-page-container">
        <div class="dashboard-card dashboard-hero-card listing-hero" data-animate="fade-up">
            <div>
                <a href="rooms.php" class="btn-outline btn-small listing-back-btn"><i class="fas fa-arrow-left"></i> Back to Rooms</a>
                <p class="dashboard-kicker">Verified Listing</p>
                <h1><?php echo htmlspecialchars($room['title']); ?></h1>
                <p><?php echo htmlspecialchars($room['location'] . (!empty($room['address']) ? ' - ' . $room['address'] : '')); ?></p>
            </div>
            <div class="listing-hero-price">
                <strong>Rs. <?php echo number_format((float) $room['price']); ?></strong>
                <span>per month</span>
            </div>
        </div>

        <div class="profile-card listing-gallery-panel" data-animate="fade-up">
            <div class="listing-gallery-grid">
                <div class="listing-gallery-main">
                    <img src="<?php echo getRoomImageUrl($primary_image['image_url'] ?? null); ?>" id="mainImage" class="listing-main-image" onclick="openLightbox()" alt="<?php echo htmlspecialchars($room['title']); ?>">
                </div>
                <div class="listing-gallery-thumbs">
                    <?php foreach (array_slice($images, 0, 4) as $image): ?>
                        <?php if ($primary_image && (int) $image['id'] === (int) $primary_image['id']) { continue; } ?>
                        <img src="<?php echo getRoomImageUrl($image['image_url']); ?>" onclick="changeMainImage('<?php echo getRoomImageUrl($image['image_url']); ?>')" class="listing-thumb-image" alt="Room photo">
                    <?php endforeach; ?>
                    <?php if (count($images) > 5): ?>
                        <button type="button" onclick="openLightbox()" class="listing-thumb-more">+<?php echo count($images) - 4; ?> more</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="listing-layout">
            <div class="listing-main-column">
                <div class="profile-card" data-animate="fade-up">
                    <div class="listing-meta-row">
                        <div class="muted-text listing-meta-inline">
                            <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($room['location']); ?></span>
                            <span><i class="fas fa-eye"></i> <?php echo (int) $room['view_count']; ?> views</span>
                            <span><i class="fas fa-heart"></i> <?php echo (int) $room['favorite_count']; ?> favorites</span>
                        </div>
                    </div>

                    <div class="profile-overview-grid listing-stat-grid">
                        <div class="profile-mini-card" data-animate="fade-up"><span class="profile-mini-label">Bedrooms</span><strong><?php echo (int) $room['bedroom_count']; ?></strong></div>
                        <div class="profile-mini-card" data-animate="fade-up"><span class="profile-mini-label">Bathrooms</span><strong><?php echo (int) $room['bathroom_count']; ?></strong></div>
                        <div class="profile-mini-card" data-animate="fade-up"><span class="profile-mini-label">Floor Area</span><strong><?php echo $room['floor_area'] ? (int) $room['floor_area'] . ' sqft' : '-'; ?></strong></div>
                    </div>

                    <div class="section-heading compact listing-section-gap">
                        <span class="section-kicker">Overview</span>
                        <h2>Description</h2>
                    </div>
                    <p class="muted-text listing-copy"><?php echo nl2br(htmlspecialchars($room['description'])); ?></p>

                    <?php if (!empty($room['amenities'])): ?>
                        <div class="section-heading compact listing-section-gap">
                            <span class="section-kicker">Comfort</span>
                            <h2>Amenities</h2>
                        </div>
                        <div class="listing-amenity-grid">
                            <?php foreach ($room['amenities'] as $amenity): ?>
                                <div class="listing-amenity-item">
                                    <?php if ($amenity['icon']): ?><i class="fas fa-<?php echo $amenity['icon']; ?>"></i><?php endif; ?>
                                    <span><?php echo htmlspecialchars($amenity['name']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="section-heading compact listing-section-gap">
                        <span class="section-kicker">Terms</span>
                        <h2>Availability & Rules</h2>
                    </div>
                    <div class="profile-meta-grid">
                        <div class="profile-meta-item"><i class="fas fa-calendar"></i><span>Available from <?php echo date('M d, Y', strtotime($room['available_from'] ?? $room['created_at'])); ?></span></div>
                        <div class="profile-meta-item"><i class="fas fa-hourglass-half"></i><span><?php echo (int) ($room['minimum_stay'] ?? 1); ?> month minimum stay</span></div>
                        <div class="profile-meta-item"><i class="fas fa-wallet"></i><span><?php echo (int) ($room['deposit_months'] ?? 1); ?> month deposit</span></div>
                    </div>

                    <?php if (empty($room['utilities_included'])): ?>
                        <div class="listing-charge-card">
                            <strong>Additional Charges</strong>
                            <div class="listing-charge-grid">
                                <?php if (!empty($room['electricity_charge'])): ?><div><i class="fas fa-bolt"></i> Electricity: <?php echo htmlspecialchars($room['electricity_charge']); ?></div><?php endif; ?>
                                <?php if (!empty($room['water_charge'])): ?><div><i class="fas fa-water"></i> Water: <?php echo htmlspecialchars($room['water_charge']); ?></div><?php endif; ?>
                                <?php if (!empty($room['internet_charge'])): ?><div><i class="fas fa-wifi"></i> Internet: <?php echo htmlspecialchars($room['internet_charge']); ?></div><?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="listing-ok-text"><i class="fas fa-check-circle"></i> All utilities are included in the rent.</div>
                    <?php endif; ?>
                </div>
            </div>

            <aside class="listing-sidebar">
                <div class="profile-card sticky-sidebar-card" data-animate="fade-up">
                    <div class="section-heading compact">
                        <span class="section-kicker">Landlord</span>
                        <h2>Contact Details</h2>
                    </div>
                    <div class="listing-host-row">
                        <img src="<?php echo getUserAvatarUrl($room['avatar'] ?? null); ?>" class="listing-host-avatar" alt="<?php echo htmlspecialchars($room['full_name']); ?>">
                        <div>
                            <h3><?php echo htmlspecialchars($room['full_name']); ?></h3>
                            <div class="muted-text">Trust Score <?php echo (int) ($room['trust_score'] ?? 30); ?></div>
                        </div>
                    </div>

                    <div class="profile-overview-grid listing-sidebar-stats">
                        <div class="profile-mini-card" data-animate="fade-up"><span class="profile-mini-label">Response</span><strong><?php echo (int) ($room['response_rate'] ?? 0); ?>%</strong></div>
                        <div class="profile-mini-card" data-animate="fade-up"><span class="profile-mini-label">Time</span><strong><?php echo max(1, (int) ($room['response_time'] ?? 0)); ?>m</strong></div>
                        <div class="profile-mini-card" data-animate="fade-up"><span class="profile-mini-label">Since</span><strong><?php echo date('Y', strtotime($room['created_at'])); ?></strong></div>
                    </div>

                    <?php if (isLoggedIn()): ?>
                        <?php if ((int) getCurrentUserId() === (int) $room['landlord_id']): ?>
                            <a href="edit-listing.php?id=<?php echo (int) $room['id']; ?>" class="btn-outline btn-block">Edit Listing</a>
                            <a href="create-contract.php?room_id=<?php echo (int) $room['id']; ?>" class="btn-primary btn-block mt-2">Create Contract</a>
                        <?php else: ?>
                            <button type="button" onclick="sendContactRequest()" class="btn-primary btn-block">Contact Landlord</button>
                            <button type="button" onclick="toggleFavorite(<?php echo (int) $room['id']; ?>)" class="btn-outline btn-block mt-2"><?php echo isFavorited(getCurrentUserId(), $room['id']) ? 'Saved' : 'Save to Favorites'; ?></button>
                            <button type="button" onclick="shareRoom()" class="btn-outline btn-block mt-2">Share Listing</button>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="<?php echo SITE_URL; ?>/auth/login.php?redirect=pages/room-detail.php?id=<?php echo (int) $room['id']; ?>" class="btn-primary btn-block">Login to Contact</a>
                    <?php endif; ?>

                    <div class="listing-safety-note">
                        <i class="fas fa-shield-alt"></i>
                        <div>
                            <strong>Safety Tip</strong>
                            <p>Never send money before seeing the room in person.</p>
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        <?php if (!empty($similar_rooms)): ?>
            <div class="listing-related" data-animate="fade-up">
                <div class="section-heading compact">
                    <span class="section-kicker">Explore More</span>
                    <h2>Similar Rooms You Might Like</h2>
                </div>
                <div class="room-grid listing-related-grid">
                    <?php foreach ($similar_rooms as $similar): ?>
                        <article class="room-card" onclick="window.location.href='room-detail.php?id=<?php echo (int) $similar['id']; ?>'">
                            <div class="card-image listing-related-image"><img src="<?php echo getRoomImageUrl($similar['primary_image'] ?? null); ?>" alt="<?php echo htmlspecialchars($similar['title']); ?>"></div>
                            <div class="card-content">
                                <div class="card-price">Rs. <?php echo number_format((float) $similar['price']); ?><span>/month</span></div>
                                <h3 class="card-title"><?php echo htmlspecialchars($similar['title']); ?></h3>
                                <div class="card-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($similar['location']); ?></div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<div id="lightbox" class="listing-lightbox">
    <button type="button" onclick="closeLightbox()" class="listing-lightbox-close">&times;</button>
    <div class="listing-lightbox-stage">
        <img id="lightboxImage" src="" class="listing-lightbox-image" alt="Room preview">
        <button type="button" onclick="changeLightboxImage(-1)" class="listing-lightbox-nav prev"><i class="fas fa-chevron-left"></i></button>
        <button type="button" onclick="changeLightboxImage(1)" class="listing-lightbox-nav next"><i class="fas fa-chevron-right"></i></button>
    </div>
    <div class="listing-lightbox-counter"><span id="lightboxCounter"></span></div>
</div>

<script>
const images = <?php echo json_encode(array_map(static function ($image) { return getRoomImageUrl($image['image_url']); }, $images)); ?>;
let currentImageIndex = 0;

function changeMainImage(src) {
    document.getElementById('mainImage').src = src;
    const nextIndex = images.indexOf(src);
    if (nextIndex >= 0) currentImageIndex = nextIndex;
}

function openLightbox() {
    document.getElementById('lightbox').style.display = 'flex';
    updateLightboxImage();
}

function closeLightbox() {
    document.getElementById('lightbox').style.display = 'none';
}

function updateLightboxImage() {
    const fallback = document.getElementById('mainImage').src;
    document.getElementById('lightboxImage').src = images[currentImageIndex] || fallback;
    document.getElementById('lightboxCounter').textContent = `${currentImageIndex + 1} of ${images.length || 1}`;
}

function changeLightboxImage(direction) {
    if (!images.length) return;
    currentImageIndex += direction;
    if (currentImageIndex < 0) currentImageIndex = images.length - 1;
    if (currentImageIndex >= images.length) currentImageIndex = 0;
    updateLightboxImage();
}

document.addEventListener('keydown', function (event) {
    if (document.getElementById('lightbox').style.display !== 'flex') return;
    if (event.key === 'ArrowLeft') changeLightboxImage(-1);
    if (event.key === 'ArrowRight') changeLightboxImage(1);
    if (event.key === 'Escape') closeLightbox();
});

function toggleFavorite(roomId) {
    fetch('<?php echo SITE_URL; ?>/api/toggle-favorite.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ room_id: roomId, csrf_token: '<?php echo generateCSRFToken(); ?>' })
    }).then((response) => response.json()).then((data) => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Could not update favorites.');
        }
    });
}

function sendContactRequest() {
    const message = window.prompt('Send a message to the landlord (optional):', 'Hello, I am interested in this room. Is it still available?');
    if (message === null) return;

    fetch('<?php echo SITE_URL; ?>/api/send-contact-request.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            room_id: <?php echo (int) $room['id']; ?>,
            landlord_id: <?php echo (int) $room['landlord_id']; ?>,
            message: message,
            csrf_token: '<?php echo generateCSRFToken(); ?>'
        })
    }).then((response) => response.json()).then((data) => {
        if (data.success) {
            alert('Contact request sent successfully.');
            if (data.conversation_id) {
                window.location.href = '<?php echo SITE_URL; ?>/pages/messages.php?conversation=' + data.conversation_id;
            }
        } else {
            alert(data.message || 'Unable to send request.');
        }
    });
}

function shareRoom() {
    if (navigator.share) {
        navigator.share({
            title: <?php echo json_encode($room['title']); ?>,
            text: 'Check out this room on Gharbeti',
            url: window.location.href
        }).catch(() => {});
    } else {
        navigator.clipboard.writeText(window.location.href).then(() => {
            alert('Listing link copied to clipboard.');
        }).catch(() => {
            alert('Copy this link: ' + window.location.href);
        });
    }
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
