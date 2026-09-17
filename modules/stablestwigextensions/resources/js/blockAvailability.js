/**
 * Hides blocks the active theme doesn't offer from cards-view Add menus (docs/theme-designer-blocks-spec.md §4.4).
 *
 * Blocks-view fields are filtered on the server (Matrix::EVENT_DEFINE_ENTRY_TYPES); cards view builds its menus from
 * the field's saved types, so it's done here. Craft's items carry no type id, so rows are matched by the label the
 * server computed the same way Craft does. Every menu is covered: the combined narrow-width one and one per group.
 * A group left with nothing is hidden too, and its plus icon moves to the first group still showing.
 *
 * Slideout responses inject this script again, so it runs once per page and marks each field it has handled.
 */
(function () {
    const labelsByField = window.stablesUnofferedBlocks || {};

    if (!Object.keys(labelsByField).length || !window.jQuery || window.stablesBlockAvailability) {
        return;
    }

    window.stablesBlockAvailability = true;

    const $ = window.jQuery;
    const handled = new WeakSet();

    function labelOf(item) {
        const label = item.querySelector('.menu-item-label');
        return (label || item).textContent.trim();
    }

    function filterManager(manager, labels) {
        if (handled.has(manager) || !manager.$btnContainer) {
            return;
        }

        const buttons = manager.$btnContainer.find('.menubtn').toArray();
        const menus = buttons.map((button) => $(button).data('disclosureMenu'));

        // Not built yet: try again on the next mutation.
        if (!buttons.length || menus.some((menu) => !menu)) {
            return;
        }

        handled.add(manager);

        buttons.forEach((button, i) => {
            const menu = menus[i];

            menu.$container.find('.menu-item').each((_, item) => {
                if (labels.includes(labelOf(item))) {
                    menu.hideItem(item);
                }
            });

            if (!menu.$container.find('li:not(.hidden)').length) {
                button.classList.add('hidden');
            }
        });

        const groupButtons = manager.$btnContainer.find('.btngroup .menubtn').toArray();
        const icon = groupButtons.length ? groupButtons[0].querySelector('.cp-icon') : null;
        const firstVisible = groupButtons.find((button) => !button.classList.contains('hidden'));

        if (icon && firstVisible && !firstVisible.contains(icon)) {
            firstVisible.querySelector('.inline-flex')?.prepend(icon);
        }
    }

    function apply() {
        for (const [fieldHandle, labels] of Object.entries(labelsByField)) {
            document.querySelectorAll(`[data-attribute="${fieldHandle}"] .nested-element-cards`).forEach((el) => {
                // The field's own manager, not one belonging to a field nested inside it.
                if (el.closest('[data-attribute]')?.dataset.attribute !== fieldHandle) {
                    return;
                }

                const manager = $(el).data('nestedElementManager');

                if (manager) {
                    filterManager(manager, labels);
                }
            });
        }
    }

    let queued = false;

    new MutationObserver(() => {
        if (!queued) {
            queued = true;
            requestAnimationFrame(() => {
                queued = false;
                apply();
            });
        }
    }).observe(document.body, { childList: true, subtree: true });

    apply();
})();
