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

      // Material writes width as a PERCENTAGE on every setTranslate. Read that
      // and cache the resolved pixels; on any pass where the inline value is
      // already px it is this module's own output from a previous run, so the
      // cache is what to build from.
      //
      // Reading the rendered width instead compounds: each pass adds the slant
      // to a box that already includes it, so the overlap doubles, then trebles
      // — panels drift off the left edge and leave dead space on the right.
      const inline = panel.style.width || '';
      let base;

      if (inline.endsWith('%')) {
        const pct = parseFloat(inline);
        base = (slideEl.getBoundingClientRect().width * pct) / 100;
        panel.dataset.slantBase = String(base);
      } else {
        base = parseFloat(panel.dataset.slantBase || '0');
      }

      if (!base) return;

      panel.style.width = `${base + slant}px`;

      // Same for the transform: derive from Material's value, never from the
      // one this module last wrote.
      const inlineT = panel.style.transform || '';
      const m = inlineT.match(/translate3d\((-?[\d.]+)px/);
      if (!m) return;

      const written = parseFloat(m[1]);
      const cached = panel.dataset.slantShifted === inlineT;
      const baseX = cached ? parseFloat(panel.dataset.slantBaseX || '0') : written;

      panel.dataset.slantBaseX = String(baseX);
      const shifted = baseX - slant / 2;
      const next = inlineT.replace(/translate3d\((-?[\d.]+)px/, `translate3d(${shifted}px`);
      panel.style.transform = next;
      panel.dataset.slantShifted = next;
    });
  };

  // After Material's own handlers, which are registered before this module.
  // setTranslate only. Material rewrites the width there and nowhere else, so
  // any other hook would run against this module's own output.
  on('setTranslate', apply);
}
