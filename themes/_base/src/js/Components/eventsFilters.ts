/**
 * Progressive enhancement for the events filter form.
 *
 * The form is a plain GET and works fully without this. All this adds is
 * submitting immediately when a preset is chosen, since a radio is a complete
 * choice and making someone press Apply afterwards is a pointless second step.
 *
 * The date inputs are deliberately NOT auto-submitted. A range isn't complete
 * until both ends are set, so firing on the first change would reload the page
 * with a half-specified filter and throw away what the person was doing.
 *
 * Auto-submit costs a full page load, which drops keyboard focus back to the
 * top of the document — for a screen reader or keyboard user that's worse than
 * the extra click it saves. So the control that triggered the reload is
 * recorded and refocused on the way back, keeping the user where they were.
 */
const FOCUS_KEY = 'events-filters:refocus';

customElements.define('events-filters', class extends HTMLElement {
    form: HTMLFormElement | null;

    constructor() {
        super();
        this.form = this.querySelector('form');
    }

    connectedCallback(): void {
        if (!this.form) return;

        this.form.setAttribute('data-enhanced', '');
        this.form.addEventListener('change', this);

        this.restoreFocus();
    }

    disconnectedCallback(): void {
        this.form?.removeEventListener('change', this);
    }

    handleEvent(event: Event): void {
        const target = event.target as HTMLElement | null;

        // Presets only — see the note above on why dates are excluded.
        if (!(target instanceof HTMLInputElement) || target.type !== 'radio') return;

        try {
            sessionStorage.setItem(FOCUS_KEY, target.id);
        } catch {
            // Private browsing or a storage-blocked context. Losing focus
            // restoration is a smaller cost than not filtering at all.
        }

        this.form?.submit();
    }

    /**
     * Move focus back to whichever control caused the reload. Read-then-clear,
     * so a later ordinary visit to the page doesn't steal focus from the top
     * of the document.
     */
    restoreFocus(): void {
        let id: string | null = null;

        try {
            id = sessionStorage.getItem(FOCUS_KEY);
            sessionStorage.removeItem(FOCUS_KEY);
        } catch {
            return;
        }

        if (!id) return;

        const control = this.querySelector<HTMLElement>(`#${CSS.escape(id)}`);
        control?.focus();
    }
});
