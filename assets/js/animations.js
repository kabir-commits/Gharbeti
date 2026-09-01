document.addEventListener('DOMContentLoaded', () => {
    document.documentElement.classList.add('js-ready');
    hydrateMotionTargets();
    initScrollAnimations();
    initCounters();
    initParallax();
    initHoverEffects();
    initTypingEffect();
    initParticles();
    initPageTransitions();
    initScrollTop();
});

function hydrateMotionTargets() {
    const groups = [
        '.page-hero',
        '.page-panel',
        '.mini-card',
        '.timeline-item',
        '.faq-item',
        '.dashboard-card',
        '.profile-card',
        '.profile-mini-card',
        '.profile-hero-card',
        '.admin-verify-item',
        '.admin-history-table',
        '.auth-card',
        '.section-heading',
        '.admin-empty-state'
    ];

    groups.forEach((selector) => {
        document.querySelectorAll(selector).forEach((element, index) => {
            if (!element.hasAttribute('data-animate')) {
                element.setAttribute('data-animate', 'fade-up');
            }
            if (!element.hasAttribute('data-delay')) {
                element.setAttribute('data-delay', String((index % 4) * 90));
            }
        });
    });
}

function initScrollAnimations() {
    const targets = document.querySelectorAll([
        '[data-animate]',
        '.step-card',
        '.feature-card',
        '.room-card',
        '.testimonial-card',
        '.page-hero',
        '.page-panel',
        '.mini-card',
        '.timeline-item',
        '.faq-item',
        '.dashboard-card',
        '.profile-card',
        '.profile-mini-card',
        '.profile-hero-card',
        '.admin-verify-item',
        '.admin-history-table',
        '.auth-card',
        '.section-heading',
        '.admin-empty-state'
    ].join(', '));

    targets.forEach((target) => {
        const delay = parseInt(target.getAttribute('data-delay') || '0', 10);
        if (!Number.isNaN(delay)) {
            target.style.transitionDelay = `${delay}ms`;
        }
    });

    if (!('IntersectionObserver' in window)) {
        targets.forEach((target) => target.classList.add('in-view'));
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('in-view');
            entry.target.querySelectorAll('.counter').forEach((counter) => {
                if (!counter.classList.contains('counted')) {
                    animateCounter(counter);
                }
            });
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.14, rootMargin: '0px 0px -40px 0px' });

    targets.forEach((target) => observer.observe(target));
}

function initCounters() {
    document.querySelectorAll('.counter').forEach((counter) => {
        if (!counter.classList.contains('counted') && counter.closest('.in-view')) {
            animateCounter(counter);
        }
    });
}

function animateCounter(counter) {
    const target = parseFloat(counter.getAttribute('data-target')) || 0;
    const duration = 1800;
    const stepTime = 20;
    const increment = target / (duration / stepTime);
    let current = 0;

    counter.classList.add('counted');

    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            counter.innerText = Number.isInteger(target) ? target : target.toFixed(1);
            clearInterval(timer);
        } else {
            counter.innerText = Number.isInteger(target) ? Math.floor(current) : current.toFixed(1);
        }
    }, stepTime);
}

function initParallax() {
    const heroContent = document.querySelector('.hero-content');
    const heroBackground = document.querySelector('.hero-background');
    if (!heroContent && !heroBackground) return;

    window.addEventListener('scroll', () => {
        const scrolled = window.pageYOffset;
        if (heroBackground) heroBackground.style.transform = `translateY(${scrolled * 0.3}px)`;
        if (heroContent) {
            heroContent.style.transform = `translateY(${scrolled * 0.2}px)`;
            heroContent.style.opacity = String(Math.max(0.2, 1 - (scrolled * 0.002)));
        }
    });
}

function initHoverEffects() {
    document.querySelectorAll('.room-card, .feature-card, .step-card').forEach((card) => {
        card.addEventListener('mousemove', (e) => {
            if (window.innerWidth < 992) return;
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const angleX = (y - rect.height / 2) / 24;
            const angleY = (rect.width / 2 - x) / 24;
            card.style.transform = `perspective(1000px) rotateX(${angleX}deg) rotateY(${angleY}deg) translateY(-8px)`;
        });

        card.addEventListener('mouseleave', () => {
            card.style.transform = '';
        });
    });
}

