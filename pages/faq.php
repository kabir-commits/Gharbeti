<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Frequently Asked Questions';
require_once __DIR__ . '/../includes/header.php';
$faqs = [
    'General' => [
        ['What is Gharbeti?', 'Gharbeti is a broker-free rental platform that helps tenants and landlords connect directly with verification, messaging, contracts, and reviews.'],
        ['Is Gharbeti free to use?', 'Browsing rooms, creating an account, and using core platform flows are available without brokerage fees.'],
    ],
    'Tenants' => [
        ['How do I contact a landlord?', 'Open a room, send a message, and continue the conversation once the contact request is accepted.'],
        ['Can I save rooms?', 'Yes. Logged-in users can save rooms to their favorites list.'],
    ],
    'Landlords' => [
        ['How do I list a room?', 'Register as a landlord, complete your profile, and create a listing from the landlord dashboard.'],
        ['How do contracts work?', 'After a tenant-landlord conversation is established, contracts can be created, reviewed, and signed digitally by both parties.'],
    ],
    'Verification' => [
        ['Why should I verify my account?', 'Verification improves trust, helps moderation, and can make other users more comfortable engaging with you.'],
    ],
];
?>
<section class="page-shell"><div class="container page-content page-content-narrow"><div class="page-hero"><span class="page-eyebrow">FAQ</span><h1>Answers to the questions users ask most often.</h1><p>Search the FAQ or filter by category to find the right answer faster.</p></div><div class="faq-tools page-panel"><input id="faqSearch" class="faq-search" type="text" placeholder="Search questions or answers..."><div class="tag-row" id="faqFilters"><button type="button" class="faq-filter is-active" data-category="all">All</button><?php foreach ($faqs as $category => $items): ?><button type="button" class="faq-filter" data-category="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></button><?php endforeach; ?></div></div><div id="faqContainer" class="faq-groups"><?php foreach ($faqs as $category => $items): ?><section class="page-panel faq-group" data-category="<?php echo htmlspecialchars($category); ?>"><h2><?php echo htmlspecialchars($category); ?></h2><div class="stack-list"><?php foreach ($items as $item): ?><article class="faq-item" data-faq-item><button type="button" class="faq-question" aria-expanded="false"><span><?php echo htmlspecialchars($item[0]); ?></span><i class="fas fa-chevron-down"></i></button><div class="faq-answer" hidden><p><?php echo htmlspecialchars($item[1]); ?></p></div></article><?php endforeach; ?></div></section><?php endforeach; ?></div><div class="page-panel page-cta"><h2>Still need help?</h2><p>If your question is not covered here, our contact page is the fastest next step.</p><div class="tag-row"><a href="<?php echo SITE_URL; ?>/pages/contact.php" class="btn-primary">Contact Support</a><a href="<?php echo SITE_URL; ?>/pages/help.php" class="btn-outline">Help Center</a></div></div></div></section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const search = document.getElementById('faqSearch');
    const filters = document.querySelectorAll('.faq-filter');
    const groups = document.querySelectorAll('.faq-group');
    const items = document.querySelectorAll('[data-faq-item]');
    let activeCategory = 'all';
    document.querySelectorAll('.faq-question').forEach((button) => {
        button.addEventListener('click', function () {
            const answer = this.nextElementSibling;
            const expanded = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            answer.hidden = expanded;
        });
    });
    function applyFilters() {
        const term = search.value.trim().toLowerCase();
        items.forEach((item) => {
            const group = item.closest('.faq-group');
            const category = group.dataset.category;
            const text = item.textContent.toLowerCase();
            const categoryMatch = activeCategory === 'all' || activeCategory === category;
            const searchMatch = term === '' || text.includes(term);
            item.style.display = categoryMatch && searchMatch ? '' : 'none';
        });
        groups.forEach((group) => {
            const visibleItems = group.querySelectorAll('[data-faq-item]:not([style*="display: none"])').length;
            group.style.display = visibleItems > 0 ? '' : 'none';
        });
    }
    filters.forEach((button) => {
        button.addEventListener('click', function () {
            activeCategory = this.dataset.category;
            filters.forEach((btn) => btn.classList.remove('is-active'));
            this.classList.add('is-active');
            applyFilters();
        });
    });
    search.addEventListener('input', applyFilters);
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
