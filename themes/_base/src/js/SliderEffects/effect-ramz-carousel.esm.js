/**
 * Ramz carousel effect for Swiper.
 *
 * The active card is wide and the resting ones are narrow, and the width
 * interpolates with the drag rather than switching on an active class — so a
 * half-finished swipe shows a half-finished transition and tracks back if the
 * thumb reverses.
 *
 * The picture inside is drawn at the ACTIVE width and never rescaled: the card
 * is a mask that opens, which is what makes the image read as sliding sideways
 * instead of stretching. Nothing here knows that — the sizes all live in
 * blocks/sliderCarousel.pcss, and this module only publishes three numbers per
 * slide for the CSS to interpolate against:
 *
 *   --carousel-open   0..1, how far this card has opened
 *   --carousel-shift  how many cards' worth of growth sit to its left, so a
 *                     grown card pushes its neighbours along instead of
 *                     overlapping them
 *   --carousel-lead   0..1 for the copy, which stays at 0 until the card is
 *                     most of the way open
 *
 * Keeping the pixels in CSS is what makes it responsive for free: the slide
 * keeps its resting width at every breakpoint, so Swiper's own layout and snap
 * maths never move, and there is nothing here to re-measure on resize.
 *
 * The two exceptions are the row's own offsets, which Swiper needs as numbers
 * — see measure() below. Both are handed over as functions, which Swiper
 * re-resolves on every update, so they follow a resize on their own.
 */
