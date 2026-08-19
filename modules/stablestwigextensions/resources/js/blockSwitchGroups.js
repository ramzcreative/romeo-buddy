/**
 * Restricts the entry type dropdown to blocks that are safe to switch between.
 *
 * Switching a block's type in the CP destroys the fields the new type doesn't
 * have — the form posts only the current type's fields, so the save rewrites
 * content from that post. Recoverable by discarding changes, but not obviously
 * enough to leave as a trap.
 *
 * This has to be JavaScript rather than CSS, unlike the field visibility rules
 * next to it. Garnish appends an open disclosure menu to document.body, so the
 * menu is not a descendant of the form being edited and no selector can scope
 * it to the block's type. Nothing else about the markup gives it away.
 *
 * Groups come from config/blockfields.php, keyed by entry type id. A type in no
 * group is left alone, keeping Craft's default behaviour.
 */
(function () {
    const groups = window.stablesSwitchGroups || {};

    if (!Object.keys(groups).length) {
        return;
    }

    /** The menu Garnish just opened for this trigger, wherever it moved it to. */
    function menuFor(button) {
        const id = button.getAttribute('aria-controls');
        return id ? document.getElementById(id) : null;
    }

    function currentTypeId(button) {
        const form = button.closest('form');
        const input = form && form.querySelector('[id$="-entryType-input"]');
        return input ? input.value : null;
    }

    function filter(button) {
        const menu = menuFor(button);
        if (!menu) {
            return;
        }

        const allowed = groups[currentTypeId(button)];
        if (!allowed) {
            return;
        }

        menu.querySelectorAll('[data-value]').forEach((option) => {
            // Hide the list item rather than the control, so the menu doesn't
            // keep a row of empty gaps where the options used to be.
            const row = option.closest('li') || option;
            row.hidden = !allowed.includes(String(option.dataset.value));
        });
    }

    let pendingButton = null;

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[id$="-entryType-button"]');
        pendingButton = button || null;

        // The menu may already exist from a previous open, in which case it's
        // re-shown rather than re-inserted and the observer never fires.
        if (button) {
            filter(button);
        }
    });

    // Garnish builds the menu lazily and appends it to the body, so the first
    // open has nothing to filter at click time. Waiting a frame or two works
    // but shows the full list first — a visible blink, and long enough for
    // someone to click the option you're about to hide. A MutationObserver
    // callback runs as a microtask, before the browser paints, so the menu is
    // filtered by the time it's ever visible.
    new MutationObserver((records) => {
        if (!pendingButton) {
            return;
        }

        for (const record of records) {
            for (const node of record.addedNodes) {
                if (node.nodeType === 1 && node.classList.contains('menu')) {
                    filter(pendingButton);
                    return;
                }
            }
        }
    }).observe(document.body, { childList: true });
})();
