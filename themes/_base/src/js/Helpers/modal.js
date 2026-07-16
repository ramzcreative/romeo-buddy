//handle click events for modals that have external buttons
const modalToggles = document.querySelectorAll('[data-toggle-modal]');
modalToggles.forEach((modalToggleEl) => {
  modalToggleEl.addEventListener('click', (event) =>{
    event.stopPropagation();

    // currentTarget (the element the listener is attached to), not target —
    // a click can land on a nested child (e.g. an <img> inside the toggle
    // button), which wouldn't itself carry the [data-toggle-modal] attribute.
    const id = event.currentTarget.getAttribute('data-toggle-modal');
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