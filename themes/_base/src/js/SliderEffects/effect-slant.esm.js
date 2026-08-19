/**
 * Slant effect
 *
 * Angled panels where the active slide expands out from underneath the panel
 * to its left, rather than sliding alongside it. Written for this boilerplate
 * against the Apple-TV-carousel direction; the reference implementation is not
 * followed, only its feel.
 *
 * ── Why clip-path and not skew ──
 * The usual way to build this is skew(-10deg) on the panel with a
 * counter-skew(10deg) on an oversized image. That is what the reference does,
 * and it has a bug: the panel skews about its own centre while the image
 * counters about ITS centre, which sits somewhere else entirely. The two
 * origins only cancel at one particular panel width, so as the width animates
 * the image drifts, and the shear drags its top edge sideways by
 * height * tan(angle) — about 57px at a 650px viewport. On a wide screen the
 * oversized image has enough slack to hide it; at narrow widths it does not,
 * and a triangular gap opens in a top corner. It is also why the same approach
 * breaks at arbitrary breakpoints: the slack is a fixed multiple of vw, and
 * the shear is a multiple of the container HEIGHT.
 *
 * So there is no skew here. The slant is a clip-path parallelogram, and the
 * image is a plain `width:100%; height:100%; object-fit:cover` inside the box
 * being clipped. A box always covers its own clip path, at every width and
 * every breakpoint, so the gap is not a bug that is fixed — it is a bug that
 * cannot be expressed.
 *
 * ── Layout ──
 * Panels are positioned by this effect, not by Swiper, so they can change
 * WIDTH as well as position — the widening is what sells the "expanding from
 * underneath" read, and it is the one thing a transform-only effect cannot do.
 * Each panel's box is `slant` wider than its visible parallelogram, and panels
 * advance by the visible width, so consecutive slanted edges interlock exactly
 * with no seam.
 *
 * Everything derives from each slide's fractional `progress`, so a drag is
 * continuous rather than snapping between states.
 */
