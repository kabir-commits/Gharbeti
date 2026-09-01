<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/auth/login.php');
}

$message = $_SESSION['admin_room_message'] ?? '';
$message_type = $_SESSION['admin_room_message_type'] ?? 'success';
unset($_SESSION['admin_room_message'], $_SESSION['admin_room_message_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $_SESSION['admin_room_message'] = 'Invalid security token.';
        $_SESSION['admin_room_message_type'] = 'error';
        redirect(SITE_URL . '/pages/admin/rooms.php');
    }

    $room_id = (int) ($_POST['room_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $reason = trim((string) ($_POST['reason'] ?? ''));

    if ($room_id <= 0) {
        $_SESSION['admin_room_message'] = 'Invalid room selected.';
        $_SESSION['admin_room_message_type'] = 'error';
        redirect(SITE_URL . '/pages/admin/rooms.php');
    }

    if ($action === 'approve') {
        $result = updateRoomStatus($room_id, 'active', getCurrentUserId());
        $_SESSION['admin_room_message'] = $result['message'];
        $_SESSION['admin_room_message_type'] = $result['success'] ? 'success' : 'error';
    } elseif ($action === 'reject') {
        $result = updateRoomStatus($room_id, 'rejected', getCurrentUserId());
        if ($result['success'] && $reason !== '') {
            logAdminAction(getCurrentUserId(), 'reject_room_listing', 'room', $room_id, 'Rejected room listing: ' . $reason);
            addSystemLog('warning', 'room_rejected', 'Room listing rejected', ['room_id' => $room_id, 'reason' => $reason]);
        }
        $_SESSION['admin_room_message'] = $result['success']
            ? ($reason !== '' ? 'Room rejected successfully.' : $result['message'])
            : $result['message'];
        $_SESSION['admin_room_message_type'] = $result['success'] ? 'success' : 'error';
    }

    redirect(SITE_URL . '/pages/admin/rooms.php');
}

$pending_rooms = [];
$history_rooms = [];

if (tableExists('rooms')) {
    $pending_stmt = $conn->prepare("SELECT r.*, p.full_name, p.avatar, p.trust_score, u.email,
        (SELECT image_url FROM room_images WHERE room_id = r.id AND is_primary = 1 LIMIT 1) as primary_image
        FROM rooms r
        LEFT JOIN profiles p ON r.landlord_id = p.user_id
        LEFT JOIN users u ON r.landlord_id = u.id
        WHERE r.status = 'pending'
        ORDER BY r.created_at ASC");
    $pending_stmt->execute();
    $pending_rooms = $pending_stmt->fetchAll();

    $history_stmt = $conn->prepare("SELECT r.*, p.full_name, u.email,
        (SELECT image_url FROM room_images WHERE room_id = r.id AND is_primary = 1 LIMIT 1) as primary_image
        FROM rooms r
        LEFT JOIN profiles p ON r.landlord_id = p.user_id
        LEFT JOIN users u ON r.landlord_id = u.id
        WHERE r.status IN ('active', 'rejected', 'inactive', 'booked')
        ORDER BY r.updated_at DESC, r.created_at DESC
        LIMIT 20");
    $history_stmt->execute();
    $history_rooms = $history_stmt->fetchAll();
}

$page_title = 'Room Moderation';
require_once __DIR__ . '/../../includes/header.php';
?>
<section class="dashboard-shell admin-verify-shell">
    <div class="dashboard-card admin-verify-card">
        <div class="admin-verify-hero">
            <div>
                <p class="dashboard-kicker">Admin Console</p>
                <h1>Room Moderation</h1>
                <p class="muted-text">Review new landlord listings before they go live. Approved rooms become active and visible across the public site.</p>
            </div>
            <div class="admin-verify-summary">
                <div>
                    <span>Pending</span>
                    <strong><?php echo count($pending_rooms); ?></strong>
                </div>
                <div>
                    <span>Recent Decisions</span>
                    <strong><?php echo count($history_rooms); ?></strong>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type === 'error' ? 'error' : 'success'; ?>" data-animate="fade-up">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="admin-verify-section">
            <div class="section-heading compact">
                <span class="section-kicker">Queue</span>
                <h2>Pending Listings</h2>
            </div>

            <?php if (empty($pending_rooms)): ?>
                <div class="admin-empty-state">
                    <h3>No rooms waiting right now</h3>
                    <p class="muted-text">New landlord submissions will appear here for review.</p>
                </div>
            <?php else: ?>
                <div class="admin-verify-list">
                    <?php foreach ($pending_rooms as $room): ?>
                        <article class="admin-verify-item">
                            <div class="admin-verify-main">
                                <div class="admin-verify-meta">
                                    <span class="status-chip pending">Pending Review</span>
                                    <span class="muted-text">Submitted <?php echo date('M d, Y H:i', strtotime($room['created_at'])); ?></span>
                                </div>
                                <h3><?php echo htmlspecialchars($room['title']); ?></h3>
                                <div class="admin-room-review-grid">
                                    <div class="admin-room-review-media">
                                        <img src="<?php echo getRoomImageUrl($room['primary_image'] ?? null); ?>" alt="<?php echo htmlspecialchars($room['title']); ?>">
                                    </div>
                                    <div class="admin-room-review-details">
                                        <div class="admin-verify-details">
                                            <span><strong>Landlord:</strong> <?php echo htmlspecialchars($room['full_name'] ?? 'Unknown'); ?></span>
                                            <span><strong>Email:</strong> <?php echo htmlspecialchars($room['email'] ?? ''); ?></span>
                                            <span><strong>Location:</strong> <?php echo htmlspecialchars($room['location']); ?></span>
                                            <span><strong>Price:</strong> Rs. <?php echo number_format((float) $room['price']); ?>/month</span>
                                            <span><strong>Bedrooms:</strong> <?php echo (int) ($room['bedroom_count'] ?? 0); ?></span>
                                            <span><strong>Bathrooms:</strong> <?php echo (int) ($room['bathroom_count'] ?? 0); ?></span>
                                        </div>
                                        <p class="muted-text admin-room-review-copy"><?php echo htmlspecialchars(mb_strimwidth((string) ($room['description'] ?? ''), 0, 220, '...')); ?></p>
                                        <div class="admin-room-review-links">
                                            <a class="btn-outline btn-small" href="<?php echo SITE_URL; ?>/pages/edit-listing.php?id=<?php echo (int) $room['id']; ?>">Review Listing</a>
                                            <a class="btn-outline btn-small" href="<?php echo SITE_URL; ?>/pages/room-detail.php?id=<?php echo (int) $room['id']; ?>">Preview Detail Page</a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="admin-verify-actions">
                                <form method="POST" class="admin-verify-approve-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="room_id" value="<?php echo (int) $room['id']; ?>">
                                    <button type="submit" name="action" value="approve" class="btn-primary">Approve & Publish</button>
                                    <button type="button" class="btn-outline" onclick="showRejectForm(<?php echo (int) $room['id']; ?>)">Reject</button>
                                </form>

                                <div id="reject-<?php echo (int) $room['id']; ?>" class="admin-reject-panel">
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="room_id" value="<?php echo (int) $room['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <textarea name="reason" placeholder="Explain why this listing is being rejected" required></textarea>
                                        <div class="admin-reject-actions">
                                            <button type="submit" class="btn-primary">Confirm Reject</button>
                                            <button type="button" class="btn-outline" onclick="hideRejectForm(<?php echo (int) $room['id']; ?>)">Cancel</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="admin-verify-section">
            <div class="section-heading compact">
                <span class="section-kicker">History</span>
                <h2>Recent Room Decisions</h2>
            </div>
            <div class="admin-history-table-wrap">
                <table class="admin-history-table">
                    <thead>
                        <tr>
                            <th>Room</th>
                            <th>Landlord</th>
                            <th>Status</th>
                            <th>Price</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($history_rooms)): ?>
                            <tr>
                                <td colspan="5" class="muted-text text-center">No moderated listings yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($history_rooms as $room): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($room['title']); ?></td>
                                    <td><?php echo htmlspecialchars($room['full_name'] ?? $room['email'] ?? 'Unknown'); ?></td>
                                    <td>
                                        <span class="status-chip <?php echo $room['status'] === 'active' ? 'verified' : ($room['status'] === 'rejected' ? 'rejected' : 'pending'); ?>">
                                            <?php echo htmlspecialchars(ucfirst($room['status'])); ?>
                                        </span>
                                    </td>
                                    <td>Rs. <?php echo number_format((float) $room['price']); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($room['updated_at'] ?? $room['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<script>
function showRejectForm(id) {
    document.getElementById('reject-' + id).style.display = 'block';
}

function hideRejectForm(id) {
    document.getElementById('reject-' + id).style.display = 'none';
}
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
