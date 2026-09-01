    </main>
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-section footer-brand">
                <?php $siteLogoUrl = $siteLogoUrl ?? getSiteLogoUrl(); ?>
                <a href="<?php echo SITE_URL; ?>/index.php" class="footer-brand-link" aria-label="Go to Gharbeti homepage">
                    <?php if ($siteLogoUrl): ?>
                        <img src="<?php echo $siteLogoUrl; ?>" alt="Gharbeti logo" class="footer-logo">
                    <?php else: ?>
                        <span class="logo-mark"><i class="fas fa-home"></i></span>
                    <?php endif; ?>
                    <span class="footer-brand-text">
                        <span class="footer-brand-name">Gharbeti</span>
                        <span class="footer-brand-tagline">Live where you belong</span>
                    </span>
                </a>
                <p>Nepal's trust-first, broker-free room discovery platform for tenants and landlords who want a safer, more direct rental experience.</p>
                <div class="social-links">
                    <a href="https://facebook.com/gharbeti" target="_blank" rel="noopener noreferrer" class="social-icon" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://instagram.com/gharbeti" target="_blank" rel="noopener noreferrer" class="social-icon" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="https://twitter.com/gharbeti" target="_blank" rel="noopener noreferrer" class="social-icon" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="https://linkedin.com/company/gharbeti" target="_blank" rel="noopener noreferrer" class="social-icon" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
            <div class="footer-section"><h4>Quick Links</h4><ul><li><a href="<?php echo SITE_URL; ?>/pages/about.php"><i class="fas fa-chevron-right"></i> About Us</a></li><li><a href="<?php echo SITE_URL; ?>/pages/how-it-works.php"><i class="fas fa-chevron-right"></i> How It Works</a></li><li><a href="<?php echo SITE_URL; ?>/pages/contact.php"><i class="fas fa-chevron-right"></i> Contact</a></li><li><a href="<?php echo SITE_URL; ?>/pages/faq.php"><i class="fas fa-chevron-right"></i> FAQ</a></li><li><a href="<?php echo SITE_URL; ?>/pages/blog.php"><i class="fas fa-chevron-right"></i> Blog</a></li><li><a href="<?php echo SITE_URL; ?>/pages/sitemap.php"><i class="fas fa-chevron-right"></i> Sitemap</a></li></ul></div>
            <div class="footer-section"><h4>For Landlords</h4><ul><li><a href="<?php echo SITE_URL; ?>/auth/register.php?role=landlord"><i class="fas fa-chevron-right"></i> List Your Property</a></li><li><a href="<?php echo SITE_URL; ?>/pages/verification-guide.php"><i class="fas fa-chevron-right"></i> Get Verified</a></li><li><a href="<?php echo SITE_URL; ?>/pages/contract-templates.php"><i class="fas fa-chevron-right"></i> Contract Templates</a></li><li><a href="<?php echo SITE_URL; ?>/pages/rental-tips.php"><i class="fas fa-chevron-right"></i> Rental Tips</a></li><li><a href="<?php echo SITE_URL; ?>/pages/pricing.php"><i class="fas fa-chevron-right"></i> Pricing</a></li></ul></div>
            <div class="footer-section"><h4>For Tenants</h4><ul><li><a href="<?php echo SITE_URL; ?>/pages/rooms.php"><i class="fas fa-chevron-right"></i> Search Rooms</a></li><li><a href="<?php echo SITE_URL; ?>/pages/verification-guide.php"><i class="fas fa-chevron-right"></i> Verification Guide</a></li><li><a href="<?php echo SITE_URL; ?>/pages/renter-rights.php"><i class="fas fa-chevron-right"></i> Renter Rights</a></li><li><a href="<?php echo SITE_URL; ?>/pages/rental-tips.php"><i class="fas fa-chevron-right"></i> Rental Tips</a></li><li><a href="<?php echo SITE_URL; ?>/pages/success-stories.php"><i class="fas fa-chevron-right"></i> Success Stories</a></li></ul></div>
            <div class="footer-section"><h4>Legal</h4><ul><li><a href="<?php echo SITE_URL; ?>/pages/privacy.php"><i class="fas fa-chevron-right"></i> Privacy Policy</a></li><li><a href="<?php echo SITE_URL; ?>/pages/terms.php"><i class="fas fa-chevron-right"></i> Terms of Service</a></li><li><a href="<?php echo SITE_URL; ?>/pages/cookie-policy.php"><i class="fas fa-chevron-right"></i> Cookie Policy</a></li><li><a href="<?php echo SITE_URL; ?>/pages/disclaimer.php"><i class="fas fa-chevron-right"></i> Disclaimer</a></li></ul></div>
            <div class="footer-section"><h4>Contact Info</h4><ul class="contact-info"><li><i class="fas fa-map-marker-alt"></i> Putalisadak, Kathmandu, Nepal</li><li><i class="fas fa-phone-alt"></i> +977 1-4234567</li><li><i class="fas fa-envelope"></i> info@gharbeti.com</li><li><i class="fas fa-clock"></i> Sun-Fri: 9:00 AM - 6:00 PM</li></ul></div>
        </div>
        <div class="footer-bottom"><div class="footer-bottom-content"><p>&copy; <?php echo date('Y'); ?> Gharbeti. All rights reserved. Made with <i class="fas fa-heart text-primary"></i> in Nepal</p><div class="footer-links"><a href="<?php echo SITE_URL; ?>/pages/privacy.php">Privacy</a><a href="<?php echo SITE_URL; ?>/pages/terms.php">Terms</a><a href="<?php echo SITE_URL; ?>/pages/sitemap.php">Sitemap</a></div></div></div>
    </footer>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/modules.js"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/animations.js"></script>
    <?php if (isLoggedIn()): ?>
    <script>
        function updateHeaderBadge(selector, count, type) {
            const link = document.querySelector(selector);
            if (!link) return;
            let badge = link.querySelector('.unread-badge');
            if (count > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'header-badge unread-badge';
                    badge.dataset.badge = type;
                    link.appendChild(badge);
                }
                badge.textContent = count;
            } else if (badge) {
                badge.remove();
            }
        }
        window.setInterval(function () {
            fetch('<?php echo SITE_URL; ?>/api/get-unread-counts.php')
                .then((response) => response.json())
                .then((data) => {
                    if (!data.success) return;
                    updateHeaderBadge('a[href="<?php echo SITE_URL; ?>/pages/messages.php"]', data.unread_messages, 'messages');
                    updateHeaderBadge('a[href="<?php echo SITE_URL; ?>/pages/notifications.php"]', data.unread_notifications, 'notifications');
                })
                .catch(() => {});
        }, 30000);
    </script>
    <?php endif; ?>
    <script>
        AOS.init({ duration: 800, once: true, offset: 100 });
    </script>
</body>
</html>
