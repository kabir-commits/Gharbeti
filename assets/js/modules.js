/**
 * GHARBETI JAVASCRIPT MODULES
 * Small reusable UI helpers layered on top of the current frontend.
 */

const UI = {
    init() {
        this.initMobileMenu();
        this.initDropdowns();
        this.initModals();
        this.initTabs();
        this.initAccordions();
        this.initTooltips();
    },

    initMobileMenu() {
        const hamburger = document.getElementById('hamburger');
        const navMenu = document.getElementById('navMenu');
        if (!hamburger || !navMenu) return;

        hamburger.addEventListener('click', () => {
            hamburger.classList.toggle('active');
            navMenu.classList.toggle('active');
            document.body.classList.toggle('no-scroll');
        });

        document.addEventListener('click', (event) => {
            if (!navMenu.classList.contains('active')) return;
            if (navMenu.contains(event.target) || hamburger.contains(event.target)) return;
            hamburger.classList.remove('active');
            navMenu.classList.remove('active');
            document.body.classList.remove('no-scroll');
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                hamburger.classList.remove('active');
                navMenu.classList.remove('active');
                document.body.classList.remove('no-scroll');
            }
        });
    },

    initDropdowns() {
        document.querySelectorAll('.dropdown-toggle').forEach((toggle) => {
            toggle.addEventListener('click', (event) => {
                event.preventDefault();
                const dropdown = toggle.closest('.nav-dropdown');
                if (!dropdown) return;
                dropdown.classList.toggle('active');
            });
        });

        document.addEventListener('click', (event) => {
            document.querySelectorAll('.nav-dropdown.active').forEach((dropdown) => {
                if (!dropdown.contains(event.target)) {
                    dropdown.classList.remove('active');
                }
            });
        });
    },

    initModals() {
        document.querySelectorAll('[data-modal]').forEach((trigger) => {
            trigger.addEventListener('click', () => this.openModal(trigger.dataset.modal));
        });

        document.querySelectorAll('[data-close-modal]').forEach((button) => {
            button.addEventListener('click', () => this.closeModal(button.dataset.closeModal));
        });

        document.querySelectorAll('.modal').forEach((modal) => {
            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    this.closeModal(modal.id);
                }
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            document.querySelectorAll('.modal.active').forEach((modal) => this.closeModal(modal.id));
        });
    },

    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        modal.classList.add('active');
        document.body.classList.add('no-scroll');
    },

    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        modal.classList.remove('active');
        document.body.classList.remove('no-scroll');
    },

    initTabs() {
        document.querySelectorAll('.tab-container').forEach((container) => {
            const tabs = container.querySelectorAll('.tab');
            const contents = container.querySelectorAll('.tab-content');
            tabs.forEach((tab) => {
                tab.addEventListener('click', () => {
                    const target = tab.dataset.tab;
                    tabs.forEach((item) => item.classList.remove('active'));
                    contents.forEach((item) => item.classList.remove('active'));
                    tab.classList.add('active');
                    const targetPanel = document.getElementById(target);
                    if (targetPanel) targetPanel.classList.add('active');
                });
            });
        });
    },

    initAccordions() {
        document.querySelectorAll('.accordion-item').forEach((item) => {
            const header = item.querySelector('.accordion-header');
            const content = item.querySelector('.accordion-content');
            if (!header || !content) return;
            header.addEventListener('click', () => {
                const isActive = item.classList.contains('active');
                document.querySelectorAll('.accordion-item.active').forEach((openItem) => {
                    openItem.classList.remove('active');
                    const openContent = openItem.querySelector('.accordion-content');
                    if (openContent) openContent.style.maxHeight = '';
                });
                if (!isActive) {
                    item.classList.add('active');
                    content.style.maxHeight = `${content.scrollHeight}px`;
                }
            });
        });
    },

    initTooltips() {
        document.querySelectorAll('[data-tooltip]').forEach((element) => {
            element.addEventListener('mouseenter', () => {
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.textContent = element.dataset.tooltip || '';
                document.body.appendChild(tooltip);
                const rect = element.getBoundingClientRect();
                const top = window.scrollY + rect.top - tooltip.offsetHeight - 10;
                const left = window.scrollX + rect.left + rect.width / 2 - tooltip.offsetWidth / 2;
                tooltip.style.top = `${Math.max(8, top)}px`;
                tooltip.style.left = `${Math.max(8, left)}px`;
                element._tooltip = tooltip;
            });
            element.addEventListener('mouseleave', () => {
                if (element._tooltip) {
                    element._tooltip.remove();
                    delete element._tooltip;
                }
            });
        });
    }
};

