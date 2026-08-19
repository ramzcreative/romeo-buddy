/**
 * Slant seam
 *
 * Companion to the Material effect, used only by the slant slider layout.
 * Material stays untouched — this runs after it and adjusts the result.
 *
 * ── What it fixes ──
 * The slant is a clip-path parallelogram on .swiper-material-wrapper. The clip
 * removes --slant-offset from each box's top-left and bottom-right, so two
 * boxes that merely TOUCH cannot have touching diagonals: a thin triangular
 * wedge of page background shows between every pair of panels. Measuring the
 * boxes will not reveal it — they are flush; it is the visible parallelograms
 * inside them that are not.
 *
 * The fix is to make each box one --slant-offset wider and pull it back by half
 * of that, so consecutive diagonals land on each other exactly.
 *
 * ── Why this is JS and not CSS ──
 * Material writes `width` and `transform` INLINE onto .swiper-material-wrapper
 * (see effect-material.esm.js). Inline styles beat any stylesheet rule, so
 * every CSS attempt at widening that box is ignored — and `width: calc(100% +
 * var(...))` is worse than ignored: it is dropped, width falls back to auto,
 * and the box collapses to zero because all of its children are absolutely
 * positioned. So the adjustment has to happen after Material, on the same
 * event, in the same units it wrote.
 *
 * Scoped to containers inside .slider-slants, so the unslanted Material slider
 * (layouts/sliders/hero.twig) is never touched.
 */
export default function SlantSeam({ swiper, on }) {
  // swiper.el is the .swiper div INSIDE Swiper Element's shadow root, and
  // closest() does not cross a shadow boundary — so resolving the layout
  // wrapper from it always returns null and this module silently does nothing.
  // hostEl is the <swiper-container> in the light DOM; the getRootNode().host
  // fallback covers builds that do not expose it.
  const root = () => {
    const host =
      swiper.hostEl ||
      (swiper.el && swiper.el.getRootNode && swiper.el.getRootNode().host) ||
      swiper.el;
    return host && host.closest ? host.closest('.slider-slants') : null;
  };

  const apply = () => {
    if (swiper.params.effect !== 'material') return;

    const host = root();
    if (!host) return;

    const height = swiper.height;
    if (!height) return;

    // Resolved here rather than read from CSS: a custom property holding a
    // calc() comes back as its unresolved token stream, which is not a number.
    // Publishing the resolved pixel value means the stylesheet and this module
    // are working from one figure that cannot drift.
    const angle = parseFloat(host.dataset.slantAngle || '10');
    const slant = height * Math.tan((angle * Math.PI) / 180);
    host.style.setProperty('--slant-offset', `${slant}px`);

    swiper.slides.forEach((slideEl) => {
      const panel = slideEl.querySelector('.swiper-material-wrapper');
      if (!panel) return;

      // Material has already set these inline; read what it decided rather
      // than recomputing it, so this stays correct if the vendor changes how
      // the width is derived.
      const width = panel.getBoundingClientRect().width;
      if (!width) return;

      panel.style.width = `${width + slant}px`;

      const current = panel.style.transform || '';
      const match = current.match(/translate3d\((-?[\d.]+)px/);
      const x = match ? parseFloat(match[1]) : 0;
      const shifted = x - slant / 2;

      panel.style.transform = match
        ? current.replace(/translate3d\((-?[\d.]+)px/, `translate3d(${shifted}px`)
        : `translate3d(${shifted}px, 0, 0)`;
    });
  };

  // After Material's own handlers, which are registered before this module.
  on('setTranslate', apply);
  on('resize', apply);
  on('observerUpdate', apply);
}
