const STORAGE_KEY = 'stables:adminBarExpanded';

/**
 * Progressive enhancement of _partials/adminBar.twig. Both the collapsed
 * preview bar and the expanded full bar are always in the DOM (server
 * rendered) — this just toggles `hidden`/`inert` between them, and between
 * the two secondary panels (the Site & Page Theme popover, the SEO
 * snippet card), remembering the expanded/collapsed choice per browser via
 * localStorage so it survives navigation.
 */
customElements.define('admin-bar', class extends HTMLElement {
	toggleWrapper: HTMLElement | null;
	expandBtn: HTMLButtonElement | null;
	fullBar: HTMLElement | null;
	collapseBtn: HTMLButtonElement | null;
	popoverTrigger: HTMLButtonElement | null;
	popoverPanel: HTMLElement | null;
	seoTrigger: HTMLButtonElement | null;
	seoCard: HTMLElement | null = null;

	constructor() {
		super();

		// The collapsed pill: toggleWrapper is the whole visual pill (hidden
		// as a unit once expanded); expandBtn is the invisible hit-target
		// that fills it, sitting BEHIND the real "Edit Entry" <a> — see the
		// comment in adminBar.twig above this markup for why they are
		// siblings, not one nested inside the other.
		this.toggleWrapper = this.querySelector<HTMLElement>('[data-admin-bar-toggle]');
		this.expandBtn = this.querySelector<HTMLButtonElement>('[data-admin-bar-expand]');
		this.fullBar = this.querySelector<HTMLElement>('[data-admin-bar-full]');
		this.collapseBtn = this.querySelector<HTMLButtonElement>('[data-admin-bar-collapse]');
		this.popoverTrigger = this.querySelector<HTMLButtonElement>('[data-admin-bar-popover-trigger]');
		this.popoverPanel = this.querySelector<HTMLElement>('[data-admin-bar-popover]');
		this.seoTrigger = this.querySelector<HTMLButtonElement>('[data-admin-bar-seo-trigger]');
	}

	connectedCallback(): void {
		this.addEventListener('click', this);
		document.addEventListener('keydown', this);

		this.setExpanded(this.readStoredExpanded(), false);

		// A page-theme preview select submits itself — there's no separate
		// "apply" button in the mockup, matching the site theme preview's
		// own one-click Activate pattern in _partials/themePreview.twig.
		const select = this.querySelector<HTMLSelectElement>('[data-admin-bar-theme-select]');
		select?.addEventListener('change', () => select.form?.requestSubmit());
	}

	disconnectedCallback(): void {
		document.removeEventListener('keydown', this);
	}

	handleEvent(event: Event): void {
		if (event.type === 'keydown') {
			if ((event as KeyboardEvent).key === 'Escape') {
				this.closeSecondaryPanels();
			}
			return;
		}

		const target = event.target as HTMLElement;

		if (target.closest('[data-admin-bar-expand]')) {
			this.setExpanded(true, true);
			return;
		}

		if (target.closest('[data-admin-bar-collapse]')) {
			this.setExpanded(false, true);
			return;
		}

		if (target.closest('[data-admin-bar-popover-trigger]')) {
			this.toggleSitePopover();
			return;
		}

		if (target.closest('[data-admin-bar-seo-trigger]')) {
			this.toggleSeoCard();
			return;
		}

		if (!target.closest('[data-admin-bar-popover]') && !target.closest('[data-admin-bar-seo-card]')) {
			this.closeSecondaryPanels();
		}
	}

	setExpanded(expanded: boolean, persist: boolean): void {
		if (this.toggleWrapper) {
			this.toggleWrapper.hidden = expanded;
		}

		if (this.expandBtn) {
			this.expandBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
		}

		if (this.fullBar) {
			this.fullBar.hidden = !expanded;
			this.setInert(this.fullBar, !expanded);
		}

		if (!expanded) {
			this.closeSecondaryPanels();
		}

		if (persist) {
			this.storeExpanded(expanded);
		}
	}

	toggleSitePopover(): void {
		if (!this.popoverPanel || !this.popoverTrigger) return;

		const opening = !!this.popoverPanel.hidden;
		this.closeSeoCard();
		this.setPanelOpen(this.popoverPanel, this.popoverTrigger, opening);
	}

	toggleSeoCard(): void {
		if (!this.seoTrigger) return;

		if (!this.seoCard) {
			this.seoCard = this.buildSeoCard();
			// Appended to `this` (the <admin-bar> host), not next to the
			// trigger button — the button lives inside .admin-bar__full,
			// which sets overflow-x: auto (forcing overflow-y to clip too,
			// per spec) so it can scroll on narrow viewports. A card
			// inserted in there would render with a real layout position
			// but be clipped invisible, same coordinate space problem the
			// popover avoids by being a direct child of <admin-bar> already.
			this.append(this.seoCard);
		}

		const opening = !!this.seoCard.hidden;
		this.closePopover();
		this.setPanelOpen(this.seoCard, this.seoTrigger, opening);
	}

	setPanelOpen(panel: HTMLElement, trigger: HTMLButtonElement, open: boolean): void {
		panel.hidden = !open;
		this.setInert(panel, !open);
		trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
	}

	closePopover(): void {
		if (this.popoverPanel && this.popoverTrigger && !this.popoverPanel.hidden) {
			this.setPanelOpen(this.popoverPanel, this.popoverTrigger, false);
		}
	}

	closeSeoCard(): void {
		if (this.seoCard && this.seoTrigger && !this.seoCard.hidden) {
			this.setPanelOpen(this.seoCard, this.seoTrigger, false);
		}
	}

	closeSecondaryPanels(): void {
		this.closePopover();
		this.closeSeoCard();
	}

	setInert(element: HTMLElement, isInert: boolean): void {
		if (isInert) {
			element.setAttribute('inert', '');
		} else {
			element.removeAttribute('inert');
		}
	}

	/**
	 * Reads the ACTUAL rendered <title>/meta description rather than any
	 * CMS field — this is what a search engine sees regardless of which
	 * field or fallback chain produced it (see modules/seo's
	 * renderSeoTags()), so the preview can't drift from reality.
	 */
	buildSeoCard(): HTMLElement {
		const card = document.createElement('div');
		card.className = 'admin-bar__seo-card';
		card.setAttribute('data-admin-bar-seo-card', '');
		card.hidden = true;
		card.setAttribute('inert', '');

		const descriptionTag = document.querySelector('meta[name="description"]');
		const description = descriptionTag instanceof HTMLMetaElement ? descriptionTag.content : '';

		const title = document.createElement('div');
		title.className = 'admin-bar__seo-title';
		title.textContent = document.title;

		const url = document.createElement('div');
		url.className = 'admin-bar__seo-url';
		url.textContent = window.location.href;

		const desc = document.createElement('div');
		desc.className = 'admin-bar__seo-desc';
		desc.textContent = description || 'No meta description set.';

		card.append(title, url, desc);

		return card;
	}

	readStoredExpanded(): boolean {
		try {
			return window.localStorage.getItem(STORAGE_KEY) === 'true';
		} catch {
			return false;
		}
	}

	storeExpanded(expanded: boolean): void {
		try {
			window.localStorage.setItem(STORAGE_KEY, expanded ? 'true' : 'false');
		} catch {
			// Private browsing / storage disabled — the bar still works, it just won't remember.
		}
	}
});

export {};
