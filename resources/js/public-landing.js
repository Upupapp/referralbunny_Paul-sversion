const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

document.documentElement.classList.add('js-ready');

function track(eventName, params = {}) {
    if (typeof window.rbTrack === 'function') {
        window.rbTrack(eventName, params);
    }
}

// ── Scroll reveal ────────────────────────────────────────────
// Pairs with the `.reveal` / `.reveal.is-visible` CSS in app.css.
// If reduced motion is preferred (or IntersectionObserver is
// unavailable), everything is shown immediately — no animation.
const revealEls = document.querySelectorAll('.reveal');

if (prefersReducedMotion || !('IntersectionObserver' in window)) {
    revealEls.forEach((el) => el.classList.add('is-visible'));
} else {
    const revealObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

    revealEls.forEach((el) => revealObserver.observe(el));
}

// ── Click analytics ──────────────────────────────────────────
// <a data-rb-event="hero_build_clicked" data-rb-params='{"placement":"hero"}'>
document.addEventListener('click', (event) => {
    const el = event.target.closest('[data-rb-event]');
    if (!el) return;

    let params = {};
    if (el.dataset.rbParams) {
        try {
            params = JSON.parse(el.dataset.rbParams);
        } catch (e) {
            params = {};
        }
    }

    track(el.dataset.rbEvent, params);
});

// ── View analytics ───────────────────────────────────────────
// <section data-rb-view-event="how_it_works_viewed"> fires once.
const viewEls = document.querySelectorAll('[data-rb-view-event]');

if (viewEls.length && 'IntersectionObserver' in window) {
    const viewObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                track(entry.target.dataset.rbViewEvent, {});
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.3 });

    viewEls.forEach((el) => viewObserver.observe(el));
}

track('landing_viewed', { path: window.location.pathname });

// ── Asset failure safety net ─────────────────────────────────
// If a critical brand image (logo/mascot) fails to load, surface
// the branded failure modal instead of a broken-image icon.
document.querySelectorAll('img[data-rb-critical]').forEach((img) => {
    img.addEventListener('error', () => {
        window.dispatchEvent(new CustomEvent('rb-modal-open'));
    }, { once: true });
});