function initTypingEffect() {
    const heroTitle = document.querySelector('.hero-title');
    if (!heroTitle || heroTitle.classList.contains('typed')) return;

    const originalText = heroTitle.innerHTML;
    const spanMatch = originalText.match(/<span>(.*?)<\/span>/);
    const spanText = spanMatch ? spanMatch[1] : '';
    const mainText = originalText.replace(/<span>.*?<\/span>/, '').trim();

    heroTitle.innerHTML = '';
    heroTitle.classList.add('typed');

    let i = 0;
    const chars = mainText.split('');
    const typeMain = setInterval(() => {
        if (i < chars.length) {
            heroTitle.innerHTML += chars[i++];
        } else {
            clearInterval(typeMain);
            if (!spanText) return;
            heroTitle.innerHTML += '<span></span>';
            const span = heroTitle.querySelector('span');
            let j = 0;
            const spanChars = spanText.split('');
            const typeSpan = setInterval(() => {
                if (span && j < spanChars.length) {
                    span.innerHTML += spanChars[j++];
                } else {
                    clearInterval(typeSpan);
                }
            }, 60);
        }
    }, 45);
}

function initParticles() {
    const hero = document.querySelector('.hero');
    if (!hero || hero.classList.contains('has-particles')) return;

    hero.classList.add('has-particles');
    for (let i = 0; i < 30; i++) createParticle(hero);
}

function createParticle(container) {
    const particle = document.createElement('div');
    particle.className = 'particle';

    const size = Math.random() * 10 + 5;
    const duration = Math.random() * 10 + 10;
    const delay = Math.random() * 5;
    const left = Math.random() * 100;
    const opacity = Math.random() * 0.3;

    particle.style.cssText = `
        position: absolute;
        width: ${size}px;
        height: ${size}px;
        background: rgba(218, 165, 32, ${opacity});
        border-radius: 50%;
        top: -10%;
        left: ${left}%;
        pointer-events: none;
        animation: floatParticle ${duration}s linear ${delay}s infinite;
    `;

    container.appendChild(particle);
}

function initPageTransitions() {
    document.querySelectorAll('a:not([target="_blank"]):not([href^="#"]):not([href^="http"])').forEach((link) => {
        link.addEventListener('click', (e) => {
            const href = link.getAttribute('href');
            if (!href || href.startsWith('#') || href.startsWith('javascript')) return;
            e.preventDefault();
            document.body.style.opacity = '0';
            document.body.style.transform = 'scale(0.985)';
            document.body.style.transition = 'all 0.25s ease';
            setTimeout(() => { window.location.href = href; }, 220);
        });
    });

    window.addEventListener('load', () => {
        document.body.style.opacity = '1';
        document.body.style.transform = 'scale(1)';
        document.body.style.transition = 'all 0.45s ease';
    });
}

function initScrollTop() {
    const scrollBtn = document.createElement('button');
    scrollBtn.className = 'scroll-top';
    scrollBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
    scrollBtn.setAttribute('aria-label', 'Scroll to top');
    document.body.appendChild(scrollBtn);

    scrollBtn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    window.addEventListener('scroll', () => {
        if (window.scrollY > 500) scrollBtn.classList.add('show');
        else scrollBtn.classList.remove('show');
    });
}

const style = document.createElement('style');
style.textContent = `
    @keyframes floatParticle {
        from { transform: translateY(0) rotate(0deg); }
        to { transform: translateY(110vh) rotate(360deg); }
    }

    @keyframes float {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }

    .step-icon, .feature-icon, .page-eyebrow { animation: float 3.8s ease-in-out infinite; }
    .particle { position: absolute; pointer-events: none; z-index: 1; }

    .scroll-top {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 50px;
        height: 50px;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        box-shadow: var(--shadow-lg);
        opacity: 0;
        visibility: hidden;
        transition: var(--transition);
        z-index: 99;
    }
    .scroll-top.show { opacity: 1; visibility: visible; }
    .scroll-top:hover { background: var(--primary-dark); transform: translateY(-3px); }
    @media (max-width: 768px) {
        .scroll-top { bottom: 20px; right: 20px; width: 40px; height: 40px; font-size: 1rem; }
    }
`;
document.head.appendChild(style);
