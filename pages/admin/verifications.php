<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/auth/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve'])) {
        approveVerification((int) $_POST['doc_id'], getCurrentUserId());
    } elseif (isset($_POST['reject'])) {
        rejectVerification((int) $_POST['doc_id'], getCurrentUserId(), sanitize($_POST['reason'] ?? ''));
    }
    redirect(SITE_URL . '/pages/admin/verifications.php');
}

$pending = [];
$history = [];

if (tableExists('verification_documents')) {
    $stmt = $conn->prepare("SELECT v.*, u.email, p.full_name, p.phone FROM verification_documents v JOIN users u ON v.user_id = u.id LEFT JOIN profiles p ON u.id = p.user_id WHERE v.status = 'pending' ORDER BY v.submitted_at ASC");
    $stmt->execute();
    $pending = $stmt->fetchAll();

    $stmt = $conn->prepare("SELECT v.*, u.email, p.full_name FROM verification_documents v JOIN users u ON v.user_id = u.id LEFT JOIN profiles p ON u.id = p.user_id WHERE v.status != 'pending' ORDER BY v.reviewed_at DESC LIMIT 20");
    $stmt->execute();
    $history = $stmt->fetchAll();
}

$page_title = 'Verification Requests';
require_once __DIR__ . '/../../includes/header.php';
?>
<section class="dashboard-shell admin-verify-shell">
    <div class="dashboard-card admin-verify-card">
        <div class="admin-verify-hero">
            <div>
                <p class="dashboard-kicker">Admin Console</p>
                <h1>Verification Requests</h1>
                <p class="muted-text">Review submitted identity documents, approve trusted users, and keep verification quality high.</p>
            </div>
            <div class="admin-verify-summary">
                <div>
                    <span>Pending</span>
                    <strong><?php echo count($pending); ?></strong>
                </div>
                <div>
                    <span>Reviewed</span>
                    <strong><?php echo count($history); ?></strong>
                </div>
            </div>
        </div>

        <div class="admin-verify-section">
            <div class="section-heading compact">
                <span class="section-kicker">Queue</span>
                <h2>Pending Verifications</h2>
            </div>

            <?php if (empty($pending)): ?>
                <div class="admin-empty-state">
                    <h3>All clear</h3>
                    <p class="muted-text">There are no pending verification requests right now.</p>
                </div>
            <?php else: ?>
                <div class="admin-verify-list">
                    <?php foreach ($pending as $doc): ?>
                        <article class="admin-verify-item">
                            <div class="admin-verify-main">
                                <div class="admin-verify-meta">
                                    <span class="status-chip pending"><?php echo ucfirst($doc['document_type']); ?></span>
                                    <span class="muted-text">Submitted <?php echo date('M d, Y H:i', strtotime($doc['submitted_at'])); ?></span>
                                </div>
                                <h3><?php echo htmlspecialchars($doc['full_name'] ?? $doc['email']); ?></h3>
                                <div class="admin-verify-details">
                                    <span><strong>Email:</strong> <?php echo htmlspecialchars($doc['email']); ?></span>
                                    <?php if (!empty($doc['phone'])): ?><span><strong>Phone:</strong> <?php echo htmlspecialchars($doc['phone']); ?></span><?php endif; ?>
                                    <span><strong>Document No:</strong> <?php echo htmlspecialchars($doc['document_number'] ?? 'N/A'); ?></span>
                                </div>
                                <a class="admin-document-link" href="<?php echo SITE_URL; ?>/assets/uploads/documents/<?php echo rawurlencode($doc['document_file']); ?>" target="_blank" rel="noopener noreferrer">
                                    <i class="fas fa-file-alt"></i>
                                    View uploaded document
                                </a>
                            </div>

                            <div class="admin-verify-actions">
                                <form method="POST" class="admin-verify-approve-form">
                                    <input type="hidden" name="doc_id" value="<?php echo (int) $doc['id']; ?>">
                                    <button type="submit" name="approve" class="btn-primary">Approve</button>
                                    <button type="button" class="btn-outline" onclick="showRejectForm(<?php echo (int) $doc['id']; ?>)">Reject</button>
                                </form>

                                <div id="reject-<?php echo (int) $doc['id']; ?>" class="admin-reject-panel">
                                    <form method="POST">
                                        <input type="hidden" name="doc_id" value="<?php echo (int) $doc['id']; ?>">
                                        <textarea name="reason" placeholder="Explain why this document is being rejected" required></textarea>
                                        <div class="admin-reject-actions">
                                            <button type="submit" name="reject" class="btn-primary">Confirm Reject</button>
                                            <button type="button" class="btn-outline" onclick="hideRejectForm(<?php echo (int) $doc['id']; ?>)">Cancel</button>
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
                <h2>Recent Decisions</h2>
            </div>
            <div class="admin-history-table-wrap">
                <table class="admin-history-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Document</th>
                            <th>Status</th>
                            <th>Reviewed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($history)): ?>
                            <tr>
                                <td colspan="4" class="muted-text text-center">No verification activity yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($history as $doc): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($doc['full_name'] ?? $doc['email']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($doc['document_type'])); ?></td>
                                    <td>
                                        <span class="status-chip <?php echo $doc['status'] === 'approved' ? 'verified' : 'rejected'; ?>">
                                            <?php echo htmlspecialchars(ucfirst($doc['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $doc['reviewed_at'] ? date('M d, Y', strtotime($doc['reviewed_at'])) : '-'; ?></td>
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
