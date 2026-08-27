export {};

/**
 * Reveal-on-scroll for [data-reveal] and [data-reveal-group].
 *
 * This file only decides *when* — every value (distance, duration, easing,
 * the stagger cascade) lives in css/includes/motion.pcss. Keeping the split
 * that way is what lets the hidden initial state ship in critical CSS, so
 * nothing flashes in before the script runs.
 */

const REVEALED = 'is-revealed';
const SELECTOR = '[data-reveal], [data-reveal-group]';

/** How far into the viewport an element must come before it reveals. */
const ROOT_MARGIN = '0px 0px -12% 0px';

function init(): void {
    const targets = document.querySelectorAll(SELECTOR);

    if (!targets.length) {
        return;
    }

    // No observer available: show everything rather than leave it hidden.
    if (!('IntersectionObserver' in window)) {
        targets.forEach((el) => el.classList.add(REVEALED));
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                entry.target.classList.add(REVEALED);

                // Reveal once. Re-running on every scroll back up is the tell
                // of a cheap site, and there's nothing left to watch for.
                observer.unobserve(entry.target);
            });
        },
        { rootMargin: ROOT_MARGIN }
    );

    targets.forEach((el) => observer.observe(el));
}

// Deliberately no prefers-reduced-motion bail-out here, unlike the Motion
// transitions. The CSS never hides anything under a reduce preference, so
// bailing would only matter if the preference flipped mid-session — at which
// point CSS would re-hide elements this file had stopped watching. Observing
// unconditionally costs one IntersectionObserver and cannot strand content.

// main.js is loaded async, so this can run before the document has parsed.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
} else {
    init();
}