export default function RamzCarousel({ swiper, on, extendParams }) {
    extendParams({
        ramzCarouselEffect: {
            // Below this much open, the copy is still at zero. High enough that
            // a card only speaks once it has clearly won.
            copyAt: 0.55,
            // Fraction of the slide transition the copy waits out before it
            // starts. At the default 900ms speed this is ~150ms.
            copyDelay: 0.17,
        },
    });

    const clamp01 = (v) => Math.min(1, Math.max(0, v));
    // Ease the width itself, not just the snap: a linear open reads mechanical
    // under the finger.
    const smooth = (t) => t * t * (3 - 2 * t);

    const isEffect = () => swiper.params.effect === 'ramz-carousel';

    /**
     * Where the row starts. The <swiper-container> spans the full width so a
     * card leaving to the left is clipped by the screen edge rather than by a
     * padding — but the first card still has to line up with the container.
     *
     * Published as a custom property the cards add to their own transform,
     * rather than as slidesOffsetBefore: that param is applied by shifting the
     * translate, which skews every slide's progress by the offset and is not
     * honoured by loop mode at all. Kept out of Swiper's model, the same
     * measurement works looping and not.
     *
     * hostEl, not el: with swiper-element `el` is the .swiper div inside the
     * shadow root, and closest() does not cross a shadow boundary.
     */
    const publishOffset = () => {
        const host = swiper.hostEl || swiper.el;
        const marker = host.closest('[data-slider-block]')?.querySelector('[data-slider-gutter]');
        const offset = marker
            ? Math.max(0, Math.round(marker.getBoundingClientRect().left - host.getBoundingClientRect().left))
            : 0;

        host.style.setProperty('--carousel-offset', `${offset}px`);
    };

    /**
     * Where it ends, when it does not loop. Swiper measures the row at its
     * RESTING widths, so it runs out of translate long before it runs out of
     * cards — on a wide screen the slider stopped a card or two in and would go
     * no further. This buys back exactly enough room for the last card to take
     * its turn open at the container edge.
     *
     * A number, deliberately, even though Swiper accepts a function here: it
     * tests the param with !!, so a function that returns 0 still reads as an
     * offset and quietly changes how loop mode fixes itself up. Recomputed
     * before each resize instead.
     */
    const tailOffset = () => {
        if (swiper.params.loop) return 0;

        const host = swiper.hostEl || swiper.el;
        const slide = host.querySelector('swiper-slide, .swiper-slide');
        const resting = slide ? slide.offsetWidth : 0;
        if (!resting) return 0;

        const stride = resting + (parseFloat(swiper.params.spaceBetween) || 0);

        // A starting estimate. How Swiper turns this into a translate limit
        // depends on whether the row is longer than the viewport, so it is
        // corrected against the real numbers in syncTail() below.
        return Math.max(0, host.clientWidth - stride);
    };

    /**
     * The gap between cards, taken from CSS so it sits with the widths it has
     * to agree with rather than being repeated in the template.
     *
     * Handed to Swiper as spaceBetween rather than drawn as a margin: Swiper
     * has to know about it, or its own grid and the row you can see disagree by
     * a gap per card. Must be a plain px value — a custom property is read back
     * as the text it was written as, so a clamp() would not parse.
     */
    const gapFromCss = () => {
        const host = swiper.hostEl || swiper.el;
        const token = getComputedStyle(host).getPropertyValue('--slider-carousel-gap');
        const gap = parseFloat(token);

        return Number.isFinite(gap) ? gap : null;
    };

    /**
     * Pins the end of the row to the last card's own snap.
     *
     * Without this the slider stops with translate to spare and the row can be
     * dragged a screen further into empty space, or — with too small an offset
     * — stops before the last card has had its turn. Reading Swiper's own grid
     * back and correcting is version-proof in a way that reimplementing its
     * limit arithmetic is not; the offset moves one to one with the limit, so
     * it settles in a single pass.
     */
    let syncing = false;
    const syncTail = () => {
        if (!isEffect() || swiper.params.loop || syncing || swiper.destroyed) return;

        const grid = swiper.slidesGrid;
        if (!grid || grid.length < 2) return;

        const wanted = grid[grid.length - 1];
        const delta = wanted - -swiper.maxTranslate();
        if (Math.abs(delta) < 1) return;

        const next = Math.max(0, (swiper.params.slidesOffsetAfter || 0) + delta);
        if (next === swiper.params.slidesOffsetAfter) return;

        swiper.params.slidesOffsetAfter = next;
        syncing = true;
        swiper.update();
        syncing = false;
    };

    on('beforeInit', () => {
        if (!isEffect()) return;

        swiper.classNames.push(`${swiper.params.containerModifierClass}ramz-carousel`);

        const gap = gapFromCss();

        const overwriteParams = {
            watchSlidesProgress: true,
            slidesOffsetAfter: tailOffset(),
            ...(gap === null ? {} : { spaceBetween: gap }),
        };

        Object.assign(swiper.params, overwriteParams);
        Object.assign(swiper.originalParams, overwriteParams);
    });

    on('init', () => {
        if (!isEffect()) return;

        publishOffset();
        syncTail();
    });

    on('resize', () => {
        if (!isEffect()) return;

        publishOffset();

        const gap = gapFromCss();
        if (gap !== null) swiper.params.spaceBetween = gap;

        swiper.params.slidesOffsetAfter = tailOffset();
        syncTail();
    });

    on('progress', () => {
        if (!isEffect()) return;

        const { copyAt } = swiper.params.ramzCarouselEffect;
        const slides = swiper.slides;
        if (!slides.length) return;

        // slideEl.progress is 0 for the open slide and counts up either side.
        // Swiper recomputes it from the incoming translate just before this
        // fires — swiper.translate is still the previous value at that moment,
        // which is why nothing here reads it.
        const direction = swiper.rtlTranslate ? -1 : 1;

        // Accumulated in DOM order, which is also the order the slides are laid
        // out in — including the ones loop mode moves around.
        let shift = 0;

        for (let i = 0; i < slides.length; i += 1) {
            const slideEl = slides[i];
            const distance = slideEl.progress;
            const open = smooth(clamp01(1 - Math.abs(distance)));

            slideEl.style.setProperty('--carousel-open', open.toFixed(4));
            slideEl.style.setProperty('--carousel-shift', (shift * direction).toFixed(4));
            slideEl.style.setProperty(
                '--carousel-lead',
                clamp01((open - copyAt) / (1 - copyAt)).toFixed(4),
            );

            shift += open;
        }
    });

    on('setTransition', (s, duration) => {
        if (!isEffect()) return;

        const { copyDelay } = swiper.params.ramzCarouselEffect;

        for (let i = 0; i < swiper.slides.length; i += 1) {
            const slideEl = swiper.slides[i];

            // Zero while a finger is down — that is what keeps the transition
            // tied to the drag — and the real speed once Swiper snaps.
            slideEl.style.setProperty('--carousel-duration', `${duration}ms`);
            slideEl.style.setProperty('--carousel-copy-delay', `${Math.round(duration * copyDelay)}ms`);
        }
    });
}
