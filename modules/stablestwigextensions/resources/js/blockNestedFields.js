/**
 * Hides fields on a block by where it's nested: `nested.<parent>.hidden` in the block field rules
 * (themes/_base/config/blockfields.json), e.g. a block inside a Columns block's column shows no background fields.
 *
 * The generated CSS beside this handles every rule it can, and stays one level deep on purpose — a selector reaching
 * further also caught same-named fields at other levels. A block two levels down (page → Columns → column → block)
 * has no selector that can tell which block owns its fields, because the column opens in a slideout and the block
 * renders inline inside it. Here, each field's real owner is read from the page instead:
 *
 *   the field's block     its nearest .matrixblock (a field outside any is the slideout's own — never hidden here)
 *   that block's parent   the next .matrixblock up, or, if there is none, the entry open in the slideout around it,
 *                         recognised by one of its layout tab uids (window.stablesNestedFields.slideoutTabs)
 *
 * A field is hidden when that parent has a rule for it. Blocks added, removed or re-rendered, and slideouts opening,
 * are followed with a MutationObserver. Slideout responses inject this script again, so it runs once per page.
 */
(function () {
    const config = window.stablesNestedFields || {};
    const rules = config.rules || {};
    const slideoutTabs = config.slideoutTabs || {};

    if (!Object.keys(rules).length || window.stablesBlockNestedFields) {
        return;
    }

    window.stablesBlockNestedFields = true;

    const HIDDEN = 'stables-nested-hidden';
    const style = document.createElement('style');
    style.textContent = `.${HIDDEN} { display: none !important; }`;
    document.head.appendChild(style);

    function slideoutType(element) {
        const content = element.closest('.so-content');

        if (!content) {
            return null;
        }

        for (const tab of content.querySelectorAll(':scope > [data-layout-tab]')) {
            const type = slideoutTabs[tab.getAttribute('data-layout-tab')];

            if (type) {
                return type;
            }
        }

        return null;
    }

    function parentTypeOf(block) {
        const outer = block.parentElement && block.parentElement.closest('.matrixblock');

        return outer ? outer.getAttribute('data-type') : slideoutType(block);
    }

    function apply() {
        document.querySelectorAll('[data-attribute]').forEach((field) => {
            const block = field.parentElement && field.parentElement.closest('.matrixblock');
            const hidden = block ? rules[parentTypeOf(block)] : null;
            const hide = !!hidden && hidden.includes(field.getAttribute('data-attribute'));

            if (field.classList.contains(HIDDEN) !== hide) {
                field.classList.toggle(HIDDEN, hide);
            }
        });
    }

    let queued = false;

    function schedule() {
        if (queued) {
            return;
        }

        queued = true;
        requestAnimationFrame(() => {
            queued = false;
            apply();
        });
    }

    // Only a block, a slideout or a field arriving or leaving can change an answer. Typing in a rich-text field also
    // produces child-list mutations, on every keystroke, and those must not rescan the page.
    const RELEVANT = '.matrixblock, .so-content, [data-attribute]';
    const relevant = (node) => node.nodeType === 1 && (node.matches(RELEVANT) || node.querySelector(RELEVANT) !== null);

    apply();
    new MutationObserver((mutations) => {
        // Our own class toggles are attribute changes, not child-list ones, so they don't re-trigger this.
        for (const mutation of mutations) {
            if ([...mutation.addedNodes].some(relevant) || [...mutation.removedNodes].some(relevant)) {
                schedule();
                return;
            }
        }
    }).observe(document.body, { childList: true, subtree: true });
})();
