const hamburger = document.getElementById('hamburger');
const navMenu = document.getElementById('navMenu');

if (hamburger && navMenu) {
    hamburger.addEventListener('click', () => {
        hamburger.classList.toggle('active');
        navMenu.classList.toggle('active');
        document.body.classList.toggle('no-scroll');
    });
}

document.addEventListener('click', (e) => {
    if (
        navMenu &&
        hamburger &&
        navMenu.classList.contains('active') &&
        !navMenu.contains(e.target) &&
        !hamburger.contains(e.target)
    ) {
        navMenu.classList.remove('active');
        hamburger.classList.remove('active');
        document.body.classList.remove('no-scroll');
    }
});

window.addEventListener('resize', () => {
    if (window.innerWidth > 768 && navMenu && hamburger && navMenu.classList.contains('active')) {
        navMenu.classList.remove('active');
        hamburger.classList.remove('active');
        document.body.classList.remove('no-scroll');
    }
});

window.addEventListener('scroll', () => {
    const navbar = document.querySelector('.navbar');
    if (!navbar) return;

    if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
    } else {
        navbar.classList.remove('scrolled');
    }
});

document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener('click', function (e) {
        const targetSelector = this.getAttribute('href');
        if (!targetSelector || targetSelector === '#') return;

        const target = document.querySelector(targetSelector);
        if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});

function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;

    let isValid = true;
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');

    inputs.forEach((input) => {
        const value = input.value.trim();

        if (!value) {
            showInputError(input, 'This field is required');
            isValid = false;
        } else if (input.type === 'email' && !validateEmail(value)) {
            showInputError(input, 'Please enter a valid email');
            isValid = false;
        } else if (input.type === 'password' && value.length < 6) {
            showInputError(input, 'Password must be at least 6 characters');
            isValid = false;
        } else {
            clearInputError(input);
        }
    });

    return isValid;
}

function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function showInputError(input, message) {
    input.classList.add('error');

    let errorMsg = input.parentNode.querySelector('.error-message');
    if (!errorMsg) {
        errorMsg = document.createElement('span');
        errorMsg.className = 'error-message';
        input.parentNode.appendChild(errorMsg);
    }
    errorMsg.textContent = message;
}

function clearInputError(input) {
    input.classList.remove('error');
    const errorMsg = input.parentNode.querySelector('.error-message');
    if (errorMsg) errorMsg.remove();
}

function checkPasswordStrength(password) {
    let strength = 0;
    const feedback = [];

    if (password.length >= 8) strength += 25; else feedback.push('At least 8 characters');
    if (password.match(/[a-z]+/)) strength += 25; else feedback.push('Add lowercase letters');
    if (password.match(/[A-Z]+/)) strength += 25; else feedback.push('Add uppercase letters');
    if (password.match(/[0-9]+/)) strength += 15; else feedback.push('Add numbers');
    if (password.match(/[$@#&!]+/)) strength += 10; else feedback.push('Add special characters');

    return { strength, feedback };
}

function showNotification(message, type = 'success', duration = 3000) {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;

    const icon = type === 'success'
        ? 'check-circle'
        : type === 'error'
            ? 'exclamation-circle'
            : type === 'warning'
                ? 'exclamation-triangle'
                : 'info-circle';

    notification.innerHTML = `
        <i class="fas fa-${icon}"></i>
        <span>${message}</span>
        <button class="notification-close"><i class="fas fa-times"></i></button>
    `;

    document.body.appendChild(notification);

    setTimeout(() => notification.classList.add('show'), 100);

    const closeButton = notification.querySelector('.notification-close');
    if (closeButton) {
        closeButton.addEventListener('click', () => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        });
    }

    setTimeout(() => {
        if (notification.parentNode) {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }
    }, duration);
}

function formatCurrency(amount) {
    return 'Rs. ' + parseInt(amount, 10).toLocaleString('en-NP');
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function copyToClipboard(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            showNotification('Copied to clipboard!');
        }).catch(() => fallbackCopy(text));
        return;
    }

    fallbackCopy(text);
}

function fallbackCopy(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);
    showNotification('Copied to clipboard!');
}

if ('IntersectionObserver' in window) {
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                const img = entry.target;
                if (img.dataset.src) {
                    img.src = img.dataset.src;
                    img.classList.add('loaded');
                }
                observer.unobserve(img);
            }
        });
    });

    document.querySelectorAll('img[data-src]').forEach((img) => imageObserver.observe(img));
}