export default function EffectSlant({ swiper, on, extendParams }) {
  extendParams({
    slantEffect: {
      // Degrees off vertical for the panel edges.
      angle: 10,
      // Panel widths as a fraction of the container.
      collapsed: 0.26,
      expanded: 0.62,
      // Gap between panels, in px.
      gap: 6,
      // Where the active panel's left edge sits, as a fraction of the
      // container. Keeps a sliver of the previous panel on screen.
      offset: 0.12,
      // How much the image counter-moves as its panel grows, 0..1. 0 re-crops
      // from the centre; 1 pins the image so the panel opens across it.
      parallax: 0.6,
    },
  });

  const clamp = (v, min, max) => Math.max(min, Math.min(max, v));

  on('beforeInit', () => {
    if (swiper.params.effect !== 'slant') return;

    swiper.classNames.push(`${swiper.params.containerModifierClass}slant`);

    const overwriteParams = {
      watchSlidesProgress: true,
      // We place every panel ourselves, so Swiper must not translate the
      // wrapper or try to size the slides.
      virtualTranslate: true,
      // The slides stay in normal flow at a fixed width. That is not cosmetic:
      // Swiper derives each slide's `progress` from its LAYOUT OFFSET, so
      // absolutely-positioning them makes every slide report the same progress
      // and the whole effect flattens. The in-flow slide is Swiper's ruler; the
      // panel inside it is what actually moves and grows.
      slidesPerView: 'auto',
      spaceBetween: 0,
    };

    Object.assign(swiper.params, overwriteParams);
    Object.assign(swiper.originalParams, overwriteParams);
  });

  const layout = () => {
    if (swiper.params.effect !== 'slant') return;

    const { angle, collapsed, expanded, gap, offset, parallax } =
      swiper.params.slantEffect;

    const containerW = swiper.width;
    const containerH = swiper.height;
    if (!containerW || !containerH) return;

    const slant = containerH * Math.tan((angle * Math.PI) / 180);
    const collapsedW = collapsed * containerW;
    const expandedW = expanded * containerW;

    // Expose the slant to CSS so the clip path and the container padding stay
    // in step with whatever the effect actually computed.
    swiper.el.style.setProperty('--slant-offset', `${slant}px`);

    const slides = swiper.slides;
    const widths = [];

    // 1. Width per panel, from how close it is to being active.
    for (let i = 0; i < slides.length; i += 1) {
      const t = clamp(1 - Math.abs(slides[i].progress), 0, 1);
      widths[i] = collapsedW + (expandedW - collapsedW) * t;
    }

    // 2. Lay them out left to right, advancing by the visible width so the
    //    slanted edges interlock.
    const xs = [];
    let x = 0;
    for (let i = 0; i < slides.length; i += 1) {
      xs[i] = x;
      x += widths[i] + gap;
    }

    // 3. Shift the whole run so the active panel lands on its mark. Uses the
    //    fractional position between the two nearest slides, so this tracks a
    //    drag instead of jumping at the halfway point.
    let ai = 0;
    for (let i = 1; i < slides.length; i += 1) {
      if (Math.abs(slides[i].progress) < Math.abs(slides[ai].progress)) ai = i;
    }
    // Interpolate along the gap to the neighbour the slider is heading toward,
    // so a drag tracks continuously instead of snapping at the halfway point.
    const p = slides[ai].progress;
    const dir = p > 0 ? 1 : -1;
    const neighbour = xs[ai + dir] ?? xs[ai];
    const anchor = xs[ai] + (neighbour - xs[ai]) * Math.abs(clamp(p, -1, 1));
    const shift = offset * containerW - anchor;

    for (let i = 0; i < slides.length; i += 1) {
      const slideEl = slides[i];
      const panel = slideEl.querySelector('.slider-slants__panel');
      if (!panel) continue;
      const w = widths[i];
      const t = clamp(1 - Math.abs(slideEl.progress), 0, 1);

      // The box is wider than the visible parallelogram by the slant, so the
      // clip path has material to cut from at both ends.
      panel.style.width = `${w + slant}px`;
      panel.style.transform = `translate3d(${xs[i] + shift}px, 0, 0)`;

      // Panels further left sit on top. This is the whole trick: the outgoing
      // panel covers the growing edge of the incoming one, so the active slide
      // reads as expanding out from underneath its neighbour rather than
      // sliding along beside it.
      panel.style.zIndex = Math.round(1000 - slideEl.progress * 100);

      const media = panel.querySelector('.slider-slants__media');
      if (media) {
        // Counter-move so the image stays put while the panel opens across it.
        const grown = w - collapsedW;
        media.style.transform = `translate3d(${-grown * parallax * (1 - t)}px, 0, 0)`;
      }
    }
  };

  on('setTranslate', layout);
  on('progress', layout);
  on('resize', layout);
  on('observerUpdate', layout);

  // virtualTranslate means Swiper never transforms the wrapper, so no
  // transitionend ever fires on it and swiper.animating stays true forever.
  // Swiper drops any slideNext/slidePrev while animating, so without this the
  // slider advances exactly once and then goes dead — which looks like the
  // effect working and the buttons being broken. Ending the transition
  // ourselves is what the built-in virtualTranslate effects rely on Swiper
  // doing for them off the wrapper.
  let endTimer = null;

  on('setTransition', (s, duration) => {
    if (swiper.params.effect !== 'slant') return;

    clearTimeout(endTimer);
    if (duration) {
      endTimer = setTimeout(() => {
        swiper.animating = false;
        swiper.emit('transitionEnd');
      }, duration);
    } else {
      swiper.animating = false;
    }

    swiper.slides.forEach((slideEl) => {
      const panel = slideEl.querySelector('.slider-slants__panel');
      if (!panel) return;
      panel.style.transitionDuration = `${duration}ms`;
      const media = panel.querySelector('.slider-slants__media');
      if (media) media.style.transitionDuration = `${duration}ms`;
    });
  });
}
