<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$per_page = 12;
$filters = [
    'location' => $_GET['location'] ?? '',
    'min_price' => isset($_GET['min_price']) ? (int) $_GET['min_price'] : null,
    'max_price' => isset($_GET['max_price']) ? (int) $_GET['max_price'] : null,
    'bedrooms' => isset($_GET['bedrooms']) ? (int) $_GET['bedrooms'] : null,
    'verified_only' => isset($_GET['verified_only']),
    'sort' => $_GET['sort'] ?? 'newest',
    'amenities' => isset($_GET['amenities']) ? array_map('intval', (array) $_GET['amenities']) : [],
    'search' => $_GET['search'] ?? ''
];

$result = searchRooms($filters, $page, $per_page);
$rooms = $result['rooms'];
$total_pages = max(1, (int) $result['total_pages']);
$amenities = getAllAmenities();
$page_title = 'Browse Rooms';
require_once __DIR__ . '/../includes/header.php';

$filter_chips = [];
if ($filters['search'] !== '') {
    $filter_chips[] = 'Search: ' . $filters['search'];
}
if ($filters['location'] !== '') {
    $filter_chips[] = 'Location: ' . $filters['location'];
}
if (!empty($filters['min_price']) || !empty($filters['max_price'])) {
    $filter_chips[] = 'Budget: ' . ($filters['min_price'] ? 'NPR ' . number_format((float) $filters['min_price']) : 'Any') . ' - ' . ($filters['max_price'] ? 'NPR ' . number_format((float) $filters['max_price']) : 'Any');
}
if (!empty($filters['bedrooms'])) {
    $filter_chips[] = $filters['bedrooms'] . '+ bedrooms';
}
if ($filters['verified_only']) {
    $filter_chips[] = 'Verified only';
}
if (!empty($filters['amenities'])) {
    $selected_amenities = [];
    foreach ($amenities as $amenity) {
        if (in_array((int) $amenity['id'], $filters['amenities'], true)) {
            $selected_amenities[] = $amenity['name'];
        }
    }
    if ($selected_amenities) {
        $filter_chips[] = 'Amenities: ' . implode(', ', array_slice($selected_amenities, 0, 3)) . (count($selected_amenities) > 3 ? ' +' . (count($selected_amenities) - 3) : '');
    }
}
?>
<section class="dashboard-shell">
    <div class="dashboard-card dashboard-surface" data-animate="fade-up">
        <div class="section-heading compact">
            <span class="section-kicker">Discover</span>
            <h1>Browse Rooms</h1>
            <p class="muted-text">Search verified listings by location, budget, room size, and the amenities that matter most.</p>
        </div>

        <div class="profile-card filter-card" data-animate="fade-up">
            <form method="get" id="filterForm">
                <div class="filter-grid">
                    <div class="form-group">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" placeholder="Title, neighborhood, or landmark" value="<?php echo htmlspecialchars($filters['search']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="locationFilter">Location</label>
                        <select id="locationFilter" name="location">
                            <option value="">All Locations</option>
                            <?php foreach (['Kathmandu', 'Lalitpur', 'Bhaktapur', 'Pokhara'] as $loc): ?>
                                <option value="<?php echo $loc; ?>" <?php echo $filters['location'] === $loc ? 'selected' : ''; ?>><?php echo $loc; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="minPriceInput">Minimum Budget</label>
                        <input type="number" id="minPriceInput" name="min_price" placeholder="Min NPR" value="<?php echo htmlspecialchars((string) ($filters['min_price'] ?? '')); ?>">
                    </div>
                    <div class="form-group">
                        <label for="maxPriceInput">Maximum Budget</label>
                        <input type="number" id="maxPriceInput" name="max_price" placeholder="Max NPR" value="<?php echo htmlspecialchars((string) ($filters['max_price'] ?? '')); ?>">
                    </div>
                    <div class="form-group">
                        <label for="bedrooms">Bedrooms</label>
                        <select id="bedrooms" name="bedrooms">
                            <option value="">Any Bedrooms</option>
                            <option value="1" <?php echo (int) $filters['bedrooms'] === 1 ? 'selected' : ''; ?>>1+ Beds</option>
                            <option value="2" <?php echo (int) $filters['bedrooms'] === 2 ? 'selected' : ''; ?>>2+ Beds</option>
                            <option value="3" <?php echo (int) $filters['bedrooms'] === 3 ? 'selected' : ''; ?>>3+ Beds</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="sort">Sort By</label>
                        <select id="sort" name="sort">
                            <option value="newest" <?php echo $filters['sort'] === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="price_asc" <?php echo $filters['sort'] === 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_desc" <?php echo $filters['sort'] === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                            <option value="popular" <?php echo $filters['sort'] === 'popular' ? 'selected' : ''; ?>>Most Viewed</option>
                            <option value="trust" <?php echo $filters['sort'] === 'trust' ? 'selected' : ''; ?>>Trust Score</option>
                        </select>
                    </div>
                </div>

                <div class="mt-3">
                    <button type="button" class="filter-section-toggle" id="amenitiesToggle" aria-expanded="<?php echo !empty($filters['amenities']) ? 'true' : 'false'; ?>" aria-controls="amenitiesPanel">
                        <i class="fas <?php echo !empty($filters['amenities']) ? 'fa-chevron-up' : 'fa-chevron-down'; ?>" id="amenitiesIcon"></i>
                        Filter by Amenities
                    </button>
                    <div id="amenitiesPanel" class="amenities-panel <?php echo !empty($filters['amenities']) ? 'is-open' : ''; ?>">
                        <div class="amenities-grid">
                            <?php foreach ($amenities as $amenity): ?>
                                <label class="amenity-option">
                                    <input type="checkbox" name="amenities[]" value="<?php echo (int) $amenity['id']; ?>" <?php echo in_array((int) $amenity['id'], $filters['amenities'], true) ? 'checked' : ''; ?>>
                                    <?php if ($amenity['icon']): ?><i class="fas fa-<?php echo htmlspecialchars($amenity['icon']); ?>" style="color:var(--primary);"></i><?php endif; ?>
                                    <span><?php echo htmlspecialchars($amenity['name']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="filter-actions mt-3">
                    <label class="checkbox-label compact-checkbox">
                        <input type="checkbox" name="verified_only" value="1" <?php echo $filters['verified_only'] ? 'checked' : ''; ?>>
                        <span>Show verified listings only</span>
                    </label>
                    <div class="inline-actions">
                        <button type="submit" class="btn-primary">Apply Filters</button>
                        <a href="rooms.php" class="btn-outline">Clear All</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="results-toolbar mb-3">
            <p class="muted-text">Found <?php echo (int) $result['total']; ?> rooms</p>
            <?php if ($filter_chips): ?>
                <div class="filter-chip-list" aria-label="Active filters">
                    <?php foreach ($filter_chips as $chip): ?>
                        <span class="filter-chip"><?php echo htmlspecialchars($chip); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (empty($rooms)): ?>
            <div class="admin-empty-state" data-animate="fade-up">
                <i class="fas fa-home" style="font-size:3rem;color:var(--text-lighter);margin-bottom:1rem;"></i>
                <h3>No Rooms Found</h3>
                <p class="muted-text">Try widening your budget, removing a few filters, or searching a nearby location.</p>
            </div>
        <?php else: ?>
            <div class="room-grid" style="grid-template-columns:repeat(auto-fill,minmax(280px,1fr));padding:0;">
                <?php foreach ($rooms as $room):
                    $trust = (int) ($room['trust_score'] ?? 30);
                    $trust_class = $trust >= 80 ? 'trust-high' : ($trust >= 50 ? 'trust-medium' : 'trust-low');
                ?>
                    <article class="room-card">
                        <a class="room-card-link" href="room-detail.php?id=<?php echo (int) $room['id']; ?>">
                            <div class="card-image">
                                <img src="<?php echo getRoomImageUrl($room['primary_image'] ?? null); ?>" alt="<?php echo htmlspecialchars($room['title']); ?>">
                                <?php if (!empty($room['is_verified'])): ?>
                                    <span class="verified-badge"><i class="fas fa-check-circle"></i> Verified</span>
                                <?php endif; ?>
                            </div>
                            <div class="card-content">
                                <div class="card-price">Rs. <?php echo number_format((float) $room['price']); ?><span>/month</span></div>
                                <h3 class="card-title"><?php echo htmlspecialchars($room['title']); ?></h3>
                                <div class="card-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($room['location']); ?></div>
                                <div class="card-meta-row">
                                    <span><i class="fas fa-bed"></i> <?php echo (int) $room['bedroom_count']; ?> bed</span>
                                    <span><i class="fas fa-bath"></i> <?php echo (int) $room['bathroom_count']; ?> bath</span>
                                    <span><i class="fas fa-eye"></i> <?php echo (int) $room['view_count']; ?></span>
                                </div>
                                <div class="landlord-info">
                                    <img src="<?php echo getUserAvatarUrl($room['avatar'] ?? null); ?>" alt="<?php echo htmlspecialchars($room['full_name'] ?? 'Landlord'); ?>" class="landlord-avatar">
                                    <span class="landlord-name"><?php echo htmlspecialchars($room['full_name'] ?? 'Landlord'); ?></span>
                                    <span class="trust-score <?php echo $trust_class; ?>"><?php echo $trust; ?></span>
                                </div>
                            </div>
                        </a>
                        <div class="card-actions-row card-content">
                            <?php if (isLoggedIn()): ?>
                                <button type="button" onclick="toggleFavorite(event, <?php echo (int) $room['id']; ?>)" class="wishlist-btn <?php echo isFavorited(getCurrentUserId(), (int) $room['id']) ? 'active' : ''; ?>" aria-label="Save <?php echo htmlspecialchars($room['title']); ?>">
                                    <i class="<?php echo isFavorited(getCurrentUserId(), (int) $room['id']) ? 'fas' : 'far'; ?> fa-heart"></i>
                                </button>
                            <?php endif; ?>
                            <a href="room-detail.php?id=<?php echo (int) $room['id']; ?>" class="card-inline-link">View details</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="inline-actions" style="justify-content:center;margin-top:2rem;">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="btn-outline btn-small"><i class="fas fa-chevron-left"></i></a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="<?php echo $i === $page ? 'btn-primary' : 'btn-outline'; ?> btn-small"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="btn-outline btn-small"><i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<script>
const amenitiesToggle = document.getElementById('amenitiesToggle');
const amenitiesPanel = document.getElementById('amenitiesPanel');
const amenitiesIcon = document.getElementById('amenitiesIcon');

amenitiesToggle?.addEventListener('click', () => {
    const isOpen = amenitiesPanel.classList.toggle('is-open');
    amenitiesToggle.setAttribute('aria-expanded', String(isOpen));
    amenitiesIcon.className = 'fas ' + (isOpen ? 'fa-chevron-up' : 'fa-chevron-down');
});

function toggleFavorite(event, roomId) {
    event.preventDefault();
    event.stopPropagation();

    fetch('<?php echo SITE_URL; ?>/api/toggle-favorite.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            room_id: roomId,
            csrf_token: '<?php echo generateCSRFToken(); ?>'
        })
    })
    .then((response) => response.json())
    .then((data) => {
        if (!data.success) {
            showNotification(data.message || 'Could not update saved rooms.', 'error');
            return;
        }

        const button = event.currentTarget;
        const icon = button.querySelector('i');
        const isAdded = data.action === 'added';
        button.classList.toggle('active', isAdded);
        icon.className = (isAdded ? 'fas' : 'far') + ' fa-heart';
        showNotification(isAdded ? 'Room saved to your favorites.' : 'Room removed from your favorites.', isAdded ? 'success' : 'info');
    })
    .catch(() => {
        showNotification('Could not update saved rooms right now.', 'error');
    });
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
