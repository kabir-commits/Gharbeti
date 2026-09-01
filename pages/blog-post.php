<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
$blog_posts = [
    1 => ['title' => 'How to Avoid Rental Scams in Nepal', 'category' => 'Safety', 'date' => '2026-02-15', 'author' => 'Gharbeti Team', 'content' => ['Never send advance money before seeing the place and confirming who controls the property.', 'Be cautious when a listing pressures you to decide immediately or avoids direct questions.', 'Use on-platform messaging when possible so there is a written record of the conversation.', 'Ask for clear rent breakdowns, utility expectations, deposit terms, and move-in conditions before you commit.']],
    2 => ['title' => 'What a Good Rental Agreement Should Always Include', 'category' => 'Legal', 'date' => '2026-02-09', 'author' => 'Gharbeti Team', 'content' => ['A strong rental agreement clearly defines monthly rent, advance payment, deposit, start date, notice period, and house rules.', 'It should also clarify who handles repairs, what utilities are included, and what conditions apply when the agreement ends.']],
    3 => ['title' => 'How Landlords Can Earn More Trust Online', 'category' => 'Landlords', 'date' => '2026-01-30', 'author' => 'Gharbeti Team', 'content' => ['Complete your profile, verify your account, and use clear room photos taken in natural light.', 'Trust grows when the listing feels complete and consistent with reality.']],
    4 => ['title' => 'Student-Friendly Areas to Consider Around Kathmandu Valley', 'category' => 'Location Guide', 'date' => '2026-01-21', 'author' => 'Gharbeti Team', 'content' => ['Students usually balance commute distance, food access, water reliability, and monthly budget more than square footage alone.']],
    5 => ['title' => 'How Trust Scores Work on Gharbeti', 'category' => 'Product Guide', 'date' => '2026-01-12', 'author' => 'Gharbeti Team', 'content' => ['Trust scores can reflect verification progress, activity quality, successful contracts, and review patterns.']],
    6 => ['title' => 'Preparing Your Room Listing Photos the Right Way', 'category' => 'Listing Tips', 'date' => '2025-12-28', 'author' => 'Gharbeti Team', 'content' => ['Take wide, bright, truthful photos of each major area and avoid heavy editing that changes the feel of the room.']],
];
$post_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$post = $blog_posts[$post_id] ?? null;
if (!$post) { http_response_code(404); }
$page_title = $post ? $post['title'] : 'Article Not Found';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="page-shell"><div class="container page-content page-content-narrow"><?php if (!$post): ?><div class="page-panel empty-state"><i class="fas fa-newspaper"></i><h3>Article not found</h3><p>The article you are looking for is not available.</p><a href="<?php echo SITE_URL; ?>/pages/blog.php" class="btn-primary">Back to Blog</a></div><?php else: ?><div class="page-hero page-hero-left"><span class="page-eyebrow"><?php echo htmlspecialchars($post['category']); ?></span><h1><?php echo htmlspecialchars($post['title']); ?></h1><p><?php echo htmlspecialchars($post['author']); ?> | <?php echo date('F d, Y', strtotime($post['date'])); ?></p></div><article class="page-panel prose-panel"><?php foreach ($post['content'] as $paragraph): ?><p><?php echo htmlspecialchars($paragraph); ?></p><?php endforeach; ?></article><?php endif; ?></div></section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