const currentLocation = window.location.pathname;
document.querySelectorAll('.nav-links a').forEach((link) => {
    const href = link.getAttribute('href');
    if (!href || href === '#') return;

    try {
        const url = new URL(href, window.location.origin);
        if (currentLocation === url.pathname) {
            link.classList.add('active');
        }
    } catch (error) {
        // Ignore invalid URLs.
    }
});

const minPriceSlider = document.getElementById('minPrice');
const maxPriceSlider = document.getElementById('maxPrice');
const minPriceVal = document.getElementById('minPriceVal');
const maxPriceVal = document.getElementById('maxPriceVal');

if (minPriceSlider && maxPriceSlider) {
    function updatePriceLabels() {
        if (parseInt(minPriceSlider.value, 10) > parseInt(maxPriceSlider.value, 10)) {
            maxPriceSlider.value = minPriceSlider.value;
        }
        if (minPriceVal) minPriceVal.textContent = minPriceSlider.value;
        if (maxPriceVal) maxPriceVal.textContent = maxPriceSlider.value;
    }

    updatePriceLabels();
    minPriceSlider.addEventListener('input', updatePriceLabels);
    maxPriceSlider.addEventListener('input', updatePriceLabels);
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('.wishlist-btn');
    if (!button) return;

    event.preventDefault();
    event.stopPropagation();
    button.classList.toggle('active');

    const icon = button.querySelector('i');
    if (!icon) return;

    if (button.classList.contains('active')) {
        icon.classList.remove('far');
        icon.classList.add('fas');
        showNotification('Added to wishlist', 'success');
    } else {
        icon.classList.remove('fas');
        icon.classList.add('far');
        showNotification('Removed from wishlist', 'info');
    }
});

function searchRooms() {
    const location = document.getElementById('location')?.value || '';
    const minPrice = document.getElementById('minPrice')?.value || 1000;
    const maxPrice = document.getElementById('maxPrice')?.value || 50000;
    window.location.href = `pages/rooms.php?location=${encodeURIComponent(location)}&min_price=${minPrice}&max_price=${maxPrice}`;
}

let page = 1;
let loading = false;
let hasMore = true;

window.addEventListener('scroll', debounce(() => {
    if (!hasMore || loading) return;

    const scrollPosition = window.innerHeight + window.scrollY;
    const threshold = document.documentElement.scrollHeight - 1000;

    if (scrollPosition >= threshold) {
        loadMoreRooms();
    }
}, 200));

function loadMoreRooms() {
    if (!document.querySelector('.room-grid')) return;

    loading = true;
    page++;

    const url = new URL(window.location.href);
    const params = new URLSearchParams(url.search);
    params.set('page', page);
    params.set('ajax', '1');

    fetch(`api/search.php?${params.toString()}`)
        .then((response) => response.json())
        .then((data) => {
            if (data.success && data.rooms.length > 0) {
                appendRooms(data.rooms);
                hasMore = data.has_more;
            } else {
                hasMore = false;
            }
            loading = false;
        })
        .catch(() => {
            loading = false;
        });
}

function appendRooms(rooms) {
    const grid = document.querySelector('.room-grid');
    if (!grid) return;

    rooms.forEach((room) => {
        grid.insertAdjacentHTML('beforeend', createRoomCard(room));
    });
}

function createRoomCard(room) {
    return `
        <div class="room-card" onclick="window.location.href='pages/room-detail.php?id=${room.id}'">
            <div class="card-image">
                <img src="${room.primary_image || 'assets/images/default-room.svg'}" alt="${room.title}">
                ${room.is_verified ? '<span class="verified-badge"><i class="fas fa-check-circle"></i> Verified</span>' : ''}
                <button class="wishlist-btn" onclick="event.stopPropagation()"><i class="far fa-heart"></i></button>
            </div>
            <div class="card-content">
                <div class="card-price">Rs. ${room.price}<span>/month</span></div>
                <h3 class="card-title">${room.title}</h3>
                <div class="card-location"><i class="fas fa-map-marker-alt"></i> ${room.location}</div>
                <div class="landlord-info">
                    <img src="${room.landlord_avatar || 'assets/images/default-avatar.svg'}" alt="${room.landlord_name}" class="landlord-avatar">
                    <span class="landlord-name">${room.landlord_name}</span>
                    <span class="trust-score ${getTrustScoreClass(room.trust_score)}">${room.trust_score || '0'}</span>
                </div>
            </div>
        </div>
    `;
}

function getTrustScoreClass(score) {
    if (score >= 80) return 'trust-high';
    if (score >= 50) return 'trust-medium';
    return 'trust-low';
}
