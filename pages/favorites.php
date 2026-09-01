<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    redirect(SITE_URL . '/auth/login.php');
}

$favorites = getFavoriteRooms(getCurrentUserId());
$page_title = 'My Favorites';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="dashboard-card dashboard-hero-card" data-animate="fade-up" style="max-width: 1200px;">
        <div class="section-heading compact">
            <div>
                <span class="section-kicker">Saved</span>
                <h1>Your shortlist should feel as polished as discovery itself.</h1>
                <p class="muted-text">Keep promising rooms in one clean place so you can compare them before reaching out.</p>
            </div>
        </div>
    </div>

    <div class="dashboard-card" data-animate="fade-up" style="max-width: 1200px;">
        <div class="section-heading compact" style="margin-bottom: 1.25rem;">
            <div>
                <span class="section-kicker">Favorites</span>
                <h2>Saved rooms</h2>
            </div>
        </div>
        <?php if (empty($favorites)): ?>
            <div class="admin-empty-state" data-animate="fade-up">
                <i class="fas fa-heart" style="font-size: 3.5rem; color: var(--text-lighter); margin-bottom: 1rem;"></i>
                <h3>No favorites yet</h3>
                <p class="muted-text">Start exploring rooms and save the ones that stand out.</p>
                <a href="rooms.php" class="btn-primary">Browse Rooms</a>
            </div>
        <?php else: ?>
            <div class="room-grid" style="grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); padding: 0;">
                <?php foreach ($favorites as $room): ?>
                    <article class="room-card" data-animate="fade-up" onclick="window.location.href='room-detail.php?id=<?php echo $room['id']; ?>'" style="position: relative;">
                        <button onclick="removeFavorite(event, <?php echo $room['id']; ?>)" class="wishlist-btn" style="left: auto; right: 12px;">
                            <i class="fas fa-trash"></i>
                        </button>
                        <div class="card-image">
                            <img src="<?php echo getRoomImageUrl($room['primary_image'] ?? null); ?>" alt="<?php echo htmlspecialchars($room['title']); ?>">
                        </div>
                        <div class="card-content">
                            <div class="card-price">Rs. <?php echo number_format((float) $room['price']); ?><span>/month</span></div>
                            <h3 class="card-title"><?php echo htmlspecialchars($room['title']); ?></h3>
                            <div class="card-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($room['location']); ?></div>
                            <div class="muted-text" style="font-size: 0.9rem; margin-top: 0.55rem;">Saved on <?php echo date('M d, Y', strtotime($room['favorited_at'])); ?></div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<script>
function removeFavorite(event, roomId) {
    event.stopPropagation();
    if (confirm('Remove from favorites?')) {
        fetch('<?php echo SITE_URL; ?>/api/toggle-favorite.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({room_id: roomId, csrf_token: '<?php echo generateCSRFToken(); ?>'})
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
    }
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
