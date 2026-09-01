<?php
require_once 'config/app.php';
require_once 'includes/functions.php';

$stats = getPlatformStats();
$featured_rooms = getRealFeaturedRooms(4);
$testimonials = getRealTestimonials(3);
$location_stats = getLocationStats();
$price_stats = getPriceRangeStats();

$page_title = 'Home';
require_once 'includes/header.php';
?>

<section class="hero">
    <div class="hero-background">
        <?php
        $hero_image = SITE_URL . '/assets/images/hero-kathmandu.jpg';
        if (!file_exists(__DIR__ . '/assets/images/hero-kathmandu.jpg')) {
            $hero_image = 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80';
        }
        ?>
        <img src="<?php echo $hero_image; ?>" alt="Kathmandu Valley">
    </div>
    <div class="hero-overlay"></div>

    <div class="hero-content">
        <h1 class="hero-title" data-animate="fade-up">
            Find Your Perfect Room
            <span>No Brokers  Verified Listings  Direct Contact</span>
        </h1>
        <p class="hero-subtitle" data-animate="fade-up" data-delay="200">
            A trusted platform connecting tenants and landlords directly in Nepal
        </p>

        <div class="search-wrapper" data-animate="fade-up" data-delay="400">
            <select class="search-select" id="location">
                <option value="">All Locations</option>
                <?php foreach ($location_stats as $loc): ?>
                    <option value="<?php echo htmlspecialchars($loc['location']); ?>">
                        <?php echo htmlspecialchars($loc['location']); ?> (<?php echo (int) $loc['count']; ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <div class="price-range">
                <span>NPR <span id="minPriceVal"><?php echo number_format((float) ($price_stats['min_price'] ?: 5000)); ?></span> - NPR <span id="maxPriceVal"><?php echo number_format((float) ($price_stats['max_price'] ?: 30000)); ?></span></span>
                <input type="range" id="minPrice" min="<?php echo (int) ($price_stats['min_price'] ?: 1000); ?>" max="<?php echo (int) ($price_stats['max_price'] ?: 50000); ?>" value="<?php echo (int) ($price_stats['min_price'] ?: 5000); ?>" step="1000">
                <input type="range" id="maxPrice" min="<?php echo (int) ($price_stats['min_price'] ?: 1000); ?>" max="<?php echo (int) ($price_stats['max_price'] ?: 50000); ?>" value="<?php echo (int) ($price_stats['max_price'] ?: 30000); ?>" step="1000">
            </div>

            <button class="search-btn" onclick="searchRooms()">
                <i class="fas fa-search"></i> Search Rooms
            </button>
        </div>

        <div class="hero-stats" data-animate="fade-up" data-delay="600">
            <div class="stat-item">
                <span class="stat-number"><?php echo formatNumber($stats['verified_rooms']); ?>+</span>
                <span class="stat-label">Verified Rooms</span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php echo formatNumber($stats['landlords']); ?>+</span>
                <span class="stat-label">Trusted Landlords</span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php echo formatNumber($stats['tenants']); ?>+</span>
                <span class="stat-label">Tenants</span>
            </div>
        </div>
    </div>
</section>

<section class="how-it-works">
    <div class="container">
        <h2 class="section-title" data-animate="fade-up">How Gharbeti Works</h2>
        <p class="section-subtitle" data-animate="fade-up" data-delay="100">
            Four simple steps to find your perfect room or tenant
        </p>

        <div class="steps-grid">
            <div class="step-card" data-animate="fade-up">
                <div class="step-number">1</div>
                <div class="step-icon"><i class="fas fa-shield-alt"></i></div>
                <h3>Get Verified</h3>
                <p>Verify your email, phone, and ID to build trust in the community</p>
            </div>
            <div class="step-card" data-animate="fade-up" data-delay="100">
                <div class="step-number">2</div>
                <div class="step-icon"><i class="fas fa-search"></i></div>
                <h3>Search & Discover</h3>
                <p>Find verified rooms with detailed amenities and real photos</p>
            </div>
            <div class="step-card" data-animate="fade-up" data-delay="200">
                <div class="step-number">3</div>
                <div class="step-icon"><i class="fas fa-comments"></i></div>
                <h3>Connect Directly</h3>
                <p>Message landlords directly, with no broker in between</p>
            </div>
            <div class="step-card" data-animate="fade-up" data-delay="300">
                <div class="step-number">4</div>
                <div class="step-icon"><i class="fas fa-file-signature"></i></div>
                <h3>Sign Contract</h3>
                <p>Generate and sign digital rental agreements instantly</p>
            </div>
        </div>
    </div>
</section>

<section class="featured-rooms">
    <div class="container">
        <h2 class="section-title" data-animate="fade-up">Featured Rooms</h2>
        <p class="section-subtitle" data-animate="fade-up" data-delay="100">
            Latest verified rooms from trusted landlords
        </p>

        <?php if (empty($featured_rooms)): ?>
            <div class="empty-state">
                <i class="fas fa-home"></i>
                <h3>No Rooms Yet</h3>
                <p>Be the first to list your room or check back soon.</p>
                <?php if (isLoggedIn() && getCurrentUserRole() === 'landlord'): ?>
                    <a href="pages/create-listing.php" class="btn-primary">List Your Room</a>
                <?php else: ?>
                    <a href="auth/register.php?role=landlord" class="btn-primary">Become a Landlord</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="room-grid">
                <?php foreach ($featured_rooms as $room):
                    $trust = (int) ($room['trust_score'] ?? 0);
                    $trust_class = $trust >= 80 ? 'trust-high' : ($trust >= 50 ? 'trust-medium' : 'trust-low');
                ?>
                    <article class="room-card" onclick="window.location.href='pages/room-detail.php?id=<?php echo (int) $room['id']; ?>'">
                        <div class="card-image">
                            <img src="<?php echo getRoomImageUrl($room['primary_image'] ?? null); ?>" alt="<?php echo htmlspecialchars($room['title']); ?>" loading="lazy">
                            <?php if (!empty($room['is_verified'])): ?>
                                <span class="verified-badge"><i class="fas fa-check-circle"></i> Verified</span>
                            <?php endif; ?>
                            <?php if (isLoggedIn()): ?>
                                <button class="wishlist-btn <?php echo isFavorited(getCurrentUserId(), (int) $room['id']) ? 'active' : ''; ?>" onclick="event.stopPropagation(); toggleFavorite(<?php echo (int) $room['id']; ?>, this)">
                                    <i class="<?php echo isFavorited(getCurrentUserId(), (int) $room['id']) ? 'fas' : 'far'; ?> fa-heart"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-content">
                            <div class="card-price">NPR <?php echo number_format((float) $room['price']); ?><span>/month</span></div>
                            <h3 class="card-title"><?php echo htmlspecialchars($room['title']); ?></h3>
                            <div class="card-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($room['location']); ?></div>
                            <div class="landlord-info">
                                <img src="<?php echo getAvatarUrl($room['landlord_avatar'] ?? null); ?>" alt="<?php echo htmlspecialchars($room['landlord_name'] ?? 'Landlord'); ?>" class="landlord-avatar">
                                <span class="landlord-name"><?php echo htmlspecialchars($room['landlord_name'] ?? 'Landlord'); ?></span>
                                <span class="trust-score <?php echo $trust_class; ?>"><?php echo $trust; ?></span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-4" data-animate="fade-up">
                <a href="pages/rooms.php" class="btn-outline">View All Rooms <i class="fas fa-arrow-right"></i></a>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="why-choose">
    <div class="container">
        <h2 class="section-title" data-animate="fade-up">Why Choose Gharbeti?</h2>
        <p class="section-subtitle" data-animate="fade-up" data-delay="100">
            Real numbers from our growing community
        </p>

        <div class="features-grid">
            <div class="feature-card" data-animate="fade-up">
                <div class="feature-icon"><i class="fas fa-check-double"></i></div>
                <h3>Verified Listings</h3>
                <p>Published listings backed by real user accounts and active moderation.</p>
                <div class="feature-stats"><span class="counter" data-target="<?php echo $stats['verified_rooms']; ?>">0</span>+<span>Verified Listings</span></div>
            </div>
            <div class="feature-card" data-animate="fade-up" data-delay="100">
                <div class="feature-icon"><i class="fas fa-handshake"></i></div>
                <h3>Direct Connections</h3>
                <p>No brokers, just direct conversations between tenants and landlords.</p>
                <div class="feature-stats"><span class="counter" data-target="<?php echo $stats['connections']; ?>">0</span>+<span>Direct Connections</span></div>
            </div>
            <div class="feature-card" data-animate="fade-up" data-delay="200">
                <div class="feature-icon"><i class="fas fa-file-contract"></i></div>
                <h3>Digital Contracts</h3>
                <p>Real rental agreements tracked inside the platform.</p>
                <div class="feature-stats"><span class="counter" data-target="<?php echo $stats['contracts']; ?>">0</span>+<span>Contracts Generated</span></div>
            </div>
            <div class="feature-card" data-animate="fade-up" data-delay="300">
                <div class="feature-icon"><i class="fas fa-star"></i></div>
                <h3>Community Trust</h3>
                <p>Transparent reviews and trust indicators from actual platform usage.</p>
                <div class="feature-stats"><span class="counter" data-target="<?php echo $stats['avg_rating']; ?>">0</span>/5<span>Average Rating</span></div>
            </div>
        </div>
    </div>
</section>

<section class="testimonials">
    <div class="container">
        <h2 class="section-title" data-animate="fade-up">What Our Users Say</h2>
        <p class="section-subtitle" data-animate="fade-up" data-delay="100">
            Real stories from real tenants and landlords
        </p>

        <?php if (empty($testimonials)): ?>
            <div class="empty-state">
                <i class="fas fa-comment-dots"></i>
                <h3>No Reviews Yet</h3>
                <p>Be the first to leave a review after your rental experience.</p>
            </div>
        <?php else: ?>
            <div class="testimonials-grid">
                <?php foreach ($testimonials as $testimonial): ?>
                    <article class="testimonial-card" data-animate="fade-up">
                        <div class="testimonial-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="<?php echo $i <= round((float) $testimonial['rating_overall']) ? 'fas' : 'far'; ?> fa-star"></i>
                            <?php endfor; ?>
                        </div>
                        <p>"<?php echo htmlspecialchars(substr($testimonial['review_text'], 0, 150)) . (strlen($testimonial['review_text']) > 150 ? '...' : ''); ?>"</p>
                        <div class="testimonial-user">
                            <img src="<?php echo getAvatarUrl($testimonial['reviewer_avatar'] ?? null); ?>" alt="<?php echo htmlspecialchars($testimonial['reviewer_name']); ?>">
                            <div>
                                <h4><?php echo htmlspecialchars($testimonial['reviewer_name']); ?></h4>
                                <span><?php echo htmlspecialchars($testimonial['room_title']); ?></span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="activity-feed" style="padding: 3rem 0;">
    <div class="container">
        <h2 class="section-title" data-animate="fade-up">Live Activity</h2>
        <p class="section-subtitle" data-animate="fade-up" data-delay="100">
            Recent updates from the platform
        </p>

        <div style="background: white; border-radius: 20px; padding: 2rem; box-shadow: var(--shadow);">
            <?php
            $activity = getRecentActivity(5);
            $all_activity = [];

            foreach ($activity['recent_rooms'] as $item) {
                $all_activity[] = [
                    'time' => strtotime($item['created_at']),
                    'icon' => '<i class="fas fa-home" style="color: var(--primary);"></i>',
                    'text' => '<strong>' . htmlspecialchars($item['user_name']) . '</strong> listed a new room: <a href="pages/room-detail.php?id=' . (int) $item['id'] . '">' . htmlspecialchars($item['title']) . '</a>'
                ];
            }
            foreach ($activity['recent_reviews'] as $item) {
                $all_activity[] = [
                    'time' => strtotime($item['created_at']),
                    'icon' => '<i class="fas fa-star" style="color: var(--accent);"></i>',
                    'text' => '<strong>' . htmlspecialchars($item['user_name']) . '</strong> left a new review'
                ];
            }
            foreach ($activity['recent_contracts'] as $item) {
                $all_activity[] = [
                    'time' => strtotime($item['created_at']),
                    'icon' => '<i class="fas fa-file-signature" style="color: var(--success);"></i>',
                    'text' => 'Contract signed between <strong>' . htmlspecialchars($item['tenant_name']) . '</strong> and <strong>' . htmlspecialchars($item['landlord_name']) . '</strong>'
                ];
            }

            usort($all_activity, function ($a, $b) {
                return $b['time'] <=> $a['time'];
            });
            ?>

            <?php if (!empty($all_activity)): ?>
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <?php foreach (array_slice($all_activity, 0, 5) as $activity_item): ?>
                        <div class="activity-item">
                            <div class="activity-icon"><?php echo $activity_item['icon']; ?></div>
                            <div><?php echo $activity_item['text']; ?></div>
                            <span class="activity-time"><?php echo timeAgo(date('Y-m-d H:i:s', $activity_item['time'])); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: var(--text-light);">No recent activity yet.</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="cta-section">
    <div class="container">
        <h2 data-animate="fade-up">Ready to find your perfect room?</h2>
        <p data-animate="fade-up" data-delay="100">Join <?php echo formatNumber($stats['tenants']); ?>+ tenants and <?php echo formatNumber($stats['landlords']); ?>+ landlords on Gharbeti</p>
        <div class="cta-buttons" data-animate="fade-up" data-delay="200">
            <a href="auth/register.php?role=tenant" class="btn-primary">Find a Room</a>
            <a href="auth/register.php?role=landlord" class="btn-outline">List Your Room</a>
        </div>
    </div>
</section>

<script>
function searchRooms() {
    const location = document.getElementById('location').value;
    const minPrice = document.getElementById('minPrice').value;
    const maxPrice = document.getElementById('maxPrice').value;
    window.location.href = 'pages/rooms.php?location=' + encodeURIComponent(location) + '&min_price=' + minPrice + '&max_price=' + maxPrice;
}

document.getElementById('minPrice').addEventListener('input', function() {
    document.getElementById('minPriceVal').textContent = parseInt(this.value, 10).toLocaleString();
});

document.getElementById('maxPrice').addEventListener('input', function() {
    document.getElementById('maxPriceVal').textContent = parseInt(this.value, 10).toLocaleString();
});

function toggleFavorite(roomId, button) {
    <?php if (!isLoggedIn()): ?>
    window.location.href = 'auth/login.php?redirect=' + encodeURIComponent(window.location.href);
    return;
    <?php endif; ?>

    fetch('api/toggle-favorite.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            room_id: roomId,
            csrf_token: '<?php echo generateCSRFToken(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            return;
        }
        const icon = button.querySelector('i');
        if (data.action === 'added') {
            button.classList.add('active');
            icon.className = 'fas fa-heart';
        } else {
            button.classList.remove('active');
            icon.className = 'far fa-heart';
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const minSlider = document.getElementById('minPrice');
    const maxSlider = document.getElementById('maxPrice');
    if (!minSlider || !maxSlider) return;

    minSlider.addEventListener('input', function() {
        if (parseInt(this.value, 10) > parseInt(maxSlider.value, 10)) {
            maxSlider.value = this.value;
            document.getElementById('maxPriceVal').textContent = parseInt(this.value, 10).toLocaleString();
        }
    });

    maxSlider.addEventListener('input', function() {
        if (parseInt(this.value, 10) < parseInt(minSlider.value, 10)) {
            minSlider.value = this.value;
            document.getElementById('minPriceVal').textContent = parseInt(this.value, 10).toLocaleString();
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