const Form = {
    init() {
        this.initValidation();
        this.initPasswordStrength();
        this.initFileUpload();
        this.initDatePickers();
    },

    initValidation() {
        document.querySelectorAll('form[data-validate]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (!this.validateForm(form)) {
                    event.preventDefault();
                }
            });

            form.querySelectorAll('input[required], select[required], textarea[required]').forEach((field) => {
                field.addEventListener('blur', () => this.validateField(field));
            });
        });
    },

    validateForm(form) {
        let valid = true;
        form.querySelectorAll('input[required], select[required], textarea[required]').forEach((field) => {
            if (!this.validateField(field)) valid = false;
        });
        return valid;
    },

    validateField(field) {
        const value = (field.value || '').trim();
        let errorMessage = '';
        const existingError = field.parentNode.querySelector('.error-message');
        if (existingError) existingError.remove();
        field.classList.remove('error');

        if (field.required && !value) {
            errorMessage = 'This field is required';
        } else if (field.type === 'email' && value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            errorMessage = 'Please enter a valid email address';
        } else if (field.type === 'password' && value && value.length < 6) {
            errorMessage = 'Password must be at least 6 characters';
        } else if (field.type === 'number' && value && Number.isNaN(Number(value))) {
            errorMessage = 'Please enter a valid number';
        }

        if (!errorMessage) return true;

        field.classList.add('error');
        const error = document.createElement('span');
        error.className = 'error-message';
        error.textContent = errorMessage;
        field.parentNode.appendChild(error);
        return false;
    },

    initPasswordStrength() {
        document.querySelectorAll('input[type="password"][data-strength]').forEach((field) => {
            let meter = field.parentNode.querySelector('.password-strength');
            if (!meter) {
                meter = document.createElement('div');
                meter.className = 'password-strength';
                field.parentNode.appendChild(meter);
            }
            field.addEventListener('input', () => {
                const strength = this.checkPasswordStrength(field.value);
                meter.className = `password-strength strength-${strength.level}`;
                meter.setAttribute('data-strength-label', strength.message);
            });
        });
    },

    checkPasswordStrength(password) {
        let score = 0;
        if (password.length >= 8) score++;
        if (/[a-z]/.test(password)) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[^a-zA-Z0-9]/.test(password)) score++;
        const level = Math.min(4, score);
        const labels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
        return { level, message: labels[level] };
    },

    initFileUpload() {
        document.querySelectorAll('.file-upload input[type="file"]').forEach((input) => {
            input.addEventListener('change', () => {
                const wrapper = input.closest('.file-upload');
                const preview = wrapper ? wrapper.querySelector('.file-preview') : null;
                if (!preview) return;
                preview.innerHTML = '';
                Array.from(input.files || []).forEach((file) => {
                    if (!file.type.startsWith('image/')) return;
                    const reader = new FileReader();
                    reader.onload = (event) => {
                        const img = document.createElement('img');
                        img.src = event.target?.result || '';
                        img.style.width = '100px';
                        img.style.height = '100px';
                        img.style.objectFit = 'cover';
                        img.style.borderRadius = '8px';
                        preview.appendChild(img);
                    };
                    reader.readAsDataURL(file);
                });
            });
        });
    },

    initDatePickers() {
        document.querySelectorAll('input[type="date"][data-min-today]').forEach((input) => {
            input.min = new Date().toISOString().split('T')[0];
        });
    }
};

const Notification = {
    show(message, type = 'success', duration = 3000) {
        const notice = document.createElement('div');
        notice.className = `notification notification-${type}`;
        const icon = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        }[type] || 'info-circle';

        notice.innerHTML = `
            <i class="fas fa-${icon}"></i>
            <span>${message}</span>
            <button type="button" class="notification-close" aria-label="Close notification">
                <i class="fas fa-times"></i>
            </button>
        `;

        document.body.appendChild(notice);
        requestAnimationFrame(() => notice.classList.add('show'));

        const close = () => {
            notice.classList.remove('show');
            setTimeout(() => notice.remove(), 300);
        };

        notice.querySelector('.notification-close')?.addEventListener('click', close);
        window.setTimeout(() => {
            if (notice.isConnected) close();
        }, duration);
    },
    success(message) { this.show(message, 'success'); },
    error(message) { this.show(message, 'error'); },
    warning(message) { this.show(message, 'warning'); },
    info(message) { this.show(message, 'info'); }
};

const Ajax = {
    get(url, data = {}) {
        const query = new URLSearchParams(data).toString();
        const target = query ? `${url}?${query}` : url;
        return fetch(target).then((response) => response.json());
    },
    post(url, data = {}) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        }).then((response) => response.json());
    },
    postForm(url, formData) {
        return fetch(url, { method: 'POST', body: formData }).then((response) => response.json());
    }
};

const Storage = {
    set(key, value) { localStorage.setItem(key, JSON.stringify(value)); },
    get(key) {
        const value = localStorage.getItem(key);
        return value ? JSON.parse(value) : null;
    },
    remove(key) { localStorage.removeItem(key); },
    clear() { localStorage.clear(); }
};

const Analytics = {
    trackEvent(category, action, label = null, value = null) {
        if (typeof window.gtag === 'function') {
            window.gtag('event', action, { event_category: category, event_label: label, value });
        }
        fetch('/gharbeti/api/track-event.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ category, action, label, value })
        }).catch(() => {});
    },
    trackPageView(page) {
        if (typeof window.gtag === 'function') {
            window.gtag('config', 'GA_MEASUREMENT_ID', { page_path: page });
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    UI.init();
    Form.init();
    Analytics.trackPageView(window.location.pathname);

    document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
        anchor.addEventListener('click', function (event) {
            const href = this.getAttribute('href');
            if (!href || href === '#') return;
            const target = document.querySelector(href);
            if (!target) return;
            event.preventDefault();
            target.scrollIntoView({ behavior: 'smooth' });
        });
    });

    if (!document.querySelector('.scroll-top')) {
        const scrollTopButton = document.createElement('button');
        scrollTopButton.type = 'button';
        scrollTopButton.className = 'scroll-top';
        scrollTopButton.setAttribute('aria-label', 'Scroll to top');
        scrollTopButton.innerHTML = '<i class="fas fa-arrow-up"></i>';
        document.body.appendChild(scrollTopButton);

        scrollTopButton.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        window.addEventListener('scroll', () => {
            if (window.scrollY > 500) {
                scrollTopButton.classList.add('show');
            } else {
                scrollTopButton.classList.remove('show');
            }
        });
    }
});

window.UI = UI;
window.Form = Form;
window.Notification = Notification;
window.Ajax = Ajax;
window.Storage = Storage;
window.Analytics = Analytics;
