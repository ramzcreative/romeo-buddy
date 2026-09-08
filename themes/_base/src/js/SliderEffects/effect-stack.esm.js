/**
 * Stack effect — the incoming slide covers the outgoing one.
 *
 * Written for stables rather than vendored: the built-in effects all move the
 * slide that is leaving (slide translates it, fade dilutes it, creative can
 * hold it but only by translating the incoming one a full slide-height). This
 * one holds the outgoing slide completely still and fully painted, and reveals
 * the incoming slide over the top of it with clip-path. Nothing is ever
 * translated, so the background is never exposed and there is no flash between
 * slides — which is the whole point of it.
 *
 * Reads slide.progress. Note the sign: Swiper gives the slide you have just
 * LEFT a POSITIVE progress and the one arriving a NEGATIVE one — the reverse
 * of what it looks like, and worth stating because inverting it silently
 * clips the wrong slide.
 *
 *   progress <  0   arriving. Clipped from the top by -progress * 100%, so it
 *                   wipes open from the bottom edge as it becomes active
 *   progress >= 0   active, or already covered. Left fully open — it sits
 *                   underneath, and clipping it would animate a shrink that
 *                   the arriving slide is not yet covering
 *
 * z-index falls as progress rises, so the arriving slide is always above the
 * one it covers and the covered one drops away beneath it.
 */
/**
 * With virtualTranslate the wrapper never moves, so its `transitionend` never
 * fires and Swiper's `animating` flag is never cleared — every subsequent
 * slideNext() then returns false and the slider wedges after one move. Swiper
 * ships an internal helper for this; effects outside the package have to carry
 * their own. This watches the slides instead and releases the flag.
 *
 * It listens on ALL slides deliberately: going forward the arriving slide is
 * the one whose clip-path changes, but going back it is the leaving one, so
 * filtering to the active slide would wedge the prev arrow.
 */
function releaseVirtualTransition(swiper, duration) {
  if (!swiper.params.virtualTranslate || duration === 0) return;

  let done = false;

  swiper.slides.forEach((slideEl) => {
    const onEnd = (e) => {
      if (e.target !== slideEl) return;
      slideEl.removeEventListener('transitionend', onEnd);
      if (done || !swiper || swiper.destroyed) return;
      done = true;
      swiper.animating = false;
      swiper.wrapperEl.dispatchEvent(
        new window.CustomEvent('transitionend', { bubbles: true, cancelable: true }),
      );
    };
    slideEl.addEventListener('transitionend', onEnd);
  });
}

export default function StackEffect({ swiper, on, extendParams }) {
  extendParams({
    stackEffect: {
      /** How far the covered slide drifts as it is covered, for a little depth. */
      depth: 0,
    },
  });

  const clamp01 = (n) => Math.max(0, Math.min(1, n));

  on('beforeInit', () => {
    if (swiper.params.effect !== 'stack') return;

    swiper.classNames.push(`${swiper.params.containerModifierClass}stack`);

    if (swiper.isElement && swiper.hostEl) {
      swiper.hostEl.classList.add(`swiper-${swiper.params.direction}`);
    }

    const overwriteParams = {
      watchSlidesProgress: true,
      // Stops the wrapper translating, so the slides genuinely stack instead
      // of sitting in a row. Same thing effect-material does.
      virtualTranslate: !swiper.params.cssMode,
      loopAdditionalSlides: 1,
    };

    Object.assign(swiper.params, overwriteParams);
    Object.assign(swiper.originalParams, overwriteParams);
  });

  on('progress', () => {
    if (swiper.params.effect !== 'stack') return;

    const { depth } = swiper.params.stackEffect;
    const isHorizontal = swiper.isHorizontal();

    for (let i = 0; i < swiper.slides.length; i += 1) {
      const slideEl = swiper.slides[i];
      const progress = slideEl.progress;

      const clipTop = clamp01(-progress) * 100;
      slideEl.style.clipPath = `inset(${clipTop}% 0 0 0)`;
      slideEl.style.zIndex = Math.round(100 - progress * 10);

      // Slides are still laid out end to end even with virtualTranslate, so
      // each one is pulled back over the others by its own offset. Without
      // this they sit side by side and the covered slide is simply off-screen,
      // which shows the page behind it — the exact flash this effect exists to
      // avoid. Swiper's own fade effect does the same thing.
      const offset = -slideEl.swiperSlideOffset;
      // Only the covered side drifts, and only when depth is asked for.
      const drift = depth && progress > 0 ? Math.min(1, progress) * depth : 0;

      slideEl.style.transform = isHorizontal
        ? `translate3d(${offset}px, ${drift}px, 0)`
        : `translate3d(0, ${offset + drift}px, 0)`;
    }
  });

  on('setTransition', (s, duration) => {
    if (swiper.params.effect !== 'stack') return;

    for (let i = 0; i < swiper.slides.length; i += 1) {
      swiper.slides[i].style.transitionDuration = `${duration}ms`;
    }

    releaseVirtualTransition(swiper, duration);
  });
}
