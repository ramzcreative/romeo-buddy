// Handle click events for modals that have external buttons.
//
// DELEGATED, not bound per element. This used to do
// `document.querySelectorAll('[data-toggle-modal]').forEach(el => el.addEventListener(...))`,
// which captures the toggles that exist at load and binds a listener to each.
// Anything added to the DOM afterwards gets nothing — and cloneNode does not
// copy event listeners, so a toggle inside a cloned node is inert.
//
// That is exactly what Swiper's loop mode does: it deep-clones slides to fake
// the wrap-around, after this module has already run. The cloned "+" buttons
// looked identical and did nothing at all. Confirmed rather than assumed: with
// the old per-element binding, clicking a cloned button fired no listener.
//
// Note it was NOT the duplicated id that broke — getElementById returns the
// first match in document order and a clone's content is identical, so the
// right panel would have opened if the click had ever been heard.
//
// One listener on the document fixes it permanently: clones, slides Swiper
// rebuilds on resize, and anything else injected later all work, because the
// lookup happens at click time rather than at load.
document.addEventListener('click', (event) => {
  // closest(), because a click can land on a nested child (e.g. an <img> inside
  // the toggle button) which wouldn't itself carry the attribute. This replaces
  // the old currentTarget read, which relied on the listener being on the
  // button itself.
  const modalToggleEl = event.target.closest('[data-toggle-modal]');

  if (!modalToggleEl) {
    return;
  }

  // No stopPropagation() any more, and none is needed. The old per-element
  // listener used it to keep the click off ancestors; delegated from document,
  // the click has already reached every ancestor by the time we see it, so the
  // call would only have blocked window-level listeners. Checked before
  // dropping it: nothing in themes/_base/src/js listens for clicks on document,
  // body or window except this handler, and accordion.ts — which listens on
  // itself and ignores anything that isn't an [accordion-trigger].

  const id = modalToggleEl.getAttribute('data-toggle-modal');
  // Get modal.
  const modal = document.getElementById(id);

  // Modal exists?
  if (modal) {
    // Get flag.
    const bool = modal.getAttribute('active') === String(true);

    // Set flag.
    modal.setAttribute('active', !bool);
  }
});

//move required modals outside of the main content area
const modalPopups = document.querySelectorAll('[data-modal-move]');
modalPopups.forEach((modalPopupEl) => {
    // Get the target container
    const targetContainer = document.getElementById('modal-holder');

    // Move the element
    if (targetContainer) {
        targetContainer.appendChild(modalPopupEl);
    }
});